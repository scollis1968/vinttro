// vinttro-api/controllers/communicatorController.js
const twilio = require('twilio');
const redisClient = require('../services/redis');
const suitecrmService = require('../services/suitecrm');
const communicatorRouting = require('../services/communicatorRouting');

// Inside route handlers, swap method calls to use communicatorRouting:
exports.saveRoute = async (req, res) => {
    try {
        const { cli, ddi } = req.body;
        if (!cli && !ddi) {
            return res.status(400).json({ error: 'Either cli or ddi must be provided' });
        }

        const { redisKey, routeData } = await communicatorRouting.saveRouteRecord(req.body);
        return res.status(200).json({ success: true, key: redisKey, route: routeData });
    } catch (error) {
        console.error('[Create Route Error]:', error);
        return res.status(500).json({ error: 'Failed to create route record' });
    }
};

exports.lookupRoute = async (req, res) => {
    try {
        const { cli, ddi } = req.query;
        const route = await communicatorRouting.findRouteRecord(cli, ddi);

        if (!route) {
            return res.status(404).json({ success: false, message: 'No matching route found' });
        }

        return res.status(200).json({ success: true, route });
    } catch (error) {
        return res.status(500).json({ error: 'Failed to query route' });
    }
};

exports.deleteRoute = async (req, res) => {
    try {
        const { cli, ddi } = req.body;
        const result = await communicatorRouting.deleteRouteRecord(cli, ddi);
        return res.status(200).json({ success: true, ...result });
    } catch (error) {
        return res.status(500).json({ error: 'Failed to delete route' });
    }
};

// Inside handleInboundCall:
exports.handleInboundCall = async (req, res) => {
    // ...
    const routeRecord = await communicatorRouting.findRouteRecord(clientCallerId, ddi);
    // ...
};