// wp-content/plugins/vinttro2.0/js/acturis-uploader.js
jQuery(document).ready(function($) {
    let selectedFile = null;
    let reportRunTimestamp = null;
    let detectedDataType = 'client'; // 'client' or 'risk'

    function logConsole(message, type = 'info') {
        const $console = $('#import-console-log');
        const timestamp = new Date().toLocaleTimeString();
        $console.append(`<div class="console-line ${type}">[${timestamp}] ${message}</div>`);
        $console.scrollTop($console[0].scrollHeight);
    }

    // Drag and Drop Effects
    const $dropZone = $('#acturis-drop-zone');
    $dropZone.on('dragover dragenter', function() { $dropZone.addClass('dragover'); });
    $dropZone.on('dragleave drop', function() { $dropZone.removeClass('dragover'); });

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

        const inputBatchSize = parseInt($('#batch-size-select').val(), 10) || 500;
        $('#btn-start-import').prop('disabled', true);
        $('#import-progress-container').slideDown(150);

        logConsole(`Starting PapaParse execution...`, 'info');

        Papa.parse(selectedFile, {
            header: true,
            skipEmptyLines: true,
            beforeFirstChunk: function(chunk) {
                const lines = chunk.split(/\r\n|\n/);
                const line1 = lines[0] || '';

                // 1. Extract report generation timestamp from line 1
                const dateMatch = line1.match(/(\d{1,2}\/\d{1,2}\/\d{4}(?:\s+\d{1,2}:\d{2}(?::\d{2})?)?)/);
                if (dateMatch) {
                    reportRunTimestamp = dateMatch[1];
                    logConsole(`Acturis Report Export Time: ${reportRunTimestamp}`, 'info');
                }

                // 2. Identify Report Type
                if (/risk/i.test(line1) || chunk.includes('"Product Target"')) {
                    detectedDataType = 'risk';
                    logConsole('Detected Report Structure: Risk & Policy Export', 'info');
                } else {
                    detectedDataType = 'client';
                    logConsole('Detected Report Structure: Client & Contact Export', 'info');
                }

                // 3. Find the actual CSV header row dynamically (Line 6)
                // Look for start of line containing "Product Target" (Risk) or "Contact Ref." / "Client Ref" (Client)
                const headerRegex = /[\r\n]+("Product Target"|"Contact Ref\."|"Client Ref"|"Client ID")/i;
                const match = chunk.match(headerRegex);

                if (match) {
                    const newlineLength = match[0].startsWith('\r\n') ? 2 : 1;
                    const headerStart = match.index + newlineLength;
                    return chunk.slice(headerStart);
                }

                // Fallback: Skip the first 5 metadata lines if regex match fails
                if (lines.length >= 6) {
                    let offset = 0;
                    for (let i = 0; i < 5; i++) {
                        offset = chunk.indexOf('\n', offset) + 1;
                    }
                    if (offset > 0) return chunk.slice(offset);
                }

                return chunk;
            },
            complete: async function(results) {
                const rawRows = results.data;
                const totalRows = rawRows.length;

                if (totalRows === 0) {
                    logConsole('File contains no records to import.', 'error');
                    $('#btn-start-import').prop('disabled', false);
                    return;
                }

                let itemsToBatch = [];
                let batchSize = inputBatchSize;

                // --- PRE-PROCESSING & BATCH PREPARATION ---
                if (detectedDataType === 'risk') {
                    logConsole(`Assembling ${totalRows} risk rows into complete opportunities...`, 'info');
                    itemsToBatch = assembleRiskOpportunities(rawRows);
                    // For risk, batch size should be opportunity count (default 25 opportunities per HTTP POST)
                    batchSize = Math.min(inputBatchSize, 25);
                    logConsole(`Assembled into ${itemsToBatch.length} complete opportunities (no boundary splits).`, 'success');
                } else {
                    // For client, 1 row = 1 contact
                    itemsToBatch = rawRows;
                    logConsole(`Found ${totalRows} client records.`, 'success');
                }

                // Chunk into batches
                const batches = [];
                for (let i = 0; i < itemsToBatch.length; i += batchSize) {
                    batches.push(itemsToBatch.slice(i, i + batchSize));
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
                                data_type: detectedDataType,
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

                        const unitLabel = detectedDataType === 'risk' ? 'opportunities' : 'rows';

                        logConsole(
                            `Batch ${batchIndex}/${batches.length}: Created ${resData.created}, Updated ${resData.updated}, Failed ${resData.failed}`,
                            resData.failed > 0 ? 'warning' : 'success'
                        );

                        if (resData.errors && resData.errors.length > 0) {
                            resData.errors.forEach(err => {
                                logConsole(`↳ [Item ${err.index ?? err.row_index}] ID: ${err.identifier || err.ref || 'N/A'} - ${err.error}`, 'error');
                            });
                        }

                        // Progress metrics
                        const percent = Math.round((processedTotal / itemsToBatch.length) * 100);
                        $('#progress-bar-fill').css('width', `${percent}%`);
                        $('#progress-percent').text(`${percent}%`);
                        $('#progress-text').text(`Processed ${processedTotal}/${itemsToBatch.length} ${unitLabel}...`);

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

    /**
     * Browser-side assembly of unpivoted EAV Risk rows into complete Opportunities
     */
    function assembleRiskOpportunities(rows) {
        const opportunities = {};

        const finKeys = ['Admin Fee', 'Basic Premium', 'Discounted Premium', 'IPT Amount', 'Part Annual Premium', 'Basic Commission', 'Commission Income'];
        const addrKeys = ['Address Line 1', 'Address Line 2', 'Address Line 3', 'City', 'County', 'Postcode', 'Country', 'Full Address with Postcode'];
        const specKeys = ['Vehicle Make', 'Vehicle Model', 'Vehicle Type', 'Door Plan', 'Engine Size CC', 'Fuel Type', 'Transmission Type', 'Production Year', 'ABI Code'];

        for (let i = 0; i < rows.length; i++) {
            const r = rows[i];
            const vRef = (r['Version Ref.'] || '').trim();
            if (!vRef) continue;

            if (!opportunities[vRef]) {
                opportunities[vRef] = {
                    opportunity_id: `OPP-${vRef}`,
                    version_ref: vRef,
                    product_target: r['Product Target'] || '',
                    business_event: r['Business Event'] || '',
                    policy_status: r['Policy/Fee Status'] || '',
                    policy_effective_date: r['Policy Effective Date (TZ)'] || '',
                    primary_insurer: r['Primary Insurer'] || '',
                    insurer_policy_number: (r['Insurer Policy No.'] || '').trim() || null,
                    policy_account_exec: r['Policy Account Executive'] || '',
                    contact_account_exec: r['Contact Account Exec'] || '',
                    client: {
                        contact_ref: r['Contact Ref.'] || '',
                        client_type: r['Client Type'] || '',
                        client_status: r['Client Status'] || '',
                        title: '',
                        first_name: r['Client First Name'] || '',
                        surname: r['Client Surname'] || '',
                        contact_name: r['Contact Name'] || ''
                    },
                    correspondence_address: {},
                    financial_summary: {},
                    proposer_answers: {},
                    _vehicles: {},
                    _drivers: {},
                    _convictions: []
                };
            }

            const opp = opportunities[vRef];

            // Financials
            for (let f = 0; f < finKeys.length; f++) {
                const k = finKeys[f];
                const val = r[k];
                if (val && !['0', '0.00', '.00', '-1'].includes(val) && !opp.financial_summary[k]) {
                    opp.financial_summary[k] = parseFloat(val) || val;
                }
            }

            // Address
            if (!opp.correspondence_address['Address Line 1'] && r['Product Section'] === 'N/A' && r['Full Address']) {
                addrKeys.forEach(k => { if (r[k]) opp.correspondence_address[k] = r[k]; });
            }

            const section = r['Product Section'] || '';
            const risk = r['Product Risk'] || '';
            const subRisk = r['Product Sub Risk'] || '';
            const partRef = r['Policy Part Ref.'] || '';
            const q = (r['Question'] || '').trim();
            const ans = ((r['Free Text Answer'] || '').trim() || (r['Drop Down Answer'] || '').trim());

            if (section === 'N/A' && q) {
                if (q === 'Title') opp.client.title = ans;
                opp.proposer_answers[q] = ans;
            } else if (section === 'Drivers' && subRisk === 'Conviction') {
                let conv = opp._convictions.find(c => c.partRef === partRef);
                if (!conv) {
                    conv = { partRef: partRef, associatedRisk: risk, details: {} };
                    opp._convictions.push(conv);
                }
                if (q) conv.details[q] = ans;
            } else if (section === 'Drivers' && (risk === 'Driver' || risk === 'Other Driver') && partRef) {
                if (!opp._drivers[partRef]) {
                    opp._drivers[partRef] = { partRef: partRef, driverType: risk, answers: {}, convictions: [] };
                }
                if (q) appendValue(opp._drivers[partRef].answers, q, ans);
            } else if (section === 'Vehicle' && partRef) {
                if (!opp._vehicles[partRef]) {
                    opp._vehicles[partRef] = { partRef: partRef, specs: {}, answers: {} };
                }
                specKeys.forEach(k => {
                    if (r[k] && r[k] !== '-1' && !opp._vehicles[partRef].specs[k]) {
                        opp._vehicles[partRef].specs[k] = r[k];
                    }
                });
                if (q) appendValue(opp._vehicles[partRef].answers, q, ans);
            }
        }

        // Finalize assembly & conviction matching
        return Object.values(opportunities).map(opp => {
            opp._convictions.forEach(conv => {
                const driver = Object.values(opp._drivers).find(d =>
                    d.driverType === conv.associatedRisk && d.answers['Previous convictions'] === 'Yes'
                ) || Object.values(opp._drivers)[0];

                if (driver) driver.convictions.push(conv.details);
            });

            opp.vehicles = Object.values(opp._vehicles)
                .filter(v => Object.keys(v.answers).length > 1 || Object.keys(v.specs).length > 0)
                .map(v => ({
                    part_ref: v.partRef,
                    registration_number: v.answers['Registration number'] || v.specs['Registration number'] || 'UNKNOWN',
                    make: v.specs['Vehicle Make'] || '',
                    model: v.specs['Vehicle Model'] || v.answers['Vehicle'] || '',
                    specs: v.specs,
                    answers: v.answers
                }));

            opp.drivers = Object.values(opp._drivers).map(d => ({
                part_ref: d.partRef,
                driver_type: d.driverType,
                first_name: d.answers['Driver first name'] || opp.client.first_name,
                surname: d.answers['Driver surname'] || opp.client.surname,
                dob: d.answers['Driver date of birth'] || null,
                gender: d.answers['Driver gender'] || null,
                occupation: d.answers['Driver occupation'] || null,
                class_of_use: d.answers['Class of use'] || null,
                convictions: d.convictions,
                answers: d.answers
            }));

            delete opp._vehicles;
            delete opp._drivers;
            delete opp._convictions;

            return opp;
        });
    }

    function appendValue(obj, key, val) {
        if (!obj[key]) {
            obj[key] = val;
        } else if (Array.isArray(obj[key])) {
            obj[key].push(val);
        } else {
            obj[key] = [obj[key], val];
        }
    }
});