function stripJid(jid) {
    if (!jid) return '';
    return String(jid).split('@')[0] || '';
}

function isLidJid(jid) {
    return Boolean(jid && String(jid).endsWith('@lid'));
}

function isBareNumericId(value) {
    return Boolean(value && /^\d{10,}$/.test(String(value).trim()));
}

function isPhoneLikeName(name, jid) {
    if (!name || !String(name).trim()) return true;

    const value = String(name).trim();
    const bare = stripJid(jid);
    if (!bare) return /^\d[\d\-]{7,}$/.test(value.replace(/\s/g, ''));

    if (value === bare) return true;
    if (value.replace(/\D/g, '') === bare.replace(/\D/g, '')) return true;

    const digitsOnly = value.replace(/\D/g, '');
    const bareDigits = bare.replace(/\D/g, '');
    if (digitsOnly && bareDigits && digitsOnly === bareDigits) return true;

    return /^[\d+\-\s]{8,}$/.test(value) && digitsOnly.length >= 8;
}

function isWeakContactName(name) {
    if (!name || !String(name).trim()) return true;

    const value = String(name).trim();
    const lower = value.toLowerCase();

    if (lower === 'whatsapp' || lower === '~whatsapp') return true;
    if (lower.startsWith('~')) return true;
    if (/^whatsapp[\s\d]*$/i.test(value)) return true;
    if (lower === 'user' || lower === 'kontak' || lower === 'contact') return true;

    return false;
}

function isGoodContactName(name, jid = null) {
    if (!name?.trim() || isWeakContactName(name)) return false;

    const trimmed = name.trim();
    const bare = jid ? stripJid(jid) : null;

    if (bare && trimmed === bare) return false;
    if (jid && isLidJid(jid) && isBareNumericId(trimmed)) return false;
    if (jid && isPhoneLikeName(trimmed, jid)) return false;

    return true;
}

function pickBetterGroupName(current, incoming, jid = null) {
    const next = incoming?.trim();
    if (!next || isWeakContactName(next)) return current?.trim() || null;

    const prev = current?.trim() || null;
    if (!prev || isWeakContactName(prev)) return next;
    if (isPhoneLikeName(prev, jid) || prev === stripJid(jid)) return next;
    if (prev === next) return prev;

    // Subjek grup biasanya lebih panjang dari nama orang — jangan timpa dengan nama pendek.
    if (next.length >= prev.length) return next;
    return prev;
}

function pickBetterName(current, incoming, jid = null) {
    const next = incoming?.trim();
    if (!next || isWeakContactName(next)) return current?.trim() || null;

    const prev = current?.trim() || null;
    if (!prev || isWeakContactName(prev)) return next;
    if (prev === next) return prev;

    const prevIsPhone = jid ? isPhoneLikeName(prev, jid) : isPhoneLikeName(prev);
    const nextIsPhone = jid ? isPhoneLikeName(next, jid) : isPhoneLikeName(next);

    if (prevIsPhone && !nextIsPhone) return next;
    if (!prevIsPhone && nextIsPhone) return prev;
    if (!prevIsPhone && !nextIsPhone) return next.length >= prev.length ? next : prev;

    return next.length > prev.length ? next : prev;
}

function formatPhoneLabel(jid) {
    const bare = stripJid(jid);
    if (!bare) return jid || 'Kontak';
    if (bare.startsWith('62') && bare.length >= 10) {
        return `+${bare}`;
    }
    return bare;
}

function formatChatFallbackName(jid, phone = null) {
    if (!jid) return 'Kontak';
    if (jid.endsWith('@g.us')) {
        return `Grup ${stripJid(jid).slice(-6)}`;
    }
    if (phone && !isBareNumericId(phone)) {
        return formatPhoneLabel(`${phone}@s.whatsapp.net`);
    }
    if (isLidJid(jid)) {
        return 'Kontak';
    }
    return formatPhoneLabel(jid);
}

module.exports = {
    stripJid,
    isLidJid,
    isBareNumericId,
    isPhoneLikeName,
    isWeakContactName,
    isGoodContactName,
    pickBetterGroupName,
    pickBetterName,
    formatPhoneLabel,
    formatChatFallbackName,
};
