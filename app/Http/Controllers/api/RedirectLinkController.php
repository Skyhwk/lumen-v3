<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\RedirectLink;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Yajra\Datatables\Datatables;

class RedirectLinkController extends Controller
{
    public function index()
    {
        $data = RedirectLink::where('is_active', true)->latest();

        $portalBase = rtrim(env('PORTALV4', 'https://portal.intilab.com'), '/') . '/redirect/';

        return Datatables::of($data)
            ->addColumn('redirect_url', function ($row) {
                return $this->buildRedirectUrl($row->batch_key);
            })
            ->filterColumn('redirect_url', function ($query, $keyword) use ($portalBase) {
                $query->whereRaw("CONCAT(?, batch_key) LIKE ?", [$portalBase, "%{$keyword}%"]);
            })
            ->make(true);
    }

    public function save(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'label' => 'nullable|string|max:255',
            'target_url' => 'required|url|max:2000',
        ], [
            'target_url.url' => 'Target URL tidak valid.',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        $redirectLink = new RedirectLink();
        $redirectLink->batch_key = $this->generateBatchKey();
        $redirectLink->label = $request->label;
        $redirectLink->target_url = $request->target_url;
        $redirectLink->visit_count = 0;
        $redirectLink->is_active = true;
        $redirectLink->created_by = $this->karyawan;
        $redirectLink->updated_by = $this->karyawan;
        $redirectLink->created_at = Carbon::now()->format('Y-m-d H:i:s');
        $redirectLink->save();

        return response()->json([
            'message' => 'Redirect link berhasil dibuat.',
            'redirect_url' => $this->buildRedirectUrl($redirectLink->batch_key),
        ], 200);
    }

    public function generateQr(Request $request)
    {
        $redirectLink = RedirectLink::where('id', $request->id)->where('is_active', true)->first();

        if (!$redirectLink) {
            return response()->json(['message' => 'Redirect link tidak ditemukan.'], 404);
        }

        $redirectUrl = $this->buildRedirectUrl($redirectLink->batch_key);
        $qrCode = base64_encode(QrCode::size(300)->generate($redirectUrl));

        return response()->json([
            'message' => 'QR Code berhasil di-generate.',
            'data' => 'data:image/svg+xml;base64,' . $qrCode,
            'redirect_url' => $redirectUrl,
        ], 200);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'label' => 'nullable|string|max:255',
            'target_url' => 'required|url|max:2000',
        ], [
            'target_url.url' => 'Target URL tidak valid.',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        $redirectLink = RedirectLink::where('id', $request->id)->where('is_active', true)->first();

        if (!$redirectLink) {
            return response()->json(['message' => 'Redirect link tidak ditemukan.'], 404);
        }

        $redirectLink->label = $request->label;
        $redirectLink->target_url = $request->target_url;
        $redirectLink->updated_by = $this->karyawan;
        $redirectLink->updated_at = Carbon::now()->format('Y-m-d H:i:s');
        $redirectLink->save();

        return response()->json(['message' => 'Redirect link berhasil diperbarui.'], 200);
    }

    public function destroy(Request $request)
    {
        $redirectLink = RedirectLink::where('id', $request->id)->where('is_active', true)->first();

        if (!$redirectLink) {
            return response()->json(['message' => 'Redirect link tidak ditemukan.'], 404);
        }

        $redirectLink->is_active = false;
        $redirectLink->deleted_by = $this->karyawan;
        $redirectLink->save();
        $redirectLink->delete();

        return response()->json(['message' => 'Redirect link berhasil dihapus.'], 200);
    }

    private function generateBatchKey(): string
    {
        do {
            $batchKey = md5((string) microtime(true));
        } while (RedirectLink::where('batch_key', $batchKey)->exists());

        return $batchKey;
    }

    private function buildRedirectUrl(string $batchKey): string
    {
        return rtrim(env('PORTALV4', 'https://portal.intilab.com'), '/') . '/redirect/' . $batchKey;
    }
}
