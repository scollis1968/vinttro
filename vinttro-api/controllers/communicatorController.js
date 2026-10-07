// vinttro-api/controllers/communicatorController.js
const twilio = require('twilio');
const redisClient = require('../services/redis');
const suitecrmService = require('../services/suitecrm');
const communicatorRouting = require('../services/communicatorRouting');
const clientCacheService = require('../services/clientCacheService');
const deliveryPlanEngine = require('../services/deliveryPlanEngine');

const ACCOUNT_SID = process.env.TWILIO_ACCOUNT_SID;
const API_KEY_SID = process.env.TWILIO_API_KEY_SID;
const API_KEY_SECRET = process.env.TWILIO_API_KEY_SECRET;
const TWIML_APP_SID = process.env.TWILIO_TWIML_APP_SID || process.env.TWIML_APP_SID;

const twilioClient = twilio(API_KEY_SID, API_KEY_SECRET, { accountSid: ACCOUNT_SID });

async function saveCallToSuiteCRM(callData) {
    try {
        await suitecrmService.saveCallRecord(callData);
    } catch (err) {
        console.error('🚨 [SuiteCRM Sync Error]:', err.message);
    }
}

// -------------------------------------------------------------------------
// ROUTE MANAGEMENT HANDLERS (Teams / Distribution)
// -------------------------------------------------------------------------
exports.saveRoute = async (req, res) => {
    try {
        const { cli, ddi } = req.body;
        
        if (!cli && !ddi) {
            return res.status(400).json({ error: 'Either cli or ddi must be provided' });
        }

        const { redisKey, routeData } = await communicatorRouting.saveRouteRecord(req.body);
        
        return res.status(200).json({ 
            success: true, 
            key: redisKey, 
            route: routeData 
        });
    } catch (error) {
        console.error('[Create Route Error]:', error);
        return res.status(500).json({ error: 'Failed to create route record', details: error.message });
    }
};

exports.lookupRoute = async (req, res) => {
    try {
        const { cli, ddi } = req.query;
        const route = await communicatorRouting.findRouteRecord(cli, ddi);

        if (!route) {
            return res.status(404).json({ success: false, message: 'No matching route found' });
        }

        return res.status(200).json({ success: true, route });
    } catch (error) {
        return res.status(500).json({ error: 'Failed to query route' });
    }
};

exports.deleteRoute = async (req, res) => {
    try {
        const { cli, ddi } = req.body;
        const result = await communicatorRouting.deleteRouteRecord(cli, ddi);
        return res.status(200).json({ success: true, ...result });
    } catch (error) {
        return res.status(500).json({ error: 'Failed to delete route' });
    }
};

// -------------------------------------------------------------------------
// CLIENT CACHE HANDLERS (Screen Pop Data)
// -------------------------------------------------------------------------
exports.saveClient = async (req, res) => {
    try {
        if (Array.isArray(req.body)) {
            // Batch Mode (Nightly Sync)
            const result = await clientCacheService.bulkSyncClients(req.body);
            return res.status(200).json({ success: true, mode: 'batch', ...result });
        } else {
            // Single Mode (SuiteCRM after_save hook)
            const { redisKey, clientPayload } = await clientCacheService.saveClientRecord(req.body);
            return res.status(200).json({ success: true, mode: 'single', key: redisKey, client: clientPayload });
        }
    } catch (error) {
        return res.status(400).json({ error: error.message });
    }
};

exports.lookupClient = async (req, res) => {
    try {
        const client = await clientCacheService.getClientByCli(req.query.cli);
        if (!client) {
            return res.status(404).json({ success: false, message: 'Client not found in cache' });
        }
        return res.status(200).json({ success: true, client });
    } catch (error) {
        return res.status(500).json({ error: 'Failed to lookup client' });
    }
};

exports.deleteClient = async (req, res) => {
    try {
        const result = await clientCacheService.deleteClientRecord(req.body.cli);
        return res.status(200).json({ success: true, ...result });
    } catch (error) {
        return res.status(500).json({ error: 'Failed to delete client' });
    }
};

// -------------------------------------------------------------------------
// WEBRTC TOKEN
// -------------------------------------------------------------------------
exports.getToken = (req, res) => {
    try {
        const identity = req.query.identity || 'agent_dev_1';

        if (!TWIML_APP_SID) {
            console.error('❌ CRITICAL: TWILIO_TWIML_APP_SID missing in .env');
            return res.status(500).json({ error: 'Server misconfiguration: TWIML_APP_SID missing' });
        }

        const AccessToken = twilio.jwt.AccessToken;
        const VoiceGrant = AccessToken.VoiceGrant;

        const token = new AccessToken(ACCOUNT_SID, API_KEY_SID, API_KEY_SECRET, { ttl: 3600, identity });
        token.addGrant(new VoiceGrant({ outgoingApplicationSid: TWIML_APP_SID, incomingAllow: true }));

        return res.status(200).json({ token: token.toJwt(), identity });
    } catch (error) {
        console.error('[Token Generation Error]:', error);
        return res.status(500).json({ error: 'Failed to generate Twilio token' });
    }
};

// -------------------------------------------------------------------------
// WALLBOARD
// -------------------------------------------------------------------------
exports.getActiveCalls = async (req, res) => {
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
};

// -------------------------------------------------------------------------
// CALL EVENTS
// -------------------------------------------------------------------------
exports.handleCallEvent = async (req, res) => {
    try {
        const io = req.app.get('io');
        const callId = req.body.CallSid || req.body.call_id;
        const conferenceSid = req.body.ConferenceSid || req.body.conference_sid;
        const newStatus = (req.body.CallStatus || req.body.status || '').toLowerCase();
        const source = req.body.source || (req.body.CallSid ? 'Twilio' : 'Unknown');
        const agentId = req.body.agent_id || req.body.identity || null;

        if (!callId || !newStatus) return res.status(400).json({ error: 'Missing call_id or status' });

        if (conferenceSid) {
            await redisClient.setEx(`map:${conferenceSid}`, 43200, callId);
        }

        const redisKey = `call:${callId}`;
        const currentCallDataRaw = await redisClient.get(redisKey);
        let currentCall = currentCallDataRaw ? JSON.parse(currentCallDataRaw) : { legs: [] };

        const legs = Array.isArray(currentCall.legs) ? currentCall.legs : [];
        legs.push({
            status: newStatus,
            agent_id: agentId,
            source: source,
            timestamp: new Date().toISOString()
        });

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

        if (io) io.emit('call_updated', updatedCallData);
        return res.status(200).json({ success: true, state: newStatus });
    } catch (error) {
        return res.status(500).json({ error: 'Internal server error' });
    }
};

// -------------------------------------------------------------------------
// INBOUND CALL DISPATCH (Parks caller & fetches client + route details)
// -------------------------------------------------------------------------
exports.handleInboundCall_Old = async (req, res) => {
    const io = req.app.get('io');
    const callSid = req.body.CallSid;
    const clientCallerId = req.body.From || 'Unknown Caller';
    const ddi = req.body.To || req.body.Called || '';
    const roomName = `Room_${callSid}`;

    const recordingCallbackUrl = `https://services.uat.vinttro.co.uk/api/communicator/recording-event?call_id=${encodeURIComponent(callSid)}&room=${encodeURIComponent(roomName)}`;
    const statusCallbackUrl = `https://services.uat.vinttro.co.uk/api/communicator/status-callback?call_id=${encodeURIComponent(callSid)}&room=${encodeURIComponent(roomName)}`;

    // Parallel lookup for Client details and Route rules
    const [clientData, routeRecord] = await Promise.all([
        clientCacheService.getClientByCli(clientCallerId),
        communicatorRouting.findRouteRecord(clientCallerId, ddi)
    ]);



    const twiml = new twilio.twiml.VoiceResponse();
    const dial = twiml.dial();

    dial.conference({
        startConferenceOnEnter: false,
        endConferenceOnExit: true,
        record: 'record-from-start',
        recordingStatusCallback: recordingCallbackUrl,
        recordingStatusCallbackEvent: 'completed',
        statusCallback: statusCallbackUrl,
        statusCallbackEvent: 'end'
    }, roomName);

    res.type('text/xml');
    res.send(twiml.toString());

    const initialLeg = {
        leg_type: 'inbound_park',
        status: 'parked',
        caller_id: clientCallerId,
        ddi: ddi,
        timestamp: new Date().toISOString()
    };

    const callPayload = {
        call_id: callSid,
        callSid: callSid,
        callerId: clientCallerId,
        from: clientCallerId,
        ddi: ddi,
        roomId: roomName,
        status: 'parked',
        first_agent: null,
        last_agent: null,
        legs: [initialLeg],
        timestamp: new Date().toISOString(),

        // Routing details
        ou: routeRecord?.ou || null,
        delivery_plan: routeRecord?.delivery_plan || null,
        route_matched_key: routeRecord?.matched_key || null,

        // Client / Screen Pop details
        client_name: clientData?.client_name || routeRecord?.client_name || null,
        client_id: clientData?.client_id || routeRecord?.client_id || null,
        crm_url: clientData?.crm_url || null,
        account_tier: clientData?.account_tier || null,
        active_leads: clientData?.active_leads || [],
        screen_pop: routeRecord?.screen_pop || clientData?.extra_meta || null
    };

    await redisClient.setEx(`call:${callSid}`, 43200, JSON.stringify(callPayload));
    // -------------------------------------------------------------------------
    // Debug & Socket Emission
    // -------------------------------------------------------------------------
    if (!io) {
        console.error('❌ ERROR: Socket.io instance ("io") is undefined on req.app!');
    } else {
        const clientCount = io.engine ? io.engine.clientsCount : 0;
        console.log(`📢 Emitting 'incoming_call' to ${clientCount} connected socket client(s)...`);
        io.emit('incoming_call', callPayload);
    }

};

exports.handleInboundCall = async (req, res) => {
    try {
        const io = req.app.get('io');
        const callSid = req.body.CallSid || `CA_MOCK_${Date.now()}`;
        const clientCallerId = req.body.From || 'Unknown Caller';
        const ddi = req.body.To || req.body.Called || '';
        const roomName = `Room_${callSid}`;

        const recordingCallbackUrl = `https://services.uat.vinttro.co.uk/api/communicator/recording-event?call_id=${encodeURIComponent(callSid)}&room=${encodeURIComponent(roomName)}`;
        const statusCallbackUrl = `https://services.uat.vinttro.co.uk/api/communicator/status-callback?call_id=${encodeURIComponent(callSid)}&room=${encodeURIComponent(roomName)}`;

        // Parallel lookup for Client details and Route rules
        const [clientData, routeRecord] = await Promise.all([
            clientCacheService.getClientByCli ? clientCacheService.getClientByCli(clientCallerId) : null,
            communicatorRouting.findRouteRecord(clientCallerId, ddi)
        ]);

        // Generate TwiML XML response for Twilio
        const twiml = new twilio.twiml.VoiceResponse();
        const dial = twiml.dial();
        dial.conference({
            startConferenceOnEnter: false,
            endConferenceOnExit: true,
            record: 'record-from-start',
            recordingStatusCallback: recordingCallbackUrl,
            recordingStatusCallbackEvent: 'completed',
            statusCallback: statusCallbackUrl,
            statusCallbackEvent: 'end'
        }, roomName);

        res.type('text/xml');
        res.send(twiml.toString());

        const initialLeg = {
            leg_type: 'inbound_park',
            status: 'parked',
            caller_id: clientCallerId,
            ddi: ddi,
            timestamp: new Date().toISOString()
        };

        // Build the call payload
        const callPayload = {
            call_id: callSid,
            callSid: callSid,
            callerId: clientCallerId,
            from: clientCallerId,
            ddi: ddi,
            roomId: roomName,
            status: 'parked',
            first_agent: null,
            last_agent: null,
            legs: [initialLeg],
            timestamp: new Date().toISOString(),

            // Routing details
            ou: routeRecord?.ou || null,
            delivery_plan: routeRecord?.delivery_plan || null,
            route_matched_key: routeRecord?.matched_key || null,

            // Client / Screen Pop details
            client_name: clientData?.client_name || routeRecord?.client_name || null,
            client_id: clientData?.client_id || routeRecord?.client_id || null,
            crm_url: clientData?.crm_url || null,
            account_tier: clientData?.account_tier || null,
            active_leads: clientData?.active_leads || [],
            screen_pop: routeRecord?.screen_pop || clientData?.extra_meta || null
        };

        // 1. Save Call State to Redis
        await redisClient.setEx(`call:${callSid}`, 43200, JSON.stringify(callPayload));

        // 2. Broadcast general incoming call event
        // if (io) {
        //     const clientCount = io.engine ? io.engine.clientsCount : 0;
        //     console.log(`📢 Emitting 'incoming_call' to ${clientCount} connected socket client(s)...`);
        //     io.emit('incoming_call', callPayload);
        // }

        // 3. Process the Delivery Plan (Runs asynchronously in background)
        deliveryPlanEngine.processDeliveryPlan(callPayload, routeRecord, req.app)
            .catch(err => console.error('[Delivery Plan Error]:', err));

    } catch (error) {
        console.error('[Inbound Call Error]:', error);
        if (!res.headersSent) {
            res.status(500).send('Internal Server Error');
        }
    }
};

// -------------------------------------------------------------------------
// WEBRTC VOICE CONNECT
// -------------------------------------------------------------------------
exports.handleVoiceConnect = async (req, res) => {
    try {
        const roomName = req.body.To || req.body.RoomName || req.query.To || 'default-room';
        const conferenceSid = req.body.ConferenceSid || req.body.conference_sid;

        const rawCaller = req.body.From || req.body.Caller || req.query.From || req.body.identity || '';
        const agentId = rawCaller.replace(/^client:/i, '') || 'Unknown Agent';
        const callSid = roomName.startsWith('Room_') ? roomName.replace('Room_', '') : roomName;

        if (callSid && callSid !== 'default-room') {
            if (conferenceSid) {
                await redisClient.setEx(`map:${conferenceSid}`, 43200, callSid);
            }

            const redisKey = `call:${callSid}`;
            const currentCallDataRaw = await redisClient.get(redisKey);

            if (currentCallDataRaw) {
                let currentCall = JSON.parse(currentCallDataRaw);
                const legs = Array.isArray(currentCall.legs) ? currentCall.legs : [];

                legs.push({
                    leg_type: 'agent_connect',
                    agent_id: agentId,
                    status: 'answered',
                    timestamp: new Date().toISOString()
                });

                const updatedCallData = {
                    ...currentCall,
                    status: 'answered',
                    first_agent: currentCall.first_agent || agentId,
                    last_agent: agentId,
                    legs: legs,
                    last_updated: new Date().toISOString(),
                    updated_by: `WebRTC (${agentId})`
                };

                await redisClient.setEx(redisKey, 43200, JSON.stringify(updatedCallData));

                const io = req.app.get('io');
                if (io) io.emit('call_updated', updatedCallData);
            }
        }

        const io = req.app.get('io');
        if (io && callSid) {
            io.emit('dismiss_incoming_call', { call_id: callSid, answered_by: agentId, reason: 'answered' });
        }

        const twiml = new twilio.twiml.VoiceResponse();
        const dial = twiml.dial();
        dial.conference({ startConferenceOnEnter: true, endConferenceOnExit: true }, roomName);

        res.type('text/xml');
        return res.send(twiml.toString());
    } catch (error) {
        console.error('[Voice Connect Error]:', error);
        const twiml = new twilio.twiml.VoiceResponse();
        const dial = twiml.dial();
        dial.conference({ startConferenceOnEnter: true, endConferenceOnExit: true }, 'default-room');

        res.type('text/xml');
        return res.send(twiml.toString());
    }
};

// -------------------------------------------------------------------------
// RECORDING COMPLETE
// -------------------------------------------------------------------------
exports.handleRecordingEvent = async (req, res) => {
    try {
        const conferenceSid = req.body.ConferenceSid || req.body.conference_sid;
        const recordingSid  = req.body.RecordingSid || req.body.recording_sid;
        const recordingUrl  = req.body.RecordingUrl || req.body.recording_url;
        const duration      = req.body.RecordingDuration || req.body.duration_seconds || 0;
        const friendlyName  = req.body.FriendlyName || req.body.RoomName || req.body.roomId || req.query.room || '';
        let targetCallSid   = req.body.CallSid || req.body.call_id || req.query.call_id;

        if (!targetCallSid && friendlyName.startsWith('Room_CA')) {
            targetCallSid = friendlyName.replace('Room_', '');
        }

        if (!targetCallSid && conferenceSid) {
            const mappedSid = await redisClient.get(`map:${conferenceSid}`);
            if (mappedSid) targetCallSid = mappedSid;
        }

        if (!targetCallSid && conferenceSid) {
            try {
                const confDetails = await twilioClient.conferences(conferenceSid).fetch();
                if (confDetails?.friendlyName?.startsWith('Room_CA')) {
                    targetCallSid = confDetails.friendlyName.replace('Room_', '');
                }
            } catch (apiErr) {
                console.warn('[Recording Event] Twilio API lookup warning:', apiErr.message);
            }
        }

        if (!targetCallSid) {
            return res.status(400).json({ error: 'Missing or unresolvable CallSid' });
        }

        if (conferenceSid && targetCallSid) {
            await redisClient.setEx(`map:${conferenceSid}`, 43200, targetCallSid);
        }

        const redisKey = `call:${targetCallSid}`;
        const currentCallRaw = await redisClient.get(redisKey);
        let callData = currentCallRaw ? JSON.parse(currentCallRaw) : {};

        callData.call_id = targetCallSid;
        if (conferenceSid) callData.conference_sid = conferenceSid;
        if (recordingSid)  callData.recording_sid  = recordingSid;
        if (recordingUrl)  callData.recording_url  = `${recordingUrl}.mp3`;
        callData.duration_seconds = parseInt(duration, 10) || callData.duration_seconds || 0;
        callData.recording_completed_at = new Date().toISOString();

        await redisClient.setEx(redisKey, 43200, JSON.stringify(callData));
        await saveCallToSuiteCRM(callData);

        return res.status(200).json({ success: true, call_id: targetCallSid });
    } catch (error) {
        return res.status(500).json({ error: 'Failed to process recording event' });
    }
};

// -------------------------------------------------------------------------
// STATUS CALLBACK
// -------------------------------------------------------------------------
exports.handleStatusCallback = async (req, res) => {
    try {
        const friendlyName = req.body.FriendlyName || req.body.RoomName || req.query.room || '';
        let targetCallSid = req.body.CallSid || req.body.callSid || req.body.call_id || req.query.call_id;

        if (!targetCallSid && friendlyName.startsWith('Room_CA')) {
            targetCallSid = friendlyName.replace('Room_', '');
        }

        const callStatus = (req.body.CallStatus || req.body.status || req.body.Status || 'completed').toLowerCase();
        const duration = req.body.CallDuration || req.body.duration || 0;
        const from = req.body.From || req.body.from;

        if (targetCallSid) {
            const redisKey = `call:${targetCallSid}`;
            const existingDataRaw = await redisClient.get(redisKey);
            let callData = existingDataRaw ? JSON.parse(existingDataRaw) : { legs: [] };

            const legs = Array.isArray(callData.legs) ? callData.legs : [];
            legs.push({
                leg_type: 'call_end',
                status: callStatus,
                timestamp: new Date().toISOString()
            });

            if (['completed', 'canceled', 'failed', 'no-answer'].includes(callStatus)) {
                const io = req.app.get('io');
                if (io) {
                    io.emit('dismiss_incoming_call', { call_id: targetCallSid, reason: 'terminated' });
                }
            }

            const updatedRecord = {
                ...callData,
                call_id: targetCallSid,
                status: callData.first_agent ? 'completed' : 'no-answer',
                from: from || callData.callerId || callData.from || 'Unknown',
                duration_seconds: parseInt(duration, 10) || callData.duration_seconds || 0,
                legs: legs,
                last_updated: new Date().toISOString()
            };

            await redisClient.setEx(redisKey, 43200, JSON.stringify(updatedRecord));
            await redisClient.sRem('active_calls', targetCallSid);
            await saveCallToSuiteCRM(updatedRecord);
        }

        return res.status(200).send('<Response/>');
    } catch (error) {
        return res.status(500).json({ error: 'Internal server error' });
    }
};


// -------------------------------------------------------------------------
// Probe Call Acceptance Handler (TwiML for PSTN Probe Calls)
// -------------------------------------------------------------------------
// * This endpoint is called by Twilio when an agent presses "1" on a PSTN probe call.
// * It bridges the agent into the caller's conference room if accepted.
// * Handles agent pressing "1" on a PSTN probe call to accept the customer
// -------------------------------------------------------------------------
exports.handleAcceptProbe = async (req, res) => {
    const digits = req.body.Digits;
    const roomId = req.query.roomId;
    const callId = req.query.callId;
    const agentName = req.query.agentName || 'PSTN Agent';
    const currentProbeSid = req.body.CallSid; // The Twilio CallSid of this specific probe call

    const twiml = new twilio.twiml.VoiceResponse();

    if (digits === '1' && roomId && callId) {
        // Attempt atomic claim
        const claimResult = await deliveryPlanEngine.claimAndCleanupCall(
            callId, 
            agentName, 
            'pstn', 
            req.app, 
            currentProbeSid
        );

        if (claimResult.success) {
            console.log(`✅ PSTN Call claimed by ${agentName}. Bridging into ${roomId}...`);
            const dial = twiml.dial();
            dial.conference({
                startConferenceOnEnter: true,
                endConferenceOnExit: true
            }, roomId);
        } else {
            console.log(`❌ PSTN Press 1 rejected: ${claimResult.reason}`);
            twiml.say('Sorry, this call has already been answered by another agent.');
            twiml.hangup();
        }
    } else {
        console.log('❌ Agent declined PSTN probe or entered invalid key.');
        twiml.say('Call declined.');
        twiml.hangup();
    }

    res.type('text/xml');
    res.send(twiml.toString());
};
exports.acceptCallWebRTC = async (req, res) => {
    try {
        const { callId, agentId } = req.body;

        const claimResult = await deliveryPlanEngine.claimAndCleanupCall(
            callId,
            agentId || 'WebRTC Agent',
            'webrtc',
            req.app
        );

        return res.json(claimResult);
    } catch (error) {
        console.error('[WebRTC Accept Error]:', error);
        return res.status(500).json({ error: 'Failed to process WebRTC call claim' });
    }
};