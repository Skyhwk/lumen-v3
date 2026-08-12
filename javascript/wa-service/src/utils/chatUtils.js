const STATUS_JIDS = new Set([
    'status@broadcast',
]);

function isStatusJid(jid) {
    if (!jid) return false;
    if (STATUS_JIDS.has(jid)) return true;
    return jid.endsWith('@broadcast') && jid.startsWith('status');
}

function splitChats(rows = []) {
    const chats = [];
    const statusChats = [];

    for (const row of rows) {
        if (isStatusJid(row.jid)) {
            statusChats.push(row);
        } else {
            chats.push(row);
        }
    }

    return { chats, statusChats };
}

function formatStatusLabel(jid) {
    if (jid === 'status@broadcast') return 'Status WhatsApp';
    return 'Status';
}

module.exports = {
    isStatusJid,
    splitChats,
    formatStatusLabel,
};
