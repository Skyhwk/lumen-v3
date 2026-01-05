<?php  
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\{OrderHeader,MasterKaryawan,GenerateLink,Invoice,RecordPembayaranInvoice,Withdraw,DistribusiInvoice};
use App\Services\{GenerateToken,RenderInvoice,SendEmail,GetAtasan};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;

class DistribusiInvoiceController extends Controller
{
    public function indexInvoice(Request $request)
    {
        // 1. Subquery Withdraw
        $withdrawSub = DB::table('withdraw')
            ->select('no_invoice', DB::raw('SUM(nilai_pembayaran) as total_pembayaran'))
            ->groupBy('no_invoice');

        // 2. Subquery Invoice Aggregate
        $invoiceAgg = DB::table('invoice')
            ->select(
                DB::raw('MAX(id) AS id'),
                'no_invoice',
                DB::raw('SUM(total_tagihan) AS total_tagihan'),
                DB::raw('FLOOR(SUM(nilai_tagihan)) AS nilai_tagihan'),
                DB::raw('MAX(nilai_pelunasan) AS nilai_pelunasan'),
                DB::raw('MAX(nama_pic) AS nama_pic'),
                DB::raw('MAX(no_pic) AS no_pic'),
                DB::raw('MAX(alamat_penagihan) AS alamat_penagihan'),
                DB::raw('MAX(created_at) AS created_at'),
                DB::raw('MAX(created_by) AS created_by'),
                DB::raw('MAX(tgl_jatuh_tempo) AS tgl_jatuh_tempo'),
                DB::raw('MAX(tgl_pelunasan) AS tgl_pelunasan'),
                DB::raw('GROUP_CONCAT(DISTINCT no_order) AS no_orders'),
                DB::raw('MAX(no_order) AS primary_no_order')
            )
            ->where('is_active', true)
            ->where('is_whitelist', false)
            ->groupBy('no_invoice');

        // 3. Main Query (PURE DATA)
        $query = DB::table(DB::raw("({$invoiceAgg->toSql()}) as inv"))
            ->mergeBindings($invoiceAgg)
            ->select(
                'inv.no_invoice',
                'inv.nilai_tagihan',
                'inv.total_tagihan',
                // Perbaikan logic sisa bayar & total bayar handle NULL
                DB::raw('(inv.nilai_pelunasan + COALESCE(w.total_pembayaran, 0)) AS total_pembayaran'),
                DB::raw('(inv.nilai_tagihan - (inv.nilai_pelunasan + COALESCE(w.total_pembayaran, 0))) AS sisa_tagihan'), 
                'inv.nama_pic',
                'inv.no_pic',
                'inv.alamat_penagihan',
                DB::raw('COALESCE(oh.nama_perusahaan, oh.konsultan) AS nama_customer'),
                'oh.konsultan', // Tambahkan ini agar filterColumn konsultan jalan
                'inv.tgl_jatuh_tempo',
                'inv.tgl_pelunasan',
                'inv.no_orders',
                'inv.id'
            )
            ->leftJoin('order_header as oh', 'inv.primary_no_order', '=', 'oh.no_order')
            // PERBAIKAN 1: Gunakan leftJoinSub agar invoice yang belum dibayar tetap muncul
            ->leftJoinSub($withdrawSub, 'w', function($join) {
                $join->on('inv.no_invoice', '=', 'w.no_invoice');
            });

        // 4. Filter Kategori (Applied ke Query Builder, BUKAN Collection)
        $query->where('inv.nilai_tagihan', '>', 5000000);
        $query->whereNotExists(function ($q) {
            $q->select(DB::raw(1))
            ->from('distribusi_invoice')
            ->whereRaw('distribusi_invoice.no_invoice = inv.no_invoice');
        });
        // if ($request->has('filtered')) {
        //     if ($request->filtered == 'invoice') {
                
        //     } elseif ($request->filtered == 'distribusi') {
        //         $query->join('distribusi_invoice as di', 'inv.no_invoice', '=', 'di.no_invoice');
        //     }
        // }

        // 5. Return DataTables (Server Side)
        // PERBAIKAN 2: Gunakan $query langsung, jangan di ->get()
        return datatables()->of($query)
            ->filterColumn('no_invoice', function($query, $keyword) {
                $query->where('inv.no_invoice', 'like', "%{$keyword}%");
            })
            ->filterColumn('nama_customer', function($query, $keyword) {
                $query->where(function($q) use ($keyword) {
                    $q->where('oh.nama_perusahaan', 'like', "%{$keyword}%")
                    ->orWhere('oh.konsultan', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('nama_pic', function($query, $keyword) {
                $query->where('inv.nama_pic', 'like', "%{$keyword}%");
            })
            ->filterColumn('no_pic', function($query, $keyword) {
                $query->where('inv.no_pic', 'like', "%{$keyword}%");
            })
            ->filterColumn('konsultan', function($query, $keyword) {
                $query->where('oh.konsultan', 'like', "%{$keyword}%");
            })
            ->addColumn('withdraw_status', function($invoice) {
                $total = number_format($invoice->total_pembayaran, 0, ',', '.');
                return "Total Bayar: Rp " . $total; 
            })
            ->addColumn('sisa_tagihan_formatted', function($invoice) {
                return number_format($invoice->sisa_tagihan, 0, ',', '.');
            })
            ->rawColumns(['withdraw_status'])
            ->make(true);
    }
    public function indexDistribusi(Request $request)
    {
        // 1. Query
        $query = DistribusiInvoice::query() // Sesuaikan query kamu sebelumnya
            ->when($request->year, function($q) use ($request) {
                $q->whereYear('created_at', $request->year);
            })
            ->when($request->month, function($q) use ($request) {
                $q->whereMonth('created_at', $request->month);
            });

        // 2. DataTables Processing
        return datatables()->of($query)
            // --- 1. KOLOM NAMA KURIR ---
            // Ambil dari JSON key "nama"
            ->addColumn('nama_kurir', function($row) {
                // Karena sudah di-cast 'array', kita bisa akses seperti array biasa
                // Gunakan '??' (null coalescing) untuk handle jika key tidak ada
                return $row->pengiriman['nama'] ?? '-';
            })

            // --- 2. KOLOM CABANG ---
            // Ambil dari JSON key "cabang"
            ->addColumn('cabang', function($row) {
                return $row->pengiriman['cabang'] ?? '-';
            })

            // --- 3. KOLOM NO RESI ---
            // Ambil dari JSON key "no_resi"
            ->addColumn('no_resi', function($row) {
                return $row->pengiriman['no_resi'] ?? '-';
            })

            // --- 4. KOLOM EKSPEDISI ---
            // Ambil dari JSON key "nama_ekspedisi"
            ->addColumn('nama_ekspedisi', function($row) {
                return $row->pengiriman['nama_ekspedisi'] ?? '-';
            })

            // Format tanggal standar
            ->editColumn('created_at', function($row) {
                return $row->created_at ? date('d-m-Y H:i', strtotime($row->created_at)) : '-';
            })
            ->editColumn('alamat', function($row) {
                return $row->alamat ?? '-';
            })
            ->editColumn('type_pengiriman', function($row) {
                return $row->type_pengiriman ?? '-';
            })
            ->make(true);
    }
    public function getInvoiceSelect2(Request $request)
    {
        $search = $request->q; // Keyword dari Select2
        if (strlen($search) < 3) {
            return response()->json(['items' => [], 'total_count' => 0]);
        }
        $perPage = 10;
        // 1. Subquery Withdraw
        $withdrawSub = DB::table('withdraw')
            ->select('no_invoice', DB::raw('SUM(nilai_pembayaran) as total_pembayaran'))
            ->groupBy('no_invoice');

        // 2. Subquery Invoice Aggregate
        $invoiceAgg = DB::table('invoice')
            ->select(
                DB::raw('MAX(id) AS id'),
                'no_invoice',
                DB::raw('SUM(total_tagihan) AS total_tagihan'),
                DB::raw('FLOOR(SUM(nilai_tagihan)) AS nilai_tagihan'),
                DB::raw('MAX(nilai_pelunasan) AS nilai_pelunasan'),
                DB::raw('MAX(nama_pic) AS nama_pic'),
                DB::raw('MAX(no_pic) AS no_pic'),
                DB::raw('MAX(no_spk) AS no_spk'),
                DB::raw('MAX(created_at) AS created_at'),
                DB::raw('MAX(created_by) AS created_by'),
                DB::raw('MAX(tgl_jatuh_tempo) AS tgl_jatuh_tempo'),
                DB::raw('MAX(tgl_pelunasan) AS tgl_pelunasan'),
                DB::raw('MAX(alamat_penagihan) AS alamat_penagihan'),
                DB::raw('GROUP_CONCAT(DISTINCT no_order) AS no_orders'),
                DB::raw('MAX(no_order) AS primary_no_order')
            )
            ->where('is_active', true)
            ->where('is_whitelist', false)
            ->groupBy('no_invoice');

        // 3. Main Query (PURE DATA)
        $query = DB::table(DB::raw("({$invoiceAgg->toSql()}) as inv"))
            ->mergeBindings($invoiceAgg)
            ->select(
                'inv.id',
                'inv.no_invoice',
                DB::raw('COALESCE(oh.nama_perusahaan, oh.konsultan) AS nama_customer'),
                'inv.tgl_jatuh_tempo',
                'inv.tgl_pelunasan',
                'inv.no_orders',
                'inv.alamat_penagihan',
                'inv.nama_pic',
                'inv.no_pic'
            )
            ->leftJoin('order_header as oh', 'inv.primary_no_order', '=', 'oh.no_order')
            // PERBAIKAN 1: Gunakan leftJoinSub agar invoice yang belum dibayar tetap muncul
            ->leftJoinSub($withdrawSub, 'w', function($join) {
                $join->on('inv.no_invoice', '=', 'w.no_invoice');
            });
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('inv.no_invoice', 'like', "%{$search}%")
                    ->orWhere('oh.nama_perusahaan', 'like', "%{$search}%");
                });
            }
            // --- PAGINATION ---
            $result = $query->paginate($perPage);
            // Format return JSON agar enak dibaca Select2 frontend
            return response()->json([
                'items' => $result->map(function($item) {
                    return [
                        'id' => $item->id, // Value yang disimpan
                        'text' => $item->no_invoice, // Teks yang muncul
                        'no_invoice' => $item->no_invoice,
                        'nama_customer' => $item->nama_customer,
                        'alamat_penagihan' => $item->alamat_penagihan ?? 'Alamat belum diisi',
                        'nama_pic' =>$item->nama_pic,
                        'no_pic' =>$item->no_pic
                    ];
                }),
                'total_count' => $result->total()
            ]);
    }

    public function getSelectKurir(Request $request)
    {
        $search = $request->q;
        $query = MasterKaryawan::with(['cabang' => function($q) {
                $q->select('id', 'nama_cabang'); 
            }])
            ->where('jabatan', 'Courier')
            ->where('is_active', true);
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                ->orWhere('nik_karyawan', 'like', "%{$search}%");
            });
        }
        $query->select('id', 'nama_lengkap', 'nik_karyawan', 'id_cabang');
        $result = $query->paginate(10);
        return response()->json([
            'items' => $result->map(function($item) {
                return [
                    'id' => $item->id, 
                    'text' => $item->nama_lengkap,
                    'cabang' => $item->cabang->nama_cabang ?? '-', 
                    'nik' => $item->nik_karyawan
                ];
            }),
            'total_count' => $result->total()
        ]);
    }

    public function submit (Request $request)
    {
        DB::beginTransaction();
        try {
            // Cek apakah ada items untuk diproses
            if (!empty($request->items)) {
                foreach ($request->items as $item) {
                    $distribusi = new DistribusiInvoice();
                    $distribusi->no_invoice       = $item['no_invoice']; // Ambil dari array item
                    $distribusi->alamat           = $item['alamat_tujuan_final'];
                    $distribusi->nama_penerima    = $item['nama_penerima']; 
                    $distribusi->no_telp          = $item['no_telp'];
                    $distribusi->tanggal_pengiriman = $request->tgl_pengiriman;
                    $distribusi->type_pengiriman  = $request->tipe_pengiriman;
                    $distribusi->pengiriman       = $request->data_pengiriman;
                    $distribusi->created_at = DATE('Y-m-d H:i:s');;
                    $distribusi->created_by = $this->karyawan;
                    $distribusi->save();
                }
                DB::commit();
                return response()->json([
                    "status"  => "success",
                    "message" => "Data distribusi berhasil disimpan.",
                    "total_data" => count($request->items)
                ], 200);
            } else {
                return response()->json([
                    "status" => "warning",
                    "message" => "Tidak ada item invoice yang dipilih."
                ], 400);
            }
        }catch (\Illuminate\Database\QueryException $e) {
        DB::rollBack();
        //Duplicate Entry (SQL Code 1062)
        if ($e->errorInfo[1] == 1062) {
            // Kita ambil nomor invoice yang duplikat dari pesan errornya
            preg_match("/entry '(.*?)' for key/", $e->getMessage(), $matches);
            $duplicateValue = $matches[1] ?? 'tersebut';

            return response()->json([
                "status" => "duplicate",
                "message" => "Gagal! Nomor Invoice [$duplicateValue] sudah pernah didistribusikan sebelumnya. Silakan periksa kembali data Anda."
            ], 400); // Gunakan 422 (Unprocessable Entity)
        }

        // Jika error database lain

        return response()->json([
            "status" => "error",
            "message" => $e->getMessage()
        ], 400);

    }catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "status"  => "error",
                "message" => "Terjadi kesalahan saat menyimpan data. Silakan coba lagi.", // Pesan buat User
                "debug"   => [ // Info buat Developer (Bisa dihapus saat production)
                    "error" => $th->getMessage(),
                    "file"  => $th->getFile(),
                    "line"  => $th->getLine()
                ]
            ], 400);
        }
    }
}