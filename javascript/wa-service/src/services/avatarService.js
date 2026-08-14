const fs = require('fs');
const path = require('path');
const http = require('http');
const https = require('https');
const { loadEnv } = require('../config/env');
const { getPool } = require('../db/connection');

const inFlight = new Map();

function safeSegment(value) {
    return String(value || 'unknown').replace(/[^a-zA-Z0-9._-]/g, '_');
}

function safeJidFilename(jid) {
    return String(jid || 'unknown').replace(/[^a-zA-Z0-9._@-]/g, '_');
}

function getAvatarDir(userId) {
    const { mediaDir } = loadEnv();
    return path.resolve(mediaDir, safeSegment(userId), 'avatars');
}

function getAvatarFilePath(userId, jid) {
    return path.join(getAvatarDir(userId), `${safeJidFilename(jid)}.jpg`);
}

function getAvatarRelativePath(userId, jid) {
    return path.posix.join(safeSegment(userId), 'avatars', `${safeJidFilename(jid)}.jpg`);
}

function getAvatarPublicUrl(userId, jid, version = null) {
    const rel = getAvatarRelativePath(userId, jid);
    const base = `/media/${rel.replace(/\\/g, '/')}`;
    return version ? `${base}?v=${version}` : base;
}

function getAvatarTtlMs() {
    const { avatarTtlHours } = loadEnv();
    return Math.max(1, avatarTtlHours) * 60 * 60 * 1000;
}

function isNotFoundError(error) {
    const msg = String(error?.message || '').toLowerCase();
    const status = error?.output?.statusCode
        || error?.data?.status
        || error?.status
        || error?.response?.status;

    return status === 404
        || msg.includes('404')
        || msg.includes('not-found')
        || msg.includes('item-not-found')
        || msg.includes('not authorized')
        || msg.includes('401');
}

function downloadUrl(url, redirects = 0) {
    return new Promise((resolve, reject) => {
        if (!url || redirects > 5) {
            reject(new Error('Invalid download URL'));
            return;
        }

        const client = url.startsWith('https') ? https : http;
        client.get(url, (res) => {
            if (res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
                downloadUrl(res.headers.location, redirects + 1).then(resolve).catch(reject);
                return;
            }

            if (res.statusCode !== 200) {
                reject(new Error(`HTTP ${res.statusCode}`));
                return;
            }

            const chunks = [];
            res.on('data', (chunk) => chunks.push(chunk));
            res.on('end', () => resolve(Buffer.concat(chunks)));
            res.on('error', reject);
        }).on('error', reject);
    });
}

async function fetchProfilePictureBuffer(sock, jid) {
    if (!sock?.profilePictureUrl) return null;

    try {
        const url = await sock.profilePictureUrl(jid, 'image');
        if (!url) return null;
        const buffer = await downloadUrl(url);
        return buffer?.length ? buffer : null;
    } catch (error) {
        if (isNotFoundError(error)) return null;
        throw error;
    }
}

function isAvatarFresh(userId, jid) {
    const filePath = getAvatarFilePath(userId, jid);
    if (!fs.existsSync(filePath)) return false;
    const stat = fs.statSync(filePath);
    return Date.now() - stat.mtimeMs < getAvatarTtlMs();
}

async function persistAvatar(userId, jid, buffer) {
    const dir = getAvatarDir(userId);
    fs.mkdirSync(dir, { recursive: true });
    const filePath = path.join(dir, `${safeJidFilename(jid)}.jpg`);
    fs.writeFileSync(filePath, buffer);
    return getAvatarPublicUrl(userId, jid, Date.now());
}

async function updateAvatarInDb(userId, jid, avatarUrl) {
    await getPool().execute(
        `UPDATE wa_contacts
         SET avatar_url = ?, updated_at = NOW()
         WHERE user_id_erp = ? AND jid = ?`,
        [avatarUrl, userId, jid],
    );

    await getPool().execute(
        `UPDATE wa_chats
         SET avatar_url = ?, updated_at = NOW()
         WHERE user_id_erp = ? AND jid = ?`,
        [avatarUrl, userId, jid],
    );
}

async function syncAvatarForJid(userId, sock, jid, { force = false } = {}) {
    if (!userId || !sock || !jid) return null;

    const key = `${userId}:${jid}`;
    if (inFlight.has(key)) return inFlight.get(key);

    const task = (async () => {
        try {
            const filePath = getAvatarFilePath(userId, jid);
            if (!force && isAvatarFresh(userId, jid)) {
                const version = fs.statSync(filePath).mtimeMs;
                return getAvatarPublicUrl(userId, jid, version);
            }

            const buffer = await fetchProfilePictureBuffer(sock, jid);
            if (!buffer) {
                if (fs.existsSync(filePath)) {
                    fs.unlinkSync(filePath);
                }
                await updateAvatarInDb(userId, jid, null);
                return null;
            }

            const avatarUrl = await persistAvatar(userId, jid, buffer);
            await updateAvatarInDb(userId, jid, avatarUrl);
            return avatarUrl;
        } catch (error) {
            console.warn(`[avatarService] sync failed for ${jid}:`, error.message);
            return null;
        } finally {
            inFlight.delete(key);
        }
    })();

    inFlight.set(key, task);
    return task;
}

async function syncAvatarsForJids(userId, sock, jids = [], { concurrency = 3, force = false } = {}) {
    const unique = [...new Set(jids.filter(Boolean))];
    const synced = new Map();

    for (let i = 0; i < unique.length; i += concurrency) {
        const batch = unique.slice(i, i + concurrency);
        const results = await Promise.all(
            batch.map(async (jid) => {
                const url = await syncAvatarForJid(userId, sock, jid, { force });
                return { jid, url };
            }),
        );

        for (const { jid, url } of results) {
            if (url) synced.set(jid, url);
        }
    }

    return synced;
}

async function syncRecentChatAvatars(userId, sock, { limit = 40 } = {}) {
    const safeLimit = Math.min(Math.max(parseInt(limit, 10) || 40, 1), 100);

    const [chatRows] = await getPool().execute(
        `SELECT jid FROM wa_chats
         WHERE user_id_erp = ?
         ORDER BY last_message_at DESC, updated_at DESC
         LIMIT ?`,
        [userId, safeLimit],
    );

    const [contactRows] = await getPool().execute(
        `SELECT jid FROM wa_contacts
         WHERE user_id_erp = ?
           AND jid NOT LIKE '%@broadcast'
           AND (avatar_url IS NULL OR avatar_url = '')
         LIMIT ?`,
        [userId, safeLimit],
    );

    const jids = [
        ...chatRows.map((row) => row.jid),
        ...contactRows.map((row) => row.jid),
    ];

    return syncAvatarsForJids(userId, sock, jids, { concurrency: 3 });
}

async function syncAvatarsInBackground(userId, sock, jids = [], io = null, { limit = 40 } = {}) {
    if (!sock) return new Map();

    const synced = jids.length
        ? await syncAvatarsForJids(userId, sock, jids, { concurrency: 3 })
        : await syncRecentChatAvatars(userId, sock, { limit });

    if (synced.size && io) {
        const messageService = require('./messageService');
        await messageService.syncAndEmitChats(userId, io);

        const { emitContactsSync } = require('../baileys/qrHandler');
        const contactService = require('./contactService');
        const contacts = await contactService.getContacts(userId);
        emitContactsSync(io, userId, contacts);
    }

    return synced;
}

module.exports = {
    syncAvatarForJid,
    syncAvatarsForJids,
    syncRecentChatAvatars,
    syncAvatarsInBackground,
    getAvatarPublicUrl,
};
