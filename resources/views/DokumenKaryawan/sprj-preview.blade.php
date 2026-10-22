<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Live Preview SPRJ</title>
    <style>
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body {
            display: flex;
            flex-direction: column;
            background: #202124;
            color: #f1f3f4;
            font: 14px Arial, sans-serif;
        }
        .toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 54px;
            padding: 8px 14px;
            background: #303134;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .45);
        }
        .title { margin-right: 12px; font-weight: 700; }
        label { color: #bdc1c6; }
        input {
            width: 92px;
            padding: 7px 8px;
            border: 1px solid #5f6368;
            border-radius: 4px;
            background: #202124;
            color: #fff;
        }
        button {
            padding: 8px 12px;
            border: 0;
            border-radius: 4px;
            background: #8ab4f8;
            color: #202124;
            font-weight: 700;
            cursor: pointer;
        }
        .status {
            margin-left: auto;
            color: #81c995;
            white-space: nowrap;
        }
        iframe {
            width: 100%;
            flex: 1;
            border: 0;
            background: #525659;
        }
    </style>
</head>
<body>
    <form class="toolbar" method="get">
        <span class="title">Live Preview SPRJ</span>
        <label for="karyawan_id">Karyawan ID</label>
        <input id="karyawan_id" name="karyawan_id" type="number" min="1" value="{{ $karyawanId }}" required>
        <label for="new_recruitment_id">Recruitment ID</label>
        <input id="new_recruitment_id" name="new_recruitment_id" type="number" min="1" value="{{ $recruitmentId }}" required>
        <button type="submit">Ganti Data</button>
        <button id="reload" type="button">Refresh</button>
        <span id="status" class="status">Memuat preview...</span>
    </form>

    <iframe id="preview" title="Preview PDF SPRJ"></iframe>

    <script>
        const karyawanId = @json($karyawanId);
        const recruitmentId = @json($recruitmentId);
        const preview = document.getElementById('preview');
        const status = document.getElementById('status');
        const query = new URLSearchParams({
            karyawan_id: karyawanId,
            new_recruitment_id: recruitmentId,
        });
        const pdfUrl = '/dev/dokumen-karyawan/sprj-preview.pdf?' + query.toString();
        const versionUrl = '/dev/dokumen-karyawan/sprj-preview/version';
        let currentVersion = null;
        let refreshing = false;

        function refreshPreview() {
            refreshing = true;
            status.textContent = 'Membuat ulang PDF...';
            preview.src = pdfUrl + '&_=' + Date.now();
        }

        preview.addEventListener('load', function () {
            refreshing = false;
            status.textContent = 'Aktif — otomatis refresh saat template disimpan';
        });

        document.getElementById('reload').addEventListener('click', refreshPreview);
        refreshPreview();

        setInterval(async function () {
            if (refreshing) return;

            try {
                const response = await fetch(versionUrl + '?_=' + Date.now(), { cache: 'no-store' });
                const data = await response.json();

                if (currentVersion === null) {
                    currentVersion = data.version;
                } else if (currentVersion !== data.version) {
                    currentVersion = data.version;
                    refreshPreview();
                }
            } catch (error) {
                status.textContent = 'Gagal memeriksa perubahan template';
            }
        }, 750);
    </script>
</body>
</html>
