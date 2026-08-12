/**
 * Normalisasi messageTimestamp Baileys (number | string | Long protobuf).
 */
function unwrapBaileysTimestamp(value) {
    if (value == null) {
        return new Date();
    }

    let raw = value;
    if (typeof raw === 'object') {
        if (typeof raw.toNumber === 'function') {
            raw = raw.toNumber();
        } else if (typeof raw.low === 'number') {
            raw = raw.low;
        } else {
            raw = Number(raw);
        }
    } else {
        raw = Number(raw);
    }

    if (!Number.isFinite(raw) || raw <= 0) {
        return new Date();
    }

    const ms = raw < 1e12 ? raw * 1000 : raw;
    const date = new Date(ms);

    if (Number.isNaN(date.getTime()) || date.getFullYear() < 2020) {
        return new Date();
    }

    return date;
}

function toMysqlDatetime(value) {
    const date = value instanceof Date ? value : new Date(value || Date.now());
    if (Number.isNaN(date.getTime())) {
        return new Date().toISOString().slice(0, 19).replace('T', ' ');
    }

    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())} `
        + `${pad(date.getUTCHours())}:${pad(date.getUTCMinutes())}:${pad(date.getUTCSeconds())}`;
}

function toTimestampMs(value) {
    const date = value instanceof Date ? value : new Date(value || Date.now());
    const ms = date.getTime();
    return Number.isFinite(ms) ? ms : Date.now();
}

function toApiIso(value) {
    const ms = toTimestampMs(value);
    return new Date(ms).toISOString();
}

function toApiIsoFromMs(ms) {
    const parsed = Number(ms);
    if (!Number.isFinite(parsed) || parsed <= 0) return null;
    return new Date(parsed).toISOString();
}

module.exports = {
    unwrapBaileysTimestamp,
    toMysqlDatetime,
    toTimestampMs,
    toApiIso,
    toApiIsoFromMs,
};
