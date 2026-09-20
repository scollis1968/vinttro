// vinttro-api/services/redis.js
const { createClient } = require('redis');

const redisClient = createClient({
    url: process.env.REDIS_URL || 'redis://127.0.0.1:6379',
    database: 1
});

redisClient.on('error', (err) => console.error('Redis DB 1 Error:', err));

// Connect automatically on app start
redisClient.connect().then(() => {
    console.log('Centralized Redis Service connected to DB 1');
});

module.exports = redisClient;