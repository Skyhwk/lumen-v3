<?php
namespace App\Http\Controllers\mobile;

use App\Models\SarHeader;
use App\Models\SarDetail;
use App\Models\ProsesFdlSar;
use App\Models\ParameterSar;

use Carbon\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Services\GenerateQrDocumentLhp;
use App\Services\RenderLhpSar;
use App\Services\GenerateStrukSarService;
use App\Services\SendEmail;

class FdlSarController extends Controller
{
    public function checkQr(Request $request)
    {
        $data = SarHeader::where('no_order', $request->no_order)->where('is_active', true)->first();
        if(!$data) {
            return response()->json([
                'message' => 'Qr Tidak Ditemukan',
                'data' => null
                ], 401);
        }
        return response()->json([
            'message' => 'Data Ditemukan',
            'data' => $data
            ], 200);
    }

    public function checkUsable(Request $request)
    {
        $data = ProsesFdlSar::where('karyawan_id', $this->user_id)->where('is_completed', false)->first();
        $header = [];
        $isUsable = false;
        if ($data) {
            $header = SarHeader::with('quotation', 'detail')->where('no_order', $data->no_order)->where('is_active', true)->first();
            $isUsable = true;
        }
        return response()->json([
            'message' => 'success',
            'is_usable' => $isUsable,
            'data' => $header
            ], 200);
    }

    public function processSar(Request $request)
    {
        try {
            $data = SarHeader::with('quotation', 'detail')
                ->where('no_order', $request->no_order)
                ->where('is_active', true)
                ->first();

            $cek = ProsesFdlSar::where('no_order', $request->no_order)->first();

            if ($cek) {
                if ($cek->is_completed) {
                    $cek->is_completed = false;
                    $cek->save();
                }
                return response()->json([
                    'message' => 'Proses sudah dimulai',
                    'data' => $data
                ], 200);
            } 

            $insert = ProsesFdlSar::create([
                'karyawan_id' => $this->user_id,
                'no_order' => $request->no_order,
                'waktu_mulai_sampling' => Carbon::now()->format('Y-m-d H:i:s')
            ]);

            return response()->json([
                'message' => 'Data berhasil diinsert',
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
                'data' => null
            ], 400);
        }
    }

    public function storeData(Request $request)
    {
        $cekHeader = SarHeader::with('quotation', 'detail')->where('no_order', $request->no_order)->where('is_active', true)->first();
        if(!$cekHeader) {
            return response()->json([
                'message' => 'Header tidak ditemukan',
                'data' => null
            ], 404);
        }

        if($cekHeader->waktu_mulai_sampling == null) {
            $cekHeader->waktu_mulai_sampling = Carbon::now()->format('Y-m-d H:i:s');
            $cekHeader->save();
        }

        $insert = new SarDetail();
        $insert->id_header = $cekHeader->id;
        $insert->hasil_uji_array = json_encode($request->hasil_uji_array);

        $hasilUjiArray = is_array($request->hasil_uji_array) ? $request->hasil_uji_array : json_decode($request->hasil_uji_array, true);
        if (is_array($hasilUjiArray) && count($hasilUjiArray) > 0) {
            $average = array_sum($hasilUjiArray) / count($hasilUjiArray);
        } else {
            $average = null;
        }

        $insert->hasil_uji = is_null($average) ? null : number_format($average, 1, '.', '');
        $insert->id_parameter = $request->id_parameter;
        $insert->koordinat = $request->koordinat;
        $insert->latitude = $request->lat;
        $insert->longitude = $request->long;
        $insert->nomor_sampel = $request->no_sampel;
        $insert->parameter = $request->parameter;
        $insert->lokasi_pengambilan_sampel = $request->nama_titik;
        $insert->created_by = $this->karyawan;
        $insert->created_at = Carbon::now()->format('Y-m-d H:i:s');

        if($cekHeader->detail->count() >= $cekHeader->jumlah_sampel) {
            $cekHeader->is_completed = true;
            $cekHeader->waktu_selesai_sampling = Carbon::now()->format('Y-m-d H:i:s');
            $cekHeader->save();
        }

        $insert->save();


        return response()->json([
            'message' => 'Data berhasil disimpan',
            'data' => $cekHeader
        ], 200);
    }

    public function prosesSelesai(Request $request)
    {
        $update = ProsesFdlSar::where('no_order', $request->no_order)
        ->update([
            'is_completed' => true,
            'waktu_selesai_sampling' => Carbon::now()->format('Y-m-d H:i:s')
        ]);

        if($update) {
            $data = SarHeader::with('detail')->where('no_order', $request->no_order)->where('is_active', true)->where('is_completed', true)->first();

            $service = new GenerateStrukSarService();
            $service->generate($data);
            
            return response()->json([
                'message' => 'Proses selesai',
                'status' => true
            ], 200);
        } else {
            return response()->json([
                'message' => 'Proses gagal',
                'status' => false
            ], 400);
        }
    }

    public function renderPdf(Request $request)
    {
        $hasilUjiSAR = SarHeader::with('detail')->findOrFail($request->id);

        $hasilUjiSAR->tanggal_lhp = date('Y-m-d');

        $file_qr = new GenerateQrDocumentLhp();
        if ($path = $file_qr->insertSAR('LHP_SAR', $hasilUjiSAR, $this->karyawan)) {
            $hasilUjiSAR->file_qr = $path;
        }

        $filename = RenderLhpSar::setDataHeader($hasilUjiSAR)->setDataDetail($hasilUjiSAR->detail)->render();

        $hasilUjiSAR->file_lhp = $filename;
        $hasilUjiSAR->save();

        return response()->json($filename, 200);
    }

    public function index(Request $request)
    {
        $proses = ProsesFdlSar::where('karyawan_id', 127)->where('is_completed', true)->get();
        
        $data = SarHeader::with('detail')
            ->where('is_active', true)
            ->whereIn('no_order', $proses->pluck('no_order'))
            ->get();

        $nilai_rujukan = ParameterSar::select('id_parameter', 'nama_lab', 'nama_regulasi', 'nilai_rujukan')
            ->where('is_active', true)
            ->get();

        $result = $data->map(function ($header) use ($nilai_rujukan) {
            // 1. Kelompokkan detail berdasarkan nomor_sampel
            $groupedBySample = $header->detail->groupBy('nomor_sampel');

            // 2. Loop setiap grup sampel
            $samples = $groupedBySample->map(function ($details, $nomor_sampel) use ($nilai_rujukan) {
                // Index detail berdasarkan id_parameter untuk lookup cepat di dalam sampel ini
                $detailMap = $details->keyBy('id_parameter');

                // Ambil lokasi dari salah satu baris detail (karena lokasi biasanya sama per sampel)
                $lokasi = $details->first()->lokasi_pengambilan_sampel ?? '-';

                // 3. Paksa semua parameter rujukan muncul di sampel ini
                $parameters = $nilai_rujukan->map(function ($param) use ($detailMap) {
                    $detail = $detailMap->get($param->id_parameter);
                    
                    $hasil_uji = $detail ? $detail->hasil_uji : '-';
                    $melebihi = false;

                    if ($detail && is_numeric($hasil_uji) && is_numeric($param->nilai_rujukan)) {
                        $melebihi = (float)$hasil_uji > (float)$param->nilai_rujukan;
                    }

                    return [
                        'id_parameter'   => $param->id_parameter,
                        'nama_regulasi'  => $param->nama_regulasi,
                        'nama_lab'       => $param->nama_lab,
                        'hasil_uji'      => $hasil_uji,
                        'nilai_rujukan'  => $param->nilai_rujukan,
                        'melebihi_rujukan' => $melebihi,
                    ];
                });

                return [
                    'nomor_sampel' => $nomor_sampel,
                    'lokasi'       => $lokasi,
                    'item_parameters' => $parameters
                ];
            })->values(); // Reset keys agar menjadi array numeric []

            return [
                'id'                     => $header->id,
                'no_order'               => $header->no_order,
                'no_quotation'           => $header->no_quotation,
                'email_pelanggan'        => $header->email_pelanggan,
                'jumlah_sampel'          => $header->jumlah_sampel,
                'nama_pelanggan'         => $header->nama_pelanggan,
                'alamat_pelanggan'       => $header->alamat_pelanggan,
                'waktu_mulai_sampling'   => $header->waktu_mulai_sampling,
                'waktu_selesai_sampling' => $header->waktu_selesai_sampling,
                'sampel_groups'          => $samples, // Sekarang data dikelompokkan per sampel
                'petugas'                => $header->detail->first()->created_by ?? $header->detail->first()->updated_by ?? '-',
            ];
        });

        return response()->json([
            'message' => 'success',
            'data'    => $result,
        ], 200);
    }

    public function generateStrukSar(Request $request)
    {
        $data = SarHeader::with('detail')->where('no_order', $request->no_order)->where('is_active', true)->first();

        $service = new GenerateStrukSarService();
        $service->generate($data);
        
        return response()->json([
            'message' => 'Struk SAR has been generated successfully',
            'data' => $data
        ], 200);
    }

    public function sendEmailStruk(Request $request)
    {
        DB::beginTransaction();
        dd('send email struk', $request->all());
        
        try {
            $data = SarHeader::with('detail')->where('no_order', $request->no_order)->where('is_active', true)->first();
            $subject = 'Hasil Pengujian SAR - ' . $data->nama_pelanggan;
            $content = "
                Yth. {$data->nama_pelanggan},

                Berikut terlampir hasil pengujian SAR dengan nomor order {$data->no_order}.

                Silakan cek lampiran untuk detail hasil pengujian.

                Terima kasih.

                Hormat kami,
                PT. Inti Surya Laboratorium
                ";
            $to = $request->input('to');
            $cc = $request->input('cc', []);
            $attachments = $request->input('attachments', []);
            $noOrder = $request->input('no_order');
            $noDocument = $request->input('no_document');

            if (empty($subject)) {
                throw new \Exception('Subject is required');
            }
            if (empty($to)) {
                throw new \Exception('Recipient email is required');
            }

            $ccArray = [];
            $bcc = [];

            if (!empty($cc)) {
                if (is_array($cc)) {
                    $ccArray = $cc;
                } else {
                    $ccArray = array_filter(array_map('trim', explode(',', $cc)));
                }
            }

            $emailInstance = SendEmail::where('to', $to)
                ->where('cc', $ccArray)
                ->where('bcc', $bcc)
                ->where('subject', $subject)
                ->where('body', $content)
                ->noReply();

            if (is_array($attachments) && !empty($attachments)) {
                $validAttachments = [];
                foreach ($attachments as $fileName) {
                    array_push($validAttachments, public_path() . '/dokumen/bas/' . $fileName);
                }

                if (!empty($validAttachments)) {
                    $emailInstance = $emailInstance->where('attachment', $validAttachments);
                }
            }

            $sent = $emailInstance->send();

            if ($sent) {
                DB::commit();
                return response()->json([
                    'message' => 'Email sent successfully',
                    'data' => $data
                ], 200);
            } else {
                throw new \Exception('Failed to send email');
            }
        } catch (\Exception $e) {
            DB::rollBack();

            error_log('Email sending error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Gagal mengirim email: ' . $e->getMessage()
            ], 500);
        }
    }

}