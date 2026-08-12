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

const runtimeSessions = new Map();
const connectLocks = new Map();

let ioInstance = null;

function setIo(io) {
    ioInstance = io;
}

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

async function closeSocket(sock) {
    if (!sock) return;
    try {
        sock.ev.removeAllListeners('connection.update');
        sock.ev.removeAllListeners('creds.update');
    } catch {
        // ignore
    }
    try {
        sock.end(undefined);
    } catch {
        // ignore
    }
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

    const previous = getSession(key);
    if (previous?.sock) {
        await closeSocket(previous.sock);
    }

    patchSession(key, { status: 'connecting', qr: null });
    emitStatus(ioInstance, key, 'connecting');
    await sessionService.upsertSession(key, { status: 'connecting' });

    const { state, saveCreds } = await useMultiFileAuthState(sessionDir);
    const { version } = await fetchLatestBaileysVersion();
    const { deviceName, devicePlatform, enableMessageHistorySync } = loadEnv();
    const browser = resolveWaBrowser(deviceName, devicePlatform);

    const contactStore = createContactStore();

    const sock = makeWASocket({
        version,
        auth: state,
        logger: pino({ level: 'silent' }),
        printQRInTerminal: false,
        browser,
        syncFullHistory: enableMessageHistorySync,
        markOnlineOnConnect: true,
        generateHighQualityLinkPreview: false,
    });

    contactStore.bind(sock.ev);
    sock.contactStore = contactStore;
    sock.store = { contacts: contactStore.contacts };

    patchSession(key, { sock, saveCreds, status: 'connecting', contactStore });

    registerBaileysEventHandlers(sock, key, ioInstance, {
        saveCreds,
        onReconnect: () => ensureSession(key),
        patchSession,
        getSession: () => getSession(key),
    });

    return getSession(key);
}

async function logoutSession(userId) {
    const key = String(userId);
    const session = getSession(key);

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

    emitStatus(ioInstance, key, 'disconnected', { phone: null });
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
    setIo,
    ensureSession,
    requireConnectedSession,
    logoutSession,
    getSession,
    getSessionStatus,
    hasStoredSession,
    patchSession,
};
