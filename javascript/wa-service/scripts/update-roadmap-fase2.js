const fs = require('fs');
const path = 'd:/PHP/LUMEN-V3/docs/whatsapp-baileys-roadmap.md';
let content = fs.readFileSync(path, 'utf8');

content = content.replace('> **Status:** Planning', '> **Status:** Fase 2 Selesai — Fase 3 Berikutnya');

const replacements = [
    '- [ ] Scaffold `javascript/wa-service/`',
    '- [ ] Migration DB `wa_sessions`',
    '- [ ] `.env.example` + `.gitignore`',
    '- [ ] Scaffold frontend `modules/whatsapp/`',
    '- [ ] sessionManager.js',
    '- [ ] qrHandler.js',
    '- [ ] eventHandlers.js',
    '- [ ] lumenAuth.js',
    '- [ ] waSocket.js',
    '- [ ] REST: status, logout',
    '- [ ] Integrasi MessagesDropdown',
    '- [ ] useWaSocket.js',
    '- [ ] useWaSession.js',
    '- [ ] QRConnect.jsx',
    '- [ ] WhatsAppPanel.jsx',
    '- [ ] Buat folder `javascript/wa-service/` + `package.json`',
    '- [ ] Install dependencies:',
    '- [ ] Buat migration `create_wa_tables.php`',
    '- [ ] Buat `.env.example` untuk wa-service',
    '- [ ] Update `.gitignore` root Lumen',
    '- [ ] Buat `ecosystem.config.js` untuk PM2',
    '- [ ] Scaffold folder frontend `src/modules/whatsapp/`',
    '- [ ] `sessionManager.js`',
    '- [ ] `qrHandler.js`',
    '- [ ] `eventHandlers.js`',
    '- [ ] `lumenAuth.js`',
    '- [ ] `waSocket.js`',
    '- [ ] REST endpoints:',
    '- [ ] `useWaSocket.js`',
    '- [ ] `useWaSession.js`',
    '- [ ] `QRConnect.jsx`',
    '- [ ] `ConnectionStatus.jsx`',
    '- [ ] `WhatsAppPanel.jsx`',
    '- [ ] Integrasi `MessagesDropdown.js`',
    '- [ ] Refactor `ListKontak.js`',
    '- [ ] Redux `waSlice.js`',
];

for (const item of replacements) {
    content = content.split(item).join(item.replace('[ ]', '[x]'));
}

fs.writeFileSync(path, content, 'utf8');
console.log('Roadmap updated successfully');
