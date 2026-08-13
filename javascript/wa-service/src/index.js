const path = require('path');
const http = require('http');
const express = require('express');
const cors = require('cors');
const { Server } = require('socket.io');
const { loadEnv } = require('./config/env');
const { pingDatabase } = require('./db/connection');
const { createApiRouter } = require('./routes/api');
const { initWaSocket } = require('./socket/waSocket');
const { setIo } = require('./baileys/sessionManager');

async function bootstrap() {
    const config = loadEnv();
    const app = express();

    app.use(cors({ origin: config.corsOrigin, credentials: true }));
    app.use(express.json({ limit: '2mb' }));
    app.use('/media', express.static(path.resolve(config.mediaDir)));

    app.get('/health', async (_req, res) => {
        let dbOk = false;
        try {
            if (config.db.database) {
                dbOk = await pingDatabase();
            }
        } catch {
            dbOk = false;
        }

        res.json({
            ok: true,
            service: 'wa-service',
            version: '0.4.0',
            phase: 4,
            database: dbOk ? 'connected' : 'not_configured',
        });
    });

    const server = http.createServer(app);
    const io = new Server(server, {
        cors: {
            origin: config.corsOrigin,
            credentials: true,
        },
    });

    initWaSocket(io);
    setIo(io);
    app.use('/api', createApiRouter(io));

    server.listen(config.port, () => {
        console.log(`[wa-service] listening on port ${config.port} (${config.nodeEnv})`);
    });
}

bootstrap().catch((error) => {
    console.error('[wa-service] failed to start:', error);
    process.exit(1);
});
