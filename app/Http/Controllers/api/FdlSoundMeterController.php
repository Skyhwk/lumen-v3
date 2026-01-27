<?php

namespace App\Http\Controllers\api;

use App\Models\DataLapanganKebisinganBySoundMeter;
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterJabatan;
use App\Models\Parameter;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Mpdf;
use PhpOffice\PhpSpreadsheet\IOFactory;
use \PhpOffice\PhpSpreadsheet\Writer\Pdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use ZipArchive;
use \App\Services\MpdfService as PDF;
use File;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class FdlSoundMeterController extends Controller
{
    public function index(Request $request)
    {
        $this->autoBlock();
        $data = DataLapanganKebisinganBySoundMeter::with(['detail','catatan'])->orderBy('id', 'desc');

        return Datatables::of($data)
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->where('created_by', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('detail.tanggal_sampling', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('tanggal_sampling', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('created_at', function ($query, $keyword) {
                $query->where('created_at', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('no_sampel', function ($query, $keyword) {
                $query->where('no_sampel', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('no_sampel_lama', function ($query, $keyword) {
                $query->where('no_sampel_lama', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('detail.nama_perusahaan', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('nama_perusahaan', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('detail.kategori_2', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('kategori_2', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('detail.kategori_3', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('kategori_3', 'like', '%' . $keyword . '%');
                });
            })
            ->make(true);
    }

    public function approve(Request $request)
    {

        DB::beginTransaction();
        try {
            if (isset($request->id) && $request->id != null) {
                $data = DataLapanganKebisinganBySoundMeter::where('id', $request->id)->first();
                if ($data) {
                    $data->is_approve = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();

                    DB::commit();
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Data no sampel ' . $data->no_sampel . ' berhasil diapprove'
                    ]);
                }
            } else {
                return response()->json([
                    'message' => 'Gagal Approve'
                ], 401);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function reject(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganAir::where('id', $request->id)->first();

            $data->is_approve = false;
            $data->rejected_at = date('Y-m-d H:i:s');
            $data->rejected_by = $this->karyawan;
            $data->approved_by = null;
            $data->approved_at = null;
            $data->save();

            return response()->json([
                'message' => 'Data no sample ' . $data->no_sampel . ' telah di reject'
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    public function delete(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganAir::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;
            $foto_lok = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
            $foto_kon = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
            $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
            if (is_file($foto_lok)) {
                unlink($foto_lok);
            }
            if (is_file($foto_kon)) {
                unlink($foto_kon);
            }
            if (is_file($foto_lain)) {
                unlink($foto_lain);
            }
            $data->delete();

            return response()->json([
                'message' => 'Data no sample ' . $request->no_sampel . ' telah di hapus'
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    public function block(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            if ($request->is_blocked == true) {
                $data = DataLapanganAir::where('id', $request->id)->first();
                $data->is_blocked = true;
                $data->blocked_by = $this->karyawan;
                $data->blocked_at = date('Y-m-d H:i:s');
                $data->save();
                return response()->json([
                    'message' => 'Data no sample ' . $data->no_sampel . ' telah di block untuk user'
                ], 200);
            } else {
                $data = DataLapanganAir::where('id', $request->id)->first();
                $data->is_blocked = false;
                $data->blocked_by = null;
                $data->blocked_at = null;
                $data->save();
                return response()->json([
                    'message' => 'Data no sample ' . $data->no_sampel . ' telah di unblock untuk user'
                ], 200);
            }
        } else {
            return response()->json([
                'message' => 'Gagal Melakukan Blocked'
            ], 401);
        }
    }

    public function updateNoSampel(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            DB::beginTransaction();
            try {
                $data = DataLapanganAir::where('no_sampel', $request->no_sampel_lama)->whereNull('no_sampel_lama')->get();

                $ws = WsValueAir::where('no_sampel', $request->no_sampel_lama)->get();

                if ($ws->isNotEmpty()) {
                    Titrimetri::where('no_sampel', $request->no_sampel_lama)->update([
                        'no_sampel' => $request->no_sampel_baru,
                        'no_sampel_lama' => $request->no_sampel_lama
                    ]);
                    Colorimetri::where('no_sampel', $request->no_sampel_lama)->update([
                        'no_sampel' => $request->no_sampel_baru,
                        'no_sampel_lama' => $request->no_sampel_lama
                    ]);
                    Gravimetri::where('no_sampel', $request->no_sampel_lama)->update([
                        'no_sampel' => $request->no_sampel_baru,
                        'no_sampel_lama' => $request->no_sampel_lama
                    ]);
                    Subkontrak::where('no_sampel', $request->no_sampel_lama)->update([
                        'no_sampel' => $request->no_sampel_baru,
                        'no_sampel_lama' => $request->no_sampel_lama
                    ]);
                }

                $ws = WsValueAir::where('no_sampel', $request->no_sampel_lama)->update([
                    'no_sampel' => $request->no_sampel_baru,
                    'no_sampel_lama' => $request->no_sampel_lama
                ]);

                // Jika data ditemukan (Collection berisi elemen)
                $data->each(function ($item) use ($request) {
                    $item->no_sampel = $request->no_sampel_baru;
                    $item->no_sampel_lama = $request->no_sampel_lama;
                    $item->updated_by = $this->karyawan;  // Pastikan $this->karyawan valid
                    $item->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                    $item->save();  // Simpan perubahan untuk setiap item
                });

                // update OrderDetail
                $order_detail_lama = OrderDetail::where('no_sampel', $request->no_sampel_lama)
                    ->first();

                if ($order_detail_lama) {
                    OrderDetail::where('no_sampel', $request->no_sampel_baru)
                        ->where('is_active', 1)
                        ->update([
                            'tanggal_terima' => $order_detail_lama->tanggal_terima
                        ]);
                }


                DB::commit();
                return response()->json([
                    'message' => 'Berhasil ubah no sampel ' . $request->no_sampel_lama . ' menjadi ' . $request->no_sampel_baru
                ], 200);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Gagal ubah no sampel ' . $request->no_sampel_lama . ' menjadi ' . $request->no_sampel_baru,
                    'error' => $e->getMessage()
                ], 401);
            }
        } else {
            return response()->json([
                'message' => 'No Sampel tidak boleh kosong'
            ], 401);
        }
    }

    public function detail(Request $request)
    {
        try {
            $data = DataLapanganAir::with('detail')->where('id', $request->id)->first();
            $this->resultx = 'get Detail sample lapangan success';

            if ($data->debit_air == null) {
                $debit = 'Data By Customer';
            } else {
                $debit = $data->debit_air;
            }
            // dd($debit);

            return response()->json([
                'id' => $data->id,
                'no_sample' => $data->no_sampel,
                'no_order' => $data->detail->no_order,
                'sampler' => $data->created_by,
                'jam' => $data->jam_pengambilan,
                'nama_perusahaan' => $data->detail->nama_perusahaan,
                'jenis' => explode('-', $data->detail->kategori_3)[1],
                'keterangan' => $data->keterangan,
                'jenis_produksi' => $data->jenis_produksi,
                'pengawet' => $data->jenis_pengawet,
                'teknik' => $data->teknik_sampling,
                'warna' => $data->warna,
                'bau' => $data->bau,
                'volume' => $data->volume,
                'suhu_air' => $data->suhu_air,
                'suhu_udara' => $data->suhu_udara,
                'ph' => $data->ph,
                'tds' => $data->tds,
                'dhl' => $data->dhl,
                'do' => $data->do,
                'debit' => $debit,
                'latitude' => $data->latitude,
                'longitude' => $data->longitude,
                'koordinat' => $data->titik_koordinat,
                'message' => $this->resultx,
                'jumlah_titik_pengambilan' => $data->jumlah_titik_pengambilan,
                'jenis_fungsi_air' => $data->jenis_fungsi_air,
                'perlakuan_penyaringan' => $data->perlakuan_penyaringan,
                'pengendalian_mutu' => $data->pengendalian_mutu,
                'teknik_pengukuran_debit' => $data->teknik_pengukuran_debit,
                'klor_bebas' => $data->klor_bebas,
                'id_sub_kategori' => explode('-', $data->detail->kategori_3)[0],
                'jenis_sample' => $data->jenis_sample,
                'ipal' => $data->status_kesediaan_ipal,
                'lokasi_sampling' => $data->lokasi_sampling,
                'diameter' => $data->diameter_sumur,
                'kedalaman1' => $data->kedalaman_sumur1,
                'kedalaman2' => $data->kedalaman_sumur2,
                'kedalamanair' => $data->kedalaman_air_terambil,
                'total_waktu' => $data->total_waktu,
                'kedalaman_titik' => $data->kedalaman_titik,
                'lokasi_pengambilan' => $data->lokasi_titik_pengambilan,
                'salinitas' => $data->salinitas,
                'kecepatan_arus' => $data->kecepatan_arus,
                'arah_arus' => $data->arah_arus,
                'pasang_surut' => $data->pasang_surut,
                'kecerahan' => $data->kecerahan,
                'lapisan_minyak' => $data->lapisan_minyak,
                'cuaca' => $data->cuaca,
                'info_tambahan' => $data->informasi_tambahan,
                'keterangan' => $data->keterangan,
                'foto_lok' => $data->foto_lokasi_sampel,
                'foto_kondisi' => $data->foto_kondisi_sampel,
                'foto_lain' => $data->foto_lain,
                'sampah' => $data->sampah,
                'status' => '200'
            ], 200);

        } catch (\exeption $err) {
            dd($err);
        }
    }

    protected function autoBlock()
    {
        $tgl = Carbon::now()->subDays(3);
        $data = DataLapanganKebisinganBySoundMeter::where('is_blocked', 0)->where('created_at', '<=', $tgl)->update(['is_blocked' => 1, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }
}