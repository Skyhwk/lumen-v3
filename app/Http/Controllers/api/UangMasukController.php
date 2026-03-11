<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;

use App\Models\SalesIn;
use App\Models\Invoice;
use App\Models\Withdraw;
use App\Models\OrderHeader;
use App\Models\SalesInDetail;
use App\Models\RecordPembayaranInvoice;

class UangMasukController extends Controller
{
    public function index(Request $request)
    {
        $data = SalesIn::with('detail')
            ->whereYear('tanggal_masuk', $request->year)
            ->whereIn('status', $request->status == 'Undefined' ? ["Undefined", "Incomplete", "Quotation"] : ["Invoice", "Done"])
            ->where('is_active', true)
            ->orderBy('id', 'DESC');

        return datatables()->of($data)->make(true);
    }

    public function getPenawaran(Request $request)
    {
        $data = OrderHeader::with(['getInvoice.recordWithdraw'])
            ->where('is_active', true)
            ->where('no_document', 'like', '%' . $request->search . '%')
            ->groupBy('no_document', 'no_order', 'nama_perusahaan', 'biaya_akhir')
            ->orderBy('no_document', 'ASC')
            ->get(['no_document', 'no_order', 'nama_perusahaan', 'biaya_akhir'])
            ->map(function ($item) {
                if (!$item->biaya_akhir)
                    return null;
                if ($item->getInvoice->isEmpty())
                    return null;

                $filteredInvoices = $item->getInvoice->filter(function ($inv) {
                    $tagihan = floatval($inv->nilai_tagihan ?? 0);
                    $totalWithdraw = $inv->recordWithdraw ? $inv->recordWithdraw->sum('nilai_pembayaran') : 0;
                    $pelunasan = $totalWithdraw > 0 ? floatval($totalWithdraw) + floatval($inv->nilai_pelunasan ?? 0) : floatval($inv->nilai_pelunasan ?? 0);

                    return $tagihan > $pelunasan;
                })->values();

                if ($filteredInvoices->isNotEmpty()) {
                    $item->setRelation('getInvoice', $filteredInvoices);
                    return $item;
                }

                return null;
            })->filter()->values();

        return response()->json([
            'message' => 'data hasbeen show',
            'data' => $data
        ], 201);
    }

    public function getInvoice(Request $request)
    {
        $data = Invoice::with('recordWithdraw')
            ->where('is_active', true)
            ->where('no_invoice', 'like', '%' . $request->search . '%')
            ->orderBy('no_invoice')
            ->get()
            ->groupBy('no_invoice')
            ->map(function ($groupedInvoices) {
                $tagihan = floatval($groupedInvoices->sum('nilai_tagihan') ?? 0);

                $withdraw = $groupedInvoices->first()->recordWithdraw;
                $totalWithdraw = $withdraw ? $withdraw->sum('nilai_pembayaran') : 0;
                $pelunasan = $totalWithdraw > 0 ? floatval($totalWithdraw) + floatval($groupedInvoices->sum('nilai_pelunasan') ?? 0) : floatval($groupedInvoices->sum('nilai_pelunasan') ?? 0);

                $invoice = $groupedInvoices->first();
                $invoice->total_tagihan = $tagihan;

                return $tagihan > $pelunasan ? $invoice : null;
            })->filter()->values();

        return response()->json([
            'message' => 'data hasbeen show',
            'data' => $data
        ], 201);
    }

    public function prosess(Request $request)
    {
        DB::beginTransaction();
        try {
            $salesIn = SalesIn::where('id', $request->id)->first();

            foreach ($request->nilai_pelunasan as $no_invoice => $nilai_pelunasan) {
                $listPenawaran = null;
                if (!$request->no_penawaran) { // mode invoice
                    $listPenawaran = Invoice::where(['no_invoice' => $no_invoice, 'is_active' => true])->get();
                }

                $nilai_pengurangan = $request->nilai_pengurangan[$no_invoice] ?? 0;

                $dataDetail = new SalesInDetail();
                $dataDetail->id_header = $request->id;
                $dataDetail->no_penawaran = $request->no_penawaran ? $request->no_penawaran : implode(', ', $listPenawaran->isNotEmpty() ? $listPenawaran->pluck('no_quotation')->toArray() : []);
                $dataDetail->id_invoice = $request->id_invoice[$no_invoice];
                $dataDetail->no_invoice = $no_invoice;
                $dataDetail->nominal_pelunasan = str_replace(['.', ','], ['', '.'], $nilai_pelunasan);
                $dataDetail->nilai_pengurangan = str_replace(['.', ','], ['', '.'], $nilai_pengurangan);
                $dataDetail->kurang_bayar = str_replace(['.', ','], ['', '.'], $request->kurang_bayar[$no_invoice]);
                $dataDetail->lebih_bayar = str_replace(['.', ','], ['', '.'], $request->lebih_bayar[$no_invoice]);
                $dataDetail->keterangan = $request->keterangan ?? null;
                $dataDetail->proccessed_by = $this->karyawan;
                $dataDetail->proccessed_at = Carbon::now()->format('Y-m-d H:i:s');
                $dataDetail->save();

                $getNilai = RecordPembayaranInvoice::select(DB::raw('SUM(nilai_pembayaran) AS nilai_pembayaran'))
                    ->where('no_invoice', $no_invoice)
                    ->where('is_active', true)
                    ->groupBy('no_invoice',)
                    ->first();

                if ($getNilai) {
                    $getSisa = RecordPembayaranInvoice::select(DB::raw('sisa_pembayaran'))
                        ->where('no_invoice', $no_invoice)
                        ->where('is_active', true)
                        ->orderBy('id', 'DESC')
                        ->first();

                    if ($getSisa->sisa_pembayaran) {
                        $sisaPembayaran = $getSisa->sisa_pembayaran - floatval(str_replace(['.', ','], ['', '.'], $nilai_pelunasan)) - floatval(str_replace(['.', ','], ['', '.'], $nilai_pengurangan ?? 0));
                    } else {
                        $sisaPembayaran = $getSisa->sisa_pembayaran;
                    }

                    $nilaiPembayaran = $getNilai->nilai_pembayaran + floatval(str_replace(['.', ','], ['', '.'], $nilai_pelunasan));
                } else {
                    $nilaiPembayaran = floatval(str_replace(['.', ','], ['', '.'], $nilai_pelunasan));
                    $sisaPembayaran = floatval(str_replace(['.', ','], ['', '.'], $request->nilai_tagihan[$no_invoice])) - floatval(\str_replace(['.', ','], ['', '.'], $nilai_pelunasan)) - floatval(str_replace(['.', ','], ['', '.'], $nilai_pengurangan ?? 0));
                }

                RecordPembayaranInvoice::insert([
                    'id_sales_in_detail' => $dataDetail->id,
                    'no_invoice' => $no_invoice,
                    'tgl_pembayaran' => $salesIn->tanggal_masuk,
                    'nilai_pembayaran' => str_replace(['.', ','], ['', '.'], $nilai_pelunasan),
                    'nilai_pengurangan' => str_replace(['.', ','], ['', '.'], $nilai_pengurangan),
                    'lebih_bayar' => str_replace(['.', ','], ['', '.'], $request->lebih_bayar[$no_invoice]),
                    'sisa_pembayaran' => $sisaPembayaran,
                    'keterangan' => $request->keterangan,
                    'status' => 0,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'created_by' => $this->karyawan,
                ]);

                if ($listPenawaran && $listPenawaran->isNotEmpty()) {
                    $nilaiPelunasan = $nilaiPembayaran;
                    $lastInvoice = null;

                    foreach ($listPenawaran as $penawaran) {
                        if ($nilaiPelunasan <= 0) break;

                        $invoice = Invoice::where('no_invoice', $no_invoice)
                            ->where('no_quotation', $penawaran->no_quotation)
                            ->where('is_active', true)
                            ->first();

                        $lastInvoice = $invoice;

                        $sisaTagihan = $invoice->total_tagihan - ($invoice->nilai_pelunasan ?? 0);
                        if ($sisaTagihan <= 0) continue;

                        if ($nilaiPelunasan >= $sisaTagihan) {
                            $bayar = $sisaTagihan;
                        } else {
                            $bayar = $nilaiPelunasan;
                        }

                        $invoice->update([
                            'tgl_pelunasan' => $salesIn->tanggal_masuk,
                            'nilai_pelunasan' => ($invoice->nilai_pelunasan ?? 0) + $bayar,
                            'keterangan_pelunasan' => $request->keterangan,
                        ]);

                        $nilaiPelunasan -= $bayar;
                    }

                    $lebihBayar = str_replace(['.', ','], ['', '.'], $request->lebih_bayar[$no_invoice]);
                    if ($lastInvoice && $lebihBayar > 0) {
                        $lastInvoice->update(['lebih_bayar' => $lebihBayar]);
                    }
                } else {
                    Invoice::where('no_invoice', $no_invoice)
                        ->where('is_active', true)
                        ->update([
                            'tgl_pelunasan' => $salesIn->tanggal_masuk,
                            'nilai_pelunasan' => $nilaiPembayaran,
                            'keterangan_pelunasan' => $request->keterangan,
                            'lebih_bayar' => str_replace(['.', ','], ['', '.'], $request->lebih_bayar[$no_invoice]),
                        ]);
                }

                if (isset($request->jenis_pelunasan[$no_invoice]) && $nilai_pengurangan) {
                    $withdraw = new Withdraw();
                    $withdraw->id_sales_in_detail = $dataDetail->id;
                    $withdraw->no_invoice = $no_invoice;
                    $withdraw->nilai_pembayaran = str_replace(['.', ','], ['', '.'], $nilai_pengurangan);
                    $withdraw->sisa_tagihan = $sisaPembayaran;
                    $withdraw->keterangan_pelunasan = $request->jenis_pelunasan[$no_invoice];
                    $withdraw->keterangan_tambahan = $request->keterangan_pelunasan[$no_invoice] ?? null;
                    $withdraw->created_by = $this->karyawan;
                    $withdraw->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $withdraw->save();
                }
            }

            $sisa = (float) str_replace(['.', ','], ['', '.'], $request->sisa_akhir);

            if ($sisa > 0) {
                $salesIn->sisa = $sisa;
                $salesIn->status = "Incomplete";
            } else {
                $salesIn->sisa = null;
                $salesIn->status = "Done";
            }

            $salesIn->save();

            DB::commit();
            return response()->json(['message' => 'Data has been processed successfully.'], 201);
        } catch (\Throwable $th) {
            DB::rollback();
            dd($th);
            return response()->json(['message' => $th->getMessage()], 401);
        }
    }

    public function deleteDetail(Request $request)
    {
        DB::beginTransaction();
        try {
            $salesInDetail = SalesInDetail::find($request->id);
            if (!$salesInDetail)
                return response()->json(['message' => 'Sales In Detail Not Found'], 404);

            $invoice = Invoice::find($salesInDetail->id_invoice);
            if (!$invoice)
                return response()->json(['message' => 'Invoice Not Found'], 404);

            $salesIn = SalesIn::find($salesInDetail->id_header);
            if (!$salesIn)
                return response()->json(['message' => 'Sales In Not Found'], 404);

            // UPDATE INVOICE
            $newNilaiPelunasan = (float) $invoice->nilai_pelunasan - (float) $salesInDetail->nominal_pelunasan;
            if ($newNilaiPelunasan > 0) {
                $invoice->tgl_pelunasan = $salesIn->tanggal_masuk;
                $invoice->nilai_pelunasan = $newNilaiPelunasan;
            } else {
                $invoice->tgl_pelunasan = null;
                $invoice->nilai_pelunasan = null;
            }
            if ($salesInDetail->lebih_bayar)
                $invoice->lebih_bayar = null;
            $invoice->updated_by = $this->karyawan;
            $invoice->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            $invoice->save();

            // UPDATE SALES IN
            $sumPelunasan = SalesInDetail::where('id_header', $salesIn->id)
                // ->where('id_invoice', $invoice->id)
                ->where('is_active', true)
                ->where('id', '!=', $salesInDetail->id)
                ->sum('nominal_pelunasan');

            $newSisa = (float) $salesIn->nominal - (float) $sumPelunasan;
            if ($newSisa > 0) {
                if ($newSisa == $salesIn->nominal) {
                    $salesIn->sisa = null;
                    $salesIn->status = "Undefined";
                } else {
                    $salesIn->sisa = $newSisa;
                    $salesIn->status = "Incomplete";
                }
            } else {
                $salesIn->sisa = null;
                $salesIn->status = "Undefined";
            }
            $salesIn->updated_by = $this->karyawan;
            $salesIn->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            $salesIn->save();

            // DELETE WITHDRAW
            if ($salesInDetail->nilai_pengurangan && $salesInDetail->nilai_pengurangan > 0) {
                $withdraw = Withdraw::where('id_sales_in_detail', $salesInDetail->id)->first();
                if (!$withdraw)
                    return response()->json(['message' => 'Withdraw Record Not Found'], 404);

                // $withdraw->is_active = false;
                // $withdraw->save();
                $withdraw->delete();
            }

            // DELETE RECORD PEMBAYARAN INVOICE + RECALCULATION
            $recordPembayaranInvoice = RecordPembayaranInvoice::where('id_sales_in_detail', $salesInDetail->id)->first();
            if (!$recordPembayaranInvoice) {
                $recordPembayaranInvoice = RecordPembayaranInvoice::where('no_invoice', $salesInDetail->no_invoice)->where('nilai_pembayaran', $salesInDetail->nominal_pelunasan)->first();
                if (!$recordPembayaranInvoice)
                    return response()->json(['message' => 'Record Pembayaran Invoice Not Found'], 404);
            };

            $recordPembayaranInvoice->is_active = false;
            $recordPembayaranInvoice->deleted_by = $this->karyawan;
            $recordPembayaranInvoice->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
            $recordPembayaranInvoice->save();

            $penguranganRestore = (float) $recordPembayaranInvoice->nilai_pengurangan;
            $pembayaranRestore = (float) $recordPembayaranInvoice->nilai_pembayaran;

            $recordsAfter = RecordPembayaranInvoice::where('no_invoice', $recordPembayaranInvoice->no_invoice)
                ->where('is_active', true)
                ->where('id', '>', $recordPembayaranInvoice->id)
                ->orderBy('id')
                ->get();

            foreach ($recordsAfter as $rec) {
                if ($penguranganRestore > 0) {
                    if ($rec->nilai_pengurangan > 0) {
                        $rec->sisa_pembayaran += $penguranganRestore;
                    } else {
                        $rec->nilai_pengurangan += $penguranganRestore;
                    }
                }

                if ($pembayaranRestore > 0)
                    $rec->sisa_pembayaran += $pembayaranRestore;

                $rec->save();
            }

            // DELETE SALES IN DETAIL + RECALCULATION
            $salesInDetail->is_active = false;
            $salesInDetail->updated_by = $this->karyawan;
            $salesInDetail->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            $salesInDetail->save();

            $penguranganRestore = (float) $salesInDetail->nilai_pengurangan;
            $pembayaranRestore = (float) $salesInDetail->nominal_pelunasan;

            $recordsAfter = SalesInDetail::where('no_invoice', $salesInDetail->no_invoice)
                ->where('is_active', true)
                ->where('id', '>', $salesInDetail->id)
                ->orderBy('id')
                ->get();

            foreach ($recordsAfter as $rec) {
                if ($penguranganRestore > 0) {
                    if ($rec->nilai_pengurangan > 0) {
                        $rec->kurang_bayar += $penguranganRestore;
                    } else {
                        $rec->nilai_pengurangan += $penguranganRestore;
                    }
                }

                if ($pembayaranRestore > 0)
                    $rec->kurang_bayar += $pembayaranRestore;

                $rec->save();
            }

            DB::commit();
            return response()->json(['message' => 'Sales In Detail has been deleted successfully'], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }
}
