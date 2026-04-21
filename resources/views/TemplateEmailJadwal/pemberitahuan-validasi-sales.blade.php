<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" 
       style="background-color: #f4f4f4; padding: 20px 0; font-family: Arial, Helvetica, sans-serif;">
    <tr>
        <td align="center">

            <!-- CONTAINER (A4 STYLE) -->
            <table role="presentation" width="900" cellpadding="0" cellspacing="0" border="0" 
                   style="background-color: #ffffff; border: 1px solid #ddd;">

                <tr>
                    <td style="padding: 24px; color: #333; font-size: 14px; line-height: 1.6;">

                        <!-- OPENING -->
                        <p style="margin: 0 0 16px;">
                            Yth. <strong>{{ $namaSales }}</strong>,
                        </p>

                        <p style="margin: 0 0 16px;">
                            Permintaan jadwal sampling untuk nomor quotation 
                            <strong>{{ $noQt }}</strong> telah 
                            <strong>divalidasi</strong> oleh Adm. Sampling.
                        </p>

                        <!-- JUDUL SECTION -->
                        <p style="margin: 0 0 12px; font-weight: bold;">
                            Ringkasan Jadwal
                        </p>

                        <!-- INFO PELANGGAN (DOKUMEN STYLE) -->
                        <table width="100%" cellpadding="6" cellspacing="0" border="0" 
                               style="margin-bottom: 16px; font-size: 13px;">
                            <tr>
                                <td width="160" style="color: #555;"><strong>Pelanggan</strong></td>
                                <td>: {{ $namaPelanggan }}</td>
                            </tr>

                            @if (!empty($alamatSampling))
                            <tr>
                                <td style="color: #555;"><strong>Alamat Sampling</strong></td>
                                <td>: {{ $alamatSampling }}</td>
                            </tr>
                            @endif

                            @if (!empty($tanggalCetak))
                            <tr>
                                <td style="color: #555;"><strong>Dicetak / Referensi</strong></td>
                                <td>: {{ $tanggalCetak }}@if (!empty($jamCetak)) — {{ $jamCetak }}@endif</td>
                            </tr>
                            @endif
                        </table>

                        <!-- TABLE DATA: non-kontrak = satu tabel; kontrak = satu tabel per periode -->
                        @foreach ($sections as $sectionIndex => $section)
                        @if (!empty($perPeriode) && !empty($section['judulPeriode']))
                        <p style="margin: {{ $sectionIndex === 0 ? '0' : '20px' }} 0 8px; font-size: 13px; color: #333;">
                            <strong>Periode {{ $section['judulPeriode'] }}</strong>
                            @if (!empty($section['noDocumentSp']))
                            <span style="color: #555;"> — No. SP: <strong>{{ $section['noDocumentSp'] }}</strong></span>
                            @endif
                        </p>
                        @endif

                        <table role="presentation" cellpadding="6" cellspacing="0" border="1" width="100%"
                               style="border-collapse: collapse; border-color: #ccc; font-size: 13px; margin-bottom: {{ (!empty($perPeriode) && $sectionIndex < count($sections) - 1) ? '20px' : '0' }};">

                            <thead>
                                <tr style="background-color: #f2f2f2;">
                                    <th align="center" width="8%">No.</th>
                                    <th align="center" width="28%">Tanggal</th>
                                    <th align="center" width="18%">Jam</th>
                                    <th align="left" width="46%">Kategori</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($section['rows'] as $row)
                                <tr>
                                    <td align="center">{{ $row['no'] }}</td>
                                    <td align="center">{{ $row['tanggal'] }}</td>
                                    <td align="center">{{ $row['jam'] }}</td>
                                    <td>{{ $row['kategori'] }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" align="center" style="padding: 12px; color: #777;">
                                        Belum ada baris jadwal.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                        @endforeach

                        <!-- FOOTER -->
                        <p style="margin: 20px 0 0; font-size: 12px; color: #666;">
                            Email ini dibuat otomatis oleh sistem. Untuk detail dokumen jadwal resmi, 
                            silakan cek pada aplikasi.
                        </p>

                    </td>
                </tr>

            </table>
            <!-- END CONTAINER -->

        </td>
    </tr>
</table>