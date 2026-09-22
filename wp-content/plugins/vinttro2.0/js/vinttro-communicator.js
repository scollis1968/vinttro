jQuery(document).ready(function($) {
    console.log("👉 VINTTRO UNIFIED COMMUNICATOR ENGINE ACTIVE");

    const NODE_URL = (typeof vinttroConfig !== 'undefined' && vinttroConfig.nodeApiUrl) 
        ? vinttroConfig.nodeApiUrl 
        : 'https://services.uat.vinttro.co.uk';

    const AGENT_ID = (typeof vinttroConfig !== 'undefined' && vinttroConfig.agentId) 
        ? vinttroConfig.agentId 
        : 'agent_dev_1';

    let activeCallPayload = null;

    // ==========================================================
    // 1. INITIALIZE WEBRTC VOICE DEVICE (Twilio.Device)
    // ==========================================================
    async function initTwilioVoiceDevice() {
        try {
            const tokenUrl = `${NODE_URL}/api/communicator/token?identity=${encodeURIComponent(AGENT_ID)}`;
            const response = await fetch(tokenUrl);
            if (!response.ok) throw new Error(`Token fetch failed: HTTP ${response.status}`);
            
            const data = await response.json();
            
            if (typeof Twilio !== 'undefined' && Twilio.Device) {
                window.vinttroTwilioDevice = new Twilio.Device(data.token, {
                    logLevel: 1,
                    codecPreferences: ['opus', 'pcmu']
                });

                window.vinttroTwilioDevice.register();

                window.vinttroTwilioDevice.on('registered', () => {
                    console.log('%c✅ Twilio Voice WebRTC Device Registered & Ready!', 'color: #38a169; font-weight: bold;');
                });

                window.vinttroTwilioDevice.on('error', (err) => {
                    console.error('❌ Twilio Voice Device Error:', err);
                });
            } else {
                console.warn('⚠️ Twilio Voice SDK JS library is missing on this page.');
            }
        } catch (err) {
            console.error('❌ Failed to initialize Twilio Voice Device:', err.message);
        }
    }

    initTwilioVoiceDevice();

    // ==========================================================
    // 2. INITIALIZE SOCKET.IO CONNECTION TO NODE API
    // ==========================================================
    const socket = io(NODE_URL, {
        transports: ['websocket', 'polling']
    });

    socket.on('connect', () => {
        console.log(`[Socket.io] Connected successfully. Socket ID: ${socket.id}`);
        
        $('#rtc-status-message')
            .removeClass('offline error')
            .addClass('success')
            .text(`Online — Connected as ${AGENT_ID}`);

        socket.emit('join_room', AGENT_ID);
    });

    socket.on('disconnect', () => {
        console.warn('[Socket.io] Disconnected from Central Controller');
        $('#rtc-status-message')
            .removeClass('success info')
            .addClass('offline')
            .text('Offline. Reconnecting to central controller...');
    });

    // ==========================================================
    // 3. LISTEN FOR REAL-TIME INBOUND CALL EVENTS
    // ==========================================================
    socket.on('incoming_call', handleIncomingCallEvent);

    function handleIncomingCallEvent(payload) {
        console.log('🚨 [Socket.io Event] Incoming Call:', payload);
        activeCallPayload = payload;

        $('#incoming-caller-id').text(payload.callerId || 'Unknown Caller');
        $('#incoming-call-subtext').text(`Inbound Call Waiting | Room: ${payload.roomId}`);
        $('#vinttro-room-id').val(payload.roomId);
        $('#vinttro-incoming-call-card').slideDown(200);

        playRingtone();
    }

    socket.on('call_updated', (data) => {
        if (activeCallPayload && data.call_id === activeCallPayload.callSid) {
            if (['completed', 'canceled', 'failed'].includes(data.status)) {
                $('#vinttro-incoming-call-card').slideUp(200);
                activeCallPayload = null;
            }
        }
    });

    // ==========================================================
    // 4. AGENT INTERACTION HANDLERS (ANSWER & DISCONNECT)
    // ==========================================================
    $('#vinttro-accept-incoming').on('click', function() {
        if (!activeCallPayload) return;

        const targetRoomName = activeCallPayload.roomId;
        console.log('[Accept] Dialing into Conference Room:', targetRoomName);

        if (window.vinttroTwilioDevice) {
            window.vinttroTwilioDevice.connect({
                params: { To: targetRoomName }
            });
            console.log('✅ Agent WebRTC audio connecting to conference...');
        } else {
            console.error('❌ Twilio Voice Device is not initialized!');
        }

        $('#vinttro-incoming-call-card').slideUp(200);
        $('#rtc-status-message')
            .removeClass('offline info success')
            .addClass('info')
            .text(`Active Call in Room: ${targetRoomName}`);
    });

    $('#vinttro-reject-incoming').on('click', function() {
        $('#vinttro-incoming-call-card').slideUp(200);
        activeCallPayload = null;
    });

    $('#vinttro-rtc-disconnect').on('click', function() {
        if (window.vinttroTwilioDevice) {
            window.vinttroTwilioDevice.disconnectAll();
        }
        $('#rtc-status-message')
            .removeClass('info success')
            .addClass('offline')
            .text('Call ended. Online and ready.');
    });

    // Ringtone generator
    function playRingtone() {
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(440, audioCtx.currentTime);
            gain.gain.setValueAtTime(0.1, audioCtx.currentTime);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.4);
        } catch (e) {
            console.log('Audio chime blocked by browser autoplay policy.');
        }
    }
});