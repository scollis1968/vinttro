const express = require('express');
const router = express.Router();
const { createClient } = require('redis');

// Redis DB 1 Connection
const redisClient = createClient({
    url: process.env.REDIS_URL || 'redis://127.0.0.1:6379',
    database: 1
});
redisClient.on('error', (err) => console.error('Redis Tasks Error:', err));
redisClient.connect().then(() => console.log('Tasks connected to Redis DB 1'));

// POST /api/tasks/seed
router.post('/seed', async (req, res) => {
    try {
        const agentEmail = req.body.agentEmail || 'finley.collis@vinttro.co.uk';
        const mockTasks = [
            { id: '101', type: 'OUTBOUND_CALL', title: 'John Doe Call', target: '+447700900077', meta: 'Lead ID: 881', notes: 'Wants fleet pricing on 5 vehicles.' },
            { id: '102', type: 'RESEARCH', title: 'Apex Group Fleet Audit', target: 'Staging Record #402', meta: 'Case Ref: AX-2026', notes: 'Verify driver insurance compliance in the visp_data_staging grid.' },
            { id: '103', type: 'CASE_REVIEW', title: 'Approve Sarah Smith Contract', target: 'Document Store', meta: 'Doc ID: 902', notes: 'Review contract terms and sign off on credit verification parameters.' }
        ];

        for (const task of mockTasks) {
            await redisClient.hSet(`vinttro:task:${task.id}`, {
                id: task.id,
                type: task.type,
                title: task.title,
                target: task.target,
                meta: task.meta,
                notes: task.notes,
                status: 'pending'
            });
            await redisClient.sAdd(`vinttro:agent:${agentEmail}:tasks`, task.id);
        }

        res.json({ success: true, message: `Successfully seeded ${mockTasks.length} tasks for ${agentEmail}` });
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

// GET /api/tasks
router.get('/', async (req, res) => {
    try {
        const agentEmail = req.query.agent || 'finley.collis@vinttro.co.uk';
        const taskIds = await redisClient.sMembers(`vinttro:agent:${agentEmail}:tasks`);
        const tasks = [];

        for (const id of taskIds) {
            const taskData = await redisClient.hGetAll(`vinttro:task:${id}`);
            if (taskData && taskData.id) {
                tasks.push(taskData);
            }
        }

        tasks.sort((a, b) => parseInt(a.id) - parseInt(b.id));
        res.json({ success: true, tasks });
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

module.exports = router;