<table width="100%" style="font-size:7px;">
    <tr>
        <td width="18%" style="vertical-align: bottom; font-family: roboto; font-weight: bold;">
            <div>PT Inti Surya Laboratorium</div>
            <div>Ruko Icon Business Park Blok O No.5-6 BSD City, Jl. BSD Raya Utama, Cisauk, Sampora Kab. Tangerang
                15341</div>
            <div>021-5089-8988/89 contact@intilab.com</div>
        </td>
        <td width="60%"
            style="vertical-align: bottom; text-align:center; padding-left:44px; margin:0; position:relative; min-height:100px; font-family: roboto; font-weight: bold;">
            Laporan hasil pengujian ini hanya berlaku bagi sampel yang tercantum di atas. Lembar ini tidak boleh diubah
            ataupun digandakan tanpa izin tertulis dari pihak Laboratorium.
            <br>Halaman {PAGENO} - {nbpg}
        </td>
        <!-- signature -->
        <td width="22%" style="position: relative; padding: 0; text-align: right;">

            <table
                style="position: absolute; bottom: 0; left: 0; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px;"
                width="260">
                <tr>
                    <td>{{ 'Tangerang, ' . \App\Helpers\Helper::tanggal_indonesia($header->tanggal_lhp) }}</td>
                </tr>
                <tr>
                    <td style="height: 70px;"><img src="{{ public_path('qr_documents/' . $header->file_qr . '.svg') }}"
                            width="70px" height="70px"></td>
                </tr>
                <tr>
                    <td style="height: 70px;"></td>
                </tr>
                <tr>
                    <td style="height: 10px;"></td>
                </tr>
            </table>
            <table
                style="position: absolute; bottom: 0; right: 0; font-family: Helvetica, sans-serif; font-size: 7px; text-align: right;">
                <tr>
                    <td style="font-family: roboto; font-weight: bold;">DP/7.8.1/ISL; Rev 3; 08 November 2022</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
    </tr>
</table>
