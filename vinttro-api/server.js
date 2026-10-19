// vinttro-api/server.js
require('dotenv').config();

const express = require('express');
const http = require('http'); // Required to wrap Express & Socket.io together
const { Server } = require('socket.io');

const app = express();
const server = http.createServer(app);

// Initialize Socket.io with CORS enabled for your WordPress/React domains
const io = new Server(server, {
    cors: {
        origin: "*",
        methods: ["GET", "POST"]
    }
});

// Attach Socket.io instance to Express so routes can call req.app.get('io')
app.set('io', io);

// Socket.io Connection & Room Handling
io.on('connection', (socket) => {
    console.log(`[Socket.io] Client connected: ${socket.id}`);

    // Allow agents to join private rooms (e.g., socket.emit('join_room', 'agent_wrtc_1'))
    socket.on('join_room', (roomName) => {
        socket.join(roomName);
        console.log(`[Socket.io] Socket ${socket.id} joined room: ${roomName}`);
    });

    socket.on('disconnect', () => {
        console.log(`[Socket.io] Client disconnected: ${socket.id}`);
    });
});

// Load Domain Routers
const communicatorRoutes = require('./routes/communicator');
const taskRoutes = require('./routes/tasks');
const importRoutes = require('./routes/import');

// 1. GLOBAL CORS HANDLER FIRST (Ensures all errors return valid CORS headers)
app.use((req, res, next) => {
    res.header("Access-Control-Allow-Origin", "*");
    res.header("Access-Control-Allow-Headers", "Origin, X-Requested-With, Content-Type, Accept");
    res.header("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
    if (req.method === 'OPTIONS') {
        return res.sendStatus(200);
    }
    next();
});

// 2. BODY PARSERS SECOND (With increased 25MB limits)
app.use(express.json({ limit: '25mb' }));
app.use(express.urlencoded({ extended: true, limit: '25mb' }));

const { version } = require('./package.json');

// Version & Health Check Endpoint
app.get('/api/version', (req, res) => {
    res.status(200).json({
        service: 'vinttro-api',
        version: version,
        status: 'online',
        timestamp: new Date().toISOString()
    });
});

// Mount Routers
app.use('/api/communicator', communicatorRoutes);
app.use('/api/tasks', taskRoutes);
app.use('/api/import', importRoutes);

const PORT = process.env.PORT || 3000;

// Listen on HTTP Server wrapper (not app.listen)
server.listen(PORT, '0.0.0.0', () => {
    console.log(`Vinttro API :) Microservice listening on port ${PORT}`);
});