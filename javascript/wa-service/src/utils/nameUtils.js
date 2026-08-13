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

    const phoneDigits = phone ? String(phone).replace(/\D/g, '') : null;
    if (phoneDigits && phoneDigits.startsWith('62') && phoneDigits.length >= 10 && phoneDigits.length <= 15) {
        return formatPhoneLabel(`${phoneDigits}@s.whatsapp.net`);
    }

    if (isLidJid(jid)) {
        return 'Kontak';
    }

    if (jid.endsWith('@s.whatsapp.net')) {
        const bare = stripJid(jid);
        if (bare.startsWith('62') && bare.length >= 10 && bare.length <= 15) {
            return formatPhoneLabel(jid);
        }
    }

    return 'Kontak';
}

function normalizePhoneDigits(raw) {
    if (!raw) return null;
    let digits = String(raw).replace(/\D/g, '');
    if (!digits) return null;

    if (digits.startsWith('0')) {
        digits = `62${digits.slice(1)}`;
    } else if (digits.startsWith('8') && digits.length >= 9 && digits.length <= 13) {
        digits = `62${digits}`;
    }

    if (digits.length < 10 || digits.length > 15) {
        return null;
    }

    return digits;
}

function phoneToJid(phone) {
    const digits = normalizePhoneDigits(phone);
    if (!digits) return null;
    return `${digits}@s.whatsapp.net`;
}

function resolvePhoneOrJid({ phone, jid } = {}) {
    const trimmedJid = jid?.trim();
    if (trimmedJid) {
        if (trimmedJid.includes('@')) return trimmedJid;
        const fromBare = phoneToJid(trimmedJid);
        if (fromBare) return fromBare;
    }

    return phoneToJid(phone);
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
    normalizePhoneDigits,
    phoneToJid,
    resolvePhoneOrJid,
};
