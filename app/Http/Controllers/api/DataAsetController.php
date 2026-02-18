<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DataAset;
use App\Models\MasterKategoriAset;
use App\Models\MasterSubKategoriAset;
use Carbon\Carbon;
use Illuminate\Http\Request;
use DataTables;
use DB;
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
        $data = DataAset::where('jenis_aset', $request->jenis_aset)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'message' => 'Data aset berhasil ditemukan',
            'data' => $data
        ], 200);
    }

    public function store(Request $request){
        DB::beginTransaction();
        try {
            $generated_no_sc = $this->generateNoSC('CS', strtoupper($request->jenis_alat_name));
            [$filename_qr, $unicode] = $this->generateQRAset($generated_no_sc);

            $data                       = new DataAset();
            $data->no_cs                = $generated_no_sc;
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

    private function generateQRAset($no_sc) {
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
                'no_sc' => $no_sc,
            ]),
            'created_at' => Carbon::now(),
            'created_by' => $this->karyawan,
        ];

        DB::table('qr_documents')->insert($dataQr);

        return [$filename . '.svg', $filename];
    }
}