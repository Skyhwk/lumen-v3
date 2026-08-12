# Roadmap: Fitur Chat WhatsApp (Baileys + WA Web UI)

> **Versi:** 1.0  
> **Tanggal:** 12 Agustus 2026  
> **Status:** Fase 3 Selesai ï¿½ Fase 4 Sedang Dikerjakan  
> **Backend:** `javascript/wa-service/` (Node.js + Baileys)  
> **Frontend:** `d:\JAVASCRIPT\REACT-JS\frontend\src\modules\whatsapp\`

---

## Daftar Isi

1. [Ringkasan](#1-ringkasan)
2. [Konteks Saat Ini](#2-konteks-saat-ini)
3. [Arsitektur Target](#3-arsitektur-target)
4. [Keputusan Arsitektur](#4-keputusan-arsitektur)
5. [Struktur Folder](#5-struktur-folder)
6. [Database Schema](#6-database-schema)
7. [Fase Implementasi](#7-fase-implementasi)
8. [Integrasi Ekosistem Existing](#8-integrasi-ekosistem-existing)
9. [Environment Variables](#9-environment-variables)
10. [Urutan Implementasi](#10-urutan-implementasi)
11. [Risiko & Mitigasi](#11-risiko--mitigasi)
12. [Estimasi Waktu](#12-estimasi-waktu)
13. [Checklist Progress](#13-checklist-progress)

---

## 1. Ringkasan

Membangun fitur chat WhatsApp di ERP React yang terintegrasi dengan ikon chat di navbar. Pengalaman pengguna meniru **WhatsApp Web**:

- Saat pertama buka ? tampil **QR code** untuk link device
- Setelah autentikasi berhasil ? interface chat penuh
- Realtime messaging, sync pesan per user, sync kontak
- Dukungan kirim/terima: teks, gambar, video, dokumen, audio
- Notifikasi realtime + badge unread di navbar

Backend menggunakan library **[@whiskeysockets/baileys](https://github.com/WhiskeySockets/Baileys)** di service Node.js terpisah dalam repo Lumen-V3.

---

## 2. Konteks Saat Ini

| Area | Lokasi | Status |
|------|--------|--------|
| Ikon chat navbar | `frontend/src/modules/main/header/messages-dropdown/MessagesDropdown.js` | Placeholder "Coming Soon" |
| Panel chat sidebar | `frontend/src/modules/main/list-kontak/ListKontak.js` | Skeleton / WIP |
| Toggle sidebar | `frontend/src/store/reducers/ui.js` ? `listKontakCollapsed` | Sudah ada |
| Realtime existing | MQTT (`REACT_APP_MQTT_BROKER_URL`) | Dipakai ticket, notifikasi |
| Auth ERP | Token header + X-Slice terenkripsi ? Lumen `/api/route` | Production |
| Backend Node di Lumen | `LUMEN-V3/javascript/` | **Kosong ï¿½ siap diisi** |
| Integrasi Baileys | ï¿½ | **Belum ada** |

**Referensi messaging terdekat:** `frontend/src/pages/request/ticket-programming/TicketConversation.js` (API + MQTT + attachment).

---

## 3. Arsitektur Target

```
+-------------------------------------------------------------+
ï¿½  React Frontend (ERP)                                       ï¿½
ï¿½  d:\JAVASCRIPT\REACT-JS\frontend                            ï¿½
ï¿½                                                             ï¿½
ï¿½  MessagesDropdown --? WhatsAppPanel (WA Web UI)             ï¿½
ï¿½       ï¿½                     ï¿½                               ï¿½
ï¿½       ï¿½                     +-- QRConnect                   ï¿½
ï¿½       ï¿½                     +-- ChatList                      ï¿½
ï¿½       ï¿½                     +-- ChatWindow                  ï¿½
+-------------------------------------------------------------+
                ï¿½ HTTP (token Lumen)       ï¿½ Socket.io + REST
                ?                          ?
+---------------------------+   +-------------------------------+
ï¿½  Lumen PHP API            ï¿½   ï¿½  wa-service (Node.js)         ï¿½
ï¿½  d:\PHP\LUMEN-V3          ï¿½   ï¿½  javascript/wa-service/       ï¿½
ï¿½                           ï¿½   ï¿½                               ï¿½
ï¿½  POST /api/cektoken       ï¿½?--ï¿½  lumenAuth.js (validasi)     ï¿½
ï¿½  POST /api/route          ï¿½   ï¿½  Baileys sessionManager       ï¿½
ï¿½  WaProxyController (ops)  ï¿½   ï¿½  Socket.io realtime           ï¿½
+---------------------------+   +-------------------------------+
                ï¿½                               ï¿½
                +-------------------------------+
                                ?
                         +-------------+
                         ï¿½   MySQL     ï¿½
                         ï¿½  (shared)   ï¿½
                         +-------------+
                                ï¿½
                                ?
                         +-------------+
                         ï¿½  WhatsApp   ï¿½
                         ï¿½  Servers    ï¿½
                         +-------------+
```

### Alur User

```
Klik ikon chat di navbar
        ï¿½
        ?
Cek status session WA user
        ï¿½
   +---------+
   ï¿½         ï¿½
disconnected  connected
   ï¿½         ï¿½
   ?         ?
Tampil QR   Tampil Chat List
   ï¿½         ï¿½
Scan HP     Pilih chat ? Chat Window
   ï¿½         ï¿½
   ?         ?
Connected   Kirim/terima pesan realtime
```

---

## 4. Keputusan Arsitektur

| # | Keputusan | Alasan |
|---|-----------|--------|
| 1 | Backend di `LUMEN-V3/javascript/wa-service/` | Satu repo, deploy terpusat, DB shared dengan Lumen |
| 2 | **1 session WA per user ERP** | Setiap karyawan scan QR device sendiri (seperti WA Web pribadi) |
| 3 | Realtime via **Socket.io** (bukan MQTT) | QR streaming, bidirectional events, latency rendah untuk chat |
| 4 | Auth via **token Lumen** | Konsisten dengan ekosistem ERP, tidak buat auth baru |
| 5 | **MySQL shared** dengan Lumen | History pesan & kontak persisten, bisa di-query dari PHP jika perlu |
| 6 | Media disimpan lokal `wa-service/media/` | Download dari WA via Baileys, serve via Express static |

> **Catatan:** Jika nanti dibutuhkan 1 nomor WA perusahaan (shared), arsitektur session manager perlu disesuaikan ï¿½ bukan scope MVP.

---

## 5. Struktur Folder

### 5.1 Backend ï¿½ `LUMEN-V3/javascript/wa-service/`

```
javascript/wa-service/
+-- src/
ï¿½   +-- index.js                 # Entry: Express + Socket.io
ï¿½   +-- config/
ï¿½   ï¿½   +-- env.js               # Load & validate env
ï¿½   +-- auth/
ï¿½   ï¿½   +-- lumenAuth.js         # Validasi token ke Lumen /api/cektoken
ï¿½   +-- baileys/
ï¿½   ï¿½   +-- sessionManager.js    # Map userId ? Baileys socket instance
ï¿½   ï¿½   +-- qrHandler.js         # Generate & emit QR ke frontend
ï¿½   ï¿½   +-- eventHandlers.js     # messages.upsert, connection.update, contacts.upsert
ï¿½   +-- services/
ï¿½   ï¿½   +-- messageService.js    # CRUD pesan, send text/media
ï¿½   ï¿½   +-- contactService.js    # Sync & enrich kontak
ï¿½   ï¿½   +-- mediaService.js      # Download/upload media WA
ï¿½   +-- socket/
ï¿½   ï¿½   +-- waSocket.js          # Socket.io rooms per userId
ï¿½   +-- routes/
ï¿½   ï¿½   +-- api.js               # REST endpoints
ï¿½   +-- db/
ï¿½       +-- connection.js        # MySQL pool (shared config Lumen)
ï¿½       +-- queries/             # Raw queries atau Sequelize models
+-- sessions/                    # Auth state Baileys per user (gitignore)
+-- media/                       # File media download dari WA (gitignore)
+-- package.json
+-- ecosystem.config.js          # PM2 config
+-- .env.example
+-- README.md
```

### 5.2 Frontend ï¿½ `frontend/src/modules/whatsapp/`

```
src/modules/whatsapp/
+-- WhatsAppPanel.jsx            # Panel utama (entry dari ListKontak / MessagesDropdown)
+-- components/
ï¿½   +-- QRConnect.jsx            # Layar scan QR (mirip WA Web)
ï¿½   +-- ChatList.jsx             # Sidebar kiri: daftar chat
ï¿½   +-- ChatWindow.jsx           # Area chat kanan
ï¿½   +-- MessageBubble.jsx        # Bubble pesan (teks, media, status)
ï¿½   +-- MessageInput.jsx         # Input teks + attach file
ï¿½   +-- ContactAvatar.jsx        # Avatar kontak/grup
ï¿½   +-- ConnectionStatus.jsx     # Indicator online/offline/connecting
ï¿½   +-- MediaPreview.jsx         # Preview sebelum kirim / lightbox gambar
+-- hooks/
ï¿½   +-- useWaSocket.js           # Socket.io connection + events
ï¿½   +-- useWaSession.js          # Status session (qr/connected/disconnected)
ï¿½   +-- useWaMessages.js         # Load, send, paginate messages
+-- services/
ï¿½   +-- waApi.js                 # REST calls ke wa-service
+-- store/
    +-- waSlice.js               # Redux: session, chats, messages, unreadTotal
```

### 5.3 Migration PHP ï¿½ `LUMEN-V3/database/migrations/`

```
database/migrations/
+-- 2026_08_12_100000_create_wa_tables.php
```

### 5.4 `.gitignore` tambahan (root Lumen)

```
javascript/wa-service/node_modules/
javascript/wa-service/sessions/
javascript/wa-service/media/
javascript/wa-service/.env
```

---

## 6. Database Schema

### 6.1 `wa_sessions`

Menyimpan metadata session WA per user ERP.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | BIGINT PK | Auto increment |
| `user_id_erp` | INT UNIQUE | ID karyawan dari ERP |
| `phone_number` | VARCHAR(20) NULL | Nomor WA setelah connect |
| `status` | ENUM | `disconnected`, `qr`, `connecting`, `connected` |
| `last_connected_at` | DATETIME NULL | Terakhir online |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

### 6.2 `wa_chats`

Daftar conversation per user.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | BIGINT PK | |
| `user_id_erp` | INT | Owner session |
| `jid` | VARCHAR(100) | WhatsApp JID (e.g. `628xxx@s.whatsapp.net`) |
| `name` | VARCHAR(255) NULL | Nama kontak/grup |
| `avatar_url` | VARCHAR(500) NULL | URL avatar |
| `is_group` | TINYINT(1) DEFAULT 0 | |
| `last_message` | TEXT NULL | Preview pesan terakhir |
| `last_message_at` | DATETIME NULL | |
| `unread_count` | INT DEFAULT 0 | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

**Index:** `UNIQUE(user_id_erp, jid)`

### 6.3 `wa_messages`

History pesan tersimpan.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | BIGINT PK | |
| `chat_id` | BIGINT FK ? wa_chats | |
| `wa_message_id` | VARCHAR(100) | ID unik dari WhatsApp |
| `from_me` | TINYINT(1) | 1 = pesan keluar |
| `sender_jid` | VARCHAR(100) NULL | Pengirim (untuk grup) |
| `type` | ENUM | `text`, `image`, `video`, `document`, `audio`, `sticker`, `location`, `contact` |
| `content` | TEXT NULL | Teks atau caption |
| `media_path` | VARCHAR(500) NULL | Path file lokal |
| `media_mime` | VARCHAR(100) NULL | MIME type |
| `media_filename` | VARCHAR(255) NULL | Nama file asli |
| `timestamp` | DATETIME | Waktu pesan |
| `status` | ENUM | `pending`, `sent`, `delivered`, `read`, `failed` |
| `created_at` | TIMESTAMP | |

**Index:** `UNIQUE(chat_id, wa_message_id)`, `INDEX(chat_id, timestamp)`

### 6.4 `wa_contacts`

Kontak tersinkron dari WhatsApp.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | BIGINT PK | |
| `user_id_erp` | INT | Owner session |
| `jid` | VARCHAR(100) | |
| `name` | VARCHAR(255) NULL | |
| `phone` | VARCHAR(20) NULL | |
| `avatar_url` | VARCHAR(500) NULL | |
| `synced_at` | DATETIME | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

**Index:** `UNIQUE(user_id_erp, jid)`

---

## 7. Fase Implementasi

### Fase 0 ï¿½ Persiapan & Spesifikasi

**Durasi:** 1ï¿½2 hari  
**Tujuan:** Scaffold project, migration DB, konfigurasi env.

#### Task

- [x] Buat folder `javascript/wa-service/` + `package.json`
- [x] Install dependencies: `@whiskeysockets/baileys`, `express`, `socket.io`, `mysql2`, `cors`, `dotenv`
- [x] Buat migration `create_wa_tables.php`
- [x] Buat `.env.example` untuk wa-service
- [x] Update `.gitignore` root Lumen
- [x] Buat `ecosystem.config.js` untuk PM2
- [x] Scaffold folder frontend `src/modules/whatsapp/`

#### Deliverable

- Project structure siap, DB migration ready, env documented.

---

### Fase 1 ï¿½ Backend Baileys Core

**Durasi:** 3ï¿½4 hari  
**Tujuan:** Node service bisa generate QR dan connect ke WhatsApp.

#### Task

- [x] `sessionManager.js` ï¿½ Map `userId ? Baileys socket`, load/save auth state dari `sessions/{userId}/`
- [x] `qrHandler.js` ï¿½ Handle `connection.update`, emit QR string ke Socket.io
- [x] `eventHandlers.js` ï¿½ Handle `creds.update` (persist session), `connection.update` (status)
- [x] `lumenAuth.js` ï¿½ Middleware REST + Socket.io handshake: validasi token ke `POST /api/cektoken`
- [x] `waSocket.js` ï¿½ Room per `userId`, events: `wa:qr`, `wa:status`, `wa:connected`, `wa:disconnected`
- [x] REST endpoints:
  - `GET /api/wa/status` ï¿½ Status koneksi user
  - `POST /api/wa/logout` ï¿½ Hapus session & disconnect
  - `GET /api/wa/qr` ï¿½ Fallback polling QR (opsional)

#### Deliverable

- User buka chat ? QR muncul di frontend via Socket.io
- Scan QR ? status `connected`
- Session persist ? reconnect tanpa QR saat buka lagi

---

### Fase 2 ï¿½ Frontend QR + Connection UI

**Durasi:** 2ï¿½3 hari  
**Tujuan:** UI layaknya WA Web saat belum/sedang connect.

#### Task

- [x] `useWaSocket.js` ï¿½ Connect Socket.io dengan token ERP dari localStorage
- [x] `useWaSession.js` ï¿½ State machine: `disconnected ? qr ? connecting ? connected`
- [x] `QRConnect.jsx` ï¿½ Tampilkan QR image, instruksi link device, loading spinner
- [x] `ConnectionStatus.jsx` ï¿½ Badge status di header panel
- [x] `WhatsAppPanel.jsx` ï¿½ Router internal: QR screen vs Chat screen
- [x] Integrasi `MessagesDropdown.js` ï¿½ Klik ikon ? buka panel WA fullscreen/overlay
- [x] Refactor `ListKontak.js` ï¿½ Render `WhatsAppPanel` atau redirect
- [x] Redux `waSlice.js` ï¿½ State session dasar

#### UX Reference (mirip WA Web)

- Background hijau gelap `#111b21` saat layar QR
- Logo WhatsApp + teks "Use WhatsApp on your computer"
- Instruksi step-by-step link device
- Auto-redirect ke chat list saat `connected`

#### Deliverable

- Flow scan QR end-to-end dari navbar
- Reconnect otomatis jika session masih valid

---

### Fase 3 ï¿½ Chat List + Pesan Teks Realtime

**Durasi:** 4ï¿½5 hari  
**Tujuan:** Baca dan kirim pesan teks, sync per user.

#### Backend

- [ ] Handler `messages.upsert` ? simpan DB + emit `wa:message:new`
- [ ] Handler `messages.update` ? update status (sent/delivered/read)
- [ ] `messageService.js`:
  - `getChats(userId)` ï¿½ Daftar chat + last message + unread
  - `getMessages(chatId, cursor, limit)` ï¿½ Pagination
  - `sendText(userId, jid, text)` ï¿½ Kirim via Baileys
  - `markRead(userId, chatId)` ï¿½ Reset unread
- [x] Sync history awal saat pertama connect
- [x] REST endpoints:
  - `GET /api/wa/chats`
  - `GET /api/wa/chats/:jid/messages`
  - `POST /api/wa/chats/:jid/send`
  - `POST /api/wa/chats/:jid/read`

#### Frontend

- [ ] `ChatList.jsx` ï¿½ Sidebar: avatar, nama, preview, waktu, badge unread, search
- [ ] `ChatWindow.jsx` ï¿½ Header chat, area bubble, scroll auto-bottom
- [ ] `MessageBubble.jsx` ï¿½ Bubble kiri/kanan, timestamp, status icon (?/??)
- [ ] `MessageInput.jsx` ï¿½ Textarea + tombol kirim (Enter = send)
- [ ] `useWaMessages.js` ï¿½ Load, send, realtime append
- [x] Redux: chats, statusChats, messages, unreadTotal
- [ ] Badge unread di `MessagesDropdown.js`

#### Deliverable

- Daftar chat realtime
- Kirim/terima teks
- Unread count per chat + navbar badge
- History pesan tersimpan di DB

---

### Fase 4 ï¿½ Media: Gambar, Video, File, Dokumen

**Durasi:** 3ï¿½4 hari  
**Tujuan:** Kirim dan terima attachment seperti WA Web.

#### Backend

- [ ] `mediaService.js`:
  - Download media dari WA (`downloadMediaMessage`)
  - Simpan ke `media/{userId}/{chatId}/{filename}`
  - Serve static via Express `/media/`
- [ ] `sendMedia(userId, jid, file, type)` ï¿½ image, video, document, audio
- [x] Parse message types: `imageMessage`, `videoMessage`, `documentMessage`, `audioMessage`, `stickerMessage`
- [ ] REST: `POST /api/wa/chats/:jid/send-media` (multipart upload)

#### Frontend

- [x] `MessageInput.jsx` — Tombol attach, file picker, preview sebelum kirim + caption
- [ ] `MessageBubble.jsx` ï¿½ Render image (thumbnail + lightbox), video player, file download link
- [ ] `MediaPreview.jsx` ï¿½ Modal preview gambar fullscreen
- [x] Upload progress indicator

#### Deliverable

- Kirim/terima gambar, video, PDF/dokumen
- Preview & download file

---

### Fase 5 ï¿½ Sync Kontak

**Durasi:** 2 hari  
**Tujuan:** Nama kontak & avatar konsisten.

#### Backend

- [ ] Handler `contacts.upsert` ? upsert `wa_contacts`
- [ ] `contactService.syncAll(userId)` ï¿½ Pull dari Baileys store
- [ ] Enrich `wa_chats.name` dari `wa_contacts`
- [x] Download & cache avatar profil

#### Frontend

- [ ] Tampilkan nama kontak (bukan JID mentah) di chat list & header
- [x] Avatar dari profil WA
- [ ] Search/filter kontak di chat list

#### Deliverable

- Kontak tersinkron per user ERP
- Nama & avatar tampil benar

---

### Fase 6 ï¿½ Polish & Fitur Lanjutan

**Durasi:** Ongoing  
**Tujuan:** UX setara WA Web penuh.

| Fitur | Prioritas | Status |
|-------|-----------|--------|
| Notifikasi desktop (Browser Notification API) | **High** | ? |
| Disconnect / logout device | **High** | ? |
| Multi-tab handling (1 session, banyak tab) | **High** | ? |
| Typing indicator | Medium | ? |
| Read receipts (centang biru) | Medium | ? |
| Reply / quote message | Medium | ? |
| Search pesan dalam chat | Medium | ? |
| Group chat support | Medium | ? |
| Forward message | Low | ? |
| Voice note play / send | Low | ? |
| Delete message (for me / for everyone) | Low | ? |
| Publish MQTT `wa/new-message` untuk notifikasi ERP global | Medium | ? |

---

## 8. Integrasi Ekosistem Existing

| Komponen | File Existing | Cara Integrasi |
|----------|---------------|----------------|
| Ikon chat navbar | `MessagesDropdown.js` | Badge unread dari `waSlice.unreadTotal`, klik buka `WhatsAppPanel` |
| Panel sidebar | `ListKontak.js` | Refactor ? render `WhatsAppPanel` |
| UI toggle | `store/reducers/ui.js` | Reuse `listKontakCollapsed` atau buat `waPanelOpen` |
| Auth token | `store/reducers/auth.js` | Token dari `localStorage.token` ? wa-service |
| API pattern | `services/api.js` + `makeSlice.js` | wa-service punya REST sendiri; auth tetap via Lumen |
| Realtime ticket | `TicketConversation.js` | Referensi pattern MQTT; WA pakai Socket.io terpisah |
| Notifikasi MQTT | `NotificationsDropdown.js` | Opsional: publish event WA ke MQTT topic |
| PM2 deploy | `NODE-JS/MQTT_NOTIFICATION/ecosystem.config.js` | Ikuti pola yang sama |
| Lumen proxy (opsional) | `WaProxyController.php` | Audit log, permission menu, rate limit |

---

## 9. Environment Variables

### 9.1 wa-service ï¿½ `javascript/wa-service/.env`

```env
# Server
PORT=5010
NODE_ENV=development

# Lumen API (validasi token)
LUMEN_API_URL=http://localhost/lumen-v3/public/api

# Database (shared dengan Lumen)
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nama_database_lumen
DB_USERNAME=root
DB_PASSWORD=

# Storage
SESSIONS_DIR=./sessions
MEDIA_DIR=./media

# CORS
CORS_ORIGIN=http://localhost:3000
```

### 9.2 Frontend ï¿½ tambahan di `.env`

```env
REACT_APP_WA_SERVICE_URL=http://localhost:5010
REACT_APP_WA_SOCKET_URL=http://localhost:5010
```

### 9.3 Production (contoh)

```env
REACT_APP_WA_SERVICE_URL=https://wa-api.intilab.com
REACT_APP_WA_SOCKET_URL=https://wa-api.intilab.com
LUMEN_API_URL=https://apps.intilab.com/lumen-v3/public/api
```

---

## 10. Urutan Implementasi

```
Fase 0  --? Scaffold + DB migration + env config
   ï¿½
   ?
Fase 1  --? Backend Baileys + QR + session persist
   ï¿½
   ?  ?-- STOP & TEST: QR scan harus jalan
Fase 2  --? Frontend QR connect UI
   ï¿½
   ?  ?-- STOP & TEST: flow connect end-to-end
Fase 3  --? Chat list + teks + realtime
   ï¿½
   ?  ?-- STOP & TEST: kirim/terima teks + unread badge
Fase 4  --? Media upload/download
   ï¿½
   ?  ?-- STOP & TEST: kirim/terima gambar & file
Fase 5  --? Sync kontak
   ï¿½
   ?
Fase 6  --? Polish & fitur lanjutan
```

> **Prinsip:** Jangan loncat fase. Setiap fase harus lulus test sebelum lanjut.

---

## 11. Risiko & Mitigasi

| Risiko | Dampak | Mitigasi |
|--------|--------|----------|
| Baileys break saat WhatsApp update | Service down | Pin versi package, monitor changelog Baileys, siapkan rollback |
| Session auth corrupt | User tidak bisa connect | Backup auth state, endpoint force re-login (hapus session + QR baru) |
| Memory leak (banyak user connect) | Server crash | Lazy load session, idle timeout auto-disconnect, monitor memory PM2 |
| Nomor WA di-ban | Fitur mati total | Dokumentasi best practice, gunakan nomor bisnis resmi, jangan spam/blasting |
| File media terlalu besar | Storage penuh, upload gagal | Limit upload size (16MB default WA), compress image sebelum kirim, cleanup media lama |
| 1 user buka di 2 browser/tab | Race condition session | Single session policy per userId, atau sync state via Redis |
| QR expired sebelum di-scan | UX buruk | Auto-refresh QR setiap 20 detik, tampilkan countdown |
| Baileys ToS / legal | Risiko compliance | Internal use only, bukan untuk blasting/marketing massal |

---

## 12. Estimasi Waktu

| Fase | Durasi | Hasil |
|------|--------|-------|
| **0** ï¿½ Persiapan | 1ï¿½2 hari | Scaffold + DB ready |
| **1** ï¿½ Baileys core | 3ï¿½4 hari | QR connect backend |
| **2** ï¿½ QR UI | 2ï¿½3 hari | QR connect frontend |
| **3** ï¿½ Chat teks | 4ï¿½5 hari | Messaging realtime |
| **4** ï¿½ Media | 3ï¿½4 hari | Gambar, video, file |
| **5** ï¿½ Kontak | 2 hari | Sync kontak |
| **6** ï¿½ Polish | Ongoing | Fitur lanjutan |
| | | |
| **MVP (Fase 0ï¿½3)** | **~10ï¿½14 hari** | Chat teks fungsional |
| **Full (Fase 0ï¿½5)** | **~15ï¿½20 hari** | Mirip WA Web |

---

## 13. Checklist Progress

### Fase 0 ï¿½ Persiapan
- [x] Scaffold javascript/wa-service/
- [x] Migration DB wa_sessions, wa_chats, wa_messages, wa_contacts
- [x] .env.example + .gitignore
- [x] Scaffold frontend modules/whatsapp/

### Fase 1 ï¿½ Backend Core
- [x] sessionManager.js
- [x] qrHandler.js
- [x] eventHandlers.js
- [x] lumenAuth.js (+ token cache)
- [x] waSocket.js
- [x] REST: status, connect, qr, logout

### Fase 2 ï¿½ Frontend QR
- [x] useWaSocket.js (singleton di Header)
- [x] useWaSession.js
- [x] QRConnect.jsx
- [x] WhatsAppPanel.jsx + halaman /whatsapp
- [x] Integrasi MessagesDropdown
- [x] WaErrorBanner, ConnectionStatus

### Fase 3 ï¿½ Chat Teks
- [x] messageService.js + syncService.js
- [x] messages.upsert / history sync / contacts sync
- [x] ChatList + ChatSidebar + ChatWindow
- [x] MessageBubble + MessageInput
- [x] SyncIndicator + badge unread
- [x] Pagination UI + search chat list
- [x] Read receipts UI (centang)

### Fase 4 ï¿½ Media
- [x] mediaService.js (download + upload)
- [x] POST /api/wa/chats/:jid/send-media
- [x] Render media di MessageBubble + attach di MessageInput
- [x] Upload progress + preview sebelum kirim + bisa di lengkapi dengan chat
- [ ] Backfill media pesan history lama

### Fase 5 ï¿½ Kontak
- [x] contactService.js + contacts.upsert handler
- [x] Avatar profil WA
- [ ] Search/filter kontak

### Fase 6 ï¿½ Polish
- [x] Logout device
- [x] Multi-tab (socket singleton)
- [x] Sync indicator
- [ ] Notifikasi desktop
- [ ] Typing indicator, reply, search pesan

---

## Lampiran

### A. Dependencies wa-service

```json
{
  "dependencies": {
    "@whiskeysockets/baileys": "^6.7.0",
    "express": "^4.21.0",
    "socket.io": "^4.8.0",
    "mysql2": "^3.11.0",
    "cors": "^2.8.5",
    "dotenv": "^16.4.0",
    "pino": "^9.0.0"
  }
}
```

### B. Socket.io Events

| Event | Direction | Payload | Keterangan |
|-------|-----------|---------|------------|
| `wa:qr` | Server ? Client | `{ qr: string }` | QR code data URL |
| `wa:status` | Server ? Client | `{ status: string }` | `disconnected\|qr\|connecting\|connected` |
| `wa:connected` | Server ? Client | `{ phone: string }` | Berhasil connect |
| `wa:disconnected` | Server ? Client | `{ reason: string }` | Putus koneksi |
| `wa:message:new` | Server ? Client | `{ message: object }` | Pesan masuk/keluar |
| `wa:message:update` | Server ? Client | `{ id, status }` | Update status pesan |
| `wa:chats:update` | Server ? Client | `{ chat: object }` | Update chat list |
| `wa:send` | Client ? Server | `{ jid, text }` | Kirim pesan teks |
| `wa:mark-read` | Client ? Server | `{ jid }` | Tandai sudah dibaca |

### C. REST API Endpoints

| Method | Path | Auth | Keterangan |
|--------|------|------|------------|
| GET | `/api/wa/status` | Token | Status session user |
| POST | `/api/wa/logout` | Token | Disconnect & hapus session |
| GET | `/api/wa/chats` | Token | Daftar chat |
| GET | `/api/wa/chats/:jid/messages` | Token | Pesan dalam chat (paginated) |
| POST | `/api/wa/chats/:jid/send` | Token | Kirim teks `{ text }` |
| POST | `/api/wa/chats/:jid/send-media` | Token | Kirim media (multipart) |
| POST | `/api/wa/chats/:jid/read` | Token | Mark as read |
| GET | `/media/*` | ï¿½ | Serve file media static |

---

*Dokumen ini akan di-update seiring progress implementasi.*

