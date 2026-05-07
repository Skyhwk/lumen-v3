<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

use App\Models\DokumenSkppa;
use App\Models\Ftc;
use App\Models\KelengkapanKonfirmasiQs;
use App\Models\LinkLhp;
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\PengesahanLhp;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;

use App\Services\GetAtasan;
use App\Services\RenderDokumenBap;
use App\Services\RenderDokumenSkppa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Yajra\Datatables\Datatables;


use SimpleSoftwareIO\QrCode\Facades\QrCode;

class DokumenSkppaController extends Controller
{
    public function index()
    {
        $dokumenBap = DokumenSkppa::with('order');

        return Datatables::of($dokumenBap)->make(true);
    }

    public function handlePrintSkppa(Request $request)
    {
        DB::beginTransaction();
        try {
            $skppa = DokumenSkppa::where('id', $request->id)->first();
            $skppa->count_print = $skppa->count_print + 1;
            $skppa->is_printed = true;
            $skppa->printed_by = $this->karyawan;
            $skppa->printed_at = Carbon::now()->format('Y-m-d H:i:s');
            $skppa->save();

            DB::commit();
            return response()->json([
                'message' => 'Berhasil Print Dokumen SKPPA' . $skppa->no_document,
            ], 200);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error download file ' . $th->getMessage(),
            ], 401);
        }
    }

    public function generateSkppa(Request $request)
    {
        $no_order = $request->no_order;
        $periode = $request->periode ?? null;

        $order = OrderHeader::with(['orderDetail', 'invoices'])->where('no_order', $no_order)->first();

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
            $kolom = Schema::hasColumn($instance->getTable(), 'tanggal_lhp')
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
        $number = $yearShort . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

        $no_document = "ISL-04-SKET/{$number}";

        $no_po = '-';

        if ($periode != null) {
            $check_periode = $order->invoices->where('periode', $periode)->first();
            if ($check_periode) {
                $no_po = $check_periode->no_po;
            } else {
                $check_periode = $order->invoices->where('periode', "all")->first();
                if ($check_periode) {
                    $no_po = $check_periode->no_po;
                }
            }
        } else {
            $no_po = $order->invoices->first()->no_po;
        }

        if (empty($no_po)) {
            $no_po = '-';
        }

        $cek_skppa = DokumenSkppa::where('no_order', $no_order)->when($periode, fn($q) => $q->where('periode', $periode))->first();
        if ($cek_skppa) {
            return true;
        } else {
            $skppa = new DokumenSkppa();
            $skppa->id_order = $order->id;
            $skppa->no_order = $order->no_order;
            $skppa->tanggal_order = $order->tanggal_order;
            $skppa->no_quotation = $order->no_document;
            $skppa->tanggal_penawaran = $order->tanggal_penawaran;
            $skppa->periode = $periode;
            $skppa->no_document = $no_document;
            $skppa->tanggal_rilis = Carbon::now()->format('Y-m-d');
            $skppa->filename = str_replace('/', '_', $no_document) . '.pdf';
            $skppa->id_pelanggan = $order->id_pelanggan;
            $skppa->nama_perusahaan = $order->nama_perusahaan;
            $skppa->alamat_perusahaan = $order->alamat_kantor;
            $skppa->alamat_sampling = $order->alamat_sampling;
            $skppa->no_po = $no_po;
            
            $details = $order->orderDetail->when($request->periode, fn($q) => $q->where('periode', $request->periode));
            $skppa->total_sampel = $details->count();
            $skppa->total_lhp = $details->pluck('cfr')->filter()->unique()->count();
            $skppa->tanggal_sampling = $details->min('tanggal_sampling');
            $skppa->subkategori = json_encode($details->groupBy('kategori_3')->map(fn($items, $kategori) => ['kategori' => $kategori, 'jumlah' => $items->count()])->values()->toArray());
            $skppa->kategori = json_encode($details->pluck('kategori_1')->filter()->unique()->toArray());

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
