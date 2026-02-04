<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

use App\Models\MasterPelanggan;
use App\Models\QuotationNonKontrak;
use App\Models\QuotationKontrakH;
use App\Models\QuotationKontrakD;
use App\Models\OrderHeader;
use App\Models\MasterKaryawan;

use App\Services\SendEmail;
use App\Services\GetAtasan;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class RekapBillingListController extends Controller
{
    public function index(Request $request)
    {
        $data = DB::table('billing_list_header')
            ->select(
                'id',
                'id_pelanggan',
                'nama_pelanggan',
                'nilai_tagihan',
                'terbayar',
                DB::raw('nilai_tagihan - terbayar as nilai_piutang'),
                'is_complete',
                DB::raw("
                    CASE
                        WHEN sales_penanggung_jawab = 'Dedi Wibowo'
                        THEN '-'
                        ELSE sales_penanggung_jawab
                    END as sales_penanggung_jawab
                ")
            )
            ->where('is_complete', $request->is_complete);

        $page = $request->start > 9 ? "lanjut" : "awal";

        return DataTables::of($data)
            ->with([
                'sum_nilai_tagihan'  => function ($query) {
                    return $query->sum('nilai_tagihan');
                },
                'sum_nilai_terbayar' => function ($query) {
                    return $query->sum('terbayar');
                },
                'sum_nilai_piutang'  => function ($query) {
                    $terbayar = $query->sum('terbayar');
                    $tagihan  = $query->sum('nilai_tagihan');
                    $piutang  = $tagihan - $terbayar;

                    return max(0, $piutang);
                },
                'page'               => function () use ($page) {
                    return $page;
                },
            ])
            ->make(true);
    }

    public function getDetail(Request $request)
    {
        $data = DB::table('billing_list_detail')
            ->select(
                'billing_list_detail.id',
                'billing_list_detail.billing_header_id',
                'billing_list_detail.no_invoice',
                'billing_list_detail.no_quotation',
                'billing_list_detail.no_order',
                'billing_list_detail.periode',
                'billing_list_detail.tgl_sampling',
                'billing_list_detail.tgl_invoice',
                'billing_list_detail.tgl_jatuh_tempo',
                'billing_list_detail.nilai_tagihan',
                'billing_list_detail.terbayar',
                DB::raw('billing_list_detail.nilai_tagihan - billing_list_detail.terbayar as nilai_piutang') ,
                'billing_list_detail.is_complete',
                'master_karyawan.nama_lengkap as sales_penanggung_jawab'
            )
            ->leftJoin('master_karyawan', 'master_karyawan.id', '=', 'billing_list_detail.sales_id')
            ->where('billing_header_id', $request->id_header)
            ->orderByRaw('(billing_list_detail.nilai_tagihan - billing_list_detail.terbayar) DESC');
        $page = $request->start > 29 ? "lanjut" : "awal";

        return DataTables::of($data)
            ->with([
                'sum_nilai_tagihan'  => function ($query) {
                    return $query->sum('nilai_tagihan');
                },
                'sum_nilai_terbayar' => function ($query) {
                    return $query->sum('terbayar');
                },
                'sum_nilai_piutang'  => function ($query) {
                    $terbayar = $query->sum('terbayar');
                    $tagihan  = $query->sum('nilai_tagihan');
                    $piutang  = $tagihan - $terbayar;

                    return max(0, $piutang);
                },
                'page'               => function () use ($page) {
                    return $page;
                },
            ])->orderColumn('nilai_piutang', function ($query, $order) {
                $query->orderByRaw('(billing_list_detail.nilai_tagihan - billing_list_detail.terbayar) ' . $order);
            })
            ->make(true);

    }

    public function export(Request $request) 
    {
        // Validate password
        if ($request->password !== env('EXPORT_DAILYQSD_PW')) {
            return response()->json(['message' => 'Password salah! Akses ditolak.'], 403);
        }

        // 1. Query data
        $query = DB::table('billing_list_header')
                ->select(
                    'id',
                    'id_pelanggan',
                    'nama_pelanggan',
                    'nilai_tagihan',
                    'terbayar',
                    DB::raw('nilai_tagihan - terbayar as nilai_piutang'),
                    'is_complete'
                )
                ->where('is_complete', $request->is_complete);

        // 2. Hitung Summary
        $isComplete    = $request->is_complete;
        $totalTagihan  = (clone $query)->sum('nilai_tagihan');
        $totalTerbayar = (clone $query)->sum('terbayar');
        $totalPiutang  = ($isComplete == 0) ? ($totalTagihan - $totalTerbayar) : 0;

        // 3. Eksekusi Get Data
        $data = $query->get();

        // 4. Proses Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // --- Tentukan Header & Kolom Terakhir ---
        $headers = ['No', 'ID Pelanggan', 'Nama Pelanggan', 'Nilai Tagihan', 'Terbayar'];
        if ($isComplete == 0) {
            $headers[] = 'Sisa Piutang';
        }

        $lastCol = ($isComplete == 0) ? 'F' : 'E';

        // Judul
        $sheet->setCellValue('A1', 'LAPORAN BILLING PELANGGAN');
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Set Header ke Baris 3
        $sheet->fromArray($headers, null, 'A3');

        // Baris 4: Summary (Total)
        $sheet->setCellValue('D4', $totalTagihan);
        $sheet->setCellValue('E4', $totalTerbayar);
        if ($isComplete == 0) {
            $sheet->setCellValue('F4', $totalPiutang);
        }

        // --- Logika Merge Header (Kolom No, ID, Nama) ---
        $colsToMerge = ['A', 'B', 'C'];
        foreach ($colsToMerge as $col) {
            $sheet->mergeCells("{$col}3:{$col}4");
        }

        // Styling Header & Baris Total
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '343A40'],
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ];
        $sheet->getStyle("A3:{$lastCol}4")->applyFromArray($headerStyle);

        // --- 5. Looping Isi Data ---
        $row = 5;
        foreach ($data as $index => $item) {
            $sheet->setCellValue('A' . $row, $index + 1);
            $sheet->setCellValue('B' . $row, $item->id_pelanggan);
            $sheet->setCellValue('C' . $row, $item->nama_pelanggan);
            $sheet->setCellValue('D' . $row, $item->nilai_tagihan);
            $sheet->setCellValue('E' . $row, $item->terbayar);
            
            if ($isComplete == 0) {
                $sheet->setCellValue('F' . $row, $item->nilai_piutang);
                if ($item->nilai_piutang > 0) {
                    $sheet->getStyle('F' . $row)->getFont()->getColor()->setRGB('FF0000');
                }
            }
            
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $row++;
        }

        // --- 6. Final Formatting ---
        $lastDataRow = $row - 1;

        // Auto-size kolom
        foreach (range('A', $lastCol) as $colID) {
            $sheet->getColumnDimension($colID)->setAutoSize(true);
        }

        // Format Angka Ribuan
        $sheet->getStyle("D4:{$lastCol}{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0');

        // Freeze Panes
        $sheet->freezePane('A5');

        // --- Output File ---
        $writer = new Xlsx($spreadsheet);
        $statusText = $isComplete == 1 ? 'Selesai' : 'Belum_Selesai';
        $fileName = "Rekapitulasi_Billing_{$statusText}_" . date('d-m-Y_His') . ".xlsx";

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    // UBAH DARI private MENJADI protected
    protected function getEmailBCC($documents)
    {
        // 1. Inisialisasi email default
        $emails = collect(['billing@intilab.com']);
        $documents = collect($documents);

        if ($documents->isEmpty()) {
            return $emails->unique()->values();
        }

        // 2. Ambil Sales ID - GANTI str_contains dengan strpos untuk PHP 7.4
        $salesIds = $documents->groupBy(function ($doc) {
            return strpos($doc, '/QTC/') !== false ? 'qtc' : 'non_qtc';
        })->flatMap(function ($docs, $type) {
            return ($type === 'qtc')
                ? QuotationKontrakH::whereIn('no_document', $docs)->pluck('sales_id')
                : QuotationNonKontrak::whereIn('no_document', $docs)->pluck('sales_id');
        })->unique();

        // 3. Jika ada Sales ID, tarik data email
        if ($salesIds->isNotEmpty()) {
            $uniqueSalesIds = $salesIds->toArray();

            // Ambil email Sales & Atasan
            $allStaff = collect();
            foreach ($uniqueSalesIds as $id) {
                try {
                    $staff = GetAtasan::where('id', $id)->get();
                    $allStaff = $allStaff->merge($staff);
                } catch (\Exception $e) {
                    continue;
                }
            }

            // Ambil email sales
            $emailsSales = MasterKaryawan::whereIn('id', $uniqueSalesIds)
                ->whereNotNull('email')
                ->pluck('email');
            
            // Filter email atasan
            $emailsAtasan = $allStaff->where('grade', 'SUPERVISOR')
                ->whereNotNull('email')
                ->pluck('email');

            // Gabungkan
            $emails = $emails->merge($emailsSales)->merge($emailsAtasan);
        }

        // 4. Return
        return $emails->filter()->unique()->values();
    }

    // UBAH DARI private MENJADI protected
    protected function buildEmailBody($billingData, $pelanggan, $user)
    {
        // Ucapan
        $hour = date("H");
        $ucapan = "Selamat,";
        if ($hour >= 4 && $hour < 10) $ucapan = "Selamat pagi,";
        elseif ($hour >= 10 && $hour < 15) $ucapan = "Selamat siang,";
        elseif ($hour >= 15 && $hour < 18) $ucapan = "Selamat sore,";
        elseif ($hour >= 18 || $hour < 4) $ucapan = "Selamat malam,";

        // Table rows
        $tableRows = "";
        $no = 1;

        foreach ($billingData as $item) {

            $nilaiTagihan = "Rp " . number_format($item->nilai_tagihan ?? 0, 0, ",", ".");
            $nilaiBayar   = "Rp " . number_format($item->terbayar ?? 0, 0, ",", ".");
            $piutang      = ($item->nilai_tagihan ?? 0) - ($item->terbayar ?? 0);
            $nilaiPiutang = "Rp " . number_format($piutang, 0, ",", ".");

            $tableRows .= "
            <tr>
                <td border='1' style='border:1px solid #000;padding:4px;text-align:center;'>$no</td>
                <td border='1' style='border:1px solid #000;padding:4px;text-align:right;'>$nilaiTagihan</td>
                <td border='1' style='border:1px solid #000;padding:4px;text-align:right;'>$nilaiBayar</td>
                <td border='1' style='border:1px solid #000;padding:4px;text-align:right;'>$nilaiPiutang</td>
                <td border='1' style='border:1px solid #000;padding:4px;'>".($item->no_invoice ?? "-")."</td>
                <td border='1' style='border:1px solid #000;padding:4px;'>".($item->no_order ?? "-")."</td>
                <td border='1' style='border:1px solid #000;padding:4px;'>".($item->no_quotation ?? "-")."</td>
                <td border='1' style='border:1px solid #000;padding:4px; text-align:center;'>".$this->tanggalInggris($item->tgl_sampling)."</td>
                <td border='1' style='border:1px solid #000;padding:4px; text-align:center;'>".$this->tanggalInggris($item->tgl_invoice)."</td>
                <td border='1' style='border:1px solid #000;padding:4px; text-align:center;'>".$this->tanggalInggris($item->tgl_jatuh_tempo)."</td>
            </tr>
            ";

            $no++;
        }

        $userName    = $this->karyawan ?? "Team Billing";
        $userJabatan = isset($user->jabatan) ? $user->jabatan->nama_jabatan : "Staff";

        $content = "
            <p>Dear Team Finance <b>$pelanggan</b></p>
            <p>$ucapan</p>
            <p>Mohon informasinya mengenai tagihan invoice berikut :</p>

            <table width='100%' cellpadding='2' cellspacing='0' border='1' style='border-collapse:collapse;margin:10px 0; font-family: Arial, sans-serif; font-size: 12px;'>
                <thead>
                    <tr style='background-color:#f2f2f2;'>
                        <th rowspan='2' style='border:1px solid #000; text-align:center;'>No</th>
                        <th rowspan='2' style='border:1px solid #000; text-align:center;'>Nilai Tagihan</th>
                        <th rowspan='2' style='border:1px solid #000; text-align:center;'>Nilai Bayar</th>
                        <th rowspan='2' style='border:1px solid #000; text-align:center;'>Nilai Piutang</th>
                        <th rowspan='2' style='border:1px solid #000; text-align:center;'>No Invoice</th>
                        <th rowspan='2' style='border:1px solid #000; text-align:center;'>No Order</th>
                        <th rowspan='2' style='border:1px solid #000; text-align:center;'>No Quotation</th>
                        <th colspan='3' style='border:1px solid #000; text-align:center;'>Tanggal</th>
                    </tr>
                    <tr style='background-color:#f2f2f2;'>
                        <th style='border:1px solid #000; text-align:center;'>Sampling</th>
                        <th style='border:1px solid #000; text-align:center;'>Invoice</th>
                        <th style='border:1px solid #000; text-align:center;'>Jatuh Tempo</th>
                    </tr>
                </thead>
                <tbody>
                    $tableRows
                </tbody>
            </table>

            <p>Kami tunggu konfirmasinya segera.</p>
            <p>Atas perhatian dan kerjasama yang baik kami ucapkan terimakasih.</p>
            <p>Best Regards,</p>
            <p><b>$userName</b><br>$userJabatan</p>
            ";

        return "<html><body>".$content."</body></html>";
    }


    public function getDetailBilling(Request $request) 
    {
        try {
            // GANTI validated() dengan akses langsung request
            if (!$request->id_pelanggan || !$request->nama_pelanggan || !$request->id_header || !$request->id_detail) {
                return response()->json(['error' => 'Data tidak lengkap'], 400);
            }

            // Get billing data
            $billingData = DB::table('billing_list_detail as bld')
                ->where('bld.billing_header_id', $request->id_header)
                ->whereIn('bld.id', $request->id_detail)
                ->select(
                    'bld.id',
                    'bld.no_invoice',
                    'bld.no_order',
                    'bld.periode',
                    'bld.no_quotation',
                    'bld.tgl_sampling',
                    'bld.tgl_jatuh_tempo',
                    'bld.tgl_invoice',
                    'bld.nilai_tagihan',
                    'bld.terbayar'
                )
                ->get();

            if ($billingData->isEmpty()) {
                return response()->json(['error' => 'Data billing tidak ditemukan'], 404);
            }

            // Get user data
            $user = auth()->user();
            
            // Get CC emails
            $noQuotations = $billingData->pluck('no_quotation')->filter()->unique()->values()->toArray();
            $bccEmails = $this->getEmailBCC($noQuotations);

            // Non Kontrak
            $emailPicNonKontrak = QuotationNonKontrak::whereIn('no_document', $noQuotations)
                ->pluck('email_pic_order');

            // Kontrak
            $emailPicKontrak = QuotationKontrakH::whereIn('no_document', $noQuotations)
                ->pluck('email_pic_order');

            // CC Emails 
            $emailCcNonKontrak = QuotationNonKontrak::whereIn('no_document', $noQuotations)
                ->pluck('email_cc');

            $emailCcKontrak = QuotationKontrakH::whereIn('no_document', $noQuotations)
                ->pluck('email_cc');

            // Gabung TO
            $emailsTo = collect()
                ->merge($emailPicNonKontrak)
                ->merge($emailPicKontrak)
                ->filter()
                ->unique()
                ->values();

            // Gabung CC
            $emailsCc = collect()
                ->merge($emailCcNonKontrak)
                ->merge($emailCcKontrak)
                ->flatMap(function ($item) {
                    // Decode JSON string → array
                    $decoded = json_decode($item, true);

                    if (is_array($decoded)) {
                        return $decoded;
                    }

                    return [$item];
                })
                ->map(function ($email) {
                    return strtolower(trim($email));
                })
                ->filter()
                ->unique()
                ->values();

            
            // Generate invoice list
            $noInvoices = $billingData->pluck('no_invoice')->filter()->unique()->values()->toArray();
            $invoiceList = implode(', ', $noInvoices);
            
            // Build subject
            $subject = "ISL - Konfirmasi Tagihan {$request->nama_pelanggan}";

            // Build email body
            $emailBody = $this->buildEmailBody($billingData, $request->nama_pelanggan, $user);

            return response()->json([
                'success' => true,
                'email' => implode(', ', $emailsTo->toArray()),
                'bcc' => implode(', ', $bccEmails->toArray()),
                'cc' => implode(', ', $emailsCc->toArray()),
                'subject' => $subject,
                'email_body' => $emailBody,
                'data' => $billingData
            ]);

        } catch (\Exception $e) {
            dd($e);
            return response()->json([
                'error' => 'Terjadi kesalahan',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // public function sendEmail(Request $request)
    // {
    //     dd($request->all());
    //     function normalizeEmails($input)
    //     {
    //         if (empty($input)) return [];

    //         if (is_array($input)) {
    //             $input = implode(',', $input);
    //         }

    //         return array_filter(array_map('trim', explode(',', $input)));
    //     }

    //     $toList  = normalizeEmails($request->to);
    //     $ccList  = normalizeEmails($request->cc);
    //     $bccList = normalizeEmails($request->bcc);
    //     $results = [];

    //     foreach ($toList as $recipient) {
    //         // Pastikan hanya mengirim jika string recipient valid
    //         if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    //             $email = SendEmail::where('to', $recipient)
    //                 ->where('subject', $request->subject)
    //                 ->where('body', $request->content)
    //                 ->where('cc', $ccList)
    //                 ->where('bcc', $bccList)
    //                 // ->where('attachment', $request->attachments)
    //                 ->where('karyawan', $this->karyawan)
    //                 ->fromFinance()
    //                 ->send();
                    
    //             $results[] = [
    //                 'recipient' => $recipient,
    //                 'status' => $email ? 'Success' : 'Failed'
    //             ];
    //         } else {
    //             $results[] = [
    //                 'recipient' => $recipient,
    //                 'status' => 'Invalid Email Format'
    //             ];
    //         }
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Proses pengiriman email berhasil.',
    //         'details' => $results
    //     ]);
    // }

    public function sendEmail(Request $request)
    {
        try {
            function normalizeEmails($input)
            {
                if (empty($input)) return [];
                if (is_array($input)) {
                    $input = implode(',', $input);
                }
                return array_filter(array_map('trim', explode(',', $input)));
            }
            
            $toList  = normalizeEmails($request->to);
            $ccList  = normalizeEmails($request->cc);
            $bccList = normalizeEmails($request->bcc);
            
            // Siapkan attachments untuk PHPMailer
            $attachmentsForEmail = [];
            
            if ($request->has('attachments') && !empty($request->attachments)) {
                // Buat folder temp di public jika belum ada
                $publicTempDir = public_path('temp/email-attachments');
                if (!file_exists($publicTempDir)) {
                    mkdir($publicTempDir, 0777, true);
                }
                
                foreach ($request->attachments as $attachment) {
                    // Validasi struktur data
                    if (!isset($attachment['data']) || !isset($attachment['name'])) {
                        \Log::warning('Attachment missing data or name');
                        continue;
                    }
                    
                    $fileData = $attachment['data'];
                    
                    if (preg_match('/^data:([a-zA-Z0-9]+\/[a-zA-Z0-9-.+]+);base64,/', $fileData, $matches)) {
                        $mimeType = $matches[1];
                        $base64Content = substr($fileData, strpos($fileData, ',') + 1);
                        $decodedFile = base64_decode($base64Content);
                        
                        // Validasi decoded file tidak kosong
                        if (empty($decodedFile)) {
                            \Log::warning('Failed to decode attachment: ' . $attachment['name']);
                            continue;
                        }
                        
                        // Sanitize filename untuk keamanan
                        $safeFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $attachment['name']);
                        
                        // Unique filename tanpa timestamp (file tidak akan dihapus otomatis)
                        $uniqueFileName = uniqid() . '_' . $safeFileName;
                        
                        // Simpan ke public folder
                        $publicPath = $publicTempDir . '/' . $uniqueFileName;
                        
                        // Cek apakah berhasil menyimpan file
                        $writeResult = file_put_contents($publicPath, $decodedFile);
                        if ($writeResult === false) {
                            \Log::error('Failed to save temporary file: ' . $publicPath);
                            continue;
                        }
                        
                        // Path relatif dari public folder untuk SendEmail service
                        $relativePath = 'temp/email-attachments/' . $uniqueFileName;
                        
                        $attachmentsForEmail[] = [
                            'path' => $relativePath,
                            'name' => $safeFileName
                        ];
                        
                        \Log::info('Attachment saved: ' . $uniqueFileName);
                    }
                }
            }
            
            $results = [];
            
            foreach ($toList as $recipient) {
                if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    try {
                        $email = SendEmail::where('to', $recipient)
                            ->where('subject', $request->subject)
                            ->where('body', $request->content)
                            ->where('cc', $ccList)
                            ->where('bcc', $bccList)
                            ->where('attachment', $attachmentsForEmail)
                            ->where('karyawan', $this->karyawan)
                            ->fromFinance()
                            ->send();
                            
                        $results[] = [
                            'recipient' => $recipient,
                            'status' => $email ? 'Success' : 'Failed',
                            'attachments_count' => count($attachmentsForEmail)
                        ];
                    } catch (\Exception $e) {
                        \Log::error('Failed to send email to ' . $recipient . ': ' . $e->getMessage());
                        $results[] = [
                            'recipient' => $recipient,
                            'status' => 'Failed',
                            'error' => $e->getMessage()
                        ];
                    }
                } else {
                    $results[] = [
                        'recipient' => $recipient,
                        'status' => 'Invalid Email Format'
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Proses pengiriman email berhasil.',
                'details' => $results,
                'total_recipients' => count($results),
                'total_attachments' => count($attachmentsForEmail)
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error sending email: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Terjadi kesalahan saat mengirim email: ' . $e->getMessage()
            ], 500);
        }
    }

    protected function tanggalInggris($tanggal)
    {
        if (empty($tanggal) || $tanggal == '0000-00-00' || $tanggal == '0000-00-00 00:00:00') {
            return '-';
        }

        $tanggal = substr($tanggal, 0, 10);
        $pecah = explode('-', $tanggal);
        if (count($pecah) !== 3) return '-';

        // Daftar bulan Inggris 3 huruf
        $bulanEng = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
            7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
        ];

        $tahunSingkat = substr($pecah[0], -2); // Ambil '25' dari '2025'

        return $pecah[2] . '-' . $bulanEng[(int)$pecah[1]] . '-' . $tahunSingkat;
    }
}