@php
    $qr = '';
    $file_qr = '';
    $tanggal_qr = '';
    $pading = '';
    if ($mode == 'downloadWSDraft') {
        $pading = 'margin-bottom: 40px;';
        $qr = 'Halaman {PAGENO} - {nbpg}';
    } else if($mode == 'downloadLHP' || $mode == 'downloadLHPFinal'){
        if (!is_null($header->file_qr)) {
            $qr = 'DP/7.8.1/ISL; Rev 3; 08 November 2022';
        } else {
            $qr = 'DP/7.8.1/ISL; Rev 3; 08 November 2022';
        }
    }
    if (!is_null($header->file_qr) && $mode != 'downloadWSDraft') {
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
        <!-- signature -->
        <td width="23%" style="text-align: right;">
            @if (isset($last) && $last)
                <table
                    style="text-align: center; font-family: Helvetica, sans-serif; margin-bottom: 40px; font-size: 9px"
                    width="100%">
                    <tr>
                        <td>{{$tanggal_qr}} <br> <img src={{$file_qr}} width="60px" height="60px" style="margin-top: 10px;"></td>
                    </tr>
                </table>
            @else
                <div></div>
            @endif
            <!-- qr -->
            <table 
                style="position: absolute; bottom: 0; width: 100%; text-align: right; font-family: Helvetica, sans-serif; font-size: 7px;"
                >
                <tr>
                    <td>
                    {{$qr}}
                    </td>
                </tr>
                <tr>
                    <td></td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
    </tr>
</table>