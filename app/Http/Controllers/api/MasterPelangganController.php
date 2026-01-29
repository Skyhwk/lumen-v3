<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\MasterPelanggan;
use App\Models\KontakPelanggan;
use App\Models\AlamatPelanggan;
use App\Models\PicPelanggan;
use App\Models\MasterKaryawan;
use App\Models\HargaTransportasi;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\OrderHeader;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\AlamatPelangganBlacklist;
use App\Models\KontakPelangganBlacklist;
use App\Models\MasterPelangganBlacklist;
use App\Models\PelangganBlacklist;
use App\Models\PicPelangganBlacklist;
use Yajra\Datatables\Datatables;

use App\Services\GetBawahan;
use Carbon\Carbon;
Carbon::setLocale('id');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class MasterPelangganController extends Controller
{
    public function index(Request $request)
    {
        $data = MasterPelanggan::with(['kontak_pelanggan', 'alamat_pelanggan', 'pic_pelanggan', 'order_customer'])->where(['is_active' => true]);

        $user = $request->attributes->get('user');
        if (isset($user->karyawan) && $user->karyawan != null) {
            $jabatan = $user->karyawan->id_jabatan;

            if ($jabatan == 24) {
                $data->where('sales_id', $this->user_id);
            }

            if ($jabatan == 21) {
                $bawahan = MasterKaryawan::where('is_active', true)->whereJsonContains('atasan_langsung', (string) $this->user_id)->pluck('id')->toArray();

                array_push($bawahan, $this->user_id);

                $data->whereIn('sales_id', $bawahan);
            }

            if ($jabatan == 157) {
                $bawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('id')->toArray();

                $karyawanNonAktif = MasterKaryawan::where('is_active', false)->pluck('id')->toArray();

                $bawahan = array_merge($bawahan, $karyawanNonAktif);
                
                array_push($bawahan, 14);

                if (!in_array($this->user_id, $bawahan)) {
                    $bawahan[] = $this->user_id;
                }

                $data->whereIn('sales_id', $bawahan);
            }

            if($this->user_id != 127){
                $data->where('sales_id', '!=', 127);
            }


        }

        return Datatables::of($data)
            ->filterColumn('order_customer', function ($query, $keyword) {
                if (str_contains('ordered', strtolower($keyword))) {
                    $query->whereHas('order_customer');
                } else {
                    $query->whereDoesntHave('order_customer');
                }
            })
            ->filterColumn('telpon', function ($query, $keyword) {
                $query->whereHas('kontak_pelanggan', function ($q) use ($keyword) {
                    $q->where('no_tlp_perusahaan', 'like', "%{$keyword}%");
                });
            })
            ->orderColumn('telpon', function ($query, $orderDirection) {
                $query->with(['kontak_pelanggan' => function($q) use ($orderDirection) {
                    $q->orderBy('no_tlp_perusahaan', $orderDirection);
                }]);
            })
            ->make(true);
    }

    public function historyPenawaranCustomer(Request $request)
    {
        $data = MasterPelanggan::with(['kontak_pelanggan', 'alamat_pelanggan', 'pic_pelanggan'])
            ->where('is_active', true);

        $user = $request->attributes->get('user');
        if (isset($user->karyawan) && $user->karyawan != null) {
            $jabatan = $user->karyawan->id_jabatan;
            if ($jabatan == 24) {
                $data->where('sales_id', $this->user_id);
            }

            if ($jabatan == 21) {
                $bawahan = MasterKaryawan::where('is_active', true)->whereJsonContains('atasan_langsung', (string) $this->user_id)->pluck('id')->toArray();
                array_push($bawahan, $this->user_id);

                $data->whereIn('sales_id', $bawahan);
            }
        }

        return Datatables::of($data)
            ->addColumn('is_ordered', function ($row) {
                return OrderHeader::where('id_pelanggan', $row->id_pelanggan)->exists();
            })
            ->addColumn('last_quoted', function ($row) {
                $lastKontrak = QuotationKontrakH::where('pelanggan_ID', $row->id_pelanggan)
                    ->orderByDesc('tanggal_penawaran')
                    ->value('tanggal_penawaran');

                $lastNonKontrak = QuotationNonKontrak::where('pelanggan_ID', $row->id_pelanggan)
                    ->orderByDesc('tanggal_penawaran')
                    ->value('tanggal_penawaran');

                $dates = array_filter([$lastKontrak, $lastNonKontrak]);

                return count($dates) ? max($dates) : null;
            })
            ->make(true);
    }

    public function getQuotationsHistory(Request $request)
    {
        $data = collect([
            QuotationKontrakH::class,
            QuotationNonKontrak::class
        ])->flatMap(
            fn($model) =>
            $model::with(['order', 'sales'])
                ->where([
                    'pelanggan_ID' => $request->id_pelanggan,
                    'is_active' => true,
                ])
                ->get()
        );

        return Datatables::of($data)->make(true);
    }

    private function checkForBlacklistedCustomer($id = null, $nama_pelanggan, $kontak_pelanggan)
    {
        $blacklistedByName = MasterPelangganBlacklist::where('nama_pelanggan', $nama_pelanggan)
            ->when($id, fn($q) => $q->where('id', '!=', $id))
            ->exists();

        if ($blacklistedByName) return response()->json(['message' => 'Pelanggan dengan nama: ' . $nama_pelanggan . ' telah terdaftar di daftar hitam'],  401);

        foreach ($kontak_pelanggan['no_tlp_perusahaan'] as $i => $telNumber) {
            if ($telNumber) {
                $telNumber = preg_replace("/[^0-9]/", "", $telNumber);

                if (substr($telNumber, 0, 2) === "62") {
                    $telNumber = "0" . substr($telNumber, 2);
                }

                $blacklistedByTelNumber = KontakPelangganBlacklist::where('no_tlp_perusahaan', $telNumber)
                    ->when($id, fn($q) => $q->where('pelanggan_id', '!=', $id))
                    ->exists();

                if ($blacklistedByTelNumber) return response()->json(['message' => 'Pelanggan dengan nomor telepon: ' . $telNumber . ' telah terdaftar di daftar hitam'], 401);
            }

            if ($kontak_pelanggan['email_perusahaan'][$i]) {
                $blacklistedByEmail = KontakPelangganBlacklist::where('email_perusahaan', $kontak_pelanggan['email_perusahaan'][$i])
                    ->when($id, fn($q) => $q->where('pelanggan_id', '!=', $id))
                    ->exists();

                if ($blacklistedByEmail) return response()->json(['message' => 'Pelanggan dengan email: ' . $kontak_pelanggan['email_perusahaan'][$i] . ' telah terdaftar di daftar hitam'], 401);
            };
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $timestamp = DATE('Y-m-d H:i:s');

            if ($request->id != '') {
                // $response = $this->checkForBlacklistedCustomer($request->id, $request->nama_pelanggan, $request->kontak_pelanggan);
                // if ($response) return $response;

                // Update existing customer
                $pelanggan = MasterPelanggan::find($request->id);

                if (!$pelanggan) {
                    return response()->json(['message' => 'Pelanggan tidak ditemukan'], 404);
                }

                $dataPelanggan = $request->only([
                    'nama_pelanggan',
                    'wilayah',
                    'sub_kategori',
                    'npwp',
                    'bahan_pelanggan',
                    'merk_pelanggan',
                    'sales_penanggung_jawab',
                ]);

                // Tamabahan Patah
                $sales = MasterKaryawan::where('nama_lengkap', $request->sales_penanggung_jawab)->first();
                $dataPelanggan['sales_id'] = $sales->id;
                $dataPelanggan['sales_penanggung_jawab'] = $sales->nama_lengkap;
                
                $dataPelanggan['nama_pelanggan'] = trim($dataPelanggan['nama_pelanggan']);
                $dataPelanggan['npwp'] = trim($dataPelanggan['npwp']);
                $dataPelanggan['id_cabang'] = $this->idcabang;
                $dataPelanggan['updated_by'] = $this->karyawan;
                $dataPelanggan['updated_at'] = $timestamp;
                if ($request->kategori_pelanggan != '')
                    $dataPelanggan['kategori_pelanggan'] = $request->kategori_pelanggan;

                $pelanggan->update($dataPelanggan);

                if ($request->has('kontak_pelanggan')) {
                    $existingKontakIds = KontakPelanggan::where('pelanggan_id', $pelanggan->id)->pluck('id')->toArray();
                    $requestKontakIds = array_filter($request->kontak_pelanggan['id']);

                    // Deactivate kontak_pelanggan not present in the request
                    $idsToDeactivate = array_diff($existingKontakIds, $requestKontakIds);
                    KontakPelanggan::whereIn('id', $idsToDeactivate)->update([
                        'is_active' => false,
                        'deleted_by' => $this->karyawan,
                        'deleted_at' => $timestamp
                    ]);

                    foreach ($request->kontak_pelanggan['no_tlp_perusahaan'] as $index => $noTlp) {
                        if (!empty($noTlp)) { // Check if not empty
                            $noTlp = preg_replace("/[^0-9]/", "", $noTlp); // bersihin non-angka

                            if (substr($noTlp, 0, 2) === "62") { // convert depannya jadi 0
                                $noTlp = "0" . substr($noTlp, 2);
                            }
                            // cek noTlp
                            $sameTelNumber = KontakPelanggan::where('no_tlp_perusahaan', $noTlp)->where('is_active', true)->first();
                            if ($sameTelNumber && $sameTelNumber->pelanggan_id !== $pelanggan->id) {
                                DB::rollback();
                                return response()->json(['message' => 'Nomor telepon perusahaan sudah ada'], 400);
                            };

                            $kontak = [
                                'pelanggan_id' => $pelanggan->id,
                                'no_tlp_perusahaan' => $noTlp,
                                'email_perusahaan' => $request->kontak_pelanggan['email_perusahaan'][$index],
                                'is_active' => true
                            ];

                            if (!empty($request->kontak_pelanggan['id'][$index])) {
                                $kontak['updated_by'] = $this->karyawan;
                                $kontak['updated_at'] = $timestamp;
                                KontakPelanggan::where('id', $request->kontak_pelanggan['id'][$index])->update($kontak);
                            } else {
                                $kontak['created_by'] = $this->karyawan;
                                $kontak['created_at'] = $timestamp;
                                KontakPelanggan::create($kontak);
                            }
                        }
                    }
                }

                if ($request->has('alamat_pelanggan')) {
                    $existingAlamatIds = AlamatPelanggan::where('pelanggan_id', $pelanggan->id)->pluck('id')->toArray();
                    $requestAlamatIds = array_filter($request->alamat_pelanggan['id']);

                    // Deactivate alamat_pelanggan not present in the request
                    $idsToDeactivate = array_diff($existingAlamatIds, $requestAlamatIds);
                    AlamatPelanggan::whereIn('id', $idsToDeactivate)->update([
                        'is_active' => false,
                        'deleted_by' => $this->karyawan,
                        'deleted_at' => $timestamp
                    ]);

                    foreach ($request->alamat_pelanggan['alamat'] as $index => $alamat) {
                        if (!empty($alamat)) { // Check if not empty
                            $alamatData = [
                                'pelanggan_id' => $pelanggan->id,
                                'type_alamat' => $request->alamat_pelanggan['type_alamat'][$index],
                                'alamat' => $alamat,
                                'is_active' => true
                            ];

                            if (!empty($request->alamat_pelanggan['id'][$index])) {
                                $alamatData['updated_by'] = $this->karyawan;
                                $alamatData['updated_at'] = $timestamp;
                                AlamatPelanggan::where('id', $request->alamat_pelanggan['id'][$index])->update($alamatData);
                            } else {
                                $alamatData['created_by'] = $this->karyawan;
                                $alamatData['created_at'] = $timestamp;
                                AlamatPelanggan::create($alamatData);
                            }
                        }
                    }
                }

                if ($request->has('pic_pelanggan')) {
                    $existingPicIds = PicPelanggan::where('pelanggan_id', $pelanggan->id)->pluck('id')->toArray();
                    $requestPicIds = array_filter($request->pic_pelanggan['id']);

                    // Deactivate pic_pelanggan not present in the request
                    $idsToDeactivate = array_diff($existingPicIds, $requestPicIds);
                    PicPelanggan::whereIn('id', $idsToDeactivate)->update([
                        'is_active' => false,
                        'deleted_by' => $this->karyawan,
                        'deleted_at' => $timestamp
                    ]);

                    foreach ($request->pic_pelanggan['nama_pic'] as $index => $namaPic) {
                        if (!empty($namaPic)) { // Check if not empty
                            $picData = [
                                'pelanggan_id' => $pelanggan->id,
                                'type_pic' => $request->pic_pelanggan['type_pic'][$index],
                                'nama_pic' => $namaPic,
                                'jabatan_pic' => $request->pic_pelanggan['jabatan_pic'][$index],
                                'no_tlp_pic' => $request->pic_pelanggan['no_tlp_pic'][$index],
                                'wa_pic' => $request->pic_pelanggan['wa_pic'][$index],
                                'email_pic' => $request->pic_pelanggan['email_pic'][$index],
                                'is_active' => true
                            ];

                            if (!empty($request->pic_pelanggan['id'][$index])) {
                                $picData['updated_by'] = $this->karyawan;
                                $picData['updated_at'] = $timestamp;
                                PicPelanggan::where('id', $request->pic_pelanggan['id'][$index])->update($picData);
                            } else {
                                $picData['created_by'] = $this->karyawan;
                                $picData['created_at'] = $timestamp;
                                PicPelanggan::create($picData);
                            }
                        }
                    }
                }

            } else {
                $response = $this->checkForBlacklistedCustomer(null, $request->nama_pelanggan, $request->kontak_pelanggan);
                if ($response) return $response;
                
                // Create new customer
                $no_tlp_perusahaan = preg_replace("/[^0-9]/", "", $request->no_tlp_perusahaan); // bersihin non-angka

                if (substr($no_tlp_perusahaan, 0, 2) === "62") { // convert depannya jadi 0
                    $no_tlp_perusahaan = "0" . substr($no_tlp_perusahaan, 2);
                }

                $existingData = MasterPelanggan::where('nama_pelanggan', $request->nama_pelanggan)
                    ->whereHas('kontak_pelanggan', function ($query) use ($no_tlp_perusahaan) {
                        $query->where('no_tlp_perusahaan', $no_tlp_perusahaan);
                    })->where('is_active', true)->first();

                if ($existingData) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Pelanggan dengan nama dan atau nomor kontak sudah ada.'
                    ], 401);
                }

                $dataPelanggan = $request->only([
                    'nama_pelanggan',
                    'wilayah',
                    'sub_kategori',
                    'npwp',
                    'bahan_pelanggan',
                    'merk_pelanggan',
                    'sales_penanggung_jawab',
                ]);

                $sales_id = MasterKaryawan::where('nama_lengkap', $request->sales_penanggung_jawab)->first()->id;
                $dataPelanggan['sales_id'] = $sales_id;

                // Generate no_urut
                $lastPelanggan = MasterPelanggan::where('id_cabang', $this->idcabang)->orderBy('no_urut', 'desc')->first();
                $noUrut = $lastPelanggan ? (int) $lastPelanggan->no_urut + 1 : 1;
                $dataPelanggan['no_urut'] = str_pad($noUrut, 5, '0', STR_PAD_LEFT);

                // Generate id_pelanggan
                $namaPelangganUpper = strtoupper(str_replace([' ', '\t', ','], '', $dataPelanggan['nama_pelanggan']));
                $idPelanggan = null;
                for ($i = 1; $i <= 10; $i++) {
                    $generatedId = $this->randomstr($namaPelangganUpper, $i);
                    if (!MasterPelanggan::where('id_pelanggan', $generatedId)->exists()) {
                        $idPelanggan = $generatedId;
                        break;
                    }
                }

                if ($idPelanggan === null) {
                    DB::rollback();
                    return response()->json(['message' => 'Gagal menghasilkan id_pelanggan yang unik'], 500);
                }

                $dataPelanggan['id_pelanggan'] = $idPelanggan;

                // Check for existing customer
                $existingPelanggan = MasterPelanggan::where('nama_pelanggan', $dataPelanggan['nama_pelanggan'])
                    ->whereHas('kontak_pelanggan', function ($query) use ($request) {
                        $query->whereIn('no_tlp_perusahaan', $request->kontak_pelanggan['no_tlp_perusahaan'])
                            ->whereIn('email_perusahaan', $request->kontak_pelanggan['email_perusahaan']);
                    })
                    ->whereHas('alamat_pelanggan', function ($query) use ($request) {
                        $query->whereIn('alamat', $request->alamat_pelanggan['alamat']);
                    })
                    ->where('is_active', true)
                    ->first();

                if ($existingPelanggan) {
                    DB::rollback();
                    return response()->json(['message' => 'Pelanggan dengan data yang sama sudah ada'], 400);
                }

                if ($request->kategori_pelanggan != '') $dataPelanggan['kategori_pelanggan'] = $request->kategori_pelanggan;
                $dataPelanggan['id_cabang'] = $this->idcabang;
                $dataPelanggan['created_by'] = $this->karyawan;
                $dataPelanggan['created_at'] = DATE('Y-m-d H:i:s');

                $pelanggan = MasterPelanggan::create($dataPelanggan);

                if ($request->has('kontak_pelanggan')) {
                    foreach ($request->kontak_pelanggan['no_tlp_perusahaan'] as $index => $noTlp) {
                        if (!empty($noTlp)) { // Check if not empty
                            $noTlp = preg_replace("/[^0-9]/", "", $noTlp); // bersihin non-angka

                            if (substr($noTlp, 0, 2) === "62") { // convert depannya jadi 0
                                $noTlp = "0" . substr($noTlp, 2);
                            }
                            // cek noTlp
                            $sameTelNumber = KontakPelanggan::where('no_tlp_perusahaan', $noTlp)->exists();
                            if ($sameTelNumber) {
                                DB::rollback();
                                return response()->json(['message' => 'Nomor telepon perusahaan sudah ada'], 400);
                            }

                            $kontak = [
                                'pelanggan_id' => $pelanggan->id,
                                'no_tlp_perusahaan' => $noTlp,
                                'email_perusahaan' => $request->kontak_pelanggan['email_perusahaan'][$index],
                                'created_by' => $this->karyawan,
                                'created_at' => $timestamp,
                                'is_active' => true
                            ];
                            KontakPelanggan::create($kontak);
                        }
                    }
                }

                if ($request->has('alamat_pelanggan')) {
                    foreach ($request->alamat_pelanggan['alamat'] as $index => $alamat) {
                        if (!empty($alamat)) { // Check if not empty
                            $alamatData = [
                                'pelanggan_id' => $pelanggan->id,
                                'type_alamat' => $request->alamat_pelanggan['type_alamat'][$index],
                                'alamat' => $alamat,
                                'created_by' => $this->karyawan,
                                'created_at' => $timestamp,
                                'is_active' => true
                            ];
                            AlamatPelanggan::create($alamatData);
                        }
                    }
                }

                if ($request->has('pic_pelanggan')) {
                    foreach ($request->pic_pelanggan['nama_pic'] as $index => $namaPic) {
                        if (!empty($namaPic)) { // Check if not empty
                            $picData = [
                                'pelanggan_id' => $pelanggan->id,
                                'type_pic' => $request->pic_pelanggan['type_pic'][$index],
                                'nama_pic' => $namaPic,
                                'jabatan_pic' => $request->pic_pelanggan['jabatan_pic'][$index],
                                'no_tlp_pic' => $request->pic_pelanggan['no_tlp_pic'][$index],
                                'wa_pic' => $request->pic_pelanggan['wa_pic'][$index],
                                'email_pic' => $request->pic_pelanggan['email_pic'][$index],
                                'created_by' => $this->karyawan,
                                'created_at' => $timestamp,
                                'is_active' => true
                            ];
                            PicPelanggan::create($picData);
                        }
                    }
                }
            }

            DB::commit();
            return response()->json(['message' => 'Data berhasil disimpan']);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function randomstr($str, $no)
    {
        // $str = str_replace([' ', '\t', ','], '', $str);
        $str = preg_replace('/[^A-Z]/', '', $str); // perbaikan oleh afryan 2025-04-15
        $result = substr(str_shuffle($str), 0, 4) . sprintf("%02d", $no);
        return $result;
    }

    public function delete(Request $request)
    {
        $pelanggan = MasterPelanggan::find($request->id);
        if ($pelanggan) {
            $pelanggan->deleted_by = $this->karyawan;
            $pelanggan->deleted_at = Date('Y-m-d H:i:s');
            $pelanggan->is_active = false;
            $pelanggan->save();
            return response()->json(['message' => 'Data pelanggan berhasil dinonaktifkan']);
        }

        return response()->json(['message' => 'Data pelanggan tidak ditemukan'], 404);
    }

    public function getWilayah(Request $request)
    {
        $wilayah = HargaTransportasi::where('is_active', true)->select('id', 'wilayah')->get();
        return response()->json([
            'message' => 'Data wilayah berhasil ditampilkan',
            'data' => $wilayah
        ], 200);
    }

    public function getSales(Request $request)
    {
        $data = MasterKaryawan::where('is_active', true)->select('id', 'nama_lengkap')->get();
        return response()->json([
            'message' => 'Data Sales berhasil ditampilkan',
            'data' => $data
        ], 200);
    }

    public function blacklist(Request $request) 
    {
        DB::beginTransaction();
        try {
            // Master Pelanggan
            $masterPelanggan = MasterPelanggan::find($request->id);
            if (!$masterPelanggan) return response()->json(['message' => 'Pelanggan tidak ditemukan'], 404);

            $blacklist = new PelangganBlacklist();
            $blacklist->id_pelanggan = $masterPelanggan->id;
            $blacklist->alasan_blacklist = $request->alasan;
            $blacklist->blacklisted_by = $this->karyawan;
            $blacklist->blacklisted_at = Carbon::now();
            $blacklist->save();
    
            $a = $masterPelanggan->replicate();
            $a->setTable((new MasterPelangganBlacklist())->getTable());
            $a->id = $masterPelanggan->id;
            $a->save();

            // Kontak Pelanggan
            $kontakPelanggan = KontakPelanggan::where('pelanggan_id', $masterPelanggan->id)->get();
            foreach ($kontakPelanggan as $kp) {
                $b = $kp->replicate();
                $b->setTable((new KontakPelangganBlacklist())->getTable());
                $b->id = $kp->id;
                $b->save();
            }

            // PIC Pelanggan
            $picPelanggan = PicPelanggan::where('pelanggan_id', $masterPelanggan->id)->get();
            foreach ($picPelanggan as $pp) {
                $c = $pp->replicate();
                $c->setTable((new PicPelangganBlacklist())->getTable());
                $c->id = $pp->id;
                $c->save();
            }

            // Alamat Pelanggan
            $alamatPelanggan = AlamatPelanggan::where('pelanggan_id', $masterPelanggan->id)->get();
            foreach ($alamatPelanggan as $ap) {
                $d = $ap->replicate();
                $d->setTable((new AlamatPelangganBlacklist())->getTable());
                $d->id = $ap->id;
                $d->save();
            }

            $masterPelanggan->delete();

            DB::commit();
    
            return response()->json(['message' => 'Pelanggan berhasil diblacklist'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function exportExcel()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', 'MASTER PELANGGAN PT INTI SURYA LABORATORIUM');

        $titleStyle = [
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];
        $sheet->getStyle('A1')->applyFromArray($titleStyle);
        $sheet->getRowDimension(1)->setRowHeight(50);

        $headers = [
            'No.', 'ID Pelanggan', 'NPWP', 'Nama Pelanggan', 
            'Kontak Pelanggan', 'Status', 'Wilayah Pelanggan', 'Sales Penanggung Jawab'
        ];
        
        $sheet->fromArray($headers, NULL, 'A2');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4A4A4A']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];
        $sheet->getStyle('A2:H2')->applyFromArray($headerStyle);
        $sheet->getRowDimension(2)->setRowHeight(25);

        $row = 3; 
        $no = 1;

        MasterPelanggan::with(['kontak_pelanggan', 'order_customer'])
            ->where('sales_id', '!=', 127)
            ->whereNotNull('sales_id')
            ->where('is_active', true)
            ->chunk(1000, function ($customers) use ($sheet, &$row, &$no) {
                foreach ($customers as $customer) {
                    $contacts = $customer->kontak_pelanggan->pluck('no_tlp_perusahaan')
                        ->map(function ($item) {
                            $tel = preg_replace('/[^\d]/', '', $item); // Hapus semua karakter selain angka

                            // Standarisasi ke format '08/02...'
                            if (substr($tel, 0, 2) === '62') {
                                $tel = '0' . substr($tel, 2);
                            } elseif (substr($tel, 0, 1) !== '0') {
                                $tel = '0' . $tel;
                            }

                            return $tel;
                        })
                        ->filter()
                        ->implode(', ');

                    $sheet->setCellValue('A' . $row, $no++); 
                    $sheet->setCellValue('B' . $row, $customer->id_pelanggan);
                    $sheet->setCellValue('C' . $row, $customer->npwp);
                    $sheet->setCellValue('D' . $row, trim($customer->nama_pelanggan));
                    $sheet->setCellValueExplicit('E' . $row, $contacts ?: '', DataType::TYPE_STRING);
                    $sheet->setCellValue('F' . $row, $customer->order_customer->isNotEmpty() ? 'ORDERED' : 'NEW');
                    $sheet->setCellValue('G' . $row, $customer->wilayah);
                    $sheet->setCellValue('H' . $row, $customer->sales_penanggung_jawab);

                    $row++;
                }

                unset($customers);
            });

        $lastRow = $row - 1;

        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ];
        $sheet->getStyle('A2:H' . $lastRow)->applyFromArray($borderStyle);

        $sheet->getStyle('A3:B' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F3:F' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A2:H' . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        
        $sheet->getStyle('E3:E' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        foreach (range('A', 'H') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        
        $sheet->getColumnDimension('D')->setAutoSize(false)->setWidth(60); 
        $sheet->getColumnDimension('E')->setAutoSize(false)->setWidth(40);
        $sheet->getColumnDimension('G')->setAutoSize(false)->setWidth(30);

        $writer = new Xlsx($spreadsheet);
        $fileName = 'export_pelanggan_' . date('Y-m-d_H-i-s') . '.xlsx';
        $folderPath = public_path('master_pelanggan');

        if (!File::exists($folderPath)) {
            File::makeDirectory($folderPath, 0755, true);
        }

        $fullPath = $folderPath . '/' . $fileName;
        $writer->save($fullPath);

        return response()->json([
            'message' => 'Master Pelanggan berhasil diekspor',
            'data' => $fileName,
        ], 201);
    }
}
