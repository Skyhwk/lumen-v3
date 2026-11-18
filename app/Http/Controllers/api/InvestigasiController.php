<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\BeritaAcaraKejadianDisipliner;
use App\Models\MasterKaryawan;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Investigasi Awal (F1) — endpoint khusus modul investigasi.
 * Tidak mencampur logika CRUD BAK di BeritaAcaraKejadianController.
 */
class InvestigasiController extends Controller
{
    /**
     * Opsi multi-select No BAK untuk form Investigasi Awal (F1).
     * Response shape sama persis dengan implementasi sebelumnya di BAK controller.
     */
    public function listForInvestigasi(Request $request)
    {
        try {
            $query = $this->bakOptionsQuery()->whereIn('bak.status', [
                BeritaAcaraKejadianDisipliner::STATUS_DRAFT,
                BeritaAcaraKejadianDisipliner::STATUS_ISSUED,
            ]);

            $periode = (int) $request->input('periode', 0);
            if ($periode >= 2000) {
                $query->whereYear('bak.tanggal_kejadian', $periode);
            }

            $search = trim((string) $request->input('q', ''));
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('bak.nomor_surat', 'like', '%' . $search . '%')
                        ->orWhere('bak.created_by', 'like', '%' . $search . '%')
                        ->orWhere('bak.perihal', 'like', '%' . $search . '%');
                });
            }

            $collection = $query
                ->orderByDesc('bak.tanggal_kejadian')
                ->orderByDesc('bak.id')
                ->limit(500)
                ->get();

            $creatorNames = $collection
                ->map(fn ($row) => trim((string) ($row->created_by ?? '')))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $divisiByCreator = [];
            if (count($creatorNames) > 0) {
                $employees = MasterKaryawan::query()
                    ->from('master_karyawan as mk')
                    ->leftJoin('master_divisi as md', 'mk.id_department', '=', 'md.id')
                    ->where('mk.is_active', true)
                    ->whereIn('mk.nama_lengkap', $creatorNames)
                    ->select('mk.nama_lengkap', 'md.nama_divisi', 'mk.department')
                    ->get();

                foreach ($employees as $emp) {
                    $key = mb_strtolower(trim((string) ($emp->nama_lengkap ?? '')));
                    if ($key === '' || isset($divisiByCreator[$key])) {
                        continue;
                    }
                    $divisi = trim((string) ($emp->nama_divisi ?? ''));
                    if ($divisi === '') {
                        $divisi = trim((string) ($emp->department ?? ''));
                    }
                    $divisiByCreator[$key] = $divisi;
                }
            }

            $rows = $collection
                ->map(function ($row) use ($divisiByCreator) {
                    $dokumentasi = $this->decodeDokumentasiList($row->dokumentasi ?? null);
                    $tanggal = $row->tanggal_kejadian
                        ? Carbon::parse($row->tanggal_kejadian)->format('Y-m-d')
                        : null;
                    $createdBy = trim((string) ($row->created_by ?? ''));
                    $creatorKey = mb_strtolower($createdBy);

                    return [
                        'id' => (int) $row->id,
                        'nomor_surat' => (string) ($row->nomor_surat ?? ''),
                        'created_by' => $createdBy,
                        'created_by_divisi' => $divisiByCreator[$creatorKey] ?? '',
                        'perihal' => trim((string) ($row->perihal ?? '')),
                        'tanggal_kejadian' => $tanggal,
                        'status' => (string) ($row->status ?? ''),
                        'dokumentasi' => $dokumentasi,
                        'jumlah_lampiran' => count($dokumentasi),
                        'has_document' => !empty($row->file_path),
                        'nama_berkas' => (string) ($row->nama_berkas ?? ''),
                        'file_path' => (string) ($row->file_path ?? ''),
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => $rows,
            ], 200);
        } catch (\Throwable $ex) {
            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 500);
        }
    }

    /**
     * Query ringan kolom BAK yang dibutuhkan opsi investigasi saja.
     */
    private function bakOptionsQuery()
    {
        return BeritaAcaraKejadianDisipliner::query()
            ->from('berita_acara_kejadian_disipliner as bak')
            ->where('bak.is_active', true)
            ->select(
                'bak.id',
                'bak.nomor_surat',
                'bak.perihal',
                'bak.tanggal_kejadian',
                'bak.status',
                'bak.dokumentasi',
                'bak.nama_berkas',
                'bak.file_path',
                'bak.created_by'
            );
    }

    private function decodeDokumentasiList($value): array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $decoded = json_decode((string) $value, true);
            $items = is_array($decoded) ? $decoded : [];
        }

        $paths = [];
        foreach ($items as $item) {
            $path = str_replace('\\', '/', trim((string) $item));
            if ($path === '' || strpos($path, '..') !== false) {
                continue;
            }
            $paths[] = $path;
        }

        return array_values(array_unique($paths));
    }
}
