// vinttro-api/services/acturisStreamProcessor.js
const fs = require('fs');
const readline = require('readline');
const { parse } = require('csv-parse');
const ActurisClientMapper = require('../mappers/acturisClientMapper');
const ActurisRiskMapper = require('../mappers/acturisRiskMapper');
const suitecrm = require('./suitecrm');

class ActurisStreamProcessor {
    constructor() {
        this.clientMapper = new ActurisClientMapper();
        this.riskMapper = new ActurisRiskMapper();
    }

    async inspectFile(filePath) {
        const fileStream = fs.createReadStream(filePath);
        const rl = readline.createInterface({ input: fileStream, crlfDelay: Infinity });

        let lineIndex = 0;
        let line1 = '';
        let line6 = '';

        for await (const line of rl) {
            lineIndex++;
            if (lineIndex === 1) line1 = line;
            if (lineIndex === 6) {
                line6 = line;
                break;
            }
        }
        rl.close();
        fileStream.destroy(); // Crucial: release handle on the file

        const line1Lower = line1.toLowerCase();
        const line6Lower = line6.toLowerCase();

        let reportType = 'client';
        if (line1Lower.includes('risk') || line6Lower.includes('product target')) {
            reportType = 'risk';
        }

        console.log(`[StreamProcessor] File: ${filePath}`);
        console.log(`[StreamProcessor] Detected Report Type: ${reportType.toUpperCase()}`);
        return { reportType, fromLine: 6 };
    }

    async processFile(filePath) {
        const { reportType, fromLine } = await this.inspectFile(filePath);

        if (reportType === 'risk') {
            return await this._processRiskStream(filePath, fromLine);
        } else {
            return await this._processClientStream(filePath, fromLine);
        }
    }

    async _processRiskStream(filePath, fromLine) {
        const stats = { totalRows: 0, opportunities: 0, created: 0, updated: 0, failed: 0 };
        let currentVersionRef = null;
        let currentRows = [];

        console.log(`[StreamProcessor] Opening CSV read stream from line ${fromLine}...`);

        const parser = fs.createReadStream(filePath).pipe(
            parse({
                from_line: fromLine,
                columns: true,
                skip_empty_lines: true,
                relax_quotes: true,
                relax_column_count: true, // Prevents crash on rows with variable field counts
                trim: true
            })
        );

        for await (const row of parser) {
            stats.totalRows++;
            const rowVersion = (row['Version Ref.'] || '').trim();
            if (!rowVersion) continue;

            if (currentVersionRef && rowVersion !== currentVersionRef) {
                console.log(`[StreamProcessor] Assembled Opportunity OPP-${currentVersionRef} (${currentRows.length} rows). Processing...`);
                await this._handleOpportunity(currentVersionRef, currentRows, stats);
                currentRows = [];
            }

            currentVersionRef = rowVersion;
            currentRows.push(row);
        }

        // Flush last opportunity
        if (currentVersionRef && currentRows.length > 0) {
            console.log(`[StreamProcessor] Assembled final Opportunity OPP-${currentVersionRef} (${currentRows.length} rows). Processing...`);
            await this._handleOpportunity(currentVersionRef, currentRows, stats);
        }

        return stats;
    }

    async _handleOpportunity(versionRef, rows, stats) {
        stats.opportunities++;
        try {
            const opp = this.riskMapper.assembleOpportunity(versionRef, rows);
            const oppAttributes = this.riskMapper.mapOpportunityAttributes(opp);

            console.log(`[StreamProcessor] -> Proposer: ${opp.client.first_name} ${opp.client.surname} | Vehicles: ${opp.vehicles.length} | Drivers: ${opp.drivers.length}`);

            // Link Contact if ref exists
            let contactId = null;
            if (opp.client?.contact_ref && typeof suitecrm.findContactByRef === 'function') {
                contactId = await suitecrm.findContactByRef(opp.client.contact_ref);
            }

            let existingOppId = null;
            if (typeof suitecrm.findOpportunityByRef === 'function') {
                existingOppId = await suitecrm.findOpportunityByRef(opp.opportunity_id);
            }

            if (existingOppId) {
                await suitecrm.updateRecord('Opportunities', existingOppId, oppAttributes);
                stats.updated++;
                console.log(`[StreamProcessor] 🔄 Updated SuiteCRM Opportunity: OPP-${versionRef}`);
            } else {
                const newOpp = await suitecrm.createRecord('Opportunities', oppAttributes);
                stats.created++;
                console.log(`[StreamProcessor] ✨ Created SuiteCRM Opportunity: OPP-${versionRef}`);

                if (contactId && newOpp?.id && typeof suitecrm.linkRelationship === 'function') {
                    await suitecrm.linkRelationship('Opportunities', newOpp.id, 'contacts', contactId);
                }
            }
        } catch (err) {
            stats.failed++;
            console.error(`[StreamProcessor] ⚠️ Error on OPP-${versionRef}: ${err.message}`);
        }
    }

    async _processClientStream(filePath, fromLine) {
        const stats = { totalRows: 0, created: 0, updated: 0, failed: 0 };

        const parser = fs.createReadStream(filePath).pipe(
            parse({
                from_line: fromLine,
                columns: true,
                skip_empty_lines: true,
                relax_quotes: true,
                relax_column_count: true,
                trim: true
            })
        );

        for await (const row of parser) {
            stats.totalRows++;
            try {
                const mapped = this.clientMapper.map(row);
                const { email, attributes } = mapped;

                let existingContactId = null;
                if (email) {
                    existingContactId = await suitecrm.findContactByEmail(email);
                }

                if (existingContactId) {
                    await suitecrm.updateRecord('Contacts', existingContactId, attributes);
                    stats.updated++;
                } else {
                    await suitecrm.createRecord('Contacts', attributes);
                    stats.created++;
                }
            } catch (err) {
                stats.failed++;
                console.error(`[StreamProcessor] Client Row Error (${row['Contact Ref.']}): ${err.message}`);
            }
        }

        return stats;
    }
}

module.exports = new ActurisStreamProcessor();