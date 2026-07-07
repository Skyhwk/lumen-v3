<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MailListIndex;
use Yajra\DataTables\Facades\DataTables;
use DB;

class EmailAktifController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('master_karyawan')
            ->join('mail_folder_meta as primary_meta', 'master_karyawan.id', '=', 'primary_meta.id_karyawan')
            ->leftJoin('mail_folder_meta as inbox_meta', function ($join) {
                $join->on('master_karyawan.id', '=', 'inbox_meta.id_karyawan')
                     ->where('inbox_meta.folder', '=', 'inbox');
            })
            ->leftJoin('mail_folder_meta as outbox_meta', function ($join) {
                $join->on('master_karyawan.id', '=', 'outbox_meta.id_karyawan')
                     ->where('outbox_meta.folder', '=', 'outbox');
            })
            ->leftJoin('mail_folder_meta as spam_meta', function ($join) {
                $join->on('master_karyawan.id', '=', 'spam_meta.id_karyawan')
                     ->where('spam_meta.folder', '=', 'spam');
            })
            ->leftJoin('mail_folder_meta as trash_meta', function ($join) {
                $join->on('master_karyawan.id', '=', 'trash_meta.id_karyawan')
                     ->where('trash_meta.folder', '=', 'trash');
            })
            ->select([
                'master_karyawan.id as id_karyawan',
                'master_karyawan.nama_lengkap',
                'master_karyawan.nik_karyawan',
                'master_karyawan.email',
                'master_karyawan.is_active',
                DB::raw('COALESCE(inbox_meta.total, 0) as inbox_total'),
                DB::raw('COALESCE(outbox_meta.total, 0) as outbox_total'),
                DB::raw('COALESCE(spam_meta.total, 0) as spam_total'),
                DB::raw('COALESCE(trash_meta.total, 0) as trash_total'),
            ])
            ->where(function ($q) {
                $q->where('master_karyawan.is_active', '=', 1)
                  ->orWhere(function ($sub) {
                      $sub->where('master_karyawan.is_active', '=', 0)
                          ->where(function ($sub2) {
                              $sub2->where(DB::raw('COALESCE(inbox_meta.total, 0)'), '>', 0)
                                   ->orWhere(DB::raw('COALESCE(outbox_meta.total, 0)'), '>', 0)
                                   ->orWhere(DB::raw('COALESCE(spam_meta.total, 0)'), '>', 0)
                                   ->orWhere(DB::raw('COALESCE(trash_meta.total, 0)'), '>', 0);
                          });
                  });
            })
            ->groupBy([
                'master_karyawan.id',
                'master_karyawan.nama_lengkap',
                'master_karyawan.nik_karyawan',
                'master_karyawan.email',
                'master_karyawan.is_active',
                'inbox_meta.total',
                'outbox_meta.total',
                'spam_meta.total',
                'trash_meta.total'
            ]);
        return DataTables::of($query)
            ->filterColumn('inbox_total', function ($query, $keyword) {
                $query->whereRaw('COALESCE(inbox_meta.total, 0) LIKE ?', ["%{$keyword}%"]);
            })
            ->filterColumn('outbox_total', function ($query, $keyword) {
                $query->whereRaw('COALESCE(outbox_meta.total, 0) LIKE ?', ["%{$keyword}%"]);
            })
            ->filterColumn('spam_total', function ($query, $keyword) {
                $query->whereRaw('COALESCE(spam_meta.total, 0) LIKE ?', ["%{$keyword}%"]);
            })
            ->filterColumn('trash_total', function ($query, $keyword) {
                $query->whereRaw('COALESCE(trash_meta.total, 0) LIKE ?', ["%{$keyword}%"]);
            })
            ->orderColumn('inbox_total', function ($query, $order) {
                $query->orderByRaw('COALESCE(inbox_meta.total, 0) ' . $order);
            })
            ->orderColumn('outbox_total', function ($query, $order) {
                $query->orderByRaw('COALESCE(outbox_meta.total, 0) ' . $order);
            })
            ->orderColumn('spam_total', function ($query, $order) {
                $query->orderByRaw('COALESCE(spam_meta.total, 0) ' . $order);
            })
            ->orderColumn('trash_total', function ($query, $order) {
                $query->orderByRaw('COALESCE(trash_meta.total, 0) ' . $order);
            })
            ->make(true);
    }

    public function delete(Request $request)
    {
        $idKaryawan = $request->input('id_karyawan');
        if (!$idKaryawan) {
            return response()->json(['message' => 'ID Karyawan wajib diisi'], 400);
        }

        DB::beginTransaction();
        try {
            DB::table('mail_list_index')->where('id_karyawan', $idKaryawan)->delete();
            
            DB::table('mail_folder_meta')
                ->where('id_karyawan', $idKaryawan)
                ->delete();

            $files = glob(storage_path("repository/setting_mail/{$idKaryawan}.*"));
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            DB::commit();
            return response()->json(['message' => 'Data email karyawan berhasil dihapus.'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menghapus data: ' . $e->getMessage()], 500);
        }
    }
}
