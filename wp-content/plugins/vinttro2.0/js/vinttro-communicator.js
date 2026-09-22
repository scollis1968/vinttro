jQuery(document).ready(function($) {
    
    // Ensure configuration object from wp_localize_script exists
    if (typeof vinttroConfig === 'undefined') {
        console.error('vinttroConfig is missing. Check script localization.');
        return;
    }

    const NODE_URL = vinttroConfig.nodeApiUrl; // https://services.uat.vinttro.co.uk
    const AGENT_ID = vinttroConfig.agentId;   // Current logged-in user email
    
    let activeCallPayload = null;

    // -------------------------------------------------------------------------
    // 1. INITIALIZE SOCKET.IO CONNECTION TO NODE API
    // -------------------------------------------------------------------------
    const socket = io(NODE_URL, {
        transports: ['websocket', 'polling']
    });

    socket.on('connect', () => {
        console.log(`[Socket.io] Connected successfully. Socket ID: ${socket.id}`);
        
        // Update UI Banner status
        $('#rtc-status-message')
            .removeClass('offline error')
            .addClass('success')
            .text(`Online — Connected as ${vinttroConfig.agentName}`);

        // Join private Agent Room so Node can send targeted calls to this agent
        socket.emit('join_room', AGENT_ID);
        socket.emit('join_room', 'agent_wrtc_1'); // Also join stub testing room
    });

    socket.on('disconnect', () => {
        console.warn('[Socket.io] Disconnected from Node Controller');
        $('#rtc-status-message')
            .removeClass('success info')
            .addClass('offline')
            .text('Offline. Reconnecting to central controller...');
    });

    // -------------------------------------------------------------------------
    // 2. LISTEN FOR INBOUND CALL EVENTS FROM NODE
    // -------------------------------------------------------------------------
    
    // Listening for Global Queue Events or Direct Agent Notifications
    socket.on('incoming_call_queue', handleIncomingCallEvent);
    socket.on('incoming_call', handleIncomingCallEvent);

    function handleIncomingCallEvent(payload) {
        console.log('[Socket.io Event Received] Incoming Call Payload:', payload);
        
        activeCallPayload = payload;

        // A. Pop-up caller identity and room details
        $('#incoming-caller-id').text(payload.callerId || 'Unknown Caller');
        $('#incoming-call-subtext').text(`Inbound Call Waiting | Room: ${payload.roomId}`);
        
        // B. Pre-fill room input box automatically
        $('#vinttro-room-id').val(payload.roomId);

        // C. Show the call card modal
        $('#vinttro-incoming-call-card').slideDown(200);

        // Optional: Play audio chime
        playRingtone();
    }

    // Listen for Call Status Deltas (e.g., Caller hung up before answer)
    socket.on('call_updated', (data) => {
        console.log('[Socket.io Event] Call state updated:', data);

        if (activeCallPayload && data.call_id === activeCallPayload.callSid) {
            if (['completed', 'canceled', 'failed'].includes(data.status)) {
                // Hide incoming popup if caller hangs up while ringing
                $('#vinttro-incoming-call-card').slideUp(200);
                activeCallPayload = null;
            }
        }
    });

    // -------------------------------------------------------------------------
    // 3. AGENT INTERACTION HANDLERS
    // -------------------------------------------------------------------------
    
    // Accept Call Button Clicked
    $('#vinttro-accept-incoming').on('click', function() {
        if (!activeCallPayload) return;

        console.log('[Accept] Joining WebRTC Room:', activeCallPayload.roomId);
        
        // 1. Tell Twilio Voice SDK to open WebRTC audio stream to the room
        if (typeof Twilio !== 'undefined' && Twilio.Device) {
            Twilio.Device.connect({
                params: {
                    RoomName: roomName,
                    To: roomName
                }
            });
        } else if (window.vinttroTwilioDevice) {
            // If your device instance is saved on a global window object
            window.vinttroTwilioDevice.connect({
                params: { RoomName: roomName }
            });
        } else {
            console.error('❌ Twilio Voice Device is not initialized in the browser!');
        }

        // Hide Pop-up banner
        $('#vinttro-incoming-call-card').slideUp(200);

        // Update status text
        $('#rtc-status-message')
            .removeClass('offline info success')
            .addClass('info')
            .text(`Connecting to Call Room: ${activeCallPayload.roomId}...`);

        // Trigger your existing "Make Call" / "Join Room" button click logic
        $('#vinttro-rtc-connect').trigger('click');
    });

    // Dismiss Call Button Clicked
    $('#vinttro-reject-incoming').on('click', function() {
        $('#vinttro-incoming-call-card').slideUp(200);
        activeCallPayload = null;
    });

    // Helper Audio Alert
    function playRingtone() {
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(440, audioCtx.currentTime); // A4 tone
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