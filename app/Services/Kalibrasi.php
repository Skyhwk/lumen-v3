<?php
namespace App\Services;

use Auth;
use Validator;
use Exception;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\RequestQuotationKontrakH;
use App\Models\RequestQuotationKontrakD;
use App\Models\Parameter;
use App\Models\RequestQuotation;
use App\Models\OrderH;
use App\Models\OrderD;
use App\Models\Pricelist;

class Kalibrasi{

    public function __construct(){
        
    }

    public function call(){
        
    }


    public function get(){
        $val_lama = $this->val_lama;
        $val_baru = $this->val_baru;
        $cek = $this->cek;
        $db = $this->db;
        $data_lama = $this->data_lama;
        $value_baru = $this->value_baru;
        $value_lama = $this->value_lama;

        $penambahan_data = [];
        $pengurangan_data = [];
        $perubahan_data = [];
        $result = [];
        $tgl = $val_lama->periode_kontrak . '-01';
        $sp = DB::table('sampling_plan')->where('no_quotation', $cek->no_document)->where('active', 0)->where('status', 1)->where('periode_kontrak', $val_lama->periode_kontrak)->first();

        if(!is_null($sp)){
            $dbJadwal=explode("-", $val_lama->periode_kontrak)[0];
            $jadwal = DB::table('jadwal')->where('sample_id', $sp->id)->where('active', 0)
            ->first();
            
            if($jadwal!=null)$tgl = $jadwal->tanggal;
        }
        
        
        if (count($val_lama->data_sampling) < count($val_baru->data_sampling)){
            //kategori data lama lebih sedikit dari data baru yang artinya ada yg ditambah
            foreach ($val_baru->data_sampling as $key => $value) {
                if (!in_array($value, $val_lama->data_sampling)) {
                    $value->status_sampling = $value_baru->status_sampling;
                    $penambahan_data[$val_baru->periode_kontrak][] = $value;
                    unset($val_baru->data_sampling[$key]);
                }
            }

            $val_baru->data_sampling = array_values($val_baru->data_sampling);
            
            $trim = 0;
            
            foreach ($val_baru->data_sampling as $keys => $data_qt_baru) {

                if(isset($val_lama->data_sampling[$keys])){

                    $keys = $keys;
                    $data_qt_lama = $val_lama->data_sampling[$keys];

                    if($data_qt_baru->kategori_1 == $data_qt_lama->kategori_1 && $data_qt_baru->kategori_2 == $data_qt_lama->kategori_2){
                        if($data_qt_baru->regulasi == $data_qt_lama->regulasi && $data_qt_baru->parameter == $data_qt_lama->parameter){
                            if($data_qt_lama->jumlah_titik > $data_qt_baru->jumlah_titik){
                                // Pengurangan Titik
                                
                                $jumlah_titik_lama = $data_qt_lama->jumlah_titik - $data_qt_baru->jumlah_titik;
                                $data_qt_lama->jumlah_titik = $jumlah_titik_lama;
                                $data_qt_lama->status_sampling = $value_lama->status_sampling;
                                $pengurangan_data[$val_lama->periode_kontrak][] = $data_qt_lama;
                            } else if($data_qt_lama->jumlah_titik < $data_qt_baru->jumlah_titik){
                                // Penambahan Titik

                                $jumlah_titik_baru = $data_qt_baru->jumlah_titik - $data_qt_lama->jumlah_titik;
                                $data_qt_baru->jumlah_titik = $jumlah_titik_baru;
                                $data_qt_baru->status_sampling = $value_baru->status_sampling;
                                $penambahan_data[$val_lama->periode_kontrak][] = $data_qt_baru;
                            } else {
                                // continue;
                                $update_order_detail_lama = OrderD::where('id_order_header', $data_lama->id_order)
                                ->where('periode', $val_lama->periode_kontrak)
                                ->where('kategori_2', $data_qt_lama->kategori_1)
                                ->where('kategori_3', $data_qt_lama->kategori_2)
                                ->where('param', json_encode($data_qt_lama->parameter))
                                ->where('regulasi', json_encode($data_qt_lama->regulasi))
                                ->where('active', 0)
                                ->orderBy('no_sample', 'DESC')
                                ->get();
                                
                                foreach ($update_order_detail_lama as $tt => $rr) {
                                    $last_id = DB::connection(env('DB_PRODUKSI'))
                                    ->table('t_po')
                                    ->where('no_sample', $rr->no_sample)
                                    ->update([
                                        'tgl_tugas' => $tgl,
                                        'kategori_1' => $value_baru->status_sampling,
                                        'update_at' => DATE('Y-m-d H:i:s')
                                    ]);

                                    $rr->tgl_sampling = $tgl;
                                    $rr->kategori_1 = $value_baru->status_sampling;
                                    $rr->update_at = DATE('Y-m-d H:i:s');
                                    $rr->save();
                                }
                            }
                        } else {
                            $update_order_detail_lama = OrderD::where('id_order_header', $data_lama->id_order)
                            ->where('periode', $val_lama->periode_kontrak)
                            ->where('kategori_2', $data_qt_lama->kategori_1)
                            ->where('kategori_3', $data_qt_lama->kategori_2)
                            ->where('param', json_encode($data_qt_lama->parameter))
                            ->where('regulasi', json_encode($data_qt_lama->regulasi))
                            ->where('active', 0)
                            ->orderBy('no_sample', 'DESC')
                            ->get();

                            foreach ($update_order_detail_lama as $tt => $rr) {
                                $par = [];
                                foreach($data_qt_baru->parameter as $k => $v){
                                    array_push($par, explode(';',$v)[1]);
                                }

                                $data_insert = [
                                    'kategori_1' => $value_baru->status_sampling,
                                    'kategori_2' => explode("-", $data_qt_baru->kategori_1)[0],
                                    'kategori_3' => explode("-", $data_qt_baru->kategori_2)[0],
                                    'param'     => json_encode($par),
                                    'regulasi' => json_encode($data_qt_baru->regulasi),
                                    'tgl_tugas' => $tgl,
                                    'update_at' => DATE('Y-m-d H:i:s')
                                ];

                                $last_id = DB::connection(env('DB_PRODUKSI'))
                                ->table('t_po')
                                ->where('no_sample', $rr->no_sample)
                                ->update($data_insert);

                                $rr->kategori_1 = $value_baru->status_sampling;
                                $rr->kategori_2 = $data_qt_baru->kategori_1;
                                $rr->kategori_3 = $data_qt_baru->kategori_2;
                                $rr->param = json_encode($data_qt_baru->parameter);
                                $rr->regulasi = json_encode($data_qt_baru->regulasi);
                                $rr->tgl_sampling = $tgl;
                                $rr->update_at = DATE('Y-m-d H:i:s');
                                $rr->save();

                                if($data_qt_lama->jumlah_titik > $data_qt_baru->jumlah_titik){
                                    // Mengurangi sekaligus update order detail

                                    $jumlah_titik_lama = $data_qt_lama->jumlah_titik - $data_qt_baru->jumlah_titik;

                                    $data_qt_baru->jumlah_titik = $jumlah_titik_lama;
                                    $data_qt_baru->status_sampling = $value_lama->status_sampling;
                                    $pengurangan_data[$val_lama->periode_kontrak][] = $data_qt_baru;
                                    
                                } else if($data_qt_lama->jumlah_titik < $data_qt_baru->jumlah_titik){
                                    // Menambah sekaligus update order detail

                                    $jumlah_titik_baru = $data_qt_baru->jumlah_titik - $data_qt_lama->jumlah_titik;

                                    $data_qt_baru->jumlah_titik = $jumlah_titik_baru;
                                    $data_qt_baru->status_sampling = $value_baru->status_sampling;
                                    $penambahan_data[$val_lama->periode_kontrak][] = $data_qt_baru;

                                } else {
                                    // continue;
                                }
                            }
                        }
                    } else {
                        //ada beda kategori
                        $cek_order_detail_lama = OrderD::where('id_order_header', $data_lama->id_order)
                            ->where('periode', $val_lama->periode_kontrak)
                            ->where('kategori_2', $data_qt_baru->kategori_1)
                            ->where('kategori_3', $data_qt_baru->kategori_2)
                            ->where('param', json_encode($data_qt_baru->parameter))
                            ->where('regulasi', json_encode($data_qt_baru->regulasi))
                            ->where('active', 0)
                            ->orderBy('no_sample', 'DESC')
                            ->get();

                        if($cek_order_detail_lama->isNotEmpty()){
                            $jumlah_titik_lama = $cek_order_detail_lama->count();
                            $jumlah_titik_baru = $data_qt_baru->jumlah_titik;

                            if($jumlah_titik_baru != $jumlah_titik_lama){
                                if($jumlah_titik_lama < $jumlah_titik_baru){
                                    // tambah data

                                    $titik = $jumlah_titik_baru - $jumlah_titik_lama;
                                    $data_qt_baru->jumlah_titik = $titik;
                                    $data_qt_baru->status_sampling = $value_baru->status_sampling;
                                    $penambahan_data[$val_lama->periode_kontrak][] = $data_qt_baru;

                                } else if ($jumlah_titik_lama > $jumlah_titik_baru){
                                    // pengurangan titik
                                    
                                    $titik = $jumlah_titik_lama - $jumlah_titik_baru;

                                    $data_qt_baru->jumlah_titik = $titik;
                                    $data_qt_baru->status_sampling = $value_lama->status_sampling;
                                    $pengurangan_data[$val_lama->periode_kontrak][] = $data_qt_baru;

                                } else {
                                    foreach ($cek_order_detail_lama as $tt => $rr) {
                                        $last_id = DB::connection(env('DB_PRODUKSI'))
                                        ->table('t_po')
                                        ->where('no_sample', $rr->no_sample)
                                        ->update([
                                            'tgl_tugas' => $tgl,
                                            'kategori_1' => $value_baru->status_sampling,
                                            'update_at' => DATE('Y-m-d H:i:s')
                                        ]);

                                        $rr->tgl_sampling = $tgl;
                                        $rr->kategori_1 = $value_baru->status_sampling;
                                        $rr->update_at = DATE('Y-m-d H:i:s');
                                        $rr->save();
                                    }
                                }
                            } else {
                                foreach ($cek_order_detail_lama as $tt => $rr) {
                                    $last_id = DB::connection(env('DB_PRODUKSI'))
                                    ->table('t_po')
                                    ->where('no_sample', $rr->no_sample)
                                    ->update([
                                        'tgl_tugas' => $tgl,
                                        'kategori_1' => $value_baru->status_sampling,
                                        'update_at' => DATE('Y-m-d H:i:s')
                                    ]);

                                    $rr->tgl_sampling = $tgl;
                                    $rr->kategori_1 = $value_baru->status_sampling;
                                    $rr->update_at = DATE('Y-m-d H:i:s');
                                    $rr->save();
                                }
                            }
                        } else {
                            $data_qt_baru->status_sampling = $value_baru->status_sampling;
                            $penambahan_data[$val_lama->periode_kontrak][] = $data_qt_baru;
                        }
                    }
                } else {
                    $data_qt_baru->status_sampling = $value_baru->status_sampling;
                    $penambahan_data[$val_lama->periode_kontrak][] = $data_qt_baru;
                }
            }
        } else if (count($val_lama->data_sampling) >= count($val_baru->data_sampling)) {
            //Jumlah kategori sama atau yg lama lebih banyak dalam satu periode
            if(count($val_lama->data_sampling) != count($val_baru->data_sampling)){
                foreach ($val_lama->data_sampling as $key => $value) {
                    if (!in_array($value, $val_baru->data_sampling)) {
                        $value->status_sampling = $value_lama->status_sampling;
                        $pengurangan_data[$val_lama->periode_kontrak][] = $value;
                        unset($val_lama->data_sampling[$key]);
                    }
                        
                }
            }

            $val_lama->data_sampling = array_values($val_lama->data_sampling);
            foreach ($val_lama->data_sampling as $keys => $data_qt_lama) {
                if(isset($val_baru->data_sampling[$keys])){

                    // data baruny ada

                    $data_qt_baru = $val_baru->data_sampling[$keys];
                    if($data_qt_lama->kategori_1 == $data_qt_baru->kategori_1 && $data_qt_lama->kategori_2 == $data_qt_baru->kategori_2){
                        if($data_qt_lama->regulasi == $data_qt_baru->regulasi && $data_qt_lama->parameter == $data_qt_baru->parameter){
                            //item sama semua kemudian cek jumlah titik
                            if($data_qt_lama->jumlah_titik > $data_qt_baru->jumlah_titik){
                                // Pengurangan Titik
                                
                                $jumlah_titik_lama = $data_qt_lama->jumlah_titik - $data_qt_baru->jumlah_titik;
                                $data_qt_lama->jumlah_titik = $jumlah_titik_lama;
                                $data_qt_lama->status_sampling = $value_lama->status_sampling;
                                $pengurangan_data[$val_lama->periode_kontrak][] = $data_qt_lama;
                            } else if($data_qt_lama->jumlah_titik < $data_qt_baru->jumlah_titik){
                                // Penambahan Titik
                                
                                $jumlah_titik_baru = $data_qt_baru->jumlah_titik - $data_qt_lama->jumlah_titik;
                                $data_qt_baru->jumlah_titik = $jumlah_titik_baru;
                                $data_qt_baru->status_sampling = $value_baru->status_sampling;
                                $penambahan_data[$val_lama->periode_kontrak][] = $data_qt_baru;
                            } else {
                                // continue;
                                $update_order_detail_lama = OrderD::where('id_order_header', $data_lama->id_order)
                                ->where('periode', $val_lama->periode_kontrak)
                                ->where('kategori_2', $data_qt_lama->kategori_1)
                                ->where('kategori_3', $data_qt_lama->kategori_2)
                                ->where('param', json_encode($data_qt_lama->parameter))
                                ->where('regulasi', json_encode($data_qt_lama->regulasi))
                                ->where('active', 0)
                                ->orderBy('no_sample', 'DESC')
                                ->get();

                                foreach ($update_order_detail_lama as $tt => $rr) {
                                    $last_id = DB::connection(env('DB_PRODUKSI'))
                                    ->table('t_po')
                                    ->where('no_sample', $rr->no_sample)
                                    ->update(['tgl_tugas' => $tgl, 'update_at' => DATE('Y-m-d H:i:s')]);

                                    $rr->tgl_sampling = $tgl;
                                    $rr->update_at = DATE('Y-m-d H:i:s');
                                    $rr->save();
                                }
                            }
                        } else {
                            //update param dan regulasi kemudian cek jumlah titik
                            
                            $update_order_detail_lama = OrderD::where('id_order_header', $data_lama->id_order)
                            ->where('periode', $val_lama->periode_kontrak)
                            ->where('kategori_2', $data_qt_lama->kategori_1)
                            ->where('kategori_3', $data_qt_lama->kategori_2)
                            ->where('param', json_encode($data_qt_lama->parameter))
                            ->where('regulasi', json_encode($data_qt_lama->regulasi))
                            ->where('active', 0)
                            ->orderBy('no_sample', 'DESC')
                            ->get();

                            foreach ($update_order_detail_lama as $tt => $rr) {
                                $par = [];
                                foreach($data_qt_baru->parameter as $k => $v){
                                    array_push($par, explode(';',$v)[1]);
                                }

                                $data_insert = [
                                    'kategori_1' => $value_baru->status_sampling,
                                    'kategori_2' => explode("-", $data_qt_baru->kategori_1)[0],
                                    'kategori_3' => explode("-", $data_qt_baru->kategori_2)[0],
                                    'param'     => json_encode($par),
                                    'regulasi' => json_encode($data_qt_baru->regulasi),
                                    'tgl_tugas' => $tgl,
                                    'update_at' => DATE('Y-m-d H:i:s')
                                ];

                                $last_id = DB::connection(env('DB_PRODUKSI'))
                                ->table('t_po')
                                ->where('no_sample', $rr->no_sample)
                                ->update($data_insert);

                                $rr->kategori_1 = $value_baru->status_sampling;
                                $rr->kategori_2 = $data_qt_baru->kategori_1;
                                $rr->kategori_3 = $data_qt_baru->kategori_2;
                                $rr->param = json_encode($data_qt_baru->parameter);
                                $rr->regulasi = json_encode($data_qt_baru->regulasi);
                                $rr->tgl_sampling = $tgl;
                                $rr->update_at = DATE('Y-m-d H:i:s');
                                $rr->save();
                            }

                            if($data_qt_lama->jumlah_titik > $data_qt_baru->jumlah_titik){
                                // Mengurangi sekaligus update order detail

                                $jumlah_titik_lama = $data_qt_lama->jumlah_titik - $data_qt_baru->jumlah_titik;

                                $data_qt_baru->jumlah_titik = $jumlah_titik_lama;
                                $data_qt_baru->status_sampling = $value_lama->status_sampling;
                                $pengurangan_data[$val_lama->periode_kontrak][] = $data_qt_baru;
                                
                            } else if($data_qt_lama->jumlah_titik < $data_qt_baru->jumlah_titik){
                                // Menambah sekaligus update order detail

                                $jumlah_titik_baru = $data_qt_baru->jumlah_titik - $data_qt_lama->jumlah_titik;

                                $data_qt_baru->jumlah_titik = $jumlah_titik_baru;
                                $data_qt_baru->status_sampling = $value_baru->status_sampling;
                                $penambahan_data[$val_lama->periode_kontrak][] = $data_qt_baru;

                            } 
                        }
                    } else {
                        $cek_order_detail_lama = OrderD::where('id_order_header', $data_lama->id_order)
                            ->where('periode', $val_lama->periode_kontrak)
                            ->where('kategori_2', $data_qt_baru->kategori_1)
                            ->where('kategori_3', $data_qt_baru->kategori_2)
                            ->where('param', json_encode($data_qt_baru->parameter))
                            ->where('regulasi', json_encode($data_qt_baru->regulasi))
                            ->where('active', 0)
                            ->orderBy('no_sample', 'DESC')
                            ->get();

                        if($cek_order_detail_lama->isNotEmpty()){
                            $jumlah_titik_lama = $cek_order_detail_lama->count();
                            $jumlah_titik_baru = $data_qt_baru->jumlah_titik;

                            if($jumlah_titik_baru != $jumlah_titik_lama){
                                if($jumlah_titik_lama < $jumlah_titik_baru){
                                    // tambah data

                                    $titik = $jumlah_titik_baru - $jumlah_titik_lama;
                                    $data_qt_baru->jumlah_titik = $titik;
                                    $data_qt_baru->status_sampling = $value_baru->status_sampling;
                                    $penambahan_data[$val_lama->periode_kontrak][] = $data_qt_baru;

                                } else if ($jumlah_titik_lama > $jumlah_titik_baru){
                                    // pengurangan titik
                                    
                                    $titik = $jumlah_titik_lama - $jumlah_titik_baru;

                                    $data_qt_baru->jumlah_titik = $titik;
                                    $data_qt_baru->status_sampling = $value_lama->status_sampling;
                                    $pengurangan_data[$val_lama->periode_kontrak][] = $data_qt_baru;

                                } else {
                                    foreach ($cek_order_detail_lama as $tt => $rr) {
                                        $last_id = DB::connection(env('DB_PRODUKSI'))
                                        ->table('t_po')
                                        ->where('no_sample', $rr->no_sample)
                                        ->update([
                                            'tgl_tugas' => $tgl,
                                            'kategori_1' => $value_baru->status_sampling,
                                            'update_at' => DATE('Y-m-d H:i:s')
                                        ]);

                                        $rr->tgl_sampling = $tgl;
                                        $rr->kategori_1 = $value_baru->status_sampling;
                                        $rr->update_at = DATE('Y-m-d H:i:s');
                                        $rr->save();
                                    }
                                }
                            } else {
                                foreach ($cek_order_detail_lama as $tt => $rr) {
                                    $last_id = DB::connection(env('DB_PRODUKSI'))
                                    ->table('t_po')
                                    ->where('no_sample', $rr->no_sample)
                                    ->update([
                                        'tgl_tugas' => $tgl,
                                        'kategori_1' => $value_baru->status_sampling,
                                        'update_at' => DATE('Y-m-d H:i:s')
                                    ]);

                                    $rr->tgl_sampling = $tgl;
                                    $rr->kategori_1 = $value_baru->status_sampling;
                                    $rr->update_at = DATE('Y-m-d H:i:s');
                                    $rr->save();
                                }
                            }
                        } else {
                            $data_qt_baru->status_sampling = $value_baru->status_sampling;
                            $penambahan_data[$val_lama->periode_kontrak][] = $data_qt_baru;
                        }
                    }
                } else {
                    //data barunya tidak ada, jadi data lama harus di hapus

                    $pengurangan_data[$val_lama->periode_kontrak][] = $data_qt_lama;
                }
            }
        }

        $result['pengurangan_data'] = $pengurangan_data;
        $result['penambahan_data'] = $penambahan_data;

        $this->result = (object)$result;
    }

}