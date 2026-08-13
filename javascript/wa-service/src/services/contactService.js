const { getPool } = require('../db/connection');
const { pickBetterName, isGoodContactName, isWeakContactName, isPhoneLikeName, stripJid, formatChatFallbackName } = require('../utils/nameUtils');

function getWaSession(userId) {
    return require('../baileys/sessionManager').getSession(userId);
}

function getStoreContactRecord(userId, jid) {
    const session = getWaSession(userId);
    const store = session?.contactStore;
    if (!store || !jid) return null;

    const direct = store.contacts?.[jid];
    if (direct) return direct;

    return store.getAll?.().find((item) => item.id === jid) || null;
}

function getStoreContactName(userId, jid) {
    const { extractContactName } = require('../utils/messageParser');
    const record = getStoreContactRecord(userId, jid);
    if (!record) return null;
    const name = extractContactName(record);
    return name && isGoodContactName(name, jid) ? name.trim() : null;
}

async function findLatestPushNameForChat(userId, jid) {
    const [rows] = await getPool().execute(
        `SELECT m.sender_push_name, m.raw_message
         FROM wa_messages m
         JOIN wa_chats c ON c.id = m.chat_id
         WHERE c.user_id_erp = ? AND c.jid = ? AND m.from_me = 0
         ORDER BY COALESCE(m.timestamp_ms, UNIX_TIMESTAMP(m.timestamp) * 1000) DESC, m.id DESC
         LIMIT 20`,
        [userId, jid],
    );

    for (const row of rows) {
        const senderPush = row.sender_push_name?.trim();
        if (senderPush && isGoodContactName(senderPush, jid)) {
            return senderPush;
        }

        if (!row.raw_message) continue;
        try {
            const raw = typeof row.raw_message === 'string'
                ? JSON.parse(row.raw_message)
                : row.raw_message;
            const pushName = raw?.pushName?.trim();
            if (pushName && isGoodContactName(pushName, jid)) {
                return pushName;
            }
        } catch {
            // ignore invalid raw payload
        }
    }

    return null;
}

async function resolveLiveContactName(userId, jid, { persist = true } = {}) {
    if (!jid || jid.endsWith('@g.us') || jid.endsWith('@broadcast')) return null;

    const existing = await findBestContactName(userId, jid);
    if (existing) return existing;

    const storeName = getStoreContactName(userId, jid);
    if (storeName) {
        if (persist) {
            await upsertContact(userId, {
                jid,
                name: storeName,
                push_name: storeName,
            }, { allowPushName: true });
        }
        return storeName;
    }

    const messagePushName = await findLatestPushNameForChat(userId, jid);
    if (messagePushName) {
        if (persist) {
            await upsertContact(userId, {
                jid,
                name: messagePushName,
                push_name: messagePushName,
            }, { allowPushName: true });
        }
        return messagePushName;
    }

    return null;
}

async function getContact(userId, jid) {
    const [rows] = await getPool().execute(
        'SELECT * FROM wa_contacts WHERE user_id_erp = ? AND jid = ? LIMIT 1',
        [userId, jid],
    );
    return rows[0] || null;
}

async function upsertContact(userId, contact, { force = false, allowPushName = false, fromPhonebook = false } = {}) {
    if (!contact?.jid) return;

    const existing = await getContact(userId, contact.jid);
    const phone = contact.phone
        || existing?.phone
        || (contact.jid.endsWith('@s.whatsapp.net') ? stripJid(contact.jid) : null);

    if (existing?.is_manual && !force) {
        if (phone && phone !== existing.phone) {
            await getPool().execute(
                `UPDATE wa_contacts SET phone = ?, synced_at = NOW(), updated_at = NOW()
                 WHERE user_id_erp = ? AND jid = ?`,
                [phone, userId, contact.jid],
            );
        }
        return;
    }

    const phonebookName = contact.phonebook_name ?? contact.name ?? null;
    const pushName = contact.push_name ?? null;

    let name;
    if (force) {
        name = phonebookName?.trim() || pushName?.trim() || null;
    } else if (fromPhonebook && phonebookName) {
        // Sama seperti baileys-store: nama phonebook dari contacts.upsert langsung dipakai
        name = existing?.is_manual ? (existing.name?.trim() || phonebookName) : phonebookName;
    } else if (phonebookName) {
        name = pickBetterName(existing?.name, phonebookName, contact.jid);
    } else if (allowPushName && pushName) {
        name = pickBetterName(existing?.name, pushName, contact.jid);
    } else {
        name = pickBetterName(existing?.name, null, contact.jid);
    }

    if (!name && !phone && !existing) return;

    await getPool().execute(
        `INSERT INTO wa_contacts (user_id_erp, jid, name, phone, is_manual, synced_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, NOW(), NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            name = CASE
                WHEN is_manual = 1 AND ? = 0 THEN name
                WHEN VALUES(name) IS NOT NULL AND VALUES(name) != '' THEN VALUES(name)
                ELSE name
            END,
            phone = COALESCE(VALUES(phone), phone),
            is_manual = CASE WHEN ? = 1 THEN 1 ELSE is_manual END,
            synced_at = NOW(),
            updated_at = NOW()`,
        [
            userId,
            contact.jid,
            name,
            phone,
            force ? 1 : 0,
            force ? 1 : 0,
            force ? 1 : 0,
        ],
    );
}

async function upsertContacts(userId, contacts = [], { allowPushName = false, fromPhonebook = false } = {}) {
    let synced = 0;
    for (const raw of contacts) {
        const { parseBaileysContact } = require('../utils/messageParser');
        const contact = typeof raw.id === 'string' ? parseBaileysContact(raw) : raw;
        if (!contact?.jid) continue;
        if (contact.jid.endsWith('@g.us') || contact.jid.endsWith('@broadcast')) continue;
        await upsertContact(userId, contact, { allowPushName, fromPhonebook });
        if (contact.lid_jid && contact.phone_jid) {
            await linkJidPair(userId, contact.lid_jid, contact.phone_jid);
        }
        synced += 1;
    }
    return synced;
}

async function linkJidPair(userId, lidJid, phoneJid) {
    if (!lidJid || !phoneJid) return;

    const phoneBare = stripJid(phoneJid);
    const phoneContact = await getContact(userId, phoneJid);
    const lidContact = await getContact(userId, lidJid);
    const bestName = pickBetterName(
        pickBetterName(phoneContact?.name, lidContact?.name, lidJid),
        null,
        phoneJid,
    );

    if (bestName) {
        await upsertContact(userId, { jid: phoneJid, name: bestName, phone: phoneBare });
        await upsertContact(userId, { jid: lidJid, name: bestName, phone: phoneBare });
    } else {
        await upsertContact(userId, { jid: phoneJid, phone: phoneBare });
        await upsertContact(userId, { jid: lidJid, phone: phoneBare });
    }
}

async function findBestContactName(userId, jid) {
    const candidates = [];

    const exact = await getContact(userId, jid);
    if (exact?.name) candidates.push(exact.name);

    const bare = stripJid(jid);
    const suffix = jid.split('@')[1] || '';

    const [related] = await getPool().execute(
        `SELECT name FROM wa_contacts
         WHERE user_id_erp = ? AND name IS NOT NULL AND name != ''
         AND (
            jid = ?
            OR phone = ?
            OR jid LIKE ?
            OR (? != '' AND phone = ?)
         )
         ORDER BY synced_at DESC`,
        [userId, jid, bare, `${bare}@%`, suffix, bare],
    );

    for (const row of related) {
        if (row.name) candidates.push(row.name);
    }

    if (suffix === 'lid' && exact?.phone) {
        const phoneJid = `${exact.phone}@s.whatsapp.net`;
        const phoneContact = await getContact(userId, phoneJid);
        if (phoneContact?.name) candidates.push(phoneContact.name);
    }

    for (const candidate of candidates) {
        if (isGoodContactName(candidate, jid)) {
            return candidate.trim();
        }
    }

    return null;
}

async function syncNamesViaPhoneLink(userId) {
    const [result] = await getPool().execute(
        `UPDATE wa_chats c
         INNER JOIN wa_contacts lid_ct ON lid_ct.user_id_erp = c.user_id_erp AND lid_ct.jid = c.jid
         INNER JOIN wa_contacts phone_ct ON phone_ct.user_id_erp = c.user_id_erp
            AND phone_ct.phone = lid_ct.phone
            AND phone_ct.jid LIKE '%@s.whatsapp.net'
            AND phone_ct.name IS NOT NULL AND phone_ct.name != ''
         SET c.name = phone_ct.name, c.updated_at = NOW()
         WHERE c.user_id_erp = ?
           AND lid_ct.phone IS NOT NULL
           AND (
                c.name IS NULL OR c.name = ''
                OR c.name = SUBSTRING_INDEX(c.jid, '@', 1)
                OR c.name REGEXP '^[0-9]{10,}$'
                OR c.name LIKE '~%'
           )`,
        [userId],
    );
    return result.affectedRows || 0;
}

function formatPhoneValue(phone) {
    if (!phone) return null;
    const digits = String(phone).replace(/\D/g, '');
    if (!digits) return null;
    return digits.startsWith('62') || digits.length >= 10 ? `+${digits}` : `+${digits}`;
}

function formatContactRow(row) {
    const jid = row.jid;
    const phoneRaw = row.phone || (jid.endsWith('@s.whatsapp.net') ? stripJid(jid) : null);
    const savedName = row.name?.trim() || null;
    const hasManualName = Boolean(row.is_manual);
    const hasPhonebookName = savedName
        && !isWeakContactName(savedName)
        && !isPhoneLikeName(savedName, jid);
    const hasGoodName = hasPhonebookName && isGoodContactName(savedName, jid);

    return {
        jid,
        name: savedName && (hasManualName || hasPhonebookName || hasGoodName)
            ? savedName
            : formatChatFallbackName(jid, phoneRaw),
        phone: formatPhoneValue(phoneRaw),
        avatar_url: row.avatar_url || null,
        has_saved_name: hasManualName || hasPhonebookName || hasGoodName,
        is_manual: hasManualName,
    };
}

async function resolvePhoneForJid(userId, jid) {
    const contact = await getContact(userId, jid);
    if (contact?.phone) {
        return String(contact.phone).replace(/\D/g, '');
    }

    if (jid.endsWith('@s.whatsapp.net')) {
        return stripJid(jid);
    }

    if (jid.endsWith('@lid')) {
        const [rows] = await getPool().execute(
            `SELECT phone FROM wa_contacts
             WHERE user_id_erp = ? AND jid = ? AND phone IS NOT NULL
             LIMIT 1`,
            [userId, jid],
        );
        if (rows[0]?.phone) {
            return String(rows[0].phone).replace(/\D/g, '');
        }
    }

    return null;
}

async function mirrorContactToLinkedJids(userId, jid, name, phone) {
    if (!phone) return;

    const phoneJid = `${phone}@s.whatsapp.net`;
    const targets = new Set([jid, phoneJid]);

    const [linked] = await getPool().execute(
        `SELECT jid FROM wa_contacts
         WHERE user_id_erp = ? AND phone = ?`,
        [userId, phone],
    );

    for (const row of linked) {
        if (row.jid) targets.add(row.jid);
    }

    for (const targetJid of targets) {
        if (targetJid.endsWith('@g.us')) continue;
        await getPool().execute(
            `INSERT INTO wa_contacts (user_id_erp, jid, name, phone, is_manual, synced_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, 1, NOW(), NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                phone = COALESCE(VALUES(phone), phone),
                is_manual = 1,
                updated_at = NOW()`,
            [userId, targetJid, name, phone],
        );
    }
}

async function pushContactToDevice(sock, jid, name, phone) {
    if (!sock || !name?.trim()) return false;

    try {
        let targetJid = jid;
        if (jid.endsWith('@lid') && phone) {
            targetJid = `${phone}@s.whatsapp.net`;
        }

        if (typeof sock.addOrEditContact === 'function') {
            const firstName = name.trim().split(/\s+/)[0] || name.trim();
            await sock.addOrEditContact(targetJid, {
                fullName: name.trim(),
                firstName,
                saveOnPrimaryAddressbook: true,
            });
            return true;
        }
    } catch (error) {
        console.warn('[contactService] pushContactToDevice failed:', error.message);
    }

    return false;
}

async function saveContactManual(userId, jid, name, { sock = null, pushToDevice = true } = {}) {
    const trimmed = name?.trim();
    if (!trimmed) {
        throw new Error('Nama kontak wajib diisi');
    }
    if (!jid || jid.endsWith('@g.us') || jid.endsWith('@broadcast')) {
        throw new Error('Kontak tidak valid');
    }

    const phone = await resolvePhoneForJid(userId, jid);
    await upsertContact(userId, { jid, name: trimmed, phone }, { force: true });
    await mirrorContactToLinkedJids(userId, jid, trimmed, phone);

    const chatNameService = require('./chatNameService');
    await chatNameService.forceUpdateChatName(userId, jid, trimmed);

    if (pushToDevice && sock) {
        await pushContactToDevice(sock, jid, trimmed, phone);
    }

    return formatContactRow(await getContact(userId, jid));
}

async function getContactDetail(userId, jid) {
    if (!jid) return null;

    const row = await getContact(userId, jid);
    if (row) {
        return formatContactRow(row);
    }

    const phone = await resolvePhoneForJid(userId, jid);
    return formatContactRow({
        jid,
        name: null,
        phone,
        avatar_url: null,
        is_manual: 0,
    });
}

function collectStoreContacts(source) {
    if (!source) return [];

    if (typeof source.getAll === 'function') {
        return source.getAll();
    }

    const raw = source.contacts || source;
    if (!raw || typeof raw !== 'object') return [];

    return Object.entries(raw).map(([id, meta]) => ({ id, ...(meta || {}) }));
}

async function syncContactsFromDevice(userId, sock, contactStore = null) {
    if (!sock && !contactStore) return 0;

    const store = contactStore || sock?.contactStore || null;
    const entries = collectStoreContacts(store || sock?.store);

    if (!entries.length) return 0;

    return upsertContacts(userId, entries, { allowPushName: true, fromPhonebook: false });
}

async function getChatContactMeta(userId, row) {
    if (!row || row.is_group || row.jid?.endsWith('@g.us')) {
        return { phone: null, has_saved_name: true, is_manual: false, avatar_url: row?.avatar_url || null };
    }

    const detail = await getContactDetail(userId, row.jid);
    const phone = detail?.phone?.replace(/\D/g, '') || null;
    const contactName = detail?.name && isGoodContactName(detail.name, row.jid)
        ? detail.name
        : null;

    return {
        phone: detail?.phone || null,
        has_saved_name: Boolean(detail?.has_saved_name && contactName),
        is_manual: Boolean(detail?.is_manual),
        contact_name: contactName,
        avatar_url: detail?.avatar_url || null,
    };
}

async function getContacts(userId, { search = '' } = {}) {
    const [rows] = await getPool().execute(
        `SELECT jid, name, phone, avatar_url, synced_at, is_manual
         FROM wa_contacts
         WHERE user_id_erp = ?
         ORDER BY name ASC, jid ASC`,
        [userId],
    );

    const seen = new Map();
    const q = search.trim().toLowerCase();

    for (const row of rows) {
        const formatted = formatContactRow(row);
        const dedupeKey = formatted.phone || formatted.jid;

        if (seen.has(dedupeKey)) {
            const prev = seen.get(dedupeKey);
            if (!prev.has_saved_name && formatted.has_saved_name) {
                seen.set(dedupeKey, formatted);
            }
            continue;
        }

        if (q) {
            const hay = `${formatted.name} ${formatted.phone || ''} ${formatted.jid}`.toLowerCase();
            if (!hay.includes(q)) continue;
        }

        seen.set(dedupeKey, formatted);
    }

    return [...seen.values()].sort((a, b) => a.name.localeCompare(b.name, 'id'));
}

async function applyMessageContactHints(userId, raw, parsed) {
    if (!parsed?.jid || parsed.from_me) return;

    const pushName = raw?.pushName?.trim();
    if (pushName && isGoodContactName(pushName, parsed.jid)) {
        await upsertContact(userId, {
            jid: parsed.jid,
            name: pushName,
            push_name: pushName,
            phone: parsed.jid.endsWith('@s.whatsapp.net') ? stripJid(parsed.jid) : undefined,
        }, { allowPushName: true });

        if (!parsed.jid.endsWith('@g.us')) {
            const chatNameService = require('./chatNameService');
            await chatNameService.updateChatName(userId, parsed.jid, pushName);
        }
    }

    const senderPn = raw?.key?.senderPn || raw?.senderPn;
    if (senderPn && parsed.jid.endsWith('@lid')) {
        await linkJidPair(userId, parsed.jid, senderPn);
    }
}

async function getContactName(userId, jid) {
    return findBestContactName(userId, jid);
}

async function getContactNamesMap(userId, jids = []) {
    if (!jids.length) return new Map();

    const index = await buildContactNameIndex(userId);
    const map = new Map();

    for (const jid of [...new Set(jids.filter(Boolean))]) {
        const name = lookupNameFromIndex(jid, index);
        if (name) map.set(jid, name);
    }

    return map;
}

async function buildContactNameIndex(userId) {
    const [rows] = await getPool().execute(
        `SELECT jid, name, phone FROM wa_contacts
         WHERE user_id_erp = ? AND name IS NOT NULL AND name != ''`,
        [userId],
    );

    const byJid = new Map();
    const byPhone = new Map();
    const lidToPhone = new Map();

    for (const row of rows) {
        if (!isGoodContactName(row.name, row.jid)) continue;

        byJid.set(row.jid, row.name);
        if (row.phone) {
            byPhone.set(row.phone, row.name);
            if (row.jid.endsWith('@lid')) {
                lidToPhone.set(row.jid, row.phone);
            }
        }
    }

    return { byJid, byPhone, lidToPhone };
}

function lookupNameFromIndex(jid, index) {
    if (!jid || !index) return null;

    if (index.byJid.has(jid)) {
        return index.byJid.get(jid);
    }

    const bare = stripJid(jid);
    if (index.byPhone.has(bare)) {
        return index.byPhone.get(bare);
    }

    if (jid.endsWith('@lid') && index.lidToPhone.has(jid)) {
        const phone = index.lidToPhone.get(jid);
        if (index.byPhone.has(phone)) {
            return index.byPhone.get(phone);
        }
    }

    if (jid.endsWith('@s.whatsapp.net') && index.byPhone.has(bare)) {
        return index.byPhone.get(bare);
    }

    return null;
}

module.exports = {
    upsertContact,
    upsertContacts,
    getContact,
    getContactName,
    findBestContactName,
    resolveLiveContactName,
    getStoreContactName,
    getContactNamesMap,
    buildContactNameIndex,
    lookupNameFromIndex,
    linkJidPair,
    syncNamesViaPhoneLink,
    getContacts,
    getContactDetail,
    saveContactManual,
    syncContactsFromDevice,
    getChatContactMeta,
    pushContactToDevice,
    applyMessageContactHints,
    formatContactRow,
};
