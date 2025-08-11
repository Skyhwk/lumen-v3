<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SalesIn;
use App\Models\SalesInDetail;
use App\Models\Invoice;
use App\Models\OrderHeader;
use Carbon\Carbon;
use DataTables;
use Illuminate\Support\Facades\DB;

class UangMasukController extends Controller
{
    public function index(Request $request)
    {
        $data = SalesIn::with(['detail'])->where('is_active', true)->whereYear('tanggal_masuk', $request->year);
        if($request->status == 'Undefined') $data = $data->whereIn('status', ["Undefined", "Quotation"]);
        if($request->status != 'Undefined') $data = $data->whereIn('status', ["Invoice", "Done"]);
        $data = $data->orderBy('id', 'DESC');

        return datatables()->of($data)->make(true);
    }

    public function getPenawaran(Request $request)
    {
        $data = OrderHeader::with(['getInvoice'])
            ->where('is_active', true)
            ->where('no_document', 'like', '%'.$request->search.'%')
            ->groupBy('no_document', 'no_order', 'nama_perusahaan', 'biaya_akhir')
            ->orderBy('no_document', 'ASC')
            ->get(['no_document', 'no_order', 'nama_perusahaan', 'biaya_akhir' ]);

        return response()->json([
            'message' => 'data hasbeen show',
            'data' => $data
        ], 201);
    }

    public function prosess(Request $request)
    {
        DB::beginTransaction();
        try {
            if(isset($request->no_pelunasan_invoice) && count($request->no_pelunasan_invoice) > 0){
                $no = 0;
                $deleteOld = SalesInDetail::where('id_header', $request->id)->delete();
                $updateHeader = SalesIn::where('id', $request->id)->first();
                $array_id_invoice = (\explode(',', $request->no_invoice[0])) ?? null;
                foreach ($request->no_pelunasan_invoice as $no_invoice => $nilai_pelunasan) {

                    $dataDetail = new SalesInDetail();
                    $dataDetail->id_header = $request->id;
                    $dataDetail->no_penawaran = $request->no_penawaran[$no] ?? null;
                    $dataDetail->id_invoice = $array_id_invoice[$no] ?? null;
                    $dataDetail->no_invoice = $no_invoice;
                    $dataDetail->nominal_pelunasan = \str_replace(['.',','] , ['', '.'], $nilai_pelunasan);
                    $dataDetail->nilai_pengurangan = empty($request->nilai_pengurangan[$no_invoice]) ? null : \str_replace(['.',','] , ['', '.'], $request->nilai_pengurangan[$no_invoice]) ?? null;
                    $dataDetail->kurang_bayar = empty($request->kurang_bayar[$no_invoice]) ? null : \str_replace(['.',','] , ['', '.'], $request->kurang_bayar[$no_invoice]) ?? null;
                    $dataDetail->lebih_bayar = empty($request->lebih_bayar[$no_invoice]) ? null : \str_replace(['.',','] , ['', '.'], $request->lebih_bayar[$no_invoice]) ?? null;
                    $dataDetail->keterangan = empty($request->keterangan) ? null : $request->keterangan ?? null;
                    $dataDetail->proccessed_by = $this->karyawan;
                    $dataDetail->proccessed_at = Carbon::now()->format('Y-m-d H:i:s');
                    $dataDetail->save();

                    $getNilai = DB::table('record_pembayaran_invoice')
                        ->select(DB::raw('SUM(nilai_pembayaran) AS nilai_pembayaran'))
                        ->where('no_invoice', $no_invoice)
                        ->where('is_active', true)
                        ->groupBy('no_invoice', )
                        ->first();

                    if ($getNilai != null) {
                        $getSisa = DB::table('record_pembayaran_invoice')
                            ->select(DB::raw('sisa_pembayaran'))
                            ->where('no_invoice', $no_invoice)
                            ->where('is_active', true)
                            ->orderBy('id', 'DESC')
                            ->first();

                        if ($getSisa->sisa_pembayaran != 0) {
                            $sisaPembayaran = $getSisa->sisa_pembayaran - floatval(\str_replace(['.',','] , ['', '.'], $nilai_pelunasan)) - floatval(str_replace(['.', ','], ['', '.'], $request->nilai_pengurangan[$no_invoice] ?? 0));
                        } else {
                            $sisaPembayaran = $getSisa->sisa_pembayaran;
                        }

                        $nilaiPembayaran = $getNilai->nilai_pembayaran + floatval(\str_replace(['.',','] , ['', '.'], $nilai_pelunasan));

                    } else {

                        $nilaiPembayaran = floatval(\str_replace(['.',','] , ['', '.'], $nilai_pelunasan));
                        $sisaPembayaran = floatval(\str_replace(['.',','] , ['', '.'], $request->nilai_tagihan[$no_invoice])) - floatval(\str_replace(['.',','] , ['', '.'], $nilai_pelunasan)) - floatval(str_replace(['.', ','], ['', '.'], $request->nilai_pengurangan[$no_invoice] ?? 0));

                    }

                    //insert ke tabel record pembayaran invoice
                    $insert = [
                        'no_invoice' => $no_invoice,
                        'tgl_pembayaran' => $updateHeader->tanggal_masuk,
                        'nilai_pembayaran' => \str_replace(['.',','] , ['', '.'], $nilai_pelunasan),
                        'nilai_pengurangan' => empty($request->nilai_pengurangan[$no_invoice]) ? null : \str_replace(['.',','] , ['', '.'], $request->nilai_pengurangan[$no_invoice]) ?? 0,
                        'lebih_bayar' => empty($request->lebih_bayar[$no_invoice]) ? null : \str_replace(['.',','] , ['', '.'], $request->lebih_bayar[$no_invoice]) ?? null,
                        'sisa_pembayaran' => $sisaPembayaran,
                        'keterangan' => $request->keterangan,
                        'status' => 0,
                        'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        'created_by' => $this->karyawan,
                    ];

                    DB::table('record_pembayaran_invoice')
                        ->insert($insert);

                    //update ke tabel invoice
                    $update = [
                        'tgl_pelunasan' => $updateHeader->tanggal_masuk,
                        'nilai_pelunasan' => $nilaiPembayaran + floatval(str_replace(['.', ','], ['', '.'], $request->nilai_pengurangan[$no_invoice] ?? 0)),
                        'keterangan_pelunasan' => $request->keterangan,
                    ];

                    DB::table('invoice')
                        ->where('no_invoice', $no_invoice)
                        ->update($update);

                    $no++;
                }
                
                $updateHeader->status = "Done";
                $updateHeader->save();
            } else {
                foreach($request->no_penawaran as $key => $no_penawaran){
                    $dataDetail = new SalesInDetail();
                    $dataDetail->id_header = $request->id;
                    $dataDetail->no_penawaran = $no_penawaran;
                    $dataDetail->keterangan = empty($request->keterangan) ? null : $request->keterangan ?? null;
                    $dataDetail->proccessed_by = $this->userid;
                    $dataDetail->proccessed_at = Carbon::now()->format('Y-m-d H:i:s');
                    $dataDetail->save();
                }

                $updateHeader = SalesIn::where('id', $request->id)->first();
                $updateHeader->status = "Quotation";
                $updateHeader->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Data has been processed successfully.'
            ], 201);
        } catch (\Throwable $th) {
            DB::rollback();
            dd($th);
            return response()->json([
                'message' => $th->getMessage()
            ], 401);
        }
    }
}