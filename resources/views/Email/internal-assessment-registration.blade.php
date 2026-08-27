<!doctype html>
<html lang="id">
<body style="margin:0;background:#f3f6fb;font-family:Arial,sans-serif;color:#17324d">
<div style="max-width:620px;margin:0 auto;padding:32px 16px">
    <div style="background:#ffffff;border-radius:14px;padding:32px;box-shadow:0 8px 28px rgba(23,50,77,.10)">
        <div style="font-size:13px;font-weight:700;letter-spacing:.08em;color:#1677c8">PT INTI SURYA LABORATORIUM</div>
        <h2 style="margin:14px 0 18px">Pendaftaran Assessment Berhasil</h2>
        <p>Halo {{ $name }},</p>
        <p>Email Anda telah terdaftar sebagai peserta <strong>{{ $assessmentName }}</strong>.</p>
        <p>Gunakan tombol berikut untuk membuka atau melanjutkan assessment Anda:</p>
        <p style="margin:28px 0"><a href="{{ $assessmentUrl }}" style="display:inline-block;background:#1677c8;color:#fff;text-decoration:none;padding:13px 22px;border-radius:8px;font-weight:700">Buka Assessment</a></p>
        <p style="font-size:13px;color:#64748b">Jika tombol tidak dapat dibuka, salin tautan berikut:<br><a href="{{ $assessmentUrl }}">{{ $assessmentUrl }}</a></p>
        <p style="margin-top:28px">Salam,<br><strong>HR Department<br>PT Inti Surya Laboratorium</strong></p>
    </div>
</div>
</body>
</html>
