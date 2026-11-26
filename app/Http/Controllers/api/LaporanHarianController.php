<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\LaporanHarian;
use App\Models\LaporanSummary;
use App\Models\LaporanSummarySumber;
use App\Models\MasterKaryawan;
use App\Services\LaporanHarianSummaryAiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanHarianController extends Controller
{
    private const JENIS = ['harian', 'mingguan', 'bulanan'];

    private const MONTHS = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    private const MONTHS_SHORT = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Agu',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des',
    ];

    private const DAYS = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    public function today(Request $request)
    {
        if (!$this->user_id) {
            return response()->json(['message' => 'User tidak dikenali'], 401);
        }

        $jenis = $this->normalizeJenis($request->input('jenis'));
        if ($denied = $this->denyJenis($jenis)) {
            return $denied;
        }

        $start = $this->periodStart($jenis);
        $row = $this->rowFor($jenis, $start);

        return response()->json([
            'can_period' => $this->canPeriodReports(),
            'jenis' => $jenis,
            'data' => $this->present($row, $jenis, $start, true),
            'history' => $this->historyRows($jenis),
        ]);
    }

    public function save(Request $request)
    {
        if (!$this->user_id) {
            return response()->json(['message' => 'User tidak dikenali'], 401);
        }

        $jenis = $this->normalizeJenis($request->input('jenis'));
        if ($denied = $this->denyJenis($jenis)) {
            return $denied;
        }

        $start = $this->periodStart($jenis);
        $isi = $this->cleanHtml((string) $request->input('isi', ''));
        $now = Carbon::now();
        $row = $this->rowFor($jenis, $start);

        if (!$row) {
            $row = new LaporanHarian();
            $row->no_dokumen = $this->makeDocumentNumber();
            $row->karyawan_id = (int) $this->user_id;
            $row->jenis = $jenis;
            $row->tanggal = $start;
            $row->created_at = $now;
        }

        $row->isi = $isi;
        $row->updated_at = $now;
        $row->save();

        return response()->json([
            'message' => 'Laporan tersimpan',
            'data' => $this->present($row, $jenis, $start, true),
        ]);
    }

    public function show(Request $request)
    {
        if (!$this->user_id) {
            return response()->json(['message' => 'User tidak dikenali'], 401);
        }

        $row = LaporanHarian::query()
            ->where('id', (int) $request->input('id'))
            ->where('karyawan_id', (int) $this->user_id)
            ->first();

        if (!$row) {
            return response()->json(['message' => 'Laporan tidak ditemukan'], 404);
        }

        $jenis = $this->normalizeJenis($row->jenis ?? 'harian');
        if ($denied = $this->denyJenis($jenis)) {
            return $denied;
        }

        $editable = $this->isCurrentPeriod($jenis, (string) $row->tanggal);

        return response()->json([
            'data' => $this->present($row, $jenis, (string) $row->tanggal, $editable),
        ]);
    }

    public function reminder()
    {
        if (!$this->user_id) {
            return response()->json(['needs_reminder' => false]);
        }

        $now = Carbon::now('Asia/Jakarta');
        $due = $now->copy()->setTime(16, 30, 0);
        $needs = $now->greaterThanOrEqualTo($due) && !$this->hasFilledToday();

        return response()->json([
            'needs_reminder' => $needs,
            'tanggal' => $now->toDateString(),
        ]);
    }

    public function summarySources(Request $request)
    {
        if (!$this->user_id) {
            return response()->json(['message' => 'User tidak dikenali'], 401);
        }
        if ($denied = $this->denySummary()) {
            return $denied;
        }

        $jenis = $this->summaryJenis($request->input('jenis'));
        $anchor = $this->anchorDate($request->input('tanggal'));
        $start = $this->periodStartFrom($jenis, $anchor);
        $summaryRow = $this->summaryFor($jenis, $start);

        return response()->json([
            'jenis' => $jenis,
            'tanggal' => $start,
            'periode_label' => $this->periodLabel($jenis, $start),
            'rows' => $this->publicSourceRows($this->sourceRows($jenis, $anchor), $summaryRow, $jenis),
            'summary' => $this->presentSummary($summaryRow),
        ]);
    }

    public function summarize(Request $request)
    {
        if (!$this->user_id) {
            return response()->json(['message' => 'User tidak dikenali'], 401);
        }
        if ($denied = $this->denySummary()) {
            return $denied;
        }

        $jenis = $this->summaryJenis($request->input('jenis'));
        $anchor = $this->anchorDate($request->input('tanggal'));
        $start = $this->periodStartFrom($jenis, $anchor);
        $periodeLabel = $this->periodLabel($jenis, $start);
        $blocks = array_values(array_filter($this->sourceRows($jenis, $anchor), function ($row) {
            return $row['sumber_id'] && $this->hasText($row['isi']);
        }));

        if (!$blocks) {
            return response()->json([
                'message' => 'Belum ada laporan yang bisa diringkas pada periode ini.',
            ], 422);
        }

        $generated = app(LaporanHarianSummaryAiService::class)->generate(
            $jenis,
            $periodeLabel,
            $blocks,
            [
                'karyawan_id' => (int) $this->user_id,
                'tanggal' => $start,
            ]
        );
        $summaryHtml = trim((string) ($generated['html'] ?? ''));

        if ($summaryHtml === '') {
            return response()->json([
                'message' => 'Layanan AI tidak mengembalikan ringkasan laporan. Silakan coba lagi.',
            ], 502);
        }

        $now = Carbon::now();
        $summary = DB::transaction(function () use ($jenis, $start, $blocks, $summaryHtml, $now) {
            $row = $this->summaryFor($jenis, $start);
            if (!$row) {
                $row = new LaporanSummary();
                $row->no_dokumen = $this->makeSummaryDocumentNumber();
                $row->karyawan_id = (int) $this->user_id;
                $row->jenis = $jenis;
                $row->tanggal = $start;
                $row->created_at = $now;
            }

            $row->isi = $summaryHtml;
            $row->updated_at = $now;
            $row->save();

            LaporanSummarySumber::query()->where('summary_id', $row->id)->delete();
            foreach ($blocks as $block) {
                $source = new LaporanSummarySumber();
                $source->summary_id = $row->id;
                $source->sumber_tipe = $block['sumber_tipe'];
                $source->sumber_id = $block['sumber_id'];
                $source->karyawan_id = $block['karyawan_id'];
                $source->created_at = $now;
                $source->save();
            }

            return $row;
        });

        return response()->json([
            'message' => 'Summary tersimpan',
            'summary' => $this->presentSummary($summary),
            'rows' => $this->publicSourceRows($this->sourceRows($jenis, $anchor), $summary, $jenis),
        ]);
    }

    private function canPeriodReports(): bool
    {
        $grade = strtoupper(trim((string) $this->grade));
        if ($grade === '') {
            return false;
        }

        return (bool) preg_match('/\b(SPV|SUPERVISOR|MANAGER)\b/', $grade);
    }

    private function denyJenis(string $jenis)
    {
        if ($jenis === 'harian' || $this->canPeriodReports()) {
            return null;
        }

        return response()->json([
            'message' => 'Laporan mingguan dan bulanan hanya untuk SPV dan Manager',
        ], 403);
    }

    private function normalizeJenis($value): string
    {
        $jenis = strtolower(trim((string) $value));

        return in_array($jenis, self::JENIS, true) ? $jenis : 'harian';
    }

    private function rowFor(string $jenis, string $tanggal): ?LaporanHarian
    {
        return LaporanHarian::query()
            ->where('karyawan_id', (int) $this->user_id)
            ->where('jenis', $jenis)
            ->where('tanggal', $tanggal)
            ->first();
    }

    private function historyRows(string $jenis)
    {
        return LaporanHarian::query()
            ->where('karyawan_id', (int) $this->user_id)
            ->where('jenis', $jenis)
            ->orderByDesc('tanggal')
            ->limit(30)
            ->get(['id', 'no_dokumen', 'jenis', 'tanggal', 'updated_at'])
            ->map(function ($row) use ($jenis) {
                return [
                    'id' => (int) $row->id,
                    'no_dokumen' => $row->no_dokumen,
                    'jenis' => $jenis,
                    'tanggal' => $row->tanggal,
                    'periode_label' => $this->periodLabel($jenis, (string) $row->tanggal),
                    'updated_at' => $row->updated_at,
                    'editable' => $this->isCurrentPeriod($jenis, (string) $row->tanggal),
                ];
            })
            ->values();
    }

    private function hasFilledToday(): bool
    {
        $row = $this->rowFor('harian', $this->todayDate());

        return $row && $this->hasText($row->isi);
    }

    private function present(?LaporanHarian $row, string $jenis, string $tanggal, bool $editable): array
    {
        return [
            'id' => $row ? (int) $row->id : null,
            'no_dokumen' => $row->no_dokumen ?? null,
            'jenis' => $jenis,
            'tanggal' => $tanggal,
            'periode_label' => $this->periodLabel($jenis, $tanggal),
            'isi' => $row->isi ?? '',
            'updated_at' => $row->updated_at ?? null,
            'editable' => $editable,
        ];
    }

    private function periodStart(string $jenis): string
    {
        $now = Carbon::now('Asia/Jakarta');
        if ($jenis === 'mingguan') {
            return $now->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        }
        if ($jenis === 'bulanan') {
            return $now->copy()->startOfMonth()->toDateString();
        }

        return $now->toDateString();
    }

    private function isCurrentPeriod(string $jenis, string $tanggal): bool
    {
        return $tanggal === $this->periodStart($jenis);
    }

    private function periodLabel(string $jenis, string $tanggal): string
    {
        $date = Carbon::parse($tanggal, 'Asia/Jakarta');
        if ($jenis === 'mingguan') {
            $end = $date->copy()->addDays(6);

            return $date->format('d') . ' ' . self::MONTHS_SHORT[(int) $date->month]
                . ' – ' . $end->format('d') . ' ' . self::MONTHS_SHORT[(int) $end->month]
                . ' ' . $end->format('Y');
        }
        if ($jenis === 'bulanan') {
            return self::MONTHS[(int) $date->month] . ' ' . $date->format('Y');
        }

        $day = self::DAYS[(int) $date->dayOfWeek];

        return $day . ', ' . $date->format('d') . ' ' . self::MONTHS[(int) $date->month] . ' ' . $date->format('Y');
    }

    private function makeDocumentNumber(): string
    {
        do {
            $number = substr(bin2hex(random_bytes(5)), 0, 9);
        } while (LaporanHarian::query()->where('no_dokumen', $number)->exists());

        return $number;
    }

    private function todayDate(): string
    {
        return Carbon::now('Asia/Jakarta')->toDateString();
    }

    private function cleanHtml(string $html): string
    {
        return trim(preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? '');
    }

    private function hasText(?string $html): bool
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace('/\s+/u', '', $text);

        return $text !== '';
    }

    private function denySummary()
    {
        if ($this->canPeriodReports()) {
            return null;
        }

        return response()->json([
            'message' => 'Summary hanya untuk SPV dan Manager',
        ], 403);
    }

    private function summaryJenis($value): string
    {
        $jenis = strtolower(trim((string) $value));

        return in_array($jenis, ['harian', 'mingguan', 'bulanan'], true) ? $jenis : 'harian';
    }

    private function anchorDate($value): Carbon
    {
        $text = trim((string) $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            return Carbon::parse($text, 'Asia/Jakarta')->startOfDay();
        }

        return Carbon::now('Asia/Jakarta')->startOfDay();
    }

    private function periodStartFrom(string $jenis, Carbon $date): string
    {
        if ($jenis === 'mingguan') {
            return $date->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        }
        if ($jenis === 'bulanan') {
            return $date->copy()->startOfMonth()->toDateString();
        }

        return $date->toDateString();
    }

    private function periodEndFrom(string $jenis, Carbon $date): string
    {
        if ($jenis === 'mingguan') {
            return $date->copy()->startOfWeek(Carbon::MONDAY)->addDays(6)->toDateString();
        }
        if ($jenis === 'bulanan') {
            return $date->copy()->endOfMonth()->toDateString();
        }

        return $date->toDateString();
    }

    private function directSubordinates()
    {
        return MasterKaryawan::query()
            ->where('is_active', true)
            ->where('id', '!=', (int) $this->user_id)
            ->whereJsonContains('atasan_langsung', (string) $this->user_id)
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'image', 'jabatan', 'department']);
    }

    private function sourceRows(string $jenis, Carbon $anchor): array
    {
        if ($jenis === 'harian') {
            return $this->subordinateDailyRows($anchor->toDateString());
        }

        $sourceJenis = $jenis === 'mingguan' ? 'harian' : 'mingguan';
        $start = $this->periodStartFrom($jenis, $anchor);
        $end = $this->periodEndFrom($jenis, $anchor);

        return LaporanSummary::query()
            ->where('karyawan_id', (int) $this->user_id)
            ->where('jenis', $sourceJenis)
            ->whereBetween('tanggal', [$start, $end])
            ->orderBy('tanggal')
            ->get()
            ->map(function (LaporanSummary $row) use ($sourceJenis) {
                return [
                    'sumber_tipe' => 'laporan_summary',
                    'sumber_id' => (int) $row->id,
                    'karyawan_id' => (int) $row->karyawan_id,
                    'nama' => $this->periodLabel($sourceJenis, (string) $row->tanggal),
                    'tanggal' => (string) $row->tanggal,
                    'no_dokumen' => $row->no_dokumen,
                    'status' => $this->hasText($row->isi) ? 'Sudah' : 'Kosong',
                    'cuplikan' => $this->snippet($row->isi),
                    'jumlah' => LaporanSummarySumber::query()->where('summary_id', $row->id)->count(),
                    'isi' => (string) $row->isi,
                    'updated_at' => $row->updated_at,
                ];
            })
            ->all();
    }

    private function subordinateDailyRows(string $tanggal): array
    {
        $people = $this->directSubordinates();
        $ids = $people->pluck('id')->map(function ($id) {
            return (int) $id;
        })->all();
        $reports = $ids === []
            ? collect()
            : LaporanHarian::query()
                ->whereIn('karyawan_id', $ids)
                ->where('jenis', 'harian')
                ->where('tanggal', $tanggal)
                ->get()
                ->keyBy('karyawan_id');

        return $people->map(function ($person) use ($reports, $tanggal) {
            $report = $reports->get($person->id);
            $filled = $report && $this->hasText($report->isi);

            return [
                'sumber_tipe' => 'laporan_harian',
                'sumber_id' => $report ? (int) $report->id : null,
                'karyawan_id' => (int) $person->id,
                'nama' => $person->nama_lengkap,
                'image' => $person->image ?? null,
                'jabatan' => $person->jabatan ?? null,
                'department' => $person->department ?? null,
                'tanggal' => $tanggal,
                'no_dokumen' => $report->no_dokumen ?? null,
                'status' => $filled ? 'Sudah' : 'Belum',
                'cuplikan' => $filled ? $this->snippet($report->isi) : '-',
                'jumlah' => null,
                'isi' => $filled ? (string) $report->isi : '',
                'updated_at' => $report ? $report->updated_at : null,
            ];
        })->all();
    }

    private function summaryFor(string $jenis, string $tanggal): ?LaporanSummary
    {
        return LaporanSummary::query()
            ->where('karyawan_id', (int) $this->user_id)
            ->where('jenis', $jenis)
            ->where('tanggal', $tanggal)
            ->first();
    }

    private function presentSummary(?LaporanSummary $row): ?array
    {
        if (!$row) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'no_dokumen' => $row->no_dokumen,
            'jenis' => $row->jenis,
            'tanggal' => (string) $row->tanggal,
            'periode_label' => $this->periodLabel((string) $row->jenis, (string) $row->tanggal),
            'isi' => $row->isi ?? '',
            'updated_at' => $row->updated_at,
        ];
    }

    private function snippet(?string $html): string
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', str_replace("\xc2\xa0", ' ', $text)) ?? '');
        if ($text === '') {
            return '-';
        }
        if (mb_strlen($text) <= 140) {
            return $text;
        }

        return mb_substr($text, 0, 137) . '...';
    }

    private function makeSummaryDocumentNumber(): string
    {
        do {
            $number = substr(bin2hex(random_bytes(5)), 0, 9);
        } while (
            LaporanSummary::query()->where('no_dokumen', $number)->exists()
            || LaporanHarian::query()->where('no_dokumen', $number)->exists()
        );

        return $number;
    }

    private function publicSourceRows(array $rows, ?LaporanSummary $summaryRow = null, string $jenis = 'harian'): array
    {
        $extracted = LaporanHarianSummaryAiService::extractSourceSummaries($summaryRow ? (string) $summaryRow->isi : null);
        $useKaryawanId = $jenis === 'harian';

        return array_values(array_map(static function (array $row) use ($extracted, $useKaryawanId): array {
            $row['ringkasan_ai'] = LaporanHarianSummaryAiService::resolveSourceSummary(
                $extracted,
                isset($row['karyawan_id']) ? (int) $row['karyawan_id'] : null,
                (string) ($row['nama'] ?? ''),
                $useKaryawanId
            );

            return $row;
        }, $rows));
    }
}
