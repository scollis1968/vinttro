// vinttro-api/routes/communicator.js
const express = require('express');
const router = express.Router();
const controller = require('../controllers/communicatorController');

// WebRTC Token Generator
router.get('/token', controller.getToken);

// Wallboard Hydration
router.get('/active-calls', controller.getActiveCalls);

// Route Record Management
router.post('/routes', controller.saveRoute);
router.get('/routes/lookup', controller.lookupRoute);
router.delete('/routes', controller.deleteRoute);

// Call Webhooks & Events
router.post('/call-event', controller.handleCallEvent);
router.post('/inbound-call', controller.handleInboundCall);
router.all('/voice-connect', controller.handleVoiceConnect);
router.post('/recording-event', controller.handleRecordingEvent);
router.post('/status-callback', controller.handleStatusCallback);

module.exports = router;