
    <div class="left">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
            <thead>
                <tr>
                    <th width="6%" class="custom">NO</th>
                    <th width="20%" class="custom" >PARAMETER</th>
                    <th width="20%" class="custom">HASIL UJI </th>
                    <th width="15%" class="custom">NAB **</th>
                    <th width="15%" class="custom">SATUAN</th>
                    <th width="24%" class="custom">TANGGAL SAMPLING</th>
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
                    <td class="pd-5-solid-left"><sup
                            style="font-size:5px; !important; margin-top:-10px;">{{ $yy['no_sampel'] }}</sup>{{ $yy['parameter'] }}
                    </td>
                        <td class="pd-5-solid-center">{{$yy['hasil']}}</td>
                        <td class="pd-5-solid-center">{{$yy['nab']}}</td>
                        <td class="pd-5-solid-center">{{$yy['satuan']}}</td>
                        <td class="pd-5-solid-center">{{$yy['tanggal_sampling']}}</td>
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
                    $hasilObservasi = json_decode($header->hasil_observasi, true) ?? null;
                @endphp
                @if($hasilObservasi)
                    @if (is_array($hasilObservasi))
                        @foreach ($hasilObservasi as $key => $note)
                            @php $p = $key + 1; @endphp
                            <tr>
                                <td width="4%" class="pd-5-solid-left" style="font-weight: bold; text-align: center;">{{$p}}</td>
                                <td width="36%" class="pd-5-solid-left" style="text-align: center;">{{$note}}</td>
                            </tr>
                        @endforeach
                    @endif
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
                    $kesimpulan = json_decode($header->kesimpulan, true) ?? null;
                @endphp

                  @if($kesimpulan)
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
                @endif 
               
            </tbody>
        </table>
    </div>



