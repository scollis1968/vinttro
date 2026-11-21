// vinttro-api/routes/clients.js
const express = require('express');
const router = express.Router();
const clientController = require('../controllers/clientController');

router.get('/search', clientController.search);
router.get('/:clientId/summary', clientController.getSummary);

module.exports = router;