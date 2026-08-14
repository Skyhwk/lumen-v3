function compareChats(a, b) {
    const pinA = Boolean(a.is_pinned);
    const pinB = Boolean(b.is_pinned);
    if (pinA && !pinB) return -1;
    if (pinB && !pinA) return 1;
    if (pinA && pinB) {
        const pa = new Date(a.pinned_at || 0).getTime();
        const pb = new Date(b.pinned_at || 0).getTime();
        if (pa !== pb) return pb - pa;
    }

    const unreadA = Number(a.unread_count) || 0;
    const unreadB = Number(b.unread_count) || 0;

    if (unreadA > 0 && unreadB === 0) return -1;
    if (unreadB > 0 && unreadA === 0) return 1;
    if (unreadA !== unreadB) return unreadB - unreadA;

    const ta = new Date(a.last_message_at || 0).getTime();
    const tb = new Date(b.last_message_at || 0).getTime();
    return tb - ta;
}

function sortChatsByUnread(chats = []) {
    return [...chats].sort(compareChats);
}

function dedupeChatsByPhone(chats = []) {
    const map = new Map();

    for (const chat of chats) {
        if (chat.is_group || chat.jid?.endsWith('@g.us')) {
            map.set(`g:${chat.jid}`, chat);
            continue;
        }

        const phone = String(chat.phone || '').replace(/\D/g, '')
            || (chat.jid?.endsWith('@s.whatsapp.net') ? chat.jid.split('@')[0] : null);

        const key = phone ? `p:${phone}` : `j:${chat.jid}`;
        const prev = map.get(key);
        if (!prev) {
            map.set(key, chat);
            continue;
        }

        const score = (item) => ({
            pinned: item.is_pinned ? 1 : 0,
            unread: Number(item.unread_count) || 0,
            time: new Date(item.last_message_at || 0).getTime(),
            isPhoneJid: item.jid?.endsWith('@s.whatsapp.net') ? 1 : 0,
        });

        const nextScore = score(chat);
        const prevScore = score(prev);
        const better = nextScore.pinned > prevScore.pinned
            || (nextScore.pinned === prevScore.pinned && nextScore.unread > prevScore.unread)
            || (nextScore.pinned === prevScore.pinned && nextScore.unread === prevScore.unread && nextScore.time > prevScore.time)
            || (
                nextScore.pinned === prevScore.pinned
                && nextScore.unread === prevScore.unread
                && nextScore.time === prevScore.time
                && nextScore.isPhoneJid > prevScore.isPhoneJid
            );

        if (better) {
            map.set(key, chat);
        }
    }

    return sortChatsByUnread([...map.values()]);
}

module.exports = { compareChats, sortChatsByUnread, dedupeChatsByPhone };
