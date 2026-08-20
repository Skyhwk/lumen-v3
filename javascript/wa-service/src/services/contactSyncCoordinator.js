const EMIT_DEBOUNCE_MS = 2500;
const CHAT_REFRESH_DEBOUNCE_MS = 3500;
const NAME_SYNC_DEBOUNCE_MS = 4000;
const DEVICE_SYNC_COOLDOWN_MS = 15 * 60 * 1000;

const emitTimers = new Map();
const chatRefreshTimers = new Map();
const nameSyncTimers = new Map();
const deviceSyncAt = new Map();

function clearTimer(map, userId) {
    const key = String(userId);
    const timer = map.get(key);
    if (timer) {
        clearTimeout(timer);
        map.delete(key);
    }
}

function scheduleContactsEmit(userId) {
    const key = String(userId);
    clearTimer(emitTimers, key);

    emitTimers.set(key, setTimeout(async () => {
        emitTimers.delete(key);
        try {
            const contactService = require('./contactService');
            const { emitContactsSync } = require('../baileys/qrHandler');
            const contacts = await contactService.getContacts(userId);
            emitContactsSync(userId, contacts);
        } catch (error) {
            console.warn(`[contactSync] emit contacts failed for ${userId}:`, error.message);
        }
    }, EMIT_DEBOUNCE_MS));
}

function scheduleChatListRefresh(userId) {
    const key = String(userId);
    clearTimer(chatRefreshTimers, key);

    chatRefreshTimers.set(key, setTimeout(async () => {
        chatRefreshTimers.delete(key);
        try {
            const messageService = require('./messageService');
            await messageService.syncAndEmitChats(userId);
        } catch (error) {
            console.warn(`[contactSync] chat refresh failed for ${userId}:`, error.message);
        }
    }, CHAT_REFRESH_DEBOUNCE_MS));
}

function scheduleContactNamesSync(userId) {
    const key = String(userId);
    clearTimer(nameSyncTimers, key);

    nameSyncTimers.set(key, setTimeout(async () => {
        nameSyncTimers.delete(key);
        try {
            const chatNameService = require('./chatNameService');
            const updated = await chatNameService.syncContactNamesToChats(userId);
            if (updated > 0) {
                scheduleChatListRefresh(userId);
            }
        } catch (error) {
            console.warn(`[contactSync] contact names sync failed for ${userId}:`, error.message);
        }
    }, NAME_SYNC_DEBOUNCE_MS));
}

function markDeviceContactSynced(userId) {
    deviceSyncAt.set(String(userId), Date.now());
}

function shouldSyncDeviceContacts(userId) {
    const last = deviceSyncAt.get(String(userId)) || 0;
    return Date.now() - last >= DEVICE_SYNC_COOLDOWN_MS;
}

function resetDeviceContactSync(userId) {
    deviceSyncAt.delete(String(userId));
}

async function syncDeviceContactsIfDue(userId, sock, contactStore) {
    if (!shouldSyncDeviceContacts(userId)) {
        return { synced: 0, skipped: true };
    }

    const contactService = require('./contactService');
    const synced = await contactService.syncContactsFromDevice(userId, sock, contactStore);
    if (synced > 0) {
        markDeviceContactSynced(userId);
        scheduleContactNamesSync(userId);
        scheduleContactsEmit(userId);
        console.log(`[contactSync] user ${userId} device contacts synced (${synced})`);
    } else {
        markDeviceContactSynced(userId);
    }

    return { synced, skipped: false };
}

async function syncDeviceContactsForced(userId, sock, contactStore) {
    resetDeviceContactSync(userId);
    const contactService = require('./contactService');
    const synced = await contactService.syncContactsFromDevice(userId, sock, contactStore);
    markDeviceContactSynced(userId);

    if (synced > 0) {
        scheduleContactNamesSync(userId);
    }
    scheduleContactsEmit(userId);
    scheduleChatListRefresh(userId);

    return synced;
}

function afterContactsMutation(userId, { refreshChats = true } = {}) {
    scheduleContactNamesSync(userId);
    scheduleContactsEmit(userId);
    if (refreshChats) {
        scheduleChatListRefresh(userId);
    }
}

module.exports = {
    scheduleContactsEmit,
    scheduleChatListRefresh,
    scheduleContactNamesSync,
    syncDeviceContactsIfDue,
    syncDeviceContactsForced,
    afterContactsMutation,
    resetDeviceContactSync,
    shouldSyncDeviceContacts,
};
