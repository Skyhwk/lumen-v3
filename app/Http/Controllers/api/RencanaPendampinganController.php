<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateDocumentSamplingJob;
use App\Jobs\RenderAndEmailJadwal;
use App\Jobs\RenderSamplingPlan;
use App\Models\Jadwal;
use App\Models\JadwalLibur;
use App\Models\JobTask;
use App\Models\MasterCabang;
use App\Models\MasterDriver;
use App\Models\MasterKaryawan;
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\PerbantuanSampler;
use App\Models\PraNoSample;
use App\Models\QuotationKontrakD;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\SamplingPlan;
use App\Services\GenerateDocumentSampling;
use App\Services\GetAtasan;
use App\Services\JadwalServices;
use App\Services\Notification;
use App\Services\RenderSamplingPlan as RenderSamplingPlanService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\Datatables\Datatables;

class RencanaPendampinganController extends Controller
{

    /*  */
    public function index(Request $request)
    {
        
        $active = $request->is_active == '' ? true : $request->is_active;

        $data = Jadwal::with([
            'samplingPlan:id,created_at,filename,is_active',
            'samplingPlan' => function ($query) {
                $query->WithTypeModelSub();
            },
        ])
            ->select('id_sampling', 'parsial', 'no_quotation', 'nama_perusahaan','isokinetic','pendampingan_k3', 'tanggal', 'periode', 'jam_mulai', 'jam_selesai', 'kategori', 'durasi', 'status', 'warna', 'note', 'urutan', 'driver', 'id_cabang', 'wilayah', DB::raw('group_concat(sampler) as sampler'), DB::raw('group_concat(id) as batch_id'), DB::raw('group_concat(userid) as batch_user'), 
            DB::raw('MAX(created_by) as created_by'), 
            DB::raw('MIN(created_at) as created_at'), // Ambil waktu buat paling awal
            DB::raw('MAX(updated_at) as updated_at'), // Ambil waktu update paling baru
            DB::raw('MAX(updated_by) as updated_by') ) // Ambil user update terakhir)
            ->groupBy('id_sampling', 'parsial', 'no_quotation', 'tanggal', 'periode', 'nama_perusahaan','isokinetic','pendampingan_k3', 'durasi', 'driver', 'kategori', 'status', 'jam_mulai', 'jam_selesai', 'warna', 'note', 'urutan', 'wilayah', 'id_cabang')
            ->whereNotNull('no_quotation')
            ->where('pendampingan_k3', true)
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
                    $days = (int) $matches[1];
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
            // ->filterColumn('sampler', function ($query, $keyword) {
            //     $query->where('sampler', 'like', '%' . $keyword . '%');
            // })
            ->filterColumn('sampler', function ($query, $keyword) {
                $table = $query->getModel()->getTable();

                $query->whereExists(function ($subquery) use ($keyword, $table) {
                    $subquery->select(DB::raw(1))
                            ->from("$table as sub")
                            ->whereRaw("(sub.id_sampling <=> $table.id_sampling)")
                            ->whereRaw("(sub.parsial <=> $table.parsial)")           // NULL-safe
                            ->whereRaw("(sub.no_quotation <=> $table.no_quotation)")
                            ->whereRaw("(sub.nama_perusahaan <=> $table.nama_perusahaan)")
                            ->whereRaw("(sub.tanggal <=> $table.tanggal)")
                            ->whereRaw("(sub.periode <=> $table.periode)")
                            ->whereRaw("(sub.jam_mulai <=> $table.jam_mulai)")
                            ->whereRaw("(sub.jam_selesai <=> $table.jam_selesai)")
                            ->whereRaw("(sub.durasi <=> $table.durasi)")
                            ->whereRaw("(sub.driver <=> $table.driver)")             // NULL-safe
                            ->whereRaw("(sub.kategori <=> $table.kategori)")
                            ->whereRaw("(sub.status <=> $table.status)")
                            ->whereRaw("(sub.id_cabang <=> $table.id_cabang)")       // NULL-safe
                            ->whereRaw("(sub.wilayah <=> $table.wilayah)")          // NULL-safe
                            ->whereRaw("(sub.is_active <=> $table.is_active)")          // NULL-safe
                            ->where('sub.sampler', 'like', '%' . $keyword . '%');
                });
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
}
