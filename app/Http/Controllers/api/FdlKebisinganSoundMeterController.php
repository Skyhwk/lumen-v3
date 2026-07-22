<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DataLapanganKebisinganBySoundMeter;
use App\Models\DetailSoundMeter;
use App\Models\KebisinganHeader;
use App\Models\OrderDetail;
use App\Models\Parameter;
use App\Models\WsValueUdara;
use App\Services\NotificationFdlService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Yajra\Datatables\Datatables;

class FdlKebisinganSoundMeterController extends Controller
{
    public function index(Request $request)
    {
        $this->autoBlock();

        $data = DataLapanganKebisinganBySoundMeter::with(['detail', 'catatan', 'kebisinganHeader', 'kebisinganHeader.ws_udara'])->orderBy('id', 'desc');

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

    public function detailSoundMeter(Request $request)
    {
        $data = DetailSoundMeter::where('no_sampel', $request->no_sampel)
            ->when($request->kode, function ($query) use ($request) {
                $query->where('id_device', $request->kode);
            })
            ->orderBy('timestamp', 'desc');

        return Datatables::of($data)->make(true);
    }

    public function approve(Request $request)
    {
        if (!isset($request->id) || $request->id == null) {
            return response()->json(['message' => 'Gagal Approve'], 401);
        }

        DB::beginTransaction();
        try {
            $data = DataLapanganKebisinganBySoundMeter::where('id', $request->id)->first();
            if (!$data) {
                DB::rollBack();
                return response()->json(['message' => 'Data lapangan tidak ditemukan'], 404);
            }

            $orderDetail = OrderDetail::where('no_sampel', $data->no_sampel)->where('is_active', true)->first();
            if (!$orderDetail) {
                DB::rollBack();
                return response()->json(['message' => 'Order detail tidak ditemukan'], 404);
            }

            $parameterInfo = $this->resolveParameter($orderDetail);
            $calculation = $this->calculateSoundMeter($data->no_sampel);

            if (!$calculation['hasil1']) {
                DB::rollBack();
                return response()->json(['message' => 'Data pembacaan sound meter belum tersedia'], 422);
            }

            $header = KebisinganHeader::where('no_sampel', $data->no_sampel)
                ->where('id_parameter', $parameterInfo['id'])
                ->where('is_active', true)
                ->first();

            if (!$header) {
                $header = new KebisinganHeader();
                $header->created_by = $this->karyawan;
                $header->created_at = Carbon::now()->format('Y-m-d H:i:s');
            }

            $header->no_sampel = $data->no_sampel;
            $header->no_sampel_lama = $data->no_sampel_lama;
            $header->id_parameter = $parameterInfo['id'];
            $header->parameter = $parameterInfo['nama'];
            $header->leq = $calculation['leq'];
            $header->ls = $calculation['ls'];
            $header->lm = $calculation['lm'];
            $header->min = $calculation['min'];
            $header->max = $calculation['max'];
            $header->data_per_shift = json_encode($calculation['data_per_shift']);
            $header->suhu_udara = $data->suhu_udara;
            $header->kelembapan_udara = $data->kelembapan_udara;
            $header->is_approved = true;
            $header->approved_by = $this->karyawan;
            $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $header->save();

            $wsValue = WsValueUdara::where('no_sampel', $data->no_sampel)->where('is_active', true)->first();
            if (!$wsValue) {
                $wsValue = new WsValueUdara();
            }

            $wsValue->id_po = $orderDetail->id;
            $wsValue->no_sampel = $data->no_sampel;
            $wsValue->id_kebisingan_header = $header->id;
            $wsValue->hasil1 = $calculation['hasil1'];
            $wsValue->satuan = 'dBA';
            $wsValue->save();

            if (Schema::hasColumn($data->getTable(), 'is_approved')) {
                $data->is_approved = true;
            }
            if (Schema::hasColumn($data->getTable(), 'is_approve')) {
                $data->is_approve = true;
            }
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->rejected_at = null;
            $data->rejected_by = null;
            $data->save();

            app(NotificationFdlService::class)->sendApproveNotification('Kebisingan Sound Meter', $data->no_sampel, $this->karyawan, $data->created_by);

            DB::commit();
            return response()->json([
                'message' => 'Data no sampel ' . $data->no_sampel . ' berhasil diapprove',
                'hasil' => $calculation['hasil1'],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function reject(Request $request)
    {
        if (!isset($request->id) || $request->id == null) {
            return response()->json(['message' => 'Gagal Reject'], 401);
        }

        $data = DataLapanganKebisinganBySoundMeter::where('id', $request->id)->first();
        if (!$data) {
            return response()->json(['message' => 'Data lapangan tidak ditemukan'], 404);
        }

        if (Schema::hasColumn($data->getTable(), 'is_approved')) {
            $data->is_approved = false;
        }
        if (Schema::hasColumn($data->getTable(), 'is_approve')) {
            $data->is_approve = false;
        }
        $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
        $data->rejected_by = $this->karyawan;
        $data->approved_by = null;
        $data->approved_at = null;
        $data->save();

        return response()->json(['message' => 'Data no sampel ' . $data->no_sampel . ' telah di reject'], 201);
    }

    public function rejectData(Request $request)
    {
        if (!isset($request->id) || $request->id == null) {
            
            try {
                app(\App\Services\RejectFdlService::class)->recordReject(
                    $data,
                    $this->karyawan,
                    $request->reason ?? null,
                    'Fdl Kebisingan Sound Meter'
                );
            } catch (\Exception $e) {
                // Ignore if it fails
            }

            return response()->json(['message' => 'Gagal Reject Data'], 401);
        }

        $data = DataLapanganKebisinganBySoundMeter::where('id', $request->id)->first();
        if (!$data) {
            return response()->json(['message' => 'Data lapangan tidak ditemukan'], 404);
        }

        $data->is_rejected = true;
        $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
        $data->rejected_by = $this->karyawan;
        $data->save();

        app(NotificationFdlService::class)->sendRejectNotification('Kebisingan Sound Meter', $data->no_sampel, $request->reason, $this->karyawan, $data->created_by);

        return response()->json(['message' => 'Data no sample ' . $data->no_sampel . ' telah di reject'], 201);
    }

    public function block(Request $request)
    {
        if (!isset($request->id) || $request->id == null) {
            return response()->json(['message' => 'Gagal Melakukan Blocked'], 401);
        }

        $data = DataLapanganKebisinganBySoundMeter::where('id', $request->id)->first();
        if (!$data) {
            return response()->json(['message' => 'Data lapangan tidak ditemukan'], 404);
        }

        if ($request->is_blocked == true) {
            $data->is_blocked = false;
            $data->blocked_by = null;
            $data->blocked_at = null;
            $message = 'Data no sample ' . $data->no_sampel . ' telah di unblock untuk user';
        } else {
            $data->is_blocked = true;
            $data->blocked_by = $this->karyawan;
            $data->blocked_at = Carbon::now()->format('Y-m-d H:i:s');
            $message = 'Data no sample ' . $data->no_sampel . ' telah di block untuk user';
        }

        $data->save();
        return response()->json(['message' => $message], 200);
    }

    public function delete(Request $request)
    {
        if (!isset($request->id) || $request->id == null) {
            return response()->json(['message' => 'Gagal Delete'], 401);
        }

        $data = DataLapanganKebisinganBySoundMeter::where('id', $request->id)->first();
        if (!$data) {
            return response()->json(['message' => 'Data lapangan tidak ditemukan'], 404);
        }

        $noSampel = $data->no_sampel;
        $this->deleteSamplingPhoto($data->foto_lokasi_sampel);
        $this->deleteSamplingPhoto($data->foto_kondisi_sampel);
        $this->deleteSamplingPhoto($data->foto_lain);
        $data->delete();

        return response()->json(['message' => 'Data no sample ' . $noSampel . ' telah di hapus'], 201);
    }

    public function updateNoSampel(Request $request)
    {
        if (!isset($request->id) || $request->id == null) {
            return response()->json(['message' => 'No Sampel tidak boleh kosong'], 401);
        }

        DB::beginTransaction();
        try {
            $data = DataLapanganKebisinganBySoundMeter::where('id', $request->id)->first();
            if (!$data) {
                DB::rollBack();
                return response()->json(['message' => 'Data lapangan tidak ditemukan'], 404);
            }

            KebisinganHeader::where('no_sampel', $request->no_sampel_lama)->update([
                'no_sampel' => $request->no_sampel_baru,
                'no_sampel_lama' => $request->no_sampel_lama,
            ]);

            WsValueUdara::where('no_sampel', $request->no_sampel_lama)->update([
                'no_sampel' => $request->no_sampel_baru,
                'no_sampel_lama' => $request->no_sampel_lama,
            ]);

            DetailSoundMeter::where('no_sampel', $request->no_sampel_lama)->update([
                'no_sampel' => $request->no_sampel_baru,
            ]);

            $orderDetailLama = OrderDetail::where('no_sampel', $request->no_sampel_lama)->first();
            if ($orderDetailLama) {
                OrderDetail::where('no_sampel', $request->no_sampel_baru)
                    ->where('is_active', 1)
                    ->update(['tanggal_terima' => $orderDetailLama->tanggal_terima]);
            }

            $data->no_sampel = $request->no_sampel_baru;
            $data->no_sampel_lama = $request->no_sampel_lama;
            $data->updated_by = $this->karyawan;
            $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            DB::commit();
            return response()->json(['message' => 'Berhasil ubah no sampel ' . $request->no_sampel_lama . ' menjadi ' . $request->no_sampel_baru], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal ubah no sampel ' . $request->no_sampel_lama . ' menjadi ' . $request->no_sampel_baru,
                'error' => $e->getMessage(),
            ], 401);
        }
    }

    protected function autoBlock()
    {
        $tgl = Carbon::now()->subDays(3);
        DataLapanganKebisinganBySoundMeter::where('is_blocked', 0)
            ->where('created_at', '<=', $tgl)
            ->update([
                'is_blocked' => 1,
                'blocked_by' => 'System',
                'blocked_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);
    }

    private function resolveParameter(OrderDetail $orderDetail)
    {
        $decoded = json_decode($orderDetail->parameter, true);
        $raw = is_array($decoded) ? ($decoded[0] ?? '') : '';
        $parts = explode(';', $raw);
        $id = $parts[0] ?? null;
        $nama = $parts[1] ?? null;

        $parameter = null;
        if ($id) {
            $parameter = Parameter::where('id', $id)->where('is_active', true)->first();
        }

        if (!$parameter && $nama) {
            $parameter = Parameter::where('nama_lab', $nama)->where('id_kategori', 4)->where('is_active', true)->first();
        }

        return [
            'id' => $parameter->id ?? $id,
            'nama' => $parameter->nama_lab ?? $nama,
        ];
    }

    private function calculateSoundMeter($noSampel)
    {
        $records = DetailSoundMeter::where('no_sampel', $noSampel)->get();
        $dataShift = [];
        $dbValues = [];

        $records->each(function ($item) use (&$dataShift, &$dbValues) {
            $shift = $item->shift ?: 'L1';
            $dataShift[$shift][] = (object) [
                'db' => $item->db,
                'laeq' => $item->LAeq,
            ];

            if (is_numeric($item->db)) {
                $dbValues[] = (float) $item->db;
            }
        });

        uksort($dataShift, function ($a, $b) {
            $numA = (int) substr($a, 1);
            $numB = (int) substr($b, 1);
            return $numA - $numB;
        });

        $shiftSummary = [];
        foreach ($dataShift as $shiftName => $items) {
            $laeqValues = array_map(function ($item) {
                return (float) $item->laeq;
            }, $items);

            $count = count($laeqValues);
            $sum = array_sum($laeqValues);
            $combinedLaeq = $count > 0 ? number_format((10 * log10((1 / $count) * $sum)), 1, '.', '') : null;
            $convertLAeq = $combinedLaeq ? number_format($combinedLaeq * 0.1, 2, '.', '') : null;
            $hasilConvert = $convertLAeq !== null ? number_format(pow(10, $convertLAeq), 2, '.', '') : null;

            $shiftSummary[$shiftName] = [
                'shift_sistem' => $shiftName,
                'total_count' => $count,
                'nilai_laeq' => $combinedLaeq,
                'hasil_laeq' => $combinedLaeq,
                'converted_laeq' => $convertLAeq,
                'convert_laeq' => $convertLAeq,
                'hasil_convert' => $hasilConvert,
            ];
        }

        $totalShift = count($shiftSummary);
        $leq = null;
        $ls = null;
        $lm = null;
        $hasil1 = null;

        if ($totalShift > 23) {
            $convertValues = array_column($shiftSummary, 'convert_laeq');
            $ls = $this->calculateLeqFromConverted(array_slice($convertValues, 0, 16), 16);
            $lm = $this->calculateLeqFromConverted(array_slice($convertValues, 16, 8), 8);
            $hasil1 = ($ls !== null && $lm !== null)
                ? number_format(10 * log10((1 / 24) * ((16 * pow(10, 0.1 * $ls)) + (8 * pow(10, 0.1 * ($lm + 5))))), 2, '.', '')
                : null;
            $ls = $ls !== null ? number_format($ls, 2, '.', '') : null;
            $lm = $lm !== null ? number_format($lm, 2, '.', '') : null;
        } elseif ($totalShift > 7) {
            $totalHasilConvert = 0;
            foreach ($shiftSummary as $summary) {
                $totalHasilConvert += (float) str_replace(',', '', $summary['hasil_convert']);
            }
            $leq = number_format($totalHasilConvert, 1, '.', '');
            $hasil1 = $totalHasilConvert > 0 ? number_format(10 * log10((1 / $totalShift) * $totalHasilConvert), 1, '.', '') : null;
        } elseif ($totalShift > 0) {
            $firstSummary = reset($shiftSummary);
            $hasil1 = $firstSummary['hasil_laeq'] ?? null;
        }

        return [
            'hasil1' => $hasil1,
            'leq' => $leq,
            'ls' => $ls,
            'lm' => $lm,
            'min' => count($dbValues) ? min($dbValues) : null,
            'max' => count($dbValues) ? max($dbValues) : null,
            'shift_summary' => $shiftSummary,
            'data_per_shift' => array_values($shiftSummary),
        ];
    }

    private function calculateLeqFromConverted($values, $divisor)
    {
        $sum = 0;
        foreach ($values as $value) {
            if ($value !== null) {
                $sum += pow(10, $value);
            }
        }

        return $sum > 0 ? 10 * log10((1 / $divisor) * $sum) : null;
    }

    private function deleteSamplingPhoto($fileName)
    {
        if (!$fileName) {
            return;
        }

        $path = public_path() . '/dokumentasi/sampling/' . $fileName;
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function viewDetail(Request $request)
    {
        try {

            $detail_sound_meter = DetailSoundMeter::where('id_device', $request->id_device)->where('no_sampel', $request->no_sampel)->get();
            $jam_pengukuran = null;
            if ($detail_sound_meter->isNotEmpty()) {
                $min_timestamp = $detail_sound_meter->min('timestamp');
                $max_timestamp = $detail_sound_meter->max('timestamp');
               Carbon::setLocale('id');
                $start =Carbon::parse($min_timestamp);
                $end =Carbon::parse($max_timestamp);
                
                $jam_pengukuran = $start->format('H:i') . ' - ' . $end->format('H:i') . ' (' . $end->translatedFormat('j F Y') . ')';
            }

            $order_detail = OrderDetail::where('no_sampel', $request->no_sampel)
                ->select('no_order', 'nama_perusahaan', 'keterangan_1')
                ->first();
            $data_lapangan = DataLapanganKebisinganBySoundMeter::where('no_sampel', $request->no_sampel)->first();
            $kebisingan_header = KebisinganHeader::where('no_sampel', $request->no_sampel)
                ->select('id', 'no_sampel', 'leq_ls', 'leq_lm', 'data_per_shift')
                ->first();
            $hasil_lsm = null;
            if ($kebisingan_header) {
                $ws_value = WsValueUdara::where('id_kebisingan_header', $kebisingan_header->id)
                    ->select('hasil1')
                    ->first();
                if ($ws_value) {
                    $hasil_lsm = $ws_value->hasil1;
                }
            }

            if(!$data_lapangan){
                return response()->json([
                    'message' => 'Silahkan lakukan input data lapangan terlebih dahulu',
                ], 404);
            }
            
            $data = [
                'no_sampel' => $request->no_sampel,
                'no_order' => $order_detail->no_order ?? null,
                'nama_perusahaan' => $order_detail->nama_perusahaan ?? null,
                'keterangan_1' => $order_detail->keterangan_1 ?? null,
                'leq_ls' => $kebisingan_header->leq_ls ?? null,
                'leq_lm' => $kebisingan_header->leq_lm ?? null,
                'lsm' => $hasil_lsm ?? null,
                'data_per_shift' => $kebisingan_header->data_per_shift ?? [],
                'sampler' => $data_lapangan->created_by ?? $data_lapangan->updated_by ?? null,
                'waktu_input' => $data_lapangan->created_at ?? $data_lapangan->updated_at ?? null,
                'jam_pengukuran' => $jam_pengukuran ?? null,
                'data_pendukung' => json_decode($data_lapangan->kondisi_lapangan_json) ?? [],
            ];

            return response()->json($data, 200);

        } catch (\Exception $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ], 500);
        }
    }

    public function approveFdl(Request $request){
        DB::beginTransaction();
        try {
            $fdl = DataLapanganKebisinganBySoundMeter::where('no_sampel', $request->no_sampel)->first();
            $header = KebisinganHeader::where('no_sampel', $request->no_sampel)->first();

            $po = OrderDetail::where('no_sampel', $data->no_sampel)
                ->where('is_active', true)
                ->first();

            if ($po) {
                // Decode parameter jika dalam format JSON
                $decoded = json_decode($po->parameter, true);

                // Pastikan JSON ter-decode dengan benar dan berisi data
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // Ambil elemen pertama dari array hasil decode
                    $parts = explode(';', $decoded[0] ?? '');

                    // Pastikan elemen kedua tersedia setelah explode
                    $parameterValue = $parts[1] ?? 'Data tidak valid';

                    // dd($parameterValue); // Output: "Pencahayaan"
                } else {
                    return response()->json([
                        'message' => 'Parameter tidak valid',
                    ], 400);
                }

            } else {
                return response()->json([
                    'message' => 'OrderDetail tidak ditemukan',
                ], 404);
            }

            $parameter = Parameter::where('nama_lab', $parameterValue)->first();

            if(!$header){
                return response()->json([
                    'message' => 'Silahkan lakukan kalkulasi data terlebih dahulu',
                ], 404);
            }

            $header->id_parameter = $parameter->id;
            $header->parameter  = $parameter->nama;
            $header->is_approved = true;
            $header->approved_by = $this->karyawan;
            $header->approved_at = Carbon::now();   
            $header->save();
            
            $fdl->is_approved = true;
            $fdl->approved_by = $this->karyawan;
            $fdl->approved_at = Carbon::now();   
            $fdl->save();
            
            DB::commit();
            return response()->json([
                'message' => 'Data FDL berhasil di-approve',
            ], 200);
            
            DB::rollBack();
            return response()->json([
                'message' => 'Data FDL tidak ditemukan',
            ], 404);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ], 500);
        }
    }
}



