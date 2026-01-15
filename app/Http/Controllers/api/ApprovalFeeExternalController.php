<?php
namespace App\Http\Controllers\api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClaimFeeExternal;
use App\Models\DailyQsd;
use Yajra\DataTables\Facades\DataTables;
use App\Services\GetBawahan;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ApprovalFeeExternalController extends Controller
{
    public function index(Request $request)
    {
        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;

        $query = ClaimFeeExternal::query()->where('status_pembayaran', 'WAITING PROCESS')
            ->where('is_active', true)->where('is_approved_manajer', false);

        if (in_array($jabatan, [15, 157])) {
            $bawahan = GetBawahan::where('id', $this->user_id)
                ->pluck('id')
                ->toArray();
            $bawahan[] = $this->user_id;

            $query->whereIn('sales_id', $bawahan);
        }

        return DataTables::of($query)->make(true);
    }

    public function approve(Request $request)
    {
        DB::beginTransaction();
        try {
            $claim = ClaimFeeExternal::findOrFail($request->id);
            $claim->is_approved_manajer = true;
            $claim->approved_manajer_at = Carbon::now()->format('Y-m-d H:i:s');
            $claim->approved_manajer_by = $this->karyawan;
            $claim->save();

            DB::commit();
            return response()->json(['message' => 'Claim Fee External Berhasil Disetujui']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Claim Fee External Gagal Disetujui'], 500);
        }
    }

    public function reject(Request $request)
    {
        DB::beginTransaction();
        try {
            $claim = ClaimFeeExternal::findOrFail($request->id);
            $claim->is_approved_manajer = false;
            $claim->status_pembayaran = 'REJECTED';
            $claim->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
            $claim->rejected_by = $this->karyawan;
            $claim->save();

            DB::commit();
            return response()->json(['message' => 'Claim Fee External Berhasil Ditolak']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Claim Fee External Gagal Ditolak'], 500);
        }
    }
}