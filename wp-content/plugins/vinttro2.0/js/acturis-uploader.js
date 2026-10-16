jQuery(document).ready(function($) {
    let selectedFile = null;

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

        // Parse entire file into structured objects
        Papa.parse(selectedFile, {
            header: true,
            skipEmptyLines: true,
            complete: async function(results) {
                const totalRows = results.data.length;
                logConsole(`CSV parsed successfully. Found ${totalRows} total records.`, 'success');

                if (totalRows === 0) {
                    logConsole('File contains no records to import.', 'error');
                    return;
                }

                // Chunk records into arrays of size N
                const batches = [];
                for (let i = 0; i < totalRows; i += batchSize) {
                    batches.push(results.data.slice(i, i + batchSize));
                }

                logConsole(`Prepared ${batches.length} sequential HTTP batch requests. Processing...`, 'info');

                let stagedTotal = 0;

                // Sequentially POST each batch to REST API
                for (let i = 0; i < batches.length; i++) {
                    const batchData = batches[i];
                    const batchIndex = i + 1;

                    try {
                        const response = await fetch(vinttroImportConfig.apiUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': vinttroImportConfig.nonce
                            },
                            body: JSON.stringify({
                                batch_index: batchIndex,
                                total_batches: batches.length,
                                batch: batchData
                            })
                        });

                        if (!response.ok) throw new Error(`HTTP Error ${response.status}`);

                        const resData = await response.json();
                        stagedTotal += batchData.length;

                        // Update UI Metrics & Progress Bar
                        const percent = Math.round((stagedTotal / totalRows) * 100);
                        $('#progress-bar-fill').css('width', `${percent}%`);
                        $('#progress-percent').text(`${percent}%`);
                        $('#progress-text').text(`Staging batch ${batchIndex} of ${batches.length} (${stagedTotal}/${totalRows} rows)...`);

                        logConsole(`Batch ${batchIndex}/${batches.length} staged (${batchData.length} records).`, 'success');

                    } catch (err) {
                        logConsole(`Failed to stage Batch ${batchIndex}: ${err.message}`, 'error');
                    }
                }

                logConsole(`✅ Complete! ${stagedTotal} records submitted to visp_data_staging.`, 'success');
                $('#btn-start-import').prop('disabled', false);
            },
            error: function(err) {
                logConsole(`PapaParse Error: ${err.message}`, 'error');
                $('#btn-start-import').prop('disabled', false);
            }
        });
    });
});