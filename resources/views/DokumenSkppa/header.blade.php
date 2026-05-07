<table class="tabel"
    style="width:100%; border-collapse:collapse; border-bottom:0.5px solid #aaa; margin-bottom:15px; padding-bottom:5px;">
    <tr>
        <!-- Logo -->
        <td style="width:50%; vertical-align:top; padding-top:10px; padding-bottom:16px;">
            <img src="{{ public_path('img/isl_logo.png') }}"
                alt="ISL Logo"
                style="width:180px; height:auto;">
        </td>

        <!-- Judul dan Ref -->
        <td style="width:50%; text-align:center; vertical-align:top; padding-top:31px; ">
            <h4 style="
                margin:0; 
                font-size:18px; 
                font-weight:bold; 
                display:inline-block; 
                border-bottom:0.5px solid #000; 
                padding-bottom:3px;
            ">
                SURAT KETERANGAN
            </h4>
            <p style="font-size: 2px;">&nbsp;</p>
            <p style="margin:0; font-size:10px;">
                No. : {{ str_replace('/', '-', $data->no_document) }}
            </p>
        </td>
        {{-- <td style="width:15%; text-align:right; vertical-align:top; padding-top:10px; color:#777; font-size:9px;">
            Halaman {PAGENO}/{nbpg}
        </td> --}}
    </tr>
</table>