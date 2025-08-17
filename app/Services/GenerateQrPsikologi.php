<?php

namespace App\Services;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Label\Font\NotoSans;
use Endroid\QrCode\Label\Alignment\LabelAlignmentCenter;
use Endroid\QrCode\Label\LabelOptions;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\Color\Color;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GenerateQrPsikologi
{
    public function insert($data, $generated_by)
    {   
        // dd($data);
        $key = $data->no_document . str_replace('.', '', microtime(true));
        $gen = MD5($key);
        $gen_tahun = self::encrypt(DATE('Y-m-d'));
        $token = self::encrypt($gen . '|' . $gen_tahun);
        $quot_type = explode('/', $data->no_document)[1] == 'QT' ? 'non_kontrak' : 'kontrak';
        
        $cek = DB::table('qr_psikologi')->where('id_quotation' , $data->id)
            ->whereJsonContains('data->no_document', $data->no_document)
            ->where('type', $data->type)
            ->where('periode', $quot_type == 'kontrak' ? $data->periode_kontrak : null)
            ->first();
        // dd($cek);
        if($cek != null) return $cek->file;
        DB::beginTransaction();
        try {
            $filename = $data->type . "_" . \str_replace("/", "_", $data->no_document) . ($quot_type == 'kontrak' ? '_' . $data->periode_kontrak : '');
            $path = public_path() . "/qr_psikologi/" . $filename . '.png';
            $link = 'https://portal.intilab.com/public/psikologi/';
            $unique = 'islps' . (int)floor(microtime(true) * 1000);
            $qrCode = QrCode::create($link . $token)
                ->setEncoding(new Encoding('UTF-8'))
                ->setSize(250)
                ->setMargin(15)
                ->setForegroundColor(new Color(0, 0, 0)) 
                ->setBackgroundColor(new Color(255, 255, 255));

            $isi_teks = $data->type == 'audiens' ? 'Qr Audiens' : 'Qr Administrator';
            
            $label = Label::create($isi_teks);
            
            $logoPath = public_path(). '/logo-watermark.png';
            if (file_exists($logoPath)) {
                $logo = Logo::create($logoPath)->setResizeToWidth(50);
            } else {
                $logo = null;
            }
            // dd($logo);
            $writer = new PngWriter();
            $result = $writer->write($qrCode, $logo, $label);
            // dd($result);
            $result->saveToFile($path);
            
            $dataQr = [
                'id_quotation' => $data->id,
                'kode_qr' => $unique,
                'file' => $filename . '.png',
                'data' => json_encode([
                    'no_document' => $data->no_document,
                    'nama_customer' => $data->nama_perusahaan,
                    'no_order' => $data->no_order,
                    'tipe' => $data->type,
                    'periode' => $data->periode_kontrak
                ]),
                'periode' => $data->periode_kontrak,
                'type' => $data->type,
                'token' => $token,
                'expired' => $data->type == 'audiens' ? Carbon::parse($data->tanggal_sampling . ' 00:00:00')
                                ->addWeekdays(10)
                                ->format('Y-m-d H:i:s') : null,
                'key' => $key,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'created_by' => $generated_by
            ];
            // dd($dataQr);
            DB::table('qr_psikologi')->insert($dataQr);
            DB::commit();

            return $filename;
        } catch (\Exception $th) {
            dd($th);
            if(isset($path) && file_exists($path)) unlink($path);
            if(isset($filename) && file_exists(public_path() . "/qr_psikologi/" . $filename . '.png')) unlink(public_path() . "/qr_psikologi/" . $filename . '.png');
            if(isset($logoPath) && file_exists($logoPath)) unlink($logoPath);
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage()
            ],500);
        }
    }

    public function encrypt($data)
    {
        $ENCRYPTION_KEY = 'intilab_jaya';
        $ENCRYPTION_ALGORITHM = 'AES-256-CBC';
        $EncryptionKey = base64_decode($ENCRYPTION_KEY);
        $InitializationVector = openssl_random_pseudo_bytes(openssl_cipher_iv_length($ENCRYPTION_ALGORITHM));
        $EncryptedText = openssl_encrypt($data, $ENCRYPTION_ALGORITHM, $EncryptionKey, 0, $InitializationVector);

        return base64_encode($EncryptedText . '::' . $InitializationVector);
    }
}
