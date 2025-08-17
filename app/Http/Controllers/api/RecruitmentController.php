<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\{
    MasterKaryawan,
    Recruitment,
    MasterCabang,
    MasterJabatan,
    SoalPsikotes,
    PapiRole,
    PapiRule,
    RecruitmantExamp,KodeUniqRecruitment};



use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;
use App\Services\Crypto;
use App\Helpers\Helper;
use Carbon\Carbon;


class RecruitmentController extends Controller{

    public function convertFile($foto = '',$p , $user = '')
    {
        $img = str_replace('data:image/jpeg;base64,', '', $foto);
        $file = base64_decode($img);
        $safeName = $user.'_'.$p.'.jpeg';
        $destinationPath = public_path() . '/recruitment/foto/';
        $success = file_put_contents($destinationPath . $safeName, $file);
        return $safeName;
    }

    public function cabang(Request $request) {
        $data = MasterCabang::where('is_active', true)->get();
        return response()->json(['data' => $data]);
    }

    public function posisi(Request $request) {
        $data = MasterJabatan::where('is_active', true)->get();
        return response()->json(['data' => $data]);
    }

    /* 20250721 public function registrasi(Request $request) {
        try {
            $check = Recruitment::where('nik_ktp', '=', $request->nik_ktp)->get();
            $p = 1;
            $month = Carbon::now()->subMonths(6)->format("Y-m-d");

            if ($check->count() != 0) {
                $cek = Recruitment::where('nik_ktp', $request->nik_ktp)->latest('created_at')->whereDate('created_at', '>', $month)->get();
                $count = $cek->count();
                $p = $check->count();
            } else {
                $count = 0;
            }

            if ($count != 0) {
                return response()->json([
                    'message'=> 'Ada data pendaftaran kurang dari 6 bulan!',
                    'status'=> 1,
                ], 401);
            } else {
                // echo $request->nik_ktp;
                $data = new Recruitment;
                if($request->id_cabang != '') $data->id_cabang                          = $request->id_cabang;
                if($request->nama_lengkap != '') $data->nama_lengkap                    = $request->nama_lengkap;
                if($request->nama_panggilan != '') $data->nama_panggilan                = $request->nama_panggilan;
                if($request->email != '') $data->email                                  = $request->email;
                if($request->tempat_lahir != '') $data->tempat_lahir                    = $request->tempat_lahir;
                if($request->umur != '') $data->umur                                    = $request->umur;
                if($request->gender != '') $data->gender                                = $request->gender;
                if($request->agama != '') $data->agama                                  = $request->agama;
                if($request->no_hp != '') $data->no_hp                                  = $request->no_hp;
                if($request->status_nikah != '') $data->status_nikah                    = $request->status_nikah;
                if($request->nik_ktp != '') $data->nik_ktp                              = $request->nik_ktp;
                if($request->alamat_ktp != '') $data->alamat_ktp                        = $request->alamat_ktp;
                if($request->alamat_domisili != '') $data->alamat_domisili              = $request->alamat_domisili;
                if($request->posisi_di_lamar != '') $data->posisi_di_lamar              = $request->posisi_di_lamar;
                if($request->salary_user != '') $data->salary_user                      = $request->salary_user;
                if($request->bpjs_kesehatan != '') $data->bpjs_kesehatan                = $request->bpjs_kesehatan;
                if($request->bpjs_ketenagakerjaan != '') $data->bpjs_ketenagakerjaan    = $request->bpjs_ketenagakerjaan;
                if($request->referensi != '') $data->referensi                          = $request->referensi;
                if($request->tanggal_lahir != '') $data->tanggal_lahir                  = $request->tanggal_lahir;
                if($request->pendidikan != '') $data->pendidikan                        = $request->pendidikan;
                if($request->pengalaman_kerja != '') $data->pengalaman_kerja            = $request->pengalaman_kerja;
                if($request->skill != '') $data->skill                                  = $request->skill;
                if($request->minat != '') $data->minat                                  = $request->minat;
                if($request->skill_bahasa != '') $data->skill_bahasa                    = $request->skill_bahasa;
                if($request->organisasi != '') $data->organisasi                        = $request->organisasi;
                if($request->sertifikat != '') $data->sertifikat                        = $request->sertifikat;
                if($request->kursus != '') $data->kursus                                = $request->kursus;
                if($request->shio != '') $data->shio                                    = $request->shio;
                if($request->elemen != '') $data->elemen                                = $request->elemen;
                if($request->orang_dalam != '') $data->orang_dalam                      = $request->orang_dalam;
                if($request->bagian_di_lamar != '') $data->bagian_di_lamar              = $request->bagian_di_lamar;
                if ($request->foto_selfie != '') $data->foto_selfie                     = self::convertFile($request->foto_selfie, $p, $request->nik_ktp);
                $data->status                                                           = 'KANDIDAT';
                $data->created_at                                                       = DATE('Y-m-d H:i:s');
                $data->save();

                return response()->json([
                    'message'=> 'Berhasil mendaftar',
                    'status'=> 0,
                ], 200);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'message'=> $th->getMessage(),
                'line'=> $th->getLine(),
            ], 200);
        }
    } */
   public function registrasi(Request $request) 
   {

        try {
            $code = Helper::generateUniqueCode('recruitment','kode_uniq',5);
            
            $check = Recruitment::where('nik_ktp', '=', $request->nik_ktp)->get();
            $p = 1;
            $month = Carbon::now()->subMonths(6)->format("Y-m-d");

            if ($check->count() != 0) {
                $cek = Recruitment::where('nik_ktp', $request->nik_ktp)->latest('created_at')->whereDate('created_at', '>', $month)->get();
                $count = $cek->count();
                $p = $check->count();
            } else {
                $count = 0;
            }

            if ($count != 0) {
                return response()->json([
                    'message' => 'Anda telah mendaftar sebelumnya. Anda dapat mengajukan pendaftaran kembali setelah 6 bulan sejak pendaftaran terakhir.',
                    'status' => 1, // Perhatikan: Status 1 biasanya untuk "sukses". Untuk error/warning, lebih cocok 0 atau status kode HTTP 4xx
                ], 401);
            } else {
                $data = new Recruitment;
                if($request->id_cabang != '') $data->id_cabang                          = $request->id_cabang;
                if($request->nama_lengkap != '') $data->nama_lengkap                    = $request->nama_lengkap;
                if($request->nama_panggilan != '') $data->nama_panggilan                = $request->nama_panggilan;
                if($request->email != '') $data->email                                  = $request->email;
                if($request->tempat_lahir != '') $data->tempat_lahir                    = $request->tempat_lahir;
                if($request->umur != '') $data->umur                                    = $request->umur;
                if($request->gender != '') $data->gender                                = $request->gender;
                if($request->agama != '') $data->agama                                  = $request->agama;
                if($request->no_hp != '') $data->no_hp                                  = $request->no_hp;
                if($request->status_nikah != '') $data->status_nikah                    = $request->status_nikah;
                if($request->nik_ktp != '') $data->nik_ktp                              = $request->nik_ktp;
                if($request->alamat_ktp != '') $data->alamat_ktp                        = $request->alamat_ktp;
                if($request->alamat_domisili != '') $data->alamat_domisili              = $request->alamat_domisili;
                if($request->posisi_di_lamar != '') $data->posisi_di_lamar              = $request->posisi_di_lamar;
                if($request->salary_user != '') $data->salary_user                      = $request->salary_user;
                if($request->bpjs_kesehatan != '') $data->bpjs_kesehatan                = $request->bpjs_kesehatan;
                if($request->bpjs_ketenagakerjaan != '') $data->bpjs_ketenagakerjaan    = $request->bpjs_ketenagakerjaan;
                if($request->referensi != '') $data->referensi                          = $request->referensi;
                if($request->tanggal_lahir != '') $data->tanggal_lahir                  = $request->tanggal_lahir;
                if($request->pendidikan != '') $data->pendidikan                        = $request->pendidikan;
                if($request->pengalaman_kerja != '') $data->pengalaman_kerja            = $request->pengalaman_kerja;
                if($request->skill != '') $data->skill                                  = $request->skill;
                if($request->minat != '') $data->minat                                  = $request->minat;
                if($request->skill_bahasa != '') $data->skill_bahasa                    = $request->skill_bahasa;
                if($request->organisasi != '') $data->organisasi                        = $request->organisasi;
                if($request->sertifikat != '') $data->sertifikat                        = $request->sertifikat;
                if($request->kursus != '') $data->kursus                                = $request->kursus;
                if($request->shio != '') $data->shio                                    = $request->shio;
                if($request->elemen != '') $data->elemen                                = $request->elemen;
                if($request->orang_dalam != '') $data->orang_dalam                      = $request->orang_dalam;
                if($request->bagian_di_lamar != '') $data->bagian_di_lamar              = $request->bagian_di_lamar;
                if ($request->foto_selfie != '') $data->foto_selfie                     = self::convertFile($request->foto_selfie, $p, $request->nik_ktp);
                $data->status                                                           = 'KANDIDAT';
                $data->created_at                                                       = DATE('Y-m-d H:i:s');
                $data->kode_uniq                                                        = $code;
                $data->save();

                //kode uniq:
                $kodeUniq = new KodeUniqRecruitment;
                $kodeUniq->id_recruitment = $data->id;
                $kodeUniq->kode_uniq = $code;
                $kodeUniq->is_active = true;
                $kodeUniq->created_at = DATE('Y-m-d H:i:s');
                $kodeUniq->save();

                return response()->json([
                    'message'=> 'Berhasil mendaftar',
                    'status'=> true,
                ], 200);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'message'=> $th->getMessage(),
                'line'=> $th->getLine(),
            ], 200);
        }
   }

    public function getKandidat(Request $request) {

        if ($request->nik != null) {

            $data = Recruitment::where('nik_ktp', $request->nik)->where('is_active', 1)->first();

            if(empty($data)) {
                $data = Recruitment::where('nik_ktp', $request->nik)->where('is_active', 1)->first();
                if(empty($data)){
                    $data = [];
                }
            }
            return response()->json([
                'message' => 'Data has been Showed',
                'data' => $data
            ], 201);
        } else {
            return response()->json([
                'message'=> 'Data Not Found!',
                'data'=> NULL,
            ], 401);
        }
    }

    public function updatekAndidatApi(Request $request) {

        try {
            $data = Recruitment::where('id', $request->id)->first();

            if($request->nationality != '')$data->kebangsaan                        = $request->nationality;
            if($request->nama_lengkap != '')$data->nama_lengkap                     = $request->nama_lengkap;
            if($request->salutation != '')$data->nama_panggilan                     = $request->salutation;
            if($request->country != '')$data->negara                                = $request->country;
            if($request->no_hp != '')$data->phone                                   = $request->no_hp;
            if($request->religion != '')$data->agama                                = $request->religion;
            if($request->email != '')$data->email                                   = $request->email;
            if($request->marital_date != '')$data->tgl_nikah                        = $request->marital_date;
            if($request->marital_place != '')$data->tempat_nikah                    = $request->marital_place;
            if($request->id_expdate != '')$data->tgl_exp_identitas                  = $request->id_expdate;
            if($request->province != '')$data->provinsi                             = $request->province;
            if($request->city != '')$data->kota                                     = $request->city;
            if($request->postal_code != '')$data->kode_pos                          = $request->postal_code;
            if($request->tinggi_badan != '')$data->tinggi_badan                     = $request->tinggi_badan;
            if($request->berat_badan != '')$data->berat_badan                       = $request->berat_badan;
            if($request->mata != '')$data->mata                                     = $request->mata;
            if($request->golongan_darah != '')$data->golongan_darah                 = $request->golongan_darah;
            if($request->penyakit_lahir != '')$data->penyakit_bawaan_lahir          = $request->penyakit_lahir;
            if($request->penyakit_kronis != '')$data->penyakit_kronis               = $request->penyakit_kronis;
            if($request->riwayat_kecelakaan != '')$data->riwayat_kecelakaan         = $request->riwayat_kecelakaan;
            if($request->bpjs_kesehatan != '')$data->bpjs_kesehatan                 = $request->bpjs_kesehatan;
            if($request->alamat_ktp != '')$data->alamat_ktp                         = $request->alamat_ktp;
            if($request->alamat_domisili != '')$data->alamat_domisili               = $request->alamat_domisili;
            if($request->bpjs_ketenagakerjaan != '')$data->bpjs_ketenagakerjaan     = $request->bpjs_ketenagakerjaan;
            if($request->orang_dalam != '')$data->orang_dalam                       = $request->orang_dalam;
            if($request->pendidikan != '')$data->pendidikan                         = $request->pendidikan;
            if($request->pengalaman_kerja != '')$data->pengalaman_kerja             = $request->pengalaman_kerja;
            if($request->skill_bahasa != '')$data->skill_bahasa                     = $request->skill_bahasa;
            if($request->skill != '')$data->skill                                   = $request->skill;
            if($request->minat != '')$data->minat                                   = $request->minat;
            if($request->organisasi != '')$data->organisasi                         = $request->organisasi;
            if($request->referensi != '')$data->referensi                           = $request->referensi;
            if($request->sertifikat != '')$data->sertifikat                         = $request->sertifikat;
            if($request->kursus != '')$data->kursus                                 = $request->kursus;
            $data->save();

            return response()->json([
                'message'=> 'Berhasil update data.',
                'status'=> 0,
            ], 200);
        } catch (\Exception $e) {
            //throw $th;
            return response()->json([
                'message' => $e->getMessage(),
                'status' => '401'
            ], 401);
        }
    }

    /*
        default/api/updatekaryawanportal
        defaultApi\recruitmentApi\portalControllerApi@updateKaryawan
    */
    public function updateKaryawan(Request $request)
        {
            if($this->rjsn != 1) {
                Helpers::saveToLogRequest($this->pathinfo, $this->globaldate, $this->param, $this->useragen, $this->resultx, $this->ip, $this->userid);
                return response()->json($this->rjsn, 401);
            } else {

                DB::beginTransaction();
                $jsonData = json_decode($request->input('json_data'), true);
                try {
                    if(isset($jsonData['email']) && $jsonData['email'] != null && $jsonData['email'] != ''){
                        $check = \str_contains($jsonData['email'], '@intilab.com');
                        if(!$check){
                            return response()->json(["message"=>"opps harus menggunakan email perusahaan"],401);
                        }
                    }

                    if(isset($jsonData['idn']) && $jsonData['idn'] != null && $jsonData['idn'] != ""){
                        $nik = preg_replace('/[^a-zA-Z0-9]/','',$jsonData['idn']);
                    }
                    $db_old = date('Y', strtotime ( '-1 year' , strtotime(date('Y-m-d')) ));
                    $table = new MasterKaryawan;
                    $data = $table::with(['emergencyContact','attachment'])
                            ->where('nik', $nik)
                            ->where('email', $jsonData['email'])
                            ->where('active', 0)
                            ->first();

                    if($data){

                        try {
                            $data->nama_lengkap = $jsonData['nama_lengkap'] ?? $data->nama_lengkap;
                            $data->nama_panggilan = $jsonData['salutation'] ?? $data->nama_panggilan;
                            $data->tanggal_lahir = $jsonData['date_birth'] ?? $data->tanggal_lahir;
                            $data->tempat_lahir = $jsonData['birth_place'] ?? $data->tempat_lahir;
                            $data->agama = $jsonData['religion'] ?? $data->agama;
                            $data->kebangsaan = $jsonData['nationality'] ?? $data->kebangsaan;
                            $data->status_pernikahan = (isset($jsonData['marital_status']) && $jsonData['marital_status'] !== '') ? $jsonData['marital_status'] : $data->status_pernikahan;
                            $data->tgl_nikah = (!empty($jsonData['marital_date'])) ? $jsonData['marital_date'] : $data->tgl_nikah;
                            $data->tempat_nikah = $jsonData['marital_place'] ?? $data->tempat_nikah;
                            $data->alamat = $jsonData['address'] ?? $data->alamat;
                            $data->no_telpon = $jsonData['phone'] ?? $data->no_telpon;
                            $data->provinsi = $jsonData['province'] ?? $data->provinsi;
                            $data->kota = $jsonData['city'] ?? $data->kota;
                            $data->pendidikan = $jsonData['jenjang'] ?? $data->pendidikan;
                            $data->sertifikat = $jsonData['sertifikasi'] ?? $data->sertifikat;
                            $data->no_rekening = (isset($jsonData['norek']) && $jsonData['norek'] !== '') ? $jsonData['norek'] : $data->no_rekening;
                            $data->nama_bank = (isset($jsonData['namaBank']) && $jsonData['namaBank'] !== '') ? $jsonData['namaBank'] : $data->nama_bank;
                            $data->save();
                        } catch (\Exception $e) {
                            Helpers::sendTo(843302196, "pesan error dari portal update data karyawan: ");
                            Helpers::sendTo(843302196, "Error: " . $e->getMessage());
                            Helpers::sendTo(843302196, "SQL: " . $e->getSql());
                            Helpers::sendTo(843302196, "Bindings: " . json_encode($e->getBindings()));
                            throw $e;
                        }
                        //proses simpan emergency contact
                        if(isset($jsonData['emergency']) && $jsonData['emergency'] != null){
                            $emergency = json_decode($jsonData['emergency'], true);

                            $contact = new ContactEmergency;
                            $existingContacts = $contact->where('id_karyawan', $data->id)->where('active', 1)->get();
                            $existingCount = $existingContacts->count();
                            $newCount = count($emergency);
                            Helpers::sendTo(843302196,"$existingCount" .'sama'."$newCount");
                            if ($existingCount == $newCount) {
                                // Update existing contacts

                                foreach ($emergency as $val) {
                                    Helpers::sendTo(843302196,"$existingCount" .'dan'."$newCount".'dan'. $data->id.'dan'.$val['id']);
                                    $dataContact = $existingContacts->where('id', $val['id'])->first();
                                    if ($dataContact) {
                                        $dataContact->nama = $val['name'];
                                        $dataContact->type = in_array($val['type'], ['orang_tua', 'saudara', 'kerabat', 'teman_kerja']) ? $val['type'] : null;
                                        $dataContact->no_hp = $val['phone'];
                                        $dataContact->save();
                                    }
                                }
                            } elseif ($newCount > $existingCount) {
                                // Update existing and insert new contacts
                                foreach ($emergency as $val) {
                                    $dataContact = $existingContacts->where('id', $val['id'])->first();
                                    if ($dataContact) {
                                        $dataContact->nama = $val['name'];
                                        $dataContact->type = in_array($val['type'], ['orang_tua', 'saudara', 'kerabat', 'teman_kerja']) ? $val['type'] : null;
                                        $dataContact->no_hp = $val['phone'];
                                        $dataContact->save();
                                    } else {
                                        $newContact = new ContactEmergency;
                                        $newContact->id_karyawan = $data->id;
                                        $newContact->nama = $val['name'];
                                        $newContact->type = in_array($val['type'], ['orang_tua', 'saudara', 'kerabat', 'teman_kerja']) ? $val['type'] : null;
                                        $newContact->no_hp = $val['phone'];
                                        $newContact->save();
                                    }
                                }

                            } elseif($newCount < $existingCount) {
                                // Update existing, delete extra contacts
                                $emerGencyId = array_column($emergency, 'id');
                                $extraContacts = $existingContacts->whereNotIn('id', $emerGencyId);
                                foreach ($extraContacts as $val) {
                                    $val->active = 0;
                                    $val->save();
                                }

                                // Kirim info ke Telegram untuk debugging
                                Helpers::sendTo(843302196, "Emergency IDs: " . implode(', ', $emerGencyId));
                                Helpers::sendTo(843302196, "Extra Contacts: " . $extraContacts->count());

                                // Pastikan perubahan tersimpan


                                // Refresh data dari database
                                $updatedContacts = ContactEmergency::where('id_karyawan', $data->id)->where('active', 0)->get();
                                Helpers::sendTo(843302196, "Updated Contacts (Inactive): " . $updatedContacts->count());

                                Helpers::sendTo(843302196,"$existingCount" .'kurang'."$newCount");

                            }else{
                                Helpers::sendTo(843302196,"$existingCount" .'tidak bisa terjamahin'."$newCount");
                            }

                        }


                        //porses simpan documen
                        if($request->hasFile('myfile')) {
                            Helpers::sendTo(843302196,"masuk file");
                            $uploadPath = public_path('attachman');

                            if(!File::isDirectory($uploadPath)){
                                File::makeDirectory($uploadPath, 0755, true, true);
                            }

                            foreach($request->file('myfile') as $keyFile => $file){
                                $fileName = $data->nik.'_'. rand() .'.'.$file->getClientOriginalExtension();
                                $fileSize = $file->getSize();
                                $extension = strtolower($file->getClientOriginalExtension());
                                $pathDocs =$file->getPathname() ;

                                if ($fileSize > 1048576 || in_array($extension, ['pdf', 'png', 'jpg', 'jpeg'])) {
                                    $image = new Imagick();
                                    $image->readImage($file->getPathname());

                                    if ($extension === 'pdf') {

                                        $image->setIteratorIndex(0);
                                        $image->setImageFormat('jpg');
                                        if ($image->getImageFormat() !== 'jpg') {
                                            Helpers::sendTo(843302196, "Gagal mengkonversi PDF ke JPG");
                                        }else{
                                            $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '.jpg';

                                        }
                                    } else if ($extension === 'png' || $extension === 'jpg' || $extension === 'jpeg') {

                                        $image->setIteratorIndex(0);
                                        $image->setImageFormat('jpg');
                                        if ($image->getImageFormat() !== 'jpg') {
                                            Helpers::sendTo(843302196, "Gagal mengkonversi gambar ke JPG");
                                        }else{
                                            $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '.jpg';

                                        }

                                    }

                                    try {
                                        Helpers::sendTo(843302196, "masuk try");
                                        $width = $image->getImageWidth();
                                        $height = $image->getImageHeight();

                                        // Hitung dimensi baru dengan mempertahankan aspek rasio
                                        $maxDimension = 1000; // Ukuran maksimum untuk lebar atau tinggi
                                        if ($width > $height) {
                                            $newWidth = $maxDimension;
                                            $newHeight = ($height / $width) * $maxDimension;
                                        } else {
                                            $newHeight = $maxDimension;
                                            $newWidth = ($width / $height) * $maxDimension;
                                        }

                                        // Resize gambarq
                                        $image->resizeImage($newWidth, $newHeight, Imagick::FILTER_LANCZOS, 1, true);
                                        $image->stripImage(); // Hapus metadata
                                        $image->setInterlaceScheme(Imagick::INTERLACE_PLANE); // Progressive loading

                                        // Kompresi dan penyimpanan
                                        $quality = 85; // Mulai dengan kualitas tinggi
                                        $targetSize = 200 * 1024; // Target ukuran 200KB

                                        do {
                                            $tempFile = tempnam(sys_get_temp_dir(), 'img');
                                            $image->setImageCompression(Imagick::COMPRESSION_JPEG);
                                            $image->setImageCompressionQuality($quality);
                                            $image->writeImage($tempFile);

                                            $fileSize = filesize($tempFile);
                                            if ($fileSize > $targetSize) {
                                                $quality -= 5; // Kurangi kualitas jika masih terlalu besar
                                            }

                                            if ($quality < 20) break; // Hindari kualitas yang terlalu rendah
                                        } while ($fileSize > $targetSize);

                                        // Pindahkan file hasil kompresi ke lokasi tujuan
                                        rename($tempFile, $uploadPath . '/' . $fileName);
                                        $path = $uploadPath . '/' . $fileName;

                                        chmod($path, 0644); // Set permission
                                    } catch (\ImagickException $e) {
                                        Helpers::sendTo(843302196, "Gagal memproses gambar: " . $e->getMessage());
                                        $path = null;
                                    }
                                } else {
                                    Helpers::sendTo(843302196, "masuk else");
                                    $path = $file->move($uploadPath, $fileName);
                                }

                                if($path) {
                                    chmod($path, 0644);

                                    if(isset($jsonData['attach']) && $jsonData['attach'] != null){
                                        $attach = json_decode($jsonData['attach'], true);
                                        try {
                                            $attachmant = new AttachmantFile;
                                            $countAttach = $attachmant->where('id_karyawan', $data->id)->where('active', 1)->count();
                                            $newCount = count($attach);
                                            if($countAttach == $newCount){ //jika kondisi jumlah sama
                                                Helpers::sendTo(843302196, "masuk if".$countAttach.'dan'.$newCount);
                                                $attachmentData = $attachmant->where('id_karyawan', $data->id)->where('id', empty($attach[$keyFile]['id_attachment']) ? null : $attach[$keyFile]['id_attachment'])->first();
                                                if($attachmentData){
                                                    $attachmentData->file_name = $fileName ?? $attachmentData->file_name;
                                                    $attachmentData->type = !empty($attach[$keyFile]['type_file']) ? $attach[$keyFile]['type_file'] : null;
                                                    $attachmentData->content = !empty($attach[$keyFile]['content']) ? $attach[$keyFile]['content'] : null;
                                                    $attachmentData->save();
                                                }
                                            }elseif($newCount > $countAttach){ //jika jumlah $newCount lebih besar dari $countAttach
                                                Helpers::sendTo(843302196, $newCount . "dan". $countAttach);
                                                foreach ($attach as $keyFile => $attachment) {
                                                    if (array_key_exists('id_attachment', $attachment)) {
                                                        Helpers::sendTo(843302196, "Data attachment loops dengan id_attachment: " . $attachment['id_attachment']);
                                                    } else {
                                                        Helpers::sendTo(843302196, "Data attachment loops tanpa id_attachment");
                                                    }
                                                    if ($attachment['id_attachment'] === 'null' || $attachment['id_attachment'] === null) {
                                                        // Insert baru
                                                        $newAttachment = new AttachmantFile;
                                                        $newAttachment->id_karyawan = $data->id;
                                                        $newAttachment->file_name = $fileName;
                                                        $newAttachment->type = !empty($attachment['type_file']) ? $attachment['type_file'] : null;
                                                        $newAttachment->content = !empty($attachment['content']) ? $attachment['content'] : null;
                                                        $newAttachment->save();

                                                        Helpers::sendTo(843302196, "Data attachment baru disimpan");
                                                    } else {
                                                        // Update data yang sudah ada
                                                        $existingAttachment = $attachmant->where('id_karyawan', $data->id)
                                                            ->where('id', $attachment['id_attachment'])
                                                            ->first();

                                                        if ($existingAttachment) {
                                                            $existingAttachment->file_name = $fileName ?? $existingAttachment->file_name;
                                                            $existingAttachment->type = !empty($attachment['type_file']) ? $attachment['type_file'] : null;
                                                            $existingAttachment->content = !empty($attachment['content']) ? $attachment['content'] : null;
                                                            $existingAttachment->save();

                                                            Helpers::sendTo(843302196, "Data attachment diperbarui");
                                                        }
                                                    }
                                                }
                                            }elseif($newCount < $countAttach){
                                                $attachId = array_column($attach, 'id_attachment');
                                                $attachmant->where('id_karyawan', $data->id)->whereNotIn('id', $attachId)->update(['active' => 0]);
                                                $updateAttach = $attachmant->where('id_karyawan', $data->id)->whereIn('id', $attachId)->first();
                                                $updateAttach->file_name = $fileName ?? $updateAttach->file_name;
                                                $updateAttach->type = !empty($attach[$keyFile]['type_file']) ? $attach[$keyFile]['type_file'] : null;
                                                $updateAttach->content = !empty($attach[$keyFile]['content']) ? $attach[$keyFile]['content'] : null;
                                                $updateAttach->save();


                                            }else{ //jika tidak ada jumlah sama sekali artinya insert awal
                                                Helpers::sendTo(843302196, "masuk attach".$attach[$keyFile]['type_file']);
                                                $newAttachment = new AttachmantFile;
                                                $newAttachment->id_karyawan = $data->id;
                                                $newAttachment->file_name = $fileName;
                                                $newAttachment->type = !empty($attach[$keyFile]['type_file']) ? $attach[$keyFile]['type_file'] : null;
                                                $newAttachment->content = !empty($attach[$keyFile]['content']) ? $attach[$keyFile]['content'] : null;
                                                $newAttachment->save();
                                                Helpers::sendTo(843302196, "File berhasil disimpan: $fileName");
                                            }

                                        } catch (\Exception $e) {
                                            Helpers::sendTo(843302196, "pesan error dari portal update data karyawan simpan gambar: ");
                                            Helpers::sendTo(843302196, "Error: " . $e->getMessage());
                                            Helpers::sendTo(843302196, "SQL: " . $e->getSql());
                                            Helpers::sendTo(843302196, "Bindings: " . json_encode($e->getBindings()));
                                        }
                                    }

                                } else {
                                    Helpers::sendTo(843302196, "Gagal menyimpan file: $fileName");
                                }
                            }
                        } else {
                            Helpers::sendTo(843302196, 'masuk sini771');
                            if(isset($jsonData['attach']) && $jsonData['attach'] != null){
                                Helpers::sendTo(843302196, 'masuk sini9090');
                                $attach = json_decode($jsonData['attach'], true);

                                $attachmant = new AttachmantFile;
                                $countAttach = $attachmant->where('id_karyawan', $data->id)->where('active', 1)->count();
                                $newCount = count($attach);
                                if($countAttach == $newCount){
                                    Helpers::sendTo(843302196, 'masuk sini44');
                                    foreach($attach as $val){
                                        Helpers::sendTo(843302196, 'value:'. $val['id_attachment']);
                                        $updateData = [
                                            'type' => !empty($val['type_file']) ? $val['type_file'] : null,
                                            'content' => !empty($val['content']) ? $val['content'] : null
                                        ];
                                        $attachmant->where('id_karyawan', $data->id)
                                                ->where('id', $val['id_attachment'])
                                                ->update($updateData);
                                        Helpers::sendTo(843302196, 'value:'. $val['id_attachment']);
                                    }
                                }elseif($newCount < $countAttach){
                                    $attachId = array_column($attach, 'id_attachment');

                                    if (empty($attachId)) {
                                        // Jika $attachId kosong atau null, nonaktifkan semua attachment
                                        Helpers::sendTo(843302196, 'kosong');
                                        $attachmant->where('id_karyawan', $data->id)->update(['active' => 0]);
                                    } else {
                                        // Jika $attachId tidak kosong, nonaktifkan attachment yang tidak ada di $attachId
                                        Helpers::sendTo(843302196, 'ada');
                                        $attachmant->where('id_karyawan', $data->id)->whereNotIn('id', $attachId)->update(['active' => 0]);
                                    }
                                    $updateAttach = $attachmant->where('id_karyawan', $data->id)->whereIn('id', $attachId)->get();
                                    foreach ($updateAttach as $key => $attach) {
                                        $attach->file_name = $fileName ?? $attach->file_name;
                                        $attach->type = !empty($attach[$key]['type_file']) ? $attach[$key]['type_file'] : null;
                                        $attach->content = !empty($attach[$key]['content']) ? $attach[$key]['content'] : null;
                                        $attach->save();
                                    }
                                }
                            }else{
                                Helpers::sendTo(843302196, 'kosong');
                                $attachmant = new AttachmantFile;
                                $attachmant->where('id_karyawan', $data->id)->update(['active' => 0]);
                            }

                            Helpers::sendTo(843302196, 'Tidak ada file yang diunggah');
                        }


                        DB::commit();
                        Helpers::sendTo(843302196,'berhasil');
                        $updatedData = MasterKaryawan::with(
                            ['emergencyContact' => function($query) {
                                $query->where('active', 1);
                            },
                            'attachment' => function($query) {
                                $query->where('active', 1);
                            }
                        ])->find($data->id);
                        $updatedData = MasterKaryawan::with(
                            ['emergencyContact' => function($query) {
                            $query->where('active', 1);
                        },'attachment'=>function($query){
                            $query->where('active', 1);
                        }])->find($data->id);
                        Helpers::sendTo(843302196,'berhasilkah');
                        return response()->json(["message"=>"berhasil update data","karyawan"=>$updatedData],200);

                    }else{
                        Helpers::sendTo(843302196,'karywana dengan NIK');
                        return response()->json(["message"=>"karywana dengan NIK : $nik ,belum ada"],404);
                    }
                } catch (\Exception $ex) {
                    //throw $th;
                    DB::rollback();
                    Log::error('error update karyawan',['error' => $ex]);
                    $templateMessage ="Error : ".$ex->getMessage()."\nLine : ".$ex->getLine()."\nFile : ".$ex->getFile()."\n pada method updateKaryawan";
                    Helpers::sendTo(843302196,$templateMessage);
                    // Helpers::sendTo(6269456033,$templateMessage);
                    return response()->json(['message'=>$ex->getMessage(), 'line'=>$ex->getLine()],500);
                }
            }
        }

    /*
        default/api/getkaryawan
        defaultApi\recruitmentApi\portalControllerApi@getKaryawan
    */
    public function getKaryawan(Request $request) {

        if($this->rjsn != 1) {
            Helpers::saveToLogRequest($this->pathinfo, $this->globaldate, $this->param, $this->useragen, $this->resultx, $this->ip, $this->userid);
            return response()->json($this->rjsn, 401);
        } else {

            try {
                // dd(\str_contains($request->email,'@intilab.com'),$request->has('email'));
                $nik=null;
                if($request->has('email') && $request->email != null || $request->email != ''){

                    $check =\str_contains($request->email,'@intilab.com');
                    if(!$check){
                        Helpers::sendTo(6269456033,'kak salah email nih');
                        return response()->json(["message"=>"opps harus menggunakan email perusahaan"],401);
                    }
                }
                if($request->has('nik') && $request->nik !=null || $request->nik != ""){
                    $nik = preg_replace('/[^a-zA-Z0-9]/','',$request->nik);
                }

                $db_old = date('Y', strtotime ( '-1 year' , strtotime(date('Y-m-d')) ));
                $table = new MasterKaryawan();
                if($nik != null && $request->has('email') && $request->email != ''){
                    $data = $table::with(['emergencyContact' => function($query) {
                            $query->where('active', 1);
                        },'attachment' => function($query) {
                            $query->where('active', 1);
                        }])
                        ->where('nik', $nik)
                        ->where('email', $request->email)
                        ->where('active', 0)
                        ->first();
                    if($data){
                        return response()->json(["data"=>boolval(1),"karyawan"=>$data],200);
                    }else{
                        return response()->json(["message"=>"karywana dengan NIK : $nik ,belum ada"],404);
                    }
                 }else{
                     return response()->json(["message"=>"karywana dengan NIK : kosong"],404);
                }
            } catch (\Exception $ex) {
               $templateMessage ="Error : ".$ex->getMessage()."\nLine : ".$ex->getLine()."\nFile : ".$ex->getFile()."\n pada method getKaryawan";
                Helpers::sendTo(843302196,$templateMessage);
                Helpers::sendTo(6269456033,$templateMessage);
                return response()->json(['message'=>$ex->getMessage(), 'line'=>$ex->getLine()],500);
            }
        }
    }

    public function getSoal(Request $request)
    {
        try {

            $soal = SoalPsikotes::whereIn('kategori_soal', $request->kategori_soal)->get();

            // Group berdasarkan kategori_soal (IST, PAPIKOSTIC, dll)
            $groupedByKategoriSoal = $soal->groupBy('kategori_soal');

            // Acak urutan kategori besar
            $shuffledKategoriSoal = collect($groupedByKategoriSoal->all());

            $finalSoal = [];

            foreach ($shuffledKategoriSoal as $kategoriSoal => $soalSet) {

                if ($kategoriSoal === 'IST') {
                    // Subkategori di IST (umum, menghafal_cepat, dll), dan urutannya juga diacak
                    $groupedBySubKategori = collect($soalSet->groupBy('kategori')->all())->shuffle();

                    foreach ($groupedBySubKategori as $subKategori => $subSoal) {
                        $processed = $subSoal
                            ->shuffle() // acak soal dalam subkategori
                            ->map(function ($item) {
                                return $this->decodeDanBersihkanSoalJawaban($item);
                            })
                            ->values();

                        $finalSoal[$kategoriSoal][$subKategori] = $processed;
                    }

                } else {
                    // Kategori tanpa subkategori
                    $processed = $soalSet
                        ->shuffle()
                        ->map(function ($item) {
                            return $this->decodeDanBersihkanSoalJawaban($item);
                         })
                        ->values();

                        $finalSoal[$kategoriSoal] = $processed;
                }
            }

            return response()->json($finalSoal);


        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine()
            ], 401);
        }
    }

     public function saveExam(Request $request)
    {
        try {
            // Validasi data yang masuk
            if (!isset($request->data[1]) || $request->data[1]['type'] == 'KOSTICK PAPI') {
                $questionIds = $request->data[1]['id_questions'];
                $userAnswers = $request->data[1]['answers'];
                
                // Menggabungkan ID soal dan jawaban pengguna untuk memudahkan pencarian
                $formatKostickPapi = array_combine($questionIds, $userAnswers);
                
                // Mengambil semua SoalPsikotes yang relevan dalam satu query
                $soalList = SoalPsikotes::whereIn('id', array_keys($formatKostickPapi))->get()->keyBy('id');
                
                // Array ini akan berisi ID PapiRole yang terkait dengan setiap jawaban yang valid
                $papiRoleIdsFromAnswers = [];
                foreach ($formatKostickPapi as $soalId => $jawabanUser) {
                    $soal = $soalList[$soalId] ?? null;

                    if (!$soal || $jawabanUser === null) {
                        continue; // Lewati jika soal tidak ditemukan atau jawaban kosong/null
                    }

                    $jawabanJson = json_decode($soal->jawaban, true);

                    // Pastikan kunci 'data' dan 'value' ada dalam JSON jawaban soal
                    if (!isset($jawabanJson['data']) || !isset($jawabanJson['value'])) {
                        continue;
                    }

                    // Cari indeks jawaban pengguna di dalam array 'data'
                    $index = array_search($jawabanUser, $jawabanJson['data']);

                    // Jika ditemukan, ambil 'value' yang sesuai (yang kita asumsikan adalah PapiRole ID)
                    if ($index !== false && isset($jawabanJson['value'][$index])) {
                        $papiRoleIdsFromAnswers[] = (int)$jawabanJson['value'][$index]; // Tambahkan ID role ke array
                    }
                }
                
                // --- Logika baru: Menghitung skor (frekuensi) untuk setiap PAPI Role ---
                // Hitung frekuensi kemunculan setiap PapiRole ID
                // Ini akan menjadi skor akhir untuk setiap role
                $aggregatedRoleScores = array_count_values($papiRoleIdsFromAnswers);
                
                // 1. Ambil semua PapiRoles beserta aspeknya (eager loading untuk efisiensi)
                $papiRoles = PapiRole::with('aspect')->get()->keyBy('id');
                
                // 2. Ambil semua PapiRules dan kelompokkan berdasarkan role_id untuk pencarian efisien
                $papiRules = PapiRule::all()->groupBy('role_id');
                
                
                // Array untuk menyimpan hasil akhir yang terstruktur (sudah diinterpretasi)
                $finalPapiAnalysis = [];
                foreach ($aggregatedRoleScores as $roleId => $totalScore) {
                    $role = $papiRoles[$roleId] ?? null; // Ambil detail role
                    if (!$role) {
                        continue;
                    }
                   
                    $aspect = $role->aspect; // Ambil detail aspek terkait
                    

                    // Cari aturan (rule) yang cocok untuk role ini berdasarkan total skornya
                    $matchingRule = null;
                    if (isset($papiRules[$roleId])) {
                        foreach ($papiRules[$roleId] as $rule) {
                            if ($totalScore >= $rule->low_value && $totalScore <= $rule->high_value) {
                                $matchingRule = $rule;
                                break; // Aturan ditemukan, berhenti mencari
                            }
                        }
                    }

                    // Inisialisasi struktur aspek jika belum ada
                    if (!isset($finalPapiAnalysis[$aspect->id])) {
                        $finalPapiAnalysis[$aspect->id] = [
                            'aspect_id' => $aspect->id,
                            'aspect_name' => $aspect->aspect,
                            'roles' => [],
                        ];
                    }

                    // Tambahkan detail role ke dalam aspek yang sesuai
                    $finalPapiAnalysis[$aspect->id]['roles'][] = [
                        'role_id' => $role->id,
                        'role_code' => $role->code, // Menggunakan kolom 'code' dari PapiRole
                        'role_description' => $role->role, // Menggunakan kolom 'role' dari PapiRole
                        'score' => $totalScore,
                        'interpretation' => $matchingRule ? $matchingRule->interprestation : 'Interpretasi tidak ditemukan.',
                    ];
                }
                // Ubah struktur array finalPapiAnalysis agar tidak menggunakan ID aspek sebagai kunci utama
                $finalPapiAnalysis = array_values($finalPapiAnalysis);
            }

            if (!isset($request->data[0]) || $request->data[0]['type'] == 'IST') {
                $questionIds = $request->data[0]['id_questions'];
                $userAnswers = $request->data[0]['answers'];
                
                // Menggabungkan ID soal dan jawaban pengguna untuk memudahkan pencarian
                $userResponsesIST = array_combine($questionIds, $userAnswers);
                $soalListIST = SoalPsikotes::whereIn('id', array_keys($userResponsesIST))
                                          ->select('id', 'kunci_jawaban', 'kategori') // Hanya ambil kolom yang dibutuhkan
                                          ->get()
                                          ->keyBy('id');
                $categoryResults = []; // Untuk menyimpan hasil per kategori

                foreach ($userResponsesIST as $soalId => $jawabanUser) {
                    $soal = $soalListIST[$soalId] ?? null;

                    if (!$soal || !isset($soal->kunci_jawaban) || !isset($soal->kategori)) {
                        // Lewati jika soal tidak ditemukan atau tidak memiliki kunci_jawaban/kategori
                        continue;
                    }

                    $kunciJawaban = $soal->kunci_jawaban;
                    $kategori = $soal->kategori;

                    // Inisialisasi kategori jika belum ada
                    if (!isset($categoryResults[$kategori])) {
                        $categoryResults[$kategori] = [
                            'total_questions' => 0,
                            'correct_answers' => 0,
                            'incorrect_answers' => 0,
                            'percentage' => 0,
                        ];
                    }

                    // Hitung benar atau salah
                    $categoryResults[$kategori]['total_questions']++;
                    if (strtolower($jawabanUser) === strtolower($kunciJawaban)) { // Bandingkan tanpa case-sensitive
                        $categoryResults[$kategori]['correct_answers']++;
                    } else {
                        $categoryResults[$kategori]['incorrect_answers']++;
                    }
                }
                // Hitung persentase untuk setiap kategori
                foreach ($categoryResults as $kategori => &$data) { // Gunakan '&' untuk mengubah array asli
                    if ($data['total_questions'] > 0) {
                        $data['percentage'] = ($data['correct_answers'] / $data['total_questions']) * 100;
                    }
                    $data['percentage'] = round($data['percentage'], 2); // Bulatkan 2 angka di belakang koma
                }
            }
            
            $finalAnswerExamp=[
                "ist" => $userResponsesIST,
                "kostick_papi" =>$formatKostickPapi
            ];

            $finalConclusionExamp=[
                "ist" => $categoryResults,
                "kostick_papi" => $finalPapiAnalysis,
            ];

            $finalTimeSpent =[
                "ist" => $request->data[0]['submitted_at'],
                "kostick_papi" => $request->data[1]['submitted_at']
            ];

            // simpan database
            $dataSave = new RecruitmantExamp;
            $dataSave->answer = json_encode($finalAnswerExamp);
            $dataSave->kode_uniq = $request->data[0]['kode_uniq'];
            $dataSave->conclusion  = json_encode($finalConclusionExamp);
            $dataSave->timespent  = json_encode($finalTimeSpent);
            $dataSave->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $dataSave->save();

            return response()->json([
                'message' => 'Hasil Examp berhasil diproses dan disimpan!',
                'answer' => $finalAnswerExamp,
                'conclusion' => $finalConclusionExamp,
            ], 200);
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json(['message'=>$ex->getMessage(), 'line'=>$ex->getLine()]);
        }

    }

    public function verify(Request $request)
    {   
        $chek = Recruitment::where('kode_uniq',$request->kode_uniq)->first();
        if($chek == null){
            return response()->json('data dengan kode '. $request->kode_uniq .', tidak di temukan periksa kembali inputan',401);
        }else if($chek->is_active == 0){
            return response()->json('data dengan kode ' . $chek->kode_uniq .', sudah tidak aktif',401);
        }else if($chek->is_used == 1){
            return response()->json('kode '.$chek->kode_uniq.' ini sudah di gunakan',401);
        }

        $chek->is_used = 1;
        $chek ->kehadiran = DATE('Y-m-d H:i:s');
        $chek->save();
        return response()->json($chek,200);

    }

    private function getAnswerDistribution($numericAnswers)
    {
        $distribution = [];
        $valueCounts = array_count_values($numericAnswers);

        foreach ($valueCounts as $value => $count) {
            $distribution[] = [
                'value' => $value,
                'count' => $count,
                'percentage' => round(($count / count($numericAnswers)) * 100, 2)
            ];
        }

        // Sort by value
        usort($distribution, function($a, $b) {
            return $a['value'] <=> $b['value'];
        });

        return $distribution;
    }

    private function decodeDanBersihkanSoalJawaban($item)
    {
        if (!empty($item->pertanyaan)) {
            $pertanyaan = json_decode($item->pertanyaan, true);

            if (isset($pertanyaan['data'])) {
                $pertanyaan['data'] = preg_replace('/^\d+\.\s*/', '', $pertanyaan['data']);
                //$pertanyaan['data'] = preg_replace('/^(Soal\s*)?\d+\.\s*/i', 'Soal ', $pertanyaan['data']);
                if (preg_match('/^(Soal\s*)?\d+\.\s*/i', $pertanyaan['data'])) {
                    $pertanyaan['data'] = '';
                }
            }

            $item->pertanyaan = $pertanyaan;
        }

        if (!empty($item->jawaban)) {
            $jawaban = json_decode($item->jawaban, true);
            if (isset($jawaban['data']) && is_array($jawaban['data'])) {
                shuffle($jawaban['data']);
            }
            $item->jawaban = $jawaban;
        }

        return $item;
    }


    public function kehadiran(Request $request) {
        // dd($request->all());
        $date = $request->date;
        $carbonDate = Carbon::parse($date);
        $year = $carbonDate->year;
        $month = $carbonDate->month;
        // [$year, $month] = explode('-', $date);
        $data = Recruitment::with('jabatan')
        ->where('is_active', 1)
        ->where('is_used',true)
        ->whereYear('kehadiran', $year)
        ->whereMonth('kehadiran', $month)
        ->get();
        return Datatables::of($data)->make(true);
    }

    public function dataPsikotes(Request $request) { // data psikotes
         $date = $request->date;
        $carbonDate = Carbon::parse($date);
        $year = $carbonDate->year;
        $month = $carbonDate->month;
        // [$year, $month] = explode('-', $date);
        $data = Recruitment::with(['examps', 'jabatan'])->where('is_active', 1)
        ->where('is_used',true)
        ->whereYear('kehadiran', $year)
        ->whereMonth('kehadiran', $month)
        ->get();
        return Datatables::of($data)->make(true);
    }
}
