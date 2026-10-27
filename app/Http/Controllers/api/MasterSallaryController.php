<?php

namespace App\Http\Controllers\api;

use App\Models\MasterSallary;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use App\Services\MasterSallaryNikSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;




class MasterSallaryController extends Controller
{
    public function index()
    {
        $data = MasterSallary::select(
                'master_sallary.*',
                'master_divisi.nama_divisi',
                DB::raw('(master_sallary.gaji_pokok + master_sallary.tunjangan_kerja) as total_gaji')
            )
            ->leftJoin('master_karyawan', function ($join) {
                $join->on('master_sallary.id_karyawan', '=', 'master_karyawan.id')
                    ->where('master_karyawan.is_active', true);
            })
            ->leftJoin('master_divisi', function ($join) {
                $join->on('master_karyawan.id_department', '=', 'master_divisi.id')
                    ->where('master_divisi.is_active', true);
            })
            ->where('master_sallary.is_active', true)
            ->whereNotNull('master_sallary.id_karyawan')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('master_karyawan as mk_current')
                    ->whereColumn('mk_current.id', 'master_sallary.id_karyawan')
                    ->where('mk_current.is_active', true);
            });

        return Datatables::of($data)
            ->filterColumn('nama_divisi', function($query, $keyword) {
                $query->where('master_divisi.nama_divisi', 'like', "%{$keyword}%");
            })
            ->filterColumn('total_gaji', function($query, $keyword) {
                $query->whereRaw("(master_sallary.gaji_pokok + master_sallary.tunjangan_kerja) like ?", ["%{$keyword}%"]);
            })
            ->orderColumn('total_gaji', function ($query, $order) {
                $query->orderByRaw("(master_sallary.gaji_pokok + master_sallary.tunjangan_kerja) {$order}");
            })
            ->make(true);
    }

    public function getKaryawan()
    {
        $existingIds = MasterSallary::where('is_active', true)
            ->whereNotNull('id_karyawan')
            ->pluck('id_karyawan')
            ->all();

        $karyawan = MasterKaryawan::where('is_active', true)
            ->when(!empty($existingIds), function ($query) use ($existingIds) {
                $query->whereNotIn('id', $existingIds);
            })
            ->select('id', 'nama_lengkap')
            ->orderBy('nama_lengkap')
            ->get()
            ->map(function ($row) {
                return [
                    'id_karyawan' => $row->id,
                    'nama_lengkap' => $row->nama_lengkap,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $karyawan,
            'message' => 'Available karyawan data retrieved successfully',
        ], 201);
    }

    public function delete(Request $request){
        try {
            $masterSallary = MasterSallary::findOrFail($request->id);
            $masterSallary->is_active = false;
            $masterSallary->deleted_at = DATE('Y-m-d H:i:s');
            $masterSallary->deleted_by = $this->karyawan;
            $masterSallary->save();

            return response()->json([
                'success' => true,
                'message' => 'Master Sallary data deleted successfully'
            ], 200);
            } catch (\Throwable $th){
                return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try{
            if (strlen(str_replace(['Rp', '.', ','], '', $request->gaji_pokok)) > 10 || strlen(str_replace(['Rp', '.', ','], '', $request->tunjangan_kerja)) > 10) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nominal Terlalu Besar',
                ], 401);
            }

            $existingKaryawan = MasterSallary::where('is_active', true)
                ->whereNotNull('id_karyawan')
                ->pluck('id_karyawan')
                ->toArray();

            $masterSallary = new MasterSallary();
            $masterSallary->created_by = $this->karyawan;
            $masterSallary->created_at = DATE('Y-m-d H:i:s');
            $masterSallary->gaji_pokok = str_replace(['Rp', '.', ','], '', $request->gaji_pokok);
            $masterSallary->bulan_efektif = preg_match('/^(\d{4}-\d{2})/', (string) $request->bulan_efektif, $m)
                ? $m[1]
                : $request->bulan_efektif;
            $masterSallary->tunjangan_kerja = str_replace(['Rp', '.', ','], '', $request->tunjangan_kerja);

            $payrollError = $this->assignPayrollSettings($masterSallary, $request);
            if ($payrollError !== null) {
                return response()->json([
                    'success' => false,
                    'message' => $payrollError,
                ], 422);
            }

            if ($request->id && in_array((int) $request->id_karyawan, array_map('intval', $existingKaryawan), true)) {
                $oldMasterSallary = MasterSallary::findorFail($request->id);
                $oldMasterSallary->updated_at = DATE('Y-m-d H:i:s');
                $oldMasterSallary->updated_by = $this->karyawan;
                $oldMasterSallary->is_active = false;
                $oldMasterSallary->save();

                $masterSallary->previous_id = $request->id;
                $masterSallary->karyawan = $oldMasterSallary->karyawan;
                $masterSallary->nik_karyawan = $oldMasterSallary->nik_karyawan;
                $masterSallary->id_karyawan = $oldMasterSallary->id_karyawan;

                $message = 'Master Sallary data updated successfully';
            } else {
                $karyawan = MasterKaryawan::findOrFail($request->id_karyawan);
                $masterSallary->id_karyawan = $karyawan->id;
                $masterSallary->nik_karyawan = $karyawan->nik_karyawan;
                $masterSallary->karyawan = $karyawan->nama_lengkap;

                $message = 'Master Sallary data inserted successfully';
            }

            $masterSallary->save();

            return response()->json([
                'success' => true,
                'message' => $message,
            ], 201);
        } catch (\Throwable $th){
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    private function formatBulanEfektif($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '-';
        }

        if (preg_match('/^(\d{4})-(\d{2})(?:-\d{2})?(?:\s.*)?$/', $value, $match)) {
            $year = (int) $match[1];
            $month = (int) $match[2];
            if ($month >= 1 && $month <= 12) {
                return Carbon::createFromDate($year, $month, 1)->locale('id')->translatedFormat('F Y');
            }
        }

        if (preg_match('/^\d{1,2}$/', $value)) {
            $month = (int) $value;
            if ($month >= 1 && $month <= 12) {
                return Carbon::createFromDate(2000, $month, 1)->locale('id')->translatedFormat('F');
            }
        }

        return $value;
    }

    private function labelMetodePembayaran($value): string
    {
        $metode = strtolower(trim((string) $value));
        if ($metode === 'tunai') {
            return 'Tunai';
        }
        if ($metode === 'transfer') {
            return 'Transfer';
        }

        return $metode !== '' ? (string) $value : '-';
    }

    private function formatExportDateTime($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '-';
        }

        try {
            return Carbon::parse($value)->format('d/m/Y H:i:s');
        } catch (\Throwable $e) {
            return $value;
        }
    }

    private function assignPayrollSettings(MasterSallary $masterSallary, Request $request): ?string
    {
        $thr = trim((string) $request->jadwal_pembayaran_thr);
        if ($thr === '') {
            return 'Jadwal pembayaran THR wajib diisi.';
        }
        if (mb_strlen($thr) > 100) {
            return 'Jadwal pembayaran THR maksimal 100 karakter.';
        }

        $metode = strtolower(trim((string) $request->metode_pembayaran_gaji));
        if (!in_array($metode, ['tunai', 'transfer'], true)) {
            return 'Metode pembayaran gaji harus Tunai atau Transfer.';
        }

        $hariKerja = trim((string) $request->jumlah_hari_kerja);
        if ($hariKerja === '' || !preg_match('/^\d+$/', $hariKerja)) {
            return 'Jumlah hari kerja harus berupa angka.';
        }
        $hariKerja = (int) $hariKerja;
        if ($hariKerja < 1 || $hariKerja > 31) {
            return 'Jumlah hari kerja harus antara 1 dan 31.';
        }

        $jadwal = trim((string) $request->jadwal_penggajian);
        if ($jadwal === '' || !preg_match('/^\d+$/', $jadwal)) {
            return 'Jadwal penggajian harus berupa angka.';
        }
        $jadwal = (int) $jadwal;
        if ($jadwal < 0 || $jadwal > 99) {
            return 'Jadwal penggajian harus antara 0 dan 99.';
        }

        $masterSallary->jadwal_pembayaran_thr = $thr;
        $masterSallary->metode_pembayaran_gaji = $metode;
        $masterSallary->jumlah_hari_kerja = $hariKerja;
        $masterSallary->jadwal_penggajian = $jadwal;

        return null;
    }

    public function getHistory(Request $request)
    {
        $query = MasterSallary::query();

        if (!empty($request->id_karyawan)) {
            $query->where('id_karyawan', $request->id_karyawan);
        } elseif (!empty($request->nik_karyawan)) {
            $query->where('nik_karyawan', $request->nik_karyawan);
        } elseif (!empty($request->karyawan)) {
            $query->where('karyawan', $request->karyawan);
        }

        $data = $query->orderBy('created_at', 'desc');

        return Datatables::of($data)->make(true);
    }

    public function exportExcel(Request $request)
    {
        if (strtoupper(trim((string) ($this->grade ?? ''))) !== 'MANAGER') {
            return response()->json([
                'message' => 'Export hanya dapat dilakukan oleh user dengan grade MANAGER',
            ], 403);
        }

        Carbon::setLocale('id');

        $data = MasterSallary::select(
                'master_sallary.*',
                'master_divisi.nama_divisi',
                DB::raw('(master_sallary.gaji_pokok + master_sallary.tunjangan_kerja) as total_gaji')
            )
            ->leftJoin('master_karyawan', function ($join) {
                $join->on('master_sallary.id_karyawan', '=', 'master_karyawan.id')
                    ->where('master_karyawan.is_active', true);
            })
            ->leftJoin('master_divisi', function ($join) {
                $join->on('master_karyawan.id_department', '=', 'master_divisi.id')
                    ->where('master_divisi.is_active', true);
            })
            ->where('master_sallary.is_active', true)
            ->whereNotNull('master_sallary.id_karyawan')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('master_karyawan as mk_current')
                    ->whereColumn('mk_current.id', 'master_sallary.id_karyawan')
                    ->where('mk_current.is_active', true);
            })
            ->orderBy('master_sallary.karyawan', 'asc')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Master Salary');

        $sheet->mergeCells('A1:N1');
        $sheet->setCellValue('A1', 'Master Salary');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headers = [
            'No',
            'NIK',
            'Karyawan',
            'Divisi',
            'Gaji Pokok',
            'Tunjangan Kerja',
            'Total Gaji',
            'Jadwal Pembayaran THR',
            'Metode Pembayaran Gaji',
            'Jumlah Hari Kerja',
            'Jadwal Penggajian',
            'Bulan Efektif',
            'Created By',
            'Created At',
        ];
        $sheet->fromArray($headers, null, 'A3');
        $sheet->getStyle('A3:N3')->getFont()->setBold(true);
        $sheet->getStyle('A3:N3')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFCCCCCC');

        $rowIdx = 4;
        $no = 1;

        foreach ($data as $row) {
            $bulanEfektif = $this->formatBulanEfektif($row->bulan_efektif);
            $createdAt = $this->formatExportDateTime($row->created_at);
            $jadwalThr = trim((string) $row->jadwal_pembayaran_thr);
            $jumlahHariKerja = $row->jumlah_hari_kerja;
            $jadwalPenggajian = $row->jadwal_penggajian;

            $sheet->setCellValue("A{$rowIdx}", $no++);
            $sheet->setCellValueExplicit("B{$rowIdx}", (string) ($row->nik_karyawan ?? '-'), DataType::TYPE_STRING);
            $sheet->setCellValue("C{$rowIdx}", $row->karyawan ?? '-');
            $sheet->setCellValue("D{$rowIdx}", $row->nama_divisi ?? '-');
            $sheet->setCellValue("E{$rowIdx}", (float) ($row->gaji_pokok ?? 0));
            $sheet->setCellValue("F{$rowIdx}", (float) ($row->tunjangan_kerja ?? 0));
            $sheet->setCellValue("G{$rowIdx}", (float) ($row->total_gaji ?? 0));
            $sheet->setCellValue("H{$rowIdx}", $jadwalThr !== '' ? $jadwalThr : '-');
            $sheet->setCellValue("I{$rowIdx}", $this->labelMetodePembayaran($row->metode_pembayaran_gaji));
            $sheet->setCellValue("J{$rowIdx}", ($jumlahHariKerja === null || $jumlahHariKerja === '') ? '-' : (int) $jumlahHariKerja);
            $sheet->setCellValue("K{$rowIdx}", ($jadwalPenggajian === null || $jadwalPenggajian === '') ? '-' : 'H+' . (int) $jadwalPenggajian);
            $sheet->setCellValue("L{$rowIdx}", $bulanEfektif);
            $sheet->setCellValue("M{$rowIdx}", $row->created_by ?? '-');
            $sheet->setCellValue("N{$rowIdx}", $createdAt);

            foreach (['E', 'F', 'G'] as $col) {
                $sheet->getStyle("{$col}{$rowIdx}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');
            }

            $rowIdx++;
        }

        foreach (range('A', 'N') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $lastRow = max($rowIdx - 1, 3);
        $sheet->getStyle("A3:N{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]);

        $fileName = 'Master_Salary_' . date('Y-m-d_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    /**
     * Rapikan duplikat master_sallary aktif akibat perubahan NIK (one-time / manual).
     */
    public function reconcile(Request $request)
    {
        try {
            $karyawan = MasterKaryawan::where('is_active', true)
                ->when($request->id_karyawan, fn ($q) => $q->where('id', $request->id_karyawan))
                ->when($request->nik_karyawan, fn ($q) => $q->where('nik_karyawan', $request->nik_karyawan))
                ->when($request->nama_lengkap, fn ($q) => $q->where('nama_lengkap', $request->nama_lengkap))
                ->firstOrFail();

            MasterSallaryNikSyncService::reconcileDuplicates(
                $karyawan->id,
                $karyawan->nama_lengkap,
                $karyawan->nik_karyawan,
                $this->karyawan
            );

            return response()->json([
                'success' => true,
                'message' => 'Master salary berhasil dirapikan untuk NIK terbaru.',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }
}