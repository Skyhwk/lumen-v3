const messageService = require('./messageService');
const contactService = require('./contactService');
const chatNameService = require('./chatNameService');
const avatarService = require('./avatarService');
const { emitStatus } = require('../baileys/qrHandler');
const { isMessageHistorySyncEnabled } = require('../utils/syncConfig');

const PHASE_LABELS = {
    init: 'Memulai sinkronisasi...',
    history: 'Menyinkronkan riwayat chat & pesan...',
    chats: 'Memuat daftar chat...',
    contacts: 'Menyinkronkan kontak...',
    names: 'Memperbarui nama chat...',
    finalize: 'Menyelesaikan sinkronisasi...',
};

function buildSyncProgress(value, { phase = 'history', label = null, isLatest = false } = {}) {
    let progress = null;

    if (isLatest) {
        progress = 0.92;
    } else if (value != null) {
        const raw = typeof value === 'object'
            ? Number(value.progress ?? value)
            : Number(value);
        if (!Number.isNaN(raw)) {
            const normalized = raw <= 1 ? raw : raw / 100;
            progress = phase === 'history'
                ? 0.08 + normalized * 0.78
                : normalized;
        }
    }

    return {
        progress: progress ?? 0,
        phase,
        label: label || PHASE_LABELS[phase] || PHASE_LABELS.history,
    };
}

function emitSyncProgress(io, userId, value, opts = {}) {
    const syncProgress = buildSyncProgress(value, opts);
    emitStatus(io, userId, 'connected', {
        syncing: true,
        syncProgress,
    });
    return syncProgress;
}

async function runInitialConnectSync(userId, io) {
    const contactSyncCoordinator = require('./contactSyncCoordinator');
    const session = require('../baileys/sessionManager').getSession(userId);

    try {
        emitSyncProgress(io, userId, 0.15, { phase: 'chats' });
        await messageService.syncAndEmitChats(userId, io);

        emitSyncProgress(io, userId, 0.45, { phase: 'contacts' });
        await contactSyncCoordinator.syncDeviceContactsIfDue(
            userId,
            session?.sock,
            session?.contactStore,
            io,
        );

        emitSyncProgress(io, userId, 0.75, { phase: 'names' });
        await chatNameService.maybeEnrichChatNames(userId, io);

        emitSyncProgress(io, userId, 0.95, { phase: 'finalize' });
        contactSyncCoordinator.scheduleContactsEmit(userId, io);

        emitStatus(io, userId, 'connected', { syncing: false, syncProgress: null });
        console.log(`[sync] user ${userId} initial connect sync complete`);
    } catch (error) {
        console.error('[sync] initial connect sync failed:', error.message);
        emitStatus(io, userId, 'connected', { syncing: false, syncProgress: null });
    }
}

async function finalizeConnectSync(userId, io) {
    const contactSyncCoordinator = require('./contactSyncCoordinator');
    const session = require('../baileys/sessionManager').getSession(userId);

    emitSyncProgress(io, userId, 0.86, { phase: 'names', label: 'Memperbarui nama chat...' });
    await chatNameService.maybeEnrichChatNames(userId, io);

    emitSyncProgress(io, userId, 0.92, { phase: 'contacts' });
    await contactSyncCoordinator.syncDeviceContactsIfDue(
        userId,
        session?.sock,
        session?.contactStore,
        io,
    );

    emitSyncProgress(io, userId, 0.96, { phase: 'chats' });
    const { chats, statusChats } = await messageService.syncAndEmitChats(userId, io);
    contactSyncCoordinator.scheduleContactsEmit(userId, io);

    emitStatus(io, userId, 'connected', { syncing: false, syncProgress: null });
    console.log(`[sync] user ${userId} connect ready — ${chats.length} chats, ${statusChats.length} status`);

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

    const applyGroupRetention = syncType !== 6;

    if (progress !== null || isLatest) {
        emitSyncProgress(io, userId, progress, { phase: 'history', isLatest });
    }

    if (contacts.length) {
        await contactService.upsertContacts(userId, contacts, { allowPushName: false, fromPhonebook: false });
        const contactSyncCoordinator = require('./contactSyncCoordinator');
        contactSyncCoordinator.scheduleContactNamesSync(userId, io);
    }

    if (chats.length) {
        await messageService.processChats(userId, chats);
        await messageService.syncAndEmitChats(userId, io);
    }

    if (messages.length) {
        if (applyGroupRetention) {
            await messageService.seedGroupAnchorsFromHistory(userId, messages);
        }

        await messageService.processMessages(userId, messages, io, {
            emit: false,
            downloadMedia: false,
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

module.exports = {
    buildSyncProgress,
    emitSyncProgress,
    runInitialConnectSync,
    finalizeConnectSync,
    processHistorySync,
};
