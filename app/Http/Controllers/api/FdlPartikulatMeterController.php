<?php

namespace App\Http\Controllers\api;

use App\Models\DataLapanganPartikulatMeter;
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;

use App\Models\WsValueUdara;
use App\Models\PartikulatHeader;

use App\Services\NotificationFdlService;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class FdlPartikulatMeterController extends Controller
{
    public function index(Request $request)
    {
        $this->autoBlock();
        $data = DataLapanganPartikulatMeter::with('detail')
            ->orderBy('id', 'desc');

        return Datatables::of($data)
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->where('created_by', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('detail.tanggal_sampling', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('tanggal_sampling', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('detail.nama_perusahaan', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('nama_perusahaan', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('detail.no_order', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('no_order', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('no_sampel', function ($query, $keyword) {
                $query->where('no_sampel', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('no_sampel_lama', function ($query, $keyword) {
                $query->where('no_sampel_lama', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('detail.kategori_3', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('kategori_3', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('parameter', function ($query, $keyword) {
                $query->where('parameter', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('lokasi', function ($query, $keyword) {
                $query->where('lokasi', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('waktu_pengukuran', function ($query, $keyword) {
                $query->where('waktu_pengukuran', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('shift_pengambilan', function ($query, $keyword) {
                $query->where('shift_pengambilan', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('suhu', function ($query, $keyword) {
                $query->where('suhu', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('kelembapan', function ($query, $keyword) {
                $query->where('kelembapan', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('tekanan_udara', function ($query, $keyword) {
                $query->where('tekanan_udara', 'like', '%' . $keyword . '%');
            })
            ->make(true);
    }

    public function updateNoSampel(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            DB::beginTransaction();
            try {
                $data = DataLapanganPartikulatMeter::where('id', $request->id)->first();
                $data->no_sampel = $request->no_sampel_baru;
                $data->no_sampel_lama = $request->no_sampel_lama;
                $data->updated_by = $this->karyawan;
                $data->updated_at = Carbon::now();
                $data->save();

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

    public function approve(Request $request)
    {
        if (isset($request->id) && $request->id != null) {

            $data = DataLapanganPartikulatMeter::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;

            $data->is_approve = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now();
            $data->rejected_at = null;
            $data->rejected_by = null;
            $data->save();

            // $activeFdl = DataLapanganPartikulatMeter::where('no_sampel', $no_sample)
            //     ->where('parameter', $data->parameter)
            //     ->get();

            // if (count($activeFdl) == count($activeFdl->where('is_approve', true))) {
            //     $pengukuran = $activeFdl->pluck('pengukuran', 'shift_pengambilan')->toArray();

            //     $rerata = [];
            //     foreach ($pengukuran as $shift => $json) {
            //         $decoded = json_decode($json, true);
            //         $values = array_map('intval', $decoded);

            //         $total = array_sum($values);
            //         $count = count($values);
            //         $rerata[$shift] = $count > 0 ? round($total / $count, 2) : null;
            //     }

            //     $globalTotal = array_sum($rerata);
            //     $globalCount = count($rerata);
            //     $rerata_total = $globalCount > 0 ? round($globalTotal / $globalCount, 4) : null;

            //     $existingHeader = PartikulatHeader::where('no_sampel', $no_sample)
            //         ->where('parameter', $data->parameter)
            //         ->first();

            //     $header = PartikulatHeader::updateOrCreate(
            //         [
            //             'no_sampel' => $no_sample,
            //             'parameter' => $data->parameter,
            //         ],
            //         [
            //             'pengukuran' => json_encode($rerata),
            //             'rerata' => $rerata_total,
            //             'created_by' => $existingHeader ? $existingHeader->created_by : $this->karyawan,
            //             'created_at' => $existingHeader ? $existingHeader->created_at : Carbon::now(),
            //             'is_active' => true,
            //             'is_approve' => true,
            //             'approved_by' => $this->karyawan,
            //             'approved_at' => Carbon::now()->format('Y-m-d H:i:s')
            //         ]
            //     );

            //     $wsUdara = WsValueUdara::updateOrCreate(
            //         [
            //             'id_partikulat_header' => $header->id,
            //             'no_sampel' => $no_sample,
            //             'id_po' => $data->detail->id,
            //         ],
            //         [
            //             'hasil1' => $rerata_total,
            //             'is_active' => true
            //         ]
            //     );
            // }

            app(NotificationFdlService::class)->sendApproveNotification("Partikulat Meter pada Shift($data->shift_pengambilan)", "$data->no_sampel($data->parameter)", $this->karyawan, $data->created_by);

            return response()->json([
                'message' => "Data Dengan No Sampel $no_sample Telah di Approve oleh $this->karyawan",
                'master_kategori' => 1
            ], 200);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    public function reject(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganPartikulatMeter::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;

            $data->is_approve = false;
            $data->rejected_at = Carbon::now();
            $data->rejected_by = $this->karyawan;
            $data->approved_by = null;
            $data->approved_at = null;
            $data->save();

            return response()->json([
                'message' => "Data Dengan No Sampel $no_sample Telah di reject oleh $this->karyawan",
                'master_kategori' => 1
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
            $data = DataLapanganPartikulatMeter::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;
            $foto_lokasi = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
            $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
            if (is_file($foto_lokasi)) {
                unlink($foto_lokasi);
            }

            if (is_file($foto_lain)) {
                unlink($foto_lain);
            }
            $data->delete();

            return response()->json([
                'message' => "Data Dengan No Sampel $no_sample Telah di hapus oleh $this->karyawan",
                'master_kategori' => 1
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
                $data = DataLapanganPartikulatMeter::where('id', $request->id)->first();
                $data->is_blocked = false;
                $data->blocked_by = null;
                $data->blocked_at = null;
                $data->save();
                return response()->json([
                    'message' => "Data Dengan No Sampel $data->no_sampel Telah di Unblock oleh $this->karyawan",
                    'master_kategori' => 1
                ], 200);
            } else {
                $data = DataLapanganPartikulatMeter::where('id', $request->id)->first();
                $data->is_blocked = true;
                $data->blocked_by = $this->karyawan;
                $data->blocked_at = Carbon::now();
                $data->save();
                return response()->json([
                    'message' => "Data Dengan No Sampel $data->no_sampel Telah di block oleh $this->karyawan",
                    'master_kategori' => 1
                ], 200);
            }
        } else {
            return response()->json([
                'message' => 'Gagal Melakukan Blocked'
            ], 401);
        }
    }

    public function detail(Request $request)
    {
        $data = DataLapanganPartikulatMeter::with('detail')
            ->where('id', $request->id)
            ->first();

        $this->resultx = 'get Detail FDL Partikulat Meter Berhasil';

        return response()->json([
            'id' => $data->id,
            'no_sample' => $data->no_sampel,
            'no_order' => $data->detail->no_order,
            'sub_kategori' => explode('-', $data->detail->kategori_3)[1],
            'id_sub_kategori' => explode('-', $data->detail->kategori_3)[0],
            'sampler' => $data->created_by,
            'nama_perusahaan' => $data->detail->nama_perusahaan,
            'keterangan' => $data->keterangan,
            'keterangan_2' => $data->keterangan_2,
            'latitude' => $data->latitude,
            'longitude' => $data->longitude,
            'message' => $this->resultx,

            'parameter' => $data->parameter,
            'lokasi' => $data->lokasi,
            'waktu_pengukuran' => $data->waktu_pengukuran,
            'shift_pengambilan' => $data->shift_pengambilan,
            'suhu' => $data->suhu,
            'kelembapan' => $data->kelembapan,
            'tekanan_udara' => $data->tekanan_udara,
            'pengukuran' => $data->pengukuran,

            'koordinat' => $data->titik_koordinat,
            'foto_lokasi' => $data->foto_lokasi_sampel,
            'foto_lain' => $data->foto_lain,
            'aktifitas' => $data->aktifitas,
            'status' => '200'
        ], 200);
    }

    protected function autoBlock()
    {
        $tgl = Carbon::now()->subDays(7);
        $data = DataLapanganPartikulatMeter::where('is_blocked', 0)->where('created_at', '<=', $tgl)->update(['is_blocked' => 1, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }
}