const messageService = require('./messageService');
const contactService = require('./contactService');
const chatNameService = require('./chatNameService');
const avatarService = require('./avatarService');
const { emitStatus } = require('../baileys/qrHandler');
const { isMessageHistorySyncEnabled } = require('../utils/syncConfig');

async function finalizeConnectSync(userId, io) {
    const session = require('../baileys/sessionManager').getSession(userId);
    if (session?.sock) {
        await chatNameService.enrichGroupNames(userId, session.sock);
        await chatNameService.syncContactNamesToChats(userId);
    }

    const syncedContacts = await contactService.syncContactsFromDevice(
        userId,
        session?.sock,
        session?.contactStore,
    );
    if (syncedContacts) {
        await chatNameService.syncContactNamesToChats(userId);
    }

    const { chats, statusChats } = await messageService.syncAndEmitChats(userId, io);
    const { emitContactsSync } = require('../baileys/qrHandler');
    const contacts = await contactService.getContacts(userId);
    emitContactsSync(io, userId, contacts);
    emitStatus(io, userId, 'connected', { syncing: false, syncProgress: null });
    console.log(`[sync] user ${userId} connect ready — ${chats.length} chats, ${statusChats.length} status, ${contacts.length} contacts`);

    if (session?.sock) {
        avatarService.syncAvatarsInBackground(userId, session.sock, [], io, { limit: 50 })
            .catch((error) => {
                console.warn(`[sync] avatar sync failed for user ${userId}:`, error.message);
            });
    }
}

async function processHistorySync(userId, { chats = [], contacts = [], messages = [], syncType }, io, meta = {}) {
    const { isLatest = false, progress = null } = meta;

    if (!isMessageHistorySyncEnabled()) {
        if (isLatest) {
            await finalizeConnectSync(userId, io);
        }
        return;
    }

    const ON_DEMAND_SYNC = 6;
    const downloadMedia = syncType === ON_DEMAND_SYNC;

    if (progress !== null) {
        emitStatus(io, userId, 'connected', {
            syncProgress: progress,
            syncing: !isLatest,
        });
    }

    if (contacts.length) {
        await contactService.upsertContacts(userId, contacts, { allowPushName: false, fromPhonebook: false });
        await chatNameService.syncContactNamesToChats(userId);
    }

    if (chats.length) {
        await messageService.processChats(userId, chats);
        await messageService.syncAndEmitChats(userId, io);
    }

    if (messages.length) {
        const applyGroupRetention = syncType !== ON_DEMAND_SYNC;

        if (applyGroupRetention) {
            await messageService.seedGroupAnchorsFromHistory(userId, messages);
        }

        await messageService.processMessages(userId, messages, io, {
            emit: downloadMedia,
            downloadMedia,
            applyGroupRetention,
        });

        const { emitMessagesReload } = require('../baileys/qrHandler');
        const jids = [...new Set(messages.map((m) => m.key?.remoteJid).filter(Boolean))];
        for (const jid of jids) {
            emitMessagesReload(io, userId, { jid });
        }
    }

    if (isLatest) {
        await finalizeConnectSync(userId, io);
    }
}

module.exports = { processHistorySync };
