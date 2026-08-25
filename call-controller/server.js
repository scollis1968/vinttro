const express = require('express');
const { createClient } = require('redis');
const twilio = require('twilio');

const app = express();
app.use(express.json()); // Parses incoming JSON payloads
app.use(express.urlencoded({ extended: true })); // Parses Twilio's default form-urlencoded payloads

// Safe cross-origin access rules for browser interaction
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
    database: 1
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
        const callId = req.body.CallSid || req.body.call_id;
        const newStatus = (req.body.CallStatus || req.body.status || '').toLowerCase();
        const source = req.body.source || (req.body.CallSid ? 'Twilio' : 'Unknown');

        if (!callId || !newStatus) {
            return res.status(400).json({ error: 'Missing call_id or status' });
        }

        const redisKey = `call:${callId}`;
        const currentCallDataRaw = await redisClient.get(redisKey);
        
        if (currentCallDataRaw) {
            const currentCall = JSON.parse(currentCallDataRaw);
            const currentPriority = STATE_PRIORITY[currentCall.status] || 0;
            const newPriority = STATE_PRIORITY[newStatus] || 0;

            if (newPriority <= currentPriority && currentCall.status !== newStatus) {
                console.log(`[Dropped] Delayed event (${newStatus}) from ${source} for Call ${callId}. Current state is already (${currentCall.status}).`);
                return res.status(200).json({ message: 'State ignored due to priority rules' });
            }
        }

        const updatedCallData = {
            call_id: callId,
            status: newStatus,
            agent_id: req.body.agent_id || null,
            last_updated: new Date().toISOString(),
            updated_by: source
        };

        await redisClient.setEx(redisKey, 43200, JSON.stringify(updatedCallData));
        console.log(`[Success] Call ${callId} updated to [${newStatus}] by ${source}`);

        return res.status(200).json({ success: true, state: newStatus });

    } catch (error) {
        console.error('Controller Error:', error);
        return res.status(500).json({ error: 'Internal server error' });
    }
});

// 4. GET Endpoint for WordPress to fetch current status on demand
app.get('/api/call/:id', async (req, res) => {
    const callData = await redisClient.get(`call:${req.params.id}`);
    if (!callData) return res.status(404).json({ error: 'Call not found or expired' });
    return res.status(200).json(JSON.parse(callData));
});

// Dev Seeder: Push mock tasks into Redis
app.post('/api/tasks/seed', async (req, res) => {
    try {
        const agentEmail = req.body.agentEmail || 'finley.collis@vinttro.co.uk';
        const mockTasks = [
            { id: '101', type: 'OUTBOUND_CALL', title: 'John Doe Call', target: '+447700900077', meta: 'Lead ID: 881', notes: 'Wants fleet pricing on 5 vehicles.' },
            { id: '102', type: 'RESEARCH', title: 'Apex Group Fleet Audit', target: 'Staging Record #402', meta: 'Case Ref: AX-2026', notes: 'Verify driver insurance compliance in the visp_data_staging grid.' },
            { id: '103', type: 'CASE_REVIEW', title: 'Approve Sarah Smith Contract', target: 'Document Store', meta: 'Doc ID: 902', notes: 'Review contract terms and sign off on credit verification parameters.' }
        ];

        for (const task of mockTasks) {
            await redisClient.hSet(`vinttro:task:${task.id}`, {
                id: task.id,
                type: task.type,
                title: task.title,
                target: task.target,
                meta: task.meta,
                notes: task.notes,
                status: 'pending'
            });
            await redisClient.sAdd(`vinttro:agent:${agentEmail}:tasks`, task.id);
        }

        res.json({ success: true, message: `Successfully seeded ${mockTasks.length} tasks for ${agentEmail}` });
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

// Fetch API: Retrieve dynamic workload catalog for an agent
app.get('/api/tasks', async (req, res) => {
    try {
        const agentEmail = req.query.agent || 'finley.collis@vinttro.co.uk';
        const taskIds = await redisClient.sMembers(`vinttro:agent:${agentEmail}:tasks`);
        const tasks = [];

        for (const id of taskIds) {
            const taskData = await redisClient.hGetAll(`vinttro:task:${id}`);
            if (taskData && taskData.id) {
                tasks.push(taskData);
            }
        }

        tasks.sort((a, b) => parseInt(a.id) - parseInt(b.id));
        res.json({ success: true, tasks });
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

// 5. Inbound Client Entry (Puts caller on hold in Conference)
app.post('/api/inbound-call', (req, res) => {
    const twiml = new twilio.twiml.VoiceResponse();
    const dial = twiml.dial();
    
    // Uses CallSid as dynamic room name to prevent caller collisions
    const roomName = req.body.CallSid || 'SalesRoom_Default';
    
    dial.conference({
        startConferenceOnEnter: false,
        endConferenceOnExit: true
    }, roomName);

    res.type('text/xml');
    res.send(twiml.toString());
});

// 6. Whisper Prompt (Executed ONLY when Mobile Agent answers)
app.post('/api/whisper-prompt', (req, res) => {
    const twiml = new twilio.twiml.VoiceResponse();
    const callerName = req.query.caller || 'John Smith';
    const roomName = req.query.room || 'SalesRoom_Default';

    const gather = twiml.gather({
        action: `/api/join-conference?room=${encodeURIComponent(roomName)}`,
        numDigits: 1,
        timeout: 8
    });
    
    gather.say(`This is an Inbound call to Sales from ${callerName}. Please press 1 to accept the call.`);
    twiml.hangup();

    res.type('text/xml');
    res.send(twiml.toString());
});

// 7. Accept & Bridge Mobile Agent into Conference
app.post('/api/join-conference', (req, res) => {
    const twiml = new twilio.twiml.VoiceResponse();
    const roomName = req.query.room || 'SalesRoom_Default';

    if (req.body.Digits === '1') {
        const dial = twiml.dial();
        dial.conference({
            startConferenceOnEnter: true
        }, roomName);
    } else {
        twiml.hangup();
    }

    res.type('text/xml');
    res.send(twiml.toString());
});

app.listen(PORT, () => {
    console.log(`Call Controller Microservice listening on port ${PORT}`);
});