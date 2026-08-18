const messageService = require('./messageService');
const contactService = require('./contactService');
const chatNameService = require('./chatNameService');
const avatarService = require('./avatarService');
const { emitStatus } = require('../baileys/qrHandler');
const { isMessageHistorySyncEnabled } = require('../utils/syncConfig');

const historySyncFallbackTimers = new Map();

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

function clearHistorySyncFallback(userId) {
    const key = String(userId);
    const timer = historySyncFallbackTimers.get(key);
    if (timer) {
        clearTimeout(timer);
        historySyncFallbackTimers.delete(key);
    }
}

function scheduleHistorySyncFallback(userId, fn, delayMs = 45000) {
    const key = String(userId);
    clearHistorySyncFallback(key);
    const timer = setTimeout(() => {
        historySyncFallbackTimers.delete(key);
        fn().catch((error) => {
            console.error(`[sync] history fallback failed for user ${key}:`, error.message);
        });
    }, delayMs);
    historySyncFallbackTimers.set(key, timer);
}

function usesNativeHistorySync(session) {
    return isMessageHistorySyncEnabled() && Boolean(session?.nativeHistorySync);
}

function markInitialSyncDone(userId) {
    const { patchSession } = require('../baileys/sessionManager');
    patchSession(userId, { initialSyncDone: true });
}

function kickoffConnectSync(userId, session) {
    if (session?.initialSyncDone) {
        messageService.syncAndEmitChats(userId).catch((error) => {
            console.warn(`[sync] silent chat refresh failed for user ${userId}:`, error.message);
        });
        emitStatus(userId, 'connected', {
            phone: session.phone || null,
            syncing: false,
            syncProgress: null,
        });
        return;
    }

    if (usesNativeHistorySync(session)) {
        scheduleHistorySyncFallback(userId, () => {
            const current = require('../baileys/sessionManager').getSession(userId);
            if (current?.status !== 'connected' || current?.initialSyncDone) return;
            console.warn(`[sync] user ${userId} native history stalled — fallback manual sync`);
            return runInitialConnectSync(userId);
        });
        return;
    }

    setTimeout(() => {
        const current = require('../baileys/sessionManager').getSession(userId);
        if (current?.initialSyncDone) return;
        runInitialConnectSync(userId).catch((error) => {
            console.error(`[sync] initial connect sync failed for user ${userId}:`, error.message);
        });
    }, 500);
}

function emitSyncProgress(userId, value, opts = {}) {
    const syncProgress = buildSyncProgress(value, opts);
    emitStatus(userId, 'connected', {
        syncing: true,
        syncProgress,
    }, { retain: false });
    return syncProgress;
}

async function runInitialConnectSync(userId) {
    clearHistorySyncFallback(userId);
    const contactSyncCoordinator = require('./contactSyncCoordinator');
    const session = require('../baileys/sessionManager').getSession(userId);

    try {
        emitSyncProgress(userId, 0.15, { phase: 'chats' });
        await messageService.syncAndEmitChats(userId);

        emitSyncProgress(userId, 0.45, { phase: 'contacts' });
        await contactSyncCoordinator.syncDeviceContactsIfDue(
            userId,
            session?.sock,
            session?.contactStore,
        );

        emitSyncProgress(userId, 0.75, { phase: 'names' });
        await chatNameService.maybeEnrichChatNames(userId);

        emitSyncProgress(userId, 0.95, { phase: 'finalize' });
        contactSyncCoordinator.scheduleContactsEmit(userId);

        emitStatus(userId, 'connected', { syncing: false, syncProgress: null });
        clearHistorySyncFallback(userId);
        markInitialSyncDone(userId);
        console.log(`[sync] user ${userId} initial connect sync complete`);
    } catch (error) {
        console.error('[sync] initial connect sync failed:', error.message);
        emitStatus(userId, 'connected', { syncing: false, syncProgress: null });
        clearHistorySyncFallback(userId);
        markInitialSyncDone(userId);
    }
}

async function finalizeConnectSync(userId) {
    clearHistorySyncFallback(userId);
    const contactSyncCoordinator = require('./contactSyncCoordinator');
    const session = require('../baileys/sessionManager').getSession(userId);

    emitSyncProgress(userId, 0.86, { phase: 'names', label: 'Memperbarui nama chat...' });
    await chatNameService.maybeEnrichChatNames(userId);

    emitSyncProgress(userId, 0.92, { phase: 'contacts' });
    await contactSyncCoordinator.syncDeviceContactsIfDue(
        userId,
        session?.sock,
        session?.contactStore,
    );

    emitSyncProgress(userId, 0.96, { phase: 'chats' });
    const { chats, statusChats } = await messageService.syncAndEmitChats(userId);
    contactSyncCoordinator.scheduleContactsEmit(userId);

    emitStatus(userId, 'connected', { syncing: false, syncProgress: null });
    markInitialSyncDone(userId);
    console.log(`[sync] user ${userId} connect ready — ${chats.length} chats, ${statusChats.length} status`);

    if (session?.sock) {
        avatarService.syncAvatarsInBackground(userId, session.sock, [], { limit: 50 })
            .catch((error) => {
                console.warn(`[sync] avatar sync failed for user ${userId}:`, error.message);
            });
    }
}

async function processHistorySync(userId, { chats = [], contacts = [], messages = [], syncType }, meta = {}) {
    const { isLatest = false, progress = null } = meta;

    if (!isMessageHistorySyncEnabled()) {
        if (isLatest) {
            await finalizeConnectSync(userId);
        }
        return;
    }

    const applyGroupRetention = syncType !== 6;
    const hasBatch = chats.length > 0 || contacts.length > 0 || messages.length > 0;

    clearHistorySyncFallback(userId);

    if (progress !== null || isLatest || hasBatch) {
        const progressHint = progress ?? (isLatest ? 1 : Math.min(0.85, 0.2 + (messages.length > 0 ? 0.4 : 0) + (chats.length > 0 ? 0.25 : 0)));
        emitSyncProgress(userId, progressHint, {
            phase: 'history',
            isLatest,
            label: hasBatch
                ? `Memproses ${chats.length} chat, ${messages.length} pesan...`
                : undefined,
        });
    }

    if (contacts.length) {
        await contactService.upsertContacts(userId, contacts, { allowPushName: false, fromPhonebook: false });
        const contactSyncCoordinator = require('./contactSyncCoordinator');
        contactSyncCoordinator.scheduleContactNamesSync(userId);
    }

    if (chats.length) {
        await messageService.processChats(userId, chats);
        await messageService.syncAndEmitChats(userId);
    }

    if (messages.length) {
        if (applyGroupRetention) {
            await messageService.seedGroupAnchorsFromHistory(userId, messages);
        }

        await messageService.processMessages(userId, messages, {
            emit: false,
            downloadMedia: false,
            applyGroupRetention,
        });

        const { emitMessagesReload } = require('../baileys/qrHandler');
        const jids = [...new Set(messages.map((m) => m.key?.remoteJid).filter(Boolean))];
        for (const jid of jids) {
            emitMessagesReload(userId, { jid });
        }
    }

    if (isLatest) {
        await finalizeConnectSync(userId);
    }
}

module.exports = {
    buildSyncProgress,
    emitSyncProgress,
    kickoffConnectSync,
    runInitialConnectSync,
    finalizeConnectSync,
    processHistorySync,
    clearHistorySyncFallback,
};
