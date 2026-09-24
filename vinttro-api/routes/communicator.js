// vinttro-api/routes/communicator.js
const express = require('express');
const router = express.Router();
const twilio = require('twilio');
const redisClient = require('../services/redis'); // Centralized Redis DB 1
const suitecrmService = require('../services/suitecrm');

const ACCOUNT_SID = process.env.TWILIO_ACCOUNT_SID;
const API_KEY_SID = process.env.TWILIO_API_KEY_SID;
const API_KEY_SECRET = process.env.TWILIO_API_KEY_SECRET;
const TWIML_APP_SID = process.env.TWILIO_TWIML_APP_SID || process.env.TWIML_APP_SID;

const twilioClient = twilio(API_KEY_SID, API_KEY_SECRET, { accountSid: ACCOUNT_SID });
const STATE_PRIORITY = { 'ringing': 1, 'answered': 2, 'completed': 3 };

const STUB_AGENTS = [
    { id: 'agent_wrtc_1', type: 'wrtc', name: 'Test WebRTC Agent', email: 'finley.collis@vinttro.co.uk' }
];

// Helper method to push structured call records to SuiteCRM
// Helper method to push structured call records to SuiteCRM
async function saveCallToSuiteCRM(callData) {
    try {
        await suitecrmService.saveCallRecord(callData);
    } catch (err) {
        console.error('🚨 [SuiteCRM Sync Error]:', err.message);
    }
}

// -------------------------------------------------------------------------
// 1. WEBRTC VOICE TOKEN GENERATOR
// -------------------------------------------------------------------------
router.get('/token', (req, res) => {
    try {
        const identity = req.query.identity || 'agent_dev_1';
        
        if (!TWIML_APP_SID) {
            console.error('❌ CRITICAL: TWILIO_TWIML_APP_SID is missing or empty in .env file!');
            return res.status(500).json({ error: 'Server misconfiguration: TWIML_APP_SID missing' });
        }

        const AccessToken = twilio.jwt.AccessToken;
        const VoiceGrant = AccessToken.VoiceGrant;

        const token = new AccessToken(ACCOUNT_SID, API_KEY_SID, API_KEY_SECRET, { ttl: 3600, identity });
        
        const voiceGrant = new VoiceGrant({
            outgoingApplicationSid: TWIML_APP_SID,
            incomingAllow: true
        });
        token.addGrant(voiceGrant);

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
// Inside routes/communicator.js -> POST /call-event
router.post('/call-event', async (req, res) => {
    try {
        const io = req.app.get('io');
        const callId = req.body.CallSid || req.body.call_id;
        const newStatus = (req.body.CallStatus || req.body.status || '').toLowerCase();
        const source = req.body.source || (req.body.CallSid ? 'Twilio' : 'Unknown');
        const agentId = req.body.agent_id || req.body.identity || null;
        
        if (!callId || !newStatus) return res.status(400).json({ error: 'Missing call_id or status' });

        const redisKey = `call:${callId}`;
        const currentCallDataRaw = await redisClient.get(redisKey);
        let currentCall = currentCallDataRaw ? JSON.parse(currentCallDataRaw) : { legs: [] };

        // Maintain call legs history
        const legs = Array.isArray(currentCall.legs) ? currentCall.legs : [];
        const newLeg = {
            status: newStatus,
            agent_id: agentId,
            source: source,
            timestamp: new Date().toISOString()
        };
        legs.push(newLeg);

        // Derive top-level first_agent and last_agent
        let firstAgent = currentCall.first_agent || null;
        let lastAgent = currentCall.last_agent || null;

        if (agentId) {
            if (!firstAgent) firstAgent = agentId;
            lastAgent = agentId;
        }

        const updatedCallData = {
            ...currentCall,
            call_id: callId,
            status: newStatus,
            first_agent: firstAgent,
            last_agent: lastAgent,
            legs: legs,
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
router.all('/voice-connect', async (req, res) => {
    try {
        const roomName = req.body.To || req.body.RoomName || req.query.To || 'default-room';
        
        // Twilio WebRTC client passes identity in From as "client:finley.collis@vinttro.co.uk"
        const rawCaller = req.body.From || req.body.Caller || req.query.From || req.body.identity || '';
        const agentId = rawCaller.replace(/^client:/i, '') || 'Unknown Agent';

        // Extract callSid from Room_CAxxx naming convention used in /inbound-call
        const callSid = roomName.startsWith('Room_') ? roomName.replace('Room_', '') : roomName;

        if (callSid && callSid !== 'default-room') {
            const redisKey = `call:${callSid}`;
            const currentCallDataRaw = await redisClient.get(redisKey);

            if (currentCallDataRaw) {
                let currentCall = JSON.parse(currentCallDataRaw);
                const legs = Array.isArray(currentCall.legs) ? currentCall.legs : [];

                // Record the agent connect leg
                legs.push({
                    leg_type: 'agent_connect',
                    agent_id: agentId,
                    status: 'answered',
                    timestamp: new Date().toISOString()
                });

                const firstAgent = currentCall.first_agent || agentId;
                const lastAgent = agentId;

                const updatedCallData = {
                    ...currentCall,
                    status: 'answered',
                    first_agent: firstAgent,
                    last_agent: lastAgent,
                    legs: legs,
                    last_updated: new Date().toISOString(),
                    updated_by: `WebRTC (${agentId})`
                };

                await redisClient.setEx(redisKey, 43200, JSON.stringify(updatedCallData));

                const io = req.app.get('io');
                if (io) io.emit('call_updated', updatedCallData);
            }
        }

        const twiml = new twilio.twiml.VoiceResponse();
        const dial = twiml.dial();
        
        dial.conference({
            startConferenceOnEnter: true,
            endConferenceOnExit: true
        }, roomName);

        res.type('text/xml');
        return res.send(twiml.toString());

    } catch (error) {
        console.error('[Voice Connect Error]:', error);

        // Fallback TwiML so audio bridges even if Redis fails
        const twiml = new twilio.twiml.VoiceResponse();
        const dial = twiml.dial();
        dial.conference({ startConferenceOnEnter: true, endConferenceOnExit: true }, 'default-room');
        
        res.type('text/xml');
        return res.send(twiml.toString());
    }
});

// -------------------------------------------------------------------------
// 6. RECORDING COMPLETE EVENT (Triggered when Twilio recording finishes)
// -------------------------------------------------------------------------
router.post('/recording-event', async (req, res) => {
    try {
        const io = req.app.get('io');
        
        // Flexible key extraction (Twilio passes uppercase Form fields, Postman might pass JSON)
        const CallSid = req.body.CallSid || req.body.callSid || req.body.call_id;
        const ConferenceSid = req.body.ConferenceSid || req.body.conferenceSid;
        const RecordingSid = req.body.RecordingSid || req.body.recordingSid;
        const RecordingUrl = req.body.RecordingUrl || req.body.recordingUrl;
        const RecordingDuration = req.body.RecordingDuration || req.body.recordingDuration || 0;
        const RecordingStatus = req.body.RecordingStatus || req.body.recordingStatus;

        // Use CallSid if available, otherwise fallback to ConferenceSid
        const effectiveCallId = CallSid || ConferenceSid;

        if (RecordingStatus === 'completed' && effectiveCallId) {
            const publicAudioUrl = RecordingUrl ? `${RecordingUrl}.mp3` : '';
            const redisKey = `call:${effectiveCallId}`;
            const existingDataRaw = await redisClient.get(redisKey);
            const callData = existingDataRaw ? JSON.parse(existingDataRaw) : {};

            const completeCallRecord = {
                ...callData,
                call_id: effectiveCallId,
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
        console.error('[Recording Event Error]:', error);
        return res.status(500).json({ error: 'Internal server error' });
    }
});

// -------------------------------------------------------------------------
// 7. TWILIO STATUS CALLBACK ROUTE (Handles call completion webhooks)
// -------------------------------------------------------------------------
router.post('/status-callback', async (req, res) => {
    try {
        const callId = req.body.CallSid || req.body.callSid || req.body.call_id;
        const callStatus = (req.body.CallStatus || req.body.status || '').toLowerCase();
        const duration = req.body.CallDuration || req.body.duration || 0;
        const from = req.body.From || req.body.from;

        if (callId) {
            const redisKey = `call:${callId}`;
            const existingDataRaw = await redisClient.get(redisKey);
            const callData = existingDataRaw ? JSON.parse(existingDataRaw) : {};

            const updatedRecord = {
                ...callData,
                call_id: callId,
                status: callStatus,
                from: from || callData.callerId,
                duration_seconds: parseInt(duration, 10),
                last_updated: new Date().toISOString()
            };

            await redisClient.setEx(redisKey, 43200, JSON.stringify(updatedRecord));

            if (['completed', 'canceled', 'failed'].includes(callStatus)) {
                await redisClient.sRem('active_calls', callId);
                await saveCallToSuiteCRM(updatedRecord);
            }
        }

        return res.status(200).send('<Response/>');
    } catch (error) {
        console.error('[Status Callback Error]:', error);
        return res.status(500).json({ error: 'Internal server error' });
    }
});

module.exports = router;