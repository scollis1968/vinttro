const express = require('express');
const router = express.Router();
const importController = require('../controllers/importController');

router.post('/batch', importController.processBatch);

module.exports = router;