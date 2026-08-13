const { getSession } = require('../baileys/sessionManager');

async function subscribePresence(userId, jid) {
    const session = getSession(userId);
    if (!session?.sock || !jid) return false;

    try {
        await session.sock.presenceSubscribe(jid);
        return true;
    } catch (error) {
        console.warn(`[presence] subscribe failed for ${jid}:`, error.message);
        return false;
    }
}

async function sendTyping(userId, jid, typing = true) {
    const session = getSession(userId);
    if (!session?.sock || !jid) return false;

    try {
        await session.sock.sendPresenceUpdate(typing ? 'composing' : 'paused', jid);
        return true;
    } catch (error) {
        console.warn(`[presence] sendTyping failed for ${jid}:`, error.message);
        return false;
    }
}

function parsePresenceUpdate({ id, presences } = {}) {
    if (!id || !presences) return null;

    const typingUsers = [];
    for (const [participant, data] of Object.entries(presences)) {
        const presence = data?.lastKnownPresence;
        if (presence === 'composing' || presence === 'recording') {
            typingUsers.push({
                jid: participant,
                type: presence,
            });
        }
    }

    return {
        jid: id,
        typing: typingUsers,
    };
}

module.exports = {
    subscribePresence,
    sendTyping,
    parsePresenceUpdate,
};
