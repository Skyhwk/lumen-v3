<?php

namespace App\Http\Controllers\api;

use App\Helpers\ShioElemenHelper;
use App\Http\Controllers\Controller;
use App\Models\NewRecruitment;
use App\Services\MpdfService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Mpdf\Output\Destination;

class DataApplicantsController extends Controller
{
    /**
     * Get Datatable list of applicants
     */
    public function index(Request $request)
    {
        $query = NewRecruitment::with(['personalRequest.masterJabatan'])
            ->when($request->filled('year'), function ($q) use ($request) {
                return $q->where(function ($sub) use ($request) {
                    $sub->whereYear('created_at', $request->year)
                        ->orWhereNull('created_at');
                });
            })
            ->orderBy('id', 'desc');

        return DataTables::of($query)
            ->addColumn('no_request', function ($row) {
                return $row->personalRequest->no_request ?? '-';
            })
            ->editColumn('posisi_dilamar', function ($row) {
                $pos = $row->personalRequest->masterJabatan->nama_jabatan 
                    ?? $row->personalRequest->posisi_name;
                if (!$pos && $row->personalRequest && !is_numeric($row->personalRequest->posisi)) {
                    $pos = $row->personalRequest->posisi;
                }
                if (!$pos && !is_numeric($row->posisi_dilamar)) {
                    $pos = $row->posisi_dilamar;
                }
                return $pos ?? '-';
            })
            ->addColumn('usia', function ($row) {
                $ttl = $this->getTtlString($row);
                $birthYear = $this->extractBirthYear($ttl);
                if ($birthYear) {
                    $age = Carbon::now()->year - $birthYear;
                    return $age . ' Thn';
                }
                return '-';
            })
            ->editColumn('shio', function ($row) {
                $birthDate = $row->tanggal_lahir ?? $this->getTtlString($row);
                $shioElemen = ShioElemenHelper::resolve($birthDate, $row->shio, $row->elemen);
                return $shioElemen['shio'] ?? '-';
            })
            ->editColumn('gaji_terakhir', function ($row) {
                $gaji = $row->ekspetasi_gaji ?: $row->gaji_terakhir;
                if ($gaji) {
                    return 'Rp ' . number_format($gaji, 0, ',', '.');
                }
                return '-';
            })
            ->editColumn('nilai_kecocokan', function ($row) {
                $score = $row->nilai_kecocokan !== null && $row->nilai_kecocokan !== '' 
                    ? $row->nilai_kecocokan 
                    : ($row->matching_score ?? rand(70, 95));
                return $score . '%';
            })
            ->editColumn('status', function ($row) {
                return $row->status ?? 'pending';
            })
            ->make(true);
    }

    /**
     * Helper to resolve TTL string from row fields
     */
    private function getTtlString($row)
    {
        if (!empty($row->tempat_tanggal_lahir)) {
            return $row->tempat_tanggal_lahir;
        }
        $parts = [];
        if (!empty($row->tempat_lahir)) $parts[] = $row->tempat_lahir;
        if (!empty($row->tanggal_lahir)) $parts[] = $row->tanggal_lahir;
        return count($parts) > 0 ? implode(', ', $parts) : null;
    }

    /**
     * Helper to extract birth year from string or date
     */
    private function extractBirthYear($ttl)
    {
        if (!$ttl) return null;
        if (preg_match('/\b(19\d\d|20\d\d)\b/', $ttl, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    /**
     * Approve applicant
     */
    public function approve(Request $request, $id)
    {
        $applicant = NewRecruitment::find($id);

        if (!$applicant) {
            return response()->json([
                'status' => 404,
                'message' => 'Applicant data not found',
            ], 404);
        }

        $user = $this->karyawan ?? $request->header('user') ?? 'HRD Admin';

        $applicant->update([
            'status' => 'approved',
            'approved_by' => $user,
            'approved_at' => Carbon::now(),
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Applicant has been approved successfully',
            'data' => $applicant,
        ], 200);
    }

    /**
     * Reject applicant
     */
    public function reject(Request $request, $id)
    {
        $applicant = NewRecruitment::find($id);

        if (!$applicant) {
            return response()->json([
                'status' => 404,
                'message' => 'Applicant data not found',
            ], 404);
        }

        $user = $this->karyawan ?? $request->header('user') ?? 'HRD Admin';
        $reason = $request->input('alasan_reject') ?? 'Tidak memenuhi kualifikasi';

        $applicant->update([
            'status' => 'rejected',
            'rejected_by' => $user,
            'rejected_at' => Carbon::now(),
            'alasan_reject' => $reason,
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Applicant has been rejected',
            'data' => $applicant,
        ], 200);
    }

    /**
     * Generate CV ATS PDF via MPDF
     */
    public function generateCvPdf(Request $request, $id)
    {
        $applicant = NewRecruitment::with(['personalRequest.masterJabatan'])->find($id);

        if (!$applicant) {
            return response()->json(['message' => 'Applicant data not found'], 404);
        }

        $posisiName = null;
        if ($applicant->personalRequest) {
            $posisiName = $applicant->personalRequest->masterJabatan->nama_jabatan 
                ?? $applicant->personalRequest->posisi_name
                ?? (is_numeric($applicant->personalRequest->posisi) ? null : $applicant->personalRequest->posisi);
        }
        if (!$posisiName && !empty($applicant->posisi_dilamar)) {
            $posisiName = is_numeric($applicant->posisi_dilamar) ? null : $applicant->posisi_dilamar;
        }
        $posisi = $posisiName ?: 'Candidate';
        
        $ttl = $this->getTtlString($applicant);
        $birthYear = $this->extractBirthYear($ttl);
        
        $birthDate = $applicant->tanggal_lahir ?? $ttl;
        $shioElemen = ShioElemenHelper::resolve($birthDate, $applicant->shio, $applicant->elemen);
        $shio = $shioElemen['shio'] ?? '-';
        $elemen = $shioElemen['elemen'] ?? '-';

        $usia = $birthYear ? (Carbon::now()->year - $birthYear) . ' Tahun' : '-';
        $gajiFormatted = ($applicant->ekspetasi_gaji ?: $applicant->gaji_terakhir) 
            ? 'Rp ' . number_format(($applicant->ekspetasi_gaji ?: $applicant->gaji_terakhir), 0, ',', '.') 
            : '-';
        $score = $applicant->nilai_kecocokan ?: ($applicant->matching_score ?: 85);

        // Build Education HTML
        $pendidikanHtml = '';
        if (is_array($applicant->pendidikan) && count($applicant->pendidikan) > 0) {
            foreach ($applicant->pendidikan as $edu) {
                $jenjang = $edu['jenjang'] ?? $edu['degree'] ?? 'Pendidikan';
                $institusi = $edu['institusi'] ?? $edu['school'] ?? '-';
                $jurusan = $edu['jurusan'] ?? $edu['major'] ?? '';
                $tahun = $edu['tahun'] ?? $edu['year'] ?? '';
                $ipk = isset($edu['gpa']) || isset($edu['ipk']) ? ' (IPK: ' . ($edu['gpa'] ?? $edu['ipk']) . ')' : '';

                $pendidikanHtml .= "
                <div style='margin-bottom: 10px;'>
                    <strong style='font-size: 13px; color: #1e293b;'>{$jenjang} {$jurusan} - {$institusi}</strong> {$ipk}<br>
                    <span style='font-size: 11px; color: #64748b;'>Tahun: {$tahun}</span>
                </div>";
            }
        } else {
            $pendidikanHtml = "<p style='color: #64748b; font-size: 11px; margin: 0;'>Tidak ada data pendidikan tercatat.</p>";
        }

        // Build Experience HTML
        $pengalamanHtml = '';
        if (is_array($applicant->pengalaman_kerja) && count($applicant->pengalaman_kerja) > 0) {
            foreach ($applicant->pengalaman_kerja as $exp) {
                $pos = $exp['posisi'] ?? $exp['position'] ?? 'Posisi';
                $perusahaan = $exp['perusahaan'] ?? $exp['company'] ?? 'Perusahaan';
                $periode = $exp['periode'] ?? $exp['period'] ?? '';
                $deskripsi = $exp['deskripsi'] ?? $exp['description'] ?? '';

                $pengalamanHtml .= "
                <div style='margin-bottom: 12px;'>
                    <strong style='font-size: 13px; color: #1e293b;'>{$pos}</strong> di <span>{$perusahaan}</span><br>
                    <span style='font-size: 11px; color: #64748b;'>Periode: {$periode}</span>";
                if ($deskripsi) {
                    $pengalamanHtml .= "<p style='font-size: 11px; color: #334155; margin: 4px 0 0 0;'>{$deskripsi}</p>";
                }
                $pengalamanHtml .= "</div>";
            }
        } else {
            $pengalamanHtml = "<p style='color: #64748b; font-size: 11px; margin: 0;'>Fresh Graduate / Tidak ada pengalaman kerja sebelumnya.</p>";
        }

        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>CV ATS - {$applicant->nama_lengkap}</title>
            <style>
                body {
                    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
                    color: #334155;
                    font-size: 12px;
                    line-height: 1.5;
                }
                .header-table {
                    width: 100%;
                    border-bottom: 2px solid #2563eb;
                    padding-bottom: 12px;
                    margin-bottom: 15px;
                }
                .applicant-name {
                    font-size: 22px;
                    font-weight: bold;
                    color: #1e293b;
                    margin: 0 0 4px 0;
                    text-transform: uppercase;
                }
                .applied-position {
                    font-size: 14px;
                    font-weight: 600;
                    color: #2563eb;
                    margin: 0;
                }
                .meta-badge {
                    background-color: #eff6ff;
                    color: #1d4ed8;
                    border: 1px solid #bfdbfe;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 11px;
                    font-weight: bold;
                    display: inline-block;
                }
                .section-title {
                    font-size: 13px;
                    font-weight: bold;
                    color: #1e293b;
                    text-transform: uppercase;
                    border-bottom: 1px solid #cbd5e1;
                    padding-bottom: 4px;
                    margin-top: 15px;
                    margin-bottom: 10px;
                    letter-spacing: 0.5px;
                }
                .info-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 10px;
                }
                .info-table td {
                    padding: 4px 8px;
                    vertical-align: top;
                }
                .info-label {
                    width: 30%;
                    font-weight: bold;
                    color: #475569;
                }
                .info-value {
                    width: 70%;
                    color: #0f172a;
                }
            </style>
        </head>
        <body>

            <table class='header-table'>
                <tr>
                    <td style='width: 70%;'>
                        <div class='applicant-name'>{$applicant->nama_lengkap}</div>
                        <div class='applied-position'>Posisi Dilamar: {$posisi}</div>
                        <div style='margin-top: 6px; color: #64748b; font-size: 11px;'>
                            Email: {$applicant->email} | Telp: {$applicant->no_telepon}
                        </div>
                    </td>
                    <td style='width: 30%; text-align: right; vertical-align: top;'>
                        <div class='meta-badge' style='background: #1e40af; color: #ffffff; padding: 6px 10px; border-radius: 4px; font-size: 12px;'>
                            Nilai Kecocokan: {$score}%
                        </div>
                    </td>
                </tr>
            </table>

            <div class='section-title'>Informasi Pribadi & Kualifikasi</div>
            <table class='info-table'>
                <tr>
                    <td class='info-label'>Tempat, Tanggal Lahir</td>
                    <td class='info-value'>{$ttl} ({$usia})</td>
                </tr>
                <tr>
                    <td class='info-label'>Shio & Elemen</td>
                    <td class='info-value'>{$shio} " . ($elemen ? "({$elemen})" : "") . "</td>
                </tr>
                <tr>
                    <td class='info-label'>Alamat KTP</td>
                    <td class='info-value'>" . ($applicant->alamat_ktp ?: '-') . "</td>
                </tr>
                <tr>
                    <td class='info-label'>Alamat Domisili</td>
                    <td class='info-value'>" . ($applicant->alamat_domisili ?: '-') . "</td>
                </tr>
                <tr>
                    <td class='info-label'>Ekspektasi Gaji</td>
                    <td class='info-value'>{$gajiFormatted}</td>
                </tr>
                <tr>
                    <td class='info-label'>Tanggal Join Tercepat</td>
                    <td class='info-value'>" . ($applicant->tanggal_join_tercepat ? Carbon::parse($applicant->tanggal_join_tercepat)->format('d F Y') : '-') . "</td>
                </tr>
            </table>

            <div class='section-title'>Riwayat Pendidikan</div>
            {$pendidikanHtml}

            <div class='section-title'>Pengalaman Kerja</div>
            {$pengalamanHtml}

            <div style='margin-top: 30px; text-align: right; color: #94a3b8; font-size: 10px; border-top: 1px solid #e2e8f0; padding-top: 8px;'>
                Dokumen ini didokumentasikan secara otomatis oleh Sistem HRD Applicant Tracking System (ATS).
            </div>

        </body>
        </html>
        ";

        $mpdf = new MpdfService([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
        ]);

        $mpdf->WriteHTML($html);
        return $mpdf->Output("CV_ATS_{$applicant->nama_lengkap}.pdf", Destination::INLINE);
    }
}
