const fs = require('fs');
const path = require('path');
const { downloadMediaMessage, getContentType, normalizeMessageContent } = require('@whiskeysockets/baileys');
const { loadEnv } = require('../config/env');

const MEDIA_UNAVAILABLE = '__unavailable__';

const MIME_EXT = {
    'image/jpeg': '.jpg',
    'image/png': '.png',
    'image/webp': '.webp',
    'video/mp4': '.mp4',
    'audio/ogg': '.ogg',
    'audio/mpeg': '.mp3',
    'application/pdf': '.pdf',
};

function safeSegment(value) {
    return String(value || 'unknown').replace(/[^a-zA-Z0-9._-]/g, '_');
}

function getMediaDir(...parts) {
    const { mediaDir } = loadEnv();
    return path.resolve(mediaDir, ...parts);
}

function guessExtension(mime, filename) {
    if (filename && path.extname(filename)) {
        return path.extname(filename);
    }
    if (mime && MIME_EXT[mime]) {
        return MIME_EXT[mime];
    }
    if (mime?.startsWith('image/')) return '.jpg';
    if (mime?.startsWith('video/')) return '.mp4';
    if (mime?.startsWith('audio/')) return '.ogg';
    return '.bin';
}

function extractMediaMeta(message) {
    const normalized = normalizeMessageContent(message?.message);
    const contentType = getContentType(normalized || message?.message);
    if (!contentType || contentType === 'conversation' || contentType === 'extendedTextMessage') {
        return null;
    }

    const payload = normalized?.[contentType] || message?.message?.[contentType];
    if (!payload) return null;

    return {
        mime: payload.mimetype || payload.mimeType || null,
        filename: payload.fileName || payload.title || null,
    };
}

function isMediaUnavailable(mediaPath) {
    return mediaPath === MEDIA_UNAVAILABLE;
}

function unavailableMediaPatch() {
    return {
        media_path: MEDIA_UNAVAILABLE,
        media_mime: null,
        media_filename: null,
        media_url: null,
    };
}

async function downloadIncoming(sock, rawMessage, userId, jid) {
    if (!sock || !rawMessage?.message) return null;

    const meta = extractMediaMeta(rawMessage);
    if (!meta) return null;

    const ctx = {
        logger: undefined,
        reuploadRequest: sock.updateMediaMessage?.bind(sock),
    };

    let buffer;
    try {
        buffer = await downloadMediaMessage(
            rawMessage,
            'buffer',
            {},
            ctx,
        );
    } catch (error) {
        const status = error?.response?.status;
        if (ctx.reuploadRequest && [403, 404, 410].includes(status)) {
            try {
                const refreshed = await ctx.reuploadRequest(rawMessage);
                buffer = await downloadMediaMessage(refreshed, 'buffer', {}, ctx);
            } catch {
                return null;
            }
        } else {
            return null;
        }
    }

    if (!buffer?.length) return null;

    const ext = guessExtension(meta.mime, meta.filename);
    const dir = getMediaDir(safeSegment(userId), safeSegment(jid));
    fs.mkdirSync(dir, { recursive: true });

    const filename = `${safeSegment(rawMessage.key?.id || Date.now())}${ext}`;
    const absolutePath = path.join(dir, filename);
    fs.writeFileSync(absolutePath, buffer);

    const relativePath = path.posix.join(
        safeSegment(userId),
        safeSegment(jid),
        filename,
    );

    return {
        media_path: relativePath,
        media_mime: meta.mime,
        media_filename: meta.filename || filename,
        media_url: `/media/${relativePath.replace(/\\/g, '/')}`,
    };
}

function saveOutgoingBuffer(userId, jid, buffer, { mime, filename }) {
    const ext = guessExtension(mime, filename);
    const dir = getMediaDir(safeSegment(userId), safeSegment(jid));
    fs.mkdirSync(dir, { recursive: true });

    const storedName = `${Date.now()}_${safeSegment(filename || `upload${ext}`)}`;
    const absolutePath = path.join(dir, storedName);
    fs.writeFileSync(absolutePath, buffer);

    const relativePath = path.posix.join(
        safeSegment(userId),
        safeSegment(jid),
        storedName,
    );

    return {
        media_path: relativePath,
        media_mime: mime,
        media_filename: filename || storedName,
        media_url: `/media/${relativePath.replace(/\\/g, '/')}`,
    };
}

module.exports = {
    MEDIA_UNAVAILABLE,
    downloadIncoming,
    saveOutgoingBuffer,
    extractMediaMeta,
    isMediaUnavailable,
    unavailableMediaPatch,
};
