<?php

namespace App\Http\Controllers\external;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

use App\Models\Subkontrak;
use App\Models\OrderDetail;
use App\Models\WsValueEmisiCerobong;
use App\Models\WsValueUdara;

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

            $jenisSampel = $this->getNilaiAkhirSel($worksheet, "T6");
            if ($jenisSampel !== 'Udara Ambient') return response()->json(['message' => 'Ini bener file udara ambient?'], 400);

            $orderDetail = OrderDetail::where('no_sampel', $noSampel)->first();
            if (!$orderDetail) return response()->json(['message' => 'Order Detail ga ada'], 400);

            for ($row = $startRow; $row <= $highestRow; $row += 2) { // += 2 karna dimerge
                $parameter = $this->getNilaiAkhirSel($worksheet, "D{$row}");
                $hasilUji = $this->getNilaiAkhirSel($worksheet, "F{$row}");

                if (!$parameter) break;

                $subkontrak = Subkontrak::where('no_sampel', $noSampel)->where('parameter', $parameter)->exists();
                if ($subkontrak) return response()->json(['message' => 'Subkontrak udah ada'], 400);

                $subkontrak = new Subkontrak();
                $subkontrak->category_id = explode('-', $orderDetail->kategori_2)[0];
                $subkontrak->no_sampel = $noSampel;
                $subkontrak->parameter = $parameter;
                $subkontrak->jenis_pengujian = 'sample';
                $subkontrak->lhps = 0;
                // $subkontrak->is_approve = 1;
                // $subkontrak->approved_by = 'System';
                // $subkontrak->approved_at = date('Y-m-d H:i:s');
                $subkontrak->created_by = 'System';
                $subkontrak->created_at = date('Y-m-d H:i:s');
                $subkontrak->save();

                $hasilUji = str_replace(',', '.', $hasilUji);

                $wsValueUdara = new WsValueUdara();
                $wsValueUdara->id_subkontrak = $subkontrak->id;
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

            DB::commit();
            return response()->json(['message' => 'dah kelar'], 200);
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
            $highestRow = $worksheet->getHighestDataRow($maxCol);

            $noSampel = $this->getNilaiAkhirSel($worksheet, "P6");

            $jenisSampel = $this->getNilaiAkhirSel($worksheet, "S6");
            if ($jenisSampel !== 'Lingkungan Kerja') return response()->json(['message' => 'Ini bener file udara lingkungan kerja?'], 400);

            $orderDetail = OrderDetail::where('no_sampel', $noSampel)->first();
            if (!$orderDetail) return response()->json(['message' => 'Order Detail ga ada'], 400);

            for ($row = $startRow; $row <= $highestRow; $row += 2) { // += 2 karna dimerge
                $parameter = $this->getNilaiAkhirSel($worksheet, "D{$row}");
                $hasilUji = $this->getNilaiAkhirSel($worksheet, "E{$row}");

                if (!$parameter) break;

                $subkontrak = Subkontrak::where('no_sampel', $noSampel)->where('parameter', $parameter)->exists();
                if ($subkontrak) return response()->json(['message' => 'Subkontrak udah ada'], 400);

                $subkontrak = new Subkontrak();
                $subkontrak->category_id = explode('-', $orderDetail->kategori_2)[0];
                $subkontrak->no_sampel = $noSampel;
                $subkontrak->parameter = $parameter;
                $subkontrak->jenis_pengujian = 'sample';
                $subkontrak->lhps = 0;
                // $subkontrak->is_approve = 1;
                // $subkontrak->approved_by = 'System';
                // $subkontrak->approved_at = date('Y-m-d H:i:s');
                $subkontrak->created_by = 'System';
                $subkontrak->created_at = date('Y-m-d H:i:s');
                $subkontrak->save();

                $hasilUji = str_replace(',', '.', $hasilUji);

                $wsValueUdara = new WsValueUdara();
                $wsValueUdara->id_subkontrak = $subkontrak->id;
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

            DB::commit();
            return response()->json(['message' => 'dah kelar'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            dd($th);
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

            $orderDetail = OrderDetail::where('no_sampel', $noSampel)->first();
            if (!$orderDetail) return response()->json(['message' => 'Order Detail ga ada'], 400);

            for ($row = $startRow; $row <= $highestRow; $row += 2) { // += 2 karna dimerge
                $parameter = $this->getNilaiAkhirSel($worksheet, "D{$row}");
                $hasilUjiCell = $this->getNilaiAkhirSel($worksheet, "F6") == 'TERKOREKSI' ? "F{$row}" : "E{$row}"; // emisi genset di f7
                $hasilUji = $this->getNilaiAkhirSel($worksheet, $hasilUjiCell);

                if (!$parameter) break;

                $subkontrak = Subkontrak::where('no_sampel', $noSampel)->where('parameter', $parameter)->exists();
                if ($subkontrak) return response()->json(['message' => 'Subkontrak udah ada'], 400);

                $subkontrak = new Subkontrak();
                $subkontrak->category_id = explode('-', $orderDetail->kategori_2)[0];
                $subkontrak->no_sampel = $noSampel;
                $subkontrak->parameter = $parameter;
                $subkontrak->jenis_pengujian = 'sample';
                $subkontrak->lhps = 0;
                // $subkontrak->is_approve = 1;
                // $subkontrak->approved_by = 'System';
                // $subkontrak->approved_at = date('Y-m-d H:i:s');
                $subkontrak->created_by = 'System';
                $subkontrak->created_at = date('Y-m-d H:i:s');
                $subkontrak->save();

                $hasilUji = str_replace(',', '.', $hasilUji);

                $wsValueEmisiCerobong = new WsValueEmisiCerobong();
                $wsValueEmisiCerobong->id_subkontrak = $subkontrak->id;
                // $wsValueEmisiCerobong->id_po = $orderDetail->id;
                $wsValueEmisiCerobong->tanggal_terima = $orderDetail->tanggal_terima;
                $wsValueEmisiCerobong->no_sampel = $noSampel;
                $wsValueEmisiCerobong->f_koreksi_c = $hasilUji;
                $wsValueEmisiCerobong->f_koreksi_c1 = $hasilUji;
                $wsValueEmisiCerobong->f_koreksi_c2 = $hasilUji;
                $wsValueEmisiCerobong->f_koreksi_c3 = $hasilUji;
                $wsValueEmisiCerobong->f_koreksi_c4 = $hasilUji;
                $wsValueEmisiCerobong->f_koreksi_c5 = $hasilUji;
                $wsValueEmisiCerobong->f_koreksi_c6 = $hasilUji;
                $wsValueEmisiCerobong->f_koreksi_c7 = $hasilUji;
                $wsValueEmisiCerobong->f_koreksi_c8 = $hasilUji;
                $wsValueEmisiCerobong->f_koreksi_c9 = $hasilUji;
                $wsValueEmisiCerobong->f_koreksi_c10 = $hasilUji;
                $wsValueEmisiCerobong->created_by = 'System';
                $wsValueEmisiCerobong->created_at = date('Y-m-d H:i:s');
                $wsValueEmisiCerobong->save();
            }

            DB::commit();
            return response()->json(['message' => 'dah kelar'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            dd($th);
        }
    }
}
