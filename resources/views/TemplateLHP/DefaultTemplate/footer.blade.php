@php
    $pangging = '';
    $file_qr = '';
    $tanggal_qr = '';
    $pading = '';
    if ($mode == 'downloadWSDraft') {
        $pading = 'margin-bottom: 40px;';
        $pangging = 'Halaman {PAGENO} - {nbpg}';
    } else if($mode == 'downloadLHP' || $mode == 'downloadLHPFinal'){
        if (!is_null($header->file_qr)) {
            $pangging = 'DP/7.8.1/ISL; Rev 3; 08 November 2022';
        } else {
            $pangging = 'DP/7.8.1/ISL; Rev 3; 08 November 2022';
        }
    }

    if (!is_null($header->file_qr) && $mode != 'downloadWSDraft') {
        $file_qr = public_path('qr_documents/' . $header->file_qr . '.svg');
        $tanggal_qr = 'Tangerang, ' . \App\Helpers\Helper::tanggal_indonesia($header->tanggal_lhp);
    }
@endphp

<table width="100%" style="font-size:7px;">
    <tr>
        <td width="18%" style="vertical-align: bottom; font-family: roboto; font-weight: bold;">
            <div>PT Inti Surya laboratirum</div>
            <div>Ruko Icon Business Park Blok O No.5-6 BSD City, Jl. BSD Raya Utama, Cisauk, Sampora Kab. Tangerang 15341</div>
            <div>021-5089-8988/89 contact@intilab.com</div>
        </td>
        <td width="60%" style="vertical-align: bottom; text-align:center; padding-left:44px; margin:0; position:relative; min-height:100px; font-family: roboto; font-weight: bold;">
            @if($mode == 'downloadLHP')
                @if($header->count_print > 1)
                    <strong>Cetakan ke-{{$header->count_print}}</strong><br/>
                @endif
            @endif
            <!-- Hasil uji ini hanya berlaku untuk kondisi sampel yang tercantum pada lembar ini dan tidak dapat digeneralisasikan untuk sampel lain. Lembar ini tidak dapat di gandakan tanpa izin dari laboratorium. -->
            <!-- Hasil uji ini hanya berlaku untuk sampel yang diuji. Lembar ini tidak boleh diubah ataupun digandakan tanpa izin tertulis dari pihak laboratorium. -->
            Laporan hasil pengujian ini hanya berlaku bagi sampel yang tercantum di atas. Lembar ini tidak boleh diubah ataupun digandakan tanpa izin tertulis dari pihak Laboratorium.
            @if ($mode != 'downloadWSDraft')
            <br>Halaman {PAGENO} - {nbpg}
            @endif
        </td>
        <!-- signature -->
        <td width="22%" style="position: relative; padding: 0; text-align: right;">
            @if (isset($last) && $last)
                @if($mode == 'downloadLHP')
                    <table
                        style="position: absolute; bottom: 0; left: 0; text-align: center; font-size: 9px;"
                        width="260"
                    >
                        <tr><td>{{$tanggal_qr}}</td></tr>
                        <tr><td style="height: 70px;"></td></tr>
                        <tr><td><strong>(<u>{{$header->nama_karyawan}}</u>)</strong></td></tr>
                        <tr><td>{{$header->jabatan_karyawan}}</td></tr>
                    </table>
                @elseif($mode == 'downloadLHPFinal')
                    <table
                        style="position: absolute; bottom: 0; left: 0; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px;"
                        width="260"
                    >
                        <tr><td>{{$tanggal_qr}}</td></tr>
                        <tr><td style="height: 70px;"><img src="{{$file_qr}}" width="50px" height="50px"></td></tr>
                        <tr><td style="height: 70px;"></td></tr>
                        <tr><td style="height: 10px;"></td></tr>
                    </table>
                @endif
            @else
                @if($mode == 'downloadLHPFinal')
                    <table
                        style="position: absolute; bottom: 0; left: 0; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px;"
                        width="260"
                    >
                        <tr><td>{{$tanggal_qr}}</td></tr>
                        <tr><td style="height: 70px;"><img src="{{$file_qr}}" width="50px" height="50px"></td></tr>
                        <tr><td style="height: 70px;"></td></tr>
                        <tr><td style="height: 10px;"></td></tr>
                    </table>
                @endif
            @endif
            <table 
                style="position: absolute; bottom: 0; right: 0; font-family: Helvetica, sans-serif; font-size: 7px; text-align: right;"
            >   
            @if($mode == 'downloadLHP')
                <tr>
                    <td><img src="{{$file_qr}}" width="40px" height="40px"></td>
                </tr>
            @endif   
            <tr><td style="font-family: roboto; font-weight: bold;">{{$pangging}}</td></tr>  
            </table>
        </td>
    </tr>
    <tr>
    </tr>
</table>