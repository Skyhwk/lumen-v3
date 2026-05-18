<div class="right" style="margin-top: 0;">
    <table style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
        <tr>
            <td>
                <table width="100%">
                    <tr>
                        <td><span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Pelanggan</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="custom5" width="120">Nama Pelanggan</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5">{{ $header->nama_pelanggan }}</td>
                    </tr>
                </table>

                <table style="padding-top: 10px;" width="100%">
                    <tr>
                        <td class="custom5" width="120">Alamat / Lokasi Sampling</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5">{{ $header->alamat_pelanggan }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
