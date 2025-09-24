<?php

namespace App\Http\Controllers\api;


use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

use Yajra\Datatables\Datatables;

use App\Models\MasterPelanggan;
use App\Models\KontakPelanggan;
use App\Models\AlamatPelanggan;
use App\Models\PicPelanggan;
use App\Models\MasterKaryawan;
use App\Models\HargaTransportasi;

date_default_timezone_set('Asia/Jakarta');

class DraftCustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = MasterPelanggan::with(['kontak_pelanggan', 'alamat_pelanggan', 'pic_pelanggan', 'pj_draft'])
            ->where([
                'is_draft' => true,
                'is_active' => true
            ]);

        $karyawan = $request->attributes->get('user')->karyawan;
        if ($karyawan->id_jabatan == 15) { // MANAGER
            $customers->where('pj_draft_id', $karyawan->id);
        } elseif ($karyawan->id_jabatan == 21) { // SPV
            $atasan = json_decode($karyawan->atasan_langsung);
            $customers->whereIn('pj_draft_id', $atasan);
        }

        return Datatables::of($customers)->make(true);
    }

    public function store(Request $request)
    {
        DB::transaction(function () use ($request) {
            $timestamp = DATE('Y-m-d H:i:s');

            if ($request->id) {
                // Update existing customer
                $pelanggan = MasterPelanggan::find($request->id);

                if (!$pelanggan) return response()->json(['message' => 'Pelanggan tidak ditemukan'], 404);

                $dataPelanggan = $request->only([
                    'nama_pelanggan',
                    'wilayah',
                    'sub_kategori',
                    'bahan_pelanggan',
                    'merk_pelanggan',
                    'pj_draft_id',
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
                $dataPelanggan['is_draft'] = true;
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
                // Create new customer
                $dataPelanggan = $request->only([
                    'nama_pelanggan',
                    'wilayah',
                    'sub_kategori',
                    'bahan_pelanggan',
                    'merk_pelanggan',
                    'pj_draft_id'
                ]);

                $lastPelanggan = MasterPelanggan::where('id_cabang', $this->idcabang)->orderBy('no_urut', 'desc')->first();
                $noUrut = $lastPelanggan ? (int) $lastPelanggan->no_urut + 1 : 1;
                $dataPelanggan['no_urut'] = str_pad($noUrut, 5, '0', STR_PAD_LEFT);

                $namaPelangganUpper = strtoupper(str_replace([' ', '\t', ','], '', $dataPelanggan['nama_pelanggan']));
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

                $existingPelanggan = MasterPelanggan::where('nama_pelanggan', $dataPelanggan['nama_pelanggan'])
                    ->whereHas('kontak_pelanggan', function ($query) use ($request) {
                        $query->whereIn('no_tlp_perusahaan', $request->kontak_pelanggan['no_tlp_perusahaan'])
                            ->whereIn('email_perusahaan', $request->kontak_pelanggan['email_perusahaan']);
                    })
                    ->whereHas('alamat_pelanggan', function ($query) use ($request) {
                        $query->whereIn('alamat', $request->alamat_pelanggan['alamat']);
                    })->first();

                if ($existingPelanggan) return response()->json(['message' => 'Pelanggan dengan data yang sama sudah ada'], 409);

                if ($request->kategori_pelanggan) $dataPelanggan['kategori_pelanggan'] = $request->kategori_pelanggan;
                $dataPelanggan['id_cabang'] = $this->idcabang;
                $dataPelanggan['created_by'] = $this->karyawan;
                $dataPelanggan['created_at'] = DATE('Y-m-d H:i:s');
                $dataPelanggan['is_draft'] = true;

                $pelanggan = MasterPelanggan::create($dataPelanggan);

                if ($request->has('kontak_pelanggan')) {
                    foreach ($request->kontak_pelanggan['no_tlp_perusahaan'] as $index => $noTlp) {
                        if ($noTlp) {
                            // cek noTlp
                            $sameTelNumber = KontakPelanggan::where('no_tlp_perusahaan', $noTlp)->exists();
                            if ($sameTelNumber) return response()->json(['message' => 'Nomor telepon perusahaan sudah ada'], 409);

                            KontakPelanggan::create([
                                'pelanggan_id' => $pelanggan->id,
                                'no_tlp_perusahaan' => $noTlp,
                                'email_perusahaan' => $request->kontak_pelanggan['email_perusahaan'][$index],
                                'created_by' => $this->karyawan,
                                'created_at' => $timestamp,
                                'is_active' => true
                            ]);
                        }
                    }
                }

                if ($request->has('alamat_pelanggan')) {
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

                if ($request->has('pic_pelanggan')) {
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
            }
        });

        return response()->json(['message' => 'Data berhasil disimpan']);
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

    public function getSales()
    {
        $data = MasterKaryawan::where([
            'id_jabatan' => 15,
            'is_active' => true
        ])->select('id', 'nama_lengkap')->get();

        return response()->json([
            'message' => 'Data Sales berhasil ditampilkan',
            'data' => $data
        ], 200);
    }

    public function generate(Request $request)
    {
        DB::beginTransaction();
        try {
            $karyawan = $request->attributes->get('user')->karyawan;
            if (!in_array($karyawan->id_jabatan, [15, 21])) {
                return response()->json(['message' => 'You don\'t have permission to perform this action'], 403);
            }

            $customers = MasterPelanggan::with(['kontak_pelanggan', 'alamat_pelanggan', 'pic_pelanggan', 'pj_draft'])
                ->where(['is_draft' => true, 'is_active' => true]);

            $sales = MasterKaryawan::where(['id_jabatan' => 24, 'is_active' => true]);

            if ($karyawan->id_jabatan == 15) { // MANAGER
                $customers->where('pj_draft_id', $karyawan->id);

                $sales->whereJsonContains('atasan_langsung', (string) $karyawan->id);
            } elseif ($karyawan->id_jabatan == 21) { // SPV
                $atasan = json_decode($karyawan->atasan_langsung);
                $customers->whereIn('pj_draft_id', $atasan);

                $sales->whereJsonContains('atasan_langsung', $atasan);
            }

            $customers = $customers->get();
            $sales = $sales->get();

            foreach ($customers as $customer) {
                $randomSales = $sales->random();

                $customer->update([
                    'sales_id' => $randomSales->id,
                    'sales_penanggung_jawab' => $randomSales->nama_lengkap,
                    'is_draft' => false,
                    'pj_draft_id' => null,
                    'updated_by' => $this->karyawan,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
            DB::commit();

            return response()->json(['message' => 'Sales has been generated successfully.', 'statusCode' => 200]);
        } catch (\Throwable $th) {
            DB::rollback();
            // dd($th);
            return response()->json(['message' => 'Failed to generating Sales.', 'statusCode' => 500]);
        }
    }
}
