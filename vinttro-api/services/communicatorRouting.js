// vinttro-api/services/communicatorRouting.js
const redisClient = require('./redis');

/**
 * Perform hierarchical call route lookup:
 * 1. CLI + DDI
 * 2. DDI only
 * 3. CLI only
 */
async function findRouteRecord(cli, ddi) {
    // Try exact CLI + DDI match first, then DDI, then CLI
    const keysToTry = [
        `route:cli:${cli}:ddi:${ddi}`,
        `route:ddi:${ddi}`,
        `route:cli:${cli}`
    ];

    for (const key of keysToTry) {
        const rawData = await redisClient.get(key);
        if (rawData) {
            const parsed = JSON.parse(rawData);
            return {
                matched_key: key,
                ...parsed
            };
        }
    }

    return null;
}

/**
 * Save or update a call routing record in Redis
 */
async function saveRouteRecord(payload) {
    const { 
        cli = '', 
        ddi = '', 
        ou = '', 
        screen_pop = {}, 
        delivery_plan = {} 
    } = payload;

    // 1. Determine key precedence (DDI + CLI match, or DDI only, or CLI only)
    let redisKey = '';
    if (cli && ddi) {
        redisKey = `route:cli:${cli}:ddi:${ddi}`;
    } else if (ddi) {
        redisKey = `route:ddi:${ddi}`;
    } else if (cli) {
        redisKey = `route:cli:${cli}`;
    } else {
        throw new Error('Either CLI or DDI is required to save a route key');
    }

    // 2. Build full payload
    const routeData = {
        cli,
        ddi,
        ou,
        screen_pop,
        delivery_plan,
        updated_at: new Date().toISOString()
    };

    // 3. Save as JSON string in Redis (no TTL so routes persist)
    await redisClient.set(redisKey, JSON.stringify(routeData));

    return { redisKey, routeData };
}

/**
 * Delete a call routing record from Redis
 */
async function deleteRouteRecord(cli, ddi) {
    const normalizedCli = cli ? cli.trim() : '*';
    const normalizedDdi = ddi ? ddi.trim() : '*';
    const redisKey = `route:${normalizedCli}:${normalizedDdi}`;

    const removed = await redisClient.del(redisKey);
    return { redisKey, removed: removed > 0 };
}

module.exports = {
    saveRouteRecord,
    findRouteRecord,
    deleteRouteRecord
};