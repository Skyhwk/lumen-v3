<?php

namespace App\Services;

use App\Models\GenerateLink;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GenerateToken
{
    public function save($type_doc, $data, $generated_by, $type) 
    {
        DB::beginTransaction();
        try {
            // $expired = $data->expired;
            if($type_doc == 'kontrak'){
                // if(is_null($data->expired)){
                    if(isset($data->periode_kontrak_akhir)){
                        $expired = Carbon::createFromFormat('m-Y', $data->periode_kontrak_akhir)
                                ->addMonths(1)
                                ->format('Y-m-d');
                    }else{
                        $expired = Carbon::createFromFormat('Y-m-d', $data->tanggal_penawaran)
                                ->addMonths(3)
                                ->format('Y-m-d');
                    }
                // }
            }else if( $type_doc == 'non_kontrak' ){
                // if(is_null($data->expired)){
                    $created_at = Carbon::createFromFormat('Y-m-d H:i:s', $data->created_at);
                    $tanggal_penawaran = Carbon::createFromFormat('Y-m-d', $data->tanggal_penawaran);
                        if ($created_at->year == $tanggal_penawaran->year) {
                            $expired = $tanggal_penawaran
                                        ->copy()
                                        ->addMonths(3)
                                        ->format('Y-m-d');
                        } else {
                            $expired = $created_at
                                        ->copy()
                                        ->addMonth(1)
                                        ->format('Y-m-d');
                        }
                // }
            } else if( $type_doc == 'INVOICE' ){
                // if(is_null($data->expired)){
                    $expired = $data->expired;
                // }
            }



            $key = $data->created_by . str_replace('.', '', microtime(true));
            $gen = MD5($key);
            $gen_tahun = self::encrypt(DATE('Y-m-d'));
            $token = self::encrypt($gen . '|' . $gen_tahun);

    
            $generateLink = new GenerateLink();
    
            $generateLink->token = $token;
            $generateLink->key = $gen;
            $generateLink->id_quotation = $data->id;
            $generateLink->quotation_status = $type_doc;
            $generateLink->expired = $expired;
            $generateLink->fileName_pdf = $data->filename;
            $generateLink->type = $type;
            $generateLink->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $generateLink->created_by = $generated_by;
    
            $generateLink->save();
            
            DB::commit();

            return (object)['id' => $generateLink->id, 'expired' => $expired];
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollback();
            dd($th);
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
