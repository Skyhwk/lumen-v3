<?php
namespace App\Services;

use App\Models\Jadwal;
use App\Models\SamplingPlan;
use App\Models\OrderHeader;
use App\Models\OrderDetail;
use App\Models\MasterKaryawan;
use App\Models\MasterSubKategori;
use App\Models\PersiapanSampelHeader;
use App\Models\QuotationKontrakH;
use App\Models\QuotationKontrakD;
use App\Models\QuotationNonKontrak;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;


class JadwalServices
{
    private $rejectJadwalKontrak;
    private $rejectJadwalNon;
    private $updateJadwalKategori;
    private $updateJadwal;
    private $addJadwal;
    private $no_quotation;
    private $quotation_id;
    private $insertParsialKontrak;
    private $insertParsial;
    private $timestamp;
    private static $instance;

    /*
        Cara Pemanggilan:
        JadwalServices::on('addJadwal', $ObjectData)->addJadwalSP();
        JadwalServices::on('updateJadwal', $ObjectData)->updateJadwalSP();
        JadwalServices::on('updateJadwalSPKategori', $ObjectData)->updateJadwalSPKategori();
        JadwalServices::on('rejectJadwalNon', $ObjectData)->rejectJadwalSP();
        JadwalServices::on('rejectJadwalKontrak', $ObjectData)->rejectJadwalSPKontrak();
        JadwalServices::on('insertParsial', $ObjectData)->insertParsial();
        JadwalServices::on('insertParsialKontrak', $ObjectData)->insertParsialKontrak();
        JadwalServices::on('no_quotation', $no_quotation)->reverseJadwalSP();
     */
    public function __construct()
    {
        $this->timestamp = Carbon::now()->format('Y-m-d H:i:s');
    }

    public function __call($method, $arguments)
    {
        throw new Exception("Method $method does not exist on JadwalServices. Arguments: " . implode(", ", $arguments) . "\n", 404);
    }

    public static function __callStatic($method, $arguments)
    {
        echo "Static method $method does not exist on JadwalServices. Arguments: " . implode(", ", $arguments) . "\n";
    }

    public static function on($field, $value)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        switch ($field) {
            case 'insertParsialKontrak':
                self::$instance->insertParsialKontrak = $value;
                break;
            case 'insertParsial':
                self::$instance->insertParsial = $value;
                break;
            case 'rejectJadwalKontrak':
                self::$instance->rejectJadwalKontrak = $value;
                break;
            case 'rejectJadwalNon':
                self::$instance->rejectJadwalNon = $value;
                break;
            case 'updateJadwalKategori':
                self::$instance->updateJadwalKategori = $value;
                break;
            case 'updateJadwal':
                self::$instance->updateJadwal = $value;
                break;
            case 'addJadwal':
                self::$instance->addJadwal = $value;
                break;
            case 'no_quotation':
                self::$instance->no_quotation = $value;
                break;
            case 'quotation_id':
                self::$instance->quotation_id = $value;
                break;
        }

        return self::$instance;
    }

    public function getQuotation()
    {
        $path = explode('/', $this->no_quotation);
        if ($path[1] == 'QTC') {
            try {
                $getRaw = QuotationKontrakH::where('no_document', $this->no_quotation)->first();
                return $getRaw;
            } catch (Exception $e) {
                throw new Exception($e->getMessage(), 401);
            }
        } else {
            try {
                $getRaw = QuotationNonKontrak::where('no_document', $this->no_quotation)->first();
                return $getRaw;
            } catch (Exception $e) {
                throw new Exception($e->getMessage(), 401);
            }
        }
    }

    public function countJadwalApproved()
    {
        if ($this->no_quotation == null) {
            throw new Exception("No Quotation is required", 400);
        }

        try {
            $type = explode('/', $this->no_quotation)[1];

            $query = SamplingPlan::query()
                ->select('id', 'periode_kontrak')
                ->where('no_quotation', $this->no_quotation)
                ->where('is_active', true)
                ->where('status', 1)
                ->where('is_approved', 1);

            if ($type != 'QTC') {
                $query->whereHas('jadwal', function ($q) {
                    $q->whereNull('parsial')
                        ->where('is_active', 1);
                });
            }

            $result = $query->with([
                'jadwal' => function ($q) {
                    $q->whereNull('parsial')
                        ->where('is_active', 1)
                        ->select('id_sampling')
                        ->groupBy('id_sampling');
                }
            ])
                ->groupBy('id', 'periode_kontrak')
                ->get();

            return $result->count();

        } catch (Exception $e) {
            throw new Exception($e->getMessage(), 401);
        }
    }

    public function countQuotation()
    {
        if ($this->no_quotation == null || $this->quotation_id == null) {
            throw new Exception("No Quotation is required", 400);
        }

        try {
            $type = explode('/', $this->no_quotation)[1];
            if ($type == 'QTC') {
                $getRaw = QuotationKontrakD::where('id_request_quotation_kontrak_h', $this->quotation_id);
            } else if ($type == 'QT') {
                $getRaw = QuotationNonKontrak::where('id', $this->quotation_id);
            }

            return $getRaw->where('status_sampling', '!=', 'SD')
                ->count();
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), 401);
        }
    }

    // tinggal di test
    public function rejectJadwalSPKontrak()
    {
        $dataReject = $this->rejectJadwalKontrak;

        if ($dataReject->no_quotation == null || $dataReject->id_sampling == null || $dataReject->karyawan == null) {
            throw new Exception('No Quotation, Sample Id, Karyawan is required', 401);
        }

        DB::beginTransaction();
        try {
            $cek = Jadwal::where('id_sampling', $dataReject->id_sampling)->where('is_active', true)->count();
            if ($cek > 1) {
                throw new Exception('Ada jadwal yang belum di reject.!', 401);
            }

            $cek_qtc = QuotationKontrakH::where('no_document', $dataReject->no_quotation)->where('is_active', true)->first();
            if ($cek_qtc->flag_status == 'ordered') {
                throw new Exception('Jadwal tidak dapat di cancel, karena status sudah ordered, mohon hubungi sales untuk reschedule.!', 401);
            }

            SamplingPlan::where('id', $dataReject->id_sampling)->update([
                'is_active' => false,
                'deleted_by' => $dataReject->karyawan,
                'deleted_at' => $this->timestamp,
            ]);

            $sales = $cek_qtc->sales_id;
            $admsales = MasterKaryawan::where('nama_lengkap', $cek_qtc->updated_by)->pluck('id')->first();

            $cek_qtc->update([
                'keterangan_reject_sp' => $dataReject->rejection_reason
            ]);

            if ($sales == $admsales) {
                $message = "Permintaan SP telah di reject oleh admin jadwal, silahkan melakukan request ulang dengan no QT " . $dataReject->no_quotation;
                Notification::where('id', $sales)->title('Reject SP')->message($message)->url('url')->send();

            } else {
                $message = "Permintaan SP telah di reject oleh admin jadwal, silahkan melakukan request ulang dengan no QT " . $dataReject->no_quotation;
                Notification::where('id', $sales)->title('Reject SP')->message($message)->url('url')->send();
                Notification::where('id', $admsales)->title('Reject SP')->message($message)->url('url')->send();
            }

            DB::commit();
            return true;
        } catch (Exception $ex) {
            DB::rollBack();
            throw new Exception($ex->getMessage(), 401);
        }
    }

    // tinggal di test
    public function rejectJadwalSP()
    {
        $dataReject = $this->rejectJadwalNon;

        if ($dataReject->no_quotation == null || $dataReject->id_sampling == null || $dataReject->karyawan == null) {
            throw new Exception('No Quotation, Sample Id, Karyawan is required', 401);
        }
        DB::beginTransaction();
        try {
            $cek = Jadwal::where('id_sampling', $dataReject->id_sampling)->where('is_active', true)->count();
            if ($cek > 1) {
                throw new Exception('Ada jadwal yang belum di reject.!', 401);
            }

            $cek_qt = QuotationNonKontrak::where('no_document', $dataReject->no_quotation)->where('is_active', true)->first();
            if ($cek_qt->flag_status == 'ordered') {
                throw new Exception('Jadwal tidak dapat di cancel, karena status sudah ordered, mohon hubungi sales untuk reschedule.!', 401);
            }

            SamplingPlan::where('id', $dataReject->id_sampling)->update([
                'is_active' => false,
                'deleted_by' => $dataReject->karyawan,
                'deleted_at' => $this->timestamp,
            ]);

            $sales = $cek_qt->sales_id;
            $admsales = MasterKaryawan::where('nama_lengkap', $cek_qt->updated_by)->pluck('id')->first();

            $cek_qt->update([
                'keterangan_reject_sp' => $dataReject->rejection_reason
            ]);

            if ($sales == $admsales) {
                $message = "Permintaan SP telah di reject oleh admin jadwal, silahkan melakukan request ulang dengan no QT " . $dataReject->no_quotation;
                Notification::where('id', $sales)->title('Reject SP')->message($message)->url('url')->send();
            } else {
                $message = "Permintaan SP telah di reject oleh admin jadwal, silahkan melakukan request ulang dengan no QT " . $dataReject->no_quotation;
                Notification::where('id', $sales)->title('Reject SP')->message($message)->url('url')->send();
                Notification::where('id', $admsales)->title('Reject SP')->message($message)->url('url')->send();
            }

            DB::commit();
            return true;
        } catch (Exception $ex) {
            DB::rollBack();
            throw new Exception($ex->getMessage(), 401);
        }
    }

    // tingga di test
    public function reverseJadwalSP()
    {
        if ($this->no_quotation == null) {
            throw new Exception('No Quotation is required', 401);
        }

        DB::beginTransaction();
        try {
            SamplingPlan::where('no_quotation', $this->no_quotation)->update(['is_active' => false]); // rubah ke delete()
            DB::commit();
            return true;
        } catch (Exception $ex) {
            DB::rollBack();
            throw new Exception($ex->getMessage(), 401);
        }
    }

    // tinggal di test
    public function updateJadwalSP()
    {
        
        $dataUpdate = $this->updateJadwal;
        
        if (
            $dataUpdate->no_quotation == null ||
            $dataUpdate->nama_perusahaan == null ||
            $dataUpdate->jam_mulai == null ||
            $dataUpdate->jam_selesai == null ||
            $dataUpdate->kategori == null ||
            $dataUpdate->warna == null ||
            $dataUpdate->durasi == null ||
            $dataUpdate->status == null ||
            $dataUpdate->batch_id == null ||
            $dataUpdate->kendaraan == null ||
            $dataUpdate->sampling == null ||
            $dataUpdate->karyawan == null ||
            $dataUpdate->jadwal_id == null ||
            $dataUpdate->tanggal == null ||
            $dataUpdate->sampler == null ||
            // $dataUpdate->driver == null ||
            $dataUpdate->pendampingan_k3 == null ||
            // $dataUpdate->isokinetic == null ||
            // $dataUpdate->durasi_lama == null ||
            $dataUpdate->tanggal_lama == null
            // || $dataUpdate->periode == null    //cek di command line karena ada kasus dimana periode null kontrak maupun non kontrak
        ) {
            throw new Exception('No Quotation, Nama Perusahaan, Jam Mulai, Jam Selesai, Kategori, Warna, Durasi, Status, Batch Id, Kendaraan, Sampling, Karyawan, Jadwal Id, Tanggal, Sampler, Durasi Lama, Tanggal Lama is required', 401);
        }

        self::$instance->no_quotation = $dataUpdate->no_quotation;

        $oldDate = $dataUpdate->tanggal_lama;
        // ===============================SEARCH DATA======================

        $data = Jadwal::where('no_quotation', $dataUpdate->no_quotation)
            ->where('tanggal', $dataUpdate->tanggal)
            ->where('durasi', $dataUpdate->durasi_lama)
            ->where('is_active', true);
        if (!empty($dataUpdate->tipe_parsial)) {
            $data = $data->where('parsial', $dataUpdate->tipe_parsial);
        } else {
            $data = $data->whereNull('parsial');
        }
        $data = $data->get();
        // =================================================================

        $dir = $dataUpdate->sampler;
        $i = 0;
        $lama = COUNT($data);
        $baru = COUNT($dir);
        DB::beginTransaction();
        try {
            $jadw = Jadwal::where('id', $dataUpdate->jadwal_id)->whereNull('parsial')->where('is_active', true)->first();
            $jadw2 = Jadwal::where('parsial', $dataUpdate->jadwal_id)->where('id', '!=', $dataUpdate->jadwal_id)->where('is_active', true)->get();
            if (!$jadw2->isEmpty()) {
                if (!empty($jadw)) {
                    foreach ($jadw2 as $key => $val) {
                        foreach (json_decode($val->kategori) as $x => $y) {
                            if (in_array($y, $dataUpdate->kategori)) {
                                throw new Exception('Ada input kategori yang sama.! ' . $y, 401);
                            }
                        }
                    }
                } else {
                    foreach ($jadw2 as $key => $val) {
                        $jadw3 = Jadwal::where('id', $val->id)->whereNull('parsial')->where('is_active', true)->first();
                        if ($jadw3) {
                            foreach (json_decode($jadw3->kategori) as $x => $y) {
                                if (in_array($y, $dataUpdate->kategori)) {
                                    throw new Exception('Ada input kategori yang sama.! ' . $y, 401);
                                }
                            }
                        }
                        foreach (json_decode($val->kategori) as $x => $y) {
                            if (in_array($y, $dataUpdate->kategori)) {
                                throw new Exception('Ada input kategori yang sama.! ' . $y, 401);
                            }
                        }
                    }
                }
            } else {
                $jadw4 = Jadwal::where('parsial', $dataUpdate->jadwal_id)->where('is_active', true)->get();
                if (!$jadw4->isEmpty()) {
                    foreach ($jadw as $key => $val) {
                        foreach (json_decode($val->kategori) as $x => $y) {
                            if (in_array($y, $dataUpdate->kategori)) {
                                throw new Exception('Ada input kategori yang sama.! ' . $y, 401);
                            }
                        }
                    }
                }
            }

            $tipe_qt = explode("/", $dataUpdate->no_quotation)[1];

            $wilayah = null;
            if (explode('/', $dataUpdate->no_quotation)[1] == 'QTC') {
                $cek = QuotationKontrakH::where('no_document', $dataUpdate->no_quotation)->select('wilayah')->first();
                $wilayah = explode('-', $cek->wilayah)[1];
            } else if (explode('/', $dataUpdate->no_quotation)[1] == 'QT') {
                $cek = QuotationNonKontrak::where('no_document', $dataUpdate->no_quotation)->select('wilayah')->first();
                $wilayah = explode('-', $cek->wilayah)[1];
            }

            if ($lama == $baru) { //tidak ada perubhan jumlah sampler
                $dbInsertLastId = null;
                Jadwal::whereIn('id', $dataUpdate->batch_id)
                    ->update([
                        'is_active' => false,
                        'updated_at' => $this->timestamp,
                        'updated_by' => $dataUpdate->karyawan,
                    ]);

                foreach ($data as $key => $val) {
                    $sampler = explode(',', $dir[$key]);
                    $nama_sampler = $sampler[1];
                    $idSampler = $sampler[0];

                    $datajad = [
                        'no_quotation' => $dataUpdate->no_quotation,
                        'nama_perusahaan' => $dataUpdate->nama_perusahaan,
                        'wilayah' => $wilayah,
                        'alamat' => $dataUpdate->alamat,
                        'tanggal' => $dataUpdate->tanggal,
                        'periode' => $dataUpdate->periode ?? null,
                        'jam' => $dataUpdate->jam_mulai,
                        'jam_mulai' => $dataUpdate->jam_mulai,
                        'jam_selesai' => $dataUpdate->jam_selesai,
                        'kategori' => json_encode($dataUpdate->kategori),
                        'sampler' => $nama_sampler,
                        'driver' => $dataUpdate->driver ?? null,
                        'pendampingan_k3' => $dataUpdate->pendampingan_k3 ?? 0,
                        'isokinetic' => $dataUpdate->isokinetic ?? 0,
                        'userid' => $idSampler,
                        'warna' => $dataUpdate->warna,
                        'note' => $dataUpdate->note,
                        'durasi' => $dataUpdate->durasi,
                        'status' => $dataUpdate->status,
                        'notif' => 0,
                        'urutan' => $dataUpdate->urutan,
                        'kendaraan' => $dataUpdate->kendaraan,
                        'parsial' => !empty($dataUpdate->tipe_parsial) ? $dataUpdate->tipe_parsial : null,
                        'updated_at' => $this->timestamp,
                        'updated_by' => $dataUpdate->karyawan,
                        'id_sampling' => $dataUpdate->sampling,
                        'id_cabang' => $dataUpdate->id_cabang,
                    ];
                    $dbInsertLastId = Jadwal::insertGetId($datajad);
                    $noqt = $val->no_quotation;
                }
                // perindahan indukMainJadwal
                /* casenya:
                 * jika yang dirubah adalah main jadwal,semantara jadwal itu  memiliki parsial,maka yang terjadi harusnya adalah data column parisal yang memiliki idInduk yang mati di Update dengan IdInduk Baru terrecord,guna menjaga konsistensi relasi induk dan parsialnya.   
                 */

                Jadwal::whereIn('parsial', $dataUpdate->batch_id)->update(['parsial' => $dbInsertLastId]);
            } else {// tempat update status jadwal lama
                $dbInsertLastId = null;
                Jadwal::whereIn('id', $dataUpdate->batch_id)
                    ->where('no_quotation', $dataUpdate->no_quotation)
                    ->where('tanggal', $oldDate)
                    ->update([
                        'is_active' => false,
                        'updated_by' => $dataUpdate->karyawan,
                        'updated_at' => $this->timestamp
                    ]);

                foreach ($dir as $key => $value) {
                    $sampler = explode(',', $value);
                    $nama_sampler = $sampler[1];
                    $idSampler = $sampler[0];
                    $samplers[] = $idSampler;
                    $body = [
                        'no_quotation' => $dataUpdate->no_quotation,
                        'nama_perusahaan' => $dataUpdate->nama_perusahaan,
                        'wilayah' => $wilayah,
                        'alamat' => $dataUpdate->alamat,
                        'id_sampling' => $dataUpdate->sampling,
                        'id_cabang' => $dataUpdate->id_cabang,
                        'tanggal' => $dataUpdate->tanggal,
                        'periode' => $dataUpdate->periode ?? null,
                        'jam' => $dataUpdate->jam_mulai,
                        'jam_mulai' => $dataUpdate->jam_mulai,
                        'jam_selesai' => $dataUpdate->jam_selesai,
                        'kategori' => json_encode($dataUpdate->kategori),
                        'sampler' => $nama_sampler,
                        'driver' => $dataUpdate->driver ?? null,
                        'pendampingan_k3' => $dataUpdate->pendampingan_k3 ?? 0,
                        'isokinetic' => $dataUpdate->isokinetic ?? 0,
                        'userid' => $idSampler,
                        'warna' => $dataUpdate->warna,
                        'note' => $dataUpdate->note,
                        'durasi' => $dataUpdate->durasi,
                        'status' => $dataUpdate->status,
                        'notif' => 0,
                        'urutan' => $dataUpdate->urutan,
                        'updated_by' => $dataUpdate->karyawan,
                        'updated_at' => $this->timestamp,
                        'kendaraan' => $dataUpdate->kendaraan,
                        'parsial' => !empty($dataUpdate->tipe_parsial) ? $dataUpdate->tipe_parsial : null,
                    ];
                    $dbInsertLastId = Jadwal::insertGetId($body);
                }
                // perindahan indukMainJadwal
                /* casenya:
                 * jika yang dirubah adalah main jadwal,semantara jadwal itu  memiliki parsial,maka yang terjadi harusnya adalah data column parisal yang memiliki idInduk yang mati di Update dengan IdInduk Baru terrecord,guna menjaga konsistensi relasi induk dan parsialnya.   
                 */
                Jadwal::whereIn('parsial', $dataUpdate->batch_id)->update(['parsial' => $dbInsertLastId]);
                /* step notifications */
                $sales = JadwalServices::on('no_quotation', $dataUpdate->no_quotation)->getQuotation()->sales_id;
                $salesAtasan = GetAtasan::where('id', $sales)->get()->pluck('id');
                $message = "Perubahan Jadwal No Quotation $dataUpdate->no_quotation telah dirubah dari tanggal $oldDate menjadi $dataUpdate->tanggal";
                Notification::whereIn('id', $salesAtasan)->title('Jadwal Parsial')->message($message)->url('url')->send();

                $noqt = $dataUpdate->no_quotation;
            }

            /* untuk notif */
            // if ($tanggal == $tglNow || $tanggal <= $tglBesok) {
            //     foreach ($samplers as $num => $noc) {
            //         $jadwals = Jadwal::where('no_quotation', $noqt)
            //             ->where('tanggal', $tanggal)
            //             ->where('userid', $noc)
            //             ->where('is_active', true)
            //             ->where('notif', 0)
            //             ->get();

            //         if (!$jadwals->isEmpty()) {
            //             $txt = "Jadwal anda tanggal <b>$tanggal</b> berubah menjadi : \n \n";
            //             foreach ($jadwals as $key => $val) {
            //                 $val->notif = 1;
            //                 $val->save();

            //                 $tes = $val->no_quotation;
            //                 $users = Jadwal::where('no_quotation', $tes)
            //                     ->where('kategori', $val->kategori)
            //                     ->where('durasi', $val->durasi)
            //                     ->where('tanggal', $val->tanggal)
            //                     ->where('is_active', true)
            //                     ->get();

            //                 foreach ($users as $keys => $var) {
            //                     $user[$keys] = $var->sampler;
            //                 }

            //                 $status = 'Sesaat';
            //                 if ($val->durasi == 1)
            //                     $status = '8 Jam';
            //                 if ($val->durasi == 2)
            //                     $status = '1 x 24 Jam';
            //                 if ($val->durasi == 3)
            //                     $status = '2 x 24 Jam';
            //                 if ($val->durasi == 4)
            //                     $status = '3 x 24 Jam';

            //                 $no_qt = $val->no_quotation;
            //                 $pt = $val->nama_perusahaan;
            //                 $alamat = $val->alamat;
            //                 $kat = str_replace("[", "", $val->kategori);
            //                 $kat = str_replace("]", "", $kat);
            //                 $kat = str_replace('"', "", $kat);
            //                 $usr = str_replace('[', "", json_encode($user));
            //                 $usr = str_replace(']', "", $usr);
            //                 $usr = str_replace('"', "", $usr);


            //                 $txt .= "\n Nomor QT : <b>$no_qt</b>";
            //                 $txt .= "\n Nama Client : <b>$pt</b>";
            //                 $txt .= "\n Alamat : <b>$alamat</b>";
            //                 $txt .= "\n Kategori : <b>$kat</b>";
            //                 $txt .= "\n Sampler : <b>$usr</b>";
            //                 $txt .= "\n Durasi : <b>$status</b>";

            //             }
            //             $u = MasterKaryawan::where('id', $noc)->first();
            //             /* debug on
            //             if($u->pin_user!=null){
            //                 $telegram = new Telegram();
            //                 $telegram->send($u->pin_user, $txt);
            //             } */
            //         }
            //     }
            // }
            //update order
            
            if ($dataUpdate->kategori != null) {
                
                $tipe_qt = explode("/", $dataUpdate->no_quotation)[1];
                if ($tipe_qt == 'QTC') {
                    $status_order = QuotationKontrakH::where('no_document', $dataUpdate->no_quotation)->where('is_active', true)->first();
                    if ($status_order != null && $status_order->flag_status == 'ordered') {

                        $orderh = OrderHeader::where('no_document', $dataUpdate->no_quotation)->where('is_active', true)->first();
                        if ($orderh != null) {

                            $array_no_samples = [];
                            foreach ($dataUpdate->kategori as $x => $y) {
                                $pra_no_sample = explode(" - ", $y)[1];
                                $no_samples = $orderh->no_order . '/' . $pra_no_sample;
                                $array_no_samples[] = $no_samples;
                            }

                            $query = OrderDetail::where('id_order_header', $orderh->id)
                            ->where('is_active', true)
                            ->whereIn('no_sampel', $array_no_samples);

                            $exists = $query->exists();;

                            if(!$exists){
                                throw new \Exception("Nomor sampel sudah berubah, silakan hubungi IT untuk pengecekan lebih lanjut.");
                            }else{
                                $updated = OrderDetail::where('id_order_header', $orderh->id)
                                    ->where('is_active', true)
                                    ->whereIn('no_sampel', $array_no_samples)
                                    ->update(['tanggal_sampling' => date('Y-m-d', strtotime($dataUpdate->tanggal))]);
                            }
                        }
                    }

                } else {
                    $status_order = QuotationNonKontrak::where('no_document', $dataUpdate->no_quotation)->where('is_active', true)->first();
                    if ($status_order != null && $status_order->flag_status == 'ordered') {
                        $orderh = OrderHeader::where('no_document', $dataUpdate->no_quotation)->where('is_active', true)->first();
                        if ($orderh != null) {
                            $array_no_samples = [];
                            foreach ($dataUpdate->kategori as $x => $y) {
                                $pra_no_sample = explode(" - ", $y)[1];
                                $no_samples = $orderh->no_order . '/' . $pra_no_sample;
                                $array_no_samples[] = $no_samples;
                            }
                            $query = OrderDetail::where('id_order_header', $orderh->id)
                            ->where('is_active', true)
                            ->whereIn('no_sampel', $array_no_samples);

                            $exists = $query->exists();

                            if(!$exists){
                                throw new \Exception("Nomor sampel sudah berubah, silakan hubungi IT untuk pengecekan lebih lanjut.");
                            }else{
                                $updated = OrderDetail::where('id_order_header', $orderh->id)
                                    ->where('is_active', true)
                                    ->whereIn('no_sampel', $array_no_samples)
                                    ->update(['tanggal_sampling' => date('Y-m-d', strtotime($dataUpdate->tanggal))]);
                            }
                        }
                    }
                }
            }

            // LOGIC UPDATE PSHEADER
            try {
                $orderh = OrderHeader::where('no_document', $dataUpdate->no_quotation)->where('is_active', true)->first();
                
                if ($orderh && !empty($dataUpdate->kategori)) {
                    // 1. Bentuk ulang array no_sampel dari kategori yang diupdate
                    $array_no_samples = [];
                    foreach ($dataUpdate->kategori as $kategori) {
                        $pra_no_sample = explode(" - ", $kategori)[1];
                        $array_no_samples[] = $orderh->no_order . '/' . $pra_no_sample;
                    }

                    // 2. Cari PersiapanSampelHeader yang mengandung salah satu dari no_sampel tersebut
                    $psh = PersiapanSampelHeader::where('is_active', 1)
                        ->where(function($query) use ($array_no_samples) {
                            foreach ($array_no_samples as $sampel) {
                                $query->orWhere('no_sampel', 'like', '%"'.$sampel.'"%');
                            }
                        })
                        ->first();

                    if ($psh) {
                        // 3. Siapin data sampler yang baru
                        $newSamplers = [];
                        foreach ($dataUpdate->sampler as $s) {
                            // Ambil namanya aja, sesuai format 'id,nama'
                            $newSamplers[] = explode(',', $s)[1]; 
                        }
                        
                        // Cek apakah ada perubahan antara oldSamplers dan newSamplers
                        $oldSamplers = explode(',', $psh->sampler_jadwal);
                        $diff = array_diff($newSamplers, $oldSamplers);
                        // 4. Update field sampler_jadwal dan save
                        if (count($diff) > 0) {
                            $psh->no_sampel = json_encode($array_no_samples);
                            $psh->tanggal_sampling = $dataUpdate->tanggal;
                            $psh->sampler_jadwal = implode(',', $newSamplers);
                            $psh->updated_by = $dataUpdate->karyawan;
                            $psh->updated_at = $this->timestamp; // atau Carbon::now()
                            $psh->save();
                        }
                    }
                }
            } catch (\Throwable $th) {
                DB::rollBack();
                throw new Exception('Gagal mengupdate data sampler di Persiapan Sampel Header: ' . $th->getMessage(), 500);
            }

            DB::commit();
            return true;
        } catch (Exception $ex) {
            DB::rollBack();
            throw new Exception($ex->getMessage(), 401);
        }
    }

    // tinggal di test
    public function updateJadwalSPKategori()
    {
        
        $dataUpdate = $this->updateJadwalKategori;

        if (
            $dataUpdate->no_quotation == null ||
            $dataUpdate->nama_perusahaan == null ||
            $dataUpdate->jam_mulai == null ||
            $dataUpdate->jam_selesai == null ||
            $dataUpdate->kategori == null ||
            $dataUpdate->warna == null ||
            $dataUpdate->durasi == null ||
            $dataUpdate->status == null ||
            $dataUpdate->batch_id == null ||
            $dataUpdate->kendaraan == null ||
            $dataUpdate->sampling == null ||
            $dataUpdate->karyawan == null ||
            $dataUpdate->jadwal_id == null ||
            $dataUpdate->tanggal == null ||
            $dataUpdate->sampler == null ||
            $dataUpdate->durasi_lama == null ||
            $dataUpdate->tanggal_lama == null
        ) {
            throw new Exception('No Quotation, Nama Perusahaan, Jam Mulai, Jam Selesai, Kategori, Warna, Note, Durasi, Status, Batch Id, Urutan, Kendaraan, Sampling, Karyawan, Jadwal Id, Tanggal, Sampler, Durasi Lama, Tanggal Lama is required', 401);
        }


        // ===============================SEARCH DATA======================
        $data = Jadwal::where('no_quotation', $dataUpdate->no_quotation)
            ->where('tanggal', $dataUpdate->tanggal)
            ->where('durasi', $dataUpdate->durasi_lama)
            ->where('is_active', true);
        if (!empty($dataUpdate->tipe_parsial)) {
            $data = $data->where('parsial', $dataUpdate->tipe_parsial);
        } else {
            $data = $data->whereNull('parsial');
        }
        $data = $data->get();
        // =================================================================

        $dir = $dataUpdate->sampler;
        $i = 0;
        $lama = COUNT($data);
        $baru = COUNT($dir);
        DB::beginTransaction();
        try {
            try {
                $jadw = Jadwal::where('id', $dataUpdate->jadwal_id)->whereNull('parsial')->where('is_active', true)->first();
                $jadw2 = Jadwal::where('parsial', $dataUpdate->jadwal_id)->where('id', '!=', $dataUpdate->jadwal_id)->where('is_active', true)->get();
                if (!$jadw2->isEmpty()) {
                    if (!empty($jadw)) {
                        foreach ($jadw2 as $key => $val) {
                            foreach (json_decode($val->kategori) as $x => $y) {
                                if (in_array($y, $dataUpdate->kategori)) {
                                    throw new Exception('Ada input kategori yang sama.! ' . $y, 401);
                                }
                            }
                        }
                    } else {
                        foreach ($jadw2 as $key => $val) {
                            $jadw3 = Jadwal::where('id', $val->id)->whereNull('parsial')->where('is_active', true)->first();
                            if ($jadw3) {
                                foreach (json_decode($jadw3->kategori) as $x => $y) {
                                    if (in_array($y, $dataUpdate->kategori)) {
                                        throw new Exception('Ada input kategori yang sama.! ' . $y, 401);
                                    }
                                }
                            }
                            foreach (json_decode($val->kategori) as $x => $y) {
                                if (in_array($y, $dataUpdate->kategori)) {
                                    throw new Exception('Ada input kategori yang sama.! ' . $y, 401);
                                }
                            }
                        }
                    }
                } else {
                    $jadw4 = Jadwal::where('parsial', $dataUpdate->jadwal_id)->where('is_active', true)->get();
                    if (!$jadw4->isEmpty()) {
                        foreach ($jadw as $key => $val) {
                            foreach (json_decode($val->kategori) as $x => $y) {
                                if (in_array($y, $dataUpdate->kategori)) {
                                    throw new Exception('Ada input kategori yang sama.! ' . $y, 401);
                                }
                            }
                        }
                    }
                }
            } catch (Exception $ex) {
                throw new Exception($ex->getMessage(), 401);
            }

            $tipe_qt = explode("/", $dataUpdate->no_quotation)[1];

            $wilayah = null;
            if (explode('/', $dataUpdate->no_quotation)[1] == 'QTC') {
                $cek = QuotationKontrakH::where('no_document', $dataUpdate->no_quotation)->select('wilayah')->first();
                $wilayah = explode('-', $cek->wilayah)[1];
            } else if (explode('/', $dataUpdate->no_quotation)[1] == 'QT') {
                $cek = QuotationNonKontrak::where('no_document', $dataUpdate->no_quotation)->select('wilayah')->first();
                $wilayah = explode('-', $cek->wilayah)[1];
            }

            if ($lama == $baru) { // tidak ada perubahan jumlah sampler
                foreach ($data as $key => $val) {
                    $sampler = explode(',', $dir[$i]);
                    $nama_sampler = $sampler[1];
                    $idSampler = $sampler[0];
                    $samplers[$i] = $idSampler;
                    $i++;
                    $val->no_quotation = $dataUpdate->no_quotation;
                    $val->nama_perusahaan = $dataUpdate->nama_perusahaan;
                    $val->wilayah = $wilayah;
                    $val->alamat = $dataUpdate->alamat;
                    $val->periode = $dataUpdate->periode ?? null;
                    $val->tanggal = $dataUpdate->tanggal;
                    $val->jam = $dataUpdate->jam_mulai;
                    $val->jam_mulai = $dataUpdate->jam_mulai;
                    $val->jam_selesai = $dataUpdate->jam_selesai;
                    $val->kategori = json_encode($dataUpdate->kategori);
                    $val->sampler = $nama_sampler;
                    $val->userid = $idSampler;
                    $val->warna = $dataUpdate->warna;
                    $val->note = $dataUpdate->note;
                    $val->durasi = $dataUpdate->durasi;
                    $val->status = $dataUpdate->status;
                    $val->id_sampling = $dataUpdate->sampling;
                    $val->notif = 0;
                    $val->urutan = $dataUpdate->urutan;
                    $val->kendaraan = $dataUpdate->kendaraan;
                    $val->parsial = !empty($dataUpdate->tipe_parsial) ? $dataUpdate->tipe_parsial : NULL;
                    $val->updated_at = $this->timestamp;
                    $val->updated_by = $dataUpdate->karyawan;
                    $val->id_cabang = $dataUpdate->id_cabang;
                    $val->save();

                    $noqt = $val->no_quotation;
                }
            } else { // ada perubahan jumlah sampler
                $dbInsertLastId = null;
                if ($dataUpdate->tipe_parsial == null) {
                    Jadwal::whereIn('id', $dataUpdate->batch_id)
                        ->update([
                            'is_active' => false,
                            'updated_at' => $this->timestamp,
                            'updated_by' => $dataUpdate->karyawan,
                        ]);
                } else {
                    Jadwal::whereIn('id', $dataUpdate->batch_id)
                        ->update([
                            'is_active' => false,
                            'updated_at' => $this->timestamp,
                            'updated_by' => $dataUpdate->karyawan,
                        ]);
                }

                foreach ($dir as $key => $value) {
                    $sampler = explode(',', $value);
                    $nama_sampler = $sampler[1];
                    $idSampler = $sampler[0];
                    $samplers[] = $idSampler;
                    $body = [
                        'no_quotation' => $dataUpdate->no_quotation,
                        'nama_perusahaan' => $dataUpdate->nama_perusahaan,
                        'id_sampling' => $dataUpdate->sampling,
                        'id_cabang' => $dataUpdate->id_cabang,
                        'wilayah' => $wilayah,
                        'alamat' => $dataUpdate->alamat,
                        'tanggal' => $dataUpdate->tanggal,
                        'periode' => $dataUpdate->periode ?? null,
                        'jam' => $dataUpdate->jam_mulai,
                        'jam_mulai' => $dataUpdate->jam_mulai,
                        'jam_selesai' => $dataUpdate->jam_selesai,
                        'kategori' => json_encode($dataUpdate->kategori),
                        'sampler' => $nama_sampler,
                        'userid' => $idSampler,
                        'warna' => $dataUpdate->warna,
                        'note' => $dataUpdate->note,
                        'durasi' => $dataUpdate->durasi,
                        'status' => $dataUpdate->status,
                        'notif' => 0,
                        'urutan' => $dataUpdate->urutan,
                        'updated_by' => $dataUpdate->karyawan,
                        'updated_at' => $this->timestamp,
                        'kendaraan' => $dataUpdate->kendaraan,
                        'parsial' => !empty($dataUpdate->tipe_parsial) ? $dataUpdate->tipe_parsial : NULL
                    ];
                    $dbInsertLastId = Jadwal::insertGetId($body);
                }
                // perindahan indukMainJadwal
                /* casenya:
                 * jika yang dirubah adalah main jadwal,semantara jadwal itu  memiliki parsial,maka yang terjadi harusnya adalah data column parisal yang memiliki idInduk yang mati di Update dengan IdInduk Baru terrecord,guna menjaga konsistensi relasi induk dan parsialnya.   
                 */
                Jadwal::whereIn('parsial', $dataUpdate->batch_id)->update(['parsial' => $dbInsertLastId]);
                $noqt = $dataUpdate->no_quotation;
            }

            /* notif */
            // if ($tanggal == $tglNow || $tanggal <= $tglBesok) {
            //     foreach ($samplers as $num => $noc) {
            //         $jadwals = Jadwal::where('no_quotation', $noqt)
            //             ->where('tanggal', $tanggal)
            //             ->where('userid', $noc)
            //             ->where('is_active', true)
            //             ->where('notif', 0)
            //             ->where('flag', 0)
            //             ->get();

            //         if (!$jadwals->isEmpty()) {
            //             $txt = "Jadwal anda tanggal <b>$tanggal</b> berubah menjadi : \n \n";
            //             foreach ($jadwals as $key => $val) {
            //                 $val->notif = 1;
            //                 $val->save();

            //                 $tes = $val->no_quotation;
            //                 $users123 = Jadwal::where('no_quotation', $tes)
            //                     ->where('kategori', $val->kategori)
            //                     ->where('tanggal', $val->tanggal)
            //                     ->where('is_active', true)
            //                     ->get();
            //                 foreach ($users123 as $keys => $var) {
            //                     $user123[$keys] = $var->sampler;
            //                 }

            //                 $status = 'Sesaat';
            //                 if ($val->durasi == 1)
            //                     $status = '8 Jam';
            //                 if ($val->durasi == 2)
            //                     $status = '1 x 24 Jam';
            //                 if ($val->durasi == 3)
            //                     $status = '2 x 24 Jam';
            //                 if ($val->durasi == 4)
            //                     $status = '3 x 24 Jam';

            //                 $no_qt = $val->no_quotation;
            //                 $pt = $val->nama_perusahaan;
            //                 $alamat = $val->alamat;
            //                 $kat = str_replace("[", "", $val->kategori);
            //                 $kat = str_replace("]", "", $kat);
            //                 $kat = str_replace('"', "", $kat);
            //                 $usr = str_replace('[', "", json_encode($user123));
            //                 $usr = str_replace(']', "", $usr);
            //                 $usr = str_replace('"', "", $usr);


            //                 $txt .= "\n Nomor QT : <b>$no_qt</b>";
            //                 $txt .= "\n Nama Client : <b>$pt</b>";
            //                 $txt .= "\n Alamat : <b>$alamat</b>";
            //                 $txt .= "\n Kategori : <b>$kat</b>";
            //                 $txt .= "\n Sampler : <b>$usr</b>";
            //                 $txt .= "\n Durasi : <b>$status</b>";

            //             }
            //             /* debug on
            //             $conn_user = new User;
            //             $u = $conn_user->setConnection($this->db)->where('id', $noc)->first();
            //             if($u->pin_user!=null){
            //                 $telegram = new Telegram();
            //                 $telegram->send($u->pin_user, $txt);
            //             } */
            //         }
            //     }
            // }

            // update
            if ($dataUpdate->kategori != null) {
                $tipe_qt = explode("/", $dataUpdate->no_quotation)[1];
                //udpate order
                if ($tipe_qt == 'QTC') {
                    try {
                        $status_order = QuotationKontrakH::where('no_document', $dataUpdate->no_quotation)->where('is_active', true)->first();
                        if ($status_order != null && $status_order->flag_status == 'ordered') {
                            $orderh = OrderHeader::where('no_document', $dataUpdate->no_quotation)->where('is_active', true)->first();
                            if ($orderh != null) {
                                $array_no_samples = [];
                                foreach ($dataUpdate->kategori as $x => $y) {
                                    $pra_no_sample = explode(" - ", $y)[1];
                                    $no_samples = $orderh->no_order . '/' . $pra_no_sample;
                                    $array_no_samples[] = $no_samples;
                                }

                                $query = OrderDetail::where('id_order_header', $orderh->id)
                                ->where('is_active', true)
                                ->whereIn('no_sampel', $array_no_samples);

                                $exists = $query->exists();;

                                if(!$exists){
                                    throw new \Exception("Nomor sampel sudah berubah, silakan hubungi IT untuk pengecekan lebih lanjut.");
                                }else{
                                    $updated = OrderDetail::where('id_order_header', $orderh->id)
                                        ->where('is_active', true)
                                        ->whereIn('no_sampel', $array_no_samples)
                                        ->update(['tanggal_sampling' => date('Y-m-d', strtotime($dataUpdate->tanggal))]);
                                }
                            }
                        }
                    } catch (Exception $ex) {
                        throw new Exception($ex->getMessage(), 401);
                    }
                } else {
                    try {
                        $status_order = QuotationNonKontrak::where('no_document', $dataUpdate->no_quotation)->where('is_active', true)->first();
                        if ($status_order != null && $status_order->flag_status == 'ordered') {
                            $orderh = OrderHeader::where('no_document', $dataUpdate->no_quotation)->where('is_active', true)->first();
                            if ($orderh != null) {
                                $array_no_samples = [];
                                foreach ($dataUpdate->kategori as $x => $y) {
                                    $pra_no_sample = explode(" - ", $y)[1];
                                    $no_samples = $orderh->no_order . '/' . $pra_no_sample;
                                    $array_no_samples[] = $no_samples;
                                }

                                $query = OrderDetail::where('id_order_header', $orderh->id)
                                ->where('is_active', true)
                                ->whereIn('no_sampel', $array_no_samples);

                                $exists = $query->exists();

                                if(!$exists){
                                    throw new \Exception("Nomor sampel sudah berubah, silakan hubungi IT untuk pengecekan lebih lanjut.");
                                }else{
                                    $updated = OrderDetail::where('id_order_header', $orderh->id)
                                        ->where('is_active', true)
                                        ->whereIn('no_sampel', $array_no_samples)
                                        ->update(['tanggal_sampling' => date('Y-m-d', strtotime($dataUpdate->tanggal))]);
                                }
                            }
                        }
                    } catch (Exception $ex) {
                        throw new Exception($ex->getMessage(), 401);
                    }
                }
            }

            // LOGIC UPDATE PSHEADER
            try {
                $orderh = OrderHeader::where('no_document', $dataUpdate->no_quotation)->where('is_active', true)->first();
                
                if ($orderh && !empty($dataUpdate->kategori)) {
                    // 1. Bentuk ulang array no_sampel dari kategori yang diupdate
                    $array_no_samples = [];
                    foreach ($dataUpdate->kategori as $kategori) {
                        $pra_no_sample = explode(" - ", $kategori)[1];
                        $array_no_samples[] = $orderh->no_order . '/' . $pra_no_sample;
                    }

                    // 2. Cari PersiapanSampelHeader yang mengandung salah satu dari no_sampel tersebut
                    $psh = PersiapanSampelHeader::where('is_active', 1)
                        ->where(function($query) use ($array_no_samples) {
                            foreach ($array_no_samples as $sampel) {
                                $query->orWhere('no_sampel', 'like', '%"'.$sampel.'"%');
                            }
                        })
                        ->first();

                    if ($psh) {
                        // 3. Siapin data sampler yang baru
                        $newSamplers = [];
                        foreach ($dataUpdate->sampler as $s) {
                            // Ambil namanya aja, sesuai format 'id,nama'
                            $newSamplers[] = explode(',', $s)[1]; 
                        }

                        // Cek apakah ada perubahan antara oldSamplers dan newSamplers
                        $oldSamplers = explode(',', $psh->sampler_jadwal);
                        $diff = array_diff($newSamplers, $oldSamplers);
                        // 4. Update field sampler_jadwal dan save
                        if (count($diff) > 0) {
                            $psh->no_sampel = json_encode($array_no_samples);
                            $psh->tanggal_sampling = $dataUpdate->tanggal;
                            $psh->sampler_jadwal = implode(',', $newSamplers);
                            $psh->updated_by = $dataUpdate->karyawan;
                            $psh->updated_at = $this->timestamp; // atau Carbon::now()
                            $psh->save();
                        }
                    }
                }
            } catch (\Throwable $th) {
                DB::rollBack();
                throw new Exception('Gagal mengupdate data sampler di Persiapan Sampel Header: ' . $th->getMessage(), 500);
            }

            DB::commit();
            return true;
        } catch (Exception $ex) {
            DB::rollback();
            throw new Exception($ex->getMessage(), 401);
        }
    }

    // tinggal di test 
    // 28-07-2025
    // public function addJadwalSP()
    // { // add jadwal baru
      
    //     $dataAdd = $this->addJadwal;
        
    //     if (
    //         $dataAdd->id_sampling == null ||
    //         $dataAdd->no_quotation == null ||
    //         $dataAdd->karyawan == null ||
    //         $dataAdd->no_document == null ||
    //         $dataAdd->tanggal == null ||
    //         $dataAdd->sampler == null ||
    //         $dataAdd->kategori == null ||
    //         $dataAdd->warna == null ||
    //         $dataAdd->durasi == null ||
    //         $dataAdd->status == null ||
    //         $dataAdd->nama_perusahaan == null ||
    //         $dataAdd->alamat == null ||
    //         $dataAdd->driver == null
    //     ) {
    //         throw new Exception("Id Sampling, No Quotation, Karyawan, No Document, Tanggal, Sampler, Kategori, Durasi, Status, Nama Perusahaan, Alamat is required when add jadwal", 401);
    //     }
    

    //     DB::beginTransaction();
    //     try {
    //         /* 
    //          *step non aktif jadwal sebelumnya jika ada
    //          *berlaku jika no dokumen sampling plan sudah naik menjadi R
    //          *berlaku jika no dokumen sampling plan sudah di jadwalkan
    //          */

    //         $no_document = $dataAdd->no_document;
             
    //         if (preg_match('/R[0-9]+$/', $no_document, $matches, PREG_OFFSET_CAPTURE)) {
    //             $originalNoDocument = substr($no_document, 0, $matches[0][1]);
    //             $documents = SamplingPlan::where('no_quotation', $dataAdd->no_quotation)
    //                 ->where('no_document', 'like', "{$originalNoDocument}%")
    //                 ->where('no_document', '<>', $no_document)
    //                 ->orderBy('no_quotation')
    //                 ->pluck('id');

    //             if ($documents->isNotEmpty()) { // Hanya lanjutkan jika ada dokumen yang ditemukan
    //                 $noQt = explode('/', $dataAdd->no_quotation);
    //                 $updateQuery = Jadwal::whereIn('id_sampling', $documents);

    //                 if (isset($noQt[1]) && $noQt[1] === 'QT') {
    //                     $updateQuery->where('no_quotation', $dataAdd->no_quotation);
    //                 }

    //                 $updateQuery->update(['is_active' => false]);
    //             }
    //         }
    //         // $no_document = $dataAdd->no_document;
    //         // $lastRPosition = strrpos($no_document, 'R');

    //         // if ($lastRPosition !== false) {
    //         //     $originalNoDocument = substr($no_document, 0, $lastRPosition);
    //         //     $documents = SamplingPlan::where('no_quotation', $dataAdd->no_quotation)
    //         //         ->where('no_document', 'like', "{$originalNoDocument}%")
    //         //         ->where('no_document', '<>', $no_document)
    //         //         ->orderBy('no_quotation')
    //         //         ->pluck('id');

    //         //     if ($documents->isNotEmpty()) { // Hanya lanjutkan jika ada dokumen yang ditemukan
    //         //         $noQt = explode('/', $dataAdd->no_quotation);
    //         //         $updateQuery = Jadwal::whereIn('id_sampling', $documents);

    //         //         if (isset($noQt[1]) && $noQt[1] === 'QT') {
    //         //             $updateQuery->where('no_quotation', $dataAdd->no_quotation);
    //         //         }

    //         //         $updateQuery->update(['is_active' => false]);
    //         //     }
    //         // }


    //         $wilayah = null;
    //         if (explode('/', $dataAdd->no_quotation)[1] == 'QTC') {
    //             $cek = QuotationKontrakH::where('no_document', $dataAdd->no_quotation)->select('wilayah')->first();
    //             $wilayah = explode('-', $cek->wilayah)[1];
    //         } else if (explode('/', $dataAdd->no_quotation)[1] == 'QT') {
    //             $cek = QuotationNonKontrak::where('no_document', $dataAdd->no_quotation)->select('wilayah')->first();
    //             $wilayah = explode('-', $cek->wilayah)[1];
    //         }

    //         // bentuk data
    //         $temBody = [];
          
    //         for ($i = 0; $i < count($dataAdd->tanggal); $i++) {
    //             $temBody[] = [
    //                 "no_quotation" => $dataAdd->no_quotation,
    //                 "quotation_id" => $dataAdd->quotation_id,
    //                 "no_document" => $dataAdd->no_document,
    //                 "nama_perusahaan" => $dataAdd->nama_perusahaan,
    //                 "id_sampling" => $dataAdd->id_sampling,
    //                 'id_cabang' => $dataAdd->id_cabang,
    //                 "alamat" => $dataAdd->alamat,
    //                 "tanggal" => Carbon::parse($dataAdd->tanggal[$i])->format("Y-m-d"),
    //                 "periode" => $dataAdd->periode != "" ? $dataAdd->periode : null,
    //                 "jam" => $dataAdd->jam_mulai[$i],
    //                 "jam_mulai" => $dataAdd->jam_mulai[$i],
    //                 "jam_selesai" => $dataAdd->jam_selesai[$i],
    //                 "kategori" => json_encode($dataAdd->kategori[$i]),
    //                 "sampler" => $dataAdd->sampler[$i],
    //                 "warna" => $dataAdd->warna[$i],
    //                 "note" => $dataAdd->note[$i],
    //                 "durasi" => $dataAdd->durasi[$i],
    //                 "status" => $dataAdd->status[$i],
    //                 "driver" => $dataAdd->driver ?? null,
    //                 "created_by" => $dataAdd->karyawan,
    //                 "created_at" => $this->timestamp,
    //                 "kendaraan" => $dataAdd->kendaraan[$i] ?? null,
    //                 "wilayah" => $wilayah,
    //             ];
    //         }
          
    //         // pengolahan data
    //         $firstJadwalId = null; // Menyimpan ID untuk referensi parsial jika diperlukan
    //         foreach ($temBody as $key => $val) {
    //             $keys=$key;
    //             if ($key == 0) { // Jika data pertama
    //                 foreach ($val['sampler'] as $key => $value) {
    //                     $commonData = [
    //                         'nama_perusahaan' => $val['nama_perusahaan'],
    //                         'no_quotation' => $val['no_quotation'],
    //                         'alamat' => $val['alamat'],
    //                         'tanggal' => $val['tanggal'],
    //                         'periode' => $val['periode'],
    //                         'jam' => $val['jam_mulai'],
    //                         'jam_mulai' => $val['jam_mulai'],
    //                         'jam_selesai' => $val['jam_selesai'],
    //                         'kategori' => $val['kategori'],
    //                         'warna' => $val['warna'],
    //                         'durasi' => $val['durasi'],
    //                         'status' => $val['status'],
    //                         'created_by' => $val['created_by'],
    //                         'created_at' => $val['created_at'],
    //                         'note' => $val['note'],
    //                         'kendaraan' => $val['kendaraan'],
    //                         'id_sampling' => $val['id_sampling'],
    //                         'id_cabang' => $val['id_cabang'][$keys],
    //                         'wilayah' => $val['wilayah'],
    //                         'driver' => $val['driver'],
    //                         'sampler' => explode(',', $value)[1],
    //                         'userid' => explode(',', $value)[0],
    //                     ];

    //                     // Menyimpan data pertama langsung ke database
    //                     $firstJadwalId = Jadwal::insertGetId($commonData);
    //                 }
    //             } else { //jika memiliki parsial
    //                 foreach ($val['sampler'] as $key => $value) {

    //                     $commonData = [
    //                         'nama_perusahaan' => $val['nama_perusahaan'],
    //                         'no_quotation' => $val['no_quotation'],
    //                         'alamat' => $val['alamat'],
    //                         'tanggal' => $val['tanggal'],
    //                         'periode' => $val['periode'],
    //                         'jam' => $val['jam_mulai'],
    //                         'jam_mulai' => $val['jam_mulai'],
    //                         'jam_selesai' => $val['jam_selesai'],
    //                         'kategori' => $val['kategori'],
    //                         'warna' => $val['warna'],
    //                         'durasi' => $val['durasi'],
    //                         'status' => $val['status'],
    //                         'created_by' => $val['created_by'],
    //                         'created_at' => $val['created_at'],
    //                         'note' => $val['note'],
    //                         'kendaraan' => $val['kendaraan'],
    //                         'id_sampling' => $val['id_sampling'],
    //                         'id_cabang' => $val['id_cabang'][$keys],
    //                         'wilayah' => $val['wilayah'],
    //                         'driver' => $val['driver'],
    //                         'sampler' => explode(',', $value)[1],
    //                         'userid' => explode(',', $value)[0],
    //                         'parsial' => $firstJadwalId,
    //                     ];
    //                     Jadwal::insert($commonData);
    //                 }
    //             }
    //         }
    //         //update request sampling
    //         $cek = SamplingPlan::where('id', $dataAdd->id_sampling)->first();
    //         if (!is_null($cek)) {
    //             $cek->status = 1;
    //             $cek->status_jadwal = 'jadwal';
    //             $cek->save();
    //         }

    //         /* step notifications */
    //         $sales = JadwalServices::on('no_quotation', $dataAdd->no_quotation)->getQuotation()->sales_id;
    //         $salesAtasan = GetAtasan::where('id', $sales)->get()->pluck('id');
    //         $message = "Jadwal No Quotation $dataAdd->no_quotation Sudah Melakukan Jadwal Parsial Di Tanggal " . implode(', ', $dataAdd->tanggal);
    //         Notification::whereIn('id', $salesAtasan)->title('Jadwal Parsial')->message($message)->url('url')->send();
    //         DB::commit();
    //         return true;
    //     } catch (Exception $ex) {
    //         DB::rollback();
    //         throw new Exception($ex->getMessage() . ' Line: ' . $ex->getLine() . ' File: ' . $ex->getFile(), 401);
    //     }
    // }
    
    public function addJadwalSP()
    { // add jadwal baru
        $dataAdd = $this->addJadwal;
       
        if (
            $dataAdd->id_sampling == null ||
            $dataAdd->no_quotation == null ||
            $dataAdd->karyawan == null ||
            $dataAdd->no_document == null ||
            $dataAdd->tanggal == null ||
            $dataAdd->sampler == null ||
            $dataAdd->kategori == null ||
            $dataAdd->warna == null ||
            $dataAdd->durasi == null ||
            $dataAdd->status == null ||
            $dataAdd->nama_perusahaan == null ||
            $dataAdd->alamat == null||
            $dataAdd->isokinetic == null||
            $dataAdd->pendampingan_k3 == null
        ) {
            throw new Exception("Id Sampling, No Quotation, Karyawan, No Document, Tanggal, Sampler, Kategori, Durasi, Status, Nama Perusahaan, Alamat is required when add jadwal", 401);
        }

        DB::beginTransaction();
        try {
            /* 
             *step non aktif jadwal sebelumnya jika ada
             *berlaku jika no dokumen sampling plan sudah naik menjadi R
             *berlaku jika no dokumen sampling plan sudah di jadwalkan
             */

            $no_document = $dataAdd->no_document;
            if (preg_match('/R[0-9]+$/', $no_document, $matches, PREG_OFFSET_CAPTURE)) {
                $originalNoDocument = substr($no_document, 0, $matches[0][1]);
                $documents = SamplingPlan::where('no_quotation', $dataAdd->no_quotation)
                    ->where('no_document', 'like', "{$originalNoDocument}%")
                    ->where('no_document', '<>', $no_document)
                    ->orderBy('no_quotation')
                    ->pluck('id');

                if ($documents->isNotEmpty()) { // Hanya lanjutkan jika ada dokumen yang ditemukan
                    $noQt = explode('/', $dataAdd->no_quotation);
                    $updateQuery = Jadwal::whereIn('id_sampling', $documents);

                    if (isset($noQt[1]) && $noQt[1] === 'QT') {
                        $updateQuery->where('no_quotation', $dataAdd->no_quotation);
                    }

                    $updateQuery->update(['is_active' => false]);
                }
            }
            // $no_document = $dataAdd->no_document;
            // $lastRPosition = strrpos($no_document, 'R');

            // if ($lastRPosition !== false) {
            //     $originalNoDocument = substr($no_document, 0, $lastRPosition);
            //     $documents = SamplingPlan::where('no_quotation', $dataAdd->no_quotation)
            //         ->where('no_document', 'like', "{$originalNoDocument}%")
            //         ->where('no_document', '<>', $no_document)
            //         ->orderBy('no_quotation')
            //         ->pluck('id');

            //     if ($documents->isNotEmpty()) { // Hanya lanjutkan jika ada dokumen yang ditemukan
            //         $noQt = explode('/', $dataAdd->no_quotation);
            //         $updateQuery = Jadwal::whereIn('id_sampling', $documents);

            //         if (isset($noQt[1]) && $noQt[1] === 'QT') {
            //             $updateQuery->where('no_quotation', $dataAdd->no_quotation);
            //         }

            //         $updateQuery->update(['is_active' => false]);
            //     }
            // }


            $wilayah = null;
            if (explode('/', $dataAdd->no_quotation)[1] == 'QTC') {
                $cek = QuotationKontrakH::where('no_document', $dataAdd->no_quotation)->select('wilayah')->first();
                $wilayah = explode('-', $cek->wilayah)[1];
            } else if (explode('/', $dataAdd->no_quotation)[1] == 'QT') {
                $cek = QuotationNonKontrak::where('no_document', $dataAdd->no_quotation)->select('wilayah')->first();
                $wilayah = explode('-', $cek->wilayah)[1];
            }
            // bentuk data
            
            $temBody = [];
            for ($i = 0; $i < count($dataAdd->tanggal); $i++) {
                $temBody[] = [
                    "no_quotation" => $dataAdd->no_quotation,
                    "quotation_id" => $dataAdd->quotation_id,
                    "no_document" => $dataAdd->no_document,
                    "nama_perusahaan" => $dataAdd->nama_perusahaan,
                    "id_sampling" => $dataAdd->id_sampling,
                    "id_cabang" => $dataAdd->id_cabang,
                    "alamat" => $dataAdd->alamat,
                    "tanggal" => Carbon::parse($dataAdd->tanggal[$i])->format("Y-m-d"),
                    "periode" => $dataAdd->periode != "" ? $dataAdd->periode : null,
                    "jam" => $dataAdd->jam_mulai[$i],
                    "jam_mulai" => $dataAdd->jam_mulai[$i],
                    "jam_selesai" => $dataAdd->jam_selesai[$i],
                    "kategori" => json_encode($dataAdd->kategori[$i]),
                    "sampler" => $dataAdd->sampler[$i],
                    "warna" => $dataAdd->warna[$i],
                    "driver" => $dataAdd->driver[$i] ?? null,

                    "note" => $dataAdd->note[$i],
                    "durasi" => $dataAdd->durasi[$i],
                    "status" => $dataAdd->status[$i],
                    "created_by" => $dataAdd->karyawan,
                    "created_at" => $this->timestamp,
                    "kendaraan" => $dataAdd->kendaraan[$i] ?? null,
                    "wilayah" => $wilayah,
                    "isokinetic" => $dataAdd->isokinetic[$i] ?? 0,
                    "pendampingan_k3" => $dataAdd->pendampingan_k3[$i] ?? 0,
                ];
            }
            
            
            // pengolahan data
            $firstJadwalId = null; // Menyimpan ID untuk referensi parsial jika diperlukan
            foreach ($temBody as $key => $val) {
                $keys = $key;
                if ($key == 0) { // Jika data pertama
                    
                    foreach ($val['sampler'] as $key => $value) {
                        $commonData = [
                            'nama_perusahaan' => $val['nama_perusahaan'],
                            'no_quotation' => $val['no_quotation'],
                            'alamat' => $val['alamat'],
                            'tanggal' => $val['tanggal'],
                            'periode' => $val['periode'],
                            'jam' => $val['jam_mulai'],
                            'jam_mulai' => $val['jam_mulai'],
                            'jam_selesai' => $val['jam_selesai'],
                            'kategori' => $val['kategori'],
                            'warna' => $val['warna'],
                            'durasi' => $val['durasi'],
                            'driver' => $val['driver'],
                            'status' => $val['status'],
                            'created_by' => $val['created_by'],
                            'created_at' => $val['created_at'],
                            'note' => $val['note'],
                            'kendaraan' => $val['kendaraan'],
                            'id_sampling' => $val['id_sampling'],
                            'id_cabang' => $val['id_cabang'][$keys],
                            'wilayah' => $val['wilayah'],
                            'isokinetic' => $val['isokinetic'][0] ?? 0,
                            'pendampingan_k3' => $val['pendampingan_k3'][$keys] ?? 0,
                            'sampler' => explode(',', $value)[1],
                            'userid' => explode(',', $value)[0],
                        ];
                        
                        // Menyimpan data pertama langsung ke database
                        $firstJadwalId = Jadwal::insertGetId($commonData);
                    }
                } else { //jika memiliki parsial
                    
                    foreach ($val['sampler'] as $key => $value) {

                        $commonData = [
                            'nama_perusahaan' => $val['nama_perusahaan'],
                            'no_quotation' => $val['no_quotation'],
                            'alamat' => $val['alamat'],
                            'tanggal' => $val['tanggal'],
                            'periode' => $val['periode'],
                            'jam' => $val['jam_mulai'],
                            'jam_mulai' => $val['jam_mulai'],
                            'jam_selesai' => $val['jam_selesai'],
                            'kategori' => $val['kategori'],
                            'warna' => $val['warna'],
                            'durasi' => $val['durasi'],
                            'driver' => $val['driver'],
                            'status' => $val['status'],
                            'created_by' => $val['created_by'],
                            'created_at' => $val['created_at'],
                            'note' => $val['note'],
                            'kendaraan' => $val['kendaraan'],
                            'id_sampling' => $val['id_sampling'],
                            'id_cabang' => $val['id_cabang'][$keys],
                            'wilayah' => $val['wilayah'],
                            'isokinetic' => $val['isokinetic'][0] ?? 0,
                            'pendampingan_k3' => $val['pendampingan_k3'][0] ?? 0,
                            'sampler' => explode(',', $value)[1],
                            'userid' => explode(',', $value)[0],
                            'parsial' => $firstJadwalId,
                        ];
                        
                        Jadwal::insert($commonData);
                    }
                }
            }

            //update request sampling
            $cek = SamplingPlan::where('id', $dataAdd->id_sampling)->first();
            if (!is_null($cek)) {
                $cek->status = 1;
                $cek->status_jadwal = 'jadwal';
                $cek->save();
            }

            /* step notifications */
            $sales = JadwalServices::on('no_quotation', $dataAdd->no_quotation)->getQuotation()->sales_id;
            $salesAtasan = GetAtasan::where('id', $sales)->get()->pluck('id');
            $message = "Jadwal No Quotation $dataAdd->no_quotation Sudah Melakukan Jadwal Parsial Di Tanggal " . implode(', ', $dataAdd->tanggal);
            //Notification::whereIn('id', $salesAtasan)->title('Jadwal Parsial')->message($message)->url('url')->send();
            DB::commit();
            return true;
        } catch (Exception $ex) {
            DB::rollback();
            throw new Exception($ex->getMessage() . ' Line: ' . $ex->getLine() . ' File: ' . $ex->getFile(), 401);
        }
    }
    // public function addJadwalSP()
    // { // add jadwal baru
    //     $dataAdd = $this->addJadwal;

    //     if (
    //         $dataAdd->id_sampling == null ||
    //         $dataAdd->no_quotation == null ||
    //         $dataAdd->karyawan == null ||
    //         $dataAdd->no_document == null ||
    //         $dataAdd->tanggal == null ||
    //         $dataAdd->sampler == null ||
    //         $dataAdd->kategori == null ||
    //         $dataAdd->warna == null ||
    //         $dataAdd->durasi == null ||
    //         $dataAdd->status == null ||
    //         $dataAdd->nama_perusahaan == null ||
    //         $dataAdd->id_cabang == null ||
    //         $dataAdd->alamat == null
    //     ) {
    //         throw new Exception("Id Sampling, No Quotation, Karyawan, No Document, Tanggal, Cabang ,Sampler, Kategori, Durasi, Status, Nama Perusahaan, Alamat is required when add jadwal", 401);
    //     }

    //     DB::beginTransaction();
    //     try {
    //         /* 
    //          *step non aktif jadwal sebelumnya jika ada
    //          *berlaku jika no dokumen sampling plan sudah naik menjadi R
    //          *berlaku jika no dokumen sampling plan sudah di jadwalkan
    //          */

    //         $no_document = $dataAdd->no_document;
    //         if (preg_match('/R[0-9]+$/', $no_document, $matches, PREG_OFFSET_CAPTURE)) {
    //             $originalNoDocument = substr($no_document, 0, $matches[0][1]);
    //             $documents = SamplingPlan::where('no_quotation', $dataAdd->no_quotation)
    //                 ->where('no_document', 'like', "{$originalNoDocument}%")
    //                 ->where('no_document', '<>', $no_document)
    //                 ->orderBy('no_quotation')
    //                 ->pluck('id');

    //             if ($documents->isNotEmpty()) { // Hanya lanjutkan jika ada dokumen yang ditemukan
    //                 $noQt = explode('/', $dataAdd->no_quotation);
    //                 $updateQuery = Jadwal::whereIn('id_sampling', $documents);

    //                 if (isset($noQt[1]) && $noQt[1] === 'QT') {
    //                     $updateQuery->where('no_quotation', $dataAdd->no_quotation);
    //                 }

    //                 $updateQuery->update(['is_active' => false]);
    //             }
    //         }

    //         $wilayah = null;
    //         if (explode('/', $dataAdd->no_quotation)[1] == 'QTC') {
    //             $cek = QuotationKontrakH::where('no_document', $dataAdd->no_quotation)->select('wilayah')->first();
    //             $wilayah = explode('-', $cek->wilayah)[1];
    //         } else if (explode('/', $dataAdd->no_quotation)[1] == 'QT') {
    //             $cek = QuotationNonKontrak::where('no_document', $dataAdd->no_quotation)->select('wilayah')->first();
    //             $wilayah = explode('-', $cek->wilayah)[1];
    //         }


    //         // bentuk data
    //         $temBody = [];
    //         for ($i = 0; $i < count($dataAdd->tanggal); $i++) {
    //             $temBody[] = [
    //                 "no_quotation" => $dataAdd->no_quotation,
    //                 "quotation_id" => $dataAdd->quotation_id,
    //                 "no_document" => $dataAdd->no_document,
    //                 "nama_perusahaan" => $dataAdd->nama_perusahaan,
    //                 "id_sampling" => $dataAdd->id_sampling,
    //                 "id_cabang" => $dataAdd->id_cabang[$i],
    //                 "alamat" => $dataAdd->alamat,
    //                 "tanggal" => Carbon::parse($dataAdd->tanggal[$i])->format("Y-m-d"),
    //                 "periode" => $dataAdd->periode != "" ? $dataAdd->periode : null,
    //                 "jam" => $dataAdd->jam_mulai[$i],
    //                 "jam_mulai" => $dataAdd->jam_mulai[$i],
    //                 "jam_selesai" => $dataAdd->jam_selesai[$i],
    //                 "kategori" => json_encode($dataAdd->kategori[$i]),
    //                 "sampler" => $dataAdd->sampler[$i],
    //                 "warna" => $dataAdd->warna[$i],
    //                 // "driver" => $dataAdd->driver[$i] ?? null,
    //                 "note" => $dataAdd->note[$i],
    //                 "durasi" => $dataAdd->durasi[$i],
    //                 "status" => $dataAdd->status[$i],
    //                 "created_by" => $dataAdd->karyawan,
    //                 "created_at" => $this->timestamp,
    //                 "kendaraan" => $dataAdd->kendaraan[$i] ?? null,
    //                 "wilayah" => $wilayah,
    //             ];
    //         }


    //         $firstJadwalId = null;
    //         foreach ($temBody as $key => $val) {
    //             if ($key == 0) { // Jika data pertama

    //                 foreach ($val['sampler'] as $key => $value) {
    //                     $commonData = [
    //                         'nama_perusahaan' => $val['nama_perusahaan'],
    //                         'no_quotation' => $val['no_quotation'],
    //                         'alamat' => $val['alamat'],
    //                         'tanggal' => $val['tanggal'],
    //                         'id_cabang' => $val['id_cabang'],
    //                         'periode' => $val['periode'],
    //                         'jam' => $val['jam_mulai'],
    //                         'jam_mulai' => $val['jam_mulai'],
    //                         'jam_selesai' => $val['jam_selesai'],
    //                         'kategori' => $val['kategori'],
    //                         'warna' => $val['warna'],
    //                         // 'driver' => $val['driver'] ?? null,
    //                         'durasi' => $val['durasi'],
    //                         'status' => $val['status'],
    //                         'created_by' => $val['created_by'],
    //                         'created_at' => $val['created_at'],
    //                         'note' => $val['note'],
    //                         'kendaraan' => $val['kendaraan'],
    //                         'id_sampling' => $val['id_sampling'],
    //                         'wilayah' => $val['wilayah'],
    //                         'sampler' => explode(',', $value)[1],
    //                         'userid' => explode(',', $value)[0],
    //                     ];

    //                     // Menyimpan data pertama langsung ke database
    //                     $firstJadwalId = Jadwal::insertGetId($commonData);
    //                 }

    //             } else { //jika memiliki parsial
    //                 foreach ($val['sampler'] as $key => $value) {

    //                     $commonData = [
    //                         'nama_perusahaan' => $val['nama_perusahaan'],
    //                         'no_quotation' => $val['no_quotation'],
    //                         'alamat' => $val['alamat'],
    //                         'tanggal' => $val['tanggal'],
    //                         'id_cabang' => $val['id_cabang'],
    //                         'periode' => $val['periode'],
    //                         'jam' => $val['jam_mulai'],
    //                         'jam_mulai' => $val['jam_mulai'],
    //                         'jam_selesai' => $val['jam_selesai'],
    //                         'kategori' => $val['kategori'],
    //                         'warna' => $val['warna'],
    //                         // 'driver' => $val['driver'] ?? null,
    //                         'durasi' => $val['durasi'],
    //                         'status' => $val['status'],
    //                         'created_by' => $val['created_by'],
    //                         'created_at' => $val['created_at'],
    //                         'note' => $val['note'],
    //                         'kendaraan' => $val['kendaraan'],
    //                         'id_sampling' => $val['id_sampling'],
    //                         'wilayah' => $val['wilayah'],
    //                         'sampler' => explode(',', $value)[1],
    //                         'userid' => explode(',', $value)[0],
    //                         'parsial' => $firstJadwalId,
    //                     ];
    //                     Jadwal::insert($commonData);
    //                 }
    //             }
    //         }

    //         //update request sampling
    //         $cek = SamplingPlan::where('id', $dataAdd->id_sampling)->first();
    //         if (!is_null($cek)) {
    //             $cek->status = 1;
    //             $cek->status_jadwal = 'jadwal';
    //             $cek->save();
    //         }

    //         /* step notifications */
    //         $sales = JadwalServices::on('no_quotation', $dataAdd->no_quotation)->getQuotation()->sales_id;
    //         $salesAtasan = GetAtasan::where('id', $sales)->get()->pluck('id');
    //         $message = "Jadwal No Quotation $dataAdd->no_quotation Sudah Melakukan Jadwal Parsial Di Tanggal " . implode(', ', $dataAdd->tanggal);
    //         Notification::whereIn('id', $salesAtasan)->title('Jadwal Parsial')->message($message)->url('url')->send();
    //         DB::commit();
    //         return true;
    //     } catch (Exception $ex) {
    //         DB::rollback();
    //         throw new Exception($ex->getMessage() . ' Line: ' . $ex->getLine() . ' File: ' . $ex->getFile(), 401);
    //     }
    // }

    public function insertParsialKontrak()
    {
        $dataParsial = $this->insertParsialKontrak;
        if (
            $dataParsial->id == null ||
            $dataParsial->id_sampling == null ||
            $dataParsial->totkateg == null ||
            $dataParsial->kategori == null ||
            $dataParsial->no_quotation == null ||
            $dataParsial->nama_perusahaan == null ||
            $dataParsial->wilayah == null ||
            $dataParsial->alamat == null ||
            $dataParsial->tanggal == null ||
            $dataParsial->durasi == null ||
            $dataParsial->status == null ||
            $dataParsial->karyawan == null ||
            $dataParsial->kendaraan == null ||
            $dataParsial->pendampingan_k3 == null ||
            $dataParsial->isokinetic == null 
            
        ) {
            throw new Exception("id, id_sampling, totkateg, kategori, no_quotation, nama_perusahaan, wilayah, alamat, tanggal, note, durasi, status, urutan, karyawan, kendaraan is required", 401);
        }

        DB::beginTransaction();
        try {
            $jadw = Jadwal::where('id', $dataParsial->id)->whereNull('parsial')->where('is_active', true)->first();
            $jadw2 = Jadwal::where('parsial', $dataParsial->id)->where('id', '!=', $dataParsial->id)->where('is_active', true)->get();
            $jadw4 = Jadwal::where('parsial', $dataParsial->id)->where('is_active', true)->get();
            $jadw5 = Jadwal::where('id', $dataParsial->id)->whereNotNull('parsial')->where('is_active', true)->first();
            if ($jadw) {
                if (!$jadw4->isEmpty()) {
                    $datcek = count($jadw4) + 1;
                    if ((int) $dataParsial->totkateg == $datcek) {
                        DB::rollBack();
                        throw new Exception("Kategori sudah terinput semua.!", 401);
                    }
                }
            } else if ($jadw5) {
                $jadw6 = Jadwal::where('parsial', $jadw5->parsial)->where('is_active', true)->get();
                $datcek = count($jadw6) + 1;
                if ((int) $dataParsial->totkateg == $datcek) {
                    DB::rollBack();
                    throw new Exception("Kategori sudah terinput semua.!", 401);
                }
            }
            if (!$jadw2->isEmpty()) {
                if (!empty($jadw)) {
                    foreach ($jadw2 as $key => $val) {
                        foreach (json_decode($val->kategori) as $x => $y) {
                            if (in_array($y, $dataParsial->kategori)) {
                                DB::rollBack();
                                throw new Exception('Ada input kategori yang sama.! 1', 401);
                            }
                        }
                    }
                } else {
                    foreach ($jadw2 as $key => $val) {
                        $jadw3 = Jadwal::where('id', $val->id)->whereNull('parsial')->where('is_active', true)->first();
                        if ($jadw3) {
                            foreach (json_decode($jadw3->kategori) as $x => $y) {
                                if (in_array($y, $dataParsial->kategori)) {
                                    DB::rollBack();
                                    throw new Exception('Ada input kategori yang sama.! 2', 401);
                                }
                            }
                        }
                        foreach (json_decode($val->kategori) as $x => $y) {
                            if (in_array($y, $dataParsial->kategori)) {
                                DB::rollBack();
                                throw new Exception('Ada input kategori yang sama.! 3', 401);
                            }
                        }
                    }
                }
            } else {
                if (!empty($jadw)) {
                    foreach (json_decode($jadw->kategori) as $x => $y) {
                        if (in_array($y, $dataParsial->kategori)) {
                            DB::rollBack();
                            throw new Exception('Ada input kategori yang sama.! 4', 401);
                        }
                    }
                } else {
                    $jadw2 = Jadwal::where('parsial', $dataParsial->id)->where('is_active', true)->get();
                    foreach ($jadw2 as $key => $val) {
                        foreach (json_decode($val->kategori) as $x => $y) {
                            if (in_array($y, $dataParsial->kategori)) {
                                DB::rollBack();
                                throw new Exception('Ada input kategori yang sama.! 5', 401);
                            }
                        }
                    }
                }
            }
            $samplers = $dataParsial->sampler;

            foreach ($samplers as $key => $value) {
                $sampler = explode(',', $value);
                $nama_sampler = $sampler[1];
                $idSampler = $sampler[0];

                $body = [
                    'no_quotation' => $dataParsial->no_quotation,
                    'nama_perusahaan' => $dataParsial->nama_perusahaan,
                    'wilayah' => strpos($dataParsial->wilayah, '-') ? explode('-', $dataParsial->wilayah)[1] : $dataParsial->wilayah,
                    'alamat' => $dataParsial->alamat,
                    'tanggal' => $dataParsial->tanggal,
                    'jam' => $dataParsial->jam_mulai,
                    'periode' => $dataParsial->periode ?? null,
                    'jam_mulai' => $dataParsial->jam_mulai,
                    'jam_selesai' => $dataParsial->jam_selesai,
                    'kategori' => json_encode($dataParsial->kategori),
                    'sampler' => $nama_sampler,
                    'userid' => $idSampler,
                    'warna' => $dataParsial->warna,
                    'note' => $dataParsial->note,
                    'durasi' => $dataParsial->durasi,
                    'status' => $dataParsial->status,
                    'notif' => 0,
                    'urutan' => $dataParsial->urutan,
                    'kendaraan' => $dataParsial->kendaraan,
                    'pendampingan_k3' => $dataParsial->pendampingan_k3,
                    'isokinetic' => $dataParsial->isokinetic,
                    'parsial' => $dataParsial->id,
                    'id_sampling' => $dataParsial->id_sampling,
                    'id_cabang' => $dataParsial->id_cabang,
                    'created_by' => $dataParsial->karyawan,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                ];

                $update = Jadwal::insert($body);
            }
            $status_order = QuotationKontrakH::where('no_document', $dataParsial->no_quotation)->where('is_active', true)->first();
            if ($status_order != null && $status_order->flag_status == 'ordered') {
                $orderh = OrderHeader::where('no_document', $dataParsial->no_quotation)->where('is_active', true)->first();
                $notFound =false;
                foreach ($dataParsial->kategori as $x => $y) {
                    $datsamp = explode(" - ", $y);
                    $kateg = MasterSubKategori::where('nama_sub_kategori', $datsamp[0])->where('is_active', true)->first();
                    $kateg1 = $kateg->id . '-' . $kateg->nama_sub_kategori;

                    $order_detail = OrderDetail::where('id_order_header', $orderh->id)
                        ->where('is_active', true)
                        ->where('kategori_3', $kateg1)
                        ->where(DB::raw('RIGHT(no_sampel, 3)'), '=', $datsamp[1])
                        ->first();
                    if ($order_detail != null) {
                        $order_detail->tanggal_sampling = date('Y-m-d', strtotime($dataParsial->tanggal));
                        $order_detail->save();
                    }else{
                        $notFound =true;
                    }
                }
                if ($notFound) {
                    throw new \Exception("Ada nomor sampel yang tidak ditemukan, silakan hubungi IT untuk pengecekan.");
                    // atau bisa pakai response JSON kalau API
                    // return response()->json(['status' => false, 'message' => 'Ada nomor sampel yang tidak ditemukan, silakan hubungi IT.'], 400);
                }
            }

            $sales = JadwalServices::on('no_quotation', $dataParsial->no_quotation)->getQuotation()->sales_id;
            $salesAtasan = GetAtasan::where('id', $sales)->get()->pluck('id');
            $message = "Jadwal No Quotation $dataParsial->no_quotation Sudah Melakukan Jadwal Parsial Di Tanggal $dataParsial->tanggal";
            Notification::whereIn('id', $salesAtasan)->title('Jadwal Parsial')->message($message)->url('url')->send();
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage(), 401);
        }
    }

    public function insertParsial()
    {
        $dataParsial = $this->insertParsial;
        if (
            $dataParsial->id == null ||
            $dataParsial->id_sampling == null ||
            $dataParsial->totkateg == null ||
            $dataParsial->kategori == null ||
            $dataParsial->no_quotation == null ||
            $dataParsial->nama_perusahaan == null ||
            $dataParsial->wilayah == null ||
            $dataParsial->alamat == null ||
            $dataParsial->tanggal == null ||
            $dataParsial->durasi == null ||
            $dataParsial->status == null ||
            $dataParsial->karyawan == null ||
            $dataParsial->pendampingan_k3 == null ||
            $dataParsial->isokinetic == null ||
            $dataParsial->kendaraan == null
        ) {
            throw new Exception("id, id_sampling, totkateg, kategori, no_quotation, nama_perusahaan, wilayah, alamat, tanggal, durasi, status, karyawan, kendaraan is required", 401);
        }

        DB::beginTransaction();
        try {
            $jadw = Jadwal::where('id', $dataParsial->id)->whereNull('parsial')->where('is_active', true)->first();
            $jadw2 = Jadwal::where('parsial', $dataParsial->id)->where('id', '!=', $dataParsial->id)->where('is_active', true)->get();
            $jadw4 = Jadwal::where('parsial', $dataParsial->id)->where('is_active', true)->get();
            $jadw5 = Jadwal::where('id', $dataParsial->id)->whereNotNull('parsial')->where('is_active', true)->first();
            if ($jadw) {
                if (!$jadw4->isEmpty()) {
                    $datcek = count($jadw4) + 1;
                    if ((int) $dataParsial->totkateg == $datcek) {
                        DB::rollBack();
                        throw new Exception("Kategori sudah terinput semua.!", 401);
                    }
                }
            } else if ($jadw5) {
                $jadw6 = Jadwal::where('parsial', $jadw5->parsial)->where('is_active', true)->get();
                $datcek = count($jadw6) + 1;
                if ((int) $dataParsial->totkateg == $datcek) {
                    DB::rollBack();
                    throw new Exception("Kategori sudah terinput semua.!", 401);
                }
            }
            if (!$jadw2->isEmpty()) {
                if (!empty($jadw)) {
                    foreach ($jadw2 as $key => $val) {
                        foreach (json_decode($val->kategori) as $x => $y) {
                            if (in_array($y, $dataParsial->kategori)) {
                                DB::rollBack();
                                throw new Exception('Ada input kategori yang sama.! 1', 401);
                            }
                        }
                    }
                } else {
                    foreach ($jadw2 as $key => $val) {
                        $jadw3 = Jadwal::where('id', $val->id)->whereNull('parsial')->where('is_active', true)->first();
                        if ($jadw3) {
                            foreach (json_decode($jadw3->kategori) as $x => $y) {
                                if (in_array($y, $dataParsial->kategori)) {
                                    DB::rollBack();
                                    throw new Exception('Ada input kategori yang sama.! 2', 401);
                                }
                            }
                        }
                        foreach (json_decode($val->kategori) as $x => $y) {
                            if (in_array($y, $dataParsial->kategori)) {
                                DB::rollBack();
                                throw new Exception('Ada input kategori yang sama.! 3', 401);
                            }
                        }
                    }
                }
            } else {
                if (!empty($jadw)) {
                    foreach (json_decode($jadw->kategori) as $x => $y) {
                        if (in_array($y, $dataParsial->kategori)) {
                            DB::rollBack();
                            throw new Exception('Ada input kategori yang sama.! 4', 401);
                        }
                    }
                } else {
                    $jadw2 = Jadwal::where('parsial', $dataParsial->id)->where('is_active', true)->get();
                    foreach ($jadw2 as $key => $val) {
                        foreach (json_decode($val->kategori) as $x => $y) {
                            if (in_array($y, $dataParsial->kategori)) {
                                DB::rollBack();
                                throw new Exception('Ada input kategori yang sama.! 5', 401);
                            }
                        }
                    }
                }
            }

            $samplers = $dataParsial->sampler;
            foreach ($samplers as $key => $value) {
                $sampler = explode(',', $value);
                $nama_sampler = $sampler[1];
                $idSampler = $sampler[0];
                $body = [
                    'no_quotation' => $dataParsial->no_quotation,
                    'nama_perusahaan' => $dataParsial->nama_perusahaan,
                    'wilayah' => strpos($dataParsial->wilayah, '-') ? explode('-', $dataParsial->wilayah)[1] : $dataParsial->wilayah,
                    'alamat' => $dataParsial->alamat,
                    'tanggal' => $dataParsial->tanggal,
                    'jam' => $dataParsial->jam_mulai,
                    'periode' => $dataParsial->periode ?? null,
                    'jam_mulai' => $dataParsial->jam_mulai,
                    'jam_selesai' => $dataParsial->jam_selesai,
                    'kategori' => json_encode($dataParsial->kategori),
                    'sampler' => $nama_sampler,
                    'userid' => $idSampler,
                    'warna' => $dataParsial->warna,
                    'note' => $dataParsial->note,
                    'durasi' => $dataParsial->durasi,
                    'status' => $dataParsial->status,
                    'pendampingan_k3' => $dataParsial->pendampingan_k3,
                    'isokinetic' => $dataParsial->isokinetic,
                    'notif' => 0,
                    'urutan' => $dataParsial->urutan,
                    'created_by' => $dataParsial->karyawan,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'kendaraan' => $dataParsial->kendaraan,
                    'parsial' => $dataParsial->id,
                    'id_sampling' => $dataParsial->id_sampling,
                    'id_cabang' => $dataParsial->id_cabang
                ];
                $update = Jadwal::insert($body);
            }

            $status_order = QuotationNonKontrak::where('no_document', $dataParsial->no_quotation)->where('is_active', true)->first();
            if ($status_order != null && $status_order->flag_status == 'ordered') {
                $orderh = OrderHeader::where('no_document', $dataParsial->no_quotation)->where('is_active', true)->first();
                foreach ($dataParsial->kategori as $x => $y) {
                    $datsamp = explode(" - ", $y);
                    $kateg = MasterSubKategori::where('nama_sub_kategori', $datsamp[0])->where('is_active', true)->first();
                    $kateg1 = $kateg->id . '-' . $kateg->nama_sub_kategori;

                    $order_detail = OrderDetail::where('id_order_header', $orderh->id)
                        ->where('is_active', true)
                        ->where('kategori_3', $kateg1)
                        ->where(DB::raw('RIGHT(no_sampel, 3)'), '=', $datsamp[1])
                        ->first();
                    if ($order_detail != null) {
                        $order_detail->tanggal_sampling = date('Y-m-d', strtotime($dataParsial->tanggal));
                        $order_detail->save();
                    }
                }
            }

            $sales = JadwalServices::on('no_quotation', $dataParsial->no_quotation)->getQuotation()->sales_id;
            $salesAtasan = GetAtasan::where('id', $sales)->get()->pluck('id');
            $message = "Jadwal No Quotation $dataParsial->no_quotation Sudah Melakukan Jadwal Parsial Di Tanggal $dataParsial->tanggal";
            Notification::whereIn('id', $salesAtasan)->title('Jadwal Parsial')->message($message)->url('url')->send();
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage(), 401);
        }
    }
}