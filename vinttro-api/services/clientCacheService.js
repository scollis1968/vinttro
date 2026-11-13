// vinttro-api/services/clientCacheService.js
const redisClient = require('./redis');
const suitecrmService = require('./suitecrm');

/**
 * Normalizes phone numbers to standard E.164 international format.
 */
function normalizeCli(rawNumber) {
    if (!rawNumber) return null;

    let cleaned = String(rawNumber).replace(/[^\d+]/g, '');
    if (!cleaned) return null;

    if (cleaned.startsWith('0')) {
        cleaned = '+44' + cleaned.slice(1);
    }

    if (!cleaned.startsWith('+')) {
        cleaned = '+' + cleaned;
    }

    return cleaned;
}

/**
 * Retrieve client details by CLI (Caller ID) from client_cli index
 */
async function getClientByCli(rawCli) {
    const cli = normalizeCli(rawCli);
    if (!cli) return null;

    try {
        // 1. Get current active dataset version
        const activeVersion = await redisClient.get('client_cli:active_version');
        const redisKey = activeVersion 
            ? `client_cli:${activeVersion}:${cli}` 
            : `client_cli:${cli}`;

        const rawData = await redisClient.get(redisKey);
        
        if (rawData) {
            const matches = JSON.parse(rawData);
            return {
                cli,
                matches,
                // Convenient shorthand for single-match callers
                client_name: matches[0]?.contact_name || null,
                client_id: matches[0]?.client_id || null,
                account_name: matches[0]?.account_name || null,
                contact_id: matches[0]?.contact_id || null
            };
        }

        // 2. Fallback check for legacy single client records (client:<cli>)
        const legacyData = await redisClient.get(`client:${cli}`);
        if (legacyData) {
            return { cli, ...JSON.parse(legacyData) };
        }
    } catch (err) {
        console.error(`[ClientCache] Error looking up phone ${cli}:`, err.message);
    }

    return null;
}

/**
 * Full Nightly/Daytime Sync: Pulls Contacts from SuiteCRM and atomically swaps Redis dataset
 */
async function syncClientsFromSuiteCRM() {
    const startTime = Date.now();

    // Fetch contacts (and bulk opportunities if available on suitecrmService)
    const [contacts, opportunities] = await Promise.all([
        suitecrmService.getAllContacts(),
        typeof suitecrmService.getAllOpportunities === 'function'
            ? suitecrmService.getAllOpportunities()
            : Promise.resolve([])
    ]);

    if (!contacts || contacts.length === 0) {
        return { success: false, message: 'No contacts retrieved from SuiteCRM' };
    }

    const newVersion = `v_${Date.now()}`;
    const phoneMap = new Map();   // Normalized CLI -> Array of contact payloads
    const summaryMap = new Map(); // clientId -> Client Summary object

    // 1. Index Opportunities by account_id (if bulk-fetched)
    const oppsByClientId = new Map();
    for (const opp of opportunities) {
        const oppAttr = opp.attributes || opp;
        const accId = oppAttr.account_id;
        if (!accId) continue;

        if (!oppsByClientId.has(accId)) {
            oppsByClientId.set(accId, []);
        }
        oppsByClientId.get(accId).push({
            id: opp.id,
            type: (oppAttr.policy_type_c || oppAttr.opportunity_type || 'business').toLowerCase(),
            tab_label: oppAttr.name || 'Policy',
            tab_tooltip: oppAttr.description || oppAttr.name || '',
            status: oppAttr.sales_stage || 'active',
            // Include any additional fields OpportunityShell expects here
            raw: oppAttr
        });
    }

    // 2. Group Contacts for both client_cli and client_summary
    for (const record of contacts) {
        const attr = record.attributes || {};
        const contactId = record.id;
        const clientId = attr.account_id || null;
        const accountName = attr.account_name || null;
        const contactName = `${attr.first_name || ''} ${attr.last_name || ''}`.trim() || 'Unknown Contact';

        const payload = {
            contact_id: contactId,
            contact_name: contactName,
            client_id: clientId,
            account_name: accountName
        };

        const phoneFields = [attr.phone_work, attr.phone_mobile, attr.phone_home, attr.phone_other, attr.phone_fax];
        let primaryNormalizedPhone = null;

        for (const rawPhone of phoneFields) {
            const normalized = normalizeCli(rawPhone);
            if (!normalized) continue;

            if (!primaryNormalizedPhone) primaryNormalizedPhone = normalized;

            if (!phoneMap.has(normalized)) {
                phoneMap.set(normalized, []);
            }

            const existingList = phoneMap.get(normalized);
            if (!existingList.some(item => item.contact_id === contactId)) {
                existingList.push(payload);
            }
        }

        // Build/merge base client_summary entry when linked to an Account/Client ID
        if (clientId && !summaryMap.has(clientId)) {
            const addressParts = [
                attr.primary_address_street,
                attr.primary_address_city,
                attr.primary_address_postalcode
            ].filter(Boolean);

            summaryMap.set(clientId, {
                client_id: clientId,
                client_name: accountName || contactName,
                details: {
                    phone: primaryNormalizedPhone || attr.phone_work || attr.phone_mobile || '',
                    email: attr.email1 || '',
                    primary_address: addressParts.join(', ')
                },
                opportunities: oppsByClientId.get(clientId) || [],
                retail: {
                    active_offers_count: 0,
                    offers: []
                },
                cached_at: new Date().toISOString()
            });
        }
    }

    // 3. Pipeline-write both client_cli and client_summary in batches
    let pipeline = redisClient.multi();
    let commandCount = 0;
    const BATCH_SIZE = 1000;

    for (const [cli, matches] of phoneMap.entries()) {
        pipeline.set(`client_cli:${newVersion}:${cli}`, JSON.stringify(matches));
        commandCount++;

        if (commandCount % BATCH_SIZE === 0) {
            await pipeline.exec();
            pipeline = redisClient.multi();
        }
    }

    for (const [clientId, summary] of summaryMap.entries()) {
        pipeline.set(`client_summary:${clientId}`, JSON.stringify(summary));
        commandCount++;

        if (commandCount % BATCH_SIZE === 0) {
            await pipeline.exec();
            pipeline = redisClient.multi();
        }
    }

    if (commandCount % BATCH_SIZE !== 0) {
        await pipeline.exec();
    }

    // 4. Atomic Pointer Swap for CLI index
    const previousVersion = await redisClient.get('client_cli:active_version');
    await redisClient.set('client_cli:active_version', newVersion);

    // 5. Async Cleanup of Old CLI Version Keys
    if (previousVersion) {
        cleanupOldVersionKeys(`client_cli:${previousVersion}:*`).catch(err =>
            console.error('[ClientCache Cleanup Warning]:', err.message)
        );
    }

    const duration = ((Date.now() - startTime) / 1000).toFixed(2);
    console.log(`✅ [ClientCache Sync Completed] ${phoneMap.size} numbers & ${summaryMap.size} client summaries indexed in ${duration}s`);

    return {
        total_contacts: contacts.length,
        total_opportunities: opportunities.length,
        indexed_phone_numbers: phoneMap.size,
        indexed_client_summaries: summaryMap.size,
        active_version: newVersion,
        duration_seconds: parseFloat(duration)
    };
}
/**
 * Asynchronously unlinks previous version keys using Redis SCAN
 */
async function cleanupOldVersionKeys(pattern) {
    let cursor = 0;
    let keysToDelete = [];

    do {
        const reply = await redisClient.scan(cursor, { MATCH: pattern, COUNT: 500 });
        cursor = reply.cursor;
        keysToDelete = keysToDelete.concat(reply.keys);

        if (keysToDelete.length >= 1000) {
            await redisClient.unlink(keysToDelete);
            keysToDelete = [];
        }
    } while (cursor !== 0);

    if (keysToDelete.length > 0) {
        await redisClient.unlink(keysToDelete);
    }
}

/**
 * Single contact update for SuiteCRM after_save hook
 */
async function saveClientRecord(data) {
    const activeVersion = await redisClient.get('client_cli:active_version') || 'v_live';
    const cli = normalizeCli(data.cli);
    
    if (!cli) throw new Error('A valid phone number (CLI) is required');

    const key = `client_cli:${activeVersion}:${cli}`;
    const rawExisting = await redisClient.get(key);
    let existingMatches = rawExisting ? JSON.parse(rawExisting) : [];

    const newMatch = {
        contact_id: data.contact_id || data.id,
        contact_name: data.contact_name || `${data.first_name || ''} ${data.last_name || ''}`.trim(),
        client_id: data.client_id || data.account_id || null,
        account_name: data.account_name || null
    };

    existingMatches = existingMatches.filter(m => m.contact_id !== newMatch.contact_id);
    existingMatches.push(newMatch);

    await redisClient.set(key, JSON.stringify(existingMatches));
    return { key, clientPayload: existingMatches };
}

/**
 * Delete client/contact from current version
 */
async function deleteClientRecord(rawCli) {
    const cli = normalizeCli(rawCli);
    if (!cli) return { removed: false };

    const activeVersion = await redisClient.get('client_cli:active_version') || 'v_live';
    const redisKey = `client_cli:${activeVersion}:${cli}`;
    const removed = await redisClient.del(redisKey);
    return { redisKey, removed: removed > 0 };
}

// Add to vinttro-api/services/clientCacheService.js

/**
 * Save or overwrite the structured Client Summary document for the Agent Dashboard
 * @param {string} clientId - SuiteCRM Account/Client ID
 * @param {object} summaryData - The parsed JSON structure (details, opportunities, retail)
 * @param {number} [ttlSeconds=0] - Optional TTL (0 = persist until next sync/update)
 */
async function saveClientSummary(clientId, summaryData, ttlSeconds = 0) {
    if (!clientId) throw new Error('clientId is required to cache client summary');
    if (!summaryData || typeof summaryData !== 'object') {
        throw new Error('summaryData must be a valid object');
    }

    const key = `client_summary:${clientId}`;
    
    // Inject/standardize metadata
    const payload = {
        ...summaryData,
        client_id: clientId,
        cached_at: new Date().toISOString()
    };

    const serialized = JSON.stringify(payload);

    if (ttlSeconds > 0) {
        await redisClient.setex(key, ttlSeconds, serialized);
    } else {
        await redisClient.set(key, serialized);
    }

    return { key, client_id: clientId, cached_at: payload.cached_at };
}

/**
 * Fetch the Client Summary document by Client ID for the Agent Dashboard
 * @param {string} clientId 
 */
async function getClientSummary(clientId) {
    if (!clientId) return null;

    const key = `client_summary:${clientId}`;
    const rawData = await redisClient.get(key);

    if (!rawData) return null;

    try {
        return JSON.parse(rawData);
    } catch (err) {
        console.error(`[ClientCache] Error parsing summary for ${clientId}:`, err.message);
        return null;
    }
}

// Export the new methods alongside existing ones
module.exports = {
    normalizeCli,
    getClientByCli,
    syncClientsFromSuiteCRM,
    saveClientRecord,
    deleteClientRecord,
    saveClientSummary,
    getClientSummary
};