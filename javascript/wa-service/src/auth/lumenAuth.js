const { loadEnv } = require('../config/env');

const TOKEN_CACHE_TTL_MS = parseInt(process.env.WA_TOKEN_CACHE_TTL_MS || '55000', 10);
const tokenCache = new Map();

function getCachedToken(token) {
    const entry = tokenCache.get(token);
    if (!entry) return null;
    if (Date.now() > entry.expiresAt) {
        tokenCache.delete(token);
        return null;
    }
    return entry;
}

function setCachedToken(token, payload) {
    tokenCache.set(token, {
        ...payload,
        expiresAt: Date.now() + TOKEN_CACHE_TTL_MS,
    });
}

function invalidateToken(token) {
    if (token) tokenCache.delete(token);
}

async function validateToken(token) {
    if (!token) {
        return { ok: false, status: 401, message: 'Token not provided' };
    }

    const cached = getCachedToken(token);
    if (cached) {
        return {
            ok: true,
            userId: cached.userId,
            user: cached.user,
            cached: true,
        };
    }

    const { lumenApiUrl } = loadEnv();
    const url = `${lumenApiUrl}/cektoken`;

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                token,
                'Content-Type': 'application/json',
            },
        });

        if (!response.ok) {
            const body = await response.json().catch(() => ({}));
            return {
                ok: false,
                status: response.status,
                message: body.message || 'Token invalid',
            };
        }

        const data = await response.json();
        const userId = data?.user_id ?? data?.id_karyawan ?? data?.id ?? data?.user?.id;

        if (!userId) {
            return { ok: false, status: 401, message: 'User id not found in token response' };
        }

        const userIdStr = String(userId);
        setCachedToken(token, { userId: userIdStr, user: data });

        return {
            ok: true,
            userId: userIdStr,
            user: data,
        };
    } catch (error) {
        return {
            ok: false,
            status: 503,
            message: `Auth service unavailable: ${error.message}`,
        };
    }
}

function authMiddleware() {
    return async (req, res, next) => {
        const token = req.headers.token || req.headers['x-token'];
        const result = await validateToken(token);

        if (!result.ok) {
            return res.status(result.status).json({ message: result.message });
        }

        req.waUser = {
            id: result.userId,
            token,
            profile: result.user,
        };

        return next();
    };
}

module.exports = { validateToken, authMiddleware, invalidateToken };
