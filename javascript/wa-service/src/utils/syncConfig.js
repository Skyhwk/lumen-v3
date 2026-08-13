const { loadEnv } = require('../config/env');

function parseEnvFlag(value, defaultValue = false) {
    if (value == null || value === '') return defaultValue;
    return ['1', 'true', 'yes', 'on'].includes(String(value).trim().toLowerCase());
}

function isMessageHistorySyncEnabled() {
    const { enableMessageHistorySync } = loadEnv();
    return enableMessageHistorySync;
}

function isAutoDownloadMediaEnabled() {
    const { autoDownloadMedia } = loadEnv();
    return autoDownloadMedia;
}

module.exports = {
    parseEnvFlag,
    isMessageHistorySyncEnabled,
    isAutoDownloadMediaEnabled,
};
