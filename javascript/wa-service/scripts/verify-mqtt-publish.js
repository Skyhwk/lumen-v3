#!/usr/bin/env node
/**
 * Tes publish event WA via waMqtt layer (Fase 1).
 *
 * Usage: npm run verify:mqtt:publish -- [userId]
 */

require('dotenv').config();
const { initWaMqtt, publishWa, clearRetainedWa } = require('../src/mqtt/waMqtt');
const { TOPIC_SUFFIX, WA_EVENTS } = require('../src/mqtt/topicSchema');

async function main() {
    const userId = process.argv[2] || 'verify_test_user';

    await initWaMqtt();
    console.log('[verify-mqtt:publish] connected');

    publishWa(userId, TOPIC_SUFFIX.STATUS, WA_EVENTS.STATUS, {
        status: 'connecting',
        verify: true,
    }, { retain: true });

    publishWa(userId, TOPIC_SUFFIX.QR, WA_EVENTS.QR, {
        qr: 'data:image/png;base64,verify',
    }, { retain: true });

    publishWa(userId, TOPIC_SUFFIX.MESSAGE_NEW, WA_EVENTS.MESSAGE_NEW, {
        message: { id: 'verify-1', body: 'test mqtt fase 1' },
    });

    clearRetainedWa(userId, TOPIC_SUFFIX.QR);
    publishWa(userId, TOPIC_SUFFIX.STATUS, WA_EVENTS.STATUS, {
        status: 'disconnected',
        verify: true,
    }, { retain: true });

    console.log('[verify-mqtt:publish] ✅ publish helpers OK for user', userId);
    process.exit(0);
}

main().catch((err) => {
    console.error('[verify-mqtt:publish] ❌', err.message);
    process.exit(1);
});
