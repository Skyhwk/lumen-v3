<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\AsetDamageHistories;
use App\Models\AsetFixingHistories;
use App\Models\AsetUsedHistories;
use App\Models\DataAset;
use App\Models\MasterKaryawan;
use App\Models\MasterKategoriAset;
use App\Models\MasterSubKategoriAset;
use Carbon\Carbon;
use Illuminate\Http\Request;
use DataTables;
use DB;
use Exception;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class DataAsetController extends Controller
{
    public function index(Request $request)
    {
        $data = DataAset::query()
            ->select(
                'data_aset.jenis_aset',
                'master_kategori_aset.nama_kategori',
                'master_sub_kategori_aset.nama_sub_kategori',
                \DB::raw('COUNT(data_aset.id) as total')
            )
            ->leftJoin('master_kategori_aset', 'master_kategori_aset.id', '=', 'data_aset.id_kategori_aset')
            ->leftJoin('master_sub_kategori_aset', 'master_sub_kategori_aset.id', '=', 'data_aset.id_subkategori_aset')
            ->where('data_aset.is_active', true)
            ->groupBy(
                'data_aset.jenis_aset',
                'master_kategori_aset.nama_kategori',
                'master_sub_kategori_aset.nama_sub_kategori'
            )
            ->orderBy('data_aset.jenis_aset', 'asc');

        return DataTables::of($data)->make(true);
    }

    public function getDetail(Request $request){
        $data = DataAset::with('fixing_histories', 'used_histories', 'damage_histories')
            ->where('jenis_aset', $request->jenis_aset)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'message' => 'Data aset berhasil ditemukan',
            'data' => $data
        ], 200);
    }

    public function getAsetDamageHistories(Request $request){
        $data = AsetDamageHistories::with('aset')->where('aset_id', $request->aset_id)->get();

        return DataTables::of($data)->make(true);
    }

    public function getAsetFixingHistories(Request $request){
        $data = AsetFixingHistories::with('aset')->where('aset_id', $request->aset_id)->get();

        return DataTables::of($data)->make(true);
    }
    
    public function getAsetUsedHistories(Request $request){
        $data = AsetUsedHistories::with('aset')->where('aset_id', $request->aset_id)->get();

        return DataTables::of($data)->make(true);
    }

    public function getKaryawan(Request $request){
        $departmentFixing = [];
        $data = MasterKaryawan::where('is_active', true)->get()->pluck('nama_lengkap')->toArray();

        return response()->json([
            'data' => $data
        ], 200);
    }

    public function updateColumn(Request $request)
    {
        DB::beginTransaction();
        try {
            if($request->mode == 'used'){
                $data = AsetUsedHistories::where('id', $request->id)->first();
            }else if($request->mode == 'damage'){
                $data = AsetDamageHistories::where('id', $request->id)->first();
            }else if($request->mode == 'fixing'){
                $data = AsetFixingHistories::where('id', $request->id)->first();
            }else{
                return response()->json([
                    'message' => 'Mode tidak ditemukan'
                ], 404);
            }
            $data->{$request->column} = $request->value;
            // $data->updated_by = $this->karyawan;
            // $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            $aset = DataAset::find($data->aset_id);
            $aset->status_alat = 'ready';
            $aset->is_ready_use = 1;
            $aset->save();

            DB::commit();
            return response()->json([
                'message' => 'Data Berhasil Diubah'
            ], 200);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage()
            ], 401);
        }
    }

    public function store(Request $request){
        DB::beginTransaction();
        try {
            $generated_no_cs = $this->generateNoSC('CS', strtoupper($request->jenis_alat_name));
            [$filename_qr, $unicode] = $this->generateQRAset($generated_no_cs, $request);
            
            $data                       = new DataAset();
            $data->no_cs                = $generated_no_cs;
            $data->unicode              = $unicode;
            $data->qr_filename          = $filename_qr;
            $data->jenis_aset           = $request->jenis_alat_name;
            $data->id_kategori_aset     = $request->id_kategori_aset;
            $data->id_subkategori_aset  = $request->id_subkategori_aset;
            $data->merk                 = $request->merk;
            $data->tipe                 = $request->tipe;
            $data->harga                = str_replace(',', '', $request->harga);
            $data->tanggal_pembelian    = $request->tanggal_pembelian;
            $data->status               = $request->status;
            $data->is_labeled           = $request->is_labeled === 'true' ? true : false;
            $data->kondisi              = $request->kondisi;
            $data->ruang                = $request->ruang_name;
            $data->lokasi               = $request->lokasi_name;
            $data->created_by           = $this->karyawan;
            $data->created_at           = Carbon::now()->format('Y-m-d H:i:s');
            $data->is_active            = true;
            $data->save();

            DB::commit();
            return response()->json([
                'message' => 'Data aset berhasil disimpan',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTrace()
            ],500);
        }
    }

    public function update(Request $request){
        DB::beginTransaction();
        try {

            $data                       = DataAset::find($request->id);
            if($request->jenis_alat_name != $data->jenis_aset){
                return response()->json([
                    'message' => 'Jenis alat tidak boleh diubah'
                ], 400);
            }
            $data->id_kategori_aset     = $request->id_kategori_aset;
            $data->id_subkategori_aset  = $request->id_subkategori_aset;
            $data->merk                 = $request->merk;
            $data->tipe                 = $request->tipe;
            $data->harga                = str_replace(',', '', $request->harga);
            $data->tanggal_pembelian    = $request->tanggal_pembelian;
            $data->status               = $request->status;
            $data->is_labeled           = $request->is_labeled === 'true' ? true : false;
            $data->kondisi              = $request->kondisi;
            $data->ruang                = $request->ruang_name;
            $data->lokasi               = $request->lokasi_name;
            $data->updated_by           = $this->karyawan;
            $data->updated_at           = Carbon::now()->format('Y-m-d H:i:s');
            $data->is_active            = true;
            $data->save();

            DB::commit();
            return response()->json([
                'message' => 'Data aset berhasil disimpan',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTrace()
            ],500);
        }
    }

    public function delete(Request $request){
        $data = DataAset::find($request->id);
        $data->is_active = false;
        $data->updated_by = $this->karyawan;
        $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
        $data->save();
    }

    public function addDamageHistory(Request $request){
        DB::beginTransaction();
        try {
            $data                       = new AsetDamageHistories();
            $data->aset_id              = $request->aset_id;
            $data->tanggal_kerusakan    = $request->tanggal_kerusakan;
            $data->penyebab_kerusakan   = $request->penyebab_kerusakan;
            $data->pengguna             = $request->pengguna;
            $data->created_by           = $this->karyawan;
            $data->created_at           = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            $aset                   = DataAset::find($request->aset_id);
            $aset->status_alat      = 'damaged';
            $aset->is_ready_use     = 0;
            $aset->save();

            DB::commit();
            return response()->json([
                'message' => 'Data kerusakan aset berhasil disimpan'
            ], 201);
        } catch (Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(),
                'trace' => $th->getTrace()
            ], 500);
        }
    }

    public function addUsedHistory(Request $request){
        DB::beginTransaction();
        try {
            $data                       = new AsetUsedHistories();
            $data->aset_id              = $request->aset_id;
            $data->tanggal_penggunaan   = $request->tanggal_penggunaan;
            if(isset($request->tanggal_pengembalian) && !empty($request->tanggal_pengembalian)){
                $data->tanggal_pengembalian  = $request->tanggal_pengembalian;
            }
            $data->peminjam             = $request->peminjam['value'];
            $data->created_by           = $this->karyawan;
            $data->created_at           = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            $aset                       = DataAset::find($request->aset_id);
            if(isset($request->tanggal_pengembalian) && !empty($request->tanggal_pengembalian)){
                $aset->status_alat      = 'ready';
                $aset->is_ready_use     = 1;
                $aset->save();
            }else{
                $aset->status_alat      = 'used';
                $aset->is_ready_use     = 0;
                $aset->save();
            }

            DB::commit();
            return response()->json([
                'message' => 'Data penggunaan aset berhasil disimpan'
            ], 201);
        } catch (Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(),
                'trace' => $th->getTrace()
            ], 500);
        }
    }

    public function addFixingHistory(Request $request){
        DB::beginTransaction();
        try {
            $data                       = new AsetFixingHistories();
            $data->aset_id              = $request->aset_id;
            $data->tanggal_mulai        = $request->tanggal_mulai;
            if(isset($request->tanggal_selesai) && !empty($request->tanggal_selesai)){
                $data->tanggal_selesai  = $request->tanggal_selesai;
            }
            $data->mekanik              = $request->mekanik['value'];
            $data->created_by           = $this->karyawan;
            $data->created_at           = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            $aset                       = DataAset::find($request->aset_id);
            if(isset($request->tanggal_selesai) && !empty($request->tanggal_selesai)){
                $conditionNeedFix = ['kendala','rusak','rusak_berat'];
                $aset->status_alat      = 'ready';
                $aset->is_ready_use     = 1;
                if(in_array($aset->kondisi, $conditionNeedFix)){
                    $aset->status_alat  = 'normal';
                }
                $aset->save();
            }else{
                $aset->status_alat      = 'fixing';
                $aset->is_ready_use     = 0;
                $aset->save();
            }

            DB::commit();
            return response()->json([
                'message' => 'Data perbaikan aset berhasil disimpan'
            ], 201);
        } catch (Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(), 
                'trace' => $th->getTrace()
            ], 500);
        }
    }

    public function getKategori(Request $request)
    {
        $data = MasterKategoriAset::where('is_active', 1)->get();
        return response()->json($data);
    }

    public function getSubKategori(Request $request){
        $data = MasterSubKategoriAset::where('id_kategori', $request->kategori_id)->get();
        return response()->json($data);
    }

    public function getJenisAset(Request $request)
    {
        $data = DataAset::select('jenis_aset')->distinct()->get();
        return response()->json($data);
    }

    public function getRuang(Request $request)
    {
        $data = DataAset::select('ruang')->distinct()->get();
        return response()->json($data);
    }

    public function getLokasi(Request $request)
    {
        $data = DataAset::select('lokasi')->distinct()->get();
        return response()->json($data);
    }

    private function generateNoSC(string $prefix, string $jenis_aset){
        $data = DataAset::where('jenis_aset', $jenis_aset)->orderBy('created_at', 'desc')->first();

        if (!$data) {
            return $prefix . "-" . $jenis_aset . '-001';
        }else{
            $cs_to_array = explode('-', $data->first()->no_cs);
            $cs_to_array[2] = $cs_to_array[2] + 1;
            return $prefix . "-" . $jenis_aset . '-' . str_pad($cs_to_array[2], 3, '0', STR_PAD_LEFT);
        }
    }

    private function generateQRAset($no_cs, $data) {
        $filename = str_replace('.','-',microtime(true));
        $path = public_path() . "/qr_assets/" . $filename . '.svg';
        if (!file_exists(dirname(public_path() . "/qr_assets/"))) {
            mkdir(dirname(public_path() . "/qr_assets/"), 0755, true);
        }

        $unique = str_replace('.','-',microtime(true));

        QrCode::size(200)->generate($unique, $path);

        $dataQr = [
            'type_document' => 'asset',
            'kode_qr' => $unique,
            'file' => $filename,
            'data' => json_encode([
                'type_document' => 'asset',
                'no_cs' => $no_cs,
                'merk' => $data->merk,
                'tanggal_pembelian' => $data->tanggal_pembelian,
                'ruang' => $data->ruang_name,
                'lokasi' => $data->lokasi_name
            ]),
            'created_at' => Carbon::now(),
            'created_by' => $this->karyawan,
        ];

        DB::table('qr_documents')->insert($dataQr);

        return [$filename . '.svg', $filename];
    }
}