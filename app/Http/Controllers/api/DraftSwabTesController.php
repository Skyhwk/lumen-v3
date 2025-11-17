<?php
namespace App\Http\Controllers\api;

use App\Helpers\HelperSatuan;
use App\Http\Controllers\Controller;
use App\Jobs\CombineLHPJob;
use App\Models\DirectLainHeader;
use App\Models\GenerateLink;
use App\Models\HistoryAppReject;
use App\Models\KonfirmasiLhp;
use App\Models\LhpsLingCustom;
use App\Models\LhpsLingDetail;
use App\Models\LhpsLingDetailHistory;
use App\Models\LhpsLingHeader;
use App\Models\LhpsLingHeaderHistory;
use App\Models\LingkunganHeader;
use App\Models\LinkLhp;
use App\Models\MasterBakumutu;
use App\Models\MasterKaryawan;
use App\Models\MasterRegulasi;
use App\Models\MasterSubKategori;
use App\Models\MetodeSampling;
use App\Models\OrderDetail;
use App\Models\Parameter;
use App\Models\PartikulatHeader;
use App\Models\PengesahanLhp;
use App\Models\QrDocument;
use App\Models\Subkontrak;
use App\Services\GenerateQrDocumentLhp;
use App\Services\LhpTemplate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class DraftSwabTesController extends Controller
{

    public function index(Request $request)
    {
        $data = OrderDetail::selectRaw('
            cfr, 
            GROUP_CONCAT(no_sampel SEPARATOR ",") as no_sampel,
            MAX(nama_perusahaan) as nama_perusahaan,
            MAX(konsultan) as konsultan,
            MAX(no_quotation) as no_quotation,
            MAX(no_order) as no_order,
            MAX(parameter) as parameter,
            MAX(regulasi) as regulasi,
            MAX(kategori_2) as kategori_2,
            MAX(kategori_3) as kategori_3,
            GROUP_CONCAT(tanggal_sampling SEPARATOR ",") as tanggal_tugas,
            GROUP_CONCAT(tanggal_terima SEPARATOR ",") as tanggal_terima
        ')
            ->with([
                'lhps_swab_udara',
                'orderHeader:id,nama_pic_order,jabatan_pic_order,no_pic_order,email_pic_order,alamat_sampling',
            ])
            ->where('is_active', true)
            ->where('kategori_3', '46-Udara Swab Test')
            ->where('status', 2)
            ->groupBy('cfr')
            ->get();

        return Datatables::of($data)->make(true);
    }

}