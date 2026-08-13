const { getPool } = require('../db/connection');

async function upsertSession(userId, { status, phone_number, last_connected_at } = {}) {
    try {
        const pool = getPool();
        const phone = phone_number ?? null;
        const connectedAt = last_connected_at ?? (status === 'connected' ? new Date() : null);

        await pool.execute(
            `INSERT INTO wa_sessions (user_id_erp, phone_number, status, last_connected_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                phone_number = COALESCE(VALUES(phone_number), phone_number),
                status = VALUES(status),
                last_connected_at = COALESCE(VALUES(last_connected_at), last_connected_at),
                updated_at = NOW()`,
            [userId, phone, status || 'disconnected', connectedAt],
        );
    } catch (error) {
        console.warn(`[sessionService] DB skip for user ${userId}:`, error.message);
    }
}

async function getSessionRecord(userId) {
    try {
        const pool = getPool();
        const [rows] = await pool.execute(
            'SELECT * FROM wa_sessions WHERE user_id_erp = ? LIMIT 1',
            [userId],
        );
        return rows[0] || null;
    } catch (error) {
        console.warn(`[sessionService] getSessionRecord skip:`, error.message);
        return null;
    }
}

module.exports = { upsertSession, getSessionRecord };
