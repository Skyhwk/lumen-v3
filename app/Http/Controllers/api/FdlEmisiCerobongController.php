<?php

namespace App\Http\Controllers\api;

use App\Models\DataLapanganEmisiCerobong;
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;

use App\Models\EmisiCerobongHeader;
use App\Models\WsValueEmisiCerobong;

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

use App\Services\AnalystFormula;
use App\Models\AnalystFormula as Formula;

class FdlEmisiCerobongController extends Controller
{
    public function index(Request $request)
    {
        $this->autoBlock();
        $data = DataLapanganEmisiCerobong::with('detail')
            ->where('tipe', $request->mode_cerobong)
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
            ->filterColumn('metode', function ($query, $keyword) {
                $query->where('metode', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('diameter_cerobong', function ($query, $keyword) {
                $query->where('diameter_cerobong', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('cuaca', function ($query, $keyword) {
                $query->where('cuaca', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('kelembapan', function ($query, $keyword) {
                $query->where('kelembapan', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('tekanan_udara', function ($query, $keyword) {
                $query->where('tekanan_udara', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('waktu_pengukuran', function ($query, $keyword) {
                $query->where('waktu_pengukuran', 'like', '%' . $keyword . '%');
            })
            ->make(true);
    }

    public function updateNoSampel(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            DB::beginTransaction();
            try {
                $data = DataLapanganEmisiCerobong::where('id', $request->id)->first();

                EmisiCerobongHeader::where('no_sampel', $request->no_sampel_lama)
                    ->update(
                        [
                            'no_sampel' => $request->no_sampel_baru,
                            'no_sampel_lama' => $request->no_sampel_lama
                        ]
                    );

                WsValueEmisiCerobong::where('no_sampel', $request->no_sampel_lama)
                    ->update(
                        [
                            'no_sampel' => $request->no_sampel_baru,
                            'no_sampel_lama' => $request->no_sampel_lama
                        ]
                    );

                $data->no_sampel = $request->no_sampel_baru;
                $data->no_sampel_lama = $request->no_sampel_lama;
                $data->updated_by = $this->karyawan;
                $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                $order_detail_lama = OrderDetail::where('no_sampel', $request->no_sampel_lama)->first();

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

    public function approve(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = DataLapanganEmisiCerobong::where('id', $request->id)->first();
            $detail = OrderDetail::where('no_sampel', $data->no_sampel)->where('is_active', true)->first();
            
            if ($detail && isset($detail->parameter)) {
                $params = json_decode($detail->parameter, true);

                $parameterList = [];

                if (is_array($params)) {
                    foreach ($params as $param) {
                        [$id, $nama] = array_map('trim', explode(';', $param, 2));
                        $parameterList[] = [
                            'id' => $id,
                            'nama' => $nama
                        ];
                    }
                }
            }
            $paramList = [
                'CO2',
                'O2',
                'Opasitas',
                'Suhu',
                'Velocity',
                'CO2 (ESTB)',
                'O2 (ESTB)',
                'Opasitas (ESTB)',
                'NO2',
                'NO',
                'SO2',
                'NOx',
                'Effisiensi Pembakaran',
                'Eff. Pembakaran',
                'CO',
                'C O',         // dipertahankan seperti permintaan
                'SO2 (P)',
                'CO (P)',
                'O2 (P)',
                'Tekanan Udara',
                'NO2-Nox (P)',
            ];

                        // ambil nama parameter dari order
            $orderedParameters = array_column($parameterList, 'nama');

            // filter, hanya parameter yang ada di whitelist
            $parameters = array_values(array_intersect($orderedParameters, $paramList));

            foreach ($parameters as $key => $value) {
                $parameter = Parameter::where('nama_lab', $value)
                    ->where('nama_kategori', 'Emisi')
                    ->where('is_active', true)
                    ->first();


                $functionObj = Formula::where('id_parameter', $parameter->id)
                    ->where('is_active', true)
                    ->first();
                if (in_array($value, $paramList)) {
                    $function = 'EmisiCerobongDirect';
                }

                $data_kalkulasi = AnalystFormula::where('function', $function)
                    ->where('data', $data)
                    ->where('id_parameter', $parameter->nama_lab)
                    ->process();

                $header = EmisiCerobongHeader::firstOrNew([
                    'no_sampel' => $data->no_sampel,
                    'id_parameter' => $parameter->id,
                ]);

                $header->fill([
                    'no_sampel' => $data->no_sampel,
                    'id_parameter' => $parameter->id,
                    'parameter' => $value,
                    'tanggal_terima' => $detail->tanggal_terima,
                    'template_stp' => 30,
                    'is_approved' => true,
                    'approved_by' => $this->karyawan,
                    'approved_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'created_by' => $this->karyawan,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                ]);

                $header->save();

                $valueEmisi = WsValueEmisiCerobong::firstOrNew([
                    'id_emisi_cerobong_header' => $header->id,
                    'no_sampel' => $data->no_sampel
                ]);

                $valueEmisi->fill([
                    'id_emisi_cerobong_header' => $header->id,
                    'no_sampel' => $data->no_sampel,
                    'id_parameter' => $parameter->id,
                    'C' => $data_kalkulasi['C1'] ?? null,
                    'C1' => $data_kalkulasi['C2'] ?? null,
                    'C2' => $data_kalkulasi['C3'] ?? null,
                    'C3' => $data_kalkulasi['C4'] ?? null,
                    'C4' => $data_kalkulasi['C5'] ?? null,
                    'C5' => $data_kalkulasi['C6'] ?? null,
                    'C6' => $data_kalkulasi['C7'] ?? null,
                    'C7' => $data_kalkulasi['C8'] ?? null,
                    'C8' => $data_kalkulasi['C9'] ?? null,
                    'C9' => $data_kalkulasi['C10'] ?? null,
                    'C10' => $data_kalkulasi['C11'] ?? null,
                    'C11' => $data_kalkulasi['C12'] ?? null,
                    'created_by' => $this->karyawan,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'suhu' => $data->suhu,
                    'Pa' => $data->tekanan_udara,
                    'suhu_cerobong' => $data->T_Flue,
                    'satuan' => $data_kalkulasi['satuan'] ?? null,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'created_by' => $this->karyawan,
                    'is_active' => true,
                ]);
                $valueEmisi->save();

            }
            
            $data->is_approve = 1;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            DB::commit();
            return response()->json([
                'message' => 'Berhasil approve data'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal approve data',
                'error' => $e->getMessage()
            ], 401);
        }
    }

    public function reject(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganEmisiCerobong::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;
            $ws_value = WsValueEmisiCerobong::Where('no_sampel', $no_sample)->get();
            foreach ($ws_value as $key => $value) {
                $value->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                $value->deleted_by = $this->karyawan;
                $value->save();
            }
            $data->is_approve = false;
            $data->rejected_at = Carbon::now();
            $data->rejected_by = $this->karyawan;
            $data->save();
            
            return response()->json([
                'message' => "Data FDL EMISI CEROBONG dengan No Sampel $no_sample berhasil direject oleh $this->karyawan",
                'master_kategori' => 1
            ], 201);
        } else {
            return response()->json([
                'message' => "Data FDL EMISI CEROBONG dengan No Sampel $no_sample gagal direject oleh $this->karyawan"
            ], 401);
        }
    }

    public function rejectData(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganEmisiCerobong::where('id', $request->id)->first();

            $data->is_rejected = true;
            $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->rejected_by = $this->karyawan;
            $data->save();

            app(NotificationFdlService::class)->sendRejectNotification('Emisi Cerobong', $request->no_sampel, $request->reason, $this->karyawan, $data->created_by);
            
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
            $data = DataLapanganEmisiCerobong::where('id', $request->id)->first();
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

            // if($this->pin!=null){
            //     $nama = $this->name;
            //     $txt = "FDL Emisi Cerobong dengan No sample $no_sample Telah di Hapus oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

            return response()->json([
                'message' => 'Data has ben Delete',
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
                $data = DataLapanganEmisiCerobong::where('id', $request->id)->first();
                $data->is_blocked = false;
                $data->blocked_by = null;
                $data->blocked_at = null;
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Unblocked for user',
                    'master_kategori' => 1
                ], 200);
            } else {
                $data = DataLapanganEmisiCerobong::where('id', $request->id)->first();
                $data->is_blocked = true;
                $data->blocked_by = $this->karyawan;
                $data->blocked_at = Carbon::now();
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Blocked for user',
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
        $data = DataLapanganEmisiCerobong::with('detail')->where('id', $request->id)->first();

        $this->resultx = 'get Detail sample lapangan Emisi Cerobong success';

        return response()->json([
            'id' => $data->id,
            'no_sample' => $data->detail->no_sample,
            'no_order' => $data->detail->no_order,
            'sub_kategori' => explode('-', $data->detail->kategori_3)[1],
            'sampler' => $data->created_by,
            'nama_perusahaan' => $data->detail->nama_perusahaan,
            'keterangan' => $data->keterangan,
            'keterangan_2' => $data->keterangan_2,
            'koordinat' => $data->titik_koordinat,
            'latitude' => $data->latitude,
            'longitude' => $data->longitude,
            'sumber_emisi' => $data->sumber_emisi,
            'merk' => $data->merk,
            'bahan_bakar' => $data->bahan_bakar,
            'cuaca' => $data->cuaca,
            'kecepatan_angin' => $data->kecepatan_angin,
            'diameter_cerobong' => $data->diameter_cerobong,
            'durasi_operasi' => $data->durasi_operasi,
            'proses_filtrasi' => $data->proses_filtrasi,
            'metode' => $data->metode,
            'T_Flue' => $data->T_Flue,
            'velocity' => $data->velocity,
            'waktu_pengukuran' => $data->waktu_pengukuran,
            'suhu' => $data->suhu,
            'kelembaban' => $data->kelembaban,
            'tekanan_udara' => $data->tekanan_udara,
            'opasitas' => $data->opasitas,
            'O2 (ESTB)' => $data->O2,
            'co' => $data->CO,
            'CO2 (ESTB)' => $data->CO2,
            'no' => $data->NO,
            'no2' => $data->NO2,
            'so2' => $data->SO2,
            'partikulat' => $data->partikulat,
            'hf' => $data->HF,
            'hci' => $data->HCI,
            'h2s' => $data->H2S,
            'nh3' => $data->NH3,
            'ci2' => $data->CI2,
            'foto_lokasi' => $data->foto_lokasi_sampel,
            'foto_kondisi' => $data->foto_kondisi_sampel,
            'foto_lain' => $data->foto_lain,

            'status' => '200'
        ], 200);
    }

    protected function autoBlock()
    {
        $tgl = Carbon::now()->subDays(7);
        $data = DataLapanganEmisiCerobong::where('is_blocked', 0)->where('created_at', '<=', $tgl)->update(['is_blocked' => 1, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }
}