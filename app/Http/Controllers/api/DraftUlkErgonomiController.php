<?php
namespace App\Http\Controllers\api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use \App\Services\MpdfService as PDF;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;
use App\Helpers\EmailLhpRilisHelpers;

use App\Models\{HistoryAppReject, OrderHeader, LhpsKebisinganHeader,LhpsKebisinganDetail,LhpsLingHeader,LhpsLingDetail,LhpsPencahayaanHeader,LhpsGetaranHeader,LhpsGetaranDetail,LhpsPencahayaanDetail,LhpsMedanLMHeader,LhpsMedanLMDetail,LhpsKebisinganHeaderHistory,LhpsKebisinganDetailHistory,LhpsGetaranHeaderHistory,LhpsGetaranDetailHistory,LhpsPencahayaanHeaderHistory,LhpsPencahayaanDetailHistory,LhpsMedanLMHeaderHistory,LhpsMedanLMDetailHistory,LhpSinarUVHeaderHistory,LhpsSinarUVDetailHistory,LhpsLingHeaderHistory,LhpsLingDetailHistory,MasterSubKategori,OrderDetail,MetodeSampling,MasterBakumutu,MasterKaryawan,LingkunganHeader,QrDocument,PencahayaanHeader,KebisinganHeader,Subkontrak,MedanLMHeader,SinarUVHeader,GetaranHeader,DataLapanganErgonomi,Parameter,DirectLainHeader,GenerateLink,DraftErgonomiFile, DraftErgonomiFileHistory, PengesahanLhp,LinkLhp,WsValueErgonomi};

use App\Services\{SendEmail,TemplateLhps,GenerateQrDocumentLhp,TemplateLhpErgonomi};
use App\Jobs\RenderLhp;
use App\Jobs\CombineLHPJob;

class DraftUlkErgonomiController extends Controller
{
    // done if status = 2
    // AmanghandleDatadetail
    public function index()
    {
        DB::statement("SET SESSION sql_mode = ''");
        $generatedFiles = DraftErgonomiFile::select('id','no_sampel','tanggal_lhp','name_file', 'is_generate_link')
        ->get()
        ->keyBy('no_sampel');

        $kategori = ["27-Udara Lingkungan Kerja", "53-Ergonomi"];

        $data = OrderDetail::with([
            'orderHeader'
            => function ($query) {
                $query->select('id', 'nama_pic_order', 'jabatan_pic_order', 'no_pic_order', 'email_pic_order', 'alamat_sampling');
            }
        ])
            ->where('order_detail.status', 2)
            ->where('is_active', true)
            ->whereIn('kategori_3', $kategori)
            ->whereJsonContains('parameter','230;Ergonomi') //aktivin filter
            ->groupBy('no_sampel')
            ->get() // ambil data dulu
            ->map(function ($item) use ($generatedFiles) {
                if (isset($generatedFiles[$item->no_sampel])) {
                    $item->isGenerate = true;
                    $item->idGenerateFile = $generatedFiles[$item->no_sampel]['id'];
                    $item->filePdf = $generatedFiles[$item->no_sampel]['name_file'];
                    $item->tanggal_lhp = $generatedFiles[$item->no_sampel]['tanggal_lhp'];
                    $item->isGenerateLink = (bool)$generatedFiles[$item->no_sampel]['is_generate_link'];
                } else {
                    $item->isGenerate = false;
                    $item->filePdf = null;
                    $item->isGenerateLink = false;
                }
                return $item;
            });
        return Datatables::of($data)->make(true);
    }

    // Amang
    public function getKategori()
    {
        $kategori = MasterSubKategori::where('id_kategori', 4)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $kategori,
            'message' => 'Available data category retrieved successfully',
        ], 201);
    }

    // Tidak digunakan sekarang, gatau nanti
    public function handleMetodeSampling(Request $request)
    {
        // dd($request->all());
        try {
            $kategori = explode('-', '4-UDARA');
            $subKategori = explode('-', $request->kategori_3);
            $data = MetodeSampling::where('kategori', $kategori[1])
                ->where('sub_kategori', $subKategori[1])->get();
            if ($data->isNotEmpty()) {
                return response()->json([
                    'status' => true,
                    'data' => $data
                ], 200);
            } else {
                return response()->json([
                    'status' => true,
                    'data' => 'belum ada method'
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $categoryKebisingan = [23, 24, 25];
        $categoryGetaran = [13, 14, 15, 16, 17, 18, 19, 20];
        $categoryLingkunganKerja = [11, 27, 53];
        $categoryPencahayaan = [28];
        $category = explode('-', $request->kategori_3)[0];
        $sub_category = explode('-', $request->kategori_3)[1];
        DB::beginTransaction();

        if (in_array($category, $categoryKebisingan)) {
            // Kebisingan
            try {
                $header = LhpsKebisinganHeader::where('no_lhp', $request->no_lhp)->where('no_order', $request->no_order)->where('id_kategori_3', $category)->where('is_active', true)->first();
                // dd($request->no_lhp, $request->no_order, $category, $header);
                if ($header == null) {
                    $header = new LhpsKebisinganHeader;
                    $header->created_by = $this->karyawan;
                    $header->created_at = Carbon::now()->format('Y-m-d H:i:s');
                } else {
                    $history = $header->replicate();
                    $history->setTable((new LhpsKebisinganHeaderHistory())->getTable());
                    $history->created_by = $this->karyawan;
                    $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $history->updated_by = null;
                    $history->updated_at = null;
                    $history->save();
                    $header->updated_by = $this->karyawan;
                    $header->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                }
                $parameter = \explode(', ', $request->parameter);
                $header->no_order = ($request->no_order != '') ? $request->no_order : NULL;
                $header->no_lhp = ($request->no_lhp != '') ? $request->no_lhp : NULL;
                $header->id_kategori_2 = ($request->kategori_2 != '') ? explode('-', $request->kategori_2)[0] : NULL;
                $header->id_kategori_3 = ($category != '') ? $category : NULL;
                $header->no_qt = ($request->no_penawaran != '') ? $request->no_penawaran : NULL;
                $header->parameter_uji = json_encode($parameter);
                $header->nama_pelanggan = ($request->nama_perusahaan != '') ? $request->nama_perusahaan : NULL;
                $header->alamat_sampling = ($request->alamat_sampling != '') ? $request->alamat_sampling : NULL;
                $header->sub_kategori = ($request->jenis_sampel != '') ? $request->jenis_sampel : NULL;
                $header->deskripsi_titik = ($request->keterangan_1 != '') ? $request->keterangan_1 : NULL;
                $header->metode_sampling = ($request->metode_sampling != '') ? $request->metode_sampling : NULL;
                $header->tanggal_sampling = ($request->tanggal_tugas != '') ? $request->tanggal_tugas : NULL;
                $header->suhu = ($request->suhu_udara != '') ? $request->suhu_udara : NULL;
                $header->kelembapan = ($request->kelembapan_udara != '') ? $request->kelembapan_udara : NULL;
                $header->periode_analisa = ($request->periode_analisa != '') ? $request->periode_analisa : NULL;
                $header->nama_karyawan = 'Abidah Walfathiyyah';
                $header->jabatan_karyawan = 'Technical Control Supervisor';
                // $header->nama_karyawan = 'Kharina Waty';
                // $header->jabatan_karyawan = 'Technical Control Manager';
                $header->regulasi = ($request->regulasi != null) ? json_encode($request->regulasi) : NULL;
                $header->tanggal_lhp = ($request->tanggal_lhp != '') ? $request->tanggal_lhp : NULL;
                $header->save();

                // Kode Lama
                // $detail = LhpsKebisinganDetail::where('id_header', $header->id)->first();
                // if ($detail != null)
                //     $detail = LhpsKebisinganDetail::where('id_header', $header->id)->delete();

                $detail = LhpsKebisinganDetail::where('id_header', $header->id)->first();
                if ($detail != null) {
                    $history = $detail->replicate();
                    $history->setTable((new LhpsKebisinganDetailHistory())->getTable());
                    $history->created_by = $this->karyawan;
                    $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $history->save();
                    $detail = LhpsKebisinganDetail::where('id_header', $header->id)->delete();
                }

                if ($request->hasil_uji) {
                    $cleaned_key_hasil_uji = array_map(fn($k) => trim($k, " '\""), array_keys($request->hasil_uji));
                    $cleaned_hasil_uji = array_combine($cleaned_key_hasil_uji, array_values($request->hasil_uji));
                    $cleaned_key_min = array_map(fn($k) => trim($k, " '\""), array_keys($request->min));
                    $cleaned_min = array_combine($cleaned_key_min, array_values($request->min));
                    $cleaned_key_max = array_map(fn($k) => trim($k, " '\""), array_keys($request->max));
                    $cleaned_max = array_combine($cleaned_key_max, array_values($request->max));
                }
                if ($request->ls) {
                    $cleaned_key_ls = array_map(fn($k) => trim($k, " '\""), array_keys($request->ls));
                    $cleaned_ls = array_combine($cleaned_key_ls, array_values($request->ls));
                    $cleaned_key_lm = array_map(fn($k) => trim($k, " '\""), array_keys($request->lm));
                    $cleaned_lm = array_combine($cleaned_key_lm, array_values($request->lm));
                }
                $cleaned_key_titik_koordinat = array_map(fn($k) => trim($k, " '\""), array_keys($request->titik_koordinat));
                $cleaned_titik_koordinat = array_combine($cleaned_key_titik_koordinat, array_values($request->titik_koordinat));
                $cleaned_key_lokasi = array_map(fn($k) => trim($k, " '\""), array_keys($request->lokasi));
                $cleaned_lokasi = array_combine($cleaned_key_lokasi, array_values($request->lokasi));
                $cleaned_key_noSampel = array_map(fn($k) => trim($k, " '\""), array_keys($request->noSampel));
                $cleaned_noSampel = array_combine($cleaned_key_noSampel, array_values($request->noSampel));
                foreach ($request->noSampel as $key => $val) {
                    // dd($cleaned_ls[$val]);
                    if (array_key_exists($val, $cleaned_noSampel)) {
                        $detail = new LhpsKebisinganDetail;
                        $detail->id_header = $header->id;
                        $detail->no_sampel = $val;
                        $detail->lokasi_keterangan = $cleaned_lokasi[$val];
                        $detail->min = $cleaned_min[$val] ?? null;
                        $detail->max = $cleaned_max[$val] ?? null;
                        $detail->leq_ls = $cleaned_ls[$val] ?? null;
                        $detail->leq_lm = $cleaned_lm[$val] ?? null;
                        $detail->titik_koordinat = $cleaned_titik_koordinat[$val];
                        $detail->hasil_uji = $cleaned_hasil_uji[$val] ?? null;
                        $detail->save();
                    }
                }

                $details = LhpsKebisinganDetail::where('id_header', $header->id)->get();
                if ($header != null) {

                    $file_qr = new GenerateQrDocumentLhp();
                    $file_qr = $file_qr->insert('LHP_KEBISINGAN', $header, $this->karyawan);
                    if ($file_qr) {
                        $header->file_qr = $file_qr;
                        $header->save();
                    }

                    $groupedByPage = [];
                    if (!empty($custom)) {
                        foreach ($custom as $item) {
                            $page = $item['page'];
                            if (!isset($groupedByPage[$page])) {
                                $groupedByPage[$page] = [];
                            }
                            $groupedByPage[$page][] = $item;
                        }
                    }

                    $job = new RenderLhp($header, $details, 'downloadWSDraft', $groupedByPage);
                    $this->dispatch($job);

                    $job = new RenderLhp($header, $details, 'downloadLHP', $groupedByPage);
                    $this->dispatch($job);

                    $job = new RenderLhp($header, $details, 'downloadLHPFinal', $groupedByPage);
                    $this->dispatch($job);

                    $fileName = 'LHP-' . str_replace("/", "-", $header->no_lhp) . '.pdf';

                    $header->file_lhp = $fileName;
                    $header->save();
                }

                DB::commit();
                return response()->json([
                    'message' => 'Data draft LHP udara no sampel ' . $request->no_sampel . ' berhasil disimpan',
                    'status' => true
                ], 201);
            } catch (\Exception $th) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                    'status' => false,
                    'line' => $th->getLine(),
                    'file' => $th->getFile()
                ], 500);
            }
        } else if (in_array($category, $categoryGetaran)) {
            try {
                // Getaran

                $header = LhpsGetaranHeader::where('no_lhp', $request->no_lhp)->where('no_order', $request->no_order)->where('id_kategori_3', $category)->where('is_active', true)->first();
                if ($header == null) {
                    $header = new LhpsGetaranHeader;
                    $header->created_by = $this->karyawan;
                    $header->created_at = Carbon::now()->format('Y-m-d H:i:s');
                } else {
                    // dd('update');
                    $history = $header->replicate();
                    $history->setTable((new LhpsGetaranHeaderHistory())->getTable());
                    $history->created_by = $this->karyawan;
                    $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $history->updated_by = null;
                    $history->updated_at = null;
                    $history->save();
                    $header->updated_by = $this->karyawan;
                    $header->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                }
                $parameter = \explode(', ', $request->parameter);
                $header->no_order = ($request->no_order != '') ? $request->no_order : NULL;
                $header->no_lhp = ($request->no_lhp != '') ? $request->no_lhp : NULL;
                $header->no_sampel = ($request->no_sampel != '') ? $request->no_sampel : NULL;
                $header->id_kategori_2 = ($request->kategori_2 != '') ? explode('-', $request->kategori_2)[0] : NULL;
                $header->id_kategori_3 = ($category != '') ? $category : NULL;
                $header->no_qt = ($request->no_penawaran != '') ? $request->no_penawaran : NULL;
                $header->parameter_uji = json_encode($parameter);
                $header->nama_pelanggan = ($request->nama_perusahaan != '') ? $request->nama_perusahaan : NULL;
                $header->alamat_sampling = ($request->alamat_sampling != '') ? $request->alamat_sampling : NULL;
                $header->sub_kategori = ($request->jenis_sampel != '') ? $request->jenis_sampel : NULL;
                // $header->deskripsi_titik = ($request->keterangan_1 != '') ? $request->keterangan_1 : NULL;
                $header->metode_sampling = ($request->metode_sampling != '') ? $request->metode_sampling : NULL;
                $header->tanggal_sampling = ($request->tanggal_tugas != '') ? $request->tanggal_tugas : NULL;
                $header->periode_analisa = ($request->periode_analisa != '') ? $request->periode_analisa : NULL;
                $header->nama_karyawan = 'Abidah Walfathiyyah';
                $header->jabatan_karyawan = 'Technical Control Supervisor';
                // $header->nama_karyawan = 'Kharina Waty';
                // $header->jabatan_karyawan = 'Technical Control Manager';
                $header->regulasi = ($request->regulasi != null) ? json_encode($request->regulasi) : NULL;
                $header->tanggal_lhp = ($request->tanggal_lhp != '') ? $request->tanggal_lhp : NULL;

                $header->save();

                $details = LhpsGetaranDetail::where('id_header', $header->id)->get();
                foreach ($details as $detail) {
                    $history = $detail->replicate();
                    $history->setTable((new LhpsGetaranDetailHistory())->getTable());
                    $history->created_by = $this->karyawan;
                    $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $history->save();
                }

                $detail = LhpsGetaranDetail::where('id_header', $header->id)->delete();
                foreach ($request->noSampel as $key => $val) {
                    if (in_array("Getaran (LK) ST", $parameter)) {
                        // dd($request->all());
                        $cleaned_key_x = array_map(fn($k) => trim($k, " '\""), array_keys($request->x));
                        $cleaned_x = array_combine($cleaned_key_x, array_values($request->x));
                        $cleaned_key_y = array_map(fn($k) => trim($k, " '\""), array_keys($request->y));
                        $cleaned_y = array_combine($cleaned_key_y, array_values($request->y));
                        $cleaned_key_z = array_map(fn($k) => trim($k, " '\""), array_keys($request->z));
                        $cleaned_z = array_combine($cleaned_key_z, array_values($request->z));
                        $cleaned_key_tipe_getaran = array_map(fn($k) => trim($k, " '\""), array_keys($request->tipe_getaran));
                        $cleaned_tipe_getaran = array_combine($cleaned_key_tipe_getaran, array_values($request->tipe_getaran));
                        $cleaned_key_aktivitas = array_map(fn($k) => trim($k, " '\""), array_keys($request->aktivitas));
                        $cleaned_aktivitas = array_combine($cleaned_key_aktivitas, array_values($request->aktivitas));
                        $cleaned_key_sumber_getaran = array_map(fn($k) => trim($k, " '\""), array_keys($request->sumber_getaran));
                        $cleaned_sumber_getaran = array_combine($cleaned_key_sumber_getaran, array_values($request->sumber_getaran));
                        $cleaned_key_durasi_paparan = array_map(fn($k) => trim($k, " '\""), array_keys($request->waktu));
                        $cleaned_durasi_paparan = array_combine($cleaned_key_durasi_paparan, array_values($request->waktu));

                        if (array_key_exists($val, $cleaned_aktivitas)) {

                            $detail = new LhpsGetaranDetail;
                            $detail->id_header = $header->id;
                            $detail->aktivitas = $cleaned_aktivitas[$val];
                            $detail->sumber_get = $cleaned_sumber_getaran[$val];
                            $detail->w_paparan = $cleaned_durasi_paparan[$val];
                            $detail->x = $cleaned_x[$val];
                            $detail->y = $cleaned_y[$val];
                            $detail->z = $cleaned_z[$val];
                            $detail->tipe_getaran = $cleaned_tipe_getaran[$val];
                            $detail->save();
                        }
                    } else if (in_array("Getaran (LK) TL", $parameter)) {
                        // dd($request->all());
                        $cleaned_key_no_sampel = array_map(fn($k) => trim($k, " '\""), array_keys($request->noSampel));
                        $cleaned_no_sampel = array_combine($cleaned_key_no_sampel, array_values($request->noSampel));
                        $cleaned_key_hasil = array_map(fn($k) => trim($k, " '\""), array_keys($request->hasil));
                        $cleaned_hasil = array_combine($cleaned_key_hasil, array_values($request->hasil));
                        $cleaned_key_tipe_getaran = array_map(fn($k) => trim($k, " '\""), array_keys($request->tipe_getaran));

                        $cleaned_tipe_getaran = array_combine($cleaned_key_tipe_getaran, array_values($request->tipe_getaran));
                        $cleaned_key_aktivitas = array_map(fn($k) => trim($k, " '\""), array_keys($request->aktivitas));
                        $cleaned_aktivitas = array_combine($cleaned_key_aktivitas, array_values($request->aktivitas));
                        // dd($cleaned_aktivitas);
                        $cleaned_key_sumber_getaran = array_map(fn($k) => trim($k, " '\""), array_keys($request->sumber_getaran));
                        $cleaned_sumber_getaran = array_combine($cleaned_key_sumber_getaran, array_values($request->sumber_getaran));
                        $cleaned_key_durasi_paparan = array_map(fn($k) => trim($k, " '\""), array_keys($request->waktu));
                        $cleaned_durasi_paparan = array_combine($cleaned_key_durasi_paparan, array_values($request->waktu));

                        if (array_key_exists($val, $cleaned_aktivitas)) {

                            $detail = new LhpsGetaranDetail;
                            $detail->id_header = $header->id;
                            $detail->no_sampel = $cleaned_no_sampel[$val];
                            $detail->aktivitas = $cleaned_aktivitas[$val];
                            $detail->sumber_get = $cleaned_sumber_getaran[$val];
                            $detail->w_paparan = $cleaned_durasi_paparan[$val];
                            $detail->hasil = $cleaned_hasil[$val];
                            $detail->tipe_getaran = $cleaned_tipe_getaran[$val];
                            $detail->save();
                        }
                    } else {
                        $cleaned_key_percepatan = array_map(fn($k) => trim($k, " '\""), array_keys($request->percepatan));
                        $cleaned_percepatan = array_combine($cleaned_key_percepatan, array_values($request->percepatan));
                        $cleaned_key_kecepatan = array_map(fn($k) => trim($k, " '\""), array_keys($request->kecepatan));
                        $cleaned_kecepatan = array_combine($cleaned_key_kecepatan, array_values($request->kecepatan));
                        $cleaned_key_tipe_getaran = array_map(fn($k) => trim($k, " '\""), array_keys($request->tipe_getaran));
                        $cleaned_tipe_getaran = array_combine($cleaned_key_tipe_getaran, array_values($request->tipe_getaran));
                        $cleaned_key_lokasi = array_map(fn($k) => trim($k, " '\""), array_keys($request->lokasi));
                        $cleaned_lokasi = array_combine($cleaned_key_lokasi, array_values($request->lokasi));
                        $cleaned_key_noSampel = array_map(fn($k) => trim($k, " '\""), array_keys($request->noSampel));
                        $cleaned_noSampel = array_combine($cleaned_key_noSampel, array_values($request->noSampel));

                        if (array_key_exists($val, $cleaned_noSampel)) {

                            $detail = new LhpsGetaranDetail;
                            $detail->id_header = $header->id;
                            $detail->no_sampel = $val;
                            $detail->keterangan = $cleaned_lokasi[$val];
                            $detail->percepatan = $cleaned_percepatan[$val];
                            $detail->kecepatan = $cleaned_percepatan[$val];
                            $detail->tipe_getaran = $cleaned_tipe_getaran[$val];
                            $detail->save();
                        }
                    }

                }

                $details = LhpsGetaranDetail::where('id_header', $header->id)->get();
                // dd($details);
                if ($header != null) {
                    $file_qr = new GenerateQrDocumentLhp();
                    $file_qr = $file_qr->insert('LHP_GETARAN', $header, $this->karyawan);
                    if ($file_qr) {
                        $header->file_qr = $file_qr;
                        $header->save();
                    }

                    $groupedByPage = [];
                    if (!empty($custom)) {
                        foreach ($custom as $item) {
                            $page = $item['page'];
                            if (!isset($groupedByPage[$page])) {
                                $groupedByPage[$page] = [];
                            }
                            $groupedByPage[$page][] = $item;
                        }
                    }

                    $job = new RenderLhp($header, $details, 'downloadWSDraft', $groupedByPage);
                    $this->dispatch($job);

                    $job = new RenderLhp($header, $details, 'downloadLHP', $groupedByPage);
                    $this->dispatch($job);

                    // $job = new RenderLhp($header, $details, 'downloadLHPFinal', $groupedByPage);
                    // $this->dispatch($job);

                    $fileName = 'LHP-' . str_replace("/", "-", $header->no_lhp) . '.pdf';

                    $header->file_lhp = $fileName;
                    $header->save();
                }
                DB::commit();

                return response()->json([
                    'message' => 'Data draft LHP Getaran no sampel ' . $request->no_sampel . ' berhasil disimpan',
                    'status' => true
                ], 201);
            } catch (\Exception $th) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                    'line' => $th->getLine(),
                    'status' => false
                ], 500);
            }

        } else if (in_array($category, $categoryPencahayaan)) {
            try {
                // Pencahayaan
                $header = LhpsPencahayaanHeader::where('no_lhp', $request->no_lhp)
                    ->where('no_order', $request->no_order)
                    ->where('id_kategori_3', $category)
                    ->where('is_active', true)
                    ->first();

                if ($header == null) {
                    $header = new LhpsPencahayaanHeader;
                    $header->created_by = $this->karyawan;
                    $header->created_at = Carbon::now()->format('Y-m-d H:i:s');
                } else {
                    $history = $header->replicate();
                    $history->setTable((new LhpsPencahayaanHeaderHistory())->getTable());
                    $history->created_by = $this->karyawan;
                    $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $history->updated_by = null;
                    $history->updated_at = null;
                    $history->save();
                    $header->updated_by = $this->karyawan;
                    $header->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                }
                $parameter = \explode(', ', $request->parameter);
                $header->no_order = ($request->no_order != '') ? $request->no_order : NULL;
                $header->no_lhp = ($request->no_lhp != '') ? $request->no_lhp : NULL;
                $header->id_kategori_2 = ($request->kategori_2 != '') ? explode('-', $request->kategori_2)[0] : NULL;
                $header->id_kategori_3 = ($category != '') ? $category : NULL;
                $header->no_qt = ($request->no_penawaran != '') ? $request->no_penawaran : NULL;
                $header->parameter_uji = json_encode($parameter);
                $header->nama_pelanggan = ($request->nama_perusahaan != '') ? $request->nama_perusahaan : NULL;
                $header->alamat_sampling = ($request->alamat_sampling != '') ? $request->alamat_sampling : NULL;
                $header->sub_kategori = ($request->jenis_sampel != '') ? $request->jenis_sampel : NULL;
                $header->deskripsi_titik = ($request->keterangan_1 != '') ? $request->keterangan_1 : NULL;
                $header->metode_sampling = ($request->metode_sampling != '') ? $request->metode_sampling : NULL;
                $header->tanggal_sampling = ($request->tanggal_tugas != '') ? $request->tanggal_tugas : NULL;
                $header->periode_analisa = ($request->periode_analisa != '') ? $request->periode_analisa : NULL;
                $header->nama_karyawan = 'Abidah Walfathiyyah';
                $header->jabatan_karyawan = 'Technical Control Supervisor';
                // $header->nama_karyawan = 'Kharina Waty';
                // $header->jabatan_karyawan = 'Technical Control Manager';
                $header->regulasi = ($request->regulasi != null) ? json_encode($request->regulasi) : NULL;
                $header->tanggal_lhp = ($request->tanggal_lhp != '') ? $request->tanggal_lhp : NULL;

                $header->save();
                $detail = LhpsPencahayaanDetail::where('id_header', $header->id)->first();
                if ($detail != null) {
                    $history = $detail->replicate();
                    $history->setTable((new LhpsPencahayaanDetailHistory())->getTable());
                    $history->created_by = $this->karyawan;
                    $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $history->save();
                }
                $detail = LhpsPencahayaanDetail::where('id_header', $header->id)->delete();
                foreach ($request->noSampel as $key => $val) {
                    $cleaned_key_hasil_uji = array_map(fn($k) => trim($k, " '\""), array_keys($request->hasil_uji));
                    $cleaned_hasil_uji = array_combine($cleaned_key_hasil_uji, array_values($request->hasil_uji));
                    $cleaned_key_lokasi = array_map(fn($k) => trim($k, " '\""), array_keys($request->lokasi));
                    $cleaned_lokasi = array_combine($cleaned_key_lokasi, array_values($request->lokasi));
                    $cleaned_key_noSampel = array_map(fn($k) => trim($k, " '\""), array_keys($request->noSampel));
                    $cleaned_noSampel = array_combine($cleaned_key_noSampel, array_values($request->noSampel));

                    if (array_key_exists($val, $cleaned_noSampel)) {

                        $detail = new LhpsPencahayaanDetail;
                        $detail->id_header = $header->id;
                        $detail->no_sampel = $val;
                        $detail->lokasi_keterangan = $cleaned_lokasi[$val];
                        $detail->hasil_uji = $cleaned_hasil_uji[$val];
                        $detail->save();
                    }
                }

                $details = LhpsPencahayaanDetail::where('id_header', $header->id)->get();
                if ($header != null) {
                    $file_qr = new GenerateQrDocumentLhp();
                    $file_qr = $file_qr->insert('LHP_PENCAHAYAAN', $header, $this->karyawan);
                    if ($file_qr) {
                        $header->file_qr = $file_qr;
                        $header->save();
                    }

                    $groupedByPage = [];
                    if (!empty($custom)) {
                        foreach ($custom as $item) {
                            $page = $item['page'];
                            if (!isset($groupedByPage[$page])) {
                                $groupedByPage[$page] = [];
                            }
                            $groupedByPage[$page][] = $item;
                        }
                    }

                    $job = new RenderLhp($header, $details, 'downloadWSDraft', $groupedByPage);
                    $this->dispatch($job);

                    $fileName = 'LHP-' . str_replace("/", "-", $header->no_lhp) . '.pdf';

                    $header->file_lhp = $fileName;
                    $header->save();
                }

                DB::commit();
                return response()->json([
                    'message' => 'Data draft LHP Pencahayaan no sampel ' . $request->no_sampel . ' berhasil disimpan',
                    'status' => true
                ], 201);
            } catch (\Exception $th) {
                DB::rollBack();
                dd($th);
                return response()->json([
                    'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                    'line' => $th->getLine(),
                    'status' => false
                ], 500);
            }

        } else if (in_array($category, $categoryLingkunganKerja)) {
            try {
                // Lingkungan Kerja

                $orderDetail = OrderDetail::where('id', $request->id)->where('is_active', true)->where('kategori_3', 'LIKE', "%{$category}%")->where('cfr', $request->no_lhp)->first();
                $orderDetailParameter = json_decode($orderDetail->parameter);
                $parameterNames = array_map(function ($param) {
                    $parts = explode(';', $param);
                    return $parts[1] ?? null;
                }, $orderDetailParameter);
                if (
                    in_array("Medan Listrik", $parameterNames) ||
                    in_array("Medan Magnit Statis", $parameterNames) ||
                    in_array("Power Density", $parameterNames)
                ) {
                    $id_kategori3 = explode('-', $request->kategori_3)[0];
                    $header = LhpsMedanLMHeader::where('no_lhp', $request->no_lhp)->where('no_order', $request->no_order)->where('id_kategori_3', $id_kategori3)->where('is_active', true)->first();


                    if ($header == null) {
                        $header = new LhpsMedanLMHeader;
                        $header->created_by = $this->karyawan;
                        $header->created_at = DATE('Y-m-d H:i:s');
                    } else {
                        $history = $header->replicate();
                        $history->setTable((new LhpsMedanLMHeaderHistory())->getTable());
                        $history->created_by = $this->karyawan;
                        $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                        $history->updated_by = null;
                        $history->updated_at = null;
                        $history->save();
                        $header->updated_by = $this->karyawan;
                        $header->updated_at = DATE('Y-m-d H:i:s');
                    }
                    $parameter = \explode(', ', $request->parameter);
                    // dd($parameter);
                    $keterangan = [];
                    if (is_array($request->keterangan)) {
                        foreach ($request->keterangan as $key => $value) {
                            if ($value != '') {
                                array_push($keterangan, $value);
                            }
                        }
                    }
                    $header->no_order = ($request->no_order != '') ? $request->no_order : NULL;
                    $header->no_lhp = ($request->no_lhp != '') ? $request->no_lhp : NULL;
                    $header->no_sampel = ($request->noSampel != '') ? $request->noSampel : NULL;
                    $header->no_qt = ($request->no_penawaran != '') ? $request->no_penawaran : NULL;
                    $header->jenis_sampel = ($request->sub_kategori != '') ? $request->sub_kategori : NULL;
                    $header->parameter_uji = json_encode($parameter);
                    $header->nama_karyawan = 'Abidah Walfathiyyah';
                    $header->jabatan_karyawan = 'Technical Control Supervisor';
                    // $header->nama_karyawan = 'Kharina Waty';
                    // $header->jabatan_karyawan = 'Technical Control Manager';
                    $header->nama_pelanggan = ($request->nama_perusahaan != '') ? $request->nama_perusahaan : NULL;
                    $header->alamat_sampling = ($request->alamat_sampling != '') ? $request->alamat_sampling : NULL;
                    $header->id_kategori_3 = ($id_kategori3 != '') ? $id_kategori3 : NULL;
                    $header->sub_kategori = ($request->sub_kategori != '') ? $request->sub_kategori : NULL;
                    $header->metode_sampling = ($request->metode_sampling != '') ? $request->metode_sampling : NULL;
                    $header->keterangan = ($keterangan != null) ? json_encode($keterangan) : NULL;
                    $header->tanggal_lhp = ($request->tanggal_lhp != '') ? $request->tanggal_lhp : NULL;
                    $header->tanggal_sampling = ($request->tgl_terima != '') ? $request->tgl_terima : NULL;
                    $header->tanggal_sampling_text = ($request->tgl_terima_hide != '') ? $request->tgl_terima_hide : NULL;
                    $header->periode_analisa = ($request->periode_analisa != '') ? $request->periode_analisa : NULL;
                    $header->regulasi = ($request->regulasi != null) ? json_encode($request->regulasi) : NULL;
                    if (count(array_filter($request->regulasi)) > 0) {
                        $header->id_regulasi = ($request->regulasi1 != null) ? $request->regulasi1 : NULL;
                    }

                    $header->save();
                    // dd($request->all());

                    $detail = LhpsMedanLMDetail::where('id_header', $header->id)->first();
                    // dd($detail);
                    if ($detail != null) {
                        $history = $detail->replicate();
                        $history->setTable((new LhpsMedanLMDetailHistory())->getTable());
                        $history->created_by = $this->karyawan;
                        $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                        $history->save();
                    }
                    $detail = LhpsMedanLMDetail::where('id_header', $header->id)->delete();
                    foreach (explode(',', $request->parameter) as $key => $val) {
                        $hasil = '';
                        $no_sampel = '';
                        $akr = '';
                        $satuan = '';
                        $attr = '';
                        $methode = '';
                        $val = trim($val, " '\"");
                        if ($request->hasil_uji) {
                            $cleaned_key_hasil_uji = array_map(fn($k) => trim($k, " '\""), array_keys($request->hasil_uji));
                            $cleaned_hasil_uji = array_combine($cleaned_key_hasil_uji, array_values($request->hasil_uji));
                        }

                        $cleaned_key_no_sampel = array_map(fn($k) => trim($k, " '\""), array_keys($request->no_sampel));
                        $cleaned_no_sampel = array_combine($cleaned_key_no_sampel, array_values($request->no_sampel));
                        $cleaned_key_nama_parameter = array_map(fn($k) => trim($k, " '\""), array_keys($request->nama_parameter));
                        $cleaned_nama_parameter = array_combine($cleaned_key_nama_parameter, array_values($request->nama_parameter));
                        $cleaned_key_attr = array_map(fn($k) => trim($k, " '\""), array_keys($request->attr));
                        $cleaned_attr = array_combine($cleaned_key_attr, array_values($request->attr));
                        $cleaned_key_akr = array_map(fn($k) => trim($k, " '\""), array_keys($request->akr));
                        $cleaned_akr = array_combine($cleaned_key_akr, array_values($request->akr));
                        // $cleaned_key_satuan = array_map(fn($k) => trim($k, " '\""), array_keys($request->satuan));
                        // $cleaned_satuan = array_combine($cleaned_key_satuan, array_values($request->satuan));
                        $cleaned_key_hasil = array_map(fn($k) => trim($k, " '\""), array_keys($request->hasil));
                        $cleaned_hasil = array_combine($cleaned_key_hasil, array_values($request->hasil));
                        $cleaned_key_methode = array_map(fn($k) => trim($k, " '\""), array_keys($request->methode ?? []));
                        $cleaned_methode = array_combine($cleaned_key_methode, array_values($request->methode ?? []));
                        // dd($request->no_sampel);
                        if (!empty($cleaned_hasil[$val])) {
                            $hasil = $cleaned_hasil[$val];
                        }
                        // dd(array_key_exists($val, $cleaned_nama_parameter));

                        if (array_key_exists($val, $cleaned_nama_parameter)) {
                            $parame = Parameter::where('id_kategori', 4)->where('nama_lab', $val)->where('is_active', true)->first();

                            $detail = new LhpsMedanLMDetail;
                            $detail->id_header = $header->id;
                            $detail->no_sampel = $cleaned_no_sampel[$val];
                            // $detail->parameter_uji = $val;
                            $detail->parameter = $parame->nama_regulasi;
                            $detail->akr = $cleaned_akr[$val];
                            $detail->attr = $cleaned_attr[$val];
                            $detail->hasil = $hasil;
                            $detail->attr = (isset($cleaned_attr[$val]) ? $cleaned_attr[$val] : '');
                            $detail->methode = (isset($cleaned_methode[$val]) ? $cleaned_methode[$val] : '');
                            $detail->save();

                        }
                    }
                    $details = LhpsMedanLMDetail::where('id_header', $header->id)->get();
                    if ($header != null) {
                        $file_qr = new GenerateQrDocumentLhp();
                        $file_qr = $file_qr->insert('LHP_MEDAN_LM', $header, $this->karyawan);
                        if ($file_qr) {
                            $header->file_qr = $file_qr;
                            $header->save();
                        }

                        $groupedByPage = [];
                        if (!empty($custom)) {
                            foreach ($custom as $item) {
                                $page = $item['page'];
                                if (!isset($groupedByPage[$page])) {
                                    $groupedByPage[$page] = [];
                                }
                                $groupedByPage[$page][] = $item;
                            }
                        }

                        $job = new RenderLhp($header, $details, 'downloadWSDraft', $groupedByPage);
                        $this->dispatch($job);

                        $job = new RenderLhp($header, $details, 'downloadLHP', $groupedByPage);
                        $this->dispatch($job);

                        $fileName = 'LHP-' . str_replace("/", "-", $header->no_lhp) . '.pdf';
                        $header->file_lhp = $fileName;
                        $header->save();
                    }

                } else if (in_array("Sinar UV", $orderDetailParameter)) {
                    // dd('Sinar UV');

                    $header = LhpSinaruvHeader::where('no_lhp', $request->no_lhp)->where('no_order', $request->no_order)->where('kategori_3', $request->kategori_3)->where('is_active', true)->first();

                    if ($header == null) {
                        $header = new LhpSinaruvHeader;
                        $header->create_by = $this->userid;
                        $header->create_at = DATE('Y-m-d H:i:s');
                    } else {
                        $history = $header->replicate();
                        $history->setTable((new LhpSinarUVHeaderHistory())->getTable());
                        $history->created_by = $this->karyawan;
                        $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                        $history->updated_by = null;
                        $history->updated_at = null;
                        $history->save();
                        $header->updated_by = $this->userid;
                        $header->updated_at = DATE('Y-m-d H:i:s');
                    }

                    $parameter_uji = \explode(', ', $request->parameter_uji);
                    $keterangan = [];
                    foreach ($request->keterangan as $key => $value) {
                        if ($value != '')
                            array_push($keterangan, $value);
                    }
                    $header->no_order = ($request->no_order != '') ? $request->no_order : NULL;
                    $header->no_lhp = ($request->no_lhp != '') ? $request->no_lhp : NULL;
                    $header->no_qt = ($request->no_penawaran != '') ? $request->no_penawaran : NULL;
                    $header->jenis_sampel = ($request->sub_kategori != '') ? $request->sub_kategori : NULL;
                    $header->parameter_uji = json_encode($parameter_uji);
                    $header->nama_karyawan = 'Abidah Walfathiyyah';
                    $header->jabatan_karyawan = 'Technical Control Supervisor';
                    // $header->nama_karyawan = 'Kharina Waty';
                    // $header->jabatan_karyawan = 'Technical Control Manager';
                    $header->nama_pelanggan = ($request->nama_perusahaan != '') ? $request->nama_perusahaan : NULL;
                    $header->alamat_sampling = ($request->alamat_sampling != '') ? $request->alamat_sampling : NULL;
                    $header->kategori_3 = ($request->kategori_3 != '') ? $request->kategori_3 : NULL;
                    $header->sub_kategori = ($request->sub_kategori != '') ? $request->sub_kategori : NULL;
                    $header->keterangan = ($keterangan != null) ? json_encode($keterangan) : NULL;
                    $header->tgl_lhp = ($request->tgl_lhp != '') ? $request->tgl_lhp : NULL;
                    $header->tgl_sampling = ($request->tgl_terima != '') ? $request->tgl_terima : NULL;
                    $header->tgl_sampling_text = ($request->tgl_terima_hide != '') ? $request->tgl_terima_hide : NULL;
                    $header->periode_analisa = ($request->periode_analisa != '') ? $request->periode_analisa : NULL;
                    $header->regulasi = ($request->regulasi != null) ? json_encode($request->regulasi) : NULL;
                    if (count(array_filter($request->regulasi)) > 0) {
                        $header->id_regulasi = ($request->regulasi1 != null) ? $request->regulasi1 : NULL;
                    }
                    $header->save();

                    $detail = LhpSinaruvHeader::where('id_header', $header->id)->first();
                    if ($detail != null) {
                        $history = $detail->replicate();
                        $history->setTable((new LhpSinarUVHeaderHistory())->getTable());
                        $history->created_by = $this->karyawan;
                        $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                        $history->save();
                    }
                    $detai = LhpSinaruvDetail::where('id_header', $header->id)->delete();
                    foreach ($request->no_sampel as $key => $val) {

                        $detail = new LhpSinaruvDetail;
                        $detail->id_header = $header->id;
                        $detail->no_sampel = $request->no_sampel[$key];
                        $detail->keterangan = $request->lokasi_keterangan[$key];
                        $detail->akr = $request->akr[$key];
                        $detail->attr = (isset($request->attr[$key]) ? $request->attr[$key] : '');
                        $detail->aktivitas_pekerjaan = $request->aktivitas_pekerjaan[$key];
                        $detail->sumber_radiasi = $request->sumber_radiasi[$key];
                        $detail->waktu_paparan = $request->waktu_paparan[$key];
                        $detail->mata = $request->mata[$key];
                        $detail->betis = $request->betis[$key];
                        $detail->siku = $request->siku[$key];
                        $detail->methode = (isset($request->methode[$key]) ? $request->methode[$key] : '');
                        $detail->save();
                    }
                } else {

                    $header = LhpsLingHeader::where('no_sampel', $request->no_sampel)->where('no_order', $request->no_order)->where('id_kategori_3', $category)->where('is_active', true)->first();
                    if ($header == null) {
                        $header = new LhpsLingHeader;
                        $header->created_by = $this->karyawan;
                        $header->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    } else {
                        $history = $header->replicate();
                        $history->setTable((new LhpsLingHeaderHistory())->getTable());
                        $history->created_by = $this->karyawan;
                        $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                        $history->updated_by = null;
                        $history->updated_at = null;
                        $history->save();
                        $header->updated_by = $this->karyawan;
                        $header->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                    }
                    $parameter = \explode(', ', $request->parameter);
                    $keterangan = [];
                    foreach ($request->keterangan as $key => $value) {
                        if ($value != '')
                            array_push($keterangan, $value);
                    }
                    $header->no_order = ($request->no_order != '') ? $request->no_order : NULL;
                    $header->no_lhp = ($request->no_lhp != '') ? $request->no_lhp : NULL;
                    $header->no_sampel = ($request->no_sampel != '') ? $request->no_sampel : NULL;
                    $header->id_kategori_2 = ($request->kategori_2 != '') ? explode('-', $request->kategori_2)[0] : NULL;
                    $header->id_kategori_3 = ($category != '') ? $category : NULL;
                    $header->no_qt = ($request->no_penawaran != '') ? $request->no_penawaran : NULL;
                    $header->parameter_uji = json_encode($parameter);
                    $header->nama_pelanggan = ($request->nama_perusahaan != '') ? $request->nama_perusahaan : NULL;
                    $header->alamat_sampling = ($request->alamat_sampling != '') ? $request->alamat_sampling : NULL;
                    $header->sub_kategori = ($request->jenis_sampel != '') ? $request->jenis_sampel : NULL;
                    $header->deskripsi_titik = ($request->keterangan_1 != '') ? $request->keterangan_1 : NULL;
                    $header->methode_sampling = ($request->metode_sampling != '') ? $request->metode_sampling : NULL;
                    $header->tanggal_sampling = ($request->tanggal_tugas != '') ? $request->tanggal_tugas : NULL;
                    $header->periode_analisa = ($request->periode_analisa != '') ? $request->periode_analisa : NULL;
                    $header->nama_karyawan = 'Abidah Walfathiyyah';
                    $header->jabatan_karyawan = 'Technical Control Supervisor';
                    // $header->nama_karyawan = 'Kharina Waty';
                    // $header->jabatan_karyawan = 'Technical Control Manager';
                    $header->keterangan = ($keterangan != null) ? json_encode($keterangan) : NULL;
                    $header->regulasi = ($request->regulasi != null) ? json_encode($request->regulasi) : NULL;
                    $header->suhu = ($request->suhu_udara != '') ? $request->suhu_udara : NULL;
                    $header->cuaca = ($request->cuaca != '') ? $request->cuaca : NULL;
                    $header->arah_angin = ($request->arah_angin != '') ? $request->arah_angin : NULL;
                    $header->kelembapan = ($request->kelembapan_udara != '') ? $request->kelembapan_udara : NULL;
                    $header->kec_angin = ($request->kecepatan_angin != '') ? $request->kecepatan_angin : NULL;
                    $header->titik_koordinat = ($request->titik_koordinat != '') ? $request->titik_koordinat : NULL;
                    // $header->header_table        = ($request->header_table != '') ? $request->header_table : NULL;
                    // $header->file_qr             = ($request->file_qr != '') ? $request->file_qr : NULL;
                    $header->tanggal_lhp = ($request->tanggal_lhp != '') ? $request->tanggal_lhp : NULL;
                    $header->save();

                    $detail = LhpsLingDetail::where('id_header', $header->id)->first();
                    if ($detail != null) {
                        $history = $detail->replicate();
                        $history->setTable((new LhpsLingDetailHistory())->getTable());
                        $history->created_by = $this->karyawan;
                        $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                        $history->save();
                    }
                    $detail = LhpsLingDetail::where('id_header', $header->id)->delete();

                    foreach (explode(',', $request->parameter) as $key => $val) {
                        $hasil = '';
                        $akr = '';
                        $satuan = '';
                        $attr = '';
                        $methode = '';
                        $bakumutu = '';
                        $val = trim($val, " '\"");
                        if ($request->hasil_uji) {
                            $cleaned_key_hasil_uji = array_map(fn($k) => trim($k, " '\""), array_keys($request->hasil_uji));
                            $cleaned_hasil_uji = array_combine($cleaned_key_hasil_uji, array_values($request->hasil_uji));
                        }
                        if ($request->C) {
                            $cleaned_key_C = array_map(fn($k) => trim($k, " '\""), array_keys($request->C));
                            $cleaned_C = array_combine($cleaned_key_C, array_values($request->C));
                            $cleaned_key_C1 = array_map(fn($k) => trim($k, " '\""), array_keys($request->C1));
                            $cleaned_C1 = array_combine($cleaned_key_C1, array_values($request->C1));
                            $cleaned_key_C2 = array_map(fn($k) => trim($k, " '\""), array_keys($request->C2));
                            $cleaned_C2 = array_combine($cleaned_key_C2, array_values($request->C2));
                        }
                        $cleaned_key_akr = array_map(fn($k) => trim($k, " '\""), array_keys($request->akr));
                        $cleaned_akr = array_combine($cleaned_key_akr, array_values($request->akr));
                        $cleaned_key_satuan = array_map(fn($k) => trim($k, " '\""), array_keys($request->satuan));
                        $cleaned_satuan = array_combine($cleaned_key_satuan, array_values($request->satuan));
                        $cleaned_key_attr = array_map(fn($k) => trim($k, " '\""), array_keys($request->attr));
                        $cleaned_attr = array_combine($cleaned_key_attr, array_values($request->attr));
                        $cleaned_key_methode = array_map(fn($k) => trim($k, " '\""), array_keys($request->methode));
                        $cleaned_methode = array_combine($cleaned_key_methode, array_values($request->methode));
                        $cleaned_key_bakumutu = array_map(fn($k) => trim($k, " '\""), array_keys($request->baku_mutu));
                        $cleaned_bakumutu = array_combine($cleaned_key_bakumutu, array_values($request->baku_mutu));
                        $cleaned_key_durasi = array_map(fn($k) => trim($k, " '\""), array_keys($request->durasi));
                        $cleaned_durasi = array_combine($cleaned_key_durasi, array_values($request->durasi));
                        if (!empty($cleaned_hasil_uji[$val])) {
                            $hasil = $cleaned_hasil_uji[$val];
                        } else {
                            if (!empty($cleaned_C[$val])) {
                                $hasil = $cleaned_C[$val];
                            } else if (!empty($cleaned_C1[$val])) {
                                $hasil = $cleaned_C1[$val];
                            } else if (!empty($cleaned_C2[$val])) {
                                $hasil = $cleaned_C2[$val];
                            }
                        }
                        if (array_key_exists($val, $cleaned_akr)) {
                            $parame = Parameter::where('id_kategori', 4)->where('nama_lab', $val)->where('is_active', true)->first();

                            $detail = new LhpsLingDetail;
                            $detail->id_header = $header->id;
                            $detail->parameter_lab = $val;
                            $detail->parameter = $parame->nama_regulasi;
                            $detail->durasi = (isset($cleaned_durasi[$val]) ? $cleaned_durasi[$val] : '');
                            $detail->akr = $cleaned_akr[$val];
                            $detail->hasil_uji = $hasil;
                            $detail->satuan = (isset($cleaned_satuan[$val]) ? $cleaned_satuan[$val] : '');
                            $detail->attr = (isset($cleaned_attr[$val]) ? $cleaned_attr[$val] : '');
                            $detail->methode = (isset($cleaned_methode[$val]) ? $cleaned_methode[$val] : '');
                            $detail->baku_mutu = (isset($cleaned_bakumutu[$val]) ? json_encode(array($cleaned_bakumutu[$val])) : '');
                            $detail->save();
                        }
                    }

                    $details = LhpsLingDetail::where('id_header', $header->id)->get();
                    if ($header != null) {
                        $file_qr = new GenerateQrDocumentLhp();
                        $file_qr = $file_qr->insert('LHP_LINGKUNGAN', $header, $this->karyawan);
                        if ($file_qr) {
                            $header->file_qr = $file_qr;
                            $header->save();
                        }

                        $groupedByPage = [];
                        if (!empty($custom)) {
                            foreach ($custom as $item) {
                                $page = $item['page'];
                                if (!isset($groupedByPage[$page])) {
                                    $groupedByPage[$page] = [];
                                }
                                $groupedByPage[$page][] = $item;
                            }
                        }

                        $job = new RenderLhp($header, $details, 'downloadWSDraft', $groupedByPage);
                        $this->dispatch($job);

                        $job = new RenderLhp($header, $details, 'downloadLHP', $groupedByPage);
                        $this->dispatch($job);

                        $fileName = 'LHP-' . str_replace("/", "-", $header->no_lhp) . '.pdf';
                        $header->file_lhp = $fileName;
                        $header->save();
                    }
                }
                // dd($header);
                DB::commit();
                return response()->json([
                    'message' => 'Data draft LHP Lingkungan no sampel ' . $request->no_lhp . ' berhasil disimpan',
                    'status' => true
                ], 201);
            } catch (\Exception $th) {
                DB::rollBack();
                dd($th);
                return response()->json([
                    'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                    'line' => $th->getLine(),
                    'status' => false
                ], 500);
            }
        }
    }

    //Amang
    public function handleDatadetail(Request $request)
    {
        try {
            $categoryKebisingan = [23, 24, 25];
            $categoryGetaran = [13, 14, 15, 16, 17, 18, 19, 20];
            $categoryLingkunganKerja = [11, 27, 53];
            $categoryPencahayaan = [28];
            $data_lapangan = array();
            $id_category = explode('-', $request->kategori_3)[0];

            $parameters = json_decode(html_entity_decode($request->parameter), true);
            $parameterArray = is_array($parameters) ? array_map('trim', explode(';', $parameters[0])) : [];
            // dd('id_category', $id_category);
            if (in_array($id_category, $categoryLingkunganKerja)) {
                $data = array();
                $data1 = array();
                $hasil = [];
                // if ($request->submenu == 'Ergonomi') {
                //     $data = DataLapanganErgonomi::where('no_sampel', $request->no_sampel)->where('is_approve', 1)->get();
                //     if ($data->isNotEmpty()) {
                //         return Datatables::of($data)->make(true);
                //     } else {
                //         return response()->json([
                //             'status' => false,
                //             'message' => 'Data tidak ditemukan'
                //         ], 404);
                //     }
                if ($parameterArray[1] == 'Ergonomi') {
                    $data = ErgonomiHeader::with('datalapangan')
                        ->where('no_sampel', $request->no_sampel)
                        ->where('is_approve', true)
                        ->where('is_active', true)
                        ->select('*') // pastikan select ada
                        ->addSelect(DB::raw("'ergonomi' as data_type"));

                    return Datatables::of($data)->make(true);
                } else if ($parameterArray[1] == 'Sinar UV') {
                    $data = SinarUvHeader::with('datalapangan', 'ws_udara')
                        ->where('no_sampel', $request->no_sampel)
                        ->where('is_approved', true)
                        ->where('is_active', true)
                        ->select('*')
                        ->addSelect(DB::raw("'sinar_uv' as data_type"))
                        ->get();

                    foreach ($data as $item) {
                        $waktu = $item->datalapangan->waktu_pemaparan ?? null;

                        if ($waktu !== null) {
                            if ($waktu >= 1 && $waktu < 5) {
                                $item->nab = 0.05;
                            } elseif ($waktu >= 5 && $waktu < 10) {
                                $item->nab = 0.01;
                            } elseif ($waktu >= 10 && $waktu < 15) {
                                $item->nab = 0.005;
                            } elseif ($waktu >= 15 && $waktu < 30) {
                                $item->nab = 0.0033;
                            } elseif ($waktu >= 30 && $waktu < 60) {
                                $item->nab = 0.0017;
                            } elseif ($waktu >= 60 && $waktu < 120) {
                                $item->nab = 0.0008;
                            } elseif ($waktu >= 120 && $waktu < 240) {
                                $item->nab = 0.0004;
                            } elseif ($waktu >= 240 && $waktu < 480) {
                                $item->nab = 0.0002;
                            } elseif ($waktu >= 480) {
                                $item->nab = 0.0001;
                            } else {
                                $item->nab = null;
                            }
                        } else {
                            $item->nab = null;
                        }
                    }

                    return Datatables::of($data)->make(true);

                } else if ($parameterArray[1] == 'Debu (P8J)') {
                    $data = DebuPersonalHeader::with('data_lapangan', 'ws_lingkungan')
                        ->where('no_sampel', $request->no_sampel)
                        ->where('is_approved', true)
                        ->where('is_active', true)
                        ->select('*')
                        ->addSelect(DB::raw("'debu_personal' as data_type"));

                    return Datatables::of($data)->make(true);
                } else if ($parameterArray[1] == 'Medan Magnit Statis' || $parameterArray[1] == 'Medan Listrik' || $parameterArray[1] == 'Power Density') {
                    $data = MedanLmHeader::with('ws_udara', 'master_parameter')
                        ->where('no_sampel', $request->no_sampel)
                        ->where('is_approve', true)
                        ->where('is_active', true)->get();
                    $i = 0;
                    $method_regulasi = [];
                    if ($data->isNotEmpty()) {
                        foreach ($data as $key => $val) {
                            // dd($val);
                            $hasil2 = json_decode($val->ws_udara->hasil1) ?? null;
                            $data1[$i]['id'] = $val->id;
                            $data1[$i]['no_sampel'] = $val->no_sampel;
                            $data1[$i]['name'] = $val->parameter;
                            $data1[$i]['keterangan'] = $val->master_parameter->nama_regulasi ?? null;
                            $data1[$i]['satuan'] = $val->master_parameter->satuan ?? null;
                            $data1[$i]['hasil'] = $hasil2->medan_magnet ?? null;

                            $data1[$i]['methode'] = $val->master_parameter->method ?? null;
                            $data1[$i]['baku_mutu'] = is_object($val->master_parameter) && $val->master_parameter->nilai_minimum ? \explode('#', $val->master_parameter->nilai_minimum) : null;
                            $data1[$i]['status'] = $val->master_parameter->status ?? null;
                            $bakumutu = MasterBakumutu::where('id_regulasi', $request->regulasi)
                                ->where('parameter', $val->parameter)
                                ->first();
                            if ($bakumutu != null && $bakumutu->method != '') {
                                $data1[$i]['satuan'] = $bakumutu->satuan;
                                $data1[$i]['methode'] = $bakumutu->method;
                                $data1[$i]['baku_mutu'][0] = $bakumutu->baku_mutu;
                                array_push($method_regulasi, $bakumutu->method);
                            }
                            $i++;
                        }
                        $hasil[] = $data1;
                    }

                } else {
                    $data2 = Subkontrak::with('ws_value_linkungan', 'master_parameter')
                        ->where('no_sampel', $request->no_sampel)
                        ->where('is_approve', 1)
                        ->where('is_active', true)
                        ->get();

                    $data3 = DirectLainHeader::with('ws_udara', 'master_parameter')
                        ->where('no_sampel', $request->no_sampel)
                        ->where('is_approve', 1)
                        ->where('is_active', true)
                        ->get();

                    $data = LingkunganHeader::with('ws_value_linkungan', 'master_parameter')
                        ->where('no_sampel', $request->no_sampel)
                        ->where('is_approved', 1)
                        ->where('is_active', true)
                        ->where('lhps', 1)
                        ->get();

                    if ($data2->isNotEmpty()) {
                        $data = $data->merge($data2);
                    }

                    if ($data3->isNotEmpty()) {
                        $data = $data->merge($data3);
                    }

                    $i = 0;
                    $method_regulasi = [];
                    if ($data->isNotEmpty()) {
                        foreach ($data as $key => $val) {
                            $data1[$i]['id'] = $val->id;
                            $data1[$i]['name'] = $val->parameter;
                            $data1[$i]['keterangan'] = $val->master_parameter->nama_regulasi ?? null;
                            $data1[$i]['satuan'] = $val->master_parameter->satuan ?? null;
                            $data1[$i]['durasi'] = $val->ws_value_linkungan->durasi ?? null;
                            $data1[$i]['C'] = $val->ws_value_linkungan->f_koreksi_c ?? $val->ws_value_linkungan->C ?? null;
                            $data1[$i]['C1'] = $val->ws_value_linkungan->f_koreksi_c1 ?? $val->ws_value_linkungan->C1 ?? null;
                            $data1[$i]['C2'] = $val->ws_value_linkungan->f_koreksi_c2 ?? $val->ws_value_linkungan->C2 ?? null;
                            $data1[$i]['methode'] = $val->master_parameter->method ?? null;
                            $data1[$i]['baku_mutu'] = is_object($val->master_parameter) && $val->master_parameter->nilai_minimum ? \explode('#', $val->master_parameter->nilai_minimum) : null;
                            $data1[$i]['status'] = $val->master_parameter->status ?? null;
                            // dd($id_category);
                            if ($id_category == 11) {
                                $data_lapangan[$i]['suhu'] = $val->ws_value_linkungan->detailLingkunganHidup->suhu;
                                $data_lapangan[$i]['kelembapan'] = $val->ws_value_linkungan->detailLingkunganHidup->kelembapan;
                                $data_lapangan[$i]['keterangan'] = $val->ws_value_linkungan->detailLingkunganHidup->keterangan;
                                $data_lapangan[$i]['cuaca'] = $val->ws_value_linkungan->detailLingkunganHidup->cuaca;
                                $data_lapangan[$i]['kecepatan_angin'] = $val->ws_value_linkungan->detailLingkunganHidup->kecepatan_angin;
                                $data_lapangan[$i]['arah_angin'] = $val->ws_value_linkungan->detailLingkunganHidup->arah_angin;
                                $data_lapangan[$i]['titik_koordinat'] = $val->ws_value_linkungan->detailLingkunganHidup->titik_koordinat;
                            }

                            $bakumutu = MasterBakumutu::where('id_regulasi', $request->regulasi)
                                ->where('parameter', $val->parameter)
                                ->first();

                            if ($bakumutu != null && $bakumutu->method != '') {
                                $data1[$i]['satuan'] = $bakumutu->satuan;
                                $data1[$i]['methode'] = $bakumutu->method;
                                $data1[$i]['baku_mutu'][0] = $bakumutu->baku_mutu;
                                array_push($method_regulasi, $bakumutu->method);
                            }
                            $i++;
                        }
                        $hasil[] = $data1;
                    }
                }
            } else if (in_array($id_category, $categoryPencahayaan)) {
                $data = array();
                $data1 = array();
                $hasil = [];
                $orders = OrderDetail::where('cfr', $request->cfr)
                    ->where('is_approve', 0)
                    ->where('is_active', true)
                    ->where('kategori_2', '4-Udara')
                    ->where('kategori_3', $request->kategori_3)
                    // ->where('is_approve', true)
                    ->where('status', 2)
                    ->pluck('no_sampel');
                $data = PencahayaanHeader::with('ws_udara', 'data_lapangan')->whereIn('no_sampel', $orders)->where('is_approved', 1)->where('is_active', true)->where('lhps', 1)->get();
                // dd( $data);
                $i = 0;
                $method_regulasi = [];
                if ($data->isNotEmpty()) {

                    foreach ($data as $key => $val) {
                        // dd($val);
                        $data1[$i]['id'] = $val->id;
                        $data1[$i]['no_sampel'] = $val->no_sampel;
                        $data1[$i]['lokasi'] = $val->data_lapangan->keterangan;
                        $data1[$i]['hasil_uji'] = $val->ws_udara->hasil1;

                        $bakumutu = MasterBakumutu::where('id_regulasi', $request->regulasi)
                            ->where('parameter', $val->parameter)
                            ->first();

                        if ($bakumutu != null && $bakumutu->method != '') {
                            $data1[$i]['satuan'] = $bakumutu->satuan;
                            $data1[$i]['methode'] = $bakumutu->method;
                            $data1[$i]['baku_mutu'][0] = $bakumutu->baku_mutu;
                            array_push($method_regulasi, $bakumutu->method);
                        }

                        $i++;
                    }
                    $hasil[] = $data1;
                }
            } else if (in_array($id_category, $categoryGetaran)) {
                $data = array();
                $data1 = array();
                $hasil = []; // Inisialisasi variabel agar tidak undefined
                $data = GetaranHeader::with('ws_udara', 'lapangan_getaran', 'master_parameter', 'lapangan_getaran_personal')->where('no_sampel', $request->no_sampel)->where('is_approve', 1)->where('is_active', true)->where('lhps', 1)->get();
                $i = 0;
                $method_regulasi = [];
                if ($data->isNotEmpty()) {

                    foreach ($data as $key => $val) {
                        $data1[$i]['id'] = $val->id;
                        $data1[$i]['name'] = $val->parameter;


                        $data1[$i]['satuan'] = $val->master_parameter->satuan;
                        $data1[$i]['hasil1'] = ($val->ws_udara->hasil1 != null) ? json_decode($val->ws_udara->hasil1) : '';
                        $data1[$i]['hasil2'] = $val->ws_udara->hasil2;
                        $data1[$i]['hasil3'] = $val->ws_udara->hasil3;
                        $data1[$i]['methode'] = $val->master_parameter->method; //
                        $data1[$i]['baku_mutu'] = \explode('#', $val->master_parameter->nilai_minimum);
                        $data1[$i]['status'] = $val->master_parameter->status;

                        if ($val->parameter == "Getaran (LK) ST" || $val->parameter == "Getaran (LK) TL") {
                            $data1[$i]['data_lapangan'] = $val->lapangan_getaran_personal;
                            $data1[$i]['no_sampel'] = $val->lapangan_getaran_personal->no_sampel;
                            $data1[$i]['keterangan'] = $val->lapangan_getaran_personal->keterangan;
                            $data1[$i]['tipe_getaran'] = 'getaran personal';
                        } else {
                            $data1[$i]['data_lapangan'] = $val->lapangan_getaran;
                            $data1[$i]['no_sampel'] = $val->lapangan_getaran->no_sampel;
                            $data1[$i]['keterangan'] = $val->lapangan_getaran->keterangan;
                            $data1[$i]['tipe_getaran'] = 'getaran';
                        }
                        // $data_lapangan[$i]['jarak_sumber_getaran'] = $val->lapangan_getaran->jarak_sumber_getaran;
                        // $data_lapangan[$i]['kondisi'] = $val->lapangan_getaran->kondisi;
                        // $data_lapangan[$i]['intensitas'] = $val->lapangan_getaran->intensitas;
                        // $data_lapangan[$i]['sumber_getaran'] = $val->lapangan_getaran->sumber_getaran;

                        $bakumutu = MasterBakumutu::where('id_regulasi', $request->regulasi)
                            ->where('parameter', $val->parameter)
                            ->first();

                        if ($bakumutu != null && $bakumutu->method != '') {
                            $data1[$i]['satuan'] = $bakumutu->satuan;
                            $data1[$i]['methode'] = $bakumutu->method;
                            $data1[$i]['baku_mutu'][0] = $bakumutu->baku_mutu;
                            array_push($method_regulasi, $bakumutu->method);
                        }

                        $i++;
                    }
                    $hasil[] = $data1;
                }
            } else if (in_array($id_category, $categoryKebisingan)) {
                // dd('masuk');

                $data = array();
                $data1 = array();
                $hasil = [];
                $orders = OrderDetail::where('cfr', $request->cfr)
                    ->where('is_approve', 0)
                    ->where('is_active', true)
                    ->where('kategori_2', '4-Udara')
                    ->where('kategori_3', $request->kategori_3)
                    // ->where('is_approve', true)
                    ->where('status', 2)
                    ->pluck('no_sampel');
                // dd($orders);
                // berubah is_activenya
                // $data = KebisinganHeader::with('ws_udara', 'data_lapangan')->whereIn('no_sampel', $orders)->where('is_approved', 1)->where('is_active', 0)->where('lhps', 1)->get();
                $data = KebisinganHeader::with('ws_udara', 'data_lapangan')->whereIn('no_sampel', $orders)->where('is_approved', 1)->where('is_active', 1)->where('lhps', 1)->get();
                // dd($data);
                $i = 0;
                $method_regulasi = [];
                if ($data->isNotEmpty()) {

                    foreach ($data as $key => $val) {
                        // dd($val);
                        $data1[$i]['id'] = $val->id;
                        $data1[$i]['no_sampel'] = $val->no_sampel;
                        $data1[$i]['titik_koordinat'] = $val->data_lapangan->titik_koordinat;
                        $data1[$i]['min'] = $val->min;
                        $data1[$i]['max'] = $val->max;
                        $data1[$i]['lokasi'] = $val->data_lapangan->lokasi_titik_sampling;
                        $data1[$i]['hasil_uji'] = $val->ws_udara->hasil1;
                        $data1[$i]['ls'] = $val->ws_udara->hasil1;
                        $data1[$i]['lm'] = $val->ws_udara->hasil1;
                        $data1[$i]['leq_ls'] = $val->ws_udara->hasil1;
                        $data1[$i]['leq_lm'] = $val->ws_udara->hasil1;
                        $data_lapangan[$i]['suhu'] = $val->data_lapangan->suhu_udara;
                        $data_lapangan[$i]['kelembapan'] = $val->data_lapangan->kelembapan_udara;
                        $i++;
                    }
                    $hasil[] = $data1;
                }
            }

            $data_all = array();
            $a = 0;
            foreach ($hasil as $key => $value) {
                foreach ($value as $row => $col) {
                    $data_all[$a] = $col;
                    $a++;
                }

            }
            $method_regulasi = array_values(array_unique($method_regulasi));
            $method = Parameter::where('is_active', true)->where('id_kategori', 1)->whereNotNull('method')->select('method')->groupBy('method')->get()->toArray();
            $result_method = array_unique(array_values(array_merge($method_regulasi, array_column($method, 'method'))));

            if ($id_category == 11 || $id_category == 27) {
                $keterangan = [
                    '▲ Hasil Uji melampaui nilai ambang batas yang diperbolehkan.',
                    '↘ Parameter diuji langsung oleh pihak pelanggan, bukan bagian dari parameter yang dilaporkan oleh laboratorium.',
                    'ẍ Parameter belum terakreditasi.'
                ];
            } else {
                $keterangan = [];
            }
            if (count($data_lapangan) > 0) {
                $data_lapangan_send = (object) $data_lapangan[0];
            } else {
                $data_lapangan_send = (object) $data_lapangan;
            }

            return response()->json([
                'status' => true,
                'data' => $data_all,
                'data_lapangan' => $data_lapangan_send,
                'spesifikasi_method' => $result_method,
                'keterangan' => $keterangan
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            dd($e);
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan ' . $e->getMessage()
            ]);
        }
    }

    public function handleDetailEdit(Request $request)
    {
        $categoryKebisingan = [23, 24, 25];
        $categoryGetaran = [13, 14, 15, 16, 17, 18, 19, 20];
        $categoryLingkunganKerja = [11, 27, 53];
        $categoryPencahayaan = [28];
        $category = explode('-', $request->kategori_3)[0];
        $sub_category = explode('-', $request->kategori_3)[1];
        $parameters = json_decode(html_entity_decode($request->parameter), true);
        $parameterArray = is_array($parameters) ? array_map('trim', explode(';', $parameters[0])) : [];

        if (in_array($category, $categoryKebisingan)) {
            try {
                $data = LhpsKebisinganHeader::where('no_lhp', $request->no_lhp)
                    ->where('id_kategori_3', $category)
                    ->where('is_active', true)
                    ->first();
                $details = LhpsKebisinganDetail::where('id_header', $data->id)->get();
                $spesifikasiMethode = KebisinganHeader::with('ws_udara', 'data_lapangan', 'master_parameter')
                    ->where('no_sampel', $request->no_sampel)
                    ->where('is_approved', 1)
                    ->where('is_active', 0)
                    ->where('lhps', 1)->get();

                return response()->json([
                    'data' => $data,
                    'details' => $details,
                ], 201);
            } catch (\Exception $th) {
                DB::rollBack();
                dd($th);
                return response()->json([
                    'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                    'status' => false
                ], 500);
            }
        } else if (in_array($category, $categoryGetaran)) {
            try {
                // Getaran
                $data = LhpsGetaranHeader::where('no_lhp', $request->no_lhp)
                    ->where('id_kategori_3', $category)
                    ->where('is_active', true)
                    ->first();
                // dd($data);
                $details = LhpsGetaranDetail::where('id_header', $data->id)->get();
                // dd($data, $details);
                $spesifikasiMethode = LhpsGetaranHeader::where('no_sampel', $request->no_sampel)
                    ->where('is_approve', 1)
                    ->where('is_active', 1)
                    ->get();

                // dd('Getaran', $request->all());
                // dd($data, $details, $spesifikasiMethode);

                return response()->json([
                    'data' => $data,
                    'details' => $details,
                ], 201);

            } catch (\Exception $th) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                    'line' => $th->getLine(),
                    'status' => false
                ], 500);
            }

        } else if (in_array($category, $categoryPencahayaan)) {
            try {
                // Pencahayaan
                $data = LhpsPencahayaanHeader::where('no_lhp', $request->no_lhp)
                    ->where('id_kategori_3', $category)
                    ->where('is_active', true)
                    ->first();
                $details = LhpsPencahayaanDetail::where('id_header', $data->id)->get();
                $spesifikasiMethode = PencahayaanHeader::with('ws_udara', 'data_lapangan')
                    ->where('no_sampel', $request->no_sampel)
                    ->where('is_approved', 1)
                    ->where('is_active', true)
                    ->where('lhps', 1)->get();

                return response()->json([
                    'data' => $data,
                    'details' => $details,
                ], 201);
            } catch (\Exception $th) {
                DB::rollBack();
                dd($th);
                return response()->json([
                    'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                    'line' => $th->getLine(),
                    'status' => false
                ], 500);
            }

        } else if (in_array($category, $categoryLingkunganKerja)) {
            try {
                if ($parameterArray[1] == 'Medan Magnit Statis' || $parameterArray[1] == 'Medan Listrik' || $parameterArray[1] == 'Power Density') {
                    $data = LhpsMedanLMHeader::where('no_sampel', $request->no_sampel)
                        ->where('is_approve', 0)
                        ->where('is_active', true)->first();
                    $details = LhpsMedanLMDetail::where('id_header', $data->id)->get();

                    $spesifikasiMethode = MedanLMHeader::with('ws_udara')
                        ->where('no_sampel', $request->no_sampel)
                        ->where('is_approve', 1)
                        ->where('is_active', true)
                        ->where('lhps', 1)->get();
                    // dd($request->all());
                    $i = 0;
                    $method_regulasi = [];
                    if ($spesifikasiMethode->isNotEmpty()) {
                        foreach ($spesifikasiMethode as $key => $val) {
                            $bakumutu = MasterBakumutu::where('id_parameter', $val->id_parameter)
                                ->where('parameter', $val->parameter)
                                ->first();

                            if ($bakumutu != null && $bakumutu->method != '') {
                                $data1[$i]['satuan'] = $bakumutu->satuan;
                                $data1[$i]['methode'] = $bakumutu->method;
                                $data1[$i]['baku_mutu'][0] = $bakumutu->baku_mutu;
                                array_push($method_regulasi, $bakumutu->method);
                            }
                        }
                    }
                    $method_regulasi = array_values(array_unique($method_regulasi));
                    // dd($method_regulasi);
                    // $method = Parameter::where('is_active', true)->where('id_kategori', 4)->whereNotNull('method')->select('method')->groupBy('method')->get()->toArray();
                    // $result_method = array_unique(array_values(array_merge($method_regulasi, array_column($method, 'method'))));
                    return response()->json([
                        'data' => $data,
                        'details' => $details,
                        'spesifikasi_method' => $method_regulasi
                    ], 201);
                } else {
                    $data = LhpsLingHeader::where('no_sampel', $request->no_sampel)
                        ->where('id_kategori_3', $category)
                        ->where('is_active', true)
                        ->first();
                    $details = LhpsLingDetail::where('id_header', $data->id)->get();
                    $spesifikasiMethode = LhpsLingHeader::with('ws_udara', 'data_lapangan', 'master_parameter')
                        ->where('no_sampel', $request->no_sampel)
                        ->where('is_approved', 1)
                        ->where('is_active', true)
                        ->where('lhps', 1)->get();
                    $i = 0;
                    $method_regulasi = [];
                    if ($spesifikasiMethode->isNotEmpty()) {
                        foreach ($spesifikasiMethode as $key => $val) {
                            $bakumutu = MasterBakumutu::where('id_regulasi', $request->regulasi)
                                ->where('parameter', $val->parameter)
                                ->first();

                            if ($bakumutu != null && $bakumutu->method != '') {
                                $data1[$i]['satuan'] = $bakumutu->satuan;
                                $data1[$i]['methode'] = $bakumutu->method;
                                $data1[$i]['baku_mutu'][0] = $bakumutu->baku_mutu;
                                array_push($method_regulasi, $bakumutu->method);
                            }
                        }
                    }
                    $method_regulasi = array_values(array_unique($method_regulasi));
                    $method = Parameter::where('is_active', true)->where('id_kategori', 1)->whereNotNull('method')->select('method')->groupBy('method')->get()->toArray();
                    $result_method = array_unique(array_values(array_merge($method_regulasi, array_column($method, 'method'))));
                    return response()->json([
                        'data' => $data,
                        'details' => $details,
                        'spesifikasi_method' => $result_method,
                    ], 200);
                }



            } catch (\Exception $th) {
                return response()->json([
                    'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                    'line' => $th->getLine(),
                    'status' => false
                ], 500);
            }
        }
    }
    public function handleApprove(Request $request)
    {
        
        DB::beginTransaction();
        $categoryLingkunganKerja = [11, 27, 53];
        $data_order = OrderDetail::where('no_sampel', $request->no_sampel)
        ->where('is_active', true)
        ->first();
        $sub_category = explode('-', $data_order->kategori_3)[1];
        $category = explode('-', $data_order->kategori_3)[0];
        
        if (in_array($category, $categoryLingkunganKerja)) {
            try {
                // Lingkungan Kerja
                $data = DraftErgonomiFile::where('no_sampel',$data_order->no_sampel)
                    ->first();
                if ($data != null) {
                    $data_order->is_approve = 1;
                    $data_order->status = 3;
                    $data_order->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data_order->approved_by = $this->karyawan;
                    $data_order->save();

                    $data->is_approve  = 1;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->approved_by = $this->karyawan;
                    $data->save();
                    
                    HistoryAppReject::insert([
                        'no_lhp' => $data_order->cfr,
                        'no_sampel' => $data_order->no_sampel,
                        'kategori_2' => $data_order->kategori_2,
                        'kategori_3' => $data_order->kategori_3,
                        'menu' => 'Draft Udara ULK Ergonomi',
                        'status' => 'approve',
                        'approved_at' => Carbon::now(),
                        'approved_by' => $this->karyawan
                    ]);

                    $cekDetail = $data_order;

                    $cekLink = LinkLhp::where('no_order', $cekDetail->no_order);
                    if ($cekDetail && $cekDetail->periode) $cekLink = $cekLink->where('periode', $cekDetail->periode);
                    $cekLink = $cekLink->first();

                    if ($cekLink) {
                        $job = new CombineLHPJob($cekDetail->cfr, $data->name_file, $cekDetail->no_order, $this->karyawan, $cekDetail->periode);
                        $this->dispatch($job);
                    }
                    
                    $orderHeader = OrderHeader::where('id', $cekDetail->id_order_header)
                    ->first();

                    EmailLhpRilisHelpers::run([
                        'cfr'              => $data->no_lhp,
                        'no_order'         => $data->no_order,
                        'nama_pic_order'   => $orderHeader->nama_pic_order ?? '-',
                        'nama_perusahaan'  => $data->nama_pelanggan,
                        'periode'          => $cekDetail->periode,
                        'karyawan'         => $this->karyawan
                    ]);
                    
                }
                DB::commit();
                return response()->json([
                    'data' => $data,
                    'status' => true,
                    'message' => 'Data draft Ergonomi  no sampel ' . $data->no_sampel . ' berhasil diapprove'
                ], 200);
            } catch (\Exception $th) {
                DB::rollback();
                return response()->json([
                    'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                    'line' => $th->getLine(),
                    'status' => false
                ], 500);
            }
        }
    } 

    // Amang
    public function handleReject(Request $request)
    {
        
        DB::beginTransaction();
        $categoryLingkunganKerja = [11, 27, 53];
        $category = explode('-', $request->kategori_3)[0];
        $sub_category = explode('-', $request->kategori_3)[1];
        $data_order = OrderDetail::where('no_sampel', $request->no_sampel)
            ->where('is_active', true)
            ->first();
        
        if (in_array($category, $categoryLingkunganKerja)) {
            try {
                // Lingkungan Kerja
                $data = DraftErgonomiFile::where('no_sampel',$data_order->no_sampel)
                    ->first();

                if ($data != null) {
                    $data_order->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data_order->rejected_by = $this->karyawan;
                    $data_order->status = 0;
                    $data_order->save();

                    
                    $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->rejected_by = $this->karyawan;
                    // $data->save();

                    $data_history = $data->replicate();
                    $data_history->setTable((new DraftErgonomiFileHistory())->getTable());

                    $data->delete();
                    
                    HistoryAppReject::insert([
                        'no_lhp' => $data_order->cfr,
                        'no_sampel' => $data_order->no_sampel,
                        'kategori_2' => $data_order->kategori_2,
                        'kategori_3' => $data_order->kategori_3,
                        'menu' => 'Draft Udara ULK Ergonomi',
                        'status' => 'reject',
                        'rejected_at' => Carbon::now(),
                        'rejected_by' => $this->karyawan
                    ]);
                }
                DB::commit();
                return response()->json([
                    'data' => $data,
                    'status' => true,
                    'message' => 'Data draft LHP air no sampel ' . $data->no_sampel . ' berhasil di reject'
                ], 200);
            } catch (\Exception $th) {
                DB::rollback();
                return response()->json([
                    'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                    'line' => $th->getLine(),
                    'status' => false
                ], 500);
            }
        }
    }

    // Amang
    public function generate(Request $request)
    {
        $categoryKebisingan = [23, 24, 25];
        $categoryGetaran = [13, 14, 15, 16, 17, 18, 19, 20];
        $categoryLingkunganKerja = [11, 27, 53];
        $categoryPencahayaan = [28];
        $category = explode('-', $request->kategori_3)[0];
        $sub_category = explode('-', $request->kategori_3)[1];
        // dd('masuk');
        DB::beginTransaction();
        try {
            if (in_array($category, $categoryKebisingan)) {
                // dd('Kebisingan');
                $header = LhpsKebisinganHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
                $quotation_status = "draft_lhp_kebisingan";
            } else if (in_array($category, $categoryGetaran)) {
                // dd('Getaran');
                $header = LhpsGetaranHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
                $quotation_status = "draft_lhp_getaran";
            } else if (in_array($category, $categoryPencahayaan)) {
                // dd('Pencahayaan');
                $header = LhpsPencahayaanHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
                $quotation_status = "draft_lhp_pencahayaan";
            } else if (in_array($category, $categoryLingkunganKerja)) {
                // dd('Lingkungan Kerja');
                if (is_string($request->parameter)) {
                    $decodedString = html_entity_decode($request->parameter);
                    $parameter = json_decode($decodedString, true);
                    $cleanedParameter = array_map(function ($item) {
                        return preg_replace('/^\d+;/', '', $item);
                    }, $parameter);
                }
                if (in_array("Medan Listrik", $cleanedParameter) || in_array("Medan Magnit Statis", $cleanedParameter) || in_array("Power Density", $cleanedParameter)) {
                    $header = LhpsMedanLMHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
                    $quotation_status = "draft_lhp_medanlm";
                } else if (in_array("Sinar UV", $cleanedParameter)) {
                    $header = LhpsSinarUVHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
                    $quotation_status = "draft_lhp_sinaruv";
                } else {
                    $header = LhpsLingHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
                    $quotation_status = "draft_lhp_lingkungan";
                }
            }
            if ($header != null) {
                $key = $header->no_sampel . str_replace('.', '', microtime(true));
                $gen = MD5($key);
                $gen_tahun = self::encrypt(DATE('Y-m-d'));
                $token = self::encrypt($gen . '|' . $gen_tahun);

                $insertData = [
                    'token' => $token,
                    'key' => $gen,
                    'id_quotation' => $header->id,
                    'quotation_status' => $quotation_status,
                    'type' => 'draft',
                    'expired' => Carbon::now()->addYear()->format('Y-m-d'),
                    'fileName_pdf' => $header->file_lhp,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s')
                ];

                $insert = GenerateLink::insertGetId($insertData);

                $header->id_token = $insert;
                $header->is_generated = true;
                $header->generated_by = $this->karyawan;
                $header->generated_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->expired = Carbon::now()->addYear()->format('Y-m-d');
                // dd('masuk');
                $header->save();
            }
            DB::commit();
            return response()->json([
                'message' => 'Generate link success!',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'status' => false
            ], 500);
        }
    }

    public function generatePdf(Request $request)
    {

        DB::beginTransaction();
        try {
            Carbon::setLocale('id');
            $noSampel = $request->no_sampel;
            /* prepare data pengesahan */
            $tanggalLhp = date('Y-m-d H:i:s');
            $pengesahan = PengesahanLhp::where('berlaku_mulai', '<=', $tanggalLhp)
                ->orderByDesc('berlaku_mulai')
                ->first();

            /* save file */
            $saveFilePDF = new DraftErgonomiFile;
            $pdfFile = $saveFilePDF::where('no_sampel',$noSampel)->first();
            
            if ($pdfFile === null) {
                $pdfFile = new $saveFilePDF(); // bikin object baru
            }
            $nama_perilis = $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah';
            $jabatan_perilis = $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor';
            $pdfFile->no_sampel = $noSampel;
            $pdfFile->created_at = Carbon::now('Asia/Jakarta');
            $pdfFile->nama_karyawan = $nama_perilis;
            $pdfFile->jabatan_karyawan = $jabatan_perilis;
            $pdfFile->created_by =$this->karyawan;
            $pdfFile->tanggal_lhp =Carbon::now('Asia/Jakarta')->locale('id')->isoFormat('YYYY-MM-DD');
            $pdfFile->save();
            
            /* prepare Qr Document */
            $file_qr = new GenerateQrDocumentLhp();
            $dataLHP = DataLapanganErgonomi::with(['detail'])
                    ->where('no_sampel', $noSampel)->first();
            if($pdfFile->file_qr == null || $pdfFile->file_qr == ''){
                $dataQr =(object)[
                    'id' => $saveFilePDF->id,
                    'no_lhp' => $dataLHP->detail->cfr,
                    'nama_pelanggan' => $dataLHP->detail->nama_perusahaan,
                    'no_order' => $dataLHP->detail->no_order,
                    'tanggal_lhp' => $tanggalLhp,
                    'nama_karyawan' => $pengesahan->nama_karyawan,
                    'jabatan_karyawan' => $pengesahan->jabatan_karyawan
                ];
                $file_qr = new GenerateQrDocumentLhp();
                $pathQr = $file_qr->insert('LHP_ERGONOMI', $dataQr, $this->karyawan);
                $pdfFile->file_qr = $pathQr;
                $pdfFile->no_lhp = $dataLHP->detail->cfr;
                $pdfFile->save();
            }
            
            // Definisikan metode yang ingin digabungkan dan ID methodnya
            $methodsToCombine = [
                'nbm' => 1,  //✅
                'reba' => 2, //✅
                'rula' => 3, //✅
                'rosa' => 4, //✅
                'rwl' => 5,  
                'brief' => 6,
                'sni_gotrak' => 7,
                'sni_bahaya_ergonomi' =>8,
                'antropometri' =>9,
                'desain_stasiun_kerja' =>10
            ];

            $mpdfConfig = array(
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_header' => 5,      // ✅ Berikan ruang untuk header
                'margin_bottom' => 15,     // ✅ Cukup ruang untuk footer (15mm + buffer)
                'margin_footer' => 5,      // ✅ Berikan ruang untuk footer
                'margin_top' => 30,
                'margin_left' => 10,
                'margin_right' => 10,
                'orientation' => 'L',
            );

            // Siapkan folder untuk menyimpan file
            /* $dir = public_path("draft_ergonomi");
            $folders = ['draft', 'lhp', 'lhp_digital']; */
            $dir = public_path("dokumen");
            $folders = ['LHP', 'LHP_DOWNLOAD', 'LHPS'];
            
            foreach ($folders as $folder) {
                if (!file_exists("$dir/$folder")) {
                    mkdir("$dir/$folder", 0755, true);
                }
            }

            // Kumpulkan semua content HTML terlebih dahulu
            $allHtmlContent = [];
            $dataMethod = null;

            // Fungsi helper untuk membuat PDF dengan konfigurasi tertentu
            $createPDF = function($type) use ($mpdfConfig, $methodsToCombine, $noSampel, $dir,$pdfFile,$allHtmlContent,$dataLHP) {
                $pdf = new PDF($mpdfConfig);
                $render = new TemplateLhpErgonomi();

                // CSS definitions (keeping your existing CSS)
                $globalCssContent = '
                    * {
                        box-sizing: border-box;
                    }
                    body {
                        font-family: Arial, sans-serif;
                        margin: 15px;
                        font-size: 9px;
                    }
                    .page-container {
                        width: 100%;
                        text-align: center;
                    }
                    .main-header-title {
                        text-align: center;
                        font-weight: bold;
                        font-size: 1.5em;
                        margin-bottom: 15px;
                        text-decoration: underline;
                    }
                    .two-column-layout {
                        width: 900px;
                        margin: 0 auto;
                        overflow: hidden;
                        text-align: left;
                    }
                    .column {
                        float: left;
                    }
                    .column-left {
                        width: 500px;
                    }
                    .column-right {
                        width: 390px; 
                        margin-left:6px; 
                    }
                    .section {
                        border: 1px solid #000;
                        padding: 6px;
                        margin-bottom: 10px;
                    }
                    .section-title {
                        font-weight: bold;
                        padding: 3px 6px;
                        margin: -6px -6px 6px -6px;
                        border-bottom: 1px solid #000;
                    }
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-bottom: 5px;
                        table-layout: fixed;
                    }
                    th, td {
                        border: 1px solid #000;
                        padding: 3px;
                        text-align: left;
                        vertical-align: top;
                    }
                    th {
                        font-weight: bold;
                        text-align: center;
                    }
                    .text-input-space {
                        width: 100%;
                        padding: 2px;
                        min-height: 1.5em;
                    }
                    .multi-line-input {
                        width: 100%;
                        border: 1px solid #000;
                        padding: 4px;
                        min-height: 40px;
                    }
                    .footer-text {
                        font-size: 0.85em;
                        margin-top: 15px;
                        padding-top: 8px;
                        display: flex;
                        justify-content: space-between;
                    }
                    .signature-block {
                        margin-top: 15px;
                        text-align: right;
                    }
                    .signature-block .signature-name {
                        margin-top: 30px;
                        font-weight: bold;
                        text-decoration: underline;
                    }
                    .interpretasi-table td { text-align: center; }
                    .interpretasi-table td:last-child { text-align: left; }
                    .uraian-tugas-table td { height: 1.8em; }
                ';
                $templateSpecificCss = '
                    /* Specific untuk Body Parts Analysis */
                    .image-placeholder-container {
                        width: 100%;
                        margin-top: 8px;
                        display: table;
                        table-layout: fixed;
                        min-height: 280px;
                    }
                    
                    .image-placeholder {
                        float: left;
                        width: 150px;
                        margin-right: 15px;
                    }
                    
                    .body-map {
                        overflow: hidden;
                    }
                    
                    .body-parts-list-container {
                        display: table-cell;
                        vertical-align: top;
                    }
                    
                    .body-parts-list {
                        width: 100%;
                        border-collapse: collapse;
                        font-size: 7.5px;
                        margin-bottom: 8px;
                    }
                    
                    .body-parts-list td {
                        padding: 2px 4px;
                        border: 1px solid #000;
                        vertical-align: middle;
                    }
                    
                    .body-parts-list td:first-child {
                        width: 70%;
                        text-align: left;
                    }
                    
                    .body-parts-list td:last-child {
                        width: 30%;
                        text-align: center;
                    }
                    
                    .input-line {
                        text-align: center;
                        font-weight: bold;
                        min-height: 12px;
                    }
                    
                    .analysis-content,
                    .conclusion-content {
                        min-height: 60px;
                        padding: 4px;
                        border: 1px solid #000;
                        font-size: 7.5px;
                        line-height: 1.3;
                    }
                    
                    /* Info table khusus untuk column kanan */
                    .info-table th,
                    .info-table td {
                        border: 0 !important;
                        text-align: left;
                        padding: 1px 0;
                        font-size: 7.5px;
                    }
                    
                    .info-table .label-column {
                        width: 40%;
                        padding-right: 3px;
                    }
                    
                    .lhp-info-table th,
                    .lhp-info-table td {
                        border: 1px solid #000 !important;
                        text-align: center;
                        font-size: 7px;
                        padding: 2px;
                    }
                    .signature-section {
                        width: 100%;
                        margin-top: 8px;
                        clear: both;
                    }

                    .signature-table {
                        width: 100%;
                        border: none !important;
                        font-family: Arial, sans-serif;
                        font-size: 8px;
                        table-layout: fixed;
                    }

                    .signature-table td {
                        border: none !important;
                        padding: 2px;
                        vertical-align: top;
                    }

                    .signature-left {
                        width: 65%;
                    }

                    .signature-right {
                        width: 35%;
                        text-align: center;
                    }

                    .signature-date {
                        margin-bottom: 8px;
                        font-size: 8px;
                    }

                    .signature-qr {
                        width: 60px;
                        height: 60px;
                        margin: 5px auto;
                        display: block;
                    }

                    .signature-text {
                        margin-top: 3px;
                        font-size: 7px;
                    }
                    .header-table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-bottom: 10px;
                        table-layout: fixed;
                    }

                    .header-table td {
                        border: none;
                        padding: 10px;
                        vertical-align: middle;
                        height: 60px;
                    }

                    .header-table .left-cell {
                        width: 33.33%;
                        text-align: left;
                        padding-left: 20px;
                    }

                    .header-table .center-cell {
                        width: 33.33%;
                        text-align: center;
                    }

                    .header-table .right-cell {
                        width: 33.33%;
                        text-align: right;
                        padding-right: 50px;
                    }
                    .header-logo {
                        height: 50px;
                        width: auto;
                        display: block;
                    }
                    .info-table {
                        border: 0;
                        margin-bottom: 6px;
                    }

                    .info-table td {
                        border: 0;
                        padding: 0px 2px;
                        font-size: 8px;
                        vertical-align: top;
                    }
                ';
                $templateSpecificCssNbm='
                    /* CSS dengan font size yang konsisten - Layout Fixed */
                    body {
                        font-family: Arial, sans-serif;
                        margin: 0;
                        padding: 0;
                        font-size: 10px; /* Base font size yang konsisten */
                        width: 100%;
                        min-width: 800px; /* Minimum width untuk mempertahankan layout */
                    }

                    .header {
                        text-align: center;
                        margin-bottom: 5px;
                    }

                    .header h1 {
                        font-size: 12px; /* Dikurangi untuk konsistensi */
                        font-weight: bold;
                        margin: 5px 0;
                        text-decoration: underline;
                    }

                    .company-name {
                        font-weight: bold;
                        font-size: 10px; /* Konsisten dengan base */
                        text-align: left;
                        margin-bottom: 10px;
                    }

                    .section-title {
                        font-weight: bold;
                        margin: 10px 0 5px 0;
                        font-size: 10px; /* Konsisten dengan base */
                    }

                    table {
                        width: 100%;
                        border-collapse: collapse;
                        font-size: 9px; /* Sedikit lebih kecil untuk tabel */
                        margin-bottom: 10px;
                        table-layout: fixed; /* Fixed table layout */
                    }

                    th,
                    td {
                        border: 1px solid black;
                        padding: 3px 5px;
                        text-align: center;
                        vertical-align: middle;
                        font-size: 9px; /* Konsisten untuk semua sel tabel */
                    }

                    thead {
                        display: table-header-group;
                    }

                    tbody {
                        display: table-row-group;
                    }

                    .body-map {
                        width: 80px;
                        height: auto;
                        margin: 5px auto;
                        display: block;
                    }

                    .info-section {
                        margin-bottom: 10px;
                    }

                    .info-section p {
                        margin: 3px 0;
                        font-size: 9px; /* Konsisten */
                    }

                    .info-label {
                        font-weight: normal;
                        width: 120px;
                        float: left;
                        font-size: 9px; /* Konsisten, tidak lagi 10pt */
                    }

                    .info-value {
                        display: inline-block;
                        font-size: 9px; /* Konsisten */
                    }

                    .customer-info,
                    .sampling-info,
                    .worker-info {
                        margin-left: 0;
                        margin-bottom: 10px;
                    }

                    .customer-info h4,
                    .sampling-info h4,
                    .worker-info h4 {
                        margin: 5px 0 2px 0;
                        font-size: 10px; /* Konsisten */
                        font-weight: bold;
                    }

                    .risk-table {
                        margin-top: 5px;
                    }

                    .left-section p {
                        font-weight: bold;
                        text-align: justify;
                        margin-bottom: 5px;
                        font-size: 9px; /* Konsisten */
                    }

                    .table-note {
                        font-size: 8px; /* Tetap kecil untuk catatan */
                        margin-top: 3px;
                        font-style: italic;
                    }

                    .job-description {
                        margin-top: 10px;
                    }

                    .job-description th {
                        width: 30%;
                        text-align: left;
                        vertical-align: top;
                        font-size: 9px; /* Konsisten */
                    }

                    .job-description td {
                        vertical-align: top;
                        font-size: 9px; /* Konsisten */
                    }

                    .conclusion-box {
                        border: 1px solid black;
                        padding: 5px;
                        min-height: 30px;
                        margin-top: 5px;
                        margin-bottom: 10px;
                        font-size: 9px; /* Konsisten */
                    }

                    .conclusion-box .section-title {
                        margin-top: 0;
                        margin-bottom: 5px;
                        font-size: 10px; /* Konsisten */
                    }

                    /* Fixed Layout - Tidak Responsif */
                    .left-section {
                        width: 60%;
                        float: left;
                        box-sizing: border-box;
                        min-width: 60%;
                        max-width: 60%;
                    }

                    .right-section {
                        width: 39%;
                        float: right;
                        box-sizing: border-box;
                        min-width: 39%;
                        max-width: 39%;
                    }

                    /* Pastikan layout tetap fixed untuk print */
                    @media print {
                        .left-section {
                            width: 60% !important;
                            min-width: 60% !important;
                            max-width: 60% !important;
                        }
                        
                        .right-section {
                            width: 39% !important;
                            min-width: 39% !important;
                            max-width: 39% !important;
                        }
                    }

                    .result-header {
                        text-align: center;
                        font-weight: bold;
                        margin: 5px 0;
                        font-size: 9px; /* Konsisten dengan tabel */
                    }

                    /* Styling untuk tabel nested SEBELUM/SESUDAH */
                    .nested-table-container {
                        padding: 0;
                    }

                    .nested-table {
                        width: 100%;
                        margin: 0;
                        border: none;
                    }

                    .nested-table td {
                        border: 1px solid black;
                        width: 50%;
                        text-align: center;
                        font-weight: bold;
                        padding: 3px;
                        font-size: 9px; /* Konsisten */
                    }

                    .total-score {
                        font-weight: bold;
                        text-align: center;
                        margin-top: 5px;
                        font-size: 9px; /* Konsisten */
                    }

                    .content-container {
                        width: 100%;
                        min-width: 800px; /* Pastikan layout minimum */
                    }

                    .clearfix::after {
                        content: "";
                        clear: both;
                        display: table;
                    }
                    
                    .info-header {
                        font-weight: bold;
                        margin-top: 8px;
                        margin-bottom: 3px;
                        font-size: 10px; /* Konsisten, tidak lagi 10pt */
                        clear: both;
                    }

                    /* Styling khusus untuk informasi di sisi kanan */
                    .right-section div {
                        font-size: 9px; /* Base untuk right section */
                    }

                    .right-section span {
                        font-size: 9px; /* Konsisten untuk semua span */
                    }

                    /* Styling untuk div dengan margin-bottom di right section */
                    .right-section div[style*="margin-bottom: 3px"] {
                        margin-bottom: 3px;
                        font-size: 9px; /* Konsisten, tidak lagi 10pt */
                }';
                $rebaSpecificCss = '
                    * { 
                        box-sizing: border-box; 
                        margin: 0;
                        padding: 0;
                    }

                    body {
                        font-family: Arial, sans-serif;
                        font-size: 8px;
                        margin: 0;
                        padding: 0;
                    }

                    .container {
                        width: 100%;
                        clear: both;
                    }

                    /* Header */
                    .main-header {
                        text-align: center;
                        font-weight: bold;
                        font-size: 12px;
                        text-decoration: underline;
                        margin-bottom: 6px;
                        padding: 4px 0;
                        clear: both;
                        width: 100%;
                        display: block;
                    }

                    /* Main content wrapper */
                    .main-content {
                        clear: both;
                        overflow: hidden;
                        margin-bottom: 6px;
                        width: 100%;
                    }

                    /* Column layouts */
                    .column-left {
                        float: left;
                        width: 30%;
                        padding-right: 2px;
                    }

                    .column-center {
                        float: left;
                        width: 30%;
                        padding: 0 1px;
                    }

                    .column-right {
                        float: right;
                        width: 40%;
                        padding-left: 2px;
                    }

                    /* Bottom section */
                    .bottom-section {
                        clear: both;
                        overflow: hidden;
                        margin-top: 6px;
                        width: 100%;
                    }

                    .bottom-left {
                        float: left;
                        width: 60%;
                        padding-right: 2px;
                    }

                    .bottom-right {
                        float: right;
                        width: 40%;
                        padding-left: 2px;
                    }

                    /* Table styles */
                    table {
                        border-collapse: collapse;
                        width: 100%;
                        margin-bottom: 4px;
                        font-size: 8px;
                        border-spacing: 0;
                    }

                    th, td {
                        border: 1px solid #000;
                        padding: 1px 2px;
                        vertical-align: top;
                        font-size: 8px;
                        line-height: 1.0;
                        margin: 0;
                    }

                    th {
                        background-color: #f2f2f2;
                        font-weight: bold;
                        text-align: center;
                        padding: 2px;
                    }

                    .section-header {
                        font-weight: bold;
                        font-size: 8px;
                        text-decoration: underline;
                        text-align: left;
                        margin: 2px 0 1px 0;
                        display: block;
                        clear: both;
                    }

                    .info-table {
                        border: 0;
                        margin-bottom: 3px;
                    }

                    .info-table td {
                        border: 0;
                        padding: 0px 1px;
                        font-size: 8px;
                        vertical-align: top;
                        line-height: 1.0;
                    }

                    .final-score {
                        background-color: #e0e0e0;
                        font-weight: bold;
                    }

                    /* Image optimization */
                    td img {
                        max-width: 100%;
                        max-height: 25px;
                        object-fit: contain;
                        display: block;
                        margin: 0 auto;
                        vertical-align: middle;
                    }

                    /* Specific table adjustments */
                    .score-table th:first-child { width: 8%; }
                    .score-table th:nth-child(2) { width: 67%; }
                    .score-table th:last-child { width: 25%; }

                    .header-info-table th { 
                        width: 33.33%; 
                        padding: 2px 1px;
                        font-size: 8px;
                    }

                    .header-info-table td {
                        padding: 2px 1px;
                        text-align: center;
                        font-size: 8px;
                    }

                    .reference-table th:nth-child(1) { width: 15%; }
                    .reference-table th:nth-child(2) { width: 12%; }
                    .reference-table th:nth-child(3) { width: 23%; }
                    .reference-table th:nth-child(4) { width: 50%; }

                    .reference-table td {
                        font-size: 7px;
                        padding: 1px;
                        line-height: 1.0;
                    }

                    /* Compact spacing */
                    .image-row td:first-child {
                        text-align: center;
                        vertical-align: middle;
                        font-size: 8px;
                    }

                    .image-row td:nth-child(2) {
                        height: 25px;
                        padding: 1px;
                    }

                    .image-row td:last-child {
                        text-align: center;
                        vertical-align: middle;
                        font-size: 8px;
                    }

                    .label-row td {
                        text-align: center;
                        font-size: 7px;
                        padding: 1px;
                        height: 12px;
                    }

                    /* Footer notes */
                    .footer-notes {
                        border: 0;
                        margin-top: 15px;
                    }

                    .footer-notes td:first-child {
                        width: 2%;
                        text-align: right;
                        vertical-align: top;
                        font-size: 7px;
                        border: 0;
                        padding-right: 3px;
                    }

                    .footer-notes td:last-child {
                        width: 98%;
                        text-align: left;
                        font-size: 7px;
                        border: 0;
                        line-height: 1.0;
                    }

                    /* Conclusion table specific */
                    .conclusion-table td:first-child {
                        width: 35%;
                        text-align: center;
                        font-weight: bold;
                        vertical-align: middle;
                        height: 35px;
                        font-size: 8px;
                    }

                    .conclusion-table td:last-child {
                        width: 65%;
                        text-align: justify;
                        vertical-align: top;
                        font-size: 8px;
                        line-height: 1.1;
                        padding: 3px;
                    }

                    /* Compact margin adjustments */
                    .compact-table {
                        margin-bottom: 2px;
                    }

                    /* Text alignment helpers */
                    .text-left { text-align: left !important; padding-left: 3px; }
                    .text-center { text-align: center !important; }
                    .text-justify { text-align: justify !important; }

                    /* Clear floats helper */
                    .clearfix::after {
                        content: "";
                        display: table;
                        clear: both;
                    }
                ';
                // Setup header yang sama untuk semua versi
                /* width: 100%; border-collapse: collapse; margin-bottom: 10px; table-layout: fixed; */
                switch($type) {
                    case 'draft':
                        $header ='<table width="100%" border="0" style="border:none; border-collapse:collapse;">
                                <tr>
                                    <td class="left-cell" style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: left; padding-left: 20px;">
                                    </td>
                                    <td style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: center;"><span>LAPORAN HASIL PENGUJIAN</span></td>
                                    <td style="border: none; padding: 10px; vertical-align: middle; height: 60px width: 33.33%; text-align: right; padding-right: 50px;">
                                    </td>
                                <tr>
                                </table>';
                            break;
                    case 'lhp':
                        // $header ='<table width="100%" border="0" style="border:none; border-collapse:collapse;">
                        //         <tr>
                        //             <td class="left-cell" style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: left; padding-left: 20px;">
                        //             </td>
                        //             <td style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: center;"><span>LAPORAN HASIL PENGUJIAN</span></td>
                        //             <td style="border: none; padding: 10px; vertical-align: middle; height: 60px width: 33.33%; text-align: right; padding-right: 50px;">
                        //             </td>
                        //         <tr>
                        //         </table>';
                        $noSampelAkre = OrderDetail::where('no_sampel',$noSampel)->first();
                        $decodeParameterNya =json_decode($noSampelAkre->parameter,true);
                        $idParameterAkre =explode(';',$decodeParameterNya[0])[0];
                        $akreditasiKan = Parameter::where('id', $idParameterAkre)->where('status', "AKREDITASI")->where('is_active', true)->first();

                        if($akreditasiKan === null){
                            $header ='<table width="100%" border="0" style="border:none; border-collapse:collapse; text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif; ">
                                    <tr>
                                        <td class="left-cell" style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: left; padding-left: 20px;">
                                            <img src="'.public_path('img/isl_logo.png').'" alt="ISL"  style ="height: 50px; width: auto; display: block;">
                                        </td>
                                        <td  style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: center;">
                                            <span style="font-size:16px;font-weight: bold; border-bottom: 1px solid #000">LAPORAN HASIL PENGUJIAN</span>
                                        </td>
                                        <td style="border: none; padding: 10px; vertical-align: middle; height: 60px width: 33.33%; text-align: right; padding-right: 50px;">
                                        </td>
                                    </tr>
                                     </table>';
                        }else{
                            $header = '<table width="100%" border="0" style="border:none; border-collapse:collapse; text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif; ">
                                    <tr>
                                        <td class="left-cell" style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: left; padding-left: 20px;">
                                            <img src="'.public_path('img/isl_logo.png').'" alt="ISL"  style ="height: 50px; width: auto; display: block;">
                                        </td>
                                        <td  style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: center;">
                                            <span style="font-weight: bold; border-bottom: 1px solid #000">LAPORAN HASIL PENGUJIAN</span>
                                        </td>
                                        <td style="border: none; padding: 10px; vertical-align: middle; height: 60px width: 33.33%; text-align: right; padding-right: 50px;">
                                            <img src="'.public_path('img/logo_kan.png').'" alt="KAN" style ="height: 50px; width: auto; display: block;">
                                        </td>
                                    </tr>
                                     </table>';
                        }
                            break;
                    case 'lhp_digital':
                        /* chek akreditasi */
                        $noSampelAkre = OrderDetail::where('no_sampel',$noSampel)->first();
                        $decodeParameterNya =json_decode($noSampelAkre->parameter,true);
                        $idParameterAkre =explode(';',$decodeParameterNya[0])[0];
                        $akreditasiKan = Parameter::where('id', $idParameterAkre)->where('status', "AKREDITASI")->where('is_active', true)->first();

                        if($akreditasiKan === null){
                            $header = '<table width="100%" border="0" style="border:none; border-collapse:collapse;">
                                    <tr>
                                        <td class="left-cell" style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: left; padding-left: 20px;">
                                            <img src="'.public_path('img/isl_logo.png').'" alt="ISL"  style ="height: 50px; width: auto; display: block;">
                                        </td>
                                        <td  style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: center;">
                                            <span class="header-title">LAPORAN HASIL PENGUJIAN</span>
                                        </td>
                                        <td style="border: none; padding: 10px; vertical-align: middle; height: 60px width: 33.33%; text-align: right; padding-right: 50px;">
                                        </td>
                                    </tr>
                                     </table>';
                        }else{
                            $header = '<table width="100%" border="0" style="border:none; border-collapse:collapse;">
                                    <tr>
                                        <td class="left-cell" style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: left; padding-left: 20px;">
                                            <img src="'.public_path('img/isl_logo.png').'" alt="ISL"  style ="height: 50px; width: auto; display: block;">
                                        </td>
                                        <td  style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: center;">
                                            <span class="header-title">LAPORAN HASIL PENGUJIAN</span>
                                        </td>
                                        <td style="border: none; padding: 10px; vertical-align: middle; height: 60px width: 33.33%; text-align: right; padding-right: 50px;">
                                            <img src="'.public_path('img/logo_kan.png').'" alt="KAN" style ="height: 50px; width: auto; display: block;">
                                        </td>
                                    </tr>
                                     </table>';
                        }
                            break;
                }
                $pdf->SetHeader($header);
                // Setup watermark dan footer berdasarkan tipe
                switch($type) {
                    case 'draft':
                        // Draft: watermark draft + footer dengan QR
                        $pageWidth = $pdf->w;
                        $watermarkPath = public_path().'/watermark-draft.png';
                        $pdf->SetWatermarkImage(
                            $watermarkPath,
                            0.1,
                            '',
                            [0, 0],
                            $pageWidth,
                            0
                        );
                        $pdf->showWatermarkImage = true;
                        
                        $footerHtml = '
                            <table width="100%" border="0" style="border:none; border-collapse:collapse; font-family: Arial, sans-serif; margin: 0; padding: 0;">
                                <tr>
                                    <td width="30%" style="vertical-align: top; font-size: 6px; line-height: 1.1; padding: 0; text-align: left; border:none;">
                                        <div>PT Inti Surya Laboratorium</div>
                                        <div>Ruko Icon Business Park Blok O No.5-6 BSD City, Jl. BSD Raya Utama, Cisauk, Sampora Kab. Tangerang 15341</div>
                                        <div>021-5089-8988/89 contact@intilab.com</div>
                                    </td>
                                    <td width="45%" style="font-size: 6px; vertical-align: top; text-align: center; line-height: 1.1; padding: 0; border:none">
                                        Laporan hasil pengujian ini hanya berlaku bagi sampel yang tercantum di atas. Lembar ini tidak boleh diubah ataupun digandakan tanpa izin tertulis dari pihak Laboratorium..<br>
                                    </td>
                                    <td width="25%" style="text-align: right; vertical-align: top; padding: 0; border:none font-family: roboto; font-weight: bold;">
                                        <b>Halaman {PAGENO} dari {nbpg}</b>
                                    </td>
                                </tr>
                            </table>';
                        break;
                        
                    case 'lhp':
                        // LHP: watermark + footer tanpa QR
                        $pdf->SetWatermarkImage(public_path('logo-watermark.png'), -1, '', [110, 35]);
                        $pdf->showWatermarkImage = true;
                        $file_qr = public_path('qr_documents/' . $pdfFile->file_qr . '.svg');
                        $footerHtml = '
                            <table width="100%" border="0" style="border:none; border-collapse:collapse; margin: 0; padding: 0;">
                                <tr>
                                    <!-- Kolom 1: Alamat -->
                                    <!-- Pindahkan font-size ke sini. Gunakan 5pt atau 8pt untuk ukuran kecil yang terbaca -->
                                    <td width="30%" style="font-size: 5pt; font-family: Arial, sans-serif; vertical-align: top; line-height: 1.2; padding: 0; text-align: left; border:none; font-weight: bold;">
                                        <div>PT Inti Surya Laboratorium</div>
                                        <div>Ruko Icon Business Park Blok O No.5-6 BSD City,</div>
                                        <div>Jl. BSD Raya Utama, Cisauk, Sampora Kab. Tangerang 15341</div>
                                        <div>021-5089-8988/89 contact@intilab.com</div>
                                    </td>

                                    <!-- Kolom 2: Disclaimer -->
                                    <td width="45%" style="font-size: 5pt; font-family: Roboto, sans-serif; vertical-align: top; text-align: center; line-height: 1.2; padding: 0; border:none; font-weight: bold;">
                                        Laporan hasil pengujian ini hanya berlaku bagi sampel yang tercantum di atas. Lembar ini tidak boleh diubah ataupun digandakan tanpa izin tertulis dari pihak Laboratorium.<br>
                                        Halaman {PAGENO} dari {nbpg}
                                    </td>

                                    <!-- Kolom 3: Kode Dokumen -->
                                    <!-- PERHATIKAN: Tanda titik koma (;) telah ditambahkan setelah border:none -->
                                    <td width="25%" style="font-size: 5pt; font-family: Roboto, sans-serif; font-weight: bold; text-align: right; vertical-align: top; padding: 0; border:none;">
                                        DP/7.8.1/ISL; Rev 3; 08 November 2022
                                    </td>
                                </tr>
                            </table>';
                        break;
                        
                    case 'lhp_digital':
                        // LHP Digital: watermark logo + footer dengan QR
                        $pdf->SetWatermarkImage(public_path('logo-watermark.png'), -1, '', [110, 35]);
                        $pdf->showWatermarkImage = true;
                        $file_qr = public_path('qr_documents/' . $pdfFile->file_qr . '.svg');
                        $footerHtml = '
                            <table width="100%" border="0" style="font-size: 6px; border:none; border-collapse:collapse; font-family: Arial, sans-serif; margin: 0; padding: 0;">
                                <tr>
                                    <td width="30%" style="vertical-align: top; font-size: 6px; line-height: 1.1; padding: 0; text-align: left; border:none;">
                                        <div>PT Inti Surya Laboratorium</div>
                                        <div>Ruko Icon Business Park Blok O No.5-6 BSD City, Jl. BSD Raya Utama, Cisauk, Sampora Kab. Tangerang 15341</div>
                                        <div>021-5089-8988/89 contact@intilab.com</div>
                                    </td>
                                    <td width="45%" style="vertical-align: top; text-align: center; line-height: 1.1; padding: 0; border:none; min-height:100px; font-family: roboto;">
                                        Laporan hasil pengujian ini hanya berlaku bagi sampel yang tercantum di atas. Lembar ini tidak boleh diubah ataupun digandakan tanpa izin tertulis dari pihak Laboratorium.<br>
                                        <b>Halaman {PAGENO} dari {nbpg}</b>
                                    </td>
                                    <td width="25%" style="text-align: right; vertical-align: top; padding: 0; border:none">
                                        <img src="'.$file_qr.'" width="25" height="25" alt="QR Code" />
                                    </td>
                                </tr>
                            </table>';
                        break;
                }
                $pdf->SetHTMLFooter($footerHtml,'0');
                $pdf->setAutoBottomMargin = 'stretch';
                // Tulis semua konten HTML
                foreach ($methodsToCombine as $methodName => $methodId) {
                    // Ambil data untuk setiap metode dan no_sampel yang diminta
                    $dataMethod = DataLapanganErgonomi::with(['detail'])
                        ->where('no_sampel', $noSampel)
                        ->where('method', $methodId)
                        ->first();
                   
                    if ($dataMethod) {
                        $ttdData = null;
                        switch($type) {
                            case 'draft':
                                $ttdData = null; // Tidak ada TTD untuk draft
                                break;
                            case 'lhp':
                                $ttdData = (object)[
                                    'show_signature' => true,
                                    'show_qr_in_signature' => true,
                                    'nama_karyawan' => $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah',
                                    'jabatan_karyawan' => $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor',
                                    'tanggal' => Carbon::now('Asia/Jakarta')->locale('id')->isoFormat('DD MMMM YYYY'),
                                    'qr_path' => public_path('qr_documents/' . $pdfFile->file_qr . '.svg')
                                ];
                                break;
                            case 'lhp_digital':
                                $ttdData = (object)[
                                    'show_signature' => true,
                                    'show_qr_in_signature' => false, // Tidak perlu QR di TTD karena sudah ada di footer
                                    'nama_karyawan' => $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah',
                                    'jabatan_karyawan' => $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor',
                                    'tanggal' => Carbon::now('Asia/Jakarta')->locale('id')->isoFormat('DD MMMM YYYY'),
                                    'qr_path' => public_path('qr_documents/' . $pdfFile->file_qr . '.svg')
                                ];
                                break;
                        }
                        $htmlContent = '';
                        // Panggil metode yang sesuai di TemplateLhpsErgonomi dan dapatkan HTMLnya
                        switch ($methodName) {
                            case 'rwl':
                                $htmlContent = $render->ergonomiRwl($dataMethod,'','',$ttdData);
                                break;
                            case 'nbm':
                                $htmlContent = $render->ergonomiNbm($dataMethod,'',$templateSpecificCssNbm,$ttdData);
                                break;
                            case 'reba':
                                $htmlContent = $render->ergonomiReba($dataMethod,$globalCssContent,$rebaSpecificCss,$ttdData);
                                break;
                            case 'rula':
                                $htmlContent = $render->ergonomiRula($dataMethod,'','',$ttdData);
                                break;
                            case 'rosa':
                                $htmlContent = $render->ergonomiRosa($dataMethod,'','',$ttdData);
                                break;
                            case 'brief':
                                $htmlContent = $render->ergonomiBrief($dataMethod,'','',$ttdData);
                                break;
                            case 'sni_gotrak':
                                 
                                $htmlContent = $render->ergonomiGontrak($dataMethod,$globalCssContent,$templateSpecificCss,$ttdData);
                                break;
                            case 'sni_bahaya_ergonomi':
                                $htmlContent = $render->ergonomiPotensiBahaya($dataMethod,'','',$ttdData);
                                break;
                        }

                        
                        if ($htmlContent != '') {
                            $allHtmlContent[] = $htmlContent;
                        }

                    }
                }
                
                $firstPage = true;
                foreach ($allHtmlContent as $htmlContent) {
                    if (!$firstPage) {
                        $pdf->AddPage();
                    }
                    $pdf->WriteHTML($htmlContent);
                    $firstPage = false;
                }
                
                // Simpan file
                $namaFile = 'LHP-'.str_replace('/', '-', $dataLHP->detail->cfr).'.pdf';
                // $pathFile = $dir.'/'.$type.'/'.$namaFile;
                $pathFile = $dir.'/'.'LHP_DOWNLOAD'.'/'.$namaFile;
                $pdf->Output($pathFile, 'F');
                return [$pdf,$namaFile];
            };

            // Buat 3 versi PDF
            //$pdfDraft = $createPDF('draft')[0];
            $pdfLhp = $createPDF('lhp')[0]; 
            //$pdfLhpDigital = $createPDF('lhp_digital')[0];
            $pdfFile->name_file = $createPDF('lhp')[1];
            $pdfFile->save();
            DB::commit();
            return response()->json('data berhasil di render',200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "message" => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ], 500);
        }
    }

    private function updatePdf($noSampel, $tanggalLhp)
    {
        try {
            Carbon::setLocale('id');
            /* save file */
            $saveFilePDF = new DraftErgonomiFile;
            $pdfFile = $saveFilePDF::where('no_sampel',$noSampel)->first();

            if($pdfFile != null)
            {
                $dataLHP = DataLapanganErgonomi::with(['detail'])
                ->where('no_sampel', $noSampel)->first();

                $carbonParseDate = Carbon::parse($tanggalLhp);
                $pdfFile->tanggal_lhp =$carbonParseDate->format('d-m-Y');
                //$pdfFile->updated_by = $this->karywan;
                //$pdfFile->updated_at = Carbon::now('Asia/Jakarta');
                $pdfFile->save();

                /* re-generate pdf start
                    *define methods combine
                    *define  configure pdf
                    *define path folder
                    *define variable collection html render
                */
                
                //step 1
                $methodsToCombine = [
                    'nbm' => 1,  //✅
                    'reba' => 2, //✅
                    'rula' => 3, //✅
                    'rosa' => 4, //✅
                    'rwl' => 5,  
                    'brief' => 6,
                    'sni_gotrak' => 7,
                    'sni_bahaya_ergonomi' =>8,
                    'antropometri' =>9,
                    'desain_stasiun_kerja' =>10
                ];

                //step 2
                $mpdfConfig = array(
                    'mode' => 'utf-8',
                    'format' => 'A4',
                    'margin_header' => 5,      // ✅ Berikan ruang untuk header
                    'margin_bottom' => 15,     // ✅ Cukup ruang untuk footer (15mm + buffer)
                    'margin_footer' => 5,      // ✅ Berikan ruang untuk footer
                    'margin_top' => 30,
                    'margin_left' => 10,
                    'margin_right' => 10,
                    'orientation' => 'L',
                );

                //step 3
                $dir = public_path("dokumen");
                $folders = ['LHP', 'LHP_DOWNLOAD', 'LHPS'];
                
                foreach ($folders as $folder) {
                    if (!file_exists("$dir/$folder")) {
                        mkdir("$dir/$folder", 0755, true);
                    }
                }

                //step 4
                $allHtmlContent = [];
                $dataMethod = null;

                // coocking
                $createPDF = function($type) use ($mpdfConfig, $methodsToCombine, $noSampel, $dir,$pdfFile,$allHtmlContent,$dataLHP) {
                    $pdf = new PDF($mpdfConfig);
                    $render = new TemplateLhpErgonomi();

                    // CSS definitions (keeping your existing CSS)
                    $globalCssContent = '
                        * {
                            box-sizing: border-box;
                        }
                        body {
                            font-family: Arial, sans-serif;
                            margin: 15px;
                            font-size: 9px;
                        }
                        .page-container {
                            width: 100%;
                            text-align: center;
                        }
                        .main-header-title {
                            text-align: center;
                            font-weight: bold;
                            font-size: 1.5em;
                            margin-bottom: 15px;
                            text-decoration: underline;
                        }
                        .two-column-layout {
                            width: 900px;
                            margin: 0 auto;
                            overflow: hidden;
                            text-align: left;
                        }
                        .column {
                            float: left;
                        }
                        .column-left {
                            width: 500px;
                        }
                        .column-right {
                            width: 390px; 
                            margin-left:6px; 
                        }
                        .section {
                            border: 1px solid #000;
                            padding: 6px;
                            margin-bottom: 10px;
                        }
                        .section-title {
                            font-weight: bold;
                            padding: 3px 6px;
                            margin: -6px -6px 6px -6px;
                            border-bottom: 1px solid #000;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-bottom: 5px;
                            table-layout: fixed;
                        }
                        th, td {
                            border: 1px solid #000;
                            padding: 3px;
                            text-align: left;
                            vertical-align: top;
                        }
                        th {
                            font-weight: bold;
                            text-align: center;
                        }
                        .text-input-space {
                            width: 100%;
                            padding: 2px;
                            min-height: 1.5em;
                        }
                        .multi-line-input {
                            width: 100%;
                            border: 1px solid #000;
                            padding: 4px;
                            min-height: 40px;
                        }
                        .footer-text {
                            font-size: 0.85em;
                            margin-top: 15px;
                            padding-top: 8px;
                            display: flex;
                            justify-content: space-between;
                        }
                        .signature-block {
                            margin-top: 15px;
                            text-align: right;
                        }
                        .signature-block .signature-name {
                            margin-top: 30px;
                            font-weight: bold;
                            text-decoration: underline;
                        }
                        .interpretasi-table td { text-align: center; }
                        .interpretasi-table td:last-child { text-align: left; }
                        .uraian-tugas-table td { height: 1.8em; }
                    ';
                    $templateSpecificCss = '
                        /* Specific untuk Body Parts Analysis */
                        .image-placeholder-container {
                            width: 100%;
                            margin-top: 8px;
                            display: table;
                            table-layout: fixed;
                            min-height: 280px;
                        }
                        
                        .image-placeholder {
                            float: left;
                            width: 150px;
                            margin-right: 15px;
                        }
                        
                        .body-map {
                            overflow: hidden;
                        }
                        
                        .body-parts-list-container {
                            display: table-cell;
                            vertical-align: top;
                        }
                        
                        .body-parts-list {
                            width: 100%;
                            border-collapse: collapse;
                            font-size: 7.5px;
                            margin-bottom: 8px;
                        }
                        
                        .body-parts-list td {
                            padding: 2px 4px;
                            border: 1px solid #000;
                            vertical-align: middle;
                        }
                        
                        .body-parts-list td:first-child {
                            width: 70%;
                            text-align: left;
                        }
                        
                        .body-parts-list td:last-child {
                            width: 30%;
                            text-align: center;
                        }
                        
                        .input-line {
                            text-align: center;
                            font-weight: bold;
                            min-height: 12px;
                        }
                        
                        .analysis-content,
                        .conclusion-content {
                            min-height: 60px;
                            padding: 4px;
                            border: 1px solid #000;
                            font-size: 7.5px;
                            line-height: 1.3;
                        }
                        
                        /* Info table khusus untuk column kanan */
                        .info-table th,
                        .info-table td {
                            border: 0 !important;
                            text-align: left;
                            padding: 1px 0;
                            font-size: 7.5px;
                        }
                        
                        .info-table .label-column {
                            width: 40%;
                            padding-right: 3px;
                        }
                        
                        .lhp-info-table th,
                        .lhp-info-table td {
                            border: 1px solid #000 !important;
                            text-align: center;
                            font-size: 7px;
                            padding: 2px;
                        }
                        .signature-section {
                            width: 100%;
                            margin-top: 8px;
                            clear: both;
                        }

                        .signature-table {
                            width: 100%;
                            border: none !important;
                            font-family: Arial, sans-serif;
                            font-size: 8px;
                            table-layout: fixed;
                        }

                        .signature-table td {
                            border: none !important;
                            padding: 2px;
                            vertical-align: top;
                        }

                        .signature-left {
                            width: 65%;
                        }

                        .signature-right {
                            width: 35%;
                            text-align: center;
                        }

                        .signature-date {
                            margin-bottom: 8px;
                            font-size: 8px;
                        }

                        .signature-qr {
                            width: 60px;
                            height: 60px;
                            margin: 5px auto;
                            display: block;
                        }

                        .signature-text {
                            margin-top: 3px;
                            font-size: 7px;
                        }
                        .header-table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-bottom: 10px;
                            table-layout: fixed;
                        }

                        .header-table td {
                            border: none;
                            padding: 10px;
                            vertical-align: middle;
                            height: 60px;
                        }

                        .header-table .left-cell {
                            width: 33.33%;
                            text-align: left;
                            padding-left: 20px;
                        }

                        .header-table .center-cell {
                            width: 33.33%;
                            text-align: center;
                        }

                        .header-table .right-cell {
                            width: 33.33%;
                            text-align: right;
                            padding-right: 50px;
                        }
                        .header-logo {
                            height: 50px;
                            width: auto;
                            display: block;
                        }
                        .info-table {
                            border: 0;
                            margin-bottom: 6px;
                        }

                        .info-table td {
                            border: 0;
                            padding: 0px 2px;
                            font-size: 8px;
                            vertical-align: top;
                        }
                    ';
                    $templateSpecificCssNbm='
                        /* CSS dengan font size yang konsisten - Layout Fixed */
                        body {
                            font-family: Arial, sans-serif;
                            margin: 0;
                            padding: 0;
                            font-size: 10px; /* Base font size yang konsisten */
                            width: 100%;
                            min-width: 800px; /* Minimum width untuk mempertahankan layout */
                        }

                        .header {
                            text-align: center;
                            margin-bottom: 5px;
                        }

                        .header h1 {
                            font-size: 12px; /* Dikurangi untuk konsistensi */
                            font-weight: bold;
                            margin: 5px 0;
                            text-decoration: underline;
                        }

                        .company-name {
                            font-weight: bold;
                            font-size: 10px; /* Konsisten dengan base */
                            text-align: left;
                            margin-bottom: 10px;
                        }

                        .section-title {
                            font-weight: bold;
                            margin: 10px 0 5px 0;
                            font-size: 10px; /* Konsisten dengan base */
                        }

                        table {
                            width: 100%;
                            border-collapse: collapse;
                            font-size: 9px; /* Sedikit lebih kecil untuk tabel */
                            margin-bottom: 10px;
                            table-layout: fixed; /* Fixed table layout */
                        }

                        th,
                        td {
                            border: 1px solid black;
                            padding: 3px 5px;
                            text-align: center;
                            vertical-align: middle;
                            font-size: 9px; /* Konsisten untuk semua sel tabel */
                        }

                        thead {
                            display: table-header-group;
                        }

                        tbody {
                            display: table-row-group;
                        }

                        .body-map {
                            width: 80px;
                            height: auto;
                            margin: 5px auto;
                            display: block;
                        }

                        .info-section {
                            margin-bottom: 10px;
                        }

                        .info-section p {
                            margin: 3px 0;
                            font-size: 9px; /* Konsisten */
                        }

                        .info-label {
                            font-weight: normal;
                            width: 120px;
                            float: left;
                            font-size: 9px; /* Konsisten, tidak lagi 10pt */
                        }

                        .info-value {
                            display: inline-block;
                            font-size: 9px; /* Konsisten */
                        }

                        .customer-info,
                        .sampling-info,
                        .worker-info {
                            margin-left: 0;
                            margin-bottom: 10px;
                        }

                        .customer-info h4,
                        .sampling-info h4,
                        .worker-info h4 {
                            margin: 5px 0 2px 0;
                            font-size: 10px; /* Konsisten */
                            font-weight: bold;
                        }

                        .risk-table {
                            margin-top: 5px;
                        }

                        .left-section p {
                            font-weight: bold;
                            text-align: justify;
                            margin-bottom: 5px;
                            font-size: 9px; /* Konsisten */
                        }

                        .table-note {
                            font-size: 8px; /* Tetap kecil untuk catatan */
                            margin-top: 3px;
                            font-style: italic;
                        }

                        .job-description {
                            margin-top: 10px;
                        }

                        .job-description th {
                            width: 30%;
                            text-align: left;
                            vertical-align: top;
                            font-size: 9px; /* Konsisten */
                        }

                        .job-description td {
                            vertical-align: top;
                            font-size: 9px; /* Konsisten */
                        }

                        .conclusion-box {
                            border: 1px solid black;
                            padding: 5px;
                            min-height: 30px;
                            margin-top: 5px;
                            margin-bottom: 10px;
                            font-size: 9px; /* Konsisten */
                        }

                        .conclusion-box .section-title {
                            margin-top: 0;
                            margin-bottom: 5px;
                            font-size: 10px; /* Konsisten */
                        }

                        /* Fixed Layout - Tidak Responsif */
                        .left-section {
                            width: 60%;
                            float: left;
                            box-sizing: border-box;
                            min-width: 60%;
                            max-width: 60%;
                        }

                        .right-section {
                            width: 39%;
                            float: right;
                            box-sizing: border-box;
                            min-width: 39%;
                            max-width: 39%;
                        }

                        /* Pastikan layout tetap fixed untuk print */
                        @media print {
                            .left-section {
                                width: 60% !important;
                                min-width: 60% !important;
                                max-width: 60% !important;
                            }
                            
                            .right-section {
                                width: 39% !important;
                                min-width: 39% !important;
                                max-width: 39% !important;
                            }
                        }

                        .result-header {
                            text-align: center;
                            font-weight: bold;
                            margin: 5px 0;
                            font-size: 9px; /* Konsisten dengan tabel */
                        }

                        /* Styling untuk tabel nested SEBELUM/SESUDAH */
                        .nested-table-container {
                            padding: 0;
                        }

                        .nested-table {
                            width: 100%;
                            margin: 0;
                            border: none;
                        }

                        .nested-table td {
                            border: 1px solid black;
                            width: 50%;
                            text-align: center;
                            font-weight: bold;
                            padding: 3px;
                            font-size: 9px; /* Konsisten */
                        }

                        .total-score {
                            font-weight: bold;
                            text-align: center;
                            margin-top: 5px;
                            font-size: 9px; /* Konsisten */
                        }

                        .content-container {
                            width: 100%;
                            min-width: 800px; /* Pastikan layout minimum */
                        }

                        .clearfix::after {
                            content: "";
                            clear: both;
                            display: table;
                        }
                        
                        .info-header {
                            font-weight: bold;
                            margin-top: 8px;
                            margin-bottom: 3px;
                            font-size: 10px; /* Konsisten, tidak lagi 10pt */
                            clear: both;
                        }

                        /* Styling khusus untuk informasi di sisi kanan */
                        .right-section div {
                            font-size: 9px; /* Base untuk right section */
                        }

                        .right-section span {
                            font-size: 9px; /* Konsisten untuk semua span */
                        }

                        /* Styling untuk div dengan margin-bottom di right section */
                        .right-section div[style*="margin-bottom: 3px"] {
                            margin-bottom: 3px;
                            font-size: 9px; /* Konsisten, tidak lagi 10pt */
                    }';
                    $rebaSpecificCss = '
                        * { 
                            box-sizing: border-box; 
                            margin: 0;
                            padding: 0;
                        }

                        body {
                            font-family: Arial, sans-serif;
                            font-size: 8px;
                            margin: 0;
                            padding: 0;
                        }

                        .container {
                            width: 100%;
                            clear: both;
                        }

                        /* Header */
                        .main-header {
                            text-align: center;
                            font-weight: bold;
                            font-size: 12px;
                            text-decoration: underline;
                            margin-bottom: 6px;
                            padding: 4px 0;
                            clear: both;
                            width: 100%;
                            display: block;
                        }

                        /* Main content wrapper */
                        .main-content {
                            clear: both;
                            overflow: hidden;
                            margin-bottom: 6px;
                            width: 100%;
                        }

                        /* Column layouts */
                        .column-left {
                            float: left;
                            width: 30%;
                            padding-right: 2px;
                        }

                        .column-center {
                            float: left;
                            width: 30%;
                            padding: 0 1px;
                        }

                        .column-right {
                            float: right;
                            width: 40%;
                            padding-left: 2px;
                        }

                        /* Bottom section */
                        .bottom-section {
                            clear: both;
                            overflow: hidden;
                            margin-top: 6px;
                            width: 100%;
                        }

                        .bottom-left {
                            float: left;
                            width: 60%;
                            padding-right: 2px;
                        }

                        .bottom-right {
                            float: right;
                            width: 40%;
                            padding-left: 2px;
                        }

                        /* Table styles */
                        table {
                            border-collapse: collapse;
                            width: 100%;
                            margin-bottom: 4px;
                            font-size: 8px;
                            border-spacing: 0;
                        }

                        th, td {
                            border: 1px solid #000;
                            padding: 1px 2px;
                            vertical-align: top;
                            font-size: 8px;
                            line-height: 1.0;
                            margin: 0;
                        }

                        th {
                            background-color: #f2f2f2;
                            font-weight: bold;
                            text-align: center;
                            padding: 2px;
                        }

                        .section-header {
                            font-weight: bold;
                            font-size: 8px;
                            text-decoration: underline;
                            text-align: left;
                            margin: 2px 0 1px 0;
                            display: block;
                            clear: both;
                        }

                        .info-table {
                            border: 0;
                            margin-bottom: 3px;
                        }

                        .info-table td {
                            border: 0;
                            padding: 0px 1px;
                            font-size: 8px;
                            vertical-align: top;
                            line-height: 1.0;
                        }

                        .final-score {
                            background-color: #e0e0e0;
                            font-weight: bold;
                        }

                        /* Image optimization */
                        td img {
                            max-width: 100%;
                            max-height: 25px;
                            object-fit: contain;
                            display: block;
                            margin: 0 auto;
                            vertical-align: middle;
                        }

                        /* Specific table adjustments */
                        .score-table th:first-child { width: 8%; }
                        .score-table th:nth-child(2) { width: 67%; }
                        .score-table th:last-child { width: 25%; }

                        .header-info-table th { 
                            width: 33.33%; 
                            padding: 2px 1px;
                            font-size: 8px;
                        }

                        .header-info-table td {
                            padding: 2px 1px;
                            text-align: center;
                            font-size: 8px;
                        }

                        .reference-table th:nth-child(1) { width: 15%; }
                        .reference-table th:nth-child(2) { width: 12%; }
                        .reference-table th:nth-child(3) { width: 23%; }
                        .reference-table th:nth-child(4) { width: 50%; }

                        .reference-table td {
                            font-size: 7px;
                            padding: 1px;
                            line-height: 1.0;
                        }

                        /* Compact spacing */
                        .image-row td:first-child {
                            text-align: center;
                            vertical-align: middle;
                            font-size: 8px;
                        }

                        .image-row td:nth-child(2) {
                            height: 25px;
                            padding: 1px;
                        }

                        .image-row td:last-child {
                            text-align: center;
                            vertical-align: middle;
                            font-size: 8px;
                        }

                        .label-row td {
                            text-align: center;
                            font-size: 7px;
                            padding: 1px;
                            height: 12px;
                        }

                        /* Footer notes */
                        .footer-notes {
                            border: 0;
                            margin-top: 15px;
                        }

                        .footer-notes td:first-child {
                            width: 2%;
                            text-align: right;
                            vertical-align: top;
                            font-size: 7px;
                            border: 0;
                            padding-right: 3px;
                        }

                        .footer-notes td:last-child {
                            width: 98%;
                            text-align: left;
                            font-size: 7px;
                            border: 0;
                            line-height: 1.0;
                        }

                        /* Conclusion table specific */
                        .conclusion-table td:first-child {
                            width: 35%;
                            text-align: center;
                            font-weight: bold;
                            vertical-align: middle;
                            height: 35px;
                            font-size: 8px;
                        }

                        .conclusion-table td:last-child {
                            width: 65%;
                            text-align: justify;
                            vertical-align: top;
                            font-size: 8px;
                            line-height: 1.1;
                            padding: 3px;
                        }

                        /* Compact margin adjustments */
                        .compact-table {
                            margin-bottom: 2px;
                        }

                        /* Text alignment helpers */
                        .text-left { text-align: left !important; padding-left: 3px; }
                        .text-center { text-align: center !important; }
                        .text-justify { text-align: justify !important; }

                        /* Clear floats helper */
                        .clearfix::after {
                            content: "";
                            display: table;
                            clear: both;
                        }
                    ';
                    // Setup header
                    switch($type) {
                        case 'draft':
                            $header ='<table width="100%" border="0" style="border:none; border-collapse:collapse;">
                                    <tr>
                                        <td class="left-cell" style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: left; padding-left: 20px;">
                                        </td>
                                        <td style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: center;"><span>LAPORAN HASIL PENGUJIAN</span></td>
                                        <td style="border: none; padding: 10px; vertical-align: middle; height: 60px width: 33.33%; text-align: right; padding-right: 50px;">
                                        </td>
                                    <tr>
                                    </table>';
                                break;
                        case 'lhp':
                            $noSampelAkre = OrderDetail::where('no_sampel',$noSampel)->first();
                            $decodeParameterNya =json_decode($noSampelAkre->parameter,true);
                            $idParameterAkre =explode(';',$decodeParameterNya[0])[0];
                            $akreditasiKan = Parameter::where('id', $idParameterAkre)->where('status', "AKREDITASI")->where('is_active', true)->first();

                            if($akreditasiKan === null){
                                $header ='<table width="100%" border="0" style="border:none; border-collapse:collapse; text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif; ">
                                        <tr>
                                            <td class="left-cell" style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: left; padding-left: 20px;">
                                                <img src="'.public_path('img/isl_logo.png').'" alt="ISL"  style ="height: 50px; width: auto; display: block;">
                                            </td>
                                            <td  style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: center;">
                                                <span style="font-size:16px;font-weight: bold; border-bottom: 1px solid #000">LAPORAN HASIL PENGUJIAN</span>
                                            </td>
                                            <td style="border: none; padding: 10px; vertical-align: middle; height: 60px width: 33.33%; text-align: right; padding-right: 50px;">
                                            </td>
                                        </tr>
                                        </table>';
                            }else{
                                $header = '<table width="100%" border="0" style="border:none; border-collapse:collapse; text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif; ">
                                        <tr>
                                            <td class="left-cell" style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: left; padding-left: 20px;">
                                                <img src="'.public_path('img/isl_logo.png').'" alt="ISL"  style ="height: 50px; width: auto; display: block;">
                                            </td>
                                            <td  style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: center;">
                                                <span style="font-weight: bold; border-bottom: 1px solid #000">LAPORAN HASIL PENGUJIAN</span>
                                            </td>
                                            <td style="border: none; padding: 10px; vertical-align: middle; height: 60px width: 33.33%; text-align: right; padding-right: 50px;">
                                                <img src="'.public_path('img/logo_kan.png').'" alt="KAN" style ="height: 50px; width: auto; display: block;">
                                            </td>
                                        </tr>
                                        </table>';
                            }
                                break;
                        case 'lhp_digital':
                            /* chek akreditasi */
                            $noSampelAkre = OrderDetail::where('no_sampel',$noSampel)->first();
                            $decodeParameterNya =json_decode($noSampelAkre->parameter,true);
                            $idParameterAkre =explode(';',$decodeParameterNya[0])[0];
                            $akreditasiKan = Parameter::where('id', $idParameterAkre)->where('status', "AKREDITASI")->where('is_active', true)->first();

                            if($akreditasiKan === null){
                                $header = '<table width="100%" border="0" style="border:none; border-collapse:collapse;">
                                        <tr>
                                            <td class="left-cell" style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: left; padding-left: 20px;">
                                                <img src="'.public_path('img/isl_logo.png').'" alt="ISL"  style ="height: 50px; width: auto; display: block;">
                                            </td>
                                            <td  style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: center;">
                                                <span class="header-title">LAPORAN HASIL PENGUJIAN</span>
                                            </td>
                                            <td style="border: none; padding: 10px; vertical-align: middle; height: 60px width: 33.33%; text-align: right; padding-right: 50px;">
                                            </td>
                                        </tr>
                                        </table>';
                            }else{
                                $header = '<table width="100%" border="0" style="border:none; border-collapse:collapse;">
                                        <tr>
                                            <td class="left-cell" style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: left; padding-left: 20px;">
                                                <img src="'.public_path('img/isl_logo.png').'" alt="ISL"  style ="height: 50px; width: auto; display: block;">
                                            </td>
                                            <td  style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: center;">
                                                <span class="header-title">LAPORAN HASIL PENGUJIAN</span>
                                            </td>
                                            <td style="border: none; padding: 10px; vertical-align: middle; height: 60px width: 33.33%; text-align: right; padding-right: 50px;">
                                                <img src="'.public_path('img/logo_kan.png').'" alt="KAN" style ="height: 50px; width: auto; display: block;">
                                            </td>
                                        </tr>
                                        </table>';
                            }
                                break;
                    }
                    $pdf->SetHeader($header);

                    // Setup watermark dan footer berdasarkan tipe
                    switch($type) {
                        case 'draft':
                            // Draft: watermark draft + footer dengan QR
                            $pageWidth = $pdf->w;
                            $watermarkPath = public_path().'/watermark-draft.png';
                            $pdf->SetWatermarkImage(
                                $watermarkPath,
                                0.1,
                                '',
                                [0, 0],
                                $pageWidth,
                                0
                            );
                            $pdf->showWatermarkImage = true;
                            
                            $footerHtml = '
                                <table width="100%" border="0" style="border:none; border-collapse:collapse; font-family: Arial, sans-serif; margin: 0; padding: 0;">
                                    <tr>
                                        <td width="30%" style="vertical-align: top; font-size: 6px; line-height: 1.1; padding: 0; text-align: left; border:none;">
                                            <div>PT Inti Surya Laboratorium</div>
                                            <div>Ruko Icon Business Park Blok O No.5-6 BSD City, Jl. BSD Raya Utama, Cisauk, Sampora Kab. Tangerang 15341</div>
                                            <div>021-5089-8988/89 contact@intilab.com</div>
                                        </td>
                                        <td width="45%" style="font-size: 6px; vertical-align: top; text-align: center; line-height: 1.1; padding: 0; border:none">
                                            Laporan hasil pengujian ini hanya berlaku bagi sampel yang tercantum di atas. Lembar ini tidak boleh diubah ataupun digandakan tanpa izin tertulis dari pihak Laboratorium..<br>
                                        </td>
                                        <td width="25%" style="text-align: right; vertical-align: top; padding: 0; border:none font-family: roboto; font-weight: bold;">
                                            <b>Halaman {PAGENO} dari {nbpg}</b>
                                        </td>
                                    </tr>
                                </table>';
                            break;
                            
                        case 'lhp':
                            // LHP: watermark + footer tanpa QR
                            $pdf->SetWatermarkImage(public_path('logo-watermark.png'), -1, '', [110, 35]);
                            $pdf->showWatermarkImage = true;
                            $file_qr = public_path('qr_documents/' . $pdfFile->file_qr . '.svg');
                            $footerHtml = '
                                <table width="100%" border="0" style="border:none; border-collapse:collapse; margin: 0; padding: 0;">
                                    <tr>
                                        <!-- Kolom 1: Alamat -->
                                        <!-- Pindahkan font-size ke sini. Gunakan 5pt atau 8pt untuk ukuran kecil yang terbaca -->
                                        <td width="30%" style="font-size: 5pt; font-family: Arial, sans-serif; vertical-align: top; line-height: 1.2; padding: 0; text-align: left; border:none; font-weight: bold;">
                                            <div>PT Inti Surya Laboratorium</div>
                                            <div>Ruko Icon Business Park Blok O No.5-6 BSD City,</div>
                                            <div>Jl. BSD Raya Utama, Cisauk, Sampora Kab. Tangerang 15341</div>
                                            <div>021-5089-8988/89 contact@intilab.com</div>
                                        </td>

                                        <!-- Kolom 2: Disclaimer -->
                                        <td width="45%" style="font-size: 5pt; font-family: Roboto, sans-serif; vertical-align: top; text-align: center; line-height: 1.2; padding: 0; border:none; font-weight: bold;">
                                            Laporan hasil pengujian ini hanya berlaku bagi sampel yang tercantum di atas. Lembar ini tidak boleh diubah ataupun digandakan tanpa izin tertulis dari pihak Laboratorium.<br>
                                            Halaman {PAGENO} dari {nbpg}
                                        </td>

                                        <!-- Kolom 3: Kode Dokumen -->
                                        <!-- PERHATIKAN: Tanda titik koma (;) telah ditambahkan setelah border:none -->
                                        <td width="25%" style="font-size: 5pt; font-family: Roboto, sans-serif; font-weight: bold; text-align: right; vertical-align: top; padding: 0; border:none;">
                                            DP/7.8.1/ISL; Rev 3; 08 November 2022
                                        </td>
                                    </tr>
                                </table>';
                            break;
                            
                        case 'lhp_digital':
                            // LHP Digital: watermark logo + footer dengan QR
                            $pdf->SetWatermarkImage(public_path('logo-watermark.png'), -1, '', [110, 35]);
                            $pdf->showWatermarkImage = true;
                            $file_qr = public_path('qr_documents/' . $pdfFile->file_qr . '.svg');
                            $footerHtml = '
                                <table width="100%" border="0" style="font-size: 6px; border:none; border-collapse:collapse; font-family: Arial, sans-serif; margin: 0; padding: 0;">
                                    <tr>
                                        <td width="30%" style="vertical-align: top; font-size: 6px; line-height: 1.1; padding: 0; text-align: left; border:none;">
                                            <div>PT Inti Surya Laboratorium</div>
                                            <div>Ruko Icon Business Park Blok O No.5-6 BSD City, Jl. BSD Raya Utama, Cisauk, Sampora Kab. Tangerang 15341</div>
                                            <div>021-5089-8988/89 contact@intilab.com</div>
                                        </td>
                                        <td width="45%" style="vertical-align: top; text-align: center; line-height: 1.1; padding: 0; border:none; min-height:100px; font-family: roboto;">
                                            Laporan hasil pengujian ini hanya berlaku bagi sampel yang tercantum di atas. Lembar ini tidak boleh diubah ataupun digandakan tanpa izin tertulis dari pihak Laboratorium.<br>
                                            <b>Halaman {PAGENO} dari {nbpg}</b>
                                        </td>
                                        <td width="25%" style="text-align: right; vertical-align: top; padding: 0; border:none">
                                            <img src="'.$file_qr.'" width="25" height="25" alt="QR Code" />
                                        </td>
                                    </tr>
                                </table>';
                            break;
                    }
                    $pdf->SetHTMLFooter($footerHtml,'0');

                    $pdf->setAutoBottomMargin = 'stretch';
                    // Tulis semua konten HTML
                    foreach ($methodsToCombine as $methodName => $methodId) {
                        // Ambil data untuk setiap metode dan no_sampel yang diminta
                        $dataMethod = DataLapanganErgonomi::with(['detail'])
                            ->where('no_sampel', $noSampel)
                            ->where('method', $methodId)
                            ->first();
                    
                        if ($dataMethod) {
                            $ttdData = null;
                            switch($type) {
                                case 'draft':
                                    $ttdData = null; // Tidak ada TTD untuk draft
                                    break;
                                case 'lhp':
                                    $ttdData = (object)[
                                        'show_signature' => true,
                                        'show_qr_in_signature' => true,
                                        'nama_karyawan' => $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah',
                                        'jabatan_karyawan' => $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor',
                                        'tanggal' => Carbon::parse($pdfFile->tanggal_lhp)->locale('id')->isoFormat('DD MMMM YYYY'),
                                        'qr_path' => public_path('qr_documents/' . $pdfFile->file_qr . '.svg')
                                    ];
                                    break;
                                case 'lhp_digital':
                                    $ttdData = (object)[
                                        'show_signature' => true,
                                        'show_qr_in_signature' => false, // Tidak perlu QR di TTD karena sudah ada di footer
                                        'nama_karyawan' => $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah',
                                        'jabatan_karyawan' => $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor',
                                        'tanggal' =>Carbon::parse($pdfFile->tanggal_lhp)->locale('id')->isoFormat('DD MMMM YYYY'),
                                        'qr_path' => public_path('qr_documents/' . $pdfFile->file_qr . '.svg')
                                    ];
                                    break;
                            }
                            $htmlContent = '';
                            // Panggil metode yang sesuai di TemplateLhpsErgonomi dan dapatkan HTMLnya
                            switch ($methodName) {
                                case 'rwl':
                                    $htmlContent = $render->ergonomiRwl($dataMethod,'','',$ttdData);
                                    break;
                                case 'nbm':
                                    $htmlContent = $render->ergonomiNbm($dataMethod,'',$templateSpecificCssNbm,$ttdData);
                                    break;
                                case 'reba':
                                    $htmlContent = $render->ergonomiReba($dataMethod,$globalCssContent,$rebaSpecificCss,$ttdData);
                                    break;
                                case 'rula':
                                    $htmlContent = $render->ergonomiRula($dataMethod,'','',$ttdData);
                                    break;
                                case 'rosa':
                                    $htmlContent = $render->ergonomiRosa($dataMethod,'','',$ttdData);
                                    break;
                                case 'brief':
                                    $htmlContent = $render->ergonomiBrief($dataMethod,'','',$ttdData);
                                    break;
                                case 'sni_gotrak':
                                    $htmlContent = $render->ergonomiGontrak($dataMethod,$globalCssContent,$templateSpecificCss,$ttdData);
                                    break;
                                case 'sni_bahaya_ergonomi':
                                    $htmlContent = $render->ergonomiPotensiBahaya($dataMethod,'','',$ttdData);
                                    break;
                            }
                            if ($htmlContent != '') {
                                $allHtmlContent[] = $htmlContent;
                            }

                        }
                    }
                    
                    $firstPage = true;
                    foreach ($allHtmlContent as $htmlContent) {
                        if (!$firstPage) {
                            $pdf->AddPage();
                        }
                        $pdf->WriteHTML($htmlContent);
                        $firstPage = false;
                    }
                    
                    // Simpan file
                    $namaFile = 'LHP-'.str_replace('/', '-', $dataLHP->detail->cfr).'.pdf';
                    // $pathFile = $dir.'/'.$type.'/'.$namaFile;
                    $pathFile = $dir.'/'.'LHP_DOWNLOAD'.'/'.$namaFile;
                    $pdf->Output($pathFile, 'F');
                    return [$pdf,$namaFile];
                };

                $pdfLhp = $createPDF('lhp')[0];
                $pdfFile->name_file = $createPDF('lhp')[1];
                $pdfFile->save();
            }
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    // Amang
    public function getUser(Request $request)
    {
        $users = MasterKaryawan::with(['department', 'jabatan'])->where('id', $request->id ?: $this->user_id)->first();

        return response()->json($users);
    }

    // Amang
    public function getLink(Request $request)
    {
        try {
            $link = GenerateLink::where(['id_quotation' => $request->id, 'quotation_status' => 'draft_lhp_air', 'type' => 'draft_air'])->first();

            if (!$link) {
                return response()->json(['message' => 'Link not found'], 404);
            }
            return response()->json(['link' => env('PORTALV3_LINK') . $link->token], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    // Amang
    public function sendEmail(Request $request)
    {
        DB::beginTransaction();
        try {
            $categoryKebisingan = [23, 24, 25];
            $categoryGetaran = [13, 14, 15, 16, 17, 18, 19, 20];
            $categoryLingkunganKerja = [11, 27, 53];
            $categoryPencahayaan = [28];
            $category = explode('-', $request->kategori)[0];
            $sub_category = explode('-', $request->kategori)[1];
            if ($request->id != '' || isset($request->id)) {
                if (in_array($category, $categoryKebisingan)) {
                    $data = LhpsKebisinganHeader::where('id', $request->id)->update([
                        'is_emailed' => true,
                        'emailed_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        'emailed_by' => $this->karyawan
                    ]);
                } else if (in_array($category, $categoryGetaran)) {
                    $data = LhpsGetaranHeader::where('id', $request->id)->update([
                        'is_emailed' => true,
                        'emailed_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        'emailed_by' => $this->karyawan
                    ]);
                } else if (in_array($category, $categoryPencahayaan)) {
                    $data = LhpsPencahayaanHeader::where('id', $request->id)->update([
                        'is_emailed' => true,
                        'emailed_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        'emailed_by' => $this->karyawan
                    ]);
                } else if (in_array($category, $categoryLingkunganKerja)) {
                    if (is_string($request->parameter)) {
                        $decodedString = html_entity_decode($request->parameter);
                        $parameter = json_decode($decodedString, true);
                        $cleanedParameter = array_map(function ($item) {
                            return preg_replace('/^\d+;/', '', $item);
                        }, $parameter);
                    }
                    if (in_array("Medan Listrik", $cleanedParameter) || in_array("Medan Magnit Statis", $cleanedParameter) || in_array("Power Density", $cleanedParameter)) {
                        $data = LhpsMedanLMHeader::where('id', $request->id)->update([
                            'is_emailed' => true,
                            'emailed_at' => Carbon::now()->format('Y-m-d H:i:s'),
                            'emailed_by' => $this->karyawan
                        ]);
                    } else if (in_array("Sinar UV", $cleanedParameter)) {
                        $data = LhpsSinarUVHeader::where('id', $request->id)->update([
                            'is_emailed' => true,
                            'emailed_at' => Carbon::now()->format('Y-m-d H:i:s'),
                            'emailed_by' => $this->karyawan
                        ]);
                    } else {
                        $data = LhpsLingHeader::where('id', $request->id)->update([
                            'is_emailed' => true,
                            'emailed_at' => Carbon::now()->format('Y-m-d H:i:s'),
                            'emailed_by' => $this->karyawan
                        ]);
                    }
                }
            }
            $email = SendEmail::where('to', $request->to)
                ->where('subject', $request->subject)
                ->where('body', $request->content)
                ->where('cc', $request->cc)
                ->where('bcc', $request->bcc)
                ->where('attachments', $request->attachments)
                ->where('karyawan', $this->karyawan)
                ->noReply()
                ->send();

            if ($email) {
                DB::commit();
                return response()->json([
                    'message' => 'Email berhasil dikirim'
                ], 200);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Email gagal dikirim'
                ], 400);
            }
        } catch (\Exception $th) {
            DB::rollBack();
            dd($th);
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function getTechnicalControl(Request $request)
    {
        try {
            $data = MasterKaryawan::where('id_department', 17)->select('jabatan', 'nama_lengkap')->get();
            return response()->json([
                'status' => true,
                'data' => $data
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line' => $th->getLine()
            ], 500);
        }
    }

    /* public function setSignature(Request $request)
    {
        $categoryKebisingan = [23, 24, 25];
        $categoryGetaran = [13, 14, 15, 16, 17, 18, 19, 20];
        $categoryLingkunganKerja = [11, 27, 53];
        $categoryPencahayaan = [28];

        try {
            if (in_array($request->category, $categoryKebisingan)) {
                $header = LhpsKebisinganHeader::where('id', $request->id)->first();
                $detail = LhpsKebisinganDetail::where('id_header', $header->id)->get();
            } else if (in_array($request->category, $categoryPencahayaan)) {
                $header = LhpsPencahayaanHeader::where('id', $request->id)->first();
                $detail = LhpsPencahayaanDetail::where('id_header', $header->id)->get();
            } else if (in_array($request->category, $categoryLingkunganKerja)) {
                if ($request->mode == "medanlm") {
                    $header = LhpsMedanLMHeader::where('id', $request->id)->first();
                    $detail = LhpsMedanLMDetail::where('id_header', $header->id)->get();
                } else if ($request->mode == "sinaruv") {
                    $header = LhpsSinarUVHeader::where('id', $request->id)->first();
                    $detail = LhpsSinarUVDetail::where('id_header', $header->id)->get();
                } else {
                    $header = LhpsLingHeader::where('id', $request->id)->first();
                    $detail = LhpsLingDetail::where('id_header', $header->id)->get();
                }
            }

            if ($header != null) {
                $header->nama_karyawan = $this->karyawan;
                $header->jabatan_karyawan = $request->attributes->get('user')->karyawan->jabatan;
                $header->save();

                $file_qr = new GenerateQrDocumentLhp();
                $file_qr = $file_qr->insert('LHP_AIR', $header, $this->karyawan);
                if ($file_qr) {
                    $header->file_qr = $file_qr;
                    $header->save();
                }

                $groupedByPage = [];
                if (!empty($custom)) {
                    foreach ($custom as $item) {
                        $page = $item['page'];
                        if (!isset($groupedByPage[$page])) {
                            $groupedByPage[$page] = [];
                        }
                        $groupedByPage[$page][] = $item;
                    }
                }

                $job = new RenderLhp($header, $detail, 'downloadWSDraft', $groupedByPage);
                $this->dispatch($job);

                $job = new RenderLhp($header, $detail, 'downloadLHP', $groupedByPage);
                $this->dispatch($job);

                return response()->json([
                    'message' => 'Signature berhasil diubah'
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
        }
    } */

    // Amang
    public function encrypt($data)
    {
        $ENCRYPTION_KEY = 'intilab_jaya';
        $ENCRYPTION_ALGORITHM = 'AES-256-CBC';
        $EncryptionKey = base64_decode($ENCRYPTION_KEY);
        $InitializationVector = openssl_random_pseudo_bytes(openssl_cipher_iv_length($ENCRYPTION_ALGORITHM));
        $EncryptedText = openssl_encrypt($data, $ENCRYPTION_ALGORITHM, $EncryptionKey, 0, $InitializationVector);
        $return = base64_encode($EncryptedText . '::' . $InitializationVector);
        return $return;
    }

    // Amang
    public function decrypt($data = null)
    {
        $ENCRYPTION_KEY = 'intilab_jaya';
        $ENCRYPTION_ALGORITHM = 'AES-256-CBC';
        $EncryptionKey = base64_decode($ENCRYPTION_KEY);
        list($Encrypted_Data, $InitializationVector) = array_pad(explode('::', base64_decode($data), 2), 2, null);
        $data = openssl_decrypt($Encrypted_Data, $ENCRYPTION_ALGORITHM, $EncryptionKey, 0, $InitializationVector);
        $extand = explode("|", $data);
        return $extand;
    }

    public function handleGenerateLink(Request $request){
        DB::beginTransaction();
        try {
            $header =DraftErgonomiFile::where('no_sampel',$request->no_sampel)->first();
            if($header == null){
                return response()->json(["message" =>"dokumen belum  di bentuk"],401);
            }
            $key = $header->no_sampel . str_replace('.', '', microtime(true));
            $gen = MD5($key);
            $gen_tahun = self::encrypt(DATE('Y-m-d'));
            $token = self::encrypt($gen . '|' . $gen_tahun);
            $insertData = [
                'token' => $token,
                'key' => $gen,
                'id_quotation' => $header->id,
                'quotation_status' => "draft_ergonomi",
                'type' => 'draft',
                'expired' => Carbon::now()->addYear()->format('Y-m-d'),
                'fileName_pdf' => $header->name_file,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'created_by' => $this->karyawan
            ];
            $insert = GenerateLink::insertGetId($insertData);
            $header->is_generate_link = true;
            $header->save();
            DB::commit();
            return response()->json([
                'message' => 'Generate link success!',
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    public function copyLink(Request $request)
    {
        $generatedFiles = DraftErgonomiFile::with('link')
            ->where('no_sampel', $request->no_sampel)
            ->where('is_generate_link', 1)
            ->first();

        if ($generatedFiles && $generatedFiles->link) {
            // $url = 'http://127.0.0.1:8000/public/auth/'; // Dev
            $url = 'https://portal.intilab.com/public/auth/'; // Prod
            $portal = $url . $generatedFiles->link->token;

            return response()->json([
                'message' => 'Link berhasil dibuat',
                'link'    => $portal
            ], 200);
        }

        return response()->json(['message' => 'Data belum tersedia'], 400);
    }

    public function updateTanggalLhp(Request $request)
    {
        DB::beginTransaction();
        try {
            $dataHeader = DraftErgonomiFile::find($request->id);

            if (!$dataHeader) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data tidak ditemukan'
                ], 404);
            }

            // Update tanggal LHP dan data pengesahan
            $dataHeader->tanggal_lhp = $request->value;

            $pengesahan = PengesahanLhp::where('berlaku_mulai', '<=', $request->value)
                ->orderByDesc('berlaku_mulai')
                ->first();

            $dataHeader->nama_karyawan = $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah';
            $dataHeader->jabatan_karyawan = $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor';

            // Update QR Document jika ada
            $qr = QrDocument::where('file', $dataHeader->file_qr)->first();
            if ($qr) {
                $dataQr = json_decode($qr->data, true);
                $dataQr['Tanggal_Pengesahan'] = Carbon::parse($request->value)->locale('id')->isoFormat('DD MMMM YYYY');
                $dataQr['Disahkan_Oleh'] = $dataHeader->nama_karyawan;
                $dataQr['Jabatan'] = $dataHeader->jabatan_karyawan;
                $qr->data = json_encode($dataQr);
                $qr->save();
            }

            // Render ulang file LHP

            $this->updatePdf($dataHeader->no_sampel,$request->value);
            // $dataHeader->file_lhp = $fileName;
            $dataHeader->save();
            DB::commit();
            return response()->json([
                'status' => true,
                'message' => 'Tanggal LHP berhasil diubah',
                'data' => $dataHeader
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ], 500);
        }
    }

}
