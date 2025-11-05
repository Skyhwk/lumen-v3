<?php

namespace App\Http\Controllers\external;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

use App\Models\Subkontrak;
use App\Models\OrderDetail;
use App\Models\WsValueUdara;

class ImportHasilPengujian extends \Laravel\Lumen\Routing\Controller
{
    public function importAmbient(Request $request)
    {
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

        $noSampel = $worksheet->getCell("Q6")->getOldCalculatedValue() ?? $worksheet->getCell("Q6")->getCalculatedValue() ?? $worksheet->getCell("Q6")->getValue() ?? null;

        $orderDetail = OrderDetail::where('no_sampel', $noSampel)->first();
        if (!$orderDetail) return response()->json(['message' => 'Order Detail ga ada'], 400);

        for ($row = $startRow; $row <= $highestRow; $row += 2) { // += 2 karna dimerge
            $parameter = $worksheet->getCell("D{$row}")->getOldCalculatedValue() ?? $worksheet->getCell("D{$row}")->getCalculatedValue() ?? $worksheet->getCell("D{$row}")->getValue() ?? null;
            $durasi = $worksheet->getCell("E{$row}")->getOldCalculatedValue() ?? $worksheet->getCell("E{$row}")->getCalculatedValue() ?? $worksheet->getCell("E{$row}")->getValue() ?? null;
            $hasilUji = $worksheet->getCell("F{$row}")->getOldCalculatedValue() ?? $worksheet->getCell("F{$row}")->getCalculatedValue() ?? $worksheet->getCell("F{$row}")->getValue() ?? null;
            $bakuMutu = $worksheet->getCell("G{$row}")->getOldCalculatedValue() ?? $worksheet->getCell("G{$row}")->getCalculatedValue() ?? $worksheet->getCell("G{$row}")->getValue() ?? null;
            $satuan = $worksheet->getCell("H{$row}")->getOldCalculatedValue() ?? $worksheet->getCell("H{$row}")->getCalculatedValue() ?? $worksheet->getCell("H{$row}")->getValue() ?? null;
            $spesifikasiMetode = $worksheet->getCell("J{$row}")->getOldCalculatedValue() ?? $worksheet->getCell("J{$row}")->getCalculatedValue() ?? $worksheet->getCell("J{$row}")->getValue() ?? null;

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
    }
}
