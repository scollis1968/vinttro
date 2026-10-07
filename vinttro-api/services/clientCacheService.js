// vinttro-api/services/clientCacheService.js
const redisClient = require('./redis');

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
 * Retrieve client details by CLI (Caller ID)
 */
async function getClientByCli(rawCli) {
    const cli = normalizeCli(rawCli);
    if (!cli) return null;

    const redisKey = `client:${cli}`;
    try {
        const rawData = await redisClient.get(redisKey);
        if (rawData) {
            return { cli, ...JSON.parse(rawData) };
        }
    } catch (err) {
        console.error(`[ClientCache] Error looking up key ${redisKey}:`, err.message);
    }

    return null;
}

/**
 * Save or update a single client record in Redis
 */
async function saveClientRecord(data) {
    const {
        cli: rawCli,
        client_name,
        client_id,
        status = 'Active',
        account_tier = null,
        crm_url = null,
        active_leads = [],
        extra_meta = {}
    } = data;

    const cli = normalizeCli(rawCli);
    if (!cli) {
        throw new Error('A valid phone number (CLI) is required to cache client');
    }

    const redisKey = `client:${cli}`;

    const clientPayload = {
        cli,
        client_id: client_id || null,
        client_name: client_name || 'Unknown Client',
        status,
        account_tier,
        crm_url,
        active_leads,
        extra_meta,
        updated_at: new Date().toISOString()
    };

    await redisClient.set(redisKey, JSON.stringify(clientPayload));
    return { redisKey, clientPayload };
}

/**
 * Remove a client record from Redis
 */
async function deleteClientRecord(rawCli) {
    const cli = normalizeCli(rawCli);
    if (!cli) return { removed: false };

    const redisKey = `client:${cli}`;
    const removed = await redisClient.del(redisKey);
    return { redisKey, removed: removed > 0 };
}

/**
 * Bulk upsert client records using Redis Pipelining (multi) for maximum speed
 */
async function bulkSyncClients(clientsArray = []) {
    if (!Array.isArray(clientsArray) || clientsArray.length === 0) {
        return { total: 0, synced: 0, errors: [] };
    }

    const multi = redisClient.multi();
    let successCount = 0;
    const errors = [];

    for (const record of clientsArray) {
        const cli = normalizeCli(record.cli);
        if (!cli) {
            errors.push({ record, error: 'A valid phone number (CLI) is required' });
            continue;
        }

        const redisKey = `client:${cli}`;
        const clientPayload = {
            cli,
            client_id: record.client_id || null,
            client_name: record.client_name || 'Unknown Client',
            status: record.status || 'Active',
            account_tier: record.account_tier || null,
            crm_url: record.crm_url || null,
            active_leads: record.active_leads || [],
            extra_meta: record.extra_meta || {},
            updated_at: new Date().toISOString()
        };

        multi.set(redisKey, JSON.stringify(clientPayload));
        successCount++;
    }

    if (successCount > 0) {
        await multi.exec();
    }

    return { total: clientsArray.length, synced: successCount, errors };
}

module.exports = {
    normalizeCli,
    getClientByCli,
    saveClientRecord,
    deleteClientRecord,
    bulkSyncClients
};