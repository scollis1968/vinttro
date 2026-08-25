require('dotenv').config();

const express = require('express');
const app = express();

// Load Domain Routers
const communicatorRoutes = require('./routes/communicator');
const taskRoutes = require('./routes/tasks');

// Global Middleware
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Global CORS Handler
app.use((req, res, next) => {
    res.header("Access-Control-Allow-Origin", "*");
    res.header("Access-Control-Allow-Headers", "Origin, X-Requested-With, Content-Type, Accept");
    res.header("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
    if (req.method === 'OPTIONS') {
        return res.sendStatus(200);
    }
    next();
});

// Mount Routers under clean API paths
app.use('/api/communicator', communicatorRoutes);
app.use('/api/tasks', taskRoutes);

const PORT = process.env.PORT || 3000;

// SINGLE HTTP Server Listener
app.listen(PORT, () => {
    console.log(`Vinttro API Microservice listening on port ${PORT}`);
});