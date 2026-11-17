// vinttro-api/controllers/importController.js
const MapperFactory = require('../mappers/mapperFactory');
const suitecrm = require('../services/suitecrm');

exports.processBatch = async (req, res) => {
    try {
        const { batch, batch_index, source = 'acturis', data_type = 'client' } = req.body;

        if (!Array.isArray(batch) || batch.length === 0) {
            return res.status(400).json({ error: 'Empty or invalid batch array supplied.' });
        }

        const mapper = MapperFactory.getMapper(source, data_type);

        const summary = {
            batch_index: batch_index || 1,
            total: batch.length,
            created: 0,
            updated: 0,
            failed: 0,
            errors: []
        };

        for (let i = 0; i < batch.length; i++) {
            const row = batch[i];
            try {
                // 1. Map raw row into normalized payload
                const mapped = mapper.map(row);
                const { email, attributes } = mapped;

                // 2. Email Deduplication (Only look up if a valid cleaned email exists)
                let existingContactId = null;
                if (email) {
                    existingContactId = await suitecrm.findContactByEmail(email);
                }

                // 3. Upsert using SuiteCRM V8 module endpoints
                if (existingContactId) {
                    await suitecrm.updateRecord('Contacts', existingContactId, attributes);
                    summary.updated++;
                } else {
                    await suitecrm.createRecord('Contacts', attributes);
                    summary.created++;
                }
            } catch (rowErr) {
                summary.failed++;
                summary.errors.push({
                    row_index: i,
                    ref: row['Contact Ref.'] || null,
                    email: row['Primary Contact Email'] || row.email || null,
                    error: rowErr.message
                });
            }
        }

        return res.status(200).json(summary);

    } catch (err) {
        console.error('[Import Batch Error]:', err);
        return res.status(500).json({ error: err.message });
    }
};