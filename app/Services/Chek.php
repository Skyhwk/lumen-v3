<?php
namespace App\Services;


use App\Models\SamplingPlan;
use App\Models\QuotationKontrakD;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\Jadwal;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
class Chek
{
    private $counter = 0;

    public function cek(array $data, $request, $db)
    {
        try {
            $getRaw = SamplingPlan::where('no_quotation', $request->no_quotation)
                ->whereIn('periode_kontrak', $data)
                ->exists();
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "code" => $e->getCode(),
                "line" => $e->getLine()
            ], 500);
        }
        if ($getRaw) {
            return true;
        } else {
            return $data;
        }
    }

    public function cekExist($request)
    {
        try {
            $getRaw = SamplingPlan::where('no_quotation', $request->no_quotation)
                ->where('is_active', 1)
                ->exists();
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "code" => $e->getCode(),
                "line" => $e->getLine()
            ], 500);
        }

        if ($getRaw) {
            return true;
        } else {
            return false;
        }
    }

    /* update lanjutan 22/04/2024  */
    public function chekCountQoutations($noQoutations, $idQoutations, $db, $mode = null)
    {
        try {
            $path = explode('/', $noQoutations);
            if ($mode == 'recap_qt') {
                if ($path[1] == 'QTC') {
                    $periodQtc = [];
                    $periodSP = [];

                    $getRaw = QuotationKontrakD::where('id_request_quotation_kontrak_h', $idQoutations)
                        ->where('status_sampling', '!=', 'SD')
                        ->get();

                    $getRawSp = SamplingPlan::where('qoutation_id', $idQoutations)
                        ->where('status_quotation', 'kontrak')
                        ->where('is_active', 1)
                        ->get();

                    foreach ($getRaw as $key => $value) {
                        array_push($periodQtc, $value->periode_kontrak);
                    }

                    foreach ($getRawSp as $key => $value) {
                        array_push($periodSP, $value->periode_kontrak);
                    }

                    $diff = array_diff($periodQtc, $periodSP);

                    if (empty($diff)) {
                        return true;
                    } else {
                        return false;
                    }
                }

            } else {
                if ($path[1] == 'QTC') {
                    $getRaw = QuotationKontrakD::where('id_request_quotation_kontrak_h', $idQoutations)
                        ->where('status_sampling', '!=', 'SD')
                        ->count();

                    return $getRaw;
                } else {
                    $getRaw = QuotationNonKontrak::where('id', $idQoutations)->count();
                    return $getRaw;
                }
            }
        } catch (\Exception $e) {
            //throw $th;
            return $e->getMessage();
        }
    }
    public function checkCountJadwal($noQt)
    {
        try {
            $db = '20' . \explode('-', \explode('/', $noQt)[2])[0]; // current data
            $cek_sp = SamplingPlan::where('no_quotation', $noQt)
                ->select('periode_kontrak')
                ->groupBy('periode_kontrak')
                ->orderBy('periode_kontrak', 'ASC')
                ->get();

            $array = $cek_sp->toArray();

            $first = reset($array);
            $last = end($array);

            $db1 = explode('-', $first->periode_kontrak)[0];
            $db2 = explode('-', $last->periode_kontrak)[0];

            if ($db1 != $db2) {
                $result = SamplingPlan::join('jadwal as r', 'sampling_plan.id', '=', 'r.sample_id')
                    ->whereNull('r.parsial')
                    ->where('r.no_qt', $noQt)
                    ->where('r.active', 0)
                    ->where('sampling_plan.status', 1)
                    ->groupBy('r.sample_id', 'sampling_plan.periode_kontrak')
                    ->select('r.sample_id', 'sampling_plan.periode_kontrak')
                    ->unionAll(
                        SamplingPlan::join('jadwal as r', 'sampling_plan.id', '=', 'r.sample_id')
                            ->whereNull('r.parsial')
                            ->where('r.no_qt', $noQt)
                            ->where('r.active', 0)
                            ->where('sampling_plan.status', 1)
                            ->groupBy('r.sample_id', 'sampling_plan.periode_kontrak')
                            ->select('r.sample_id', 'sampling_plan.periode_kontrak')
                    )
                    ->get();
            } else {
                $result = SamplingPlan::join('jadwal as r', 'sampling_plan.id', '=', 'r.sample_id')
                    ->whereNull('r.parsial')
                    ->where('r.no_qt', $noQt)
                    ->where('r.active', 0)
                    ->where('sampling_plan.status', 1)
                    ->groupBy('r.sample_id', 'sampling_plan.periode_kontrak')
                    ->select('r.sample_id', 'sampling_plan.periode_kontrak')
                    ->get();
            }

            // Return the count of the result
            return $result->count();


        } catch (\Exception $e) {
            //throw $th;
            return $e->getMessage();
        }
    }

    public function checkCountJadwalApp($noQt)
    {
        try {
            $db = '20' . \explode('-', \explode('/', $noQt)[2])[0]; // current data
            $result = [];
            if (\explode('/', $noQt)[1] == 'QT') {
                $query = "SELECT r.sample_id, t.periode_kontrak FROM intilab_2024.sampling_plan t JOIN intilab_2024.jadwal r ON t.id = r.sample_id WHERE r.parsial IS NULL and r.no_qt = '$noQt' AND r.active = 0 AND t.status = 1 and t.is_approve = 1 GROUP BY r.sample_id, t.periode_kontrak";

                $result = DB::select($query);
            } else if (\explode('/', $noQt)[1] == 'QTC') {
                $cek_sp = DB::table('sampling_plan')
                    ->where('no_quotation', $noQt)
                    ->select('periode_kontrak', 'no_document')
                    ->groupBy('periode_kontrak', 'no_document')
                    ->orderBy('periode_kontrak', 'ASC')
                    ->get();

                $array = $cek_sp->toArray();

                $first = reset($array);
                $last = end($array);
                // $db1 = \explode('-', $first->periode_kontrak)[0];
                // $db2 = \explode('-', $last->periode_kontrak)[0];

                // if($db1!=$db2){
                //     $query = "SELECT r.sample_id, t.periode_kontrak FROM intilab_$db1.sampling_plan t JOIN intilab_$db1.jadwal r ON t.id = r.sample_id WHERE r.parsial IS NULL and r.no_qt = '$noQt' AND r.active = 0 AND t.status = 1 and t.is_approve = 1 GROUP BY r.sample_id, t.periode_kontrak
                //     UNION ALL
                //     SELECT r.sample_id, t.periode_kontrak FROM intilab_$db1.sampling_plan t JOIN intilab_$db2.jadwal r ON t.id = r.sample_id WHERE r.parsial IS NULL and r.no_qt = '$noQt' AND r.active = 0 AND t.status = 1 and t.is_approve = 1 GROUP BY r.sample_id, t.periode_kontrak
                //     ";
                // } else {

                // }
                $query = "SELECT r.sample_id, t.periode_kontrak FROM intilab_2024.sampling_plan t JOIN intilab_2024.jadwal r ON t.id = r.sample_id WHERE r.parsial IS NULL and r.no_qt = '$noQt' AND r.active = 0 AND t.status = 1 and t.is_approve = 1 GROUP BY r.sample_id, t.periode_kontrak";

                $result = DB::select($query);
                if ($result == null) {
                    $result = DB::connection($db)->table('sampling_plan')
                        ->where('no_quotation', $noQt)
                        ->where('active', 0)
                        ->get();
                }
            }

            //    return $result->count();

            return count($result);
        } catch (\Exception $e) {
            //throw $th;
            return $e->getMessage();
        }
    }





    /* update lanjutan 22/04/2024  */

    // public function checkCountJadwalApp ($noQt)
    // {
    //     try {
    //         $db = '20'.\explode('-', \explode('/', $noQt)[2])[0]; // current data
    //         $cek_sp = DB::connection($db)->table('sampling_plan')
    //         ->where('no_quotation', $noQt)
    //         ->select('periode_kontrak', 'no_document')
    //         ->groupBy('periode_kontrak', 'no_document')
    //         ->orderBy('periode_kontrak', 'ASC')
    //         ->get();

    //         $array = $cek_sp->toArray();

    //         $first = reset($array);
    //         $last = end($array);

    //         $db1 = \explode('-', $first->periode_kontrak)[0];
    //         $db2 = \explode('-', $last->periode_kontrak)[0];
    //         if($db1!=$db2){
    //             $query = "SELECT r.sample_id, t.periode_kontrak FROM intilab_$db1.sampling_plan t JOIN intilab_$db1.jadwal r ON t.id = r.sample_id WHERE r.parsial IS NULL and r.no_qt = '$noQt' AND r.active = 0 AND t.status = 1 and t.is_approve = 1 GROUP BY r.sample_id, t.periode_kontrak
    //             UNIOn ALL
    //             SELECT r.sample_id, t.periode_kontrak FROM intilab_$db1.sampling_plan t JOIN intilab_$db2.jadwal r ON t.id = r.sample_id WHERE r.parsial IS NULL and r.no_qt = '$noQt' AND r.active = 0 AND t.status = 1 and t.is_approve = 1 GROUP BY r.sample_id, t.periode_kontrak
    //             ";
    //         } else {
    //             $query = "SELECT r.sample_id, t.periode_kontrak FROM intilab_$db1.sampling_plan t JOIN intilab_$db2.jadwal r ON t.id = r.sample_id WHERE r.parsial IS NULL and r.no_qt = '$noQt' AND r.active = 0 AND t.status = 1 and t.is_approve = 1 GROUP BY r.sample_id, t.periode_kontrak";
    //         }

    //         $result = DB::select($query);

    //     //    return $result->count();
    //        return count($result);
    //     } catch (\Exception $e) {
    //         //throw $th;
    //         return $e->getMessage();
    //     }
    // }

    public function setCounter(int $counter)
    {
        $this->counter = $counter;
    }

    public function resetCounter()
    {
        $this->counter = 0;
    }

    public function getCounter()
    {
        return $this->counter;
    }

    /*
     *  Create No Document Sampling Plan
     *  @param $request request
     *  @param $db database
     */
    public function createNoDocSampling(Request $request)
    {
        $cek = SamplingPlan::orderBy('id', "DESC")->first();
        $no_ = '1';

        if ($cek != null) {
            // Mengambil tahun saat ini
            $tahun_chek = date('y'); // 2 digit tahun (25)

            // Mengambil tahun dari dokumen terakhir
            $parts = explode('/', $cek->no_document);
            if (count($parts) > 2) {
                $tahun_cek_full = $parts[2]; // Bagian yang berisi tahun-bulan
                $tahun_cek_docLast = explode('-', $tahun_cek_full)[0]; // Tahun dokumen terakhir

                // Jika tahun sama, increment nomor dokumen
                if ($tahun_chek == $tahun_cek_docLast) {
                    $no_ = (int) explode('/', $cek->no_document)[3] + 1;
                }
            }
        }

        // Format nomor dokumen menjadi 8 digit
        $no_ = sprintf('%08d', ($no_));
        $no_document = 'ISL/SP' . '/' . DATE('y', strtotime($request->tanggal_penawaran)) . '-' . self::romawi(DATE('m', strtotime($request->tanggal_penawaran))) . '/' . $no_;

        return $no_document;
    }




    //utilis
    public function romawi($bulan = 0)
    {
        $satuan = (int) $bulan - 1;
        $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        return $romawi[$satuan];
    }

    /*
    update lanjutan 22/04/2024 
    ini di gunakan pada case requst sampling di recapQT dan qtOrder
    */
    /* public function cekExistSampling($noQt,$db)
    {
        // dd($request);
        try {
            //code...
            $conn = new SamplingPlan;
            $data = $conn->setConnection($db);
            $getRaw=$data->where('no_quotation',$noQt)
            ->where('active',0)
            ->where('status',0)
            ->where('is_approve',0)
            ->exists();
                
        } catch (\Throwable $th) {
            //throw $th;
            dd($th->getMessage());
            return $th;
        }
        // return $getRaw;
        return false;
        
    } */

    public function cekExistSampling($noQt, $db)
    {
        // dd($request);
        try {
            //code...
            $conn = new SamplingPlan;
            $data = $conn;
            $getRaw = $data->where('no_quotation', $noQt)
                ->where('active', 0)
                ->where('status', 0)
                ->where('is_approve', 0)
                ->exists();

        } catch (\Throwable $th) {
            //throw $th;
            dd($th->getMessage());
            return $th;
        }
        // return $getRaw;
        return false;

    }
    /* re-fix 
     */
}