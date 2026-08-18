/**
 * Tes lokal: start session Baileys + tunggu QR (tanpa HTTP auth).
 * Usage: node scripts/test-qr-connect.js [userId]
 */
require('dotenv').config();

const { ensureSession, waitForSessionProgress, getSessionStatus, logoutSession } = require('../src/baileys/sessionManager');
const { initWaMqtt } = require('../src/mqtt/waMqtt');

const userId = process.argv[2] || 'test-qr';

async function main() {
    console.log(`[test] user=${userId} — init MQTT...`);
    await initWaMqtt().catch((err) => {
        console.warn('[test] MQTT optional:', err.message);
    });

    console.log('[test] ensureSession...');
    await ensureSession(userId);

    console.log('[test] waiting up to 25s for QR or connected...');
    const status = await waitForSessionProgress(userId, { timeoutMs: 25000, intervalMs: 200 });
    console.log('[test] result:', {
        status: status.status,
        hasQr: Boolean(status.qr),
        qrLength: status.qr?.length || 0,
        phone: status.phone,
        hasStoredSession: status.hasStoredSession,
    });

    if (status.status === 'qr' || status.qr) {
        console.log('[test] SUCCESS — QR tersedia');
        process.exit(0);
    }

    console.error('[test] FAIL — tidak dapat QR');
    process.exit(1);
}

main().catch((err) => {
    console.error('[test] error:', err);
    process.exit(1);
});

process.on('SIGINT', async () => {
    await logoutSession(userId).catch(() => {});
    process.exit(0);
});
