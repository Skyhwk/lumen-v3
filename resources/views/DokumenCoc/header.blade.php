<table class="tabel"
    style="width:100%; border-collapse:collapse; border-bottom:0.5px solid #aaa; margin-bottom:15px; padding-bottom:5px;">
    <tr>
        <td style="width:50%; vertical-align:top; padding-top:10px; padding-bottom:16px;">
            <img src="{{ public_path('img/isl_logo.png') }}" alt="ISL Logo" style="width:180px; height:auto;">
        </td>

        <td style="width:50%; text-align:center; vertical-align:top; padding-top:31px; ">
            <h4
                style="
                margin:0; 
                font-size:30px; 
                font-weight:bold; 
                font-family: roboto;
                display:inline-block; 
                border-bottom:0.5px solid #000; 
                padding-bottom:3px;
            ">
                SURAT KETERANGAN
            </h4>
            <p style="font-size: 2px;">&nbsp;</p>
            <p style="margin:0; font-size:14px; font-family: roboto; font-weight: bold;">
                No. : {{ str_replace('/', '-', $data->no_dokumen) }}
            </p>
        </td>
    </tr>
</table>
