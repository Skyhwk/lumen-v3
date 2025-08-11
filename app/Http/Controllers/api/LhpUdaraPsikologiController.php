<?php

namespace App\Http\Controllers\api;



use App\Http\Controllers\Controller; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

use App\Models\GenerateLink;
use App\Models\MasterKaryawan;
use App\Models\OrderHeader;
use App\Models\OrderDetail;
use App\Models\PsikologiHeader;
use App\Models\LhpUdaraPsikologiHeader;
use App\Models\LhpUdaraPsikologiDetail;

use App\Services\SendEmail;
use App\Services\GenerateQrDocumentLhpp;

use App\Jobs\JobPrintLhp;
use App\Jobs\RenderLhpp;

class LhpUdaraPsikologiController extends Controller
{
	public function index(Request $request)
	{
		$data = OrderDetail::with('dataPsikologi', 'lhp_psikologi')
			->where('is_active', $request->is_active)
			->where('kategori_2', '4-Udara')
			->where('status', 3)
			->whereJsonContains('parameter', [
				"318;Psikologi"
			])
			->whereNotNull('tanggal_terima')
			->select('no_order', 'no_quotation', 'cfr', "tanggal_sampling", "nama_perusahaan", DB::raw('COUNT(*) as total'))
			->groupBy('no_order', 'no_quotation', 'cfr', "tanggal_sampling", "nama_perusahaan")
			->get();

		return Datatables::of($data)->make(true);
	}


}