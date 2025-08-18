<?php

namespace App\Services;

use App\Models\HistoriPrinting;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class Printing
{
    protected static $apikey;
    protected static $url = 'http://10.88.4.160:1126/';
    protected static $karyawan;
    protected static $file;
    protected static $printer;
    protected static $filename;
    protected static $printer_name;
    protected static $destination;
    protected static $pages;
    protected static $copies;

    public static function get()
    {
        if(empty(self::$apikey)) {
            self::$apikey = env('PRINT_NODE_API_KEY');
        }

        $response = Http::withBasicAuth(self::$apikey, '')
            ->get(self::$url."printers");

        if ($response->failed()) {
            throw new \Exception('Failed to get printers');
        }
        
        $result = [];
        foreach ($response->json() as $item) {
            $result[] = [
                'id' => $item['id'],
                'description' => $item['description'],
                'state' => $item['state'],
                'computer_state' => $item['computer']['state'],
                'destination' => $item['name']
            ];
        }

        return $result;
    }

    public static function where($type, $value)
    {
        if($type == 'pdf' || $type == 'docx' || $type == 'doc' || $type == 'xlsx' || $type == 'xls') {
            if(empty($value)) {
                throw new \Exception('File is required');
            }
            self::$file = $value;
        }
        if($type == 'printer') {
            if(empty($value)) {
                throw new \Exception('Printer is required');
            }
            self::$printer = $value;
        }
        if($type == 'karyawan') {
            if(empty($value)) {
                throw new \Exception('Karyawan is required');
            }
            self::$karyawan = $value;
        }
        if($type == 'filename') {
            if(empty($value)) {
                throw new \Exception('Filename is required');
            }
            self::$filename = $value;
        }

        if($type == 'printer_name') {
            if(empty($value)) {
                throw new \Exception('Printer name is required');
            }
            self::$printer_name = $value;
        }

        if($type == 'destination') {
            self::$destination = $value;
        }

        if($type == 'pages') {
            self::$pages = $value;
        }
        
        if($type == 'copies') {
            self::$copies = $value;
        }

        return new static;
    }

    public static function print()
    {
        if(empty(self::$apikey)) {
            self::$apikey = env('PRINT_NODE_API_KEY');
        }

        if(empty(self::$printer)) {
            throw new \Exception('Printer is required');
        }

        if(empty(self::$file)) {
            throw new \Exception('File is required'); 
        }

        if(empty(self::$karyawan)) {
            throw new \Exception('Karyawan is required');
        }

        if(empty(self::$filename)) {
            throw new \Exception('Filename is required');
        }

    //    dd(self::$filename, self::$printer, self::$karyawan, self::$printer_name, self::$destination, self::$pages, self::$copies);

        // Get file extension
        $extension = strtolower(pathinfo(self::$filename, PATHINFO_EXTENSION));

        // Set content type based on file extension
        if ($extension == 'pdf') {
            $contentType = 'pdf_uri';
        } else {
            throw new \Exception('Unsupported file type');
        }

        $filename = explode('/', self::$filename);
        $filename = end($filename);
        

        
        // $printJob = Http::withHeaders([
        //     'Content-Type' => 'application/json'
        // ])->post(self::$url.'print', [
        //         'printer_name' => self::$printer,
        //         'filename' => $filename,
        //         'pages' => self::$pages,
        //         'copies' => (int) self::$copies
        //     ]);

        $printJob = Http::attach('file', fopen(self::$file, 'r'), $filename)
                        ->post(self::$url.'print', [
                        'printer_name' => self::$printer_name,
                        'pages' => self::$pages,
                        'copies' => self::$copies,
                        'fit_to_page' => true
                    ]);

        if ($printJob->failed()) {
            $error = json_decode($printJob->body(), true);
            $return_error = $error['message'] ?? $error['detail'];
            throw new \Exception($return_error);
        }

        $response = $printJob->json();

        if($response['status'] == 'error') {
            throw new \Exception($response['message']);
        }
        
        if ($printJob->failed()) {
            throw new \Exception('Failed to print');
        }

        HistoriPrinting::create([
            'filename' => $filename,
            'karyawan' => self::$karyawan,
            'status' => $printJob->status(),
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'printer' => self::$printer_name,
            'destination' => self::$destination
        ]);

        return $printJob->status();
    }
}