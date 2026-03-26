<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\LayoutCertificate;
use App\Services\SendEmail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Repository;
use Yajra\Datatables\Datatables;

class LayoutCertificateController extends Controller
{
    public function index()
    {
        $data = LayoutCertificate::all();

        return Datatables::of($data)->make(true);
    }

    public function getTemplate()
    {
        $data = DB::table('template_background')
            ->select('id', 'nama_template')
            ->where('is_active', 1)
            ->get();

        return response()->json(['data' => $data]);
    }

    public function store(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'id_template' => 'required',
            'nama_file'   => 'required|string|max:255',
            'details'     => 'required|string',
        ])->validate();

        try {
            DB::beginTransaction();

            $layout              = new LayoutCertificate();
            $layout->id_template = $validated['id_template'];
            $layout->nama_file   = $validated['nama_file'];
            $layout->code  = $validated['details'];
            $layout->created_by  = $this->karyawan;
            $layout->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Layout certificate berhasil disimpan',
                'data'    => $layout,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan layout certificate',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'id'          => 'required|exists:layout_certificates,id',
            'id_template' => 'required',
            'nama_file'   => 'required|string|max:255',
            'details'     => 'required|string',
        ])->validate();

        try {
            DB::beginTransaction();

            $layout = LayoutCertificate::findOrFail($validated['id']);

            $oldNamaBlade = strtolower(str_replace(' ', '_', $layout->nama_blade)) . '.blade.php';
            $oldPathBlade = resource_path('views/sertifikat-templates/' . $oldNamaBlade);

            $layout->id_template = $validated['id_template'];
            $layout->nama_file   = $validated['nama_file'];
            $layout->code        = $validated['details'];
            $layout->updated_by  = $this->karyawan;
            $layout->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Layout certificate berhasil diupdate',
                'data'    => $layout,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate layout certificate',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'id' => 'required|exists:layout_certificates,id',
        ])->validate();

        try {
            DB::beginTransaction();

            $layout = LayoutCertificate::findOrFail($validated['id']);

            $namaFileBlade = strtolower(str_replace(' ', '_', $layout->nama_blade)) . '.blade.php';
            $pathBlade     = resource_path('views/sertifikat-templates/' . $namaFileBlade);

            if (file_exists($pathBlade)) {
                unlink($pathBlade);
            }

            $layout->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Layout certificate berhasil dihapus',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus layout certificate',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

   
}
