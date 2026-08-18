require('dotenv').config();
const fs = require('fs');
const path = require('path');
const pino = require('pino');
const {
    default: makeWASocket,
    useMultiFileAuthState,
    fetchLatestBaileysVersion,
    DisconnectReason,
} = require('@whiskeysockets/baileys');

const userId = process.argv[2] || '99999';
const sessionDir = path.resolve('./sessions', `minimal-${userId}`);

async function main() {
    fs.mkdirSync(sessionDir, { recursive: true });
    const { state, saveCreds } = await useMultiFileAuthState(sessionDir);
    const { version } = await fetchLatestBaileysVersion();
    console.log('[minimal] baileys version', version);

    let gotQr = false;

    const sock = makeWASocket({
        version,
        auth: state,
        logger: pino({ level: 'warn' }),
        printQRInTerminal: true,
        browser: ['INTILAB TEST', 'Chrome', '120.0.0'],
    });

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', (update) => {
        const { connection, lastDisconnect, qr } = update;
        if (qr) {
            gotQr = true;
            console.log('[minimal] QR received, length=', qr.length);
        }
        if (connection === 'open') {
            console.log('[minimal] CONNECTED', sock.user?.id);
            process.exit(0);
        }
        if (connection === 'close') {
            const code = lastDisconnect?.error?.output?.statusCode;
            console.log('[minimal] CLOSE code=', code, 'reason=', lastDisconnect?.error?.message);
            if (code === DisconnectReason.loggedOut) {
                process.exit(1);
            }
        }
    });

    await new Promise((r) => setTimeout(r, 25000));
    console.log('[minimal] timeout gotQr=', gotQr);
    process.exit(gotQr ? 0 : 1);
}

main().catch((e) => {
    console.error(e);
    process.exit(1);
});
