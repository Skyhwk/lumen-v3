<?php

namespace App\Http\Controllers\Greatday;

use App\Models\Payroll;
use App\Services\Greatday\RenderSlipGajiService;
use App\Services\Greatday\SlipGajiPinService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SlipGajiController extends Controller
{
    public function periods(Request $request)
    {
        if (!$this->user_id) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            $payrolls = Payroll::with(['payrollHeader', 'karyawan'])
                ->where('id_karyawan', $this->user_id)
                ->where('is_active', 1)
                ->whereHas('payrollHeader', function ($query) {
                    $query->where('is_active', 1);
                })
                ->orderByDesc('periode_payroll')
                ->get()
                ->filter(function (Payroll $payroll) {
                    return $this->resolvePayrollNik($payroll) !== null;
                });

            $hasPin = SlipGajiPinService::hasPin($this->user_id);

            $data = $payrolls->map(function (Payroll $payroll) use ($hasPin) {
                $periodeRaw = $payroll->periode_payroll;
                $periodeDate = $this->parsePeriode($periodeRaw);

                return [
                    'payroll_id' => $payroll->id,
                    'periode_payroll' => $periodeRaw,
                    'periode_label' => $periodeDate
                        ? $periodeDate->translatedFormat('F Y')
                        : '-',
                    'tgl_transfer' => optional($payroll->payrollHeader)->tgl_transfer,
                    'requires_pin' => $hasPin,
                ];
            })->values();

            return response()->json([
                'message' => 'success',
                'data' => $data,
                'has_pin' => $hasPin,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function view(Request $request)
    {
        if (!$this->user_id) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'payroll_id' => 'required|integer',
            'pin' => 'nullable|string|size:6|regex:/^\d{6}$/',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        try {
            $payroll = $this->findOwnedPayroll($request->payroll_id);
            $this->assertPinAccess($payroll, $request->pin);

            $result = $this->getOrGenerateSlip($payroll, $request->pin);
            $imagePath = $result['image']['path'] ?? null;

            if (!$imagePath || !is_file($imagePath)) {
                $message = $result['image_error'] ?? 'Gagal menghasilkan preview slip gaji. Pastikan ekstensi Imagick dan Ghostscript/pdftoppm terpasang di server.';

                return response()->json(['message' => $message], 500);
            }

            $periodeDate = $this->parsePeriode($payroll->periode_payroll);

            return response()->json([
                'message' => 'success',
                'data' => [
                    'payroll_id' => $payroll->id,
                    'periode_label' => $periodeDate
                        ? $periodeDate->translatedFormat('F Y')
                        : '-',
                    'image_base64' => base64_encode(file_get_contents($imagePath)),
                    'image_mime' => 'image/webp',
                    'pdf_filename' => $result['filename'] ?? null,
                    'pdf_password_hint' => $result['password_hint'] ?? null,
                    'pdf_password_format' => $result['password_format'] ?? null,
                ],
            ], 200);
        } catch (\Throwable $th) {
            $status = $th->getCode() >= 400 && $th->getCode() < 600 ? $th->getCode() : 500;

            return response()->json(['message' => $th->getMessage()], $status);
        }
    }

    public function download(Request $request)
    {
        if (!$this->user_id) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'payroll_id' => 'required|integer',
            'pin' => 'nullable|string|size:6|regex:/^\d{6}$/',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        try {
            $payroll = $this->findOwnedPayroll($request->payroll_id);
            $this->assertPinAccess($payroll, $request->pin);

            $result = $this->getOrGenerateSlip($payroll, $request->pin);
            $pdfPath = $result['path'] ?? null;
            $filename = $result['filename'] ?? 'slip-gaji.pdf';

            if (!$pdfPath || !is_file($pdfPath)) {
                return response()->json(['message' => 'File PDF slip gaji tidak ditemukan'], 404);
            }

            return response()->download($pdfPath, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Throwable $th) {
            $status = $th->getCode() >= 400 && $th->getCode() < 600 ? $th->getCode() : 500;

            if ($status === 500 && str_contains($th->getMessage(), 'PIN')) {
                $status = 403;
            }

            return response()->json(['message' => $th->getMessage()], $status);
        }
    }

    private function findOwnedPayroll($payrollId)
    {
        $payroll = Payroll::with(['payrollHeader', 'karyawan'])
            ->where('id', $payrollId)
            ->where('id_karyawan', $this->user_id)
            ->where('is_active', 1)
            ->first();

        if (!$payroll) {
            throw new \Exception('Data slip gaji tidak ditemukan', 404);
        }

        $this->assertPayrollHasNik($payroll);

        return $payroll;
    }

    private function resolvePayrollNik(Payroll $payroll): ?string
    {
        $nik = trim((string) (
            $payroll->nik_karyawan
            ?? optional(RenderSlipGajiService::resolvePayrollKaryawan($payroll))->nik_karyawan
            ?? ''
        ));

        return $nik !== '' ? $nik : null;
    }

    private function assertPayrollHasNik(Payroll $payroll): void
    {
        if ($this->resolvePayrollNik($payroll) === null) {
            throw new \Exception('Slip gaji tidak tersedia karena NIK karyawan belum diisi', 422);
        }
    }

    private function assertPinAccess(Payroll $payroll, $pin)
    {
        if (!SlipGajiPinService::hasPin($this->user_id)) {
            return;
        }

        if (empty($pin)) {
            throw new \Exception('PIN proteksi diperlukan untuk slip gaji ini', 403);
        }

        if (!SlipGajiPinService::verifyPin($this->user_id, $pin)) {
            throw new \Exception('PIN proteksi tidak valid', 403);
        }
    }

    private function parsePeriode($periodePayroll)
    {
        if (empty($periodePayroll)) {
            return null;
        }

        try {
            $value = preg_match('/^\d{4}-\d{2}$/', $periodePayroll)
                ? $periodePayroll . '-01'
                : $periodePayroll;

            return Carbon::parse($value)->locale('id');
        } catch (\Throwable $th) {
            return null;
        }
    }

    private function getOrGenerateSlip(Payroll $payroll, $verifiedPin = null)
    {
        $karyawan = RenderSlipGajiService::resolvePayrollKaryawan($payroll);

        if (!$karyawan) {
            throw new \Exception('Data karyawan tidak ditemukan', 404);
        }

        $filenames = RenderSlipGajiService::buildSlipFilenames($payroll);
        $pdfFilename = $filenames['pdf_filename'];
        $imageFilename = $filenames['image_filename'];

        $pdfPath = public_path('dokumen/payroll/slip_gaji/pdf/' . $pdfFilename);
        $imagePath = public_path('dokumen/payroll/slip_gaji/image/' . $imageFilename);

        $passwordMeta = RenderSlipGajiService::resolvePdfPasswordForKaryawan(
            $karyawan,
            $verifiedPin,
            $this->user_id
        );

        $this->invalidateCachedSlipIfStale($pdfPath, $imagePath);

        if (is_file($pdfPath) && is_file($imagePath)) {
            return [
                'filename' => $pdfFilename,
                'path' => $pdfPath,
                'url' => '/dokumen/payroll/slip_gaji/pdf/' . $pdfFilename,
                'image' => [
                    'filename' => $imageFilename,
                    'path' => $imagePath,
                    'url' => '/dokumen/payroll/slip_gaji/image/' . $imageFilename,
                ],
                'image_url' => '/dokumen/payroll/slip_gaji/image/' . $imageFilename,
                'password_hint' => $passwordMeta['hint'],
                'password_format' => $passwordMeta['type'] === 'pin' ? 'pin' : 'dmY',
            ];
        }

        if (is_file($pdfPath) && !is_file($imagePath)) {
            return RenderSlipGajiService::convertExistingPayrollPdf($payroll, $pdfPath, $verifiedPin);
        }

        return RenderSlipGajiService::fromPayrollId($payroll->id, $verifiedPin)->generate();
    }

    private function invalidateCachedSlipIfStale($pdfPath, $imagePath)
    {
        if (!is_file($pdfPath)) {
            return;
        }

        $pdfTime = filemtime($pdfPath);
        $references = [
            public_path('img/isl_logo.png'),
            public_path('isl_logo.png'),
            public_path('logo-watermark.png'),
            resource_path('views/Slip-Gaji.blade.php'),
            base_path('app/Services/Greatday/RenderSlipGajiService.php'),
        ];

        foreach ($references as $referencePath) {
            if (is_file($referencePath) && filemtime($referencePath) > $pdfTime) {
                @unlink($pdfPath);
                if (is_file($imagePath)) {
                    @unlink($imagePath);
                }

                return;
            }
        }
    }
}
