@php
    $halaman = '';
    $file_qr = '';
    $tanggal_qr = '';
    $pading = '';
    if ($mode == 'downloadWSDraft') {
        $pading = 'margin-bottom: 40px;';
        $halaman = 'Halaman {PAGENO} - {nbpg}'; 
    } else if($mode == 'downloadLHP' || $mode == 'downloadLHPFinal'){
        if (!is_null($header->file_qr)) {
            $halaman = 'DP/7.8.1/ISL; Rev 3; 08 November 2022';
        } else {
            $halaman = 'DP/7.8.1/ISL; Rev 3; 08 November 2022';
        }
    }

    if (!is_null($header->file_qr)) {
        $file_qr = public_path('qr_documents/' . $header->file_qr . '.svg');
        $tanggal_qr = 'Tangerang, ' . \App\Helpers\Helper::tanggal_indonesia($header->tanggal_lhp);
    }
@endphp

<table width="100%" style="font-size:7px;">
    <tr>
        <td width="15%" style="vertical-align: bottom;">
            <div>PT Inti Surya laboratirum</div>
            <div>Ruko Icon Business Park Blok O No.5-6 BSD City, Jl. BSD Raya Utama, Cisauk, Sampora Kab. Tangerang 15341</div>
            <div>021-5089-8988/89 contact@intilab.com</div>
        </td>
        <td width="59%" style="vertical-align: bottom; text-align:center; padding:0; padding-left:44px; margin:0; position:relative; min-height:100px;">
            Hasil uji ini hanya berlaku untuk kondisi sampel yang tercantum pada lembar ini dan tidak dapat digeneralisasikan untuk sampel lain. Lembar ini tidak dapat di gandakan tanpa izin dari laboratorium.
            @if ($mode != 'downloadWSDraft')
            <br>Halaman {PAGENO} - {nbpg}
            @endif
        </td>
        <td width="26%" style="position: relative; padding: 0; text-align: right;">
            @if ($mode != 'downloadWSDraft')
              @if (isset($last) && $last && $mode)
                @if($mode == 'downloadLHP')
                    <table
                        style="position: absolute; bottom: 50px; right: 20px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px;"
                        width="260"
                    >
                        <tr><td>{{$tanggal_qr}}</td></tr>
                        <tr><td style="height: 70px;"></td></tr>
                        <tr><td><strong>(<u>{{$header->nama_karyawan}}</u>)</strong></td></tr>
                        <tr><td>{{$header->jabatan_karyawan}}</td></tr>
                    </table>
                @elseif($mode == 'downloadLHPFinal')
                    <table
                        style="position: absolute; bottom: 0; right: 20px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px;"
                        width="260"
                    >  
                        <tr><td>{{$tanggal_qr}}</td></tr>
                        <tr><td><img src="{{$file_qr}}" width="70px" height="70px"></td></tr>
                        <tr><td style="height: 70px;"></td></tr>
                        <tr><td style="height: 10px;"></td></tr>
                    </table>
                @endif
            @endif
            @else 

            @endif
                <table
                        style="position: absolute; bottom: 0; right: 20px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px;"
                        width="260"
                    >
                @if($mode == 'downloadWSDraft')
                        @if (!is_null($header->file_qr))
                        <tr><td>{{$tanggal_qr}}</td></tr>
                        <tr><td><img src="{{$file_qr}}" width="70px" height="70px"></td></tr>
                        <tr><td style="height: 70px;"></td></tr>
                        <tr><td style="height: 10px;"></td></tr>
                        @endif
                @endif
                <tr><td>{{$halaman}}</td></tr>
            </table>
         
        </td>
    </tr>
    <tr>
    </tr>
</table>