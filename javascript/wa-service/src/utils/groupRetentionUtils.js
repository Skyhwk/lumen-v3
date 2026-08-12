const { loadEnv } = require('../config/env');
const { unwrapBaileysTimestamp } = require('./timestampUtils');

function getGroupMessageRetentionDays() {
    const { groupMessageRetentionDays } = loadEnv();
    return Math.max(1, groupMessageRetentionDays || 3);
}

function getGroupMessageCutoff() {
    const cutoff = new Date();
    cutoff.setUTCHours(0, 0, 0, 0);
    cutoff.setUTCDate(cutoff.getUTCDate() - getGroupMessageRetentionDays());
    return cutoff;
}

function isGroupJid(jid) {
    return Boolean(jid?.endsWith('@g.us'));
}

function normalizeTimestamp(timestamp) {
    if (timestamp instanceof Date) {
        return Number.isNaN(timestamp.getTime()) ? new Date() : timestamp;
    }
    return unwrapBaileysTimestamp(timestamp);
}

function isGroupMessageRetained(jid, timestamp) {
    if (!isGroupJid(jid)) return true;

    const ts = normalizeTimestamp(timestamp);
    if (Number.isNaN(ts.getTime()) || ts.getFullYear() < 2020) {
        return true;
    }

    return ts >= getGroupMessageCutoff();
}

module.exports = {
    getGroupMessageRetentionDays,
    getGroupMessageCutoff,
    isGroupJid,
    isGroupMessageRetained,
    normalizeTimestamp,
};
