jQuery(document).ready(function($) {
    console.log("👉 VINTTRO UNIFIED COMMUNICATOR ENGINE ACTIVE (HEADER CTI ENABLED)");

    const NODE_URL = (typeof vinttroConfig !== 'undefined' && vinttroConfig.nodeApiUrl) 
        ? vinttroConfig.nodeApiUrl 
        : 'https://services.uat.vinttro.co.uk';

    const AGENT_ID = (typeof vinttroConfig !== 'undefined' && vinttroConfig.agentId) 
        ? vinttroConfig.agentId 
        : 'agent_dev_1';

    let activeCallPayload = null;
    let callTimerInterval = null;
    let callStartTime = null;

    // Helper: Build Multi-Match Selector HTML
    function buildMultiMatchSelectHtml(matches, selectedIdx = '') {
        const isAssigned = selectedIdx !== '' && selectedIdx !== null && selectedIdx !== undefined;
        const statusClass = isAssigned ? 'assigned' : 'unassigned';
        
        const optionsHtml = matches.map((m, index) => {
            const label = m.account_name ? `${m.contact_name} (${m.account_name})` : m.contact_name;
            const isSelected = String(index) === String(selectedIdx) ? 'selected' : '';
            return `<option value="${index}" ${isSelected}>${label}</option>`;
        }).join('');

        const placeholderSelected = !isAssigned ? 'selected' : '';

        return `
            <select class="contact-select-dropdown vinttro-contact-selector ${statusClass}">
                <option value="" disabled ${placeholderSelected}>-- ⚠️ Select Contact (${matches.length} Matches) --</option>
                ${optionsHtml}
            </select>
        `;
    }

    // ==========================================================
    // 1. CTI HEADER STATE & TIMER HELPERS
    // ==========================================================
    function setCallBarState(state, data = {}) {
        const $bar =$('#vinttro-header-call-bar');
        const $idle =$('.segment-idle');
        const $ringing =$('.segment-ringing');
        const $active =$('.segment-active');

        $bar.removeClass('state-idle state-ringing state-active').addClass(`state-${state}`);

        if (state === 'idle') {
            $idle.show();
            $ringing.hide();$active.hide();
            stopCallTimer();
            $('#vinttro-hdr-mute').html('🎙️ Mute').data('muted', false);
            activeCallPayload = null;
            window.activeRingingCallId = null;
        } else if (state === 'ringing') {
            $idle.hide(); 
            $active.hide();$ringing.show();
            
            if (data.callerHtml) {
                $('#vinttro-hdr-caller-id').html(data.callerHtml);
            } else {
                $('#vinttro-hdr-caller-id').text(data.callerId || 'Unknown Caller');
            }
        } else if (state === 'active') {
            $idle.hide(); 
            $ringing.hide();$active.show();
            
            // Retain dropdown in header during active call if multi-match
            if (data.callerHtml) {
                $('#vinttro-hdr-active-party').html(data.callerHtml);
            } else {
                $('#vinttro-hdr-active-party').text(data.callerId || 'In Call');
            }
            startCallTimer();
        }
    }

    function startCallTimer() {
        stopCallTimer();
        callStartTime = Date.now();
        $('#vinttro-hdr-timer').text('00:00');
        callTimerInterval = setInterval(() => {
            const elapsed = Math.floor((Date.now() - callStartTime) / 1000);
            const mins = String(Math.floor(elapsed / 60)).padStart(2, '0');
            const secs = String(elapsed % 60).padStart(2, '0');
            $('#vinttro-hdr-timer').text(`${mins}:${secs}`);
        }, 1000);
    }

    function stopCallTimer() {
        if (callTimerInterval) {
            clearInterval(callTimerInterval);
            callTimerInterval = null;
        }
        $('#vinttro-hdr-timer').text('00:00');
    }

    function updateWebRTCHealthUI(status, label) {
        const $dot =$('#vinttro-health-dot');
        const $text =$('#vinttro-health-text');
        $dot.removeClass('health-online health-connecting health-offline').addClass(`health-${status}`);
        $text.text(label);
    }

    // ==========================================================
    // 2. INITIALIZE WEBRTC VOICE DEVICE (Twilio.Device)
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
                    updateWebRTCHealthUI('online', 'RTC Ready');
                    populateAudioDevices();
                });

                window.vinttroTwilioDevice.on('error', (err) => {
                    console.error('❌ Twilio Voice Device Error:', err);
                    updateWebRTCHealthUI('offline', 'RTC Error');
                });

                window.vinttroTwilioDevice.on('disconnect', () => {
                    console.log('🔌 Twilio WebRTC Connection Disconnected');
                    setCallBarState('idle');
                });
            } else {
                console.warn('⚠️ Twilio Voice SDK JS library is missing on this page.');
                updateWebRTCHealthUI('offline', 'No SDK');
            }
        } catch (err) {
            console.error('❌ Failed to initialize Twilio Voice Device:', err.message);
            updateWebRTCHealthUI('offline', 'Auth Failed');
        }
    }

    initTwilioVoiceDevice();

    // ==========================================================
    // 3. INITIALIZE SOCKET.IO CONNECTION TO NODE API
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
    // 4. REAL-TIME TELEPHONY SOCKET EVENTS
    // ==========================================================

    socket.on('incoming_call', handleIncomingCallEvent);

    function getCallDisplayName(payload) {
        if (!payload) return 'Unknown Caller';

        const name = payload.client_name 
                || payload.clientName 
                || payload.caller_name 
                || payload.callerName 
                || payload.contact_name 
                || payload.contactName;

        if (name && name.trim() !== '') {
            return name;
        }

        return payload.callerId || payload.from || payload.fromNumber || 'Unknown Caller';
    }

    function handleIncomingCallEvent(payload) {
        console.log('🚨 [Socket.io Event] Incoming Call Payload:', payload);
        activeCallPayload = payload;
        window.activeRingingCallId = payload.call_id || payload.callSid;

        const matches = payload.client_matches || [];
        const isMultiMatch = matches.length > 1;

        if (isMultiMatch) {
            activeCallPayload.selected_contact_id = null;
            activeCallPayload.selected_client_id = null;
            activeCallPayload.selected_index = '';

            const selectHtml = buildMultiMatchSelectHtml(matches, '');

            // Update CTI Header Bar & Legacy Communicator Card
            setCallBarState('ringing', { callerHtml: selectHtml });
            $('#incoming-caller-id').html(selectHtml);

            // Bind change listener on selector dropdown
            $(document).off('change', '.vinttro-contact-selector').on('change', '.vinttro-contact-selector', async function() {
                const selectedIdx = $(this).val();
                const selected = matches[selectedIdx];
                if (selected) {
                    activeCallPayload.selected_index = selectedIdx;
                    activeCallPayload.selected_contact_id = selected.contact_id;
                    activeCallPayload.selected_client_id = selected.client_id;
                    activeCallPayload.client_name = selected.contact_name;
                    activeCallPayload.account_name = selected.account_name;

                    // Sync selector UI across header bar and card
                    $('.vinttro-contact-selector')
                        .val(selectedIdx)
                        .removeClass('unassigned')
                        .addClass('assigned');

                    console.log('👤 [Communicator] Agent selected contact:', selected);

                    // Push selection to Node API so Redis updates call state in real-time
                    const callId = activeCallPayload ? (activeCallPayload.call_id || activeCallPayload.callSid) : null;
                    if (callId) {
                        try {
                            await fetch(`${NODE_URL}/api/communicator/update-call-contact`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    callId: callId,
                                    contactId: selected.contact_id,
                                    clientId: selected.client_id,
                                    contactName: selected.contact_name,
                                    accountName: selected.account_name
                                })
                            });
                        } catch (err) {
                            console.warn('⚠️ Could not sync contact selection to backend:', err.message);
                        }
                    }
                }
            });

            $('#incoming-call-subtext').text(`⚠️ ${matches.length} Matches | Select Contact`);
        } else {
            // Single Match or Unknown Caller
            const displayName = getCallDisplayName(payload);
            
            activeCallPayload.selected_contact_id = payload.contact_id || null;
            activeCallPayload.selected_client_id = payload.client_id || null;

            setCallBarState('ringing', { callerId: displayName });
            $('#incoming-caller-id').text(displayName);
            $('#incoming-call-subtext').text(`Inbound Call Waiting | Room: ${payload.roomId}`);
        }

        $('#vinttro-room-id').val(payload.roomId);
        $('#vinttro-incoming-call-card').slideDown(200);

        playRingtone();
    }

    socket.on('call_updated', (data) => {
        if (activeCallPayload && data.call_id === (activeCallPayload.call_id || activeCallPayload.callSid)) {
            if (['completed', 'canceled', 'failed'].includes(data.status)) {
                setCallBarState('idle');
                $('#vinttro-incoming-call-card').slideUp(200);
            }
        }
    });
    
    socket.on('dismiss_incoming_call', function(data) {
        console.log('[Communicator] Received dismissal event:', data);

        stopRingtoneAudio();
        $('#vinttro-incoming-call-card').slideUp(200);

        // 💡 CRITICAL FIX: If this agent answered the call, DO NOT reset header call bar to idle!
        if (data.reason === 'answered' && (data.answered_by === AGENT_ID || data.answered_by === 'WebRTC Agent')) {
            console.log('✅ Call answered by current agent. Retaining active call state.');
            return;
        }

        const currentCallId = activeCallPayload ? (activeCallPayload.call_id || activeCallPayload.callSid) : null;
        if (window.activeRingingCallId === data.call_id || currentCallId === data.call_id) {
            setCallBarState('idle');
        }
    });

    // ==========================================================
    // 5. CALL CONTROL HANDLERS (HEADER & PAGE BUTTONS)
    // ==========================================================
    
    // ANSWER CALL
    $(document).on('click', '#vinttro-hdr-accept, #vinttro-accept-incoming', async function() {
        if (!activeCallPayload) return;

        const callId = activeCallPayload.call_id || activeCallPayload.callSid;
        const targetRoomName = activeCallPayload.roomId;
        const matches = activeCallPayload.client_matches || [];
        const isMultiMatch = matches.length > 1;

        console.log('[Accept] Claiming call and dialing into Conference Room:', targetRoomName);
        stopRingtoneAudio();

        // 1. Notify Node API to claim call
        try {
            await fetch(`${NODE_URL}/api/communicator/accept-webrtc`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    callId: callId,
                    agentId: AGENT_ID,
                    contactId: activeCallPayload.selected_contact_id || null,
                    clientId: activeCallPayload.selected_client_id || null
                })
            });
        } catch (err) {
            console.error('⚠️ Failed to report call claim to Node API:', err.message);
        }

        // 2. Connect Twilio WebRTC audio
        if (window.vinttroTwilioDevice) {
            window.vinttroTwilioDevice.connect({
                params: { To: targetRoomName }
            });
            console.log('✅ Agent WebRTC audio connecting to conference...');
        } else {
            console.error('❌ Twilio Voice Device is not initialized!');
        }

        // 3. Transition CTI Header UI to Active State
        if (isMultiMatch) {
            const activeSelectHtml = buildMultiMatchSelectHtml(matches, activeCallPayload.selected_index);
            setCallBarState('active', { callerHtml: activeSelectHtml, roomId: targetRoomName });
        } else {
            const displayName = getCallDisplayName(activeCallPayload);
            setCallBarState('active', { callerId: displayName, roomId: targetRoomName });
        }

        $('#vinttro-incoming-call-card').slideUp(200);
    });

    // DISMISS / REJECT CALL
    $(document).on('click', '#vinttro-hdr-reject, #vinttro-reject-incoming', function() {
        stopRingtoneAudio();
        setCallBarState('idle');
        $('#vinttro-incoming-call-card').slideUp(200);
    });

    // END / HANG UP CALL
    $(document).on('click', '#vinttro-hdr-end, #vinttro-rtc-disconnect', function() {
        if (window.vinttroTwilioDevice) {
            window.vinttroTwilioDevice.disconnectAll();
        }
        setCallBarState('idle');
    });

    // MUTE / UNMUTE MICROPHONE
    $(document).on('click', '#vinttro-hdr-mute', function() {
        if (!window.vinttroTwilioDevice) return;
        
        const activeConn = window.vinttroTwilioDevice.activeConnection();
        if (activeConn) {
            const isMuted = activeConn.isMuted();
            activeConn.mute(!isMuted);
            
            if (!isMuted) {
                $(this).html('🔇 Unmute').css('background', '#eab308');
            } else {
                $(this).html('🎙️ Mute').css('background', '#334155');
            }
        }
    });

    // ==========================================================
    // 6. PRESENCE DROPDOWN & AUDIO DEVICE MODAL CONTROLS
    // ==========================================================
    
    // Toggle Presence Menu
    $(document).on('click', '#vinttro-presence-toggle', function(e) {
        e.stopPropagation();
        $('#vinttro-presence-menu').toggle();
    });

    $(document).on('click', function() {$('#vinttro-presence-menu').hide();
    });

    // Change Agent Status
    $(document).on('click', '.vinttro-presence-option', function() {
        const newStatus = $(this).data('status');$('#vinttro-presence-badge')
            .removeClass('status-available status-away status-dnd')
            .addClass(`status-${newStatus}`);

        if (socket && socket.connected) {
            socket.emit('set_agent_status', { agentId: AGENT_ID, status: newStatus });
        }
    });

    // Audio Device Modal Toggles & Enumeration
    async function populateAudioDevices() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) return;

        try {
            await navigator.mediaDevices.getUserMedia({ audio: true });
            const devices = await navigator.mediaDevices.enumerateDevices();

            const $micSelect =$('#vinttro-mic-select').empty();
            const $speakerSelect =$('#vinttro-speaker-select').empty();

            devices.forEach(device => {
                if (device.kind === 'audioinput') {
                    $micSelect.append(new Option(device.label || `Microphone ${$micSelect.children().length + 1}`, device.deviceId));
                } else if (device.kind === 'audiooutput') {
                    $speakerSelect.append(new Option(device.label || `Speaker ${$speakerSelect.children().length + 1}`, device.deviceId));
                }
            });
        } catch (err) {
            console.warn('Unable to enumerate audio devices:', err.message);
        }
    }

    $(document).on('click', '#vinttro-open-devices', function(e) {
        e.stopPropagation();
        populateAudioDevices();
        $('#vinttro-device-modal').fadeIn(150);
    });

    $(document).on('click', '#vinttro-close-devices, #vinttro-device-modal', function(e) {
        if (e.target === this) $('#vinttro-device-modal').fadeOut(150);
    });

    // ==========================================================
    // 7. RINGTONE AUDIO UTILITY
    // ==========================================================
    function playRingtone() {
        try {
            if (!window.vinttroAudioCtx) {
                window.vinttroAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
            }
            if (window.vinttroAudioCtx.state === 'suspended') {
                window.vinttroAudioCtx.resume();
            }
            window.vinttroOsc = window.vinttroAudioCtx.createOscillator();
            window.vinttroGain = window.vinttroAudioCtx.createGain();
            
            window.vinttroOsc.type = 'sine';
            window.vinttroOsc.frequency.setValueAtTime(440, window.vinttroAudioCtx.currentTime);
            window.vinttroGain.gain.setValueAtTime(0.1, window.vinttroAudioCtx.currentTime);
            
            window.vinttroOsc.connect(window.vinttroGain);
            window.vinttroGain.connect(window.vinttroAudioCtx.destination);
            window.vinttroOsc.start();
        } catch (e) {
            console.log('Audio chime blocked or unavailable:', e.message);
        }
    }

    function stopRingtoneAudio() {
        try {
            if (window.vinttroOsc) {
                window.vinttroOsc.stop();
                window.vinttroOsc.disconnect();
                window.vinttroOsc = null;
            }
        } catch (e) {
            // Silence teardown errors
        }
    }
});