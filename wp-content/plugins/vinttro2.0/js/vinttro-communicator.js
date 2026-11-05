(function($) {
    "use strict";

    $(document).ready(function() {
        console.log("👉 VINTTRO UNIFIED COMMUNICATOR ENGINE ACTIVE (SYNCHRONIZED CTI MODE)");

        const NODE_URL = (typeof vinttroConfig !== 'undefined' && vinttroConfig.nodeApiUrl) 
            ? vinttroConfig.nodeApiUrl 
            : 'http://localhost:3005';

        const AGENT_ID = (typeof vinttroConfig !== 'undefined' && vinttroConfig.agentId) 
            ? vinttroConfig.agentId 
            : 'agent_dev_1';

        const AGENT_PHONE_URL = (typeof vinttroConfig !== 'undefined' && vinttroConfig.agentPhoneUrl)
            ? vinttroConfig.agentPhoneUrl
            : '/visp/agent-phone';

        let activeCallPayload = null;
        let callTimerInterval = null;
        let callStartTime = null;

        // ==========================================================
        // 1. BROADCASTCHANNEL IPC (Synchronized Inter-tab Bus)
        // ==========================================================
        const ctiBus = new BroadcastChannel('vinttro_cti_bus');

        ctiBus.onmessage = function(event) {
            const data = event.data;
            const { action, payload, status, text, muted } = data;

            // Full State Hydration (When this tab loads or phone window announces state)
            if (action === 'FULL_STATE_SYNC') {
                updateWebRTCHealthUI(data.phoneStatus, data.phoneText);
                if (data.phoneStatus === 'online') {
                    $('#vinttro-btn-launch-phone').hide();
                } else {
                    $('#vinttro-btn-launch-phone').show();
                }

                if (data.callState === 'RINGING' && data.callData) {
                    handleIncomingCallEvent(data.callData);
                } else if (data.callState === 'ACTIVE' && data.callData) {
                    activeCallPayload = data.callData;
                    setCallBarState('active', { 
                        callerId: getCallDisplayName(data.callData),
                        roomId: data.callData.roomId 
                    });
                    startCallTimer(data.callStartTime);
                    applyMuteUI(data.isMuted);
                } else if (data.callState === 'IDLE') {
                    setCallBarState('idle');
                }
            }
            else if (action === 'PHONE_STATUS') {
                updateWebRTCHealthUI(status, text);
                if (status === 'online') {
                    $('#vinttro-btn-launch-phone').hide();
                } else {
                    $('#vinttro-btn-launch-phone').show();
                }
            } 
            else if (action === 'INCOMING_CALL_RINGING') {
                handleIncomingCallEvent(payload);
            } 
            else if (action === 'SYNC_CALL_ACTIVE') {
                // Another tab answered the call
                stopRingtoneAudio();
                activeCallPayload = payload.callData || activeCallPayload;
                setCallBarState('active', { callerId: payload.displayName, roomId: payload.roomId });
                startCallTimer(payload.callStartTime);
                $('#vinttro-incoming-call-card').slideUp(200);
            }
            else if (action === 'SYNC_CALL_ENDED' || action === 'SYNC_CALL_DISMISSED') {
                stopRingtoneAudio();
                setCallBarState('idle');
                $('#vinttro-incoming-call-card').slideUp(200);
            }
            else if (action === 'MUTE_STATE_CHANGED') {
                applyMuteUI(muted);
            }
            else if (action === 'SYNC_CONTACT_SELECTION') {
                // Sync dropdown across all open tabs
                if (activeCallPayload) {
                    activeCallPayload.selected_index = payload.selectedIndex;
                    activeCallPayload.selected_contact_id = payload.contactId;
                    activeCallPayload.selected_client_id = payload.clientId;
                    activeCallPayload.client_name = payload.clientName;
                    activeCallPayload.account_name = payload.accountName;
                }
                $('.vinttro-contact-selector')
                    .val(payload.selectedIndex)
                    .removeClass('unassigned')
                    .addClass('assigned');
            }
        };

        // Hydrate this tab immediately upon opening
        ctiBus.postMessage({ action: 'REQUEST_STATE' });

        function applyMuteUI(isMuted) {
            const $muteBtn = $('#vinttro-hdr-mute');
            $muteBtn.data('muted', isMuted);
            if (isMuted) {
                $muteBtn.html('🔇 Unmute').css('background', '#eab308');
            } else {
                $muteBtn.html('🎙️ Mute').css('background', '#334155');
            }
        }

        // Softphone window launcher
        function launchSoftphone() {
            const winWidth = 360;
            const winHeight = 540;
            const left = (window.screen.width || 1200) - winWidth - 30;
            const top = 50;

            const phoneWin = window.open(
                AGENT_PHONE_URL,
                'VinttroAgentPhone',
                `width=${winWidth},height=${winHeight},top=${top},left=${left},resizable=yes,scrollbars=no,status=no`
            );

            if (phoneWin) {
                phoneWin.focus();
            } else {
                alert('Popup blocked! Please allow popups for this site to open your softphone.');
            }
        }

        $(document).on('click', '#vinttro-btn-launch-phone, #vinttro-webrtc-health-btn', function(e) {
            if ($(e.target).closest('#vinttro-open-devices').length) return;
            launchSoftphone();
        });

        // Multi-Match Dropdown Builder
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
        // 2. CTI HEADER STATE & TIMER HELPERS
        // ==========================================================
        function setCallBarState(state, data = {}) {
            const $bar = $('#vinttro-header-call-bar');
            const $idle = $('.segment-idle');
            const $ringing = $('.segment-ringing');
            const $active = $('.segment-active');

            $bar.removeClass('state-idle state-ringing state-active').addClass(`state-${state}`);

            const hasClient = activeCallPayload && (
                activeCallPayload.selected_client_id || 
                activeCallPayload.client_id || 
                (activeCallPayload.client_matches && activeCallPayload.client_matches.length > 0)
            );
            
            const popBtn = hasClient 
                ? `<button type="button" class="vinttro-open-client-btn" title="Open Client in New Tab" style="background:transparent; border:none; cursor:pointer; font-size:16px; margin-left:8px; padding:0; vertical-align:middle; transition:transform 0.1s;" onmouseover="this.style.transform='scale(1.15)'" onmouseout="this.style.transform='scale(1)'">🗂️</button>` 
                : '';

            if (state === 'idle') {
                $idle.show();
                $ringing.hide();
                $active.hide();
                stopCallTimer();
                $('#vinttro-hdr-mute').html('🎙️ Mute').css('background', '#334155').data('muted', false);
                activeCallPayload = null;
                window.activeRingingCallId = null;
            } else if (state === 'ringing') {
                $idle.hide(); 
                $active.hide();
                $ringing.show();
                
                if (data.callerHtml) {
                    $('#vinttro-hdr-caller-id').html(data.callerHtml + popBtn);
                } else {
                    $('#vinttro-hdr-caller-id').html((data.callerId || 'Unknown Caller') + popBtn);
                }
            } else if (state === 'active') {
                $idle.hide(); 
                $ringing.hide();
                $active.show();
                
                if (data.callerHtml) {
                    $('#vinttro-hdr-active-party').html(data.callerHtml + popBtn);
                } else {
                    $('#vinttro-hdr-active-party').html((data.callerId || 'In Call') + popBtn);
                }
            }
        }

        // Shared timestamp timer ensures all tabs tick identically
        function startCallTimer(startTime) {
            stopCallTimer();
            callStartTime = startTime || Date.now();
            function tick() {
                const elapsed = Math.max(0, Math.floor((Date.now() - callStartTime) / 1000));
                const mins = String(Math.floor(elapsed / 60)).padStart(2, '0');
                const secs = String(elapsed % 60).padStart(2, '0');
                $('#vinttro-hdr-timer').text(`${mins}:${secs}`);
            }
            tick();
            callTimerInterval = setInterval(tick, 1000);
        }

        function stopCallTimer() {
            if (callTimerInterval) {
                clearInterval(callTimerInterval);
                callTimerInterval = null;
            }
            $('#vinttro-hdr-timer').text('00:00');
        }

        function updateWebRTCHealthUI(status, label) {
            const $dot = $('#vinttro-health-dot');
            const $text = $('#vinttro-health-text');
            $dot.removeClass('health-online health-connecting health-offline').addClass(`health-${status}`);
            $text.text(label);
        }

        function getCallDisplayName(payload) {
            if (!payload) return 'Unknown Caller';
            const name = payload.client_name || payload.clientName || payload.caller_name || payload.callerName || payload.contact_name || payload.contactName;
            return (name && name.trim() !== '') ? name : (payload.callerId || payload.from || payload.fromNumber || 'Unknown Caller');
        }

        function handleIncomingCallEvent(payload) {
            activeCallPayload = payload;
            window.activeRingingCallId = payload.call_id || payload.callSid;

            const matches = payload.client_matches || [];
            const isMultiMatch = matches.length > 1;

            if (isMultiMatch) {
                const selectedIdx = activeCallPayload.selected_index !== undefined ? activeCallPayload.selected_index : '';
                const selectHtml = buildMultiMatchSelectHtml(matches, selectedIdx);
                setCallBarState('ringing', { callerHtml: selectHtml });
                $('#incoming-caller-id').html(selectHtml);

                $(document).off('change', '.vinttro-contact-selector').on('change', '.vinttro-contact-selector', async function() {
                    const idx = $(this).val();
                    const selected = matches[idx];
                    if (selected) {
                        activeCallPayload.selected_index = idx;
                        activeCallPayload.selected_contact_id = selected.contact_id;
                        activeCallPayload.selected_client_id = selected.client_id;
                        activeCallPayload.client_name = selected.contact_name;
                        activeCallPayload.account_name = selected.account_name;

                        // Broadcast contact change to all other tabs and phone window
                        ctiBus.postMessage({
                            action: 'UPDATE_CONTACT_SELECTION',
                            payload: {
                                selectedIndex: idx,
                                contactId: selected.contact_id,
                                clientId: selected.client_id,
                                clientName: selected.contact_name,
                                accountName: selected.account_name
                            }
                        });
                    }
                });

                $('#incoming-call-subtext').text(`⚠️ ${matches.length} Matches | Select Contact`);
            } else {
                const displayName = getCallDisplayName(payload);
                activeCallPayload.selected_contact_id = payload.contact_id || null;
                activeCallPayload.selected_client_id = payload.client_id || null;

                setCallBarState('ringing', { callerId: displayName });
                $('#incoming-caller-id').text(displayName);
                $('#incoming-call-subtext').text(`Inbound Call Waiting`);
            }

            $('#vinttro-room-id').val(payload.roomId);
            $('#vinttro-incoming-call-card').slideDown(200);
            playRingtone();
        }

        // ==========================================================
        // 3. CALL CONTROLS (REMOTE TO SOFTPHONE WINDOW)
        // ==========================================================

        // Manual Screen-Pop Click (Opens client view in new tab)
        $(document).on('click', '.vinttro-open-client-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (!activeCallPayload) return;
            
            const clientId = activeCallPayload.selected_client_id 
                || activeCallPayload.client_id 
                || (activeCallPayload.client_matches && activeCallPayload.client_matches[0]?.client_id);
            
            if (clientId) {
                window.open(`/visp/client-view?client_id=${encodeURIComponent(clientId)}`, '_blank');
            }
        });

        // ANSWER CALL
        $(document).on('click', '#vinttro-hdr-accept, #vinttro-accept-incoming', async function() {
            if (!activeCallPayload) return;

            const callId = activeCallPayload.call_id || activeCallPayload.callSid;
            const targetRoomName = activeCallPayload.roomId;
            const matches = activeCallPayload.client_matches || [];
            const isMultiMatch = matches.length > 1;

            stopRingtoneAudio();

            // 1. Claim Call in Backend
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
                console.error('⚠️ Call claim report failed:', err.message);
            }

            // 2. Instruct Pop-out Softphone to Connect Audio and Broadcast Sync
            ctiBus.postMessage({
                action: 'ANSWER_CALL',
                payload: {
                    roomId: targetRoomName,
                    displayName: getCallDisplayName(activeCallPayload)
                }
            });

            // 3. Local UI Transition
            if (isMultiMatch) {
                const activeSelectHtml = buildMultiMatchSelectHtml(matches, activeCallPayload.selected_index);
                setCallBarState('active', { callerHtml: activeSelectHtml, roomId: targetRoomName });
            } else {
                setCallBarState('active', { callerId: getCallDisplayName(activeCallPayload), roomId: targetRoomName });
            }

            startCallTimer(Date.now());
            $('#vinttro-incoming-call-card').slideUp(200);
        });

        // DISMISS / REJECT CALL
        $(document).on('click', '#vinttro-hdr-reject, #vinttro-reject-incoming', function() {
            stopRingtoneAudio();
            setCallBarState('idle');
            $('#vinttro-incoming-call-card').slideUp(200);
            ctiBus.postMessage({ action: 'REJECT_CALL' });
        });

        // END / HANG UP CALL
        $(document).on('click', '#vinttro-hdr-end, #vinttro-rtc-disconnect', function() {
            ctiBus.postMessage({ action: 'HANGUP_CALL' });
            setCallBarState('idle');
        });

        // MUTE / UNMUTE MICROPHONE
        $(document).on('click', '#vinttro-hdr-mute', function() {
            const isCurrentlyMuted = $(this).data('muted') === true;
            const newMuteState = !isCurrentlyMuted;

            ctiBus.postMessage({
                action: 'MUTE_TOGGLE',
                payload: { muted: newMuteState }
            });
            applyMuteUI(newMuteState);
        });

        // Audio Chime Helpers
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
            } catch (e) {}
        }
    });
})(jQuery);