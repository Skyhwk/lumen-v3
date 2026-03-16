<?php

namespace App\Http\Controllers\mobile;

// DATA LAPANGAN
use App\Models\DataLapanganSampah;

// MASTER DATA
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;
use App\Models\OrderHeader;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;

// SERVICE
use App\Services\SendTelegram;
use App\Services\GetAtasan;
use App\Services\InsertActivityFdl;

use App\Http\Controllers\Controller;
use App\Models\PendampinganK3;
use App\Models\PendampinganK3Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class FdlPendampinganK3Controller extends Controller
{
    public function getOrderByNoOrder(Request $request)
    {
        $order = OrderHeader::with('orderDetail')->where('no_order', $request->no_order)->first();

        return response()->json([
            'data' => $order
        ]);
    }

    public function index()
    {
        $template = PendampinganK3Template::where('is_active', 1)->get();

        return response()->json([
            'data' => $template
        ]);
    }

    public function store(Request $request)
    {
        $fotoFiles = [];
        $index = 1;

        while ($request->has("foto_lokasi_{$index}")) {
            $foto = $request->input("foto_lokasi_{$index}");
            if ($foto) {
                $fotoFiles[] = $this->convertImg($foto, "{$index}", $this->user_id);
            }
            $index++;
        }

        $data = PendampinganK3::create([
            'no_order'          => $request->no_order,
            'nama_perusahaan'   => $request->nama_perusahaan,
            'nomor_sampel'      => $request->nomor_sampel,
            'lokasi'            => $request->lokasi,
            'jenis_kegiatan'    => $request->jenis_kegiatan,
            'tanggal_observasi' => $request->tanggal_observasi,
            'waktu_observasi'   => $request->waktu_observasi,
            'templates'         => $request->templates,
            'foto'              => json_encode($fotoFiles),
            'created_by'        => $this->karyawan,
            'created_at'        => Carbon::now(),
        ]);

        return response()->json(['message' => 'Data berhasil disimpan'], 201);
    }

    public function convertImg($foto = '', $type = '', $user = '')
    {
        $img = str_replace('data:image/jpeg;base64,', '', $foto);
        $file = base64_decode($img);
        $safeName = DATE('YmdHis') . '_' . $user . $type . '.jpeg';
        $destinationPath = public_path() . '/dokumentasi/sampling/';
        $success = file_put_contents($destinationPath . $safeName, $file);
        return $safeName;
    }
}
