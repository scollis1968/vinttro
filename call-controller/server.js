const express = require('express');
const { createClient } = require('redis');

const app = express();
app.use(express.json()); // Parses incoming JSON payloads
app.use(express.urlencoded({ extended: true })); // Parses Twilio's default form-urlencoded payloads

const PORT = 3000;

// 1. Connect strictly to Redis Database 1
const redisClient = createClient({
    url: 'redis://127.0.0.1:6379',
    database: 1 // <--- Keeps WP data separate in DB 0
});

redisClient.on('error', (err) => console.error('Redis Client Error', err));
redisClient.connect().then(() => console.log('Connected securely to Redis DB 1'));

// 2. Define State Weights to prevent out-of-order packet overrides
const STATE_PRIORITY = {
    'ringing': 1,
    'answered': 2,
    'completed': 3
};

// 3. Central Webhook Endpoint for Twilio, SuiteCRM, and WP
app.post('/api/call-event', async (req, res) => {
    try {
        // Normalize payload data depending on who sent it
        // (Twilio uses CallSid/CallStatus, custom scripts might use call_id/status)
        const callId = req.body.CallSid || req.body.call_id;
        const newStatus = (req.body.CallStatus || req.body.status || '').toLowerCase();
        const source = req.body.source || (req.body.CallSid ? 'Twilio' : 'Unknown');

        if (!callId || !newStatus) {
            return res.status(400).json({ error: 'Missing call_id or status' });
        }

        const redisKey = `call:${callId}`;

        // Fetch existing call state from Redis
        const currentCallDataRaw = await redisClient.get(redisKey);
        
        if (currentCallDataRaw) {
            const currentCall = JSON.parse(currentCallDataRaw);
            const currentPriority = STATE_PRIORITY[currentCall.status] || 0;
            const newPriority = STATE_PRIORITY[newStatus] || 0;

            // If a delayed packet arrives AFTER the call is already answered or completed, drop it
            if (newPriority <= currentPriority && currentCall.status !== newStatus) {
                console.log(`[Dropped] Delayed event (${newStatus}) from ${source} for Call ${callId}. Current state is already (${currentCall.status}).`);
                return res.status(200).json({ message: 'State ignored due to priority rules' });
            }
        }

        // Build the updated state object
        const updatedCallData = {
            call_id: callId,
            status: newStatus,
            agent_id: req.body.agent_id || null,
            last_updated: new Date().toISOString(),
            updated_by: source
        };

        // Save to Redis DB 1 with a 12-hour TTL (43200 seconds) so old data auto-cleans
        await redisClient.setEx(redisKey, 43200, JSON.stringify(updatedCallData));
        
        console.log(`[Success] Call ${callId} updated to [${newStatus}] by ${source}`);

        // OPTIONAL: Publish event to Redis Pub/Sub for WebSockets later
        // await redisClient.publish('call-updates', JSON.stringify(updatedCallData));

        return res.status(200).json({ success: true, state: newStatus });

    } catch (error) {
        console.error('Controller Error:', error);
        return res.status(500).json({ error: 'Internal server error' });
    }
});

// 4. Simple GET Endpoint for WordPress to fetch current status on demand
app.get('/api/call/:id', async (req, res) => {
    const callData = await redisClient.get(`call:${req.params.id}`);
    if (!callData) return res.status(404).json({ error: 'Call not found or expired' });
    return res.status(200).json(JSON.parse(callData));
});

app.listen(PORT, () => {
    console.log(`Call Controller Microservice listening on port ${PORT}`);
});