/**
 * Vinttro WebRTC Engine Client
 * Handles room signaling, local media ingestion, and video/audio mapping.
 */
console.log("👉 TWILIO RTC SCRIPT HAS RUN AND IS ALIVE!");


jQuery(document).ready(function($) {
    let activeRoom = null;

    const $connectBtn    = $('#vinttro-rtc-connect');
    const $disconnectBtn = $('#vinttro-rtc-disconnect');
    const $localTrackDom = $('#local-video-feed');
    const $remoteGridDom = $('#remote-video-grid');
    const $statusAlert   = $('#rtc-status-message');

    // 1. CLICK EVENT TO INITIALIZE THE CALL
    $connectBtn.on('click', async function() {
        updateStatus("Requesting secure infrastructure access token...", "info");
        $connectBtn.prop('disabled', true);

        try {
            // Fetch token from the secure custom WordPress REST API endpoint we built earlier
            const response = await fetch(`${vinttroSettings.root}vinttro/v1/rtc-token`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': vinttroSettings.nonce // Built-in WP verification token
                },
                body: JSON.stringify({ roomName: 'vinttro-hq' })
            });

            if (!response.ok) throw new Error("Failed validation check from server.");
            const data = await response.json();

            // Fire up Twilio WebRTC client network connection
            initializeWebRTCSession(data.token);

        } catch (error) {
            updateStatus(`Authorization Denied: ${error.message}`, "error");
            $connectBtn.prop('disabled', false);
        }
    });

    // 2. CONNECT TO THE SIGNALING LAYER
    function initializeWebRTCSession(token) {
        updateStatus("Connecting to Vinttro WebRTC Node...", "info");

        Twilio.Video.connect(token, {
            name: 'vinttro-hq',
            audio: true,
            video: { width: 640, height: 480 }
        }).then(room => {
            activeRoom = room;
            updateStatus("Connected to Internal Vinttro Workspace", "success");

            $connectBtn.hide();
            $disconnectBtn.show();

            // Instantly render local webcam/microphone stream
            room.localParticipant.videoTracks.forEach(publication => {
                $localTrackDom.append(publication.track.attach());
            });

            // Map already active people in the room
            room.participants.forEach(participantConnected);

            // Set dynamic listeners for incoming/leaving members
            room.on('participantConnected', participantConnected);
            room.on('participantDisconnected', participantDisconnected);
            room.once('disconnected', error => {
                if (error) console.error(`Disconnected due to network error: ${error.code}`);
                cleanUpMediaStreams();
            });

        }).catch(err => {
            updateStatus(`WebRTC Network Failure: ${err.message}`, "error");
            $connectBtn.prop('disabled', false);
        });
    }

    // 3. HANDLE INCOMING MEMBERS & REMOTE STREAMS
    function participantConnected(participant) {
        console.log(`Member authenticated & entered room: ${participant.identity}`);
        
        // Listen for new video/audio tracks they publish
        participant.on('trackSubscribed', track => {
            const trackElement = track.attach();
            trackElement.id = `track-${participant.sid}-${track.sid}`;
            $remoteGridDom.append(trackElement);
        });

        // Clean up tracks if they turn off camera or mute mic mid-call
        participant.on('trackUnsubscribed', track => {
            $(`#track-${participant.sid}-${track.sid}`).remove();
        });
    }

    function participantDisconnected(participant) {
        console.log(`Member left room: ${participant.identity}`);
        // Strip out any stranded elements tied to this specific connection session
        $remoteGridDom.find(`[id^="track-${participant.sid}"]`).remove();
    }

    // 4. DISCONNECT HANDLERS
    $disconnectBtn.on('click', function() {
        if (activeRoom) {
            activeRoom.disconnect();
        }
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