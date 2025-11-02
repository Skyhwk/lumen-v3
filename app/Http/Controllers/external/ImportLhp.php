<?php

namespace App\Http\Controllers\external;

use App\Models\LhpsEmisiCHeader;
use App\Models\LhpsEmisiHeader;
use App\Models\LhpsKebisinganHeader;
use App\Models\LhpsLingHeader;
use App\Models\Parameter;
use App\Models\OrderDetail;
use App\Services\GenerateQrDocumentLhp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Lumen\Routing\Controller as BaseController;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Mpdf;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use Exception;

class ImportLhp extends BaseController
{
    private function findHeaderPositions(array $results, string $mode)
    {
        if ($mode == 'air') {
            $headers = [
                'PARAMETER',
                'BAKUMUTU',
                'SPESIFIKASIMETODE',
                'NOLHP',
                'NOSAMPEL',
                'JENISSAMPEL',
                'SATUAN',
                'HASILUJI'
            ];
        } else if ($mode == 'udara') {
            $headers = [
                'PARAMETER',
                'BAKUMUTU',
                'SPESIFIKASIMETODE',
                'NOLHP',
                'NOSAMPEL',
                'JENISSAMPEL',
                'DURASI',
                'SATUAN',
                'HASILUJI'
            ];
        }

        $positions = [];

        foreach ($headers as $header) {
            $position = $this->searchArray($header, $results);
            if ($position !== null) {
                $coords = explode(',', $position);
                $positions[$header] = [
                    'row' => (int) $coords[0],
                    'col' => (int) $coords[1]
                ];
            }
        }

        return $positions;
    }

    private function filterArray($cell, $header)
    {
        if (!is_array($cell)) {
            return [];
        }

        $result = array_filter($cell, function ($item) use ($header) {
            return preg_match('/\b' . preg_quote($header, '/') . '\b/', strtoupper($item));
        });

        return $result;
    }

    private function number_to_alphabet($number)
    {
        $number = intval($number);
        if ($number <= 0) {
            return '';
        }
        $alphabet = '';
        while ($number != 0) {
            $p = ($number - 1) % 26;
            $number = intval(($number - $p) / 26);
            $alphabet = chr(65 + $p) . $alphabet;
        }
        return $alphabet;
    }

    private function alphabet_to_number($string)
    {
        $string = strtoupper($string);
        $length = strlen($string);
        $number = 0;
        $level = 1;

        while ($length >= $level) {
            $char = $string[$length - $level];
            $c = ord($char) - 64;
            $number += $c * (26 ** ($level - 1));
            $level++;
        }

        return $number;
    }

    private function searchArray(string $search_value = '', array $array = [])
    {
        foreach ($array as $key1 => $val1) {
            $temp_path = [];
            array_push($temp_path, $key1);
            if (is_array($val1) && count($val1) > 0) {
                foreach ($val1 as $key2 => $val2) {
                    if (strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', $val2)) == $search_value) {
                        array_push($temp_path, $key2);
                        return join(",", $temp_path);
                    }
                }
            } elseif ($val1 == $search_value) {
                return join(",", $temp_path);
            }
        }
        return null;
    }

    public function indexAir(Request $request)
    {
        DB::beginTransaction();
        try {
            // Validasi file upload
            if (!$request->hasFile('file')) {
                return response()->json([
                    'message' => 'File tidak ditemukan',
                ], 400);
            }

            $file = $request->file('file');

            // Validasi jenis file
            if (!in_array($file->getClientOriginalExtension(), ['xlsx', 'xls'])) {
                return response()->json([
                    'message' => 'Format file tidak didukung. Gunakan format Excel (.xlsx atau .xls)',
                ], 400);
            }

            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load($file->getRealPath());

            // Cek apakah sheet 'LHP' ada
            if (!$spreadsheet->sheetNameExists('LHP')) {
                return response()->json([
                    'message' => 'Sheet LHP tidak ditemukan dalam file Excel',
                ], 400);
            }

            $worksheet = $spreadsheet->getSheetByName('LHP');

            /**
             * LHPS AIR HEADER
             */
            $results = [];
            foreach ($worksheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(FALSE);
                $row_content = [];
                foreach ($cellIterator as $cel) {
                    try {
                        $value = $cel->getOldCalculatedValue()
                            ?? $cel->getCalculatedValue()
                            ?? $cel->getValue();

                        if ($value === false || $value === '') {
                            $value = $cel->getValue();
                        }
                    } catch (\Throwable $e) {
                        $value = $cel->getOldCalculatedValue()
                            ?? $cel->getValue();

                        if ($value === false || $value === '') {
                            $value = $cel->getValue();
                        }
                    }
                    array_push($row_content, $value);
                }
                $results[] = $row_content;
            }

            // Cari posisi header-header yang diperlukan
            $headerPositions = $this->findHeaderPositions($results, 'air');

            // Validasi header wajib
            $requiredHeaders = ['PARAMETER', 'SPESIFIKASIMETODE', 'NOLHP', 'NOSAMPEL', 'JENISSAMPEL'];
            foreach ($requiredHeaders as $header) {
                if (empty($headerPositions[$header])) {
                    return response()->json([
                        'message' => "Header $header tidak ditemukan dalam file Excel",
                    ], 400);
                }
            }

            $highestRow = $worksheet->getHighestRow();

            // Ambil posisi parameter
            $parameterRow = $headerPositions['PARAMETER']['row'];
            $parameterCol = $headerPositions['PARAMETER']['col'];
            $parameterStartRow = $parameterRow + 3; // Data dimulai 2 baris setelah header

            // Ambil posisi spesifikasi metode
            $spesifikasiRow = $headerPositions['SPESIFIKASIMETODE']['row'];
            $spesifikasiCol = $headerPositions['SPESIFIKASIMETODE']['col'];

            // Tentukan range kolom untuk data parameter
            $kolomPar = $this->number_to_alphabet($parameterCol + 1);
            $kolomSpe = $this->number_to_alphabet($spesifikasiCol + 1);

            // Cek apakah ada data di kolom spesifikasi pada baris pertama data
            $cellValue = $worksheet->getCell($kolomSpe . ($spesifikasiRow + 2))->getOldCalculatedValue()
                ?? $worksheet->getCell($kolomSpe . ($spesifikasiRow + 2))->getCalculatedValue()
                ?? $worksheet->getCell($kolomSpe . ($spesifikasiRow + 2))->getValue();
            if ($cellValue === null || $cellValue === '') {
                // Jika kosong, extend satu kolom ke kanan
                $kolomSpe = $this->number_to_alphabet($spesifikasiCol + 2);
            }

            // Ambil data parameter
            $getHeader = $worksheet->rangeToArray($kolomPar . $parameterStartRow . ':' . $kolomSpe . $highestRow);

            // Proses header baku mutu
            $headTable = NULL;
            if (!empty($headerPositions['BAKUMUTU'])) {
                $bakuRow = $headerPositions['BAKUMUTU']['row'];
                $bakuCol = $headerPositions['BAKUMUTU']['col'];
                $kolomBak = $this->number_to_alphabet($bakuCol + 1);
                $barisMutu = $bakuRow + 3; // Data baku mutu biasanya 2 baris setelah header

                $cellValue = $worksheet->getCell($kolomBak . $barisMutu)->getOldCalculatedValue()
                    ?? $worksheet->getCell($kolomBak . $barisMutu)->getCalculatedValue()
                    ?? $worksheet->getCell($kolomBak . $barisMutu)->getValue();
                if ($cellValue !== null && $cellValue !== '') {
                    $getBaku = $this->filterArray($worksheet->getMergeCells(), $kolomBak . ($bakuRow + 1));
                    $v = [];
                    foreach ($getBaku as $vv) {
                        $explode = explode(':', $vv);
                        if (count($explode) >= 2) {
                            $startCell = $explode[0];
                            $endCell = $explode[1];

                            if (strlen($startCell) >= 2) {
                                $split = str_split($startCell, 1);
                                $split1 = str_split($endCell, 1);
                                $cov = intval($split[1]);
                                $cov1 = intval($split1[1]);
                                $rangeData = $worksheet->rangeToArray($split[0] . $cov . ':' . $split1[0] . $cov1);
                                if (!empty($rangeData)) {
                                    $v = array_merge($v, $rangeData);
                                    $v = reset($rangeData);
                                }
                            }
                        }
                    }
                    $headTable = json_encode($v);
                }
            }

            // Proses parameter
            $param = [];
            foreach ($getHeader as $valhead) {
                //cek nama parameter di excel kosong atau tidak
                if (isset($valhead[1]) && $valhead[1] !== null && $valhead[1] !== "" && !empty($valhead[1])) {
                    //cek method di excel kosong atau tidak
                    $lastValue = end($valhead);
                    if ($lastValue == '-' || $lastValue === null) {
                        $method = 'nama_regulasi = ? AND method IS NULL';
                        $nameParam = Parameter::select('nama_lab')
                            ->where('id_kategori', 1)
                            ->whereRaw($method, [$valhead[1]])
                            ->first();
                    } else {
                        $nameParam = Parameter::select('nama_lab')
                            ->where('id_kategori', 1)
                            ->where(function ($query) use ($valhead, $lastValue) {
                                $query->where('nama_regulasi', $valhead[1])
                                    ->orWhere('method', $lastValue);
                            })
                            ->first();
                    }
                    if ($nameParam == NULL) {
                        array_push($param, $valhead[1]);
                    } else {
                        array_push($param, $nameParam->nama_lab);
                    }
                }
            }

            // Ambil data LHP, Sample, dan Jenis Sample
            $lhpRow = $headerPositions['NOLHP']['row'];
            $lhpCol = $headerPositions['NOLHP']['col'];
            $kolomLhp = $this->number_to_alphabet($lhpCol + 1);
            $barisLhp = $lhpRow + 2; // Data biasanya 1 baris setelah header

            $sampleRow = $headerPositions['NOSAMPEL']['row'];
            $sampleCol = $headerPositions['NOSAMPEL']['col'];
            $kolomSam = $this->number_to_alphabet($sampleCol + 1);
            $barisSam = $sampleRow + 2;

            $jenisRow = $headerPositions['JENISSAMPEL']['row'];
            $jenisCol = $headerPositions['JENISSAMPEL']['col'];
            $kolomJen = $this->number_to_alphabet($jenisCol + 1);
            $barisJen = $jenisRow + 2;

            $noLhp = $worksheet->getCell($kolomLhp . $barisLhp)->getOldCalculatedValue()
                ?? $worksheet->getCell($kolomLhp . $barisLhp)->getCalculatedValue()
                ?? $worksheet->getCell($kolomLhp . $barisLhp)->getValue();
            $noSample = $worksheet->getCell($kolomSam . $barisSam)->getOldCalculatedValue()
                ?? $worksheet->getCell($kolomSam . $barisSam)->getCalculatedValue()
                ?? $worksheet->getCell($kolomSam . $barisSam)->getValue();
            $jenisSample = $worksheet->getCell($kolomJen . $barisJen)->getOldCalculatedValue()
                ?? $worksheet->getCell($kolomJen . $barisJen)->getCalculatedValue()
                ?? $worksheet->getCell($kolomJen . $barisJen)->getValue();

            if (empty($noLhp)) {
                return response()->json([
                    'message' => 'No LHP tidak ditemukan atau kosong',
                ], 400);
            }

            $noOrder = explode('/', $noLhp);

            if (count($noOrder) > 2) {
                $noLhp = implode('/', array_slice($noOrder, 0, 2));
            }

            //get detail informasi pelanggan
            $pelanggan = OrderDetail::where('cfr', str_replace(' ', '', $noLhp))
                ->where('no_sampel', str_replace(' ', '', $noSample))
                ->first();

            $insert = [];
            if ($pelanggan == null) {
                $insert = [
                    'no_order' => str_replace(' ', '', $noOrder[0]),
                    'no_lhp' => str_replace(' ', '', $noLhp),
                    'no_sampel' => $noSample,
                    'parameter_uji' => json_encode($param),
                    'sub_kategori' => $jenisSample,
                    'header_table' => $headTable,
                ];
            } else {
                $insert = [
                    'no_order' => str_replace(' ', '', $noOrder[0]),
                    'no_lhp' => str_replace(' ', '', $noLhp),
                    'no_sampel' => $noSample,
                    'parameter_uji' => json_encode($param),
                    'sub_kategori' => $jenisSample,
                    'nama_pelanggan' => $pelanggan->nama_perusahaan,
                    'deskripsi_titik' => $pelanggan->keterangan_1,
                    'tanggal_sampling' => $pelanggan->tanggal_sampling,
                    'header_table' => $headTable,
                ];
            }

            if (DB::table('lhps_air_header')->where('no_lhp', $noLhp)->exists()) {
                return response()->json([
                    'message' => 'No LHP sudah ada',
                ], 400);
            }

            $header = DB::table('lhps_air_header')->insertGetId($insert);
            // dump(DB::table('lhps_air_header')->find($header));

            /**
             * LHPS AIR DETAIL
             */
            $no = 0;
            foreach ($getHeader as $value) {
                //cek nama parameter di excel kosong atau tidak
                if (isset($value[1]) && $value[1] !== null && $value[1] !== "" && !empty($value[1])) {
                    //cek method di excel kosong atau tidak
                    $lastValue = end($value);
                    if ($lastValue == '-' || $lastValue === null) {
                        $method = 'nama_regulasi = ? AND method IS NULL';
                        $nameParam = Parameter::select('nama_lab')
                            ->where('id_kategori', 1)
                            ->whereRaw($method, [$value[1]])
                            ->first();
                    } else {
                        $nameParam = Parameter::select('nama_lab')
                            ->where('id_kategori', 1)
                            ->where(function ($query) use ($value, $lastValue) {
                                $query->where('nama_regulasi', $value[1])
                                    ->orWhere('method', $lastValue);
                            })
                            ->first();
                    }

                    $parameter_lab = ($nameParam == NULL) ? NULL : $nameParam->nama_lab;

                    // Initialize val array
                    $val = [];

                    // Proses baku mutu untuk detail
                    if (!empty($headerPositions['BAKUMUTU'])) {
                        $bakuRow = $headerPositions['BAKUMUTU']['row'];
                        $bakuCol = $headerPositions['BAKUMUTU']['col'];
                        $kolomBak = $this->number_to_alphabet($bakuCol + 1);
                        $barisMutu = $bakuRow + 3; // Data baku mutu biasanya 2 baris setelah header

                        $cellValue = $worksheet->getCell($kolomBak . $barisMutu)->getOldCalculatedValue()
                            ?? $worksheet->getCell($kolomBak . $barisMutu)->getCalculatedValue()
                            ?? $worksheet->getCell($kolomBak . $barisMutu)->getValue();

                        if ($cellValue !== null && $cellValue !== '') {
                            $getBaku = $this->filterArray($worksheet->getMergeCells(), $kolomBak . ($bakuRow + 1));
                            // get baku mutu untuk lhps detail
                            foreach ($getBaku as $values) {
                                $explode = explode(':', $values);
                                if (count($explode) >= 2) {
                                    $startCell = $explode[0];
                                    $endCell = $explode[1];

                                    if (strlen($startCell) >= 2) {
                                        $split = str_split($startCell, 1);
                                        $split1 = str_split($endCell, 1);
                                        $cov = intval($split[1]) + 1 + $no; // Sesuaikan dengan baris data
                                        $cov1 = intval($split1[1]) + 1 + $no;
                                        $rangeData = $worksheet->rangeToArray($split[0] . $cov . ':' . $split1[0] . $cov1);
                                        if (!empty($rangeData)) {
                                            foreach ($rangeData as $k => $vals) {
                                                $val = $vals;
                                            }
                                        }
                                    }
                                }
                            }
                        } else {
                            $kolomHas = $this->number_to_alphabet($bakuCol + 1);
                            $barisHas = $bakuRow + 2 + $no; // Sesuaikan dengan nomor urut data
                            $cellValue = $worksheet->getCell($kolomHas . $barisHas)->getOldCalculatedValue()
                                ?? $worksheet->getCell($kolomHas . $barisHas)->getCalculatedValue()
                                ?? $worksheet->getCell($kolomHas . $barisHas)->getValue();
                            $val = [$cellValue];
                        }
                    }
                    // get satuan detail
                    $satuan = null;
                    if (!empty($headerPositions['SATUAN'])) {
                        $satuanRow = $headerPositions['SATUAN']['row'];
                        $satuanCol = $headerPositions['SATUAN']['col'];
                        $kolomSat = $this->number_to_alphabet($satuanCol + 1);
                        $barisSat = $satuanRow + 3 + $no; // Sesuaikan dengan nomor urut data
                        $satuan = $worksheet->getCell($kolomSat . $barisSat)->getOldCalculatedValue()
                            ?? $worksheet->getCell($kolomSat . $barisSat)->getCalculatedValue()
                            ?? $worksheet->getCell($kolomSat . $barisSat)->getValue();
                    }

                    //get hasil uji untuk lhps detail
                    $hasilUji = null;
                    if (!empty($headerPositions['HASILUJI'])) {
                        $hasilRow = $headerPositions['HASILUJI']['row'];
                        $hasilCol = $headerPositions['HASILUJI']['col'];
                        $kolomHas = $this->number_to_alphabet($hasilCol + 1);
                        $barisHas = $hasilRow + 3 + $no; // Sesuaikan dengan nomor urut data
                        $hasilUji = $worksheet->getCell($kolomHas . $barisHas)->getOldCalculatedValue()
                            ?? $worksheet->getCell($kolomHas . $barisHas)->getCalculatedValue()
                            ?? $worksheet->getCell($kolomHas . $barisHas)->getValue();
                    }

                    $insert = [
                        'id_header' => $header,
                        'akr' => isset($value[0]) ? $value[0] : null,
                        'parameter_lab' => $parameter_lab,
                        'parameter' => $value[1],
                        'hasil_uji' => $hasilUji,
                        'baku_mutu' => json_encode($val),
                        'satuan' => $satuan,
                        'methode' => $lastValue !== '-' ? str_replace('\\', '', $lastValue) : null,
                    ];

                    $detail = DB::table('lhps_air_detail')->insert($insert);
                }
                $no++;
            }
            // dump(DB::table('lhps_air_detail')->where('id_header', $header)->get());
            DB::commit();
            return response()->json([
                'message' => 'Data LHP Air berhasil diimport',
                'header_id' => $header
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            // Log error untuk debugging
            \Log::error('Import LHP Air Error: ' . $e->getMessage());
            \Log::error('Import LHP Air Trace: ' . $e->getTraceAsString());

            return response()->json([
                'message' => 'Terjadi kesalahan saat mengimport data',
                'error' => $e->getMessage() . ', Line : ' . $e->getLine()

            ], 500);
        }
    }

    public function indexUdara(Request $request)
    {
        DB::beginTransaction();
        try {
            // Validasi file upload
            if (!$request->hasFile('file')) {
                return response()->json([
                    'message' => 'File tidak ditemukan',
                ], 400);
            }

            $file = $request->file('file');

            // Validasi jenis file
            if (!in_array($file->getClientOriginalExtension(), ['xlsx', 'xls'])) {
                return response()->json([
                    'message' => 'Format file tidak didukung. Gunakan format Excel (.xlsx atau .xls)',
                ], 400);
            }

            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load($file->getRealPath());

            // Cek apakah sheet 'LHP' ada
            if (!$spreadsheet->sheetNameExists('LHP')) {
                return response()->json([
                    'message' => 'Sheet LHP tidak ditemukan dalam file Excel',
                ], 400);
            }

            $worksheet = $spreadsheet->getSheetByName('LHP');

            /**
             * LHPS UDARA HEADER
             */
            $results = [];
            foreach ($worksheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(FALSE);
                $row_content = [];
                foreach ($cellIterator as $cel) {
                    try {
                        $value = $cel->getOldCalculatedValue()
                            ?? $cel->getCalculatedValue()
                            ?? $cel->getValue();

                        if ($value === false || $value === '') {
                            $value = $cel->getValue();
                        }
                    } catch (\Throwable $e) {
                        $value = $cel->getOldCalculatedValue()
                            ?? $cel->getValue();

                        if ($value === false || $value === '') {
                            $value = $cel->getValue();
                        }
                    }
                    array_push($row_content, $value);
                }
                $results[] = $row_content;
            }
            // Cari posisi header-header yang diperlukan
            $headerPositions = $this->findHeaderPositions($results, 'udara');

            // Validasi header wajib
            $requiredHeaders = ['PARAMETER', 'SPESIFIKASIMETODE', 'NOLHP', 'NOSAMPEL', 'JENISSAMPEL'];
            foreach ($requiredHeaders as $header) {
                if (empty($headerPositions[$header])) {
                    return response()->json([
                        'message' => "Header $header tidak ditemukan dalam file Excel",
                    ], 400);
                }
            }

            $highestRow = $worksheet->getHighestRow();

            // Ambil posisi parameter
            $parameterRow = $headerPositions['PARAMETER']['row'];
            $parameterCol = $headerPositions['PARAMETER']['col'];
            $parameterStartRow = $parameterRow + 3; // Data dimulai 2 baris setelah header

            // Ambil posisi spesifikasi metode
            $spesifikasiRow = $headerPositions['SPESIFIKASIMETODE']['row'];
            $spesifikasiCol = $headerPositions['SPESIFIKASIMETODE']['col'];

            // Tentukan range kolom untuk data parameter
            $kolomPar = $this->number_to_alphabet($parameterCol + 1);
            $kolomSpe = $this->number_to_alphabet($spesifikasiCol + 1);

            // Cek apakah ada data di kolom spesifikasi pada baris pertama data
            $cellValue = $worksheet->getCell($kolomSpe . ($spesifikasiRow + 2))->getOldCalculatedValue()
                ?? $worksheet->getCell($kolomSpe . ($spesifikasiRow + 2))->getCalculatedValue()
                ?? $worksheet->getCell($kolomSpe . ($spesifikasiRow + 2))->getValue();
            if ($cellValue === null || $cellValue === '') {
                // Jika kosong, extend satu kolom ke kanan
                $kolomSpe = $this->number_to_alphabet($spesifikasiCol + 2);
            }

            // Ambil data parameter
            $getHeader = $worksheet->rangeToArray($kolomPar . $parameterStartRow . ':' . $kolomSpe . $highestRow);
            // Proses header baku mutu
            $headTable = NULL;
            if (!empty($headerPositions['BAKUMUTU'])) {
                $bakuRow = $headerPositions['BAKUMUTU']['row'];
                $bakuCol = $headerPositions['BAKUMUTU']['col'];
                $kolomBak = $this->number_to_alphabet($bakuCol + 1);
                $barisMutu = $bakuRow + 3; // Data baku mutu biasanya 2 baris setelah header

                $cellValue = $worksheet->getCell($kolomBak . $barisMutu)->getOldCalculatedValue()
                    ?? $worksheet->getCell($kolomBak . $barisMutu)->getCalculatedValue()
                    ?? $worksheet->getCell($kolomBak . $barisMutu)->getValue();
                if ($cellValue !== null && $cellValue !== '') {
                    $getBaku = $this->filterArray($worksheet->getMergeCells(), $kolomBak . ($bakuRow + 1));
                    $v = [];
                    foreach ($getBaku as $vv) {
                        $explode = explode(':', $vv);
                        if (count($explode) >= 2) {
                            $startCell = $explode[0];
                            $endCell = $explode[1];

                            if (strlen($startCell) >= 2) {
                                $split = str_split($startCell, 1);
                                $split1 = str_split($endCell, 1);
                                $cov = intval($split[1]);
                                $cov1 = intval($split1[1]);
                                $rangeData = $worksheet->rangeToArray($split[0] . $cov . ':' . $split1[0] . $cov1);
                                if (!empty($rangeData)) {
                                    $v = array_merge($v, $rangeData);
                                    $v = reset($rangeData);
                                }
                            }
                        }
                    }
                    $headTable = json_encode($v);
                }
            }
            // Proses parameter
            $param = [];
            foreach ($getHeader as $valhead) {
                //cek nama parameter di excel kosong atau tidak
                if (isset($valhead[1]) && $valhead[1] !== null && $valhead[1] !== "" && !empty($valhead[1])) {
                    //cek method di excel kosong atau tidak
                    $lastValue = end($valhead);
                    if ($lastValue == '-' || $lastValue === null) {
                        $method = 'nama_regulasi = ? AND method IS NULL';
                        $nameParam = Parameter::select('nama_lab')
                            ->where('id_kategori', 4) // Kategori udara
                            ->whereRaw($method, [$valhead[1]])
                            ->first();
                    } else {
                        $nameParam = Parameter::select('nama_lab')
                            ->where('id_kategori', 4) // Kategori udara
                            ->where(function ($query) use ($valhead, $lastValue) {
                                $query->where('nama_regulasi', $valhead[1])
                                    ->orWhere('method', $lastValue);
                            })
                            ->first();
                    }
                    if ($nameParam == NULL) {
                        array_push($param, $valhead[1]);
                    } else {
                        array_push($param, $nameParam->nama_lab);
                    }
                }
            }

            // Ambil data LHP, Sample, dan Jenis Sample
            $lhpRow = $headerPositions['NOLHP']['row'];
            $lhpCol = $headerPositions['NOLHP']['col'];
            $kolomLhp = $this->number_to_alphabet($lhpCol + 1);
            $barisLhp = $lhpRow + 2; // Data biasanya 1 baris setelah header

            $sampleRow = $headerPositions['NOSAMPEL']['row'];
            $sampleCol = $headerPositions['NOSAMPEL']['col'];
            $kolomSam = $this->number_to_alphabet($sampleCol + 1);
            $barisSam = $sampleRow + 2;

            $jenisRow = $headerPositions['JENISSAMPEL']['row'];
            $jenisCol = $headerPositions['JENISSAMPEL']['col'];
            $kolomJen = $this->number_to_alphabet($jenisCol + 1);
            $barisJen = $jenisRow + 2;

            $noLhp = $worksheet->getCell($kolomLhp . $barisLhp)->getOldCalculatedValue()
                ?? $worksheet->getCell($kolomLhp . $barisLhp)->getCalculatedValue()
                ?? $worksheet->getCell($kolomLhp . $barisLhp)->getValue();
            $noSample = $worksheet->getCell($kolomSam . $barisSam)->getOldCalculatedValue()
                ?? $worksheet->getCell($kolomSam . $barisSam)->getCalculatedValue()
                ?? $worksheet->getCell($kolomSam . $barisSam)->getValue();
            $jenisSample = $worksheet->getCell($kolomJen . $barisJen)->getOldCalculatedValue()
                ?? $worksheet->getCell($kolomJen . $barisJen)->getCalculatedValue()
                ?? $worksheet->getCell($kolomJen . $barisJen)->getValue();

            if (empty($noLhp)) {
                return response()->json([
                    'message' => 'No LHP tidak ditemukan atau kosong',
                ], 400);
            }

            $noOrder = explode('/', $noLhp);

            if (count($noOrder) > 2) {
                $noLhp = implode('/', array_slice($noOrder, 0, 2));
            }

            //get detail informasi pelanggan
            $pelanggan = OrderDetail::with('orderHeader')->where('cfr', str_replace(' ', '', $noLhp))
                ->where('no_sampel', str_replace(' ', '', $noSample))
                ->first();

            $insert = [];
            if ($pelanggan == null) {
                $insert = [
                    'no_order' => str_replace(' ', '', $noOrder[0]),
                    'no_lhp' => str_replace(' ', '', $noLhp),
                    'no_sampel' => $noSample,
                    'parameter_uji' => json_encode($param),
                    'sub_kategori' => $jenisSample,
                    'header_table' => $headTable,
                ];
            } else {
                $insert = [
                    'no_order' => str_replace(' ', '', $noOrder[0]),
                    'no_lhp' => str_replace(' ', '', $noLhp),
                    'no_sampel' => $noSample,
                    'parameter_uji' => json_encode($param),
                    'sub_kategori' => $jenisSample,
                    'nama_pelanggan' => $pelanggan->nama_perusahaan,
                    'deskripsi_titik' => $pelanggan->keterangan_1,
                    'tanggal_sampling' => $pelanggan->tanggal_sampling,
                    'header_table' => $headTable,
                    'no_qt' => $pelanggan->orderHeader->no_document,
                    'status_sampling' => $pelanggan->no_quotation,
                    'alamat_sampling' => $pelanggan->orderHeader->alamat_sampling,
                    'id_kategori_2' => explode('-', $pelanggan->kategori_2)[0],
                    'id_kategori_3' => explode('-', $pelanggan->kategori_3)[0],
                    // 'methode_sampling' => "", // gtau drmna
                    'tanggal_terima' => $pelanggan->tanggal_terima,
                    // 'periode_analisa' =>
                    'regulasi' => $pelanggan->regulasi,
                    // 'regulasi_custom' =>
                    // 'keterangan' =>
                    // 'suhu' =>
                    // 'cuaca' =>
                    // 'arah_angin' =>
                    // 'kelembapan' =>
                    // 'kec_angin' =>
                    // 'titik_koordinat' =>
                    'nama_karyawan' => 'Abidah Walfathiyyah',
                    'jabatan_karyawan' => 'Technical Control Supervisor',
                    // 'file_qr' => ''
                    // 'file_lhp' =>
                    // 'tanggal_lhp' =>
                    // 'created_by' =>
                    // 'created_at' =>
                    // 'updated_by' =>
                    // 'updated_at' =>
                    // 'approved_by' =>
                    // 'approved_at' =>
                    // 'rejected_by' =>
                    // 'rejected_at' =>
                    // 'deleted_by' =>
                    // 'deleted_at' =>
                    // 'is_generated' =>
                    // 'generated_by' =>
                    // 'generated_at' =>
                    // 'is_emailed' =>
                    // 'emailed_by' =>
                    // 'emailed_at' =>
                    // 'id_token' =>
                    // 'expired' =>
                ];
            }
            // dd($insert);
            if (DB::table('lhps_ling_header')->where('no_lhp', $noLhp)->exists()) {
                return response()->json([
                    'message' => 'No LHP sudah ada',
                ], 400);
            }

            $header = DB::table('lhps_ling_header')->insertGetId($insert);

            $lhpsLingHeader = LhpsLingHeader::find($header);
            $file_qr = new GenerateQrDocumentLhp();
            if ($path = $file_qr->insert('LHP_UDARA', $lhpsLingHeader, 'Abidah Walfathiyyah')) {
                $lhpsLingHeader->file_qr = $path;
                $lhpsLingHeader->save();
            }

            // dump(DB::table('lhps_ling_header')->find($header));

            /**
             * LHPS UDARA DETAIL
             */
            $no = 0;
            foreach ($getHeader as $value) {
                //cek nama parameter di excel kosong atau tidak
                if (isset($value[1]) && $value[1] !== null && $value[1] !== "" && !empty($value[1])) {
                    //cek method di excel kosong atau tidak
                    $lastValue = end($value);
                    if ($lastValue == '-' || $lastValue === null) {
                        $method = 'nama_regulasi = ? AND method IS NULL';
                        $nameParam = Parameter::select('nama_lab')
                            ->where('id_kategori', 4) // Kategori udara
                            ->whereRaw($method, [$value[1]])
                            ->first();
                    } else {
                        $nameParam = Parameter::select('nama_lab')
                            ->where('id_kategori', 4) // Kategori udara
                            ->where(function ($query) use ($value, $lastValue) {
                                $query->where('nama_regulasi', $value[1])
                                    ->orWhere('method', $lastValue);
                            })
                            ->first();
                    }

                    $parameter_lab = ($nameParam == NULL) ? NULL : $nameParam->nama_lab;

                    $val = [];
                    if (!empty($headerPositions['BAKUMUTU'])) {
                        $bakuRow = $headerPositions['BAKUMUTU']['row'];
                        $bakuCol = $headerPositions['BAKUMUTU']['col'];
                        $kolomBak = $this->number_to_alphabet($bakuCol + 1);
                        $barisMutu = $bakuRow + 3; // Data baku mutu biasanya 2 baris setelah header

                        $cellValue = $worksheet->getCell($kolomBak . $barisMutu)->getOldCalculatedValue()
                            ?? $worksheet->getCell($kolomBak . $barisMutu)->getCalculatedValue()
                            ?? $worksheet->getCell($kolomBak . $barisMutu)->getValue();

                        if ($cellValue !== null && $cellValue !== '') {
                            $getBaku = $this->filterArray($worksheet->getMergeCells(), $kolomBak . ($bakuRow + 1));
                            // get baku mutu untuk lhps detail
                            foreach ($getBaku as $values) {
                                $explode = explode(':', $values);
                                if (count($explode) >= 2) {
                                    $startCell = $explode[0];
                                    $endCell = $explode[1];

                                    if (strlen($startCell) >= 2) {
                                        $split = str_split($startCell, 1);
                                        $split1 = str_split($endCell, 1);
                                        $cov = intval($split[1]) + 1 + $no; // Sesuaikan dengan baris data
                                        $cov1 = intval($split1[1]) + 1 + $no;
                                        $rangeData = $worksheet->rangeToArray($split[0] . $cov . ':' . $split1[0] . $cov1);
                                        if (!empty($rangeData)) {
                                            foreach ($rangeData as $k => $vals) {
                                                $val = $vals;
                                            }
                                        }
                                    }
                                }
                            }
                        } else {
                            $kolomHas = $this->number_to_alphabet($bakuCol + 1);
                            $barisHas = $bakuRow + 2 + $no; // Sesuaikan dengan nomor urut data
                            $cellValue = $worksheet->getCell($kolomHas . $barisHas)->getOldCalculatedValue()
                                ?? $worksheet->getCell($kolomHas . $barisHas)->getCalculatedValue()
                                ?? $worksheet->getCell($kolomHas . $barisHas)->getValue();
                            $val = [$cellValue];
                        }
                    }

                    // get durasi detail (khusus untuk udara)
                    $durasi = null;
                    if (!empty($headerPositions['DURASI'])) {
                        $durasiRow = $headerPositions['DURASI']['row'];
                        $durasiCol = $headerPositions['DURASI']['col'];
                        $kolomDur = $this->number_to_alphabet($durasiCol + 1);
                        $barisDur = $durasiRow + 3 + $no; // Sesuaikan dengan nomor urut data
                        $durasi = $worksheet->getCell($kolomDur . $barisDur)->getOldCalculatedValue()
                            ?? $worksheet->getCell($kolomDur . $barisDur)->getCalculatedValue()
                            ?? $worksheet->getCell($kolomDur . $barisDur)->getValue();
                    }

                    // get satuan detail
                    $satuan = null;
                    if (!empty($headerPositions['SATUAN'])) {
                        $satuanRow = $headerPositions['SATUAN']['row'];
                        $satuanCol = $headerPositions['SATUAN']['col'];
                        $kolomSat = $this->number_to_alphabet($satuanCol + 1);
                        $barisSat = $satuanRow + 3 + $no; // Sesuaikan dengan nomor urut data
                        $satuan = $worksheet->getCell($kolomSat . $barisSat)->getOldCalculatedValue()
                            ?? $worksheet->getCell($kolomSat . $barisSat)->getCalculatedValue()
                            ?? $worksheet->getCell($kolomSat . $barisSat)->getValue();
                    }

                    //get hasil uji untuk lhps detail
                    $hasilUji = null;
                    if (!empty($headerPositions['HASILUJI'])) {
                        $hasilRow = $headerPositions['HASILUJI']['row'];
                        $hasilCol = $headerPositions['HASILUJI']['col'];
                        $kolomHas = $this->number_to_alphabet($hasilCol + 1);
                        $barisHas = $hasilRow + 3 + $no; // Sesuaikan dengan nomor urut data
                        $hasilUji = $worksheet->getCell($kolomHas . $barisHas)->getOldCalculatedValue()
                            ?? $worksheet->getCell($kolomHas . $barisHas)->getCalculatedValue()
                            ?? $worksheet->getCell($kolomHas . $barisHas)->getValue();
                    }

                    $insert = [
                        'id_header' => $header,
                        'akr' => isset($value[0]) ? $value[0] : null,
                        'parameter_lab' => $parameter_lab,
                        'parameter' => $value[1],
                        'durasi' => $durasi,
                        'hasil_uji' => $hasilUji,
                        'baku_mutu' => json_encode($val),
                        'satuan' => $satuan,
                        'methode' => $lastValue !== '-' ? str_replace('\\', '', $lastValue) : null,
                    ];

                    $detail = DB::table('lhps_ling_detail')->insert($insert);
                }
                $no++;
            }
            // dump(DB::table('lhps_ling_detail')->where('id_header', $header)->get());
            DB::commit();
            return response()->json([
                'message' => 'Data berhasil diimport',
                'header_id' => $header
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            // Log error untuk debugging
            \Log::error('Import LHP Udara Error: ' . $e->getMessage());
            \Log::error('Import LHP Udara Trace: ' . $e->getTraceAsString());

            return response()->json([
                'message' => 'Terjadi kesalahan saat mengimport data',
                'error' => $e->getMessage() . ', Line : ' . $e->getLine()
            ], 500);
        }
    }

    public function indexKebisingan(Request $request)
    {
        DB::beginTransaction();
        try {
            // Validasi file upload
            if (!$request->hasFile('file')) {
                return response()->json([
                    'message' => 'File tidak ditemukan',
                ], 400);
            }

            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();

            // Validasi jenis file
            if (!in_array($file->getClientOriginalExtension(), ['xlsx', 'xls'])) {
                return response()->json([
                    'message' => 'Format file tidak didukung. Gunakan format Excel (.xlsx atau .xls)',
                ], 400);
            }

            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load($file->getRealPath());

            // Cek apakah sheet 'LHP' ada
            if (!$spreadsheet->sheetNameExists('LHP')) {
                return response()->json([
                    'message' => 'Sheet LHP tidak ditemukan dalam file Excel',
                ], 400);
            }

            $worksheet = $spreadsheet->getSheetByName('LHP');

            /**
             * LHPS KEBISINGAN HEADER
             */
            $results = [];
            foreach ($worksheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(FALSE);
                $row_content = [];
                foreach ($cellIterator as $cel) {
                    try {
                        $value = $cel->getOldCalculatedValue()
                            ?? $cel->getCalculatedValue()
                            ?? $cel->getValue();

                        if ($value === false || $value === '') {
                            $value = $cel->getValue();
                        }
                    } catch (\Throwable $e) {
                        $value = $cel->getOldCalculatedValue()
                            ?? $cel->getValue();

                        if ($value === false || $value === '') {
                            $value = $cel->getValue();
                        }
                    }
                    array_push($row_content, $value);
                }
                $results[] = $row_content;
            }

            // Cari posisi header yang diperlukan
            $headerPositions = [];
            $requiredHeaders = ['LOKASIKETERANGANSAMPEL', 'NOLHP', 'JENISSAMPEL', 'TITIKKOORDINAT'];

            foreach ($requiredHeaders as $header) {
                $position = $this->searchArray($header, $results);
                if ($position !== null) {
                    $coords = explode(',', $position);
                    $headerPositions[$header] = [
                        'row' => (int) $coords[0],
                        'col' => (int) $coords[1]
                    ];
                }
            }

            // Validasi header wajib
            // foreach ($requiredHeaders as $header) {
            //     if (empty($headerPositions[$header])) {
            //         return response()->json([
            //             'message' => "Header $header tidak ditemukan dalam file Excel",
            //         ], 400);
            //     }
            // }

            $highestRow = $worksheet->getHighestRow();

            // Ambil posisi lokasi keterangan sampel
            $lokasiRow = isset($headerPositions['LOKASIKETERANGANSAMPEL']) ? $headerPositions['LOKASIKETERANGANSAMPEL']['row'] : null;
            $lokasiCol = isset($headerPositions['LOKASIKETERANGANSAMPEL']) ? $headerPositions['LOKASIKETERANGANSAMPEL']['col'] : null;
            $kolomPar = $this->number_to_alphabet($lokasiCol + 1);
            $getBarisValue = $lokasiRow + 3;

            // Ambil posisi titik koordinat
            $koordinatRow = isset($headerPositions['TITIKKOORDINAT']) ? $headerPositions['TITIKKOORDINAT']['row'] : null;
            $koordinatCol = isset($headerPositions['TITIKKOORDINAT']) ? $headerPositions['TITIKKOORDINAT']['col'] : null;
            $kolomSpe = $this->number_to_alphabet($koordinatCol + 1);
            $barisSpe = $koordinatRow + 3;

            // Cek apakah ada data di kolom koordinat pada baris pertama data
            $cellValue = $worksheet->getCell($kolomSpe . $barisSpe)->getOldCalculatedValue()
                ?? $worksheet->getCell($kolomSpe . $barisSpe)->getCalculatedValue()
                ?? $worksheet->getCell($kolomSpe . $barisSpe)->getValue();
            if ($cellValue === null || $cellValue === '') {
                // Jika kosong, extend satu kolom ke kanan
                $kolomSpe = $this->number_to_alphabet($koordinatCol + 2);
            }

            // Ambil data header
            $getHeader = $worksheet->rangeToArray($kolomPar . $getBarisValue . ':' . $kolomSpe . $highestRow);

            // Ambil data LHP dan Jenis Sample
            $lhpRow = $headerPositions['NOLHP']['row'];
            $lhpCol = $headerPositions['NOLHP']['col'];
            $kolomLhp = $this->number_to_alphabet($lhpCol + 1);
            $barisLhp = $lhpRow + 2;

            $jenisRow = $headerPositions['JENISSAMPEL']['row'];
            $jenisCol = $headerPositions['JENISSAMPEL']['col'];
            $kolomJen = $this->number_to_alphabet($jenisCol + 1);
            $barisJen = $jenisRow + 2;

            $noLhp = $worksheet->getCell($kolomLhp . $barisLhp)->getOldCalculatedValue()
                ?? $worksheet->getCell($kolomLhp . $barisLhp)->getCalculatedValue()
                ?? $worksheet->getCell($kolomLhp . $barisLhp)->getValue();
            $jenisSample = $worksheet->getCell($kolomJen . $barisJen)->getOldCalculatedValue()
                ?? $worksheet->getCell($kolomJen . $barisJen)->getCalculatedValue()
                ?? $worksheet->getCell($kolomJen . $barisJen)->getValue();

            if (strtolower($jenisSample) == 'lingkungan kerja') {
                if (stripos($originalName, '24 Jam')) {
                    $jenisSample = 'Kebisingan 24 Jam';
                } else {
                    $jenisSample = 'Kebisingan';
                }
            }

            if (empty($noLhp)) {
                return response()->json([
                    'message' => 'No LHP tidak ditemukan atau kosong',
                ], 400);
            }

            $noOrder = explode('/', $noLhp);

            // Get detail informasi pelanggan
            $pelanggan = OrderDetail::where('cfr', str_replace(' ', '', $noLhp))
                ->first();

            $insert = [];
            if ($pelanggan == null) {
                $insert = [
                    'no_order' => str_replace(' ', '', $noOrder[0]),
                    'no_lhp' => str_replace(' ', '', $noLhp),
                    'parameter_uji' => json_encode($jenisSample),
                    'sub_kategori' => $jenisSample,
                ];
            } else {
                $modifiedData = [];
                if ($pelanggan->regulasi != null) {
                    foreach (json_decode($pelanggan->regulasi) as $item) {
                        list($id, $text) = explode('-', $item, 2);
                        $modifiedData[] = trim($text);
                    }
                } else {
                    $modifiedData = null;
                }

                $insert = [
                    'no_order' => str_replace(' ', '', $noOrder[0]),
                    'no_lhp' => str_replace(' ', '', $noLhp),
                    'no_qt' => $pelanggan->no_penawaran,
                    'nama_pelanggan' => $pelanggan->nama_perusahaan,
                    'alamat_sampling' => $pelanggan->alamat_perusahaan,
                    'parameter_uji' => $pelanggan->param,
                    'id_kategori_3' => explode('-', $pelanggan->kategori_3)[0],
                    'sub_kategori' => $jenisSample,
                    'regulasi' => $modifiedData == null ? $modifiedData : json_encode($modifiedData),
                    'tanggal_sampling' => $pelanggan->tanggal_sampling,
                    // 'no_sampel' => json_encode()
                    'no_qt' => $pelanggan->no_quotation,
                    'id_kategori_2' => explode('-', $pelanggan->kategori_2)[0],
                    'deskripsi_titik' => $pelanggan->keterangan_1,
                    'nama_karyawan' => 'Abidah Walfathiyyah',
                    'jabatan_karyawan' => 'Technical Control Supervisor',
                ];
            }

            $header = DB::table('lhps_kebisingan_header')->insertGetId($insert);

            $lhpsKebisinganHeader = LhpsKebisinganHeader::find($header);
            $file_qr = new GenerateQrDocumentLhp();
            if ($path = $file_qr->insert('LHP_KEBISINGAN', $lhpsKebisinganHeader, 'Abidah Walfathiyyah')) {
                $lhpsKebisinganHeader->file_qr = $path;
                $lhpsKebisinganHeader->save();
            }

            /**
             * LHPS KEBISINGAN DETAIL
             */
            $no = 0;
            foreach ($getHeader as $value) {
                // Cek apakah ada data pada kolom ke-2 (lokasi keterangan)
                if (isset($value[1]) && $value[1] !== null && $value[1] !== "" && !empty($value[1])) {

                    if (strtoupper($jenisSample) == 'KEBISINGAN') {
                        // Ambil nomor sampel
                        $kolomSample = $this->number_to_alphabet($lokasiCol + 1);
                        $barisSample = $lokasiRow + 3 + $no;
                        $noSampel = $worksheet->getCell($kolomSample . $barisSample)->getOldCalculatedValue()
                            ?? $worksheet->getCell($kolomSample . $barisSample)->getCalculatedValue()
                            ?? $worksheet->getCell($kolomSample . $barisSample)->getValue();

                        // Ambil lokasi keterangan
                        $kolomLok = $this->number_to_alphabet($lokasiCol + 2);
                        $barisLok = $lokasiRow + 3 + $no;
                        $lokasiKeterangan = $worksheet->getCell($kolomLok . $barisLok)->getOldCalculatedValue()
                            ?? $worksheet->getCell($kolomLok . $barisLok)->getCalculatedValue()
                            ?? $worksheet->getCell($kolomLok . $barisLok)->getValue();

                        // Ambil data MIN
                        $minPosition = $this->searchArray('MIN', $results);
                        $min = null;
                        if ($minPosition !== null) {
                            $minCoords = explode(',', $minPosition);
                            $kolomMin = $this->number_to_alphabet($minCoords[1] + 1);
                            $barisMin = $minCoords[0] + 2 + $no;
                            $min = $worksheet->getCell($kolomMin . $barisMin)->getOldCalculatedValue()
                                ?? $worksheet->getCell($kolomMin . $barisMin)->getCalculatedValue()
                                ?? $worksheet->getCell($kolomMin . $barisMin)->getValue();
                        }

                        // Ambil data MAX
                        $maxPosition = $this->searchArray('MAX', $results);
                        $max = null;
                        if ($maxPosition !== null) {
                            $maxCoords = explode(',', $maxPosition);
                            $kolomMax = $this->number_to_alphabet($maxCoords[1] + 1);
                            $barisMax = $maxCoords[0] + 2 + $no;
                            $max = $worksheet->getCell($kolomMax . $barisMax)->getOldCalculatedValue()
                                ?? $worksheet->getCell($kolomMax . $barisMax)->getCalculatedValue()
                                ?? $worksheet->getCell($kolomMax . $barisMax)->getValue();
                        }

                        // Ambil hasil uji
                        $hasilPosition = $this->searchArray('HASILUJI', $results);
                        $hasil = null;
                        if ($hasilPosition !== null) {
                            $hasilCoords = explode(',', $hasilPosition);
                            $kolomHasil = $this->number_to_alphabet($hasilCoords[1] + 1);
                            $barisHasil = $hasilCoords[0] + 2 + $no;
                            $hasil = $worksheet->getCell($kolomHasil . $barisHasil)->getOldCalculatedValue()
                                ?? $worksheet->getCell($kolomHasil . $barisHasil)->getCalculatedValue()
                                ?? $worksheet->getCell($kolomHasil . $barisHasil)->getValue();
                        }

                        // Ambil titik koordinat
                        $kolomKoordinat = $this->number_to_alphabet($koordinatCol + 1);
                        $barisKoordinat = $koordinatRow + 3 + $no;
                        $titikKoordinat = $worksheet->getCell($kolomKoordinat . $barisKoordinat)->getOldCalculatedValue()
                            ?? $worksheet->getCell($kolomKoordinat . $barisKoordinat)->getCalculatedValue()
                            ?? $worksheet->getCell($kolomKoordinat . $barisKoordinat)->getValue();

                        $insert = [
                            'id_header' => $header,
                            'no_sampel' => $noSampel,
                            'lokasi_keterangan' => $lokasiKeterangan,
                            'min' => $min,
                            'max' => $max,
                            'hasil_uji' => $hasil,
                            'titik_koordinat' => $titikKoordinat,
                        ];

                        DB::table('lhps_kebisingan_detail')->insert($insert);
                    } else if (strtoupper($jenisSample) == 'KEBISINGAN 24 JAM') {
                        // Ambil nomor sampel
                        $kolomSample = $this->number_to_alphabet($lokasiCol + 1);
                        $barisSample = $lokasiRow + 3 + $no;
                        $noSampel = $worksheet->getCell($kolomSample . $barisSample)->getOldCalculatedValue()
                            ?? $worksheet->getCell($kolomSample . $barisSample)->getCalculatedValue()
                            ?? $worksheet->getCell($kolomSample . $barisSample)->getValue();

                        // Ambil lokasi keterangan
                        $kolomLok = $this->number_to_alphabet($lokasiCol + 2);
                        $barisLok = $lokasiRow + 3 + $no;
                        $lokasiKeterangan = $worksheet->getCell($kolomLok . $barisLok)->getOldCalculatedValue()
                            ?? $worksheet->getCell($kolomLok . $barisLok)->getCalculatedValue()
                            ?? $worksheet->getCell($kolomLok . $barisLok)->getValue();

                        // Ambil data LS (Siang)
                        $lsPosition = $this->searchArray('LSSIANG', $results);
                        $ls = null;
                        if ($lsPosition !== null) {
                            $lsCoords = explode(',', $lsPosition);
                            $kolomLs = $this->number_to_alphabet($lsCoords[1] + 1);
                            $barisLs = $lsCoords[0] + 2 + $no;
                            $ls = $worksheet->getCell($kolomLs . $barisLs)->getOldCalculatedValue()
                                ?? $worksheet->getCell($kolomLs . $barisLs)->getCalculatedValue()
                                ?? $worksheet->getCell($kolomLs . $barisLs)->getValue();
                        }

                        // Ambil data LM (Malam)
                        $lmPosition = $this->searchArray('LMMALAM', $results);
                        $lm = null;
                        if ($lmPosition !== null) {
                            $lmCoords = explode(',', $lmPosition);
                            $kolomLm = $this->number_to_alphabet($lmCoords[1] + 1);
                            $barisLm = $lmCoords[0] + 2 + $no;
                            $lm = $worksheet->getCell($kolomLm . $barisLm)->getOldCalculatedValue()
                                ?? $worksheet->getCell($kolomLm . $barisLm)->getCalculatedValue()
                                ?? $worksheet->getCell($kolomLm . $barisLm)->getValue();
                        }

                        // Ambil data LSM (Siang-Malam)
                        $lsmPosition = $this->searchArray('LS-MSIANG-MALAM', $results);
                        $lsm = null;
                        if ($lsmPosition !== null) {
                            $lsmCoords = explode(',', $lsmPosition);
                            $kolomLsm = $this->number_to_alphabet($lsmCoords[1] + 1);
                            $barisLsm = $lsmCoords[0] + 2 + $no;
                            $lsm = $worksheet->getCell($kolomLsm . $barisLsm)->getOldCalculatedValue()
                                ?? $worksheet->getCell($kolomLsm . $barisLsm)->getCalculatedValue()
                                ?? $worksheet->getCell($kolomLsm . $barisLsm)->getValue();
                        }

                        // Ambil titik koordinat
                        $kolomKoordinat = $this->number_to_alphabet($koordinatCol + 1);
                        $barisKoordinat = $koordinatRow + 3 + $no;
                        $titikKoordinat = $worksheet->getCell($kolomKoordinat . $barisKoordinat)->getOldCalculatedValue()
                            ?? $worksheet->getCell($kolomKoordinat . $barisKoordinat)->getCalculatedValue()
                            ?? $worksheet->getCell($kolomKoordinat . $barisKoordinat)->getValue();

                        $insert = [
                            'id_header' => $header,
                            'no_sampel' => $noSampel,
                            'lokasi_keterangan' => $lokasiKeterangan,
                            'leq_lm' => $lm,
                            'leq_ls' => $ls,
                            'leq_lsm' => $lsm,
                            'titik_koordinat' => $titikKoordinat,
                        ];

                        DB::table('lhps_kebisingan_detail')->insert($insert);
                    } else {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'Jenis Sampel tidak didukung. Harus "KEBISINGAN" atau "KEBISINGAN 24 JAM"',
                        ], 400);
                    }
                }
                $no++;
            }

            DB::commit();
            return response()->json([
                'message' => 'Data LHP Kebisingan berhasil diimport',
                'header_id' => $header
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            // Log error untuk debugging
            \Log::error('Import LHP Kebisingan Error: ' . $e->getMessage());
            \Log::error('Import LHP Kebisingan Trace: ' . $e->getTraceAsString());

            return response()->json([
                'message' => 'Terjadi kesalahan saat mengimport data',
                'error' => $e->getMessage() . ', Line : ' . $e->getLine()
            ], 500);
        }
    }

    public function indexEmisi(Request $request)
    {
        DB::beginTransaction();
        try {
            // Validasi file upload
            if (!$request->hasFile('file')) {
                return response()->json([
                    'message' => 'File tidak ditemukan',
                ], 400);
            }

            $file = $request->file('file');

            // Validasi jenis file
            if (!in_array($file->getClientOriginalExtension(), ['xlsx', 'xls'])) {
                return response()->json([
                    'message' => 'Format file tidak didukung. Gunakan format Excel (.xlsx atau .xls)',
                ], 400);
            }

            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load($file->getRealPath());

            // Cek apakah sheet 'LHP' ada
            if (!$spreadsheet->sheetNameExists('LHP')) {
                return response()->json([
                    'message' => 'Sheet LHP tidak ditemukan dalam file Excel',
                ], 400);
            }

            $worksheet = $spreadsheet->getSheetByName('LHP');

            /**
             * LHPS EMISI HEADER
             */
            $results = [];
            foreach ($worksheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(FALSE);
                $row_content = [];
                foreach ($cellIterator as $cel) {
                    try {
                        $value = $cel->getOldCalculatedValue()
                            ?? $cel->getCalculatedValue()
                            ?? $cel->getValue();

                        if ($value === false || $value === '') {
                            $value = $cel->getValue();
                        }
                    } catch (\Throwable $e) {
                        $value = $cel->getOldCalculatedValue()
                            ?? $cel->getValue();

                        if ($value === false || $value === '') {
                            $value = $cel->getValue();
                        }
                    }
                    array_push($row_content, $value);
                }
                $results[] = $row_content;
            }

            // Cari posisi header-header yang diperlukan
            $headerPositions = $this->findHeaderPositions($results, 'udara');

            // Validasi header wajib
            $requiredHeaders = ['PARAMETER', 'SPESIFIKASIMETODE', 'NOLHP', 'NOSAMPEL', 'JENISSAMPEL'];
            foreach ($requiredHeaders as $header) {
                if (empty($headerPositions[$header])) {
                    return response()->json([
                        'message' => "Header $header tidak ditemukan dalam file Excel",
                    ], 400);
                }
            }

            $highestRow = $worksheet->getHighestRow();

            // Ambil posisi parameter
            $parameterRow = $headerPositions['PARAMETER']['row'];
            $parameterCol = $headerPositions['PARAMETER']['col'];
            $parameterStartRow = $parameterRow + 3; // Data dimulai 2 baris setelah header

            // Ambil posisi spesifikasi metode
            $spesifikasiRow = $headerPositions['SPESIFIKASIMETODE']['row'];
            $spesifikasiCol = $headerPositions['SPESIFIKASIMETODE']['col'];

            // Tentukan range kolom untuk data parameter
            $kolomPar = $this->number_to_alphabet($parameterCol + 1);
            $kolomSpe = $this->number_to_alphabet($spesifikasiCol + 1);

            // Cek apakah ada data di kolom spesifikasi pada baris pertama data
            $cellValue = $worksheet->getCell($kolomSpe . ($spesifikasiRow + 2))->getOldCalculatedValue()
                ?? $worksheet->getCell($kolomSpe . ($spesifikasiRow + 2))->getCalculatedValue()
                ?? $worksheet->getCell($kolomSpe . ($spesifikasiRow + 2))->getValue();
            if ($cellValue === null || $cellValue === '') {
                // Jika kosong, extend satu kolom ke kanan
                $kolomSpe = $this->number_to_alphabet($spesifikasiCol + 2);
            }

            // Ambil data parameter
            $getHeader = $worksheet->rangeToArray($kolomPar . $parameterStartRow . ':' . $kolomSpe . $highestRow);
            // Proses header baku mutu
            $headTable = NULL;
            if (!empty($headerPositions['BAKUMUTU'])) {
                $bakuRow = $headerPositions['BAKUMUTU']['row'];
                $bakuCol = $headerPositions['BAKUMUTU']['col'];
                $kolomBak = $this->number_to_alphabet($bakuCol + 1);
                $barisMutu = $bakuRow + 3; // Data baku mutu biasanya 2 baris setelah header

                $cellValue = $worksheet->getCell($kolomBak . $barisMutu)->getOldCalculatedValue()
                    ?? $worksheet->getCell($kolomBak . $barisMutu)->getCalculatedValue()
                    ?? $worksheet->getCell($kolomBak . $barisMutu)->getValue();
                if ($cellValue !== null && $cellValue !== '') {
                    $getBaku = $this->filterArray($worksheet->getMergeCells(), $kolomBak . ($bakuRow + 1));
                    $v = [];
                    foreach ($getBaku as $vv) {
                        $explode = explode(':', $vv);
                        if (count($explode) >= 2) {
                            $startCell = $explode[0];
                            $endCell = $explode[1];

                            if (strlen($startCell) >= 2) {
                                $split = str_split($startCell, 1);
                                $split1 = str_split($endCell, 1);
                                $cov = intval($split[1]);
                                $cov1 = intval($split1[1]);
                                $rangeData = $worksheet->rangeToArray($split[0] . $cov . ':' . $split1[0] . $cov1);
                                if (!empty($rangeData)) {
                                    $v = array_merge($v, $rangeData);
                                    $v = reset($rangeData);
                                }
                            }
                        }
                    }
                    $headTable = json_encode($v);
                }
            }
            // Proses parameter
            $param = [];
            foreach ($getHeader as $valhead) {
                //cek nama parameter di excel kosong atau tidak
                if (isset($valhead[1]) && $valhead[1] !== null && $valhead[1] !== "" && !empty($valhead[1])) {
                    //cek method di excel kosong atau tidak
                    $lastValue = end($valhead);
                    if ($lastValue == '-' || $lastValue === null) {
                        $method = 'nama_regulasi = ? AND method IS NULL';
                        $nameParam = Parameter::select('nama_lab')
                            ->where('id_kategori', 4) // Kategori udara
                            ->whereRaw($method, [$valhead[1]])
                            ->first();
                    } else {
                        $nameParam = Parameter::select('nama_lab')
                            ->where('id_kategori', 4) // Kategori udara
                            ->where(function ($query) use ($valhead, $lastValue) {
                                $query->where('nama_regulasi', $valhead[1])
                                    ->orWhere('method', $lastValue);
                            })
                            ->first();
                    }
                    if ($nameParam == NULL) {
                        array_push($param, $valhead[1]);
                    } else {
                        array_push($param, $nameParam->nama_lab);
                    }
                }
            }

            // Ambil data LHP, Sample, dan Jenis Sample
            $lhpRow = $headerPositions['NOLHP']['row'];
            $lhpCol = $headerPositions['NOLHP']['col'];
            $kolomLhp = $this->number_to_alphabet($lhpCol + 1);
            $barisLhp = $lhpRow + 2; // Data biasanya 1 baris setelah header

            $sampleRow = $headerPositions['NOSAMPEL']['row'];
            $sampleCol = $headerPositions['NOSAMPEL']['col'];
            $kolomSam = $this->number_to_alphabet($sampleCol + 1);
            $barisSam = $sampleRow + 2;

            $jenisRow = $headerPositions['JENISSAMPEL']['row'];
            $jenisCol = $headerPositions['JENISSAMPEL']['col'];
            $kolomJen = $this->number_to_alphabet($jenisCol + 1);
            $barisJen = $jenisRow + 2;

            $noLhp = $worksheet->getCell($kolomLhp . $barisLhp)->getOldCalculatedValue()
                ?? $worksheet->getCell($kolomLhp . $barisLhp)->getCalculatedValue()
                ?? $worksheet->getCell($kolomLhp . $barisLhp)->getValue();
            $noSample = $worksheet->getCell($kolomSam . $barisSam)->getOldCalculatedValue()
                ?? $worksheet->getCell($kolomSam . $barisSam)->getCalculatedValue()
                ?? $worksheet->getCell($kolomSam . $barisSam)->getValue();
            $jenisSample = $worksheet->getCell($kolomJen . $barisJen)->getOldCalculatedValue()
                ?? $worksheet->getCell($kolomJen . $barisJen)->getCalculatedValue()
                ?? $worksheet->getCell($kolomJen . $barisJen)->getValue();

            if (empty($noLhp)) {
                return response()->json([
                    'message' => 'No LHP tidak ditemukan atau kosong',
                ], 400);
            }

            $noOrder = explode('/', $noLhp);

            if (count($noOrder) > 2) {
                $noLhp = implode('/', array_slice($noOrder, 0, 2));
            }

            //get detail informasi pelanggan
            $pelanggan = OrderDetail::with('orderHeader')->where('cfr', str_replace(' ', '', $noLhp))
                ->where('no_sampel', str_replace(' ', '', $noSample))
                ->first();

            if ($jenisSample == 'Emisi Sumber Tidak Bergerak') {
                $insert = [];
                if ($pelanggan == null) {
                    $insert = [
                        'no_order' => str_replace(' ', '', $noOrder[0]),
                        'no_lhp' => str_replace(' ', '', $noLhp),
                        'no_sampel' => $noSample,
                        'parameter_uji' => json_encode($param),
                        'sub_kategori' => $jenisSample,
                        // 'header_table' => $headTable,
                    ];
                } else {
                    $insert = [
                        'no_order' => str_replace(' ', '', $noOrder[0]),
                        'no_lhp' => str_replace(' ', '', $noLhp),
                        'no_sampel' => $noSample,
                        'parameter_uji' => json_encode($param),
                        'sub_kategori' => $jenisSample,
                        'nama_pelanggan' => $pelanggan->nama_perusahaan,
                        'deskripsi_titik' => $pelanggan->keterangan_1,
                        'tanggal_sampling' => $pelanggan->tanggal_sampling,
                        // 'header_table' => $headTable,
                        'no_quotation' => $pelanggan->orderHeader->no_document,
                        // 'status_sampling' => $pelanggan->no_quotation,
                        'alamat_sampling' => $pelanggan->orderHeader->alamat_sampling,
                        'kategori' => ucwords(explode('-', $pelanggan->kategori_2)[1]),
                        'id_kategori_2' => explode('-', $pelanggan->kategori_2)[0],
                        'id_kategori_3' => explode('-', $pelanggan->kategori_3)[0],
                        // 'methode_sampling' => "", // gtau drmna
                        // 'tgl_lhp' => date('Y-m-d'),
                        // 'periode_analisa' =>
                        'regulasi' => $pelanggan->regulasi,
                        // 'regulasi_custom' =>
                        // 'keterangan' =>
                        // 'suhu' =>
                        // 'cuaca' =>
                        // 'arah_angin' =>
                        // 'kelembapan' =>
                        // 'kec_angin' =>
                        // 'titik_koordinat' =>
                        'nama_karyawan' => 'Abidah Walfathiyyah',
                        'jabatan_karyawan' => 'Technical Control Supervisor',
                        // 'file_qr' => ''
                        // 'file_lhp' =>
                        // 'tanggal_lhp' =>
                        // 'created_by' =>
                        // 'created_at' => date('Y-m-d H:i:s'),
                        // 'updated_by' =>
                        // 'updated_at' =>
                        // 'approved_by' =>
                        // 'approved_at' =>
                        // 'rejected_by' =>
                        // 'rejected_at' =>
                        // 'deleted_by' =>
                        // 'deleted_at' =>
                        // 'is_generated' =>
                        // 'generated_by' =>
                        // 'generated_at' =>
                        // 'is_emailed' =>
                        // 'emailed_by' =>
                        // 'emailed_at' =>
                        // 'id_token' =>
                        // 'expired' =>
                    ];
                }
                // dd($insert);
                if (DB::table('lhps_emisic_header')->where('no_lhp', $noLhp)->exists()) {
                    return response()->json([
                        'message' => 'No LHP sudah ada',
                    ], 400);
                }
                
                $header = DB::table('lhps_emisic_header')->insertGetId($insert);
    
                $LhpsEmisiCHeader = LhpsEmisiCHeader::find($header);
                $file_qr = new GenerateQrDocumentLhp();
                if ($path = $file_qr->insert('LHP_EMISI', $LhpsEmisiCHeader, 'Abidah Walfathiyyah')) {
                    $LhpsEmisiCHeader->file_qr = $path;
                    $LhpsEmisiCHeader->save();
                }
    
                // dump(DB::table('lhps_emisi_header')->find($header));
    
                /**
                 * LHPS UDARA DETAIL
                 */
                $no = 0;
                foreach ($getHeader as $value) {
                    //cek nama parameter di excel kosong atau tidak
                    if (isset($value[1]) && $value[1] !== null && $value[1] !== "" && !empty($value[1])) {
                        //cek method di excel kosong atau tidak
                        $lastValue = end($value);
                        if ($lastValue == '-' || $lastValue === null) {
                            $method = 'nama_regulasi = ? AND method IS NULL';
                            $nameParam = Parameter::select('nama_lab')
                                ->where('id_kategori', 4) // Kategori udara
                                ->whereRaw($method, [$value[1]])
                                ->first();
                        } else {
                            $nameParam = Parameter::select('nama_lab')
                                ->where('id_kategori', 4) // Kategori udara
                                ->where(function ($query) use ($value, $lastValue) {
                                    $query->where('nama_regulasi', $value[1])
                                        ->orWhere('method', $lastValue);
                                })
                                ->first();
                        }
    
                        $parameter_lab = ($nameParam == NULL) ? NULL : $nameParam->nama_lab;
    
                        $val = [];
                        if (!empty($headerPositions['BAKUMUTU'])) {
                            $bakuRow = $headerPositions['BAKUMUTU']['row'];
                            $bakuCol = $headerPositions['BAKUMUTU']['col'];
                            $kolomBak = $this->number_to_alphabet($bakuCol + 1);
                            $barisMutu = $bakuRow + 3; // Data baku mutu biasanya 2 baris setelah header
    
                            $cellValue = $worksheet->getCell($kolomBak . $barisMutu)->getOldCalculatedValue()
                                ?? $worksheet->getCell($kolomBak . $barisMutu)->getCalculatedValue()
                                ?? $worksheet->getCell($kolomBak . $barisMutu)->getValue();
    
                            if ($cellValue !== null && $cellValue !== '') {
                                $getBaku = $this->filterArray($worksheet->getMergeCells(), $kolomBak . ($bakuRow + 1));
                                // get baku mutu untuk lhps detail
                                foreach ($getBaku as $values) {
                                    $explode = explode(':', $values);
                                    if (count($explode) >= 2) {
                                        $startCell = $explode[0];
                                        $endCell = $explode[1];
    
                                        if (strlen($startCell) >= 2) {
                                            $split = str_split($startCell, 1);
                                            $split1 = str_split($endCell, 1);
                                            $cov = intval($split[1]) + 1 + $no; // Sesuaikan dengan baris data
                                            $cov1 = intval($split1[1]) + 1 + $no;
                                            $rangeData = $worksheet->rangeToArray($split[0] . $cov . ':' . $split1[0] . $cov1);
                                            if (!empty($rangeData)) {
                                                foreach ($rangeData as $k => $vals) {
                                                    $val = $vals;
                                                }
                                            }
                                        }
                                    }
                                }
                            } else {
                                $kolomHas = $this->number_to_alphabet($bakuCol + 1);
                                $barisHas = $bakuRow + 2 + $no; // Sesuaikan dengan nomor urut data
                                $cellValue = $worksheet->getCell($kolomHas . $barisHas)->getOldCalculatedValue()
                                    ?? $worksheet->getCell($kolomHas . $barisHas)->getCalculatedValue()
                                    ?? $worksheet->getCell($kolomHas . $barisHas)->getValue();
                                $val = [$cellValue];
                            }
                        }
    
                        // get durasi detail (khusus untuk udara)
                        $durasi = null;
                        if (!empty($headerPositions['DURASI'])) {
                            $durasiRow = $headerPositions['DURASI']['row'];
                            $durasiCol = $headerPositions['DURASI']['col'];
                            $kolomDur = $this->number_to_alphabet($durasiCol + 1);
                            $barisDur = $durasiRow + 3 + $no; // Sesuaikan dengan nomor urut data
                            $durasi = $worksheet->getCell($kolomDur . $barisDur)->getOldCalculatedValue()
                                ?? $worksheet->getCell($kolomDur . $barisDur)->getCalculatedValue()
                                ?? $worksheet->getCell($kolomDur . $barisDur)->getValue();
                        }
    
                        // get satuan detail
                        $satuan = null;
                        if (!empty($headerPositions['SATUAN'])) {
                            $satuanRow = $headerPositions['SATUAN']['row'];
                            $satuanCol = $headerPositions['SATUAN']['col'];
                            $kolomSat = $this->number_to_alphabet($satuanCol + 1);
                            $barisSat = $satuanRow + 3 + $no; // Sesuaikan dengan nomor urut data
                            $satuan = $worksheet->getCell($kolomSat . $barisSat)->getOldCalculatedValue()
                                ?? $worksheet->getCell($kolomSat . $barisSat)->getCalculatedValue()
                                ?? $worksheet->getCell($kolomSat . $barisSat)->getValue();
                        }
    
                        //get hasil uji untuk lhps detail
                        $hasilUji = null;
                        if (!empty($headerPositions['HASILUJI'])) {
                            $hasilRow = $headerPositions['HASILUJI']['row'];
                            $hasilCol = $headerPositions['HASILUJI']['col'];
                            $kolomHas = $this->number_to_alphabet($hasilCol + 1);
                            $barisHas = $hasilRow + 3 + $no; // Sesuaikan dengan nomor urut data
                            $hasilUji = $worksheet->getCell($kolomHas . $barisHas)->getOldCalculatedValue()
                                ?? $worksheet->getCell($kolomHas . $barisHas)->getCalculatedValue()
                                ?? $worksheet->getCell($kolomHas . $barisHas)->getValue();
                        }
    
                        $insert = [
                            'id_header' => $header,
                            'akr' => isset($value[0]) ? $value[0] : null,
                            'parameter_lab' => $parameter_lab,
                            'parameter' => $value[1],
                            // 'durasi' => $durasi,
                            'C' => $hasilUji,
                            'baku_mutu' => json_encode($val),
                            'satuan' => $satuan,
                            'spesifikasi_metode' => $lastValue !== '-' ? str_replace('\\', '', $lastValue) : null,
                        ];
    
                        $detail = DB::table('lhps_emisic_detail')->insert($insert);
                    }
                    $no++;
                }
                // dump(DB::table('lhps_emisi_detail')->where('id_header', $header)->get());
                DB::commit();
                return response()->json([
                    'message' => 'Data berhasil diimport',
                    'header_id' => $header
                ], 201);
            } else {
                $insert = [];
                if ($pelanggan == null) {
                    $insert = [
                        'no_order' => str_replace(' ', '', $noOrder[0]),
                        'no_lhp' => str_replace(' ', '', $noLhp),
                        'no_sampel' => $noSample,
                        'parameter_uji' => json_encode($param),
                        'sub_kategori' => $jenisSample,
                        'header_table' => $headTable,
                    ];
                } else {
                    $insert = [
                        'no_order' => str_replace(' ', '', $noOrder[0]),
                        'no_lhp' => str_replace(' ', '', $noLhp),
                        // 'no_sampel' => $noSample,
                        'parameter_uji' => json_encode($param),
                        'sub_kategori' => $jenisSample,
                        'nama_pelanggan' => $pelanggan->nama_perusahaan,
                        // 'deskripsi_titik' => $pelanggan->keterangan_1,
                        'tanggal_sampling' => $pelanggan->tanggal_sampling,
                        // 'header_table' => $headTable,
                        'no_quotation' => $pelanggan->orderHeader->no_document,
                        // 'status_sampling' => $pelanggan->no_quotation,
                        'alamat_sampling' => $pelanggan->orderHeader->alamat_sampling,
                        'id_kategori_2' => explode('-', $pelanggan->kategori_2)[0],
                        'id_kategori_3' => explode('-', $pelanggan->kategori_3)[0],
                        // 'methode_sampling' => "", // gtau drmna
                        'tgl_lhp' => date('Y-m-d'),
                        // 'periode_analisa' =>
                        'regulasi' => $pelanggan->regulasi,
                        // 'regulasi_custom' =>
                        // 'keterangan' =>
                        // 'suhu' =>
                        // 'cuaca' =>
                        // 'arah_angin' =>
                        // 'kelembapan' =>
                        // 'kec_angin' =>
                        // 'titik_koordinat' =>
                        'nama_karyawan' => 'Abidah Walfathiyyah',
                        'jabatan_karyawan' => 'Technical Control Supervisor',
                        // 'file_qr' => ''
                        // 'file_lhp' =>
                        // 'tanggal_lhp' =>
                        // 'created_by' =>
                        'created_at' => date('Y-m-d H:i:s'),
                        // 'updated_by' =>
                        // 'updated_at' =>
                        // 'approved_by' =>
                        // 'approved_at' =>
                        // 'rejected_by' =>
                        // 'rejected_at' =>
                        // 'deleted_by' =>
                        // 'deleted_at' =>
                        // 'is_generated' =>
                        // 'generated_by' =>
                        // 'generated_at' =>
                        // 'is_emailed' =>
                        // 'emailed_by' =>
                        // 'emailed_at' =>
                        // 'id_token' =>
                        // 'expired' =>
                    ];
                }
                // dd($insert);
                if (DB::table('lhps_emisi_header')->where('no_lhp', $noLhp)->exists()) {
                    return response()->json([
                        'message' => 'No LHP sudah ada',
                    ], 400);
                }
    
                $header = DB::table('lhps_emisi_header')->insertGetId($insert);
    
                $LhpsEmisiHeader = LhpsEmisiHeader::find($header);
                $file_qr = new GenerateQrDocumentLhp();
                if ($path = $file_qr->insert('LHP_EMISI', $LhpsEmisiHeader, 'Abidah Walfathiyyah')) {
                    $LhpsEmisiHeader->file_qr = $path;
                    $LhpsEmisiHeader->save();
                }
    
                // dump(DB::table('lhps_emisi_header')->find($header));
    
                /**
                 * LHPS UDARA DETAIL
                 */
                $no = 0;
                foreach ($getHeader as $value) {
                    //cek nama parameter di excel kosong atau tidak
                    if (isset($value[1]) && $value[1] !== null && $value[1] !== "" && !empty($value[1])) {
                        //cek method di excel kosong atau tidak
                        $lastValue = end($value);
                        if ($lastValue == '-' || $lastValue === null) {
                            $method = 'nama_regulasi = ? AND method IS NULL';
                            $nameParam = Parameter::select('nama_lab')
                                ->where('id_kategori', 4) // Kategori udara
                                ->whereRaw($method, [$value[1]])
                                ->first();
                        } else {
                            $nameParam = Parameter::select('nama_lab')
                                ->where('id_kategori', 4) // Kategori udara
                                ->where(function ($query) use ($value, $lastValue) {
                                    $query->where('nama_regulasi', $value[1])
                                        ->orWhere('method', $lastValue);
                                })
                                ->first();
                        }
    
                        $parameter_lab = ($nameParam == NULL) ? NULL : $nameParam->nama_lab;
    
                        $val = [];
                        if (!empty($headerPositions['BAKUMUTU'])) {
                            $bakuRow = $headerPositions['BAKUMUTU']['row'];
                            $bakuCol = $headerPositions['BAKUMUTU']['col'];
                            $kolomBak = $this->number_to_alphabet($bakuCol + 1);
                            $barisMutu = $bakuRow + 3; // Data baku mutu biasanya 2 baris setelah header
    
                            $cellValue = $worksheet->getCell($kolomBak . $barisMutu)->getOldCalculatedValue()
                                ?? $worksheet->getCell($kolomBak . $barisMutu)->getCalculatedValue()
                                ?? $worksheet->getCell($kolomBak . $barisMutu)->getValue();
    
                            if ($cellValue !== null && $cellValue !== '') {
                                $getBaku = $this->filterArray($worksheet->getMergeCells(), $kolomBak . ($bakuRow + 1));
                                // get baku mutu untuk lhps detail
                                foreach ($getBaku as $values) {
                                    $explode = explode(':', $values);
                                    if (count($explode) >= 2) {
                                        $startCell = $explode[0];
                                        $endCell = $explode[1];
    
                                        if (strlen($startCell) >= 2) {
                                            $split = str_split($startCell, 1);
                                            $split1 = str_split($endCell, 1);
                                            $cov = intval($split[1]) + 1 + $no; // Sesuaikan dengan baris data
                                            $cov1 = intval($split1[1]) + 1 + $no;
                                            $rangeData = $worksheet->rangeToArray($split[0] . $cov . ':' . $split1[0] . $cov1);
                                            if (!empty($rangeData)) {
                                                foreach ($rangeData as $k => $vals) {
                                                    $val = $vals;
                                                }
                                            }
                                        }
                                    }
                                }
                            } else {
                                $kolomHas = $this->number_to_alphabet($bakuCol + 1);
                                $barisHas = $bakuRow + 2 + $no; // Sesuaikan dengan nomor urut data
                                $cellValue = $worksheet->getCell($kolomHas . $barisHas)->getOldCalculatedValue()
                                    ?? $worksheet->getCell($kolomHas . $barisHas)->getCalculatedValue()
                                    ?? $worksheet->getCell($kolomHas . $barisHas)->getValue();
                                $val = [$cellValue];
                            }
                        }
    
                        // get durasi detail (khusus untuk udara)
                        $durasi = null;
                        if (!empty($headerPositions['DURASI'])) {
                            $durasiRow = $headerPositions['DURASI']['row'];
                            $durasiCol = $headerPositions['DURASI']['col'];
                            $kolomDur = $this->number_to_alphabet($durasiCol + 1);
                            $barisDur = $durasiRow + 3 + $no; // Sesuaikan dengan nomor urut data
                            $durasi = $worksheet->getCell($kolomDur . $barisDur)->getOldCalculatedValue()
                                ?? $worksheet->getCell($kolomDur . $barisDur)->getCalculatedValue()
                                ?? $worksheet->getCell($kolomDur . $barisDur)->getValue();
                        }
    
                        // get satuan detail
                        $satuan = null;
                        if (!empty($headerPositions['SATUAN'])) {
                            $satuanRow = $headerPositions['SATUAN']['row'];
                            $satuanCol = $headerPositions['SATUAN']['col'];
                            $kolomSat = $this->number_to_alphabet($satuanCol + 1);
                            $barisSat = $satuanRow + 3 + $no; // Sesuaikan dengan nomor urut data
                            $satuan = $worksheet->getCell($kolomSat . $barisSat)->getOldCalculatedValue()
                                ?? $worksheet->getCell($kolomSat . $barisSat)->getCalculatedValue()
                                ?? $worksheet->getCell($kolomSat . $barisSat)->getValue();
                        }
    
                        //get hasil uji untuk lhps detail
                        $hasilUji = null;
                        if (!empty($headerPositions['HASILUJI'])) {
                            $hasilRow = $headerPositions['HASILUJI']['row'];
                            $hasilCol = $headerPositions['HASILUJI']['col'];
                            $kolomHas = $this->number_to_alphabet($hasilCol + 1);
                            $barisHas = $hasilRow + 3 + $no; // Sesuaikan dengan nomor urut data
                            $hasilUji = $worksheet->getCell($kolomHas . $barisHas)->getOldCalculatedValue()
                                ?? $worksheet->getCell($kolomHas . $barisHas)->getCalculatedValue()
                                ?? $worksheet->getCell($kolomHas . $barisHas)->getValue();
                        }
    
                        $insert = [
                            'id_header' => $header,
                            // 'akr' => isset($value[0]) ? $value[0] : null,
                            'parameter_lab' => $parameter_lab,
                            'parameter' => $value[1],
                            'durasi' => $durasi,
                            'hasil_uji' => $hasilUji,
                            'baku_mutu' => json_encode($val),
                            'satuan' => $satuan,
                            // 'methode' => $lastValue !== '-' ? str_replace('\\', '', $lastValue) : null,
                        ];
    
                        $detail = DB::table('lhps_emisi_detail')->insert($insert);
                    }
                    $no++;
                }
                // dump(DB::table('lhps_emisi_detail')->where('id_header', $header)->get());
                DB::commit();
                return response()->json([
                    'message' => 'Data berhasil diimport',
                    'header_id' => $header
                ], 201);
            }
        } catch (Exception $e) {
            DB::rollBack();
            // Log error untuk debugging
            \Log::error('Import LHP Emisi Error: ' . $e->getMessage());
            \Log::error('Import LHP Emisi Trace: ' . $e->getTraceAsString());

            return response()->json([
                'message' => 'Terjadi kesalahan saat mengimport data',
                'error' => $e->getMessage() . ', Line : ' . $e->getLine()
            ], 500);
        }
    }
}
