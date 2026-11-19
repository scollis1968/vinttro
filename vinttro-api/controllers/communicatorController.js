// vinttro-api/controllers/communicatorController.js
const twilio = require('twilio');
const redisClient = require('../services/redis');
const suitecrmService = require('../services/suitecrm');
const communicatorRouting = require('../services/communicatorRouting');
const clientCacheService = require('../services/clientCacheService');
const deliveryPlanEngine = require('../services/deliveryPlanEngine');
const MapperFactory = require('../mappers/mapperFactory');

const ACCOUNT_SID = process.env.TWILIO_ACCOUNT_SID;
const API_KEY_SID = process.env.TWILIO_API_KEY_SID;
const API_KEY_SECRET = process.env.TWILIO_API_KEY_SECRET;
const TWIML_APP_SID = process.env.TWILIO_TWIML_APP_SID || process.env.TWIML_APP_SID;

const twilioClient = twilio(API_KEY_SID, API_KEY_SECRET, { accountSid: ACCOUNT_SID });

// Add right after: const twilioClient = twilio(API_KEY_SID, API_KEY_SECRET, { accountSid: ACCOUNT_SID });
function getBaseUrl(req) {
    if (process.env.PUBLIC_API_URL) {
        return process.env.PUBLIC_API_URL.replace(/\/+$/, '');
    }
    const proto = req.get('x-forwarded-proto') || req.protocol || 'https';
    const host = req.get('host');
    return `${proto}://${host}`;
}

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
    console.log('📥 [DEBUG Webhook]', req.path, JSON.stringify(req.body));
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

// --- Inside exports.handleInboundCall_Old ---
    const baseUrl = getBaseUrl(req);
    const recordingCallbackUrl = `${baseUrl}/api/communicator/recording-event?call_id=${encodeURIComponent(callSid)}&room=${encodeURIComponent(roomName)}`;
    const statusCallbackUrl = `${baseUrl}/api/communicator/status-callback?call_id=${encodeURIComponent(callSid)}&room=${encodeURIComponent(roomName)}`;

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

        const baseUrl = getBaseUrl(req);
        const recordingCallbackUrl = `${baseUrl}/api/communicator/recording-event?call_id=${encodeURIComponent(callSid)}&room=${encodeURIComponent(roomName)}`;
        const statusCallbackUrl = `${baseUrl}/api/communicator/status-callback?call_id=${encodeURIComponent(callSid)}&room=${encodeURIComponent(roomName)}`;


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

        console.log('📤 [DEBUG Inbound TwiML]:', twiml.toString());
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
            account_name: clientData?.account_name || null,
            contact_id: clientData?.contact_id || null,
            client_matches: clientData?.matches || [], // <-- ADD THIS LINE
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
// WEBRTC VOICE CONNECT (Handles both Inbound Room Joins & Outbound Calls)
// -------------------------------------------------------------------------
exports.handleVoiceConnect = async (req, res) => {
    try {
        const io = req.app.get('io');
        const target = (req.body.To || req.body.RoomName || req.query.To || '').trim();
        const callSid = req.body.CallSid;
        const conferenceSid = req.body.ConferenceSid || req.body.conference_sid;

        const rawCaller = req.body.From || req.body.Caller || req.query.From || req.body.identity || '';
        const agentId = rawCaller.replace(/^client:/i, '') || 'Unknown Agent';

        const isPhoneNumber = /^\+?[0-9\s\-()]{7,20}$/.test(target);

        // =================================================================
        // BRANCH A: OUTBOUND PSTN CALL (From Dial Pad or Click-to-Dial)
        // =================================================================
        if (isPhoneNumber) {
            let formattedTo = target.replace(/[^\d+]/g, '');
            if (formattedTo.startsWith('0')) formattedTo = '+44' + formattedTo.slice(1);
            if (!formattedTo.startsWith('+')) formattedTo = '+' + formattedTo;

            // Custom CRM metadata passed from twilioDevice.connect({ params })
            const clientId = req.body.clientId || null;
            const contactId = req.body.contactId || null;
            const opportunityId = req.body.opportunityId || null;
            const taskId = req.body.taskId || null;
            const clientName = req.body.clientName || null;

            const baseUrl = getBaseUrl(req);
            const recordingCallbackUrl = `${baseUrl}/api/communicator/recording-event?call_id=${encodeURIComponent(callSid)}`;
            const statusCallbackUrl = `${baseUrl}/api/communicator/status-callback?call_id=${encodeURIComponent(callSid)}`;
            const outboundCallerId = process.env.TWILIO_CALLER_ID || process.env.TWILIO_PHONE_NUMBER;

            if (callSid) {
                const callPayload = {
                    call_id: callSid,
                    callSid: callSid,
                    direction: 'Outbound',
                    callerId: outboundCallerId || agentId,
                    from: outboundCallerId || agentId,
                    to: formattedTo,
                    status: 'ringing',
                    first_agent: agentId,
                    last_agent: agentId,
                    client_id: clientId,
                    contact_id: contactId,
                    opportunity_id: opportunityId,
                    task_id: taskId,
                    client_name: clientName,
                    legs: [{
                        leg_type: 'outbound_dial',
                        agent_id: agentId,
                        to: formattedTo,
                        status: 'ringing',
                        timestamp: new Date().toISOString()
                    }],
                    timestamp: new Date().toISOString(),
                    last_updated: new Date().toISOString()
                };

                await redisClient.setEx(`call:${callSid}`, 43200, JSON.stringify(callPayload));
                await redisClient.sAdd('active_calls', callSid);
                if (io) io.emit('call_updated', callPayload);
            }

            const twiml = new twilio.twiml.VoiceResponse();
            const dial = twiml.dial({
                callerId: outboundCallerId,
                record: 'record-from-answer-dual',
                recordingStatusCallback: recordingCallbackUrl,
                recordingStatusCallbackEvent: 'completed'
            });

            dial.number({
                statusCallback: statusCallbackUrl,
                statusCallbackEvent: ['initiated', 'ringing', 'answered', 'completed']
            }, formattedTo);

            console.log('📤 [DEBUG Outbound TwiML]:', twiml.toString());
            res.type('text/xml');
            return res.send(twiml.toString());
        }

        // =================================================================
        // BRANCH B: INBOUND CONFERENCE BRIDGE (Existing Flow)
        // =================================================================
        const roomName = target || 'default-room';
        const inboundCallSid = roomName.startsWith('Room_') ? roomName.replace('Room_', '') : roomName;

        if (inboundCallSid && inboundCallSid !== 'default-room') {
            if (conferenceSid) {
                await redisClient.setEx(`map:${conferenceSid}`, 43200, inboundCallSid);
            }

            const redisKey = `call:${inboundCallSid}`;
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
                if (io) io.emit('call_updated', updatedCallData);
            }
        }

        if (io && inboundCallSid) {
            io.emit('dismiss_incoming_call', { call_id: inboundCallSid, answered_by: agentId, reason: 'answered' });
        }

        const twiml = new twilio.twiml.VoiceResponse();
        const dial = twiml.dial();
        dial.conference({ startConferenceOnEnter: true, endConferenceOnExit: true }, roomName);

        res.type('text/xml');
        return res.send(twiml.toString());
    } catch (error) {
        console.error('[Voice Connect Error]:', error);
        const twiml = new twilio.twiml.VoiceResponse();
        twiml.say('Sorry, we could not connect your call.');
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
    console.log('📥 [DEBUG Webhook]', req.path, JSON.stringify(req.body));
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
// Add to exports in communicatorController.js

exports.syncSuiteCRMClients = async (req, res) => {
    try {
        // Trigger background sync or wait for result
        const result = await clientCacheService.syncClientsFromSuiteCRM();
        return res.status(200).json({ success: true, ...result });
    } catch (error) {
        console.error('[SuiteCRM Client Sync Controller Error]:', error);
        return res.status(500).json({ error: 'Failed to sync clients from SuiteCRM', details: error.message });
    }
};
// Add to exports in communicatorController.js
exports.updateCallContact = async (req, res) => {
    try {
        let { callId, agentId, contactId, clientId, contactName, accountName } = req.body;

        // Fallback: if the dashboard doesn't have callId, find the active call for this agent
        if (!callId && agentId) {
            const activeSids = await redisClient.sMembers('active_calls');
            for (const sid of activeSids) {
                const raw = await redisClient.get(`call:${sid}`);
                if (!raw) continue;
                const parsed = JSON.parse(raw);
                if (parsed.last_agent === agentId || parsed.first_agent === agentId) {
                    callId = sid;
                    break;
                }
            }
        }

        if (!callId) {
            return res.status(200).json({ success: false, skipped: true, reason: 'No active call' });
        }

        const redisKey = `call:${callId}`;
        const currentCallDataRaw = await redisClient.get(redisKey);

        if (!currentCallDataRaw) {
            return res.status(404).json({ error: 'Call record not found in Redis' });
        }

        const currentCall = JSON.parse(currentCallDataRaw);
        const previousClientId = currentCall.client_id || null;

        currentCall.contact_id = contactId || null;
        currentCall.client_id = clientId || null;
        currentCall.client_name = contactName || accountName || currentCall.client_name;
        currentCall.account_name = accountName || currentCall.account_name;

        // Clear stale parent overrides so saveCallRecord links to the newly selected Account
        if (clientId && clientId !== previousClientId) {
            currentCall.opportunity_id = null;
            currentCall.task_id = null;

            const legs = Array.isArray(currentCall.legs) ? currentCall.legs : [];
            legs.push({
                leg_type: 'client_switch',
                agent_id: agentId || currentCall.last_agent || null,
                client_id: clientId,
                account_name: accountName || contactName || '',
                timestamp: new Date().toISOString()
            });
            currentCall.legs = legs;
        }

        await redisClient.setEx(redisKey, 43200, JSON.stringify(currentCall));

        // If the agent switched client during post-call wrap-up after SuiteCRM was already written, update it
        if (currentCall.suitecrm_call_id && clientId) {
            await suitecrmService.updateRecord('Calls', currentCall.suitecrm_call_id, {
                parent_type: 'Accounts',
                parent_id: clientId
            });
        }

        return res.status(200).json({ success: true, callId, clientId });
    } catch (error) {
        console.error('[Update Call Contact Error]:', error);
        return res.status(500).json({ error: 'Failed to update call contact' });
    }
};

// Add to vinttro-api/controllers/communicatorController.js

/**
 * POST /communicator/clients/:id/summary
 * Uploads client summary payload to Redis
 */
exports.saveClientSummary = async (req, res) => {
    try {
        const { id } = req.params;
        let payload = req.body;

        if (!payload || Object.keys(payload).length === 0) {
            return res.status(400).json({ success: false, message: 'Payload body is empty' });
        }

        // Optional: If source header is present, normalize using MapperFactory
        const source = req.headers['x-source-system']; // e.g., 'acturis' or 'suitecrm'
        if (source) {
            try {
                const mapper = MapperFactory.getMapper(source, 'client');
                payload = mapper.toDashboardSummary(payload);
            } catch (mapperErr) {
                console.warn(`[MapperFactory] Bypass mapping: ${mapperErr.message}`);
            }
        }

        const result = await clientCacheService.saveClientSummary(id, payload);
        return res.status(200).json({ success: true, data: result });
    } catch (err) {
        console.error('[saveClientSummary Error]:', err.message);
        return res.status(500).json({ success: false, error: err.message });
    }
};

/**
 * GET /communicator/clients/:id/summary
 * Returns the cached dashboard JSON for the React screen
 */
exports.getClientSummary = async (req, res) => {
    try {
        const { id } = req.params;
        const summary = await clientCacheService.getClientSummary(id);

        if (!summary) {
            return res.status(404).json({ 
                success: false, 
                message: `No cached summary found for client ID: ${id}` 
            });
        }

        return res.status(200).json(summary);
    } catch (err) {
        console.error('[getClientSummary Error]:', err.message);
        return res.status(500).json({ success: false, error: err.message });
    }
};



const CHANNEL_TO_MODULE = {
  call: 'Calls',
  email: 'Emails',
  whatsapp: 'Notes',
  sms: 'Notes',
  note: 'Notes',
};

// GET /api/communicator/events?accountId=...&channel=call,email&limit=25
exports.getEvents = async (req, res) => {
  try {
    const { accountId, channel, limit = 25 } = req.query;
    const maxItems = Math.min(parseInt(limit, 10) || 25, 100);
    const mapper = MapperFactory.getMapper('suitecrm', 'event');

    const requestedChannels = channel
      ? channel.split(',').map((c) => c.trim().toLowerCase())
      : ['call', 'email'];

    const modules = [...new Set(
      requestedChannels.map((c) => CHANNEL_TO_MODULE[c]).filter(Boolean)
    )];

    const results = await Promise.all(
      modules.map(async (moduleName) => {
        const params = {
          'page[number]': '1',
          'page[size]': String(maxItems),
          sort: '-date_entered',
        };

        if (accountId) {
          params['filter[parent_id][eq]'] = accountId;
        }

        const response = await suitecrmService.getModuleRecords(moduleName, params);
        const rows = response?.data || [];

        return rows.map((row) => mapper.mapRecord(moduleName, row, { includeDetails: false }));
      })
    );

    const merged = results
      .flat()
      .filter((item) => !channel || requestedChannels.includes(item.channel))
      .sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp))
      .slice(0, maxItems);

    return res.json({ events: merged });
  } catch (err) {
    console.error('Error fetching unified events:', err);
    return res.status(500).json({ error: 'Failed to load communication events', details: err.message });
  }
};

// GET /api/communicator/events/:module/:id
exports.getEventDetails = async (req, res) => {
  try {
    const { module: moduleName, id } = req.params;
    if (!['Calls', 'Emails', 'Notes'].includes(moduleName)) {
      return res.status(400).json({ error: 'Invalid event module' });
    }

    const mapper = MapperFactory.getMapper('suitecrm', 'event');
    const response = await suitecrmService.getRecordById(moduleName, id);

    if (!response?.data) {
      return res.status(404).json({ error: 'Event not found' });
    }

    const event = mapper.mapRecord(moduleName, response.data, { includeDetails: true });
    return res.json({ event });
  } catch (err) {
    console.error('Error fetching event detail:', err);
    return res.status(500).json({ error: 'Failed to load event details', details: err.message });
  }
};

// GET /api/communicator/recordings/:recordingSid
exports.streamRecording = async (req, res) => {
  try {
    const { recordingSid } = req.params;
    if (!/^RE[a-f0-9]{32}$/i.test(recordingSid)) {
      return res.status(400).json({ error: 'Invalid recording SID' });
    }

    const authUser = process.env.TWILIO_AUTH_TOKEN ? ACCOUNT_SID : API_KEY_SID;
    const authPass = process.env.TWILIO_AUTH_TOKEN || API_KEY_SECRET;
    const twilioUrl = `https://api.twilio.com/2010-04-01/Accounts/${ACCOUNT_SID}/Recordings/${recordingSid}.mp3`;

    const headers = {
      Authorization: `Basic ${Buffer.from(`${authUser}:${authPass}`).toString('base64')}`,
    };
    if (req.headers.range) {
      headers.Range = req.headers.range;
    }

    const upstream = await fetch(twilioUrl, { headers });
    if (!upstream.ok && upstream.status !== 206) {
      return res.status(upstream.status).end();
    }

    res.status(upstream.status);
    ['content-type', 'content-length', 'content-range', 'accept-ranges'].forEach((h) => {
      const val = upstream.headers.get(h);
      if (val) res.setHeader(h, val);
    });

    const { Readable } = require('stream');
    return Readable.fromWeb(upstream.body).pipe(res);
  } catch (err) {
    console.error('Error streaming Twilio recording:', err);
    return res.status(500).end();
  }
};