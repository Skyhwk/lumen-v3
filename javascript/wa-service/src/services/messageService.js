const { getPool } = require('../db/connection');
const { parseBaileysMessage, parseBaileysChat } = require('../utils/messageParser');
const {
    buildMessageKey,
    buildQuotedFromRow,
    buildForwardPayload,
    parseMentionsJson,
    serializeMentions,
} = require('../utils/messageActionUtils');
const contactService = require('./contactService');
const chatNameService = require('./chatNameService');
const mediaService = require('./mediaService');
const { emitChatsSync, emitMessageNew, emitChatUpdate, emitChatDeleted, emitMessageMedia, emitMessageDeleted, emitMessageEdited } = require('../baileys/qrHandler');
const { isStatusJid, splitChats, formatStatusLabel } = require('../utils/chatUtils');
const { sortChatsByUnread, dedupeChatsByPhone } = require('../utils/chatSortUtils');
const { pickStatus } = require('../utils/messageStatus');
const { isPhoneLikeName, isWeakContactName, isGoodContactName, stripJid, isLidJid, isBareNumericId, resolvePhoneOrJid } = require('../utils/nameUtils');
const { isGroupJid, isGroupMessageRetained } = require('../utils/groupRetentionUtils');
const { getChatAnchor, setChatAnchorFromMessage } = require('./chatAnchorService');
const { isMessageHistorySyncEnabled } = require('../utils/syncConfig');
const { toMysqlDatetime, toTimestampMs, toApiIsoFromMs } = require('../utils/timestampUtils');
const { sendWhatsAppAlbum } = require('../utils/albumSender');

function extractPushNameFromRaw(rawMessage) {
    if (!rawMessage) return null;
    try {
        const raw = typeof rawMessage === 'string' ? JSON.parse(rawMessage) : rawMessage;
        return raw?.pushName?.trim() || null;
    } catch {
        return null;
    }
}

function resolveGroupSenderDisplayName(msg, contactName = null) {
    if (contactName && isGoodContactName(contactName, msg.sender_jid)) {
        return contactName;
    }

    const pushName = msg.sender_push_name || extractPushNameFromRaw(msg.raw_message);
    if (pushName && !isWeakContactName(pushName) && !isPhoneLikeName(pushName, msg.sender_jid)) {
        return pushName;
    }

    const bare = stripJid(msg.sender_jid);
    if (isLidJid(msg.sender_jid) || isBareNumericId(bare)) {
        return pushName?.trim() || 'Anggota grup';
    }

    if (bare && !isBareNumericId(bare)) {
        return bare;
    }

    return 'Anggota grup';
}

function getWaSession(userId) {
    return require('../baileys/sessionManager').getSession(userId);
}

async function requireWaSession(userId) {
    return require('../baileys/sessionManager').requireConnectedSession(userId);
}

function inferMediaTypeFromMime(mime = '', filename = '') {
    const type = String(mime).toLowerCase();
    const name = String(filename).toLowerCase();
    if (type.startsWith('image/')) return 'image';
    if (type.startsWith('video/')) return 'video';
    if (type.startsWith('audio/')) return 'audio';
    if (/\.(jpe?g|png|gif|webp)$/i.test(name)) return 'image';
    if (/\.(mp4|mov|webm|mkv)$/i.test(name)) return 'video';
    if (/\.(mp3|ogg|wav|m4a|aac)$/i.test(name)) return 'audio';
    return 'document';
}

const messageSyncLocks = new Map();

async function buildLastMessagePreview(userId, parsed) {
    const content = parsed.content || '';
    const truncated = content.length > 120 ? `${content.slice(0, 117)}...` : content;

    if (!parsed.jid?.endsWith('@g.us')) {
        return truncated;
    }

    if (parsed.from_me) {
        return truncated ? `Anda: ${truncated}` : 'Anda';
    }

    const index = await contactService.buildContactNameIndex(userId);
    const contactName = contactService.lookupNameFromIndex(parsed.sender_jid, index);
    const sender = resolveGroupSenderDisplayName(parsed, contactName);

    return truncated ? `${sender}: ${truncated}` : sender;
}

function resolveQuotedSenderDisplayName(msg, index, { chatJid, chatName } = {}) {
    if (msg.quoted_from_me) {
        return 'Anda';
    }

    if (msg.quoted_sender_name === 'Anda') {
        return 'Anda';
    }

    const jid = chatJid || msg.jid;
    const isGroup = jid?.endsWith('@g.us');

    if (!msg.quoted_text && !msg.reply_to_wa_message_id) {
        return null;
    }

    if (!msg.quoted_sender_jid) {
        return 'Anda';
    }

    const contactName = contactService.lookupNameFromIndex(msg.quoted_sender_jid, index);

    if (isGroup) {
        return resolveGroupSenderDisplayName(
            {
                sender_jid: msg.quoted_sender_jid,
                sender_push_name: msg.sender_push_name,
                raw_message: msg.raw_message,
            },
            contactName,
        );
    }

    if (contactName && isGoodContactName(contactName, msg.quoted_sender_jid)) {
        return contactName;
    }

    if (chatName && isGoodContactName(chatName, jid)) {
        return chatName;
    }

    const bare = stripJid(msg.quoted_sender_jid);
    if (bare && !isBareNumericId(bare)) {
        return bare;
    }

    return chatName || bare || 'Kontak';
}

async function buildQuotedFromMeMap(userId, jid, waMessageIds = []) {
    const ids = [...new Set(waMessageIds.filter(Boolean))];
    if (!ids.length || !jid) return new Map();

    const placeholders = ids.map(() => '?').join(',');
    const [rows] = await getPool().execute(
        `SELECT m.wa_message_id, m.from_me
         FROM wa_messages m
         JOIN wa_chats c ON c.id = m.chat_id
         WHERE c.user_id_erp = ? AND c.jid = ? AND m.wa_message_id IN (${placeholders})`,
        [userId, jid, ...ids],
    );

    const map = new Map();
    for (const row of rows) {
        if (row.from_me) {
            map.set(row.wa_message_id, true);
        }
    }
    return map;
}

function isQuotedSenderMe(msg, userPhone) {
    if (!msg?.quoted_sender_jid || !userPhone) return false;
    const bare = stripJid(msg.quoted_sender_jid);
    const phoneDigits = String(userPhone).replace(/\D/g, '');
    return Boolean(phoneDigits && bare === phoneDigits);
}

async function applyQuotedReplyMeta(userId, parsed, userPhone) {
    if (!parsed?.reply_to_wa_message_id) return;

    const quotedRow = await getMessageRow(userId, parsed.jid, parsed.reply_to_wa_message_id);
    if (quotedRow?.from_me) {
        parsed.quoted_sender_jid = null;
        parsed.quoted_sender_name = 'Anda';
        return;
    }

    if (isQuotedSenderMe(parsed, userPhone)) {
        parsed.quoted_sender_jid = null;
        parsed.quoted_sender_name = 'Anda';
    }
}

async function enrichMessagesWithSenders(userId, messages = [], chatMeta = {}) {
    if (!messages.length) return messages;

    const index = await contactService.buildContactNameIndex(userId);
    const isGroup = messages.some((msg) => msg.jid?.endsWith('@g.us'));
    const chatJid = chatMeta.jid || messages[0]?.jid || null;
    const replyIds = messages.map((msg) => msg.reply_to_wa_message_id).filter(Boolean);
    const quotedFromMeMap = await buildQuotedFromMeMap(userId, chatJid, replyIds);
    const session = getWaSession(userId);
    const userPhone = session?.phone || null;

    return messages.map((msg) => {
        let next = { ...msg };

        if (isGroup && !msg.from_me && msg.sender_jid) {
            const contactName = contactService.lookupNameFromIndex(msg.sender_jid, index);
            next.sender_name = resolveGroupSenderDisplayName(msg, contactName);
        }

        if (msg.quoted_text || msg.reply_to_wa_message_id) {
            const quotedFromMe = quotedFromMeMap.get(msg.reply_to_wa_message_id)
                || isQuotedSenderMe(msg, userPhone);

            if (quotedFromMe) {
                next.quoted_sender_name = 'Anda';
                next.quoted_from_me = true;
                next.quoted_sender_jid = null;
            } else {
                const quotedName = resolveQuotedSenderDisplayName(msg, index, {
                    chatJid: msg.jid,
                    chatName: chatMeta.name || msg.chat_name,
                });
                if (quotedName) {
                    next.quoted_sender_name = quotedName;
                }
            }
        }

        const mentions = Array.isArray(msg.mentions)
            ? msg.mentions
            : parseMentionsJson(msg.mentions);
        if (mentions?.length) {
            const mentionNames = {};
            for (const jid of mentions) {
                const name = contactService.lookupNameFromIndex(jid, index);
                if (!name) continue;
                const bare = stripJid(jid);
                const digits = bare.replace(/\D/g, '') || bare;
                mentionNames[jid] = name;
                mentionNames[bare] = name;
                if (digits) mentionNames[digits] = name;
            }
            if (Object.keys(mentionNames).length) {
                next.mention_names = mentionNames;
            }
        }

        return next;
    });
}

async function ensureChat(userId, jid, patch = {}) {
    const pool = getPool();
    const session = getWaSession(userId);
    const isGroup = patch.is_group != null
        ? Boolean(patch.is_group)
        : jid.endsWith('@g.us');

    let name = patch.name || null;
    if (isPhoneLikeName(name, jid) || isWeakContactName(name)) name = null;

    if (isGroup) {
        if (!name) {
            name = await chatNameService.resolveChatName(userId, jid, {
                sock: session?.sock,
                hintName: patch.name,
            });
        }
    } else {
        if (!name) {
            name = await contactService.getContactName(userId, jid);
        }

        if (!name) {
            name = await chatNameService.resolveChatName(userId, jid, {
                sock: session?.sock,
                hintName: patch.name,
            });
        }
    }

    if (!name || isWeakContactName(name) || isPhoneLikeName(name, jid)) {
        name = null;
    }

    await pool.execute(
        `INSERT INTO wa_chats (user_id_erp, jid, name, is_group, last_message, last_message_at, unread_count, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            name = CASE
                WHEN jid LIKE '%@g.us' THEN
                    CASE
                        WHEN name IS NULL OR name = '' OR name = SUBSTRING_INDEX(jid, '@', 1)
                            OR name REGEXP '^[0-9+\\\\-\\\\s]{8,}$'
                            OR name LIKE '~%'
                            OR LOWER(name) = 'whatsapp'
                            OR LENGTH(COALESCE(VALUES(name), '')) >= LENGTH(name)
                        THEN COALESCE(NULLIF(VALUES(name), ''), name)
                        ELSE name
                    END
                WHEN VALUES(name) IS NOT NULL AND VALUES(name) != ''
                    AND (
                        name IS NULL OR name = ''
                        OR name = SUBSTRING_INDEX(jid, '@', 1)
                        OR name REGEXP '^[0-9+\\\\-\\\\s]{8,}$'
                        OR name LIKE '~%'
                        OR LOWER(name) = 'whatsapp'
                        OR LENGTH(VALUES(name)) > LENGTH(name)
                    )
                THEN VALUES(name)
                ELSE name
            END,
            is_group = COALESCE(VALUES(is_group), is_group),
            last_message = COALESCE(VALUES(last_message), last_message),
            last_message_at = COALESCE(VALUES(last_message_at), last_message_at),
            unread_count = CASE
                WHEN ? IS NOT NULL THEN VALUES(unread_count)
                ELSE unread_count
            END,
            updated_at = NOW()`,
        [
            userId,
            jid,
            name,
            isGroup ? 1 : 0,
            patch.last_message || null,
            patch.last_message_at ? toMysqlDatetime(patch.last_message_at) : null,
            patch.unread_count ?? 0,
            patch.unread_count !== undefined && patch.unread_count !== null ? 1 : null,
        ],
    );

    const [rows] = await pool.execute(
        'SELECT * FROM wa_chats WHERE user_id_erp = ? AND jid = ? LIMIT 1',
        [userId, jid],
    );

    if (patch.lid_jid && patch.phone_jid) {
        await contactService.linkJidPair(userId, patch.lid_jid, patch.phone_jid);
    } else if (patch.lid_jid && jid.endsWith('@s.whatsapp.net')) {
        await contactService.linkJidPair(userId, patch.lid_jid, jid);
    } else if (patch.phone_jid && jid.endsWith('@lid')) {
        await contactService.linkJidPair(userId, jid, patch.phone_jid);
    }

    if (!isGroup) {
        const resolvedName = await contactService.getContactName(userId, jid);
        if (resolvedName && isGoodContactName(resolvedName, jid)) {
            await chatNameService.updateChatName(userId, jid, resolvedName);
        }
    } else if (session?.sock && (!rows[0]?.name || rows[0].name === jid.split('@')[0])) {
        const subject = await chatNameService.fetchGroupSubject(session.sock, jid);
        if (subject) {
            await chatNameService.updateChatName(userId, jid, subject);
        }
    }

    const [freshRows] = await pool.execute(
        'SELECT * FROM wa_chats WHERE user_id_erp = ? AND jid = ? LIMIT 1',
        [userId, jid],
    );

    return freshRows[0] || rows[0] || null;
}

async function saveMessage(userId, parsedMessage, { incrementUnread = false } = {}) {
    if (!parsedMessage?.jid || !parsedMessage.wa_message_id) return null;

    const lastPreview = await buildLastMessagePreview(userId, parsedMessage);

    const chat = await ensureChat(userId, parsedMessage.jid, {
        last_message: lastPreview,
        last_message_at: parsedMessage.timestamp,
        is_group: parsedMessage.jid.endsWith('@g.us'),
    });

    if (!chat) return null;

    const [existingRows] = await getPool().execute(
        `SELECT m.status FROM wa_messages m
         WHERE m.chat_id = ? AND m.wa_message_id = ?
         LIMIT 1`,
        [chat.id, parsedMessage.wa_message_id],
    );
    const isNewMessage = existingRows.length === 0;
    const messageStatus = pickStatus(
        existingRows[0]?.status,
        parsedMessage.status || (parsedMessage.from_me ? 'pending' : 'delivered'),
    );

    await getPool().execute(
        `INSERT INTO wa_messages (
            chat_id, wa_message_id, from_me, sender_jid, sender_push_name, type, content,
            reply_to_wa_message_id, quoted_text, quoted_sender_jid, quoted_sender_name, mentions,
            is_forwarded, is_edited, edited_at,
            media_path, media_mime, media_filename, raw_message,
            timestamp, timestamp_ms, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            content = CASE
                WHEN VALUES(content) IS NOT NULL AND CHAR_LENGTH(TRIM(VALUES(content))) > 0
                THEN VALUES(content)
                ELSE content
            END,
            type = CASE
                WHEN VALUES(content) IS NOT NULL AND CHAR_LENGTH(TRIM(VALUES(content))) > 0
                THEN VALUES(type)
                ELSE type
            END,
            sender_push_name = COALESCE(VALUES(sender_push_name), sender_push_name),
            reply_to_wa_message_id = COALESCE(VALUES(reply_to_wa_message_id), reply_to_wa_message_id),
            quoted_text = COALESCE(VALUES(quoted_text), quoted_text),
            quoted_sender_jid = COALESCE(VALUES(quoted_sender_jid), quoted_sender_jid),
            quoted_sender_name = COALESCE(VALUES(quoted_sender_name), quoted_sender_name),
            mentions = COALESCE(VALUES(mentions), mentions),
            is_forwarded = GREATEST(is_forwarded, VALUES(is_forwarded)),
            is_edited = GREATEST(is_edited, VALUES(is_edited)),
            edited_at = COALESCE(VALUES(edited_at), edited_at),
            media_path = COALESCE(VALUES(media_path), media_path),
            media_mime = COALESCE(VALUES(media_mime), media_mime),
            media_filename = COALESCE(VALUES(media_filename), media_filename),
            raw_message = COALESCE(VALUES(raw_message), raw_message),
            status = CASE
                WHEN FIELD(VALUES(status), 'failed','pending','sent','delivered','read')
                    >= FIELD(status, 'failed','pending','sent','delivered','read')
                THEN VALUES(status)
                ELSE status
            END,
            timestamp_ms = CASE
                WHEN VALUES(timestamp_ms) IS NULL THEN timestamp_ms
                WHEN timestamp_ms IS NULL THEN VALUES(timestamp_ms)
                WHEN VALUES(timestamp_ms) <= timestamp_ms THEN VALUES(timestamp_ms)
                ELSE timestamp_ms
            END,
            timestamp = CASE
                WHEN VALUES(timestamp_ms) IS NULL THEN timestamp
                WHEN timestamp_ms IS NULL THEN VALUES(timestamp)
                WHEN VALUES(timestamp_ms) <= timestamp_ms THEN VALUES(timestamp)
                ELSE timestamp
            END`,
        [
            chat.id,
            parsedMessage.wa_message_id,
            parsedMessage.from_me ? 1 : 0,
            parsedMessage.sender_jid,
            parsedMessage.sender_push_name || null,
            parsedMessage.type,
            parsedMessage.content,
            parsedMessage.reply_to_wa_message_id || null,
            parsedMessage.quoted_text || null,
            parsedMessage.quoted_sender_jid || null,
            parsedMessage.quoted_sender_name || null,
            serializeMentions(parsedMessage.mentions),
            parsedMessage.is_forwarded ? 1 : 0,
            parsedMessage.is_edited ? 1 : 0,
            parsedMessage.edited_at ? toMysqlDatetime(parsedMessage.edited_at) : null,
            parsedMessage.media_path || null,
            parsedMessage.media_mime || null,
            parsedMessage.media_filename || null,
            parsedMessage.raw_message || null,
            toMysqlDatetime(parsedMessage.timestamp),
            parsedMessage.timestamp_ms || toTimestampMs(parsedMessage.timestamp),
            messageStatus,
        ],
    );

    const [rows] = await getPool().execute(
        `SELECT m.*, c.jid, c.name AS chat_name
         FROM wa_messages m
         JOIN wa_chats c ON c.id = m.chat_id
         WHERE c.user_id_erp = ? AND c.jid = ? AND m.wa_message_id = ?
         LIMIT 1`,
        [userId, parsedMessage.jid, parsedMessage.wa_message_id],
    );

    if (incrementUnread && !parsedMessage.from_me && isNewMessage) {
        const session = getWaSession(userId);
        const isChatOpen = session?.openJid === parsedMessage.jid;
        if (!isChatOpen) {
            await getPool().execute(
                `UPDATE wa_chats
                 SET unread_count = unread_count + 1, updated_at = NOW()
                 WHERE user_id_erp = ? AND jid = ?`,
                [userId, parsedMessage.jid],
            );
        }
    }

    setChatAnchorFromMessage(userId, parsedMessage);

    return rows[0] || null;
}

async function processMessages(userId, messages = [], io, {
    emit = true,
    downloadMedia = true,
    applyGroupRetention = false,
} = {}) {
    let saved = 0;
    let skippedGroupOld = 0;
    const session = getWaSession(userId);
    const sock = session?.sock;

    for (const raw of messages) {
        const parsed = parseBaileysMessage(raw);
        if (!parsed) continue;

        if (applyGroupRetention && !isGroupMessageRetained(parsed.jid, parsed.timestamp)) {
            skippedGroupOld += 1;
            continue;
        }

        try {
            await contactService.applyMessageContactHints(userId, raw, parsed);
            if (raw?.pushName && parsed.jid?.endsWith('@g.us') && parsed.sender_jid) {
                await contactService.upsertContact(userId, {
                    jid: parsed.sender_jid,
                    name: raw.pushName.trim(),
                });
            }
        } catch (error) {
            console.warn(`[messageService] contact hint skip:`, error.message);
        }

        let mediaPatch = {};
        if (downloadMedia && sock && parsed.type !== 'text') {
            const downloaded = await mediaService.downloadIncoming(sock, raw, userId, parsed.jid);
            mediaPatch = downloaded || mediaService.unavailableMediaPatch();
        }

        let rawMessage = null;
        try {
            const shouldStoreRaw = parsed.type !== 'text'
                || !parsed.content?.trim()
                || parsed.reply_to_wa_message_id
                || parsed.mentions?.length
                || parsed.is_forwarded;
            if (shouldStoreRaw) {
                rawMessage = JSON.stringify(raw);
            }
        } catch {
            rawMessage = null;
        }

        if (parsed.reply_to_wa_message_id) {
            await applyQuotedReplyMeta(userId, parsed, session?.phone);
        }

        const savedMsg = await saveMessage(userId, {
            ...parsed,
            ...mediaPatch,
            raw_message: rawMessage,
        }, {
            incrementUnread: emit && !parsed.from_me,
        });

        if (savedMsg && emit) {
            const formatted = formatMessageRow(savedMsg);
            const [enriched] = await enrichMessagesWithSenders(userId, [formatted]);
            emitMessageNew(io, userId, { message: enriched || formatted });
            emitChatUpdate(io, userId, await getChatByJid(userId, parsed.jid));
        }

        saved += 1;
    }

    if (skippedGroupOld) {
        console.log(`[messageService] skipped ${skippedGroupOld} group messages older than retention window`);
    }

    return saved;
}

async function processChats(userId, chats = []) {
    const { setChatAnchor } = require('./chatAnchorService');
    const { unwrapBaileysTimestamp } = require('../utils/timestampUtils');

    for (const raw of chats) {
        const parsed = parseBaileysChat(raw);
        if (!parsed) continue;

        const lastMsg = raw.messages?.[0] || raw.lastMessage;
        if (lastMsg?.key?.id) {
            const ts = lastMsg.messageTimestamp || parsed.last_message_at;
            setChatAnchor(userId, parsed.jid, lastMsg.key, unwrapBaileysTimestamp(ts));
        }

        await ensureChat(userId, parsed.jid, parsed);
    }
}

function formatMessageRow(row) {
    const hasMedia = row.media_path && !mediaService.isMediaUnavailable(row.media_path);
    const mediaUrl = hasMedia
        ? `/media/${String(row.media_path).replace(/\\/g, '/')}`
        : null;
    const timestampMs = Number(row.timestamp_ms) || toTimestampMs(row.timestamp);

    return {
        id: row.id,
        wa_message_id: row.wa_message_id,
        jid: row.jid,
        chat_name: row.chat_name || null,
        from_me: Boolean(row.from_me),
        sender_jid: row.sender_jid,
        sender_push_name: row.sender_push_name || null,
        sender_name: row.sender_name || null,
        type: row.type,
        content: row.content,
        reply_to_wa_message_id: row.reply_to_wa_message_id || null,
        quoted_text: row.quoted_text || null,
        quoted_sender_jid: row.quoted_sender_jid || null,
        quoted_sender_name: row.quoted_sender_name || null,
        mentions: parseMentionsJson(row.mentions),
        mention_names: row.mention_names || null,
        is_forwarded: Boolean(row.is_forwarded),
        is_edited: Boolean(row.is_edited),
        edited_at: row.edited_at || null,
        media_path: hasMedia ? row.media_path : null,
        media_mime: hasMedia ? row.media_mime || null : null,
        media_filename: hasMedia ? row.media_filename || null : null,
        media_url: mediaUrl,
        timestamp: toApiIsoFromMs(timestampMs),
        timestamp_ms: timestampMs,
        status: row.status,
    };
}

function formatChatRow(row, contactName = null, contactMeta = {}) {
    const isStatus = isStatusJid(row.jid);
    const displayName = isStatus
        ? formatStatusLabel(row.jid)
        : chatNameService.resolveDisplayName(row, contactName, {
            pushName: contactMeta.push_name || null,
        });

    return {
        id: row.id,
        jid: row.jid,
        name: displayName,
        phone: contactMeta.phone || null,
        has_saved_name: contactMeta.has_saved_name ?? true,
        is_manual: Boolean(contactMeta.is_manual),
        avatar_url: row.avatar_url || contactMeta.avatar_url || null,
        is_group: Boolean(row.is_group),
        is_status: isStatus,
        last_message: row.last_message,
        last_message_at: row.last_message_ms
            ? toApiIsoFromMs(row.last_message_ms)
            : (row.last_message_at ? toApiIsoFromMs(toTimestampMs(row.last_message_at)) : null),
        last_message_ms: row.last_message_ms ? Number(row.last_message_ms) : null,
        unread_count: row.unread_count || 0,
        is_pinned: Boolean(row.is_pinned),
        pinned_at: row.pinned_at || null,
    };
}

async function getChatByJid(userId, jid) {
    const [rows] = await getPool().execute(
        `SELECT c.*, ct.name AS contact_name,
                (SELECT MAX(m.timestamp_ms)
                 FROM wa_messages m
                 WHERE m.chat_id = c.id) AS last_message_ms
         FROM wa_chats c
         LEFT JOIN wa_contacts ct ON ct.user_id_erp = c.user_id_erp AND ct.jid = c.jid
         WHERE c.user_id_erp = ? AND c.jid = ?
         LIMIT 1`,
        [userId, jid],
    );
    if (!rows[0]) return null;

    const contactMeta = await contactService.getChatContactMeta(userId, rows[0]);
    const nameIndex = await contactService.buildContactNameIndex(userId);
    const resolvedContact = rows[0].is_group || jid.endsWith('@g.us')
        ? null
        : (contactMeta.contact_name
            || contactService.lookupNameFromIndex(jid, nameIndex)
            || rows[0].contact_name);

    return formatChatRow(rows[0], resolvedContact, contactMeta);
}

async function getChats(userId) {
    const [rows] = await getPool().execute(
        `SELECT c.*, ct.name AS contact_name,
                (SELECT MAX(m.timestamp_ms)
                 FROM wa_messages m
                 WHERE m.chat_id = c.id) AS last_message_ms
         FROM wa_chats c
         LEFT JOIN wa_contacts ct ON ct.user_id_erp = c.user_id_erp AND ct.jid = c.jid
         WHERE c.user_id_erp = ?
         ORDER BY c.is_pinned DESC, c.pinned_at DESC, (c.unread_count > 0) DESC, c.unread_count DESC, c.last_message_at DESC, c.updated_at DESC`,
        [userId],
    );

    const nameIndex = await contactService.buildContactNameIndex(userId);
    const formatted = [];

    for (const row of rows) {
        const contactMeta = row.is_group || row.jid?.endsWith('@g.us')
            ? { phone: null, has_saved_name: true, is_manual: false }
            : await contactService.getChatContactMeta(userId, row);

        let resolvedContact = row.is_group || row.jid?.endsWith('@g.us')
            ? null
            : (contactMeta.contact_name
                || contactService.lookupNameFromIndex(row.jid, nameIndex)
                || row.contact_name);

        let pushName = null;
        if (!row.is_group && !row.jid?.endsWith('@g.us')) {
            if (!resolvedContact || !isGoodContactName(resolvedContact, row.jid)) {
                const liveName = await contactService.resolveLiveContactName(userId, row.jid, { persist: true });
                if (liveName) {
                    resolvedContact = liveName;
                    if (!row.name || isPhoneLikeName(row.name, row.jid) || isWeakContactName(row.name)) {
                        await chatNameService.updateChatName(userId, row.jid, liveName);
                        row.name = liveName;
                    }
                }
            }
            if (!resolvedContact) {
                pushName = contactService.getStoreContactName(userId, row.jid);
            }
        }

        formatted.push(formatChatRow(row, resolvedContact, {
            ...contactMeta,
            push_name: pushName,
        }));
    }

    const deduped = dedupeChatsByPhone(formatted);
    return splitChats(deduped);
}

async function syncChatMedia(userId, jid, io = null, { limit = 25 } = {}) {
    const session = getWaSession(userId);
    const sock = session?.sock;
    if (!sock) {
        return { synced: 0, updated: [], requested: 0 };
    }

    const safeLimit = Math.min(Math.max(parseInt(limit, 10) || 25, 1), 50);
    const [rows] = await getPool().execute(
        `SELECT m.id, m.wa_message_id, m.from_me, m.type, m.timestamp,
                m.raw_message, m.media_path, m.media_mime, m.media_filename, c.jid
         FROM wa_messages m
         JOIN wa_chats c ON c.id = m.chat_id
         WHERE c.user_id_erp = ? AND c.jid = ?
           AND m.type != 'text'
           AND (m.media_path IS NULL OR m.media_path = '')
         ORDER BY m.timestamp DESC
         LIMIT ${safeLimit}`,
        [userId, jid],
    );

    if (!rows.length) {
        return { synced: 0, updated: [], requested: 0 };
    }

    const updated = [];
    const needsFetch = [];

    for (const row of rows) {
        if (!row.raw_message) {
            needsFetch.push(row);
            continue;
        }

        try {
            const raw = JSON.parse(row.raw_message);
            const mediaPatch = await mediaService.downloadIncoming(sock, raw, userId, jid)
                || mediaService.unavailableMediaPatch();

            await getPool().execute(
                `UPDATE wa_messages
                 SET media_path = ?, media_mime = ?, media_filename = ?
                 WHERE id = ?`,
                [mediaPatch.media_path, mediaPatch.media_mime, mediaPatch.media_filename, row.id],
            );

            if (mediaService.isMediaUnavailable(mediaPatch.media_path)) {
                continue;
            }

            const formatted = formatMessageRow({ ...row, ...mediaPatch });
            updated.push(formatted);
            if (io) {
                emitMessageMedia(io, userId, { jid, message: formatted });
            }
        } catch {
            await getPool().execute(
                `UPDATE wa_messages
                 SET media_path = ?, media_mime = NULL, media_filename = NULL
                 WHERE id = ? AND (media_path IS NULL OR media_path = '')`,
                [mediaService.MEDIA_UNAVAILABLE, row.id],
            ).catch(() => {});
        }
    }

    let requested = 0;
    if (needsFetch.length && typeof sock.fetchMessageHistory === 'function' && isMessageHistorySyncEnabled()) {
        const oldest = needsFetch[needsFetch.length - 1];

        try {
            const ts = new Date(oldest.timestamp).getTime();
            await sock.fetchMessageHistory(
                safeLimit,
                {
                    remoteJid: jid,
                    id: oldest.wa_message_id,
                    fromMe: Boolean(oldest.from_me),
                },
                ts,
            );
            requested = needsFetch.length;
            console.log(`[messageService] fetchMessageHistory requested for ${jid} (${needsFetch.length} media pending)`);
        } catch (error) {
            console.warn('[messageService] fetchMessageHistory skip:', error.message);
        }
    }

    if (updated.length) {
        console.log(`[messageService] synced ${updated.length} media for ${jid}`);
    }

    return { synced: updated.length, updated, requested };
}

async function findMessageAnchor(userId, jid) {
    const [rows] = await getPool().execute(
        `SELECT m.wa_message_id, m.from_me, m.timestamp
         FROM wa_messages m
         JOIN wa_chats c ON c.id = m.chat_id
         WHERE c.user_id_erp = ? AND c.jid = ?
         ORDER BY m.timestamp DESC
         LIMIT 1`,
        [userId, jid],
    );
    if (rows[0]) return rows[0];

    const cached = getChatAnchor(userId, jid);
    if (cached) return cached;

    return null;
}

async function requestMessageHistory(userId, jid, anchor, count = 50) {
    if (!isMessageHistorySyncEnabled()) {
        return { requested: false, reason: 'history_sync_disabled' };
    }
    const session = getWaSession(userId);
    const sock = session?.sock;
    if (!sock?.fetchMessageHistory || !anchor?.wa_message_id) {
        return { requested: false, reason: 'not_connected' };
    }

    const safeCount = Math.min(Math.max(parseInt(count, 10) || 50, 1), 50);
    const ts = new Date(anchor.timestamp).getTime();

    await sock.fetchMessageHistory(
        safeCount,
        {
            remoteJid: jid,
            id: anchor.wa_message_id,
            fromMe: Boolean(anchor.from_me),
        },
        ts,
    );

    return { requested: true, count: safeCount };
}

async function bootstrapGroupHistory(userId, jid, { count = 50 } = {}) {
    if (!isMessageHistorySyncEnabled()) {
        return { requested: false, reason: 'history_sync_disabled' };
    }

    if (!isGroupJid(jid)) {
        return syncChatMessages(userId, jid, { count });
    }

    const session = getWaSession(userId);
    if (!session?.sock?.fetchMessageHistory) {
        return { requested: false, reason: 'not_connected' };
    }

    const safeCount = Math.min(Math.max(parseInt(count, 10) || 50, 1), 50);
    const [total] = await getPool().execute(
        `SELECT COUNT(*) AS c
         FROM wa_messages m
         JOIN wa_chats c ON c.id = m.chat_id
         WHERE c.user_id_erp = ? AND c.jid = ?`,
        [userId, jid],
    );

    const messageCount = total[0]?.c || 0;
    if (messageCount >= safeCount) {
        return { requested: false, reason: 'sufficient_messages', count: messageCount };
    }

    const anchor = await findMessageAnchor(userId, jid);
    if (!anchor) {
        return { requested: false, reason: 'no_anchor_message' };
    }

    const result = await requestMessageHistory(userId, jid, anchor, safeCount);
    if (result.requested) {
        console.log(`[messageService] bootstrapGroupHistory ${jid} (${messageCount}/${safeCount} in DB)`);
    }
    return { ...result, bootstrap: true };
}

async function syncChatMessages(userId, jid, { count = 50 } = {}) {
    if (!isMessageHistorySyncEnabled()) {
        return { requested: false, reason: 'history_sync_disabled' };
    }

    const lockKey = `${userId}:${jid}`;
    if (messageSyncLocks.has(lockKey)) {
        return messageSyncLocks.get(lockKey);
    }

    const task = (async () => {
        const session = getWaSession(userId);
        const sock = session?.sock;
        if (!sock?.fetchMessageHistory) {
            return { requested: false, reason: 'not_connected' };
        }

        const safeCount = Math.min(Math.max(parseInt(count, 10) || 50, 1), 50);

        const [rows] = await getPool().execute(
            `SELECT m.wa_message_id, m.from_me, m.timestamp
             FROM wa_messages m
             JOIN wa_chats c ON c.id = m.chat_id
             WHERE c.user_id_erp = ? AND c.jid = ?
             ORDER BY m.timestamp ASC
             LIMIT 1`,
            [userId, jid],
        );

        let oldest = rows[0];
        if (!oldest) {
            oldest = await findMessageAnchor(userId, jid);
        }

        if (!oldest) {
            return { requested: false, reason: 'no_anchor_message' };
        }

        const result = await requestMessageHistory(userId, jid, oldest, safeCount);
        if (result.requested) {
            console.log(`[messageService] fetchMessageHistory ${jid} (+${safeCount} older)`);
        }
        return result;
    })().finally(() => {
        messageSyncLocks.delete(lockKey);
    });

    messageSyncLocks.set(lockKey, task);
    return task;
}

async function backupAndDeleteMessage(userId, key, reason = 'whatsapp_delete', io = null) {
    const remoteJid = key?.remoteJid;
    const waMessageId = key?.id;
    if (!remoteJid || !waMessageId) return null;

    const [rows] = await getPool().execute(
        `SELECT m.*, c.jid, c.user_id_erp
         FROM wa_messages m
         JOIN wa_chats c ON c.id = m.chat_id
         WHERE c.user_id_erp = ? AND c.jid = ? AND m.wa_message_id = ?
         LIMIT 1`,
        [userId, remoteJid, waMessageId],
    );

    const row = rows[0];
    if (!row) return null;

    await getPool().execute(
        `INSERT INTO wa_messages_deleted (
            original_message_id, chat_id, user_id_erp, jid, wa_message_id,
            from_me, sender_jid, type, content,
            media_path, media_mime, media_filename, raw_message,
            message_timestamp, status, delete_reason, original_created_at, deleted_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())`,
        [
            row.id,
            row.chat_id,
            row.user_id_erp,
            row.jid,
            row.wa_message_id,
            row.from_me,
            row.sender_jid,
            row.type,
            row.content,
            row.media_path,
            row.media_mime,
            row.media_filename,
            row.raw_message,
            row.timestamp,
            row.status,
            reason,
            row.created_at,
        ],
    );

    await getPool().execute('DELETE FROM wa_messages WHERE id = ?', [row.id]);

    if (io) {
        emitMessageDeleted(io, userId, {
            jid: remoteJid,
            wa_message_id: waMessageId,
        });
    }

    return row;
}

async function backupAndDeleteChatMessages(userId, jid, reason = 'whatsapp_delete_all', io = null) {
    const [rows] = await getPool().execute(
        `SELECT m.*, c.jid, c.user_id_erp
         FROM wa_messages m
         JOIN wa_chats c ON c.id = m.chat_id
         WHERE c.user_id_erp = ? AND c.jid = ?`,
        [userId, jid],
    );

    if (!rows.length) return 0;

    for (const row of rows) {
        await getPool().execute(
            `INSERT INTO wa_messages_deleted (
                original_message_id, chat_id, user_id_erp, jid, wa_message_id,
                from_me, sender_jid, type, content,
                media_path, media_mime, media_filename, raw_message,
                message_timestamp, status, delete_reason, original_created_at, deleted_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())`,
            [
                row.id,
                row.chat_id,
                row.user_id_erp,
                row.jid,
                row.wa_message_id,
                row.from_me,
                row.sender_jid,
                row.type,
                row.content,
                row.media_path,
                row.media_mime,
                row.media_filename,
                row.raw_message,
                row.timestamp,
                row.status,
                reason,
                row.created_at,
            ],
        );
    }

    await getPool().execute(
        `DELETE m FROM wa_messages m
         JOIN wa_chats c ON c.id = m.chat_id
         WHERE c.user_id_erp = ? AND c.jid = ?`,
        [userId, jid],
    );

    if (io) {
        emitMessageDeleted(io, userId, { jid, all: true });
    }

    return rows.length;
}

function rowToLastMessageForDelete(row, jid) {
    if (row.raw_message) {
        try {
            const raw = JSON.parse(row.raw_message);
            if (raw?.key?.id) {
                const ts = raw.messageTimestamp
                    || Math.floor(toTimestampMs(row.timestamp_ms || row.timestamp) / 1000);
                return {
                    key: raw.key,
                    messageTimestamp: ts,
                    message: raw.message || { conversation: row.content || '' },
                };
            }
        } catch {
            // fallback below
        }
    }

    const ts = Math.floor(toTimestampMs(row.timestamp_ms || row.timestamp) / 1000);
    return {
        key: buildMessageKey({ ...row, jid: row.jid || jid }),
        messageTimestamp: ts,
        message: row.content ? { conversation: row.content } : { conversation: '' },
    };
}

function buildLastMessagesForDelete(msgRows, jid) {
    const MAX = 50;
    const slice = msgRows.slice(-MAX);

    if (!slice.length) {
        return {
            lastMessageTimestamp: Math.floor(Date.now() / 1000),
        };
    }

    return slice.map((row) => rowToLastMessageForDelete(row, jid));
}

async function backupChatToDeleted(chat, messageCount, reason = 'delete_chat') {
    if (!chat?.id) return null;

    await getPool().execute(
        `INSERT INTO wa_chats_deleted (
            original_chat_id, user_id_erp, jid, name, avatar_url,
            is_group, last_message, last_message_at, unread_count,
            is_pinned, pinned_at, message_count, delete_reason,
            original_created_at, deleted_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())`,
        [
            chat.id,
            chat.user_id_erp,
            chat.jid,
            chat.name,
            chat.avatar_url,
            chat.is_group ? 1 : 0,
            chat.last_message,
            chat.last_message_at,
            chat.unread_count || 0,
            chat.is_pinned ? 1 : 0,
            chat.pinned_at,
            messageCount || 0,
            reason,
            chat.created_at,
        ],
    );

    return chat;
}

async function removeChatRow(userId, jid) {
    const [result] = await getPool().execute(
        'DELETE FROM wa_chats WHERE user_id_erp = ? AND jid = ?',
        [userId, jid],
    );
    return result.affectedRows > 0;
}

async function clearOpenChatIfNeeded(userId, jid) {
    const { getSession, patchSession } = require('../baileys/sessionManager');
    const session = getSession(userId);
    if (session?.openJid === jid) {
        patchSession(userId, { openJid: null });
    }
}

async function purgeChatLocally(userId, jid, reason = 'delete_chat', io = null, {
    skipMessageBackup = false,
    messageCount = null,
} = {}) {
    const [chatRows] = await getPool().execute(
        'SELECT * FROM wa_chats WHERE user_id_erp = ? AND jid = ? LIMIT 1',
        [userId, jid],
    );
    const chat = chatRows[0];
    if (!chat) return { ok: false, jid, backedUpMessages: 0 };

    let backedUpMessages = messageCount ?? 0;
    if (!skipMessageBackup) {
        backedUpMessages = await backupAndDeleteChatMessages(userId, jid, reason, io);
    }

    await backupChatToDeleted(chat, backedUpMessages, reason);
    await removeChatRow(userId, jid);
    await clearOpenChatIfNeeded(userId, jid);

    if (io) {
        emitChatDeleted(io, userId, { jid });
    }

    return { ok: true, jid, backedUpMessages };
}

async function deleteChat(userId, jid, io = null) {
    if (isStatusJid(jid)) {
        throw new Error('Status broadcast tidak bisa dihapus');
    }

    const session = await requireWaSession(userId);

    const [chatRows] = await getPool().execute(
        'SELECT * FROM wa_chats WHERE user_id_erp = ? AND jid = ? LIMIT 1',
        [userId, jid],
    );
    const chat = chatRows[0];
    if (!chat) {
        throw new Error('Chat tidak ditemukan');
    }

    const [msgRows] = await getPool().execute(
        `SELECT m.*, c.jid
         FROM wa_messages m
         JOIN wa_chats c ON c.id = m.chat_id
         WHERE c.user_id_erp = ? AND c.jid = ?
         ORDER BY COALESCE(m.timestamp_ms, UNIX_TIMESTAMP(m.timestamp) * 1000) ASC`,
        [userId, jid],
    );

    const lastMessages = buildLastMessagesForDelete(msgRows, jid);

    try {
        await session.sock.chatModify({ delete: true, lastMessages }, jid);
    } catch (error) {
        console.error(`[messageService] deleteChat chatModify failed for ${jid}:`, error.message);
        throw new Error(`Gagal menghapus chat di WhatsApp: ${error.message}`);
    }

    return purgeChatLocally(userId, jid, 'user_delete_chat', io, {
        messageCount: msgRows.length,
    });
}

async function handleMessagesDelete(userId, payload, io = null) {
    if (payload?.all && payload?.jid) {
        const backedUpMessages = await backupAndDeleteChatMessages(
            userId,
            payload.jid,
            'whatsapp_delete_all',
            io,
        );
        await purgeChatLocally(userId, payload.jid, 'whatsapp_delete_all', io, {
            skipMessageBackup: true,
            messageCount: backedUpMessages,
        });
        return backedUpMessages;
    }

    const keys = payload?.keys || [];
    let deleted = 0;
    for (const key of keys) {
        const result = await backupAndDeleteMessage(userId, key, 'whatsapp_delete', io);
        if (result) deleted += 1;
    }
    return deleted;
}

async function getMessages(userId, jid, cursor, limit = 50) {
    const safeLimit = Math.min(Math.max(parseInt(limit, 10) || 50, 1), 200);
    const params = [userId, jid];
    let cursorSql = '';
    const tsExpr = 'COALESCE(m.timestamp_ms, UNIX_TIMESTAMP(m.timestamp) * 1000)';

    if (cursor) {
        const cursorMs = typeof cursor === 'object'
            ? (cursor.timestamp_ms || toTimestampMs(cursor.timestamp))
            : toTimestampMs(cursor);
        const cursorId = typeof cursor === 'object'
            ? (parseInt(cursor.id, 10) || 0)
            : Number.MAX_SAFE_INTEGER;

        cursorSql = ` AND (${tsExpr} < ? OR (${tsExpr} = ? AND m.id < ?))`;
        params.push(cursorMs, cursorMs, cursorId);
    }

    const [rows] = await getPool().execute(
        `SELECT m.*, c.jid, c.name AS chat_name
         FROM wa_messages m
         JOIN wa_chats c ON c.id = m.chat_id
         WHERE c.user_id_erp = ? AND c.jid = ? ${cursorSql}
         ORDER BY ${tsExpr} DESC, m.id DESC
         LIMIT ${safeLimit}`,
        params,
    );

    const reversed = rows.reverse();
    const reparsed = await enrichEmptyContentFromRaw(reversed);
    const enriched = await enrichMessagesWithSenders(userId, reparsed, {
        name: reparsed[0]?.chat_name || null,
    });
    const messages = enriched.map(formatMessageRow);
    const hasMore = await computeMessagesHasMore(userId, jid, reparsed, safeLimit);

    return {
        messages,
        hasMore,
    };
}

function escapeLikePattern(value) {
    return String(value).replace(/[\\%_]/g, (char) => `\\${char}`);
}

async function searchMessages(userId, jid, query, { limit = 30, offset = 0 } = {}) {
    const term = String(query || '').trim();
    if (!term || term.length < 2) {
        return { messages: [], total: 0, query: term };
    }

    const safeLimit = Math.min(Math.max(parseInt(limit, 10) || 30, 1), 100);
    const safeOffset = Math.max(parseInt(offset, 10) || 0, 0);
    const like = `%${escapeLikePattern(term)}%`;
    const tsExpr = 'COALESCE(m.timestamp_ms, UNIX_TIMESTAMP(m.timestamp) * 1000)';

    const [countRows] = await getPool().execute(
        `SELECT COUNT(*) AS total
         FROM wa_messages m
         JOIN wa_chats c ON c.id = m.chat_id
         WHERE c.user_id_erp = ? AND c.jid = ?
           AND m.content IS NOT NULL
           AND m.content LIKE ? ESCAPE '\\\\'`,
        [userId, jid, like],
    );

    const [rows] = await getPool().execute(
        `SELECT m.*, c.jid, c.name AS chat_name
         FROM wa_messages m
         JOIN wa_chats c ON c.id = m.chat_id
         WHERE c.user_id_erp = ? AND c.jid = ?
           AND m.content IS NOT NULL
           AND m.content LIKE ? ESCAPE '\\\\'
         ORDER BY ${tsExpr} DESC, m.id DESC
         LIMIT ${safeLimit} OFFSET ${safeOffset}`,
        [userId, jid, like],
    );

    const reparsed = await enrichEmptyContentFromRaw(rows);
    const enriched = await enrichMessagesWithSenders(userId, reparsed, {
        name: reparsed[0]?.chat_name || null,
    });

    return {
        messages: enriched.map(formatMessageRow),
        total: countRows[0]?.total || 0,
        query: term,
        limit: safeLimit,
        offset: safeOffset,
    };
}

async function computeMessagesHasMore(userId, jid, rows, safeLimit) {
    if (rows.length === safeLimit) return true;
    if (!rows.length) return false;

    const oldest = rows[0];
    if (!oldest) return false;

    const oldestMs = oldest.timestamp_ms || toTimestampMs(oldest.timestamp);
    const oldestId = oldest.id || 0;
    const tsExpr = 'COALESCE(m.timestamp_ms, UNIX_TIMESTAMP(m.timestamp) * 1000)';

    const [olderRows] = await getPool().execute(
        `SELECT 1 AS x
         FROM wa_messages m
         JOIN wa_chats c ON c.id = m.chat_id
         WHERE c.user_id_erp = ? AND c.jid = ?
           AND (${tsExpr} < ? OR (${tsExpr} = ? AND m.id < ?))
         LIMIT 1`,
        [userId, jid, oldestMs, oldestMs, oldestId],
    );

    if (olderRows.length) return true;

    if (isGroupJid(jid) && isMessageHistorySyncEnabled()) {
        return Boolean(await findMessageAnchor(userId, jid));
    }

    return false;
}

async function getMessageRow(userId, jid, waMessageId) {
    if (!waMessageId || String(waMessageId).startsWith('opt-')) {
        return null;
    }

    const [rows] = await getPool().execute(
        `SELECT m.*, c.jid
         FROM wa_messages m
         JOIN wa_chats c ON c.id = m.chat_id
         WHERE c.user_id_erp = ? AND m.wa_message_id = ?
         ORDER BY CASE WHEN c.jid = ? THEN 0 ELSE 1 END, m.id DESC
         LIMIT 1`,
        [userId, waMessageId, jid],
    );

    return rows[0] || null;
}

async function formatSavedMessage(userId, row) {
    if (!row) return null;
    const formatted = formatMessageRow(row);
    const [enriched] = await enrichMessagesWithSenders(userId, [formatted]);
    return enriched || formatted;
}

async function sendText(userId, jid, text, options = {}) {
    const session = await requireWaSession(userId);
    await ensureChat(userId, jid, {});

    const { replyTo, mentions = [] } = options;
    const payload = { text };
    if (mentions.length) {
        payload.mentions = mentions;
    }

    const sendOptions = {};
    if (replyTo) {
        const quotedRow = await getMessageRow(userId, jid, replyTo);
        if (quotedRow) {
            sendOptions.quoted = buildQuotedFromRow(quotedRow);
        }
    }

    const sent = await session.sock.sendMessage(jid, payload, sendOptions);
    const parsed = parseBaileysMessage(sent);
    if (parsed) {
        let rawMessage = null;
        try {
            rawMessage = JSON.stringify(sent);
        } catch {
            rawMessage = null;
        }

        if (replyTo) {
            const quotedRow = await getMessageRow(userId, jid, replyTo);
            if (quotedRow) {
                parsed.reply_to_wa_message_id = replyTo;
                parsed.quoted_text = quotedRow.content || `[${quotedRow.type}]`;
                if (quotedRow.from_me) {
                    parsed.quoted_sender_jid = null;
                    parsed.quoted_sender_name = 'Anda';
                } else {
                    parsed.quoted_sender_jid = quotedRow.sender_jid || jid;
                    const index = await contactService.buildContactNameIndex(userId);
                    const chatName = await contactService.getContactName(userId, jid);
                    parsed.quoted_sender_name = resolveQuotedSenderDisplayName(
                        parsed,
                        index,
                        { chatJid: jid, chatName },
                    );
                }
            }
        }
        if (mentions.length) {
            parsed.mentions = mentions;
        }

        const saved = await saveMessage(userId, {
            ...parsed,
            status: 'pending',
            raw_message: rawMessage,
        });
        if (saved) {
            return formatSavedMessage(userId, saved);
        }
    }

    return {
        wa_message_id: sent?.key?.id,
        jid,
        from_me: true,
        type: 'text',
        content: text,
        mentions,
        reply_to_wa_message_id: replyTo || null,
        timestamp: new Date().toISOString(),
        timestamp_ms: Date.now(),
        status: 'pending',
    };
}

async function sendMediaAlbum(userId, jid, files = [], { caption = '' } = {}) {
    const session = await requireWaSession(userId);
    await ensureChat(userId, jid, {});

    if (!Array.isArray(files) || files.length < 2) {
        throw new Error('Album minimal 2 media');
    }

    const sentMessages = await sendWhatsAppAlbum(session.sock, jid, files, caption);
    const albumId = `album-${Date.now()}`;
    const baseMs = Date.now();
    const formatted = [];

    for (let index = 0; index < files.length; index += 1) {
        const file = files[index];
        const sent = sentMessages[index];
        const isLast = index === files.length - 1;
        const fileCaption = isLast ? (caption || '') : '';
        const mime = file.mimetype || 'application/octet-stream';
        const filename = file.originalname || 'file';

        let parsed = parseBaileysMessage(sent);
        if (!parsed && sent?.key?.id) {
            const now = new Date(baseMs + index);
            parsed = {
                wa_message_id: sent.key.id,
                jid: sent.key.remoteJid || jid,
                from_me: 1,
                type: inferMediaTypeFromMime(mime, filename),
                content: fileCaption || filename,
                timestamp: now,
                timestamp_ms: now.getTime(),
                status: 'pending',
            };
        }

        const stored = mediaService.saveOutgoingBuffer(userId, jid, file.buffer, { mime, filename });

        if (parsed) {
            const saved = await saveMessage(userId, {
                ...parsed,
                ...stored,
                content: fileCaption || parsed.content,
                status: 'pending',
            });
            if (saved) {
                const message = await formatSavedMessage(userId, saved);
                if (message) {
                    formatted.push({ ...message, album_id: albumId });
                    continue;
                }
            }
        }

        formatted.push({
            jid,
            from_me: true,
            wa_message_id: sent?.key?.id || null,
            type: inferMediaTypeFromMime(mime, filename),
            content: fileCaption || filename,
            ...stored,
            timestamp: new Date(baseMs + index).toISOString(),
            timestamp_ms: baseMs + index,
            status: 'pending',
            album_id: albumId,
        });
    }

    return formatted;
}

async function sendMedia(userId, jid, file, { caption = '', replyTo = null } = {}) {
    const session = await requireWaSession(userId);
    await ensureChat(userId, jid, {});

    if (!file?.buffer?.length) {
        throw new Error('File tidak valid');
    }

    const mime = file.mimetype || 'application/octet-stream';
    const filename = file.originalname || 'file';
    let payload;

    if (mime.startsWith('image/')) {
        payload = { image: file.buffer, mimetype: mime, caption };
    } else if (mime.startsWith('video/')) {
        payload = { video: file.buffer, mimetype: mime, caption };
    } else if (mime.startsWith('audio/')) {
        payload = { audio: file.buffer, mimetype: mime, ptt: false };
    } else {
        payload = { document: file.buffer, mimetype: mime, fileName: filename, caption };
    }

    const sendOptions = {};
    if (replyTo) {
        const quotedRow = await getMessageRow(userId, jid, replyTo);
        if (quotedRow) {
            sendOptions.quoted = buildQuotedFromRow(quotedRow);
        }
    }

    const sent = await session.sock.sendMessage(jid, payload, sendOptions);
    let parsed = parseBaileysMessage(sent);
    if (!parsed && sent?.key?.id) {
        const now = new Date();
        parsed = {
            wa_message_id: sent.key.id,
            jid: sent.key.remoteJid || jid,
            from_me: 1,
            type: inferMediaTypeFromMime(mime, filename),
            content: caption || filename,
            timestamp: now,
            timestamp_ms: now.getTime(),
            status: 'pending',
        };
    }

    const stored = mediaService.saveOutgoingBuffer(userId, jid, file.buffer, { mime, filename });

    if (parsed) {
        if (replyTo) {
            const quotedRow = await getMessageRow(userId, jid, replyTo);
            if (quotedRow) {
                parsed.reply_to_wa_message_id = replyTo;
                parsed.quoted_text = quotedRow.content || `[${quotedRow.type}]`;
                if (quotedRow.from_me) {
                    parsed.quoted_sender_jid = null;
                    parsed.quoted_sender_name = 'Anda';
                } else {
                    parsed.quoted_sender_jid = quotedRow.sender_jid || jid;
                    const index = await contactService.buildContactNameIndex(userId);
                    const chatName = await contactService.getContactName(userId, jid);
                    parsed.quoted_sender_name = resolveQuotedSenderDisplayName(
                        parsed,
                        index,
                        { chatJid: jid, chatName },
                    );
                }
            }
        }

        const saved = await saveMessage(userId, {
            ...parsed,
            ...stored,
            content: caption || parsed.content,
            status: 'pending',
        });
        if (saved) {
            return formatSavedMessage(userId, saved);
        }
    }

    return {
        jid,
        from_me: true,
        wa_message_id: sent?.key?.id || null,
        type: inferMediaTypeFromMime(mime, filename),
        content: caption || filename,
        ...stored,
        timestamp: new Date().toISOString(),
        timestamp_ms: Date.now(),
        status: 'pending',
    };
}

async function editMessage(userId, jid, waMessageId, newText, io = null) {
    const session = await requireWaSession(userId);

    const row = await getMessageRow(userId, jid, waMessageId);
    if (!row) {
        throw new Error('Pesan tidak ditemukan');
    }
    if (!row.from_me) {
        throw new Error('Hanya pesan sendiri yang bisa diedit');
    }
    if (row.type !== 'text') {
        throw new Error('Hanya pesan teks yang bisa diedit');
    }

    const targetJid = row.jid || jid;
    const key = buildMessageKey(row);
    const sent = await session.sock.sendMessage(targetJid, {
        text: newText,
        edit: key,
    });

    const editedAt = new Date();
    await getPool().execute(
        `UPDATE wa_messages m
         JOIN wa_chats c ON c.id = m.chat_id
         SET m.content = ?, m.is_edited = 1, m.edited_at = ?
         WHERE c.user_id_erp = ? AND m.wa_message_id = ?`,
        [newText, toMysqlDatetime(editedAt), userId, waMessageId],
    );

    const updated = await getMessageRow(userId, targetJid, waMessageId);
    const formatted = await formatSavedMessage(userId, updated);
    if (formatted && io) {
        emitMessageEdited(io, userId, { jid: targetJid, message: formatted });
        emitChatUpdate(io, userId, await getChatByJid(userId, targetJid));
    }

    return formatted || {
        wa_message_id: sent?.key?.id || waMessageId,
        jid: targetJid,
        content: newText,
        is_edited: true,
        edited_at: editedAt.toISOString(),
    };
}

async function deleteMessage(userId, jid, waMessageId, { forEveryone = true } = {}, io = null) {
    const session = await requireWaSession(userId);

    const row = await getMessageRow(userId, jid, waMessageId);
    if (!row) {
        throw new Error('Pesan tidak ditemukan');
    }

    const targetJid = row.jid || jid;
    const key = buildMessageKey(row);

    if (forEveryone) {
        if (!row.from_me) {
            throw new Error('Hanya pesan sendiri yang bisa dihapus untuk semua');
        }
        await session.sock.sendMessage(targetJid, { delete: key });
    } else {
        const ts = Math.floor(new Date(row.timestamp).getTime() / 1000);
        await session.sock.chatModify({
            deleteForMe: {
                deleteMedia: Boolean(row.media_path),
                key,
                timestamp: ts,
            },
        }, targetJid);
    }

    await backupAndDeleteMessage(userId, key, forEveryone ? 'delete_for_everyone' : 'delete_for_me', io);
    if (io) {
        emitChatUpdate(io, userId, await getChatByJid(userId, targetJid));
    }

    return { ok: true, wa_message_id: waMessageId };
}

async function forwardMessage(userId, fromJid, waMessageId, toJid, io = null) {
    const session = getWaSession(userId);
    if (!session?.sock) {
        throw new Error('WhatsApp belum terhubung');
    }

    const row = await getMessageRow(userId, fromJid, waMessageId);
    if (!row) {
        throw new Error('Pesan sumber tidak ditemukan');
    }

    await ensureChat(userId, toJid, {});
    const forwardPayload = buildForwardPayload(row);
    const sent = await session.sock.sendMessage(toJid, {
        forward: forwardPayload,
        forceForward: true,
    });

    const parsed = parseBaileysMessage(sent);
    if (parsed) {
        let rawMessage = null;
        try {
            rawMessage = JSON.stringify(sent);
        } catch {
            rawMessage = null;
        }

        const saved = await saveMessage(userId, {
            ...parsed,
            is_forwarded: 1,
            status: 'pending',
            raw_message: rawMessage,
        });
        const formatted = await formatSavedMessage(userId, saved);
        if (formatted && io) {
            emitMessageNew(io, userId, { message: formatted });
            emitChatUpdate(io, userId, await getChatByJid(userId, toJid));
        }
        return formatted;
    }

    return {
        wa_message_id: sent?.key?.id,
        jid: toJid,
        from_me: true,
        is_forwarded: true,
        timestamp: new Date().toISOString(),
        status: 'pending',
    };
}

async function getGroupParticipants(userId, jid) {
    if (!jid?.endsWith('@g.us')) {
        return [];
    }

    const session = getWaSession(userId);
    if (!session?.sock) {
        throw new Error('WhatsApp belum terhubung');
    }

    const meta = await session.sock.groupMetadata(jid);
    const index = await contactService.buildContactNameIndex(userId);

    return (meta.participants || []).map((participant) => {
        const participantJid = participant.id || participant.jid;
        const contactName = contactService.lookupNameFromIndex(participantJid, index);
        const bare = stripJid(participantJid);
        const pushName = participant.notify || participant.name || null;
        const resolvedName = contactName || pushName;
        return {
            jid: participantJid,
            phone: bare,
            name: resolvedName && resolvedName !== bare ? resolvedName : (pushName || contactName || bare),
            admin: participant.admin || null,
        };
    }).sort((a, b) => (a.name || '').localeCompare(b.name || '', 'id'));
}

async function applyIncomingMessageContent(userId, key, updatePayload, io = null) {
    const { parseMessageContentUpdate } = require('../utils/messageParser');
    const parsed = parseMessageContentUpdate(key, updatePayload);
    if (!parsed?.content?.trim()) return null;

    const jid = key.remoteJid;
    let rawMessage = null;
    try {
        rawMessage = JSON.stringify({ key, message: updatePayload.message, messageTimestamp: updatePayload.messageTimestamp });
    } catch {
        rawMessage = null;
    }

    const saved = await saveMessage(userId, {
        ...parsed,
        jid,
        wa_message_id: key.id,
        raw_message: rawMessage,
    });

    if (!saved) return null;

    const formatted = await formatSavedMessage(userId, saved);
    if (formatted && io) {
        emitMessageEdited(io, userId, { jid, message: formatted });
        emitChatUpdate(io, userId, await getChatByJid(userId, jid));
    }

    return formatted;
}

async function enrichEmptyContentFromRaw(rows = []) {
    const patched = [];

    for (const row of rows) {
        if (row.content?.trim() || !row.raw_message) {
            patched.push(row);
            continue;
        }

        try {
            const raw = JSON.parse(row.raw_message);
            const reparsed = parseBaileysMessage(raw);
            if (reparsed?.content?.trim()) {
                patched.push({
                    ...row,
                    content: reparsed.content,
                    type: reparsed.type,
                });
                getPool().execute(
                    `UPDATE wa_messages SET content = ?, type = ? WHERE id = ?`,
                    [reparsed.content, reparsed.type, row.id],
                ).catch(() => {});
                continue;
            }
        } catch {
            // ignore
        }

        patched.push(row);
    }

    return patched;
}

async function applyIncomingEdit(userId, editPayload, io = null) {
    if (!editPayload?.wa_message_id || !editPayload.content) return null;

    await getPool().execute(
        `UPDATE wa_messages m
         JOIN wa_chats c ON c.id = m.chat_id
         SET m.content = ?, m.is_edited = 1, m.edited_at = ?
         WHERE c.user_id_erp = ? AND m.wa_message_id = ?`,
        [
            editPayload.content,
            toMysqlDatetime(editPayload.edited_at || new Date()),
            userId,
            editPayload.wa_message_id,
        ],
    );

    const [rows] = await getPool().execute(
        `SELECT m.*, c.jid
         FROM wa_messages m
         JOIN wa_chats c ON c.id = m.chat_id
         WHERE c.user_id_erp = ? AND m.wa_message_id = ?
         LIMIT 1`,
        [userId, editPayload.wa_message_id],
    );

    const formatted = await formatSavedMessage(userId, rows[0]);
    if (formatted && io) {
        emitMessageEdited(io, userId, { jid: formatted.jid, message: formatted });
    }
    return formatted;
}

async function markRead(userId, jid, io = null) {
    const { patchSession } = require('../baileys/sessionManager');
    patchSession(userId, { openJid: jid });

    const presenceService = require('./presenceService');
    presenceService.subscribePresence(userId, jid).catch(() => {});

    await getPool().execute(
        'UPDATE wa_chats SET unread_count = 0, updated_at = NOW() WHERE user_id_erp = ? AND jid = ?',
        [userId, jid],
    );

    await getPool().execute(
        `UPDATE wa_messages m
         JOIN wa_chats c ON c.id = m.chat_id
         SET m.status = 'read'
         WHERE c.user_id_erp = ? AND c.jid = ? AND m.from_me = 0
           AND m.status IN ('delivered', 'sent', 'pending')`,
        [userId, jid],
    );

    const session = getWaSession(userId);
    if (session?.sock) {
        try {
            const [rows] = await getPool().execute(
                `SELECT m.wa_message_id, m.sender_jid
                 FROM wa_messages m
                 JOIN wa_chats c ON c.id = m.chat_id
                 WHERE c.user_id_erp = ? AND c.jid = ? AND m.from_me = 0
                 ORDER BY m.timestamp DESC
                 LIMIT 100`,
                [userId, jid],
            );

            const isGroup = jid.endsWith('@g.us');
            const keys = rows.map((row) => {
                const key = {
                    remoteJid: jid,
                    id: row.wa_message_id,
                    fromMe: false,
                };
                if (isGroup && row.sender_jid && row.sender_jid !== jid) {
                    key.participant = row.sender_jid;
                }
                return key;
            });

            if (keys.length) {
                await session.sock.readMessages(keys);
            }
        } catch (error) {
            console.warn(`[messageService] readMessages failed for ${jid}:`, error.message);
        }
    }

    if (io) {
        const chat = await getChatByJid(userId, jid);
        if (chat) {
            emitChatUpdate(io, userId, chat);
        }
    }

    return { ok: true };
}

function clearOpenChat(userId) {
    const { patchSession } = require('../baileys/sessionManager');
    patchSession(userId, { openJid: null });
    return { ok: true };
}

async function setChatPinned(userId, jid, pinned, io = null) {
    await ensureChat(userId, jid, {});
    await getPool().execute(
        `UPDATE wa_chats
         SET is_pinned = ?, pinned_at = ?, updated_at = NOW()
         WHERE user_id_erp = ? AND jid = ?`,
        [pinned ? 1 : 0, pinned ? toMysqlDatetime(new Date()) : null, userId, jid],
    );

    const chat = await getChatByJid(userId, jid);
    if (chat && io) {
        emitChatUpdate(io, userId, chat);
    }
    return chat;
}

async function seedGroupAnchorsFromHistory(userId, rawMessages = []) {
    const newestByJid = new Map();

    for (const raw of rawMessages) {
        const jid = raw.key?.remoteJid;
        if (!isGroupJid(jid)) continue;

        const parsed = parseBaileysMessage(raw);
        if (!parsed) continue;

        const existing = newestByJid.get(jid);
        if (!existing || parsed.timestamp > existing.timestamp) {
            newestByJid.set(jid, { raw, parsed });
        }
    }

    for (const [jid, { raw, parsed }] of newestByJid) {
        setChatAnchorFromMessage(userId, parsed);

        const [rows] = await getPool().execute(
            `SELECT COUNT(*) AS c
             FROM wa_messages m
             JOIN wa_chats c ON c.id = m.chat_id
             WHERE c.user_id_erp = ? AND c.jid = ?`,
            [userId, jid],
        );

        if ((rows[0]?.c || 0) > 0) continue;

        let rawMessage = null;
        try {
            rawMessage = JSON.stringify(raw);
        } catch {
            rawMessage = null;
        }

        await saveMessage(userId, {
            ...parsed,
            raw_message: rawMessage,
        }, { incrementUnread: false });
        console.log(`[messageService] seeded group anchor for ${jid}`);
    }
}

async function syncAndEmitChats(userId, io) {
    const { chats, statusChats } = await getChats(userId);
    emitChatsSync(io, userId, { chats, statusChats });
    return { chats, statusChats };
}

async function startChat(userId, { phone, jid } = {}, io = null) {
    const session = await requireWaSession(userId);

    let targetJid = resolvePhoneOrJid({ phone, jid });
    if (!targetJid) {
        throw new Error('Nomor telepon tidak valid');
    }

    if (targetJid.endsWith('@g.us') || targetJid.endsWith('@broadcast')) {
        throw new Error('Gunakan daftar chat untuk membuka grup atau status');
    }

    if (targetJid.endsWith('@s.whatsapp.net') && typeof session.sock.onWhatsApp === 'function') {
        const bare = stripJid(targetJid);
        try {
            const results = await session.sock.onWhatsApp([bare]);
            const match = Array.isArray(results) ? results[0] : null;
            if (match && match.exists === false) {
                throw new Error('Nomor tidak terdaftar di WhatsApp');
            }
            if (match?.jid) {
                targetJid = match.jid;
            }
        } catch (error) {
            if (String(error.message || '').includes('tidak terdaftar')) {
                throw error;
            }
            console.warn('[messageService] onWhatsApp check skipped:', error.message);
        }
    }

    await ensureChat(userId, targetJid, {});
    chatNameService.enrichSingleChatName(userId, targetJid, io).catch(() => {});
    const chat = await getChatByJid(userId, targetJid);

    if (chat && io) {
        emitChatUpdate(io, userId, chat);
    }

    return { jid: targetJid, chat };
}

module.exports = {
    ensureChat,
    saveMessage,
    processMessages,
    processChats,
    getChats,
    getMessages,
    searchMessages,
    syncChatMedia,
    syncChatMessages,
    bootstrapGroupHistory,
    backupAndDeleteMessage,
    backupAndDeleteChatMessages,
    deleteChat,
    purgeChatLocally,
    handleMessagesDelete,
    sendText,
    sendMedia,
    sendMediaAlbum,
    editMessage,
    deleteMessage,
    forwardMessage,
    getGroupParticipants,
    applyIncomingMessageContent,
    applyIncomingEdit,
    getMessageRow,
    markRead,
    clearOpenChat,
    setChatPinned,
    seedGroupAnchorsFromHistory,
    syncAndEmitChats,
    getChatByJid,
    formatMessageRow,
    formatChatRow,
    formatSavedMessage,
    startChat,
};
