const express = require('express');
const multer = require('multer');
const { authMiddleware } = require('../auth/lumenAuth');
const {
    ensureSession,
    getSessionStatus,
    logoutSession,
    getSession,
} = require('../baileys/sessionManager');
const { emitContactsSync } = require('../baileys/qrHandler');
const messageService = require('../services/messageService');
const chatNameService = require('../services/chatNameService');
const contactService = require('../services/contactService');
const avatarService = require('../services/avatarService');
const { loadEnv } = require('../config/env');
const { isMessageHistorySyncEnabled } = require('../utils/syncConfig');

function createApiRouter(io) {
    const router = express.Router();
    const upload = multer({
        storage: multer.memoryStorage(),
        limits: { fileSize: 16 * 1024 * 1024 },
    });

    router.get('/wa/status', authMiddleware(), (req, res) => {
        const status = getSessionStatus(req.waUser.id);
        const { enableMessageHistorySync } = loadEnv();
        res.json({
            ok: true,
            userId: req.waUser.id,
            historySyncEnabled: enableMessageHistorySync,
            ...status,
        });
    });

    router.post('/wa/connect', authMiddleware(), async (req, res) => {
        try {
            await ensureSession(req.waUser.id);
            const status = getSessionStatus(req.waUser.id);
            res.json({ ok: true, ...status });
        } catch (error) {
            res.status(500).json({ ok: false, message: error.message });
        }
    });

    router.get('/wa/qr', authMiddleware(), (req, res) => {
        const status = getSessionStatus(req.waUser.id);
        res.json({
            ok: true,
            qr: status.qr || null,
            status: status.status,
        });
    });

    router.post('/wa/logout', authMiddleware(), async (req, res) => {
        await logoutSession(req.waUser.id);
        res.json({ ok: true, message: 'Session cleared' });
    });

    router.get('/wa/chats', authMiddleware(), async (req, res) => {
        try {
            const forceEnrich = req.query.enrich === '1';
            await chatNameService.maybeEnrichChatNames(req.waUser.id, io, { force: forceEnrich });
            const { chats, statusChats } = await messageService.getChats(req.waUser.id);
            res.json({ ok: true, chats, statusChats });
        } catch (error) {
            res.status(500).json({ ok: false, message: error.message });
        }
    });

    router.post('/wa/chats/close', authMiddleware(), async (req, res) => {
        try {
            messageService.clearOpenChat(req.waUser.id);
            res.json({ ok: true });
        } catch (error) {
            res.status(500).json({ ok: false, message: error.message });
        }
    });

    router.post('/wa/chats/start', authMiddleware(), async (req, res) => {
        try {
            const session = getSession(req.waUser.id);
            if (!session?.sock) {
                return res.status(400).json({ ok: false, message: 'WhatsApp belum terhubung' });
            }

            const { phone, jid } = req.body || {};
            if (!phone && !jid) {
                return res.status(400).json({ ok: false, message: 'Nomor atau kontak wajib diisi' });
            }

            const result = await messageService.startChat(req.waUser.id, { phone, jid }, io);
            res.json({ ok: true, ...result });
        } catch (error) {
            const status = String(error.message || '').includes('tidak valid')
                || String(error.message || '').includes('tidak terdaftar')
                ? 400
                : 500;
            res.status(status).json({ ok: false, message: error.message });
        }
    });

    router.get('/wa/contacts', authMiddleware(), async (req, res) => {
        try {
            const contacts = await contactService.getContacts(req.waUser.id, {
                search: req.query.search || '',
            });
            res.json({ ok: true, contacts });
        } catch (error) {
            res.status(500).json({ ok: false, message: error.message });
        }
    });

    router.get('/wa/contacts/:jid', authMiddleware(), async (req, res) => {
        try {
            const jid = decodeURIComponent(req.params.jid);
            const contact = await contactService.getContactDetail(req.waUser.id, jid);
            res.json({ ok: true, contact });
        } catch (error) {
            res.status(500).json({ ok: false, message: error.message });
        }
    });

    router.post('/wa/contacts', authMiddleware(), async (req, res) => {
        try {
            const { jid, name, pushToDevice = true } = req.body || {};
            if (!jid?.trim()) {
                return res.status(400).json({ ok: false, message: 'JID kontak wajib diisi' });
            }
            if (!name?.trim()) {
                return res.status(400).json({ ok: false, message: 'Nama kontak wajib diisi' });
            }

            const session = getSession(req.waUser.id);
            const contact = await contactService.saveContactManual(
                req.waUser.id,
                jid.trim(),
                name.trim(),
                { sock: session?.sock, pushToDevice: Boolean(pushToDevice) },
            );

            const chat = await messageService.getChatByJid(req.waUser.id, jid.trim());
            if (chat) {
                const { emitChatUpdate } = require('../baileys/qrHandler');
                emitChatUpdate(io, req.waUser.id, chat);
            }

            const contacts = await contactService.getContacts(req.waUser.id);
            emitContactsSync(io, req.waUser.id, contacts);

            res.json({ ok: true, contact, chat });
        } catch (error) {
            res.status(500).json({ ok: false, message: error.message });
        }
    });

    router.patch('/wa/contacts/:jid', authMiddleware(), async (req, res) => {
        try {
            const jid = decodeURIComponent(req.params.jid);
            const { name, pushToDevice = true } = req.body || {};
            if (!name?.trim()) {
                return res.status(400).json({ ok: false, message: 'Nama kontak wajib diisi' });
            }

            const session = getSession(req.waUser.id);
            const contact = await contactService.saveContactManual(
                req.waUser.id,
                jid,
                name.trim(),
                { sock: session?.sock, pushToDevice: Boolean(pushToDevice) },
            );

            const chat = await messageService.getChatByJid(req.waUser.id, jid);
            if (chat) {
                const { emitChatUpdate } = require('../baileys/qrHandler');
                emitChatUpdate(io, req.waUser.id, chat);
            }

            const contacts = await contactService.getContacts(req.waUser.id);
            emitContactsSync(io, req.waUser.id, contacts);

            res.json({ ok: true, contact, chat });
        } catch (error) {
            res.status(500).json({ ok: false, message: error.message });
        }
    });

    router.post('/wa/contacts/sync', authMiddleware(), async (req, res) => {
        try {
            const session = getSession(req.waUser.id);
            if (!session?.sock) {
                return res.status(400).json({ ok: false, message: 'WhatsApp belum terhubung' });
            }

            const contactSyncCoordinator = require('../services/contactSyncCoordinator');
            const synced = await contactSyncCoordinator.syncDeviceContactsForced(
                req.waUser.id,
                session.sock,
                session.contactStore,
                io,
            );

            const contacts = await contactService.getContacts(req.waUser.id);
            emitContactsSync(io, req.waUser.id, contacts);

            avatarService.syncAvatarsInBackground(req.waUser.id, session.sock, [], io, { limit: 50 })
                .catch((error) => {
                    console.warn(`[api] avatar sync after contacts/sync failed:`, error.message);
                });

            res.json({ ok: true, synced, contacts });
        } catch (error) {
            res.status(500).json({ ok: false, message: error.message });
        }
    });

    router.post('/wa/avatars/sync', authMiddleware(), async (req, res) => {
        try {
            const session = getSession(req.waUser.id);
            if (!session?.sock) {
                return res.status(400).json({ ok: false, message: 'WhatsApp belum terhubung' });
            }

            const jids = Array.isArray(req.body?.jids)
                ? req.body.jids.filter(Boolean)
                : [];
            const limit = req.body?.limit ? parseInt(req.body.limit, 10) : 50;

            const synced = jids.length
                ? await avatarService.syncAvatarsForJids(req.waUser.id, session.sock, jids, { concurrency: 3 })
                : await avatarService.syncRecentChatAvatars(req.waUser.id, session.sock, { limit });

            await messageService.syncAndEmitChats(req.waUser.id, io);
            const contacts = await contactService.getContacts(req.waUser.id);
            emitContactsSync(io, req.waUser.id, contacts);

            res.json({
                ok: true,
                synced: synced.size,
                jids: [...synced.keys()],
            });
        } catch (error) {
            res.status(500).json({ ok: false, message: error.message });
        }
    });

    router.post('/wa/chats/:jid/open', authMiddleware(), async (req, res) => {
        try {
            const jid = decodeURIComponent(req.params.jid);
            await messageService.ensureChat(req.waUser.id, jid, {});
            chatNameService.enrichSingleChatName(req.waUser.id, jid, io).catch(() => {});
            const chat = await messageService.getChatByJid(req.waUser.id, jid);

            await messageService.markRead(req.waUser.id, jid, io);

            if (isMessageHistorySyncEnabled()) {
                const syncTask = jid.endsWith('@g.us')
                    ? messageService.bootstrapGroupHistory(req.waUser.id, jid, { count: 50 })
                    : messageService.syncChatMessages(req.waUser.id, jid, { count: 50 });

                syncTask.catch((error) => {
                    console.warn(`[api] background message sync failed for ${jid}:`, error.message);
                });
            }

            messageService.syncChatMedia(req.waUser.id, jid, io, { limit: 30 })
                .catch((error) => {
                    console.warn(`[api] background media sync failed for ${jid}:`, error.message);
                });

            const session = getSession(req.waUser.id);
            if (session?.sock) {
                avatarService.syncAvatarForJid(req.waUser.id, session.sock, jid)
                    .then(async (avatarUrl) => {
                        if (!avatarUrl) return;
                        const { emitChatUpdate } = require('../baileys/qrHandler');
                        const refreshed = await messageService.getChatByJid(req.waUser.id, jid);
                        if (refreshed) emitChatUpdate(io, req.waUser.id, refreshed);
                    })
                    .catch((error) => {
                        console.warn(`[api] background avatar sync failed for ${jid}:`, error.message);
                    });
            }

            res.json({ ok: true, chat });
        } catch (error) {
            res.status(500).json({ ok: false, message: error.message });
        }
    });

    router.post('/wa/chats/:jid/sync-messages', authMiddleware(), async (req, res) => {
        try {
            const jid = decodeURIComponent(req.params.jid);
            if (!isMessageHistorySyncEnabled()) {
                return res.json({ ok: true, requested: false, reason: 'history_sync_disabled' });
            }

            const session = getSession(req.waUser.id);
            if (!session?.sock) {
                return res.status(400).json({ ok: false, message: 'WhatsApp belum terhubung' });
            }

            const count = req.body?.count ? parseInt(req.body.count, 10) : 50;
            const syncFn = jid.endsWith('@g.us')
                ? messageService.bootstrapGroupHistory
                : messageService.syncChatMessages;
            const result = await syncFn(req.waUser.id, jid, { count });
            res.json({ ok: true, ...result });
        } catch (error) {
            res.status(500).json({ ok: false, message: error.message });
        }
    });

    router.post('/wa/chats/:jid/sync-media', authMiddleware(), async (req, res) => {
        try {
            const jid = decodeURIComponent(req.params.jid);
            const session = getSession(req.waUser.id);
            if (!session?.sock) {
                return res.status(400).json({ ok: false, message: 'WhatsApp belum terhubung' });
            }

            const limit = req.body?.limit ? parseInt(req.body.limit, 10) : 25;
            const result = await messageService.syncChatMedia(req.waUser.id, jid, io, { limit });
            res.json({
                ok: true,
                synced: result.synced,
                requested: result.requested,
                messages: result.updated,
            });
        } catch (error) {
            res.status(500).json({ ok: false, message: error.message });
        }
    });

    router.get('/wa/chats/:jid/messages', authMiddleware(), async (req, res) => {
        try {
            const jid = decodeURIComponent(req.params.jid);
            const { cursor, cursorMs, cursorId, limit } = req.query;
            const paginationCursor = cursorMs || cursor
                ? {
                    timestamp: cursor || null,
                    timestamp_ms: cursorMs ? parseInt(cursorMs, 10) : null,
                    id: cursorId ? parseInt(cursorId, 10) : null,
                }
                : null;
            const result = await messageService.getMessages(
                req.waUser.id,
                jid,
                paginationCursor,
                limit ? parseInt(limit, 10) : 50,
            );
            res.json({ ok: true, messages: result.messages, hasMore: result.hasMore });
        } catch (error) {
            res.status(500).json({ ok: false, message: error.message });
        }
    });

    router.get('/wa/chats/:jid/messages/search', authMiddleware(), async (req, res) => {
        try {
            const jid = decodeURIComponent(req.params.jid);
            const { q, limit, offset } = req.query;
            const result = await messageService.searchMessages(
                req.waUser.id,
                jid,
                q,
                {
                    limit: limit ? parseInt(limit, 10) : 30,
                    offset: offset ? parseInt(offset, 10) : 0,
                },
            );
            res.json({ ok: true, ...result });
        } catch (error) {
            res.status(500).json({ ok: false, message: error.message });
        }
    });

    router.post('/wa/chats/:jid/send', authMiddleware(), async (req, res) => {
        try {
            const jid = decodeURIComponent(req.params.jid);
            const { text, replyTo, mentions } = req.body || {};
            if (!text?.trim()) {
                return res.status(400).json({ ok: false, message: 'Pesan tidak boleh kosong' });
            }
            const message = await messageService.sendText(req.waUser.id, jid, text.trim(), {
                replyTo: replyTo || null,
                mentions: Array.isArray(mentions) ? mentions : [],
            });
            res.json({ ok: true, message });
        } catch (error) {
            res.status(500).json({ ok: false, message: error.message });
        }
    });

    router.post('/wa/chats/:jid/send-media', authMiddleware(), upload.single('file'), async (req, res) => {
        try {
            const jid = decodeURIComponent(req.params.jid);
            if (!req.file) {
                return res.status(400).json({ ok: false, message: 'File wajib diupload' });
            }
            const message = await messageService.sendMedia(req.waUser.id, jid, req.file, {
                caption: req.body?.caption || '',
                replyTo: req.body?.replyTo || null,
            });
            res.json({ ok: true, message });
        } catch (error) {
            res.status(500).json({ ok: false, message: error.message });
        }
    });

    router.post('/wa/chats/:jid/send-media-album', authMiddleware(), upload.array('files', 10), async (req, res) => {
        try {
            const jid = decodeURIComponent(req.params.jid);
            const files = Array.isArray(req.files) ? req.files.filter(Boolean) : [];
            if (files.length < 2) {
                return res.status(400).json({ ok: false, message: 'Album minimal 2 media' });
            }
            const messages = await messageService.sendMediaAlbum(req.waUser.id, jid, files, {
                caption: req.body?.caption || '',
            });
            res.json({ ok: true, messages });
        } catch (error) {
            res.status(500).json({ ok: false, message: error.message });
        }
    });

    router.post('/wa/chats/:jid/read', authMiddleware(), async (req, res) => {
        try {
            const jid = decodeURIComponent(req.params.jid);
            await messageService.markRead(req.waUser.id, jid, io);
            res.json({ ok: true });
        } catch (error) {
            res.status(500).json({ ok: false, message: error.message });
        }
    });

    router.patch('/wa/chats/:jid/pin', authMiddleware(), async (req, res) => {
        try {
            const jid = decodeURIComponent(req.params.jid);
            const pinned = req.body?.pinned !== false;
            const chat = await messageService.setChatPinned(req.waUser.id, jid, pinned, io);
            res.json({ ok: true, chat });
        } catch (error) {
            res.status(500).json({ ok: false, message: error.message });
        }
    });

    router.delete('/wa/chats/:jid', authMiddleware(), async (req, res) => {
        try {
            const jid = decodeURIComponent(req.params.jid);
            const result = await messageService.deleteChat(req.waUser.id, jid, io);
            res.json({ ok: true, ...result });
        } catch (error) {
            res.status(500).json({ ok: false, message: error.message });
        }
    });

    router.get('/wa/chats/:jid/participants', authMiddleware(), async (req, res) => {
        try {
            const jid = decodeURIComponent(req.params.jid);
            const participants = await messageService.getGroupParticipants(req.waUser.id, jid);
            res.json({ ok: true, participants });
        } catch (error) {
            res.status(500).json({ ok: false, message: error.message });
        }
    });

    router.patch('/wa/chats/:jid/messages/:msgId', authMiddleware(), async (req, res) => {
        try {
            const jid = decodeURIComponent(req.params.jid);
            const msgId = decodeURIComponent(req.params.msgId);
            const { text } = req.body || {};
            if (!text?.trim()) {
                return res.status(400).json({ ok: false, message: 'Pesan tidak boleh kosong' });
            }
            const message = await messageService.editMessage(
                req.waUser.id,
                jid,
                msgId,
                text.trim(),
                io,
            );
            res.json({ ok: true, message });
        } catch (error) {
            res.status(500).json({ ok: false, message: error.message });
        }
    });

    router.delete('/wa/chats/:jid/messages/:msgId', authMiddleware(), async (req, res) => {
        try {
            const jid = decodeURIComponent(req.params.jid);
            const msgId = decodeURIComponent(req.params.msgId);
            const forEveryone = req.body?.forEveryone !== false;
            const result = await messageService.deleteMessage(
                req.waUser.id,
                jid,
                msgId,
                { forEveryone },
                io,
            );
            res.json({ ok: true, ...result });
        } catch (error) {
            res.status(500).json({ ok: false, message: error.message });
        }
    });

    router.post('/wa/messages/forward', authMiddleware(), async (req, res) => {
        try {
            const { fromJid, waMessageId, toJid } = req.body || {};
            if (!fromJid || !waMessageId || !toJid) {
                return res.status(400).json({
                    ok: false,
                    message: 'fromJid, waMessageId, dan toJid wajib diisi',
                });
            }
            const message = await messageService.forwardMessage(
                req.waUser.id,
                fromJid,
                waMessageId,
                toJid,
                io,
            );
            res.json({ ok: true, message });
        } catch (error) {
            res.status(500).json({ ok: false, message: error.message });
        }
    });

    return router;
}

module.exports = { createApiRouter };
