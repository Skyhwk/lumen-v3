const mqtt = require('mqtt');
const { loadEnv } = require('../config/env');
const {
    buildWaTopic,
    wrapPayload,
    isRetainedTopic,
} = require('./topicSchema');

let client = null;
let connectPromise = null;

const QOS1_SUFFIXES = new Set([
    'connected',
    'disconnected',
    'chats/sync',
    'contacts/sync',
    'message/new',
    'message/media',
]);

function resolveBrokerUrl(mqttConfig) {
    if (mqttConfig.brokerUrl) {
        return mqttConfig.brokerUrl;
    }

    const { host, port, username, password } = mqttConfig;
    if (username) {
        const encodedUser = encodeURIComponent(username);
        const encodedPass = encodeURIComponent(password);
        return `mqtt://${encodedUser}:${encodedPass}@${host}:${port}`;
    }

    return `mqtt://${host}:${port}`;
}

function resolveQos(suffix, options = {}) {
    if (options.qos !== undefined) {
        return options.qos;
    }
    return QOS1_SUFFIXES.has(suffix) ? 1 : 0;
}

function initWaMqtt() {
    if (client?.connected) {
        return Promise.resolve(client);
    }

    if (connectPromise) {
        return connectPromise;
    }

    connectPromise = new Promise((resolve, reject) => {
        const { mqtt: mqttConfig } = loadEnv();
        const url = resolveBrokerUrl(mqttConfig);
        const masked = url.replace(/:([^:@/]+)@/, ':***@');

        const nextClient = mqtt.connect(url, {
            clientId: mqttConfig.clientId,
            reconnectPeriod: mqttConfig.reconnectMs,
            connectTimeout: 15000,
            keepalive: 30,
            clean: true,
        });

        nextClient.once('connect', () => {
            client = nextClient;
            console.log(`[wa-mqtt] connected (${masked})`);
            resolve(client);
        });

        nextClient.once('error', (err) => {
            connectPromise = null;
            console.error('[wa-mqtt] connect error:', err.message);
            reject(err);
        });

        nextClient.on('reconnect', () => {
            console.log('[wa-mqtt] reconnecting...');
        });

        nextClient.on('close', () => {
            console.warn('[wa-mqtt] connection closed');
        });
    });

    return connectPromise;
}

function getWaMqtt() {
    return client;
}

function publishWa(userId, suffix, event, data = {}, options = {}) {
    if (!client?.connected) {
        console.warn(`[wa-mqtt] skip publish (offline): ${suffix} user=${userId}`);
        return false;
    }

    const topic = buildWaTopic(userId, suffix);
    const payload = JSON.stringify(wrapPayload(event, data));
    const qos = resolveQos(suffix, options);
    const retain = options.retain !== undefined ? options.retain : isRetainedTopic(suffix);

    client.publish(topic, payload, { qos, retain }, (err) => {
        if (err) {
            console.error(`[wa-mqtt] publish failed ${topic}:`, err.message);
        }
    });

    return true;
}

function clearRetainedWa(userId, suffix) {
    if (!client?.connected) {
        return false;
    }

    const topic = buildWaTopic(userId, suffix);
    client.publish(topic, '', { qos: 0, retain: true });
    return true;
}

async function closeWaMqtt() {
    connectPromise = null;
    if (!client) {
        return;
    }

    await new Promise((resolve) => {
        client.end(false, {}, () => resolve());
    });
    client = null;
}

module.exports = {
    initWaMqtt,
    getWaMqtt,
    publishWa,
    clearRetainedWa,
    closeWaMqtt,
};
