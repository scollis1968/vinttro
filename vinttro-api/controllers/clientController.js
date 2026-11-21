// vinttro-api/controllers/clientController.js
const { searchClients } = require('../services/clientSearchService');
const { getClientSummary } = require('../services/clientCacheService');

exports.search = async (req, res) => {
    try {
        const q = String(req.query.q || '').trim();
        const limit = Math.min(Math.max(parseInt(req.query.limit, 10) || 15, 1), 50);

        if (q.length < 2) {
            return res.status(200).json({ query: q, count: 0, results: [] });
        }

        const results = await searchClients(q, limit);
        return res.status(200).json({
            query: q,
            count: results.length,
            results
        });
    } catch (err) {
        console.error('[Client Search Error]:', err);
        return res.status(500).json({ error: 'Failed to search clients' });
    }
};

exports.getSummary = async (req, res) => {
    try {
        const summary = await getClientSummary(req.params.clientId);
        if (!summary) {
            return res.status(404).json({ error: 'Client summary not found' });
        }
        return res.status(200).json(summary);
    } catch (err) {
        console.error('[Client Summary Error]:', err);
        return res.status(500).json({ error: 'Failed to load client summary' });
    }
};