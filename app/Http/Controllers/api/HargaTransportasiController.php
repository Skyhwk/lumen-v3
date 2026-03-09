<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\HargaTransportasi;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;

class HargaTransportasiController extends Controller
{
    public function index(Request $request)
    {
        $data = HargaTransportasi::withHistory()
        ->when($request->status, fn($q) => $q->where('master_harga_transportasi.status', $request->status))
        ->where('master_harga_transportasi.is_active', true);
        return Datatables::of($data)->make(true);
        
    }

    public function store(Request $request)
{
    DB::beginTransaction();
    try {
        if ($request->id != '') {
            $harga_transport = HargaTransportasi::find($request->id);

            if ($harga_transport) {
                if ($request->transportasi != '') $harga_transport->transportasi = \str_replace('.', '', $request->transportasi);
                if ($request->per_orang != '') $harga_transport->per_orang = \str_replace('.', '', $request->per_orang);
                if ($request->total != '') $harga_transport->total = \str_replace('.', '', $request->total);
                if ($request->tiket != '') $harga_transport->tiket = \str_replace('.', '', $request->tiket);

                if ($request->penginapan != '') $harga_transport->penginapan = \str_replace('.', '', $request->penginapan);
                if ($request->{'24jam'} != '') $harga_transport->{'24jam'} = \str_replace('.', '', $request->{'24jam'});


                $harga_transport->updated_by = $this->karyawan;
                $harga_transport->updated_at = DATE('Y-m-d H:i:s');
                $harga_transport->save();
            } else {
                return response()->json(['message' => 'Wilayah tidak ditemukan'], 404);
            }
        } else {
            $existingHargaTransport = HargaTransportasi::where('wilayah', $request->wilayah)
                ->where('status', $request->status)
                ->where('is_active', true)
                ->first();

            if ($existingHargaTransport) {
                return response()->json(['message' => 'Data untuk wilayah dan status tersebut sudah ada'], 400);
            }

            $harga_transport = new HargaTransportasi;

            $harga_transport->status = $request->status != '' ? $request->status : null;
            $harga_transport->wilayah = $request->wilayah != '' ? $request->wilayah : null;
            $harga_transport->transportasi = $request->transportasi != '' ? \str_replace('.', '', $request->transportasi) : null;
            $harga_transport->per_orang = $request->per_orang != '' ? \str_replace('.', '', $request->per_orang) : null;
            $harga_transport->total = $request->total != '' ? \str_replace('.', '', $request->total) : null;

            $harga_transport->tiket = $request->tiket != '' ? \str_replace('.', '', $request->tiket) : null;
            $harga_transport->penginapan = $request->penginapan != '' ? \str_replace('.', '', $request->penginapan) : null;
            $harga_transport->{'24jam'} = $request->{'24jam'} != '' ? \str_replace('.', '', $request->{'24jam'}) : null;


            $harga_transport->created_by = $this->karyawan;
            $harga_transport->created_at = DATE('Y-m-d H:i:s');
            $harga_transport->save();
        }

        DB::commit();
        return response()->json(['message' => 'Data telah disimpan'], 201);
    } catch (\Throwable $th) {
        DB::rollback();
        return response()->json(['message' => $th->getMessage()], 500);
    }
}

    public function delete(Request $request){
        if($request->id !=''){
            $data = HargaTransportasi::where('id', $request->id)->first();
            if($data){
                $data->deleted_at = Date('Y:m:d H:i:s');
                $data->deleted_by = $this->karyawan;
                $data->is_active = false;
                $data->save();

                return response()->json(['message' => 'Parameter successfully deleted'], 201);
            }

            return response()->json(['message' => 'Data Not Found.!'], 401);
        } else {
            return response()->json(['message' => 'Data Not Found.!'], 401);
        }
    }

    public function updateGlobal(Request $request)
    {
        $required = [
            'kategori_wilayah' => 'Kategori Wilayah Tidak Boleh Kosong',
            'kategori_harga'   => 'Kategori Harga Tidak Boleh Kosong',
            'status'           => 'Status Kenaikan Tidak Boleh Kosong',
            'persentase'       => 'Persentase Tidak Boleh Kosong',
        ];

        foreach ($required as $field => $message) {
            if (!$request->$field) return response()->json(['message' => $message], 401);
        }

        $field = $request->kategori_harga;
        
        $olds = HargaTransportasi::where([
            'status' => $request->kategori_wilayah,
            'is_active' => true
        ])->get();

        foreach ($olds as $old) {
            // set record baru sebagai data lama yg nonaktif (sbg history)
            $new = $old->replicate();

            $new->id_hist = $old->id;
            $new->created_by = $this->karyawan;
            $new->created_at = date('Y-m-d H:i:s');
            $new->updated_by = null;
            $new->updated_at = null;
            $new->is_active = false;

            $new->save();

            // update data lama pake data baru
            $value = $old->{$field};

            if ($request->status === 'Naik') {
                $value += ($value * $request->persentase / 100);
            } else {
                $value -= ($value * $request->persentase / 100);
            }

            $old->{$field} = $value;
        
            $old->updated_by = $this->karyawan;
            $old->updated_at = date('Y-m-d H:i:s');

            $old->save();
        }

        return response()->json(['message' => 'Harga berhasil diupdate'], 201);
    }
}


