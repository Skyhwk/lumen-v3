<?php

namespace App\Http\Controllers\api;

use App\Helpers\ShioElemenHelper;
use App\Models\MasterDivisi;
use App\Models\MasterKaryawan;
use App\Models\NewRecruitment;
use App\Models\PersonnelRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class BankDataCandidatController extends DataApplicantsController
{
    /**
     * Hanya Manager divisi HRD yang boleh transfer dari bank data kandidat.
     */
    protected function isHrdManager(): bool
    {
        if (strtoupper(trim((string) ($this->grade ?? ''))) !== 'MANAGER') {
            return false;
        }

        $departmentId = (int) env('ATS_HRD_DEPARTMENT_ID', 0); // tambahkan kalau mau hardcode HRD Division ID di env
        if ($departmentId > 0 && (int) $this->department === $departmentId) {
            return true;
        }

        if (!$this->user_id) {
            return false;
        }

        $employee = MasterKaryawan::query()
            ->where('id', $this->user_id)
            ->first(['department', 'id_department']);

        if (!$employee) {
            return false;
        }

        if (strtoupper(trim((string) ($employee->department ?? ''))) === 'HRD') {
            return true;
        }

        $divisiName = MasterDivisi::query()
            ->where('id', $employee->id_department)
            ->value('nama_divisi');

        return strtoupper(trim((string) $divisiName)) === 'HRD';
    }

    protected function denyUnlessHrdManager()
    {
        if ($this->isHrdManager()) {
            return null;
        }

        return response()->json([
            'message' => 'Transfer kandidat hanya dapat dilakukan oleh Manager divisi HRD',
        ], 403);
    }

    protected function isBankCandidate(NewRecruitment $candidate): bool
    {
        return (int) $candidate->is_active === 1
            && ($candidate->personnel_request_id === null || $candidate->personnel_request_id === '');
    }

    public function listActivePersonnelRequests(Request $request)
    {
        if ($response = $this->denyUnlessHrdManager()) {
            return $response;
        }

        $items = PersonnelRequest::with(['masterJabatan', 'masterDivisi'])
            ->where('is_active', 1)
            ->where('is_publish', 1)
            ->where(function ($q) {
                $q->where('is_reject', 0)->orWhereNull('is_reject');
            })
            ->orderByDesc('id')
            ->get()
            ->map(function ($row) {
                $posisi = optional($row->masterJabatan)->nama_jabatan ?: ($row->posisi ?: '-');
                $divisi = optional($row->masterDivisi)->nama_divisi ?: ($row->divisi_alias ?: ($row->divisi ?: '-'));

                return [
                    'id' => $row->id,
                    'no_request' => $row->no_request,
                    'posisi' => $posisi,
                    'divisi' => $divisi,
                    'jumlah_personal' => (int) $row->jumlah_personal,
                    'label' => trim(($row->no_request ?: '-') . ' — ' . $posisi . ' / ' . $divisi),
                ];
            })
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => $items,
        ], 200);
    }

    public function transferCandidate(Request $request)
    {
        if ($response = $this->denyUnlessHrdManager()) {
            return $response;
        }

        $candidateId = $request->input('id');
        $targetPersonnelRequestId = (int) $request->input('target_personnel_request_id', 0);

        if (!$candidateId) {
            return response()->json(['message' => 'ID kandidat tidak ditemukan'], 400);
        }
        if ($targetPersonnelRequestId <= 0) {
            return response()->json(['message' => 'Target personnel request wajib dipilih'], 400);
        }

        $candidate = NewRecruitment::query()->find($candidateId);
        if (!$candidate) {
            return response()->json(['message' => 'Data kandidat tidak ditemukan'], 404);
        }

        if (!$this->isBankCandidate($candidate)) {
            return response()->json([
                'message' => 'Kandidat tidak ditemukan di bank data atau sudah terhubung ke personnel request',
            ], 422);
        }

        $target = PersonnelRequest::with('masterJabatan')
            ->where('id', $targetPersonnelRequestId)
            ->where('is_active', 1)
            ->where('is_publish', 1)
            ->where(function ($q) {
                $q->where('is_reject', 0)->orWhereNull('is_reject');
            })
            ->first();

        if (!$target) {
            return response()->json(['message' => 'Target personnel request tidak aktif / tidak tersedia'], 422);
        }

        try {
            DB::beginTransaction();

            $history = json_decode($candidate->meta_history ?: '[]', true);
            $history = is_array($history) ? $history : [];
            $history[] = [
                'status' => 'transfer_personnel_request',
                'at' => Carbon::now()->toDateTimeString(),
                'transferred_by' => $this->karyawan,
                'from_personnel_request_id' => null,
                'from_no_request' => null,
                'to_personnel_request_id' => $target->id,
                'to_no_request' => $target->no_request,
                'source' => 'bank_data_candidat',
            ];

            $posisiDilamar = optional($target->masterJabatan)->nama_jabatan
                ?: ($target->posisi ?: $candidate->posisi_dilamar);

            DB::table('new_recruitment')->where('id', $candidate->id)->update([
                'personnel_request_id' => $target->id,
                'posisi_dilamar' => $posisiDilamar,
                'meta_history' => json_encode(array_values($history)),
                'updated_at' => Carbon::now(),
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Kandidat berhasil ditransfer ke personnel request ' . ($target->no_request ?: $target->id),
                'data' => [
                    'candidate_id' => $candidate->id,
                    'personnel_request_id' => $target->id,
                    'no_request' => $target->no_request,
                ],
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal transfer kandidat: ' . $th->getMessage(),
            ], 500);
        }
    }
    /**
     * Datatable list kandidat bank (personnel_request_id NULL, is_active = 1).
     */
    public function index(Request $request)
    {
        $query = NewRecruitment::with(['hrdInterview', 'userInterview', 'appliedPositionJabatan', 'masterJabatan'])
            ->where('is_active', 1)
            ->where(function ($q) {
                $q->whereNull('personnel_request_id')
                    ->orWhere('personnel_request_id', '');
            })
            ->when($request->filled('year'), function ($q) use ($request) {
                return $q->where(function ($sub) use ($request) {
                    $sub->whereYear('created_at', $request->year)
                        ->orWhereNull('created_at');
                });
            })
            ->orderBy('id', 'desc');

        return DataTables::of($query)
            ->filterColumn('nama_lengkap', function ($q, $keyword) {
                $q->where('nama_lengkap', 'like', "%{$keyword}%");
            })
            ->editColumn('posisi_dilamar', function ($row) {
                return $this->resolvePositionName($row);
            })
            ->filterColumn('posisi_dilamar', function ($q, $keyword) {
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('posisi_dilamar', 'like', "%{$keyword}%")
                        ->orWhereHas('appliedPositionJabatan', function ($j) use ($keyword) {
                            $j->where('nama_jabatan', 'like', "%{$keyword}%");
                        })
                        ->orWhereHas('masterJabatan', function ($j) use ($keyword) {
                            $j->where('nama_jabatan', 'like', "%{$keyword}%");
                        });
                });
            })
            ->filterColumn('status', function ($q, $keyword) {
                $q->where('status', 'like', "%{$keyword}%");
            })
            ->addColumn('usia', function ($row) {
                $birthYear = $this->extractBirthYear($row);
                if ($birthYear) {
                    $age = Carbon::now()->year - $birthYear;
                    return $age . ' Yrs';
                }
                return '-';
            })
            ->filterColumn('usia', function ($q, $keyword) {
                $cleanDigits = preg_replace('/[^0-9]/', '', $keyword);
                if (!empty($cleanDigits)) {
                    $targetYear = Carbon::now()->year - (int) $cleanDigits;
                    $q->where(function ($sub) use ($targetYear, $cleanDigits) {
                        $sub->whereYear('tanggal_lahir', $targetYear)
                            ->orWhere('tempat_tanggal_lahir', 'like', "%{$cleanDigits}%");
                    });
                } else {
                    $q->where('tempat_tanggal_lahir', 'like', "%{$keyword}%");
                }
            })
            ->editColumn('shio', function ($row) {
                $birthDate = $row->tanggal_lahir ?? $this->getTtlString($row);
                $shioElemen = ShioElemenHelper::resolve($birthDate, $row->shio, $row->elemen);
                $shio = $shioElemen['shio'] ?? null;
                $elemen = $shioElemen['elemen'] ?? null;
                if ($shio && $elemen) {
                    return "{$shio} ({$elemen})";
                }
                return $shio ?: ($elemen ?: '-');
            })
            ->filterColumn('shio', function ($q, $keyword) {
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('shio', 'like', "%{$keyword}%")
                        ->orWhere('elemen', 'like', "%{$keyword}%")
                        ->orWhere('tempat_tanggal_lahir', 'like', "%{$keyword}%")
                        ->orWhere('tanggal_lahir', 'like', "%{$keyword}%");
                });
            })
            ->editColumn('nilai_kecocokan', function ($row) {
                if ($row->nilai_kecocokan !== null && $row->nilai_kecocokan !== '') {
                    return (float) $row->nilai_kecocokan;
                }

                if ($row->matching_score !== null && $row->matching_score !== '') {
                    return (float) $row->matching_score;
                }

                return null;
            })
            ->filterColumn('nilai_kecocokan', function ($q, $keyword) {
                $cleanVal = preg_replace('/[^0-9.]/', '', $keyword);
                if (!empty($cleanVal)) {
                    $q->where(function ($sub) use ($cleanVal) {
                        $sub->where('nilai_kecocokan', 'like', "%{$cleanVal}%")
                            ->orWhere('matching_score', 'like', "%{$cleanVal}%");
                    });
                }
            })
            ->editColumn('status', function ($row) {
                return $row->status ?: 'assessment';
            })
            ->addColumn('hrd_interview', function ($row) {
                return $row->hrdInterview;
            })
            ->addColumn('user_interview', function ($row) {
                return $row->userInterview;
            })
            ->make(true);
    }
}
