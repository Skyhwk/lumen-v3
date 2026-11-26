<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\MasterKaryawan;
use App\Models\MasterCabang;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use \App\Services\MpdfService as PDF;
use App\Services\DateColumnFilterService;
use Carbon\Carbon;

class KaryawanNonAktifController extends Controller
{
    public function index(Request $request)
    {
        $this->autoNaKaryawan();

        $data = MasterKaryawan::with('divisi', 'jabatan', 'cabang')
            ->where('active', false)
            ->where('is_active', false)
            ->whereNotNull('reason_non_active');

        return Datatables::of($data)
            ->addColumn('effective_non_aktive_date', function ($row) {
                return $row->effective_date ?? '-';
            })
            ->addColumn('lama_bekerja', function ($row) {
                return $this->getDuration($row);
            })
            ->filterColumn('effective_date', DateColumnFilterService::datatablesFilter('effective_date'))
            ->filterColumn('jabatan.nama_jabatan', function ($query, $keyword) {
                $keyword = trim((string) $keyword);
                if ($keyword === '') {
                    return;
                }

                $query->whereHas('jabatan', function ($jabatan) use ($keyword) {
                    $jabatan->where('nama_jabatan', 'like', '%' . $keyword . '%');
                });
            })
            ->make(true);
    }

    public function getDuration($row)
    {
        if (empty($row->tgl_mulai_kerja) || empty($row->effective_date)) {
            return '-';
        }
        $start = Carbon::parse($row->tgl_mulai_kerja)->startOfDay();
        $end = Carbon::parse($row->effective_date)->startOfDay();
        if ($start->gt($end)) {
            return '0 Tahun, 0 Bulan, 0 Hari';
        }
        $diff = $start->diff($end);

        if ($diff->y > 0) {
            return "{$diff->y} Tahun, {$diff->m} Bulan, {$diff->d} Hari";
        } else if ($diff->m > 0) {
            return "{$diff->m} Bulan, {$diff->d} Hari";
        } else {
            return "{$diff->d} Hari";
        }
    }

    public function getSpv(Request $request)
    {
        $data = MasterKaryawan::where('is_active', true)->where('jabatan', 'Recruitment Supervisor');
        return Datatables::of($data)->make(true);
    }

    public function aktivasiKaryawan(Request $request)
    {
        $searchYear = $request->search;
        $db = isset($searchYear) ? date('Y', strtotime($searchYear)) : $this->db;
        DB::beginTransaction();
        try {
            $allowedStatus = [
                'Permanent',
                'Probation',
                'Freelance',
                'Contract',
                'Trainee',
                'Training',
                'Special'
            ];

            $check = MasterKaryawan::where('nik_karyawan', $request->nik)
                ->where('is_active', true)
                ->where('active', true)
                ->first();

            if ($check) {
                return response()->json([
                    'message' => 'NIK Sudah digunakan oleh staff : ' . $check->nama_lengkap . '.!',
                ], 400);
            }

            // Validate the status_karyawan
            if (!in_array($request->status_karyawan, $allowedStatus)) {
                throw new \Exception("Invalid status_karyawan value provided.");
            }

            $data = MasterKaryawan::where('id', $request->id)->first();
            $data->status_karyawan = $request->status_karyawan;
            $data->notes = $request->notes;
            $data->nik_karyawan = $request->nik_karyawan;
            $data->effective_date = NULL;
            $data->is_active = true;
            $data->active = true;
            $data->updated_by = $this->karyawan;
            $data->updated_at = date('Y-m-d H:i:s');
            $data->save();

            DB::commit();
            return response()->json([
                'message' => 'Karyawan berhasil diaktivasi',
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function printSuratNonAktif(Request $request)
    {
        $searchYear = $request->search;
        $db = isset($searchYear) ? date('Y', strtotime($searchYear)) : $this->db;
        try {
            $data = MasterKaryawan::with('jabatan', 'department')->where('id', $request->id)->first();
            if (!$data) {
                return response()->json([
                    'message' => 'Data karyawan tidak ditemukan',
                ], 404);
            }

            $hrd = MasterKaryawan::with('jabatan', 'department')->where('id', $this->user_id)->first();
            $cabang = MasterCabang::where('id', $this->idcabang)->first();

            $mpdfConfig = array(
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 18,
                'margin_right' => 18,
                'margin_top' => 34,
                'margin_bottom' => 30,
                'margin_header' => 12,
                'margin_footer' => 18,
                'setAutoTopMargin' => 'stretch',
                'setAutoBottomMargin' => 'stretch',
                'orientation' => 'P'
            );
            $pdf = new PDF($mpdfConfig);

            $namaDivisi = e(optional($data->department)->nama_divisi ?: '-');
            $namaJabatan = e(optional($data->jabatan)->nama_jabatan ?: '-');
            $namaHrd = e(optional($hrd)->nama_lengkap ?: '-');
            $jabatanHrd = e(optional(optional($hrd)->jabatan)->nama_jabatan ?: '-');
            $tanggalTerakhir = !empty($data->effective_date)
                ? e(self::tanggal_indonesia($data->effective_date))
                : '-';
            $alamatCabang = e(optional($cabang)->alamat_cabang ?: '');
            $teleponCabang = e(optional($cabang)->tlp_cabang ?: '');

            $pdf->SetHTMLHeader('
                <table style="width: 198mm; margin-left: -13mm; border-collapse: collapse;">
                    <tr>
                        <td style="width: 36%; vertical-align: middle; padding: 0 0 3mm 14mm;">
                            <img src="' . public_path() . '/logo/isl_logo.png" alt="ISL" style="height: 52px; width: auto;">
                        </td>
                        <td style="width: 44%; text-align: center; vertical-align: middle; padding: 0 0 0mm 20mm;">
                            <div style="font-size: 14pt; font-weight: bold; letter-spacing: 0.4px;">SURAT PEMBERITAHUAN</div>
                            <div style="font-size: 9pt; margin-top: 4px;">Ref : ISL/&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;/' . DATE('y') . '-' . self::romawi(DATE('m')) . '/&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>
                        </td>
                        <td style="width: 28%; padding: 0 12mm 7mm 0;"></td>
                    </tr>
                </table>
            ');

            $pdf->WriteHTML('
                <style>
                    body { font-family: sans-serif; font-size: 11pt; color: #111; line-height: 1.45; }
                    p { margin: 0 0 8px 0; }
                    table.identitas { width: 100%; border-collapse: collapse; margin: 6px 0 12px 0; }
                    table.identitas td { padding: 3px 0; vertical-align: top; font-size: 10.5pt; }
                    table.identitas td.label { width: 145px; }
                </style>
                <div style="position: fixed; left: -12mm; top: -29mm; width: 198mm; height: 275mm; border: 1.2pt solid #1a1a1a;">&nbsp;</div>
                <div style="position: fixed; left: -12mm; top: -2mm; width: 198mm; border-top: 0.5pt solid grey; font-size: 1pt; line-height: 0;">&nbsp;</div>
                <div style="position: fixed; left: -12mm; top: 233mm; width: 198mm; border-top: 0.5pt solid grey; font-size: 1pt; line-height: 0;">&nbsp;</div>
                <div style="position: fixed; left: -12mm; top: 249mm; width: 198mm; text-align: right; font-family: serif; font-style: italic; font-size: 8pt; color: #000; line-height: 1.35;">
                    Note : Dokumen ini diterbitkan otomatis oleh sistem<br>' . date('YmdHi') . '
                </div>
                <p style="margin-top: 10mm;">Perihal : <b>Karyawan Keluar</b></p>
                <p>Kepada Seluruh Karyawan,<br><b>PT Inti Surya Laboratorium</b></p>
                <p style="text-align: justify;">Sehubungan dengan ketentuan, peraturan, dan serta kebijakan perusahaan <b>perihal hubungan dengan pihak luar, </b>pihak perusahaan, khususnya dari pihak <b>HRD, </b>mengingatkan dan serta menegaskan kembali kepada seluruh karyawan bahwa <b>tidak diperkenankan dalam bentuk apapun juga untuk mengadakan komunikasi perihal pekerjaan dengan karyawan yang telah keluar dan atau tidak bekerja pada perusahaan, </b>agar karyawan yang sedang bekerja dapat berlaku dan bertindak dengan baik dan dengan sebagaimana mestinya.</p>
                <p>Perihal nama karyawan yang telah keluar, berikut kami sampaikan informasinya sebagai berikut :</p>
                <table class="identitas">
                    <tr>
                        <td class="label">Nama</td>
                        <td>: ' . e($data->nama_lengkap) . '</td>
                    </tr>
                    <tr>
                        <td class="label">NIK</td>
                        <td>: ' . e($data->nik_karyawan) . '</td>
                    </tr>
                    <tr>
                        <td class="label">Divisi / Posisi</td>
                        <td>: ' . $namaDivisi . ' / ' . $namaJabatan . '</td>
                    </tr>
                    <tr>
                        <td class="label">Terakhir Bekerja</td>
                        <td>: ' . $tanggalTerakhir . '</td>
                    </tr>
                    <tr>
                        <td class="label">Alasan</td>
                        <td>: ' . e($data->reason_non_active) . '</td>
                    </tr>
                </table>
            ');
            if ($data->notes != null && $data->notes != '') {
                $pdf->WriteHTML('<p>Note : ' . e($data->notes) . '</p>');
            }
            $pdf->WriteHTML('
                <p>Dihimbau kepada seluruh karyawan agar dapat diperhatikan, dimengerti dan dipahami serta dapat bekerja dengan sebaik-baiknya dan sebenar-benarnya.</p>
                <p style="margin-top: 18px;">Tangerang, ' . self::tanggal_indonesia(date('Y-m-d')) . '</p>
                <p style="margin-top: 48px;"><b><u>' . $namaHrd . '</u></b><br>' . $jabatanHrd . '</p>
            ');

            $pdf->SetHTMLFooter('
                <div style="text-align: center; font-size: 9pt; color: #333; padding-top: 4px;">
                    ' . $alamatCabang . '<br>P : ' . $teleponCabang . ' - www.intilab.com - contact@intilab.com
                </div>
            ');

            $binary = $pdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
            return response()->json([
                'message' => 'pdf hasbeen generate',
                'file_name' => ($data->nama_lengkap ?: 'surat-non-aktif') . '.pdf',
                'pdf_base64' => base64_encode($binary),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membuat surat non aktif: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function romawi($bulan = 0)
    {
        $satuan = (int) $bulan - 1;
        $romawi = ['I', ' II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        return $romawi[$satuan];
    }

    public function tanggal_indonesia($tanggal)
    {

        $bulan = array(
            1 => 'Januari',
            'Februari',
            'Maret',
            'April',
            'Mei',
            'Juni',
            'Juli',
            'Agustus',
            'September',
            'Oktober',
            'November',
            'Desember'
        );

        $var = explode('-', $tanggal);

        return $var[2] . ' ' . $bulan[(int) $var[1]] . ' ' . $var[0];
    }

    public function autoNaKaryawan()
    {
        DB::beginTransaction();
        try {
            MasterKaryawan::whereDate('effective_date', '<=', date('Y-m-d'))->update([
                'is_active' => false
            ]);

            DB::commit();
            return response()->json([
                'message' => 'Karyawan telah di non aktif kan'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan saat memperbarui karyawan',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
