<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\FollowUpCustomerAccount;
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
        $row = $service->activePreviewFor((int) $this->user_id);

        return response()->json($this->payload($service, $service->present($row)));
    }

    public function start()
    {
        if (!$this->user_id) {
            return response()->json(['message' => 'User tidak dikenali'], 401);
        }

        $service = new FollowUpPortalService();
        $row = $service->previewFor((int) $this->user_id);

        if ($row === null) {
            return response()->json([
                'message' => 'Tidak ada pelanggan dalam antrian',
                'data' => null,
                'counts' => $this->tabCounts($service, $this->canViewDone(), false),
                'can_view_done' => $this->canViewDone(),
                'can_follow_up' => true,
            ], 422);
        }

        return response()->json(array_merge(
            ['message' => 'Data pelanggan siap di-follow up'],
            $this->payload($service, $service->present($row))
        ));
    }

    public function release(Request $request)
    {
        if (!$this->user_id) {
            return response()->json(['message' => 'User tidak dikenali'], 401);
        }

        $id = (int) $request->input('id');
        $service = new FollowUpPortalService();
        $service->releasePreview((int) $this->user_id, $id > 0 ? $id : null);

        return response()->json($this->payload($service, null));
    }

    public function submit(Request $request)
    {
        if (!$this->user_id) {
            return response()->json(['message' => 'User tidak dikenali'], 401);
        }

        $keputusan = (string) $request->input('keputusan');
        $keterangan = trim((string) $request->input('keterangan'));
        $id = (int) $request->input('id');

        if (!in_array($keputusan, ['bersedia', 'tidak_bersedia', 'tidak_terhubung'], true)) {
            return response()->json(['message' => 'Pilih salah satu keputusan'], 422);
        }
        if ($keterangan === '') {
            return response()->json(['message' => 'Keterangan wajib diisi'], 422);
        }
        if ($id < 1) {
            return response()->json(['message' => 'Data follow up tidak ditemukan'], 422);
        }

        $service = new FollowUpPortalService();
        $saved = $service->submit((int) $this->user_id, (string) $this->karyawan, $id, $keputusan, $keterangan);

        if (!$saved) {
            $alreadyDone = FollowUpCustomerAccount::query()
                ->where('id', $id)
                ->whereNotNull('followed_at')
                ->exists();

            if ($alreadyDone) {
                return response()->json(['message' => 'Data ini sudah di-follow up'], 422);
            }

            return response()->json([
                'message' => 'Sesi follow up habis atau bukan data Anda. Ambil ulang dengan Mulai follow up.',
            ], 422);
        }

        return response()->json(array_merge(
            ['message' => 'Follow up tersimpan'],
            $this->payload($service, null)
        ));
    }

    public function done()
    {
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

        if (!$this->canViewDone()) {
            $data->whereRaw('1 = 0');
        }

        return Datatables::of($data)
            ->editColumn('keputusan', function ($row) {
                return $this->keputusanLabel((string) $row->keputusan);
            })
            ->filterColumn('keputusan', function ($query, $keyword) {
                $keyword = strtolower(trim($keyword));
                if ($keyword === '') {
                    return;
                }
                if (strpos($keyword, 'terhubung') !== false) {
                    $query->where('keputusan', 'tidak_dapat_terhubung');
                    return;
                }
                if (strpos($keyword, 'tidak') !== false && strpos($keyword, 'bersedia') !== false) {
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

    private function payload(FollowUpPortalService $service, ?array $data): array
    {
        return [
            'data' => $data,
            'counts' => $this->tabCounts($service, $this->canViewDone(), $data !== null),
            'can_view_done' => $this->canViewDone(),
            'can_follow_up' => true,
        ];
    }

    private function tabCounts(FollowUpPortalService $service, bool $canViewDone, bool $hasActiveCustomer): array
    {
        return [
            'pending' => $hasActiveCustomer ? 1 : null,
            'done' => $canViewDone ? $service->counts()['done'] : 0,
        ];
    }

    private function canViewDone(): bool
    {
        $grade = strtoupper(str_replace('_', ' ', trim((string) $this->grade)));

        return in_array($grade, ['MANAGER', 'SENIOR MANAGER', 'DIRECTOR'], true);
    }

    private function keputusanLabel(string $value): string
    {
        $labels = [
            'bersedia' => 'Bersedia buat akun',
            'tidak_bersedia' => 'Tidak bersedia',
            'tidak_dapat_terhubung' => 'Tidak dapat terhubung',
        ];

        return $labels[$value] ?? $value;
    }
}
