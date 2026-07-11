console.log("👉 TWILIO RTC DEVICE-AWARE ENGINE ACTIVE!");

jQuery(document).ready(function($) {
    let activeRoom = null;
    let syncClient = null; 
    let liveToken  = null; 

    const $connectBtn     = $('#vinttro-rtc-connect');
    const $disconnectBtn  = $('#vinttro-rtc-disconnect');
    const $localTrackDom  = $('#local-video-feed');
    const $remoteGridDom  = $('#remote-video-grid');
    const $statusAlert    = $('#rtc-status-message');
    
    const $callTypeSelect = $('#vinttro-call-type');
    const $micSelect      = $('#vinttro-mic-select');
    const $camSelect      = $('#vinttro-cam-select');
    const $roomInput      = $('#vinttro-room-id');

    // ==========================================================
    // ⚙️ 1. PAGE INITIALIZATION: UNMASK HARDWARE LABELS
    // ==========================================================
    async function initializeDeviceDirectory() {
        try {
            const initialStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: true }).catch(() => {
                return navigator.mediaDevices.getUserMedia({ audio: true });
            });
            
            initialStream.getTracks().forEach(track => track.stop());
            const systemDevices = await navigator.mediaDevices.enumerateDevices();
            
            $micSelect.empty();
            $camSelect.empty();

            let micCount = 0;
            let camCount = 0;

            systemDevices.forEach(device => {
                if (device.kind === 'audioinput') {
                    $micSelect.append(`<option value="${device.deviceId}">${device.label || `Microphone ${++micCount}`}</option>`);
                } else if (device.kind === 'videoinput') {
                    $camSelect.append(`<option value="${device.deviceId}">${device.label || `Camera ${++camCount}`}</option>`);
                }
            });

            if (camCount === 0) {
                $camSelect.append('<option value="">No Camera Found</option>');
                $callTypeSelect.val('audio').trigger('change'); 
            }

        } catch (err) {
            console.error("❌ RAW HARDWARE ERROR:", err.name, "-", err.message); 
            console.warn("Hardware enumeration restricted:", err.message);
            updateStatus("Hardware scanning restricted. Using defaults.", "info");
        }
    }

    initializeDeviceDirectory();


    // ==========================================================
    // 🛰️ 2. TWILIO SYNC WEBSOCKET INITIALIZATION
    // ==========================================================

    async function activateAgentSyncListeningTerminal() {
        try {
            const response = await fetch(`${vinttroSettings.root}vinttro/v1/rtc-token`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': vinttroSettings.nonce },
                body: JSON.stringify({ roomName: 'vinttro-dashboard-mount' })
            });
            
            const data = await response.json();
            liveToken  = data.token; 

            syncClient = new Twilio.Sync.Client(liveToken);

            syncClient.on('connectionStateChanged', state => {
                console.log(`%c📡 WebSocket Node Sync State: ${state}`, "color: #3182ce; font-weight: bold;");
            });

            // ------------------------------------------------------
            // 📧 NEW: Direct Agent-to-Agent Private Signaling Channel
            // ------------------------------------------------------
            if (vinttroSettings.currentUserEmail) {
                const userEmailClean = vinttroSettings.currentUserEmail.replace(/[^a-zA-Z0-9]/g, '_');
                
                syncClient.document(`vinttro_user_channel_${userEmailClean}`).then(doc => {
                    console.log(`%c👤 SUCCESS: Subscribed to private user mailbox: [vinttro_user_channel_${userEmailClean}]`, "color: #805ad5; font-weight: bold;");
                    
                    doc.on('updated', event => {
                        console.log("🔔 DIRECT PEER CALL SIGNAL DETECTED BY WEBSOCKET!", event);
                        const callPayload = event.data;
                        
                        if (callPayload && callPayload.action === 'incoming_call') {
                            triggerInboundCallAlert({
                                callSid: callPayload.roomId, // Map room identity to callSid for the UI alert
                                roomId: callPayload.roomId,
                                callerId: callPayload.callerIdentity
                            });
                        }
                    });
                }).catch(docError => {
                    console.error("❌ CRITICAL: Private user channel sync rejected:", docError);
                });
            } else {
                console.warn("⚠️ User-to-user signaling disabled: vinttroSettings.currentUserEmail is missing.");
            }

            // ------------------------------------------------------
            // 📦 ORIGINAL: Public Customer Inbound Queue Channel
            // ------------------------------------------------------
            syncClient.list('vinttro_live_queue').then(list => {
                console.log("%c✅ SUCCESS: Browser is actively subscribed to the Twilio Sync List channel!", "color: #2f855a; font-weight: bold;");
                
                list.getItems({ limit: 1 }).then(page => {
                    console.log(`📦 Current items stored in this cloud list: ${page.items.length}`);
                });

                list.on('itemAdded', event => {
                    console.log("%c🔔 RAW EVENT DETECTED BY WEBSOCKET!", "color: #ecc94b; font-weight: bold;", event);
                    
                    const callPayload = event.item ? event.item.data : null;
                    console.log("📋 Extracted Call Payload Data Map:", callPayload);

                    if (callPayload) {
                        const cleanData = callPayload.data ? callPayload.data : callPayload;
                        triggerInboundCallAlert(cleanData);
                    } else {
                        error_log("❌ Event captured, but payload configuration map was empty or unreadable.");
                    }
                });
            }).catch(listError => {
                console.error("❌ CRITICAL: Twilio Sync List subscription rejected by cloud:", listError);
            });

        } catch (err) {
            console.error("Failed to mount secure WebSocket sync cluster:", err);
        }
    }

    activateAgentSyncListeningTerminal();


    // ==========================================================
    // 🔔 3. INTERACTIVE MODAL NOTIFICATION HANDLER
    // ==========================================================
    function triggerInboundCallAlert(callData) {
        console.log("🚨 INCOMING QUEUE ASSIGNMENT ALERT RECEIVED:", callData);
        
        // Quick verification check to ensure fields are populated correctly
        if (!callData.callSid || !callData.roomId) {
            console.error("❌ Aborting alert rendering: callSid or roomId unpacked as undefined!", callData);
            return;
        }

        const alertHtml = `
            <div id="alert-node-${callData.callSid}" class="vinttro-incoming-call-toast" style="position: fixed; top: 20px; right: 20px; background: #1a202c; border: 2px solid #3182ce; color: white; padding: 20px; border-radius: 8px; box-shadow: 0 10px 15px rgba(0,0,0,0.5); z-index: 99999; min-width: 320px; font-family: -apple-system, sans-serif;">
                <h4 style="margin:0 0 5px 0; color: #63b3ed;">📞 Incoming Customer Call</h4>
                <p style="margin:0 0 15px 0; font-size: 0.9rem;">Caller ID: <strong>${callData.callerId}</strong></p>
                <div style="display:flex; gap:10px;">
                    <button class="accept-toast-btn" data-sid="${callData.callSid}" data-room="${callData.roomId}" style="background: #2f855a; border:none; color:white; padding: 8px 16px; font-weight:bold; border-radius:4px; cursor:pointer;">Accept and Bridge</button>
                    <button class="reject-toast-btn" style="background: transparent; border:1px solid #e53e3e; color:#fc8181; padding: 8px 16px; border-radius:4px; cursor:pointer;">Dismiss</button>
                </div>
            </div>
        `;

        $('body').append(alertHtml);
        
        const alertAudio = new Audio('https://actions.google.com/sounds/v1/alarms/digital_watch_alarm_long.ogg');
        alertAudio.volume = 0.3;
        alertAudio.play().catch(() => console.log("Audio play deferred until user interacts with document."));
    }

// ==========================================================
    // 🤝 4. THE CALL ACCEPTANCE HANDSHAKE
    // ==========================================================
    $(document).on('click', '.accept-toast-btn', async function() {
        const targetCallSid = $(this).data('sid');
        const targetRoomId  = $(this).data('room');
        
        $(`#alert-node-${targetCallSid}`).remove();
        updateStatus(`Joining call canvas container: [${targetRoomId}]...`, "info");

        // 🚀 SMART FIX: If this is a pure WebRTC peer-to-peer call, bypass the telephone network intercept!
        if (targetRoomId && targetRoomId.toString().startsWith('peer_call_')) {
            console.log("🤝 Peer-to-Peer browser video call verified. Connecting directly to room matrix.");
            initializeWebRTCSession(liveToken, targetRoomId);
            return; // Exit here so we don't hit the unneeded backend API
        }

        // ==========================================
        // ORIGINAL CUSTOMER PSTN PHONE BRIDGE INTERCEPT
        // ==========================================
        try {
            const bridgeResponse = await fetch(`${vinttroSettings.root}vinttro/v1/accept-call`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': vinttroSettings.nonce },
                body: JSON.stringify({ callSid: targetCallSid, roomId: targetRoomId })
            });

            if (!bridgeResponse.ok) throw new Error("Server failed to establish media routing intercept.");

            initializeWebRTCSession(liveToken, targetRoomId);

        } catch (error) {
            updateStatus(`Handshake Aborted: ${error.message}`, "error");
        }
    });

    // ==========================================================
    // 🎛️ 5. UI CONTROLS HANDLERS
    // ==========================================================
    $callTypeSelect.on('change', function() {
        if ($(this).val() === 'audio') {
            $('.cam-wrapper').hide(); 
        } else {
            if ($camSelect.val() !== "") $('.cam-wrapper').show();
        }
    });

$connectBtn.on('click', async function() {
    const inputValue = $roomInput.val().trim();
    
    if (!inputValue) {
        updateStatus("Please enter a Room ID or Phone Number.", "error");
        return;
    }

    // 🔍 Regular expression to check if input looks like an international phone number
    const isPhoneNumber = /^\+?[1-9]\d{1,14}$/.test(inputValue.replace(/\s+/g, ''));

    const isEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(inputValue);

    if (isEmail) {
        // ==========================================
        // 📧 PEER-TO-PEER EMAIL CALL FLOW
        // ==========================================
        const targetEmail = inputValue.toLowerCase();
        const adhocRoomName = "peer_call_" + btoa(targetEmail).replace(/=/g, ''); // Safe unique room name

        updateStatus(`Ringing ${targetEmail}...`, "info");
        $connectBtn.prop('disabled', true);

        try {
            // Tell the backend to notify the user logged in with this email
            const response = await fetch(`${vinttroSettings.root}vinttro/v1/peer-call`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': vinttroSettings.nonce },
                body: JSON.stringify({ 
                    targetEmail: targetEmail,
                    callerIdentity: "agent_dev_1", // The person making the call
                    roomId: adhocRoomName 
                })
            });

            if (!response.ok) throw new Error("User offline or call rejected.");
            
            // Instantly join the room yourself and wait for them to answer
            initializeWebRTCSession(liveToken, adhocRoomName);
            $connectBtn.hide();
            $disconnectBtn.show();

        } catch (error) {
            updateStatus(`Call Failed: ${error.message}`, "error");
            $connectBtn.prop('disabled', false);
        }

    } else if (isPhoneNumber) { 
        const cleanPhoneNumber = inputValue.replace(/\s+/g, '');
        // Create a unique, web-safe room identifier for this specific lead call
        const adhocRoomName = "call_lead_" + cleanPhoneNumber.replace('+', '');

        updateStatus(`Initiating video room bridge for outbound call...`, "info");
        $connectBtn.prop('disabled', true);

        try {
            // 1. Tell your WordPress backend to trigger the outbound call leg
            const response = await fetch(`${vinttroSettings.root}vinttro/v1/outbound-call`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': vinttroSettings.nonce },
                body: JSON.stringify({ 
                    toNumber: cleanPhoneNumber,
                    leadId: "test_lead_999", // Hardcoded tracking sample
                    agentId: "agent_dev_1",
                    roomId: adhocRoomName    // Pass the matching room name to the backend
                })
            });

            if (!response.ok) throw new Error("Server rejected outbound workspace creation.");
            const data = await response.json();
            
            // 2. IMMEDIATELY dump your local agent into the video room!
            // Your browser will load up and wait inside the canvas for the phone caller to drop in.
            updateStatus(`Room active. Dialing lead phone line...`, "success");
            initializeWebRTCSession(liveToken, adhocRoomName);

            $connectBtn.hide();
            $disconnectBtn.show();

            } catch (error) {
                updateStatus(`Bridge Failure: ${error.message}`, "error");
                $connectBtn.prop('disabled', false);
            }

    } else {
        // ==========================================
        // 🎥 ORIGINAL WEBRTC VIDEO ROOM FLOW
        // ==========================================
        updateStatus(`Securing terminal connection credentials for space: [${inputValue}]...`, "info");
        $connectBtn.prop('disabled', true);

        try {
            if (liveToken) {
                initializeWebRTCSession(liveToken, inputValue);
            } else {
                const response = await fetch(`${vinttroSettings.root}vinttro/v1/rtc-token`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': vinttroSettings.nonce },
                    body: JSON.stringify({ roomName: inputValue })
                });

                if (!response.ok) throw new Error("Failed validation check from server.");
                const data = await response.json();
                initializeWebRTCSession(data.token, inputValue);
            }
        } catch (error) {
            updateStatus(`Authorization Denied: ${error.message}`, "error");
            $connectBtn.prop('disabled', false);
        }
    }
});


    // ==========================================================
    // 📞 6. WEBRTC MEDIA ENGINE ROUTER
    // ==========================================================
    function initializeWebRTCSession(token, roomName) {
        updateStatus("Routing real-time media streams...", "info");

        const selectedMicId = $micSelect.val();
        const selectedCamId = $camSelect.val();
        const callMode      = $callTypeSelect.val();

        const connectionConstraints = {
            name: roomName,
            audio: selectedMicId ? { deviceId: { exact: selectedMicId } } : true,
            video: false 
        };

        if (callMode === 'video' && selectedCamId) {
            connectionConstraints.video = { 
                deviceId: { exact: selectedCamId },
                width: 640, 
                height: 480 
            };
        }

        Twilio.Video.connect(token, connectionConstraints).then(room => {
            activeRoom = room;
            updateStatus(`Active Session Channel: Connected to [${roomName}]`, "success");

            $connectBtn.hide();
            $disconnectBtn.show();

            room.localParticipant.videoTracks.forEach(publication => {
                $localTrackDom.append(publication.track.attach());
            });

            room.participants.forEach(participantConnected);

            room.on('participantConnected', participantConnected);
            room.on('participantDisconnected', participantDisconnected);
            room.once('disconnected', () => cleanUpMediaStreams());

        }).catch(err => {
            updateStatus(`WebRTC Network Failure: ${err.message}`, "error");
            $connectBtn.prop('disabled', false);
        });
    }

    function participantConnected(participant) {
        console.log(`Member synced: ${participant.identity}`);
        
        // 🚀 NEW: If an empty-room alert is counting down, kill it because a user just joined!
        clearInterval(hangupCountdownTimer);
        $('#vinttro-empty-room-modal').remove();
        updateStatus(`Active Session Channel: Connected to [${activeRoom ? activeRoom.name : ''}]`, "success");
        
        participant.on('trackSubscribed', track => {
            const trackElement = track.attach();
            trackElement.id = `track-${participant.sid}-${track.sid}`;
            $remoteGridDom.append(trackElement);
        });

        participant.on('trackUnsubscribed', track => {
            $(`#track-${participant.sid}-${track.sid}`).remove();
        });
    }

function participantDisconnected(participant) {
        // 1. Remove their video/audio feeds from the DOM as before
        $remoteGridDom.find(`[id^="track-${participant.sid}"]`).remove();
        console.log(`Member disconnected: ${participant.identity}. Remaining remote participants: ${activeRoom ? activeRoom.participants.size : 0}`);

        // 2. 🚀 NEW: Check if you are the last person standing
        if (activeRoom && activeRoom.participants.size === 0) {
            
            // 👉 CHOOSE PATH A: Terminate immediately and silently
            /*
            console.log("Empty room detected. Auto-disconnecting channel leg.");
            activeRoom.disconnect();
            */

            // 👉 CHOOSE PATH B: Show an interactive, modern overlay UI block
            triggerEmptyRoomAlert();
        }
    }

    $disconnectBtn.on('click', function() {
        if (activeRoom) activeRoom.disconnect();
    });

    function cleanUpMediaStreams() {
        clearInterval(hangupCountdownTimer); // 🚀 Add this line
        $('#vinttro-empty-room-modal').remove(); // 🚀 Add this line
        
        $localTrackDom.empty();
        $remoteGridDom.empty();
        $connectBtn.show().prop('disabled', false);
        $disconnectBtn.hide();
        updateStatus("Session closed. Offline.", "offline");
        activeRoom = null;
    }
    function updateStatus(message, type) {
        $statusAlert.text(message).attr('class', `status-banner ${type}`);
    }

    // ==========================================================
    // ⏳ 7. EMPTY ROOM WRAP-UP MONITOR
    // ==========================================================
    let hangupCountdownTimer = null;

    function triggerEmptyRoomAlert() {
        // Prevent duplicate overlays if one is already active
        if ($('#vinttro-empty-room-modal').length) return;

        const modalHtml = `
            <div id="vinttro-empty-room-modal" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(20, 23, 28, 0.9); display: flex; flex-direction: column; align-items: center; justify-content: center; border-radius: 8px; z-index: 100; text-align: center; font-family: -apple-system, sans-serif; animation: fadeIn 0.2s ease;">
                <div style="background: #1a202c; border: 1px solid #4a5568; padding: 30px; border-radius: 10px; max-width: 400px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.7);">
                    <div style="font-size: 3rem; margin-bottom: 15px;">👋</div>
                    <h4 style="margin: 0 0 10px 0; color: #fff; font-size: 1.2rem;">No active participants left</h4>
                    <p style="margin: 0 0 20px 0; color: #a0aec0; font-size: 0.95rem;">Disconnecting automatically in <strong id="hangup-countdown" style="color: #fc8181;">5</strong> seconds...</p>
                    <div style="display: flex; gap: 12px; justify-content: center;">
                        <button id="empty-room-hangup-now" style="background: #e53e3e; color: white; border: none; padding: 10px 20px; font-weight: bold; border-radius: 6px; cursor: pointer;">Hang Up Now</button>
                        <button id="empty-room-stay" style="background: transparent; border: 1px solid #4a5568; color: #a0aec0; padding: 10px 20px; font-weight: bold; border-radius: 6px; cursor: pointer;">Wait in Room</button>
                    </div>
                </div>
            </div>
        `;

        // Inject the modal directly over your main video grid workspace container
        $('#vinttro-rtc-workspace').css('position', 'relative').append(modalHtml);

        // Start a 5-second ticking countdown backstop
        let timeLeft = 5;
        hangupCountdownTimer = setInterval(() => {
            timeLeft--;
            $('#hangup-countdown').text(timeLeft);
            
            if (timeLeft <= 0) {
                clearInterval(hangupCountdownTimer);
                console.log("Countdown backstop expired. Hanging up call.");
                if (activeRoom) activeRoom.disconnect();
                $('#vinttro-empty-room-modal').remove();
            }
        }, 1000);
    }

    // Bind Button Click: Hang Up Right Away
    $(document).on('click', '#empty-room-hangup-now', function() {
        clearInterval(hangupCountdownTimer);
        if (activeRoom) activeRoom.disconnect();
        $('#vinttro-empty-room-modal').remove();
    });

    // Bind Button Click: Dismiss and stay in the empty canvas
    $(document).on('click', '#empty-room-stay', function() {
        clearInterval(hangupCountdownTimer);
        $('#vinttro-empty-room-modal').remove();
        updateStatus("Waiting alone inside active channel session...", "info");
    });

    // ==========================================================
    // 💼 8. UNIVERSAL WORKSPACE TASK PIPELINE CONTROLLER (PROXIED)
    // ==========================================================
    const currentAgentEmail = "finley.collis@vinttro.co.uk"; 

    function loadAgentTasks() {
        const $container = $('#vinttro-active-tasks');
        $container.html('<p style="padding:15px; color:#a0aec0;">🔄 Fetching secure task matrix via WordPress...</p>');

        // 🚀 THE SECURE SWITCH: Query the local WP AJAX endpoint instead of Node directly
        // ajaxurl is globally exposed by WordPress in the admin panel layout
        const secureWpEndpoint = `${ajaxurl}?action=vinttro_get_tasks&agent=${encodeURIComponent(currentAgentEmail)}`;

        fetch(secureWpEndpoint)
            .then(res => res.json())
            .then(data => {
                if (!data.success || !data.tasks.length) {
                    $container.html('<p style="padding:15px; color:#a0aec0;">🎉 Clean desk! No outstanding tasks found.</p>');
                    $('#vinttro-task-count').text('0 Tasks');
                    return;
                }

                $container.empty();
                $('#vinttro-task-count').text(`${data.tasks.length} Active Task${data.tasks.length !== 1 ? 's' : ''}`);

                data.tasks.forEach(task => {
                    let typeBadge = '';
                    if (task.type === 'OUTBOUND_CALL') typeBadge = '<span class="vinttro-badge" style="background:#3182ce;">📞 Outbound Call</span>';
                    if (task.type === 'RESEARCH')     typeBadge = '<span class="vinttro-badge" style="background:#319795;">🔍 Data Research</span>';
                    if (task.type === 'CASE_REVIEW')  typeBadge = '<span class="vinttro-badge" style="background:#dd6b20;">📂 Case Review</span>';

                    const cardHtml = `
                        <div class="vinttro-task-card" data-task-id="${task.id}" data-task-type="${task.type}" data-title="${task.title}" data-target="${task.target}" data-meta="${task.meta}" data-notes="${task.notes}">
                            <div class="task-info">
                                <div style="margin-bottom:6px;">${typeBadge} <span class="task-id">#${task.id}</span></div>
                                <h4 class="task-customer-name" style="margin:0;">${task.title}</h4>
                                <p class="task-meta">${task.meta} | ${task.target}</p>
                            </div>
                            <div class="task-action">
                                <button class="vinttro-btn vinttro-btn-primary claim-task-btn">Open Worksheet</button>
                            </div>
                        </div>
                    `;
                    $container.append(cardHtml);
                });
            })
            .catch(err => {
                console.error("❌ Task proxy pipeline breakdown:", err);
                $container.html('<p style="padding:15px; color:#e53e3e;">❌ Failed to query secure proxy state machine.</p>');
            });
    }

    // Run the proxy initialization pass
    loadAgentTasks();
});

