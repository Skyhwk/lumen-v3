<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DraftAir;
// use App\Models\User;
use App\Models\ErgonomiHeader;
use App\Models\LhpsAirHeader;
use App\Models\LhpsGetaranHeader;
use App\Models\LhpsHygieneSanitasiHeader;
use App\Models\LhpsIklimHeader;
use App\Models\LhpsKebisinganHeader;
use App\Models\LhpsLingHeader;
use App\Models\LhpsMedanLMHeader;
use App\Models\LhpsMicrobiologiHeader;
use App\Models\LhpsSwabTesHeader;
use App\Models\LhpUdaraPsikologiHeader;
use App\Models\MasterKaryawan;
use App\Models\OrderDetail;
use App\Models\ParameterFdl;
use App\Models\TicketRLHP;
use App\Services\GetAtasan;
use App\Services\GetBawahan;
use App\Services\Notification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Yajra\Datatables\Datatables;

class RekapRLHPController extends Controller
{
 
    public function index(Request $request)
    {
        try {
            $department = $request->attributes->get('user')->karyawan->id_department;

            if (($department == 17 || $department == 7 || in_array($this->user_id, [13])) && ! in_array($this->user_id, [10, 15, 93, 123])) {
                $data = TicketRLHP::where('is_active', true)
                    ->whereIn('status', ['DONE', 'REJECT', 'VOID'])
                    ->orderByDesc('id');

                return DataTables::of($data)
                    ->editColumn('data_perusahaan', function ($row) {
                        return $row->data_perusahaan
                            ? json_decode($row->data_perusahaan, true)
                            : [];
                    })
                    ->editColumn('perubahan_data', function ($row) {
                        return $row->perubahan_data
                            ? json_decode($row->perubahan_data, true)
                            : [];
                    })
                    ->editColumn('perubahan_tanggal', function ($row) {
                        return $row->perubahan_tanggal
                            ? json_decode($row->perubahan_tanggal, true)
                            : [];
                    })
                    ->addColumn('reff', function ($row) {
                        $filePath = public_path('ticket_rlhp/' . $row->filename);
                        return file_exists($filePath) ? file_get_contents($filePath) : 'File not found';
                    })
                    ->make(true);

            } else {

                $grade = $this->grade;
                // dd($grade);
                if ($grade == 'MANAGER') {
                    $getBawahan = GetBawahan::where('id', $this->user_id)->get()
                        ->pluck('nama_lengkap')
                        ->toArray();
                } else {
                    $getBawahan = [$this->karyawan];
                }

                $data = TicketRLHP::whereIn('request_by', $getBawahan)
                    ->where('is_active', true)
                    ->orderByDesc('id');

                return DataTables::of($data)
                    ->editColumn('data_perusahaan', function ($row) {
                        return $row->data_perusahaan
                            ? json_decode($row->data_perusahaan, true)
                            : [];
                    })
                    ->editColumn('perubahan_data', function ($row) {
                        return $row->perubahan_data
                            ? json_decode($row->perubahan_data, true)
                            : [];
                    })
                    ->editColumn('perubahan_tanggal', function ($row) {
                        return $row->perubahan_tanggal
                            ? json_decode($row->perubahan_tanggal, true)
                            : [];
                    })
                    ->addColumn('reff', function ($row) {
                        $filePath = public_path('ticket_rlhp/' . $row->filename);
                        return file_exists($filePath) ? file_get_contents($filePath) : 'File not found';
                    })
                    ->addColumn('can_approve', function ($row) use ($getBawahan) {
                        return in_array($row->created_by, $getBawahan) && $this->karyawan != $row->created_by;
                    })
                    ->make(true);
            }

        } catch (\Exception $e) {
            return response()->json([
                'data'    => [],
                'message' => $e->getMessage(),
            ], 201);
        }
    }
}
