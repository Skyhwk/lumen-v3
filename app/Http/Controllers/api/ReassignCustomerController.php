<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DFUS;
use App\Models\HistoryPerubahanSales;
use App\Models\MasterKaryawan;
use App\Services\RandomSalesAssigner;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReassignCustomerController extends Controller
{
    public function index(Request $request)
    {

        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;

        $query = HistoryPerubahanSales::with([
            'detailPelanggan:id_pelanggan,nama_pelanggan,wilayah,id',
            'salesLama:id,nama_lengkap',
            'salesBaru:id,nama_lengkap',
            'status',
        ])
            ->where('is_called', 0)
            ->orderByDesc('id');

        switch ($jabatan) {
            case 24: // Sales Staff
                $query->where('id_sales_baru', $this->user_id);
                break;

            case 21: // Sales Supervisor
                $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)->pluck('id')->toArray();
                array_push($bawahan, $this->user_id);

                $query->whereIn('id_sales_baru', $bawahan);
                break;
        }

        $query = $query->filterReassignList();

        return datatables()->eloquent($query)
            ->filterColumn('detail_pelanggan.nama_pelanggan', function ($query, $keyword) {
                $query->whereHas('detailPelanggan', function ($q) use ($keyword) {
                    $q->where('nama_pelanggan', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('detail_pelanggan.wilayah', function ($query, $keyword) {
                $query->whereHas('detailPelanggan', function ($q) use ($keyword) {
                    $q->where('wilayah', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('sales_lama.nama_lengkap', function ($query, $keyword) {
                $query->whereHas('salesLama', function ($q) use ($keyword) {
                    $q->where('nama_lengkap', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('sales_baru.nama_lengkap', function ($query, $keyword) {
                $query->whereHas('salesBaru', function ($q) use ($keyword) {
                    $q->where('nama_lengkap', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('tanggal_rotasi', function ($query, $keyword) {
                $query->whereDate('tanggal_rotasi', 'like', "%{$keyword}%");
            })
            ->make(true);
    }

    public function test()
    {
        $randomSales = new RandomSalesAssigner;
        $randomSales->run();
    }

    public function saveDFUS(Request $request)
    {
        // dd($request->all());
        $message = null;
        if ($request->has('array_data')) {
            $sudahDihubungi = [];
            $data           = [];
            $dataHistory    = [];
            foreach ($request->array_data as $item) {
                if (! isset($item['id_pelanggan']) || ! isset($item['kontak_pelanggan']) || ! isset($item['sales_penanggung_jawab'])) {
                    continue;
                }

                $kontak = preg_replace(['/[^0-9]/', '/^(\+62|62)/'], ['', '0'], $item['kontak_pelanggan']);
                if ($kontak == '') {
                    continue;
                }

                $cekLog = DB::table('log_webphone')
                    ->join('master_karyawan', 'master_karyawan.id', '=', 'log_webphone.karyawan_id')
                    ->where('log_webphone.number', 'like', '%' . $kontak . '%')
                    ->select('master_karyawan.nama_lengkap', 'log_webphone.created_at', 'log_webphone.number')
                    ->orderBy('log_webphone.created_at', 'desc')
                    ->first();

                if (
                    $cekLog && $cekLog->nama_lengkap != $this->karyawan &&
                    \Carbon\Carbon::parse($cekLog->created_at)->diffInMonths(\Carbon\Carbon::now()) < 2
                ) {
                    $time             = Carbon::parse($cekLog->created_at)->translatedFormat('d F Y H:i');
                    $sudahDihubungi[] = "<strong>{$item['nama_pelanggan']}</strong><br /> oleh: <strong>{$cekLog->nama_lengkap}</strong><br />pada: {$time}";
                }

                $data[] = [
                    'id_pelanggan'           => $item['id_pelanggan'],
                    'kontak'                 => 'Perusahaan - ' . $kontak,
                    'sales_penanggung_jawab' => $item['sales_penanggung_jawab'],
                    'tanggal'                => $item['tanggal'] ?? Carbon::now()->format('Y-m-d'),
                    'jam'                    => $item['jam'] ?? Carbon::now()->format('H:i:s'),
                    'created_by'             => $this->karyawan,
                    'created_at'             => Carbon::now()->format('Y-m-d H:i:s'),
                ];

                $dataHistory[] = $item['id_history'];

            }

            // kalau ada yang udah dihubungi, batalkan insert
            if (! empty($sudahDihubungi)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Beberapa pelanggan sudah pernah dihubungi: <br /><br />' . implode('<br /><br />', $sudahDihubungi) . '<br /><br />Silahkan cek kembali data yang akan ditambahkan.',
                ], 500);
            }

            // aman semua → insert
            if (! empty($data)) {
                DFUS::insert($data);
                HistoryPerubahanSales::whereIn('id', $dataHistory)->update(['is_called' => 1]);
                return response()->json([
                    'status'  => 'success',
                    'message' => 'Data berhasil disimpan.',
                ], 200);
            }

            // kalau kosong beneran
            return response()->json([
                'status'  => 'error',
                'message' => 'Tidak ada data valid untuk disimpan.',
            ], 204);
        } else {
            if (! $request->id_pelanggan || ! $request->kontak || ! $request->sales_penanggung_jawab || ! $request->tanggal || ! $request->jam) {
                $message = 'Data tidak lengkap.';
            } else {
                // dd($request->all());
                $cekLog = DB::table('log_webphone')
                    ->join('master_karyawan', 'master_karyawan.id', '=', 'log_webphone.karyawan_id')
                    ->where('log_webphone.number', 'like', '%' . preg_replace(['/[^0-9]/', '/^(\+62|62)/'], ['', '0'], \explode(' - ', $request->kontak)[1] . '%'))
                    ->select('master_karyawan.nama_lengkap', 'log_webphone.created_at', 'log_webphone.number')
                    ->orderBy('log_webphone.created_at', 'desc')
                    ->first();

                $karyawan_now = $request->attributes->get('user');

                if ($cekLog) {

                    if (($karyawan_now->karyawan->id_jabatan != 148) && $cekLog->nama_lengkap != $this->karyawan && \Carbon\Carbon::parse($cekLog->created_at)->diffInMonths(\Carbon\Carbon::now()) < 2) {
                        return response()->json([
                            'status'  => 'error',
                            'message' => 'Pelanggan sudah pernah dihubungi pada ' . $cekLog->created_at . ' oleh ' . $cekLog->nama_lengkap . '.',
                        ], 401);
                    }
                }

                $dfus                         = new DFUS;
                $dfus->id_pelanggan           = $request->id_pelanggan;
                $dfus->kontak                 = $request->kontak;
                $dfus->sales_penanggung_jawab = ($karyawan_now->karyawan->id_jabatan != 148) ? $request->sales_penanggung_jawab : $this->karyawan;
                $dfus->tanggal                = $request->tanggal;
                $dfus->jam                    = $request->jam;
                $dfus->created_by             = $this->karyawan;
                $dfus->save();

                HistoryPerubahanSales::where('id', $request->id)->update(['is_called' => 1]);


                

                $message = 'Data berhasil disimpan.';
            }
        }

        return response()->json(['message' => $message, 'status' => 'success'], 200);
    }
}
