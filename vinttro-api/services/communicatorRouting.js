// vinttro-api/services/communicatorRouting.js
const redisClient = require('./redis');

/**
 * Perform hierarchical call route lookup:
 * 1. CLI + DDI
 * 2. CLI + *
 * 3. * + DDI
 */
async function findRouteRecord(cli, ddi) {
    const rawCli = cli ? cli.trim() : '*';
    const rawDdi = ddi ? ddi.trim() : '*';

    const searchKeys = [
        `route:${rawCli}:${rawDdi}`,
        `route:${rawCli}:*`,
        `route:*:${rawDdi}`
    ];

    for (const key of searchKeys) {
        try {
            const rawRecord = await redisClient.get(key);
            if (rawRecord) {
                const parsed = JSON.parse(rawRecord);
                return { matched_key: key, ...parsed };
            }
        } catch (err) {
            console.error(`[Redis Route Lookup Error] Key ${key}:`, err.message);
        }
    }

    return null;
}

/**
 * Save or update a call routing record in Redis
 */
async function saveRouteRecord(data) {
    const { cli, ddi, client_name, client_id, ou, delivery_plan, screen_pop } = data;

    const normalizedCli = cli ? cli.trim() : '*';
    const normalizedDdi = ddi ? ddi.trim() : '*';
    const redisKey = `route:${normalizedCli}:${normalizedDdi}`;

    const routeData = {
        cli: normalizedCli,
        ddi: normalizedDdi,
        client_name: client_name || null,
        client_id: client_id || null,
        ou: ou || null,
        delivery_plan: delivery_plan || null,
        screen_pop: screen_pop || {},
        updated_at: new Date().toISOString()
    };

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
    findRouteRecord,
    saveRouteRecord,
    deleteRouteRecord
};