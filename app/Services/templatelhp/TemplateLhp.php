<?php

namespace App\Http\Controllers\templatelhp;

use Auth;
use Validator;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use \App\Services\MpdfService as PDF;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class TemplateLhp extends Controller
{
    protected $filename;

    public function __construct(){
        $this->filename = 'coba';
    }

    public function tanggal_indonesia($tanggal){
        $bulan = array (
        1 =>   	'Januari',
                'Februari',
                'Maret',
                'April',
                'Mei',
                'Juni',
                'Juli',
                'Agustus',
                'September',
                'Oktober',
                'November',
                'Desember'
        );
        if($tanggal != '') {
            $var = explode('-', $tanggal);
            return $var[2] . ' ' . $bulan[ (int)$var[1] ] . ' ' . $var[0];
        }else {
            return '-';
        }
    }
    
    static function lhpAir20Kolom($data, $data1, $mode_download, $custom, $custom2 = null)
    {
        $totData = $data->header_table ? count(json_decode($data->header_table)) : 0;
        // $methode_sampling = $data->methode_sampling ? $data->methode_sampling : '-';
        $period = explode(" - ",$data->periode_analisa);
        $period1 = self::tanggal_indonesia($period[0]);
        $period2 = self::tanggal_indonesia($period[1]);
        $customBod = [];
        $temptArrayPush=[];

        if ($data->methode_sampling != null ) {
            $methode_sampling = "";
            $dataArray = json_decode($data->methode_sampling);

            $result = array_map(function($item) {
                $parts = explode(';', $item);
                $accreditation = strpos($parts[0], 'AKREDITASI') !== false;
                $sni = $parts[1];
                return $accreditation ? "{$sni} <sup style=\"border-bottom: 1px solid;\">a</sup>" : $sni;
            }, $dataArray);

            foreach($result as $index => $item) {
                $methode_sampling .= "<span>
                                        <span>".($index + 1).". ".$item."</span>
                                    </span><br>";
            }

        } else {
            $methode_sampling = "-";
        }
        
        if(!empty($custom)) {
            foreach($custom as $key => $value) {
                    $bodi = '<div class="left"><table
                                style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                                <thead>
                                <tr>
                                    <th width="25"
                                        class="pd-5-solid-top-center">NO</th>
                                    <th width="200" class="pd-5-solid-top-center">PARAMETER</th>
                                    <th width="60" class="pd-5-solid-top-center">HASIL
                                        UJI</th>
                                    <th width="85" class="pd-5-solid-top-center">BAKU
                                        MUTU**</th>
                                    <th width="50" class="pd-5-solid-top-center">SATUAN</th>
                                    <th width="220" class="pd-5-solid-top-center">SPESIFIKASI
                                        METODE</th>
                                </tr></thead><tbody>';
                $tot = count($value);
                foreach( $value as $k => $v ) {
                    // dd($v['parameter']);
                    if(!empty($v['attr'])){
                        if(!in_array($v['attr'],$temptArrayPush)){
                            $temptArrayPush[]=$v['attr'];
                        }
                    }

                    if(!empty($v['akr'])){
                        if(!in_array($v['akr'],$temptArrayPush)){
                            $temptArrayPush[]=$v['akr'];
                        }
                    }


                    $satuan = $v['satuan'] != "null" ? $v['satuan'] : '';
                    $baku = str_replace("[", "", $v['baku_mutu']);
                    $baku = str_replace("]", "", $baku);
                    $baku = str_replace('"', '', $baku);
                    $i = $k + 1;
                    $akr = '&nbsp;&nbsp;';
                    if($v['akr']!='')$akr = $v['akr'];
                    if($i == $tot) {
                        $bodi .= '<tr>
                                    <td class="pd-5-solid-center">' . $i . '</td>
                                    <td class="pd-5-solid-left">'.$akr.'&nbsp;'.$v['parameter'].'</td>
                                    <td class="pd-5-solid-center">'.str_replace('.',',',$v['hasil_uji']).'&nbsp;'.$v['attr'].'</td>
                                    <td class="pd-5-solid-center">'.$baku.'</td>
                                    <td class="pd-5-solid-center">'.$satuan.'</td>
                                    <td class="pd-5-solid-left">'.$v['methode'].'</td>
                                </tr>';
                    }else {
                    $bodi .= '<tr>
                            <td class="pd-5-dot-center">' . $i . '</td>
                            <td class="pd-5-dot-left">'.$akr.'&nbsp;'.$v['parameter'].'</td>
                            <td class="pd-5-dot-center">'.str_replace('.',',',$v['hasil_uji']).'&nbsp;'.$v['attr'].'</td>
                            <td class="pd-5-dot-center">'.$baku.'</td>
                            <td class="pd-5-dot-center">'.$satuan.'</td>
                            <td class="pd-5-dot-left">'.$v['methode'].'</td>
                        </tr>';
                    }
                }
                $bodi .= '</tbody></table></div>'; 
                array_push($customBod, $bodi);
            }
        }else {
            $bodi = '<div class="left"><table
                            style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                            <thead>
                            <tr>
                                <th width="25"
                                    class="pd-5-solid-top-center">NO</th>
                                <th width="200" class="pd-5-solid-top-center">PARAMETER</th>
                                <th width="60" class="pd-5-solid-top-center">HASIL
                                    UJI</th>
                                <th width="85" class="pd-5-solid-top-center">BAKU
                                    MUTU**</th>
                                <th width="50" class="pd-5-solid-top-center">SATUAN</th>
                                <th width="220" class="pd-5-solid-top-center">SPESIFIKASI
                                    METODE</th>
                            </tr></thead><tbody>';
            $tot = count($data1);
            foreach( $data1 as $k => $v ) {
                // dd($v['parameter']);
                if(!empty($v['attr'])){
                    if(!in_array($v['attr'],$temptArrayPush)){
                        $temptArrayPush[]=$v['attr'];
                    }
                }

                if(!empty($v['akr'])){
                    if(!in_array($v['akr'],$temptArrayPush)){
                        $temptArrayPush[]=$v['akr'];
                    }
                }
                $satuan = $v['satuan'] != "null" ? $v['satuan'] : '';
                $baku = str_replace("[", "", $v['baku_mutu']);
                $baku = str_replace("]", "", $baku);
                $baku = str_replace('"', '', $baku);
                $i = $k + 1;
                $akr = '&nbsp;&nbsp;';
                if($v['akr']!='')$akr = $v['akr'];
                if($i == $tot) {

                    $bodi .= '<tr>
                                <td class="pd-5-solid-center">' . $i . '</td>
                                <td class="pd-5-solid-left">'.$akr.'&nbsp;'.$v['parameter'].'</td>
                                <td class="pd-5-solid-center">'. str_replace('.',',',$v['hasil_uji']) .'&nbsp;'.$v['attr'].'</td>
                                <td class="pd-5-solid-center">'.$baku.'</td>
                                <td class="pd-5-solid-center">'.$satuan.'</td>
                                <td class="pd-5-solid-left">'.$v['methode'].'</td>
                            </tr>';
                }else {
                   
                $bodi .= '<tr>
                        <td class="pd-5-dot-center">' . $i . '</td>
                        <td class="pd-5-dot-left">'.$akr.'&nbsp;'.$v['parameter'].'</td>
                        <td class="pd-5-dot-center">'. str_replace('.',',',$v['hasil_uji']) .'&nbsp;'.$v['attr'].'</td>
                        <td class="pd-5-dot-center">'.$baku.'</td>
                        <td class="pd-5-dot-center">'.$satuan.'</td>
                        <td class="pd-5-dot-left">'.$v['methode'].'</td>
                    </tr>';
                }
            }
            $bodi .= '</tbody></table></div>'; 
            array_push($customBod, $bodi);
        }
        if($mode_download == 'downloadWSDraft' || $mode_download == 'draft_customer') { 
            $pading = 'margin-bottom: 40px;';
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
        }else if($mode_download == 'downloadLHP') {
            $pading = '';
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
        }  
        $ttd = '<table
            style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px"
            width="100%">
            <tr>
                <td>Tangerang, '.self::tanggal_indonesia($data->tgl_lhp).'</td>
            </tr>
        </table>
        <table
            style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; '.$pading.'"
            width="100%">
            <tr>
                <td>
                    <span
                        style="font-weight: bold; border-bottom: 1px solid #000">('.$data->nama_karyawan.')</span>
                </td>
            </tr>
            <tr>
                <td>
                    '.$data->jabatan_karyawan.'
                </td>
            </tr>
        </table>';

        $header = '<table width="100%"
                    style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td>
                            <span
                                style="font-weight: bold; border-bottom: 1px solid #000">'.$title_lhp.'</span>
                        </td>
                    </tr>
                </table><div class="right" style="margin-top: 13.9px;">
                        <table
                            style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                            <tr>
                                <td>
                                    <table
                                        style="border-collapse: collapse; text-align: center;"
                                        width="100%">
                                        <tr>
                                            <td class="pd-5-solid-top-center" width="120">No. LHP</td>
                                            <td class="pd-5-solid-top-center" width="120">No.
                                                SAMPLE</td>
                                            <td class="pd-5-solid-top-center" width="200">JENIS
                                                SAMPLE</td>
                                        </tr>
                                        <tr>
                                            <td class="pd-5-solid-center">'.$data->no_lhp.'</td>
                                            <td class="pd-5-solid-center">'.$data->no_sample.'</td>
                                            <td class="pd-5-solid-center">'.$data->sub_kategori.'</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <table style="padding: 20px 0px 0px 0px;"
                                        width="100%">
                                        <tr>
                                            <td><span
                                                    style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                    Pelanggan</span></td>
                                        </tr>
                                        <tr>
                                            <td class="custom5" width="120">Nama
                                                Pelanggan</td>
                                            <td class="custom5" width="12">:</td>
                                            <td class="custom5">'.$data->nama_pelanggan.'</td>
                                        </tr>
                                    </table>
                                    <table style="padding: 10px 0px 0px 0px;"
                                        width="100%">
                                        <tr>
                                            <td class="custom5" width="120">Alamat /
                                                Lokasi
                                                Sampling</td>
                                            <td class="custom5" width="12">:</td>
                                            <td class="custom5">'.$data->alamat_sampling.'</td>
                                        </tr>
                                    </table>
                                    <table style="padding: 10px 0px 0px 0px;"
                                        width="100%">
                                        <tr>
                                            <td class="custom5" width="120"><span
                                                    style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                    Sampling</span></td>
                                        </tr>
                                        <tr>
                                            <td class="custom5" width="120">Tanggal
                                                Sampling</td>
                                            <td class="custom5" width="12">:</td>
                                            <td class="custom5">'.self::tanggal_indonesia($data->tanggal_sampling).'</td>
                                        </tr>
                                        <tr>
                                            <td class="custom5">Metode Sampling</td>
                                            <td class="custom5">:</td>
                                            <td class="custom5">'.$methode_sampling.'</td>
                                        </tr>
                                        <tr>
                                            <td class="custom5">Deskripsi Sampel</td>
                                            <td class="custom5">:</td>
                                            <td class="custom5">'.$data->deskripsi_titik.'</td>
                                        </tr>
                                    </table>
                                    <table style="padding: 10px 0px 0px 0px;"
                                        width="100%">
                                        <tr>
                                            <td class="custom5" width="120">Periode
                                                Analisa</td>
                                            <td class="custom5" width="12">:</td>
                                            <td class="custom5">'.$period1. ' - '.$period2.'</td>
                                        </tr>
                                    </table>';
                                    $header .= '<table style="padding: 10px 0px 0px 0px;" width="100%">';
                                    
                                    if($data->regulasi != null){
                                        foreach(json_decode($data->regulasi) as $t => $y) {
                                            $header .=  '<tr>
                                             <td class="custom5" colspan="3">**'.$y.'</td>
                                         </tr>';
                                        }
                                    }
                                $header .= '</table>';
                                $header .= '<table style="padding: 5px 0px 0px 10px;"
                                    width="100%">';
                                // dd($data->keterangan);
                                if($data->keterangan != null){
                                    foreach(json_decode($data->keterangan) as $py => $vx) {
                                        foreach($temptArrayPush as $symbol){
                                            if(strpos($vx,$symbol) === 0){
                                                $header .= '<tr>
                                                                <td class="custom5" colspan="3">'.$vx.'
                                                                </td>
                                                            </tr>';
                                            }
                                        }
                                    };
                                }
                            $header .='</table></td>
                            </tr>
                        </table>
                    </div>';
        $url = request()->headers->all()['origin'][0];
        $file_qr = "$url/utc/apps/public/qr_documents/$data->file_qr.svg";
        if($mode_download == 'downloadWSDraft') { 
            $qr_img = '';
        }else if($mode_download == 'downloadLHP') {
            if (!is_null($data->file_qr)) {
                $qr_img = '<img src="' .$file_qr.'" width="45px" height="45px" style="margin-top: 10px;">';
            }  else {
                $qr_img = '';
            }
        }
        $no_lhp = str_replace("/", "-", $data->no_lhp);
        $name = 'LHP-'.$no_lhp.'.pdf';
        // dd($bodi, $header, $name, $ttd, $qr_img, $mode_download);
        $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download, $custom2);

        return $ress;
    }

    static function lhpAirLebih20Kolom($data, $data1, $mode_download, $custom, $custom2 = null)
    {
        
        $totData = $data->header_table ? count(json_decode($data->header_table)) : 0;
        $colc = '';
        $rowc = '';
        if($totData > 1) {
            $colc = 'colspan="'.$totData.'"';
            $rowc = 'rowspan="2"';
        }
        // $methode_sampling = $data->methode_sampling ? $data->methode_sampling : '-';

        if ($data->methode_sampling != null ) {
            $methode_sampling = "";
            $dataArray = json_decode($data->methode_sampling);

            $result = array_map(function($item) {
                $parts = explode(';', $item);
                $accreditation = strpos($parts[0], 'AKREDITASI') !== false;
                $sni = $parts[1];
                return $accreditation ? "{$sni} <sup style=\"border-bottom: 1px solid;\">a</sup>" : $sni;
            }, $dataArray);

            foreach($result as $index => $item) {
                $methode_sampling .= "<span>
                                        <span>".($index + 1).". ".$item."</span>
                                    </span><br>";
            }

        } else {
            $methode_sampling = "-";
        }
        
        $period = explode(" - ",$data->periode_analisa);
        // dd($period);
        $period1 = '';
        $period2 = '';
        if (!empty($period) && count($period) > 1) {
            $period1 = self::tanggal_indonesia($period[0]);
            $period2 = self::tanggal_indonesia($period[1]); 
        }
        // =================Isi Data==================
        $customBod = [];
        $temptArrayPush=[];
        if(!empty($custom)) {
                foreach($custom as $key => $value) {
                    $bodi = '<div class="left"><table
                                    style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                                    <thead>
                                    <tr>
                                        <th width="25"
                                            class="pd-5-solid-top-center" '.$rowc.'>NO</th>
                                        <th width="200" class="pd-5-solid-top-center" '.$rowc.'>PARAMETER</th>
                                        <th width="60" class="pd-5-solid-top-center" '.$rowc.'>HASIL
                                            UJI</th>
                                        <th width="85" class="pd-5-solid-top-center" '.$colc.'>BAKU
                                            MUTU**</th>
                                        <th width="50" class="pd-5-solid-top-center" '.$rowc.'>SATUAN</th>
                                        <th width="220" class="pd-5-solid-top-center" '.$rowc.'>SPESIFIKASI
                                            METODE</th>
                                    </tr><tr>';
                                    if($totData > 1) {
                                        foreach(json_decode($data->header_table) as $key => $val) {
                                         $bodi .= '<th class="pd-5-solid-top-center" width="50">'.$val.'</th>';
                                    }
                   }
                   $bodi .= '</tr></thead><tbody>';
                //    dd($value);
                   $totdat = count($value);
                    foreach($value as $k => $v) {
                         $i = $k + 1;
                         if(!empty($v['akr'])){
                            if(!in_array($v['akr'],$temptArrayPush)){
                                $temptArrayPush[]=$v['akr'];
                            }
                         }

                         if(!empty($v['attr'])){
                            if(!in_array($v['attr'],$temptArrayPush)){
                                $temptArrayPush[]=$v['attr'];
                            }
                         }

                         $akr = '&nbsp;&nbsp;';
                        if($v['akr']!='')$akr = $v['akr'];
                         $satuan = $v['satuan'] != "null" ? $v['satuan'] : '';
                         if($i == $totdat) {
                             $bodi .= '<tr>
                                     <td class="pd-5-solid-center">' . $i . '</td>
                                     <td class="pd-5-solid-left">'.$akr.'&nbsp;'.$v['parameter'].'</td>
                                     <td class="pd-5-solid-center">'. str_replace('.',',',$v['hasil_uji']) .'&nbsp;'.$v['attr'].'</td>';
                                 foreach(json_decode($v['baku_mutu']) as $kk => $vv) {
                                     $bodi .= '<td class="pd-5-solid-center">'.$vv.'</td>';
                                 }
                                     $bodi .= '<td class="pd-5-solid-center">'.$satuan.'</td>
                                     <td class="pd-5-solid-left">'.$v['methode'].'</td>
                                 </tr>';
                                }else {
                             $bodi .= '<tr>
                                     <td class="pd-5-dot-center">' . $i . '</td>
                                     <td class="pd-5-dot-left">'.$akr.'&nbsp;'.$v['parameter'].'</td>
                                     <td class="pd-5-dot-center">'. str_replace('.',',',$v['hasil_uji']) .'&nbsp;'.$v['attr'].'</td>';
                                 foreach(json_decode($v['baku_mutu']) as $kk => $vv) {
                                     $bodi .= '<td class="pd-5-dot-center">'.$vv.'</td>';
                                 }
                                     $bodi .= '<td class="pd-5-dot-center">'.$satuan.'</td>
                                     <td class="pd-5-dot-left">'.$v['methode'].'</td>
                                 </tr>';
                         }
                    }
                     $bodi .= '</tbody></table></div>';
                    array_push($customBod, $bodi);

                }
        }else {
            // $bodi = '<div class="left"><table
            //                 style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            //                 <thead>
            //                 <tr>
            //                     <th width="10"
            //                         class="pd-5-solid-top-center" rowspan="2">NO</th>
            //                     <th width="150" class="pd-5-solid-top-center" rowspan="2">PARAMETER</th>
            //                     <th width="30" class="pd-5-solid-top-center" rowspan="2">HASIL
            //                         UJI</th>
            //                     <th width="190" class="pd-5-solid-top-center" rowspan="2">BAKU
            //                         MUTU**</th>
            //                     <th width="20" class="pd-5-solid-top-center" rowspan="2">SATUAN</th>
            //                     <th width="150" class="pd-5-solid-top-center" rowspan="2">SPESIFIKASI
            //                         METODE</th>
            //                 </tr><tr>';
            $bodi = '<div class="left"><table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                <thead>
                    <tr>
                        <th width="25" class="pd-5-solid-top-center" rowspan="2">NO</th>
                        <th width="200" class="pd-5-solid-top-center" rowspan="2">PARAMETER</th>
                        <th width="60" class="pd-5-solid-top-center" rowspan="2">HASIL UJI</th>';

            if ($totData == 1) {
                $bodi .= '<th width="85" class="pd-5-solid-top-center" rowspan="2">BAKU MUTU**</th>';
            } else {
                $bodi .= '<th width="85" class="pd-5-solid-top-center" colspan="' . $totData . '">BAKU MUTU**</th>';
            }
            $bodi .='<th width="50" class="pd-5-solid-top-center" rowspan="2">SATUAN</th>
                                 <th width="220" class="pd-5-solid-top-center" rowspan="2">SPESIFIKASI
                                     METODE</th>
                             </tr><tr>';
            if($totData !== 1) {
                foreach(json_decode($data->header_table) as $key => $val) {
                    $bodi .= '<th class="pd-5-solid-top-center" width="50">'.$val.'</th>';
                }
            }
           $bodi .= '<tr></thead><tbody>';
           $totdat = count($data1);
            foreach($data1 as $k => $v) {
                 $i = $k + 1;
                 if(!empty($v['akr'])){
                    if(!in_array($v['akr'],$temptArrayPush)){
                        $temptArrayPush[]=$v['akr'];
                    }
                 }

                 if(!empty($v['attr'])){
                    if(!in_array($v['attr'],$temptArrayPush)){
                        $temptArrayPush[]=$v['attr'];
                    }
                 }
                 $akr = '&nbsp;&nbsp;';
                if($v['akr']!='')$akr = $v['akr'];
                 $satuan = $v['satuan'] != "null" ? $v['satuan'] : '';
                 if($i == $totdat) {
                     $bodi .= '<tr>
                             <td class="pd-5-solid-center">' . $i . '</td>
                             <td class="pd-5-solid-left">'.$akr.'&nbsp;'.$v['parameter'].'</td>
                             <td class="pd-5-solid-center">'. str_replace('.',',',$v['hasil_uji']) .'&nbsp;'.$v['attr'].'</td>';
                         foreach(json_decode($v['baku_mutu']) as $kk => $vv) {
                             $bodi .= '<td class="pd-5-solid-center">'.$vv.'</td>';
                         }
                             $bodi .= '<td class="pd-5-solid-center">'.$satuan.'</td>
                             <td class="pd-5-solid-left">'.$v['methode'].'</td>
                         </tr>';
                        }else {
                     $bodi .= '<tr>
                             <td class="pd-5-dot-center">' . $i . '</td>
                             <td class="pd-5-dot-left">'.$akr.'&nbsp;'.$v['parameter'].'</td>
                             <td class="pd-5-dot-center">'. str_replace('.',',',$v['hasil_uji']) .'&nbsp;'.$v['attr'].'</td>';
                         foreach(json_decode($v['baku_mutu']) as $kk => $vv) {
                             $bodi .= '<td class="pd-5-dot-center">'.$vv.'</td>';
                         }
                             $bodi .= '<td class="pd-5-dot-center">'.$satuan.'</td>
                             <td class="pd-5-dot-left">'.$v['methode'].'</td>
                         </tr>';
                 }
            }
             $bodi .= '</tbody></table></div>';
            array_push($customBod, $bodi);
        }
        // =================Isi Kolom Kanan==================
            if($mode_download == 'downloadWSDraft') { 
                $pading = 'margin-bottom: 40px;';
                $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            }else if($mode_download == 'downloadLHP') {
                $pading = '';
                $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            }  
                $ttd = '<table
                    style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px"
                    width="100%">
                    <tr>
                        <td>Tangerang, '.self::tanggal_indonesia($data->tgl_lhp).'</td>
                    </tr>
                </table>
                <table
                    style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; '.$pading.'"
                    width="100%">
                    <tr>
                        <td>
                            <span
                                style="font-weight: bold; border-bottom: 1px solid #000">('.$data->nama_karyawan.')</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            '.$data->jabatan_karyawan.'
                        </td>
                    </tr>
                </table>';
                $header = '<table width="100%"
                    style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td>
                            <span
                                style="font-weight: bold; border-bottom: 1px solid #000">'.$title_lhp.'</span>
                        </td>
                    </tr>
                </table><div class="right" style="margin-top: 13.9px;">
                    <table
                        style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            <td>
                                <table
                                    style="border-collapse: collapse; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td class="custom" width="120">No. LHP</td>
                                        <td class="custom" width="120">No.
                                            SAMPLE</td>
                                        <td class="custom" width="200">JENIS
                                            SAMPLE</td>
                                    </tr>
                                    <tr>
                                        <td class="custom">'.$data->no_lhp.'</td>
                                        <td class="custom">'.$data->no_sample.'</td>
                                        <td class="custom">'.$data->sub_kategori.'</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table style="padding: 20px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Pelanggan</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Nama
                                            Pelanggan</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.$data->nama_pelanggan.'</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120">Alamat /
                                            Lokasi
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.$data->alamat_sampling.'</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120"><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Sampling</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Tanggal
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.self::tanggal_indonesia($data->tanggal_sampling).'</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Metode Sampling</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">'.$methode_sampling.'</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Keterangan</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">'.$data->deskripsi_titik.'</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Titik Koordinat</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">'.$data->titik_koordinat.'</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120">Periode
                                            Analisa</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.$period1. ' - '.$period2.'</td>
                                    </tr></table>';

                                $header .= '<table style="padding: 10px 0px 0px 0px;" width="100%">';
                                    if($data->regulasi != null){
                                        foreach(json_decode($data->regulasi) as $t => $y) {
                                            $header .=  '<tr>
                                             <td class="custom5" colspan="3">**'.$y.'**</td>
                                         </tr>';
                                         }
                                    }
                                    
                                $header .= '</table>';
                    
                    $header .= '<table style="padding: 5px 0px 0px 10px;"
                                    width="100%">';
                    if($data->keterangan != null){
                        foreach(json_decode($data->keterangan) as $py => $vx) {
                            foreach($temptArrayPush as $symbol){
                                if(strpos($vx,$symbol) === 0){
                                    $header .= '<tr>
                                                    <td class="custom5" colspan="3">'.$vx.'
                                                    </td>
                                                </tr>';
                                }
                            }
                        };
                    }
                            $header .='</table>
                            </td>
                        </tr>
                        </table>
                </div>';
         $url = request()->headers->all()['origin'][0];
        $file_qr = "$url/utc/apps/public/qr_documents/$data->file_qr.svg";
        if($mode_download == 'downloadWSDraft') { 
            $qr_img = '';
        }else if($mode_download == 'downloadLHP') {
            if (!is_null($data->file_qr)) {
                $qr_img = '<img src="' .$file_qr.'" width="45px" height="45px" style="margin-top: 10px;">';
            }  else {
                $qr_img = '';
            }
        }
        $no_lhp = str_replace("/", "-", $data->no_lhp);
        $name = 'LHP - '.$no_lhp.'.pdf';
        $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download);
        return $ress;
    }
    static function lhpPadatan($data, $data1, $mode_download, $custom)
    {
        
        $totData = count(json_decode($data->header_table));
        // $methode_sampling = $data->methode_sampling ? $data->methode_sampling : '-';
        $period = explode(" - ",$data->periode_analisa);
        $period1 = self::tanggal_indonesia($period[0]);
        $period2 = self::tanggal_indonesia($period[1]);
        // =================Isi Data==================
        $customBod = [];
        if(!empty($custom)) {

        }else {
            $bodi = '<div class="left"><table
                            style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                            <thead>
                            <tr>
                                <th width="10"
                                    class="pd-5-solid-top-center" rowspan="2">NO</th>
                                <th width="150" class="pd-5-solid-top-center" rowspan="2">PARAMETER</th>
                                <th width="30" class="pd-5-solid-top-center" rowspan="2">HASIL
                                    UJI</th>
                                <th width="190" class="pd-5-solid-top-center" colspan="'.$totData.'">BAKU
                                    MUTU**</th>
                                <th width="20" class="pd-5-solid-top-center" rowspan="2">SATUAN</th>
                                <th width="150" class="pd-5-solid-top-center" rowspan="2">SPESIFIKASI
                                    METODE</th>
                            </tr><tr>';
           foreach(json_decode($data->header_table) as $key => $value) {
            $bodi .= '<th class="pd-5-solid-top-center" width="50">'.$value.'</th>';
           }
           $bodi .= '</tr></thead><tbody>';
           $totdat = count($data1);
            foreach($data1 as $k => $v) {
                 $i = $k + 1;
                 $akr = '&nbsp;&nbsp;';
                if($v->akr!='')$akr = $v->akr;
                 $satuan = $v->satuan != "null" ? $v->satuan : '';
                 if($i == $totdat) {
                     $bodi .= '<tr>
                             <td class="pd-5-solid-center">' . $i . '</td>
                             <td class="pd-5-solid-left">'.$akr.'&nbsp;'.$v->parameter.'</td>
                             <td class="pd-5-solid-center">'.$v->hasil_uji.'&nbsp;'.$v->attr.'</td>';
                         foreach(json_decode($v->baku_mutu) as $kk => $vv) {
                             $bodi .= '<td class="pd-5-solid-center">'.$vv.'</td>';
                         }
                             $bodi .= '<td class="pd-5-solid-center">'.$satuan.'</td>
                             <td class="pd-5-solid-left">'.$v->methode.'</td>
                         </tr>';
                        }else {
                     $bodi .= '<tr>
                             <td class="pd-5-dot-center">' . $i . '</td>
                             <td class="pd-5-dot-left">'.$akr.'&nbsp;'.$v->parameter.'</td>
                             <td class="pd-5-dot-center">'.$v->hasil_uji.'&nbsp;'.$v->attr.'</td>';
                         foreach(json_decode($v->baku_mutu) as $kk => $vv) {
                             $bodi .= '<td class="pd-5-dot-center">'.$vv.'</td>';
                         }
                             $bodi .= '<td class="pd-5-dot-center">'.$satuan.'</td>
                             <td class="pd-5-dot-left">'.$v->methode.'</td>
                         </tr>';
                 }
            }
            array_push($customBod, $bodi);
        }
        if($mode_download == 'downloadWSDraft') { 
            $pading = 'margin-bottom: 40px;';
        }else if($mode_download == 'downloadLHP') {
            $pading = '';
        }  
        $ttd = '<table
                style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px"
                width="100%">
                <tr>
                    <td>Tangerang, '.self::tanggal_indonesia($data->tgl_lhp).'</td>
                </tr>
            </table>
            <table
                style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; '.$pading.'"
                width="100%">
                <tr>
                    <td>
                        <span
                            style="font-weight: bold; border-bottom: 1px solid #000">('.$data->nama_karyawan.')</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        '.$data->jabatan_karyawan.'
                    </td>
                </tr>
            </table>';
         $header = '</tbody></table></div>';
        // =================Isi Kolom Kanan==================
                $header .= '<table width="100%"
                    style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td>
                            <span
                                style="font-weight: bold; border-bottom: 1px solid #000">LAPORAN
                                HASIL PENGUJIAN</span>
                        </td>
                    </tr>
                </table><div class="right" style="margin-top: 13.9px;">
                    <table
                        style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            <td>
                                <table
                                    style="border-collapse: collapse; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td class="custom" width="120">No. LHP</td>
                                        <td class="custom" width="120">No.
                                            SAMPLE</td>
                                        <td class="custom" width="200">JENIS
                                            SAMPLE</td>
                                    </tr>
                                    <tr>
                                        <td class="custom">'.$data->no_lhp.'</td>
                                        <td class="custom">'.$data->no_sample.'</td>
                                        <td class="custom">'.$data->sub_kategori.'</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table style="padding: 20px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Pelanggan</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Nama
                                            Pelanggan</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.$data->nama_pelanggan.'</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120">Alamat /
                                            Lokasi
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.$data->alamat_sampling.'</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120">Periode
                                            Analisa</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.$period1. ' - '.$period2.'</td>
                                    </tr></table>';

                                $header .= '<table style="padding: 10px 0px 0px 0px;" width="100%">';
                                    foreach(json_decode($data->regulasi) as $t => $y) {
                                       $header .=  '<tr>
                                        <td class="custom5" colspan="3">'.$y.'</td>
                                    </tr>';
                                    }
                                $header .= '</table>';
                    
                    $header .= '<table style="padding: 5px 0px 0px 10px;"
                                    width="100%">';
                    foreach(json_decode($data->keterangan) as $py => $vx) {
                        $header .= '<tr>
                                        <td class="custom5" colspan="3">'.$vx.'
                                        </td>
                                    </tr>';
                    };
                            $header .='</table></td>
                        </tr>
                        </table>
                </div>';
         $url = request()->headers->all()['origin'][0];
        $file_qr = "$url/utc/apps/public/qr_documents/$data->file_qr.svg";
        if($mode_download == 'downloadWSDraft') { 
            $qr_img = '';
        }else if($mode_download == 'downloadLHP') {
            if (!is_null($data->file_qr)) {
                $qr_img = '<img src="' .$file_qr.'" width="45px" height="45px" style="margin-top: 10px;">';
            }  else {
                $qr_img = '';
            }
        }
          
        $no_lhp = str_replace("/", "-", $data->no_lhp);
        $name = 'LHP - '.$no_lhp.'.pdf';
       $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download);
        return $ress;
    }
    
    static function lhpUdaraAdverseOdor($data = array())
    {
        $boddi = '<table
                        style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            <td width="25"
                                class="custom">NO</td>
                            <td width="70" class="custom">WAKTU SURVEI</td>
                            <td width="180" class="custom">KARAKTERISTIK ODOR</td>
                            <td width="180" class="custom">KONTINUITAS</td>
                            <td width="180" class="custom">INTENSITAS</td>
                        </tr>
                        <tr>
                            <td class="custom1">1</td>
                            <td class="custom1"></td>
                            <td class="custom1"></td>
                            <td class="custom1"></td>
                            <td class="custom1"></td>
                        </tr>
                        <tr>
                            <td class="custom1">2</td>
                            <td class="custom1"></td>
                            <td class="custom1"></td>
                            <td class="custom1"></td>
                            <td class="custom1"></td>
                        </tr>
                        <tr>
                            <td class="custom1">3</td>
                            <td class="custom1"></td>
                            <td class="custom1"></td>
                            <td class="custom1"></td>
                            <td class="custom1"></td>
                        </tr>
                        <tr>
                            <td class="custom1">4</td>
                            <td class="custom1"></td>
                            <td class="custom1"></td>
                            <td class="custom1"></td>
                            <td class="custom1"></td>
                        </tr>
                        <tr>
                            <td class="custom1">5</td>
                            <td class="custom1"></td>
                            <td class="custom1"></td>
                            <td class="custom1"></td>
                            <td class="custom1"></td>
                        </tr>
                        <tr>
                            <td class="custom1">6</td>
                            <td class="custom1"></td>
                            <td class="custom1"></td>
                            <td class="custom1"></td>
                            <td class="custom1"></td>
                        </tr>
                        <tr>
                            <td class="custom1">7</td>
                            <td class="custom1"></td>
                            <td class="custom1"></td>
                            <td class="custom1"></td>
                            <td class="custom1"></td>
                        </tr>
                        <tr>
                            <td class="custom3">8</td>
                            <td class="custom3"></td>
                            <td class="custom3"></td>
                            <td class="custom3"></td>
                            <td class="custom4"></td>
                        </tr>
                    </table>
                    <table
                        style="padding: 5px 0px 0px 0px; font-family: Arial, Helvetica, sans-serif; font-size: 10px;"
                        width="95%">
                        <tr>
                            <td>
                                <span
                                    style="font-weight: bold; border-bottom: 1px solid #000">KESIMPULAN</span>
                            </td>
                        </tr>
                    </table>
                    <table
                        style="padding: 0px 0px 0px 0px; font-family: Arial, Helvetica, sans-serif; font-size: 10px;"
                        width="95%">
                        <tr>
                            <td class="custom8">
                                Lorem ipsum dolor sit amet consectetur,
                                adipisicing elit. Nesciunt sapiente eius
                                explicabo repellat reprehenderit. Enim,
                                quisquam! Voluptatem, magni error eligendi
                                quibusdam fugiat eius cum voluptas minima.
                                Tempore culpa assumenda dolores eius repudiandae
                                ipsam sint possimus, quisquam quos? A hic
                                voluptas voluptates magni reiciendis. Porro
                                minus perferendis, eveniet accusamus aperiam
                                praesentium cumque quos tempore debitis cum,
                                libero placeat voluptates eos, provident ad
                                facere labore dolores obcaecati illum dicta quas
                                amet voluptatem corrupti! Necessitatibus
                                inventore nostrum nulla labore consequatur
                                consectetur similique quam! Ab labore libero
                                adipisci laborum cupiditate blanditiis sapiente
                                explicabo sed unde quos ipsam aut molestias
                                natus, hic, earum amet culpa!
                            </td>
                        </tr>
                    </table>
                    <table
                        style="padding: 5px 0px 0px 0px; font-family: Arial, Helvetica, sans-serif; font-size: 10px;"
                        width="95%">
                        <tr>
                            <td>
                                <span
                                    style="font-weight: bold; border-bottom: 1px solid #000">REFERENSI</span>
                            </td>
                        </tr>
                    </table>
                    <table
                        style="padding: 0px 0px 0px 0px; border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;"
                        width="95%">
                        <tr>
                            <td colspan="2" class="custom7">
                                KARAKTERISTIK ODOR
                            </td>
                            <td colspan="2" class="custom7">
                                KARAKTERISTIK ODOR
                            </td>
                        </tr>
                        <tr>
                            <td class="custom7" width="40">
                                KODE
                            </td>
                            <td class="custom7">
                                DESKRIPTOR
                            </td>
                            <td class="custom7" width="40">
                                KODE
                            </td>
                            <td class="custom7">
                                DESKRIPTOR
                            </td>
                        </tr>
                        <tr>
                            <td class="custom7" width="40">
                                1
                            </td>
                            <td class="custom8">
                                Fragrant
                            </td>
                            <td class="custom7" width="40">
                                21
                            </td>
                            <td class="custom8">
                                Like blood, raw meat
                            </td>
                        </tr>
                        <tr>
                            <td class="custom7" width="40">
                                2
                            </td>
                            <td class="custom8">
                                Perfumy
                            </td>
                            <td class="custom7" width="40">
                                22
                            </td>
                            <td class="custom8">
                                Rubbish
                            </td>
                        </tr>
                        <tr>
                            <td class="custom7" width="40">
                                3
                            </td>
                            <td class="custom8">
                                Sweet
                            </td>
                            <td class="custom7" width="40">
                                23
                            </td>
                            <td class="custom8">
                                Compost
                            </td>
                        </tr>
                        <tr>
                            <td class="custom7" width="40">
                                4
                            </td>
                            <td class="custom8">
                                Fruity
                            </td>
                            <td class="custom7" width="40">
                                24
                            </td>
                            <td class="custom8">
                                Silage
                            </td>
                        </tr>
                        <tr>
                            <td class="custom7" width="40">
                                5
                            </td>
                            <td class="custom8">
                                Bakery (fresh bread)
                            </td>
                            <td class="custom7" width="40">
                                25
                            </td>
                            <td class="custom8">
                                Sickening
                            </td>
                        </tr>
                        <tr>
                            <td class="custom7" width="40">
                                6
                            </td>
                            <td class="custom8">
                                Coffee-like
                            </td>
                            <td class="custom7" width="40">
                                26
                            </td>
                            <td class="custom8">
                                Musty, earthy, mouldy
                            </td>
                        </tr>
                        <tr>
                            <td class="custom7" width="40">
                                7
                            </td>
                            <td class="custom8">
                                Spicy
                            </td>
                            <td class="custom7" width="40">
                                27
                            </td>
                            <td class="custom8">
                                Sharp, pungent, acid
                            </td>
                        </tr>
                        <tr>
                            <td class="custom7" width="40">
                                8
                            </td>
                            <td class="custom8">
                                Meaty (cooked)
                            </td>
                            <td class="custom7" width="40">
                                28
                            </td>
                            <td class="custom8">
                                Metallic
                            </td>
                        </tr>
                        <tr>
                            <td class="custom7" width="40">
                                9
                            </td>
                            <td class="custom8">
                                Sea/marine
                            </td>
                            <td class="custom7" width="40">
                                29
                            </td>
                            <td class="custom8">
                                Tar-like
                            </td>
                        </tr>
                        <tr>
                            <td class="custom7" width="40">
                                10
                            </td>
                            <td class="custom8">
                                Herbal, green, cut grass
                            </td>
                            <td class="custom7" width="40">
                                30
                            </td>
                            <td class="custom8">
                                Oily, fatty
                            </td>
                        </tr>
                        <tr>
                            <td class="custom7" width="40">
                                11
                            </td>
                            <td class="custom8">
                                Bark-like
                            </td>
                            <td class="custom7" width="40">
                                31
                            </td>
                            <td class="custom8">
                                Like gasoline, solvent
                            </td>
                        </tr>
                        <tr>
                            <td class="custom7" width="40">
                                12
                            </td>
                            <td class="custom8">
                                Woody, resinous
                            </td>
                            <td class="custom7" width="40">
                                32
                            </td>
                            <td class="custom8">
                                Fishy
                            </td>
                        </tr>
                        <tr>
                            <td class="custom7" width="40">
                                13
                            </td>
                            <td class="custom8">
                                Medicinal
                            </td>
                            <td class="custom7" width="40">
                                33
                            </td>
                            <td class="custom8">
                                Putrid, foul, decayed
                            </td>
                        </tr>
                        <tr>
                            <td class="custom7" width="40">
                                14
                            </td>
                            <td class="custom8">
                                Burnt, smoky
                            </td>
                            <td class="custom7" width="40">
                                34
                            </td>
                            <td class="custom8">
                                Paint-like
                            </td>
                        </tr>
                        <tr>
                            <td class="custom7" width="40">
                                15
                            </td>
                            <td class="custom8">
                                Soapy
                            </td>
                            <td class="custom7" width="40">
                                35
                            </td>
                            <td class="custom8">
                                Rancid
                            </td>
                        </tr>
                        <tr>
                            <td class="custom7" width="40">
                                16
                            </td>
                            <td class="custom8">
                                Garlic, onion
                            </td>
                            <td class="custom7" width="40">
                                36
                            </td>
                            <td class="custom8">
                                Sulphur smelling
                            </td>
                        </tr>
                        <tr>
                            <td class="custom7" width="40">
                                17
                            </td>
                            <td class="custom8">
                                Cooked vegetables
                            </td>
                            <td class="custom7" width="40">
                                37
                            </td>
                            <td class="custom8">
                                Dead animal
                            </td>
                        </tr>
                        <tr>
                            <td class="custom7" width="40">
                                18
                            </td>
                            <td class="custom8">
                                Chemical
                            </td>
                            <td class="custom7" width="40">
                                38
                            </td>
                            <td class="custom8">
                                Faecal (like manure)
                            </td>
                        </tr>
                        <tr>
                            <td class="custom7" width="40">
                                19
                            </td>
                            <td class="custom8">
                                Etherish, anaesthetic
                            </td>
                            <td class="custom7" width="40">
                                39
                            </td>
                            <td class="custom8">
                                Sewer odour
                            </td>
                        </tr>
                        <tr>
                            <td class="custom7" width="40">
                                20
                            </td>
                            <td class="custom8">
                                Sour, acrid, vinegar
                            </td>
                            <td class="custom7" width="40">
                                40
                            </td>
                            <td class="custom8">
                                Other
                            </td>
                        </tr>
                    </table>';

        $kolomkanan = '<td align="top" style="vertical-align: top;">
                    <table
                        style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            <td>
                                <table
                                    style="border-collapse: collapse; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td class="custom" width="120">No. LHP</td>
                                        <td class="custom" width="160">No.
                                            SAMPLE</td>
                                        <td class="custom" width="240">JENIS
                                            SAMPLE</td>
                                    </tr>
                                    <tr>
                                        <td class="custom">2342</td>
                                        <td class="custom">234234234/4543</td>
                                        <td class="custom">AIR</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table style="padding: 20px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Pelanggan</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Nama
                                            Pelanggan</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">dakfjhsdkjfds
                                            fdsjfhdsjfk</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120">Alamat /
                                            Lokasi
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">dfkjdshfikjdsf
                                            dskfjhdskjfds
                                            fdskjfhdskjfhds fdskjfhdsjf</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120"><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Sampling</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Tanggal
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">sdkfjdhskjf
                                            dsfkjhsdfkjdsf</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Metode Sampling</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">sdkfjdhskjf
                                            dsfkjhsdfkjdsf</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Keterangan</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">sdkfjdhskjf
                                            dsfkjhsdfkjdsf</td>
                                    </tr>
                                </table>
                                <table style="padding: 30px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120">Periode
                                            Analisa</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">dsfhjdsf</td>
                                    </tr>
                                </table>
                                <table style="padding: 5px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120"><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">KONTINUITAS</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" colspan="3">
                                            <span style="font-weight: bold;">4</span>&nbsp;&nbsp;=&nbsp;&nbsp;TERUS
                                            MENERUS SEPANJANG
                                            OBSERVASI
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" colspan="3">
                                            <span style="font-weight: bold;">3</span>&nbsp;&nbsp;=&nbsp;&nbsp;HAMPIR
                                            SETIAP WAKTU OBSERVASI
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" colspan="3">
                                            <span style="font-weight: bold;">2</span>&nbsp;&nbsp;=&nbsp;&nbsp;&#60;
                                            50% WAKTU OBSERVASI
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" colspan="3">
                                            <span style="font-weight: bold;">1</span>&nbsp;&nbsp;=&nbsp;&nbsp;SESEKALI
                                            SEPANJANG WAKTU OBERSVASI
                                        </td>
                                    </tr>
                                </table>
                                <table style="padding: 5px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120"><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">KONTINUITAS</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" colspan="3">
                                            <span style="font-weight: bold;">6</span>&nbsp;&nbsp;=&nbsp;&nbsp;CUKUP
                                            KUAT
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" colspan="3">
                                            <span style="font-weight: bold;">5</span>&nbsp;&nbsp;=&nbsp;&nbsp;SANGAT
                                            KUAT
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" colspan="3">
                                            <span style="font-weight: bold;">4</span>&nbsp;&nbsp;=&nbsp;&nbsp;CUKUP
                                            KUAT
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" colspan="3">
                                            <span style="font-weight: bold;">3</span>&nbsp;&nbsp;=&nbsp;&nbsp;JELAS
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" colspan="3">
                                            <span style="font-weight: bold;">2</span>&nbsp;&nbsp;=&nbsp;&nbsp;LEMAH
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" colspan="3">
                                            <span style="font-weight: bold;">1</span>&nbsp;&nbsp;=&nbsp;&nbsp;SANGAT
                                            LEMAH
                                        </td>
                                    </tr>
                                </table>
                                <table
                                    style="padding: 50px 0px 0px 340px; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td>Tangerang,
                                            17
                                            Oktober 2023</td>
                                    </tr>
                                </table>
                                <table
                                    style="padding: 50px 0px 0px 340px; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td>
                                            <span
                                                style="font-weight: bold; border-bottom: 1px solid #000">(Dwi
                                                Meisya
                                                B.)</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            Manager
                                            Teknis
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>';
        $footer = '<table width="100%" style="vertical-align: bottom; font-family: serif;
                                font-size: 5pt; color: #000000;">
                                <tr>
                                    <td width="30%">DP/7.8.1/ISL; Rev 3; 08 November 2022</td>
                                    <td width="70%">Hasil uji ini hanya berlaku untuk sampel yang diuji. Lembar ini tidak boleh diubah ataupun digandakan tanpa izin tertulis dari pihak laboratorium.</td>
                                </tr>
                            </table>';
        $name = 'testzakkiylhp';
        $ress = self::formatTemplate($boddi, $kolomkanan, $footer, $name);
        return $ress;
    }

    static function lhpDirectKebisingan24Jam($data, $data1, $mode_download, $custom)
    {

        $methode_sampling = $data->metode_sampling ? $data->metode_sampling : '-';
        $period = explode(" - ",$data->periode_analisa);
        $period1 = self::tanggal_indonesia($period[0]);
        $period2 = self::tanggal_indonesia($period[1]);
        $customBod = [];
        if(!empty($custom)) {

        }else {
            $bodi = '<div class="left"><table
                            style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                            <thead>
                            <tr>
                                <th rowspan="2" width="25"
                                    class="pd-5-solid-top-center">NO</th>
                                <th rowspan="2" width="270" class="pd-5-solid-top-center">LOKASI /
                                    KETERANGAN
                                    SAMPEL</th>
                                <th width="270" class="pd-5-solid-top-center" colspan="3">
                                    Kebisingan 24
                                    Jam (dBA)
                                </th>
                                <th rowspan="2" width="210" class="pd-5-solid-top-center">TITIK
                                    KOORDINAT</th>
                            </tr>
                            
                            <tr>
                                <th class="pd-5-solid-top-center">Ls (Siang)</th>
                                <th class="pd-5-solid-top-center">Lm (Malam)</th>
                                <th class="pd-5-solid-top-center">Ls-m (Siang-Malam)</th>
                            </tr></thead><tbody>';
                            $tot = count($data1);
                            foreach($data1 as $kk => $yy) {
                                $p = $kk + 1;
                                if($p == $tot) {
                                    $bodi .= '<tr>
                                                    <td class="pd-5-solid-center">'.$p.'</td>
                                                    <td class="pd-5-solid-left"><sup style="font-size:5px; !important; margin-top:-10px;">'.$yy['no_sampel'].'</sup>'.$yy['lokasi_keterangan'].'</td>
                                                    <td class="pd-5-solid-center">'.$yy['leq_ls'].'</td>
                                                    <td class="pd-5-solid-center">'.$yy['leq_lm'].'</td>
                                                    <td class="pd-5-solid-center">'. str_replace('.','.',$yy['hasil']) .'</td>
                                                    <td class="pd-5-solid-left">'.$yy['titik_koordinat'].'</td>
                                                </tr>';
                                }else {
                                    $bodi .= '<tr>
                                                    <td class="pd-5-solid-center">'.$p.'</td>
                                                    <td class="pd-5-solid-left"><sup style="font-size:5px; !important; margin-top:-10px;">'.$yy['no_sampel'].'</sup>'.$yy['lokasi_keterangan'].'</td>
                                                    <td class="pd-5-solid-center">'.$yy['leq_ls'].'</td>
                                                    <td class="pd-5-solid-center">'.$yy['leq_lm'].'</td>
                                                    <td class="pd-5-solid-center">'. str_replace('.',',',$yy['hasil']) .'</td>
                                                    <td class="pd-5-solid-left">'.$yy['titik_koordinat'].'</td>
                                                </tr>';
    
                                }
                            }
                        
            $bodi .= '</tbody></table></div>';
            array_push($customBod, $bodi);
        }
        if($mode_download == 'downloadWSDraft') { 
            $pading = 'margin-bottom: 40px;';
            $title ='LAPORAN HASIL PENGUJIAN';
        }else if($mode_download == 'downloadLHP') {
            $pading = '';
            $title ='LAPORAN HASIL PENGUJIAN';
        }  
        $ttd = '<table
                    style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px"
                    width="100%">
                    <tr>
                        <td>Tangerang, '.self::tanggal_indonesia($data->tgl_lhp).'</td>
                    </tr>
                </table>
                <table
                    style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; '.$pading.'"
                    width="100%">
                    <tr>
                        <td>
                            <span
                                style="font-weight: bold; border-bottom: 1px solid #000">('.$data->nama_karyawan.')</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            '.$data->jabatan_karyawan.'
                        </td>
                    </tr>
                </table>';
        $header = '<table width="100%"
                    style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td>
                            <span
                                style="font-weight: bold; border-bottom: 1px solid #000">"'. $title .'"</span>
                        </td>
                    </tr>
                </table><div class="right" style="margin-top: 13.9px;"><table
                        style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            <td>
                                <table
                                    style="border-collapse: collapse; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td class="custom" width="120">No. LHP</td>
                                        <td class="custom" width="240">JENIS
                                            SAMPLE</td>
                                    </tr>
                                    <tr>
                                        <td class="custom">'.$data->no_lhp.'</td>
                                        <td class="custom">'.$data->sub_kategori.'</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table style="padding: 20px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Pelanggan</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Nama
                                            Pelanggan</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.$data->nama_pelanggan.'</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120">Alamat /
                                            Lokasi
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.$data->alamat_sampling.'</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120"><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Sampling</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Tanggal
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.self::tanggal_indonesia($data->tgl_sampling).'</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Metode Sampling</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">'.$methode_sampling.'</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Periode
                                            Analisa</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">'.$period1. ' - '.$period2.'</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120"><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Kondisi
                                                Lapangan</span></td>
                                    </tr>
                                </table>';
                               $header .= '<table style="padding: 10px 0px 0px 0px;" width="100%">';
                                    foreach(json_decode($data->regulasi) as $t => $y) {
                                       $header .=  '<tr>
                                        <td class="custom5" colspan="3">'.$y.'</td>
                                    </tr>';
                                    }
                                $header .= '</table>';
                                $header .= '<table
                                    style="border-collapse: collapse;"
                                    width="100%">
                                    <tr>
                                        <td class="custom">A</td>
                                        <td class="custom">Peruntukan Kawasan</td>
                                        <td class="custom">Tingkat Kebisingan
                                            (dBA) **</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">1</td>
                                        <td class="custom1">Perumahan dan
                                            Pemukiman</td>
                                        <td class="custom2">55</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">2</td>
                                        <td class="custom1">Perdagangan dan Jasa</td>
                                        <td class="custom2">70</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">3</td>
                                        <td class="custom1">Perkantoran dan
                                            Perdagangan</td>
                                        <td class="custom2">65</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">4</td>
                                        <td class="custom1">Ruang Terbuka Hijau</td>
                                        <td class="custom2">50</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">5</td>
                                        <td class="custom1">Industri</td>
                                        <td class="custom2">70</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">6</td>
                                        <td class="custom1">Pemerintahan dan
                                            Fasilitas Umum</td>
                                        <td class="custom2">60</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">7</td>
                                        <td class="custom1">Rekreasi</td>
                                        <td class="custom2">70</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">8</td>
                                        <td class="custom1">Khusus
                                            :&nbsp;&nbsp;- Pelabuhan Laut</td>
                                        <td class="custom2">70</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">8</td>
                                        <td class="custom1">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;-
                                            Cagar
                                            Budaya</td>
                                        <td class="custom2">60</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">8</td>
                                        <td class="custom1">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;-
                                            Bandar Udara /
                                            Stasiun Kereta Api *)</td>
                                        <td class="custom2"></td>
                                    </tr>
                                    <tr>
                                        <td class="custom">B</td>
                                        <td class="custom">Lingkungan Kerja</td>
                                        <td class="custom"></td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">1</td>
                                        <td class="custom1">Rumah Sakit atau
                                            sejenisnya</td>
                                        <td class="custom2">55</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">2</td>
                                        <td class="custom1">Sekolah atau
                                            sejenisnya</td>
                                        <td class="custom2">55</td>
                                    </tr>
                                    <tr>
                                        <td class="custom9">3</td>
                                        <td class="custom4">Tempat Ibadah atau
                                            sejenisnya</td>
                                        <td class="custom9">55</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="custom">
                                            Keterangan : *) Disesuaikan dengan
                                            ketentuan Menteri Perhubungan
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>';
        $url = request()->headers->all()['origin'][0];
        $file_qr = "$url/utc/apps/public/qr_documents/$data->file_qr.svg";
        if($mode_download == 'downloadWSDraft') { 
            $qr_img = '';
        }else if($mode_download == 'downloadLHP') {
            if (!is_null($data->file_qr)) {
                $qr_img = '<img src="' .$file_qr.'" width="45px" height="45px" style="margin-top: 10px;">';
            }  else {
                $qr_img = '';
            }
        }
        $no_lhp = str_replace("/", "-", $data->no_lhp);
        $name = 'LHP-'.$no_lhp.'.pdf';
        $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download);
        return $ress;
    }

    static function lhpLingkungan($data, $data1, $mode_download, $custom)
    {
        $methode_sampling = $data->metode_sampling ? $data->metode_sampling : '-';
        $period = explode(" - ",$data->periode_analisa);
        $period = array_filter($period);
        // dd($period);
        $period1 = '';
        $period2 = '';
        if (!empty($period) ) {
            $period1 = self::tanggal_indonesia($period[0]);
            $period2 = self::tanggal_indonesia($period[1]); 
        }
        
        $customBod = [];
        if($data->id_kategori_3 == 11) {
            if(!empty($custom)) {
                foreach($custom as $key => $value) {
                    $bodi = '<div class="left"><table
                            style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                            <thead>
                            <tr>
                                <th width="25"
                                    class="pd-5-solid-top-center">NO</th>
                                <th width="170" class="pd-5-solid-top-center">PARAMETER</th>
                                <th width="50" class="pd-5-solid-top-center">DURASI</th>
                                <th width="70" class="pd-5-solid-top-center">HASIL UJI</th>
                                <th width="70" class="pd-5-solid-top-center">BAKU MUTU</th>
                                <th width="50" class="pd-5-solid-top-center">SATUAN</th>
                                <th width="210" class="pd-5-solid-top-center">SPESIFIKASI METODE</th>
                            </tr></thead><tbody>';
                            $tot = count($value);
                            foreach($value as $kk => $yy) {
                                $p = $kk + 1;
                                $akr = ($yy['akr'] != "") ? $yy['akr'] : "&nbsp;&nbsp;";
                                if($p == $tot) {
                                    $bodi .= '<tr>
                                                    <td class="pd-5-solid-center">'.$p.'</td>
                                                    <td class="pd-5-solid-left">'.$akr.'&nbsp;'.$yy['parameter'].'</td>
                                                    <td class="pd-5-solid-center">'.$yy['durasi'].'</td>
                                                    <td class="pd-5-solid-center">'. str_replace('.',',',$yy['hasil_uji']) . $yy['attr'] .'</td>
                                                    <td class="pd-5-solid-center">'.json_decode($yy['baku_mutu'])[0].'</td>
                                                    <td class="pd-5-solid-center">'.$yy['satuan'].'</td>
                                                    <td class="pd-5-solid-left">'.$yy['methode'].'</td>
                                                </tr>';
                                }else {
                                    $bodi .= '<tr>
                                                    <td class="pd-5-dot-center">'.$p.'</td>
                                                    <td class="pd-5-dot-left">'.$akr.'&nbsp;'.$yy['parameter'].'</td>
                                                    <td class="pd-5-dot-center">'.$yy['durasi'].'</td>
                                                    <td class="pd-5-solid-center">'. str_replace('.',',',$yy['hasil_uji']) . $yy['attr'] .'</td>
                                                    <td class="pd-5-dot-center">'.json_decode($yy['baku_mutu'])[0].'</td>
                                                    <td class="pd-5-dot-center">'.$yy['satuan'].'</td>
                                                    <td class="pd-5-dot-left">'.$yy['methode'].'</td>
                                                </tr>';
                                }
                            }
                    $bodi .= '</tbody></table></div>';

                    array_push($customBod, $bodi);
                }
            }else {
                $bodi = '<div class="left"><table
                        style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                        <thead>
                        <tr>
                            <th width="25"
                                class="pd-5-solid-top-center">NO</th>
                            <th width="170" class="pd-5-solid-top-center">PARAMETER</th>
                            <th width="50" class="pd-5-solid-top-center">DURASI</th>
                            <th width="70" class="pd-5-solid-top-center">HASIL UJI</th>
                            <th width="70" class="pd-5-solid-top-center">BAKU MUTU</th>
                            <th width="50" class="pd-5-solid-top-center">SATUAN</th>
                            <th width="210" class="pd-5-solid-top-center">SPESIFIKASI METODE</th>
                        </tr></thead><tbody>';
                        $tot = count($data1);
                        foreach($data1 as $kk => $yy) {
                            $p = $kk + 1;
                            $akr = ($yy['akr'] != "") ? $yy['akr'] : "&nbsp;&nbsp;";
                            if($p == $tot) {
                                $bodi .= '<tr>
                                                <td class="pd-5-solid-center">'.$p.'</td>
                                                <td class="pd-5-solid-left">'.$akr.'&nbsp;'.$yy['parameter'].'</td>
                                                <td class="pd-5-solid-center">'.$yy['durasi'].'</td>
                                                <td class="pd-5-solid-center">'. str_replace('.',',',$yy['hasil_uji']) . $yy['attr'] .'</td>
                                                <td class="pd-5-solid-center">'.json_decode($yy['baku_mutu'])[0].'</td>
                                                <td class="pd-5-solid-center">'.$yy['satuan'].'</td>
                                                <td class="pd-5-solid-left">'.$yy['methode'].'</td>
                                            </tr>';
                            }else {
                                $bodi .= '<tr>
                                                <td class="pd-5-dot-center">'.$p.'</td>
                                                <td class="pd-5-dot-left">'.$akr.'&nbsp;'.$yy['parameter'].'</td>
                                                <td class="pd-5-dot-center">'.$yy['durasi'].'</td>
                                                <td class="pd-5-solid-center">'. str_replace('.',',',$yy['hasil_uji']) . $yy['attr'] .'</td>
                                                <td class="pd-5-dot-center">'.json_decode($yy['baku_mutu'])[0].'</td>
                                                <td class="pd-5-dot-center">'.$yy['satuan'].'</td>
                                                <td class="pd-5-dot-left">'.$yy['methode'].'</td>
                                            </tr>';
                            }
                        }
                $bodi .= '</tbody></table></div>';
                array_push($customBod, $bodi);
            }
            
        }else if($data->id_kategori_3 == 27) {
            if(!empty($custom)) {
                foreach($custom as $key => $value) {
                    $bodi = '<div class="left"><table
                            style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                            <thead>
                            <tr>
                                <th width="25"
                                    class="pd-5-solid-top-center">NO</th>
                                <th width="170" class="pd-5-solid-top-center">PARAMETER</th>
                                <th width="70" class="pd-5-solid-top-center">HASIL UJI</th>
                                <th width="70" class="pd-5-solid-top-center">BAKU MUTU</th>
                                <th width="50" class="pd-5-solid-top-center">SATUAN</th>
                                <th width="210" class="pd-5-solid-top-center">SPESIFIKASI METODE</th>
                            </tr></thead><tbody>';
                            $tot = count($value);
                            foreach($value as $kk => $yy) {
                                $p = $kk + 1;
                                $akr = ($yy['akr'] != "") ? $yy['akr'] : "&nbsp;&nbsp;";
                                if($p == $tot) {
                                    $bodi .= '<tr>
                                                    <td class="pd-5-solid-center">'.$p.'</td>
                                                    <td class="pd-5-solid-left">'.$akr.'&nbsp;'.$yy['parameter'].'</td>
                                                    <td class="pd-5-solid-center">'. str_replace('.',',',$yy['hasil_uji']) . $yy['attr'] .'</td>
                                                    <td class="pd-5-solid-center">'.json_decode($yy['baku_mutu'])[0].'</td>
                                                    <td class="pd-5-solid-center">'.$yy['satuan'].'</td>
                                                    <td class="pd-5-solid-left">'.$yy['methode'].'</td>
                                                </tr>';
                                }else {
                                    $bodi .= '<tr>
                                                    <td class="pd-5-dot-center">'.$p.'</td>
                                                    <td class="pd-5-dot-left">'.$akr.'&nbsp;'.$yy['parameter'].'</td>
                                                    <td class="pd-5-dot-center">'. str_replace('.',',',$yy['hasil_uji']) . $yy['attr'] .'</td>
                                                    <td class="pd-5-dot-center">'.json_decode($yy['baku_mutu'])[0].'</td>
                                                    <td class="pd-5-dot-center">'.$yy['satuan'].'</td>
                                                    <td class="pd-5-dot-left">'.$yy['methode'].'</td>
                                                </tr>';
                                }
                            }
                        
                    $bodi .= '</tbody></table></div>';
                    array_push($customBod, $bodi);
                }
            }else {
                $bodi = '<div class="left"><table
                        style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                        <thead>
                        <tr>
                            <th width="25"
                                class="pd-5-solid-top-center">NO</th>
                            <th width="170" class="pd-5-solid-top-center">PARAMETER</th>
                            <th width="70" class="pd-5-solid-top-center">HASIL UJI</th>
                            <th width="70" class="pd-5-solid-top-center">BAKU MUTU</th>
                            <th width="50" class="pd-5-solid-top-center">SATUAN</th>
                            <th width="210" class="pd-5-solid-top-center">SPESIFIKASI METODE</th>
                        </tr></thead><tbody>';
                        $tot = count($data1);
                        foreach($data1 as $kk => $yy) {
                            $p = $kk + 1;
                            $akr = ($yy['akr'] != "") ? $yy['akr'] : "&nbsp;&nbsp;";
                            if($p == $tot) {
                                $bodi .= '<tr>
                                                <td class="pd-5-solid-center">'.$p.'</td>
                                                <td class="pd-5-solid-left">'.$akr.'&nbsp;'.$yy['parameter'].'</td>
                                                <td class="pd-5-solid-center">'. str_replace('.',',',$yy['hasil_uji']) . $yy['attr'] .'</td>
                                                <td class="pd-5-solid-center">'.json_decode($yy['baku_mutu'])[0].'</td>
                                                <td class="pd-5-solid-center">'.$yy['satuan'].'</td>
                                                <td class="pd-5-solid-left">'.$yy['methode'].'</td>
                                            </tr>';
                            }else {
                                $bodi .= '<tr>
                                                <td class="pd-5-dot-center">'.$p.'</td>
                                                <td class="pd-5-dot-left">'.$akr.'&nbsp;'.$yy['parameter'].'</td>
                                                <td class="pd-5-dot-center">'. str_replace('.',',',$yy['hasil_uji']) . $yy['attr'] .'</td>
                                                <td class="pd-5-dot-center">'.json_decode($yy['baku_mutu'])[0].'</td>
                                                <td class="pd-5-dot-center">'.$yy['satuan'].'</td>
                                                <td class="pd-5-dot-left">'.$yy['methode'].'</td>
                                            </tr>';
                            }
                        }
                    
                $bodi .= '</tbody></table></div>';
                array_push($customBod, $bodi);
            }
        }

        $url = request()->headers->all()['origin'][0];
        $file_qr = "$url/utc/apps/public/qr_documents/$data->file_qr.svg";

        if($mode_download == 'downloadWSDraft') { 
            $qr_img = '';
        }else if($mode_download == 'downloadLHP') {
            if (!is_null($data->file_qr)) {
                $qr_img = '<img src="' .$file_qr.'" width="45px" height="45px" style="margin-top: 10px;">';
            }  else {
                $qr_img = '';
            }
        }
        
        // dd($data->tanggal_sampling);
        $tgl_sampling = '';
        if (strpos($data->tanggal_sampling, " - ") !== false) {
            $tgl_ = explode(" - ", $data->tanggal_sampling);
            $tgl_mulai = self::tanggal_indonesia($tgl_[0]);
            $tgl_selesai = self::tanggal_indonesia($tgl_[1]);
            $tgl_sampling = $tgl_mulai . ' - ' . $tgl_selesai;
        } else {
            
            $tgl_sampling = self::tanggal_indonesia($data->tanggal_sampling);
        }
        if ($data->id_kategori_3 == 11) {
            $ketling = '<tr>
                            <td class="custom5">Cuaca</td>
                            <td class="custom5">:</td>
                            <td class="custom5">' . $data->cuaca . '</td>
                            <td class="custom5"></td>
                            <td class="custom5">Kecepatan Angin</td>
                            <td class="custom5">:</td>
                            <td class="custom5">' . $data->kec_angin . '</td>
                        </tr>
                        <tr>
                            <td class="custom5">Suhu Lingkungan</td>
                            <td class="custom5">:</td>
                            <td class="custom5">' . $data->suhu . '</td>
                            <td class="custom5"></td>
                            <td class="custom5">Arah Angin</td>
                            <td class="custom5">:</td>
                            <td class="custom5">' . $data->arah_angin . '</td>
                        </tr>
                            <tr>
                            <td class="custom5">Kelembapan</td>
                            <td class="custom5">:</td>
                            <td class="custom5">' . $data->kelembapan . '</td>
                        </tr>';
        } else if ($data->id_kategori_3 == 27) {
            $ketling = '<tr>
                            <td class="custom5">Suhu Lingkungan</td>
                            <td class="custom5">:</td>
                            <td class="custom5" colspan="5">' . $data->suhu . '</td>
                        </tr>
                            <tr>
                            <td class="custom5">Kelembapan</td>
                            <td class="custom5">:</td>
                            <td class="custom5" colspan="5">' . $data->kelembapan . '</td>
                        </tr>';

        }
        $bodreg = '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        if($data->regulasi != null){
            foreach (json_decode($data->regulasi) as $t => $y) {
                $bodreg .= '<tr>
                                <td class="custom5" colspan="3">' . $y . '</td>
                            </tr>';
            }
        }
        
        $bodreg .= '</table>';
        $bodketer = '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        if($data->keterangan != null){
            foreach (json_decode($data->keterangan) as $t => $y) {
                $bodketer .= '<tr>
                                <td class="custom5" colspan="3">' . $y . '</td>
                            </tr>';
            }
        }
        $bodketer .= '</table>';
        if($mode_download == 'downloadWSDraft') { 
            $pading = 'margin-bottom: 40px;';
            $title = 'LAPORAN HASIL PENGUJIAN';
        }else if($mode_download == 'downloadLHP') {
            $pading = '';
            $title = 'LAPORAN HASIL PENGUJIAN';
        }  
        $ttd = '<table
                    style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px"
                    width="100%">
                    <tr>
                        <td>Tangerang, '.self::tanggal_indonesia($data->tgl_lhp).'</td>
                    </tr>
                </table>
                <table
                    style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; '.$pading.'"
                    width="100%">
                    <tr>
                        <td>
                            <span
                                style="font-weight: bold; border-bottom: 1px solid #000">('.$data->nama_karyawan.')</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            '.$data->jabatan_karyawan.'
                        </td>
                    </tr>
                </table>';
        
         $header = '<table width="100%"
                    style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td>
                            <span
                                style="font-weight: bold; border-bottom: 1px solid #000">"'.$title.'"</span>
                        </td>
                    </tr>
                </table><div class="right" style="margin-top: 13.9px;">
                    <table
                        style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            <td>
                                <table
                                    style="border-collapse: collapse; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td class="pd-5-solid-top-center" width="120">No. LHP</td>
                                        <td class="pd-5-solid-top-center" width="120">No. SAMPEl</td>
                                        <td class="pd-5-solid-top-center" width="240">JENIS
                                            SAMPLE</td>
                                    </tr>
                                    <tr>
                                        <td class="pd-5-solid-center">' . $data->no_lhp . '</td>
                                        <td class="pd-5-solid-center">' . $data->no_sample . '</td>
                                        <td class="pd-5-solid-center">' . $data->sub_kategori . '</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table style="padding: 20px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Pelanggan</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Nama
                                            Pelanggan</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $data->nama_pelanggan . '</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120">Alamat /
                                            Lokasi
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $data->alamat_sampling . '</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" colspan="7"><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Sampling</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Tanggal
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5" colspan="5">' . $tgl_sampling . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Metode Sampling</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5" colspan="5">' . $methode_sampling . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Periode
                                            Analisa</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5" colspan="5">' . $period1 . ' - ' . $period2 . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" colspan="7"><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Kondisi
                                                Lingkungan</span></td>
                                    </tr>'.$ketling.'</table>'.$bodreg.$bodketer.'</td></tr></table></div>';

        $no_lhp = str_replace("/", "-", $data->no_lhp);
        $name = 'LHP-'.$no_lhp.'.pdf';
        $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download);
        return $ress;
    }
    
    static function lhpGetaran($data, $data1, $mode_download, $custom)
    {

        $methode_sampling = $data->metode_sampling ? $data->metode_sampling : '-';
        $period = explode(" - ",$data->periode_analisa);
        $period1 = self::tanggal_indonesia($period[0]);
        $period2 = self::tanggal_indonesia($period[1]);

        // dd($data);
        $parame = json_decode($data->parameter_uji);
        $customBod = [];
        if(!empty($custom)) {

        }else {
            if(in_array("Getaran (LK) TL", $parame) || in_array("Getaran (LK) ST", $parame)) {
                // dd('masukkk');
                       $bodi = '<div class="left"><table
                                style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                                <thead>
                                <tr>
                                    <th width="25"
                                        class="pd-5-solid-top-center">NO</th>
                                    <th width="270" class="pd-5-solid-top-center">Keterangan</th>
                                    <th width="110" class="pd-5-solid-top-center">
                                        Aktivitas Pekerja
                                    </th>
                                    <th width="110" class="pd-5-solid-top-center">Sumber Getaran</th>
                                    <th width="50" class="pd-5-solid-top-center">Waktu Pemaparan</th>
                                    <th width="100" class="pd-5-solid-top-center">Hasil Pengukuran (m/s<sup>2</sup>)</th>
                                </tr>
                                
                                </thead><tbody>';
                                $tot = count($data1);
                                foreach($data1 as $kk => $yy) {
                                    $p = $kk + 1;
                                    if($p == $tot) {
                                        $bodi .= '<tr>
                                                        <td class="pd-5-solid-center">'.$p.'</td>
                                                        <td class="pd-5-solid-left"><sup style="font-size:5px; !important; margin-top:-10px;">'.$yy['no_sample'].'</sup>'.$yy['keterangan'].'</td>
                                                        <td class="pd-5-solid-left">'.$yy['aktivitas'].'</td>
                                                        <td class="pd-5-solid-left">'.$yy['sumber_get'].'</td>
                                                        <td class="pd-5-solid-center">'.$yy['w_paparan'].'</td>
                                                        <td class="pd-5-solid-left">'.$yy['hasil'].'</td>
                                                    </tr>';
                                    }else {
                                        $bodi .= '<tr>
                                                        <td class="pd-5-dot-center">'.$p.'</td>
                                                        <td class="pd-5-dot-left"><sup style="font-size:5px; !important; margin-top:-10px;">'.$yy['no_sample'].'</sup>'.$yy['keterangan'].'</td>
                                                        <td class="pd-5-dot-left">'.$yy['aktivitas'].'</td>
                                                        <td class="pd-5-dot-left">'.$yy['sumber_get'].'</td>
                                                        <td class="pd-5-dot-center">'.$yy['w_paparan'].'</td>
                                                        <td class="pd-5-dot-left">'.$yy['hasil'].'</td>
                                                    </tr>';
                                    }
                                }
                                 $bodi .= '</tbody></table></div>';
            }else {
                // dd('masukkk');
                $bodi = '<div class="left"><table
                                style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                                <thead>
                                <tr>
                                    <th width="25"
                                        class="custom">NO</th>
                                    <th width="270" class="custom">Keterangan</th>
                                    <th width="210" class="custom">Sumber Getaran</th>
                                    <th width="100" class="custom">Hasil Pengukuran (m/s<sup>2</sup>)</th>
                                </tr>
                                
                                </thead><tbody>';
                                foreach($data1 as $kk => $yy) {
                                    $p = $kk + 1;
                                    $bodi .= '<tr>
                                                    <td class="custom3">'.$p.'</td>
                                                    <td class="custom4"><sup style="font-size:5px; !important; margin-top:-10px;">'.$yy['no_sample'].'</sup>'.$yy['keterangan'].'</td>
                                                    <td class="custom3">'.$yy['sumber_get'].'</td>
                                                    <td class="custom4">'.$yy['hasil'].'</td>
                                                </tr>';
                                }
    
                                $bodi .= '</tbody></table></div>';
            }
            array_push($customBod, $bodi);
        }
        if($mode_download == 'downloadWSDraft') { 
            $pading = 'margin-bottom: 40px;';
            $title = 'LAPORAN HASIL PENGUJIAN';
        }else if($mode_download == 'downloadLHP') {
            $pading = '';
            $title ='LAPORAN HASIL PENGUJIAN';
        }         
        $ttd = '<table
                    style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px"
                    width="100%">
                    <tr>
                        <td>Tangerang, '.self::tanggal_indonesia($data->tgl_lhp).'</td>
                    </tr>
                </table>
                <table
                    style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; '.$pading.'"
                    width="100%">
                    <tr>
                        <td>
                            <span
                                style="font-weight: bold; border-bottom: 1px solid #000">('.$data->nama_karyawan.')</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            '.$data->jabatan_karyawan.'
                        </td>
                    </tr>
                </table>';
        $header = '<table width="100%"
                    style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td>
                            <span
                                style="font-weight: bold; border-bottom: 1px solid #000">"'.$title.'"</span>
                        </td>
                    </tr>
                </table><div class="right" style="margin-top: 13.9px;">
                    <table
                        style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            <td>
                                <table
                                    style="border-collapse: collapse; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td class="custom" width="120">No. LHP</td>
                                        <td class="custom" width="240">JENIS
                                            SAMPLE</td>
                                    </tr>
                                    <tr>
                                        <td class="custom">'.$data->no_lhp.'</td>
                                        <td class="custom">'.$data->sub_kategori.'</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table style="padding: 20px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Pelanggan</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Nama
                                            Pelanggan</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.$data->nama_pelanggan.'</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120">Alamat /
                                            Lokasi
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.$data->alamat_sampling.'</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120"><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Sampling</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Tanggal
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.self::tanggal_indonesia($data->tgl_sampling).'</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Metode Sampling</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">'.$methode_sampling.'</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Periode
                                            Analisa</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">'.$period1. ' - '.$period2.'</td>
                                    </tr>
                                </table>';
                               $header .= '<table style="padding: 10px 0px 0px 0px;" width="100%">';
                                    foreach(json_decode($data->regulasi) as $t => $y) {
                                       $header .=  '<tr>
                                        <td class="custom5" colspan="3">'.$y.'</td>
                                    </tr>';
                                    }
                                $header .= '</table>';
        if ($data->id_kategori_3 == 17) {
        $header .= '<table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                  <tr>
                      <th class="custom" rowspan="2">Jumlah Waktu Pemaparan Per Hari Kerja (Jam)</th>
                      <th class="custom">Resultan Percepatan di Sb. X, Sb. Y dan Sb. Z</th>
                  </tr>
                  <tr>
                      <th class="custom">Meter Per Detik Kuadrat (m/s<sup>2</sup>)</th>
                  </tr>
                  <tr>
                      <td class="custom1">6 jam sampai dengan 8 jam</td>
                      <td class="custom2">5</td>
                  </tr>
                  <tr>
                      <td class="custom1">4 jam dan kurang dari 6 jam</td>
                      <td class="custom2">6</td>
                  </tr>
                  <tr>
                      <td class="custom1">2 jam dan kurang dari 4 jam</td>
                      <td class="custom2">7</td>
                  </tr>
                  <tr>
                      <td class="custom1">1 jam dan kurang dari 2 jam</td>
                      <td class="custom2">10</td>
                  </tr>
                  <tr>
                      <td class="custom1">0.5 jam dan kurang dari 1 jam</td>
                      <td class="custom2">14</td>
                  </tr>
                  <tr>
                      <td class="custom4">kurang dari 0.5 jam</td>
                      <td class="custom9">20</td>
                  </tr>
              </table>';
        } else if ($data->id_kategori_3 == 19) {
        $header .= '<table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                      <tr>
                          <th class="custom" style="border: 1px solid black; vertical-align:middle;" rowspan="2">Frekuensi (HZ)</th>
                          <th class="custom" colspan="4">Batas Getaran, Peak, mm/s</th>
                      </tr>
                      <tr>
                          <th class="custom">Kategori A</th>
                          <th class="custom">Kategori B</th>
                          <th class="custom">Kategori C</th>
                          <th class="custom">Kategori D</th>
                      </tr>
                      <tr>
                          <td class="custom1">4</td>
                          <td class="custom1"><2</td>
                          <td class="custom1">2 - 27</td>
                          <td class="custom1">> 27 - 40</td>
                          <td class="custom1">>140</td>
                      </tr>
                      <tr>
                          <td class="custom1">5</td>
                          <td class="custom1"><7,5</td>
                          <td class="custom1">< 7,5 - 25</td>
                          <td class="custom1">>24 - 130</td>
                          <td class="custom1">>130</td>
                      </tr>
                      <tr>
                          <td class="custom1">6,3</td>
                          <td class="custom1"><7</td>
                          <td class="custom1"><7 - 21</td>
                          <td class="custom1">>21 - 110</td>
                          <td class="custom1">>110</td>
                      </tr>
                      <tr>
                          <td class="custom1">8</td>
                          <td class="custom1"><6</td>
                          <td class="custom1"><6 - 19</td>
                          <td class="custom1">>19 - 110</td>
                          <td class="custom1">>100</td>
                      </tr>
                      <tr>
                          <td class="custom1">10</td>
                          <td class="custom1"><5,2</td>
                          <td class="custom1"><5,2 - 16</td>
                          <td class="custom1">>16 - 90</td>
                          <td class="custom1">>90</td>
                      </tr>
                      <tr>
                          <td class="custom1">12,5</td>
                          <td class="custom1"><4,8</td>
                          <td class="custom1"><4,8 - 15</td>
                          <td class="custom1">>15 - 80</td>
                          <td class="custom1">>80</td>
                      </tr>
                      <tr>
                          <td class="custom1">16</td>
                          <td class="custom1"><4</td>
                          <td class="custom1"><4 - 14</td>
                          <td class="custom1">>14 - 70</td>
                          <td class="custom1">>70</td>
                      </tr>
                      <tr>
                          <td class="custom1">20</td>
                          <td class="custom1"><3,8</td>
                          <td class="custom1"><3,8 - 12</td>
                          <td class="custom1">>12 - 67</td>
                          <td class="custom1">>67</td>
                      </tr>
                      <tr>
                          <td class="custom1">25</td>
                          <td class="custom1"><3,2</td>
                          <td class="custom1"><3,2 - 10</td>
                          <td class="custom1">>10 - 60</td>
                          <td class="custom1">>60</td>
                      </tr>
                      <tr>
                          <td class="custom1">31,5</td>
                          <td class="custom1"><3</td>
                          <td class="custom1"><3 - 9</td>
                          <td class="custom1">>9 - 53</td>
                          <td class="custom1">>53</td>
                      </tr>
                      <tr>
                          <td class="custom1">40</td>
                          <td class="custom1"><2</td>
                          <td class="custom1"><2 - 8</td>
                          <td class="custom1">>8 - 50</td>
                          <td class="custom1">>50</td>
                      </tr>
                      <tr>
                          <td class="custom9">50</td>
                          <td class="custom9"><1</td>
                          <td class="custom9"><1 - 7</td>
                          <td class="custom9">>7 - 42</td>
                          <td class="custom9">>42</td>
                      </tr>
                  </table>';
        } else if ($data->id_kategori_3 == 20) {
        $header .= '<table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                      <tr>
                          <th class="custom" style="border: 1px solid black; vertical-align:middle;" rowspan="2">
                            Jumlah Waktu Pajanan Per Hari <br>
                            <span>(Jam)</span>
                          </th>
                      </tr>
                      <tr>
                          <th class="custom">
                            Nilai Ambang Batas <br> 
                            <span>(m/det<sup>2</sup>)</span>
                          </th>
                      </tr>
                      <tr>
                          <td class="custom1">0,5</td>
                          <td class="custom1">3,4644</td>
                      </tr>
                      <tr>
                          <td class="custom1">1</td>
                          <td class="custom1">2,4497</td>
                      </tr>
                      <tr>
                          <td class="custom1">2</td>
                          <td class="custom1">1,7322</td>
                      </tr>
                      <tr>
                          <td class="custom1">4</td>
                          <td class="custom1">1,2249</td>
                      </tr>
                      <tr>
                          <td class="custom9">8</td>
                          <td class="custom9">0,8661</td>
                      </tr>

                  </table>';
        } else if ($data->id_kategori_3 == 14) {
        $header .= '<table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                        <tr>
                            <th class="custom">
                              Kelas
                            </th>
                            <th class="custom">
                              Jenis Bangunan
                            </th>
                            <th class="custom" style="border: 1px solid black; width: 30%;">
                              Kecepatan Getaran Maksimum <br> 
                              <span>(mm/detik)</span>
                            </th>
                        </tr>
                        <tr>
                            <td class="custom1">1</td>
                            <td class="custom2">Peruntukan dan bangunan kuno yang mempunyai nilai sejarah yang tinggi</td>
                            <td class="custom1">2</td>
                        </tr>
                        <tr>
                            <td class="custom1">2</td>
                            <td class="custom2">Bangunan dan kerusakan yang sudah ada, tampak keretakan - keretakan pada tembok</td>
                            <td class="custom1">5</td>
                        </tr>
                        <tr>
                            <td class="custom1">3</td>
                            <td class="custom2">Bangunan untuk dalam kondisi teknis yang baik, ada kerusakan - kerusakan kecil seperti : plesteran yang retak</td>
                            <td class="custom1">10</td>
                        </tr>
                        <tr>
                            <td class="custom1">4</td>
                            <td class="custom2">Bangunan "kuat" (misalnya bangunan industri terbuat dari beton dan baja)</td>
                            <td class="custom1">10 - 40</td>
                        </tr>
                    </table>';
         } else if ($data->id_kategori_3 == 15) {
         $header .= '<table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                        <tr>
                            <th class="custom">
                              Kelas
                            </th>
                            <th class="custom">
                              Jenis Bangunan
                            </th>
                            <th class="custom" style="border: 1px solid black; width: 30%;">
                              Kecepatan Getaran Maksimum <br> 
                              <span>(mm/detik)</span>
                            </th>
                        </tr>
                        <tr>
                            <td class="custom1">1</td>
                            <td class="custom2">Peruntukan dan bangunan kuno yang mempunyai nilai sejarah yang tinggi</td>
                            <td class="custom1">2</td>
                        </tr>
                        <tr>
                            <td class="custom1">2</td>
                            <td class="custom2">Bangunan dan kerusakan yang sudah ada, tampak keretakan - keretakan pada tembok</td>
                            <td class="custom1">5</td>
                        </tr>
                        <tr>
                            <td class="custom1">3</td>
                            <td class="custom2">Bangunan untuk dalam kondisi teknis yang baik, ada kerusakan - kerusakan kecil seperti : plesteran yang retak</td>
                            <td class="custom1">10</td>
                        </tr>
                        <tr>
                            <td class="custom1">4</td>
                            <td class="custom2">Bangunan "kuat" (misalnya bangunan industri terbuat dari beton dan baja)</td>
                            <td class="custom1">10 - 40</td>
                        </tr>
                    </table>';
        }
                                $header .= '</td>
                        </tr>
                    </table>
                </div>';
        $url = request()->headers->all()['origin'][0];
        $file_qr = "$url/utc/apps/public/qr_documents/$data->file_qr.svg";
        if($mode_download == 'downloadWSDraft') { 
            $qr_img = '';
        }else if($mode_download == 'downloadLHP') {
            if (!is_null($data->file_qr)) {
                $qr_img = '<img src="' .$file_qr.'" width="45px" height="45px" style="margin-top: 10px;">';
            }  else {
                $qr_img = '';
            }
        }
        $no_lhp = str_replace("/", "-", $data->no_lhp);
        $name = 'LHP-'.$no_lhp.'.pdf';
        // dd($bodi);
        $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download);
        return $ress;
    }

    static function lhpDirectKebisinganmenggunakantabelbakuKolomBakumutu($data = array())
    {

        $boddi = '<table
                        style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                        <tr>
                            <th rowspan="2" width="25"
                                class="custom">NO</th>
                            <th rowspan="2" width="270" class="custom">LOKASI /
                                KETERANGAN
                                SAMPEL</th>
                            <th width="200" class="custom" colspan="3">
                                Kebisingan (dBA)
                            </th>
                            <th rowspan="2" width="70" class="custom">NAB**</th>
                            <th rowspan="2" width="210" class="custom">TITIK
                                KOORDINAT</th>
                        </tr>
                        <tr>
                            <th class="custom" width="50">MIN</th>
                            <th class="custom" width="50">MAX</th>
                            <th class="custom">HASIL UJI</th>
                        </tr>';
        $dat = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '25', '26', '27', '28', '29', '30'];
        for ($i = 0; $i <= 40; $i++) {
            if (isset($dat[$i])) {
                $boddi .= '<tr>
                            <td class="custom1">' . $dat[$i] . '</td>
                            <td class="custom2"></td>
                            <td class="custom2"></td>
                            <td class="custom2"></td>
                            <td class="custom2"></td>
                            <td class="custom2"></td>
                            <td class="custom2"></td>
                        </tr>';
            } else {
                $boddi .= '<tr>
                            <td class="custom6"></td>
                            <td class="custom6"></td>
                            <td class="custom6"></td>
                            <td class="custom6"></td>
                            <td class="custom6"></td>
                            <td class="custom6"></td>
                            <td class="custom6"></td>
                        </tr>';
            }
        }
        $boddi .= '<tr>
                            <td class="custom4"></td>
                            <td class="custom4"></td>
                            <td class="custom4"></td>
                            <td class="custom4"></td>
                            <td class="custom4"></td>
                            <td class="custom4"></td>
                            <td class="custom4"></td>
                        </tr></table>';

        $kolomkanan = '<td align="top" style="vertical-align: top;">
                    <table
                        style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            <td>
                                <table
                                    style="border-collapse: collapse; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td class="custom" width="120">No. LHP</td>
                                        <td class="custom" width="240">JENIS
                                            SAMPLE</td>
                                    </tr>
                                    <tr>
                                        <td class="custom">2342</td>
                                        <td class="custom">AIR</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table style="padding: 20px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Pelanggan</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Nama
                                            Pelanggan</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">dakfjhsdkjfds
                                            fdsjfhdsjfk</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120">Alamat /
                                            Lokasi
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">dfkjdshfikjdsf
                                            dskfjhdskjfds
                                            fdskjfhdskjfhds fdskjfhdsjf</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120"><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Sampling</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Tanggal
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">sdkfjdhskjf
                                            dsfkjhsdfkjdsf</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Metode Sampling</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">sdkfjdhskjf
                                            dsfkjhsdfkjdsf</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Periode
                                            Analisa</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">dsfhjdsf</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120"><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Kondisi
                                                Lingkungan</span></td>
                                    </tr>
                                </table>
                                <table style="padding: 50px 0px 12px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="12">**</td>
                                        <td class="custom5">Peraturan Menteri
                                            Ketenagakerjaan Republik Indonesia
                                            No. 5 Tahun 2018 Lamp. I.B
                                            Tentang Nilai Ambang Batas
                                            Kebisingan.</td>
                                    </tr>
                                </table>
                                <table
                                    style="border-collapse: collapse;"
                                    width="100%">
                                    <tr>
                                        <td class="custom">Waktu Pemaparan
                                            per Hari</td>
                                        <td class="custom">ntensitas Kebisingan
                                            (dalam dBA)
                                        </td>
                                        <td class="custom">Waktu Pemaparan
                                            per Hari</td>
                                        <td class="custom">Intensitas Kebisingan
                                            (dalam dBA)</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">8 Jam</td>
                                        <td class="custom2">85</td>
                                        <td class="custom2">28,12 Detik</td>
                                        <td class="custom2">115</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">4</td>
                                        <td class="custom2">88</td>
                                        <td class="custom2">14,06</td>
                                        <td class="custom2">118</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">2</td>
                                        <td class="custom2">91</td>
                                        <td class="custom2">7,03</td>
                                        <td class="custom2">121</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">1</td>
                                        <td class="custom2">94</td>
                                        <td class="custom2">3,52</td>
                                        <td class="custom2">124</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">30 Menit</td>
                                        <td class="custom2">97</td>
                                        <td class="custom2">1,76</td>
                                        <td class="custom2">127</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">15</td>
                                        <td class="custom2">100</td>
                                        <td class="custom2">0,88</td>
                                        <td class="custom2">130</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">7,5</td>
                                        <td class="custom2">103</td>
                                        <td class="custom2">0,44</td>
                                        <td class="custom2">133</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">3,75</td>
                                        <td class="custom2">106</td>
                                        <td class="custom2">0,22</td>
                                        <td class="custom2">136</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">1,88</td>
                                        <td class="custom2">109</td>
                                        <td class="custom2">0,11</td>
                                        <td class="custom2">139</td>
                                    </tr>
                                    <tr>
                                        <td class="custom4">0,94</td>
                                        <td class="custom4">112</td>
                                        <td class="custom4"></td>
                                        <td class="custom4"></td>
                                    </tr>
                                </table>
                                <table
                                    style="padding: 5px 0px 0px 340px; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td>Tangerang,
                                            17
                                            Oktober 2023</td>
                                    </tr>
                                </table>
                                <table
                                    style="padding: 50px 0px 0px 340px; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td>
                                            <span
                                                style="font-weight: bold; border-bottom: 1px solid #000">(Dwi
                                                Meisya
                                                B.)</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            Manager
                                            Teknis
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>';
        $footer = '<table width="100%" style="vertical-align: bottom; font-family: serif;
                                font-size: 5pt; color: #000000;">
                                <tr>
                                    <td width="30%">DP/7.8.1/ISL; Rev 3; 08 November 2022</td>
                                    <td width="70%">Hasil uji ini hanya berlaku untuk sampel yang diuji. Lembar ini tidak boleh diubah ataupun digandakan tanpa izin tertulis dari pihak laboratorium.</td>
                                </tr>
                            </table>';
        $name = 'testzakkiylhp';
        $ress = self::formatTemplate($boddi, $kolomkanan, $footer, $name);
        return $ress;
    }

    static function lhpDirectKebisinganmenggunakantabelbakumutu20kolom($data, $data1, $mode_download, $custom)
    {
        $methode_sampling = $data->metode_sampling ? $data->metode_sampling : '-';
        $period = explode(" - ",$data->periode_analisa);
        $period1 = self::tanggal_indonesia($period[0]);
        $period2 = self::tanggal_indonesia($period[1]);
        
         $customBod = [];
        if(!empty($custom)) {

        }else {
            $bodi = '<div class="left"><table
                            style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                            <thead>
                            <tr>
                                <th rowspan="2" width="25"
                                    class="custom">NO</th>
                                <th rowspan="2" width="270" class="custom">LOKASI /
                                    KETERANGAN
                                    SAMPEL</th>
                                <th width="200" class="custom" colspan="3">
                                    Kebisingan (dBA)
                                </th>
                                <th rowspan="2" width="210" class="custom">TITIK
                                    KOORDINAT</th>
                            </tr>
                            <tr>
                                <th class="custom" width="60">MIN</th>
                                <th class="custom" width="60">MAX</th>
                                <th class="custom" width="60">HASIL UJI</th>
                            </tr></thead><tbody>';
            $tot = count($data1);
            foreach($data1 as $kk => $yy) {
                $p = $kk + 1;
                if($tot == $p) {
                    $bodi .= '<tr>
                                <td class="pd-5-solid-center">'.$p.'</td>
                                <td class="pd-5-solid-left"><sup style="font-size:5px; !important; margin-top:-10px;">'.$yy['no_sampel'].'</sup>'.$yy['lokasi_keterangan'].'</td>
                                <td class="pd-5-solid-center">'.$yy['min'].'</td>
                                <td class="pd-5-solid-center">'.$yy['max'].'</td>
                                <td class="pd-5-solid-center">'. str_replace('.',',',$yy['hasil']) .'</td>
                                <td class="pd-5-solid-left">'.$yy['titik_koordinat'].'</td>
                            </tr>';
                }else {
                    $bodi .= '<tr>
                                <td class="pd-5-dot-center">'.$p.'</td>
                                <td class="pd-5-dot-left"><sup style="font-size:5px; !important; margin-top:-10px;">'.$yy['no_sampel'].'</sup>'.$yy['lokasi_keterangan'].'</td>
                                <td class="pd-5-dot-center">'.$yy['min'].'</td>
                                <td class="pd-5-dot-center">'.$yy['max'].'</td>
                                <td class="pd-5-dot-center">'. str_replace('.',',',$yy['hasil']) .'</td>
                                <td class="pd-5-dot-left">'.$yy['titik_koordinat'].'</td>
                            </tr>';
                 
                }
            }
            $bodi .= '</tbody></table></div>';
            array_push($customBod, $bodi);
        }
        if($mode_download == 'downloadWSDraft') { 
            $pading = 'margin-bottom: 40px;';
            $title ='LAPORAN HASIL PENGUJIAN';
        }else if($mode_download == 'downloadLHP') {
            $pading = '';
            $title ='LAPORAN HASIL PENGUJIAN';
        }  
        $ttd = '<table
                    style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px"
                    width="100%">
                    <tr>
                        <td>Tangerang, '.self::tanggal_indonesia($data->tgl_lhp).'</td>
                    </tr>
                </table>
                <table
                    style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; '.$pading.'"
                    width="100%">
                    <tr>
                        <td>
                            <span
                                style="font-weight: bold; border-bottom: 1px solid #000">('.$data->nama_karyawan.')</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            '.$data->jabatan_karyawan.'
                        </td>
                    </tr>
                </table>';
        $header = '<table width="100%"
                    style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td>
                            <span
                                style="font-weight: bold; border-bottom: 1px solid #000">"'. $title .'"</span>
                        </td>
                    </tr>
                </table><div class="right" style="margin-top: 13.9px;">
                    <table
                        style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            <td>
                                <table
                                    style="border-collapse: collapse; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td class="custom" width="120">No. LHP</td>
                                        <td class="custom" width="240">JENIS
                                            SAMPLE</td>
                                    </tr>
                                    <tr>
                                        <td class="custom">'.$data->no_lhp.'</td>
                                        <td class="custom">'.$data->sub_kategori.'</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table style="padding: 20px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Pelanggan</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Nama
                                            Pelanggan</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.$data->nama_pelanggan.'</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120">Alamat /
                                            Lokasi
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.$data->alamat_sampling.'</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120"><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Sampling</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Tanggal
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.self::tanggal_indonesia($data->tgl_sampling).'</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Metode Sampling</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">'.$methode_sampling.'</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Periode
                                            Analisa</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">'.$period1. ' - '.$period2.'</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120"><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Kondisi Lingkungan</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Suhu</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.$data->suhu.'</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Kelembapan</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">'.$data->kelembapan.'</td>
                                    </tr>
                                </table>';
                                 $header .= '<table style="padding: 10px 0px 0px 0px;" width="100%">';
                                    foreach(json_decode($data->regulasi) as $t => $y) {
                                       $header .=  '<tr>
                                        <td class="custom5" colspan="3">'.$y.'</td>
                                    </tr>';
                                    }
                                $header .= '</table>';
                                $header .= '<table
                                    style="border-collapse: collapse;"
                                    width="100%">
                                    <tr>
                                        <td class="custom">Waktu Pemaparan
                                            per Hari</td>
                                        <td class="custom">ntensitas Kebisingan
                                            (dalam dBA)
                                        </td>
                                        <td class="custom">Waktu Pemaparan
                                            per Hari</td>
                                        <td class="custom">Intensitas Kebisingan
                                            (dalam dBA)</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">8 Jam</td>
                                        <td class="custom2">85</td>
                                        <td class="custom2">28,12 Detik</td>
                                        <td class="custom2">115</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">4</td>
                                        <td class="custom2">88</td>
                                        <td class="custom2">14,06</td>
                                        <td class="custom2">118</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">2</td>
                                        <td class="custom2">91</td>
                                        <td class="custom2">7,03</td>
                                        <td class="custom2">121</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">1</td>
                                        <td class="custom2">94</td>
                                        <td class="custom2">3,52</td>
                                        <td class="custom2">124</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">30 Menit</td>
                                        <td class="custom2">97</td>
                                        <td class="custom2">1,76</td>
                                        <td class="custom2">127</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">15</td>
                                        <td class="custom2">100</td>
                                        <td class="custom2">0,88</td>
                                        <td class="custom2">130</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">7,5</td>
                                        <td class="custom2">103</td>
                                        <td class="custom2">0,44</td>
                                        <td class="custom2">133</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">3,75</td>
                                        <td class="custom2">106</td>
                                        <td class="custom2">0,22</td>
                                        <td class="custom2">136</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">1,88</td>
                                        <td class="custom2">109</td>
                                        <td class="custom2">0,11</td>
                                        <td class="custom2">139</td>
                                    </tr>
                                    <tr>
                                        <td class="custom3">0,94</td>
                                        <td class="custom3">112</td>
                                        <td class="custom4"></td>
                                        <td class="custom4"></td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>';
                $url = request()->headers->all()['origin'][0];
                $file_qr = "$url/utc/apps/public/qr_documents/$data->file_qr.svg";
                if($mode_download == 'downloadWSDraft') { 
                    $qr_img = '';
                }else if($mode_download == 'downloadLHP') {
                    if (!is_null($data->file_qr)) {
                        $qr_img = '<img src="' .$file_qr.'" width="45px" height="45px" style="margin-top: 10px;">';
                    }  else {
                        $qr_img = '';
                    }
                }
                $no_lhp = str_replace("/", "-", $data->no_lhp);
                $name = 'LHP-'.$no_lhp.'.pdf';
                $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download);
        return $ress;
    }

    static function DirectESBBensin($data, $data1, $mode_download, $custom)
    {
        // dd($data, $data1, $mode_download);
        $methode_sampling = $data->metode_sampling ? $data->metode_sampling : '-';
        $period = explode(" - ",$data->periode_analisa);
        $period1 = self::tanggal_indonesia($period[0]);
        $period2 = self::tanggal_indonesia($period[1]);
        $customBod = [];
        if(!empty($custom)) {

        }else {
            $bodi = '<div class="left"><table
                            style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                            <thead>
                            <tr>
                                <th rowspan="2" width="25"
                                    class="pd-5-solid-top-center">NO</th>
                                <th rowspan="2" width="250" class="pd-5-solid-top-center">JENIS /
                                    NAMA KENDARAAN</th>
                                <th rowspan="2" width="75" class="pd-5-solid-top-center">BOBOT</th>
                                <th rowspan="2" width="75" class="pd-5-solid-top-center">TAHUN</th>
                                <th colspan="2" class="pd-5-solid-top-center">HASIL UJI</th>
                                <th colspan="2" class="pd-5-solid-top-center">BAKU MUTU
                                    **</th>
                            </tr>
                            <tr>
                                <th class="pd-5-solid-top-center" width="75">CO (%)</th>
                                <th class="pd-5-solid-top-center" width="75">HC (ppm)</th>
                                <th class="pd-5-solid-top-center" width="75">CO (%)</th>
                                <th class="pd-5-solid-top-center" width="75">HC (ppm)</th>
                            </tr></thead><tbody>';
                        $tot = count($data1);
                        foreach($data1 as $key => $value) {
                            $baku = json_decode($value['baku_mutu']);
                            $hasil = json_decode($value['hasil_uji']);
                            // dd($hasil);
                            // dd($hasil->CO);
                            $p = $key + 1;
                            if($p == $tot) {
                                $bodi .= '<tr>
                                    <td class="pd-5-solid-center">'.$p.'</td>
                                    <td class="pd-5-solid-left"><sup style="font-size:5px; !important; margin-top:-10px;">'.$value['no_sample'].'</sup>'.$value['nama_kendaraan'].'</td>
                                    <td class="pd-5-solid-center">' . $value['bobot'] . '</td>
                                    <td class="pd-5-solid-center">' . $value['tahun_kendaraan'] . '</td>
                                    <td class="pd-5-solid-center">' . $hasil->CO . '</td>
                                    <td class="pd-5-solid-center">' . $hasil->HC . '</td>
                                    <td class="pd-5-solid-center">' . $baku->CO . '</td>
                                    <td class="pd-5-solid-center">' . $baku->HC . '</td>
                                </tr>';
                            }else {
                                $bodi .= '<tr>
                                    <td class="pd-5-dot-center">'.$p.'</td>
                                    <td class="pd-5-dot-left"><sup style="font-size:5px; !important; margin-top:-10px;">'.$value['no_sample'].'</sup>'.$value['nama_kendaraan'].'</td>
                                    <td class="pd-5-dot-center">' . $value['bobot'] . '</td>
                                    <td class="pd-5-dot-center">' . $value['tahun_kendaraan'] . '</td>
                                    <td class="pd-5-dot-center">' . $hasil->CO . '</td>
                                    <td class="pd-5-dot-center">' . $hasil->HC . '</td>
                                    <td class="pd-5-dot-center">' . $baku->CO . '</td>
                                    <td class="pd-5-dot-center">' . $baku->HC . '</td>
                                </tr>';
                            }
                        }
                        
                        $bodi .=  '</tbody></table></div>';
                        array_push($customBod, $bodi);
        }
            // dd($data->parameter_uji);
            $parame = str_replace("[", "", $data->parameter_uji);
            $parame = str_replace("]", "", $parame);
            $parame = str_replace('"', '', $parame);
            $alamat_sample = $data->alamat_sampling != null ? $data->alamat_sampling : "-";
            $methode_sampling = $data->metode_sampling != null ? $data->metode_sampling : "-";
            if($mode_download == 'downloadWSDraft') { 
                $pading = 'margin-bottom: 40px;';
            }else if($mode_download == 'downloadLHP') {
                $pading = '';
            }  
        $ttd = '<table
                    style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px"
                    width="100%">
                    <tr>
                        <td>Tangerang, '.self::tanggal_indonesia($data->tgl_lhp).'</td>
                    </tr>
                </table>
                <table
                    style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; '.$pading.'"
                    width="100%">
                    <tr>
                        <td>
                            <span
                                style="font-weight: bold; border-bottom: 1px solid #000">('.$data->nama_karyawan.')</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            '.$data->jabatan_karyawan.'
                        </td>
                    </tr>
                </table>';
        $header = '<table width="100%"
                    style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td>
                            <span
                                style="font-weight: bold; border-bottom: 1px solid #000">LAPORAN
                                HASIL PENGUJIAN</span>
                        </td>
                    </tr>
                </table><div class="right" style="margin-top: 13.9px;">
                    <table
                        style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            <td>
                                <table
                                    style="border-collapse: collapse; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td class="custom" width="120">No. LHP</td>
                                        <td class="custom" width="240">JENIS
                                            SAMPLE</td>
                                    </tr>
                                    <tr>
                                        <td class="custom">' . $data->no_lhp . '</td>
                                        <td class="custom">' . $data->sub_kategori . '</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table style="padding: 20px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Pelanggan</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Nama
                                            Pelanggan</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $data->nama_pelanggan . '</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120">Alamat /
                                            Lokasi
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.$alamat_sample.'</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120"><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Sampling</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Kategori</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $data->sub_kategori . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Parameter</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.$parame.'</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Metode Sampling</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">'.$methode_sampling.'</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Tanggal
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . self::tanggal_indonesia($data->tgl_sampling) . '</td>
                                    </tr>

                                    <tr>
                                        <td class="custom5">Periode
                                            Analisa</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">'.$period1.' - '.$period2.'</td>
                                    </tr>
                                </table>';
                                $header .= '<table style="padding: 10px 0px 0px 0px;" width="100%">';
                                    foreach(json_decode($data->regulasi) as $t => $y) {
                                       $header .=  '<tr>
                                        <td class="custom5" colspan="3">'.$y.'</td>
                                    </tr>';
                                    }
                                $header .= '</table>';
                                $header .= '<table
                                    style="padding: 200px 0px 0px 200px; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td>Tangerang, '.self::tanggal_indonesia($data->tgl_lhp).'</td>
                                    </tr>
                                </table>
                                <table
                                    style="padding: 50px 0px 0px 200px; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td>
                                            <span
                                                style="font-weight: bold; border-bottom: 1px solid #000">('.$data->nama_karyawan.')</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                           '.$data->jabatan_karyawan .'
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>';
        $url = request()->headers->all()['origin'][0];
        $file_qr = "$url/utc/apps/public/qr_documents/$data->file_qr.svg";
        if($mode_download == 'downloadWSDraft') { 
            $qr_img = '';
        }else if($mode_download == 'downloadLHP') {
            if (!is_null($data->file_qr)) {
                $qr_img = '<img src="' .$file_qr.'" width="45px" height="45px" style="margin-top: 10px;">';
            }  else {
                $qr_img = '';
            }
        }
        $no_lhp = str_replace("/", "-", $data->no_lhp);
        $name = 'LHP-'.$no_lhp.'.pdf';
        $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download);
        return $ress;
    }

        static function DirectESBSolar($data, $data1, $mode_download, $custom)
    {
        // dd($data1);
        $methode_sampling = $data->metode_sampling ? $data->metode_sampling : '-';
        $period = explode(" - ",$data->periode_analisa);
        $period1 = self::tanggal_indonesia($period[0]);
        $period2 = self::tanggal_indonesia($period[1]);

        $customBod = [];
        if(!empty($custom)) {

        }else {
            $bodi = '<div class="left"><table
                            style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                            <thead>
                            <tr>
                                <th rowspan="2" width="25"
                                    class="pd-5-solid-top-center">NO</th>
                                <th rowspan="2" width="310" class="pd-5-solid-top-center">JENIS /
                                    NAMA KENDARAAN</th>
                                <th rowspan="2" width="85" class="pd-5-solid-top-center">BOBOT</th>
                                <th rowspan="2" width="85" class="pd-5-solid-top-center">TAHUN</th>
                                <th width="105" class="pd-5-solid-top-center">HASIL UJI</th>
                                <th width="105" class="pd-5-solid-top-center">BAKU MUTU
                                    **</th>
                            </tr>
                            <tr>
                                <th class="pd-5-solid-top-center" colspan="2">Satuan = Opasitas (%)</th>
                            </tr></thead><tbody>';
                            $tot = count($data1);
                            foreach($data1 as $key => $value) {
                                $baku = json_decode($value['baku_mutu']);
                                $hasil = json_decode($value['hasil_uji']);
                                $p = $key + 1; 
                                if($p == $tot) {
                                    $bodi .= '<tr>
                                        <td class="pd-5-solid-center">'.$p.'</td>
                                        <td class="pd-5-solid-left"><sup style="font-size:5px; !important; margin-top:-10px;">'.$value['no_sample'].'</sup>'.$value['nama_kendaraan'].'</td>
                                        <td class="pd-5-solid-center">' . $value['bobot_kendaraan'] . '</td>
                                        <td class="pd-5-solid-center">' . $value['tahun_kendaraan'] . '</td>
                                        <td class="pd-5-solid-center">' . $hasil->OP . '</td>
                                        <td class="pd-5-solid-center">' . $baku->OP . '</td>
                                    </tr>';
                                }else {
                                    $bodi .= '<tr>
                                        <td class="pd-5-dot-center">'.$p.'</td>
                                        <td class="pd-5-dot-left"><sup style="font-size:5px; !important; margin-top:-10px;">'.$value['no_sample'].'</sup>'.$value['nama_kendaraan'].'</td>
                                        <td class="pd-5-dot-center">' . $value['bobot_kendaraan'] . '</td>
                                        <td class="pd-5-dot-center">' . $value['tahun_kendaraan'] . '</td>
                                        <td class="pd-5-dot-center">' . $hasil->OP . '</td>
                                        <td class="pd-5-dot-center">' . $baku->OP . '</td>
                                    </tr>';
                                }
    
                            }
                            $bodi .=  '</tbody></table></div>';
                             array_push($customBod, $bodi);
        }
            $parame = str_replace("[", "", $data->parameter_uji);
            $parame = str_replace("]", "", $parame);
            $parame = str_replace('"', '', $parame);
            $alamat_sample = $data->alamat_sampling != null ? $data->alamat_sampling : "-";
            $methode_sampling = $data->metode_sampling != null ? $data->metode_sampling : "-";
            if($mode_download == 'downloadWSDraft') { 
                $pading = 'margin-bottom: 40px;';
            }else if($mode_download == 'downloadLHP') {
                $pading = '';
            }  
            $ttd = '<table
                    style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px"
                    width="100%">
                    <tr>
                        <td>Tangerang, '.self::tanggal_indonesia($data->tgl_lhp).'</td>
                    </tr>
                </table>
                <table
                    style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; '.$pading.'"
                    width="100%">
                    <tr>
                        <td>
                            <span
                                style="font-weight: bold; border-bottom: 1px solid #000">('.$data->nama_karyawan.')</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            '.$data->jabatan_karyawan.'
                        </td>
                    </tr>
                </table>';
        $header = '<table width="100%"
                    style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td>
                            <span
                                style="font-weight: bold; border-bottom: 1px solid #000">LAPORAN
                                HASIL PENGUJIAN</span>
                        </td>
                    </tr>
                </table><div class="right" style="margin-top: 13.9px;"><table
                        style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            <td>
                                <table
                                    style="border-collapse: collapse; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td class="custom" width="120">No. LHP</td>
                                        <td class="custom" width="240">JENIS
                                            SAMPLE</td>
                                    </tr>
                                    <tr>
                                        <td class="custom">' . $data->no_lhp . '</td>
                                        <td class="custom">' . $data->sub_kategori . '</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table style="padding: 20px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Pelanggan</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Nama
                                            Pelanggan</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $data->nama_pelanggan . '</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120">Alamat /
                                            Lokasi
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.$alamat_sample.'</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120"><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Sampling</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Kategori</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $data->sub_kategori . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Parameter</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $parame . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Metode Sampling</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">-</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Tanggal
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . self::tanggal_indonesia($data->tgl_sampling) . '</td>
                                    </tr>

                                    <tr>
                                        <td class="custom5">Periode
                                            Analisa</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">'.$period1.' - '.$period2.'</td>
                                    </tr>
                                </table>';
                                 $header .= '<table style="padding: 10px 0px 0px 0px;" width="100%">';
                                    foreach(json_decode($data->regulasi) as $t => $y) {
                                       $header .=  '<tr>
                                        <td class="custom5" colspan="3">'.$y.'</td>
                                    </tr>';
                                    }
                                $header .= '</table></td>
                        </tr>
                    </table>
                </div>';
        $url = request()->headers->all()['origin'][0];
        $file_qr = "$url/utc/apps/public/qr_documents/$data->file_qr.svg";
        if($mode_download == 'downloadWSDraft') { 
            $qr_img = '';
        }else if($mode_download == 'downloadLHP') {
            if (!is_null($data->file_qr)) {
                $qr_img = '<img src="' .$file_qr.'" width="45px" height="45px" style="margin-top: 10px;">';
            }  else {
                $qr_img = '';
            }
        }
        $no_lhp = str_replace("/", "-", $data->no_lhp);
        $name = 'LHP-'.$no_lhp.'.pdf';
        $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download);
        return $ress;
    }

    static function emisisumbertidakbergerak($data, $data1, $mode_download, $custom)
    {
        // dd($data, $data1, $mode_download);
        // $methode_sampling = $data->metode_sampling ? $data->metode_sampling : '-';
        $period = explode(" - ",$data->periode_analisa);
        $period1 = self::tanggal_indonesia($period[0]);
        $period2 = self::tanggal_indonesia($period[1]);

        $customBod = [];
        if(!empty($custom)) {

        }else {
            $bodi = '<div class="left"><table
                            style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                            <thead>
                            <tr>
                                <th rowspan="2" width="25"
                                    class="pd-5-solid-top-center">NO</th>
                                <th rowspan="2" width="250" class="pd-5-solid-top-center">PARAMETER</th>
                                <th colspan="2" width="150" class="pd-5-solid-top-center">HASIL UJI</th>
                                <th rowspan="2" width="75" class="pd-5-solid-top-center">BAKU MUTU</th>
                                <th rowspan="2" class="pd-5-solid-top-center">SATUAN</th>
                                <th rowspan="2" class="pd-5-solid-top-center">SPESIFIKASI METODE</th>
                            </tr>
                            <tr>
                                <th class="pd-5-solid-top-center" width="75">TERUKUR</th>
                                <th class="pd-5-solid-top-center" width="75">TERKOREKSI</th>
                            </tr></thead><tbody>';
                            $tot = count($data1);
                        foreach($data1 as $key => $value) {
                           $akr = $value->akr != ''? $value->akr :'&nbsp;&nbsp;';
                            $p = $key + 1;
                            // dd($value);
                            if($p == $tot) {
                                $bodi .= '<tr>
                                    <td class="pd-5-solid-center">'.$p.'</td>
                                    <td class="pd-5-solid-left">'.$akr.'&nbsp;'.$value->parameter.'</td>
                                    <td class="pd-5-solid-center">' . $value->terukur .'&nbsp;'.$value->attr. '</td>
                                    <td class="pd-5-solid-center">' . $value->terkoreksi . '</td>
                                    <td class="pd-5-solid-center">' . $value->baku_mutu . '</td>
                                    <td class="pd-5-solid-center">' . $value->satuan . '</td>
                                    <td class="pd-5-solid-center">' . $value->spesifikasi_metode . '</td>
                                </tr>';  
                            }else {
                                $bodi .= '<tr>
                                    <td class="pd-5-dot-center">'.$p.'</td>
                                    <td class="pd-5-dot-left">'.$akr.'&nbsp;'.$value->parameter.'</td>
                                    <td class="pd-5-dot-center">' . $value->terukur .'&nbsp;'.$value->attr. '</td>
                                    <td class="pd-5-dot-center">' . $value->terkoreksi . '</td>
                                    <td class="pd-5-dot-center">' . $value->baku_mutu . '</td>
                                    <td class="pd-5-dot-center">' . $value->satuan . '</td>
                                    <td class="pd-5-dot-center">' . $value->spesifikasi_metode . '</td>
                                </tr>';
                            }
                        }
                        
                        $bodi .=  '</tbody></table></div>';
                        array_push($customBod, $bodi);
        }
            // dd($data->parameter_uji);
            
            $alamat_sample = $data->alamat_sampling != null ? $data->alamat_sampling : "-";
            // $methode_sampling = $data->metode_sampling != null ? $data->metode_sampling : "-";
            if($mode_download == 'downloadWSDraft') { 
                $pading = 'margin-bottom: 40px;';
            }else if($mode_download == 'downloadLHP') {
                $pading = '';
            }  
        $ttd = '<table
                    style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px"
                    width="100%">
                    <tr>
                        <td>Tangerang, '.self::tanggal_indonesia($data->tgl_lhp).'</td>
                    </tr>
                </table>
                <table
                    style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; '.$pading.'"
                    width="100%">
                    <tr>
                        <td>
                            <span
                                style="font-weight: bold; border-bottom: 1px solid #000">('.$data->nama_karyawan.')</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            '.$data->jabatan_karyawan.'
                        </td>
                    </tr>
                </table>';
        $header = '<table width="100%"
                    style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td>
                            <span
                                style="font-weight: bold; border-bottom: 1px solid #000">LAPORAN
                                HASIL PENGUJIAN</span>
                        </td>
                    </tr>
                </table><div class="right" style="margin-top: 13.9px;">
                    <table
                        style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            <td>
                                <table
                                    style="border-collapse: collapse; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td class="custom" width="120">No. LHP</td>
                                        <td class="custom" width="120">No. SAMPLE</td>
                                        <td class="custom" width="240">JENIS
                                            SAMPLE</td>
                                    </tr>
                                    <tr>
                                        <td class="custom">' . $data->no_lhp . '</td>
                                        <td class="custom">' . $data->no_sample . '</td>
                                        <td class="custom">' . $data->sub_kategori . '</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table style="padding: 20px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Pelanggan</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Nama
                                            Pelanggan</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $data->nama_pelanggan . '</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120">Alamat /
                                            Lokasi
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.$alamat_sample.'</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120"><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Sampling</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Tanggal
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . self::tanggal_indonesia($data->tgl_sampling) . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Periode
                                            Analisa</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">'.$period1.' - '.$period2.'</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Keterangan</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">'.$data->keterangan.'</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Titik Koordinat</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">'.$data->titik_koordinat.'</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Laju Alir (Velocity)</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5"></td>
                                    </tr>
                                </table>';
                                $header .= '<table style="padding: 10px 0px 0px 0px;" width="100%">';
                                    foreach(json_decode($data->regulasi) as $t => $y) {
                                       $header .=  '<tr>
                                        <td class="custom5" colspan="3">'.$y.'</td>
                                    </tr>';
                                    }
                                $header .= '</table>';
                                $header .= '<table style="padding: 10px 0px 0px 0px;" width="100%">';
                                    foreach(json_decode($data->ket_hasil) as $t => $y) {
                                       $header .=  '<tr>
                                        <td class="custom5" colspan="3">'.$y.'</td>
                                    </tr>';
                                    }
                                $header .= '</table></td>
                        </tr>
                    </table>
                </div>';
        $url = request()->headers->all()['origin'][0];
        $file_qr = "$url/utc/apps/public/qr_documents/$data->file_qr.svg";
        if($mode_download == 'downloadWSDraft') { 
            $qr_img = '';
        }else if($mode_download == 'downloadLHP') {
            if (!is_null($data->file_qr)) {
                $qr_img = '<img src="' .$file_qr.'" width="45px" height="45px" style="margin-top: 10px;">';
            }  else {
                $qr_img = '';
            }
        }
        $no_lhp = str_replace("/", "-", $data->no_lhp);
        $name = 'LHP-'.$no_lhp.'.pdf';
         $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download);
        return $ress;
    }

    public function DirectPencahayaandenganKolomBakumutu($data, $data1, $mode_download, $custom)
    {
        $methode_sampling = $data->metode_sampling ? $data->metode_sampling : '-';
        $period = explode(" - ",$data->periode_analisa);
        $period1 = self::tanggal_indonesia($period[0]);
        $period2 = self::tanggal_indonesia($period[1]);
         $customBod = [];
        if(!empty($custom)) {

        }else {
            $bodi = '<div style="float:right;width:95%;"><table
                            style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                            <thead>
                            <tr>
                                <th rowspan="2" width="25"
                                    class="pd-5-solid-top-center">NO</th>
                                <th rowspan="2" width="300" class="pd-5-solid-top-center">LOKASI /
                                    KETERANGAN
                                    SAMPEL</th>
                                <th width="100" class="pd-5-solid-top-center">
                                    HASIL UJI
                                </th>
                                <th width="200" class="pd-5-solid-top-center">
                                    BAKU MUTU**
                                </th>
                            </tr>
                            <tr>
                                <th class="pd-5-solid-top-center" colspan="2">Satuan = LUX</th>
                            </tr></thead><tbody>';
                            $tot = count($data1);
             foreach ($data1 as $kk => $yy) {
                $p = $kk + 1;
                if($p == $tot) {
                    $bodi .= '<tr>
                                <td class="pd-5-solid-center">'.$p.'</td>
                                <td class="pd-5-solid-left">' . $data->keterangan.'</td>
                                <td class="pd-5-solid-center">'. str_replace('.',',',$data1[0]['hasil']) .'</td>
                                <td class="pd-5-solid-left">'.$baku.'</td>
                            </tr>';
                }else {
                    $bodi .= '<tr>
                                <td class="pd-5-dot-center">'.$p.'</td>
                                <td class="pd-5-dot-left">' . $data->keterangan.'</td>
                                <td class="pd-5-dot-center">'. str_replace('.',',',$data1[0]['hasil']) .'</td>
                                <td class="pd-5-dot-left">'.$baku.'</td>
                            </tr>';
                }
             }               
                    
            $bodi .= '</tbody></table></div>';
             array_push($customBod, $bodi);
        }
        if($mode_download == 'downloadWSDraft') { 
            $pading = 'margin-bottom: 40px;';
            $title ='LAPORAN HASIL PENGUJIAN';
        }else if($mode_download == 'downloadLHP') {
            $pading = '';
            $title ='LAPORAN HASIL PENGUJIAN';
        }  
         $ttd = '<table
                    style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px"
                    width="100%">
                    <tr>
                        <td>Tangerang, '.self::tanggal_indonesia($data->tgl_lhp).'</td>
                    </tr>
                </table>
                <table
                    style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; '.$pading.'"
                    width="100%">
                    <tr>
                        <td>
                            <span
                                style="font-weight: bold; border-bottom: 1px solid #000">('.$data->nama_karyawan.')</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            '.$data->jabatan_karyawan.'
                        </td>
                    </tr>
                </table>';
        $header = '<table width="100%"
                    style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td>
                            <span
                                style="font-weight: bold; border-bottom: 1px solid #000">"'. $title .'"</span>
                        </td>
                    </tr>
                </table><div class="right" style="margin-top: 13.9px;">
                    <table
                        style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            <td>
                                <table
                                    style="border-collapse: collapse; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td class="custom" width="120">No. LHP</td>
                                        <td class="custom" width="240">JENIS
                                            SAMPLE</td>
                                    </tr>
                                    <tr>
                                        <td class="custom">'.$data->cfr.'</td>
                                        <td class="custom">'.$data->name_categori.'</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table style="padding: 20px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Pelanggan</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Nama
                                            Pelanggan</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.$data->nama.'</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120">Alamat /
                                            Lokasi
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">-</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120"><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Sampling</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Parameter</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.$parame.'</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Tanggal
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.self::tanggal_indonesia($tgl).'</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Metode Sampling</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">'.$data1[0]['method'].'</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Periode
                                            Analisa</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">-</td>
                                    </tr>
                                </table>
                                <table style="padding: 50px 0px 12px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="12">**</td>
                                        <td class="custom5">'.$data->perkemen.'</td>
                                    </tr>
                                </table>
                                <table
                                    style="border-collapse: collapse;"
                                    width="100%">
                                    <tr>
                                        <td class="custom">No</td>
                                        <td class="custom">KETERANGAN
                                        </td>
                                        <td class="custom">INTENSITAS(LUX)</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">1</td>
                                        <td class="custom1">Penerangan darurat</td>
                                        <td class="custom2">5</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">2</td>
                                        <td class="custom1">Halaman dan jalan</td>
                                        <td class="custom2">20</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">3</td>
                                        <td class="custom1">Pekerjaan membedakan barang kasar <sup>*</sup></td>
                                        <td class="custom2">50</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">4</td>
                                        <td class="custom1">Pekerjaan yang membedakan barang - barang kecil secara sepintas lalu <sup>*</sup></td>
                                        <td class="custom2">100</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">5</td>
                                        <td class="custom1">Pekerjaan yang membedakan barang - barang kecil yang agak teliti <sup>*</sup></td>
                                        <td class="custom2">200</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">6</td>
                                        <td class="custom1">Pekerjaan pembedaan yang teliti daripada barang - barang kecil dan halus <sup>*</sup></td>
                                        <td class="custom2">300</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">7</td>
                                        <td class="custom1">Pekerjaan membeda-bedakan barang - barang halus dengan kontras yang sedang dan dalam waktu yang lama <sup>*</sup></td>
                                        <td class="custom2">500 - 1000</td>
                                    </tr>
                                    <tr>
                                        <td class="custom9">8</td>
                                        <td class="custom4">Pekerjaan membeda-bedakan barang - barang yang sangat halus dengan kontras yang sangat kurang untuk waktu yang lama <sup>*</sup></td>
                                        <td class="custom9">1000</td>
                                    </tr>
                                    <tr>
                                    <td colspan="2"><sup>*</sup> Rincian secara lengkap agar dapat dilihat langsung pada regulasi yang dimaksud</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>';
        $url = request()->headers->all()['origin'][0];
        $file_qr = "$url/utc/apps/public/qr_documents/$data->file_qr.svg";
        if($mode_download == 'downloadWSDraft') { 
            $qr_img = '';
        }else if($mode_download == 'downloadLHP') {
            if (!is_null($data->file_qr)) {
                $qr_img = '<img src="' .$file_qr.'" width="45px" height="45px" style="margin-top: 10px;">';
            }  else {
                $qr_img = '';
            }
        }
        $no_lhp = str_replace("/", "-", $data->no_lhp);
        $name = 'LHP-'.$no_lhp.'.pdf';
        $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download);
        return $ress;
    }
    public function DirectPencahayaanMenggunakanTabelBakumutu($data, $data1, $mode_download, $custom)
    {
        $methode_sampling = $data->metode_sampling ? $data->metode_sampling : '-';
        $period = explode(" - ",$data->periode_analisa);
        $period1 = self::tanggal_indonesia($period[0]);
        $period2 = self::tanggal_indonesia($period[1]);
        $customBod = [];
        if(!empty($custom)) {

        }else {
            $bodi = '<div style="float:right;width:95%;"><table
                            style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                            <thead>
                            <tr>
                                <th rowspan="2" width="25"
                                    class="pd-5-solid-top-center">NO</th>
                                <th rowspan="2" width="300" class="pd-5-solid-top-center">LOKASI /
                                    KETERANGAN
                                    SAMPEL</th>
                                <th width="100" class="pd-5-solid-top-center">
                                    HASIL UJI
                                </th>
                            </tr>
                            <tr>
                                <th class="pd-5-solid-top-center" colspan="2">Satuan = LUX</th>
                            </tr></thead><tbody>';
                            $tot = count($data1);
             foreach ($data1 as $kk => $yy) {
                $p = $kk + 1;
                if($p == $tot) {
                    $bodi .= '<tr>
                                <td class="pd-5-solid-center">'.$p.'</td>
                                <td class="pd-5-solid-left"><sup style="font-size:5px; !important; margin-top:-10px;">'.$yy['no_sampel'].'</sup>'.$yy['lokasi_keterangan'].'</td>
                                <td class="pd-5-solid-center">'. str_replace('.',',',$yy['hasil']) .'</td>
                            </tr>';
                }else {
                    $bodi .= '<tr>
                                <td class="pd-5-dot-center">'.$p.'</td>
                                <td class="pd-5-dot-left"><sup style="font-size:5px; !important; margin-top:-10px;">'.$yy['no_sampel'].'</sup>'.$yy['lokasi_keterangan'].'</td>
                                <td class="pd-5-dot-center">'. str_replace('.',',',$yy['hasil']) .'</td>
                            </tr>';
                }
             }               
                    
            $bodi .= '</tbody></table></div>';
             array_push($customBod, $bodi);
        }
        if($mode_download == 'downloadWSDraft') { 
            $pading = 'margin-bottom: 40px;';
            $title ='LAPORAN HASIL PENGUJIAN';
        }else if($mode_download == 'downloadLHP') {
            $pading = '';
            $title ='LAPORAN HASIL PENGUJIAN';
        }  
        $ttd = '<table
                    style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px"
                    width="100%">
                    <tr>
                        <td>Tangerang, '.self::tanggal_indonesia($data->tgl_lhp).'</td>
                    </tr>
                </table>
                <table
                    style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; '.$pading.'"
                    width="100%">
                    <tr>
                        <td>
                            <span
                                style="font-weight: bold; border-bottom: 1px solid #000">('.$data->nama_karyawan.')</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            '.$data->jabatan_karyawan.'
                        </td>
                    </tr>
                </table>';
        $header = '<table width="100%"
                    style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td>
                            <span
                                style="font-weight: bold; border-bottom: 1px solid #000">"'. $title .'"</span>
                        </td>
                    </tr>
                </table><div class="right" style="margin-top: 13.9px;">
                    <table
                        style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            <td>
                                <table
                                    style="border-collapse: collapse; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td class="custom" width="120">No. LHP</td>
                                        <td class="custom" width="240">JENIS
                                            SAMPLE</td>
                                    </tr>
                                    <tr>
                                        <td class="custom">'.$data->no_lhp.'</td>
                                        <td class="custom">'.$data->sub_kategori.'</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table style="padding: 20px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Pelanggan</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Nama
                                            Pelanggan</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.$data->nama_pelanggan.'</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120">Alamat /
                                            Lokasi
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.$data->alamat_sampling.'</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                
                                    <tr>
                                        <td class="custom5" width="120">Metode
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">'.$methode_sampling.'</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Tanggal
                                            Sampling</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">'.self::tanggal_indonesia($data->tgl_sampling).'</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Periode Analisa</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">'.$period1.' - '.$period2.'</td>
                                    </tr>
                                </table>';
                                $header .= '<table style="padding: 10px 0px 0px 0px;" width="100%">';
                                    foreach(json_decode($data->regulasi) as $t => $y) {
                                       $header .=  '<tr>
                                        <td class="custom5" colspan="3">'.$y.'</td>
                                    </tr>';
                                    }
                                $header .= '</table>';
                                $header .= '<table
                                    style="border-collapse: collapse;"
                                    width="100%">
                                    <tr>
                                        <td class="custom">No</td>
                                        <td class="custom">KETERANGAN
                                        </td>
                                        <td class="custom">INTENSITAS(LUX)</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">1</td>
                                        <td class="custom1">Penerangan darurat</td>
                                        <td class="custom2">5</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">2</td>
                                        <td class="custom1">Halaman dan jalan</td>
                                        <td class="custom2">20</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">3</td>
                                        <td class="custom1">Pekerjaan membedakan barang kasar <sup>*</sup></td>
                                        <td class="custom2">50</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">4</td>
                                        <td class="custom1">Pekerjaan yang membedakan barang - barang kecil secara sepintas lalu <sup>*</sup></td>
                                        <td class="custom2">100</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">5</td>
                                        <td class="custom1">Pekerjaan yang membedakan barang - barang kecil yang agak teliti <sup>*</sup></td>
                                        <td class="custom2">200</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">6</td>
                                        <td class="custom1">Pekerjaan pembedaan yang teliti daripada barang - barang kecil dan halus <sup>*</sup></td>
                                        <td class="custom2">300</td>
                                    </tr>
                                    <tr>
                                        <td class="custom2">7</td>
                                        <td class="custom1">Pekerjaan membeda-bedakan barang - barang halus dengan kontras yang sedang dan dalam waktu yang lama <sup>*</sup></td>
                                        <td class="custom2">500 - 1000</td>
                                    </tr>
                                    <tr>
                                        <td class="custom9">8</td>
                                        <td class="custom4">Pekerjaan membeda-bedakan barang - barang yang sangat halus dengan kontras yang sangat kurang untuk waktu yang lama <sup>*</sup></td>
                                        <td class="custom9">1000</td>
                                    </tr>
                                    <tr>
                                    <td colspan="2"><sup>*</sup> Rincian secara lengkap agar dapat dilihat langsung pada regulasi yang dimaksud</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>';
        $url = request()->headers->all()['origin'][0];
        $file_qr = "$url/utc/apps/public/qr_documents/$data->file_qr.svg";
        if($mode_download == 'downloadWSDraft') { 
            $qr_img = '';
        }else if($mode_download == 'downloadLHP') {
            if (!is_null($data->file_qr)) {
                $qr_img = '<img src="' .$file_qr.'" width="45px" height="45px" style="margin-top: 10px;">';
            }  else {
                $qr_img = '';
            }
        }
        $no_lhp = str_replace("/", "-", $data->no_lhp);
        $name = 'LHP-'.$no_lhp.'.pdf';
        $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download);
        return $ress;
    }
    

    static function formatTemplate($bodi, $header, $filename, $ttd, $qr_img, $mode_download, $custom2)
    {
        $mpdfConfig = array(
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_header' => 13,
            'margin_bottom'=> 17,
            'margin_footer' => 8,
            'margin_top' => 23.5,
            'margin_left' => 10, 
            'margin_right' => 10, 
            // 'setAutoTopMargin' => 'stretch',
            // 'setAutoBottomMargin' => 'stretch',
            'orientation' => 'L',
            // 'isHtml5ParserEnabled' => true
        );
        $pdf = new PDF($mpdfConfig);
        $stylesheet = " .custom {
                            padding: 3px;
                            text-align: center;
                            border: 1px solid #000000;
                            font-weight: bold;
                            font-size: 9px;
                        }
                        .custom {
                            padding: 3px;
                            text-align: center;
                            border: 1px solid #000000;
                            font-size: 9px;
                        }
                        .custom1 {
                            padding: 3px;
                            text-align: left;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px dotted #5b5b5b;
                            font-size: 9px;
                        }
                        .custom2 {
                            padding: 3px;
                            text-align: center;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px dotted #5b5b5b;
                            font-size: 9px;
                        }
                        .custom3 {
                            padding: 3px;
                            text-align: center;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px solid #000000;
                            font-size: 9px;
                        }
                        .custom4 {
                            padding: 3px;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px solid #000000;
                            font-size: 9px;
                        }
                        .custom5 {
                            text-align: left;
                        }
                        .custom6 {
                            padding: 3px;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                        }
                        .custom7 {
                            text-align: center;
                            border: 1px solid #000000;
                            font-weight: bold;
                            font-size: 9px;
                        }
                        .custom8 {
                            text-align: center;
                            font-style: italic;
                            border: 1px solid #000000;
                            font-weight: bold;
                            font-size: 9px;
                        }
                        .custom9 {
                            padding: 3px;
                            text-align: center;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px solid #000000;
                            font-size: 9px;
                        }
                        .pd-5-dot-center {
                            padding: 8px;
                            text-align: center;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px dotted #000000;
                            font-size: 9px;
                        }
                        .pd-5-dot-left {
                            padding: 8px;
                            text-align: left;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px dotted #000000;
                            font-size: 9px;
                        }
                        .pd-5-solid-left {
                            padding: 8px;
                            text-align: left;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px solid #000000;
                            font-size: 9px;
                        }
                        .pd-5-solid-center {
                            padding: 8px;
                            text-align: center;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px solid #000000;
                            font-size: 9px;
                        }
                        .pd-5-solid-top-center {
                            padding: 8px;
                            text-align: center;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px solid #000000;
                            border-top: 1px solid #000000;
                            font-size: 9px;
                            font-weight: bold;
                        }
                        .right {
                            float: right;
                            width: 40%;
                            height: 100%;
                        }
                        .left {
                            float: left;
                            width: 59%;
                        }
                        .left2 {
                            float: left;
                            width: 69%;
                        }";
        $url = request()->headers->all()['origin'][0];
        $file_qr = "$url/utc/apps/public/qr_documents/$qr_img.svg";
        if($mode_download == 'downloadWSDraft') { 
            $qr = 'Halaman {PAGENO} - {nbpg}';
            $tanda_tangan = '';
            // $ketFooter = '<td width="15%" style="vertical-align: bottom;"></td><td width="62%" style="vertical-align: bottom; text-align:center;">Lembar Draft Tergenerate Otomatis Oleh Sistem.</td>';
            $ketFooter = '<td width="15%" style="vertical-align: middle;">
                          <div>PT Inti Surya Laboratorium</div>
                          <div>Ruko Icon Business Park Blok O No.5-6 BSD City, Jl. BSD Raya Utama, Cisauk, Sampora Kab. Tangerang 15341</div>
                          <div>021-5089-8988/89 contact@intilab.com</div>
                          </td>
                          <td style="vertical-align: middle; text-align:right;">Hasil Uji ini hanya berlaku untuk sampel yang diuji. Lembar ini tidak boleh diubah atau digandakan tanpa izin tertulis dari pihak Laboratorium..</td>';
            $url = public_path() . '/watermark-draft.png';
            $body = '<body style="background: url('.$url.');">';
        }else if($mode_download == 'downloadLHP') {
            // if (!is_null($qr_img)) {
            //     $qr = '<img src="' .$file_qr.'" width="45px" height="45px" style="margin-top: 10px;"><br>Halaman {PAGENO} - {nbpg}';
            // }  else {
            //     $qr = 'Halaman {PAGENO} - {nbpg}';
            // }
            if (!is_null($qr_img)) {
                $qr = '<img src="' .$file_qr.'" width="45px" height="45px" style="margin-top: 10px;"><br>DP/7.8.1/ISL; Rev 3; 08 November 2022';
            }  else {
                $qr = 'DP/7.8.1/ISL; Rev 3; 08 November 2022';
            }
            $tanda_tangan = $ttd;
            $ketFooter = '<td width="15%" style="vertical-align: bottom;">
                          <div>PT Inti Surya Laboratorium</div>
                          <div>Ruko Icon Business Park Blok O No.5-6 BSD City, Jl. BSD Raya Utama, Cisauk, Sampora Kab. Tangerang 15341</div>
                          <div>021-5089-8988/89 contact@intilab.com</div>
                          </td>
                          <td width="59%" style="vertical-align: bottom; text-align:center; padding:0; padding-left:44px; margin:0; position:relative; min-height:100px;">
                            Hasil uji ini hanya berlaku untuk sampel yang diujixx. Lembar ini tidak boleh diubah ataupun digandakan tanpa izin tertulis dari pihak laboratorium.
                            <br>Halaman {PAGENO} - {nbpg}
                        </td>';
            $body = '<body>';
        }else if($mode_download == 'draft_customer') {
            if (!is_null($qr_img)) {
                $qr = '<img src="' .$file_qr.'" width="45px" height="45px" style="margin-top: 10px;"><br>Halaman {PAGENO} - {nbpg}';
            }  else {
                $qr = 'Halaman {PAGENO} - {nbpg}';
            }
            $tanda_tangan = '';
            $ketFooter = '<td width="15%" style="vertical-align: bottom;">DP/7.8.1/ISL; Rev 3; 08 November 2022</td><td width="62%" style="vertical-align: bottom; text-align:center;">Hasil uji ini hanya berlaku untuk sampel yang diuji. Lembar ini tidak boleh diubah ataupun digandakan tanpa izin tertulis dari pihak laboratorium.</td>';
            $body = '<body>';
        }
        
        $pdf->WriteHTML($stylesheet, 1);
        $pdf->WriteHTML('<!DOCTYPE html>
            <html>
                '.$body.'');
        // =================Isi Data==================
        // dd($header);
        $pdf->SetHTMLHeader($header,'', TRUE);
        $pdf->SetHTMLFooter('
            <table width="100%" style="font-size:7px">
                <tr>
                    '.$ketFooter.'
                    <td width="23%" style="text-align: right;">'.$tanda_tangan.$qr.'</td>
                </tr>
                <tr>
                </tr>
            </table>
        ');
        // dd($bodi[0]);
        $tot = count($bodi) - 1;
        foreach($bodi as $key => $val) {
            $pdf->WriteHTML($val);
            if($tot > $key) {
                $pdf->AddPage();
            }
        }

        $pdf->WriteHTML('</body>
        </html>');
        if($mode_download == 'downloadWSDraft' && $custom2 == 1) {
            $pdf->Output('../../../utc/apps/public/lhps/'.$filename);
            $pdf->Output('TemplateWSDraft/'.$filename);
        }
        else if($mode_download == 'downloadWSDraft' || $mode_download == 'draft_customer') {
            $pdf->Output('../../../utc/apps/public/lhps/'.$filename);
            $pdf->Output('TemplateWSDraft/'.$filename);
        }else if($mode_download == 'downloadLHP') {
            $pdf->Output('TemplateLHP/'.$filename);
        }
        return $filename;
    }
}