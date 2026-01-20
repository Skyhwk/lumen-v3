<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\SamplingPlan;
use App\Models\MasterKaryawan;
use App\Models\Jadwal;
use App\Models\JadwalLibur;
use App\Models\MasterDriver;
use App\Models\PraNoSample;
use App\Models\QuotationKontrakH;
use App\Models\MasterCabang;
use App\Models\QuotationKontrakD;
use App\Models\QuotationNonKontrak;
use App\Models\OrderHeader;
use App\Models\OrderDetail;
use App\Models\PerbantuanSampler;
use App\Jobs\RenderSamplingPlan;
use App\Services\JadwalServices;
use App\Services\GetAtasan;
use App\Services\Notification;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;
use App\Services\RenderSamplingPlan as RenderSamplingPlanService;
use App\Jobs\RenderAndEmailJadwal;
use App\Models\JobTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class SamplingPlanController extends Controller
{

    public function index(Request $request)
    {
        
        $active = $request->is_active == '' ? true : $request->is_active;

        $data = Jadwal::with([
            'samplingPlan:id,created_at,filename,is_active',
            'samplingPlan' => function ($query) {
                $query->WithTypeModelSub();
            }
        ])
            ->select('id_sampling', 'parsial', 'no_quotation', 'nama_perusahaan','isokinetic','pendampingan_k3', 'tanggal', 'periode', 'jam_mulai', 'jam_selesai', 'kategori', 'durasi', 'status', 'warna', 'note', 'urutan', 'driver', 'id_cabang', 'wilayah', DB::raw('group_concat(sampler) as sampler'), DB::raw('group_concat(id) as batch_id'), DB::raw('group_concat(userid) as batch_user'), 'created_by', 'created_at', 'updated_at', 'updated_by')
            ->groupBy('id_sampling', 'parsial', 'no_quotation', 'tanggal', 'periode', 'nama_perusahaan','isokinetic','pendampingan_k3', 'durasi', 'driver', 'kategori', 'status', 'jam_mulai', 'jam_selesai', 'warna', 'note', 'urutan', 'wilayah', 'id_cabang', 'created_by', 'created_at', 'updated_at', 'updated_by')
            ->whereNotNull('no_quotation')
            ->where('is_active', $active);

        // Filter cabang
        // if ($request->filled('id_cabang_filter')) {
        //     $idCabang = is_array($request->id_cabang_filter) ? $request->id_cabang_filter : [$request->id_cabang_filter];

        //     $data->where(function ($query) use ($idCabang) {
        //         $filtered = array_filter($idCabang, fn($v) => $v !== 'null');
        //         if (!empty($filtered)) {
        //             $query->whereIn('id_cabang', $filtered);
        //         }

        //         if (in_array('null', $idCabang, true)) {
        //             $query->orWhereNull('id_cabang');
        //         }
        //     });
        // }
        // CEK HIERARKI KLAN (Auth Check)
        $myPrivileges = $this->privilageCabang;
        $isOrangPusat = in_array("1", $myPrivileges);
        if ($isOrangPusat) {
            if ($request->filled('id_cabang_filter')) {
                $idCabang = is_array($request->id_cabang_filter) ? $request->id_cabang_filter : [$request->id_cabang_filter];
                $data->where(function ($query) use ($idCabang) {
                    $filtered = array_filter($idCabang, fn($v) => $v !== 'null');
                    if (!empty($filtered)) {
                        $query->whereIn('id_cabang', $filtered);
                    }
                    if (in_array('null', $idCabang, true)) {
                        $query->orWhereNull('id_cabang');
                    }
                });
            }

        } else {
            $data->whereIn('id_cabang', $myPrivileges);
            if ($request->filled('id_cabang_filter')) {
                $reqFilter = is_array($request->id_cabang_filter) ? $request->id_cabang_filter : [$request->id_cabang_filter];
                $data->whereIn('id_cabang', $reqFilter);
            }
        }

        $data->orderBy('tanggal', 'DESC');

        if ($request->tahun != '') {
            $data->whereYear('tanggal', Carbon::createFromFormat('Y', $request->tahun)->year);
        }

        return Datatables::of($data)
            ->filterColumn('no_quotation', function ($query, $keyword) {
                $query->where('no_quotation', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('nama_perusahaan', function ($query, $keyword) {
                $query->where('nama_perusahaan', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('tanggal', function ($query, $keyword) {
                $query->where('tanggal', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('jam_mulai', function ($query, $keyword) {
                $query->where('jam_mulai', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('jam_selesai', function ($query, $keyword) {
                $query->where('jam_selesai', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('durasi', function ($query, $keyword) {
                $keyword = strtolower($keyword);
                if (strpos($keyword, 'sesaat') !== false) {
                    $query->where('durasi', 0);
                } elseif (strpos($keyword, '8 jam') !== false) {
                    $query->where('durasi', 1);
                } elseif (preg_match('/(\d+)x?24/', $keyword, $matches)) {
                    // Handle 1x24, 2x24, etc.
                    $days = (int)$matches[1];
                    $query->where('durasi', $days + 1); // durasi 2 = 1x24, durasi 3 = 2x24, etc.
                }
            })
            ->filterColumn('status', function ($query, $keyword) {
                $keyword = strtolower($keyword);
                if (strpos($keyword, 'book') !== false) {
                    $query->where('status', 0);
                } elseif (strpos($keyword, 'fix') !== false) {
                    $query->where('status', 1);
                }
            })
            ->filterColumn('kategori', function ($query, $keyword) {
                $query->where('kategori', 'like', '%' . $keyword . '%');
            })
            // Filter kolom 'sampler' dengan where biasa, karena havingRaw tidak berfungsi di sini
            ->filterColumn('sampler', function ($query, $keyword) {
                $query->where('sampler', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('updated_by', 'like', '%' . $keyword . '%')
                        ->orWhere('created_by', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('created_at', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('updated_at', 'like', '%' . $keyword . '%')
                        ->orWhere('created_at', 'like', '%' . $keyword . '%');
                });
            })
            ->with([
                'cabang_options' => $this->getBranchOptionsForUser() 
            ])
            ->make(true);
    }

    private function getBranchOptionsForUser()
    {
        $myPrivileges = $this->privilageCabang;
        $isOrangPusat = in_array("1", $myPrivileges);

        $query = MasterCabang::select('id', 'nama_cabang'); // Sesuaikan nama kolom

        if (!$isOrangPusat) {
            $query->whereIn('id', $myPrivileges);
        }
        // Ambil datanya
        return $query->get()->map(function($item) {
            return [
                'value' => $item->id,
                'label' => $item->nama_cabang
            ];
        });
    }

    public function kantorCabang(Request $request)
    {
        $branch = MasterCabang::select('id', 'kode_cabang', 'nama_cabang')->get();
        return response()->json($branch, 200);
    }

    public function updateWarnaSampler(Request $request)
    {
        $data = MasterKaryawan::where('id', $request->id)->first();
        $data->warna = $request->warna;
        $data->save();

        return response()->json([
            'message' => 'Saved Successfully',
        ], 200);
    }

    public function getsamplerApi(Request $request)
    {
        try {
            $samplers = MasterKaryawan::with('jabatan');
            if ($request->mode == 'add') {
                $samplers->whereIn('id_jabatan', [94]); // 'Sampler'
            } else {
                $samplers->whereIn('id_jabatan', [70, 75, 94, 110]); // 'Sampler', 'K3 Staff','Technical Assurance Staff','Sampling Admin Staff'
            }

            $samplers = $samplers->where('is_active', true)
                ->orderBy('nama_lengkap')
                ->get();
            $privateSampler =  PerbantuanSampler::with('users.jabatan')
                ->where('is_active', true)
                ->orderBy('nama_lengkap')
                ->get();
            $privateSampler->transform(function ($item) {
                $digitCount = strlen((string)$item->user_id);
    
                // 2. Tentukan suffix (akhiran nama)
                if ($digitCount > 4) {
                    $item->nama_display = $item->nama_lengkap . ' (freelance)';
                } else {
                    $item->nama_display = $item->nama_lengkap . ' (perbantuan)';
                }
                // $item->nama_display = $item->nama_lengkap . ' (perbantuan)';
                unset($item->jabatan);
                if ($item->users && $item->users->jabatan) {
                    // Kita "copy" objek jabatan dari dalam users ke root item
                    // Sehingga nanti di frontend bisa panggil item.jabatan.nama_jabatan
                    $jabatanObj = $item->users->getRelation('jabatan');
                    $item->setRelation('jabatan', $jabatanObj);
                } else {
                    // Fallback jika data kosong (opsional, biar frontend gak error undefined)
                    $jabatanObj = (object)[
                        "nama_jabatan" => "Freelance Sampler"
                    ];
                    $item->jabatan = $jabatanObj;
                }
                unset($item->users);
                return $item;
            });
            $samplers->transform(function ($item) {
                $item->nama_display = $item->nama_lengkap;
                return $item;
            });
            $allSamplers = $samplers->concat($privateSampler);
            $allSamplers = $allSamplers->unique('user_id');
            $allSamplers = $allSamplers->sortBy('nama_display')->values();

            return Datatables::of($allSamplers)->make(true);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => '401'
            ], 401);
        }
    }

    public function insertJadwalLibur(Request $request)
    {
        if ($request->id != '') {
            $data = JadwalLibur::where('id', $request->id)->first();
            $data->updated_by = $this->karyawan;
            $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            $message = 'Berhasil update jadwal libur';
        } else {
            $data = new JadwalLibur();
            $data->created_by = $this->karyawan;
            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $message = 'Berhasil menambahkan jadwal libur';
        }
        ($request->judul != '') ? $data->judul = $request->judul : $data->judul = null;
        ($request->deskripsi != '') ? $data->deskripsi = $request->deskripsi : $data->deskripsi = null;
        ($request->start_date != '') ? $data->start_date = $request->start_date : $data->start_date = null;
        ($request->end_date != '') ? $data->end_date = $request->end_date : $data->end_date = $request->start_date;
        $data->save();

        return response()->json([
            'message' => $message,
        ], 200);
    }

    public function showJadwalLibur()
    {
        $data = JadwalLibur::where('is_active', true)->orderBy('id', 'desc');

        return Datatables::of($data)->make(true);
    }

    public function deleteJadwalLibur(Request $request)
    {
        JadwalLibur::where('id', $request->id)->update(['is_active' => false]);

        return response()->json([
            'message' => 'Berhasil menghapus jadwal libur',
        ], 200);
    }

    public function insertCutiSampler(Request $request)
    {
        try {
            if (isset($request->id) && $request->id != null) {
                $usr = explode(",", $request->sampler);
                $data = Jadwal::where('id', $request->id)->first();
                $data->nama_perusahaan = $request->keterangan;
                $data->tanggal = $request->tanggal_mulai;
                $data->sampler = $usr[1];
                $data->userid = $usr[0];
                $data->durasi = $request->total_hari;
                $data->flag = 1;
                $data->save();

                $message = 'Cuti has been update.!';
            } else {
                $usr = explode(",", $request->sampler);
                $data = new Jadwal();
                $data->nama_perusahaan = $request->keterangan;
                $data->tanggal = $request->tanggal_mulai;
                $data->sampler = $usr[1];
                $data->userid = $usr[0];
                $data->durasi = $request->total_hari;
                $data->flag = 1;
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                $message = 'CUTI has been Save.!';
            }
            return response()->json([
                'message' => $message,
                'status' => '200'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => $e->getCode(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function insertDriver(Request $request)
    {
        try {
            $usr = explode(",", $request->driver);
            $id = $usr[0];
            $nama = $usr[1];
            if (isset($request->id) && $request->id != null) {

                $data = MasterDriver::where('id', $request->id)->first();
                $data->user_id = $id ?? null;
                $data->nama_driver = $nama ?? null;
                $data->updated_by = $this->karyawan ?? null;
                $data->updated_at = Carbon::now()->format('Y-m-d H:i:s') ?? null;
                $data->is_active = true;
                $data->save();

                $message = 'Cuti has been update.!';
            } else {

                $data = new MasterDriver();
                $data->user_id = $id ?? null;
                $data->nama_driver = $nama ?? null;
                $data->created_by = $this->karyawan ?? null;
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s') ?? null;
                $data->is_active = true;
                $data->save();

                $message = 'Driver Berhasil Ditambahkan.!';
            }
            return response()->json([
                'message' => $message,
                'status' => 200,
                'success' => true
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => $e->getCode(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function deleteDriver(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = MasterDriver::where('id', $request->id)->first();
            $data->is_active = false;
            $data->save();
            DB::commit();
            return response()->json([
                'message' => 'Driver Berhasil Dihapus.!',
                'status' => 200,
                'success' => true
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'status' => $e->getCode(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function showCutiSampler()
    {
        $data = Jadwal::where('is_active', true)
            ->where('flag', 1)
            ->orderBy('id', 'DESC');

        return Datatables::of($data)->make(true);
    }

    public function showDriver()
    {
        $data = MasterDriver::where('is_active', true);
        return Datatables::of($data)->make(true);
    }

    public function deleteCutiSampler(Request $request)
    {
        Jadwal::where('id', $request->id)->delete();

        return response()->json([
            'message' => 'Cuti berhasil dihapus',
            'status' => '200'
        ], 200);
    }

    public function getSingleJadwal(Request $request)
    {

        try {
            //code...
            $kategori = str_replace(['&quot;'], '"', $request->kategori);
            $kategori = str_replace(['&amp;'], '&', $kategori);

            $data = Jadwal::where('no_quotation', '=', $request->no_quotation)
                ->where('tanggal', DB::raw("CAST('" . $request->tanggal . "' AS DATE)"))
                ->where('kategori', $kategori)
                ->where('durasi', $request->durasi)
                ->where('is_active', true)
                ->get();

            $value = array();
            $value['batch_id'] = [];
            foreach ($data as $key => $val) {
                $value['id'] = $val->id;
                $value['no_quotation'] = $val->no_quotation;
                $value['nama_perusahaan'] = $val->nama_perusahaan;
                $value['wilayah'] = $val->wilayah;
                $value['alamat'] = $val->alamat;
                $value['tanggal'] = $val->tanggal . ' ' . $val->jam;
                $value['tgl_lama'] = $val->tanggal;
                $value['kategori'] = json_decode($val->kategori);
                $value['note'] = $val->note;
                $value['warna'] = $val->warna;
                $value['durasi'] = $val->durasi;
                $value['status'] = $val->status;
                $value['jam_mulai'] = $val->jam_mulai;
                $value['jam_selesai'] = $val->jam_selesai;
                $value['urutan'] = $val->urutan;
                $value['kendaraan'] = $val->kendaraan;
                $value['parsial'] = $val->parsial;
                $value['id_sampling'] = $val->id_sampling;

                array_push($value['batch_id'], $val->id);
                $user = MasterKaryawan::where('nama_lengkap', $val->sampler)->where('is_active', true)->first();
                $message = '';
                $resonse_code = '200';
                if (!is_null($user)) {
                    $usr[] = $user->id . ',' . $user->nama_lengkap;
                    $value['sampler'] = $usr;
                } else {
                    $message = 'Sampler atas nama ' . $val->sampler . ' tidak ditemukan / keluar silahkan cek dan dilakukan update sampler.!';
                    $resonse_code = '201';
                }

                $value['ketegori_detail'] = [];
                $sp = SamplingPlan::where('id', $val->id_sampling)->first();

                if ($sp) {
                    $value['periode_kontrak'] = $sp->periode_kontrak;
                    $value['no_sp'] = $sp->no_document;
                    $value['opsi_1'] = $sp->opsi_1;
                    $value['opsi_2'] = $sp->opsi_2;
                    $value['opsi_3'] = $sp->opsi_3;
                    $value['is_sabtu'] = $sp->is_sabtu;
                    $value['is_minggu'] = $sp->is_minggu;
                    $value['is_malam'] = $sp->is_malam;
                    $value['tambahan'] = $sp->tambahan;
                    $value['keterangan_lain'] = $sp->keterangan_lain;
                } else {
                    return response()->json([
                        'message' => 'Data sampling plan tidak ditemukan.!',
                    ], 401);
                }

                $qt = explode("/", $val->no_quotation)[1];

                if ($qt == 'QT') {
                    $jumlah_titik = 0;
                    $detail = QuotationNonKontrak::where('no_document', $val->no_quotation)->where('is_active', true)->first();
                    // dd($detail);
                    if ($detail != null) {
                        $value['konsultan'] = $detail->konsultan;
                        $value['nama_perusahaan'] = $detail->nama_perusahaan;
                        $value['alamat_sampling'] = $detail->alamat_sampling;
                        $value['status_sampling'] = $detail->status_sampling;
                        $value['transportasi'] = $detail->transportasi;
                        $value['wilayah'] = $detail->wilayah;
                        $value['perdiem_jumlah_orang'] = $detail->perdiem_jumlah_orang;
                        $value['perdiem_jumlah_hari'] = $detail->perdiem_jumlah_hari;

                        $query = PraNoSample::where('no_quotation', $val->no_quotation)->first();
                    } else {
                        return response()->json([
                            'message' => 'NO QT' . $val->no_quotation . ' Sudah NON AKTIF.!',
                        ], 401);
                    }
                } else if ($qt == 'QTC') {
                    $query = PraNoSample::where('no_quotation', $val->no_quotation)->where('periode', $val->periode)->first();
                    $kontrakData = QuotationKontrakH::with([
                        'detail' => function ($q) use ($sp) {
                            $q->where('periode_kontrak', $sp->periode_kontrak);
                        }
                    ])
                        ->where('no_document', $val->no_quotation)
                        ->first();

                    if (!$kontrakData) {
                        return response()->json([
                            'message' => 'Data kontrak tidak ditemukan',
                        ], 404);
                    }
                    $cek_H = $kontrakData;
                    $detail = null;
                    foreach ($kontrakData->detail as $d) {
                        if ($d->periode_kontrak == $sp->periode_kontrak) {
                            $detail = $d;
                            break;
                        }
                    }

                    if (!$detail) {
                        return response()->json([
                            'message' => 'Detail kontrak tidak ditemukan untuk periode tersebut',
                        ], 404);
                    }

                    $jumlah_titik = 0;
                    $data_pendukung = json_decode($detail->data_pendukung_sampling);
                    foreach ($data_pendukung as $item) {
                        if ($item->periode_kontrak < $sp->periode_kontrak) {
                            foreach ($item->data_sampling as $sampling) {
                                $jumlah_titik += (int) $sampling->jumlah_titik;
                            }
                        }
                    }

                    $value['konsultan'] = $cek_H->konsultan;
                    $value['nama_perusahaan'] = $cek_H->nama_perusahaan;
                    $value['alamat_sampling'] = $cek_H->alamat_sampling;
                    $value['status_sampling'] = $detail->status_sampling;
                    $value['transportasi'] = $detail->transportasi;
                    $value['wilayah'] = $cek_H->wilayah;
                    $value['perdiem_jumlah_orang'] = $detail->perdiem_jumlah_orang;
                    $value['perdiem_jumlah_hari'] = $detail->perdiem_jumlah_hari;
                }
                $value['jumlah_sebelum'] = $jumlah_titik;
                $value['ketegori_detail'] = $detail->data_pendukung_sampling;
                $value['tipe_qt'] = $qt;
                $pra_sample = [];
                if ($query != null) {
                    $pra_sample = json_decode($query->kategori);
                }
            }

            return response()->json([
                'data' => $value,
                'kategori' => $pra_sample,
                'message' => $message,
                'status' => $resonse_code
            ], 200);
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
                'status' => '401'
            ], 401);
            //throw $th;
        }
    }

    public function updateJadwal(Request $request)
    {
        
        try {
            //code...
            $dataObject = (object) [
                'no_quotation' => $request->no_quotation,
                'nama_perusahaan' => $request->nama_perusahaan,
                'jam_mulai' => $request->jam_mulai,
                'jam_selesai' => $request->jam_selesai,
                'kategori' => $request->kategori,
                'warna' => $request->warna,
                'note' => $request->note,
                'durasi' => $request->durasi,
                'status' => $request->status,
                'batch_id' => $request->batch_id,
                'urutan' => $request->urutan != '' ? $request->urutan : null,
                'kendaraan' => $request->kendaraan,
                'sampling' => $request->sampling,
                'karyawan' => $this->karyawan,
                'jadwal_id' => $request->id,
                'tanggal' => $request->tanggal,
                'alamat' => $request->alamat,
                'sampler' => $request->sampler,
                'driver' => ($request->has('driver')) ? $request->driver[0] : null,
                'durasi_lama' => $request->durasi_lama,
                'tanggal_lama' => $request->tanggal_lama,
                'tipe_parsial' => $request->tipe_parsial,
                'isokinetic' => (int)$request->isokinetic,
                'pendampingan_k3' => (int)$request->pendampingan_k3,
                'id_cabang' => $request->id_cabang[0],
            ];

            $type = explode('/', $request->no_quotation)[1];
            if ($request->durasi_lama == $request->durasi) {
                if ($type == 'QTC') {
                    $dataObject->periode = $request->periode;
                }
                
                $jadwal = JadwalServices::on('updateJadwal', $dataObject)->updateJadwalSP();
            } else {
                if ($type == 'QTC') {
                    $dataObject->periode = $request->periode;
                }
                $jadwal = JadwalServices::on('updateJadwalKategori', $dataObject)->updateJadwalSPKategori();
            }

            // TAMBAHAN UNTUK UPDATE ORDER DETAIL
            $this->updateOrderDetail($dataObject, $request->tanggal);

            if ($jadwal) {
                return response()->json([
                    'message' => 'Berhasil melakukan update Jadwal.!',
                    'status' => '200'
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Gagal melakukan update Jadwal.!',
                    'status' => '400'
                ], 400);
            }
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
                'status' => '401'
            ], 401);
        }
    }

    public function insertParsial(Request $request)
    {
        try {
            $dataObject = (object) [
                'id' => $request->id,
                'id_sampling' => $request->id_sampling,
                'totkateg' => count($request->kategori),
                'kategori' => $request->kategori,
                'no_quotation' => $request->no_quotation,
                'nama_perusahaan' => $request->nama_perusahaan,
                'wilayah' => $request->wilayah,
                'alamat' => $request->alamat,
                'tanggal' => $request->tanggal,
                'note' => $request->note,
                'warna' => $request->warna,
                'jam_mulai' => $request->jam_mulai,
                'jam_selesai' => $request->jam_selesai,
                'durasi' => $request->durasi,
                'status' => $request->status,
                'urutan' => $request->urutan != '' ? $request->urutan : null,
                'karyawan' => $this->karyawan,
                'kendaraan' => $request->kendaraan,
                'sampler' => $request->sampler,
                'driver' => $request->driver,
                'pendampingan_k3' => $request->pendampingan_k3,
                'isokinetic' => $request->isokinetic,
                'id_cabang' => $request->id_cabang[0],
            ];

            $type = explode('/', $request->no_quotation)[1];
            if ($type == 'QTC') {
                $dataObject->periode = $request->periode;
                $jadwal = JadwalServices::on('insertParsialKontrak', $dataObject)->insertParsialKontrak();
            } else {
                $jadwal = JadwalServices::on('insertParsial', $dataObject)->insertParsial();
            }

            $this->updateOrderDetail($dataObject, $request->tanggal);

            if ($jadwal) {
                return response()->json([
                    'message' => 'Berhasil melakukan insert Jadwal Parsial.!',
                    'status' => '200'
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Gagal melakukan insert Jadwal Parsial.!',
                    'status' => '400'
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => '401'
            ], 401);
        }
    }

    private function updateOrderDetail($data, $tanggal)
    {
        $cekOrder = OrderHeader::where('no_document', $data->no_quotation)->where('is_active', true)->first();
        if ($cekOrder) {
            $array_no_samples = [];
            foreach ($data->kategori as $x => $y) {
                $pra_no_sample = explode(" - ", $y)[1];
                $no_samples = $cekOrder->no_order . '/' . $pra_no_sample;
                $array_no_samples[] = $no_samples;
            }

            $orderDetail = OrderDetail::where('id_order_header', $cekOrder->id)->whereIn('no_sampel', $array_no_samples)->get();
            foreach ($orderDetail as $od) {
                $od->tanggal_sampling = $tanggal;
                $od->save();

                Log::channel('perubahan_tanggal')->info('Order Detail updated: ' . $od->no_sampel . ' -> ' . $tanggal);
            }
        }
    }

    public function cancelJadwal(Request $request)
    {
        
        DB::beginTransaction();
        try {
            if (!is_array($request->mode['batchId'])) {
                $batchId = explode(',', $request->mode['batchId']);
            } else {
                $batchId = $request->mode['batchId'];
            }
            $temptMessage = '';
            if ($request->mode['parsial'] !== "") { //menandakan data yg terpilih adalah partial
              
                $dataParsial = Jadwal::whereIn('id', $batchId)
                    ->where('is_active', true)
                    ->update([
                        'flag' => 1,
                        "is_active" => false,
                        "canceled_by" => $this->karyawan,
                        "canceled_at" => Carbon::now()->format('Y-m-d H:i:s'),
                    ]);
            } else {
                // data induk
                $dataParsial = Jadwal::whereIn('parsial', $batchId)
                    ->where('is_active', true)
                    ->get(); //->update(["active" =>1]);
                if ($dataParsial->isEmpty()) {
                    $main = Jadwal::whereIn('id', $batchId)
                        ->where(function ($query) {
                            $query->whereNull('parsial')
                                ->orWhere('parsial', '');
                        })
                        ->where('is_active', true)
                        ->update([
                            'flag' => 1,
                            "is_active" => false,
                            "canceled_by" => $this->karyawan,
                            "canceled_at" => Carbon::now()->format('Y-m-d H:i:s'),
                        ]);
                    $id_sampling = Jadwal::whereIn('id', $batchId)->get()->pluck('id_sampling')->toArray();
                    SamplingPlan::whereIn('id', array_unique($id_sampling))
                        ->update(['status' => 0, 'is_approved' => 0]);
                    $temptMessage = 'dan data sudah berada di req.sampling';
                } else {
                    return response()->json([
                        'message' => 'Terdapat Jadwal Parsial yg belum di hapus.!',
                        'status' => '401'
                    ], 401);
                }
            }
            
            // $message = "No QT :" . $request->no_quotation . "\ndengan Tanggal Jadwal " . $request->tanggal . " sudah di cancel oleh staff :". $karyawan->karyawan($this->db,$this->karyawan)."\n" . ($temptMessage != '') ? $temptMessage : "";
            $message = "No QT: " . $request->no_quotation . "\ndengan Tanggal Jadwal " . $request->tanggal . " sudah di cancel oleh " . $this->karyawan . "\n" . (($temptMessage != '') ? $temptMessage : "");

            $atasan = GetAtasan::where('id', $this->user_id)->get();
            Notification::whereIn('id', $atasan)->title('Cancel QT')->message($message)->url('url')->send();

            DB::commit();
            return response()->json([
                'message' => 'Jadwal berhasil dicancel',
                'status' => '200'
            ], 200);
        } catch (\Exception $ex) {
            DB::rollback();
            $templateMessage = "Error : " . $ex->getMessage() . "\nLine : " . $ex->getLine() . "\nFile : " . $ex->getFile() . "\n pada method cancelJadwal";
            return response()->json($templateMessage, 401);
        }
    }

    public function getPraNomorSample(Request $request)
    {
        $isContract = $request->periode_kontrak !== null;
        $categories = [];
        if (!$isContract) {
            $qt = QuotationNonKontrak::where('no_document', $request->no_quotation)->first();
            $dataPendukungSampling = json_decode($qt->data_pendukung_sampling);
            foreach ($dataPendukungSampling as $dps) {
                $kategori = explode("-", $dps->kategori_2)[1];
                foreach ($dps->penamaan_titik as $penamaanTitik) {
                    $props = get_object_vars($penamaanTitik);
                    $noSampel = key($props);

                    array_push($categories, "$kategori - $noSampel");
                }
            };
        } else {
            $qtH = QuotationKontrakH::where('no_document', $request->no_quotation)->first();
            $qtD = QuotationKontrakD::where('id_request_quotation_kontrak_h', $qtH->id)->where('periode_kontrak', $request->periode_kontrak)->first();

            $dataPendukungSampling = json_decode($qtD->data_pendukung_sampling);
            foreach ($dataPendukungSampling as $dps) {
                foreach ($dps->data_sampling as $ds) {
                    $kategori = explode("-", $ds->kategori_2)[1];
                    foreach ($ds->penamaan_titik as $penamaanTitik) {
                        $props = get_object_vars($penamaanTitik);
                        $noSampel = key($props);

                        array_push($categories, "$kategori - $noSampel");
                    }
                }
            };
        }

        $categories = str_replace('\\', '', json_encode($categories));

        $sp['pra_no_sample'] = ['kategori' => $categories];

        return response()->json($sp, 200);
    }

    public function renderPDF(Request $request)
    {
        $samplingPlan = $request->sampling_plan;

        if ($samplingPlan['status_quotation'] == 'kontrak') {
            $chek = QuotationKontrakH::where('id', $samplingPlan['quotation_id'])->where('flag_status', 'rejected')->first();
            if ($chek) {
                return response()->json([
                    'message' => 'No Dokumen ' . $chek->no_document . ' sedang di reject, tidak bisa di proses.,menunggu proses dari sales!',
                    'status' => '401'
                ], 401);
            }
            $filename = RenderSamplingPlanService::onKontrak($samplingPlan['quotation_id'])->onPeriode($samplingPlan['periode_kontrak'])->renderPartialKontrak();
        } else {
            $chek = QuotationNonKontrak::where('id', $samplingPlan['quotation_id'])->where('flag_status', 'rejected')->first();
            if ($chek) {
                return response()->json([
                    'message' => 'No Dokumen ' . $chek->no_document . ' sedang di reject, tidak bisa di proses.,menunggu proses dari sales!',
                    'status' => '401'
                ], 401);
            }
            $filename = RenderSamplingPlanService::onNonKontrak($samplingPlan['quotation_id'])->save();
        }

        return response()->json($filename, 200);
    }

    public function renderPDFSamplingPlan(Request $request)
    {
        $job = new RenderSamplingPlan($request->quotation_id, $request->status_quotation);
        $this->dispatch($job);
    }

    public function getDataReEmail(Request $request)
    {
        $data = SamplingPlan::withTypeModelSub()
            ->select(DB::raw('MAX(id) as id'), 'no_quotation', 'status_quotation', DB::raw('MAX(quotation_id) as quotation_id'))
            ->where('is_active', true)
            ->where('status', 1)
            ->where('is_approved', true)
            ->where('status_jadwal', 'jadwal')
            ->groupBy('no_quotation', 'status_quotation')
            ->orderBy('id', 'DESC');

        return Datatables::of($data)
            ->filterColumn('no_quotation', function ($query, $keyword) {
                $query->where('no_quotation', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('nama_perusahaan', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->whereHas('quotation', function ($sub) use ($keyword) {
                        $sub->where('nama_perusahaan', 'like', "%{$keyword}%");
                    })->orWhereHas('quotationKontrak', function ($sub) use ($keyword) {
                        $sub->where('nama_perusahaan', 'like', "%{$keyword}%");
                    });
                });
            })
            ->make(true);
    }

    public function sendReEmail(Request $request)
    {
        DB::beginTransaction();
        try {
            $timestamp = Carbon::now()->format('Y-m-d H:i:s');
            $cek = SamplingPlan::where('id', $request->id)->where('is_active', true)->first();
            $checkJadwal = JadwalServices::on('no_quotation', $cek->no_quotation)->countJadwalApproved();
            $chekQoutations = JadwalServices::on('no_quotation', $cek->no_quotation)
                ->on('quotation_id', $cek->quotation_id)->countQuotation();

            if ($chekQoutations == $checkJadwal) {
                $type = explode('/', $cek->no_quotation);
                if ($type[1] == 'QTC') {
                    $data = Jadwal::select([
                        'periode',
                        DB::raw("GROUP_CONCAT(DISTINCT tanggal ORDER BY tanggal ASC) as tanggal"),
                        DB::raw("MIN(jam_mulai) as jam_mulai"),
                        DB::raw("MAX(jam_selesai) as jam_selesai"),
                        DB::raw("GROUP_CONCAT(DISTINCT sampler) as sampler")
                    ])
                        ->where('no_quotation', $cek->no_quotation)
                        ->where('is_active', true)
                        ->groupBy('periode')
                        ->get();

                    $value = [];
                    if ($data->isNotEmpty()) {
                        foreach ($data as $row) {
                            $periode = $row->periode; // contoh: '2025-03'
                            $value[$periode] = [
                                'tanggal' => array_unique(explode(',', $row->tanggal)),
                                'jam_mulai' => $row->jam_mulai,
                                'jam_selesai' => $row->jam_selesai,
                                'sampler' => array_unique(explode(',', $row->sampler)),
                            ];
                        }
                    }
                    ksort($value);
                } else {
                    $data = Jadwal::where('no_quotation', $cek->no_quotation)
                        ->where('is_active', true)
                        ->groupBy('no_quotation')
                        ->get([
                            DB::raw("GROUP_CONCAT(DISTINCT tanggal ORDER BY tanggal ASC) as tanggal"),
                            DB::raw("MIN(jam_mulai) as jam_mulai"),
                            DB::raw("MAX(jam_selesai) as jam_selesai"),
                            DB::raw("GROUP_CONCAT(DISTINCT sampler) as sampler")
                        ]);

                    $value = [];
                    if ($data->isNotEmpty()) {
                        $value['tanggal'] = array_unique(explode(',', $data[0]->tanggal));
                        $value['jam_mulai'] = $data[0]->jam_mulai;
                        $value['jam_selesai'] = $data[0]->jam_selesai;
                        $value['sampler'] = array_unique(explode(',', $data[0]->sampler));
                    }
                }

                JobTask::insert([
                    'job' => 'RenderAndEmailJadwal',
                    'status' => 'processing',
                    'no_document' => $cek->no_quotation,
                    'timestamp' => $timestamp
                ]);

                $dataRequest = (object) [
                    'sampling_id' => $request->id,
                    'no_document' => $cek->no_quotation,
                    'quotation_id' => $cek->quotation_id,
                    'karyawan' => $this->karyawan,
                    'karyawan_id' => $this->user_id,
                    'timestamp' => $timestamp,
                ];

                $job = new RenderAndEmailJadwal($dataRequest, $value);
                $this->dispatch($job);

                DB::commit();
                return response()->json([
                    'message' => 'Berhasil mengirim ulang email jadwal ' . $cek->no_quotation,
                    'status' => 'success'
                ], 200);
            } else {
                DB::commit();
                return response()->json([
                    'message' => 'Terdapat jadwal yang belum di-approve pada Validator.!',
                    'status' => 'failed'
                ], 200);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 'failed'
            ], 500);
        }
    }

    public function getStatusSampling(Request $request)
    {
        try {
            $getLabelStatusSampling =QuotationKontrakD::where('id_request_quotation_kontrak_h',$request->id_request_quotation_kontrak_h)
            ->where('periode_kontrak',$request->periode_kontrak)->first(['status_sampling']);
            
            return response()->json(['data'=>$getLabelStatusSampling],200);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json(["message"=>$th->getMessage(),"line"=>$getLine(),"file" =>$th->getFile()],400);
        }
    }
}
