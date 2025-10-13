<?php 
namespace App\Http\Controllers\api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;



use App\Models\{DraftErgonomiFile,OrderDetail};
use App\Services\PrintLhp;
class LhpErgonomiController extends Controller
{
    public function index(Request $request){
        DB::statement("SET SESSION sql_mode = ''");
        $generatedFiles = DraftErgonomiFile::select('id','no_sampel','tanggal_lhp','name_file', 'is_generate_link')
        ->get()
        ->keyBy('no_sampel');

        $kategori = ["27-Udara Lingkungan Kerja", "53-Ergonomi"];

        $data = OrderDetail::with([
            'orderHeader'
            => function ($query) {
                $query->select('id', 'nama_pic_order', 'jabatan_pic_order', 'no_pic_order', 'email_pic_order', 'alamat_sampling');
            }
        ])
            ->where('order_detail.status', 3)
            ->where('is_active', true)
            ->whereIn('kategori_3', $kategori)
            ->whereJsonContains('parameter','230;Ergonomi') //aktivin filter
            ->groupBy('no_sampel')
            ->get() // ambil data dulu
            ->map(function ($item) use ($generatedFiles) {
                if (isset($generatedFiles[$item->no_sampel])) {
                    $item->isGenerate = true;
                    $item->idGenerateFile = $generatedFiles[$item->no_sampel]['id'];
                    $item->filePdf = $generatedFiles[$item->no_sampel]['name_file'];
                    $item->tanggal_lhp = $generatedFiles[$item->no_sampel]['tanggal_lhp'];
                    $item->isGenerateLink = (bool)$generatedFiles[$item->no_sampel]['is_generate_link'];
                } else {
                    $item->isGenerate = false;
                    $item->filePdf = null;
                    $item->isGenerateLink = false;
                }
                return $item;
            });
        return Datatables::of($data)->make(true);
    }

    public function handleDownload(Request $request) {
        try {
            $header = DraftErgonomiFile::where('no_sampel', $request->no_sampel)->first();
            $fileName =null;
            if($header != null && $header->name_file != null) {
                $fileName = $header->name_file;
            }
            
            return response()->json([
                 'file_name' => url('draft_ergonomi/lhp/' . $fileName),
                'message' => 'Download file '.$request->no_sampel.' berhasil!'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error download file '.$th->getMessage(),
            ], 401);
        }
        
    }

    public function rePrint(Request $request) 
    {
        DB::beginTransaction();
        $header = DraftErgonomiFile::where('no_sampel', $request->no_sampel)->first();
        $header->count_print = $header->count_print + 1; 

        if ($header != null) {
            $servicePrint = new PrintLhp();
            $servicePrint->printErgonomi($header);
            
        }
        
        $header->save();
        
        DB::commit();

        return response()->json([
            'message' => 'Berhasil Melakukan Reprint Data ' . $request->no_sampel . ' berhasil!'
        ], 200);
    }

    public function handleReject(Request $request) {
        DB::beginTransaction();
        try {
            $header = DraftErgonomiFile::where('no_sampel', $request->no_sampel)->first();

            if($header != null) {
                $header->is_approve = 0;
                $header->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->rejected_by = $this->karyawan.'(LHP)';
                
                // $header->file_qr = null;
                $header->save();

                $data_order = OrderDetail::where('no_sampel', $request->no_sampel)->where('is_active', true)->update([
                    'status' => 2,
                    'is_approve' => 0,
                    'rejected_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'rejected_by' => $this->karyawan . '(LHP)'
                ]);
            }

            DB::commit();
            return response()->json([
                'message' => 'Reject no sampel '.$request->no_sampel.' berhasil!'
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan '.$e->getMessage(),
            ], 401);
        }
    }
}