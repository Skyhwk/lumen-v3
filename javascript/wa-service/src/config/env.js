require('dotenv').config();

const required = ['DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'LUMEN_API_URL'];

function loadEnv() {
    const config = {
        port: parseInt(process.env.PORT || '5010', 10),
        nodeEnv: process.env.NODE_ENV || 'development',
        lumenApiUrl: (process.env.LUMEN_API_URL || '').replace(/\/$/, ''),
        db: {
            host: process.env.DB_HOST || '127.0.0.1',
            port: parseInt(process.env.DB_PORT || '3306', 10),
            database: process.env.DB_DATABASE || '',
            user: process.env.DB_USERNAME || 'root',
            password: process.env.DB_PASSWORD || '',
        },
        sessionsDir: process.env.SESSIONS_DIR || './sessions',
        mediaDir: process.env.MEDIA_DIR || './media',
        corsOrigin: process.env.CORS_ORIGIN || 'http://localhost:3000',
        deviceName: process.env.WA_DEVICE_NAME || 'INTILAB SUPER APPS',
        devicePlatform: (process.env.WA_DEVICE_PLATFORM || 'appropriate').trim().toLowerCase(),
        groupMessageRetentionDays: parseInt(process.env.GROUP_MESSAGE_RETENTION_DAYS || '3', 10),
        avatarTtlHours: parseInt(process.env.WA_AVATAR_TTL_HOURS || '24', 10),
        enableMessageHistorySync: ['1', 'true', 'yes', 'on']
            .includes(String(process.env.WA_ENABLE_MESSAGE_HISTORY_SYNC || '').trim().toLowerCase()),
        autoDownloadMedia: ['1', 'true', 'yes', 'on']
            .includes(String(process.env.WA_AUTO_DOWNLOAD_MEDIA || '').trim().toLowerCase()),
    };

    if (config.nodeEnv === 'production') {
        const missing = required.filter((key) => !process.env[key]);
        if (missing.length) {
            throw new Error(`Missing required env: ${missing.join(', ')}`);
        }
    }

    return config;
}

module.exports = { loadEnv };
