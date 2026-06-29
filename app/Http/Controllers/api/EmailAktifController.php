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
                'master_karyawan.nama_lengkap as nama_karyawan',
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
        return DataTables::of($query)->make(true);
    }

    public function listIndex(Request $request)
    {
        $idKaryawan = $request->input('id_karyawan');
        $folder = $request->input('folder');

        if (!$idKaryawan || !$folder) {
            return response()->json(['message' => 'Karyawan dan Folder harus ditentukan'], 400);
        }

        $query = MailListIndex::query()
            ->where('id_karyawan', $idKaryawan)
            ->where('folder', $folder)
            ->select([
                'id',
                'id_karyawan',
                'folder',
                'uid',
                'seq_num',
                'from_addr',
                'to_addr',
                'subject',
                'email_date',
                'size_bytes',
                'is_seen'
            ]);

        return DataTables::of($query)->make(true);
    }

    public function clearData(Request $request)
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
                ->whereIn('folder', ['inbox', 'outbox', 'spam', 'trash'])
                ->update([
                    'total' => 0,
                    'unread_count' => 0,
                    'indexed_count' => 0,
                    'last_uid' => 0,
                    'min_seq' => 0,
                    'max_seq' => 0,
                ]);

            DB::commit();
            return response()->json(['message' => 'Data email karyawan berhasil direset ke 0.'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal mereset data: ' . $e->getMessage()], 500);
        }
    }
}
