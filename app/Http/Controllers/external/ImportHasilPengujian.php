<?php

namespace App\Http\Controllers\external;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

use App\Models\Subkontrak;
use App\Models\OrderDetail;
use App\Models\WsValueUdara;
use App\Models\Parameter;
use App\Models\EmisiCerobongHeader;
use App\Models\WsValueEmisiCerobong;

class ImportHasilPengujian extends \Laravel\Lumen\Routing\Controller
{
    private function getNilaiAkhirSel($worksheet, $cell)
    {
        return $worksheet->getCell($cell)->getOldCalculatedValue() ?? $worksheet->getCell($cell)->getCalculatedValue() ?? $worksheet->getCell($cell)->getValue() ?? null;
    }

    public function importLhpUdaraAmbient(Request $request)
    {
        DB::beginTransaction();
        try {
            $file = $request->file;
            if (!$file) return response()->json(['message' => 'Filenya mana kocak'], 400);

            if (!in_array($file->getClientOriginalExtension(), ['xlsx', 'xls'])) return response()->json(['message' => 'Filenya bukan excel'], 400);

            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load($file->getRealPath());
            if (!$spreadsheet->sheetNameExists('LHP')) return response()->json(['message' => 'Sheet LHP ga ketemu'], 400);

            $worksheet = $spreadsheet->getSheetByName('LHP');

            $startRow = 7;
            $maxCol = 'J';
            $highestRow = $worksheet->getHighestDataRow($maxCol);

            $noSampel = $this->getNilaiAkhirSel($worksheet, "Q6");

            // $jenisSampel = $this->getNilaiAkhirSel($worksheet, "T6");
            // if ($jenisSampel !== 'Udara Ambient') return response()->json(['message' => 'Ini bener file udara ambient?'], 400);

            $orderDetail = OrderDetail::where('no_sampel', $noSampel)->first();
            if (!$orderDetail) return response()->json(['message' => 'Order Detail ga ada'], 400);
            
            $parameters = [];
            $idHeader = [];

            $parameterUji = json_decode($orderDetail->parameter, TRUE);

            $parameterUji = array_map(function ($item) {
                return \explode(';', $item)[1];
            }, $parameterUji);

            for ($row = $startRow; $row <= $highestRow; $row += 2) { // += 2 karna dimerge
                $durasi = $this->getNilaiAkhirSel($worksheet, "E{$row}") ?? null;
                $parameter = $this->getNilaiAkhirSel($worksheet, "D{$row}");

                if($parameter == 'PM ₁₀' && $durasi != '1 Jam') $parameter = "PM 10 ({$durasi})";
                if($parameter == 'PM ₁₀' && $durasi == '1 Jam') $parameter = "PM 10";

                if($parameter == 'PM ₂,₅' && $durasi != '1 Jam') $parameter = "PM 2.5 ({$durasi})";
                if($parameter == 'PM ₂,₅' && $durasi == '1 Jam') $parameter = "PM 2.5";

                if($parameter == 'Hidrogen Sulfida (H₂S)' && $durasi == '1 Jam') $parameter = 'Sulfur (H₂S)';
                if($parameter == 'Hidrogen Sulfida (H₂S)' && $durasi != '1 Jam') $parameter = "H2S ({$durasi})";

                if($parameter == 'Amoniak (NH₃)' && $durasi != '1 Jam') $parameter = 'NH3 ({$durasi})';
                if($parameter == 'Amoniak (NH₃)' && $durasi == '1 Jam') $parameter = 'NH3';

                if($parameter == 'Total Partikulat' && $durasi != '1 Jam') $parameter = "TSP ({$durasi})";
                if($parameter == 'Total Partikulat' && $durasi == '1 Jam') $parameter = "TSP";

                if($parameter == 'Timah Hitam (Pb)' && $durasi == '1 Jam') $parameter = "Pb";
                if($parameter == 'Timah Hitam (Pb)' && $durasi != '1 Jam') $parameter = "Pb ({$durasi})";

                if($parameter == 'Hidrokarbon Non Metana (NMHC)' && $durasi != '1 Jam') $parameter = "HCNM ({$durasi})";
                if($parameter == 'Hidrokarbon Non Metana (NMHC)' && $durasi == '1 Jam') $parameter = "HCNM";
                if($parameter == 'Hidrokarbon (HC) - Non-Methane') $parameter = "HCNM";
                
                if($parameter == 'Karbon Monoksida (CO)' && $durasi != '1 Jam') $parameter = "CO ({$durasi})";
                if($parameter == 'Karbon Monoksida (CO)' && $durasi == '1 Jam') $parameter = "C O";
                
                if($parameter == 'Karbon Dioksida (CO₂)' && $durasi != '1 Jam') $parameter = "CO2 ({$durasi})";
                if($parameter == 'Karbon Dioksida (CO₂)' && $durasi == '1 Jam') $parameter = "CO2";
                
                if($parameter == 'Sulfur Dioksida (SO₂)' && $durasi != '1 Jam') $parameter = "SO2 ({$durasi})";
                if($parameter == 'Sulfur Dioksida (SO₂)' && $durasi == '1 Jam') $parameter = "SO2";
                
                if($parameter == 'Nitrogen Dioksida (NO₂)' && $durasi != '1 Jam') $parameter = "NO2 ({$durasi})";
                if($parameter == 'Nitrogen Dioksida (NO₂)' && $durasi == '1 Jam') $parameter = "NO2";

                if($parameter == 'Oksidan Fotokimia (Oᵪ) sebagai Ozon (O₃)' && $durasi != '1 Jam') $parameter = "O3 ({$durasi})";
                if($parameter == 'Oksidan Fotokimia (Oᵪ) sebagai Ozon (O₃)' && $durasi == '1 Jam') $parameter = "O3";
                
                if($parameter == 'Ozon (O₃)' && $durasi != '1 Jam') $parameter = "O3 ({$durasi})";
                if($parameter == 'Ozon (O₃)' && $durasi == '1 Jam') $parameter = "O3";

                $hasilUji = $this->getNilaiAkhirSel($worksheet, "F{$row}");
                
                if (!$parameter) break;

                $cekParameter = Parameter::where('nama_regulasi', $parameter)
                ->whereIn('nama_lab', $parameterUji)
                ->where('id_kategori', 4)
                ->first();               
                
                $parameter = $cekParameter->nama_lab ?? $parameter;
                $id_subkontrak = null;
                $validasi = WsValueUdara::with([
                            'lingkungan',
                            'partikulat',
                            'direct_lain',
                            'subkontrak'
                        ])->where('no_sampel', $noSampel)
                        ->where(function ($query) use ($parameter) {
                            $query->whereHas('lingkungan',fn($r) => $r->where('parameter', $parameter))
                                ->orWhereHas('partikulat',fn($r) => $r->where('parameter', $parameter))
                                ->orWhereHas('direct_lain',fn($r) => $r->where('parameter', $parameter))
                                ->orWhereHas('subkontrak',fn($r) => $r->where('parameter', $parameter));
                        })
                        ->first();
                
                if($validasi == null) {
                    $subkontrak = new Subkontrak();
                    $subkontrak->category_id = explode('-', $orderDetail->kategori_2)[0];
                    $subkontrak->no_sampel = $noSampel;
                    $subkontrak->parameter = $parameter;
                    $subkontrak->jenis_pengujian = 'sample';
                    $subkontrak->lhps = 0;
                    $subkontrak->is_approve = 1;
                    $subkontrak->created_by = 'System';
                    $subkontrak->created_at = date('Y-m-d H:i:s');
                    $subkontrak->save();
                    $id_subkontrak = $subkontrak->id;
                }

                $hasilUji = str_replace(',', '.', $hasilUji);

                if($id_subkontrak == null){
                    $wsValueUdara = WsValueUdara::where('id', $validasi->id)->first();
                    $wsValueUdara->f_koreksi_1 = $hasilUji;
                    $wsValueUdara->f_koreksi_2 = $hasilUji;
                    $wsValueUdara->f_koreksi_3 = $hasilUji;
                    $wsValueUdara->f_koreksi_4 = $hasilUji;
                    $wsValueUdara->f_koreksi_5 = $hasilUji;
                    $wsValueUdara->f_koreksi_6 = $hasilUji;
                    $wsValueUdara->f_koreksi_7 = $hasilUji;
                    $wsValueUdara->f_koreksi_8 = $hasilUji;
                    $wsValueUdara->f_koreksi_9 = $hasilUji;
                    $wsValueUdara->f_koreksi_10 = $hasilUji;
                    $wsValueUdara->f_koreksi_11 = $hasilUji;
                    $wsValueUdara->f_koreksi_12 = $hasilUji;
                    $wsValueUdara->f_koreksi_13 = $hasilUji;
                    $wsValueUdara->f_koreksi_14 = $hasilUji;
                    $wsValueUdara->f_koreksi_15 = $hasilUji;
                    $wsValueUdara->f_koreksi_16 = $hasilUji;
                    $wsValueUdara->f_koreksi_17 = $hasilUji;
                    $wsValueUdara->save();
                } else {
                    $wsValueUdara = new WsValueUdara();
                    $wsValueUdara->id_subkontrak = $id_subkontrak;
                    $wsValueUdara->id_po = $orderDetail->id;
                    $wsValueUdara->no_sampel = $noSampel;
                    $wsValueUdara->f_koreksi_1 = $hasilUji;
                    $wsValueUdara->f_koreksi_2 = $hasilUji;
                    $wsValueUdara->f_koreksi_3 = $hasilUji;
                    $wsValueUdara->f_koreksi_4 = $hasilUji;
                    $wsValueUdara->f_koreksi_5 = $hasilUji;
                    $wsValueUdara->f_koreksi_6 = $hasilUji;
                    $wsValueUdara->f_koreksi_7 = $hasilUji;
                    $wsValueUdara->f_koreksi_8 = $hasilUji;
                    $wsValueUdara->f_koreksi_9 = $hasilUji;
                    $wsValueUdara->f_koreksi_10 = $hasilUji;
                    $wsValueUdara->f_koreksi_11 = $hasilUji;
                    $wsValueUdara->f_koreksi_12 = $hasilUji;
                    $wsValueUdara->f_koreksi_13 = $hasilUji;
                    $wsValueUdara->f_koreksi_14 = $hasilUji;
                    $wsValueUdara->f_koreksi_15 = $hasilUji;
                    $wsValueUdara->f_koreksi_16 = $hasilUji;
                    $wsValueUdara->f_koreksi_17 = $hasilUji;
                    $wsValueUdara->save();
                }
                $parameters[] = $parameter;
                $idHeader[] = $wsValueUdara->id;
            }

            DB::commit();
            return response()->json(['message' => 'dah kelar', 'parameters' => $parameters, 'idHeader' => $idHeader], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            dd($th);
        }
    }

    public function importLhpUdaraLingkunganKerja(Request $request)
    {
        DB::beginTransaction();
        try {
            $file = $request->file;
            if (!$file) return response()->json(['message' => 'Filenya mana kocak'], 400);

            if (!in_array($file->getClientOriginalExtension(), ['xlsx', 'xls'])) return response()->json(['message' => 'Filenya bukan excel'], 400);

            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load($file->getRealPath());
            if (!$spreadsheet->sheetNameExists('LHP')) return response()->json(['message' => 'Sheet LHP ga ketemu'], 400);

            $worksheet = $spreadsheet->getSheetByName('LHP');

            $startRow = 7;
            $maxCol = 'I';
            $isDuration = false;
            $highestRow = $worksheet->getHighestDataRow($maxCol);
            $noSampel = $this->getNilaiAkhirSel($worksheet, "P6");

            if($noSampel == ''){
                $noSampel = $this->getNilaiAkhirSel($worksheet, "Q6");
                $isDuration = true;
            }

            $noSampel = trim($noSampel);
            // $jenisSampel = $this->getNilaiAkhirSel($worksheet, "S6");
            // if ($jenisSampel !== 'Lingkungan Kerja') return response()->json(['message' => 'Ini bener file udara lingkungan kerja?'], 400);
            if($noSampel == '' || $noSampel == "Lingkungan Kerja") return response()->json(['message' => 'Format Excel tidak dapat di import'], 400);
            $orderDetail = OrderDetail::where('no_sampel', $noSampel)->first();
            if ($orderDetail == null) return response()->json(['message' => "{$noSampel} nomor tidak dapat ditemukan atau ada perbedaan karakter."], 400);

            $parameters = [];
            $idHeader = [];
            
            $parameterUji = json_decode($orderDetail->parameter, TRUE);

            $parameterUji = array_map(function ($item) {
                return \explode(';', $item)[1];
            }, $parameterUji);

            for ($row = $startRow; $row <= $highestRow; $row += 2) { // += 2 karna dimerge
                $parameter = $this->getNilaiAkhirSel($worksheet, "D{$row}");
                

                if($isDuration){
                    $hasilUji = $this->getNilaiAkhirSel($worksheet, "F{$row}");
                    $durasi = $this->getNilaiAkhirSel($worksheet, "E{$row}") ?? null;

                    if($parameter == 'PM ₁₀' && $durasi != '1 Jam') $parameter = "PM 10 ({$durasi})";
                    if($parameter == 'PM ₁₀' && $durasi == '1 Jam') $parameter = "PM 10";

                    if($parameter == 'PM ₂,₅' && $durasi != '1 Jam') $parameter = "PM 2.5 ({$durasi})";
                    if($parameter == 'PM ₂,₅' && $durasi == '1 Jam') $parameter = "PM 2.5";

                    if($parameter == 'Hidrogen Sulfida (H₂S)' && $durasi == '1 Jam') $parameter = 'Sulfur (H₂S)';
                    if($parameter == 'Hidrogen Sulfida (H₂S)' && $durasi != '1 Jam') $parameter = "H2S ({$durasi})";

                    if($parameter == 'Amoniak (NH₃)' && $durasi != '1 Jam') $parameter = 'NH3 ({$durasi})';
                    if($parameter == 'Amoniak (NH₃)' && $durasi == '1 Jam') $parameter = 'NH3';

                    if($parameter == 'Total Partikulat' && $durasi != '1 Jam') $parameter = "TSP ({$durasi})";
                    if($parameter == 'Total Partikulat' && $durasi == '1 Jam') $parameter = "TSP";

                    if($parameter == 'Timah Hitam (Pb)' && $durasi == '1 Jam') $parameter = "Pb";
                    if($parameter == 'Timah Hitam (Pb)' && $durasi != '1 Jam') $parameter = "Pb ({$durasi})";

                    if($parameter == 'Hidrokarbon Non Metana (NMHC)' && $durasi != '1 Jam') $parameter = "HCNM ({$durasi})";
                    if($parameter == 'Hidrokarbon Non Metana (NMHC)' && $durasi == '1 Jam') $parameter = "HCNM";
                    if($parameter == 'Hidrokarbon (HC) - Non-Methane') $parameter = "HCNM";
                    
                    if($parameter == 'Karbon Monoksida (CO)' && $durasi != '1 Jam') $parameter = "CO ({$durasi})";
                    if($parameter == 'Karbon Monoksida (CO)' && $durasi == '1 Jam') $parameter = "C O";
                    
                    if($parameter == 'Karbon Dioksida (CO₂)' && $durasi != '1 Jam') $parameter = "CO2 ({$durasi})";
                    if($parameter == 'Karbon Dioksida (CO₂)' && $durasi == '1 Jam') $parameter = "CO2";
                    
                    if($parameter == 'Sulfur Dioksida (SO₂)' && $durasi != '1 Jam') $parameter = "SO2 ({$durasi})";
                    if($parameter == 'Sulfur Dioksida (SO₂)' && $durasi == '1 Jam') $parameter = "SO2";
                    
                    if($parameter == 'Nitrogen Dioksida (NO₂)' && $durasi != '1 Jam') $parameter = "NO2 ({$durasi})";
                    if($parameter == 'Nitrogen Dioksida (NO₂)' && $durasi == '1 Jam') $parameter = "NO2";

                    if($parameter == 'Oksidan Fotokimia (Oᵪ) sebagai Ozon (O₃)' && $durasi != '1 Jam') $parameter = "O3 ({$durasi})";
                    if($parameter == 'Oksidan Fotokimia (Oᵪ) sebagai Ozon (O₃)' && $durasi == '1 Jam') $parameter = "O3";
                    
                    if($parameter == 'Ozon (O₃)' && $durasi != '1 Jam') $parameter = "O3 ({$durasi})";
                    if($parameter == 'Ozon (O₃)' && $durasi == '1 Jam') $parameter = "O3";

                    if($parameter == 'Benzena (C₆H₆)' && $durasi != '1 Jam') $parameter = "Benzene ({$durasi})";
                    if($parameter == 'Benzena (C₆H₆)' && $durasi == '1 Jam') $parameter = "Benzene";

                    if($parameter == 'Toluena (C₇H₈)' && $durasi != '1 Jam') $parameter = "Toluene ({$durasi})";
                    if($parameter == 'Toluena (C₇H₈)' && $durasi == '1 Jam') $parameter = "Toluene";

                    if($parameter == 'Xylena (C₈H₁₀)' && $durasi != '1 Jam') $parameter = "Xylene ({$durasi})";
                    if($parameter == 'Xylena (C₈H₁₀)' && $durasi == '1 Jam') $parameter = "Xylene";
                
                } else {
                    $hasilUji = $this->getNilaiAkhirSel($worksheet, "E{$row}");

                    if($parameter == 'PM ₁₀') $parameter = "PM 10";
                    if($parameter == 'PM ₂,₅') $parameter = "PM 2.5";

                    if($parameter == 'Hidrogen Sulfida (H₂S)') $parameter = 'Sulfur (H₂S)';

                    if($parameter == 'Amoniak (NH₃)') $parameter = 'NH3';

                    if($parameter == 'Total Partikulat') $parameter = "TSP";

                    if($parameter == 'Timah Hitam (Pb)') $parameter = "Pb";

                    if($parameter == 'Hidrokarbon Non Metana (NMHC)') $parameter = "HCNM";
                    
                    if($parameter == 'Karbon Monoksida (CO)') $parameter = "C O";
                    
                    if($parameter == 'Karbon Dioksida (CO₂)') $parameter = "CO2";
                    
                    if($parameter == 'Sulfur Dioksida (SO₂)') $parameter = "SO2";
                    
                    if($parameter == 'Nitrogen Dioksida (NO₂)') $parameter = "NO2";

                    if($parameter == 'Oksidan Fotokimia (Oᵪ) sebagai Ozon (O₃)') $parameter = "O3";
                    
                    if($parameter == 'Ozon (O₃)') $parameter = "O3";

                    if($parameter == 'Benzena (C₆H₆)') $parameter = "Benzene";

                    if($parameter == 'Toluena (C₇H₈)') $parameter = "Toluene";

                    if($parameter == 'Xylena (C₈H₁₀)') $parameter = "Xylene";
                    
                }

                if (!$parameter) break;
                
                $cekParameter = Parameter::where('nama_regulasi', $parameter)
                ->whereIn('nama_lab', $parameterUji)
                ->where('id_kategori', 4)
                ->first();               
                
                $parameter = $cekParameter->nama_lab ?? $parameter;
                $id_subkontrak = null;
                $validasi = WsValueUdara::with([
                            'lingkungan',
                            'partikulat',
                            'direct_lain',
                            'subkontrak',
                            'microbiologi'
                        ])->where('no_sampel', $noSampel)
                        ->where(function ($query) use ($parameter) {
                            $query->whereHas('lingkungan',fn($r) => $r->where('parameter', $parameter))
                                ->orWhereHas('partikulat',fn($r) => $r->where('parameter', $parameter))
                                ->orWhereHas('direct_lain',fn($r) => $r->where('parameter', $parameter))
                                ->orWhereHas('subkontrak',fn($r) => $r->where('parameter', $parameter))
                                ->orWhereHas('microbiologi',fn($r) => $r->where('parameter', $parameter));
                        })
                        ->first();
                if($validasi == null) {
                    $subkontrak = new Subkontrak();
                    $subkontrak->category_id = explode('-', $orderDetail->kategori_2)[0];
                    $subkontrak->no_sampel = $noSampel;
                    $subkontrak->parameter = $parameter;
                    $subkontrak->jenis_pengujian = 'sample';
                    $subkontrak->lhps = 0;
                    $subkontrak->is_approve = 1;
                    $subkontrak->created_by = 'System';
                    $subkontrak->created_at = date('Y-m-d H:i:s');
                    $subkontrak->save();
                    $id_subkontrak = $subkontrak->id;
                }

                $hasilUji = str_replace(',', '.', $hasilUji);

                if($id_subkontrak == null){
                    $wsValueUdara = WsValueUdara::where('id', $validasi->id)->first();
                    $wsValueUdara->f_koreksi_1 = $hasilUji;
                    $wsValueUdara->f_koreksi_2 = $hasilUji;
                    $wsValueUdara->f_koreksi_3 = $hasilUji;
                    $wsValueUdara->f_koreksi_4 = $hasilUji;
                    $wsValueUdara->f_koreksi_5 = $hasilUji;
                    $wsValueUdara->f_koreksi_6 = $hasilUji;
                    $wsValueUdara->f_koreksi_7 = $hasilUji;
                    $wsValueUdara->f_koreksi_8 = $hasilUji;
                    $wsValueUdara->f_koreksi_9 = $hasilUji;
                    $wsValueUdara->f_koreksi_10 = $hasilUji;
                    $wsValueUdara->f_koreksi_11 = $hasilUji;
                    $wsValueUdara->f_koreksi_12 = $hasilUji;
                    $wsValueUdara->f_koreksi_13 = $hasilUji;
                    $wsValueUdara->f_koreksi_14 = $hasilUji;
                    $wsValueUdara->f_koreksi_15 = $hasilUji;
                    $wsValueUdara->f_koreksi_16 = $hasilUji;
                    $wsValueUdara->f_koreksi_17 = $hasilUji;
                    $wsValueUdara->save();
                } else {
                    $wsValueUdara = new WsValueUdara();
                    $wsValueUdara->id_subkontrak = $id_subkontrak;
                    $wsValueUdara->id_po = $orderDetail->id;
                    $wsValueUdara->no_sampel = $noSampel;
                    $wsValueUdara->f_koreksi_1 = $hasilUji;
                    $wsValueUdara->f_koreksi_2 = $hasilUji;
                    $wsValueUdara->f_koreksi_3 = $hasilUji;
                    $wsValueUdara->f_koreksi_4 = $hasilUji;
                    $wsValueUdara->f_koreksi_5 = $hasilUji;
                    $wsValueUdara->f_koreksi_6 = $hasilUji;
                    $wsValueUdara->f_koreksi_7 = $hasilUji;
                    $wsValueUdara->f_koreksi_8 = $hasilUji;
                    $wsValueUdara->f_koreksi_9 = $hasilUji;
                    $wsValueUdara->f_koreksi_10 = $hasilUji;
                    $wsValueUdara->f_koreksi_11 = $hasilUji;
                    $wsValueUdara->f_koreksi_12 = $hasilUji;
                    $wsValueUdara->f_koreksi_13 = $hasilUji;
                    $wsValueUdara->f_koreksi_14 = $hasilUji;
                    $wsValueUdara->f_koreksi_15 = $hasilUji;
                    $wsValueUdara->f_koreksi_16 = $hasilUji;
                    $wsValueUdara->f_koreksi_17 = $hasilUji;
                    $wsValueUdara->save();
                }
                $parameters[] = $parameter;
                $idHeader[] = $wsValueUdara->id;
            }

            DB::commit();
            return response()->json(['message' => 'Done', 'parameters' => $parameters, 'idHeader' => $idHeader], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage() . ' ' . $th->getLine()], 400);
        }
    }

    public function importLhpEmisiTidakBergerak(Request $request)
    {
        DB::beginTransaction();
        try {
            $file = $request->file;
            if (!$file) return response()->json(['message' => 'Filenya mana kocak'], 400);

            if (!in_array($file->getClientOriginalExtension(), ['xlsx', 'xls'])) return response()->json(['message' => 'Filenya bukan excel'], 400);

            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load($file->getRealPath());
            if (!$spreadsheet->sheetNameExists('LHP')) return response()->json(['message' => 'Sheet LHP ga ketemu'], 400);

            $worksheet = $spreadsheet->getSheetByName('LHP');

            $startRow = 7;
            $maxCol = 'I';
            $highestRow = $worksheet->getHighestDataRow($maxCol);

            $noSampel = $this->getNilaiAkhirSel($worksheet, "P6");
            if (!$noSampel) $noSampel = $this->getNilaiAkhirSel($worksheet, "Q6"); // emisi genset di q6
            $jenisSampel = $this->getNilaiAkhirSel($worksheet, "R6");
            if (!$jenisSampel) $jenisSampel = $this->getNilaiAkhirSel($worksheet, "S6"); // emisi genset di s6
            if ($jenisSampel !== 'Emisi Sumber Tidak Bergerak') return response()->json(['message' => 'Ini bener file estb?'], 400);
            $noSampel = str_replace(' ', '', trim($noSampel));

            $orderDetail = OrderDetail::where('no_sampel', $noSampel)->first();
            if ($orderDetail == null) return response()->json(['message' => "{$noSampel} nomor tidak dapat ditemukan atau ada perbedaan karakter."], 400);

            $parameters = [];
            $idHeader = [];
            
            $parameterUji = json_decode($orderDetail->parameter, TRUE);

            $parameterUji = array_map(function ($item) {
                return \explode(';', $item)[1];
            }, $parameterUji);
            
            $parameters = [];
            $idHeader = [];
            for ($row = $startRow; $row <= $highestRow; $row += 2) { // += 2 karna dimerge
                $parameter = $this->getNilaiAkhirSel($worksheet, "D{$row}");
                // $hasilUjiCell = $this->getNilaiAkhirSel($worksheet, "F6") == 'TERKOREKSI' ? "F{$row}" : "E{$row}"; // emisi genset di f7
                // $hasilUji = $this->getNilaiAkhirSel($worksheet, $hasilUjiCell);
                $hasilUji = $this->getNilaiAkhirSel($worksheet, "E{$row}");
                $hasilTerkoreksi = $this->getNilaiAkhirSel($worksheet, "F6") == 'TERKOREKSI' ? $this->getNilaiAkhirSel($worksheet, "F{$row}") : null;

                if (!$parameter) break;

                if($parameter == 'PM ₁₀') $parameter = "PM 10";

                if($parameter == 'PM ₂,₅') $parameter = "PM 2.5";

                if($parameter == 'Hidrogen Sulfida (H₂S)') $parameter = 'Sulfur (H₂S)';

                if($parameter == 'Amoniak (NH₃)') $parameter = 'NH3';

                if($parameter == 'Total Partikulat') $parameter = "TSP";

                if($parameter == 'Timah Hitam (Pb)') $parameter = "Pb";

                if($parameter == 'Hidrokarbon Non Metana (NMHC)') $parameter = "HCNM";
                
                if($parameter == 'Karbon Monoksida (CO)') $parameter = "C O";
                
                if($parameter == 'Karbon Dioksida (CO₂)') $parameter = "CO2";
                
                if($parameter == 'Sulfur Dioksida (SO₂)') $parameter = "SO2";
                
                if($parameter == 'Nitrogen Dioksida (NO₂)') $parameter = "NO2";

                if($parameter == 'Oksidan Fotokimia (Oᵪ) sebagai Ozon (O₃)') $parameter = "O3";
                
                if($parameter == 'Ozon (O₃)') $parameter = "O3";

                if($parameter == 'Benzena (C₆H₆)') $parameter = "Benzene";

                if($parameter == 'Toluena (C₇H₈)') $parameter = "Toluene";

                if($parameter == 'Xylena (C₈H₁₀)') $parameter = "Xylene";

                if($parameter == 'Nitrogen Oksida (NOₓ) sebagai NO₂ + NO') $parameter = "NOx";

                if($parameter == 'Opasitas') $parameter = "Opasitas (Solar)";

                $cekParameter = Parameter::where('nama_regulasi', $parameter)
                ->whereIn('nama_lab', $parameterUji)
                ->where('id_kategori', 4)
                ->first();               
                
                $parameter = $cekParameter->nama_lab ?? $parameter;
                $id_subkontrak = null;

                $cekData = WsValueEmisiCerobong::with(['emisi_cerobong_header', 'emisi_isokinetik'])
                ->where('no_sampel', $noSampel)
                ->where(function ($query) use ($parameter) {
                    $query->whereHas('emisi_cerobong_header', fn($r) => $r->where('parameter', $parameter));
                        // ->orWhereHas('emisi_isokinetik', fn($r) => $r->where('parameter', $parameter));
                })->first();
                if($cekData == null){
                    $subkontrak = new Subkontrak();
                    $subkontrak->category_id = explode('-', $orderDetail->kategori_2)[0];
                    $subkontrak->no_sampel = $noSampel;
                    $subkontrak->parameter = $parameter;
                    $subkontrak->jenis_pengujian = 'sample';
                    $subkontrak->lhps = 0;
                    $subkontrak->is_approve = 1;
                    $subkontrak->created_by = 'System';
                    $subkontrak->created_at = date('Y-m-d H:i:s');
                    $subkontrak->save();

                    $id_subkontrak = $subkontrak->id;
                } 

                $hasilUji = str_replace(',', '.', $hasilUji);
                $hasilTerkoreksi = str_replace(',', '.', $hasilTerkoreksi);
                
                if($hasilTerkoreksi != '' && $hasilTerkoreksi != null && $hasilTerkoreksi != '-') {
                    // dd('masuk atur');
                    if($id_subkontrak == null){
                        $wsValueEmisiCerobong = WsValueEmisiCerobong::where('id', $cekData->id)->first();
                        $wsValueEmisiCerobong->no_sampel = $noSampel;
                        $wsValueEmisiCerobong->C = $hasilUji;
                        $wsValueEmisiCerobong->C1 = $hasilUji;
                        $wsValueEmisiCerobong->C2 = $hasilUji;
                        $wsValueEmisiCerobong->C3 = $hasilUji;
                        $wsValueEmisiCerobong->C4 = $hasilUji;
                        $wsValueEmisiCerobong->C5 = $hasilUji;
                        $wsValueEmisiCerobong->C6 = $hasilUji;
                        $wsValueEmisiCerobong->C7 = $hasilUji;
                        $wsValueEmisiCerobong->C8 = $hasilUji;
                        $wsValueEmisiCerobong->C9 = $hasilUji;
                        $wsValueEmisiCerobong->C10 = $hasilUji;
                        $wsValueEmisiCerobong->f_koreksi_c = $hasilTerkoreksi;
                        $wsValueEmisiCerobong->f_koreksi_c1 = $hasilTerkoreksi;
                        $wsValueEmisiCerobong->f_koreksi_c2 = $hasilTerkoreksi;
                        $wsValueEmisiCerobong->f_koreksi_c3 = $hasilTerkoreksi;
                        $wsValueEmisiCerobong->f_koreksi_c4 = $hasilTerkoreksi;
                        $wsValueEmisiCerobong->f_koreksi_c5 = $hasilTerkoreksi;
                        $wsValueEmisiCerobong->f_koreksi_c6 = $hasilTerkoreksi;
                        $wsValueEmisiCerobong->f_koreksi_c7 = $hasilTerkoreksi;
                        $wsValueEmisiCerobong->f_koreksi_c8 = $hasilTerkoreksi;
                        $wsValueEmisiCerobong->f_koreksi_c9 = $hasilTerkoreksi;
                        $wsValueEmisiCerobong->f_koreksi_c10 = $hasilTerkoreksi;
                        $wsValueEmisiCerobong->created_by = 'System';
                        $wsValueEmisiCerobong->created_at = date('Y-m-d H:i:s');
                        $wsValueEmisiCerobong->save();
                    } else {
                        $wsValueEmisiCerobong = new WsValueEmisiCerobong();
                        $wsValueEmisiCerobong->id_subkontrak = $subkontrak->id;
                        $wsValueEmisiCerobong->tanggal_terima = $orderDetail->tanggal_terima;
                        $wsValueEmisiCerobong->no_sampel = $noSampel;
                        $wsValueEmisiCerobong->C = $hasilUji;
                        $wsValueEmisiCerobong->C1 = $hasilUji;
                        $wsValueEmisiCerobong->C2 = $hasilUji;
                        $wsValueEmisiCerobong->C3 = $hasilUji;
                        $wsValueEmisiCerobong->C4 = $hasilUji;
                        $wsValueEmisiCerobong->C5 = $hasilUji;
                        $wsValueEmisiCerobong->C6 = $hasilUji;
                        $wsValueEmisiCerobong->C7 = $hasilUji;
                        $wsValueEmisiCerobong->C8 = $hasilUji;
                        $wsValueEmisiCerobong->C9 = $hasilUji;
                        $wsValueEmisiCerobong->C10 = $hasilUji;
                        $wsValueEmisiCerobong->f_koreksi_c = $hasilTerkoreksi;
                        $wsValueEmisiCerobong->f_koreksi_c1 = $hasilTerkoreksi;
                        $wsValueEmisiCerobong->f_koreksi_c2 = $hasilTerkoreksi;
                        $wsValueEmisiCerobong->f_koreksi_c3 = $hasilTerkoreksi;
                        $wsValueEmisiCerobong->f_koreksi_c4 = $hasilTerkoreksi;
                        $wsValueEmisiCerobong->f_koreksi_c5 = $hasilTerkoreksi;
                        $wsValueEmisiCerobong->f_koreksi_c6 = $hasilTerkoreksi;
                        $wsValueEmisiCerobong->f_koreksi_c7 = $hasilTerkoreksi;
                        $wsValueEmisiCerobong->f_koreksi_c8 = $hasilTerkoreksi;
                        $wsValueEmisiCerobong->f_koreksi_c9 = $hasilTerkoreksi;
                        $wsValueEmisiCerobong->f_koreksi_c10 = $hasilTerkoreksi;
                        $wsValueEmisiCerobong->created_by = 'System';
                        $wsValueEmisiCerobong->created_at = date('Y-m-d H:i:s');
                        $wsValueEmisiCerobong->save();
                    }
                } else {
                    // dd('masuk sini');
                    if($id_subkontrak == null){
                        $wsValueEmisiCerobong = WsValueEmisiCerobong::where('id', $cekData->id)->first();
                        $wsValueEmisiCerobong->no_sampel = $noSampel;
                        $wsValueEmisiCerobong->C = $hasilUji;
                        $wsValueEmisiCerobong->C1 = $hasilUji;
                        $wsValueEmisiCerobong->C2 = $hasilUji;
                        $wsValueEmisiCerobong->C3 = $hasilUji;
                        $wsValueEmisiCerobong->C4 = $hasilUji;
                        $wsValueEmisiCerobong->C5 = $hasilUji;
                        $wsValueEmisiCerobong->C6 = $hasilUji;
                        $wsValueEmisiCerobong->C7 = $hasilUji;
                        $wsValueEmisiCerobong->C8 = $hasilUji;
                        $wsValueEmisiCerobong->C9 = $hasilUji;
                        $wsValueEmisiCerobong->C10 = $hasilUji;
                        $wsValueEmisiCerobong->created_by = 'System';
                        $wsValueEmisiCerobong->created_at = date('Y-m-d H:i:s');
                        $wsValueEmisiCerobong->save();
                    } else {
                        $wsValueEmisiCerobong = new WsValueEmisiCerobong();
                        $wsValueEmisiCerobong->id_subkontrak = $subkontrak->id;
                        $wsValueEmisiCerobong->tanggal_terima = $orderDetail->tanggal_terima;
                        $wsValueEmisiCerobong->no_sampel = $noSampel;
                        $wsValueEmisiCerobong->C = $hasilUji;
                        $wsValueEmisiCerobong->C1 = $hasilUji;
                        $wsValueEmisiCerobong->C2 = $hasilUji;
                        $wsValueEmisiCerobong->C3 = $hasilUji;
                        $wsValueEmisiCerobong->C4 = $hasilUji;
                        $wsValueEmisiCerobong->C5 = $hasilUji;
                        $wsValueEmisiCerobong->C6 = $hasilUji;
                        $wsValueEmisiCerobong->C7 = $hasilUji;
                        $wsValueEmisiCerobong->C8 = $hasilUji;
                        $wsValueEmisiCerobong->C9 = $hasilUji;
                        $wsValueEmisiCerobong->C10 = $hasilUji;
                        $wsValueEmisiCerobong->created_by = 'System';
                        $wsValueEmisiCerobong->created_at = date('Y-m-d H:i:s');
                        $wsValueEmisiCerobong->save();
                    }
                }
                // dd($noSampel);
                
                $parameters[] = $parameter;
                $idHeader[] = $wsValueEmisiCerobong->id;
            }

            DB::commit();
            return response()->json(['message' => 'Done', 'parameters' => $parameters, 'idHeader' => $idHeader], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            dd($th);
        }
    }
}
