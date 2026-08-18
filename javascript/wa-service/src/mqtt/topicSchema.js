/**
 * Kontrak topic MQTT WhatsApp — dipakai wa-service (publish) dan frontend (subscribe).
 * Prefix: /intilab/wa/{userId}/{suffix}
 */

const TOPIC_PREFIX = (process.env.WA_MQTT_TOPIC_PREFIX || '/intilab/wa').replace(/\/$/, '');

/** Map suffix topic → event Socket.IO legacy (untuk routing di frontend). */
const WA_EVENTS = Object.freeze({
    STATUS: 'wa:status',
    QR: 'wa:qr',
    CONNECTED: 'wa:connected',
    DISCONNECTED: 'wa:disconnected',
    CHATS_SYNC: 'wa:chats:sync',
    CHATS_UPDATE: 'wa:chats:update',
    CHAT_DELETED: 'wa:chat:deleted',
    PRESENCE_UPDATE: 'wa:presence:update',
    CONTACTS_SYNC: 'wa:contacts:sync',
    MESSAGE_NEW: 'wa:message:new',
    MESSAGE_UPDATE: 'wa:message:update',
    MESSAGE_MEDIA: 'wa:message:media',
    MESSAGE_DELETED: 'wa:message:deleted',
    MESSAGE_EDITED: 'wa:message:edited',
    MESSAGES_RELOAD: 'wa:messages:reload',
});

/** Suffix topic MQTT (tanpa prefix user). */
const TOPIC_SUFFIX = Object.freeze({
    STATUS: 'status',
    QR: 'qr',
    CONNECTED: 'connected',
    DISCONNECTED: 'disconnected',
    CHATS_SYNC: 'chats/sync',
    CHATS_UPDATE: 'chats/update',
    CHAT_DELETED: 'chats/deleted',
    PRESENCE: 'presence',
    CONTACTS_SYNC: 'contacts/sync',
    MESSAGE_NEW: 'message/new',
    MESSAGE_UPDATE: 'message/update',
    MESSAGE_MEDIA: 'message/media',
    MESSAGE_DELETED: 'message/deleted',
    MESSAGE_EDITED: 'message/edited',
    MESSAGES_RELOAD: 'messages/reload',
});

const SUFFIX_TO_EVENT = Object.freeze({
    [TOPIC_SUFFIX.STATUS]: WA_EVENTS.STATUS,
    [TOPIC_SUFFIX.QR]: WA_EVENTS.QR,
    [TOPIC_SUFFIX.CONNECTED]: WA_EVENTS.CONNECTED,
    [TOPIC_SUFFIX.DISCONNECTED]: WA_EVENTS.DISCONNECTED,
    [TOPIC_SUFFIX.CHATS_SYNC]: WA_EVENTS.CHATS_SYNC,
    [TOPIC_SUFFIX.CHATS_UPDATE]: WA_EVENTS.CHATS_UPDATE,
    [TOPIC_SUFFIX.CHAT_DELETED]: WA_EVENTS.CHAT_DELETED,
    [TOPIC_SUFFIX.PRESENCE]: WA_EVENTS.PRESENCE_UPDATE,
    [TOPIC_SUFFIX.CONTACTS_SYNC]: WA_EVENTS.CONTACTS_SYNC,
    [TOPIC_SUFFIX.MESSAGE_NEW]: WA_EVENTS.MESSAGE_NEW,
    [TOPIC_SUFFIX.MESSAGE_UPDATE]: WA_EVENTS.MESSAGE_UPDATE,
    [TOPIC_SUFFIX.MESSAGE_MEDIA]: WA_EVENTS.MESSAGE_MEDIA,
    [TOPIC_SUFFIX.MESSAGE_DELETED]: WA_EVENTS.MESSAGE_DELETED,
    [TOPIC_SUFFIX.MESSAGE_EDITED]: WA_EVENTS.MESSAGE_EDITED,
    [TOPIC_SUFFIX.MESSAGES_RELOAD]: WA_EVENTS.MESSAGES_RELOAD,
});

/** Topic yang pakai retained message — state langsung tersedia saat client reconnect. */
const RETAINED_SUFFIXES = new Set([
    TOPIC_SUFFIX.STATUS,
    TOPIC_SUFFIX.QR,
]);

function normalizeUserId(userId) {
    return String(userId).trim();
}

function buildWaTopic(userId, suffix) {
    const uid = normalizeUserId(userId);
    const cleanSuffix = String(suffix).replace(/^\/+/, '');
    return `${TOPIC_PREFIX}/${uid}/${cleanSuffix}`;
}

function buildWaSubscribePattern(userId) {
    return `${TOPIC_PREFIX}/${normalizeUserId(userId)}/#`;
}

function parseWaTopic(topic) {
    const prefix = `${TOPIC_PREFIX}/`;
    if (!topic.startsWith(prefix)) {
        return null;
    }

    const rest = topic.slice(prefix.length);
    const slashIdx = rest.indexOf('/');
    if (slashIdx <= 0) {
        return null;
    }

    const userId = rest.slice(0, slashIdx);
    const suffix = rest.slice(slashIdx + 1);
    const event = SUFFIX_TO_EVENT[suffix] || null;

    return { userId, suffix, event };
}

function isRetainedTopic(suffix) {
    return RETAINED_SUFFIXES.has(suffix);
}

function wrapPayload(event, data = {}) {
    return {
        event,
        ts: Date.now(),
        ...data,
    };
}

module.exports = {
    TOPIC_PREFIX,
    WA_EVENTS,
    TOPIC_SUFFIX,
    SUFFIX_TO_EVENT,
    RETAINED_SUFFIXES,
    buildWaTopic,
    buildWaSubscribePattern,
    parseWaTopic,
    isRetainedTopic,
    wrapPayload,
};
