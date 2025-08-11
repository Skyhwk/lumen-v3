<?php

namespace App\Http\Controllers\api;

use App\Models\FollowUpWebsite;
use App\Models\MasterKategori;
use App\Services\SendEmail;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;

class FollowUpWebsiteController extends Controller
{
    public function indexFollowed()
    {
        $data = FollowUpWebsite::where('is_active', true)->where('status', true);
        return Datatables::of($data)->make(true);
    }

    public function index()
    {
        $data = FollowUpWebsite::where('is_active', true)->where('status', false);
        return Datatables::of($data)->make(true);
    }

    public function getKategori(Request $request)
    {
        $data = MasterKategori::with('subCategories')
            ->whereHas('subCategories')
            ->where('is_active', true)
            ->get();
        $results = [];
        foreach ($data as $key => $value) {
            $results[] = [
                'id' => $value->id,
                'text' => $value->nama_kategori,
                'children' => $value->subCategories->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'text' => $item->nama_sub_kategori
                    ];
                })->toArray()
            ];
        }
        return response()->json($results, 200);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {

            $categories = MasterKategori::with([
                'subCategories' => function ($query) use ($request) {
                    $query->whereIn('id', $request->kategori);
                }
            ])
                ->whereHas('subCategories', function ($query) use ($request) {
                    $query->whereIn('id', $request->kategori);
                })
                ->where('is_active', true)
                ->get();

            foreach ($categories as $key => $value) {
                $results[] = [
                    'kategori' => $value->id . '-' . $value->nama_kategori,
                    'sub_kategori' => $value->subCategories->map(function ($item) {
                        return $item->id . '-' . $item->nama_sub_kategori;
                    })->toArray()
                ];
            }

            $data = FollowUpWebsite::create([
                'nama_pic' => $request->nama_pic,
                'nama_perusahaan' => $request->nama_perusahaan,
                'alamat_perusahaan' => $request->alamat_perusahaan,
                'no_perusahaan' => $request->no_perusahaan,
                'email_pic' => $request->email_pic,
                'no_pic' => $request->no_pic,
                'sumber_informasi' => $request->sumber,
                'kategori' => json_encode($results),
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            if ($data) {
                $body = self::generateEmailBody($data);

                $email = SendEmail::where('to', 'faidhah@intilab.com')
                    ->where('subject', 'Pemberitahuan Permintaan Penawaran Baru')
                    ->where('bcc', ['winda@intilab.com'])
                    ->where('body', $body)
                    ->where('karyawan', env('MAIL_NOREPLY_USERNAME'))
                    ->noReply()
                    ->send();

                DB::commit();
                return response()->json([
                    'message' => 'Pesan anda berhasil dikirim, tim kami akan segera menghubungi anda dalam 1x24 jam',
                    'status' => 'success'
                ], 200);
            } else {
                DB::rollback();
                return response()->json([
                    'message' => 'Terjadi Kesalahan pada server kami',
                    'status' => 'failed'
                ], 401);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }

    public function update(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = FollowUpWebsite::where('id', $request->id)->update([
                'status' => true,
                'approved_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'approved_by' => $this->karyawan
            ]);
            if ($data) {
                DB::commit();
                return response()->json([
                    'message' => 'Data Follow Up Website berhasil di Approve',
                    'status' => 'success'
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Data Follow Up Website gagal di Approve',
                    'status' => 'failed'
                ], 401);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }


    private function generateEmailBody($data)
    {
        $body = '<!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Pemberitahuan Permintaan Penawaran Baru</title>
        </head>
        <body style="font-family: sans-serif;">
            <p>Yth. Tim Sales,</p>
            <p>Permintaan penawaran baru dari <b>' . $data->nama_perusahaan . '</b> diterima.</p>
            <p>Detail singkat mengenai permintaan ini adalah sebagai berikut:</p>
            <ul>
                <li><b>Nama Perusahaan:</b> ' . $data->nama_perusahaan . '</li>
                <li><b>Alamat Perusahaan:</b> ' . $data->alamat_perusahaan . '</li>
                <li><b>Telfon Perusahaan:</b> ' . $data->no_perusahaan . '</li>
                <li><b>Nama PIC:</b> ' . $data->nama_pic . '</li>
                <li><b>Email PIC:</b> ' . $data->email_pic . '</li>
                <li><b>Telfon PIC:</b> ' . $data->no_pic . '</li>
                <li><b>Jenis Layanan yang Diminta:</b>
                    <ul>';
        foreach (json_decode($data->kategori) as $item) {
            $body .= '<li><b>' . explode('-', $item->kategori)[1];
            if (count($item->sub_kategori) > 0) {
                $body .= '</b><ul>';
                foreach ($item->sub_kategori as $sub_kategori) {
                    $body .= '<li><b>' . explode('-', $sub_kategori)[1] . '</b></li>';
                }
                $body .= '</ul>';
            }
            $body .= '</li>';
        }
        $body .= '</ul>
                </li>
                <li><b>Tanggal Permintaan:</b> ' . $data->created_at . '</li>
            </ul>
            <p>Mohon agar tim segera menindaklanjuti permintaan ini dan mulai mempersiapkan penawaran yang sesuai.</p>
            <p>Terima kasih atas perhatian dan kerjasamanya.</p>
        </body>
        </html>';

        return $body;
    }
}