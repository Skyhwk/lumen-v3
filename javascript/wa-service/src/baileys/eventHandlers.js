const { DisconnectReason } = require('@whiskeysockets/baileys');
const {
    toDataUrl,
    emitQr,
    emitStatus,
    emitConnected,
    emitDisconnected,
    emitMessageUpdate,
} = require('./qrHandler');
const sessionService = require('../services/sessionService');
const messageService = require('../services/messageService');
const contactService = require('../services/contactService');
const avatarService = require('../services/avatarService');
const { processHistorySync } = require('../services/syncService');
const { isMessageHistorySyncEnabled } = require('../utils/syncConfig');

function registerBaileysEventHandlers(sock, userId, io, { saveCreds, onReconnect, patchSession, getSession }) {
    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('messaging-history.set', async (payload) => {
        try {
            await processHistorySync(userId, payload, io, {
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
            const chatNameService = require('../services/chatNameService');
            const { emitContactsSync } = require('./qrHandler');
            const named = contacts.filter((c) => c?.name?.trim()).length;
            console.log(`[baileys] contacts.upsert user ${userId}: ${contacts.length} total, ${named} with phonebook name`);
            await contactService.upsertContacts(userId, contacts, { fromPhonebook: true, allowPushName: false });
            await chatNameService.syncContactNamesToChats(userId);
            await messageService.syncAndEmitChats(userId, io);
            const list = await contactService.getContacts(userId);
            emitContactsSync(io, userId, list);

            const jids = contacts.map((c) => c.id || c.jid).filter(Boolean);
            if (jids.length) {
                avatarService.syncAvatarsInBackground(userId, sock, jids.slice(0, 25), io)
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
            const chatNameService = require('../services/chatNameService');
            const { emitContactsSync } = require('./qrHandler');
            await contactService.upsertContacts(userId, contacts, { fromPhonebook: false, allowPushName: true });
            await chatNameService.syncContactNamesToChats(userId);
            await messageService.syncAndEmitChats(userId, io);
            const list = await contactService.getContacts(userId);
            emitContactsSync(io, userId, list);

            const avatarJids = contacts
                .filter((c) => c?.id && (c.imgUrl !== undefined || c.notify))
                .map((c) => c.id);
            if (avatarJids.length) {
                avatarService.syncAvatarsForJids(userId, sock, avatarJids, { force: true, concurrency: 2 })
                    .then(async () => {
                        await messageService.syncAndEmitChats(userId, io);
                        const refreshed = await contactService.getContacts(userId);
                        emitContactsSync(io, userId, refreshed);
                    })
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
                await messageService.syncAndEmitChats(userId, io);
            }
        } catch (error) {
            console.error(`[baileys] chats.upsert failed:`, error.message);
        }
    });

    sock.ev.on('chats.update', async (updates) => {
        try {
            await messageService.processChats(userId, updates);
            await messageService.syncAndEmitChats(userId, io);
        } catch (error) {
            console.error(`[baileys] chats.update failed:`, error.message);
        }
    });

    sock.ev.on('chats.delete', async (jids) => {
        try {
            for (const jid of jids || []) {
                if (!jid) continue;
                await messageService.purgeChatLocally(userId, jid, 'whatsapp_delete_chat', io);
            }
        } catch (error) {
            console.error(`[baileys] chats.delete failed:`, error.message);
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
            await messageService.syncAndEmitChats(userId, io);
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
                await messageService.syncAndEmitChats(userId, io);
            }
        } catch (error) {
            console.error(`[baileys] groups.update failed:`, error.message);
        }
    });

    sock.ev.on('chats.phoneNumberShare', async ({ lid, jid: phoneJid }) => {
        try {
            const contactService = require('../services/contactService');
            const chatNameService = require('../services/chatNameService');
            if (!lid || !phoneJid) return;

            await contactService.linkJidPair(userId, lid, phoneJid);
            await chatNameService.syncContactNamesToChats(userId);
            await chatNameService.enrichPrivateChatNames(userId, sock);
            await messageService.syncAndEmitChats(userId, io);
        } catch (error) {
            console.error(`[baileys] chats.phoneNumberShare failed:`, error.message);
        }
    });

    sock.ev.on('messages.upsert', async ({ messages, type }) => {
        if (!messages?.length) return;
        try {
            const shouldEmit = type === 'notify' || type === 'append';
            await messageService.processMessages(userId, messages, io, {
                emit: shouldEmit,
            });
            if (shouldEmit) {
                await messageService.syncAndEmitChats(userId, io);
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
                        io,
                    );
                    continue;
                }

                const editPayload = parseEditedMessageUpdate(update.update);
                if (editPayload) {
                    await messageService.applyIncomingEdit(userId, editPayload, io);
                    continue;
                }

                if (update.update?.message && parseMessageContentUpdate(update.key, update.update)) {
                    const applied = await messageService.applyIncomingMessageContent(
                        userId,
                        update.key,
                        update.update,
                        io,
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

                emitMessageUpdate(io, userId, {
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
            const deleted = await messageService.handleMessagesDelete(userId, payload, io);
            if (deleted) {
                await messageService.syncAndEmitChats(userId, io);
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
                emitQr(io, userId, qrDataUrl);
                emitStatus(io, userId, 'qr');
                await sessionService.upsertSession(userId, { status: 'qr' });
            } catch (error) {
                console.error(`[baileys] QR encode failed for user ${userId}:`, error.message);
            }
        }

        if (connection === 'open') {
            const rawId = sock.user?.id || '';
            const phone = rawId.split(':')[0]?.split('@')[0] || null;

            patchSession(userId, { status: 'connected', phone, qr: null });
            emitConnected(io, userId, phone);
            emitStatus(io, userId, 'connected', {
                phone,
                syncing: isMessageHistorySyncEnabled(),
            });
            await sessionService.upsertSession(userId, {
                status: 'connected',
                phone_number: phone,
                last_connected_at: new Date(),
            });

            console.log(`[baileys] user ${userId} connected as ${phone || rawId}`);

            setTimeout(async () => {
                try {
                    await messageService.syncAndEmitChats(userId, io);
                    const chatNameService = require('../services/chatNameService');
                    await chatNameService.enrichAllChatNames(userId, io);

                    const session = getSession();
                    const synced = await contactService.syncContactsFromDevice(
                        userId,
                        session?.sock,
                        session?.contactStore,
                    );
                    if (synced) {
                        await chatNameService.syncContactNamesToChats(userId);
                        const { emitContactsSync } = require('./qrHandler');
                        const contacts = await contactService.getContacts(userId);
                        emitContactsSync(io, userId, contacts);
                        console.log(`[baileys] user ${userId} contacts synced from store (${synced})`);
                    }

                    if (!isMessageHistorySyncEnabled()) {
                        emitStatus(io, userId, 'connected', { syncing: false, syncProgress: null });
                    }
                } catch (error) {
                    console.error(`[baileys] initial chat sync failed:`, error.message);
                }
            }, 2000);
        }

        if (connection === 'close') {
            const statusCode = lastDisconnect?.error?.output?.statusCode;
            const loggedOut = statusCode === DisconnectReason.loggedOut;
            const current = getSession() || {};
            const keepPhone = loggedOut ? null : current.phone;

            patchSession(userId, {
                status: loggedOut ? 'disconnected' : 'connecting',
                sock: null,
                qr: null,
                phone: keepPhone,
            });

            if (loggedOut) {
                emitDisconnected(io, userId, 'logged_out');
                emitStatus(io, userId, 'disconnected', { phone: null });
                await sessionService.upsertSession(userId, {
                    status: 'disconnected',
                    phone_number: null,
                });
                console.log(`[baileys] user ${userId} logged out`);
                return;
            }

            const reason = lastDisconnect?.error?.message || 'connection_closed';
            emitDisconnected(io, userId, reason);
            emitStatus(io, userId, 'connecting');
            await sessionService.upsertSession(userId, { status: 'connecting' });
            console.log(`[baileys] user ${userId} disconnected (${reason}), reconnecting...`);

            setTimeout(() => {
                onReconnect().catch((err) => {
                    console.error(`[baileys] reconnect failed for user ${userId}:`, err.message);
                });
            }, 3000);
        }
    });
}

module.exports = { registerBaileysEventHandlers };
