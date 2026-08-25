const express = require('express');
const router = express.Router();
const { createClient } = require('redis');
const twilio = require('twilio');

// Environment Credentials
const ACCOUNT_SID = process.env.TWILIO_ACCOUNT_SID;
const API_KEY_SID = process.env.TWILIO_API_KEY_SID;
const API_KEY_SECRET = process.env.TWILIO_API_KEY_SECRET;
const TWILIO_PHONE_NUMBER = process.env.TWILIO_PHONE_NUMBER;

const twilioClient = twilio(API_KEY_SID, API_KEY_SECRET, { accountSid: ACCOUNT_SID });

// Redis DB 1 Connection
const redisClient = createClient({
    url: process.env.REDIS_URL || 'redis://127.0.0.1:6379',
    database: 1
});
redisClient.on('error', (err) => console.error('Redis Communicator Error:', err));
redisClient.connect().then(() => console.log('Communicator connected to Redis DB 1'));

const STATE_PRIORITY = { 'ringing': 1, 'answered': 2, 'completed': 3 };
const STUB_AGENTS = [
    { id: 'agent_mobile_1', type: 'mobile', number: '+447748633867', name: 'Test Agent' }
];

// POST /api/communicator/call-event
router.post('/call-event', async (req, res) => {
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
                console.log(`[Dropped] Delayed event (${newStatus}) from ${source} for Call ${callId}. Current state is (${currentCall.status}).`);
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
        return res.status(200).json({ success: true, state: newStatus });

    } catch (error) {
        console.error('Controller Error:', error);
        return res.status(500).json({ error: 'Internal server error' });
    }
});

// GET /api/communicator/call/:id
router.get('/call/:id', async (req, res) => {
    const callData = await redisClient.get(`call:${req.params.id}`);
    if (!callData) return res.status(404).json({ error: 'Call not found or expired' });
    return res.status(200).json(JSON.parse(callData));
});

// POST /api/communicator/inbound-call
router.post('/inbound-call', async (req, res) => {
    const callSid = req.body.CallSid;
    const clientCallerId = req.body.From || 'Unknown Caller';
    const roomName = `Room_${callSid}`;

    const twiml = new twilio.twiml.VoiceResponse();
    const dial = twiml.dial();
    
    dial.conference({
        startConferenceOnEnter: false,
        endConferenceOnExit: true
    }, roomName);

    res.type('text/xml');
    res.send(twiml.toString());

    try {
        for (const agent of STUB_AGENTS) {
            if (agent.type === 'mobile') {
                const whisperUrl = `https://services.uat.vinttro.co.uk/api/communicator/whisper-prompt?caller=${encodeURIComponent(clientCallerId)}&room=${encodeURIComponent(roomName)}`;

                console.log(`[Dispatch] Dialing mobile agent ${agent.name} (${agent.number})...`);
                
                await twilioClient.calls.create({
                    to: agent.number,
                    from: TWILIO_PHONE_NUMBER,
                    url: whisperUrl
                });
            }
        }
    } catch (error) {
        console.error('[Dispatch Error] Failed to place agent outbound call:', error.message);
    }
});

// POST /api/communicator/whisper-prompt
router.post('/whisper-prompt', (req, res) => {
    const twiml = new twilio.twiml.VoiceResponse();
    const callerName = req.query.caller || 'John Smith';
    const roomName = req.query.room || 'SalesRoom_Default';

    const gather = twiml.gather({
        action: `/api/communicator/join-conference?room=${encodeURIComponent(roomName)}`,
        numDigits: 1,
        timeout: 8
    });
    
    gather.say(`This is an Inbound call to Sales from ${callerName}. Please press 1 to accept the call.`);
    twiml.hangup();

    res.type('text/xml');
    res.send(twiml.toString());
});

// POST /api/communicator/join-conference
router.post('/join-conference', (req, res) => {
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

module.exports = router;