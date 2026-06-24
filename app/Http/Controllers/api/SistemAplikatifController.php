<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmbedSpreadsheet;
use Yajra\DataTables\Facades\DataTables;
use DB;

class SistemAplikatifController extends Controller
{
    public function index(Request $request)
    {
        $data = EmbedSpreadsheet::query()
            ->select([
                'id',
                'nama_formulir',
                'source',
                'url_form',
                'source as Source',
                'url_form as Link Spreadsheet',
                'type',
                'created_by',
                'updated_by',
                'created_at',
                'updated_at',
                'deleted_by',
                'deleted_at'
            ]);
        return DataTables::of($data)->make(true);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $id = $request->id;

            $data = [
                'nama_formulir' => $request->nama_formulir,
                'type'          => $request->type,
                'updated_by'    => $this->karyawan,
            ];

            $source = $request->source ?? $request->url;
            $url_form = $request->url_form ?? $request->url;

            if ($request->type === 'Dokumen') {
                if ($request->hasFile('file')) {
                    $file = $request->file('file');
                    $filename = time() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
                    $destinationPath = base_path('public/uploads/documents');
                    if (!file_exists($destinationPath)) {
                        mkdir($destinationPath, 0777, true);
                    }
                    $file->move($destinationPath, $filename);
                    
                    $data['source'] = $filename;
                } else if ($id) {
                    $old = EmbedSpreadsheet::find($id);
                    if ($old) {
                        $data['source'] = $old->source;
                        $data['url_form'] = $old->url_form;
                    }
                }
                if ($request->has('url_form')) {
                    $data['url_form'] = $request->url_form;
                }
            } else {
                $data['source'] = $source;
                $data['url_form'] = $url_form;
            }

            if ($id == null || $id == '') {
                $data['created_by'] = $this->karyawan;
            }

            $spreadsheet = EmbedSpreadsheet::updateOrCreate(
                ['id' => $id],
                $data
            );

            DB::commit();
            return response()->json([
                'message' => 'Data formulir berhasil disimpan.',
                'data' => $spreadsheet
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function delete(Request $request)
    {
        DB::beginTransaction();
        try {
            $id = $request->id;
            if (!$id) {
                return response()->json(['message' => 'ID tidak ditemukan.'], 400);
            }

            $spreadsheet = EmbedSpreadsheet::find($id);
            if (!$spreadsheet) {
                return response()->json(['message' => 'Data tidak ditemukan.'], 404);
            }

            $spreadsheet->deleted_by = $this->karyawan;
            $spreadsheet->save();
            $spreadsheet->delete();

            DB::commit();
            return response()->json(['message' => 'Data formulir berhasil dihapus.'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
