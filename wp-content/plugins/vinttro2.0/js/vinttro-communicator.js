(function($) {
    "use strict";

    $(document).ready(function() {
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