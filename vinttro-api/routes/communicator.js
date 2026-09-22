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
    { id: 'agent_wrtc_1', type: 'wrtc', name: 'Test WebRTC Agent', email: 'finley.collis@vinttro.co.uk' }
];

// Helper Function: Write Final Completed Call Record to SuiteCRM
async function saveCallToSuiteCRM(callData) {
    try {
        console.log(`[SuiteCRM Sync] Writing call record for ${callData.call_id}...`);
        // Example POST request to your PHP microservice or SuiteCRM V8 API:
        // await fetch('https://services.uat.vinttro.co.uk/vinttro-api/public/index.php/calls', {
        //     method: 'POST',
        //     headers: { 'Content-Type': 'application/json' },
        //     body: JSON.stringify(callData)
        // });
    } catch (err) {
        console.error('[SuiteCRM Sync Error]:', err.message);
    }
}

// -------------------------------------------------------------------------
// 1. WALLBOARD HYDRATION ENDPOINT
// -------------------------------------------------------------------------
router.get('/active-calls', async (req, res) => {
    try {
        const activeSids = await redisClient.sMembers('active_calls');
        if (!activeSids || activeSids.length === 0) {
            return res.status(200).json([]);
        }

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
        const io = req.app.get('io');
        const callId = req.body.CallSid || req.body.call_id;
        const newStatus = (req.body.CallStatus || req.body.status || '').toLowerCase();
        const source = req.body.source || (req.body.CallSid ? 'Twilio' : 'Unknown');

        if (!callId || !newStatus) {
            return res.status(400).json({ error: 'Missing call_id or status' });
        }

        const redisKey = `call:${callId}`;
        const currentCallDataRaw = await redisClient.get(redisKey);
        let currentCall = currentCallDataRaw ? JSON.parse(currentCallDataRaw) : { segments: [] };
        
        if (currentCallDataRaw) {
            const currentPriority = STATE_PRIORITY[currentCall.status] || 0;
            const newPriority = STATE_PRIORITY[newStatus] || 0;

            if (newPriority <= currentPriority && currentCall.status !== newStatus) {
                console.log(`[Dropped] Delayed event (${newStatus}) from ${source} for Call ${callId}. Current state is (${currentCall.status}).`);
                return res.status(200).json({ message: 'State ignored due to priority rules' });
            }
        }

        // Close last segment timestamp if call ended
        if (newStatus === 'completed' && currentCall.segments && currentCall.segments.length > 0) {
            let lastSegment = currentCall.segments[currentCall.segments.length - 1];
            if (!lastSegment.end_time) {
                lastSegment.end_time = new Date().toISOString();
            }
        }

        const updatedCallData = {
            ...currentCall,
            call_id: callId,
            status: newStatus,
            agent_id: req.body.agent_id || currentCall.agent_id || null,
            last_updated: new Date().toISOString(),
            updated_by: source
        };

        // Save Call Record to Redis (Expire in 12 hours)
        await redisClient.setEx(redisKey, 43200, JSON.stringify(updatedCallData));

        // Manage active calls set
        if (['ringing', 'answered', 'in-progress'].includes(newStatus)) {
            await redisClient.sAdd('active_calls', callId);
        } else if (['completed', 'canceled', 'failed'].includes(newStatus)) {
            await redisClient.sRem('active_calls', callId);
        }

        // BROADCAST via Socket.io
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

                io.emit('incoming_call_queue', payload);
                io.to(agent.id).emit('incoming_call', payload);
            }
        }
    } catch (error) {
        console.error('[Dispatch Error]:', error.message);
    }
});

// -------------------------------------------------------------------------
// 4. RECORDING COMPLETE EVENT (Flushes complete call record to SuiteCRM)
// -------------------------------------------------------------------------
router.post('/recording-event', async (req, res) => {
    try {
        const io = req.app.get('io');
        const { CallSid, ConferenceSid, RecordingSid, RecordingUrl, RecordingDuration, RecordingStatus } = req.body;

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

            // 1. Save final state to Redis (Retain for 30 days)
            await redisClient.setEx(redisKey, 2592000, JSON.stringify(completeCallRecord));

            // 2. Flush complete record (with segments & recording URL) to SuiteCRM
            await saveCallToSuiteCRM(completeCallRecord);

            // 3. Broadcast real-time update
            io.emit('recording_ready', completeCallRecord);
        }

        return res.status(200).send('<Response/>');
    } catch (error) {
        console.error('[Recording Webhook Error]:', error);
        return res.status(500).json({ error: 'Internal server error' });
    }
});

module.exports = router;