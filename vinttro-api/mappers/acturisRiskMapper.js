// vinttro-api/mappers/acturisRiskMapper.js
const BaseMapper = require('./baseMapper');

class ActurisRiskMapper extends BaseMapper {
    /**
     * Compiles an array of unpivoted EAV rows for a single Version Ref
     * into a consolidated Opportunity domain object.
     */
    assembleOpportunity(versionRef, rows) {
        const first = rows[0];

        const opp = {
            opportunity_id: `OPP-${versionRef}`,
            version_ref: versionRef,
            product_target: this.cleanString(first['Product Target']),
            business_event: this.cleanString(first['Business Event']),
            policy_status: this.cleanString(first['Policy/Fee Status']),
            policy_effective_date: this.cleanString(first['Policy Effective Date (TZ)']),
            primary_insurer: this.cleanString(first['Primary Insurer']),
            insurer_policy_number: this.cleanString(first['Insurer Policy No.']) || null,
            account_executives: {
                policy_exec: this.cleanString(first['Policy Account Executive']),
                contact_exec: this.cleanString(first['Contact Account Exec'])
            },
            client: {
                contact_ref: this.cleanString(first['Contact Ref.']),
                client_type: this.cleanString(first['Client Type']),
                client_status: this.cleanString(first['Client Status']),
                title: '',
                first_name: this.cleanString(first['Client First Name']),
                surname: this.cleanString(first['Client Surname']),
                contact_name: this.cleanString(first['Contact Name'])
            },
            correspondence_address: {},
            financials: {},
            proposer_answers: {},
            _vehicles: {},
            _drivers: {},
            _convictions: []
        };

        const finKeys = [
            'Admin Fee', 'Basic Premium', 'Discounted Premium', 
            'IPT Amount', 'Part Annual Premium', 'Basic Commission', 'Commission Income'
        ];

        const addrKeys = [
            'Address Line 1', 'Address Line 2', 'Address Line 3', 
            'City', 'County', 'Postcode', 'Country', 'Full Address with Postcode'
        ];

        const specKeys = [
            'Vehicle Make', 'Vehicle Model', 'Vehicle Type', 'Door Plan', 
            'Engine Size CC', 'Fuel Type', 'Transmission Type', 'Production Year', 'ABI Code'
        ];

        for (const r of rows) {
            // 1. Financials
            for (const f of finKeys) {
                const val = r[f];
                if (val && !['0', '0.00', '.00', '-1'].includes(val) && !opp.financials[f]) {
                    opp.financials[f] = parseFloat(val) || val;
                }
            }

            // 2. Address (from Proposer N/A section)
            if (!opp.correspondence_address['Address Line 1'] && r['Product Section'] === 'N/A' && r['Full Address']) {
                addrKeys.forEach(k => {
                    if (r[k]) opp.correspondence_address[k] = this.cleanString(r[k]);
                });
            }

            const section = r['Product Section'] || '';
            const risk = r['Product Risk'] || '';
            const subRisk = r['Product Sub Risk'] || '';
            const partRef = r['Policy Part Ref.'] || '';
            const q = this.cleanString(r['Question']);
            const ans = this.cleanString(r['Free Text Answer'] || r['Drop Down Answer']);

            // 3. Proposer Questions
            if (section === 'N/A' && q) {
                if (q === 'Title') opp.client.title = ans;
                opp.proposer_answers[q] = ans;
            }
            // 4. Sub-Risk (Convictions)
            else if (section === 'Drivers' && subRisk === 'Conviction' && partRef) {
                let conv = opp._convictions.find(c => c.partRef === partRef);
                if (!conv) {
                    conv = { partRef, associatedRisk: risk, details: {} };
                    opp._convictions.push(conv);
                }
                if (q) conv.details[q] = ans;
            }
            // 5. Drivers
            else if (section === 'Drivers' && ['Driver', 'Other Driver'].includes(risk) && partRef) {
                if (!opp._drivers[partRef]) {
                    opp._drivers[partRef] = { partRef, driverType: risk, answers: {}, convictions: [] };
                }
                if (q) this._append(opp._drivers[partRef].answers, q, ans);
            }
            // 6. Vehicles
            else if (section === 'Vehicle' && partRef) {
                if (!opp._vehicles[partRef]) {
                    opp._vehicles[partRef] = { partRef, specs: {}, answers: {} };
                }
                specKeys.forEach(k => {
                    if (r[k] && r[k] !== '-1' && !opp._vehicles[partRef].specs[k]) {
                        opp._vehicles[partRef].specs[k] = this.cleanString(r[k]);
                    }
                });
                if (q) this._append(opp._vehicles[partRef].answers, q, ans);
            }
        }

        // Link convictions to the driver with Previous convictions = Yes
        for (const conv of opp._convictions) {
            const driver = Object.values(opp._drivers).find(d => 
                d.driverType === conv.associatedRisk && d.answers['Previous convictions'] === 'Yes'
            ) || Object.values(opp._drivers)[0];

            if (driver) driver.convictions.push(conv.details);
        }

        // Clean arrays and filter out ghost vehicle parts
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
    }

    /**
     * Builds attributes formatted for SuiteCRM Opportunities module
     */
    mapOpportunityAttributes(opp) {
        const client = opp.client || {};
        const vehicles = opp.vehicles || [];
        const drivers = opp.drivers || [];

        const primaryVrn = vehicles.length > 0 ? vehicles[0].registration_number : 'Vehicle';
        const oppName = `${client.surname || 'Client'} - ${opp.product_target || 'Motor'} (${primaryVrn})`;

        let closeDate = new Date().toISOString().split('T')[0];
        if (opp.policy_effective_date) {
            const parts = opp.policy_effective_date.split('/');
            if (parts.length === 3) {
                closeDate = `${parts[2]}-${parts[1]}-${parts[0]}`;
            }
        }

        // Consolidated risk text notes
        const notes = [
            `=== ACTURIS RISK DETAILS [${opp.opportunity_id}] ===`,
            `Status: ${opp.policy_status} | Event: ${opp.business_event}`,
            `Insurer: ${opp.primary_insurer} (Policy No: ${opp.insurer_policy_number || 'Pending'})`,
            `Executive: ${opp.account_executives?.policy_exec || 'N/A'}`,
            '',
            '--- VEHICLES ---'
        ];

        vehicles.forEach((v, idx) => {
            notes.push(`#${idx + 1} [${v.registration_number}] ${v.make} ${v.model} (Val: £${v.answers?.Value || '0'}, Parked: ${v.answers?.['Overnight parking'] || 'N/A'})`);
        });

        notes.push('', '--- DRIVERS ---');
        drivers.forEach((d, idx) => {
            notes.push(`#${idx + 1} ${d.first_name} ${d.surname} (${d.driver_type}) - DOB: ${d.dob}, Occ: ${d.occupation}`);
            if (d.convictions?.length) {
                d.convictions.forEach(c => {
                    notes.push(`   * Conviction: ${c['Conviction code']} on ${c['Conviction date']} (${c['Penalty points']} pts)`);
                });
            }
        });

        return {
            name: oppName,
            opportunity_type: opp.business_event === 'New Business' ? 'New Business' : 'Existing Business',
            sales_stage: this.mapSalesStage(opp.policy_status),
            date_closed: closeDate,
            amount: opp.financials['Basic Premium'] || opp.financials['Part Annual Premium'] || 0.00,
            description: notes.join('\n')
        };
    }

    mapSalesStage(status) {
        const s = (status || '').toLowerCase();
        if (s.includes('live')) return 'Closed Won';
        if (s.includes('expired') || s.includes('lapsed') || s.includes('cancelled')) return 'Closed Lost';
        if (s.includes('quoted')) return 'Proposal/Price Quote';
        return 'Prospecting';
    }

    _append(obj, key, val) {
        if (!obj[key]) obj[key] = val;
        else if (Array.isArray(obj[key])) obj[key].push(val);
        else obj[key] = [obj[key], val];
    }
}

module.exports = ActurisRiskMapper;