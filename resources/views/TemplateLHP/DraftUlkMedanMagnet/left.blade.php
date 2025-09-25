@if (!empty($custom))
    @foreach ($data as $key => $value)
        <div class="left">
            <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                <thead>
                    <tr>
                        <th width="25" class="custom">NO</th>
                        <th width="170" class="custom">PARAMETER</th>
                        <th width="210" class="custom">HASIL UJI </th>
                        <th width="50" class="custom">NAB **</th>
                        <th width="50" class="custom">SATUAN</th>
                        <th width="120" class="custom">SPESIFIKASI METODE</th>
                    </tr>
                </thead>
                <tbody>
                    @php 
                        $totdat = count($value); 
                    @endphp
                    @foreach ($value as $kk => $yy)
                        @php
                            $p = $kk + 1;
                        @endphp
                        <tr>
                            <td class="pd-5-solid-center">{{$p}}</td>
                            <td class="pd-5-solid-left">{{$yy['parameter']}}</td>
                            <td class="pd-5-solid-center">{{$yy['hasil']}}</td>
                            <td class="pd-5-solid-center">{{$yy['nab']}}</td>
                            <td class="pd-5-solid-center">{{$yy['satuan']}}</td>
                            <td class="pd-5-solid-center">{{$yy['methode']}}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

@else
    <div class="left">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
            <thead>
                <tr>
                    <th width="25" class="custom">NO</th>
                    <th width="170" class="custom">PARAMETER</th>
                    <th width="210" class="custom">HASIL UJI </th>
                    <th width="50" class="custom">NAB **</th>
                    <th width="50" class="custom">SATUAN</th>
                    <th width="120" class="custom">SPESIFIKASI METODE</th>
                </tr>
            </thead>
            <tbody>
                @php $totdat = count($detail); @endphp
                @foreach ($detail as $kk => $yy)
                    @php
                        $p = $kk + 1;
                    @endphp
                    <tr>
                        <td class="pd-5-solid-center">{{$p}}</td>
                        <td class="pd-5-solid-left">{{$yy['parameter']}}</td>
                        <td class="pd-5-solid-center">{{$yy['hasil']}}</td>
                        <td class="pd-5-solid-center">{{$yy['nab']}}</td>
                        <td class="pd-5-solid-center">{{$yy['satuan']}}</td>
                        <td class="pd-5-solid-center">{{$yy['methode']}}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @php
            $additionalNotes = [
                ['parameter' => 'Sumber Radiasi', 'data' => 'Panel'],
                ['parameter' => 'Waktu Pemaparan (Per-menit)', 'data' => '6 Menit'],
                ['parameter' => 'Frekuensi Area (MHz)', 'data' => '984,9'],
            ];
        @endphp
        <br />
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px; width: 100%;">
            <thead>
                <tr>
                    <th width="4%" class="custom" style="text-align: center;">NO</th>
                    <th width="36%" class="custom" style="text-align: center;">PARAMETER</th>
                    <th width="60%" class="custom" style="text-align: center;">DATA</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($additionalNotes as $key => $note)
                    @php
                        $p = $key + 1;
                    @endphp
                    <tr>
                        <td width="4%" class="pd-5-solid-left" style="font-weight: bold; text-align: center;">{{$p}}</td>
                        <td width="36%" class="pd-5-solid-left" style="font-weight: bold; text-align: center;">{{$note['parameter']}}</td>
                        <td width="60%" class="pd-5-solid-left" style="text-align: center;">{{$note['data']}}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <br/>
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px; width: 100%;">
            <thead>
                <tr>
                    <th colspan="2" class="custom" style="text-align: center; ">HASIL OBSERVASI</th>
                </tr>
            
            </thead>
            <tbody>
                @php
                    $hasilObservasi = json_decode($header->hasil_observasi, true);
                @endphp

                @if (is_array($hasilObservasi))
                    @foreach ($hasilObservasi as $key => $note)
                        @php $p = $key + 1; @endphp
                        <tr>
                            <td width="4%" class="pd-5-solid-left" style="font-weight: bold; text-align: center;">{{$p}}</td>
                            <td width="36%" class="pd-5-solid-left" style="text-align: center;">{{$note}}</td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
        <br />
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px; width: 100%;">
            <thead>
                <tr>
                    <th colspan="2" class="custom" style="text-align: center;">KESIMPULAN</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $kesimpulan = json_decode($header->kesimpulan, true);
                @endphp
                @if (is_array($kesimpulan))
                    @foreach ($kesimpulan as $key => $note)
                        @php
                            $p = $key + 1;
                        @endphp
                        <tr>
                            <td width="4%" class="pd-5-solid-left" style="font-weight: bold; text-align: center;">{{$p}}</td>
                            <td width="96%" class="pd-5-solid-left" style="font-weight: bold; text-align: center;">{{$note}}</td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
    </div>
@endif



