function buildMessageKey(row) {
    const key = {
        remoteJid: row.jid,
        id: row.wa_message_id,
        fromMe: Boolean(row.from_me),
    };

    if (row.sender_jid && row.sender_jid !== row.jid) {
        key.participant = row.sender_jid;
    }

    return key;
}

function buildQuotedFromRow(row) {
    if (row.raw_message) {
        try {
            const raw = JSON.parse(row.raw_message);
            if (raw?.key && raw?.message) return raw;
        } catch {
            // fallback below
        }
    }

    const key = buildMessageKey(row);
    let message = {};

    if (row.type === 'text') {
        const text = row.content || '';
        message = text.includes('\n') || /[*_~`]/.test(text)
            ? { extendedTextMessage: { text } }
            : { conversation: text };
    } else {
        message = { conversation: row.content || `[${row.type}]` };
    }

    return { key, message };
}

function buildForwardPayload(row) {
    if (row.raw_message) {
        try {
            const raw = JSON.parse(row.raw_message);
            if (raw?.key && raw?.message) return raw;
        } catch {
            // fallback below
        }
    }

    return buildQuotedFromRow(row);
}

function parseMentionsJson(value) {
    if (!value) return [];
    if (Array.isArray(value)) return value;
    try {
        const parsed = JSON.parse(value);
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

function serializeMentions(mentions = []) {
    if (!mentions?.length) return null;
    return JSON.stringify(mentions);
}

module.exports = {
    buildMessageKey,
    buildQuotedFromRow,
    buildForwardPayload,
    parseMentionsJson,
    serializeMentions,
};
