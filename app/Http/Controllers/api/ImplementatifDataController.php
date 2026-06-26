<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmbedSpreadsheet;
use Yajra\DataTables\Facades\DataTables;
use DB;

class ImplementatifDataController extends Controller
{
    public function index(Request $request)
    {
        $data = EmbedSpreadsheet::query()->where('type', 'link')
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
                    
                    if ($extension === 'pdf') {
                        $pdfBaseName = pathinfo($originalName, PATHINFO_FILENAME);
                        $pdfFolder = str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9\s_-]/', '', $pdfBaseName));
                        $pdfFolder = rtrim($pdfFolder, '_-');
                        if (empty($pdfFolder)) {
                            $pdfFolder = 'pdf_' . time();
                        }
                        
                        $pdfSubdir = $destinationDir . '/' . $pdfFolder;
                        if (!file_exists($pdfSubdir)) {
                            mkdir($pdfSubdir, 0777, true);
                        }
                        
                        $tempPdfName = time() . '_' . str_replace(' ', '_', $originalName);
                        $file->move($pdfSubdir, $tempPdfName);
                        $pdfPath = $pdfSubdir . '/' . $tempPdfName;
                        
                        $outputPrefix = $pdfSubdir . '/Page';
                        $command = "pdftoppm -jpeg -r 150 " . escapeshellarg($pdfPath) . " " . escapeshellarg($outputPrefix);
                        exec($command, $output, $returnVar);
                        
                        if (file_exists($pdfPath)) {
                            unlink($pdfPath);
                        }
                        
                        $pattern = $pdfSubdir . '/Page-*.jpg';
                        $generatedFiles = glob($pattern);
                        
                        if (empty($generatedFiles)) {
                            throw new \Exception("Gagal mengonversi PDF ke gambar.");
                        }
                        
                        natsort($generatedFiles);
                        
                        foreach ($generatedFiles as $gFile) {
                            if (preg_match('/Page-(\d+)\.jpg$/', $gFile, $matches)) {
                                $pageNum = $matches[1];
                                $newFilename = 'Page' . $pageNum . '.jpg';
                                $newFilePath = $pdfSubdir . '/' . $newFilename;
                                rename($gFile, $newFilePath);
                            }
                        }
                        
                        $data['source'] = 'uploads/akreditasi/dokumen-implementatif/' . $formFolder . '/' . $pdfFolder;
                    } else {
                        $filename = time() . '_' . str_replace(' ', '_', $originalName);
                        $file->move($destinationDir, $filename);
                        
                        $data['source'] = 'uploads/akreditasi/dokumen-implementatif/' . $formFolder . '/' . $filename;
                    }
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
