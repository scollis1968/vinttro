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

const PORT = process.env.PORT || 3000;

// Listen on HTTP Server wrapper (not app.listen)
server.listen(PORT, () => {
    console.log(`Vinttro API Microservice (HTTP + Socket.io) listening on port ${PORT}`);
});