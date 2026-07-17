<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmbedSpreadsheet;
use Yajra\DataTables\Facades\DataTables;
use DB;

class FormulirController extends Controller
{
    public function index(Request $request)
    {
        $data = EmbedSpreadsheet::query()
            ->select([
                'id',
                'nama_formulir',
                'source',
                'url_form',
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
            
            $old = null;
            if ($id) {
                $old = EmbedSpreadsheet::find($id);
            }

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
                    
                    $namaFormulir = $request->nama_formulir;
                    $formFolder = str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9\s_-]/', '', $namaFormulir));
                    if (empty($formFolder)) {
                        $formFolder = 'formulir_' . time();
                    }
                    
                    $destinationDir = base_path('public/uploads/akreditasi/dokumen-implementatif/' . $formFolder);
                    if (!file_exists($destinationDir)) {
                        mkdir($destinationDir, 0777, true);
                    }
                    
                    $extension = strtolower($file->getClientOriginalExtension());
                    $originalName = $file->getClientOriginalName();
                    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];

                    if (!in_array($extension, $allowedExtensions, true)) {
                        throw new \Exception('Format file tidak didukung. Gunakan PDF, JPG, JPEG, atau PNG.');
                    }

                    $nameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);
                    $cleanName = str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9\s_-]/', '', $nameWithoutExt));
                    $cleanName = rtrim($cleanName, '_-');
                    if (empty($cleanName)) {
                        $cleanName = 'document';
                    }
                    $filename = $cleanName . '_' . time() . '.' . $extension;
                    $file->move($destinationDir, $filename);

                    $newFile = 'uploads/akreditasi/dokumen-implementatif/' . $formFolder . '/' . $filename;

                    $newUploaders = [
                        [
                            'file' => $newFile,
                            'uploader' => $this->karyawan ?: 'System',
                            'uploaded_at' => date('Y-m-d H:i:s')
                        ]
                    ];

                    if ($old && !empty($old->source)) {
                        $existingFiles = is_array($old->source) ? $old->source : [$old->source];
                        $savedFiles = array_merge($existingFiles, [$newFile]);
                        $data['source'] = json_encode($savedFiles);
                    } else {
                        $data['source'] = $newFile;
                    }

                    $existingUploaders = ($old && !empty($old->uploader)) ? (is_array($old->uploader) ? $old->uploader : json_decode($old->uploader, true)) : [];
                    if (!is_array($existingUploaders)) {
                        $existingUploaders = [];
                    }
                    $allUploaders = array_merge($existingUploaders, $newUploaders);
                    $data['uploader'] = json_encode($allUploaders);
                } else if ($id) {
                    if ($old) {
                        $data['source'] = $old->source;
                        $data['url_form'] = $old->url_form;
                    }
                }
                if ($request->has('url_form')) {
                    $data['url_form'] = $request->url_form;
                }
            } else {
                $source = trim($source ?? '');
                $url_form = trim($url_form ?? '');

                if ($url_form !== '' && $source === '') {
                    return response()->json(['message' => 'Link Spreadsheet wajib diisi jika Link Form diisi.'], 422);
                }

                if ($url_form === '' && $source === '') {
                    return response()->json(['message' => 'Link Spreadsheet atau Link Form harus diisi.'], 422);
                }

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

    public function previewDocument(Request $request)
    {
        try {
            $path = str_replace('\\', '/', trim((string) $request->path));
            $path = ltrim($path, '/');

            if ($path === '' || strpos($path, '..') !== false) {
                return response()->json(['message' => 'Path file tidak valid.'], 422);
            }

            if (!preg_match('#^uploads/akreditasi/dokumen-implementatif/#', $path)) {
                return response()->json(['message' => 'Path file tidak valid.'], 403);
            }

            $fullPath = public_path($path);
            if (!file_exists($fullPath) || !is_file($fullPath)) {
                return response()->json(['message' => 'File tidak ditemukan.'], 404);
            }

            $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            $mimeMap = [
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
            ];

            if (!isset($mimeMap[$extension])) {
                return response()->json(['message' => 'Format file tidak didukung untuk preview.'], 422);
            }

            return response()->json([
                'message' => 'OK',
                'data' => base64_encode(file_get_contents($fullPath)),
                'mime' => $mimeMap[$extension],
                'filename' => basename($fullPath),
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function download($filename)
    {
        $filePath = public_path('uploads/documents/' . $filename);

        if (!file_exists($filePath)) {
            abort(404);
        }

        return response()->download($filePath);
    }
}
