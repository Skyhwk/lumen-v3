const path = require('path');
const http = require('http');
const express = require('express');
const cors = require('cors');
const { loadEnv } = require('./config/env');
const { pingDatabase } = require('./db/connection');
const { createApiRouter } = require('./routes/api');
const { initWaMqtt, getWaMqtt, closeWaMqtt } = require('./mqtt/waMqtt');

async function bootstrap() {
    const config = loadEnv();
    const app = express();

    await initWaMqtt().catch((error) => {
        console.error('[wa-service] MQTT init failed:', error.message);
        throw error;
    });

    app.set('trust proxy', 1);
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
            version: '0.6.0',
            phase: 6,
            realtime: getWaMqtt()?.connected ? 'mqtt' : 'mqtt_offline',
            database: dbOk ? 'connected' : 'not_configured',
        });
    });

    app.use('/api', createApiRouter());

    const server = http.createServer(app);

    server.listen(config.port, () => {
        console.log(`[wa-service] listening on port ${config.port} (${config.nodeEnv})`);
    });

    const shutdown = async () => {
        console.log('[wa-service] shutting down...');
        await closeWaMqtt();
        server.close(() => process.exit(0));
    };

    process.on('SIGINT', shutdown);
    process.on('SIGTERM', shutdown);
}

bootstrap().catch((error) => {
    console.error('[wa-service] failed to start:', error);
    process.exit(1);
});
