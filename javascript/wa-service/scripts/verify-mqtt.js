#!/usr/bin/env node
/**
 * Fase 0 — verifikasi koneksi & ACL topic MQTT WhatsApp.
 *
 * Usage:
 *   cd javascript/wa-service
 *   cp .env.example .env   # isi MQTT_* sama dengan Lumen/notifikasi
 *   npm install
 *   npm run verify:mqtt
 *   npm run verify:mqtt -- 12345   # userId test custom
 */

require('dotenv').config();
const mqtt = require('mqtt');
const {
    buildWaTopic,
    buildWaSubscribePattern,
    TOPIC_SUFFIX,
    WA_EVENTS,
    wrapPayload,
} = require('../src/mqtt/topicSchema');

function resolveBrokerUrl() {
    if (process.env.MQTT_BROKER_URL) {
        return process.env.MQTT_BROKER_URL.trim();
    }

    const host = process.env.MQTT_HOST || 'apps.intilab.com';
    const port = process.env.MQTT_PORT || '1111';
    const username = process.env.MQTT_USERNAME || '';
    const password = process.env.MQTT_PASSWORD || '';

    if (username) {
        const encodedUser = encodeURIComponent(username);
        const encodedPass = encodeURIComponent(password);
        return `mqtt://${encodedUser}:${encodedPass}@${host}:${port}`;
    }

    return `mqtt://${host}:${port}`;
}

function waitForEvent(client, event, timeoutMs = 10000) {
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => {
            client.removeListener(event, onEvent);
            client.removeListener('error', onError);
            reject(new Error(`Timeout menunggu event "${event}" (${timeoutMs}ms)`));
        }, timeoutMs);

        function onEvent(...args) {
            clearTimeout(timer);
            client.removeListener('error', onError);
            resolve(args);
        }

        function onError(err) {
            clearTimeout(timer);
            client.removeListener(event, onEvent);
            reject(err);
        }

        client.once(event, onEvent);
        client.once('error', onError);
    });
}

async function main() {
    const testUserId = process.argv[2] || 'verify_test_user';
    const brokerUrl = resolveBrokerUrl();
    const maskedUrl = brokerUrl.replace(/:([^:@/]+)@/, ':***@');

    console.log('[verify-mqtt] Broker:', maskedUrl);
    console.log('[verify-mqtt] Test userId:', testUserId);
    console.log('[verify-mqtt] Subscribe pattern:', buildWaSubscribePattern(testUserId));

    const subClientId = `wa_verify_sub_${Date.now()}`;
    const pubClientId = process.env.WA_MQTT_CLIENT_ID || `wa_verify_pub_${Date.now()}`;

    const subClient = mqtt.connect(brokerUrl, {
        clientId: subClientId,
        reconnectPeriod: 0,
        connectTimeout: 15000,
    });

    try {
        await waitForEvent(subClient, 'connect');
        console.log('[verify-mqtt] Subscriber connected ✓');

        const subscribePattern = buildWaSubscribePattern(testUserId);
        await new Promise((resolve, reject) => {
            subClient.subscribe(subscribePattern, { qos: 1 }, (err) => {
                if (err) reject(new Error(`Subscribe gagal: ${err.message}`));
                else resolve();
            });
        });
        console.log('[verify-mqtt] Subscribe ACL OK ✓');

        const pubClient = mqtt.connect(brokerUrl, {
            clientId: pubClientId,
            reconnectPeriod: 0,
            connectTimeout: 15000,
        });

        await waitForEvent(pubClient, 'connect');
        console.log('[verify-mqtt] Publisher connected ✓');

        const statusTopic = buildWaTopic(testUserId, TOPIC_SUFFIX.STATUS);
        const statusPayload = wrapPayload(WA_EVENTS.STATUS, {
            status: 'disconnected',
            phone: null,
            verify: true,
        });

        const messagePromise = new Promise((resolve, reject) => {
            const timer = setTimeout(() => {
                reject(new Error('Tidak menerima pesan status dalam 10 detik'));
            }, 10000);

            subClient.once('message', (topic, buf) => {
                clearTimeout(timer);
                try {
                    resolve({ topic, payload: JSON.parse(buf.toString()) });
                } catch (err) {
                    reject(new Error(`Payload bukan JSON valid: ${err.message}`));
                }
            });
        });

        await new Promise((resolve, reject) => {
            pubClient.publish(
                statusTopic,
                JSON.stringify(statusPayload),
                { qos: 0, retain: true },
                (err) => {
                    if (err) reject(new Error(`Publish gagal: ${err.message}`));
                    else resolve();
                },
            );
        });
        console.log('[verify-mqtt] Publish retained status OK ✓');

        const received = await messagePromise;
        console.log('[verify-mqtt] Received:', received.topic);
        console.log('[verify-mqtt] Payload event:', received.payload.event);

        if (received.payload.event !== WA_EVENTS.STATUS) {
            throw new Error(`Event tidak sesuai: ${received.payload.event}`);
        }

        // Bersihkan retained test message
        await new Promise((resolve) => {
            pubClient.publish(statusTopic, '', { retain: true }, () => resolve());
        });

        pubClient.end(true);
        subClient.end(true);

        console.log('\n[verify-mqtt] ✅ Semua cek Fase 0 lulus.');
        console.log('[verify-mqtt] Broker siap untuk migrasi WA Socket.IO → MQTT.');
        process.exit(0);
    } catch (error) {
        console.error('\n[verify-mqtt] ❌ Gagal:', error.message);
        console.error('[verify-mqtt] Pastikan MQTT_HOST/PORT/USERNAME/PASSWORD sama dengan Lumen.');
        console.error('[verify-mqtt] ACL Mosquitto harus allow publish/subscribe /intilab/wa/#');
        subClient.end(true);
        process.exit(1);
    }
}

main();
