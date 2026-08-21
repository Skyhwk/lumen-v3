<?php

/**
 * 100 soal SOFTWARE ENGINEERING untuk assessment programmer (manager scope).
 * Bahasa Indonesia, skenario kontekstual, referensi jurnal/literatur.
 * Git, API, keamanan, best practice tim dev.
 * Return: array of ['text','options','answer','explanation']
 */

$helpers = require __DIR__ . '/QuestionSeederHelpers.php';
$make = $helpers['makeQuestion'];

$questions = [];

// ── 1. Git & version control — workflow tim dev (25) ─────────────────────────
$git = [
    [
        'Developer selesai fitur login OAuth. Perintah Git manakah yang menyimpan snapshot perubahan ke repository lokal',
        'git commit',
        ['git push', 'git merge', 'git branch -d'],
        'git commit menyimpan snapshot ke repo lokal.',
    ],
    [
        'Setelah commit lokal, developer ingin mengirim perubahan ke remote GitHub/GitLab. Perintah Git manakah yang digunakan',
        'git push',
        ['git commit', 'git pull', 'git reset --hard'],
        'git push mengirim commit lokal ke remote repository.',
    ],
    [
        'Sebelum mulai coding, developer menjalankan git pull di branch develop. Apa yang dilakukan git pull',
        'Mengunduh perubahan remote lalu menggabungkannya ke branch lokal (fetch + merge/rebase)',
        ['Hanya membuat commit baru', 'Menghapus branch lokal', 'Otomatis git stash pop'],
        'git pull = sync kode terbaru dari remote ke lokal.',
    ],
    [
        'Tim membuat branch feature/payment-gateway terpisah dari main. Apa fungsi utama git branch',
        'Mengisolasi pengembangan fitur agar tidak mengganggu branch main',
        ['Menghapus commit lama', 'Mengenkripsi repository', 'Mengganti remote URL'],
        'Branch memungkinkan parallel development.',
    ],
    [
        'Perbedaan git rebase dan git merge: apa hasil utama git rebase',
        'History commit menjadi linear (commit di-replay di atas branch target)',
        ['Selalu membuat merge commit', 'Menghapus seluruh history', 'Tidak mengubah posisi commit'],
        'Rebase rewrite history — hati-hati pada shared branch.',
    ],
    [
        'Dua developer mengedit baris yang sama di config.php berbeda, lalu merge. Apa yang terjadi',
        'Terjadi merge conflict karena Git tidak bisa auto-merge perubahan yang bentrok',
        ['Merge otomatis sukses karena branch berbeda', 'Commit message harus sama', 'Remote repository berbeda'],
        'Conflict harus diselesaikan manual oleh developer.',
    ],
    [
        'Developer punya perubahan WIP belum siap commit, tapi harus pindah branch urgent. Perintah Git manakah yang tepat',
        'git stash (simpan perubahan sementara)',
        ['git push --force', 'git branch -D', 'git clean -fd'],
        'git stash menyimpan WIP sementara, bisa dipulihkan dengan git stash pop.',
    ],
    [
        'Bug di production perlu dibatalkan dari commit yang sudah di-push ke main (shared branch). Perintah Git manakah yang paling aman',
        'git revert (membuat commit baru yang membatalkan perubahan)',
        ['git reset --hard lalu git push --force', 'Menghapus repository', 'Mengedit history commit publik'],
        'git revert aman untuk shared branch karena tidak rewrite history.',
    ],
    [
        'Developer menjalankan git reset --hard HEAD~1 pada branch lokal yang belum di-push. Apa efeknya',
        'Commit terakhir dihapus dan perubahan working directory ikut dibuang',
        ['Aman untuk commit yang sudah di-push ke remote', 'Membuat merge commit otomatis', 'Hanya unstaged file yang terpengaruh'],
        'git reset --hard bersifat destruktif — hanya untuk branch lokal.',
    ],
    [
        'File .gitignore berisi node_modules/, .env, vendor/. Apa fungsi .gitignore',
        'Menghindari file/folder tertentu agar tidak di-track Git',
        ['Mengenkripsi file sebelum commit', 'Mengecilkan ukuran commit otomatis', 'Mengganti file .env saat deploy'],
        'Ignore dependency, build artifact, dan secret.',
    ],
    [
        'Pull Request dibuat sebelum merge feature ke main. Apa tujuan utama Pull Request',
        'Gate code review untuk menjaga kualitas sebelum integrasi',
        ['Deploy langsung ke production', 'Mengganti unit test', 'Menghapus branch main'],
        'PR/MR = review + CI check sebelum merge.',
    ],
    [
        'Tim memakai Conventional Commits, contoh: feat(auth): add OAuth2 login. Apa manfaatnya',
        'History commit lebih terbaca dan changelog bisa diautomasi',
        ['git push menjadi lebih cepat', 'Tidak perlu branch feature', 'Mengganti kebutuhan testing'],
        'Format feat:/fix:/docs: standar Conventional Commits.',
    ],
    [
        'Developer ingin memindahkan commit abc123 saja ke branch hotfix. Perintah Git manakah yang digunakan',
        'git cherry-pick abc123',
        ['git revert abc123 saja', 'git merge --all', 'git clone ulang'],
        'cherry-pick menerapkan commit tertentu ke branch aktif.',
    ],
    [
        'Setelah release production v2.1.0, tim menandai versi di Git. Perintah Git manakah yang tepat',
        'git tag v2.1.0',
        ['git commit v2.1.0', 'git branch v2.1.0-only', 'git ignore v2.1.0'],
        'git tag menandai versi release.',
    ],
    [
        'Branch main diproteksi: tidak boleh push langsung, wajib PR + 2 approval. Apa tujuan protected branch',
        'Mencegah perubahan langsung ke main tanpa review dan CI',
        ['Mengizinkan force push untuk semua developer', 'Menonaktifkan CI', 'Memudahkan penghapusan branch main'],
        'Protected branch menjaga stabilitas production branch.',
    ],
    [
        'Bug muncul di v2.0.0 dan hilang di v2.1.0. Perintah Git manakah yang membantu menemukan commit penyebab bug',
        'git bisect',
        ['git merge', 'git tag saja', 'git clone --depth 1'],
        'git bisect mencari commit penyebab dengan binary search.',
    ],
    [
        'Contributor eksternal fork repository lalu buat PR ke upstream. Apa perbedaan fork dan branch lokal',
        'Fork = salinan repository penuh di akun contributor, branch = cabang di repo yang sama',
        ['Fork sama persis dengan branch lokal', 'Fork menggantikan git clone', 'Fork menghapus upstream otomatis'],
        'GitHub flow: fork → branch → PR → review.',
    ],
    [
        'Tim squash merge 15 commit WIP menjadi 1 commit di main. Apa manfaat squash merge',
        'History main lebih bersih dengan 1 commit per fitur',
        ['Semua commit WIP tetap tampil di main', 'History commit dihapus total', 'Review code tidak diperlukan'],
        'Squash merge vs merge biasa — trade-off history detail.',
    ],
    [
        'Perbedaan git fetch origin dan git pull: apa yang dilakukan git fetch saja',
        'Mengunduh perubahan remote tanpa merge ke branch lokal',
        ['Otomatis merge ke branch lokal', 'Mengirim commit ke remote', 'Menghapus branch lokal'],
        'fetch update refs remote — review dulu sebelum merge.',
    ],
    [
        'Developer checkout commit hash abc123 langsung (detached HEAD). Apa arti detached HEAD',
        'HEAD menunjuk ke commit tertentu, bukan ke ujung branch',
        ['Repository rusak', 'Tidak bisa membuat commit baru', 'Remote repository terhapus'],
        'Detached HEAD = checkout commit langsung, bukan branch tip.',
    ],
    [
        'API mengalami breaking change sehingga client lama tidak kompatibel. Kapan versi MAJOR (Semantic Versioning) harus dinaikkan',
        'Saat ada perubahan API yang breaking / tidak backward-compatible',
        ['Saat hanya bug fix kecil', 'Saat menambah fitur backward-compatible', 'Saat update dokumentasi saja'],
        'SemVer: MAJOR.MINOR.PATCH — semver.org.',
    ],
    [
        'Developer ingin tahu siapa terakhir mengubah baris 42 di file.php. Informasi apa yang diberikan git blame',
        'Nama author dan commit terakhir yang mengubah baris tersebut',
        ['Persentase test coverage baris itu', 'Graf branch repository', 'URL remote repository'],
        'git blame = line-level authorship untuk debugging.',
    ],
    [
        'Satu repository berisi frontend, backend, dan mobile app. Apa definisi monorepo',
        'Satu repository Git untuk banyak project/package terkait',
        ['Repository hanya boleh 1 file', 'Repository tanpa branch', 'Repository tanpa CI'],
        'Monorepo dipakai Google/Meta untuk perubahan lintas project atomik.',
    ],
    [
        'Tim memakai trunk-based development: branch pendek, merge ke main hampir setiap hari. Apa tujuan praktik ini',
        'Integrasi sering ke main untuk mengurangi merge conflict besar',
        ['Branch fitur hidup berbulan-bulan tanpa merge', 'Tidak perlu branch main', 'Tidak perlu CI/CD'],
        'Trunk-based development = continuous integration culture.',
    ],
    [
        'Developer menjalankan git push --force ke branch main yang dipakai 10 orang. Apa risiko utama',
        'Commit orang lain bisa tertimpa sehingga terjadi data loss dan konflik tim',
        ['Selalu aman jika yakin', 'Git otomatis backup semua commit', 'Otomatis membuat Pull Request'],
        'Force push di shared branch sangat berbahaya.',
    ],
];
for ($n = 0; $n < 25; $n++) {
    $g = $git[$n];
    $questions[] = $make($g[0], $g[1], $g[2], $g[3], $n);
}

// ── 2. API, HTTP, arsitektur web — OWASP & REST (25) ───────────────────────
$api = [
    [
        'GET /api/users/123 dipanggil 10 kali. HTTP GET idempotent artinya',
        'Multiple identical requests — efek sama dengan satu request',
        ['Always changes server state', 'Requires body', 'Cannot cache'],
        'GET safe & idempotent — REST constraint (Fielding, 2000).',
    ],
    [
        'POST /api/orders membuat order baru. POST typically',
        'Create resource — non-idempotent (duplikat jika retry tanpa idempotency key)',
        ['Always idempotent', 'Only read', 'No body allowed'],
        'POST create/submit — OWASP API Security: gunakan idempotency key untuk payment.',
    ],
    [
        'PATCH /api/users/123 { "name": "Budi" } vs PUT full replace. PATCH',
        'Partial update — hanya field yang dikirim',
        ['Replace entire resource always', 'Delete resource', 'Read only'],
        'PATCH partial modify — common REST pattern.',
    ],
    [
        'API return HTTP 201 Created setelah POST /api/products',
        'Resource successfully created — include Location header',
        ['OK generic 200', 'Not found 404', 'Server error 500'],
        '201 Created — RESTful status code semantics.',
    ],
    [
        'Request tanpa token ke /api/admin. HTTP 401 vs 403: 401',
        'Unauthenticated — identitas belum diverifikasi',
        ['Forbidden — no permission', 'Not found', 'Rate limit'],
        '401 = "Siapa Anda?" — 403 = "Anda tidak punya akses".',
    ],
    [
        'User login tapi akses /api/admin/users. HTTP 403',
        'Forbidden — authenticated tapi tidak punya permission',
        ['Not logged in 401', 'Resource not found 404', 'Rate limit only'],
        '403 = authorization failure. OWASP API #1-BOLA sering return 403/404.',
    ],
    [
        'REST stateless: JWT di Authorization header setiap request',
        'Each request contains all info needed — no server session state',
        ['Server stores session always', 'No HTTP methods', 'No JSON'],
        'Stateless scalability — load balancer friendly.',
    ],
    [
        'RESTful URL design untuk resource users',
        'Nouns plural: GET /api/v1/users, POST /api/v1/users',
        ['Verbs in URL: /api/getUsers', 'SQL in path', 'MixedCaseRequired'],
        'Resources as nouns — REST best practice.',
    ],
    [
        'API breaking change: versioning strategy',
        '/api/v2/users atau Accept: application/vnd.api.v2+json',
        ['No versioning ever', 'Version hidden in body only', 'Random URL'],
        'Explicit versioning — backward compatibility plan.',
    ],
    [
        'JWT token structure: eyJhbGci...eyJzdWI...SflKxwRJ',
        'header.payload.signature (3 parts base64)',
        ['payload only', 'encrypted always', 'session id only'],
        'JWT RFC 7519 — verify signature, jangan trust payload tanpa verify.',
    ],
    [
        'OAuth 2.0 Authorization Code Flow untuk web app login Google',
        'Redirect ke auth server → exchange code for token (server-side)',
        ['Password in URL', 'No redirect', 'Client secret in frontend SPA'],
        'Secure for web apps — secret di backend, bukan browser.',
    ],
    [
        'Frontend localhost:3000 call API api.company.com — browser block. CORS',
        'Cross-Origin Resource Sharing — browser access control',
        ['Database encryption', 'SQL injection prevention', 'Git merge'],
        'CORS header Access-Control-Allow-Origin — API gateway config.',
    ],
    [
        'Content-Type: application/json pada POST request',
        'Request body format is JSON',
        ['Binary file always', 'HTML only', 'No charset'],
        'API contract — OpenAPI spec document Content-Type.',
    ],
    [
        'Rate limiting 100 req/min per API key pada public API',
        'Prevent abuse, protect server resources, fair usage',
        ['Speed up API', 'Replace auth', 'Encrypt data'],
        'Throttle — OWASP API #4 Unrestricted Resource Consumption.',
    ],
    [
        'Pagination GET /api/orders?page=2&limit=20',
        'page & limit atau cursor-based for large dataset',
        ['sort only', 'no limit ever', 'full dump always'],
        'Limit result set — prevent excessive data exposure (OWASP API #3).',
    ],
    [
        'Payment API POST dengan Idempotency-Key header pada retry',
        'Prevent duplicate charges — same key → same result',
        ['Slow response', 'Auth failure', 'CORS error'],
        'Idempotency key — Stripe/payment gateway pattern.',
    ],
    [
        'Webhook: payment gateway POST callback ke /api/webhooks/payment',
        'Server push HTTP callback on event — event-driven',
        ['Polling every second', 'WebSocket only', 'FTP upload'],
        'Webhook vs polling — real-time integration pattern.',
    ],
    [
        'GraphQL POST { user(id:1) { name email posts { title } } }',
        'Client request exact fields needed — one query',
        ['Always multiple round trips', 'No schema', 'Replace HTTP'],
        'Flexible field selection — avoid over-fetching.',
    ],
    [
        'Microservices: order-service, payment-service, notification-service terpisah',
        'Independent deploy vs operational complexity (network, monitoring)',
        ['Always simpler', 'No network calls', 'Single DB always'],
        'Distributed system overhead — CAP theorem applies.',
    ],
    [
        'Load balancer nginx distribute traffic ke 3 app server',
        'Distribute traffic — high availability & scaling',
        ['Encrypt DB', 'Compile code', 'Git merge'],
        'Horizontal scaling — round-robin, least-connections.',
    ],
    [
        'Reverse proxy nginx → upstream Laravel app servers',
        'Forward client requests to backend — SSL termination, caching',
        ['Replace database', 'Write business logic', 'Git hosting'],
        'Gateway pattern — nginx/Traefik/Kong.',
    ],
    [
        'Cache-Control: max-age=3600 pada static assets',
        'Browser/CDN cache 3600 seconds before revalidate',
        ['Force no cache always', 'Auth token expiry', 'DB timeout'],
        'HTTP caching — performance optimization.',
    ],
    [
        'ETag: "abc123" header — client If-None-Match: "abc123" → 304 Not Modified',
        'Conditional request — cache validation, save bandwidth',
        ['Encryption', 'CORS', 'JWT signing'],
        'Resource version fingerprint — efficient caching.',
    ],
    [
        'OpenAPI/Swagger spec openapi.yaml di repository',
        'Document API contract — enable automated testing & code generation',
        ['Replace tests', 'Database schema', 'Git config'],
        'ApyGuard/RedTeam (2026): spec-driven DAST improves coverage.',
    ],
    [
        'HATEOAS: response include links { "self": "/users/1", "orders": "/users/1/orders" }',
        'Hypermedia links guide available actions — REST maturity level 3',
        ['No links in response', 'SOAP only', 'GraphQL only'],
        'Richardson Maturity Model — Fielding REST dissertation.',
    ],
];
for ($n = 0; $n < 25; $n++) {
    $a = $api[$n];
    $questions[] = $make($a[0], $a[1], $a[2], $a[3], 100 + $n);
}

// ── 3. Keamanan aplikasi — OWASP & penelitian UGM (25) ───────────────────────
$security = [
    [
        'Input user: SELECT * FROM users WHERE id = \' + input + \'. Serangan & mitigasi',
        'SQL Injection — gunakan parameterized queries / prepared statements',
        ['XSS — escape HTML', 'CSRF — anti-token', 'Disable HTTPS'],
        'OWASP Top 10 A03 Injection. Never interpolate raw input.',
    ],
    [
        'Comment form render user input tanpa escape: <script>alert(1)</script>',
        'XSS — escape output, Content-Security-Policy, sanitize input',
        ['SQL Injection — prepared stmt', 'Store password plain', 'Disable CORS'],
        'OWASP A03 XSS — stored/reflected XSS prevention.',
    ],
    [
        'Form bank transfer tanpa token, attacker trick user submit. Mitigasi',
        'Anti-CSRF token per session/form + SameSite cookie',
        ['Disable cookies', 'Use HTTP only', 'Remove auth'],
        'OWASP A01 Broken Access Control — CSRF token validation.',
    ],
    [
        'Password storage di database production',
        'Strong adaptive hash + salt: bcrypt, Argon2id — NEVER plain/MD5',
        ['MD5 hash saja', 'Plain text', 'Base64 encode'],
        'OWASP Password Storage Cheat Sheet — bcrypt cost factor ≥10.',
    ],
    [
        'HTTPS/TLS pada api.company.com',
        'Encrypt data in transit — integrity & confidentiality',
        ['Encrypt data at rest only', 'SQL queries', 'Git history'],
        'TLS 1.2+ mandatory — Let\'s Encrypt / commercial CA.',
    ],
    [
        'DB user aplikasi Laravel: hanya SELECT,INSERT,UPDATE on app_db — bukan root',
        'Principle of Least Privilege — minimum access needed',
        ['Admin for everyone', 'Shared one account', 'Root DB user in .env'],
        'OWASP — limit blast radius jika app compromised.',
    ],
    [
        'API key Stripe dan DB password di .env — jangan commit',
        'Never commit to git — use .env + secret manager (Vault, AWS SM)',
        ['Commit to private repo OK', 'Hardcode in React bundle', 'Share via WhatsApp'],
        'OWASP A02 Cryptographic Failures — secrets management.',
    ],
    [
        'JWT auth token disimpan httpOnly Secure SameSite cookie vs localStorage',
        'httpOnly cookie — not accessible via JavaScript (XSS mitigation)',
        ['localStorage best always', 'URL query param', 'Console.log debug'],
        'XSS cannot steal httpOnly cookie — defense in depth.',
    ],
    [
        'Validasi form hanya di JavaScript frontend',
        'Tidak cukup — server-side validation WAJIB, client-side tambahan UX',
        ['Client-side only OK', 'Never validate', 'Database constraint saja'],
        'Never trust client — attacker bypass frontend easily.',
    ],
    [
        'OWASP Top 10 2021 purpose untuk tim dev',
        'Awareness common web vulnerabilities — prioritize mitigation',
        ['Replace coding standards', 'Git workflow', 'UI design guide'],
        'OWASP Top 10 — industry standard risk catalog.',
    ],
    [
        'npm audit / composer audit menemukan CVE di dependency lodash',
        'Dependency vulnerability scanning — supply chain security',
        ['Ignore updates forever', 'Disable audit', 'Pin vulnerable forever'],
        'OWASP A06 Vulnerable Components — keep dependencies updated.',
    ],
    [
        'Response header X-Content-Type-Options: nosniff',
        'Prevent MIME type sniffing — browser follow declared Content-Type',
        ['Enable CORS *', 'Disable HTTPS', 'Allow iframe all'],
        'Security header — MDN & OWASP Secure Headers.',
    ],
    [
        'Content-Security-Policy: default-src \'self\'; script-src \'self\'',
        'Restrict script sources — mitigate XSS injection',
        ['Open all origins', 'Replace auth', 'SQL escape only'],
        'CSP — OWASP XSS prevention defense layer.',
    ],
    [
        'Login endpoint menerima 10.000 attempt/password detik dari 1 IP',
        'Rate limit, account lockout, CAPTCHA, MFA — brute force mitigation',
        ['Unlimited attempts OK', 'Log password for debug', 'Disable HTTPS'],
        'OWASP A07 Identification & Authentication Failures.',
    ],
    [
        'Login: password + OTP dari authenticator app',
        'Multi-Factor Authentication — something you know + have',
        ['Password only enough', 'Same factor twice', 'No second factor'],
        'MFA blocks 99.9% automated attacks — Microsoft research.',
    ],
    [
        'Encrypt sensitive column credit_card di database at rest',
        'Encryption at rest — AES-256 column/database level',
        ['HTTPS only enough for DB', 'Base64 is encryption', 'MD5 encrypts'],
        'PCI-DSS requirement — protect stored cardholder data.',
    ],
    [
        'Log request body termasuk password dan JWT token',
        'NEVER log passwords, tokens, full PAN — redact PII/secrets',
        ['Log all for debug prod', 'Log JWT secret', 'Log credit card OK'],
        'OWASP Logging Cheat Sheet — sensitive data exclusion.',
    ],
    [
        'Browser block JS dari evil.com access DOM api.company.com',
        'Same-Origin Policy — browser security model',
        ['Applies to server DB', 'Replaces CORS', 'Git security'],
        'SOP + CORS — complementary browser security.',
    ],
    [
        'Session cookie flags: Secure; HttpOnly; SameSite=Strict',
        'Secure=HTTPS only, HttpOnly=no JS access, SameSite=CSRF mitigation',
        ['No flags needed', 'Store in URL', 'Plain HTTP OK'],
        'OWASP Session Management — cookie hardening.',
    ],
    [
        'Tim security simulate attack find SQLi before production',
        'Penetration testing — proactive vulnerability assessment',
        ['Replace unit tests', 'Daily git merge', 'UI testing only'],
        'OWASP Testing Guide — complement automated SAST/DAST.',
    ],
    [
        'CVE published for library used in app, no patch available yet',
        'Zero-day / unpatched vulnerability — monitor, workaround, WAF rule',
        ['Patched yesterday', 'Only CSS bug', 'Only in Git'],
        'Vulnerability management lifecycle.',
    ],
    [
        'Security architecture: WAF + input validation + auth + encryption + monitoring',
        'Defense in depth — multiple security layers',
        ['Single firewall enough', 'Security through obscurity only', 'No monitoring'],
        'Layered controls — no single point of failure.',
    ],
    [
        'React SPA bundle include API_KEY = "sk_live_xxx" hardcoded',
        'Exposed to all users — use backend proxy, never secrets in frontend',
        ['Always safe in bundle', 'Replace OAuth', 'Store in git OK'],
        'OWASP API #2 Broken Authentication — client-side secrets visible.',
    ],
    [
        'Role admin, manager, staff dengan permission berbeda di Laravel',
        'RBAC — Role-Based Access Control, permissions via roles',
        ['One role for all', 'No authorization check', 'Password per row'],
        'OWASP A01 — enforce authorization on every endpoint.',
    ],
    [
        'Security breach detected: data exfiltration ke IP unknown. First step',
        'Contain & assess scope — isolate affected systems, preserve evidence',
        ['Delete all logs', 'Announce publicly first', 'Ignore until Monday'],
        'NIST Incident Response: Contain → Eradicate → Recover → Learn.',
    ],
];
for ($n = 0; $n < 25; $n++) {
    $s = $security[$n];
    $questions[] = $make($s[0], $s[1], $s[2], $s[3], 200 + $n);
}

// ── 4. Best practice & situational judgment tim dev (25) ─────────────────────
$practices = [
    [
        'Pull Request review sebelum merge ke main. Tujuan utama code review',
        'Kualitas kode, knowledge sharing, deteksi bug & security issue',
        ['Menunda deploy selamanya', 'Ganti pair programming total', 'Hapus semua test'],
        'Google research: code review catches bugs early — industry standard.',
    ],
    [
        'Unit test PHPUnit/Jest characteristics',
        'Fast, isolated, repeatable, self-validating — F.I.R.S.T principles',
        ['Depend on production DB', 'Manual only', 'Run once a year'],
        'Test pyramid — unit tests foundation.',
    ],
    [
        'Copy-paste logic validasi di 5 controller. SOLID/DRY solution',
        'DRY — extract ke FormRequest/Service, single source of truth',
        ['Copy lagi ke controller ke-6', 'One giant file', 'No functions'],
        'DRY + SRP — maintainability.',
    ],
    [
        'SOLID: UserController handle HTTP + DB + email + PDF. Violation',
        'Single Responsibility — one class, one reason to change',
        ['Open/Closed', 'Liskov', 'Interface Segregation'],
        'SRP — split concerns: Controller, Service, Repository.',
    ],
    [
        'Legacy module tanpa test, deadline ketat, shortcut debt',
        'Track technical debt, allocate refactor sprint, jangan ignore forever',
        ['Ignore forever OK', 'Rewrite all overnight', 'Skip tests permanently'],
        'Martin Fowler Technical Debt metaphor — manage incrementally.',
    ],
    [
        'Production bug critical malam hari. Hotfix flow aman',
        'Branch hotfix → fix → test → PR review → deploy → monitor',
        ['Push langsung main tanpa review', 'Edit file di server prod', 'Skip CI'],
        'Controlled hotfix — DORA: restore service fast with process.',
    ],
    [
        'Staging environment mirror production config',
        'Pre-production testing — catch issues before prod deploy',
        ['Replace development', 'Public demo only', 'Backup storage'],
        'Environment parity — 12-factor app config.',
    ],
    [
        'Feature flag LaunchDarkly toggle new checkout tanpa deploy rollback',
        'Runtime feature control — gradual rollout, A/B test',
        ['Replace git', 'Disable tests', 'Hardcode always on'],
        'Feature flags — continuous delivery enabler.',
    ],
    [
        'GitHub Actions: push → build → test → deploy otomatis',
        'CI/CD pipeline — automated quality gate',
        ['Manual FTP deploy', 'Once per year', 'No automated tests'],
        'UGM thesis (2024): CI/CD + SAST/DAST prevent vulnerable code merge.',
    ],
    [
        'Minimum API documentation untuk tim frontend',
        'Endpoint, params, auth, request/response example, error codes',
        ['No docs — verbal only', 'Emoji README', 'Tebak dari kode'],
        'OpenAPI spec — contract-first development.',
    ],
    [
        'Pair programming on complex algorithm bug',
        'Real-time review & knowledge transfer — faster resolution',
        ['Always slower waste', 'Replace all tests', 'Solo only always'],
        'XP practice — quality + learning.',
    ],
    [
        'Sprint retrospective: what went well, what to improve',
        'Continuous process improvement — Agile manifesto',
        ['Skip discussion', 'Replace planning', 'Annual only'],
        'Scrum retrospective — team kaizen.',
    ],
    [
        'Definition of Done: code written + reviewed + tests pass + docs updated',
        'Team agreement on "complete" — prevent half-done features',
        ['Code written only', 'No QA needed', 'Deploy untracked'],
        'DoD — Agile quality gate.',
    ],
    [
        'Production outage postmortem: focus on system improvement, not blame',
        'Blameless postmortem — learn & improve processes',
        ['Find who to fire', 'Hide incident', 'No action items'],
        'Google SRE — blameless culture builds trust.',
    ],
    [
        'Situasi: bug kritis production jam 2 pagi, on-call engineer. Tindakan tepat',
        'Assess impact, notify lead/stakeholder, hotfix terkontrol atau rollback',
        ['Diam sampai jam kerja', 'Fix langsung di prod tanpa backup', 'Sembunyikan dari tim'],
        'Incident response — transparency & controlled action.',
    ],
    [
        'Situasi: deadline besok, QA minta 2 hari testing tambahan. Tindakan tepat',
        'Komunikasikan risk ke product owner/stakeholder — jangan skip test kritis',
        ['Skip semua test', 'Deploy tanpa review', 'Salahkan QA'],
        'Risk-based decision — stakeholder accepts trade-off explicitly.',
    ],
    [
        'Situasi: rekan push breaking build ke main, CI red. Tindakan tepat',
        'Revert/fix segera, notify tim, review CI guard & branch protection',
        ['Biarkan broken sampai besok', 'Blame publicly Slack', 'Disable CI'],
        'Broken main blocks entire team — fix fast (trunk-based discipline).',
    ],
    [
        'Situasi: manager minta hardcode DB password biar cepat demo. Tindakan tepat',
        'Tolak — gunakan .env, jelaskan risiko security & compliance',
        ['Hardcode hapus nanti (never happens)', 'Share via chat', 'Commit ke git private'],
        'OWASP + 12-factor — secrets never in source code.',
    ],
    [
        'Situasi: requirement "buat seperti Tokopedia" tanpa detail. Tindakan tepat',
        'Klarifikasi acceptance criteria & user story sebelum coding',
        ['Asumsi sendiri', 'Coding dulu tanya belakangan', 'Copy code Tokopedia'],
        'Agile — definition of ready prevents rework.',
    ],
    [
        'Situasi: legacy payment module tanpa test, perlu ubah tax calculation. Tindakan tepat',
        'Tambah test coverage area kritikal dulu, refactor incremental',
        ['Rewrite total tanpa plan', 'Ubah langsung deploy Jumat sore', 'Tidak usah test'],
        'Characterization tests — safety net before change.',
    ],
    [
        'Situasi: merge conflict kompleks 50 files dengan developer lain. Tindakan tepat',
        'Diskusi dengan author branch, pair resolve, test setelah merge',
        ['Pilih all ours blind', 'Force push', 'Delete branch tanpa komunikasi'],
        'Collaborative conflict resolution — preserve both intents.',
    ],
    [
        'Situasi: monitoring alert CPU 80% trigger 50x/hari tapi normal. Tindakan tepat',
        'Tune threshold/query, dokumentasi baseline — jangan disable alerts',
        ['Disable all alerts', 'Ignore semua', 'Uninstall monitoring'],
        'Alert fatigue — improve signal-to-noise ratio.',
    ],
    [
        'Situasi: perlu breaking change API /users response format. Tindakan tepat',
        'Version API v2, deprecate v1 dengan timeline & migration guide',
        ['Break v1 tanpa notice', 'Hide dari client', 'Delete documentation'],
        'API lifecycle management — semver + deprecation policy.',
    ],
    [
        'Situasi: junior developer minta bantuan terus tiap 30 menit. Tindakan tepat',
        'Bimbing dengan pairing session, arahkan ke docs, beri context bukan fish',
        ['Kerjakan semua sendiri', 'Abaikan permintaan', 'Salahkan junior publicly'],
        'Mentoring — grow team capability (DORA: generative culture).',
    ],
    [
        'Variabel $d vs $daysUntilPaymentDue pada kode PHP. Prinsip clean code manakah yang paling relevan',
        'Penamaan variabel harus jelas dan menggambarkan maksud (meaningful naming)',
        ['Variabel cukup 1 huruf agar ringkas', 'Tidak perlu konvensi penamaan', 'Wajib Hungarian notation'],
        'Clean Code (Robert C. Martin): nama variabel harus self-documenting.',
    ],
];
for ($n = 0; $n < 25; $n++) {
    $p = $practices[$n];
    $questions[] = $make($p[0], $p[1], $p[2], $p[3], 300 + $n);
}

return array_slice($questions, 0, 100);
