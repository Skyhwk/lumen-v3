<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

// use Datatables;
use Carbon\Carbon;

Carbon::setLocale('id');

use App\Models\DFUS;
use App\Models\DFUSKeterangan;
use App\Models\KontakPelangganBlacklist;
use App\Models\LogWebphone;
use App\Models\LogWebphoneBackup;
use App\Models\OrderHeader;
use App\Models\MasterPelanggan;
use App\Models\MasterKaryawan;
use App\Models\MasterPelangganBlacklist;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Yajra\DataTables\DataTables as DataTables;
use Illuminate\Support\Facades\DB;

class OpenLogWebphoneController extends Controller
{
    public function execute(Request $request)
    {
        $data = MasterPelanggan::with('kontak_pelanggan')
            ->where('id_pelanggan', $request->value)
            ->where('is_active', true)
            ->first(); // ⬅️ WAJIB

        $numbers = [];

        if ($data && $data->kontak_pelanggan) {
            $numbers = $data->kontak_pelanggan
                ->pluck('no_tlp_perusahaan')
                ->filter()
                ->values()
                ->toArray();
        }


        $logIds = LogWebphone::whereIn('number', $numbers)
            ->where('created_at', '>=', Carbon::now()->subMonths(2))
            ->pluck('id')
            ->toArray();

        collect($logIds)->chunk(1000)->each(function ($chunk) {
            DB::transaction(function () use ($chunk) {
                $logs = LogWebphone::whereIn('id', $chunk->toArray())->get();

                if ($logs->isNotEmpty()) {
                    LogWebphoneBackup::insert(
                        $logs->map(fn($log) => self::prepareBackupData($log, ['created_at']))->toArray()
                    );

                    LogWebphone::whereIn('id', $chunk->toArray())->delete();
                }
            });
        });


        return response()->json([
            'message' => 'Success Delete Webphone Log',
        ], 200);
    }

    public function getPelanggan(Request $request)
    {
        $data = MasterPelanggan::where('id_pelanggan', 'Like', $request->search . '%')
            ->select('id_pelanggan', 'nama_pelanggan')
            ->where('is_active', true)
            ->limit(50)
            ->get();

        return response()->json($data);
    }

    private static function prepareBackupData($model, array $dateFields): array
    {
        $data = $model->toArray();
        unset($data['id']);

        foreach ($dateFields as $field) {
            if (!empty($data[$field])) {
                $data[$field] = Carbon::parse($data[$field])
                    ->format('Y-m-d H:i:s');
            }
        }

        return $data;
    }
}
