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
use Yajra\Datatables\Datatables;

class MasterPelangganController extends Controller
{
    public function index(Request $request)
    {
        $data = MasterPelanggan::with(['kontak_pelanggan', 'alamat_pelanggan', 'pic_pelanggan', 'order_customer'])->where(['is_active' => true]);

        $user = $request->attributes->get('user');
        if (isset($user->karyawan) && $user->karyawan != null) {
            $cek_jabatan = DB::table('master_jabatan')->where('id', $user->karyawan->id_jabatan)->first();
            if ($cek_jabatan->nama_jabatan == 'Sales Staff') {
                $data->where('sales_penanggung_jawab', $user->karyawan->nama_lengkap);
            }

            if ($cek_jabatan->nama_jabatan == 'Sales Supervisor') {
                $cek_bawahan = MasterKaryawan::where('is_active', true)->whereJsonContains('atasan_langsung', (string) $this->user_id)->pluck('nama_lengkap')->toArray();
                $data->whereIn('sales_penanggung_jawab', $cek_bawahan);
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
            ->make(true);
    }

    public function historyPenawaranCustomer(Request $request)
    {
        $data = MasterPelanggan::with(['kontak_pelanggan', 'alamat_pelanggan', 'pic_pelanggan'])
            ->where('is_active', true);

        $user = $request->attributes->get('user');
        if (isset($user->karyawan) && $user->karyawan != null) {
            $cek_jabatan = DB::table('master_jabatan')->where('id', $user->karyawan->id_jabatan)->first();
            if ($cek_jabatan->nama_jabatan == 'Sales Staff') {
                $data->where('sales_penanggung_jawab', $user->karyawan->nama_lengkap);
            }

            if ($cek_jabatan->nama_jabatan == 'Sales Supervisor') {
                $cek_bawahan = MasterKaryawan::where('is_active', true)->whereJsonContains('atasan_langsung', (string) $this->user_id)->pluck('nama_lengkap')->toArray();
                $data->whereIn('sales_penanggung_jawab', $cek_bawahan);
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

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $timestamp = DATE('Y-m-d H:i:s');

            if ($request->id != '') {
                // Update existing customer
                $pelanggan = MasterPelanggan::find($request->id);

                if (!$pelanggan) {
                    return response()->json(['message' => 'Pelanggan tidak ditemukan'], 404);
                }

                $dataPelanggan = $request->only([
                    'nama_pelanggan',
                    'wilayah',
                    'sub_kategori',
                    'bahan_pelanggan',
                    'merk_pelanggan',
                    'sales_penanggung_jawab',
                ]);

                // Tamabahan Patah
                $sales_id = MasterKaryawan::where('nama_lengkap', $request->sales_penanggung_jawab)->first()->id;
                $dataPelanggan['sales_id'] = $sales_id;
                /* Update menambahkan trim by 565 : 2025-05-08*/
                $dataPelanggan['nama_pelanggan'] = trim($dataPelanggan['nama_pelanggan']);
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
                            $sameTelNumber = KontakPelanggan::where('no_tlp_perusahaan', $noTlp)->first();
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
                // Create new customer
                $dataPelanggan = $request->only([
                    'nama_pelanggan',
                    'wilayah',
                    'sub_kategori',
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
                    ->first();

                if ($existingPelanggan) {
                    DB::rollback();
                    return response()->json(['message' => 'Pelanggan dengan data yang sama sudah ada'], 400);
                }
                if ($request->kategori_pelanggan != '')
                    $dataPelanggan['kategori_pelanggan'] = $request->kategori_pelanggan;
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
}
