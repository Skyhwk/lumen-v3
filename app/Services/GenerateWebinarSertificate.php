<?php

namespace App\Services;

use Mpdf\Mpdf;
use Exception;
use App\Helpers\Helper;
use App\Models\QrDocument;
use Carbon\Carbon;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\View;

class GenerateWebinarSertificate
{
    private $full_name;
    private $id_sertifikat;
    private $no_sertifikat;
    private $folder_name;
    private $bg_img_path;
    private $prefix_filename;
    private $webinar_title;
    private $webinar_topic;
    private $webinar_date;
    private $top_distance = 30;
    private $pemateri;
    private $mpdf;
    private $template;
    private $font;
    private $qr_code;

    /**
     * Konstruktor untuk inisialisasi objek GenerateWebinarSertificate
     * 
     * @param string $full_name ex: "John Doe"
     * @param string $folder_name ex: "sertifikat_webinar"
     * @param string $bg_img_path ex: "sertifikat-bg.jpg"
     * @param string $prefix_filename ex: "sertifikat-"
     * @param string $webinar_title ex: "Webinar"
     * @param string $webinar_topic ex: "Bahasa Inggris"
     * @param string $webinar_date ex: "2023-06-01"
     * @param array $pemateri required ex: ["Pemateri 1", "Pemateri 2", "Pemateri 3"]
     * @param string $template ex: "default"
     * 
     * @return void
     */
    public function __construct(string $full_name){
        $this->full_name = $full_name;
    }

    /**
     * Generate sertifikat
     * 
     * @return string ex: "sertifikat-123.pdf"
     */
    public function generate(): string
    {
        try{
            // Validasi input
            $this->validateInputs();
            
            // Inisialisasi MPDF dengan font Great Vibes
            $this->initializeMpdf();
            
            // dd('masuk');
            // Generate sertifikat
            $this->createCertificate();
            
            // Buat nama file
            $filename = $this->generateFilename();
            $fullPath = $this->getFullPath($filename);
            
            // Simpan file
            $this->saveCertificate($fullPath);
            
            $this->resetParams();
            return $fullPath;
        } catch (Exception $e) {
            $this->resetParams();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'status' => 500
            ], 500);
        }
    }

    private function validateInputs(): void
    {
        if (empty($this->full_name)) {
            throw new Exception("Nama lengkap tidak boleh kosong");
        }

        // Cek file background di public/background-sertifikat
        if (!file_exists($this->bg_img_path)) {
            // Coba cari file gambar di folder background-sertifikat
            $bgFiles = glob(public_path('background-sertifikat/*.{jpg,jpeg,png,gif,bmp,webp}'), GLOB_BRACE);
            
            if (empty($bgFiles)) {
                throw new Exception("File background tidak ditemukan di: " . public_path('background-sertifikat'));
            }
            
            // Gunakan file pertama yang ditemukan
            $this->bg_img_path = $bgFiles[0];
        }

        // Validasi ekstensi file
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
        $extension = strtolower(pathinfo($this->bg_img_path, PATHINFO_EXTENSION));
        
        if (!in_array($extension, $allowedExtensions)) {
            throw new Exception("Format file background tidak didukung. Gunakan: " . implode(', ', $allowedExtensions));
        }

        // Buat folder penyimpanan jika belum ada
        if (!is_dir($this->folder_name)) {
            if (!mkdir($this->folder_name, 0755, true)) {
                throw new Exception("Gagal membuat folder: {$this->folder_name}");
            }
        }
        
        // Pastikan folder writable
        if (!is_writable($this->folder_name)) {
            throw new Exception("Folder tidak dapat ditulisi: {$this->folder_name}");
        }
    }

    private function initializeMpdf(): void
    {
        // Definisikan font Great Vibes
        $fontData = [
            'R' => public_path('fonts/GreatVibes-Regular.ttf'),
        ];

        // Konfigurasi MPDF dengan font Great Vibes
        $config = [
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'L', // Landscape untuk sertifikat
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
            'default_font_size' => 0,
            'tempDir' => storage_path('tmp/mpdf'),
            'default_font' => 'dejavusans',
            'fontdata' => [
                'dejavusans' => [
                    'R' => 'DejaVuSans.ttf',
                    'B' => 'DejaVuSans-Bold.ttf',
                    'I' => 'DejaVuSans-Oblique.ttf',
                    'BI' => 'DejaVuSans-BoldOblique.ttf',
                ],
                'greatvibes' => [
                    'R' => 'GreatVibes-Regular.ttf',
                ]
            ]
        ];

        // Cek apakah font Great Vibes tersedia
        $fontPath = public_path('fonts/GreatVibes-Regular.ttf');
        if (!file_exists($fontPath)) {
            // Jika font tidak ditemukan, download secara otomatis
            $this->downloadGreatVibesFont();
        }

        $this->mpdf = new Mpdf($config);

        $this->mpdf->SetDisplayMode('fullpage');
        $this->mpdf->SetAutoPageBreak(false);
        
        // Set metadata PDF
        $this->mpdf->SetTitle("Sertifikat - " . $this->full_name);
        $this->mpdf->SetAuthor("Inti Surya Laboratorium");
        $this->mpdf->SetCreator("Inti Surya Laboratorium");
    }

    private function downloadGreatVibesFont(): void
    {
        $fontUrl = 'https://github.com/google/fonts/raw/main/ofl/greatvibes/GreatVibes-Regular.ttf';
        $fontDir = public_path('fonts');
        $fontPath = $fontDir . '/GreatVibes-Regular.ttf';
        
        // Buat folder fonts jika belum ada
        if (!is_dir($fontDir)) {
            mkdir($fontDir, 0755, true);
        }
        
        // Download font dari Google Fonts
        $fontContent = @file_get_contents($fontUrl);
        if ($fontContent !== false) {
            file_put_contents($fontPath, $fontContent);
        } else {
            // Fallback ke font default jika gagal download
            throw new Exception("Font Great Vibes tidak ditemukan dan gagal didownload. Silakan upload font GreatVibes-Regular.ttf ke folder public/fonts/");
        }
    }

    private function createCertificate(): void
    {
        try {
            // Tambah halaman baru
            $this->mpdf->AddPage();

            // Dapatkan dimensi halaman
            $pageWidth = $this->mpdf->w;
            $pageHeight = $this->mpdf->h;

            // Konversi nama ke format yang tepat
            $convertedName = $this->formatName($this->full_name);
            // Hitung ukuran font berdasarkan panjang nama
            $fontSize = $this->calculateFontSize(strlen($convertedName));
            
            // Get image data
            $imageData = file_get_contents($this->bg_img_path);
            if ($imageData === false) {
                throw new Exception("Gagal membaca file background: {$this->bg_img_path}");
            }
            
            $base64 = base64_encode($imageData);
            $mimeType = mime_content_type($this->bg_img_path) ?: 'image/jpeg';
            $imageSrc = 'data:' . $mimeType . ';base64,' . $base64;

            // Generate QR Code
            $this->qr_code = $this->generateQrCode();

            $tanggalWebinar = !is_null($this->webinar_date) ? '<div>Tanggal ' . $this->webinar_date . '</div>' : '';

            // Render Blade template untuk konten pemateri dan QR code
            $templateContent = $this->renderTemplate($convertedName, $tanggalWebinar);

            // HTML dan CSS untuk sertifikat dengan posisi tepat di tengah
            $html = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    * {
                        margin: 0;
                        padding: 0;
                        box-sizing: border-box;
                    }
                    
                    @page {
                        margin: 0;
                        padding: 0;
                    }
                    
                    body {
                        width: ' . $pageWidth . 'mm;
                        height: ' . $pageHeight . 'mm;
                        position: relative;
                        overflow: hidden;
                        margin: 0;
                        padding: 0;
                    }
                    
                    .background-layer {
                        position: absolute;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        z-index: 0;
                    }
                    
                    .background-image {
                        width: 100%;
                        height: 100%;
                        object-fit: cover;
                    }
                    
                    .text-container {
                        position: absolute;
                        top: ' . $this->top_distance . '%;
                        left: 10%;
                        z-index: 1;
                        width: 80%;
                        text-align: center;
                    }
                    
                    ul {
                        list-style: none;
                        margin: 0;
                        padding: 0;
                    }

                    ul li {
                        margin-bottom: 20px;
                    }

                    .webinar-topic {
                        font-size: 13pt;
                        font-weight: bold;
                        text-align: center;
                        line-height: 1.2;
                        margin: 0;
                        padding: 0;
                        font-style: normal;
                        letter-spacing: 1px;
                        word-spacing: 3px;
                    }
                    
                    .certificate-name {
                        font-family: "'. $this->font .'", serif;
                        font-size: ' . $fontSize . 'pt;
                        color: #2c3e50;
                        text-align: center;
                        line-height: 1.2;
                        margin: 0;
                        padding: 0;
                        font-weight: normal;
                        font-style: normal;
                        letter-spacing: 1px;
                        word-spacing: 3px;
                    }

                    .webinar-detail-container {
                        font-family: "dejavusans", serif;
                    }

                    .layout-webinar-detail {
                        position: relative;
                        width: 100%;
                        height: 100%;
                    }

                    .qr-code-container {
                        position: absolute;
                        left: 10%;
                        z-index: 1;
                        width: 80%;
                    }

                    .qr-code-container img {
                        padding-top: 100px;
                    }
                </style>
            </head>
            <body>
                <!-- Background Layer -->
                <div class="background-layer">
                    <img src="' . $imageSrc . '" class="background-image" alt="Certificate Background" />
                </div>
                
                '. $templateContent .'
            </body>
            </html>';

            $this->mpdf->WriteHTML($html);
        } catch (Exception $e) {
            throw new Exception("Gagal membuat sertifikat: " . $e->getMessage());
        }
    }

    private function formatName(string $name): string
    {
        // Konversi nama ke format yang sesuai
        $name = trim($name);
        
        // Hapus karakter khusus dan extra spaces
        $name = preg_replace('/\s+/', ' ', $name);
        
        // Konversi ke Title Case (huruf pertama setiap kata besar, lainnya kecil)
        $name = ucwords(strtolower($name));
        
        // Untuk font Great Vibes, kita bisa biarkan natural tanpa uppercase
        return $name;
    }

    private function calculateFontSize(int $nameLength): int
    {
        // Ukuran font default untuk nama pendek (≤ 22 karakter)
        $defaultFontSize = 64;
        $maxCharacters = 22;
        $minFontSize = 32;
        
        // Jika jumlah karakter <= 22, gunakan ukuran font default
        if ($nameLength <= $maxCharacters) {
            return $defaultFontSize;
        }
        
        // Hitung pengurangan font size secara proporsional
        // Setiap 5 karakter tambahan, kurangi 4pt dari font size
        $excessCharacters = $nameLength - $maxCharacters;
        $reduction = ceil($excessCharacters / 5) * 10;
        $calculatedSize = $defaultFontSize - $reduction;

        $this->top_distance = $this->top_distance + 2;
        
        // Pastikan ukuran font tidak di bawah minimum
        return max($calculatedSize, $minFontSize);
    }

    private function generateFilename(): string
    {
        // Buat nama file yang aman
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $this->full_name);
        $safeName = substr($safeName, 0, 50);
        
        return $this->prefix_filename . $safeName . '.pdf';
    }

    private function getFullPath(string $filename): string
    {
        // Pastikan folder_name menggunakan DIRECTORY_SEPARATOR
        $folder = rtrim($this->folder_name, '/\\') . DIRECTORY_SEPARATOR;
        return $folder . $filename;
    }


    private function saveCertificate(string $fullPath): void
    {
        // Output ke file
        dd($fullPath);
        $this->mpdf->Output($fullPath, 'F');
        
        // Verifikasi file berhasil dibuat
        if (!file_exists($fullPath)) {
            throw new Exception("Gagal menyimpan file sertifikat: {$fullPath}");
        }
        
        // Verifikasi file tidak kosong
        if (filesize($fullPath) === 0) {
            throw new Exception("File sertifikat kosong: {$fullPath}");
        }
    }

    /**
     * Getter untuk informasi sertifikat
     */
    public function getCertificateInfo(): array
    {
        return [
            'full_name' => $this->full_name,
            'folder_name' => $this->folder_name,
            'bg_img_path' => $this->bg_img_path,
            'prefix_filename' => $this->prefix_filename,
            'background_exists' => file_exists($this->bg_img_path),
            'background_size' => file_exists($this->bg_img_path) ? filesize($this->bg_img_path) : 0,
            'font_exists' => file_exists(public_path('fonts/GreatVibes-Regular.ttf'))
        ];
    }

    /**
     * Method untuk output langsung ke browser
     */
    public function outputToBrowser(string $downloadName = null): void
    {
        if (!$this->mpdf) {
            $this->initializeMpdf();
            $this->createCertificate();
        }
        
        $filename = $downloadName ?: $this->prefix_filename . preg_replace('/[^a-zA-Z0-9]/', '_', $this->full_name) . '.pdf';
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        
        echo $this->mpdf->Output('', 'S');
        exit;
    }

    /**
     * Method untuk mendapatkan konten PDF sebagai string
     */
    public function getPdfContent(): string
    {
        if (!$this->mpdf) {
            $this->initializeMpdf();
            $this->createCertificate();
        }
        
        return $this->mpdf->Output('', 'S');
    }

    /**
     * Method untuk mengubah lokasi background image
     */
    public function setBackgroundImage(string $bg_img_path): self
    {
        $this->bg_img_path = $bg_img_path;
        return $this;
    }

    /**
     * Method untuk render Blade template untuk pemateri dan QR code
     */
    private function renderTemplate(string $full_name, string $webinar_date): string
    {
        try {
            // Validasi template file exists
            $templatePath = 'sertifikat-templates.' . $this->template;
            
            if (!View::exists($templatePath)) {
                throw new Exception("Template tidak ditemukan: resources/views/sertifikat-templates/{$this->template}.blade.php");
            }
            
            // dd($templatePath);
            // Data yang akan dikirim ke template
            $data = [
                'full_name' => $full_name,
                'pemateri' => $this->pemateri,
                'webinar_title' => $this->webinar_title,
                'webinar_topic' => $this->webinar_topic,
                'webinar_date' => $webinar_date,
                'qr_code' => $this->qr_code, // Untuk QR code
            ];

            // Render template
            return View::make($templatePath, $data)->render();
        } catch (Exception $e) {
            throw new Exception("Gagal render template: " . $e->getMessage());
        }
    }

    /**
     * Method untuk mengubah lokasi folder penyimpanan
     */
    public function setStorageFolder(string $folder_name): self
    {
        $this->folder_name = $folder_name;
        return $this;
    }

    /**
     * Method untuk mendapatkan relative URL dari file yang disimpan
     */
    public function getRelativeUrl(string $fullPath = null): string
    {
        if (!$fullPath) {
            $filename = $this->generateFilename();
            $fullPath = $this->getFullPath($filename);
        }
        
        // Konversi absolute path ke relative URL
        $basePath = public_path();
        if (strpos($fullPath, $basePath) === 0) {
            return str_replace($basePath, '', $fullPath);
        }
        
        return '/' . ltrim($fullPath, '/');
    }

    /**
     * Method untuk menghapus file sertifikat yang sudah dibuat
     */
    public static function deleteCertificate(string $filePath): bool
    {
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return false;
    }

    /**
     * Generate Qr Code For Tempalate
     * 
     */
    private function generateQrCode(): string
    {
        $qr = QrDocument::where('id_document', $this->id_sertifikat)
                ->where('type_document', 'e_certificate_webinar')
                ->where('is_active', 1)
                ->first();

        if (!$qr) $qr = new QrDocument();

        $qr->id_document = $this->id_sertifikat;
        $qr->type_document = 'e_certificate_webinar';
        $qr->kode_qr = $this->generateQr($this->no_sertifikat);
        $qr->file = str_replace("/", "_", $this->no_sertifikat);

        $qr->data = json_encode([
            'tipe_dokumen'          => 'e-certificate webinar',
            'penerima_sertifikat'   => $this->full_name,
            'no_sertifikat'         => $this->no_sertifikat,
            'judul_webinar'         => $this->webinar_title,
            'topik_webinar'         => $this->webinar_topic,
            'tanggal_webinar'       => $this->webinar_date,
            'panelis'               => $this->pemateri
        ]);

        $qr->created_by = 'SYSTEM';
        $qr->created_at = Carbon::now();

        $qr->save();

        // Qr Image Render
        $qr_img = '<img class="qr-code" src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="80px" height="80px" style="padding : 5px;border : 2px solid #ffffff; border-radius: 2px;">';

        return $qr_img;
    }

    private function generateQr($noDocument)
    {
        $filename = str_replace("/", "_", $noDocument);
        $dir = public_path("qr_documents");

        if (!file_exists($dir)) mkdir($dir, 0755, true);

        $path = $dir . "/$filename.svg";
        $link = 'https://www.intilab.com/validation/';
        $unique = 'isldc' . (int) floor(microtime(true) * 1000);

        QrCode::size(200)->color(255, 255, 255)        // QR putih
                ->backgroundColor(0, 0, 0, 0) // background transparan
                ->generate($link . $unique, $path);

        return $unique;
    }

    /**
     * Setter untuk full_name
     * 
     * @param string $full_name
     * @return self
     */
    public function setFullName(string $full_name): self
    {
        $this->full_name = $full_name;
        return $this;
    }

    /**
     * Setter untuk id_sertifikat
     * 
     * @param string $id_sertifikat
     * @return self
     */
    public function setIdSertifikat(string $id_sertifikat): self
    {
        $this->id_sertifikat = $id_sertifikat;
        return $this;
    }

    /**
     * Setter untuk no_sertifikat
     * 
     * @param string $no_sertifikat
     * @return self
     */
    public function setNoSertifikat(string $no_sertifikat): self
    {
        $this->no_sertifikat = $no_sertifikat;
        return $this;
    }

    /**
     * Setter untuk folder_name
     * 
     * @param string $folder_name
     * @return self
     */
    public function setFolderName(string $folder_name): self
    {
        $this->folder_name = $folder_name;
        return $this;
    }

    /**
     * Setter untuk bg_img_path
     * 
     * @param string|null $bg_img_path
     * @return self
     */
    public function setBgImgPath(string $bg_img_path = null): self
    {
        $this->bg_img_path = $bg_img_path ?? public_path('background-sertifikat/certificate-bg.jpg');
        return $this;
    }

    /**
     * Setter untuk prefix_filename
     * 
     * @param string $prefix_filename
     * @return self
     */
    public function setPrefixFilename(string $prefix_filename): self
    {
        $this->prefix_filename = $prefix_filename;
        return $this;
    }

    /**
     * Setter untuk webinar_title
     * 
     * @param string $webinar_title
     * @return self
     */
    public function setWebinarTitle(string $webinar_title): self
    {
        $this->webinar_title = $webinar_title;
        return $this;
    }

    /**
     * Setter untuk webinar_topic
     * 
     * @param string $webinar_topic
     * @return self
     */
    public function setWebinarTopic(string $webinar_topic): self
    {
        $this->webinar_topic = strtoupper($webinar_topic);
        return $this;
    }

    /**
     * Setter untuk webinar_date
     * 
     * @param string|null $webinar_date
     * @return self
     */
    public function setWebinarDate(string $webinar_date = null): self
    {
        $this->webinar_date = $webinar_date ? Helper::tanggal_indonesia($webinar_date) : null;
        return $this;
    }

    /**
     * Setter untuk top_distance
     * 
     * @param int $top_distance
     * @return self
     */
    public function setTopDistance(int $top_distance): self
    {
        $this->top_distance = $top_distance;
        return $this;
    }

    /**
     * Setter untuk pemateri
     * 
     * @param array $pemateri
     * @return self
     */
    public function setPemateri(array $pemateri): self
    {
        $this->pemateri = $pemateri;
        return $this;
    }

    /**
     * Setter untuk template
     * 
     * @param string $template
     * @return self
     */
    public function setTemplate(string $template): self
    {
        $this->template = $template;
        return $this;
    }

    /**
     * Setter untuk font
     * 
     * @param string $font
     * @return self
     */
    public function setFont(string $font): self
    {
        $this->font = strtolower(str_replace(' ', '', $font));
        return $this;
    }

    private function resetParams()
    {
        $this->id_sertifikat = null;
        $this->full_name = null;
        $this->no_sertifikat = null;
        $this->webinar_title = null;
        $this->webinar_topic = null;
        $this->webinar_date = null;
        $this->pemateri = null;
        $this->qr_code = null;
    }
}