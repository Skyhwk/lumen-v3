<?php
namespace App\Services;

use App\Models\Jadwal;
use App\Models\SamplingPlan;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Services\{Notification, GetAtasan};
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class SamplingPlanServices
{
    private $insertKontrak;
    private $insertNon;
    private $insertSingleKontrak;
    private $insertSingleNon;
    private $no_quotation;
    private $tanggal_penawaran;
    private $timestamp;
    private static $instance;

    /*
        Cara Pemanggilan
        SamplingPlanServices::on('insertKontrak', $Object_data)->insertSPKontrak();
        SamplingPlanServices::on('insertNon', $Object_data)->insertSP();
        SamplingPlanServices::on('insertSingleKontrak', $Object_data)->insertSPSingleKontrak();
        SamplingPlanServices::on('insertSingleNon', $Object_data)->insertSPSingle();
    */

    public function __construct()
    {
        $this->timestamp = Carbon::now()->format('Y-m-d H:i:s');
    }

    public function __call($method, $arguments)
    {
        throw new Exception("Method $method does not exist on SamplingPlanServices. Arguments: " . implode(", ", $arguments) . "\n", 404);
    }

    public static function __callStatic($method, $arguments)
    {
        echo "Static method $method does not exist on SamplingPlanServices. Arguments: " . implode(", ", $arguments) . "\n";
    }

    public static function on($field, $value)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        switch ($field) {
            case 'insertSingleKontrak':
                self::$instance->insertSingleKontrak = $value;
                break;
            case 'insertSingleNon':
                self::$instance->insertSingleNon = $value;
                break;
            case 'insertKontrak':
                self::$instance->insertKontrak = $value;
                break;
            case 'insertNon':
                self::$instance->insertNon = $value;
                break;
            case 'tanggal_penawaran':
                self::$instance->tanggal_penawaran = $value;
                break;
            case 'no_quotation':
                self::$instance->no_quotation = $value;
                break;
        }

        return self::$instance;
    }

    private function romawi($bulan = 0)
    {
        $satuan = (int) $bulan - 1;
        $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        return $romawi[$satuan];
    }

    public function createNoDocSampling()
    {
        $no_ = explode('/', $this->no_quotation);
        if ($no_[1] == 'QT') {
            $no_[3] = preg_replace('/R\d+$/', '', $no_[3]);
        }
        $no_[1] = 'SP';
        $no_document = implode('/', $no_);

        return $no_document;
    }

    private function cekExist()
    {
        try {
            $cek = SamplingPlan::where('no_quotation', $this->no_quotation)
                ->where('is_active', true)
                ->exists();
            return $cek;
        } catch (Exception $e) {
            return false;
        }
    }

    // cleared
    public function insertSPKontrak()
    {
        $dataInsert = $this->insertKontrak;
        if ($dataInsert->periode == null || $dataInsert->no_quotation == null || $dataInsert->quotation_id == null || $dataInsert->karyawan == null || $dataInsert->tanggal_sampling == null || $dataInsert->jam_sampling == null || $dataInsert->tanggal_penawaran == null) {
            throw new Exception('Periode, No quotation, Quotation id, Karyawan, Tanggal sampling, Jam sampling, Tanggal penawaran is required when execute insertSPKontrak()', 401);
        }

        self::$instance->tanggal_penawaran = $dataInsert->tanggal_penawaran;
        self::$instance->no_quotation = $dataInsert->no_quotation;

        DB::beginTransaction();
        try {
            $insert = null;
            $temptInsert = array();
            $periodeT = [];

            $no_document = $this->createNoDocSampling();
            $countPeriode = count($dataInsert->periode);
            $indexPeriode = 1;
            foreach ($dataInsert->periode as $keys => $singlePeriode) {
                $getNodocument = $no_document . '/' . $indexPeriode . '-' . $countPeriode;

                if (isset($dataInsert->tambahan[$keys]) && is_array($dataInsert->tambahan[$keys])) {
                    $tambahan = array_filter($dataInsert->tambahan[$keys], function ($item) {
                        return !empty($item);
                    });
                } else {
                    $tambahan = [];
                }

                if (isset($dataInsert->keterangan_lain[$keys]) && is_array($dataInsert->keterangan_lain[$keys])) {
                    $keterangan_lain = array_filter($dataInsert->keterangan_lain[$keys], function ($item) {
                        return !empty($item);
                    });
                } else {
                    $keterangan_lain = [];
                }

                $tanggalSamplin = [];
                foreach ($dataInsert->tanggal_sampling[$keys] as $key => $tanggal) {

                    if (is_array($tanggal)) {
                        $tanggal = $tanggal[0] . ' s/d ' . $tanggal[1];
                    }

                    $jam = $dataInsert->jam_sampling[$keys][$key];
                    $jam = $jam[0] . ' - ' . $jam[1];
                    $formattedValue = $tanggal . ',' . $jam;
                    array_push($tanggalSamplin, $formattedValue);
                }

                $isSabtu = !empty($dataInsert->is_sabtu[$keys]) ? (bool) $dataInsert->is_sabtu[$keys] : false;
                $isMinggu = !empty($dataInsert->is_minggu[$keys]) ? (bool) $dataInsert->is_minggu[$keys] : false;
                $isMalam = !empty($dataInsert->is_malam[$keys]) ? (bool) $dataInsert->is_malam[$keys] : false;
                $enumValues = [0 => 'Tidak', 1 => 'Ya'];

                $temptInsert[$keys] = [
                    'no_document' => $getNodocument,
                    'no_quotation' => $dataInsert->no_quotation,
                    'quotation_id' => $dataInsert->quotation_id,
                    'tambahan' => json_encode($tambahan),
                    'keterangan_lain' => json_encode($keterangan_lain),
                    'periode_kontrak' => $singlePeriode,
                    'is_sabtu' => $enumValues[$isSabtu],
                    'is_minggu' => $enumValues[$isMinggu],
                    'is_malam' => $enumValues[$isMalam],
                    'status_quotation' => 'kontrak',
                    'created_by' => $dataInsert->karyawan,
                    'created_at' => $this->timestamp
                ];

                foreach ($tanggalSamplin as $index => $tanggal) {
                    $temptInsert[$keys]['opsi_' . ($index + 1)] = $tanggal;
                }
                array_push($periodeT, $singlePeriode);
                $indexPeriode++;
            }

            if ($this->cekExist()) {
                try {
                    if (isset($dataInsert->sample_id) && $dataInsert->sample_id != null) { //jika berada di posisi selisih periode pada revisi
                        $sampleIds = $dataInsert->sample_id;
                        $samplingPlans = SamplingPlan::whereIn('id', $sampleIds)
                            ->where('is_active', true)
                            ->where('status', 1)
                            ->get()->keyBy('id');

                        if ($samplingPlans->isEmpty()) {
                            DB::rollBack();
                            throw new Exception('Data not Found.!');
                        }

                        $r = 0;
                        $b = 0;
                        $updatedJadwal = [];
                        $updatedSamplingPlan = [];
                        foreach ($sampleIds as $key => $value) {
                            if (isset($samplingPlans[$value])) {
                                $updateStatus = $samplingPlans[$value];
                                $no_document = $updateStatus->no_document;
                                $no_doc_suffix = substr($no_document, -4);
                                if (strpos($no_doc_suffix, 'R') !== false) {
                                    list($no_doc_prefix, $r_number) = explode('R', $no_document);
                                    $r = (int) $r_number + 1;
                                    $new_no_document = $no_doc_prefix . 'R' . $r;
                                } else {
                                    $new_no_document = $no_document . 'R1';
                                }

                                $temptInsert[$b]['no_document'] = $new_no_document;

                                $updatedJadwal[] = [
                                    'sample_id' => $updateStatus->sample_id,
                                    'is_active' => false,
                                ];

                                $updatedSamplingPlan[] = [
                                    'id' => $updateStatus->id,
                                    'status' => 0,
                                    'is_active' => false,
                                    'status_jadwal' => 'cancel',
                                ];

                            } else {
                                DB::rollBack();
                                throw new Exception('Data not Found.!');
                            }
                            $b++;
                        }

                        Jadwal::whereIn('sample_id', array_column($updatedJadwal, 'sample_id'))
                            ->where('is_active', true)
                            ->update(['is_active' => false]);

                        SamplingPlan::whereIn('id', array_column($updatedSamplingPlan, 'id'))
                            ->update([
                                'status' => 0,
                                'is_active' => false,
                                'status_jadwal' => 'cancel',
                            ]);
                    }
                    $insert = SamplingPlan::insert($temptInsert);
                } catch (Exception $ex) {
                    DB::rollBack();
                    throw new Exception($ex->getMessage(), 500);
                }
            } else {
                //PERMINTAAN JADWAL BARU
                $insert = SamplingPlan::insert($temptInsert);
            }

            if ($insert) {
                $dataQuotation = QuotationKontrakH::where('no_document', $dataInsert->no_quotation)->where('is_active', true)->first();
                if ($dataQuotation->flag_status != 'ordered') {
                    $dataQuotation->flag_status = 'sp';
                    $dataQuotation->sp_by = 1;
                    $dataQuotation->save();
                }

                $adminJadwal = GetAtasan::where('id', 187)->get()->pluck('id');
                $sales = GetAtasan::where('id', $dataQuotation->sales_id)->get()->pluck('id');
                $implodePeriode = implode(",", $periodeT);
                $message1 = "Pesan! \nPermintaan request jadwal dengan no QT $dataInsert->no_quotation dengan periode $implodePeriode , silahkan untuk menjadwalkan  pada menu REQUEST SAMPLING PLAN";
                $message2 = "Pesan! \nNomor Quotation $dataInsert->no_quotation telah dilakukan permintaan jadwal.";
                Notification::whereIn('id', $adminJadwal)->title('Request SP')->message($message1)->url('url')->send();
                Notification::whereIn('id', $sales)->title('Request SP')->message($message2)->url('url')->send();
                DB::commit();
                return true;
            } else {
                DB::rollBack();
                throw new Exception('Something Wrong.!', 401);
            }
        } catch (\Throwable $ex) {
            DB::rollBack();
            throw new Exception($ex->getMessage(), 401);
        }
    }

    // cleared
    public function insertSP()
    {
        $dataInsert = $this->insertNon;
        if ($dataInsert->no_quotation == null || $dataInsert->quotation_id == null || $dataInsert->karyawan == null || $dataInsert->tanggal_sampling == null || $dataInsert->jam_sampling == null || $dataInsert->tanggal_penawaran == null) {
            throw new Exception('No quotation, Quotation id, Karyawan, Tanggal sampling, Jam sampling, Tanggal penawaran is required when execute insertSP()', 401);
        }
        self::$instance->tanggal_penawaran = $dataInsert->tanggal_penawaran;
        self::$instance->no_quotation = $dataInsert->no_quotation;

        DB::beginTransaction();
        try {
            $insert = null;
            $tanggalSamplin = [];
            $temptInsert = [];
            foreach ($dataInsert->tanggal_sampling as $key => $value) {
                if (is_array($value) && count($value) === 2) {
                    $tanggal = $value[0] . ' s/d ' . $value[1];
                } else {
                    $tanggal = $value;
                }

                if (isset($dataInsert->jam_sampling[$key]) && is_array($dataInsert->jam_sampling[$key]) && count($dataInsert->jam_sampling[$key]) === 2) {
                    $jam = $dataInsert->jam_sampling[$key][0] . ' - ' . $dataInsert->jam_sampling[$key][1];
                } else {
                    $jam = $dataInsert->jam_sampling[$key];
                }

                $formattedValue = $tanggal . ',' . $jam;
                array_push($tanggalSamplin, $formattedValue);
            }

            if (isset($dataInsert->tambahan) && is_array($dataInsert->tambahan)) {
                $tambahan = array_filter($dataInsert->tambahan, function ($item) {
                    return !empty($item);
                });
            } else {
                $tambahan = [];
            }

            if (isset($dataInsert->keterangan_lain) && is_array($dataInsert->keterangan_lain)) {
                $keterangan_lain = array_filter($dataInsert->keterangan_lain, function ($item) {
                    return !empty($item);
                });
            } else {
                $keterangan_lain = [];
            }

            $isSabtu = !empty($dataInsert->is_sabtu) ? (bool) $dataInsert->is_sabtu : false;
            $isMinggu = !empty($dataInsert->is_minggu) ? (bool) $dataInsert->is_minggu : false;
            $isMalam = !empty($dataInsert->is_malam) ? (bool) $dataInsert->is_malam : false;
            $enumValues = [0 => 'Tidak', 1 => 'Ya'];

            $no_document = $this->createNoDocSampling();
            $temptInsert = [
                'no_document' => $no_document,
                'quotation_id' => $dataInsert->quotation_id,
                'no_quotation' => $dataInsert->no_quotation,
                'is_sabtu' => $enumValues[$isSabtu],
                'is_minggu' => $enumValues[$isMinggu],
                'is_malam' => $enumValues[$isMalam],
                'tambahan' => json_encode($tambahan),
                'keterangan_lain' => json_encode($keterangan_lain),
                'periode_kontrak' => null,
                'opsi_3' => null,
                'created_by' => $dataInsert->karyawan,
                'created_at' => $this->timestamp,
            ];

            foreach ($tanggalSamplin as $index => $tanggal) {
                $temptInsert['opsi_' . ($index + 1)] = $tanggal;
            }
            if ($this->cekExist()) {
                try {
                    $updateStatus = SamplingPlan::where('no_quotation', $dataInsert->no_quotation)
                        ->where('status', 1)
                        ->orderBy('id', 'desc')
                        ->first();
                    if ($updateStatus) {
                        $no_document = $updateStatus->no_document;
                        $no_doc_suffix = substr($no_document, -4);

                        if (strpos($no_doc_suffix, 'R') !== false) {
                            list($no_doc_prefix, $r_number) = explode('R', $no_document);
                            $r = (int) $r_number + 1;
                            $new_no_document = $no_doc_prefix . 'R' . $r;
                        } else {
                            $new_no_document = $no_document . 'R1';
                        }

                        $temptInsert['no_document'] = $new_no_document;

                        Jadwal::where('id_sampling', $updateStatus->id)
                            ->where('is_active', true)
                            ->update(['is_active' => false]);
                        SamplingPlan::where('id', $updateStatus->id)
                            ->update(['status' => 0, 'is_active' => false, 'status_jadwal' => 'cancel']);
                    } else {//belum  ada terjadwal
                        $updateStatus = SamplingPlan::where('no_quotation', $dataInsert->no_quotation)
                            ->where('status', 0)
                            ->orderBy('id', 'desc')
                            ->first();
                        Jadwal::where('id_sampling', $updateStatus->id)
                            ->where('is_active', true)
                            ->update(['is_active' => false]);
                        SamplingPlan::where('id', $updateStatus->id)
                            ->update(['status' => 0, 'is_active' => false, 'status_jadwal' => 'cancel']);
                    }

                    $insert = SamplingPlan::insert($temptInsert);
                } catch (Exception $ex) {
                    DB::rollBack();
                    throw new Exception($ex->getMessage(), 500);
                }
            } else { //INPUTAN BARU
                $insert = SamplingPlan::insert($temptInsert);
            }

            if ($insert) {
                $datau = QuotationNonKontrak::where('no_document', $dataInsert->no_quotation)->where('is_active', true)->first();
                if ($datau->flag_status != 'ordered') {
                    $datau->flag_status = 'sp';
                    $datau->sp_by = 1;
                    $datau->save();
                }

                $message1 = "Pesan! \nAda permintaan jadwal no QT $dataInsert->no_quotation , silahkan untuk menjadwalkan pada menu REQUEST SAMPLING PLAN";
                $message2 = "Pesan! \nNomor Quotation $dataInsert->no_quotation telah dilakukan permintaan jadwal.";
                $adminJadwal = GetAtasan::where('id', 187)->get()->pluck('id');
                $sales = GetAtasan::where('id', $datau->sales_id)->get()->pluck('id');
                Notification::whereIn('id', $adminJadwal)->title('Request SP')->message($message1)->url('url')->send();
                Notification::whereIn('id', $sales)->title('Request SP')->message($message2)->url('url')->send();
            } else {
                DB::rollBack();
                throw new Exception('Something Wrong.!', 401);
            }
            DB::commit();
            return true;
        } catch (Exception $ex) {
            DB::rollBack();
            throw new Exception($ex->getMessage(), 401);
        }
    }

    // tinggal test
    public function insertSPSingleKontrak()
    {
        $dataInsertSingle = $this->insertSingleKontrak;

        if (
            $dataInsertSingle->no_document == null ||
            $dataInsertSingle->periode == null ||
            $dataInsertSingle->no_quotation == null ||
            $dataInsertSingle->quotation_id == null ||
            $dataInsertSingle->karyawan == null ||
            $dataInsertSingle->tanggal_sampling == null ||
            $dataInsertSingle->jam_sampling == null ||
            $dataInsertSingle->sample_id == null
        ) {
            dd(
                $dataInsertSingle->no_document,
                $dataInsertSingle->periode,
                $dataInsertSingle->no_quotation,
                $dataInsertSingle->quotation_id,
                $dataInsertSingle->karyawan,
                $dataInsertSingle->tanggal_sampling,
                $dataInsertSingle->jam_sampling,
                $dataInsertSingle->sample_id
            );
            throw new Exception('Periode, No Document, No quotation, Quotation ID, Sample ID, Karyawan, Tanggal sampling, Jam sampling is required when execute insertSPKontrak()', 401);
        }

        self::$instance->no_quotation = $dataInsertSingle->no_quotation;

        DB::beginTransaction();
        try {
            $insert = null;
            $temptInsert = [];

            $tanggalSamplin = [];
            foreach ($dataInsertSingle->tanggal_sampling as $key => $value) {
                if (is_array($value) && count($value) === 2) {
                    $tanggal = $value[0] . ' s/d ' . $value[1];
                } else {
                    $tanggal = $value;
                }

                if (isset($dataInsertSingle->jam_sampling[$key]) && is_array($dataInsertSingle->jam_sampling[$key]) && count($dataInsertSingle->jam_sampling[$key]) === 2) {
                    $jam = $dataInsertSingle->jam_sampling[$key][0] . ' - ' . $dataInsertSingle->jam_sampling[$key][1];
                } else {
                    $jam = $dataInsertSingle->jam_sampling[$key];
                }

                $formattedValue = $tanggal . ',' . $jam;
                array_push($tanggalSamplin, $formattedValue);
            }

            if (isset($dataInsertSingle->tambahan) && is_array($dataInsertSingle->tambahan)) {
                $tambahan = array_filter($dataInsertSingle->tambahan, function ($item) {
                    return !empty($item);
                });
            } else {
                $tambahan = [];
            }

            if (isset($dataInsertSingle->keterangan_lain) && is_array($dataInsertSingle->keterangan_lain)) {
                $keterangan_lain = array_filter($dataInsertSingle->keterangan_lain, function ($item) {
                    return !empty($item);
                });
            } else {
                $keterangan_lain = [];
            }

            $isSabtu = !empty($dataInsertSingle->is_sabtu) ? (bool) $dataInsertSingle->is_sabtu : false;
            $isMinggu = !empty($dataInsertSingle->is_minggu) ? (bool) $dataInsertSingle->is_minggu : false;
            $isMalam = !empty($dataInsertSingle->is_malam) ? (bool) $dataInsertSingle->is_malam : false;
            $enumValues = [0 => 'Tidak', 1 => 'Ya'];

            $temptInsert = [
                'no_document' => $dataInsertSingle->no_document,
                'no_quotation' => $dataInsertSingle->no_quotation,
                'quotation_id' => $dataInsertSingle->quotation_id,
                'tambahan' => json_encode($tambahan),
                'keterangan_lain' => json_encode($keterangan_lain),
                'periode_kontrak' => $dataInsertSingle->periode,
                'is_sabtu' => $enumValues[$isSabtu],
                'is_minggu' => $enumValues[$isMinggu],
                'is_malam' => $enumValues[$isMalam],
                'status_quotation' => 'kontrak',
                'opsi_3' => null,
                'created_by' => $dataInsertSingle->karyawan,
                'created_at' => $this->timestamp
            ];

            foreach ($tanggalSamplin as $index => $tanggal) {
                $temptInsert['opsi_' . ($index + 1)] = $tanggal;
            }
            if ($this->cekExist()) {
                try {
                    $updateStatus = SamplingPlan::where('no_quotation', $dataInsertSingle->no_quotation)
                        ->where('periode_kontrak', $dataInsertSingle->periode)
                        ->where('is_active', true)
                        ->orderBy('id', 'desc')
                        ->first();


                    if ($updateStatus) {
                        $no_document = $updateStatus->no_document;
                        $no_doc_suffix = substr($no_document, -4);

                        if (strpos($no_doc_suffix, 'R') !== false) {
                            list($no_doc_prefix, $r_number) = explode('R', $no_document);
                            $r = (int) $r_number + 1;
                            $new_no_document = $no_doc_prefix . 'R' . $r;
                        } else {
                            $new_no_document = $no_document . 'R1';
                        }

                        $temptInsert['no_document'] = $new_no_document;

                        $updateStatus->is_active = false;
                        $updateStatus->status_jadwal = 'cancel';
                        $updateStatus->save();
                    }
                    $insert = SamplingPlan::insert($temptInsert);
                } catch (Exception $ex) {
                    DB::rollBack();
                    throw new Exception($ex->getMessage(), 500);
                }
            } else {
                $insert = SamplingPlan::insert($temptInsert);
            }

            if ($insert) {
                $datau = QuotationKontrakH::where('no_document', $dataInsertSingle->no_quotation)->where('is_active', true)->first();
                if ($datau->flag_status != 'ordered') {
                    $datau->flag_status = 'sp';
                    $datau->save();
                }
            }
            DB::commit();
            return true;
        } catch (Exception $ex) {
            DB::rollBack();
            throw new Exception($ex->getMessage(), 500);
        }
    }

    // tinggal test 
    public function insertSPSingle()
    {
        $dataInsertSingle = $this->insertSingleNon;

        if (
            $dataInsertSingle->no_document == null ||
            $dataInsertSingle->quotation_id == null ||
            $dataInsertSingle->no_quotation == null ||
            $dataInsertSingle->karyawan == null ||
            $dataInsertSingle->tanggal_sampling == null ||
            $dataInsertSingle->jam_sampling == null
        ) {
            dd(
                $dataInsertSingle->no_document == null,
                $dataInsertSingle->quotation_id == null,
                $dataInsertSingle->no_quotation == null,
                $dataInsertSingle->karyawan == null,
                $dataInsertSingle->tanggal_sampling == null,
                $dataInsertSingle->jam_sampling == null
            );
            throw new Exception('No Document, No Quotation, Quotation ID, Karyawan, Tanggal Sampling, Jam Sampling is required when execute insertSPSingle()', 401);
        }

        self::$instance->no_quotation = $dataInsertSingle->no_quotation;

        DB::beginTransaction();
        try {
            $insert = null;
            $tanggalSamplin = [];
            foreach ($dataInsertSingle->tanggal_sampling as $key => $value) {
                if (is_array($value) && count($value) === 2) {
                    $tanggal = $value[0] . ' s/d ' . $value[1];
                } else {
                    $tanggal = $value;
                }

                if (isset($dataInsertSingle->jam_sampling[$key]) && is_array($dataInsertSingle->jam_sampling[$key])) {
                    $jam = $dataInsertSingle->jam_sampling[$key][0] . ' - ' . $dataInsertSingle->jam_sampling[$key][1];
                } else {
                    $jam = $dataInsertSingle->jam_sampling[$key];
                }

                $formattedValue = $tanggal . ',' . $jam;
                array_push($tanggalSamplin, $formattedValue);
            }

            if (isset($dataInsertSingle->tambahan) && is_array($dataInsertSingle->tambahan)) {
                $tambahan = array_filter($dataInsertSingle->tambahan, function ($item) {
                    return !empty($item);
                });
            } else {
                $tambahan = [];
            }

            if (isset($dataInsertSingle->keterangan_lain) && is_array($dataInsertSingle->keterangan_lain)) {
                $keterangan_lain = array_filter($dataInsertSingle->keterangan_lain, function ($item) {
                    return !empty($item);
                });
            } else {
                $keterangan_lain = [];
            }

            $isSabtu = !empty($dataInsertSingle->is_sabtu) ? (bool) $dataInsertSingle->is_sabtu : false;
            $isMinggu = !empty($dataInsertSingle->is_minggu) ? (bool) $dataInsertSingle->is_minggu : false;
            $isMalam = !empty($dataInsertSingle->is_malam) ? (bool) $dataInsertSingle->is_malam : false;
            $enumValues = [0 => 'Tidak', 1 => 'Ya'];
            $temptInsert = [
                'no_document' => $dataInsertSingle->no_document,
                'no_quotation' => $dataInsertSingle->no_quotation,
                'quotation_id' => $dataInsertSingle->quotation_id,
                'is_sabtu' => $enumValues[$isSabtu],
                'is_minggu' => $enumValues[$isMinggu],
                'is_malam' => $enumValues[$isMalam],
                'tambahan' => json_encode($tambahan),
                'keterangan_lain' => json_encode($keterangan_lain),
                'periode_kontrak' => null,
                'opsi_3' => null,
                'created_by' => $dataInsertSingle->karyawan,
                'created_at' => $this->timestamp,
            ];

            foreach ($tanggalSamplin as $index => $tanggal) {
                $temptInsert['opsi_' . ($index + 1)] = $tanggal;
            }

            if ($this->cekExist()) {
                try {
                    $updateStatus = SamplingPlan::where('no_quotation', $dataInsertSingle->no_quotation)
                        ->where('is_active', true)
                        ->orderBy('id', 'desc')
                        ->first();
                    if ($updateStatus) {
                        $no_document = $updateStatus->no_document;
                        $no_doc_suffix = substr($no_document, -4);

                        if (strpos($no_doc_suffix, 'R') !== false) {
                            list($no_doc_prefix, $r_number) = explode('R', $no_document);
                            $r = (int) $r_number + 1;
                            $new_no_document = $no_doc_prefix . 'R' . $r;
                        } else {
                            $new_no_document = $no_document . 'R1';
                        }

                        $temptInsert['no_document'] = $new_no_document;

                        $updateStatus->is_active = false;
                        $updateStatus->status_jadwal = 'cancel';
                        $updateStatus->save();
                    }
                    $insert = SamplingPlan::insert($temptInsert);
                } catch (Exception $ex) {
                    throw new Exception($ex->getMessage(), 500);
                }
            } else {
                $insert = SamplingPlan::insert($temptInsert);
            }

            if ($insert) {
                $datau = QuotationNonKontrak::where('no_document', $dataInsertSingle->no_quotation)->where('is_active', true)->first();
                if ($datau->flag_status != 'ordered') {
                    $datau->flag_status = 'sp';
                    $datau->save();
                }
            }
            DB::commit();
            return true;
        } catch (Exception $ex) {
            DB::rollBack();
            throw new Exception($ex->getMessage(), $ex->getCode(), $ex);
        }
    }

}