# wa-service

Backend WhatsApp Web untuk ERP Lumen-V3, menggunakan [@whiskeysockets/baileys](https://github.com/WhiskeySockets/Baileys).

## Setup

```bash
cd javascript/wa-service
cp .env.example .env
# isi DB_* dan LUMEN_API_URL sesuai environment lokal
npm install
npm run dev
```

## Endpoints

| Method | Path | Keterangan |
|--------|------|------------|
| GET | `/health` | Health check |
| GET | `/api/wa/status` | Status session (header `token`) |
| POST | `/api/wa/connect` | Mulai / reconnect session Baileys |
| GET | `/api/wa/qr` | QR data URL terakhir (polling fallback) |
| POST | `/api/wa/logout` | Logout & hapus session files |

## Socket.io Events

| Event | Direction | Keterangan |
|-------|-----------|------------|
| `wa:connect` | Client → Server | Trigger connect Baileys |
| `wa:qr` | Server → Client | `{ qr: dataUrl }` |
| `wa:status` | Server → Client | `{ status, phone? }` |
| `wa:connected` | Server → Client | `{ phone }` |
| `wa:disconnected` | Server → Client | `{ reason }` |
| `wa:logout` | Client → Server | Hapus session |

Connect dengan auth:

```js
io(url, { auth: { token: localStorage.token } })
```

## Environment

| Variable | Default | Keterangan |
|----------|---------|------------|
| `WA_DEVICE_NAME` | `INTILAB SUPER APPS` | Nama yang tampil di Perangkat Tertaut WhatsApp |
| `WA_DEVICE_PLATFORM` | `appropriate` | Platform: `windows`, `ubuntu`/`linux`, `macos`, atau `appropriate` (deteksi OS server) |
| `WA_ENABLE_MESSAGE_HISTORY_SYNC` | *(kosong = off)* | `true`/`1`/`yes`/`on` untuk sync history pesan dari HP. Default **mati** — hanya pesan realtime sejak connect. |
| `GROUP_MESSAGE_RETENTION_DAYS` | `3` | Retensi pesan grup saat history sync **aktif** |
| `WA_AVATAR_TTL_HOURS` | `24` | Cache foto profil WA (jam) sebelum di-refresh ulang |

## PM2

```bash
npm run pm2:start
npm run pm2:logs
```

## Roadmap

Lihat `docs/whatsapp-baileys-roadmap.md` di root Lumen-V3.
