const anchors = new Map();

function anchorKey(userId, jid) {
    return `${userId}:${jid}`;
}

function setChatAnchor(userId, jid, key, timestamp) {
    if (!userId || !jid || !key?.id) return;

    const ts = timestamp instanceof Date ? timestamp : new Date(timestamp || Date.now());
    anchors.set(anchorKey(userId, jid), {
        wa_message_id: key.id,
        from_me: key.fromMe ? 1 : 0,
        timestamp: ts,
    });
}

function getChatAnchor(userId, jid) {
    return anchors.get(anchorKey(userId, jid)) || null;
}

function setChatAnchorFromMessage(userId, parsedMessage) {
    if (!parsedMessage?.jid || !parsedMessage?.wa_message_id) return;
    setChatAnchor(
        userId,
        parsedMessage.jid,
        {
            id: parsedMessage.wa_message_id,
            fromMe: Boolean(parsedMessage.from_me),
            remoteJid: parsedMessage.jid,
        },
        parsedMessage.timestamp,
    );
}

module.exports = {
    setChatAnchor,
    getChatAnchor,
    setChatAnchorFromMessage,
};
