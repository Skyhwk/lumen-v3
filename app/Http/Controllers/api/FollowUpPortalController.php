<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\FollowUpCustomerAccount;
use App\Models\MasterKaryawan;
use App\Services\FollowUpPortalService;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;

class FollowUpPortalController extends Controller
{
    public function current()
    {
        if (!$this->user_id) {
            return response()->json(['message' => 'User tidak dikenali'], 401);
        }

        $service = new FollowUpPortalService();
        $canFollowUp = $this->isMagangIntern();
        if (!$canFollowUp) {
            $service->releaseOpenAssignments((int) $this->user_id);
        }

        $data = $canFollowUp ? $service->present($service->currentFor((int) $this->user_id)) : null;

        return response()->json([
            'data' => $data,
            'counts' => $this->tabCounts($service, $data !== null, $this->canViewDone()),
            'can_view_done' => $this->canViewDone(),
            'can_follow_up' => $canFollowUp,
        ]);
    }

    public function submit(Request $request)
    {
        if (!$this->user_id) {
            return response()->json(['message' => 'User tidak dikenali'], 401);
        }
        if (!$this->isMagangIntern()) {
            return response()->json(['message' => 'Follow up hanya untuk Magang/Intern'], 403);
        }

        $keputusan = (string) $request->input('keputusan');
        $keterangan = trim((string) $request->input('keterangan'));
        $id = (int) $request->input('id');

        if (!in_array($keputusan, ['bersedia', 'tidak_bersedia'], true)) {
            return response()->json(['message' => 'Pilih bersedia atau tidak bersedia'], 422);
        }
        if ($keterangan === '') {
            return response()->json(['message' => 'Keterangan wajib diisi'], 422);
        }
        if ($id < 1) {
            return response()->json(['message' => 'Data follow up tidak ditemukan'], 422);
        }

        $service = new FollowUpPortalService();
        $next = $service->submit((int) $this->user_id, (string) $this->karyawan, $id, $keputusan, $keterangan);
        if ($next === null && !FollowUpCustomerAccount::query()->where('id', $id)->whereNotNull('followed_at')->exists()) {
            return response()->json(['message' => 'Data ini bukan giliran kamu atau sudah dikerjakan'], 422);
        }

        $data = $service->present($next);

        return response()->json([
            'message' => 'Follow up tersimpan',
            'data' => $data,
            'counts' => $this->tabCounts($service, $data !== null, $this->canViewDone()),
            'can_view_done' => $this->canViewDone(),
            'can_follow_up' => true,
        ]);
    }

    public function done()
    {
        if (!$this->canViewDone()) {
            return response()->json(['message' => 'Daftar yang sudah di-follow up hanya untuk Manager dan divisi IT Programming & Support'], 403);
        }

        $data = FollowUpCustomerAccount::query()
            ->whereNotNull('followed_at')
            ->select([
                'id',
                'id_pelanggan',
                'nama_pelanggan',
                'no_tlp',
                'pic_nama',
                'pic_telepon',
                'keputusan',
                'keterangan',
                'followed_by',
                'followed_at',
            ]);

        return Datatables::of($data)
            ->editColumn('keputusan', function ($row) {
                return $row->keputusan === 'bersedia' ? 'Bersedia' : 'Tidak bersedia';
            })
            ->filterColumn('keputusan', function ($query, $keyword) {
                $keyword = strtolower(trim($keyword));
                if ($keyword === '') {
                    return;
                }
                if (strpos($keyword, 'tidak') !== false) {
                    $query->where('keputusan', 'tidak_bersedia');
                    return;
                }
                if (strpos($keyword, 'bersedia') !== false) {
                    $query->where('keputusan', 'bersedia');
                    return;
                }
                $query->where('keputusan', 'like', '%' . $keyword . '%');
            })
            ->make(true);
    }

    private function tabCounts(FollowUpPortalService $service, bool $hasCurrent, bool $canViewDone): array
    {
        return [
            'pending' => $hasCurrent ? 1 : 0,
            'done' => $canViewDone ? $service->counts()['done'] : null,
        ];
    }

    private function isMagangIntern(): bool
    {
        if (!$this->user_id) {
            return false;
        }

        $status = MasterKaryawan::query()
            ->where('id', $this->user_id)
            ->value('status_karyawan');

        return strcasecmp(trim((string) $status), 'Magang/Intern') === 0;
    }

    private function canViewDone(): bool
    {
        $grade = strtoupper(trim((string) $this->grade));
        if (in_array($grade, ['MANAGER', 'SENIOR MANAGER'], true)) {
            return true;
        }

        return $this->isItProgrammingDivisionMember();
    }
}
