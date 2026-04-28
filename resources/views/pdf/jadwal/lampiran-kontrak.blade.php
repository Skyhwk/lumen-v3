{{-- Lampiran: tabel ringkas No, Tanggal, Jam, Petugas sampler, Kategori --}}
                <table class="tabel" width="100%">
                    <tr class="tr_top">
                        <td class="text-left text-wrap" style="width: 33.33%;"><img class="img_0"
                                src="{{ public_path() }}/img/isl_logo.png" alt="ISL" width="80">
                        </td>
                        <td style="width: 33.33%; text-align: center;">
                            <h5 style="text-align:center; font-size:14px;"><b><u>SAMPLING PLAN</u></b></h5>
                            <p style="font-size: 10px;text-align:center;margin-top: -10px;">{{ $periode_ }}</p>
                        </td>
                        <td style="text-align: right;">
                            <p style="font-size: 9px; text-align:right;">{{ $tanggalCetak }} - {{ $jamCetak }}</p> <br>
                            <span style="font-size:11px; font-weight: bold; border: 1px solid gray;">{{ $status_kontrak }}</span>
                            <span style="font-size:11px; font-weight: bold; border: 1px solid gray;">{{ $sampling }}</span>
                        </td>
                    </tr>
                </table>
                <table class="table table-bordered" width="100%">
                    <tr>
                        <td colspan="2" style="font-size: 12px; padding: 5px;"><h6 style="font-size:12px; font-weight: bold;">{!! $perusahaan !!}</h6></td>
                        <td style="font-size: 12px; padding: 5px;"><span style="font-size:12px; font-weight: bold;">{{ $sampling_plan->no_quotation }}</span></td>
                    </tr>
                    <tr>
                        <td colspan="2" style="font-size: 12px; padding: 5px;"><span style="font-size:12px;">{{ $data->alamat_sampling }}</span></td>
                        <td style="font-size: 12px; padding: 5px;"><span style="font-size:12px; font-weight: bold;">{{ $sampling_plan->no_document }}</span></td>
                    </tr>
                </table>

                <table class="table table-bordered" style="font-size: 8px; margin-top:10px;" width="100%">
                    <thead class="text-center">
                        <tr>
                            <th width="5%" style="padding: 5px !important;">NO</th>
                            <th width="22%">Tanggal</th>
                            <th width="12%">Jam</th>
                            <th width="20%">Petugas sampler</th>
                            <th width="41%">Kategori</th>
                        </tr>
                    </thead>
                    <tbody>
@foreach ($lampiranRows as $row)
                        <tr>
                            <td style="vertical-align: middle; text-align:center; font-size: 11px;">{{ $row['no'] }}</td>
                            <td style="vertical-align: middle; text-align:center; font-size: 11px;">{{ $row['tanggal'] }}</td>
                            <td style="vertical-align: middle; text-align:center; font-size: 11px;">{{ $row['jam'] }}</td>
                            <td style="vertical-align: middle; text-align:center; font-size: 10px; padding: 4px;">{{ $row['petugas_sampler'] ?? '—' }}</td>
                            <td style="vertical-align: middle; font-size: 11px; padding: 5px;">{{ $row['kategori'] }}</td>
                        </tr>
@endforeach
                    </tbody>
                </table>
