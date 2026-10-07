// services/deliveryPlanEngine.js

/**
 * Stub function to fetch current OU status (Open/Closed/After-Hours)
 * Later, this can read schedules from Redis or MariaDB.
 */
async function getOuState(ouName) {
    console.log(`🔍 Checking state for Operating Unit: "${ouName}"`);
    return 'open'; // Stubbed for now
}

/**
 * Strategy Registry: Handlers for each step action type
 */

// vinttro-api/services/deliveryPlanEngine.js
const twilio = require('twilio');

const accountSid = process.env.TWILIO_ACCOUNT_SID;
const apiKeySid = process.env.TWILIO_API_KEY_SID;
const apiKeySecret = process.env.TWILIO_API_KEY_SECRET;
const authToken = process.env.TWILIO_AUTH_TOKEN;
const twilioFromNumber = process.env.TWILIO_PHONE_NUMBER;

// Initialize Twilio client using either API Key/Secret OR Auth Token
let twilioClient = null;
if (apiKeySid && apiKeySecret && accountSid) {
    twilioClient = twilio(apiKeySid, apiKeySecret, { accountSid });
} else if (accountSid && authToken) {
    twilioClient = twilio(accountSid, authToken);
}

// Helper: Normalize UK numbers (+4407... -> +447...)
function normalizePhoneNumber(phone) {
    if (!phone) return phone;
    let cleaned = phone.replace(/\s+/g, '');
    if (cleaned.startsWith('+440')) {
        cleaned = '+44' + cleaned.slice(4);
    }
    return cleaned;
}

// Helper: Determine target channel type
function getTargetType(destination) {
    if (!destination) return null;
    if (destination.includes('@')) return 'webrtc';
    if (/^\+?[0-9\s\-]{7,15}$/.test(destination.trim())) return 'pstn';
    return 'unknown';
}

const actionHandlers = {
    /**
     * Action: QUEUE / DISTRIBUTE CALL
     */
    queue: async (step, context) => {
        const { callPayload, io } = context;
        const { name, distribution, members = [] } = step;
        const clientName = callPayload.client_name || callPayload.callerId || 'a customer';

        console.log(`📞 [Plan Action: QUEUE] "${name}" (${distribution})`);

        for (const member of members) {
            const targets = [member.dial1, member.dial2].filter(Boolean);

            for (const target of targets) {
                const targetType = getTargetType(target);

                // --- BRANCH A: WebRTC (Socket.IO via Email) ---
                if (targetType === 'webrtc') {
                    if (io) {
                        const roomSockets = io.sockets.adapter.rooms.get(target);
                        if (roomSockets && roomSockets.size > 0) {
                            console.log(`🔔 WebRTC Ringing: ${member.name} (${target})`);
                            io.to(target).emit('incoming_call', {
                                ...callPayload,
                                queue_name: name
                            });
                        } else {
                            console.log(`💤 WebRTC Offline: ${member.name} (${target})`);
                        }
                    }
                } 

                // --- BRANCH B: PSTN Probe Call (Outbound Phone Call) ---
                // --- BRANCH B: PSTN Probe Call (Outbound Phone Call) ---
                else if (targetType === 'pstn') {
                    const formattedTarget = normalizePhoneNumber(target);
                    console.log(`📱 [PSTN Probe] Preparing call to: ${member.name} (${formattedTarget})`);
                    console.log(`   └─ From Number: ${twilioFromNumber}`);
                    console.log(`   └─ Twilio Client Ready: ${!!twilioClient}`);

                    if (twilioClient && twilioFromNumber) {
                        try {
                            const callbackUrl = `${process.env.PUBLIC_API_URL || 'https://services.uat.vinttro.co.uk'}/api/communicator/accept-probe?roomId=${encodeURIComponent(callPayload.roomId)}&clientName=${encodeURIComponent(clientName)}`;

                            const twiml = new twilio.twiml.VoiceResponse();
                            const gather = twiml.gather({
                                numDigits: 1,
                                action: callbackUrl,
                                method: 'POST',
                                timeout: 10
                            });
                            gather.say(`This is an inbound call from ${clientName}. Press 1 to accept.`);
                            twiml.say('Call acceptance timed out. Goodbye.');

                            console.log(`🚀 [Twilio API] Sending calls.create request for ${formattedTarget}...`);

                            const call = await twilioClient.calls.create({
                                twiml: twiml.toString(),
                                to: formattedTarget,
                                from: twilioFromNumber
                            });

                            console.log(`✅ [Twilio API Success] Call queued in Twilio! CallSid: ${call.sid}`);

                        } catch (err) {
                            console.error(`❌ [Twilio API Error] Code: ${err.code} | Message: ${err.message}`);
                        }
                    } else {
                        console.warn('⚠️ Twilio client or TWILIO_PHONE_NUMBER missing; skipping PSTN probe.');
                    }
                }
            }
        }

        return { status: 'distribution_initiated' };
    }
};

/**
 * Main Delivery Plan Processor
 */
async function processDeliveryPlan(callPayload, routeRecord, reqApp) {
    const io = reqApp.get('io');
    const deliveryPlan = routeRecord?.delivery_plan;

    if (!deliveryPlan) {
        console.warn('⚠️ No delivery plan attached to route record. Defaulting to park.');
        return null;
    }

    // 1. Evaluate Operating Unit State
    const ouState = await getOuState(routeRecord.ou); // 'open'
    const planForState = deliveryPlan[ouState];

    if (!planForState || !Array.isArray(planForState.steps)) {
        console.warn(`⚠️ No plan steps defined for OU state: "${ouState}"`);
        return null;
    }

    console.log(`🚀 Executing Delivery Plan for state [${ouState.toUpperCase()}] (${planForState.steps.length} steps)`);

    // 2. Context shared across all step executions
    const context = {
        callPayload,
        routeRecord,
        io,
        ouState
    };

    // 3. Execute Steps Sequentially
    const stepResults = [];
    for (const step of planForState.steps) {
        const handler = actionHandlers[step.action];

        if (handler) {
            try {
                const result = await handler(step, context);
                stepResults.push({ action: step.action, success: true, result });
            } catch (err) {
                console.error(`❌ Step execution failed [${step.action}]:`, err.message);
                stepResults.push({ action: step.action, success: false, error: err.message });
                break; // Stop execution on step failure if needed
            }
        } else {
            console.warn(`⚠️ Unknown step action handler: "${step.action}"`);
        }
    }

    return stepResults;
}

module.exports = {
    getOuState,
    processDeliveryPlan
};