<?php

/**
 * 100 soal SOFTWARE ENGINEERING untuk assessment programmer (manager scope).
 * Git, API, keamanan, best practice tim dev.
 * Return: array of ['text','options','answer','explanation']
 */

$rotate = static function (array $options, int $seed): array {
    $shift = abs($seed) % max(1, count($options));
    return array_merge(array_slice($options, $shift), array_slice($options, 0, $shift));
};

$make = static function (string $text, $correct, array $wrong, string $explanation, int $seed) use ($rotate): array {
    $options = $rotate(array_merge([(string) $correct], array_map('strval', array_slice($wrong, 0, 3))), $seed);
    return [
        'text' => $text,
        'options' => $options,
        'answer' => array_search((string) $correct, $options, true),
        'explanation' => $explanation,
    ];
};

$questions = [];

// ── 1. Git & version control (25) ────────────────────────────────────────────
$git = [
    ['git commit fungsinya', 'Menyimpan snapshot perubahan ke repository lokal', ['Push ke remote', 'Merge branch otomatis', 'Hapus branch'], 'Commit = snapshot local history.'],
    ['git push fungsinya', 'Mengirim commit ke remote repository', ['Commit lokal saja', 'Pull dari remote', 'Reset hard'], 'Upload commits to remote.'],
    ['git pull = fetch +', 'merge (atau rebase tergantung config)', ['commit', 'push', 'stash pop always'], 'Pull update local from remote.'],
    ['git branch fungsinya', 'Pointer ke line of development terpisah', ['Replace commit', 'Delete remote', 'Encrypt repo'], 'Branch isolasi fitur.'],
    ['git merge vs rebase: rebase', 'Replay commits on top of target branch (linear history)', ['Selalu merge commit', 'Hapus history', 'Tidak ubah commit'], 'Rebase rewrite history linear.'],
    ['Merge conflict terjadi ketika', 'Same lines changed differently di kedua branch', ['Branch berbeda nama', 'Commit message sama', 'Remote berbeda'], 'Git cannot auto-merge.'],
    ['git stash berguna untuk', 'Simpan perubahan sementara tanpa commit', ['Hapus branch', 'Force push', 'Delete remote'], 'Temporary WIP storage.'],
    ['git revert vs reset: revert', 'Buat commit baru yang undo perubahan (safe shared history)', ['Hapus history public', 'Force push required', 'Tidak untuk shared branch'], 'Revert non-destructive for shared.'],
    ['git reset --hard', 'Discard local changes & move HEAD (destructive)', ['Safe for pushed commits', 'Creates merge commit', 'Only stages files'], 'Hard reset loses uncommitted work.'],
    ['.gitignore fungsinya', 'Exclude files from git tracking', ['Encrypt files', 'Compress repo', 'Replace .env'], 'Ignore build artifacts, secrets.'],
    ['Pull Request / Merge Request purpose', 'Code review sebelum merge ke main', ['Deploy production', 'Run unit test only local', 'Replace CI'], 'Review gate before merge.'],
    ['Conventional Commits benefit', 'Readable history, automate changelog', ['Faster push', 'No branch needed', 'Replace tests'], 'feat:, fix:, docs: etc.'],
    ['git cherry-pick', 'Apply specific commit(s) to current branch', ['Delete commit', 'Merge all branches', 'Clone repo'], 'Port individual commits.'],
    ['git tag biasanya untuk', 'Mark release versions (v1.0.0)', ['Daily commits', 'Branch names', 'Ignore files'], 'Release markers.'],
    ['Protected branch rule', 'Restrict direct push, require PR/review', ['Allow force push all', 'No CI', 'Delete main freely'], 'Guard production branch.'],
    ['git bisect digunakan untuk', 'Find commit that introduced bug', ['Merge conflict', 'Create tag', 'Clone shallow'], 'Binary search history.'],
    ['Fork vs branch (GitHub flow)', 'Fork copy repo for external contributor', ['Same as branch local', 'Replace clone', 'Delete upstream'], 'Fork for cross-repo contribution.'],
    ['Squash merge benefit', 'Single commit per feature on main', ['Keep all WIP commits', 'No history', 'Skip review'], 'Clean main history.'],
    ['git fetch vs pull: fetch', 'Download remote changes without merge', ['Auto merge always', 'Push changes', 'Delete local'], 'Fetch update refs only.'],
    ['Detached HEAD state artinya', 'HEAD points to commit, not branch tip', ['Repo corrupted', 'Cannot commit', 'Remote deleted'], 'Checkout commit directly.'],
    ['Semantic Versioning MAJOR bump when', 'Breaking API change', ['Bug fix only', 'New backward-compatible feature', 'Documentation only'], 'MAJOR = incompatible change.'],
    ['git blame / annotate shows', 'Who last modified each line', ['Test coverage', 'Branch graph', 'Remote URL'], 'Line-level authorship.'],
    ['Monorepo artinya', 'Multiple projects in one repository', ['One file only', 'No branches', 'No CI'], 'Shared repo multi-package.'],
    ['Trunk-based development', 'Short-lived branches, frequent merge to main', ['Long-lived feature branches only', 'No main branch', 'No CI'], 'Integrate often to trunk.'],
    ['Force push (--force) risk on shared branch', 'Overwrite others\' commits, data loss', ['Always safe', 'Auto backup', 'Creates PR'], 'Dangerous on shared history.'],
];
for ($n = 0; $n < 25; $n++) {
    $g = $git[$n];
    $questions[] = $make($g[0], $g[1], $g[2], $g[3], $n);
}

// ── 2. API, HTTP, arsitektur web (25) ──────────────────────────────────────
$api = [
    ['HTTP GET idempotent artinya', 'Multiple identical requests same effect as one', ['Changes server state always', 'Requires body', 'Cannot cache'], 'GET safe & idempotent.'],
    ['HTTP POST typically', 'Create resource / non-idempotent action', ['Always idempotent', 'Only read', 'No body allowed'], 'POST create/submit.'],
    ['HTTP PUT vs PATCH: PATCH', 'Partial update resource', ['Replace entire resource always', 'Delete resource', 'Read only'], 'PATCH partial modify.'],
    ['HTTP 201 status means', 'Created — resource successfully created', ['OK generic', 'Not found', 'Server error'], '201 Created.'],
    ['HTTP 401 vs 403: 401', 'Unauthenticated — identity not verified', ['Forbidden authorized', 'Not found', 'Server error'], '401 = who are you?'],
    ['HTTP 403 artinya', 'Forbidden — authenticated but no permission', ['Not logged in', 'Resource not found', 'Rate limit only'], '403 = no access rights.'],
    ['REST constraint: stateless', 'Each request contains all info needed', ['Server stores session always', 'No HTTP methods', 'No JSON'], 'No server session state.'],
    ['RESTful resource naming best practice', 'Nouns plural: /users, /orders', ['Verbs in URL: /getUsers', 'SQL in path', 'Mixed case required'], 'Resources as nouns.'],
    ['API versioning common approach', '/v1/users or Accept header versioning', ['No versioning ever', 'Version in body only hidden', 'Random URL'], 'Explicit API version.'],
    ['JWT structure', 'header.payload.signature', ['payload only', 'encrypted always', 'session id only'], 'Three base64 parts.'],
    ['OAuth 2.0 authorization code flow', 'Redirect user to auth server, exchange code for token', ['Password in URL', 'No redirect', 'Client secret in frontend only'], 'Secure for web apps.'],
    ['CORS purpose', 'Browser cross-origin access control', ['Database encryption', 'SQL injection prevention', 'Git merge'], 'Cross-Origin Resource Sharing.'],
    ['Content-Type application/json means', 'Request/response body is JSON', ['Binary file always', 'HTML only', 'No charset'], 'JSON payload format.'],
    ['Rate limiting purpose', 'Prevent abuse, protect server resources', ['Speed up API', 'Replace auth', 'Encrypt data'], 'Throttle request frequency.'],
    ['Pagination API common params', 'page & limit or cursor', ['sort only', 'no limit ever', 'full dump always'], 'Limit result set size.'],
    ['Idempotency key (POST payments) prevents', 'Duplicate charges on retry', ['Slow response', 'Auth failure', 'CORS error'], 'Same key → same result.'],
    ['Webhook artinya', 'Server push HTTP callback on event', ['Polling every second', 'WebSocket only', 'FTP upload'], 'Event-driven HTTP POST.'],
    ['GraphQL vs REST: GraphQL client', 'Request exact fields needed in one query', ['Always multiple round trips', 'No schema', 'Replace HTTP'], 'Flexible field selection.'],
    ['Microservices trade-off', 'Independent deploy vs operational complexity', ['Always simpler', 'No network calls', 'Single DB always'], 'Distributed system overhead.'],
    ['Load balancer function', 'Distribute traffic across servers', ['Encrypt DB', 'Compile code', 'Git merge'], 'High availability scaling.'],
    ['Reverse proxy (nginx) role', 'Forward client requests to backend servers', ['Replace database', 'Write business logic', 'Git hosting'], 'Gateway to app servers.'],
    ['Cache-Control: max-age', 'Browser/CDN cache duration seconds', ['Force no cache always', 'Auth token expiry', 'DB timeout'], 'HTTP caching directive.'],
    ['ETag header used for', 'Conditional requests, cache validation', ['Encryption', 'CORS', 'JWT signing'], 'Resource version fingerprint.'],
    ['OpenAPI/Swagger spec purpose', 'Document & generate API contracts', ['Replace tests', 'Database schema', 'Git config'], 'Machine-readable API docs.'],
    ['HATEOAS in REST', 'Hypermedia links guide available actions', ['No links in response', 'SOAP only', 'GraphQL only'], 'Links in response body.'],
];
for ($n = 0; $n < 25; $n++) {
    $a = $api[$n];
    $questions[] = $make($a[0], $a[1], $a[2], $a[3], 100 + $n);
}

// ── 3. Keamanan aplikasi (25) ────────────────────────────────────────────────
$security = [
    ['SQL Injection prevention best practice', 'Parameterized queries / prepared statements', ['String concat user input', 'Hide SQL in comments', 'Disable HTTPS'], 'Never interpolate raw input.'],
    ['XSS (Cross-Site Scripting) prevention', 'Escape output, CSP, sanitize input', ['Store password plain', 'Disable CORS always', 'Use GET for login'], 'Prevent script injection in browser.'],
    ['CSRF protection common method', 'Anti-CSRF token per session/form', ['Disable cookies', 'Use HTTP only', 'Remove auth'], 'Verify request origin/token.'],
    ['Password storage must use', 'Strong hash + salt (bcrypt, Argon2)', ['MD5 alone', 'Plain text', 'Base64 encode only'], 'Adaptive hashing algorithms.'],
    ['HTTPS/TLS protects', 'Data in transit encryption & integrity', ['Data at rest on disk', 'SQL queries', 'Git history'], 'Transport layer security.'],
    ['Principle of Least Privilege', 'Grant minimum access needed', ['Admin for everyone', 'Shared one account', 'Root DB user in app'], 'Minimize permission scope.'],
    ['Secrets (.env, API keys) should', 'Never commit to git, use secret manager', ['Commit to public repo', 'Hardcode in frontend', 'Share via chat'], 'Keep secrets out of VCS.'],
    ['JWT stored in httpOnly cookie benefit', 'Not accessible via JavaScript (XSS mitigation)', ['Always in localStorage best', 'URL query param', 'Console log'], 'httpOnly reduces XSS token theft.'],
    ['Input validation should happen', 'Server-side always (client-side additional)', ['Client-side only', 'Never validate', 'Database only'], 'Never trust client alone.'],
    ['OWASP Top 10 purpose', 'Awareness of common web vulnerabilities', ['Replace coding standards', 'Git workflow', 'UI design'], 'Security risk catalog.'],
    ['Dependency vulnerability scanning', 'Check libraries for known CVEs', ['Ignore package updates', 'Disable npm audit', 'Pin forever no update'], 'Supply chain security.'],
    ['Security headers: X-Content-Type-Options nosniff', 'Prevent MIME type sniffing', ['Enable CORS *', 'Disable HTTPS', 'Allow iframe all'], 'nosniff header.'],
    ['Content-Security-Policy (CSP)', 'Restrict sources of scripts/styles/loads', ['Open all origins', 'Replace auth', 'SQL escape'], 'Mitigate XSS injection.'],
    ['Brute force login mitigation', 'Rate limit, lockout, CAPTCHA, MFA', ['Unlimited attempts', 'Log password', 'Disable HTTPS'], 'Slow down credential stuffing.'],
    ['Multi-Factor Authentication (MFA)', 'Something you know + have/biometric', ['Password only enough', 'Same factor twice', 'No second factor'], 'Additional auth factor.'],
    ['Encryption at rest example', 'Encrypt DB disk or sensitive columns', ['HTTPS only enough for DB', 'Base64 is encryption', 'MD5 encrypts'], 'Protect stored data.'],
    ['Logging sensitive data rule', 'Never log passwords, tokens, full PAN', ['Log all request bodies', 'Log JWT secret', 'Log credit card'], 'Redact PII/secrets in logs.'],
    ['Same-Origin Policy', 'Browser restricts cross-origin DOM access', ['Applies to server DB', 'Replaces CORS', 'Git security'], 'Browser security model.'],
    ['Secure cookie flags', 'Secure, HttpOnly, SameSite', ['No flags needed', 'Store in URL', 'Plain HTTP ok'], 'Cookie hardening.'],
    ['Penetration testing purpose', 'Simulate attack find vulnerabilities', ['Replace unit tests', 'Daily git merge', 'UI testing only'], 'Proactive security assessment.'],
    ['Zero-day vulnerability', 'Unknown flaw, no patch yet', ['Patched yesterday', 'Only in Git', 'Only CSS bug'], 'No vendor fix available.'],
    ['Defense in depth', 'Multiple security layers', ['Single firewall enough', 'Security through obscurity only', 'No monitoring'], 'Layered controls.'],
    ['API key in frontend SPA risk', 'Exposed to users — use backend proxy', ['Always safe public', 'Replace OAuth', 'Store in git'], 'Secrets not in client bundle.'],
    ['RBAC (Role-Based Access Control)', 'Permissions assigned via roles', ['One role for all', 'No authorization', 'Password per row'], 'Role → permission mapping.'],
    ['Security incident response first step', 'Contain & assess scope', ['Delete all logs', 'Announce publicly first', 'Ignore until Monday'], 'Contain, then investigate.'],
];
for ($n = 0; $n < 25; $n++) {
    $s = $security[$n];
    $questions[] = $make($s[0], $s[1], $s[2], $s[3], 200 + $n);
}

// ── 4. Best practice & SJT tim dev (25) ──────────────────────────────────────
$practices = [
    ['Code review utama tujuannya', 'Menjaga kualitas, knowledge sharing, deteksi bug', ['Menunda deploy selamanya', 'Ganti pair programming', 'Hapus test'], 'Review sebelum merge.'],
    ['Unit test seharusnya', 'Fast, isolated, repeatable', ['Depend on production DB', 'Manual only', 'Run once a year'], 'F.I.R.S.T principles.'],
    ['DRY principle artinya', 'Don\'t Repeat Yourself — hindari duplikasi logic', ['Delete all comments', 'One file only', 'No functions'], 'Single source of truth.'],
    ['SOLID: Single Responsibility', 'One class/module one reason to change', ['One class does everything', 'No interfaces', 'Global state'], 'SRP separation.'],
    ['Technical debt best handled by', 'Track, prioritize, allocate refactor time', ['Ignore forever', 'Rewrite all at once always', 'No tests needed'], 'Manage debt incrementally.'],
    ['Production bug hotfix flow aman', 'Branch from main → fix → test → PR → deploy', ['Push langsung ke main tanpa review', 'Edit di server production', 'Skip CI'], 'Controlled hotfix pipeline.'],
    ['Environment: staging purpose', 'Pre-production testing mirror prod', ['Replace development', 'Public demo only', 'Backup storage'], 'Validate before prod deploy.'],
    ['Feature flag benefit', 'Toggle feature without deploy rollback', ['Replace git', 'Disable tests', 'Hardcode always'], 'Runtime feature control.'],
    ['CI/CD pipeline runs', 'Automated build, test, deploy on change', ['Manual FTP only', 'Once per year', 'No tests'], 'Continuous integration/delivery.'],
    ['Documentation minimum for API', 'Endpoint, params, auth, examples, errors', ['No docs needed', 'Only README emoji', 'Verbal only'], 'Docs reduce integration friction.'],
    ['Pair programming benefit', 'Real-time review & knowledge transfer', ['Slower always bad', 'Replace all tests', 'Solo only'], 'Collaborative coding.'],
    ['Agile sprint retrospective purpose', 'Improve process, reflect on sprint', ['Skip bugs discussion', 'Replace planning', 'Annual only'], 'Continuous improvement.'],
    ['Definition of Done includes', 'Code review, tests pass, docs updated', ['Code written only', 'No QA', 'Deploy manual untracked'], 'Team agreement on complete.'],
    ['Blameless postmortem focus', 'Learn from incident, improve systems', ['Find who to fire', 'Hide incident', 'No action items'], 'System improvement not blame.'],
    ['Situasi: temukan bug kritis di production malam hari. Tindakan tepat', 'Assess impact, notify on-call/lead, hotfix terkontrol atau rollback', ['Diam sampai pagi', 'Fix langsung di prod tanpa backup', 'Sembunyikan dari tim'], 'Incident response transparan.'],
    ['Situasi: deadline ketat, QA minta delay. Tindakan tepat', 'Komunikasikan risk ke stakeholder, jangan skip test kritis', ['Skip semua test', 'Deploy tanpa review', 'Salahkan QA'], 'Balance risk vs quality.'],
    ['Situasi: rekan push code breaking build ke main. Tindakan tepat', 'Revert/fix segera, komunikasi ke tim, cek CI guard', ['Biarkan broken', 'Blame publicly', 'Disable CI'], 'Restore green build fast.'],
    ['Situasi: diminta hardcode credential agar cepat. Tindakan tepat', 'Tolak, gunakan env/secret manager, jelaskan risiko', ['Hardcode lalu hapus nanti', 'Share via WhatsApp', 'Commit ke git private'], 'Secrets never in source.'],
    ['Situasi: requirement tidak jelas dari user. Tindakan tepat', 'Klarifikasi acceptance criteria sebelum coding', ['Asumsi sendiri', 'Coding dulu tanya belakangan', 'Copy fitur competitor'], 'Clarify before implement.'],
    ['Situasi: legacy code tanpa test mau diubah. Tindakan tepat', 'Tambah test coverage area kritikal dulu, refactor incremental', ['Rewrite total tanpa plan', 'Ubah langsung deploy', 'Tidak usah test'], 'Safety net before change.'],
    ['Situasi: conflict merge kompleks di branch fitur. Tindakan tepat', 'Diskusi dengan author branch lain, test setelah resolve', ['Pilih semua ours/theirs blind', 'Force push', 'Delete branch'], 'Collaborative conflict resolution.'],
    ['Situasi: monitoring alert false positive terus. Tindakan tepat', 'Tune threshold/query, dokumentasi, jangan ignore alert system', ['Disable all alerts', 'Ignore semua', 'Uninstall monitoring'], 'Improve signal-to-noise.'],
    ['Situasi: API breaking change diperlukan. Tindakan tepat', 'Version API (v2), deprecate v1 dengan timeline', ['Break v1 tanpa notice', 'Hide dari client', 'Delete docs'], 'Backward compatibility plan.'],
    ['Situasi: junior minta bantuan terus. Tindakan tepat', 'Bimbing dengan pairing, arahkan ke docs, beri context', ['Kerjakan semua sendiri', 'Abaikan permintaan', 'Salahkan junior'], 'Mentoring grows team.'],
    ['Clean code: meaningful naming means', 'Names reveal intent without excessive comments', ['Single letter always', 'Hungarian only', 'No naming convention'], 'Readable self-documenting code.'],
];
for ($n = 0; $n < 25; $n++) {
    $p = $practices[$n];
    $questions[] = $make($p[0], $p[1], $p[2], $p[3], 300 + $n);
}

return array_slice($questions, 0, 100);
