@php
    use App\Models\TabelRegulasi;
    use App\Models\MasterRegulasi;

    $regulasi = array_merge(json_decode($header->regulasi_custom ?? '[]'), json_decode($header->regulasi ?? '[]'));
    $regulasi = array_unique($regulasi);
    $regulasi = array_values($regulasi);
    
    switch ($sub_kategori){
        case 'Kebisingan':
            $kategori = 'Regulasi Acuan Pengujian dan Monitoring Kualitas Kebisingan :';
            break;
        case 'Kebisingan (8 Jam)':
            $kategori = 'Regulasi Acuan Pengujian dan Monitoring Kualitas Kebisingan :';
            break;
        case 'Kebisingan (24 Jam)':
            $kategori = 'Regulasi Acuan Pengujian dan Monitoring Kualitas Kebisingan :';
            break;
        case 'getaran (lengan & tangan)':
            $kategori = 'Regulasi Acuan Pengujian dan Monitoring Kualitas Getaran :';
            break;
        case 'getaran':
            $kategori = 'Regulasi Acuan Pengujian dan Monitoring Kualitas Getaran :';
            break;
        case 'getaran (mesin)':
            $kategori = 'Regulasi Acuan Pengujian dan Monitoring Kualitas Getaran :';
            break;
        case 'getaran (kejut bangunan)':
            $kategori = 'Regulasi Acuan Pengujian dan Monitoring Kualitas Getaran :';
            break;
        case 'getaran (lingkungan)':
            $kategori = 'Regulasi Acuan Pengujian dan Monitoring Kualitas Getaran :';
            break;
        case 'getaran (bangunan)':
            $kategori = 'Regulasi Acuan Pengujian dan Monitoring Kualitas Getaran :';
            break;
        case 'getaran (seluruh tubuh)':
            $kategori = 'Regulasi Acuan Pengujian dan Monitoring Kualitas Getaran :';
            break;
        case 'Pencahayaan':
            $kategori = 'Regulasi Acuan Pengujian dan Monitoring Kualitas Pencahayaan :';
            break;
        case 'Intensitas Pencahayaan':
            $kategori = 'Regulasi Acuan Pengujian dan Monitoring Kualitas Pencahayaan :';
            break;
        case 'Iklim Kerja':
            $kategori = 'Regulasi Acuan Pengujian dan Monitoring Kualitas Iklim Kerja :';
            break;
        default:
            $kategori = '';
            break;
    }

@endphp

@if (!empty($regulasi))
        @foreach ($regulasi as $y)
            @php
                $regulasiId = explode('-', $y)[0];
                $regulasiName = explode('-', $y)[1] ?? '';
                $regulasi = MasterRegulasi::find($regulasiId);
                $tableObj = TabelRegulasi::whereJsonContains('id_regulasi', $regulasiId)->where('is_active', true)->first();
                $table = $tableObj ? $tableObj->konten : '';
            @endphp

            @if ($table)
                <div style="page-break-before: always;">
                    <table style="padding-top: 5px; font-size: 10px;" width="100%">
                        <tr>
                            <td class="custom5" colspan="3">{{ $kategori }}</td>
                        </tr>
                    </table>
                    <table style="padding-top: 5px; font-size: 10px; width: 59%;">
                        <tr>
                            <td class="custom5" colspan="3"><strong>{{ $regulasiName }}</strong></td>
                        </tr>
                    </table>

                        {!! preg_replace(
                        [
                            '/<table(\s|>)/i',
                            '/<td([^>]*)>\s*<div\s+style="text-align:\s*center"[^>]*>(.*?)<\/div>\s*<\/td>/is',
                        ],
                        [
                            '<table border="1" cellspacing="0" cellpadding="2" style="border: 1px solid #000;  font-family:Arial, Helvetica, sans-serif; font-size:10px; float: Left; width: 59%;"$1',
                            '<td$1 style="text-align:center;"><div style="text-align:center; ">$2</div></td>',
                        ],
                        $table
                    ) !!}
                </div>
            @endif
        @endforeach
    @endif
