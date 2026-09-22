// vinttro-api/routes/communicator.js
const express = require('express');
const router = express.Router();
const twilio = require('twilio');
const redisClient = require('../services/redis'); // Centralized Redis DB 1

const ACCOUNT_SID = process.env.TWILIO_ACCOUNT_SID;
const API_KEY_SID = process.env.TWILIO_API_KEY_SID;
const API_KEY_SECRET = process.env.TWILIO_API_KEY_SECRET;
const TWIML_APP_SID = process.env.TWILIO_TWIML_APP_SID;

const twilioClient = twilio(API_KEY_SID, API_KEY_SECRET, { accountSid: ACCOUNT_SID });
const STATE_PRIORITY = { 'ringing': 1, 'answered': 2, 'completed': 3 };

const STUB_AGENTS = [
    { id: 'agent_wrtc_1', type: 'wrtc', name: 'Test WebRTC Agent', email: 'finley.collis@vinttro.co.uk' }
];

async function saveCallToSuiteCRM(callData) {
    try {
        console.log(`[SuiteCRM Sync] Writing call record for ${callData.call_id}...`);
    } catch (err) {
        console.error('[SuiteCRM Sync Error]:', err.message);
    }
}

// -------------------------------------------------------------------------
// 1. WEBRTC VOICE TOKEN GENERATOR (Bypasses WP Nonce issues)
// -------------------------------------------------------------------------
router.get('/token', (req, res) => {
    try {
        const identity = req.query.identity || 'agent_dev_1';
        const AccessToken = twilio.jwt.AccessToken;
        const VoiceGrant = AccessToken.VoiceGrant;

        const token = new AccessToken(ACCOUNT_SID, API_KEY_SID, API_KEY_SECRET, { ttl: 3600, identity });
        
        const voiceGrant = new VoiceGrant({
            outgoingApplicationSid: TWIML_APP_SID,
            incomingAllow: true
        });
        token.addGrant(voiceGrant);

        // Fixed: Use toJwt() with lowercase 'wt'
        return res.status(200).json({ token: token.toJwt(), identity });
    } catch (error) {
        console.error('[Token Generation Error]:', error);
        return res.status(500).json({ error: 'Failed to generate Twilio token' });
    }
});

// -------------------------------------------------------------------------
// 2. WALLBOARD HYDRATION ENDPOINT
// -------------------------------------------------------------------------
router.get('/active-calls', async (req, res) => {
    try {
        const activeSids = await redisClient.sMembers('active_calls');
        if (!activeSids || activeSids.length === 0) return res.status(200).json([]);

        const keys = activeSids.map(sid => `call:${sid}`);
        const rawCalls = await redisClient.mGet(keys);
        const activeCalls = rawCalls.filter(Boolean).map(item => JSON.parse(item));

        return res.status(200).json(activeCalls);
    } catch (error) {
        return res.status(500).json({ error: 'Failed to fetch active calls' });
    }
});

// -------------------------------------------------------------------------
// 3. CENTRAL CALL EVENT INGESTION
// -------------------------------------------------------------------------
router.post('/call-event', async (req, res) => {
    try {
        const io = req.app.get('io');
        const callId = req.body.CallSid || req.body.call_id;
        const newStatus = (req.body.CallStatus || req.body.status || '').toLowerCase();
        const source = req.body.source || (req.body.CallSid ? 'Twilio' : 'Unknown');
        
        console.log(`[Incoming Event] Source: ${source} | CallSid: ${callId} | Status: ${newStatus}`);
        
        if (!callId || !newStatus) return res.status(400).json({ error: 'Missing call_id or status' });

        const redisKey = `call:${callId}`;
        const currentCallDataRaw = await redisClient.get(redisKey);
        let currentCall = currentCallDataRaw ? JSON.parse(currentCallDataRaw) : { segments: [] };
        
        if (currentCallDataRaw) {
            const currentPriority = STATE_PRIORITY[currentCall.status] || 0;
            const newPriority = STATE_PRIORITY[newStatus] || 0;

            if (newPriority <= currentPriority && currentCall.status !== newStatus) {
                return res.status(200).json({ message: 'State ignored due to priority rules' });
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

        await redisClient.setEx(redisKey, 43200, JSON.stringify(updatedCallData));

        if (['ringing', 'answered', 'in-progress'].includes(newStatus)) {
            await redisClient.sAdd('active_calls', callId);
        } else if (['completed', 'canceled', 'failed'].includes(newStatus)) {
            await redisClient.sRem('active_calls', callId);
        }

        io.emit('call_updated', updatedCallData);
        return res.status(200).json({ success: true, state: newStatus });

    } catch (error) {
        return res.status(500).json({ error: 'Internal server error' });
    }
});

// -------------------------------------------------------------------------
// 4. INBOUND CALL DISPATCH (Parks caller in Conference)
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

    // Store call state in Redis DB 1
    const callPayload = {
        callSid: callSid,
        callerId: clientCallerId,
        roomId: roomName,
        status: 'parked',
        timestamp: new Date().toISOString()
    };
    await redisClient.setEx(`call:${callSid}`, 43200, JSON.stringify(callPayload));

    // Emit Socket.io alert to connected agents
    io.emit('incoming_call', callPayload);
});

// -------------------------------------------------------------------------
// 5. WEBRTC VOICE CONNECT (Bridges browser into conference)
// -------------------------------------------------------------------------
router.all('/voice-connect', (req, res) => {
    const roomName = req.body.To || req.body.RoomName || req.query.To || 'default-room';

    const twiml = new twilio.twiml.VoiceResponse();
    const dial = twiml.dial();
    
    dial.conference({
        startConferenceOnEnter: true,
        endConferenceOnExit: true
    }, roomName);

    res.type('text/xml');
    res.send(twiml.toString());
});

// -------------------------------------------------------------------------
// 6. RECORDING COMPLETE EVENT
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

            await redisClient.setEx(redisKey, 2592000, JSON.stringify(completeCallRecord));
            await saveCallToSuiteCRM(completeCallRecord);
            io.emit('recording_ready', completeCallRecord);
        }

        return res.status(200).send('<Response/>');
    } catch (error) {
        return res.status(500).json({ error: 'Internal server error' });
    }
});

module.exports = router;