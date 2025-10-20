@php
    use App\Models\TabelRegulasi;
    use App\Models\MasterRegulasi;
@endphp

@if ($custom)
    @if (!empty($header->regulasi_custom))
        @foreach (json_decode($header->regulasi_custom ?? '[]') as $key => $y)
           
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
                            <td class="custom5" colspan="3">Regulasi Acuan Pengujian dan Monitoring Kualitas Kebisingan :</td>
                        </tr>
                    </table>
                    <table style="padding-top: 10px; font-size: 10px;" width="100%">
                        @if ($key + 1 == $page)
                            <tr>
                                <td class="custom5" colspan="3"><strong>{{ $regulasiName }}</strong></td>
                            </tr>
                        @endif
                    </table>

                    {!! preg_replace(
                    '/<th(\s|>)/i',
                    '<th style="background:#f2f2f2; font-weight:bold; text-align:center;"$1',
                    preg_replace(
                        '/<td(\s|>)/i',
                        '<td class="pd-5-solid-center',
                        preg_replace(
                            '/<table(\s|>)/i',
                            '<table border="1" cellspacing="0" cellpadding="2" style="border:1px solid #000; border-collapse:collapse; font-family:Arial, Helvetica, sans-serif; font-size:10px;"$1',
                            $table
                        )
                    )
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
                $regulasi = MasterRegulasi::find($regulasiId);
                $tableObj = TabelRegulasi::whereJsonContains('id_regulasi', $regulasiId)->where('is_active', true)->first();
                $table = $tableObj ? $tableObj->konten : '';
            @endphp

            @if ($table)
                <div style="page-break-before: always;">     
                    <table style="padding-top: 5px;font-size: 10px;" width="100%">
                        <tr>
                            <td class="custom5" colspan="3">Regulasi Acuan Pengujian dan Monitoring Kualitas Kebisingan :</td>
                        </tr>
                    </table>
                    <table style="padding-top: 5px; font-size: 10px;" width="100%">
                        <tr>
                            <td class="custom5" colspan="3"><strong>{{ $regulasiName }}</strong></td>
                        </tr>
                    </table>

                  {!! preg_replace(
                    '/<th(\s|>)/i',
                    '<th style="background:#f2f2f2; font-weight:bold; text-align:center;"$1',
                    preg_replace(
                        '/<td(\s|>)/i',
                        '<td class="pd-5-solid-center',
                        preg_replace(
                            '/<table(\s|>)/i',
                            '<table border="1" cellspacing="0" cellpadding="2" style="border:1px solid #000; border-collapse:collapse; font-family:Arial, Helvetica, sans-serif; font-size:10px;"$1',
                            $table
                        )
                    )
                ) !!}

                </div>
            @endif
        @endforeach
    @endif
@endif
