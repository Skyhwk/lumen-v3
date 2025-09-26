<table class="body" width="100%" style="vertical-align: top; border-collapse: collapse;" border="0">
    {{-- <table class="body" width="100%" style="vertical-align: top;" border="1" style="border-collapse: collapse;"> --}}
    <tr>
        <td width="35%">
            <img height="60px" src="{{ public_path() . '/img/isl_logo.png' }}" alt="ISL" style="object-fit: contain;">
        </td>
        <td width="65%" style="font-family:Arial; text-align: right; vertical-align: bottom;">
            <h1 style="font-size: 40px">Slip Gaji</h1>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <table class="header" border="0" width="100%" style="border-collapse: collapse; margin-bottom: 10px;">
                <tr>
                    <td width="60%" style="vertical-align: top;">
                        <table class="company" border="0" width="100%"
                            style="border-collapse: collapse; text-align: left; vertical-align: top;">
                            <tr>
                                <th
                                    style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                                    <b>Di Transfer Pada: {{ $tgl_transfer }}</b>
                                </th>
                            </tr>
                            <tr>
                                <td
                                    style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                                    {{-- Jl. Raya Cisauk Lapan Blok O No. 5 - 6, Sampora, --}}
                                </td>
                            </tr>
                            <tr>
                                <td
                                    style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: left;">
                                    {{-- Kec. Cisauk, Kabupaten Tangerang, Banten 15345 --}}
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td width="40%" style="vertical-align: top;">
                        <svg width="325" height="92" xmlns="http://www.w3.org/2000/svg">
                            <rect x="0" y="0" width="325" height="92" rx="10" ry="10" fill="transparent" stroke="lightgray" stroke-width="1" />
                            
                            <text x="10" y="20" style="font-family: Arial, sans-serif; font-size: 11px;">Nama / NIK</text>
                            <text x="130" y="20" style="font-family: Arial, sans-serif; font-size: 11px;">: {{ $karyawan->nama }} ({{ $karyawan->nik }})</text>
                            
                            <text x="10" y="35" style="font-family: Arial, sans-serif; font-size: 11px;">Department</text>
                            <text x="130" y="35" style="font-family: Arial, sans-serif; font-size: 11px;">: {{ $karyawan->divisi }}</text>
                            
                            <text x="10" y="50" style="font-family: Arial, sans-serif; font-size: 11px;">Jabatan</text>
                            <text x="130" y="50" style="font-family: Arial, sans-serif; font-size: 11px;">: {{ $karyawan->jabatan }}</text>
                            
                            <text x="10" y="65" style="font-family: Arial, sans-serif; font-size: 11px;">Tgl Mulai Bekerja</text>
                            <text x="130" y="65" style="font-family: Arial, sans-serif; font-size: 11px;">: {{ $karyawan->start_date }}</text>
                            
                            <text x="10" y="80" style="font-family: Arial, sans-serif; font-size: 11px;">Periode Gaji</text>
                            <text x="130" y="80" style="font-family: Arial, sans-serif; font-size: 11px;">: {{ $periode }}</text>
                        </svg>

                        {{-- <table class="personal" border="0" width="100%"
                            style="text-align: left; vertical-align: top; margin-top: 15px; border-collapse: collapse;">
                            <tr>
                                <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                                    Nama / NIK
                                </td>
                                <td
                                    style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: right;">
                                    {{ $karyawan->nama }} ({{ $karyawan->nik }})
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                                    Dept / Jabatan
                                </td>
                                <td
                                    style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: right;">
                                    {{ $karyawan->divisi }} / {{ $karyawan->jabatan }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                                    Tgl Mulai Bekerja
                                </td>
                                <td
                                    style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: right;">
                                    {{ $karyawan->start_date }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small;">
                                    Periode Gaji
                                </td>
                                <td
                                    style="padding: 2px; font-family:Arial, sans-serif; font-size:x-small; text-align: right;">
                                    {{ $periode }}
                                </td>
                            </tr>
                        </table> --}}
                    </td>
                </tr>
            </table>
            <table class="payroll" border="0" width="100%" style="border-collapse: collapse;">
                <tr>
                    <th width="50%"
                        style="padding: 2px; background-color: lightgray; text-align: left; font-family:Arial; font-size:x-small;">
                        Pendapatan
                    </th>
                    <th width="5%"
                        style="padding: 2px; background-color: lightgray; text-align: left; font-family:Arial; font-size:x-small;">
                        &nbsp;
                    </th>
                    <th width="45%"
                        style="padding: 2px; background-color: lightgray; text-align: left; font-family:Arial; font-size:x-small;">
                        Potongan
                    </th>
                </tr>
                <tr>
                    <td width="50%" style="vertical-align: top;" height="150px">
                        <table class="pendapatan" border="0" width="100%"
                            style="border-collapse: collapse; vertical-align: top;">
                            <tr>
                                <td width="50%"
                                    style="padding: 2px; font-family:Arial; font-size:x-small; text-align: left;">
                                    Gaji Pokok
                                </td>
                                <td width="50%"
                                    style="padding: 2px; font-family:Arial; font-size:x-small; text-align: right;">
                                    {{ number_format($pendapatan->gaji_pokok, 0, ',', '.') }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 2px; font-family:Arial; font-size:x-small; text-align: left;">
                                    Pencadangan Upah
                                </td>
                                <td style="padding: 2px; font-family:Arial; font-size:x-small; text-align: right;">
                                    @if ($pendapatan->pencadangan_upah > 0)
                                        {{ number_format($pendapatan->pencadangan_upah, 0, ',', '.') }}
                                    @else
                                        0
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 2px; font-family:Arial; font-size:x-small; text-align: left;">
                                    Tunjangan
                                </td>
                                <td style="padding: 2px; font-family:Arial; font-size:x-small; text-align: right;">
                                    {{ number_format($pendapatan->tunjangan, 0, ',', '.') }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 2px; font-family:Arial; font-size:x-small; text-align: left;">
                                    Bonus
                                </td>
                                <td style="padding: 2px; font-family:Arial; font-size:x-small; text-align: right;">
                                    {{ number_format($pendapatan->bonus, 0, ',', '.') }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 2px; font-family:Arial; font-size:x-small; text-align: left;">
                                    Insentif
                                </td>
                                <td style="padding: 2px; font-family:Arial; font-size:x-small; text-align: right;">
                                    {{ number_format($pendapatan->incentive, 0, ',', '.') }}
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td width="5%" style="vertical-align: top;">
                        &nbsp;
                    </td>
                    <td width="45%" style="vertical-align: top;" height="150px">
                        <table class="potongan" border="0" width="100%"
                            style="border-collapse: collapse; vertical-align: top;">
                            <tr>
                                <td width="50%"
                                    style="padding: 2px; font-family:Arial; font-size:x-small; text-align: left;">
                                    Potongan Absen
                                </td>
                                <td width="50%"
                                    style="padding: 2px; font-family:Arial; font-size:x-small; text-align: right;">
                                    {{ number_format($potongan->absen, 0, ',', '.') }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 2px; font-family:Arial; font-size:x-small; text-align: left;">
                                    Potongan BPJS TK
                                </td>
                                <td style="padding: 2px; font-family:Arial; font-size:x-small; text-align: right;">
                                    {{ number_format($potongan->jamsostek, 0, ',', '.') }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 2px; font-family:Arial; font-size:x-small; text-align: left;">
                                    Potongan BPJS Kesehatan
                                </td>
                                <td style="padding: 2px; font-family:Arial; font-size:x-small; text-align: right;">
                                    {{ number_format($potongan->bpjs_kesehatan, 0, ',', '.') }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 2px; font-family:Arial; font-size:x-small; text-align: left;">
                                    Potongan Pencadangan Upah
                                </td>
                                <td style="padding: 2px; font-family:Arial; font-size:x-small; text-align: right;">
                                    @if ($potongan->pencadangan_upah < 0)
                                        {{ number_format(abs($potongan->pencadangan_upah), 0, ',', '.') }}
                                    @else
                                        0
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 2px; font-family:Arial; font-size:x-small; text-align: left;">
                                    Potongan Pinjaman
                                </td>
                                <td style="padding: 2px; font-family:Arial; font-size:x-small; text-align: right;">
                                    {{ number_format($potongan->loan, 0, ',', '.') }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 2px; font-family:Arial; font-size:x-small; text-align: left;">
                                    Potongan Sanksi
                                </td>
                                <td style="padding: 2px; font-family:Arial; font-size:x-small; text-align: right;">
                                    {{ number_format($potongan->sanksi, 0, ',', '.') }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 2px; font-family:Arial; font-size:x-small; text-align: left;">
                                    Potongan Pajak PPh21
                                </td>
                                <td style="padding: 2px; font-family:Arial; font-size:x-small; text-align: right;">
                                    {{ number_format($potongan->pph, 0, ',', '.') }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 2px; font-family:Arial; font-size:x-small; text-align: left;">
                                    Potongan Lainnya
                                </td>
                                <td style="padding: 2px; font-family:Arial; font-size:x-small; text-align: right;">
                                    {{ number_format($potongan->potongan_lain, 0, ',', '.') }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td width="50%" style="vertical-align: top; border-top: 1px solid #000;">
                        <table class="total_pendapatan" border="0" width="100%"
                            style="border-collapse: collapse; vertical-align: top;">
                            <tr>
                                <th width="50%"
                                    style="padding: 2px; font-family:Arial; font-size:x-small; text-align: left;">
                                    Total Pendapatan
                                </th>
                                <th width="50%"
                                    style="padding: 2px; font-family:Arial; font-size:x-small; text-align: right;">
                                    {{ number_format($pendapatan->total_pendapatan, 0, ',', '.') }}
                                </th>
                            </tr>
                        </table>
                    </td>
                    <td width="5%" style="vertical-align: top; border-top: 1px solid #000;">
                        &nbsp;
                    </td>
                    <td width="45%" style="vertical-align: top; border-top: 1px solid #000;">
                        <table class="total_potongan" border="0" width="100%"
                            style="border-collapse: collapse; vertical-align: top;">
                            <tr>
                                <th width="50%"
                                    style="padding: 2px; font-family:Arial; font-size:x-small; text-align: left;">
                                    Total Potongan
                                </th>
                                <th width="50%"
                                    style="padding: 2px; font-family:Arial; font-size:x-small; text-align: right;">
                                    {{ number_format($potongan->total_potongan, 0, ',', '.') }}
                                </th>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <table class="keterangan" border="0" width="100%"
                style="border-collapse: collapse; text-align: left; vertical-align: top; margin-top: 35px;">
                <tr>
                    <td width="70%"
                        style="text-align: left; vertical-align: center; font-family: Arial; font-size: x-small;">
                        Pembayaran gaji telah dilakukan oleh perusahaan<br>
                        Secara transfer ke rekening karyawan<br>
                        {{ $karyawan->nama_bank }} {{ $karyawan->no_rekening }} ({{ $karyawan->nama }})
                    </td>
                    <td width="30%">
                        <svg width="225" height="55" xmlns="http://www.w3.org/2000/svg">
                            <rect x="0" y="0" width="225" height="55" rx="10" ry="10" fill="transparent" stroke="#000" stroke-width="1" />
                            
                            <text x="112.5" y="15" style="font-family: Arial, sans-serif; font-size: 11px;" text-anchor="middle">Total Penerimaan Bulan Ini</text>
                            
                            <text x="112.5" y="45" style="font-family: Arial, sans-serif; font-size: 28.8px; font-weight: bold;" text-anchor="middle">{{ number_format($pendapatan->take_home_pay, 0, ',', '.') }}</text>
                        </svg>
                    </td>
                    {{-- <td width="30%" style="text-align: center; vertical-align: center; font-family: Arial; font-size: x-small; border: 1px solid #000;">
                        Total Penerimaan Bulan Ini<br>
                        <h1 style="font-family: Arial; font-size: xx-large; font-weight: bold;">
                            {{ number_format($pendapatan->take_home_pay, 0, ',', '.') }}
                        </h1>
                    </td> --}}
                </tr>
            </table>
        </td>
    </tr>
</table>
