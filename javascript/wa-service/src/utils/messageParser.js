const { getContentType, normalizeMessageContent } = require('@whiskeysockets/baileys');
const { mapBaileysAck } = require('./messageStatus');
const { isWeakContactName, stripJid } = require('./nameUtils');
const { unwrapBaileysTimestamp } = require('./timestampUtils');

const TYPE_MAP = {
    conversation: 'text',
    extendedTextMessage: 'text',
    imageMessage: 'image',
    videoMessage: 'video',
    documentMessage: 'document',
    audioMessage: 'audio',
    stickerMessage: 'sticker',
    locationMessage: 'location',
    contactMessage: 'contact',
    buttonsMessage: 'text',
    listMessage: 'text',
    listResponseMessage: 'text',
    templateMessage: 'text',
    templateButtonReplyMessage: 'text',
    buttonsResponseMessage: 'text',
    interactiveMessage: 'text',
    interactiveResponseMessage: 'text',
    pollCreationMessage: 'text',
    pollUpdateMessage: 'text',
};

const MEDIA_TYPES = new Set(['image', 'video', 'document', 'audio', 'sticker', 'location', 'contact']);

// proto.WebMessageInfo.StubType — skip atau format pesan sistem
const SKIP_STUB_TYPES = new Set([
    2, // CIPHERTEXT — placeholder, tunggu isi asli dari HP
]);

function mapMessageType(contentType) {
    return TYPE_MAP[contentType] || 'text';
}

function isMediaType(type) {
    return MEDIA_TYPES.has(type);
}

function extractText(normalized, contentType) {
    if (!normalized) return '';

    if (normalized.conversation) return normalized.conversation;
    if (normalized.extendedTextMessage?.text) return normalized.extendedTextMessage.text;

    const node = contentType ? normalized[contentType] : null;
    if (node && typeof node === 'object') {
        if (typeof node.text === 'string' && node.text.trim()) return node.text;
        if (typeof node.contentText === 'string' && node.contentText.trim()) return node.contentText;
        if (typeof node.description === 'string' && node.description.trim()) return node.description;
        if (typeof node.caption === 'string' && node.caption.trim()) return node.caption;
        if (typeof node.buttonText === 'string' && node.buttonText.trim()) return node.buttonText;
        if (typeof node.title === 'string' && node.title.trim()) return node.title;
        if (typeof node.footer === 'string' && node.footer.trim()) return node.footer;
    }

    if (normalized.imageMessage?.caption) return normalized.imageMessage.caption;
    if (normalized.videoMessage?.caption) return normalized.videoMessage.caption;
    if (normalized.documentMessage?.caption) return normalized.documentMessage.caption;
    if (normalized.documentMessage?.fileName) return normalized.documentMessage.fileName;

    if (contentType === 'imageMessage') return '[Gambar]';
    if (contentType === 'videoMessage') return '[Video]';
    if (contentType === 'audioMessage') return '[Audio]';
    if (contentType === 'documentMessage') return '[Dokumen]';
    if (contentType === 'stickerMessage') return '[Stiker]';
    if (contentType === 'locationMessage') return '[Lokasi]';
    if (contentType === 'contactMessage') return '[Kontak]';

    return '';
}

function formatMessageStub(msg) {
    const stubType = msg.messageStubType;
    const params = Array.isArray(msg.messageStubParameters) ? msg.messageStubParameters : [];

    if (stubType == null) return null;

    if (SKIP_STUB_TYPES.has(Number(stubType))) {
        return { skip: true };
    }

    const joined = params.filter(Boolean).join(', ');
    const stubLabels = {
        1: 'Pesan dihapus',
        3: joined ? `Grup dibuat: ${joined}` : 'Grup dibuat',
        4: joined ? `${joined} bergabung` : 'Anggota bergabung',
        5: joined ? `${joined} keluar` : 'Anggota keluar',
        6: joined ? `Admin: ${joined}` : 'Peran admin diubah',
        7: joined ? `Nama grup: ${joined}` : 'Nama grup diubah',
    };

    const label = stubLabels[Number(stubType)];
    if (label) {
        return { skip: false, content: label, type: 'text' };
    }

    if (joined) {
        return { skip: false, content: joined, type: 'text' };
    }

    return { skip: true };
}

function resolveOutgoingStatus(msg) {
    const ack = mapBaileysAck(msg.status);
    if (ack) return ack;

    const ts = unwrapBaileysTimestamp(msg.messageTimestamp).getTime();
    if (ts && Date.now() - ts > 60000) {
        return 'delivered';
    }

    return 'pending';
}

function extractContextInfo(normalized, contentType) {
    if (!normalized) return null;
    const node = normalized[contentType];
    return node?.contextInfo || null;
}

function extractQuotedPreview(contextInfo) {
    if (!contextInfo?.quotedMessage) return null;

    const quoted = normalizeMessageContent(contextInfo.quotedMessage);
    if (!quoted) return { text: '', type: 'text' };

    const qType = getContentType(quoted) || 'conversation';
    const text = extractText(quoted, qType);
    return {
        text: text || `[${mapMessageType(qType)}]`,
        type: mapMessageType(qType),
        participant: contextInfo.participant || null,
        stanzaId: contextInfo.stanzaId || null,
    };
}

function parseBaileysMessage(msg) {
    if (!msg?.key?.remoteJid || !msg.key.id) return null;

    const stub = formatMessageStub(msg);
    if (stub?.skip) return null;

    const normalized = normalizeMessageContent(msg.message);
    const contentType = getContentType(normalized || msg.message) || 'conversation';
    let type = stub?.type || mapMessageType(contentType);
    let content = stub?.content || extractText(normalized, contentType);

    if (!content?.trim() && isMediaType(type)) {
        // Media tanpa caption — tetap simpan
    } else if (!content?.trim() && type === 'text') {
        return null;
    }

    const contextInfo = extractContextInfo(normalized, contentType)
        || msg.message?.protocolMessage?.contextInfo
        || null;
    const quoted = extractQuotedPreview(contextInfo);
    const mentions = Array.isArray(contextInfo?.mentionedJid)
        ? contextInfo.mentionedJid.filter(Boolean)
        : [];
    const isForwarded = Boolean(
        contextInfo?.isForwarded
        || contextInfo?.forwardingScore,
    );

    const ts = msg.messageTimestamp || msg.message?.messageTimestamp;
    const timestamp = unwrapBaileysTimestamp(ts);
    const timestamp_ms = timestamp.getTime();
    const pushName = msg.pushName?.trim() || null;

    return {
        wa_message_id: msg.key.id,
        jid: msg.key.remoteJid,
        from_me: msg.key.fromMe ? 1 : 0,
        sender_jid: msg.key.participant || msg.key.remoteJid,
        sender_push_name: pushName,
        type,
        content,
        timestamp,
        timestamp_ms,
        status: msg.key.fromMe ? resolveOutgoingStatus(msg) : 'delivered',
        reply_to_wa_message_id: quoted?.stanzaId || contextInfo?.stanzaId || null,
        quoted_text: quoted?.text || null,
        quoted_sender_jid: quoted?.participant || contextInfo?.participant || null,
        mentions,
        is_forwarded: isForwarded ? 1 : 0,
        is_edited: 0,
    };
}

function parseEditedMessageUpdate(update) {
    const protocol = update?.message?.protocolMessage;
    if (!protocol?.editedMessage || !protocol.key?.id) return null;

    const normalized = normalizeMessageContent(protocol.editedMessage);
    const contentType = getContentType(normalized) || 'conversation';
    const content = extractText(normalized, contentType);

    return {
        wa_message_id: protocol.key.id,
        content: content || null,
        edited_at: new Date(),
    };
}

function parseMessageContentUpdate(key, updatePayload) {
    if (!key?.id || !updatePayload?.message) return null;

    const protocol = updatePayload.message.protocolMessage;
    if (protocol?.type != null && !protocol?.editedMessage) {
        return null;
    }

    const synthetic = {
        key,
        message: updatePayload.message,
        messageTimestamp: updatePayload.messageTimestamp,
        pushName: updatePayload.pushName,
        messageStubType: updatePayload.messageStubType,
    };

    return parseBaileysMessage(synthetic);
}

function parseBaileysChat(chat) {
    if (!chat?.id) return null;

    const ts = chat.conversationTimestamp || chat.lastMessageRecvTimestamp;
    const lastMessageAt = ts ? unwrapBaileysTimestamp(ts) : null;
    const isGroup = chat.id.endsWith('@g.us');
    const isLid = chat.id.endsWith('@lid');
    const isPhoneUser = chat.id.endsWith('@s.whatsapp.net');

    let rawName = null;
    if (isGroup) {
        rawName = chat.subject || chat.groupSubject || null;
    } else {
        rawName = chat.name || null;
    }
    if (rawName && isWeakContactName(rawName)) {
        rawName = null;
    }

    const lidJid = chat.lidJid || (isLid ? chat.id : null);
    const phoneJid = isPhoneUser ? chat.id : null;

    return {
        jid: chat.id,
        lid_jid: lidJid,
        phone_jid: phoneJid,
        name: rawName,
        is_group: isGroup ? 1 : 0,
        unread_count: typeof chat.unreadCount === 'number' && chat.unreadCount >= 0
            ? chat.unreadCount
            : undefined,
        last_message_at: lastMessageAt,
    };
}

function extractPhonebookName(contact) {
    // Nama dari phonebook HP — field `name` di contacts.upsert (contactAction.fullName)
    if (contact?.name?.trim()) {
        return contact.name.trim();
    }
    const verified = contact?.verifiedName?.trim();
    if (verified && !isWeakContactName(verified)) {
        return verified;
    }
    return null;
}

function extractPushName(contact) {
    const notify = contact?.notify?.trim();
    if (notify && !isWeakContactName(notify)) {
        return notify;
    }
    return null;
}

function extractContactName(contact) {
    return extractPhonebookName(contact) || extractPushName(contact);
}

function resolveContactPhone(contact, jid) {
    const phoneJid = contact.jid || contact.phoneNumber || null;
    if (phoneJid && String(phoneJid).endsWith('@s.whatsapp.net')) {
        return stripJid(phoneJid);
    }
    if (phoneJid && !String(phoneJid).includes('@')) {
        return String(phoneJid).replace(/\D/g, '') || null;
    }
    if (jid.endsWith('@s.whatsapp.net')) {
        return stripJid(jid);
    }
    return null;
}

function parseBaileysContact(contact) {
    if (!contact?.id) return null;

    const jid = contact.id;
    const phone = resolveContactPhone(contact, jid);
    const lidJid = contact.lid || (jid.endsWith('@lid') ? jid : null);
    const phoneJid = contact.jid
        || (phone ? `${phone}@s.whatsapp.net` : null)
        || (jid.endsWith('@s.whatsapp.net') ? jid : null);

    return {
        jid,
        phonebook_name: extractPhonebookName(contact),
        push_name: extractPushName(contact),
        name: extractContactName(contact),
        phone,
        lid_jid: lidJid,
        phone_jid: phoneJid,
    };
}

module.exports = {
    parseBaileysMessage,
    parseBaileysChat,
    parseBaileysContact,
    parseEditedMessageUpdate,
    parseMessageContentUpdate,
    extractContactName,
    extractPhonebookName,
    extractPushName,
    isMediaType,
};
