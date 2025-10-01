@php
    use App\Models\TabelRegulasi;
    use App\Models\MasterRegulasi;
@endphp

@if ($custom)
    @if (!empty($header->regulasi_custom))
        @foreach (json_decode($header->regulasi_custom ?? '[]') as $key => $y)
            @php
                $regulasiId = explode('-', $y->regulasi)[0];
                $regulasiName = explode('-', $y->regulasi)[1] ?? '';
                $regulasi = MasterRegulasi::where('id',  $regulasiId)->first();
                $tableObj = TabelRegulasi::whereJsonContains('id_regulasi', $regulasiId)->first();
                $table = $tableObj ? $tableObj->konten : '';
            @endphp
                @if(!empty($table))
                    {!! preg_replace(
                        '/<table(\s|>)/i',
                        '<table border="1" cellspacing="0" cellpadding="2" style="border: 1px solid #000;"$1',
                        $table
                    ) !!}
                @else
                    <table></table>
                @endif

            @if ($table)
                <div style="page-break-before: always;">
                    <table style="padding-top: 5px; font-size: 10px;" width="100%">
                        <tr>
                            <td class="custom5" colspan="3">Regulasi Acuan Pengujian dan Monitoring Kualitas Getaran :</td>
                        </tr>
                    </table>
                    <table style="padding-top: 5px; font-size: 10px;" width="100%">
                        @if ($y->page == $page)
                            <tr>
                                <td class="custom5" colspan="3"><strong>{{ $regulasiName }}</strong></td>
                            </tr>
                        @endif
                    </table>

                   {!! preg_replace(
                        [
                            '/<table(\s|>)/i',
                            '/<td([^>]*)>\s*<div\s+style="text-align:\s*center"[^>]*>(.*?)<\/div>\s*<\/td>/is',
                        ],
                        [
                            '<table border="1" cellspacing="0" cellpadding="2" style="border: 1px solid #000;  font-family:Arial, Helvetica, sans-serif; font-size:10px;"$1',
                            '<td$1 style="text-align:center;"><div style="text-align:center;">$2</div></td>',
                        ],
                        $table
                    ) !!}
                </div>
            @endif
        @endforeach
    @endif
@else
    @if (!empty($header->regulasi))
        @foreach (json_decode($header->regulasi ?? '[]') as $y)
            @php
                $regulasiId = explode('-', $y)[0];
                $regulasiName = explode('-', $y)[1] ?? '';
                $regulasi = MasterRegulasi::where('id',  $regulasiId)->first();
                $tableObj = TabelRegulasi::whereJsonContains('id_regulasi', $regulasiId)->first();
                $table = $tableObj ? $tableObj->konten : '';
            @endphp
            @if ($table)
                <div style="page-break-before: always;">
                    <table style="padding-top: 5px; font-size: 10px;" width="100%">
                        <tr>
                            <td class="custom5" colspan="3">Regulasi Acuan Pengujian dan Monitoring Kualitas Getaran :</td>
                        </tr>
                    </table>
                    <table style="padding-top: 5px; font-size: 10px;" width="100%">
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
                            '<table border="1" cellspacing="0" cellpadding="2" style="border: 1px solid #000;  font-family:Arial, Helvetica, sans-serif; font-size:10px;"$1',
                            '<td$1 style="text-align:center;"><div style="text-align:center; ">$2</div></td>',
                        ],
                        $table
                    ) !!}
                </div>
            @endif
        @endforeach
    @endif
@endif
