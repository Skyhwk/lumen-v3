<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

use Datatables;

use App\Models\MasterPelanggan;
use App\Models\KontakPelanggan;
use App\Models\AlamatPelanggan;
use App\Models\PicPelanggan;
use App\Models\MasterKaryawan;
use App\Models\HargaTransportasi;
use App\Models\HistoryPerubahanSales;
use App\Models\KontakPelangganBlacklist;
use App\Models\MasterPelangganBlacklist;

date_default_timezone_set('Asia/Jakarta');

class DraftCustomerController extends Controller
{
    public function index()
    {
        $data = MasterPelanggan::with(['kontak_pelanggan', 'alamat_pelanggan', 'pic_pelanggan'])
            ->where([
                'created_by' => $this->karyawan,
                'is_active' => true
            ])->orderByDesc('id')->limit(5)->get();

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

            if ($request->id) {
                $response = $this->checkForBlacklistedCustomer($request->id, $request->nama_pelanggan, $request->kontak_pelanggan);
                if ($response) return $response;

                // Update existing customer
                $pelanggan = MasterPelanggan::find($request->id);

                if (!$pelanggan) return response()->json(['message' => 'Pelanggan tidak ditemukan'], 404);

                $dataPelanggan = $request->only([
                    'nama_pelanggan',
                    'wilayah',
                    'sub_kategori',
                    'bahan_pelanggan',
                    'merk_pelanggan',
                ]);

                $dataPelanggan['nama_pelanggan'] = trim($dataPelanggan['nama_pelanggan']);

                // cek nama Pelanggan
                if ($pelanggan->nama_pelanggan !== $dataPelanggan['nama_pelanggan']) {
                    $sameCustName = MasterPelanggan::where('nama_pelanggan', $dataPelanggan['nama_pelanggan'])->exists();
                    if ($sameCustName) return response()->json(['message' => 'Nama pelanggan sudah ada'], 409);
                };

                $dataPelanggan['id_cabang'] = $this->idcabang;
                $dataPelanggan['updated_by'] = $this->karyawan;
                $dataPelanggan['updated_at'] = $timestamp;

                if ($request->kategori_pelanggan) $dataPelanggan['kategori_pelanggan'] = $request->kategori_pelanggan;

                $pelanggan->update($dataPelanggan);

                if ($request->kontak_pelanggan) {
                    $existingKontakIds = KontakPelanggan::where('pelanggan_id', $pelanggan->id)->pluck('id')->toArray();
                    $requestKontakIds = array_filter($request->kontak_pelanggan['id']);

                    $idsToDeactivate = array_diff($existingKontakIds, $requestKontakIds);
                    KontakPelanggan::whereIn('id', $idsToDeactivate)->update([
                        'is_active' => false,
                        'deleted_by' => $this->karyawan,
                        'deleted_at' => $timestamp
                    ]);

                    foreach ($request->kontak_pelanggan['no_tlp_perusahaan'] as $index => $noTlp) {
                        if ($noTlp) {
                            $noTlp = preg_replace("/[^0-9]/", "", $noTlp); // bersihin non-angka

                            if (substr($noTlp, 0, 2) === "62") { // convert depannya jadi 0
                                $noTlp = "0" . substr($noTlp, 2);
                            }
                            // cek noTlp
                            $sameTelNumber = KontakPelanggan::where('no_tlp_perusahaan', $noTlp)->first();
                            if ($sameTelNumber && $sameTelNumber->pelanggan_id !== $pelanggan->id) {
                                return response()->json(['message' => 'Nomor telepon perusahaan sudah ada'], 409);
                            };

                            $kontak = [
                                'pelanggan_id' => $pelanggan->id,
                                'no_tlp_perusahaan' => $noTlp,
                                'email_perusahaan' => $request->kontak_pelanggan['email_perusahaan'][$index],
                                'is_active' => true
                            ];

                            if ($request->kontak_pelanggan['id'][$index]) {
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

                if ($request->alamat_pelanggan) {
                    $existingAlamatIds = AlamatPelanggan::where('pelanggan_id', $pelanggan->id)->pluck('id')->toArray();
                    $requestAlamatIds = array_filter($request->alamat_pelanggan['id']);

                    $idsToDeactivate = array_diff($existingAlamatIds, $requestAlamatIds);
                    AlamatPelanggan::whereIn('id', $idsToDeactivate)->update([
                        'is_active' => false,
                        'deleted_by' => $this->karyawan,
                        'deleted_at' => $timestamp
                    ]);

                    foreach ($request->alamat_pelanggan['alamat'] as $index => $alamat) {
                        if ($alamat) {
                            $alamatData = [
                                'pelanggan_id' => $pelanggan->id,
                                'type_alamat' => $request->alamat_pelanggan['type_alamat'][$index],
                                'alamat' => $alamat,
                                'is_active' => true
                            ];

                            if ($request->alamat_pelanggan['id'][$index]) {
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

                if ($request->pic_pelanggan) {
                    $existingPicIds = PicPelanggan::where('pelanggan_id', $pelanggan->id)->pluck('id')->toArray();
                    $requestPicIds = array_filter($request->pic_pelanggan['id']);

                    $idsToDeactivate = array_diff($existingPicIds, $requestPicIds);
                    PicPelanggan::whereIn('id', $idsToDeactivate)->update([
                        'is_active' => false,
                        'deleted_by' => $this->karyawan,
                        'deleted_at' => $timestamp
                    ]);

                    foreach ($request->pic_pelanggan['nama_pic'] as $index => $namaPic) {
                        if ($namaPic) {
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

                            if ($request->pic_pelanggan['id'][$index]) {
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
                $dataPelanggan = $request->only([
                    'nama_pelanggan',
                    'wilayah',
                    'sub_kategori',
                    'bahan_pelanggan',
                    'merk_pelanggan',
                ]);

                
                $lastPelanggan = MasterPelanggan::where('id_cabang', $this->idcabang)->orderBy('no_urut', 'desc')->first();
                $noUrut = $lastPelanggan ? (int) $lastPelanggan->no_urut + 1 : 1;
                $dataPelanggan['no_urut'] = str_pad($noUrut, 5, '0', STR_PAD_LEFT);
                
                $namaPelangganUpper = strtoupper(str_replace([' ', '\t', ','], '', $dataPelanggan['nama_pelanggan']));
                $clearNamaPelanggan = preg_replace('/(,?\s*\.?\s*(PT|CV|UD|PD|KOPERASI|PERUM|PERSERO|BUMD|YAYASAN))\s*$/i', '', $namaPelangganUpper);
                $idPelanggan = null;
                for ($i = 1; $i <= 10; $i++) {
                    $generatedId = $this->randomstr($namaPelangganUpper, $i);
                    if (!MasterPelanggan::where('id_pelanggan', $generatedId)->exists()) {
                        $idPelanggan = $generatedId;
                        break;
                    }
                }

                if (!$idPelanggan) return response()->json(['message' => 'Gagal menghasilkan id_pelanggan yang unik'], 500);

                $dataPelanggan['id_pelanggan'] = $idPelanggan;

                $noTelpList = array_filter($request->kontak_pelanggan['no_tlp_perusahaan'] ?? []);
                $alamatList = array_filter($request->alamat_pelanggan['alamat'] ?? []);

                $existingPelanggan = MasterPelanggan::where('nama_pelanggan', $dataPelanggan['nama_pelanggan'])
                    ->orWhereHas('kontak_pelanggan', function ($query) use ($noTelpList) {
                        $query->whereIn('no_tlp_perusahaan', $noTelpList);
                    })
                    ->orWhereHas('alamat_pelanggan', function ($query) use ($alamatList) {
                        $query->whereIn('alamat', $alamatList);
                    })
                    ->first();

                if ($existingPelanggan) return response()->json(['message' => 'Pelanggan dengan data yang sama sudah ada'], 400);

                if ($request->kategori_pelanggan) $dataPelanggan['kategori_pelanggan'] = $request->kategori_pelanggan;
                $dataPelanggan['id_cabang'] = $this->idcabang;
                $dataPelanggan['created_by'] = $this->karyawan;
                $dataPelanggan['created_at'] = DATE('Y-m-d H:i:s');


                // RANDOMIZE SALES
                $sales = MasterKaryawan::where([
                    'id_jabatan' => 24,
                    'is_active' => true
                ])->get();

                $salesWithTodayCount = $sales->map(function ($salesman) {
                    $salesman->today_customer_count = MasterPelanggan::whereDate('created_at', date('Y-m-d'))
                        ->where('sales_id', $salesman->id)
                        ->count();

                    return $salesman;
                });

                $minToday = $salesWithTodayCount->min('today_customer_count');

                $availableSales = $salesWithTodayCount->filter(function ($salesman) use ($minToday) {
                    return $salesman->today_customer_count == $minToday;
                });

                // Random dari yang available
                $selectedSales = $availableSales->random();

                // BUAT KE BANK DATA DULU (CEK APAKAH SUDAH FULL BANK DATA, KALAU YA SET NULL)
                $currentBankData = MasterPelanggan::whereNull('sales_penanggung_jawab')->whereNull('sales_id')->where('is_active', true)->count();

                if($currentBankData > 5000) {
                    $dataPelanggan['sales_id'] = $selectedSales->id;
                    $dataPelanggan['sales_penanggung_jawab'] = $selectedSales->nama_lengkap;
                } else {
                    $dataPelanggan['sales_id'] = null;
                    $dataPelanggan['sales_penanggung_jawab'] = null;
                }
                // END RANDOMIZE SALES

                $existingPelanggan = MasterPelanggan::where('nama_pelanggan', 'like', '%' . $clearNamaPelanggan . '%')->where('is_active', true)->first();

                if ($existingPelanggan) return response()->json(['message' => 'Pelanggan dengan data yang sama sudah ada'], 400);

                $pelanggan = MasterPelanggan::create($dataPelanggan);
                if (!$request->kontak_pelanggan['no_tlp_perusahaan'][0]) return response()->json(['message' => 'Nomor telepon perusahaan harus diisi'], 400);

                foreach ($request->kontak_pelanggan['no_tlp_perusahaan'] as $index => $noTlp) {
                    $noTlp = preg_replace("/[^0-9]/", "", $noTlp); // bersihin non-angka

                    if (substr($noTlp, 0, 2) === "62") { // convert depannya jadi 0
                        $noTlp = "0" . substr($noTlp, 2);
                    }
                    // cek noTlp
                    $sameTelNumber = KontakPelanggan::where('no_tlp_perusahaan', $noTlp)->exists();
                    if ($sameTelNumber) return response()->json(['message' => 'Nomor telepon perusahaan sudah ada'], 400);

                    KontakPelanggan::create([
                        'pelanggan_id' => $pelanggan->id,
                        'no_tlp_perusahaan' => $noTlp,
                        'email_perusahaan' => $request->kontak_pelanggan['email_perusahaan'][$index],
                        'created_by' => $this->karyawan,
                        'created_at' => $timestamp,
                        'is_active' => true
                    ]);
                }

                if ($request->alamat_pelanggan) {
                    foreach ($request->alamat_pelanggan['alamat'] as $index => $alamat) {
                        if ($alamat) {
                            AlamatPelanggan::create([
                                'pelanggan_id' => $pelanggan->id,
                                'type_alamat' => $request->alamat_pelanggan['type_alamat'][$index],
                                'alamat' => $alamat,
                                'created_by' => $this->karyawan,
                                'created_at' => $timestamp,
                                'is_active' => true
                            ]);
                        }
                    }
                }

                if ($request->pic_pelanggan) {
                    foreach ($request->pic_pelanggan['nama_pic'] as $index => $namaPic) {
                        if ($namaPic) {
                            PicPelanggan::create([
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
                            ]);
                        }
                    }
                }

                $historySales = new HistoryPerubahanSales();
                $historySales->id_pelanggan = $pelanggan->id_pelanggan;
                $historySales->id_sales_lama = null;
                $historySales->id_sales_baru = $pelanggan->sales_id;
                $historySales->tanggal_rotasi = date('Y-m-d H:i:s');
                $historySales->save();
            }

            DB::commit();
            return response()->json(['message' => 'Data berhasil disimpan']);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json(['message' => 'Data gagal disimpan'], 500);
        };
    }

    public function randomstr($str, $no)
    {
        $str = preg_replace('/[^A-Z]/', '', $str);
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

    public function getWilayah()
    {
        $wilayah = HargaTransportasi::where('is_active', true)->select('id', 'wilayah')->get();
        return response()->json([
            'message' => 'Data wilayah berhasil ditampilkan',
            'data' => $wilayah
        ], 200);
    }

    // public function getSales()
    // {
    //     $data = MasterKaryawan::where([
    //         'id_jabatan' => 24,
    //         'is_active' => true
    //     ])->get();

    //     return response()->json([
    //         'message' => 'Data Sales berhasil ditampilkan',
    //         'data' => $data
    //     ], 200);
    // }
}
