<?php

namespace App\Http\Controllers\api;


use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use App\Models\JenisFont;
use App\Models\LayoutCertificate;
use App\Models\SertifikatWebinarDetail;
use App\Models\SertifikatWebinarHeader;
use App\Models\TemplateBackground;
use App\Services\GenerateWebinarSertificate;
use App\Models\WebinarQna;
use App\Services\SendEmail;
use Yajra\Datatables\Datatables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Repository;

class SertifikatWebinarController extends Controller
{
    public function index()
    {
        $data = SertifikatWebinarHeader::get();

        return Datatables::of($data)->make(true);
    }

    public function details(Request $request)
    {
        $data = SertifikatWebinarDetail::where('header_id', $request->id)->get();

        return Datatables::of($data)->make(true);
    }

    private function generateWebinarCode(string $date, $existingCodes): string
    {
        /**
         * $existingCodes = ISL012601 -> no urut webinar adalah 01 setelah webinar jadi kombinasi terdiri dari
         *  ISL -> kode perusahaan
         *  01 -> urutan webinar di adakan (reset setiap tahun baru)
         *  26 -> tahun webinar di adakan
         *  01 -> bulan webinar di adakan
         */ 
        $companyCode = 'ISL';
        $year = date('y', strtotime($date)); // 2 digit tahun
        $month = date('m', strtotime($date)); // 2 digit bulan
        $sequence = 1;
        
        foreach ($existingCodes as $code => $value) {
            if (strlen($code) === 9 && substr($code, 0, 3) === $companyCode) {
                $codeYear = substr($code, 5, 2);
                if ($codeYear === $year) {
                    $codeSequence = (int) substr($code, 3, 2);
                    if ($codeSequence >= $sequence) {
                        $sequence = $codeSequence + 1;
                    }
                }
            }
        }
        
        $sequenceStr = str_pad($sequence, 2, '0', STR_PAD_LEFT);
        $webinarCode = $companyCode . $sequenceStr . $year . $month;
        
        return $webinarCode;
    }

    public function storeHeader(Request $request)
    {
        DB::beginTransaction();
        try {
            $existingCodes = SertifikatWebinarHeader::pluck('webinar_code')
                ->map(fn($v) => strtoupper($v))
                ->flip();
            
            $webinarCode = $this->generateWebinarCode(
                $request->date,
                $existingCodes
            );
            
            SertifikatWebinarHeader::create([
                'webinar_code' => strtoupper($webinarCode),
                'title' => $request->title,
                'topic' => $request->topic,
                'sub_topic' => $request->sub_topic,
                'speakers' => json_decode($request->speakers, true),
                'date' => $request->date,
                'id_template' => $request->template_id,
                'id_layout' => $request->layout_id,
                'id_font' => $request->font_id,
                'created_at' => Carbon::now(),
                'created_by' => $this->karyawan
            ]);

            DB::commit();
            return response()->json(['message' => 'Berhasil Membuat Webinar', 'status' => '200'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal Membuat Webinar',
                'line' => $th->getLine(),
                'status' => '500'
            ], 500);
        }
    }

    public function importDataAudience(Request $request)
    {
        $file = $request->file('file_input');

        // Validasi file - terima xlsx dan csv
        if (!$file) {
            return response()->json(['error' => 'File tidak ditemukan'], 400);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['xlsx', 'csv'])) {
            return response()->json(['error' => 'File tidak valid. Harus .xlsx atau .csv'], 400);
        }
        
        DB::beginTransaction();
        try {
            if ($extension === 'csv') {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Csv');
                $reader->setDelimiter(',');
                $reader->setEnclosure('"');
                $reader->setSheetIndex(0);
                $spreadsheet = $reader->load($file->getPathname());
            } else {
                $spreadsheet = IOFactory::load($file->getPathname());
            }

            $sheet = $spreadsheet->getActiveSheet();

            // Cari row yang mengandung "Question Details" di kolom A
            $startRow = null;
            $highestRow = $sheet->getHighestRow();

            for ($i = 1; $i <= $highestRow; $i++) {
                $cellValue = $sheet->getCell('A' . $i)->getFormattedValue();

                // Cek apakah cell mengandung "Question Details" (case insensitive)
                if ($cellValue && stripos($cellValue, 'Attendee Details') !== false) {
                    $startRow = $i + 2; // Mulai dari row setelahnya (skip header)
                    break;
                }
            }

            // Jika tidak ketemu "Question Details", return error
            if ($startRow === null) {
                DB::rollBack();
                return response()->json([
                    'error' => 'Header "Attendee Details" tidak ditemukan di kolom A',
                    'status' => '400'
                ], 400);
            }

            // Loop dari row yang sudah didetect
            $attendances = [];
            $importedCount = 0;
            $code = SertifikatWebinarHeader::find($request->id)->webinar_code;
            $max = SertifikatWebinarDetail::where('header_id', $request->id)
                ->max('number_attend');

            $numberAttend = ((int) $max) + 1;

            // 🔥 Ambil data existing SEKALI
            $existing = SertifikatWebinarDetail::where('header_id', $request->id)
                ->get()
                ->keyBy(fn($row) => strtolower($row->email . '|' . $row->name));

            for ($rowIndex = $startRow; $rowIndex <= $highestRow; $rowIndex++) {
                $name  = trim($sheet->getCell('C' . $rowIndex)->getFormattedValue()) . ' '. trim($sheet->getCell('D' . $rowIndex)->getFormattedValue());
                $email = trim($sheet->getCell('E' . $rowIndex)->getFormattedValue());
                $time_session = trim($sheet->getCell('J' . $rowIndex)->getFormattedValue());

                if (!$name || !$email || $time_session === '') continue;
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;

                $time_session = (int) $time_session;
                $key = strtolower($email . '|' . $name);

                if (isset($attendances[$key])) {
                    $attendances[$key]['time_session'] += $time_session;
                    continue;
                }

                if (isset($existing[$key])) {
                    $useNumberAttend = $existing[$key]->number_attend;
                } else {
                    $useNumberAttend = sprintf('%04d', $numberAttend);
                    $numberAttend++;
                    $importedCount++;
                }

                $attendances[$key] = [
                    'header_id'     => $request->id,
                    'number_attend' => $useNumberAttend,
                    'name'          => $name,
                    'email'         => $email,
                    'time_session'  => ($time_session > 180 ? 180 : $time_session),
                    // 'filename'      => $code . '-' . $useNumberAttend . '.pdf',
                ];
            }
            
            if (empty($attendances)) {
                DB::rollBack();
                return response()->json([
                    'error' => 'Tidak ada data valid untuk diimport',
                    'status' => '400'
                ], 400);
            }
            // hilangkan data jika time_session < 60
            foreach ($attendances as $key => $value) {
                if ($value['time_session'] < 60) {
                    unset($attendances[$key]);
                }
            }

            SertifikatWebinarDetail::upsert(
                array_values($attendances),
                ['header_id', 'email', 'name'],
                ['time_session','filename']
            );

            DB::commit();
    
            self::bulkGenerateCertificate($request->id);

            // Http::post('http://127.0.0.1:2999/render-sertifikat', ["id" => $request->id]);
                

            return response()->json(['message' => 'Berhasil mengimport data', 'status' => '200'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' =>
                'Gagal mengimport data',
                'line' => $th->getLine(),
                'status' => '500'
            ], 500);
        }
    }

    public function bulkGenerateCertificate(int $id)
    {
        $getHeader = SertifikatWebinarHeader::with(['details'])->where('id', $id)->first();
        $getDetail = $getHeader->details;
        $layout = LayoutCertificate::where('id', $getHeader->id_layout)->first();
        $font = JenisFont::where('id', $getHeader->id_font)->first();
        $template = TemplateBackground::where('id', $getHeader->id_template)->first();
        foreach ($getDetail as $key => $value) {
            /**
             * Mulai generate sertifikat satu per satu
             */
            $no_sertifikat = $getHeader->webinar_code . '-' . $value->number_attend;
            $filename = $no_sertifikat . '.pdf';
            $generate = GenerateWebinarSertificate::make($filename)
            ->options([
                'layout'            => $layout->nama_file,
                'font'              => $font->jenis_font ?? 'roboto',
                'template'          => $template->nama_template,
                'recipientName'     => $value->name,
                'id'                => $value->id,
                'webinarTitle'      => $getHeader->title,
                'webinarTopic'      => $getHeader->topic,
                'webinarSubTopic'   => $getHeader->sub_topic,
                'webinarDate'       => $getHeader->date,
                'panelis'           => $getHeader->speakers,
                'noSertifikat'      => $no_sertifikat,
            ])
            ->generate();

            if($generate instanceof Exception) {
                return response()->json([
                    'message' => 'Gagal menggenerate sertifikat',
                    'line' => $generate->getLine(),
                    'status' => '500'
                ], 500);
            }

            $value->update([
                'filename' => $filename
            ]);


        }
    }

    public function importDataQna(Request $request)
    {
        $file = $request->file('file_input');

        // Validasi file - terima xlsx dan csv
        if (!$file) {
            return response()->json(['error' => 'File tidak ditemukan'], 400);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['xlsx', 'csv'])) {
            return response()->json(['error' => 'File tidak valid. Harus .xlsx atau .csv'], 400);
        }

        DB::beginTransaction();
        try {
            // Untuk CSV, set delimiter secara eksplisit
            if ($extension === 'csv') {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Csv');
                $reader->setDelimiter(',');
                $reader->setEnclosure('"');
                $reader->setSheetIndex(0);
                $spreadsheet = $reader->load($file->getPathname());
            } else {
                $spreadsheet = IOFactory::load($file->getPathname());
            }

            $sheet = $spreadsheet->getActiveSheet();

            // Cari row yang mengandung "Question Details" di kolom A
            $startRow = null;
            $highestRow = $sheet->getHighestRow();

            for ($i = 1; $i <= $highestRow; $i++) {
                $cellValue = $sheet->getCell('A' . $i)->getFormattedValue();

                // Cek apakah cell mengandung "Question Details" (case insensitive)
                if ($cellValue && stripos($cellValue, 'Question Details') !== false) {
                    $startRow = $i + 2; // Mulai dari row setelahnya (skip header)
                    break;
                }
            }

            // Jika tidak ketemu "Question Details", return error
            if ($startRow === null) {
                DB::rollBack();
                return response()->json([
                    'error' => 'Header "Question Details" tidak ditemukan di kolom A',
                    'status' => '400'
                ], 400);
            }

            // Loop dari row yang sudah didetect
            $qna = [];
            $importedCount = 0;

            for ($rowIndex = $startRow; $rowIndex <= $highestRow; $rowIndex++) {
                $question  = $sheet->getCell('B' . $rowIndex)->getFormattedValue();
                $asker_name  = $sheet->getCell('C' . $rowIndex)->getFormattedValue();
                $asker_email  = $sheet->getCell('D' . $rowIndex)->getFormattedValue();
                $answer  = $sheet->getCell('E' . $rowIndex)->getFormattedValue();
                $question_time  = $sheet->getCell('F' . $rowIndex)->getFormattedValue();
                $answered_time  = $sheet->getCell('G' . $rowIndex)->getFormattedValue();
                $answerer_name = $sheet->getCell('H' . $rowIndex)->getFormattedValue();
                $answerer_email = $sheet->getCell('I' . $rowIndex)->getFormattedValue();

                // Skip jika data wajib kosong
                if (!$asker_name || !$asker_email || !$question) {
                    continue;
                }

                // Bersihkan whitespace
                $asker_name = trim($asker_name);
                $asker_email = trim($asker_email);
                $question = trim($question);

                // Validasi email format
                if (!filter_var($asker_email, FILTER_VALIDATE_EMAIL)) {
                    continue; // Skip invalid email
                }

                $qna[] = [
                    'webinar_id' => $request->id,
                    'asker_name' => $asker_name,
                    'asker_email' => $asker_email,
                    'question' => $question,
                    'answered_on_webinar' => trim($answer) === 'live answered' ? 1 : 0,
                    'answer' => $answer ? trim($answer) : null,
                    'question_time' => $question_time
                        ? Carbon::createFromFormat('m/d/Y h:i:s A', $question_time)->format('Y-m-d H:i:s')
                        : null,
                    'answered_time' => $answered_time
                        ? Carbon::createFromFormat('m/d/Y h:i:s A', $answered_time)->format('Y-m-d H:i:s')
                        : null,
                    'answerer_name' => $answerer_name ? trim($answerer_name) : null,
                    'answerer_email' => $answerer_email ? trim($answerer_email) : null,
                ];
                $importedCount++;
            }

            // Cek apakah ada data yang akan diimport
            if (empty($qna)) {
                DB::rollBack();
                return response()->json([
                    'error' => 'Tidak ada data valid untuk diimport',
                    'status' => '400'
                ], 400);
            }
            // Insert batch
            WebinarQna::insert($qna);

            SertifikatWebinarHeader::where('id', $request->id)->update(['qna_uploaded' => '1']);
            DB::commit();

            return response()->json([
                'message' => "Berhasil mengimport {$importedCount} data",
                'imported' => $importedCount,
                'start_row' => $startRow,
                'status' => '200'
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal mengimport data',
                'error' => $th->getMessage(),
                'line' => $th->getLine(),
                'status' => '500'
            ], 500);
        }
    }

    public function renderSertifikatOld(Request $request) {
        $header = SertifikatWebinarHeader::with('details', 'layout', 'font', 'template')->find($request->id);
        $title = $header->title;
        $topic = $header->topic;
        $webinarDate = $header->date;
        $panelis = [];
        foreach ($header->speakers as $detail) {
            $panelis[] = '<strong>' . $detail['nama']  . '</strong> (' . $detail['jabatan'] . ')';
        }
        $font = [
            'fontName' => $header->font->jenis_font,
            'filename' => $header->font->filename
        ];
        $template = $header->template->file;
        $layout = $header->layout->nama_blade;
        $code = $header->webinar_code;
        $year = date('Y');
        $month = date('m');
        $no_sertifikat = 'ISL/' . $code . '/' . substr($year, -2) . '-' . self::monthToRoman($month) . '/';
        foreach ($header->details as $detail) {
            $filename = $code . '-' . $detail->number_attend . '-' .  $detail->name . '.pdf';
            // dd($filename);
            $generateService = GenerateWebinarSertificate::make($filename)
            ->options([
                'id' => $detail->id,
                'template' => $template, //--> background image
                'layout' => $layout, //--> layout blade
                'font' => $font, //--> font fullname recipient certificate
                'recipientName' => $detail->name,
                'webinarTitle' => $title,
                'webinarTopic' => $topic,
                'webinarDate' => $webinarDate,
                'panelis' => $panelis,
                'noSertifikat' => $no_sertifikat . $detail->number_attend,
            ])
            ->generate();

        }
        
        SertifikatWebinarDetail::where('header_id', $request->id)->update(['sertifikat_generated' => '1']);

        return response()->json(['message' => `Sertifikat Webinar {$header->title} berhasil di generate`], 200);
    }

    public function getTemplate()
    {
        $jenis_font = JenisFont::select('id', 'jenis_font')->where('is_active', true)->get();
        $template = TemplateBackground::select('id', 'nama_template')->where('is_active', true)->get();
        $layout = LayoutCertificate::select('id', 'id_template', 'nama_file')->get();

        return response()->json([
            'message' => 'Data hasbeen show',
            'data' => [
                'fonts' => $jenis_font,
                'templates' => $template,
                'layouts' => $layout
            ],
        ], 200);
    }

    public function getKaryawan()
    {
        $data = MasterKaryawan::where('is_active', true)
            ->select('nama_lengkap', 'jabatan', 'id')
            ->get();
        return response()->json(['message' => 'Data hasbeen show', 'data' => $data], 200);
    }

    public function delete(Request $request)
    {
        $data = SertifikatWebinarHeader::where('id', $request->id)->delete();
        return response()->json(['message' => 'Data Berhasil dihapus'], 200);
    }

    public function getQna(Request $request)
    {
        $query = WebinarQna::where('webinar_id', $request->header_id);

        // Jika ada parameter search, filter berdasarkan multiple fields
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;

            $query->where(function ($q) use ($searchTerm) {
                $q->where('asker_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('asker_email', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('question', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('answer', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('answerer_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('answerer_email', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Order by: yang belum dijawab dulu, lalu berdasarkan waktu pertanyaan
        $data = $query->orderByRaw('CASE WHEN answer IS NULL THEN 0 ELSE 1 END')
            ->orderBy('question_time', 'desc')
            ->get();

        return response()->json([
            'message' => 'Data has been shown',
            'data' => $data,
            'total' => $data->count(),
            'answered' => $data->where('answer', '!=', null)->count(),
            'unanswered' => $data->where('answer', null)->count()
        ], 200);
    }

    public function submitAnswerQna(Request $request)
    {
        $data = WebinarQna::where('id', $request->id)->update(['answer' => $request->answer, 'answerer_name' => 'Intilab Webinar', 'answerer_email' => 'webinar@intilab.com']);
        return response()->json(['message' => 'Data has been updated', 'data' => $data], 200);
    }

    public function deleteQna(Request $request)
    {
        $data = WebinarQna::where('id', $request->id)->delete();
        return response()->json(['message' => 'Data Berhasil dihapus'], 200);
    }
    private static function monthToRoman(int $month): string
    {
        $romans = [
            1  => 'I',
            2  => 'II',
            3  => 'III',
            4  => 'IV',
            5  => 'V',
            6  => 'VI',
            7  => 'VII',
            8  => 'VIII',
            9  => 'IX',
            10 => 'X',
            11 => 'XI',
            12 => 'XII',
        ];

        return $romans[$month] ?? '';
    }


    public function indexTemplateEmail(Request $request)
    {
        $header = DB::table('sertifikat_webinar_header')
            ->where('id', $request->id)
            ->first();

        if (!$header) {
            return response()->json([
                'success' => false,
                'message' => 'Data webinar tidak ditemukan',
            ], 404);
        }

        $body = '';
        $attachments = [];

        if (!empty($header->body_email)) {
            $filename = $header->body_email;
            $number = pathinfo($filename, PATHINFO_FILENAME);
            
            try {
                $body = Repository::dir('certificate')->key($number)->get();
            } catch (\Exception $e) {
                $body = '';
            }
        }

        if (!empty($header->attachments)) {
            $attachmentNames = json_decode($header->attachments, true);
            
            if (is_array($attachmentNames) && count($attachmentNames) > 0) {
                $folderName = Str::slug($header->title ?? 'webinar');
                
                $basePath = public_path("uploads/webinar/{$folderName}");
                $baseUrl = url("uploads/webinar/{$folderName}");
                
                foreach ($attachmentNames as $filename) {
                    $filePath = "{$basePath}/{$filename}";
                    
                    if (file_exists($filePath)) {
                        $attachments[] = [
                            'name' => $filename,
                            'url' => "{$baseUrl}/{$filename}",
                            'size' => filesize($filePath),
                        ];
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'header' => $header,
                'body' => $body,
                'attachments' => $attachments,
                'subject' => "E-Sertifikat Webinar {$header->title}",
            ],
            'message' => 'Template email berhasil dimuat',
        ]);
    }

   
    public function setTemplateEmail(Request $request)
    {
        $uuid = (int) str_replace('.', '', microtime(true));

        Repository::dir('certificate')->key($uuid)->save($request->content);

        $header = DB::table('sertifikat_webinar_header')
            ->where('id', $request->id)
            ->first();

        if (!$header) {
            return response()->json([
                'success' => false,
                'message' => 'Data webinar tidak ditemukan',
            ], 404);
        }

        $folderName = Str::slug($header->title ?? 'webinar');
        
        $basePath = public_path("uploads/webinar/{$folderName}");

        if (!file_exists($basePath)) {
            mkdir($basePath, 0755, true);
        }

        $existingAttachments = $request->input('existingAttachments', []);
        
        $deletedAttachments = $request->input('deletedAttachments', []);
        
        if (!empty($deletedAttachments) && is_array($deletedAttachments)) {
            foreach ($deletedAttachments as $filename) {
                $filePath = "{$basePath}/{$filename}";
                if (file_exists($filePath)) {
                    unlink($filePath); 
                }
            }
        }

        $newAttachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $originalName = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();
                $filename = pathinfo($originalName, PATHINFO_FILENAME);
                $name = $originalName;
                $file->move($basePath, $name);
                $newAttachments[] = $name;
            }
        }

        $allAttachments = array_merge(
            is_array($existingAttachments) ? $existingAttachments : [], 
            $newAttachments
        );

        DB::table('sertifikat_webinar_header')
            ->where('id', $request->id)
            ->update([
                'body_email' => $uuid . '.txt',
                'attachments' => json_encode($allAttachments),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Template Email berhasil disimpan',
        ]);
    }

    // public function sendEmailBulk(Request $request)
    // {
    //     try {

    //         Http::post('http://127.0.0.1:2999/send-email', ["id" => $request->id]);

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Email berhasil dikirim',
    //         ], 200);

    //     } catch (\Throwable $th) {
    //         throw $th;
    //     }
        
    // }



    // public function sendEmail(Request $request)
    // {
    //     DB::beginTransaction();
    //     try {

    //         $header = DB::table('sertifikat_webinar_header')
    //             ->where('id', $request->id)
    //             ->first();

    //         if (! $header) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Header tidak ditemukan',
    //             ], 404);
    //         }

    //         $detail = DB::table('sertifikat_webinar_detail')
    //             ->where('header_id', $request->id)
    //             ->where('time_session' , '>', 60)
    //             ->whereNotNull('time_session')
    //             ->orderBy('id','asc')
    //             ->get();

    //         // dd($detail);
                
    //         if ($detail->isEmpty()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Data peserta kosong',
    //             ], 404);
    //         }

    //         $filename = $header->body_email;
    //         $number   = pathinfo($filename, PATHINFO_FILENAME);

    //         $body = Repository::dir('certificate')->key($number)->get();

    //         if (! $body) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Template email tidak ditemukan',
    //             ], 404);
    //         }


    //         $templateAttachments = [];
    //         if (!empty($header->attachments)) {
    //             $attachmentNames = json_decode($header->attachments, true);
    //             if (is_array($attachmentNames)) {
    //                 $folderName = Str::slug($header->title ?? 'webinar');
    //                 $basePath = public_path("uploads/webinar/{$folderName}");
    //                 foreach ($attachmentNames as $filename) {
    //                     $filePath = "{$basePath}/{$filename}";
                        
    //                     if (file_exists($filePath)) {
    //                         $templateAttachments[] = $filePath;
    //                     }
    //                 }
    //             }
    //         }

    //         foreach ($detail as $value) {

            
    //             $qna = DB::table('webinar_qna')
    //                 ->where('webinar_id', $request->id)
    //                 ->where('asker_name', $value->name)
    //                 ->where('asker_email', $value->email)
    //                 ->get();

    //             $qnaHtml = '';

    //             if ($qna->isNotEmpty()) {

    //                 $questions = [];
    //                 $answers = [];
                    
    //                 foreach ($qna as $item) {
    //                     if (!empty($item->question)) {
    //                         $questions[] = e($item->question);
    //                     }
    //                     if (!empty($item->answer)) {
    //                         $answers[] = e($item->answer);
    //                     }
    //                 }
                    
    //                 $answers = array_unique($answers);
                    
    //                 $qnaHtml .= '<table width="100%" cellpadding="10" cellspacing="0" style="border: none;">
    //                         <tr>
    //                             <td width="50%" valign="top" style="border: none;">
    //                                 <strong>Pertanyaan:</strong>
    //                                 <ul style="margin: 5px 0; padding-left: 20px;">';
                    
    //                 foreach ($questions as $question) {
    //                     $qnaHtml .= '<li style="margin-bottom: 5px;">' . $question . '</li>';
    //                 }
                    
    //                 $qnaHtml .= '</ul>
    //                             </td>
    //                             <td width="50%" valign="top" style="border: none;">
    //                                 <strong>Jawaban:</strong>
    //                                 <ul style="margin: 5px 0; padding-left: 20px;">';
                    
    //                 if (!empty($answers)) {
    //                     foreach ($answers as $answer) {
    //                         $qnaHtml .= '<li style="margin-bottom: 5px;">' . $answer . '</li>';
    //                     }
    //                 } else {
    //                     $qnaHtml .= '<li style="margin-bottom: 5px;"><em>Belum dijawab</em></li>';
    //                 }
                    
    //                 $qnaHtml .= '
    //                                 </ul>
    //                             </td>
    //                         </tr>
    //                     </table>
    //                 ';
    //             } 

    //             $replace = [
    //                 '{{name}}'  => $value->name,
    //                 '{{title}}' => $header->title,
    //                 '{{date}}'  => Carbon::parse($header->date)
    //                     ->locale('id')
    //                     ->translatedFormat('l, d F Y'),
    //                 '{{qna}}'=> $qnaHtml 
    //             ];

    //             $emailBody = str_replace(
    //                 array_keys($replace),
    //                 array_values($replace),
    //                 $body
    //             );

    //             /**
    //              * attachement dari template body email belum ada
    //              * kemungkinan yang akan di letakan di template adalah :
    //              * materi webinar
    //              * Q&A global
    //              */

    //             $validAttachments = [];

    //             array_push($validAttachments, public_path() . '/certificates/' . $value->filename);

    //             $validAttachments = array_merge($validAttachments, $templateAttachments);


    //             $mail = SendEmail::where('to', $value->email)
    //                 ->where('subject', 'E-Sertifikat ' . $header->title)
    //                 ->where('body', $emailBody)
    //                 ->where('karyawan', 'System')
    //                 ->noReply();

    //             if (!empty($validAttachments)) {
    //                 $mail = $mail->where('attachment', $validAttachments);
    //             }
                
    //             $mail->send();
    //         }

    //         DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Email berhasil dikirim',
    //         ], 200);

    //     } catch (\Throwable $e) {

    //         DB::rollBack();

    //         Log::error('Send Email Webinar Error', [
    //             'id'      => $request->id,
    //             'message' => $e->getMessage(),
    //             'file'    => $e->getFile(),
    //             'line'    => $e->getLine(),
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Gagal mengirim email',
    //             'error'   => $e->getMessage(), // hapus di production
    //         ], 500);
    //     }
    // }

    public function sendEmailBulk(Request $request)
    {
        DB::beginTransaction();
        try {

            $header = DB::table('sertifikat_webinar_header')
                ->where('id', $request->id)
                ->first();

            if (! $header) {
                return response()->json([
                    'success' => false,
                    'message' => 'Header tidak ditemukan',
                ], 404);
            }

            $detail = DB::table('sertifikat_webinar_detail')
                ->where('header_id', $request->id)
                ->where('time_session' , '>', 60)
                ->whereNotNull('time_session')
                ->limit(5)
                ->orderBy('id','asc')
                ->get();

            // dd($detail);
                
            if ($detail->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data peserta kosong',
                ], 404);
            }

            $filename = $header->body_email;
            $number   = pathinfo($filename, PATHINFO_FILENAME);

            $body = Repository::dir('certificate')->key($number)->get();

            if (! $body) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template email tidak ditemukan',
                ], 404);
            }


            $templateAttachments = [];
            if (!empty($header->attachments)) {
                $attachmentNames = json_decode($header->attachments, true);
                if (is_array($attachmentNames)) {
                    $folderName = Str::slug($header->title ?? 'webinar');
                    $basePath = public_path("uploads/webinar/{$folderName}");
                    foreach ($attachmentNames as $filename) {
                        $filePath = "{$basePath}/{$filename}";
                        
                        if (file_exists($filePath)) {
                            $templateAttachments[] = $filePath;
                        }
                    }
                }
            }

            foreach ($detail as $value) {

            
                $qna = DB::table('webinar_qna')
                    ->where('webinar_id', $request->id)
                    ->where('asker_name', $value->name)
                    ->where('asker_email', $value->email)
                    ->get();

                $qnaHtml = '';

                if ($qna->isNotEmpty()) {

                    $questions = [];
                    $answers = [];
                    
                    foreach ($qna as $item) {
                        if (!empty($item->question)) {
                            $questions[] = e($item->question);
                        }
                        if (!empty($item->answer)) {
                            $answers[] = e($item->answer);
                        }
                    }
                    
                    $answers = array_unique($answers);
                    
                    $qnaHtml .= '<table width="100%" cellpadding="10" cellspacing="0" style="border: none;">
                            <tr>
                                <td width="50%" valign="top" style="border: none;">
                                    <strong>Pertanyaan:</strong>
                                    <ul style="margin: 5px 0; padding-left: 20px;">';
                    
                    foreach ($questions as $question) {
                        $qnaHtml .= '<li style="margin-bottom: 5px;">' . $question . '</li>';
                    }
                    
                    $qnaHtml .= '</ul>
                                </td>
                                <td width="50%" valign="top" style="border: none;">
                                    <strong>Jawaban:</strong>
                                    <ul style="margin: 5px 0; padding-left: 20px;">';
                    
                    if (!empty($answers)) {
                        foreach ($answers as $answer) {
                            $qnaHtml .= '<li style="margin-bottom: 5px;">' . $answer . '</li>';
                        }
                    } else {
                        $qnaHtml .= '<li style="margin-bottom: 5px;"><em>Belum dijawab</em></li>';
                    }
                    
                    $qnaHtml .= '
                                    </ul>
                                </td>
                            </tr>
                        </table>
                    ';
                } 

                $replace = [
                    '{{name}}'  => $value->name,
                    '{{title}}' => $header->title,
                    '{{date}}'  => Carbon::parse($header->date)
                        ->locale('id')
                        ->translatedFormat('l, d F Y'),
                    '{{qna}}'=> $qnaHtml 
                ];

                $emailBody = str_replace(
                    array_keys($replace),
                    array_values($replace),
                    $body
                );

                /**
                 * attachement dari template body email belum ada
                 * kemungkinan yang akan di letakan di template adalah :
                 * materi webinar
                 * Q&A global
                 */

                $validAttachments = [];

                array_push($validAttachments, public_path() . '/certificates/' . $value->filename);

                $validAttachments = array_merge($validAttachments, $templateAttachments);


                $mail = SendEmail::where('to', $value->email)
                    ->where('subject', 'E-Sertifikat ' . $header->title)
                    ->where('body', $emailBody)
                    ->where('karyawan', 'System')
                    ->noReply();

                if (!empty($validAttachments)) {
                    $mail = $mail->where('attachment', $validAttachments);
                }
                
                $mail->send();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Email berhasil dikirim',
            ], 200);

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Send Email Webinar Error', [
                'id'      => $request->id,
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim email',
                'error'   => $e->getMessage(), // hapus di production
            ], 500);
        }
    }
}
