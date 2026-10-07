// vinttro-api/routes/communicator.js
const express = require('express');
const router = express.Router();
const controller = require('../controllers/communicatorController');

// WebRTC Token Generator
router.get('/token', controller.getToken);

// Wallboard Hydration
router.get('/active-calls', controller.getActiveCalls);

// Route Record Management (Teams / Distribution)
router.post('/routes', controller.saveRoute);
router.get('/routes/lookup', controller.lookupRoute);
router.delete('/routes', controller.deleteRoute);

// Client Cache Management (Screen Pop Details)
router.post('/clients', controller.saveClient);            // Accepts single object OR batch array
router.get('/clients/lookup', controller.lookupClient);    // CLI lookup
router.delete('/clients', controller.deleteClient);        // Remove cached client

// Call Webhooks & Events
router.post('/call-event', controller.handleCallEvent);
router.post('/inbound-call', controller.handleInboundCall);
router.all('/voice-connect', controller.handleVoiceConnect);
router.post('/recording-event', controller.handleRecordingEvent);
router.post('/status-callback', controller.handleStatusCallback);
router.post('/accept-probe', controller.handleAcceptProbe);

module.exports = router;