// test-deploy.js
const BASE_URL = 'http://localhost:3000';
const TEST_CALL_ID = `deploy-test-${Date.now()}`;

async function runTests() {
    console.log(`🚀 Starting Post-Deployment Smoke Tests (Call ID: ${TEST_CALL_ID})...\n`);
    let passed = true;

    try {
        // TEST 1: Create Call via JSON (SuiteCRM Simulation)
        const t1 = await fetch(`${BASE_URL}/api/call-event`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ call_id: TEST_CALL_ID, status: 'ringing', source: 'Test_SuiteCRM' })
        });
        const r1 = await t1.json();
        if (t1.status === 200 && r1.success === true) {
            console.log('✅ Test 1 Passed: JSON ingestion and call initialization working.');
        } else {
            console.error('❌ Test 1 Failed: Could not initialize call via JSON.');
            passed = false;
        }

        // TEST 2: Update Call via URL-Encoded Form (Twilio Simulation)
        const formParams = new URLSearchParams();
        formParams.append('CallSid', TEST_CALL_ID);
        formParams.append('CallStatus', 'answered');

        const t2 = await fetch(`${BASE_URL}/api/call-event`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formParams
        });
        const r2 = await t2.json();
        if (t2.status === 200 && r2.state === 'answered') {
            console.log('✅ Test 2 Passed: Twilio URL-encoded form parsing working.');
        } else {
            console.error('❌ Test 2 Failed: Twilio form update failed.');
            passed = false;
        }

        // TEST 3: State Machine Priority Check (Out-of-Order Packet)
        const t3 = await fetch(`${BASE_URL}/api/call-event`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ call_id: TEST_CALL_ID, status: 'ringing', source: 'Test_Delayed' })
        });
        const r3 = await t3.json();
        if (t3.status === 200 && r3.message && r3.message.includes('ignored')) {
            console.log('✅ Test 3 Passed: Out-of-order state tracking protection active.');
        } else {
            console.error('❌ Test 3 Failed: State engine allowed a completed/answered call to revert to ringing.');
            passed = false;
        }

        // TEST 4: Read back state from Redis (WordPress Simulation)
        const t4 = await fetch(`${BASE_URL}/api/call/${TEST_CALL_ID}`);
        const r4 = await t4.json();
        if (t4.status === 200 && r4.status === 'answered' && r4.updated_by === 'Twilio') {
            console.log('✅ Test 4 Passed: End-to-end data retrieval from Redis DB 1 verified.');
        } else {
            console.error('❌ Test 4 Failed: Retried state data mismatch.');
            passed = false;
        }

    } catch (error) {
        console.error('❌ Deployment Test Execution Error:', error.message);
        passed = false;
    }

    console.log('\n--------------------------------------------');
    if (passed) {
        console.log('🎉 DEPLOYMENT SUCCESSFUL: All systems nominal.');
        process.exit(0);
    } else {
        console.log('🚨 DEPLOYMENT FAILURE: Check PM2 logs immediately.');
        process.exit(1);
    }
}

runTests();