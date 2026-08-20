const { isJidGroup } = require('@whiskeysockets/baileys');
const { getPool } = require('../db/connection');
const contactService = require('./contactService');
const { pickBetterName, pickBetterGroupName, isPhoneLikeName, isWeakContactName, isGoodContactName, formatChatFallbackName } = require('../utils/nameUtils');

function getWaSession(userId) {
    return require('../baileys/sessionManager').getSession(userId);
}

async function forceUpdateChatName(userId, jid, name) {
    if (!jid || !name?.trim()) return false;

    await getPool().execute(
        'UPDATE wa_chats SET name = ?, updated_at = NOW() WHERE user_id_erp = ? AND jid = ?',
        [name.trim(), userId, jid],
    );
    return true;
}

async function updateChatName(userId, jid, name) {
    if (!jid || !name?.trim()) return false;

    const [rows] = await getPool().execute(
        'SELECT name FROM wa_chats WHERE user_id_erp = ? AND jid = ? LIMIT 1',
        [userId, jid],
    );

    const merged = jid.endsWith('@g.us')
        ? pickBetterGroupName(rows[0]?.name, name.trim(), jid)
        : pickBetterName(rows[0]?.name, name.trim(), jid);
    if (!merged || merged === rows[0]?.name) return false;

    await getPool().execute(
        'UPDATE wa_chats SET name = ?, updated_at = NOW() WHERE user_id_erp = ? AND jid = ?',
        [merged, userId, jid],
    );
    return true;
}

async function fetchGroupSubject(sock, jid) {
    if (!sock || !isJidGroup(jid)) return null;
    try {
        const meta = await sock.groupMetadata(jid);
        return meta?.subject?.trim() || null;
    } catch {
        return null;
    }
}

async function resolveChatName(userId, jid, { sock = null, hintName = null } = {}) {
    const session = sock ? { sock } : getWaSession(userId);
    const activeSock = session?.sock || sock;

    let name = hintName?.trim() || null;

    if (isJidGroup(jid)) {
        if (isPhoneLikeName(name, jid)) name = null;
        if (!name && activeSock) {
            name = await fetchGroupSubject(activeSock, jid);
        }
    } else {
        if (isPhoneLikeName(name, jid)) name = null;
        if (!name) {
            name = await contactService.getContactName(userId, jid);
        }
        if (!name) {
            name = await contactService.resolveLiveContactName(userId, jid, { persist: false });
        }
    }

    if (name && isGoodContactName(name, jid)) {
        return name.trim();
    }

    return null;
}

function needsNameRefresh(name, jid) {
    if (!name) return true;
    return isPhoneLikeName(name, jid) || isWeakContactName(name);
}

async function enrichPrivateChatNames(userId, sock) {
    const [chats] = await getPool().execute(
        `SELECT jid, name FROM wa_chats
         WHERE user_id_erp = ? AND jid NOT LIKE '%@g.us' AND jid NOT LIKE '%@broadcast'`,
        [userId],
    );

    let updated = 0;

    for (const chat of chats) {
        if (!needsNameRefresh(chat.name, chat.jid)) continue;

        let resolved = await contactService.findBestContactName(userId, chat.jid);
        if (!resolved) {
            resolved = await contactService.resolveLiveContactName(userId, chat.jid, { persist: true });
        }
        if (!resolved) continue;

        const changed = await updateChatName(userId, chat.jid, resolved);
        if (changed) updated += 1;
    }

    return updated;
}

async function syncContactNamesToChats(userId) {
    const pool = getPool();
    let updated = 0;

    const [contacts] = await pool.execute(
        `SELECT jid, name, phone FROM wa_contacts
         WHERE user_id_erp = ? AND name IS NOT NULL AND name != ''`,
        [userId],
    );

    for (const contact of contacts) {
        if (!isGoodContactName(contact.name, contact.jid)) continue;
        const changed = await updateChatName(userId, contact.jid, contact.name);
        if (changed) updated += 1;
    }

    for (const contact of contacts) {
        if (!contact.phone || !isGoodContactName(contact.name, contact.jid)) continue;

        const [rows] = await getPool().execute(
            `SELECT jid, name FROM wa_chats
             WHERE user_id_erp = ?
             AND (
                jid = ?
                OR jid LIKE ?
                OR jid LIKE ?
                OR SUBSTRING_INDEX(jid, '@', 1) = ?
             )`,
            [
                userId,
                contact.jid,
                `${contact.phone}@%`,
                `%${contact.phone}%`,
                contact.phone,
            ],
        );

        for (const chat of rows) {
            if (chat.jid.endsWith('@g.us')) continue;
            if (!needsNameRefresh(chat.name, chat.jid)) continue;
            const changed = await updateChatName(userId, chat.jid, contact.name);
            if (changed) updated += 1;
        }
    }

    return updated;
}

async function enrichGroupNames(userId, sock) {
    if (!sock) return 0;

    let updated = 0;

    try {
        const groups = await sock.groupFetchAllParticipating();
        for (const [jid, meta] of Object.entries(groups || {})) {
            const subject = meta?.subject || meta?.name;
            if (!subject) continue;
            const changed = await updateChatName(userId, jid, subject);
            if (changed) updated += 1;
        }
    } catch (error) {
        console.warn(`[chatName] groupFetchAllParticipating failed for ${userId}:`, error.message);
    }

    const [groupChats] = await getPool().execute(
        `SELECT jid, name FROM wa_chats
         WHERE user_id_erp = ? AND jid LIKE '%@g.us'
         AND (name IS NULL OR name = SUBSTRING_INDEX(jid, '@', 1) OR name REGEXP '^[0-9\\\\-]{8,}$')`,
        [userId],
    );

    for (const chat of groupChats) {
        const subject = await fetchGroupSubject(sock, chat.jid);
        if (!subject) continue;
        const changed = await updateChatName(userId, chat.jid, subject);
        if (changed) updated += 1;
    }

    return updated;
}

async function enrichAllChatNames(userId) {
    const session = getWaSession(userId);
    const sock = session?.sock;
    if (!sock) return { updated: 0 };

    let updated = 0;
    updated += await enrichGroupNames(userId, sock);
    updated += await contactService.syncNamesViaPhoneLink(userId);
    updated += await syncContactNamesToChats(userId);
    updated += await enrichPrivateChatNames(userId, sock);

    const [chats] = await getPool().execute(
        'SELECT jid, name FROM wa_chats WHERE user_id_erp = ?',
        [userId],
    );

    for (const chat of chats) {
        if (!needsNameRefresh(chat.name, chat.jid)) continue;

        const resolved = await resolveChatName(userId, chat.jid, { sock });
        let finalName = resolved;
        if (!finalName) {
            finalName = await contactService.resolveLiveContactName(userId, chat.jid, { persist: true });
        }
        if (!finalName) continue;

        const changed = await updateChatName(userId, chat.jid, finalName);
        if (changed) updated += 1;
    }

    if (updated > 0) {
        const messageService = require('./messageService');
        await messageService.syncAndEmitChats(userId);
    }

    if (updated > 0) {
        console.log(`[chatName] user ${userId} — ${updated} nama chat diperbarui`);
    }

    return { updated };
}

function resolveDisplayName(row, contactName = null, { pushName = null } = {}) {
    const jid = row?.jid;
    const chatName = row?.name?.trim() || null;
    const contact = contactName?.trim() || null;
    const livePushName = pushName?.trim() || null;
    const isGroup = Boolean(row?.is_group) || jid?.endsWith('@g.us');

    if (isGroup) {
        if (chatName && !isWeakContactName(chatName)) {
            return chatName;
        }
        return formatChatFallbackName(jid);
    }

    if (contact && isGoodContactName(contact, jid)) {
        return contact;
    }

    if (livePushName && isGoodContactName(livePushName, jid)) {
        return livePushName;
    }

    if (chatName && isGoodContactName(chatName, jid)) {
        return chatName;
    }

    if (contact && !isWeakContactName(contact)) {
        return contact;
    }

    return formatChatFallbackName(jid, row?.phone || null);
}

const enrichLocks = new Map();
const enrichMeta = new Map();

const ENRICH_COOLDOWN_MS = 10 * 60 * 1000;
const ENRICH_LOCK_MS = 5 * 60 * 1000;

async function enrichSingleChatName(userId, jid) {
    if (!jid) return false;

    const session = getWaSession(userId);
    const sock = session?.sock;

    if (jid.endsWith('@g.us')) {
        const subject = await fetchGroupSubject(sock, jid);
        if (subject) {
            return updateChatName(userId, jid, subject);
        }
    }

    let resolved = await resolveChatName(userId, jid, { sock });
    if (!resolved) {
        resolved = await contactService.findBestContactName(userId, jid);
    }
    if (!resolved) {
        resolved = await contactService.resolveLiveContactName(userId, jid, { persist: true });
    }
    if (!resolved) return false;

    const changed = await updateChatName(userId, jid, resolved);
    if (changed) {
        const messageService = require('./messageService');
        const { emitChatUpdate } = require('../baileys/qrHandler');
        const chat = await messageService.getChatByJid(userId, jid);
        if (chat) emitChatUpdate(userId, chat);
    }
    return changed;
}

async function maybeEnrichChatNames(userId, { force = false } = {}) {
    const key = String(userId);
    const now = Date.now();
    const meta = enrichMeta.get(key) || {};

    if (!force && meta.lastAt && (now - meta.lastAt) < ENRICH_COOLDOWN_MS) {
        return { updated: 0, skipped: true };
    }

    if (enrichLocks.has(key)) {
        return enrichLocks.get(key);
    }

    const promise = enrichAllChatNames(userId).then((result) => {
        enrichMeta.set(key, { lastAt: Date.now() });
        return result;
    }).finally(() => {
        setTimeout(() => enrichLocks.delete(key), ENRICH_LOCK_MS);
    });

    enrichLocks.set(key, promise);
    return promise;
}

module.exports = {
    updateChatName,
    forceUpdateChatName,
    fetchGroupSubject,
    resolveChatName,
    syncContactNamesToChats,
    enrichGroupNames,
    enrichPrivateChatNames,
    enrichAllChatNames,
    enrichSingleChatName,
    maybeEnrichChatNames,
    resolveDisplayName,
};
