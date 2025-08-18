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
use App\Models\TmpOrderD;
use App\Models\Pricelist;

class KalibrasiNoSample{

    public function __construct(){
        
    }

    public function call(){
        
    }


    public function get(){
        $val_lama = $this->val_lama;
        $val_baru = $this->val_baru;
        $id_order = $this->id_order;
        $db = $this->db;
        $value_baru = $this->value_baru;
        $value_lama = $this->value_lama;

        $penambahan_data = [];
        $pengurangan_data = [];
        $perubahan_data = [];
        $result = [];
        
        
        if (count($val_lama->data_sampling) < count($val_baru->data_sampling)){
            //kategori data lama lebih sedikit dari data baru yang artinya ada yg ditambah
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
                                $update_order_detail_lama = TmpOrderD::where('id_order_header', $id_order)
                                ->where('periode', $val_lama->periode_kontrak)
                                ->where('kategori_2', $data_qt_lama->kategori_1)
                                ->where('kategori_3', $data_qt_lama->kategori_2)
                                ->where('param', json_encode($data_qt_lama->parameter))
                                ->where('regulasi', json_encode($data_qt_lama->regulasi))
                                ->where('active', 0)
                                ->orderBy('no_sample', 'DESC')
                                ->get();
                            }
                        } else {
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
                        //ada beda kategori
                        $cek_order_detail_lama = TmpOrderD::where('id_order_header', $id_order)
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
                $val_lama->data_sampling = array_values($val_lama->data_sampling);
            } 

            else {
                $jumlah_titik_lama = [];
                $jumlah_titik_baru = [];
                $kategori_lama = [];
                $kategori_baru = [];
                $sub_kategori_lama = [];
                $sub_kategori_baru = [];
                foreach ($val_baru->data_sampling as $z => $c) {
                    array_push($jumlah_titik_baru, $c->jumlah_titik);
                    array_push($kategori_baru, $c->kategori_1);
                    array_push($sub_kategori_baru, $c->kategori_2);
                }

                foreach ($val_lama->data_sampling as $z => $c) {
                    array_push($jumlah_titik_lama, $c->jumlah_titik);
                    array_push($kategori_lama, $c->kategori_1);
                    array_push($sub_kategori_lama, $c->kategori_2);
                }

                $lama = array_sum($jumlah_titik_lama);
                $baru = array_sum($jumlah_titik_baru);

                $kategori_lama = array_values(array_unique($kategori_lama));
                $kategori_baru = array_values(array_unique($kategori_baru));

                $sub_kategori_lama = array_values(array_unique($sub_kategori_lama));
                $sub_kategori_baru = array_values(array_unique($sub_kategori_baru));

                // dd($kategori_lama, $kategori_baru);
                if($lama == $baru && $kategori_lama == $kategori_baru && $sub_kategori_lama == $sub_kategori_baru){
                    foreach ($val_lama->data_sampling as $key => $value) {
                        if (!in_array($value, $val_baru->data_sampling)) {
                            $value->status_sampling = $value_lama->status_sampling;
                            $pengurangan_data[$val_lama->periode_kontrak][] = $value;
                            unset($val_lama->data_sampling[$key]);
                        }
                        
                    }

                    $val_lama->data_sampling = array_values($val_lama->data_sampling);
                    if(count($val_lama->data_sampling) != count($val_baru->data_sampling)){
                        foreach ($val_baru->data_sampling as $key => $value) {
                            if (!in_array($value, $val_lama->data_sampling)) {
                                $value->status_sampling = $value_baru->status_sampling;
                                $penambahan_data[$val_baru->periode_kontrak][] = $value;
                                unset($val_baru->data_sampling[$key]);
                            }
                            
                        }
                    }

                    $val_baru->data_sampling = array_values($val_baru->data_sampling);
                }
            }

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
                            } 
                        } else {
                            //update param dan regulasi kemudian cek jumlah titik

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
                        $cek_order_detail_lama = TmpOrderD::where('id_order_header', $id_order)
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