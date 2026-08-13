const { Browsers } = require('@whiskeysockets/baileys');

function resolveWaBrowser(deviceName, platform = 'appropriate') {
    const name = deviceName || 'INTILAB SUPER APPS';
    const key = String(platform || 'appropriate').trim().toLowerCase();

    switch (key) {
        case 'windows':
        case 'win':
            return Browsers.windows(name);
        case 'ubuntu':
        case 'linux':
            return Browsers.ubuntu(name);
        case 'macos':
        case 'mac':
            return Browsers.macOS(name);
        case 'appropriate':
        default:
            return Browsers.appropriate(name);
    }
}

module.exports = { resolveWaBrowser };
