const express = require('express');
const { createClient } = require('redis');

const app = express();
app.use(express.json()); // Parses incoming JSON payloads
app.use(express.urlencoded({ extended: true })); // Parses Twilio's default form-urlencoded payloads

// 🚀 ADD THIS: Safe cross-origin access rules for browser interaction
app.use((req, res, next) => {
    res.header("Access-Control-Allow-Origin", "*");
    res.header("Access-Control-Allow-Headers", "Origin, X-Requested-With, Content-Type, Accept");
    res.header("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
    if (req.method === 'OPTIONS') {
        return res.sendStatus(200);
    }
    next();
});

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


// ==========================================================
// 🛠️ DEV SEEDER: Push mock multi-type tasks into Redis via cURL
// ==========================================================
app.post('/api/tasks/seed', async (req, res) => {
    try {
        const agentEmail = req.body.agentEmail || 'finley.collis@vinttro.co.uk';
        
        // Sample Tasks Definition Array
        const mockTasks = [
            { id: '101', type: 'OUTBOUND_CALL', title: 'John Doe Call', target: '+447700900077', meta: 'Lead ID: 881', notes: 'Wants fleet pricing on 5 vehicles.' },
            { id: '102', type: 'RESEARCH', title: 'Apex Group Fleet Audit', target: 'Staging Record #402', meta: 'Case Ref: AX-2026', notes: 'Verify driver insurance compliance in the visp_data_staging grid.' },
            { id: '103', type: 'CASE_REVIEW', title: 'Approve Sarah Smith Contract', target: 'Document Store', meta: 'Doc ID: 902', notes: 'Review contract terms and sign off on credit verification parameters.' }
        ];

        // Pipeline keys to Redis
        for (const task of mockTasks) {
            // 1. Store the core task hash details
            await redisClient.hSet(`vinttro:task:${task.id}`, {
                id: task.id,
                type: task.type,
                title: task.title,
                target: task.target,
                meta: task.meta,
                notes: task.notes,
                status: 'pending'
            });
            // 2. Add this task ID to the agent's active workload tracking set
            await redisClient.sAdd(`vinttro:agent:${agentEmail}:tasks`, task.id);
        }

        res.json({ success: true, message: `Successfully seeded ${mockTasks.length} tasks for ${agentEmail}` });
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

// ==========================================================
// 📥 FETCH API: Retrieve dynamic workload catalog for an agent
// ==========================================================
app.get('/api/tasks', async (req, res) => {
    try {
        const agentEmail = req.query.agent || 'finley.collis@vinttro.co.uk';
        
        // 1. Grab all task IDs assigned to this agent's set
        const taskIds = await redisClient.sMembers(`vinttro:agent:${agentEmail}:tasks`);
        const tasks = [];

        // 2. Hydrate each task ID into its full data map hash
        for (const id of taskIds) {
            const taskData = await redisClient.hGetAll(`vinttro:task:${id}`);
            if (taskData && taskData.id) {
                tasks.push(taskData);
            }
        }

        // Return sorted by ID
        tasks.sort((a, b) => parseInt(a.id) - parseInt(b.id));
        res.json({ success: true, tasks });
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});


app.listen(PORT, () => {
    console.log(`Call Controller Microservice listening on port ${PORT}`);
});