console.log("👉 TWILIO RTC DEVICE-AWARE ENGINE ACTIVE!");

jQuery(document).ready(function($) {
    let activeRoom = null;
    let syncClient = null; // 🚀 NEW: Twilio Sync Client State
    let liveToken  = null; // 🚀 NEW: Cached Token for immediate background reuse

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
            // Trigger a quick permission handshake to unmask generic device names
            const initialStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: true }).catch(() => {
                // Fallback for audio-only desktop machines
                return navigator.mediaDevices.getUserMedia({ audio: true });
            });
            
            // Kill tracking streams instantly so camera lights turn back off
            initialStream.getTracks().forEach(track => track.stop());
            
            // Re-query system map to populate choices with official names
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
                $callTypeSelect.val('audio').trigger('change'); // Force audio-mode fallback
            }

        } catch (err) {
            console.error("❌ RAW HARDWARE ERROR:", err.name, "-", err.message); 
            console.warn("Hardware enumeration restricted:", err.message);
            updateStatus("Hardware scanning restricted. Using defaults.", "info");
        }
    }

    // Initialize hardware configuration instantly
    initializeDeviceDirectory();


// ==========================================================
    // 🛰️ 2. TWILIO SYNC WEBSOCKET INITIALIZATION (DIAGNOSTIC MODE)
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

            // Attempting to subscribe to the queue container...
            syncClient.list('vinttro_live_queue').then(list => {
                console.log("%c✅ SUCCESS: Browser is actively subscribed to the Twilio Sync List channel!", "color: #2f855a; font-weight: bold;");
                
                // 🔍 DEBUG TRACER: Log if the list currently has items sitting inside it on load
                list.getItems({ limit: 1 }).then(page => {
                    console.log(`📦 Current items stored in this cloud list: ${page.items.length}`);
                });

                // 🔔 LISTEN: This fires when ANY new item lands in the cloud bucket
                list.on('itemAdded', event => {
                    console.log("%c🔔 RAW EVENT DETECTED BY WEBSOCKET!", "color: #ecc94b; font-weight: bold;", event);
                    
                    // Safely extract data block regardless of inner encapsulation format
                    const callPayload = event.item ? event.item.data : null;
                    console.log("📋 Extracted Call Payload Data Map:", callPayload);

                    if (callPayload) {
                        triggerInboundCallAlert(callPayload);
                    } else {
                        console.error("❌ Event captured, but payload configuration map was empty or unreadable.");
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
        
        // Render a clean notification toast drawer at the top of their viewport frame
        const alertHtml = `
            <div id="alert-node-${callData.callSid}" class="vinttro-incoming-call-toast" style="position: fixed; top: 20px; right: 20px; background: #1a202c; border: 2px solid #3182ce; color: white; padding: 20px; border-radius: 8px; box-shadow: 0 10px 15px rgba(0,0,0,0.5); z-index: 99999; min-width: 320px; font-family: -apple-system, sans-serif;">
                <h4 style="margin:0 0 5px 0; color: #63b3ed;">📞 Incoming Customer Call</h4>
                <p style="margin:0 0 15px 0; font-size: 0.9rem;">Caller ID: <strong>${callData.callerId}</strong></p>
                <div style="display:flex; gap:10px;">
                    <button class="accept-toast-btn" data-sid="${callData.callSid}" data-room="${callData.roomId}" style="background: #2f855a; border:none; color:white; padding: 8px 16px; font-weight:bold; border-radius:4px; cursor:pointer; transition: 0.2s;">Accept and Bridge</button>
                    <button class="reject-toast-btn" style="background: transparent; border:1px solid #e53e3e; color:#fc8181; padding: 8px 16px; border-radius:4px; cursor:pointer; transition: 0.2s;">Dismiss</button>
                </div>
            </div>
        `;

        $('body').append(alertHtml);
        
        // Play an elegant, subtle notification ping sound through the agent's active headset
        const alertAudio = new Audio('https://actions.google.com/sounds/v1/alarms/digital_watch_alarm_long.ogg');
        alertAudio.volume = 0.3;
        alertAudio.play().catch(() => console.log("Audio play deferred until user interacts with document."));
    }


    // ==========================================================
    // 🤝 4. THE CALL ACCEPTANCE HANDSHAKE (THE AGENT TOAST CLICK)
    // ==========================================================
    $(document).on('click', '.accept-toast-btn', async function() {
        const targetCallSid = $(this).data('sid');
        const targetRoomId  = $(this).data('room');
        
        // Close out the visual notification block instantly
        $(`#alert-node-${targetCallSid}`).remove();
        updateStatus(`Joining call canvas container: [${targetRoomId}]...`, "info");

        try {
            // A. Instruct WordPress to live-redirect the waiting landline caller out of hold music
            const bridgeResponse = await fetch(`${vinttroSettings.root}vinttro/v1/accept-call`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': vinttroSettings.nonce },
                body: JSON.stringify({ callSid: targetCallSid, roomId: targetRoomId })
            });

            if (!bridgeResponse.ok) throw new Error("Server failed to establish media routing intercept.");

            // B. Launch the agent's media component channels using our cached token asset
            initializeWebRTCSession(liveToken, targetRoomId);

        } catch (error) {
            updateStatus(`Handshake Aborted: ${error.message}`, "error");
        }
    });

    $(document).on('click', '.reject-toast-btn', function() {
        $(this).closest('.vinttro-incoming-call-toast').remove();
    });


    // ==========================================================
    // 🎛️ 5. TOGGLE UI DEPENDING ON CALL TYPE CHOSEN
    // ==========================================================
    $callTypeSelect.on('change', function() {
        if ($(this).val() === 'audio') {
            $('.cam-wrapper').hide(); // Instantly hide camera selectors & local monitor views
        } else {
            if ($camSelect.val() !== "") $('.cam-wrapper').show();
        }
    });


    // ==========================================================
    // ⚡ 6. MANUAL OUTBOUND CONNECTION INITIATOR (OLD BUTTON CLICK)
    // ==========================================================
    $connectBtn.on('click', async function() {
        const chosenRoom = $roomInput.val().trim() || 'vinttro-hq';
        updateStatus(`Securing terminal connection credentials for space: [${chosenRoom}]...`, "info");
        $connectBtn.prop('disabled', true);

        try {
            // Reuse cached page-load token if it exists, otherwise request a manual one
            if (liveToken) {
                initializeWebRTCSession(liveToken, chosenRoom);
            } else {
                const response = await fetch(`${vinttroSettings.root}vinttro/v1/rtc-token`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': vinttroSettings.nonce },
                    body: JSON.stringify({ roomName: chosenRoom })
                });

                if (!response.ok) throw new Error("Failed validation check from server.");
                const data = await response.json();
                initializeWebRTCSession(data.token, chosenRoom);
            }

        } catch (error) {
            updateStatus(`Authorization Denied: ${error.message}`, "error");
            $connectBtn.prop('disabled', false);
        }
    });


    // ==========================================================
    // 📞 7. MAP EXPLICIT HARDWARE CONSTRAINTS TO TWILIO SIGNALING
    // ==========================================================
    function initializeWebRTCSession(token, roomName) {
        updateStatus("Routing real-time media streams...", "info");

        const selectedMicId = $micSelect.val();
        const selectedCamId = $camSelect.val();
        const callMode      = $callTypeSelect.val();

        // Build precise target constraints based on drop-down definitions
        const connectionConstraints = {
            name: roomName,
            audio: selectedMicId ? { deviceId: { exact: selectedMicId } } : true,
            video: false // Default baseline
        };

        // Inject camera paths only if user actively requests a video call layout
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

            // Only attach local monitoring feed if video tracks are compiled
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
        $remoteGridDom.find(`[id^="track-${participant.sid}"]`).remove();
    }

    $disconnectBtn.on('click', function() {
        if (activeRoom) activeRoom.disconnect();
    });

    function cleanUpMediaStreams() {
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
});