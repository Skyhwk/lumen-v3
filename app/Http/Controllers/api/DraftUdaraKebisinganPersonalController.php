<?php

namespace App\Http\Controllers\api;

use App\Models\HistoryAppReject;
use App\Models\KonfirmasiLhp;
use App\Models\LhpsKebisinganHeader;
use App\Models\LhpsKebisinganDetail;
use App\Models\LhpsKebisinganCustom;

use App\Helpers\EmailLhpRilisHelpers;

use App\Models\LhpsKebisinganHeaderHistory;
use App\Models\LhpsKebisinganDetailHistory;


use App\Models\MasterSubKategori;
use App\Models\OrderHeader;
use App\Models\OrderDetail;
use App\Models\MasterRegulasi;
use App\Models\MasterKaryawan;
use App\Models\PengesahanLhp;
use App\Models\QrDocument;
use App\Models\KebisinganHeader;
use App\Models\Parameter;

use App\Models\GenerateLink;
use App\Services\PrintLhp;
use App\Services\SendEmail;
use App\Services\GenerateQrDocumentLhp;
use App\Services\LhpTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Jobs\CombineLHPJob;
use App\Models\LinkLhp;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class DraftUdaraKebisinganPersonalController extends Controller
{
    public function index(Request $request)
    {
        DB::statement("SET SESSION sql_mode = ''");

        $user = $request->user;

        $orderHeader = OrderHeader::where('user_id', $user->id)->where('status', 2)->get();

        return response()->json([
            'status' => true,
            'data' => $orderHeader,
        ]);
    }
}