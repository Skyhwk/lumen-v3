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

## Realtime (MQTT)

Push events via broker MQTT (`/intilab/wa/{userId}/#`). Client commands via REST.

| Method | Path | Keterangan |
|--------|------|------------|
| POST | `/api/wa/connect` | Mulai session + sync chat jika connected |
| POST | `/api/wa/logout` | Logout session |
| POST | `/api/wa/typing` | `{ jid, typing? }` — kirim indikator mengetik |

Verifikasi broker:

```bash
npm run verify:mqtt
npm run verify:mqtt:publish
```

## Environment

| Variable | Default | Keterangan |
|----------|---------|------------|
| `WA_DEVICE_NAME` | `INTILAB SUPER APPS` | Nama yang tampil di Perangkat Tertaut WhatsApp |
| `WA_DEVICE_PLATFORM` | `appropriate` | Platform: `windows`, `ubuntu`/`linux`, `macos`, atau `appropriate` (deteksi OS server) |
| `WA_ENABLE_MESSAGE_HISTORY_SYNC` | *(kosong = off)* | `true`/`1`/`yes`/`on` untuk sync history pesan dari HP. Default **mati** — hanya pesan realtime sejak connect. |
| `GROUP_MESSAGE_RETENTION_DAYS` | `3` | Retensi pesan grup saat history sync **aktif** |
| `WA_AVATAR_TTL_HOURS` | `24` | Cache foto profil WA (jam) sebelum di-refresh ulang |
| `MQTT_HOST` | `apps.intilab.com` | Broker MQTT (native TCP, sama Lumen) |
| `MQTT_PORT` | `1883` | Port native MQTT publisher |
| `MQTT_USERNAME` | — | Credential sama notifikasi |
| `MQTT_PASSWORD` | — | Credential sama notifikasi |
| `WA_MQTT_TOPIC_PREFIX` | `/intilab/wa` | Prefix topic realtime WA |
| `WA_MQTT_CLIENT_ID` | `wa-service-publisher` | Client ID publisher wa-service |

## PM2

```bash
npm run pm2:start
npm run pm2:logs
```

## Roadmap

Lihat `docs/whatsapp-baileys-roadmap.md` di root Lumen-V3.
