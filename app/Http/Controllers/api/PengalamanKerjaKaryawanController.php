<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\MasterKaryawan;
use App\Models\PengalamanKerjaKaryawan;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PengalamanKerjaKaryawanController extends Controller
{
    public function index(Request $request)
    {
        $data = MasterKaryawan::with(['jabatan', 'cabang'])
            ->where('is_active', true)
            ->where('grade', '!=', 'DIRECTOR');

        return Datatables::of($data)
            ->addColumn('jabatan', function ($row) {
                return optional($row->jabatan)->nama_jabatan ?? ($row->jabatan ?? '-');
            })
            ->addColumn('cabang', function ($row) {
                return optional($row->cabang)->nama_cabang ?? '-';
            })
            ->addColumn('jumlah_pengalaman', function ($row) {
                return $this->experienceItems($row)->count();
            })
            ->addColumn('pengalaman_terakhir', function ($row) {
                $latest = $this->latestExperience($row);
                if (!$latest) {
                    return '-';
                }
                $company = trim((string) ($latest->nama_perusahaan ?? ''));
                $position = trim((string) ($latest->posisi_kerja ?? ''));
                if ($company === '' && $position === '') {
                    return '-';
                }
                if ($company !== '' && $position !== '') {
                    return $position . ' · ' . $company;
                }

                return $company !== '' ? $company : $position;
            })
            ->addColumn('periode_terakhir', function ($row) {
                $latest = $this->latestExperience($row);
                if (!$latest) {
                    return '-';
                }

                return $this->formatExperiencePeriod($latest, $row);
            })
            ->filterColumn('jabatan', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->whereHas('jabatan', function ($sub) use ($keyword) {
                        $sub->where('nama_jabatan', 'like', '%' . $keyword . '%');
                    })->orWhere('jabatan', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('cabang', function ($query, $keyword) {
                $query->whereHas('cabang', function ($sub) use ($keyword) {
                    $sub->where('nama_cabang', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('pengalaman_terakhir', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->whereHas('pengalaman_kerja', function ($sub) use ($keyword) {
                        $sub->where(function ($active) {
                            $active->where('is_active', 1)->orWhere('is_active', true)->orWhereNull('is_active');
                        })->where(function ($w) use ($keyword) {
                            $w->where('nama_perusahaan', 'like', '%' . $keyword . '%')
                                ->orWhere('posisi_kerja', 'like', '%' . $keyword . '%')
                                ->orWhere('lokasi_perusahaan', 'like', '%' . $keyword . '%');
                        });
                    })->orWhere('pengalaman_kerja', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('periode_terakhir', function ($query, $keyword) {
                $query->whereHas('pengalaman_kerja', function ($sub) use ($keyword) {
                    $sub->where(function ($active) {
                        $active->where('is_active', 1)->orWhere('is_active', true)->orWhereNull('is_active');
                    })->where(function ($w) use ($keyword) {
                        $w->where('tgl_mulai_kerja', 'like', '%' . $keyword . '%')
                            ->orWhere('tgl_berakhir_kerja', 'like', '%' . $keyword . '%');
                    });
                });
            })
            ->filterColumn('jumlah_pengalaman', function ($query, $keyword) {
                if (!is_numeric($keyword)) {
                    return;
                }
                $query->has('pengalaman_kerja', '=', (int) $keyword);
            })
            ->make(true);
    }

    public function showById(Request $request)
    {
        $karyawanId = (int) $request->karyawan_id;
        if ($karyawanId <= 0) {
            return response()->json(['data' => []], 200);
        }

        $rows = PengalamanKerjaKaryawan::where('karyawan_id', $karyawanId)
            ->where(function ($q) {
                $q->where('is_active', 1)->orWhere('is_active', true)->orWhereNull('is_active');
            })
            ->orderByDesc('tgl_mulai_kerja')
            ->orderByDesc('id')
            ->get();

        if ($rows->isEmpty()) {
            $karyawan = MasterKaryawan::find($karyawanId);
            $rows = $karyawan ? $this->legacyExperienceFromJson($karyawan) : collect([]);
        }

        return response()->json(['data' => $rows->values()->all()], 200);
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            PengalamanKerjaKaryawan::create([
                'karyawan_id' => $request->karyawan_id,
                'nama_perusahaan' => $request->nama_perusahaan,
                'lokasi_perusahaan' => $request->lokasi_perusahaan,
                'posisi_kerja' => $request->posisi_kerja,
                'tgl_mulai_kerja' => $request->tgl_mulai_kerja,
                'tgl_berakhir_kerja' => $this->nullableEndDate($request->tgl_berakhir_kerja),
                'alasan_keluar' => $request->alasan_keluar,
                'created_by' => $this->karyawan,
                'created_at' => date('Y-m-d H:i:s'),
                'is_active' => true,
            ]);

            DB::commit();

            return response()->json(['message' => 'Data berhasil disimpan'], 200);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request)
    {
        try {
            DB::beginTransaction();

            $data = PengalamanKerjaKaryawan::find($request->id);
            $data->nama_perusahaan = $request->nama_perusahaan;
            $data->lokasi_perusahaan = $request->lokasi_perusahaan;
            $data->posisi_kerja = $request->posisi_kerja;
            $data->tgl_mulai_kerja = $request->tgl_mulai_kerja;
            $data->tgl_berakhir_kerja = $this->nullableEndDate($request->tgl_berakhir_kerja);
            $data->alasan_keluar = $request->alasan_keluar;
            $data->updated_by = $this->karyawan;
            $data->updated_at = date('Y-m-d H:i:s');
            $data->save();

            DB::commit();

            return response()->json(['message' => 'Data berhasil diupdate', 'data' => $data], 200);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request)
    {
        try {
            DB::beginTransaction();

            $data = PengalamanKerjaKaryawan::find($request->id);
            $data->is_active = false;
            $data->deleted_by = $this->karyawan;
            $data->deleted_at = date('Y-m-d H:i:s');
            $data->save();

            DB::commit();

            return response()->json(['message' => 'Data berhasil dihapus'], 200);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    private function experienceItems($row): Collection
    {
        static $cache = [];

        $karyawanId = (int) ($row->id ?? 0);
        if ($karyawanId <= 0) {
            return collect([]);
        }

        if (isset($cache[$karyawanId])) {
            return $cache[$karyawanId];
        }

        $rows = PengalamanKerjaKaryawan::where('karyawan_id', $karyawanId)
            ->where(function ($q) {
                $q->where('is_active', 1)->orWhere('is_active', true)->orWhereNull('is_active');
            })
            ->orderByDesc('tgl_mulai_kerja')
            ->orderByDesc('id')
            ->get();

        if ($rows->isEmpty()) {
            $rows = $this->legacyExperienceFromJson($row);
        }

        $cache[$karyawanId] = $rows;

        return $rows;
    }

    private function legacyExperienceFromJson($row): Collection
    {
        $raw = $row->getAttributes()['pengalaman_kerja'] ?? null;
        if ($raw === null || $raw === '') {
            return collect([]);
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($decoded)) {
            return collect([]);
        }

        return collect($decoded)
            ->filter(fn ($item) => is_array($item))
            ->map(function ($item) {
                return (object) [
                    'id' => $item['id'] ?? null,
                    'nama_perusahaan' => $item['nama_perusahaan'] ?? null,
                    'lokasi_perusahaan' => $item['lokasi_perusahaan'] ?? null,
                    'posisi_kerja' => $item['posisi_kerja'] ?? null,
                    'tgl_mulai_kerja' => $item['tgl_mulai_kerja'] ?? $item['mulai_kerja'] ?? $item['tanggal_mulai'] ?? null,
                    'tgl_berakhir_kerja' => $item['tgl_berakhir_kerja'] ?? $item['akhir_kerja'] ?? $item['tanggal_selesai'] ?? null,
                    'alasan_keluar' => $item['alasan_keluar'] ?? $item['alasan_resign'] ?? null,
                ];
            })
            ->filter(function ($item) {
                return trim((string) ($item->nama_perusahaan ?? '')) !== ''
                    || trim((string) ($item->posisi_kerja ?? '')) !== '';
            })
            ->values();
    }

    private function latestExperience($row)
    {
        return $this->experienceItems($row)
            ->sortByDesc(function ($item) {
                $start = $item->tgl_mulai_kerja ?? null;
                try {
                    $ts = $start ? Carbon::parse($start)->timestamp : 0;
                } catch (\Exception $e) {
                    $ts = 0;
                }

                return sprintf('%010d-%08d', $ts, (int) ($item->id ?? 0));
            })
            ->first();
    }

    private function formatExperiencePeriod($item, $row): string
    {
        $start = $this->formatIdDate($item->tgl_mulai_kerja ?? null);
        $endRaw = $item->tgl_berakhir_kerja ?? null;
        $end = $this->formatIdDate($endRaw);

        if ($start === '-' && $end === '-') {
            return '-';
        }
        if ($start === '-') {
            return $end;
        }
        if ($end === '-') {
            $all = $this->experienceItems($row)->sortByDesc('tgl_mulai_kerja')->values();
            $latest = $all->first();
            if ($latest && (int) ($latest->id ?? 0) === (int) ($item->id ?? 0)) {
                return $start . ' – Sampai Sekarang';
            }

            return $start;
        }

        return $start . ' – ' . $end;
    }

    private function formatIdDate($value): string
    {
        if ($value === null || $value === '' || strpos((string) $value, '0000-00-00') === 0) {
            return '-';
        }
        try {
            $date = Carbon::parse($value);
            if ($date->year < 1971) {
                return '-';
            }

            return $date->locale('id')->translatedFormat('j F Y');
        } catch (\Exception $e) {
            return '-';
        }
    }

    private function nullableEndDate($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (strpos((string) $value, '0000-00-00') === 0) {
            return null;
        }
        try {
            if (Carbon::parse($value)->year < 1971) {
                return null;
            }
        } catch (\Exception $e) {
            return null;
        }

        return $value;
    }
}
