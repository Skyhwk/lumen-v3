const STATUS_RANK = {
    failed: 0,
    pending: 1,
    sent: 2,
    delivered: 3,
    read: 4,
};

const BAILEYS_ACK_MAP = {
    0: 'failed',
    1: 'pending',
    2: 'sent',
    3: 'delivered',
    4: 'read',
    5: 'read',
};

function mapBaileysAck(ack) {
    if (ack == null) return null;
    return BAILEYS_ACK_MAP[Number(ack)] || null;
}

function pickStatus(current, incoming) {
    const next = incoming || 'pending';
    if (!current) return next;
    const currentRank = STATUS_RANK[current] ?? 0;
    const nextRank = STATUS_RANK[next] ?? 0;
    return nextRank >= currentRank ? next : current;
}

function isUpgrade(current, incoming) {
    if (!incoming) return false;
    if (!current) return true;
    return (STATUS_RANK[incoming] ?? 0) >= (STATUS_RANK[current] ?? 0);
}

module.exports = {
    STATUS_RANK,
    mapBaileysAck,
    pickStatus,
    isUpgrade,
};
