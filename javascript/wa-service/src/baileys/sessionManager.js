const fs = require('fs');
const path = require('path');
const pino = require('pino');
const {
    default: makeWASocket,
    useMultiFileAuthState,
    fetchLatestBaileysVersion,
} = require('@whiskeysockets/baileys');
const { loadEnv } = require('../config/env');
const { resolveWaBrowser } = require('../utils/browserConfig');
const { registerBaileysEventHandlers } = require('./eventHandlers');
const { createContactStore } = require('./contactStore');
const { emitStatus } = require('./qrHandler');
const sessionService = require('../services/sessionService');

const { closeSocket } = require('./socketUtils');

const runtimeSessions = new Map();
const connectLocks = new Map();
const reconnectTimers = new Map();

function getSessionDir(userId) {
    const { sessionsDir } = loadEnv();
    return path.resolve(sessionsDir, String(userId));
}

function hasStoredSession(userId) {
    return fs.existsSync(path.join(getSessionDir(userId), 'creds.json'));
}

function patchSession(userId, patch) {
    const key = String(userId);
    const current = runtimeSessions.get(key) || {
        status: 'disconnected',
        phone: null,
        qr: null,
        sock: null,
        saveCreds: null,
    };
    const next = { ...current, ...patch };
    runtimeSessions.set(key, next);
    return next;
}

function getSession(userId) {
    return runtimeSessions.get(String(userId)) || null;
}

function clearReconnectTimer(userId) {
    const key = String(userId);
    const timer = reconnectTimers.get(key);
    if (timer) {
        clearTimeout(timer);
        reconnectTimers.delete(key);
    }
}

function scheduleReconnect(userId, fn, delayMs = 3000) {
    const key = String(userId);
    clearReconnectTimer(key);
    const timer = setTimeout(() => {
        reconnectTimers.delete(key);
        fn().catch((err) => {
            console.error(`[baileys] reconnect failed for user ${key}:`, err.message);
        });
    }, delayMs);
    reconnectTimers.set(key, timer);
}

function isSessionRestarting(userId) {
    return connectLocks.has(String(userId));
}

function getSessionStatus(userId) {
    const session = getSession(userId);
    if (!session) {
        return {
            status: 'disconnected',
            phone: null,
            qr: null,
            hasStoredSession: hasStoredSession(userId),
        };
    }

    return {
        status: session.status || 'disconnected',
        phone: session.phone || null,
        qr: session.qr || null,
        hasStoredSession: hasStoredSession(userId),
    };
}

async function waitForSessionProgress(userId, { timeoutMs = 20000, intervalMs = 300 } = {}) {
    const deadline = Date.now() + timeoutMs;

    while (Date.now() < deadline) {
        const status = getSessionStatus(userId);

        if (status.status === 'connected' || status.status === 'qr' || status.qr) {
            return status;
        }

        if (status.status === 'disconnected' && status.hasStoredSession) {
            return status;
        }

        await new Promise((resolve) => { setTimeout(resolve, intervalMs); });
    }

    return getSessionStatus(userId);
}

async function ensureSession(userId) {
    const key = String(userId);
    const existing = getSession(key);

    if (existing?.sock && ['connected', 'connecting', 'qr'].includes(existing.status)) {
        return existing;
    }

    if (connectLocks.has(key)) {
        return connectLocks.get(key);
    }

    const promise = startSession(key).finally(() => connectLocks.delete(key));
    connectLocks.set(key, promise);
    return promise;
}

async function startSession(userId) {
    const key = String(userId);
    const sessionDir = getSessionDir(key);
    fs.mkdirSync(sessionDir, { recursive: true });

    clearReconnectTimer(key);

    const previous = getSession(key);
    if (previous?.sock) {
        patchSession(key, { sock: null });
        await closeSocket(previous.sock);
    }

    patchSession(key, { status: 'connecting', qr: null });
    emitStatus(key, 'connecting');
    await sessionService.upsertSession(key, { status: 'connecting' });

    const { state, saveCreds } = await useMultiFileAuthState(sessionDir);
    const { version } = await fetchLatestBaileysVersion();
    const { deviceName, devicePlatform, enableMessageHistorySync } = loadEnv();
    const browser = resolveWaBrowser(deviceName, devicePlatform);
    const sessionRecord = await sessionService.getSessionRecord(key);
    // History sync native Baileys hanya setelah pernah connect sukses — pairing (515) + syncFullHistory = 428
    const syncFullHistory = enableMessageHistorySync
        && Boolean(state.creds?.registered)
        && Boolean(sessionRecord?.last_connected_at);

    const contactStore = createContactStore();

    const sock = makeWASocket({
        version,
        auth: state,
        logger: pino({ level: 'silent' }),
        printQRInTerminal: false,
        browser,
        syncFullHistory,
        markOnlineOnConnect: true,
        generateHighQualityLinkPreview: false,
    });

    contactStore.bind(sock.ev);
    sock.contactStore = contactStore;
    sock.store = { contacts: contactStore.contacts };

    patchSession(key, { sock, saveCreds, status: 'connecting', contactStore, nativeHistorySync: syncFullHistory });

    registerBaileysEventHandlers(sock, key, {
        saveCreds,
        onReconnect: () => ensureSession(key),
        patchSession,
        getSession: () => getSession(key),
        scheduleReconnect: (fn, delayMs) => scheduleReconnect(key, fn, delayMs),
        isSessionRestarting: () => isSessionRestarting(key),
    });

    return getSession(key);
}

async function logoutSession(userId) {
    const key = String(userId);
    const session = getSession(key);

    clearReconnectTimer(key);
    connectLocks.delete(key);

    if (session?.sock) {
        try {
            await session.sock.logout();
        } catch {
            // ignore — session may already be closed
        }
        await closeSocket(session.sock);
    }

    runtimeSessions.delete(key);

    const sessionDir = getSessionDir(key);
    if (fs.existsSync(sessionDir)) {
        fs.rmSync(sessionDir, { recursive: true, force: true });
    }

    await sessionService.upsertSession(key, {
        status: 'disconnected',
        phone_number: null,
    });

    emitStatus(key, 'disconnected', { phone: null });
    console.log(`[baileys] user ${key} session cleared`);
}

async function requireConnectedSession(userId, { waitMs = 10000 } = {}) {
    const key = String(userId);
    let session = getSession(key);

    if (!session?.sock || session.status !== 'connected') {
        await ensureSession(key);
        const deadline = Date.now() + waitMs;
        while (Date.now() < deadline) {
            session = getSession(key);
            if (session?.status === 'connected' && session?.sock) {
                break;
            }
            await new Promise((resolve) => { setTimeout(resolve, 250); });
        }
    }

    session = getSession(key);
    if (!session?.sock) {
        throw new Error('WhatsApp belum terhubung');
    }
    if (session.status !== 'connected') {
        throw new Error('WhatsApp sedang menghubungkan, coba lagi sebentar');
    }

    return session;
}

module.exports = {
    ensureSession,
    waitForSessionProgress,
    requireConnectedSession,
    logoutSession,
    getSession,
    getSessionStatus,
    hasStoredSession,
    patchSession,
};
