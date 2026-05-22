console.log("👉 TWILIO RTC DEVICE-AWARE ENGINE ACTIVE!");

jQuery(document).ready(function($) {
    let activeRoom = null;

    const $connectBtn     = $('#vinttro-rtc-connect');
    const $disconnectBtn  = $('#vinttro-rtc-disconnect');
    const $localTrackDom  = $('#local-video-feed');
    const $remoteGridDom  = $('#remote-video-grid');
    const $statusAlert    = $('#rtc-status-message');
    
    const $callTypeSelect = $('#vinttro-call-type');
    const $micSelect      = $('#vinttro-mic-select');
    const $camSelect      = $('#vinttro-cam-select');
    const $roomInput      = $('#vinttro-room-id');

    // 1. PAGE INITIALIZATION: UNMASK HARDWARE LABELS
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
            console.warn("Hardware enumeration restricted:", err.message);
            updateStatus("Hardware scanning restricted. Using defaults.", "info");
        }
    }

    initializeDeviceDirectory();

    // 2. TOGGLE UI DEPENDING ON CALL TYPE CHOSEN
    $callTypeSelect.on('change', function() {
        if ($(this).val() === 'audio') {
            $('.cam-wrapper').hide(); // Instantly hide camera selectors & local monitor views
        } else {
            if ($camSelect.val() !== "") $('.cam-wrapper').show();
        }
    });

    // 3. INITIALIZE SECURE TOKEN REQUEST
    $connectBtn.on('click', async function() {
        const chosenRoom = $roomInput.val().trim() || 'vinttro-hq';
        updateStatus(`Securing terminal connection credentials for space: [${chosenRoom}]...`, "info");
        $connectBtn.prop('disabled', true);

        try {
            const response = await fetch(`${vinttroSettings.root}vinttro/v1/rtc-token`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': vinttroSettings.nonce
                },
                body: JSON.stringify({ roomName: chosenRoom })
            });

            if (!response.ok) throw new Error("Failed validation check from server.");
            const data = await response.json();

            initializeWebRTCSession(data.token, chosenRoom);

        } catch (error) {
            updateStatus(`Authorization Denied: ${error.message}`, "error");
            $connectBtn.prop('disabled', false);
        }
    });

    // 4. MAP EXPLICIT HARDWARE CONSTRAINTS TO TWILIO SIGNALING
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