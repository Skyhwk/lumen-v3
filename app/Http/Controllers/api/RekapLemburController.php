<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use Mpdf;
use DataTables;

use App\Models\FormDetail;
use App\Models\MasterDivisi;
use App\Models\MasterKaryawan;

class RekapLemburController extends Controller
{
    public function index()
    {
        $rekap = FormDetail::on('android_intilab')
            ->select('tanggal_mulai as tanggal', DB::raw('count(user_id) as jumlah'))
            ->whereNotNull('approved_finance_by')
            ->where('is_active', true)
            ->groupBy('tanggal_mulai')
            ->orderByDesc('tanggal_mulai');

        return DataTables::of($rekap)->make(true);
    }

    private function getRekap($date)
    {
        $divisi = MasterDivisi::where('is_active', true)->get();

        $rekap = [];
        foreach ($divisi as $item) {
            $detail = FormDetail::on('android_intilab')
                ->where('department_id', $item->id)
                ->where('tanggal_mulai', $date)
                ->whereNotNull('approved_finance_by')
                ->where('is_active', true)
                ->get();

            if ($detail->isNotEmpty()) {
                $karyawan = MasterKaryawan::whereIn('id', $detail->pluck('user_id')->unique()->toArray())->get();
                $detail->map(function ($item) use ($karyawan) {
                    $item->karyawan = $karyawan->where('id', $item->user_id)->first();
                });

                $rekap[] = [
                    'kode_divisi' => $item->kode_divisi,
                    'nama_divisi' => $item->nama_divisi,
                    'detail' => $detail->toArray()
                ];
            }
        }

        return $rekap;
    }

    public function detail(Request $request)
    {
        return response()->json(['data' => $this->getRekap($request->tanggal), 'message' => 'Data retrieved successfully'], 200);
    }

    public function exportPdf(Request $request)
    {
        $mpdf = new Mpdf();

        $mpdf->WriteHTML(view('pdf.rekap_lembur', [
            'data' => $this->getRekap($request->tanggal),
            'tanggal' => $request->tanggal
        ])->render());

        return $mpdf->Output('Rekap_Lembur_' . $request->tanggal . '.pdf', 'D');
    }

    public function generatePdf(Request $request)
    {
        $mpdf = new Mpdf();

        $mpdf->WriteHTML(view('pdf.rekap_lembur', [
            'data' => $this->getRekap($request->tanggal),
            'tanggal' => $request->tanggal
        ])->render());

        $filename = 'Rekap_Lembur_' . $request->tanggal . '.pdf';
        $path = public_path('rekap_lembur');

        if (!file_exists($path)) mkdir($path, 0777, true);

        $mpdf->Output($path . '/' . $filename, \Mpdf\Output\Destination::FILE);

        return response()->json(['data' => $filename, 'message' => 'PDF generated successfully'], 200);
    }
}
