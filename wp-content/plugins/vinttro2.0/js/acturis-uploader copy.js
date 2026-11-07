jQuery(document).ready(function($) {
    let selectedFile = null;
    let reportRunTimestamp = null;

    function logConsole(message, type = 'info') {
        const $console =$('#import-console-log');
        const timestamp = new Date().toLocaleTimeString();
        $console.append(`<div class="console-line ${type}">[${timestamp}] ${message}</div>`);
        $console.scrollTop($console[0].scrollHeight);
    }

    // Drag and Drop Effects
    const $dropZone =$('#acturis-drop-zone');
    $dropZone.on('dragover dragenter', function() {$dropZone.addClass('dragover'); });
    $dropZone.on('dragleave drop', function() {$dropZone.removeClass('dragover'); });

    // Handle File Selection
    $('#acturis-file-input').on('change', function(e) {
        selectedFile = e.target.files[0];
        if (selectedFile) {
            $('.drop-zone-text p').html(`<strong>Selected File:</strong> ${selectedFile.name} (${(selectedFile.size / 1024).toFixed(1)} KB)`);
            $('#btn-start-import').prop('disabled', false);
            logConsole(`Loaded file: ${selectedFile.name}`, 'info');
        }
    });

    // Start Streaming File via PapaParse
    $('#btn-start-import').on('click', function() {
        if (!selectedFile) return;

        const batchSize = parseInt($('#batch-size-select').val(), 10) || 500;
        $('#btn-start-import').prop('disabled', true);
        $('#import-progress-container').slideDown(150);

        logConsole(`Starting PapaParse execution (Batch size: ${batchSize})...`, 'info');

        // Parse file into structured objects
        Papa.parse(selectedFile, {
            header: true,
            skipEmptyLines: true,
            beforeFirstChunk: function(chunk) {
                // 1. Extract report generation timestamp from line 1
                const dateMatch = chunk.match(/Advanced Client Report\s+([\d/]+\s+[\d:]+)/i);
                if (dateMatch) {
                    reportRunTimestamp = dateMatch[1];
                    logConsole(`Acturis Report Export Time: ${reportRunTimestamp}`, 'info');
                }

                // 2. Find the actual CSV header row dynamically
                const headerIndex = chunk.indexOf('"Contact Ref."');
                if (headerIndex !== -1) {
                    return chunk.slice(headerIndex);
                }

                return chunk;
            },
            complete: async function(results) {
                const totalRows = results.data.length;
                logConsole(`CSV parsed successfully. Found ${totalRows} total records.`, 'success');

                if (totalRows === 0) {
                    logConsole('File contains no records to import.', 'error');
                    $('#btn-start-import').prop('disabled', false);
                    return;
                }

                // Chunk records into arrays of size N
                const batches = [];
                for (let i = 0; i < totalRows; i += batchSize) {
                    batches.push(results.data.slice(i, i + batchSize));
                }

                logConsole(`Prepared ${batches.length} batch request(s). Target: ${vinttroImportConfig.apiUrl}`, 'info');

                let processedTotal = 0;
                let totalCreated = 0;
                let totalUpdated = 0;
                let totalFailed = 0;

                // Sequentially POST each batch to vinttro-api
                for (let i = 0; i < batches.length; i++) {
                    const batchData = batches[i];
                    const batchIndex = i + 1;

                    try {
                        const response = await fetch(vinttroImportConfig.apiUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                source: 'acturis',
                                data_type: 'client',
                                report_run_at: reportRunTimestamp,
                                batch_index: batchIndex,
                                total_batches: batches.length,
                                batch: batchData
                            })
                        });

                        const resData = await response.json();

                        if (!response.ok) {
                            throw new Error(resData.error || `HTTP ${response.status}`);
                        }

                        processedTotal += batchData.length;
                        totalCreated += (resData.created || 0);
                        totalUpdated += (resData.updated || 0);
                        totalFailed += (resData.failed || 0);

                        // Batch level log
                        logConsole(
                            `Batch ${batchIndex}/${batches.length}: Created ${resData.created}, Updated ${resData.updated}, Failed ${resData.failed}`,
                            resData.failed > 0 ? 'warning' : 'success'
                        );

                        // Output row-level failures if any
                        if (resData.errors && resData.errors.length > 0) {
                            resData.errors.forEach(err => {
                                logConsole(`↳ [Row ${err.row_index}] Ref: ${err.ref || 'N/A'} - ${err.error}`, 'error');
                            });
                        }

                        // Update UI Metrics & Progress Bar
                        const percent = Math.round((processedTotal / totalRows) * 100);
                        $('#progress-bar-fill').css('width', `${percent}%`);
                        $('#progress-percent').text(`${percent}%`);
                        $('#progress-text').text(`Processed ${processedTotal}/${totalRows} rows...`);

                    } catch (err) {
                        logConsole(`Batch ${batchIndex} request failed: ${err.message}`, 'error');
                    }
                }

                logConsole(`🏁 Import Complete! Total Created: ${totalCreated}, Updated: ${totalUpdated}, Failed: ${totalFailed}`, 'success');
                $('#btn-start-import').prop('disabled', false);
            },
            error: function(err) {
                logConsole(`PapaParse Error: ${err.message}`, 'error');
                $('#btn-start-import').prop('disabled', false);
            }
        });
    });
});