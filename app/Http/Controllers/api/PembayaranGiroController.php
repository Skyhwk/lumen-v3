<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PembayaranGiro;
use App\Models\SalesIn;
use Carbon\Carbon;
use DataTables;
use Illuminate\Support\Facades\DB;

class PembayaranGiroController extends Controller
{
    public function index(Request $request)
    {
        $year = ($request->year != "") ? $request->year : date('Y'); 
        $data = PembayaranGiro::whereYear('tanggal_giro', $year)
        ->where('status', $request->status)->orderBy('id', 'DESC');
        return DataTables::of($data)
        // Tambahkan closure filter dengan parameter kedua 'true' 
        // untuk meng-override filter bawaan DataTables
        ->filter(function ($query) {
            // Biarkan kosong agar Yajra tidak menambahkan WHERE tambahan 
            // dari hasil .search() yang tidak sengaja terinput
        }, true)
        ->make(true);
    }

    public function create(Request $request)
    {
        DB::beginTransaction();
        try {
            $microtime = microtime(true);
            $no_dokumen = str_replace('.', '/', $microtime);

            $pembayaranGiro = new PembayaranGiro();            
            $pembayaranGiro->batch_id = $no_dokumen;
            $pembayaranGiro->nominal = $request->nominal;
            $pembayaranGiro->tanggal_giro = $request->tanggal_giro;
            $pembayaranGiro->keterangan = $request->keterangan;
            $pembayaranGiro->created_by = $this->karyawan;
            $pembayaranGiro->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $pembayaranGiro->save();

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
            $data = PembayaranGiro::find($request->id);
            $data->update([$request->column => $request->value]);

            return response()->json(['message' => 'Data berhasil diubah']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()]);
        }
    }

    public function delete(Request $request)
    {
        $data = PembayaranGiro::find($request->id);
        if ($data) {
            $data->delete();
            return response()->json(['message' => 'Data berhasil dihapus']);
        } else {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }
    }

    public function pemasukan(Request $request)
    {
        $data = PembayaranGiro::where('tanggal_giro', $request->tanggal_giro)->sum('nominal');
        return response()->json(['data' => $data]);
    }

    public function approve(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = PembayaranGiro::find($request->id);
            $data->status = 'Cleared';
            $data->clearing_by = $this->karyawan;
            $data->clearing_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            $salesIn = new SalesIn();            
            $salesIn->no_dokumen = $data->batch_id;
            $salesIn->keterangan = $data->keterangan;
            $salesIn->nominal = $data->nominal;
            $salesIn->tanggal_masuk = $request->tanggal_masuk;
            $salesIn->type_pembayaran = 'Giro';
            $salesIn->type_rekening = $request->type_rekening;
            $salesIn->created_by = $this->karyawan;
            $salesIn->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $salesIn->save();   

            DB::commit();
            return response()->json(['message' => 'Data berhasil diproses']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 401);
        }
    }

    public function reject(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = PembayaranGiro::find($request->id);
            $data->status = 'Uncleared';
            $data->clearing_by = NULL;
            $data->clearing_at = NULL;
            $data->save();

            $cekSalesIn = SalesIn::where('no_dokumen', $data->batch_id)->first();
            if ($cekSalesIn) {
                $cekSalesIn->delete();
            }

            DB::commit();
            return response()->json(['message' => 'Data berhasil direject']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 401);
        }
    }
}