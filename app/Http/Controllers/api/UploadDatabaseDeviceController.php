<?php

namespace App\Http\Controllers\api;

use App\Models\DatabaseDevice;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;


class UploadDatabaseDeviceController extends Controller
{
    public function index()
    {
        $data = DatabaseDevice::where('is_active', true);

        return Datatables::of($data)
            ->filterColumn('filename', function ($query, $keyword) {
                $query->where('filename', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->where('created_by', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('created_at', function ($query, $keyword) {
                $query->where('created_at', 'like', '%' . $keyword . '%');
            })
            ->make(true);
    }

    public function getDataDatabase(Request $request)
    {
        try {
            $data = DatabaseDevice::where('id', $request->id_database)->first();

            if (!$data) {
                return response()->json(['message' => 'Data database tidak ditemukan'], 404);
            }

            $cek = explode('_', $data->filename);
            if ($cek[0] == 'sound') {
                $tableName = 'sound_meter';
            } elseif ($cek[0] == 'flow') {
                $tableName = 'flow_meter';
            } else {
                return response()->json(['message' => 'Data database tidak ditemukan'], 404);
            }

            $dbFile = storage_path('app/database_devices/' . $data->filename);

            if (!file_exists($dbFile)) {
                return response()->json(['message' => 'File database tidak ditemukan'], 404);
            }

            $pdo = new \PDO("sqlite:" . $dbFile);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->query("SELECT * FROM {$tableName}");
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $columns = [];
            for ($i = 0; $i < $stmt->columnCount(); $i++) {
                $meta = $stmt->getColumnMeta($i);
                $columns[] = $meta['name'];
            }

            return response()->json([
                'data' => $results,
                'columns' => $columns
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil data database.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function storeDatabase(Request $request)
    {
        DB::beginTransaction();
        try {
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();
            $tempPath = $file->getPathname();

            $pdo = new \PDO("sqlite:$tempPath");
            $query = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('sound_meter', 'flow_meter')");
            $result = $query->fetch(\PDO::FETCH_ASSOC);

            if ($result && isset($result['name'])) {
                if ($result['name'] === 'sound_meter') {
                    $newFileName = 'sound_meter_' . Carbon::now()->format('Y-m-d H:i:s') . '.' . $extension;
                } elseif ($result['name'] === 'flow_meter') {
                    $newFileName = 'flow_meter_' . Carbon::now()->format('Y-m-d H:i:s') . '.' . $extension;
                } else {
                    return response()->json([
                        'message' => 'File database tidak mengandung tabel sound_meter atau flow_meter.',
                    ], 422);
                }
            } else {
                return response()->json([
                    'message' => 'File database tidak valid.',
                ], 422);
            }

            $storagePath = storage_path("app/database_devices");
            if (!is_dir($storagePath)) {
                mkdir($storagePath, 0777, true);
                chmod($storagePath, 0777);
            }

            $file->move($storagePath, $newFileName);
            chmod($storagePath . '/' . $newFileName, 0777);
            $data = DatabaseDevice::create([
                'filename' => $newFileName,
                'created_by' => $this->karyawan,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s')
            ]);

            if ($data) {
                DB::commit();
                return response()->json([
                    'message' => 'File berhasil diunggah dan disimpan.',
                    'status' => 'success'
                ], 200);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'File gagal diunggah dan disimpan.',
                    'status' => 'failed'
                ], 500);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengunggah file.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteDatabase(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = DatabaseDevice::where('id', $request->id)->first();

            if (!$data) {
                return response()->json([
                    'message' => 'Database tidak ditemukan',
                    'status' => 'error'
                ], 404);
            } else {
                $data->update([
                    'is_active' => false,
                    'deleted_by' => $this->karyawan,
                    'deleted_at' => Carbon::now()->format('Y-m-d H:i:s')
                ]);
            }

            DB::commit();
            return response()->json([
                'message' => 'Database berhasil dihapus',
                'status' => 'success'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan saat menghapus database.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}