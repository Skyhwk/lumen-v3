const QRCode = require('qrcode');
const { publishWa, clearRetainedWa } = require('../mqtt/waMqtt');
const { TOPIC_SUFFIX, WA_EVENTS } = require('../mqtt/topicSchema');

async function toDataUrl(qrString) {
    return QRCode.toDataURL(qrString, {
        margin: 1,
        width: 264,
        errorCorrectionLevel: 'M',
    });
}

function emitQr(userId, qr) {
    publishWa(userId, TOPIC_SUFFIX.QR, WA_EVENTS.QR, { qr }, { retain: true });
}

function emitStatus(userId, status, extra = {}, options = {}) {
    if (status === 'disconnected' || status === 'connected' || status === 'connecting') {
        clearRetainedWa(userId, TOPIC_SUFFIX.QR);
    }
    const retain = options.retain !== undefined ? options.retain : true;
    publishWa(userId, TOPIC_SUFFIX.STATUS, WA_EVENTS.STATUS, { status, ...extra }, { retain });
}

function emitConnected(userId, phone, extra = {}) {
    clearRetainedWa(userId, TOPIC_SUFFIX.QR);
    publishWa(userId, TOPIC_SUFFIX.CONNECTED, WA_EVENTS.CONNECTED, { phone, ...extra });
}

function emitDisconnected(userId, reason) {
    publishWa(userId, TOPIC_SUFFIX.DISCONNECTED, WA_EVENTS.DISCONNECTED, { reason });
}

function emitChatsSync(userId, payload) {
    if (Array.isArray(payload)) {
        publishWa(userId, TOPIC_SUFFIX.CHATS_SYNC, WA_EVENTS.CHATS_SYNC, {
            chats: payload,
            statusChats: [],
        });
        return;
    }

    publishWa(userId, TOPIC_SUFFIX.CHATS_SYNC, WA_EVENTS.CHATS_SYNC, {
        chats: payload?.chats || [],
        statusChats: payload?.statusChats || [],
    });
}

function emitChatUpdate(userId, chat) {
    publishWa(userId, TOPIC_SUFFIX.CHATS_UPDATE, WA_EVENTS.CHATS_UPDATE, { chat });
}

function emitChatDeleted(userId, payload) {
    publishWa(userId, TOPIC_SUFFIX.CHAT_DELETED, WA_EVENTS.CHAT_DELETED, payload || {});
}

function emitPresenceUpdate(userId, payload) {
    publishWa(userId, TOPIC_SUFFIX.PRESENCE, WA_EVENTS.PRESENCE_UPDATE, payload);
}

function emitMessageNew(userId, payload) {
    publishWa(userId, TOPIC_SUFFIX.MESSAGE_NEW, WA_EVENTS.MESSAGE_NEW, payload);
}

function emitMessageUpdate(userId, payload) {
    publishWa(userId, TOPIC_SUFFIX.MESSAGE_UPDATE, WA_EVENTS.MESSAGE_UPDATE, payload);
}

function emitMessageMedia(userId, payload) {
    publishWa(userId, TOPIC_SUFFIX.MESSAGE_MEDIA, WA_EVENTS.MESSAGE_MEDIA, payload);
}

function emitMessageDeleted(userId, payload) {
    publishWa(userId, TOPIC_SUFFIX.MESSAGE_DELETED, WA_EVENTS.MESSAGE_DELETED, payload);
}

function emitMessageEdited(userId, payload) {
    publishWa(userId, TOPIC_SUFFIX.MESSAGE_EDITED, WA_EVENTS.MESSAGE_EDITED, payload);
}

function emitMessagesReload(userId, payload) {
    publishWa(userId, TOPIC_SUFFIX.MESSAGES_RELOAD, WA_EVENTS.MESSAGES_RELOAD, payload);
}

function emitContactsSync(userId, contacts = []) {
    publishWa(userId, TOPIC_SUFFIX.CONTACTS_SYNC, WA_EVENTS.CONTACTS_SYNC, { contacts });
}

module.exports = {
    toDataUrl,
    emitQr,
    emitStatus,
    emitConnected,
    emitDisconnected,
    emitChatsSync,
    emitChatUpdate,
    emitChatDeleted,
    emitPresenceUpdate,
    emitMessageNew,
    emitMessageUpdate,
    emitMessageMedia,
    emitMessageDeleted,
    emitMessageEdited,
    emitMessagesReload,
    emitContactsSync,
};
