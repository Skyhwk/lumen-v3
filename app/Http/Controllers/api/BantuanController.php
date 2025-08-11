<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\QuotationNonKontrak;
use App\Models\QuotationKontrakH;
use App\Models\MasterPelanggan;
use App\Services\NewRenderInvoice;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Services\GenerateToken;
use App\Services\GeneratePraSampling;

use Illuminate\Support\Facades\DB;


class BantuanController extends Controller
{
    public function decrypt(Request $request)
    {
        $ENCRYPTION_KEY = 'intilab_jaya';
        $ENCRYPTION_ALGORITHM = 'AES-256-CBC';
        $EncryptionKey = base64_decode($ENCRYPTION_KEY);
        list($Encrypted_Data, $InitializationVector) = array_pad(explode('::', base64_decode($request->data), 2), 2, null);
        $data = openssl_decrypt($Encrypted_Data, $ENCRYPTION_ALGORITHM, $EncryptionKey, 0, $InitializationVector);
        $extand = explode("|", $data);
        return $extand;
    }

    public function generateLinkQt(Request $request)
    {

        if ($request->mode == 'non_kontrak') {
            $quotationNonKontrak = QuotationNonKontrak::where('no_document', $request->no_document)->first();

            // GENERATE QR
            // GenerateQrDocument::insert('quotation_non_kontrak', $quotationNonKontrak, $this->karyawan);

            // GENERATE DOCUMENT
            // RenderNonKontrak::renderHeader($quotationNonKontrak->id);

            // GENERATE LINK & TOKEN
            $token = GenerateToken::save('non_kontrak', $quotationNonKontrak, $this->karyawan, 'quotation');

            // $quotationNonKontrak->is_generated = true;
            // $quotationNonKontrak->generated_by = $this->karyawan;
            // $quotationNonKontrak->generated_at = Carbon::now()->format('Y-m-d H:i:s');
            $quotationNonKontrak->id_token = $token->id;
            $quotationNonKontrak->expired = $token->expired;

            $quotationNonKontrak->save();
        } else if ($request->mode == 'kontrak') {
            $quotationKontrak = QuotationKontrakH::where('no_document', $request->no_document)->first();

            // GENERATE QR
            // GenerateQrDocument::insert('quotation_kontrak', $quotationKontrak, $this->karyawan);

            // GENERATE DOCUMENT
            // $renderKontrak = new RenderKontrak();
            // $renderKontrak->renderDataQuotation($quotationKontrak->id);

            // GENERATE LINK & TOKEN
            $token = GenerateToken::save('kontrak', $quotationKontrak, $this->karyawan, 'quotation');

            // $quotationKontrak->is_generated = true;
            // $quotationKontrak->generated_by = $this->karyawan;
            // $quotationKontrak->generated_at = Carbon::now()->format('Y-m-d H:i:s');
            $quotationKontrak->id_token = $token->id;
            $quotationKontrak->expired = $token->expired;

            $quotationKontrak->save();
        } else {
            return response()->json(["message" => "Module not found",], 400);
        }

        return response()->json(["message" => "Data Quotation has been generated"], 200);
    }

    public function renderInvoice(Request $request)
    {
        // dd($request->all());
        try {
            $render = new NewRenderInvoice();
            $render->renderInvoice($request->no_invoice);
            return true; // Jika sukses
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function injectGeneratePraSampling(Request $request)
    {
        DB::beginTransaction();
        try {
            $no_document = $request->no_document;

            if (isset($request->id_order) && $request->id_order != null) {
                $cek_order = OrderHeader::where('id', $request->id_order)->where('is_active', true)->first();
                $no_qt_lama = $cek_order->no_document;
                $no_qt_baru = $no_document;
                $id_order = $request->id_order;

                $parse = new GeneratePraSampling;
                $parse->type($request->type);
                $parse->where('no_qt_lama', $no_qt_lama);
                $parse->where('no_qt_baru', $no_qt_baru);
                $parse->where('id_order', $id_order);
                $parse->save();
            } else {
                $parse = new GeneratePraSampling;
                $parse->type($request->type);
                $parse->where('no_qt_baru', $no_document);
                $parse->where('generate', 'new');
                $parse->save();
            }

            DB::commit();
            return response()->json([
                "message" => "Pra Nomor Sampling " . $no_document . " has been generated",
                "status" => "success",
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'status' => 'failed',
            ];
        }

    }

    public function fixIdPelanggan(Request $request)
    {
        DB::beginTransaction();
        try {
            $customers = MasterPelanggan::where('id_pelanggan', 'REGEXP', '[^a-zA-Z0-9]')
                // ->limit(67)
                ->get();

            $changes = [];
            foreach ($customers as $customer) {
                $from = $customer->id_pelanggan; // ID pelanggan lama
                $pelanggan = $customer->nama_pelanggan; // Nama pelanggan

                $idPelanggan = null;
                for ($i = 1; $i <= 10; $i++) {
                    $generatedId = $this->randomstr($customer->nama_pelanggan, $i);

                    if ($this->containsBadWords($generatedId)) {
                        dump("Bad ID generated: " . $generatedId);
                        continue; // Jika badword ditemukan, lanjutkan dengan mencoba ID lain
                    }

                    if (!MasterPelanggan::where('id_pelanggan', $generatedId)->exists()) {
                        $idPelanggan = $generatedId;
                        break; // Jika ditemukan ID yang valid, keluar dari loop
                    }
                }

                if (!$idPelanggan) {
                    throw new \Exception("Unable to generate valid ID for customer: " . $pelanggan);
                }

                $qtc = QuotationKontrakH::where('pelanggan_ID', $customer->id_pelanggan)
                    ->update(['pelanggan_ID' => $idPelanggan]);
                $qt = QuotationNonKontrak::where('pelanggan_ID', $customer->id_pelanggan)
                    ->update(['pelanggan_ID' => $idPelanggan]);

                $customer->id_pelanggan = $idPelanggan;
                $customer->save();

                $changes[] = [
                    'from' => $from,
                    'to' => $idPelanggan,
                    'pelanggan' => $pelanggan,
                    'qtc' => $qtc ? 'Terdapat ' . $qtc . ' data' : 'false',
                    'qt' => $qt ? 'Terdapat ' . $qt . ' data' : 'false'
                ];
            }
            $filePath = storage_path('app/public/changes_MasterPelanggan.txt');

            $changesString = '';
            foreach ($changes as $change) {
                $changesString .= "From: {$change['from']}, To: {$change['to']}, Pelanggan: {$change['pelanggan']}, QTC: {$change['qtc']}, QT: {$change['qt']}\n";
            }

            file_put_contents($filePath, $changesString);

            // DB::commit();
            return response()->json($changes, 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    private function randomstr($str, $no)
    {
        // $str = str_replace([' ', '\t', ','], '', $str);
        $str = preg_replace('/[^A-Z]/', '', $str); // perbaikan oleh afryan 2025-04-15
        $result = substr(str_shuffle($str), 0, 4) . sprintf("%02d", $no);
        return $result;
    }

    private function containsBadWords($idPelanggan)
    {
        $badWords = [
            'BODO',
            'BABI',
            'ANJG',
            'ANJ',
            'AJG',
            'KONT',
            'KNTL',
            'MEME',
            'MMPP',
            'GOBL',
            'TOLO',
            'TOL0',
            'TOLI',
            'TOLU',
            'TAI',
            'TAEE',
            'JANC',
            'NGNT',
            'NGOC',
            'JEMB',
            'PANT',
            'PEPE',
            'PUKI',
            'SUUU',
            'ASUU',
            'ASEM',
            'AJIG',
            'JAMB',
            'PLER',
            'FUUK',
            'FCKU',
            'DIUU',
            'EWEK',
            'EWE',
            'BANG',
            'KONT',
            'TOLI',
            'LOLI',
            'FUUK',
            'PUNK',
            'CUPU',
            'TROL',
            'BUNG',
            'RAP1',
            'MAHO',
            'NIGG',
            'BONK',
            'BUST',
            'BOOB',
            'ANJ',
            'L0NG',
            'J4NG',
            'LOLO',
            'PIMP',
            'WANK',
            'COCK',
            'SHIT',
            'TROX',
            'LULZ',
            'LASH',
            'MONK',
            'JEMB',
            'RAPE',
            'GANG',
            'VULG',
            'PORN',
            'BULL',
            'SHTS',
            // 'SH!T',
            // 'BUST',
            'FUKK',
            'VIRG',
            'SEX',
            // 'BILL',
            'VAGI',
            'KISS',
            'FAGG',
            'ANAL',
            'FUCK',
            // 'TIT',
            'FACK',
            'TITS'
        ];

        $idPelanggan = strtoupper($idPelanggan);

        foreach ($badWords as $badWord) {
            if (strpos($idPelanggan, $badWord) !== false) {
                return true; // Jika badword ada di dalam id_pelanggan
            }
        }

        return false; // Jika tidak ada badword
    }

}