<?php

namespace App\Services;

use App\Models\DokumenSkppa;
use App\Models\Ftc;
use Carbon\Carbon;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

use App\Models\LinkLhp;
use App\Models\OrderHeader;
use App\Models\PengesahanLhp;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CombineLHPService
{
    public function combine($noLhp, $fileLhp, $noOrder, $karyawan, $periode = null)
    {
        if (!$noLhp) {
            Log::error("CombineLHPService: No. LHP is required (noOrder: {$noOrder})");
            return;
        }

        if (!$fileLhp) {
            Log::error("CombineLHPService: LHP File is required (noOrder: {$noOrder})");
            return;
        }

        if (!$noOrder) {
            Log::error("CombineLHPService: No. Order is required");
            return;
        }

        $orderHeader = OrderHeader::with('orderDetail')->where(['no_order' => $noOrder, 'is_active' => true])->latest()->first();
        if (!$orderHeader) {
            Log::error("CombineLHPService: Order tidak ditemukan (noOrder: {$noOrder})");
            return;
        }

        if (str_contains($orderHeader->no_document, 'QTC') && !$periode) {
            Log::error("CombineLHPService: Order QTC tanpa periode (noOrder: {$noOrder})");
            return;
        }

        $linkLhp = LinkLhp::where('no_order', $noOrder)->when($periode, fn($q) => $q->where('periode', $periode))->latest()->first();
        if (!$linkLhp) {
            Log::error("CombineLHPService: Link LHP tidak ditemukan (noOrder: {$noOrder})");
            return;
        }

        $finalDirectoryPath = public_path('laporan/hasil_pengujian');
        $finalFilename = $periode ? $noOrder . '_' . $periode . '.pdf' : $noOrder . '.pdf';
        $finalFullPath = $finalDirectoryPath . '/' . $finalFilename;
        if (!File::isDirectory($finalDirectoryPath)) {
            File::makeDirectory($finalDirectoryPath, 0777, true);
        }

        DB::beginTransaction();
        try {
            $httpClient = Http::asMultipart();
            $fileMetadata = [];

            if ($linkLhp->list_lhp_rilis) {
                $lhpRilis = json_decode($linkLhp->list_lhp_rilis, true);
                $lhpRilis = array_unique(array_merge($lhpRilis, [$noLhp]));
                sort($lhpRilis, SORT_NATURAL);

                foreach ($lhpRilis as $item) {
                    $existingFile = "LHP-" . str_replace('/', '-', $item) . ".pdf";
                    if ($existingFile !== $fileLhp) {
                        $lhpPath = public_path('dokumen/LHP_DOWNLOAD/' . $existingFile);
                        if (File::exists($lhpPath)) {
                            $httpClient->attach('pdfs[]', File::get($lhpPath), $existingFile);
                            $fileMetadata[] = 'skyhwk12';
                        } else {
                            Log::warning("CombineLHPService: File not found {$lhpPath}");
                        }
                    } else {
                        $lhpPath = public_path('dokumen/LHP_DOWNLOAD/' . $fileLhp);
                        if (File::exists($lhpPath)) {
                            $httpClient->attach('pdfs[]', File::get($lhpPath), $fileLhp);
                            $fileMetadata[] = 'skyhwk12';
                        } else {
                            Log::warning("CombineLHPService: File not found {$lhpPath}");
                        }
                    }
                }
            } else { // kalo blm ada samsek
                $lhpPath = public_path('dokumen/LHP_DOWNLOAD/' . $fileLhp);

                if (File::exists($lhpPath)) {
                    $httpClient->attach('pdfs[]', File::get($lhpPath), $fileLhp);
                    $fileMetadata[] = 'skyhwk12';
                } else {
                    Log::warning("CombineLHPService: File not found {$lhpPath}");
                }
            }

            $httpClient->attach('metadata', json_encode($fileMetadata));
            // $httpClient->attach('final_password', $orderHeader->id_pelanggan);

            $response = $httpClient->post(env('PDF_COMBINER_SERVICE', 'http://127.0.0.1:2999') . '/merge');
            if (!$response->successful()) {
                throw new \Exception('Python PDF Service failed (' . $response->status() . '): ' . $response->body());
            }

            File::put($finalFullPath, $response->body());

            $listLhpRilis = json_decode($linkLhp->list_lhp_rilis ?: '[]', true);
            if (!in_array($noLhp, $listLhpRilis)) {
                $countLhp = $orderHeader->orderDetail->when($periode, fn($q) => $q->where('periode', $periode))->where('is_active', true)->pluck('cfr')->unique()->count();
                $listLhpRilis[] = $noLhp;

                sort($listLhpRilis, SORT_NATURAL);

                $linkLhp->list_lhp_rilis = json_encode($listLhpRilis);
                $linkLhp->jumlah_lhp_rilis = count($listLhpRilis);
                $linkLhp->jumlah_lhp = $countLhp;
                $linkLhp->is_completed = $countLhp == count($listLhpRilis);

                if ($countLhp == count($listLhpRilis)) {
                    // buat surate keterangan
                    $this->generateSuratKeterangan($noOrder, $periode);
                }
            }

            $linkLhp->filename = $finalFilename;

            $linkLhp->updated_by = $karyawan;
            $linkLhp->updated_at = Carbon::now();

            $linkLhp->save();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            Log::error("CombineLHPService: Exception Error for {$noOrder} - {$th->getMessage()}");
            Log::error($th);
        }
    }

    public function testRendersuratKeterangan($no_order, $periode = null){
        $this->generateSuratKeterangan($no_order, $periode);
    }

    private function generateSuratKeterangan($no_order, $periode = null)
    {
        $order = OrderHeader::with(['orderDetail','invoices'])->where('no_order', $no_order)->first();

        $yearFull = date('Y');
        $yearShort = date('y');

        // Ambil terakhir di tahun yang sama
        $last = DokumenSkppa::whereYear('generate_at', $yearFull)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = 1;

        if ($last) {
            // contoh: ISL-04-SKET/260001
            $explode = explode('/', $last->no_document);

            if (isset($explode[1])) {
                $lastCode = $explode[1]; // 260001
                $lastNumber = (int) substr($lastCode, 2); // ambil 0001 → 1
                $nextNumber = $lastNumber + 1;
            }
        }

        $dataDetail = $order->orderDetail;
        $no_sampel = $dataDetail->where('periode', $periode)->where('is_active', 1)->pluck('no_sampel')->toArray();
        $tanggal_sampling = $dataDetail->where('periode', $periode)->where('is_active', 1)->pluck('tanggal_sampling')->toArray();
        $tanggal_terima = $dataDetail->where('periode', $periode)->where('is_active', 1)->pluck('tanggal_terima')->toArray();

        // Sesudah
        $cekTracking = Ftc::whereIn('no_sample', $no_sampel)
            ->selectRaw('CAST(ftc_laboratory AS DATE) as ftc_laboratory')
            ->pluck('ftc_laboratory')
            ->toArray();

        $cekTracking = array_filter($cekTracking);
        sort($cekTracking);

        $tanggal_analisa_awal  = !empty($cekTracking) ? $cekTracking[0] : null;

        // Ambil tgl_release_lhp terakhir dari semua tabel LHP Header
        $lhpModels = [
            \App\Models\LhpsAirHeader::class,
            \App\Models\LhpsEmisiCHeader::class,
            \App\Models\LhpsEmisiHeader::class,
            \App\Models\LhpsEmisiIsokinetikHeader::class,
            \App\Models\LhpsErgonomiHeader::class,
            \App\Models\LhpsGetaranHeader::class,
            \App\Models\LhpsHygieneSanitasiHeader::class,
            \App\Models\LhpsIklimHeader::class,
            \App\Models\LhpsKebisinganHeader::class,
            \App\Models\LhpsKebisinganPersonalHeader::class,
            \App\Models\LhpsLingHeader::class,
            \App\Models\LhpsMedanLMHeader::class,
            \App\Models\LhpsMicrobiologiHeader::class,
            \App\Models\LhpsPadatanHeader::class,
            \App\Models\LhpsPencahayaanHeader::class,
            \App\Models\LhpsSinarUVHeader::class,
            \App\Models\LhpsSwabTesHeader::class,
            \App\Models\LhpUdaraPsikologiHeader::class, // tanpa 's' sesuai nama file
        ];

        $allTanggalRelease = [];
        foreach ($lhpModels as $model) {
            $instance = new $model;
            $kolom = \Schema::hasColumn($instance->getTable(), 'tanggal_lhp') 
                ? 'tanggal_lhp' 
                : 'tanggal_rilis_lhp';

            $tanggal = $model::where('no_order', $no_order)
                ->whereNotNull($kolom)
                ->pluck($kolom)
                ->map(fn($t) => Carbon::parse($t)->toDateString())
                ->toArray();

            $allTanggalRelease = array_merge($allTanggalRelease, $tanggal);
        }

        $allTanggalRelease = array_filter(array_unique($allTanggalRelease));
        sort($allTanggalRelease);

        $tanggal_analisa_akhir = !empty($allTanggalRelease) ? end($allTanggalRelease) : null;

        // Jika awal dan akhir sama, kosongkan akhir
        if ($tanggal_analisa_awal === $tanggal_analisa_akhir) {
            $tanggal_analisa_akhir = null;
        }

        $tanggal_sampling = array_filter($tanggal_sampling);
        sort($tanggal_sampling);
        if (!empty($tanggal_sampling)) {
            $tanggal_sampling_awal = $tanggal_sampling[0];
            $tanggal_sampling_akhir = $tanggal_sampling[count($tanggal_sampling) - 1];
            if ($tanggal_sampling_awal == $tanggal_sampling_akhir) {
                $tanggal_sampling_akhir = null;
            }
        } else {
            $tanggal_sampling_awal = null;
            $tanggal_sampling_akhir = null;
        }

        $tanggal_terima = array_filter($tanggal_terima); // Hilangkan nilai null/false
        sort($tanggal_terima);
        if (!empty($tanggal_terima)) {
            $tanggal_terima_awal = $tanggal_terima[0];
            $tanggal_terima_akhir = $tanggal_terima[count($tanggal_terima) - 1];
            if ($tanggal_terima_awal == $tanggal_terima_akhir) {
                $tanggal_terima_akhir = null;
            }
        } else {
            $tanggal_terima_awal = null;
            $tanggal_terima_akhir = null;
        }

        // gabung YY + 4 digit
        $number = $yearShort . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        $no_document = "ISL-04-SKET/{$number}";

        $no_po = '-';

        if($periode != null) {
            $check_periode = $order->invoices->where('periode', $periode)->first();
            if($check_periode) {
                $no_po = $check_periode->no_po;
            } else {
                $check_periode = $order->invoices->where('periode', "all")->first();
                if($check_periode) {
                    $no_po = $check_periode->no_po;
                }
            }
        } else {
            $no_po = $order->invoices->first()->no_po;
        }

        if(empty($no_po)) {
            $no_po = '-';
        }

        $cek_skppa = DokumenSkppa::where('no_order', $no_order)->where('periode', $periode)->first();
        if($cek_skppa) {
            return true;
        } else {
            $skppa = new DokumenSkppa();
            $skppa->id_order = $order->id;
            $skppa->no_order = $order->no_order;
            $skppa->periode = $periode;
            $skppa->no_document = $no_document;
            $skppa->no_quotation = $order->no_document;
            $skppa->tanggal_rilis = Carbon::now()->format('Y-m-d');
            $skppa->filename = str_replace('/', '_', $no_document) . '.pdf';
            $skppa->nama_perusahaan = $order->nama_perusahaan;
            $skppa->alamat_perusahaan = $order->alamat_kantor;
            $skppa->no_po = $no_po;
            $skppa->tanggal_sampling_awal = $tanggal_sampling_awal;
            $skppa->tanggal_sampling_akhir = $tanggal_sampling_akhir;
            $skppa->tanggal_sampel_diterima_awal = $tanggal_terima_awal;
            $skppa->tanggal_sampel_diterima_akhir = $tanggal_terima_akhir;
            $skppa->tanggal_penyelesaian_analisa_awal = $tanggal_analisa_awal;
            $skppa->tanggal_penyelesaian_analisa_akhir = $tanggal_analisa_akhir;
            $skppa->generate_at = Carbon::now()->format('Y-m-d H:i:s');
            $skppa->generate_by = 'system';
            $skppa->save();
        }

        $pengesah = PengesahanLhp::where('berlaku_mulai', '<=', Carbon::now())
            ->orderByDesc('berlaku_mulai')
            ->first();

        $filename = \str_replace("/", "_", $skppa->no_document);
        $path = public_path() . "/qr_documents/" . $filename . '.svg';
        if (!file_exists($path)) {
            $link = 'https://www.intilab.com/validation/';
            $unique = 'isldc' . (int) floor(microtime(true) * 1000);

            QrCode::size(200)->generate($link . $unique, $path);
            $dataQr = [
                'type_document' => 'skppa',
                'kode_qr' => $unique,
                'file' => $filename,
                'data' => json_encode([
                    'no_document' => $skppa->no_document,
                    'nama_customer' => $order->nama_perusahaan,
                    'type_document' => 'Surat Keterangan Penyelesaian Pekerjaan Analisa',
                    'Tanggal_Pengesahan' => Carbon::now()->locale('id')->isoFormat('DD MMMM YYYY'),
                    'Disahkan_Oleh' => $pengesah->nama_karyawan,
                    'Jabatan' => $pengesah->jabatan_karyawan
                ]),
                'created_at' => Carbon::now(),
                'created_by' => 'System',
            ];

            DB::table('qr_documents')->insert($dataQr);
            // self::generatePDF($request->no_invoice);
        }

        $render = new RenderDokumenSkppa();

        $fileName = $render->execute($skppa, public_path() . '/qr_documents/' . $filename . '.svg');

        $skppa->filename = $fileName;
        $skppa->save();


        return true;
    }
}
