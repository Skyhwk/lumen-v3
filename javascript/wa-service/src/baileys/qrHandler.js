const QRCode = require('qrcode');

async function toDataUrl(qrString) {
    return QRCode.toDataURL(qrString, {
        margin: 1,
        width: 264,
        errorCorrectionLevel: 'M',
    });
}

function emitQr(io, userId, qr) {
    if (!io) return;
    io.to(`user:${userId}`).emit('wa:qr', { qr });
}

function emitStatus(io, userId, status, extra = {}) {
    if (!io) return;
    io.to(`user:${userId}`).emit('wa:status', { status, ...extra });
}

function emitConnected(io, userId, phone, extra = {}) {
    if (!io) return;
    io.to(`user:${userId}`).emit('wa:connected', { phone, ...extra });
}

function emitDisconnected(io, userId, reason) {
    if (!io) return;
    io.to(`user:${userId}`).emit('wa:disconnected', { reason });
}

function emitChatsSync(io, userId, payload) {
    if (!io) return;

    if (Array.isArray(payload)) {
        io.to(`user:${userId}`).emit('wa:chats:sync', { chats: payload, statusChats: [] });
        return;
    }

    io.to(`user:${userId}`).emit('wa:chats:sync', {
        chats: payload?.chats || [],
        statusChats: payload?.statusChats || [],
    });
}

function emitChatUpdate(io, userId, chat) {
    if (!io) return;
    io.to(`user:${userId}`).emit('wa:chats:update', { chat });
}

function emitChatDeleted(io, userId, payload) {
    if (!io) return;
    io.to(`user:${userId}`).emit('wa:chat:deleted', payload || {});
}

function emitPresenceUpdate(io, userId, payload) {
    if (!io) return;
    io.to(`user:${userId}`).emit('wa:presence:update', payload);
}

function emitMessageNew(io, userId, payload) {
    if (!io) return;
    io.to(`user:${userId}`).emit('wa:message:new', payload);
}

function emitMessageUpdate(io, userId, payload) {
    if (!io) return;
    io.to(`user:${userId}`).emit('wa:message:update', payload);
}

function emitMessageMedia(io, userId, payload) {
    if (!io) return;
    io.to(`user:${userId}`).emit('wa:message:media', payload);
}

function emitMessageDeleted(io, userId, payload) {
    if (!io) return;
    io.to(`user:${userId}`).emit('wa:message:deleted', payload);
}

function emitMessageEdited(io, userId, payload) {
    if (!io) return;
    io.to(`user:${userId}`).emit('wa:message:edited', payload);
}

function emitMessagesReload(io, userId, payload) {
    if (!io) return;
    io.to(`user:${userId}`).emit('wa:messages:reload', payload);
}

function emitContactsSync(io, userId, contacts = []) {
    if (!io) return;
    io.to(`user:${userId}`).emit('wa:contacts:sync', { contacts });
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
