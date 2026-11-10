<!DOCTYPE html>
<html lang="id" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  {{-- Paksa skema terang: jangan ikut dark mode aplikasi email --}}
  <meta name="color-scheme" content="light only">
  <meta name="supported-color-schemes" content="light only">
  <title>Pengingat Jadwal Sampling H-14</title>
  <style type="text/css">
    :root {
      color-scheme: light only;
      supported-color-schemes: light only;
    }
    body, table, td, th, p, div, span, a {
      color-scheme: light only;
    }

    /*
     * Override dark mode (iOS Mail, Apple Mail, sebagian Android/Outlook web).
     * Warna desain dipaksa kembali agar tidak di-invert tema gelap.
     */
    @media (prefers-color-scheme: dark) {
      .dm-outer { background-color: #f1f5f9 !important; }
      .dm-card { background-color: #ffffff !important; border-color: #E0E2F5 !important; }
      .dm-white { background-color: #ffffff !important; color: #1f2937 !important; }
      .dm-soft { background-color: #f8fafc !important; color: #64748b !important; }
      .dm-blue { background-color: #0304EC !important; color: #ffffff !important; }
      .dm-blue-soft { background-color: #EEF0FF !important; border-color: #C7CAFF !important; color: #0f172a !important; }
      .dm-label { background-color: #F5F6FF !important; color: #475569 !important; }
      .dm-text { color: #111827 !important; }
      .dm-muted { color: #475569 !important; }
      .dm-title { color: #0304EC !important; }
      .dm-warn { background-color: #fffbeb !important; border-color: #fcd34d !important; color: #92400e !important; }
      .dm-link { color: #0304EC !important; }
      .dm-btn-outline { background-color: #ffffff !important; border-color: #0304EC !important; }
      .dm-btn-outline a { color: #0304EC !important; }
      .dm-btn-solid { background-color: #0304EC !important; }
      .dm-btn-solid a { color: #ffffff !important; }
    }

    /* Outlook.com / beberapa client Android */
    [data-ogsc] .dm-outer,
    [data-ogsb] .dm-outer { background-color: #f1f5f9 !important; }
    [data-ogsc] .dm-card,
    [data-ogsb] .dm-card { background-color: #ffffff !important; }
    [data-ogsc] .dm-white,
    [data-ogsb] .dm-white { background-color: #ffffff !important; color: #1f2937 !important; }
    [data-ogsc] .dm-blue,
    [data-ogsb] .dm-blue { background-color: #0304EC !important; color: #ffffff !important; }
    [data-ogsc] .dm-text,
    [data-ogsb] .dm-text { color: #111827 !important; }
    [data-ogsc] .dm-title,
    [data-ogsb] .dm-title { color: #0304EC !important; }

    .table-scroll {
      width: 100%;
      max-width: 100%;
      overflow-x: auto !important;
      overflow-y: hidden;
      -webkit-overflow-scrolling: touch;
      border: 1px solid #E0E2F5;
      border-radius: 10px;
      background-color: #ffffff;
    }
    .table-scroll table {
      min-width: 520px;
      width: 520px !important;
      max-width: none !important;
      border: 0 !important;
      border-radius: 0 !important;
    }

    @media only screen and (max-width: 480px) {
      .outer { padding: 8px 6px !important; }
      .pad { padding-left: 18px !important; padding-right: 18px !important; }
      .big-date { font-size: 20px !important; }
      .table-scroll { display: block !important; width: 100% !important; overflow-x: auto !important; }
      .table-scroll table { width: 520px !important; min-width: 520px !important; }
    }
  </style>
  <!--[if mso]>
  <style type="text/css">
    body, table, td { font-family: Arial, Helvetica, sans-serif !important; }
  </style>
  <![endif]-->
</head>
<body class="dm-outer" bgcolor="#f1f5f9" style="margin:0;padding:0;background-color:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#1f2937;line-height:1.6;-webkit-text-size-adjust:100%;">
@php
  $sectionTitle = 'margin:30px 0 6px;font-size:17px;line-height:1.35;color:#0304EC;font-weight:700;';
  $sectionHint  = 'margin:0 0 12px;font-size:14px;line-height:1.55;color:#475569;';
  $card         = 'border:1px solid #E0E2F5;border-radius:10px;border-collapse:separate;border-spacing:0;overflow:hidden;width:100%;background-color:#ffffff;';
  $lbl          = 'padding:11px 14px;border-top:1px solid #E0E2F5;background-color:#F5F6FF;font-size:13px;font-weight:700;color:#475569;width:38%;vertical-align:top;';
  $val          = 'padding:11px 14px;border-top:1px solid #E0E2F5;background-color:#ffffff;font-size:15px;color:#111827;vertical-align:top;word-break:break-word;';
  $th           = 'padding:10px 14px;background-color:#0304EC;color:#ffffff;font-size:13px;font-weight:700;text-align:left;';
  $cell         = 'padding:11px 14px;border-top:1px solid #E0E2F5;background-color:#ffffff;font-size:15px;color:#111827;vertical-align:top;word-break:break-word;';
  $empty        = 'margin:0;padding:12px 14px;border:1px dashed #cbd5e1;border-radius:10px;font-size:14px;color:#64748b;background-color:#ffffff;';

  $picDisplay = [];
  foreach (($picRows ?? []) as $r) {
      $p   = $r['pic'] ?? [];
      $lab = trim((string) ($r['label'] ?? ''));
      $key = mb_strtolower(implode('|', array_map(function ($v) { return trim((string) $v); },
                 [$p['nama'] ?? '', $p['jabatan'] ?? '', $p['no'] ?? '', $p['email'] ?? ''])));
      if (isset($picDisplay[$key])) {
          if ($lab !== '' && !in_array($lab, $picDisplay[$key]['labels'], true)) {
              $picDisplay[$key]['labels'][] = $lab;
          }
      } else {
          $picDisplay[$key] = ['labels' => [$lab], 'pic' => $p];
      }
  }
@endphp

  <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#f1f5f9;">
    Sampling dijadwalkan pada {{ $payload['tanggal_label'] }}. Mohon pastikan lokasi dan PIC sudah siap.
    &nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
  </div>

  <table role="presentation" class="dm-outer" width="100%" cellspacing="0" cellpadding="0" bgcolor="#f1f5f9" style="background-color:#f1f5f9;">
    <tr>
      <td align="center" class="outer" bgcolor="#f1f5f9" style="padding:20px 12px;background-color:#f1f5f9;">

        <table role="presentation" class="dm-card" width="100%" cellspacing="0" cellpadding="0" bgcolor="#ffffff" style="max-width:640px;background-color:#ffffff;border:1px solid #E0E2F5;border-radius:12px;overflow:hidden;">

          <tr>
            <td class="pad dm-white" bgcolor="#ffffff" style="background-color:#ffffff;padding:18px 28px 14px;border-bottom:3px solid #D71919;">
              <div class="dm-title" style="font-size:24px;line-height:1.1;font-weight:800;letter-spacing:.02em;color:#0304EC;">INTI SURYA</div>
              <div class="dm-title" style="font-size:10px;line-height:1.4;font-weight:700;letter-spacing:.46em;color:#0304EC;margin-top:3px;">LABORATORIUM</div>
            </td>
          </tr>
          <tr>
            <td class="pad dm-blue" bgcolor="#0304EC" style="background-color:#0304EC;padding:20px 28px;">
              <div style="font-size:22px;line-height:1.3;font-weight:700;color:#ffffff;">Pengingat Jadwal Sampling</div>
              <div style="font-size:13px;color:#DDE0FF;margin-top:6px;">Dikirim 14 hari sebelum tanggal sampling (H-14)</div>
            </td>
          </tr>

          <tr>
            <td class="pad dm-white" bgcolor="#ffffff" style="padding:26px 28px 30px;background-color:#ffffff;color:#1f2937;">

              <p class="dm-text" style="margin:0 0 12px;font-size:16px;color:#1f2937;">Yth. Bapak/Ibu PIC,</p>
              <p class="dm-text" style="margin:0 0 20px;font-size:16px;color:#1f2937;">
                Kami mengingatkan bahwa sampling di perusahaan Anda akan dilaksanakan sesuai jadwal di bawah ini.
                Mohon periksa kembali kesiapan lokasi dan PIC.
              </p>

              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0;">
                <tr>
                  <td class="dm-blue-soft" bgcolor="#EEF0FF" style="padding:18px 20px;background-color:#EEF0FF;border:1px solid #C7CAFF;border-radius:10px;">
                    <div class="dm-title" style="font-size:12px;font-weight:700;letter-spacing:.04em;color:#0304EC;">Tanggal Sampling</div>
                    <div class="big-date dm-text" style="font-size:24px;line-height:1.3;font-weight:700;color:#0f172a;margin-top:4px;">{{ $payload['tanggal_label'] }}</div>
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:14px;">
                      <tr>
                        <td width="50%" style="vertical-align:top;padding-right:8px;">
                          <div class="dm-muted" style="font-size:12px;font-weight:700;color:#475569;">Jam Mulai</div>
                          <div class="dm-text" style="font-size:16px;font-weight:700;color:#111827;margin-top:2px;">{{ $payload['jam_mulai'] }}</div>
                        </td>
                        <td width="50%" style="vertical-align:top;padding-left:8px;">
                          <div class="dm-muted" style="font-size:12px;font-weight:700;color:#475569;">Durasi</div>
                          <div class="dm-text" style="font-size:16px;font-weight:700;color:#111827;margin-top:2px;">{{ $payload['durasi_label'] }}</div>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>

              <h3 class="dm-title" style="{{ $sectionTitle }}">Informasi Dokumen</h3>
              <table role="presentation" class="dm-card" width="100%" cellspacing="0" cellpadding="0" bgcolor="#ffffff" style="{{ $card }}">
                <tr>
                  <td class="dm-label" bgcolor="#F5F6FF" style="{{ $lbl }}border-top:0;">No Quotation</td>
                  <td class="dm-text" bgcolor="#ffffff" style="{{ $val }}border-top:0;">{{ $payload['no_quotation'] }}</td>
                </tr>
                <tr>
                  <td class="dm-label" bgcolor="#F5F6FF" style="{{ $lbl }}">No Order</td>
                  <td class="dm-text" bgcolor="#ffffff" style="{{ $val }}">{{ $payload['no_order'] }}</td>
                </tr>
                <tr>
                  <td class="dm-label" bgcolor="#F5F6FF" style="{{ $lbl }}">Perusahaan</td>
                  <td class="dm-text" bgcolor="#ffffff" style="{{ $val }}">{{ $payload['nama_perusahaan'] ?: '-' }}</td>
                </tr>
              </table>

              <h3 class="dm-title" style="{{ $sectionTitle }}">PIC Terdaftar</h3>
              @if(empty($picDisplay))
                <p class="dm-muted" style="{{ $empty }}">Belum ada data PIC.</p>
              @else
                @foreach($picDisplay as $row)
                  <table role="presentation" class="dm-card" width="100%" cellspacing="0" cellpadding="0" bgcolor="#ffffff" style="{{ $card }}margin-bottom:12px;">
                    <tr>
                      <td colspan="2" class="dm-blue" bgcolor="#0304EC" style="padding:10px 14px;background-color:#0304EC;color:#ffffff;font-size:14px;font-weight:700;">{{ implode(' & ', array_filter($row['labels'])) }}</td>
                    </tr>
                    <tr>
                      <td class="dm-label" bgcolor="#F5F6FF" style="{{ $lbl }}border-top:0;">Nama</td>
                      <td class="dm-text" bgcolor="#ffffff" style="{{ $val }}border-top:0;">{{ $row['pic']['nama'] ?: '-' }}</td>
                    </tr>
                    <tr>
                      <td class="dm-label" bgcolor="#F5F6FF" style="{{ $lbl }}">Jabatan</td>
                      <td class="dm-text" bgcolor="#ffffff" style="{{ $val }}">{{ $row['pic']['jabatan'] ?: '-' }}</td>
                    </tr>
                    <tr>
                      <td class="dm-label" bgcolor="#F5F6FF" style="{{ $lbl }}">Kontak</td>
                      <td class="dm-text" bgcolor="#ffffff" style="{{ $val }}">{{ $row['pic']['no'] ?: '-' }}</td>
                    </tr>
                    <tr>
                      <td class="dm-label" bgcolor="#F5F6FF" style="{{ $lbl }}">Email</td>
                      <td class="dm-text" bgcolor="#ffffff" style="{{ $val }}">{{ $row['pic']['email'] ?: '-' }}</td>
                    </tr>
                  </table>
                @endforeach
              @endif

              <h3 class="dm-title" style="{{ $sectionTitle }}">Kategori yang akan diambil</h3>
              @if(empty($payload['kategori_summary']))
                <p class="dm-muted" style="{{ $empty }}">Belum ada data kategori.</p>
              @else
                {{-- Wrapper scroll horizontal: lebar kolom tetap, di mobile digeser ke samping --}}
                <div class="table-scroll dm-card" style="width:100%;max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;border:1px solid #E0E2F5;border-radius:10px;background-color:#ffffff;">
                  <table role="presentation" width="520" cellspacing="0" cellpadding="0" bgcolor="#ffffff" style="width:520px;min-width:520px;border-collapse:separate;border-spacing:0;background-color:#ffffff;">
                    <tr>
                      <td width="56" class="dm-blue" bgcolor="#0304EC" style="{{ $th }}width:56px;text-align:center;white-space:nowrap;">No</td>
                      <td width="340" class="dm-blue" bgcolor="#0304EC" style="{{ $th }}width:340px;white-space:nowrap;">Kategori</td>
                      <td width="124" class="dm-blue" bgcolor="#0304EC" style="{{ $th }}width:124px;text-align:center;white-space:nowrap;">Jumlah</td>
                    </tr>
                    @foreach($payload['kategori_summary'] as $i => $kat)
                      <tr>
                        <td width="56" class="dm-muted" bgcolor="#ffffff" style="{{ $cell }}width:56px;text-align:center;color:#475569;white-space:nowrap;">{{ $i + 1 }}</td>
                        <td width="340" class="dm-text" bgcolor="#ffffff" style="{{ $cell }}width:340px;white-space:nowrap;">{{ $kat['nama'] }}</td>
                        <td width="124" class="dm-text" bgcolor="#ffffff" style="{{ $cell }}width:124px;text-align:center;white-space:nowrap;"><strong>{{ $kat['count'] }}</strong></td>
                      </tr>
                    @endforeach
                  </table>
                </div>
              @endif

              <h3 class="dm-title" style="{{ $sectionTitle }}">Daftar Sampler</h3>
              @if(empty($payload['sampler_list']))
                <p class="dm-muted" style="{{ $empty }}">Belum ada data sampler.</p>
              @else
                <table role="presentation" class="dm-card" width="100%" cellspacing="0" cellpadding="0" bgcolor="#ffffff" style="{{ $card }}">
                  <tr>
                    <td width="48" class="dm-blue" bgcolor="#0304EC" style="{{ $th }}text-align:center;">No</td>
                    <td class="dm-blue" bgcolor="#0304EC" style="{{ $th }}">Nama Sampler</td>
                  </tr>
                  @foreach($payload['sampler_list'] as $i => $sampler)
                    <tr>
                      <td width="48" class="dm-muted" bgcolor="#ffffff" style="{{ $cell }}text-align:center;color:#475569;">{{ $i + 1 }}</td>
                      <td class="dm-text" bgcolor="#ffffff" style="{{ $cell }}">{{ $sampler }}</td>
                    </tr>
                  @endforeach
                </table>
              @endif

              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:14px;border-collapse:separate;border-spacing:0;">
                <tr>
                  <td class="dm-warn" bgcolor="#fffbeb" style="padding:12px 14px;border:1px solid #fcd34d;border-radius:10px;background-color:#fffbeb;font-size:14px;color:#92400e;">
                    <strong>Catatan:</strong> sampler yang bertugas dapat berubah sesuai dengan kondisi di lapangan.
                  </td>
                </tr>
              </table>

              <h3 class="dm-title" style="{{ $sectionTitle }}">Ada pertanyaan?</h3>
              <p class="dm-text" style="margin:0 0 12px;font-size:15px;color:#1f2937;">
                Apabila ada pertanyaan, silakan hubungi sales kami:<br>
                <strong>{{ $salesName }}</strong> ({{ $salesKontak }})
              </p>
              <p class="dm-text" style="margin:0 0 14px;font-size:15px;color:#1f2937;">
                Anda juga dapat menghubungi kami melalui portal pelanggan, atau mengunduh aplikasi kami melalui Play Store.
              </p>

              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0;">
                <tr>
                  <td align="center" class="dm-btn-solid" bgcolor="#0304EC" style="border-radius:10px;background-color:#0304EC;">
                    <a href="{{ $portal }}" style="display:block;padding:14px 18px;font-size:16px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:10px;">Buka Portal Pelanggan</a>
                  </td>
                </tr>
                <tr><td style="height:10px;line-height:10px;font-size:10px;">&nbsp;</td></tr>
                <tr>
                  <td align="center" class="dm-btn-outline dm-white" bgcolor="#ffffff" style="border-radius:10px;border:2px solid #0304EC;background-color:#ffffff;">
                    <a class="dm-link" href="{{ $playstore }}" style="display:block;padding:12px 18px;font-size:16px;font-weight:700;color:#0304EC;text-decoration:none;border-radius:10px;">Unduh Aplikasi di Play Store</a>
                  </td>
                </tr>
              </table>
              <p class="dm-text" style="margin:26px 0 0;font-size:15px;color:#1f2937;">
                Terima kasih.<br>
                <strong>INTI SURYA LABORATORIUM</strong>
              </p>
            </td>
          </tr>

          <tr>
            <td class="pad dm-soft" bgcolor="#f8fafc" style="padding:16px 28px;background-color:#f8fafc;border-top:1px solid #E0E2F5;font-size:12px;line-height:1.5;color:#64748b;">
              Email ini dikirim otomatis oleh sistem kami sebagai pengingat jadwal sampling.
            </td>
          </tr>
        </table>

      </td>
    </tr>
  </table>
</body>
</html>
