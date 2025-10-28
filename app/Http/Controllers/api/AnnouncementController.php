<?php


namespace App\Http\Controllers\api;

use App\Models\Announcement;
use App\Models\MasterDivisi;
use App\Models\MasterKaryawan;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;




class AnnouncementController extends Controller
{

    public function index(Request $request)
    {

        $data = Announcement::whereYear('created_at', $request->periode)
            ->orderBy('id', 'desc')

            ->get();

        return Datatables::of($data)
            ->addColumn('reff', function ($row) {
                $filePath = public_path('announcement/' . $row->filename);
                if (file_exists($filePath) && is_file($filePath)) {
                    return file_get_contents($filePath);
                } else {
                    return 'File not found';
                }
            })->make(true);
    }

    public function saveAnnouncement(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {

            $employee = MasterKaryawan::where('is_active', true)->pluck('id')
                ->map(fn($id) => (string) $id)
                ->toArray();
            if (empty($request->id)) {
                $announcement = new Announcement();
                $announcement->delivery = json_encode($employee);
                $announcement->created_at = Carbon::now();
                $announcement->created_by = $this->karyawan;

                $announcement->description = $request->judul;

                $microtime = str_replace(".", "", microtime(true));
                $uniq_id = $microtime;
                $filename = $microtime . '.txt';
                $content = $request->detail;
                $contentDir = 'announcement';

                if (!file_exists(public_path($contentDir))) {
                    mkdir(public_path($contentDir), 0777, true);
                }

                file_put_contents(public_path($contentDir . '/' . $filename), $content);

                $announcement->filename = $filename;

                $announcement->save();
                DB::commit();

                return response()->json(['message' => 'Announcement Berhasil Ditambahkan!', 'status' => 200], 200);


            } else {
                $announcement = Announcement::find($request->id);
                $announcement->description = $request->judul;
                $announcement->is_read = null;
                $announcement->is_deleted = null;
                $announcement->updated_at = Carbon::now();
                $announcement->updated_by = $this->karyawan;

                $microtime = str_replace(".", "", microtime(true));
                $uniq_id = $microtime;
                $filename = $microtime . '.txt';
                $content = $request->detail;
                $contentDir = 'announcement';

                if (!file_exists(public_path($contentDir))) {
                    mkdir(public_path($contentDir), 0777, true);
                }

                file_put_contents(public_path($contentDir . '/' . $filename), $content);

                $announcement->filename = $filename;

                $announcement->save();
                DB::commit();

                return response()->json(['message' => 'Announcement Berhasil Diubah!', 'status' => 201], 201);

            }
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => "Terjadi Kesalahan",
                "error" => $th->getMessage()
            ], 500);
        }
    }

    public function deleteAnnouncement(Request $request)
    {
        DB::beginTransaction();
        try {
            $announcement = Announcement::find($request->id);
            $announcement->delete();
            DB::commit();
            return response()->json(['message' => 'Announcement Berhasil Dihapus!'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => "Terjadi Kesalahan",
                "error" => $th->getMessage()
            ], 500);
        }
    }


}