// vinttro-api/services/suitecrm.js
const redisClient = require('./redis'); // Centralized Redis DB 1

const SUITECRM_URL = (process.env.SUITECRM_URL || '').replace(/\/$/, '');
const CLIENT_ID = process.env.SUITECRM_CLIENT_ID;
const CLIENT_SECRET = process.env.SUITECRM_CLIENT_SECRET;
const USERNAME = process.env.SUITECRM_USERNAME;
const PASSWORD = process.env.SUITECRM_PASSWORD;

/**
 * Fetch or retrieve cached SuiteCRM OAuth2 Access Token
 */
async function getAccessToken() {
    const redisKey = 'suitecrm:token';
    
    // 1. Check Redis DB 1 cache
    try {
        const cachedToken = await redisClient.get(redisKey);
        if (cachedToken) return cachedToken;
    } catch (err) {
        console.warn('[SuiteCRM Auth Warning] Failed to read token from Redis:', err.message);
    }

    // 2. Obtain new token from SuiteCRM
    console.log('[SuiteCRM Auth] Requesting new OAuth2 access token...');
    
    const tokenUrl = `${SUITECRM_URL}/access_token`;
    const response = await fetch(tokenUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            grant_type: 'password',
            client_id: CLIENT_ID,
            client_secret: CLIENT_SECRET,
            username: USERNAME,
            password: PASSWORD
        })
    });

    if (!response.ok) {
        const errorText = await response.text();
        throw new Error(`Auth failed (HTTP ${response.status}): ${errorText}`);
    }

    const data = await response.json();
    const token = data.access_token;
    const expiresIn = (data.expires_in || 3600) - 300; // Cache with 5 min buffer

    if (token) {
        // Cache token in Redis DB 1
        await redisClient.setEx(redisKey, expiresIn, token);
        return token;
    }

    throw new Error('No access_token returned by SuiteCRM');
}

/**
 * Generic JSON API Record Creator for SuiteCRM V8
 */
async function createRecord(moduleName, attributes) {
    const token = await getAccessToken();
    const apiUrl = `${SUITECRM_URL}/V8/module`;

    const payload = {
        data: {
            type: moduleName,
            attributes: attributes
        }
    };

    const response = await fetch(apiUrl, {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/vnd.api+json',
            'Accept': 'application/vnd.api+json'
        },
        body: JSON.stringify(payload)
    });

    if (!response.ok) {
        const errText = await response.text();
        throw new Error(`SuiteCRM V8 module write failed (${response.status}): ${errText}`);
    }

    return await response.json();
}

/**
 * Formats and writes completed call records to SuiteCRM
 */
async function saveCallRecord(callData) {
    const identifier = callData.call_id || callData.conference_sid || 'UNKNOWN_CALL';
    const callerNumber = callData.from || callData.callerId || 'Unknown';
    const durationSeconds = parseInt(callData.duration_seconds || 0, 10);

    const durationHours = Math.floor(durationSeconds / 3600);
    const durationMinutes = Math.floor((durationSeconds % 3600) / 60);

    const startTime = callData.timestamp 
        ? new Date(callData.timestamp).toISOString().replace('T', ' ').substring(0, 19)
        : new Date().toISOString().replace('T', ' ').substring(0, 19);

    const legsJson = JSON.stringify(callData.legs || [], null, 2);

    const attributes = {
        name: `Inbound Call - ${callerNumber}`,
        direction: 'Inbound',
        status: 'Held',
        date_start: startTime,
        duration_hours: durationHours,
        duration_minutes: durationMinutes,
        description: `Call SID: ${identifier}\nCaller: ${callerNumber}\nFirst Agent: ${callData.first_agent || 'N/A'}\nLast Agent: ${callData.last_agent || 'N/A'}\nRecording URL: ${callData.recording_url || 'N/A'}\n\n--- Call Legs ---\n${legsJson}`,
        
        // SuiteCRM Custom Fields (Populate these if created in SuiteCRM Studio)
        first_agent_c: callData.first_agent || '',
        last_agent_c: callData.last_agent || '',
        call_legs_json_c: JSON.stringify(callData.legs || [])
    };

    console.log(`[SuiteCRM Sync] Pushing Call ${identifier} (First Agent: ${callData.first_agent || 'None'}) to SuiteCRM...`);
    const result = await createRecord('Calls', attributes);
    console.log(`✅ [SuiteCRM Sync Success] Call record created with ID: ${result?.data?.id || 'OK'}`);
    return result;
}

module.exports = {
    getAccessToken,
    createRecord,
    saveCallRecord
};