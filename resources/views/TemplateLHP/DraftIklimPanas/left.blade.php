@if (!empty($detail))
    <div class="left">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
                <tr>
                    <th width="8%" class="custom">NO</th>
                    <th width="25%" class="custom" colspan="2">LOKASI / KETERANGAN SAMPEL</th>
                    <th width="15%"  class="custom">INDEX SUHU BASAH DAN BOLA (°C)</th>
                    <th width="16%" class="custom">AKTIVITAS PEKERJAAN</th>
                    <th width="16%" class="custom">DURASI PAPARAN TERHADAP PEKERJAAN PER JAM</th>
                    <th width="20%" class="custom">TANGGAL SAMPLING</th>
                </tr>
               
            </thead>
            <tbody>
                @foreach ($detail as $k => $yy)
                <tr>
                    <td class="pd-5-solid-center">{{ $k + 1 }}</td>
                    <td class="pd-5-solid-left" width="7%" style="text-align: right; border-right: none;"> 
                        <sup style=" margin-top: -10px;">{{ $yy['no_sampel'] ?? '' }}</sup> 
                    </td>
                    <td class="pd-5-solid-left" width="18%" style="border-left: none; text-align: left;"> 
                        {{ $yy['keterangan'] ?? '' }}
                    </td>
                    <td class="pd-5-solid-center">{{ $yy['hasil'] ?? '' }}</td>
                    <td class="pd-5-solid-center">{{ $yy['aktivitas_pekerjaan'] ?? '' }}</td>
                    <td class="pd-5-solid-center">{{ $yy['durasi_paparan'] ?? '' }} Jam</td>
                    <td class="pd-5-solid-center">{{\App\Helpers\Helper::tanggal_indonesia($yy['tanggal_sampling'])}}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
