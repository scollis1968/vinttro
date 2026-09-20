// vinttro-api/routes/communicator.js
const express = require('express');
const router = express.Router();
const twilio = require('twilio');
const redisClient = require('../services/redis'); // Import centralized Redis DB 1

// Environment Credentials
const ACCOUNT_SID = process.env.TWILIO_ACCOUNT_SID;
const API_KEY_SID = process.env.TWILIO_API_KEY_SID;
const API_KEY_SECRET = process.env.TWILIO_API_KEY_SECRET;
const TWILIO_PHONE_NUMBER = process.env.TWILIO_PHONE_NUMBER;
const SYNC_SERVICE_SID = process.env.TWILIO_SYNC_SERVICE_SID;

const twilioClient = twilio(API_KEY_SID, API_KEY_SECRET, { accountSid: ACCOUNT_SID });

const STATE_PRIORITY = { 'ringing': 1, 'answered': 2, 'completed': 3 };

// STUB Agents
const STUB_AGENTS = [
    { id: 'agent_mobile_1', type: 'mobile', number: '+447748633867', name: 'Test Mobile Agent' },
    { id: 'agent_wrtc_1', type: 'wrtc', name: 'Test WebRTC Agent', email: 'finley.collis@vinttro.co.uk' }
];

// -------------------------------------------------------------------------
// 1. WALLBOARD HYDRATION ENDPOINT
// -------------------------------------------------------------------------
// Used by WordPress wallboards on initial page load to fetch all active calls
router.get('/active-calls', async (req, res) => {
    try {
        // Fetch all call SIDs currently in the 'active_calls' set
        const activeSids = await redisClient.sMembers('active_calls');
        if (!activeSids || activeSids.length === 0) {
            return res.status(200).json([]);
        }

        // Fetch the Redis record for each active SID
        const keys = activeSids.map(sid => `call:${sid}`);
        const rawCalls = await redisClient.mGet(keys);
        const activeCalls = rawCalls
            .filter(Boolean)
            .map(item => JSON.parse(item));

        return res.status(200).json(activeCalls);
    } catch (error) {
        console.error('[Hydration Error] Failed to fetch active calls:', error);
        return res.status(500).json({ error: 'Failed to fetch active calls' });
    }
});

// -------------------------------------------------------------------------
// 2. CENTRAL CALL EVENT INGESTION
// -------------------------------------------------------------------------
router.post('/call-event', async (req, res) => {
    try {
        const io = req.app.get('io'); // Get shared Socket.io instance
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

        // Save Call Record to Redis (Expire in 12 hours)
        await redisClient.setEx(redisKey, 43200, JSON.stringify(updatedCallData));

        // Manage the 'active_calls' Set for wallboards
        if (newStatus === 'ringing' || newStatus === 'answered' || newStatus === 'in-progress') {
            await redisClient.sAdd('active_calls', callId);
        } else if (newStatus === 'completed' || newStatus === 'canceled' || newStatus === 'failed') {
            await redisClient.sRem('active_calls', callId);
        }

        // BROADCAST via Socket.io to all listening WordPress clients & Wallboards
        io.emit('call_updated', updatedCallData);

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

// -------------------------------------------------------------------------
// 3. INBOUND CALL DISPATCH
// -------------------------------------------------------------------------
router.post('/inbound-call', async (req, res) => {
    const io = req.app.get('io');
    const callSid = req.body.CallSid;
    const clientCallerId = req.body.From || 'Unknown Caller';
    const roomName = `Room_${callSid}`;

    const twiml = new twilio.twiml.VoiceResponse();
    const dial = twiml.dial();
    
    dial.conference({
        startConferenceOnEnter: false,
        endConferenceOnExit: true,
        record: 'record-from-start',
        recordingStatusCallback: 'https://services.uat.vinttro.co.uk/api/communicator/recording-event',
        recordingStatusCallbackEvent: 'completed'
    }, roomName);

    res.type('text/xml');
    res.send(twiml.toString());

    try {
        for (const agent of STUB_AGENTS) {
            // A. Dispatch Outbound PSTN Dial to Mobile Agents
            if (agent.type === 'mobile') {
                const whisperUrl = `https://services.uat.vinttro.co.uk/api/communicator/whisper-prompt?caller=${encodeURIComponent(clientCallerId)}&room=${encodeURIComponent(roomName)}`;
                console.log(`[Dispatch] Dialing mobile agent ${agent.name} (${agent.number})...`);
                
                await twilioClient.calls.create({
                    to: agent.number,
                    from: TWILIO_PHONE_NUMBER,
                    url: whisperUrl
                });
            }

            // B. Broadcast Real-Time Push Notification to WebRTC Clients
            if (agent.type === 'wrtc') {
                const payload = {
                    callSid: callSid,
                    callerId: clientCallerId,
                    roomId: roomName,
                    roomName: roomName,
                    agentId: agent.id,
                    status: 'parked',
                    timestamp: new Date().toISOString()
                };

                // 1. Socket.io Direct Emission (Primary, zero-latency)
                // Emits globally and specifically to the agent's socket room
                io.emit('incoming_call_queue', payload);
                io.to(agent.id).emit('incoming_call', payload);

                // 2. Twilio Sync Fallback (Optional)
                if (SYNC_SERVICE_SID) {
                    await twilioClient.sync.v1
                        .services(SYNC_SERVICE_SID)
                        .syncLists('vinttro_live_queue')
                        .syncListItems
                        .create({ data: payload, ttl: 120 });
                }
            }
        }
    } catch (error) {
        console.error('[Dispatch Error] Failed during agent notification dispatch:', error.message);
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

// -------------------------------------------------------------------------
// 4. RECORDING COMPLETE EVENT
// -------------------------------------------------------------------------
router.post('/recording-event', async (req, res) => {
    try {
        const io = req.app.get('io');
        const {
            CallSid,
            ConferenceSid,
            RecordingSid,
            RecordingUrl,
            RecordingDuration,
            RecordingStatus
        } = req.body;

        if (RecordingStatus === 'completed') {
            const publicAudioUrl = `${RecordingUrl}.mp3`;
            const redisKey = `call:${CallSid}`;
            const existingDataRaw = await redisClient.get(redisKey);
            const callData = existingDataRaw ? JSON.parse(existingDataRaw) : {};

            const completeCallRecord = {
                ...callData,
                call_id: CallSid,
                conference_sid: ConferenceSid,
                recording_sid: RecordingSid,
                recording_url: publicAudioUrl,
                duration_seconds: parseInt(RecordingDuration, 10),
                recording_completed_at: new Date().toISOString()
            };

            // Save back to Redis DB 1 (Retain audio metadata for 30 days)
            await redisClient.setEx(redisKey, 2592000, JSON.stringify(completeCallRecord));

            // Notify Wallboard/WP that recording is processed
            io.emit('recording_ready', completeCallRecord);
        }

        return res.status(200).send('<Response/>');
    } catch (error) {
        console.error('[Recording Webhook Error]:', error);
        return res.status(500).json({ error: 'Internal server error' });
    }
});

module.exports = router;