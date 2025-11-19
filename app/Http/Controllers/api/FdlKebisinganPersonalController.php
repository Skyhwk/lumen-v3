<?php

namespace App\Http\Controllers\api;

use App\Models\DataLapanganKebisinganPersonal;
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;

use App\Models\KebisinganHeader;
use App\Models\WsValueUdara;

use App\Services\NotificationFdlService;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class FdlKebisinganPersonalController extends Controller
{
    public function index(Request $request){
        $this->autoBlock();
        $data = DataLapanganKebisinganPersonal::with('detail')->orderBy('id', 'desc');

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
            ->filterColumn('departemen', function ($query, $keyword) {
                $query->where('departemen', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('sumber_kebisingan', function ($query, $keyword) {
                $query->where('sumber_kebisingan', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('jarak_sumber_kebisingan', function ($query, $keyword) {
                $query->where('jarak_sumber_kebisingan', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('waktu_paparan', function ($query, $keyword) {
                $query->where('waktu_paparan', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('waktu_pengukuran', function ($query, $keyword) {
                $query->where('waktu_pengukuran', 'like', '%' . $keyword . '%');
            })
            ->make(true);
    }

    public function updateNoSampel(Request $request){
        if (isset($request->id) && $request->id != null) {
            DB::beginTransaction();
            try {
                $data = DataLapanganKebisinganPersonal::where('id', $request->id)->first();

                KebisinganHeader::where('no_sampel', $request->no_sampel_lama)
                ->update(
                    [
                        'no_sampel' => $request->no_sampel_baru,
                        'no_sampel_lama' => $request->no_sampel_lama
                    ]
                );

                WsValueUdara::where('no_sampel', $request->no_sampel_lama)
                ->update(
                    [
                        'no_sampel' => $request->no_sampel_baru,
                        'no_sampel_lama' => $request->no_sampel_lama
                    ]
                );

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
                
                $data->no_sampel = $request->no_sampel_baru;
                $data->no_sampel_lama = $request->no_sampel_lama;
                $data->updated_by = $this->karyawan;
                $data->updated_at = Carbon::now();
                $data->save();

                DB::commit();
                return response()->json([
                    'message' => 'Berhasil ubah no sampel '.$request->no_sampel_lama.' menjadi '.$request->no_sampel_baru
                ], 200);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Gagal ubah no sampel '.$request->no_sampel_lama.' menjadi '.$request->no_sampel_baru,
                    'error' => $e->getMessage()
                ], 401);
            }
        } else {
            return response()->json([
                'message' => 'No Sampel tidak boleh kosong'
            ], 401);
        }
    }

    public function approve(Request $request){
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganKebisinganPersonal::where('id', $request->id)->first();
            $order = OrderDetail::select('parameter')->where('no_sampel', $data->no_sampel)->where('is_active', true)->first();
            $parameterArray = json_decode($order->parameter, true);
            // ambil item pertama (karena isinya array)
            $firstItem = $parameterArray[0]; // "271;Kebisingan (P8J)"

            // pecah berdasarkan tanda ;
            list($id, $nama) = explode(";", $firstItem);
            
            $data->is_approve  = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now();
            $data->rejected_at = null;
            $data->rejected_by = null;
            $data->save();

            $header = KebisinganHeader::where('no_sampel', $data->no_sampel)->where('id_parameter', $id)->where('is_active', true)->first();
            if(!$header){
                $header = new KebisinganHeader;
            }
            $header->no_sampel = $data->no_sampel;
            $header->no_sampel_lama = $data->no_sampel_lama;
            $header->id_parameter = $id;
            $header->parameter = $nama;
            $header->created_by = $this->karyawan;
            $header->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $header->is_approved = true;
            $header->is_personal = true;
            $header->approved_by = $this->karyawan;
            $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $header->save();

            $wsValue = WsValueUdara::where('no_sampel', $data->no_sampel)->where('is_active', true)->first();
            if(!$wsValue){
                $wsValue = new WsValueUdara;
            }
            $wsValue->no_sampel = $data->no_sampel;
            $wsValue->id_kebisingan_header = $header->id;
            $wsValue->hasil1 = $data->value_kebisingan;
            $wsValue->save();

            app(NotificationFdlService::class)->sendApproveNotification('Kebisingan Personal', $data->no_sampel, $this->karyawan, $data->created_by);

            return response()->json([
                'message' => 'Data has ben Approved',
                'master_kategori' => 1
            ], 200);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    public function reject(Request $request){
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganKebisinganPersonal::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;

            $data->is_approve = false;
            $data->rejected_at = Carbon::now();
            $data->rejected_by = $this->karyawan;
            $data->approved_by = null;
            $data->approved_at = null;
            $data->save();

            return response()->json([
                'message' => 'Data has ben Reject',
                'master_kategori' => 1
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    public function delete(Request $request){
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganKebisinganPersonal::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;
            $foto_lok = public_path() .'/dokumentasi/sampling/'. $data->foto_lokasi_sampel;
            $foto_kon = public_path() .'/dokumentasi/sampling/'. $data->foto_kondisi_sampel;
            $foto_lain = public_path() .'/dokumentasi/sampling/'.$data->foto_lain;
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
            //     $txt = "FDL AIR dengan No sample $no_sample Telah di Hapus oleh $nama";

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

    public function block(Request $request){
        if (isset($request->id) && $request->id != null) {
            if ($request->is_blocked == true) {
                $data = DataLapanganKebisinganPersonal::where('id', $request->id)->first();
                $data->is_blocked     = false;
                $data->blocked_by    = null;
                $data->blocked_at    = null;
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Unblocked for user',
                    'master_kategori' => 1
                ], 200);
            } else {
                $data = DataLapanganKebisinganPersonal::where('id', $request->id)->first();
                $data->is_blocked     = true;
                $data->blocked_by    = $this->karyawan;
                $data->blocked_at    = Carbon::now();
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

    public function detail(Request $request){
        $data = DataLapanganKebisinganPersonal::with('detail')->where('id', $request->id)->first();
        
        $this->resultx = 'get Detail FDL KEBISINGAN PERSONAL success';

        return response()->json([
            'id'                            => $data->id,
            'no_sample'                     => $data->no_sampel,
            'no_order'                      => $data->detail->no_order,
            'sampler'                       => $data->created_by,
            'sub_kategori'                  => explode('-', $data->detail->kategori_3)[1],
            'id_sub_kategori'               => explode('-', $data->detail->kategori_3)[0],
            'nama_perusahaan'               => $data->detail->nama_perusahaan,
            'keterangan'                    => $data->keterangan,
            'keterangan_2'                  => $data->keterangan_2,
            'departemen'                    => $data->departemen,
            'latitude'                      => $data->latitude,
            'longitude'                     => $data->longitude,
            'titik_koordinat'               => $data->titik_koordinat,
            'message'                       => $this->resultx,
            'waktu_pengukuran'              => $data->waktu_pengukuran,
            'waktu_paparan'                 => $data->waktu_paparan,
            'jam_mulai_pengujian'           => $data->jam_mulai_pengujian,
            'jam_akhir_pengujian'           => $data->jam_akhir_pengujian,
            'total_waktu_istirahat_personal'=> $data->total_waktu_istirahat_personal,
            'sumber_kebisingan'             => $data->sumber_kebisingan,
            'jarak_sumber_kebisingan'       => $data->jarak_sumber_kebisingan,
            'foto_lokasi'                   => $data->foto_lokasi_sample,
            'foto_lain'                     => $data->foto_lain,
            'status'                        => '200'
        ], 200);
    }

    protected function autoBlock()
    {
        $tgl = Carbon::now()->subDays(7);
        $data = DataLapanganKebisinganPersonal::where('is_blocked', 0)->where('created_at', '<=', $tgl)->update(['is_blocked' => 1, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }

    public function uploadData(Request $request)
    {
        try {
            $file = $request->file('file_input');
            if (!$file || $file->getClientOriginalExtension() !== 'xlsx') {
                return response()->json(['error' => 'File tidak valid. Harus .xlsx'], 400);
            }
            
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            
            $values = [];
            
        foreach ($sheet->getRowIterator() as $rowIndex => $row) {
            $cell = $sheet->getCell('D' . $rowIndex)->getValue();

            if (is_numeric($cell)) {
                $values[] = floatval($cell);
            }
        }

        if (count($values) === 0) {
            return response()->json(['error' => 'Kolom D kosong atau tidak ada angka valid'], 400);
        }

        $average = array_sum($values) / count($values);

        return response()->json([
            'success' => true,
            'jumlah_data' => count($values),
            'average' => round($average, 1),
            'filename' => $file->getClientOriginalName(),
        ]);
        } catch (\Exception $th) {
            dd($th);
        }
    }

    public function approveData(Request $request)
    {
        $data = DataLapanganKebisinganPersonal::where('id', $request->id)->first();
        $data->value_kebisingan = $request->result;
        $data->filename = $request->filename;
        $data->save();

        return response()->json([
            'message' => 'Success input data',
        ],200);
    }

    public function rejectData(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganKebisinganPersonal::where('id', $request->id)->first();

            $data->is_rejected = true;
            $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->rejected_by = $this->karyawan;
            $data->save();

            app(NotificationFdlService::class)->sendRejectNotification("Kebisingan Personal", $request->no_sampel, $request->reason, $this->karyawan, $data->created_by);
            
            return response()->json([
                'message' => 'Data no sample ' . $data->no_sampel . ' telah di reject'
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }


} 