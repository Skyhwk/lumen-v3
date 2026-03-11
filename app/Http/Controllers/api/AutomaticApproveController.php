<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\AutomaticApprove;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Yajra\Datatables\Datatables;

class AutomaticApproveController extends Controller
{
    public function index(Request $request)
    {
        $table = AutomaticApprove::with(['sample']);
        return Datatables::of($table)
            ->addColumn('kategori', function ($data) {
                return optional($data->sample)->nama_kategori ?? 'Nama tidak ditemukan';
            })
            ->filterColumn('kategori', function ($query, $keyword) {
                $query->whereHas('sample', function ($query) use ($keyword) {
                    $query->where('master_kategori.nama_kategori', 'like', '%' . $keyword . '%');
                });
            })
            ->make(true);

    }

    public function store(Request $request)
    {

        DB::beginTransaction();
        try {
            AutomaticApprove::create([
                'nama_template' => $request->template_name,
                'id_template'   => $request->template_id,
                'id_kategori'   => $request->category_id,
                'interval'      => $request->interval,
                'created_at'    => Carbon::now()->format('Y-m-d H:i:s'),
                'created_by'    => $this->karyawan,
            ]);
            DB::commit();

            return response()->json(['status' => 'success', 'message' => 'Data Automatic Approve Berhasil Disimpan']);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Terjadi Kesalahan Sistem', 'error' => $th->getMessage()]);
        }

    }

    public function update(Request $request)
    {
        DB::beginTransaction();
        try {

            $data = AutomaticApprove::find($request->id);

            if (! $data) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Data Tidak Ditemukan',
                ]);
            }

            $data->update([
                'nama_template' => $request->template_name,
                'id_template'   => $request->template_id,
                'id_kategori'   => $request->category_id,
                'interval'      => $request->interval,
                'updated_at'    => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_by'    => $this->karyawan,
            ]);

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Data Automatic Approve Berhasil Diupdate',
            ]);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi Kesalahan Sistem',
                'error'   => $th->getMessage(),
            ]);
        }
    }

    public function delete(Request $request)
    {
        DB::beginTransaction();
        try {

            $data = AutomaticApprove::find($request->id);

            if (! $data) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Data Tidak Ditemukan',
                ]);
            }

            $data->delete();

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Data Automatic Approve Berhasil Dihapus',
            ]);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi Kesalahan Sistem',
                'error'   => $th->getMessage(),
            ]);
        }
    }

    public function getMasterKategori(Request $request)
    {

        $data = DB::table('master_kategori')
            ->where('is_active', true);

        return response()->json(['status' => 'success', 'data' => $data->get()]);

    }

    public function getTemplateStp(Request $request)
    {
        $data = DB::table('template_stp')
            ->leftJoin('automatic_approve', 'template_stp.id', '=', 'automatic_approve.id_template')
            ->where('template_stp.category_id', $request->category_id)
            ->where('template_stp.is_active', true)
            ->select(
                'template_stp.id',
                'template_stp.name',
                DB::raw('CASE WHEN automatic_approve.id IS NOT NULL THEN true ELSE false END as is_used')
            )
            ->get();

        return response()->json(['status' => 'success', 'data' => $data]);
    }
}
