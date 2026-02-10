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

class ClaimFeeExternalController extends Controller
{
    public function outstandingIndex(Request $request)
    {
        $status = $request->status;
        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;

        $query = ClaimFeeExternal::query()
            ->where('is_active', true)
            ->whereIn('status_pembayaran', ['WAITING PROCESS', 'REJECTED']);

        if (in_array($jabatan, [24, 86, 148])) {
            $query->where('sales_id', $this->user_id);
        }else if (in_array($jabatan, [21, 15, 154, 157])) {
            $bawahan = GetBawahan::where('id', $this->user_id)
                ->pluck('id')
                ->toArray();
            $bawahan[] = $this->user_id;

            $query->whereIn('sales_id', $bawahan);
        }
        return DataTables::of($query)->make(true);
    }


    
    public function settlementIndex(Request $request)
    {
        $status = $request->status;
        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
        
        $query = ClaimFeeExternal::query()
            ->where('is_active', true)->whereIn('status_pembayaran', ['PROCESSED', 'READY TO TRANSFER', 'TRANSFER']);

        if (in_array($jabatan, [24, 86, 148])) {
            $query->where('sales_id', $this->user_id);
        }else if (in_array($jabatan, [21, 15, 154, 157])) {
            $bawahan = GetBawahan::where('id', $this->user_id)
                ->pluck('id')
                ->toArray();
            $bawahan[] = $this->user_id;

            $query->whereIn('sales_id', $bawahan);
        }

        return DataTables::of($query)->make(true);
    }


    public function getOrders(Request $request)
    {
        $term = trim($request->term);

        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;

        // Guard: term minimal 3 karakter
        if (!$term || strlen($term) < 3) {
            return response()->json([
                'data' => []
            ]);
        }

        $query = DailyQsd::where('is_lunas', true)
            ->where(function ($q) use ($term) {
                $q->where('no_order', 'like', "%{$term}%");
            });

        // filter berdasarkan jabatan tertentu
        if (in_array($jabatan, [24, 86, 148])) {
            $query->where('sales_id', $this->user_id);
        }else if (in_array($jabatan, [21, 15, 154, 157])) {
            $bawahan = GetBawahan::where('id', $this->user_id)
                ->pluck('id')
                ->toArray();
            $bawahan[] = $this->user_id;

            $query->whereIn('sales_id', $bawahan);
        }

        $data = $query
            ->selectRaw('
                no_order,
                COUNT(DISTINCT periode) as total_kontrak,
                GROUP_CONCAT(DISTINCT periode SEPARATOR ", ") as kontrak,
                MAX(nama_perusahaan) as nama_perusahaan,
                MAX(no_quotation) as no_quotation,
                MAX(no_invoice) as no_invoice,
                MAX(biaya_akhir) as biaya_akhir
            ')
            ->groupBy('no_order')
            ->get();

        return response()->json([
            'data' => $data
        ]);
    }

    public function getOrderByKontrak(Request $request)
    {
        $order = DailyQsd::where('no_order', $request->no_order)
            ->where('periode', $request->kontrak)
            ->where('is_lunas', true)
            ->first();

        return response()->json($order);
    }

    public function getOrderDetail(Request $request)
    {
        $order = DailyQsd::where('is_lunas', true)
            ->where('no_order', $request->no_order)
            ->where('kontrak', 'N')
            ->first();

        return response()->json($order);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $id = $request->id;
            $noInvoice = preg_replace('/\s*\(Lunas\)/i', '', $request->no_invoice);
            // Cek apakah ini mode edit (ada id) atau tambah baru
            if ($id) {
                // Mode UPDATE
                $claim = ClaimFeeExternal::findOrFail($id);
                $claim->updated_by = $this->karyawan;
                $claim->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                $message = 'Claim Fee External Berhasil Diperbarui';
            } else {
                // Mode INSERT
                $claim = new ClaimFeeExternal();
                $claim->created_by = $this->karyawan;
                $claim->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $message = 'Claim Fee External Berhasil Tersimpan';
            }
            
            $claim->no_order = $request->no_order;
            $claim->nama_penerima = $request->nama_penerima;
            $claim->nama_bank = $request->bank != '' ? $request->bank : null;
            $claim->no_rekening = $request->no_rekening != '' ? $request->no_rekening : null;
            $claim->nama_perusahaan = $request->nama_perusahaan;
            $claim->nominal = $request->nominal;
            $claim->due_date = $request->tanggal_claim;
            $claim->status_pembayaran = 'WAITING PROCESS';
            $claim->no_quotation = $request->no_quotation;
            $claim->sales_id = $this->user_id;
            $claim->no_invoice = $noInvoice;
            $claim->biaya_akhir = $request->biaya_akhir;
            $claim->periode = $request->periode;
            $claim->metode_pembayaran = $request->metode_bayar;
            $claim->save();
            
            DB::commit();
            return response()->json(['message' => $message], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan pada server', 
                'details' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        $claim = ClaimFeeExternal::find($request->id);
        if (!$claim) {
            return response()->json(['message' => 'Claim Fee External tidak ditemukan'], 404);
        }
        $claim->deleted_by = $this->karyawan;
        $claim->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
        $claim->is_active = false;
        $claim->save();

        return response()->json(['message' => 'Claim Fee External Berhasil Dihapus']);
    }

    public function indexManager(Request $request)
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


}