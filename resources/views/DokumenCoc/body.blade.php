@php
    use Carbon\Carbon;
    Carbon::setLocale('id');

    function indonesianDateFormat($date, $useTime = false)
    {
        if (!$date) {
            return '-';
        }

        return Carbon::parse($date)->translatedFormat('d F Y' . ($useTime ? ' H:i' : ''));
    }

    $titikSampling = json_decode($data->titik_sampling, true);
    $titikSamplingChunks = [];
    if (count($titikSampling) > 4) {
        $left = [];
        $right = [];
        foreach (array_values($titikSampling) as $i => $item) {
            if ($i % 2 == 0) {
                $left[] = $item;
            } else {
                $right[] = $item;
            }
        }

        $max = max(count($left), count($right));
        for ($i = 0; $i < $max; $i++) {
            $titikSamplingChunks[] = [$left[$i] ?? null, $right[$i] ?? null];
        }
    }

    $parameter = json_decode($data->parameter_uji, true);
    $parameterChunks = [];
    if (count($parameter) > 4) {
        $left = [];
        $right = [];

        foreach (array_values($parameter) as $i => $item) {
            if ($i % 2 == 0) {
                $left[] = $item;
            } else {
                $right[] = $item;
            }
        }

        $max = max(count($left), count($right));
        for ($i = 0; $i < $max; $i++) {
            $parameterChunks[] = [$left[$i] ?? null, $right[$i] ?? null];
        }
    }
@endphp

<p style="margin: 0; font-size: 14px;">
    Perihal : <b>Dokumen <i>Chain of Custody (COC)</i></b>
</p>

<p style="margin-top: 18px; font-size: 14px;">
    Dengan ini perusahaan menerangkan rincian dokumen sebagai berikut :
</p>

<table style="width: 100%; font-size: 14px; line-height: 1.2;">
    <tr>
        <td style="width: 38%; vertical-align: top;">Nama Perusahaan</td>
        <td style="width: 2%; vertical-align: top;">:</td>
        <td style="vertical-align: top;">{{ $data->nama_perusahaan }}</td>
    </tr>
    <tr>
        <td style="width: 38%; vertical-align: top;">Alamat Sampling</td>
        <td style="width: 2%; vertical-align: top;">:</td>
        <td style="vertical-align: top;">{{ $data->alamat_sampling }}</td>
    </tr>
    <tr>
        <td style="width: 38%; vertical-align: top;">No. Penawaran</td>
        <td style="width: 2%; vertical-align: top;">:</td>
        <td style="vertical-align: top;">{{ $data->no_penawaran }}</td>
    </tr>
    <tr>
        <td style="width: 38%; vertical-align: top;">No. Order</td>
        <td style="width: 2%; vertical-align: top;">:</td>
        <td style="vertical-align: top;">{{ $data->no_order }}</td>
    </tr>
    <tr>
        <td style="width: 38%; vertical-align: top;">No. LHP</td>
        <td style="width: 2%; vertical-align: top;">:</td>
        <td style="vertical-align: top;">{{ $data->no_lhp }}</td>
    </tr>
    <tr>
        <td style="width: 38%; vertical-align: top;">Titik Lokasi Sampling</td>
        <td style="width: 2%; vertical-align: top;">:</td>
        <td style="vertical-align: top;">
            @if (count($titikSampling) <= 4)
                <table style="width: 100%; border-collapse: collapse;">
                    @foreach ($titikSampling as $item)
                        <tr>
                            <td style="padding: 1px 0; font-size: 12px">
                                {{ count($titikSampling) > 1 ? "- $item" : $item }}
                            </td>
                        </tr>
                    @endforeach
                </table>
            @else
                <table style="width: 100%; border-collapse: collapse;">
                    @foreach ($titikSamplingChunks as $row)
                        <tr>
                            @foreach ($row as $item)
                                @if ($item)
                                    <td style="padding: 1px 0; font-size: 12px">- {{ $item }}</td>
                                @else
                                    <td colspan="3"></td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </table>
            @endif
        </td>
    </tr>
    <tr>
        <td style="width: 38%; vertical-align: top;">Parameter Uji</td>
        <td style="width: 2%; vertical-align: top;">:</td>
        <td style="vertical-align: top;">
            @if (count($parameter) <= 4)
                <table style="width: 100%; border-collapse: collapse;">
                    @foreach ($parameter as $item)
                        <tr>
                            <td style="padding: 1px 0; font-size: 12px">
                                {{ count($regulasi) > 1 ? '- ' : '' }}{{ preg_replace('/^\d+;/', '', $item) }}
                            </td>
                        </tr>
                    @endforeach
                </table>
            @else
                <table style="width: 100%; border-collapse: collapse;">
                    @foreach ($parameterChunks as $row)
                        <tr>
                            @foreach ($row as $item)
                                @if ($item)
                                    <td style="padding: 1px 0; font-size: 12px">
                                        - {{ preg_replace('/^\d+;/', '', $item) }}</td>
                                @else
                                    <td colspan="3"></td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </table>
            @endif
        </td>
    </tr>
    <tr>
        <td style="width: 38%; vertical-align: top;">Regulasi</td>
        <td style="width: 2%; vertical-align: top;">:</td>
        <td style="vertical-align: top;">
            @php
                $regulasi = json_decode($data->regulasi, true);
            @endphp
            <table style="width: 100%; border-collapse: collapse;">
                @foreach ($regulasi as $item)
                    <tr>
                        <td style="padding: 1px 0; font-size: 12px">
                            {{ count($regulasi) > 1 ? '- ' : '' }}{{ preg_replace('/^\d+-/', '', $item) }}
                        </td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>

<p style="margin-top: 15px; font-size: 14px;"><b>Tabel Rangkaian Waktu Kegiatan</b></p>

<table width="100%" cellspacing="0" cellpadding="0" style="border-collapse: collapse; font-size: 12px;">
    <tr>
        <td
            style="width: 35%; text-align: center; border: 1px solid #000; font-weight: bold; padding: 5px; background-color: #f0f0f0;">
            Proses
        </td>
        <td
            style="width: 32.5%; text-align: center; border: 1px solid #000; font-weight: bold; padding: 5px; background-color: #f0f0f0;">
            Waktu Mulai
        </td>
        <td
            style="width: 32.5%; text-align: center; border: 1px solid #000; font-weight: bold; padding: 5px; background-color: #f0f0f0;">
            Waktu Selesai
        </td>
    </tr>

    <tr>
        <td style="border: 1px solid #000; padding: 5px;">
            Penawaran
        </td>
        <td colspan="2" style="border: 1px solid #000; padding: 5px;">
            {{ indonesianDateFormat($data->tgl_penawaran) }}
        </td>
    </tr>

    <tr>
        <td style="border: 1px solid #000; padding: 5px;">
            Konfirmasi Order
        </td>
        <td colspan="2" style="border: 1px solid #000; padding: 5px;">
            {{ indonesianDateFormat($data->tgl_konfirmasi_order) }}
        </td>
    </tr>

    <tr>
        <td style="border: 1px solid #000; padding: 5px;">
            Kegiatan Sampling
        </td>
        <td style="border: 1px solid #000; padding: 5px;">
            {{ indonesianDateFormat($data->tgl_mulai_sampling, true) }}
        </td>
        <td style="border: 1px solid #000; padding: 5px;">
            {{ indonesianDateFormat($data->tgl_selesai_sampling, true) }}
        </td>
    </tr>

    <tr>
        <td style="border: 1px solid #000; padding: 5px;">
            Sampel diterima Laboratorium
        </td>
        <td colspan="2" style="border: 1px solid #000; padding: 5px;">
            {{ indonesianDateFormat($data->tgl_terima_lab, true) }}
        </td>
    </tr>

    <tr>
        <td style="border: 1px solid #000; padding: 5px;">
            Kegiatan Analisa
        </td>
        <td style="border: 1px solid #000; padding: 5px;">
            {{ indonesianDateFormat($data->tgl_mulai_analisa, true) }}
        </td>
        <td style="border: 1px solid #000; padding: 5px;">
            {{ indonesianDateFormat($data->tgl_selesai_analisa, true) }}
        </td>
    </tr>

    <tr>
        <td style="border: 1px solid #000; padding: 5px;">
            Technical Control Check
        </td>
        <td style="border: 1px solid #000; padding: 5px;">
            {{ indonesianDateFormat($data->tgl_mulai_tcc, true) }}
        </td>
        <td style="border: 1px solid #000; padding: 5px;">
            {{ indonesianDateFormat($data->tgl_selesai_tcc, true) }}
        </td>
    </tr>

    <tr>
        <td style="border: 1px solid #000; padding: 5px;">
            Drafting Dokumen
        </td>
        <td style="border: 1px solid #000; padding: 5px;">
            {{ indonesianDateFormat($data->tgl_mulai_drafting, true) }}
        </td>
        <td style="border: 1px solid #000; padding: 5px;">
            {{ indonesianDateFormat($data->tgl_selesai_drafting, true) }}
        </td>
    </tr>

    <tr>
        <td style="border: 1px solid #000; padding: 5px;">
            Penerbitan LHP
        </td>
        <td colspan="2" style="border: 1px solid #000; padding: 5px;">
            {{ indonesianDateFormat($data->tgl_penerbitan_lhp, true) }}
        </td>
    </tr>
</table>

<p style="font-size: 14px; text-align: justify;">
    Demikian surat keterangan ini dibuat agar dapat dipergunakan sebagaimana mestinya.
</p>

<div style="margin-top: 20px; font-size: 14px;">
    <table style="font-size: 14px; border-collapse: collapse;">
        <tr>
            <td style="padding: 0; text-align: left;">Tangerang,
                {{ indonesianDateFormat($data->tgl_penerbitan_lhp) }}<br />
                PT Inti Surya Laboratorium
            </td>
        </tr>
        <tr>
            <td style="padding: 18px 0 0 0; text-align: center;">
                <img src="{{ $qr }}" width="80px" height="80px">
            </td>
        </tr>
    </table>
</div>
