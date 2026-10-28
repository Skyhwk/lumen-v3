<style>
    body { font-family: 'Times New Roman', Times, serif; font-size: 12pt; color: #111; line-height: 1.45; }
    .center { text-align: center; }
    .bold { font-weight: bold; }
    .underline { text-decoration: underline; }
    .mt-16 { margin-top: 16px; }
    .mt-24 { margin-top: 24px; }
    .signature { margin-top: 48px; width: 100%; }
    .signature td { width: 50%; vertical-align: top; }
    .meta-table { width: 100%; border-collapse: collapse; margin: 12px 0; }
    .meta-table td { padding: 4px 0; vertical-align: top; }
    .meta-label { width: 130px; }
    .meta-sep { width: 12px; }
</style>

<div class="center bold" style="font-size: 14pt; text-transform: uppercase;">
    Surat Peringatan {{ $warningTypeLabel }}
</div>
<div class="center mt-16">Nomor: {{ $documentNumber }}</div>

<p class="mt-24">
    Yang bertanda tangan di bawah ini, pihak Human Resources PT Inti Surya Laboratorium,
    dengan ini memberikan surat peringatan kepada:
</p>

<table class="meta-table">
    <tr>
        <td class="meta-label">Nama</td>
        <td class="meta-sep">:</td>
        <td>{{ $employeeName }}</td>
    </tr>
    <tr>
        <td class="meta-label">NIK</td>
        <td class="meta-sep">:</td>
        <td>{{ $employeeNik }}</td>
    </tr>
    <tr>
        <td class="meta-label">Divisi</td>
        <td class="meta-sep">:</td>
        <td>{{ $divisionName }}</td>
    </tr>
    <tr>
        <td class="meta-label">Jabatan</td>
        <td class="meta-sep">:</td>
        <td>{{ $positionName }}</td>
    </tr>
</table>

<p>
    Berdasarkan ketentuan perusahaan yang berlaku, karyawan tersebut di atas telah melakukan
    pelanggaran disiplin dengan rincian sebagai berikut:
</p>

<table class="meta-table">
    <tr>
        <td class="meta-label">Tanggal kejadian</td>
        <td class="meta-sep">:</td>
        <td>{{ $incidentDateLabel }}</td>
    </tr>
    <tr>
        <td class="meta-label">Jenis pelanggaran</td>
        <td class="meta-sep">:</td>
        <td>{{ $violationCategory }}</td>
    </tr>
</table>

<div>{!! $violationDescription !!}</div>

@if(!empty($previousReference))
    <p>Surat ini merupakan lanjutan dari {{ $previousReference }}.</p>
@endif

<p class="mt-16">
    Surat Peringatan ini diterbitkan pada {{ $issueDateLong }} agar karyawan bersangkutan
    memperbaiki sikap dan kinerja serta mematuhi peraturan perusahaan.
    Apabila terjadi pelanggaran serupa, perusahaan akan mengambil tindakan disiplin berikutnya
    sesuai ketentuan yang berlaku.
</p>

<table class="signature">
    <tr>
        <td></td>
        <td class="center">
            Tangerang, {{ $issueDateShort }}<br>
            Human Resources<br><br><br><br>
            _________________________
        </td>
    </tr>
</table>

<p style="font-size: 9pt; color: #555; margin-top: 24px;">
    Dokumen ini digenerate secara elektronik melalui sistem Disipliner — Surat Peringatan.
</p>
