const {
    generateWAMessageFromContent,
    prepareWAMessageMedia,
    proto,
} = require('@whiskeysockets/baileys');

const MEDIA_ALBUM = proto.MessageAssociation.AssociationType.MEDIA_ALBUM;
const MAX_ALBUM_FILES = 10;

function countAlbumMedia(files = []) {
    let expectedImageCount = 0;
    let expectedVideoCount = 0;

    for (const file of files) {
        const mime = (file?.mimetype || '').toLowerCase();
        if (mime.startsWith('video/')) {
            expectedVideoCount += 1;
        } else if (mime.startsWith('image/')) {
            expectedImageCount += 1;
        } else {
            throw new Error('Album hanya mendukung gambar dan video');
        }
    }

    return { expectedImageCount, expectedVideoCount };
}

function buildMediaOptions(sock, jid) {
    return {
        upload: sock.waUploadToServer,
        mediaCache: sock.config?.mediaCache,
        options: sock.config?.options,
        jid,
        logger: sock.logger,
    };
}

function buildMediaPayload(file, caption = '') {
    const mime = file.mimetype || 'application/octet-stream';
    const filename = file.originalname || 'file';

    if (mime.startsWith('image/')) {
        return { payload: { image: file.buffer, mimetype: mime, caption }, mime, filename };
    }
    if (mime.startsWith('video/')) {
        return { payload: { video: file.buffer, mimetype: mime, caption }, mime, filename };
    }

    throw new Error('Album hanya mendukung gambar dan video');
}

async function relayGeneratedMessage(sock, jid, waMsg) {
    if (typeof sock.relayMessage !== 'function') {
        throw new Error('Sesi WhatsApp tidak mendukung pengiriman album');
    }

    await sock.relayMessage(jid, waMsg.message, { messageId: waMsg.key.id });
    return waMsg;
}

async function sendAlbumParent(sock, jid, userJid, counts) {
    const waMsg = generateWAMessageFromContent(jid, {
        albumMessage: {
            expectedImageCount: counts.expectedImageCount,
            expectedVideoCount: counts.expectedVideoCount,
        },
    }, { userJid });

    await relayGeneratedMessage(sock, jid, waMsg);
    return waMsg.key;
}

async function sendAlbumChild(sock, jid, userJid, albumKey, file, caption, index) {
    const { payload } = buildMediaPayload(file, caption);
    const mediaContent = await prepareWAMessageMedia(payload, buildMediaOptions(sock, jid));

    const waMsg = generateWAMessageFromContent(jid, {
        ...mediaContent,
        messageContextInfo: {
            messageAssociation: {
                associationType: MEDIA_ALBUM,
                parentMessageKey: albumKey,
                messageIndex: index,
            },
        },
    }, { userJid });

    return relayGeneratedMessage(sock, jid, waMsg);
}

async function sendWhatsAppAlbum(sock, jid, files = [], caption = '') {
    if (!sock) {
        throw new Error('WhatsApp belum terhubung');
    }
    if (!Array.isArray(files) || files.length < 2) {
        throw new Error('Album minimal 2 media');
    }
    if (files.length > MAX_ALBUM_FILES) {
        throw new Error(`Album maksimal ${MAX_ALBUM_FILES} media`);
    }

    const userJid = sock.user?.id;
    if (!userJid) {
        throw new Error('Sesi WhatsApp belum siap');
    }

    const counts = countAlbumMedia(files);
    const albumKey = await sendAlbumParent(sock, jid, userJid, counts);

    const sent = [];
    for (let index = 0; index < files.length; index += 1) {
        const file = files[index];
        if (!file?.buffer?.length) {
            throw new Error('File tidak valid');
        }
        const isLast = index === files.length - 1;
        const fileCaption = isLast ? (caption || '') : '';
        const waMsg = await sendAlbumChild(sock, jid, userJid, albumKey, file, fileCaption, index);
        sent.push(waMsg);
    }

    return sent;
}

module.exports = {
    MAX_ALBUM_FILES,
    sendWhatsAppAlbum,
};
