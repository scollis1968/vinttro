<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Intercept /visp/agent-phone route and render the standalone audio engine
add_action( 'template_redirect', function() {
    $request_path = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
    if ( $request_path === 'visp/agent-phone' ) {
        if ( ! is_user_logged_in() ) {
            wp_die( 'Access denied. Please log in to Vinttro.', 'Unauthorized', array( 'response' => 403 ) );
        }
        vinttro_render_agent_phone();
        exit;
    }
} );

function vinttro_render_agent_phone() {
    $current_user = wp_get_current_user();
    $agent_email  = ! empty( $current_user->user_email ) ? sanitize_email( $current_user->user_email ) : 'agent_dev_1';
    
    $default_node = ( defined('WP_ENVIRONMENT_TYPE') && wp_get_environment_type() === 'development' )
        ? 'http://localhost:3005'
        : 'https://services.uat.vinttro.co.uk';
    $node_url = defined('VINTTRO_NODE_URL') ? VINTTRO_NODE_URL : $default_node;
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Vinttro Softphone Audio Engine</title>
        
        <!-- External Dependencies -->
        <script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
        <script src="https://unpkg.com/@twilio/voice-sdk@2.11.0/dist/twilio.min.js"></script>
        <script src="https://cdn.tailwindcss.com"></script>

        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; }
            .status-dot { height: 10px; width: 10px; border-radius: 50%; display: inline-block; }
            .status-offline { background-color: #ef4444; box-shadow: 0 0 0 2px #fee2e2; }
            .status-online { background-color: #22c55e; box-shadow: 0 0 0 2px #dcfce7; }
            .status-connecting { background-color: #eab308; box-shadow: 0 0 0 2px #fef08a; }
            .drag-region { -webkit-app-region: drag; }
            .no-drag { -webkit-app-region: no-drag; }
        </style>
    </head>
    <body class="p-4 flex flex-col h-screen text-slate-800">

        <!-- Header -->
        <div class="drag-region flex items-center justify-between pb-3 border-b border-slate-200 mb-3">
            <div>
                <h1 class="text-sm font-bold text-slate-900 m-0">Vinttro Softphone Engine</h1>
                <p class="text-xs text-slate-500 m-0" id="agent-identity-label">Loading...</p>
            </div>
            <div class="bg-blue-50 text-blue-600 p-2 rounded-md">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
            </div>
        </div>

        <!-- Status Dashboards -->
        <div class="flex-1 space-y-3 no-drag">
            <!-- Node API Status -->
            <div class="bg-white p-3 rounded-md shadow-sm border border-slate-200 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="status-dot status-connecting" id="node-status-dot"></span>
                    <span class="text-sm font-medium">Central Node Sync</span>
                </div>
                <span class="text-xs text-slate-500 font-mono" id="node-status-text">Connecting...</span>
            </div>

            <!-- Twilio WebRTC Status -->
            <div class="bg-white p-3 rounded-md shadow-sm border border-slate-200 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="status-dot status-connecting" id="rtc-status-dot"></span>
                    <span class="text-sm font-medium">WebRTC Audio</span>
                </div>
                <span class="text-xs text-slate-500 font-mono" id="rtc-status-text">Authenticating...</span>
            </div>

            <!-- Outbound / DTMF Dial Pad -->
            <div class="bg-white p-3 rounded-md shadow-sm border border-slate-200">
                <div class="flex gap-1.5 mb-2">
                    <input
                        type="tel"
                        id="dialpad-input"
                        placeholder="Enter number (e.g. 07700 900000)"
                        class="flex-1 px-2.5 py-1.5 text-sm font-mono border border-slate-300 rounded focus:outline-none focus:border-blue-500"
                    />
                    <button
                        type="button"
                        id="dialpad-clear"
                        title="Backspace"
                        class="px-2.5 py-1.5 text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-600 rounded border border-slate-300"
                    >⌫</button>
                </div>

                <div class="grid grid-cols-3 gap-1.5 mb-2">
                    <button type="button" class="dial-digit py-1.5 text-sm font-semibold bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded" data-digit="1">1</button>
                    <button type="button" class="dial-digit py-1.5 text-sm font-semibold bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded" data-digit="2">2</button>
                    <button type="button" class="dial-digit py-1.5 text-sm font-semibold bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded" data-digit="3">3</button>
                    <button type="button" class="dial-digit py-1.5 text-sm font-semibold bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded" data-digit="4">4</button>
                    <button type="button" class="dial-digit py-1.5 text-sm font-semibold bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded" data-digit="5">5</button>
                    <button type="button" class="dial-digit py-1.5 text-sm font-semibold bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded" data-digit="6">6</button>
                    <button type="button" class="dial-digit py-1.5 text-sm font-semibold bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded" data-digit="7">7</button>
                    <button type="button" class="dial-digit py-1.5 text-sm font-semibold bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded" data-digit="8">8</button>
                    <button type="button" class="dial-digit py-1.5 text-sm font-semibold bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded" data-digit="9">9</button>
                    <button type="button" class="dial-digit py-1.5 text-sm font-semibold bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded" data-digit="*">*</button>
                    <button type="button" class="dial-digit py-1.5 text-sm font-semibold bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded" data-digit="0">0</button>
                    <button type="button" class="dial-digit py-1.5 text-sm font-semibold bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded" data-digit="#">#</button>
                </div>

                <button
                    type="button"
                    id="dialpad-call-btn"
                    class="w-full py-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-500 rounded shadow-sm transition-colors"
                >📞 Call Number</button>
            </div>

            <!-- Active Call Status Card -->
            <div class="bg-slate-800 text-white p-4 rounded-md shadow-sm border border-slate-700 mt-3" id="call-card">
                <div class="text-xs text-slate-400 font-semibold mb-1 uppercase tracking-wider" id="call-state">IDLE</div>
                <div class="text-base font-bold truncate" id="call-target">Ready for Calls</div>
                <div class="text-sm text-slate-300 mt-1 font-mono" id="call-timer">00:00</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="pt-3 text-center border-t border-slate-200 mt-3 no-drag">
            <p class="text-xs text-slate-400 m-0">Audio engine active. You can minimize this window.</p>
        </div>

<!-- Audio Engine Script -->
        <script>
            const urlParams = new URLSearchParams(window.location.search);
            const AGENT_ID = urlParams.get('agent') || '<?php echo esc_js( strtolower( trim( $agent_email ) ) ); ?>';
            const NODE_URL = urlParams.get('node') || '<?php echo esc_js( untrailingslashit( esc_url_raw( $node_url ) ) ); ?>';
            
            let twilioDevice = null;
            let activeCall = null;
            let socket = null;
            let callTimer = null;
            
            // Single Source of Truth for Call State
            let currentCallState = 'IDLE'; // 'IDLE' | 'RINGING' | 'ACTIVE'
            let currentCallData = null;
            let callStartTime = null;
            let isMuted = false;

            document.getElementById('agent-identity-label').innerText = AGENT_ID;

            function normalizePhoneNumber(raw) {
                if (!raw) return '';
                let cleaned = String(raw).replace(/[^\d+]/g, '');
                if (!cleaned) return '';
                if (cleaned.startsWith('0')) cleaned = '+44' + cleaned.slice(1);
                if (!cleaned.startsWith('+')) cleaned = '+' + cleaned;
                return cleaned;
            }

            // 1. BroadcastChannel Inter-tab Bus
            const ctiBus = new BroadcastChannel('vinttro_cti_bus');

            function getPhoneRegistrationStatus() {
                const isOnline = twilioDevice && (twilioDevice.state === 'registered' || twilioDevice.status?.() === 'registered');
                return {
                    status: isOnline ? 'online' : 'connecting',
                    text: isOnline ? 'RTC Ready' : 'Connecting'
                };
            }

            function broadcastFullState() {
                const reg = getPhoneRegistrationStatus();
                ctiBus.postMessage({
                    action: 'FULL_STATE_SYNC',
                    phoneStatus: reg.status,
                    phoneText: reg.text,
                    callState: currentCallState,
                    callData: currentCallData,
                    callStartTime: callStartTime,
                    isMuted: isMuted
                });
            }

            // Place Outbound PSTN Call (from Dial Pad or Click-to-Dial)
            async function placeOutboundCall({ to, clientId = null, contactId = null, opportunityId = null, taskId = null, clientName = null } = {}) {
                const formattedTo = normalizePhoneNumber(to);
                console.log('[Softphone] Outbound dial requested:', { raw: to, formattedTo, clientId, opportunityId, taskId });

                if (!formattedTo) {
                    alert('Please enter a valid phone number.');
                    return;
                }

                if (!twilioDevice || twilioDevice.state !== 'registered') {
                    alert('Softphone is not registered with Twilio yet. Check WebRTC Audio status.');
                    return;
                }

                if (currentCallState !== 'IDLE') {
                    alert('You are already on an active call.');
                    return;
                }

                try {
                    const params = { To: formattedTo };
                    if (clientId) params.clientId = String(clientId);
                    if (contactId) params.contactId = String(contactId);
                    if (opportunityId) params.opportunityId = String(opportunityId);
                    if (taskId) params.taskId = String(taskId);
                    if (clientName) params.clientName = String(clientName);

                    activeCall = await twilioDevice.connect({ params });
                    bindActiveCallEvents(activeCall);

                    const displayLabel = clientName ? `${clientName} (${formattedTo})` : formattedTo;
                    currentCallState = 'ACTIVE';
                    callStartTime = Date.now();
                    currentCallData = {
                        direction: 'Outbound',
                        to: formattedTo,
                        client_id: clientId,
                        contact_id: contactId,
                        opportunity_id: opportunityId,
                        task_id: taskId,
                        client_name: clientName || formattedTo
                    };

                    updateCallUI('ACTIVE', displayLabel);

                    ctiBus.postMessage({
                        action: 'SYNC_CALL_ACTIVE',
                        payload: {
                            displayName: displayLabel,
                            roomId: null,
                            callStartTime: callStartTime,
                            callData: currentCallData
                        }
                    });
                } catch (err) {
                    console.error('[Softphone] Outbound connect failed:', err);
                    alert('Failed to start call: ' + err.message);
                    resetCallState();
                }
            }

            function bindActiveCallEvents(call) {
                if (!call) return;
                call.on('disconnect', () => {
                    console.log('[Softphone] Call disconnected');
                    activeCall = null;
                    resetCallState();
                    ctiBus.postMessage({ action: 'SYNC_CALL_ENDED' });
                });
                call.on('cancel', () => {
                    console.log('[Softphone] Call canceled');
                    activeCall = null;
                    resetCallState();
                    ctiBus.postMessage({ action: 'SYNC_CALL_ENDED' });
                });
                call.on('error', (err) => {
                    console.error('[Softphone] Active call error:', err);
                });
            }

            // Dial Pad UI Listeners
            const dialInput = document.getElementById('dialpad-input');
            const clearBtn = document.getElementById('dialpad-clear');
            const callBtn = document.getElementById('dialpad-call-btn');

            document.querySelectorAll('.dial-digit').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const digit = btn.getAttribute('data-digit');
                    if (!digit) return;

                    if (currentCallState === 'ACTIVE' && activeCall) {
                        // Send DTMF tone during active call (e.g. external IVR menus)
                        activeCall.sendDigits(digit);
                        console.log('[Softphone] Sent DTMF:', digit);
                    } else if (dialInput) {
                        dialInput.value += digit;
                        dialInput.focus();
                    }
                });
            });

            if (clearBtn && dialInput) {
                clearBtn.addEventListener('click', () => {
                    dialInput.value = dialInput.value.slice(0, -1);
                    dialInput.focus();
                });
            }

            if (callBtn && dialInput) {
                callBtn.addEventListener('click', () => {
                    placeOutboundCall({ to: dialInput.value });
                });

                dialInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        placeOutboundCall({ to: dialInput.value });
                    }
                });
            }

            // Listen for control commands from WordPress tabs
            ctiBus.onmessage = async (event) => {
                const { action, payload } = event.data;
                console.log(`[CTI Bus] Received: ${action}`, payload);

                if (action === 'REQUEST_STATE') {
                    broadcastFullState();
                }
                else if (action === 'OUTBOUND_DIAL') {
                    if (dialInput && payload?.to) dialInput.value = payload.to;
                    await placeOutboundCall(payload || {});
                }
                else if (action === 'ANSWER_CALL') {
                    if (twilioDevice) {
                        activeCall = await twilioDevice.connect({ params: { To: payload.roomId } });
                        bindActiveCallEvents(activeCall);
                        currentCallState = 'ACTIVE';
                        callStartTime = Date.now();
                        if (currentCallData) {
                            currentCallData = { ...currentCallData, ...payload };
                        }

                        updateCallUI('ACTIVE', payload.displayName || 'Active Call');

                        ctiBus.postMessage({
                            action: 'SYNC_CALL_ACTIVE',
                            payload: {
                                displayName: payload.displayName || 'Active Call',
                                roomId: payload.roomId,
                                callStartTime: callStartTime,
                                callData: currentCallData
                            }
                        });
                    }
                } 
                else if (action === 'HANGUP_CALL') {
                    if (twilioDevice) twilioDevice.disconnectAll();
                    activeCall = null;
                    resetCallState();
                    ctiBus.postMessage({ action: 'SYNC_CALL_ENDED' });
                } 
                else if (action === 'REJECT_CALL') {
                    resetCallState();
                    ctiBus.postMessage({ action: 'SYNC_CALL_DISMISSED' });
                }
                else if (action === 'MUTE_TOGGLE') {
                    const callToMute = activeCall || (twilioDevice?.calls && twilioDevice.calls[0]);
                    if (callToMute) {
                        isMuted = payload.muted;
                        callToMute.mute(isMuted);
                        ctiBus.postMessage({ action: 'MUTE_STATE_CHANGED', muted: isMuted });
                    }
                }
                else if (action === 'UPDATE_CONTACT_SELECTION') {
                    if (currentCallData) {
                        currentCallData.selected_index = payload.selectedIndex;
                        currentCallData.selected_contact_id = payload.contactId;
                        currentCallData.selected_client_id = payload.clientId;
                        currentCallData.client_name = payload.clientName;
                        currentCallData.account_name = payload.accountName;
                    }
                    ctiBus.postMessage({ action: 'SYNC_CONTACT_SELECTION', payload: payload });
                }
            };

            function resetCallState() {
                currentCallState = 'IDLE';
                currentCallData = null;
                callStartTime = null;
                isMuted = false;
                updateCallUI('IDLE', 'Ready for Calls');
            }

            // 2. Socket.io Connection
            socket = io(NODE_URL, { transports: ['websocket', 'polling'] });

            socket.on('connect', () => {
                updateStatus('node', 'online', 'Connected');
                socket.emit('join_room', AGENT_ID);
            });

            socket.on('disconnect', () => {
                updateStatus('node', 'offline', 'Disconnected');
            });

            socket.on('incoming_call', (payload) => {
                console.log('[Socket] Incoming call received', payload);
                currentCallState = 'RINGING';
                currentCallData = payload;

                updateCallUI('RINGING', payload.client_name || payload.from || 'Unknown Caller');
                ctiBus.postMessage({ action: 'INCOMING_CALL_RINGING', payload: payload });
            });

            socket.on('call_updated', (data) => {
                if (['completed', 'canceled', 'failed'].includes(data.status)) {
                    activeCall = null;
                    resetCallState();
                    ctiBus.postMessage({ action: 'SYNC_CALL_ENDED', payload: data });
                }
            });

            // 3. Twilio WebRTC
            async function initTwilio() {
                try {
                    const res = await fetch(`${NODE_URL}/api/communicator/token?identity=${encodeURIComponent(AGENT_ID)}`);
                    if (!res.ok) throw new Error('Token fetch failed');
                    
                    const data = await res.json();
                    
                    twilioDevice = new Twilio.Device(data.token, {
                        logLevel: 1,
                        codecPreferences: ['opus', 'pcmu']
                    });

                    twilioDevice.register();

                    twilioDevice.on('registered', () => {
                        console.log('[Softphone] Twilio Device registered');
                        updateStatus('rtc', 'online', 'Ready');
                        broadcastFullState();

                        // Check if a Click-to-Dial action launched this window
                        const pendingRaw = localStorage.getItem('vinttro_pending_dial');
                        if (pendingRaw) {
                            localStorage.removeItem('vinttro_pending_dial');
                            try {
                                const pending = JSON.parse(pendingRaw);
                                if (pending.expiresAt && pending.expiresAt > Date.now()) {
                                    if (dialInput && pending.to) dialInput.value = pending.to;
                                    placeOutboundCall(pending);
                                }
                            } catch (e) {}
                        }
                    });

                    twilioDevice.on('error', (err) => {
                        updateStatus('rtc', 'offline', 'Error');
                        console.error('Twilio Error:', err);
                        ctiBus.postMessage({ action: 'PHONE_STATUS', status: 'offline', text: 'RTC Error' });
                    });

                    twilioDevice.on('disconnect', () => {
                        activeCall = null;
                        resetCallState();
                        ctiBus.postMessage({ action: 'SYNC_CALL_ENDED' });
                    });

                } catch (err) {
                    updateStatus('rtc', 'offline', 'Auth Failed');
                    console.error(err);
                    ctiBus.postMessage({ action: 'PHONE_STATUS', status: 'offline', text: 'Auth Failed' });
                }
            }

            function updateStatus(type, state, text) {
                const dot = document.getElementById(`${type}-status-dot`);
                const label = document.getElementById(`${type}-status-text`);
                if (dot) dot.className = `status-dot status-${state}`;
                if (label) label.innerText = text;
            }

            function updateCallUI(state, text) {
                const stateEl = document.getElementById('call-state');
                const targetEl = document.getElementById('call-target');
                const timerEl = document.getElementById('call-timer');
                const cardEl = document.getElementById('call-card');

                stateEl.innerText = state;
                targetEl.innerText = text;

                if (state === 'ACTIVE') {
                    cardEl.className = 'bg-blue-600 text-white p-4 rounded-md shadow-sm border border-blue-500 mt-3';
                    startTimer();
                } else if (state === 'RINGING') {
                    cardEl.className = 'bg-amber-500 text-white p-4 rounded-md shadow-sm border border-amber-400 mt-3';
                    timerEl.innerText = 'Ringing...';
                    stopTimer();
                } else {
                    cardEl.className = 'bg-slate-800 text-white p-4 rounded-md shadow-sm border border-slate-700 mt-3';
                    timerEl.innerText = '00:00';
                    stopTimer();
                }
            }

            function startTimer() {
                stopTimer();
                callStartTime = callStartTime || Date.now();
                callTimer = setInterval(() => {
                    const elapsed = Math.floor((Date.now() - callStartTime) / 1000);
                    const mins = String(Math.floor(elapsed / 60)).padStart(2, '0');
                    const secs = String(elapsed % 60).padStart(2, '0');
                    document.getElementById('call-timer').innerText = `${mins}:${secs}`;
                }, 1000);
            }

            function stopTimer() {
                if (callTimer) clearInterval(callTimer);
                callTimer = null;
            }

            window.addEventListener('beforeunload', () => {
                ctiBus.postMessage({ action: 'PHONE_STATUS', status: 'offline', text: 'Phone Off' });
            });

            initTwilio();
        </script>
    </body>
    </html>
    <?php
}