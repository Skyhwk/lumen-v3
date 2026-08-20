const { DisconnectReason } = require('@whiskeysockets/baileys');
const {
    toDataUrl,
    emitQr,
    emitStatus,
    emitConnected,
    emitDisconnected,
    emitMessageUpdate,
} = require('./qrHandler');
const { closeSocket } = require('./socketUtils');
const sessionService = require('../services/sessionService');
const messageService = require('../services/messageService');
const contactService = require('../services/contactService');
const avatarService = require('../services/avatarService');
const { processHistorySync, kickoffConnectSync, buildSyncProgress } = require('../services/syncService');

function registerBaileysEventHandlers(sock, userId, {
    saveCreds,
    onReconnect,
    patchSession,
    getSession,
    scheduleReconnect,
    isSessionRestarting,
}) {
    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('messaging-history.set', async (payload) => {
        try {
            await processHistorySync(userId, payload, {
                isLatest: payload.isLatest,
                progress: payload.progress,
                syncType: payload.syncType,
            });
        } catch (error) {
            console.error(`[baileys] history sync failed for ${userId}:`, error.message);
        }
    });

    sock.ev.on('contacts.upsert', async (contacts) => {
        try {
            const contactSyncCoordinator = require('../services/contactSyncCoordinator');
            const named = contacts.filter((c) => c?.name?.trim()).length;
            console.log(`[baileys] contacts.upsert user ${userId}: ${contacts.length} total, ${named} with phonebook name`);
            await contactService.upsertContacts(userId, contacts, { fromPhonebook: true, allowPushName: false });
            contactSyncCoordinator.afterContactsMutation(userId);

            const jids = contacts.map((c) => c.id || c.jid).filter(Boolean);
            if (jids.length) {
                avatarService.syncAvatarsInBackground(userId, sock, jids.slice(0, 25))
                    .catch((error) => {
                        console.warn(`[baileys] avatar sync after contacts.upsert failed:`, error.message);
                    });
            }
        } catch (error) {
            console.error(`[baileys] contacts.upsert failed:`, error.message);
        }
    });

    sock.ev.on('contacts.update', async (contacts) => {
        try {
            const contactSyncCoordinator = require('../services/contactSyncCoordinator');
            await contactService.upsertContacts(userId, contacts, { fromPhonebook: false, allowPushName: true });
            contactSyncCoordinator.afterContactsMutation(userId, { refreshChats: false });

            const avatarJids = contacts
                .filter((c) => c?.id && (c.imgUrl !== undefined || c.notify))
                .map((c) => c.id);
            if (avatarJids.length) {
                avatarService.syncAvatarsInBackground(userId, sock, avatarJids.slice(0, 15))
                    .catch((error) => {
                        console.warn(`[baileys] avatar sync after contacts.update failed:`, error.message);
                    });
            }
        } catch (error) {
            console.error(`[baileys] contacts.update failed:`, error.message);
        }
    });

    sock.ev.on('chats.upsert', async (chats) => {
        try {
            await messageService.processChats(userId, chats);
            if (getSession()?.status === 'connected') {
                await messageService.syncAndEmitChats(userId);
            }
        } catch (error) {
            console.error(`[baileys] chats.upsert failed:`, error.message);
        }
    });

    sock.ev.on('chats.update', async (updates) => {
        try {
            await messageService.processChats(userId, updates);
            await messageService.syncAndEmitChats(userId);
        } catch (error) {
            console.error(`[baileys] chats.update failed:`, error.message);
        }
    });

    sock.ev.on('chats.delete', async (jids) => {
        try {
            for (const jid of jids || []) {
                if (!jid) continue;
                await messageService.purgeChatLocally(userId, jid, 'whatsapp_delete_chat');
            }
        } catch (error) {
            console.error(`[baileys] chats.delete failed:`, error.message);
        }
    });

    sock.ev.on('presence.update', (update) => {
        try {
            const presenceService = require('../services/presenceService');
            const { emitPresenceUpdate } = require('./qrHandler');
            const parsed = presenceService.parsePresenceUpdate(update);
            if (parsed) {
                emitPresenceUpdate( userId, parsed);
            }
        } catch (error) {
            console.error(`[baileys] presence.update failed:`, error.message);
        }
    });

    sock.ev.on('groups.upsert', async (groups) => {
        try {
            const chatNameService = require('../services/chatNameService');
            for (const group of groups || []) {
                const jid = group.id;
                const subject = group.subject || group.name;
                if (jid && subject) {
                    await chatNameService.updateChatName(userId, jid, subject);
                }
            }
            await messageService.syncAndEmitChats(userId);
        } catch (error) {
            console.error(`[baileys] groups.upsert failed:`, error.message);
        }
    });

    sock.ev.on('groups.update', async (updates) => {
        try {
            const chatNameService = require('../services/chatNameService');
            let changed = false;
            for (const update of updates || []) {
                if (!update.id || !update.subject) continue;
                const updated = await chatNameService.updateChatName(userId, update.id, update.subject);
                if (updated) changed = true;
            }
            if (changed) {
                await messageService.syncAndEmitChats(userId);
            }
        } catch (error) {
            console.error(`[baileys] groups.update failed:`, error.message);
        }
    });

    sock.ev.on('chats.phoneNumberShare', async ({ lid, jid: phoneJid }) => {
        try {
            const contactSyncCoordinator = require('../services/contactSyncCoordinator');
            if (!lid || !phoneJid) return;

            await contactService.linkJidPair(userId, lid, phoneJid);
            contactSyncCoordinator.afterContactsMutation(userId);
        } catch (error) {
            console.error(`[baileys] chats.phoneNumberShare failed:`, error.message);
        }
    });

    sock.ev.on('messages.upsert', async ({ messages, type }) => {
        if (!messages?.length) return;
        try {
            const shouldEmit = type === 'notify' || type === 'append';
            await messageService.processMessages(userId, messages, {
                emit: shouldEmit,
                downloadMedia: false,
            });
            if (shouldEmit) {
                await messageService.syncAndEmitChats(userId);
            }
        } catch (error) {
            console.error(`[baileys] messages.upsert failed:`, error.message);
        }
    });

    sock.ev.on('messages.update', async (updates) => {
        try {
            const pool = require('../db/connection').getPool();
            const { parseEditedMessageUpdate, parseMessageContentUpdate } = require('../utils/messageParser');

            for (const update of updates) {
                if (!update.key?.id) continue;

                if (update.update?.message === null) {
                    await messageService.backupAndDeleteMessage(
                        userId,
                        update.key,
                        'whatsapp_revoke',
                    );
                    continue;
                }

                const editPayload = parseEditedMessageUpdate(update.update);
                if (editPayload) {
                    await messageService.applyIncomingEdit(userId, editPayload);
                    continue;
                }

                if (update.update?.message && parseMessageContentUpdate(update.key, update.update)) {
                    const applied = await messageService.applyIncomingMessageContent(
                        userId,
                        update.key,
                        update.update,
                    );
                    if (applied) continue;
                }

                const { mapBaileysAck, isUpgrade } = require('../utils/messageStatus');
                const status = mapBaileysAck(update.update?.status);
                if (!status) continue;

                const [existingRows] = await pool.execute(
                    `SELECT m.status FROM wa_messages m
                     JOIN wa_chats c ON c.id = m.chat_id
                     WHERE c.user_id_erp = ? AND m.wa_message_id = ?
                     LIMIT 1`,
                    [userId, update.key.id],
                );
                const currentStatus = existingRows[0]?.status;
                if (!isUpgrade(currentStatus, status)) continue;

                await pool.execute(
                    `UPDATE wa_messages m
                     JOIN wa_chats c ON c.id = m.chat_id
                     SET m.status = ?
                     WHERE c.user_id_erp = ? AND m.wa_message_id = ?`,
                    [status, userId, update.key.id],
                );

                emitMessageUpdate( userId, {
                    wa_message_id: update.key.id,
                    jid: update.key.remoteJid,
                    status,
                });
            }
        } catch (error) {
            console.error(`[baileys] messages.update failed:`, error.message);
        }
    });

    sock.ev.on('messages.delete', async (payload) => {
        try {
            const deleted = await messageService.handleMessagesDelete(userId, payload);
            if (deleted) {
                await messageService.syncAndEmitChats(userId);
            }
        } catch (error) {
            console.error(`[baileys] messages.delete failed:`, error.message);
        }
    });

    sock.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            try {
                const qrDataUrl = await toDataUrl(qr);
                patchSession(userId, { status: 'qr', qr: qrDataUrl });
                emitQr( userId, qrDataUrl);
                emitStatus( userId, 'qr');
                await sessionService.upsertSession(userId, { status: 'qr' });
            } catch (error) {
                console.error(`[baileys] QR encode failed for user ${userId}:`, error.message);
            }
        }

        if (connection === 'open') {
            const rawId = sock.user?.id || '';
            const phone = rawId.split(':')[0]?.split('@')[0] || null;

            patchSession(userId, { status: 'connected', phone, qr: null });
            const sessionAfterConnect = getSession() || {};

            if (sessionAfterConnect.initialSyncDone) {
                emitConnected(userId, phone, { syncing: false, syncProgress: null });
                emitStatus(userId, 'connected', {
                    phone,
                    syncing: false,
                    syncProgress: null,
                });
            } else {
                const initialProgress = buildSyncProgress(0, { phase: 'init' });
                emitConnected(userId, phone, { syncing: true, syncProgress: initialProgress });
                emitStatus(userId, 'connected', {
                    phone,
                    syncing: true,
                    syncProgress: initialProgress,
                }, { retain: false });
            }
            await sessionService.upsertSession(userId, {
                status: 'connected',
                phone_number: phone,
                last_connected_at: new Date(),
            });

            console.log(`[baileys] user ${userId} connected as ${phone || rawId}`);

            kickoffConnectSync(userId, getSession());
        }

        if (connection === 'close') {
            const statusCode = lastDisconnect?.error?.output?.statusCode;
            const loggedOut = statusCode === DisconnectReason.loggedOut;
            const restartRequired = statusCode === DisconnectReason.restartRequired;
            const current = getSession() || {};

            // Socket lama sudah diganti startSession — abaikan close event stale
            if (current.sock && current.sock !== sock) {
                return;
            }
            // startSession sedang membuat socket baru — jangan timpa state / jadwalkan reconnect
            if (isSessionRestarting?.() && current.sock !== sock) {
                return;
            }

            const keepPhone = loggedOut ? null : current.phone;

            patchSession(userId, {
                status: loggedOut ? 'disconnected' : 'connecting',
                sock: null,
                qr: null,
                phone: keepPhone,
            });

            if (loggedOut) {
                try {
                    await closeSocket(sock);
                } catch {
                    // ignore
                }
                emitDisconnected( userId, 'logged_out');
                emitStatus( userId, 'disconnected', { phone: null });
                await sessionService.upsertSession(userId, {
                    status: 'disconnected',
                    phone_number: null,
                });
                console.log(`[baileys] user ${userId} logged out (code ${statusCode ?? 'n/a'})`);
                return;
            }

            if (restartRequired) {
                emitStatus( userId, 'connecting', { pairing: true });
                await sessionService.upsertSession(userId, { status: 'connecting' });
                console.log(`[baileys] user ${userId} pairing complete — restart socket (code 515)...`);
                scheduleReconnect?.(() => onReconnect(), 500);
                return;
            }

            const reason = lastDisconnect?.error?.message || 'connection_closed';
            emitDisconnected( userId, reason);
            emitStatus( userId, 'connecting');
            await sessionService.upsertSession(userId, { status: 'connecting' });
            console.log(
                `[baileys] user ${userId} disconnected (${reason}, code ${statusCode ?? 'n/a'}), reconnecting in 3s...`,
            );

            scheduleReconnect?.(() => onReconnect(), 3000);
        }
    });
}

module.exports = { registerBaileysEventHandlers };
