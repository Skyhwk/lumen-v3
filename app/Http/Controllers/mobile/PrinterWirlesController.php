<?php

namespace App\Http\Controllers\mobile;

use Laravel\Lumen\Routing\Controller as BaseController; // Pastikan mengarah ke Base Controller Lumen Anda
use Illuminate\Http\Request;

class PrinterWirlesController extends BaseController
{
    public function testPrinter()
    {
        $payload = [];
        $htmlReceipt = "
        <div style='font-family: \"Courier New\", Courier, monospace; font-size: 14px; width: 100%; color: #000;'>
            <div style='text-align: center; font-weight: bold; font-size: 16px; margin-bottom: 2px;'>SUPERMARKET MAJU JAYA</div>
            <div style='text-align: center; font-size: 12px; line-height: 1.2;'>
                Jl. Teknologi Industri No. 99, Jakarta<br>
                Telp: 0812-3456-7890
            </div>
            
            <div style='border-bottom: 1px dashed #000; margin: 8px 0;'></div>
            
            <table style='width: 100%; font-size: 12px; line-height: 1.4;'>
                <tr>
                    <td>Kasir: Samsul Rizal</td>
                    <td style='text-align: right;'>INV-089123456</td>
                </tr>
                <tr>
                    <td colspan='2'>Waktu: 20-05-2026 15:30:00</td>
                </tr>
            </table>
            
            <div style='border-bottom: 1px dashed #000; margin: 8px 0;'></div>
            
            <table style='width: 100%; font-size: 13px; border-collapse: collapse; line-height: 1.4;'>
                <tr>
                    <td colspan='2' style='font-weight: bold;'>Indomie Goreng Spesial</td>
                </tr>
                <tr>
                    <td style='color: #333;'>3 x Rp 3.000</td>
                    <td style='text-align: right;'>Rp 9.000</td>
                </tr>
                <tr>
                    <td colspan='2' style='font-weight: bold;'>Beras Maknyus Premium 5Kg</td>
                </tr>
                <tr>
                    <td style='color: #333;'>1 x Rp 65.000</td>
                    <td style='text-align: right;'>Rp 65.000</td>
                </tr>
                <tr>
                    <td colspan='2' style='font-weight: bold;'>Minyak Goreng Sunco 2L</td>
                </tr>
                <tr>
                    <td style='color: #333;'>2 x Rp 38.000</td>
                    <td style='text-align: right;'>Rp 76.000</td>
                </tr>
            </table>
            
            <div style='border-bottom: 1px dashed #000; margin: 8px 0;'></div>
            
            <table style='width: 100%; font-size: 13px; line-height: 1.5;'>
                <tr style='font-weight: bold;'>
                    <td>Total Belanja</td>
                    <td style='text-align: right;'>Rp 150.000</td>
                </tr>
                <tr>
                    <td>Tunai</td>
                    <td style='text-align: right;'>Rp 200.000</td>
                </tr>
                <tr style='font-weight: bold;'>
                    <td>Kembalian</td>
                    <td style='text-align: right;'>Rp 50.000</td>
                </tr>
            </table>
            
            <div style='border-bottom: 1px dashed #000; margin: 8px 0;'></div>
            
            <div style='text-align: center; font-size: 11px; line-height: 1.3; margin-bottom: 10px;'>
                Terima Kasih Atas Kunjungan Anda<br>
                Barang yang sudah dibeli tidak dapat ditukar/dikembalikan.
            </div>
        </div>
        ";

        // Masukkan objek HTML ke payload
        $struk = new \stdClass();
        $struk->type = 4; // lihat di document
        $struk->content = $htmlReceipt;
        array_push($payload, $struk);

        // 2. MEMBUAT BARCODE (Tetap pisah di luar HTML agar barcodenya di-render hardware printer secara sempurna)
        $barcode = new \stdClass();
        $barcode->type = 2; // lihat di document
        $barcode->value = "INV089123456"; 
        $barcode->width = 120; // Sesuai kertas 80mm
        $barcode->height = 50; 
        $barcode->align = 1; // Center
        array_push($payload, $barcode);

        // 3. SPASI KOSONG PENUTUP (Paper Feed)
        $space = new \stdClass();
        $space->type = 0; // lihat di document
        $space->content = "<br /><br /><br />"; 
        $space->bold = 0;
        $space->align = 0;
        array_push($payload, $space);

        // Render JSON Object murni untuk aplikasi Android
        $jsonResult = json_encode($payload, JSON_FORCE_OBJECT);

        return response($jsonResult, 200)
                ->header('Content-Type', 'application/json');
    }
}