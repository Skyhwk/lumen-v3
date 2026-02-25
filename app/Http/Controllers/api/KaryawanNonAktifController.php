<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\MasterKaryawan;
use App\Models\MasterCabang;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use \App\Services\MpdfService as PDF;

class KaryawanNonAktifController extends Controller
{
    public function index(Request $request)
    {
        $this->autoNaKaryawan();

        $data = MasterKaryawan::with('divisi', 'jabatan', 'cabang')
            ->where('active', false)
            ->where('is_active', false)
            ->where('deleted_by', null)
            ->get();

        return Datatables::of($data)->make(true);
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
                throw new Exception("Invalid status_karyawan value provided.");
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
        } catch (Exception $e) {
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
            $hrd = MasterKaryawan::with('jabatan', 'department')->where('id', $this->user_id)->first();

            $cabang = MasterCabang::where('id', $this->idcabang)->first();

            $mpdfConfig = array(
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_header' => 3,
                'setAutoTopMargin' => 'stretch',
                'setAutoBottomMargin' => 'stretch',
                'orientation' => 'P'
            );
            $pdf = new PDF($mpdfConfig);

            $fileName = $data->nama_lengkap . '.pdf';

            $pdf->SetHTMLHeader('
                    <table width="100%">
                        <tr>
                            <td class="text-left text-wrap" style="width: 33.33%;"><img class="img_0" src="' . public_path() . '/logo/isl_logo.png" alt="ISL">/td>
                            <td style="width: 40%; text-align: center;"><h3>SURAT PEMBERITAHUAN</h4><p style="text-align: center !important;">Ref : ISL/&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;/' . DATE('y') . '-' . self::romawi(DATE('m')) . '/&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</p></td>
                            <td style="text-align: right;"></td>
                        </tr>
                    </table>
                ');

            $pdf->WriteHTML('
                <br>
                <p>Perihal : <b>Karyawan Keluar</b></p>

                <p>Kepada Seluruh Karyawan,<br> <b>PT Inti Surya Laboratorium</b></p>

                <p style="text-align: justify;">Sehubungan dengan ketentuan, peraturan, dan serta kebijakan perusahaan <b>perihal hubungan dengan pihak luar, </b>pihak perusahaan, khususnya dari pihak <b>HRD, </b>mengingatkan dan serta menegaskan kembali kepada seluruh karyawan bahwa <b>tidak diperkenankan dalam bentuk apapun juga untuk mengadakan komunikasi perihal pekerjaan dengan karyawan yang telah keluar dan atau tidak bekerja pada perusahaan, </b>agar karyawan yang sedang bekerja dapat berlaku dan bertindak dengan baik dan dengan sebagaimana mestinya.</p>

                <p>Perihal nama karyawan yang telah keluar, berikut kami sampaikan informasinya sebagai berikut : <br>
                <table style="font-size: 0.8rem !important;">
                    <tr>
                        <td>Nama</td>
                        <td>: ' . $data->nama_lengkap . '</td>
                    </tr>
                    <tr>
                        <td>NIK</td>
                        <td>: ' . $data->nik_karyawan . '</td>
                    </tr>
                    <tr>
                        <td>Divisi / Posisi</td>
                        <td>: ' . $data->department . ' / ' . $data->jabatan . '</td>
                    </tr>
                    <tr>
                        <td>Terakhir Bekerja</td>
                        <td>: ' . self::tanggal_indonesia($data->effective_date) . '</td>
                    </tr>
                    <tr>
                        <td>Alasan</td>
                        <td>: ' . $data->reason_non_active . '</td>
                    </tr>
                </table>

                </p>');
            if ($data->notes != null || $data->notes != '') {
                $pdf->WriteHTML('<p>Note : ' . $data->notes . '</p>');
            }
            $pdf->WriteHTML(' <p>Dihimbau kepada seluruh karyawan agar dapat diperhatikan, dimengerti dan dipahami serta dapat bekerja dengan sebaik-baiknya dan sebenar-benarnya.</p>
                <br>
                <p>Tangerang, ' . self::tanggal_indonesia(date('Y-m-d')) . '</p>
                <br>
                <br>
                <p><b><u>' . $hrd->nama_lengkap . '</u></b><br>
                ' . $hrd->jabatan . '</p>
                ');

            $pdf->SetHTMLFooter('<p class="footer">' . $cabang->alamat_cabang . '<br>P : ' . $cabang->tlp_cabang . ' - www.intilab.com - contact@intilab.com</p>');

            $pdf->Output('dokumen/' . $fileName);
            return response()->json([
                'message' => 'pdf hasbeen generate',
                'link' => $fileName
            ], 200);
        } catch (\Exception $e) {
            dd($e);
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
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan saat memperbarui karyawan',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
