const {
    ensureSession,
    getSessionStatus,
    logoutSession,
} = require('../baileys/sessionManager');
const messageService = require('../services/messageService');

function initWaSocket(io) {
    const { validateToken } = require('../auth/lumenAuth');

    io.use(async (socket, next) => {
        const token = socket.handshake.auth?.token || socket.handshake.headers?.token;
        const result = await validateToken(token);

        if (!result.ok) {
            return next(new Error(result.message));
        }

        socket.waUserId = result.userId;
        socket.waUser = result.user;
        return next();
    });

    io.on('connection', (socket) => {
        const userId = socket.waUserId;
        socket.join(`user:${userId}`);

        const status = getSessionStatus(userId);
        socket.emit('wa:status', status);

        if (status.qr) {
            socket.emit('wa:qr', { qr: status.qr });
        }

        ensureSession(userId).then(async () => {
            const latest = getSessionStatus(userId);
            if (latest.status === 'connected') {
                try {
                    await messageService.syncAndEmitChats(userId, io);
                } catch (error) {
                    console.error(`[waSocket] chat sync on connect failed for ${userId}:`, error.message);
                }
            }
        }).catch((error) => {
            console.error(`[waSocket] ensureSession failed for ${userId}:`, error.message);
            socket.emit('wa:status', { status: 'disconnected', error: error.message });
        });

        socket.on('wa:connect', () => {
            ensureSession(userId).catch((error) => {
                console.error(`[waSocket] wa:connect failed for ${userId}:`, error.message);
            });
        });

        socket.on('wa:logout', async () => {
            await logoutSession(userId);
        });

        socket.on('wa:ping', () => {
            socket.emit('wa:pong', { ts: Date.now() });
        });

        socket.on('disconnect', () => {
            socket.leave(`user:${userId}`);
        });
    });
}

module.exports = { initWaSocket };
