<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SalesIn;
use Carbon\Carbon;
use DataTables;
use Illuminate\Support\Facades\DB;

class SalesInController extends Controller
{
    public function index(Request $request)
    {
        $data = SalesIn::where('is_active', true)->whereYear('tanggal_masuk', $request->year)->orderBy('id', 'DESC');

        return DataTables::of($data)->make(true);
    }

    public function create(Request $request)
    {
        DB::beginTransaction();
        try {
            $microtime = microtime(true);
            $no_dokumen = str_replace('.', '/', $microtime);

            $salesIn = new SalesIn();            
            $salesIn->no_dokumen = $no_dokumen;
            $salesIn->tanggal_masuk = $request->tanggal_masuk;
            $salesIn->keterangan = $request->keterangan;
            $salesIn->nominal = $request->nominal;
            $salesIn->type_pembayaran = $request->type_pembayaran;
            $salesIn->type_rekening = $request->type_rekening;
            $salesIn->created_by = $this->karyawan;
            $salesIn->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $salesIn->save();

            DB::commit();
            return response()->json(['message' => 'Data berhasil ditambahkan'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 401);
        }
    }

    public function update(Request $request)
    {
        try {
            $data = SalesIn::find($request->id);
            $data->update([$request->column => $request->value]);

            return response()->json(['message' => 'Data berhasil diubah']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()]);
        }
    }

    public function delete(Request $request)
    {
        $data = SalesIn::find($request->id);
        $data->is_active = false;
        $data->save();
        return response()->json(['message' => 'Data berhasil dihapus']);
    }

    public function pemasukan(Request $request)
    {
        $data = SalesIn::where('tanggal_masuk', $request->tanggal_masuk)->where('is_active', true)->sum('nominal');
        return response()->json(['data' => $data]);
    }
}