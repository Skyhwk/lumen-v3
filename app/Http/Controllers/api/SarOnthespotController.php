<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\SarOnthespotHeader;
use App\Services\GenerateSkhpSarOnthespotService;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;

class SarOnthespotController extends Controller
{
    public function index(Request $request)
    {
        $query = SarOnthespotHeader::query()
            ->withCount(['detail', 'hasilUji'])
            ->orderByDesc('created_at');

        return Datatables::of($query)
            ->addColumn('status_label', function ($row) {
                return $row->status_order === 'done' ? 'Selesai' : 'Berjalan';
            })
            ->addColumn('jumlah_parameter', fn ($row) => $row->detail_count ?? 0)
            ->addColumn('jumlah_hasil_uji', fn ($row) => $row->hasil_uji_count ?? 0)
            ->addColumn('has_skhp', fn ($row) => !empty($row->file_skhp))
            ->make(true);
    }

    public function show(Request $request)
    {
        $header = SarOnthespotHeader::with(['detail', 'hasilUji.acuan'])
            ->findOrFail($request->id);

        $data = $header->toArray();
        $data['hasil_uji'] = collect($header->hasilUji)->map(function ($item) {
            $row = $item->toArray();
            if (is_string($row['hasil_uji_array'] ?? null)) {
                $decoded = json_decode($row['hasil_uji_array'], true);
                $row['hasil_uji_array'] = is_array($decoded) ? $decoded : [];
            }
            $row['nilai_rujukan'] = optional($item->acuan)->nilai_rujukan;

            return $row;
        })->values()->all();

        return response()->json($data, 200);
    }

    public function renderPdf(Request $request)
    {
        $header = SarOnthespotHeader::with(['detail', 'hasilUji.acuan'])
            ->findOrFail($request->id);

        if ($header->status_order !== 'done' && empty($header->file_skhp)) {
            return response()->json([
                'message' => 'SKHP belum tersedia. Order masih berjalan.',
            ], 400);
        }

        $service = new GenerateSkhpSarOnthespotService();

        if (!empty($header->file_skhp)) {
            $path = public_path('dokumen/SkhpOnthespot/' . $header->file_skhp);
            if (file_exists($path)) {
                return response()->json($header->file_skhp, 200);
            }
        }

        $filename = $service->renderPdfOnly($header, $this->karyawan);

        return response()->json($filename, 200);
    }

    public function downloadSkhp(Request $request)
    {
        $header = SarOnthespotHeader::findOrFail($request->id);

        if (empty($header->file_skhp)) {
            return response()->json([
                'message' => 'SKHP belum tersedia untuk order ini',
            ], 404);
        }

        $path = public_path('dokumen/SkhpOnthespot/' . $header->file_skhp);

        if (!file_exists($path)) {
            $header = SarOnthespotHeader::with(['detail', 'hasilUji.acuan'])
                ->findOrFail($request->id);
            $service = new GenerateSkhpSarOnthespotService();
            $service->renderPdfOnly($header, $this->karyawan);
            $path = public_path('dokumen/SkhpOnthespot/' . $header->file_skhp);
        }

        if (!file_exists($path)) {
            return response()->json([
                'message' => 'File SKHP tidak ditemukan',
            ], 404);
        }

        return response(file_get_contents($path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $header->file_skhp . '"',
        ]);
    }

    public function resendSkhpEmail(Request $request)
    {
        $header = SarOnthespotHeader::findOrFail($request->id);

        if (empty($header->file_skhp)) {
            return response()->json([
                'message' => 'SKHP belum tersedia untuk order ini',
            ], 400);
        }

        if (empty($header->email)) {
            return response()->json([
                'message' => 'Email pelanggan kosong',
            ], 400);
        }

        $service = new GenerateSkhpSarOnthespotService();
        $emailSent = $service->resendEmail($header, $this->karyawan);

        if (!$emailSent) {
            return response()->json([
                'message' => 'Gagal mengirim email SKHP',
            ], 400);
        }

        return response()->json([
            'message' => 'Email SKHP berhasil dikirim ulang ke ' . $header->email,
        ], 200);
    }
}
