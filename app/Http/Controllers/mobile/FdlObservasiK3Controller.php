<?php

namespace App\Http\Controllers\mobile;

use App\Http\Controllers\Controller;
use App\Models\ObservasiK3;
use App\Models\ObservasiK3Kategori;
use App\Models\ObservasiK3Template;
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\TicketSkK3;
use App\Services\InsertActivityFdl;
use App\Services\ObservasiK3PengesahanPdfService;
use App\Services\SendEmail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class FdlObservasiK3Controller extends Controller
{
    public function getByNoQt(Request $request)
    {
        $noQt = trim((string) ($request->no_qt ?? $request->no_quotation ?? ''));

        if ($noQt === '') {
            return response()->json(['message' => 'Nomor QT wajib diisi'], 422);
        }

        $ticket = TicketSkK3::where('no_quotation', $noQt)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        if (!$ticket) {
            return response()->json([
                'message' => 'Nomor QT tidak ditemukan pada ticket SK K3',
            ], 404);
        }

        $today = Carbon::now('Asia/Jakarta')->format('Y-m-d');

        $orderDetailBase = OrderDetail::where('no_quotation', $noQt)->where('is_active', true);

        if (!(clone $orderDetailBase)->exists()) {
            return response()->json([
                'message' => 'Data order detail aktif untuk nomor QT ini tidak ditemukan',
            ], 404);
        }

        $orderDetails = (clone $orderDetailBase)
            // ->whereDate('tanggal_sampling', $today)
            ->orderBy('no_sampel')
            ->get([
                'id',
                'no_order',
                'no_quotation',
                'no_sampel',
                'keterangan_1',
                'keterangan_2',
                'nama_perusahaan',
                'periode',
                'tanggal_sampling',
            ]);

        $metaRow = $orderDetails->first()
            ?? (clone $orderDetailBase)->orderBy('no_sampel')->first();

        $sampleMessage = null;
        if ($orderDetails->isEmpty()) {
            $sampleMessage = "Tidak ada nomor sampel dengan tanggal sampling hari ini ({$today}).";
        }

        return response()->json([
            'data' => [
                'no_quotation' => $noQt,
                'nomor_ticket_sk_k3' => $ticket->nomor_ticket,
                'no_order' => $metaRow->no_order,
                'nama_perusahaan' => $ticket->nama_perusahaan ?: $metaRow->nama_perusahaan,
                'periode' => $ticket->periode ?: $metaRow->periode,
                'ticket_status' => $ticket->status,
                'tanggal_sampling_filter' => $today,
                'sample_message' => $sampleMessage,
                'order_detail' => $orderDetails->map(function ($row) {
                    return [
                        'id' => $row->id,
                        'no_order' => $row->no_order,
                        'no_quotation' => $row->no_quotation,
                        'no_sampel' => $row->no_sampel,
                        'keterangan_1' => $row->keterangan_1,
                        'keterangan_2' => $row->keterangan_2,
                        'tanggal_sampling' => $row->tanggal_sampling,
                    ];
                })->values(),
            ],
        ]);
    }

    /** Daftar transaksi observasi per QT — untuk lembar pengesahan */
    public function listObservasiByNoQt(Request $request)
    {
        $noQt = trim((string) ($request->no_qt ?? $request->no_quotation ?? ''));

        if ($noQt === '') {
            return response()->json(['message' => 'Nomor QT wajib diisi'], 422);
        }

        $ticket = TicketSkK3::where('no_quotation', $noQt)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        if (!$ticket) {
            return response()->json([
                'message' => 'Nomor QT tidak ditemukan pada ticket SK K3',
            ], 404);
        }

        $orderDetail = OrderDetail::where('no_quotation', $noQt)
            ->where('is_active', true)
            ->orderBy('no_sampel')
            ->first();

        $observasi = ObservasiK3::where('no_quotation', $noQt)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->get([
                'id',
                'no_order',
                'nama_perusahaan',
                'tanggal_observasi',
                'waktu_observasi',
                'petugas_observasi',
                'jenis_kegiatan',
                'is_pengesahan_selesai',
                'created_at',
            ]);

        return response()->json([
            'data' => [
                'no_quotation' => $noQt,
                'nomor_ticket_sk_k3' => $ticket->nomor_ticket,
                'no_order' => $orderDetail->no_order ?? null,
                'nama_perusahaan' => $ticket->nama_perusahaan ?: ($orderDetail->nama_perusahaan ?? null),
                'observasi_list' => $observasi->map(function ($row) {
                    return [
                        'id' => $row->id,
                        'no_order' => $row->no_order,
                        'nama_perusahaan' => $row->nama_perusahaan,
                        'tanggal_observasi' => $row->tanggal_observasi,
                        'waktu_observasi' => $row->waktu_observasi,
                        'petugas_observasi' => $row->petugas_observasi,
                        'jenis_kegiatan' => $row->jenis_kegiatan,
                        'is_pengesahan_selesai' => (bool) $row->is_pengesahan_selesai,
                        'created_at' => $row->created_at,
                    ];
                })->values(),
            ],
        ]);
    }

    /** Observasi yang diinput user login — untuk lembar pengesahan (default 14 hari) */
    public function listObservasiMilikSaya(Request $request)
    {
        $hari = (int) ($request->hari ?? 14);
        if ($hari < 1) {
            $hari = 14;
        }
        if ($hari > 90) {
            $hari = 90;
        }

        $since = Carbon::now('Asia/Jakarta')->subDays($hari)->startOfDay();

        $observasi = ObservasiK3::where('created_by', $this->karyawan)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $since)
            ->orderByDesc('id')
            ->get($this->columnsObservasiList());

        return response()->json([
            'data' => [
                'created_by' => $this->karyawan,
                'batas_hari' => $hari,
                'since' => $since->toDateTimeString(),
                'observasi_list' => $observasi->map(function ($row) {
                    $isEmailed = $this->resolveIsEmailed($row);

                    return [
                        'id' => $row->id,
                        'no_quotation' => $row->no_quotation,
                        'nomor_ticket_sk_k3' => $row->nomor_ticket_sk_k3,
                        'no_order' => $row->no_order,
                        'nama_perusahaan' => $row->nama_perusahaan,
                        'tanggal_observasi' => $row->tanggal_observasi,
                        'waktu_observasi' => $row->waktu_observasi,
                        'petugas_observasi' => $row->petugas_observasi,
                        'jenis_kegiatan' => $row->jenis_kegiatan,
                        'is_pengesahan_selesai' => (bool) $row->is_pengesahan_selesai,
                        'is_emailed' => $isEmailed,
                        'emailed_by' => $row->emailed_by,
                        'emailed_at' => $row->emailed_at ?? $row->email_pengesahan_sent_at ?? null,
                        'emailed_to' => $row->emailed_to,
                        'email_required' => (bool) $row->is_pengesahan_selesai && !$isEmailed,
                        'has_pdf' => !empty($row->file_pengesahan_pdf),
                        'created_at' => $row->created_at,
                    ];
                })->values(),
            ],
        ]);
    }

  /** Master bagian B — semua kategori aktif */
    public function listKategori()
    {
        $kategori = ObservasiK3Kategori::with('template:id,kode,judul_form,deskripsi_program')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('urutan')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'kode' => $row->kode,
                    'nama' => $row->nama,
                    'urutan' => $row->urutan,
                    'has_form' => (bool) $row->observasi_k3_template_id,
                    'template' => $row->template ? [
                        'id' => $row->template->id,
                        'kode' => $row->template->kode,
                        'judul_form' => $row->template->judul_form,
                        'deskripsi_program' => $row->template->deskripsi_program,
                    ] : null,
                ];
            });

        return response()->json(['data' => $kategori]);
    }

    /** Master bagian C — struktur form per template */
    public function getTemplateByKategori(Request $request)
    {
        $kategori = ObservasiK3Kategori::where('id', $request->kategori_id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (!$kategori || !$kategori->observasi_k3_template_id) {
            return response()->json(['message' => 'Form untuk kategori ini belum tersedia'], 404);
        }

        $template = ObservasiK3Template::with([
            'hirarki' => function ($q) {
                $q->where('is_active', true)->whereNull('deleted_at')->orderBy('urutan');
            },
            'hirarki.items' => function ($q) {
                $q->where('is_active', true)->whereNull('deleted_at')->orderBy('urutan');
            },
        ])
            ->where('id', $kategori->observasi_k3_template_id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (!$template) {
            return response()->json(['message' => 'Template tidak ditemukan'], 404);
        }

        return response()->json([
            'data' => [
                'kategori' => [
                    'id' => $kategori->id,
                    'kode' => $kategori->kode,
                    'nama' => $kategori->nama,
                ],
                'template' => [
                    'id' => $template->id,
                    'kode' => $template->kode,
                    'judul_form' => $template->judul_form,
                    'deskripsi_program' => $template->deskripsi_program,
                    'hirarki' => $template->hirarki->map(function ($h) {
                        return [
                            'id' => $h->id,
                            'kode' => $h->kode,
                            'label' => $h->label,
                            'sub_label' => $h->sub_label,
                            'items' => $h->items->map(function ($item) {
                                return [
                                    'id' => $item->id,
                                    'pertanyaan' => $item->pertanyaan,
                                    'tipe_input' => $item->tipe_input,
                                    'config' => $item->config,
                                    'wajib_keterangan' => $item->wajib_keterangan,
                                ];
                            })->values(),
                        ];
                    })->values(),
                ],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validationError = $this->validateObservasiStore($request);
        if ($validationError !== null) {
            return response()->json(['message' => $validationError], 422);
        }

        $fotoFiles = [];
        $index = 1;

        while ($request->has("foto_lokasi_{$index}")) {
            $foto = $request->input("foto_lokasi_{$index}");
            if ($foto) {
                $fotoFiles[] = $this->convertImg($foto, "{$index}", $this->user_id);
            }
            $index++;
        }

        $obs = ObservasiK3::create([
            'no_quotation' => $request->no_quotation ?? $request->no_qt,
            'nomor_ticket_sk_k3' => $request->nomor_ticket_sk_k3,
            'no_order' => $request->no_order,
            'nama_perusahaan' => $request->nama_perusahaan,
            'nomor_sampel' => $this->decodeJsonField($request->nomor_sampel),
            'lokasi' => $this->decodeJsonField($request->lokasi),
            'jenis_kegiatan' => $request->jenis_kegiatan,
            'tanggal_observasi' => $request->tanggal_observasi,
            'waktu_observasi' => $request->waktu_observasi,
            'petugas_observasi' => $request->petugas_observasi ?? $this->karyawan,
            'kategori_terpilih' => $this->decodeJsonField($request->kategori_terpilih),
            'form_jawaban' => $this->decodeJsonField($request->form_jawaban),
            'foto' => $fotoFiles,
            'is_active' => true,
            'is_pengesahan_selesai' => false,
            'created_by' => $this->karyawan,
            'created_at' => Carbon::now(),
        ]);

        InsertActivityFdl::by($this->user_id)
            ->action('input')
            ->target("Observasi K3 pada QT " . ($request->no_quotation ?? $request->no_qt ?? $request->no_order))
            ->save();

        return response()->json([
            'message' => 'Data observasi K3 berhasil disimpan. Lanjutkan lembar pengesahan.',
            'data' => ['id' => $obs->id],
        ], 201);
    }

    public function getPengesahan(Request $request)
    {
        $id = $request->id ?? $request->observasi_k3_id;
        if (!$id) {
            return response()->json(['message' => 'ID observasi wajib diisi'], 422);
        }

        $obs = ObservasiK3::where('id', $id)->where('is_active', true)->first();
        if (!$obs) {
            return response()->json(['message' => 'Data observasi tidak ditemukan'], 404);
        }

        $snapshot = $obs->snapshot_pengesahan;
        if (!$snapshot || (($snapshot['format_version'] ?? 1) < 2)) {
            $snapshot = $this->buildSnapshotPengesahan($obs);
        }

        $baseUrl = rtrim(url('/dokumentasi/sampling'), '/');
        $fotoUrls = collect($obs->foto ?? [])
            ->filter()
            ->map(function ($file) use ($baseUrl) {
                return "{$baseUrl}/{$file}";
            })
            ->values()
            ->all();

        $kategoriTerpilih = collect($obs->kategori_terpilih ?? [])
            ->pluck('nama')
            ->filter()
            ->values()
            ->all();

        if ($obs->is_pengesahan_selesai && !$obs->file_pengesahan_pdf) {
            $snapshotForPdf = $snapshot ?? $this->buildSnapshotPengesahan($obs);
            $pdfService = new ObservasiK3PengesahanPdfService();
            $pdfFilename = $pdfService->generate($obs, $snapshotForPdf);
            $obs->update(['file_pengesahan_pdf' => $pdfFilename]);
            $obs->refresh();
        }

        $emailMeta = $this->resolveEmailMetaObservasi($obs);
        $emailInfo = $this->mapEmailInfoObservasi($obs);

        return response()->json([
            'data' => array_merge([
                'id' => $obs->id,
                'is_pengesahan_selesai' => (bool) $obs->is_pengesahan_selesai,
                'jabatan_petugas' => $obs->jabatan_petugas,
                'nama_mengetahui' => $obs->nama_mengetahui,
                'tanggal_pengesahan' => $obs->tanggal_pengesahan,
                'petugas_observasi' => $obs->petugas_observasi,
                'ttd_petugas' => $obs->ttd_petugas,
                'ttd_mengetahui' => $obs->ttd_mengetahui,
                'ttd_petugas_url' => $obs->ttd_petugas ? "{$baseUrl}/{$obs->ttd_petugas}" : null,
                'ttd_mengetahui_url' => $obs->ttd_mengetahui ? "{$baseUrl}/{$obs->ttd_mengetahui}" : null,
                'foto_urls' => $fotoUrls,
                'kategori_terpilih' => $kategoriTerpilih,
                'file_pengesahan_pdf' => $obs->file_pengesahan_pdf,
                'pdf_path' => $obs->file_pengesahan_pdf ? 'dokumen/observasi_k3/' . $obs->file_pengesahan_pdf : null,
                'email_meta' => $emailMeta,
                'snapshot' => $snapshot,
            ], $emailInfo),
        ]);
    }

    public function savePengesahan(Request $request)
    {
        $id = $request->id ?? $request->observasi_k3_id;
        if (!$id) {
            return response()->json(['message' => 'ID observasi wajib diisi'], 422);
        }

        $obs = ObservasiK3::where('id', $id)->where('is_active', true)->first();
        if (!$obs) {
            return response()->json(['message' => 'Data observasi tidak ditemukan'], 404);
        }

        if ($obs->is_pengesahan_selesai) {
            return response()->json(['message' => 'Lembar pengesahan sudah disimpan'], 422);
        }

        $jabatan = trim((string) ($request->jabatan_petugas ?? ''));
        $namaMengetahui = trim((string) ($request->nama_mengetahui ?? ''));
        $tanggal = $request->tanggal_pengesahan;
        $ttdPetugas = $request->ttd_petugas;
        $ttdMengetahui = $request->ttd_mengetahui;

        if ($jabatan === '') {
            return response()->json(['message' => 'Jabatan petugas wajib diisi'], 422);
        }
        if ($namaMengetahui === '') {
            return response()->json(['message' => 'Nama yang mengetahui (customer) wajib diisi'], 422);
        }
        if (!$tanggal) {
            return response()->json(['message' => 'Tanggal pengesahan wajib diisi'], 422);
        }
        if (!$ttdPetugas) {
            return response()->json(['message' => 'Tanda tangan petugas wajib'], 422);
        }
        if (!$ttdMengetahui) {
            return response()->json(['message' => 'Tanda tangan yang mengetahui wajib'], 422);
        }

        $filePetugas = $this->convertImg($ttdPetugas, '_ttd_petugas', $this->user_id);
        $fileMengetahui = $this->convertImg($ttdMengetahui, '_ttd_mengetahui', $this->user_id);
        $obs->jabatan_petugas = $jabatan;
        $snapshot = $this->buildSnapshotPengesahan($obs);

        $obs->update([
            'jabatan_petugas' => $jabatan,
            'nama_mengetahui' => $namaMengetahui,
            'tanggal_pengesahan' => $tanggal,
            'ttd_petugas' => $filePetugas,
            'ttd_mengetahui' => $fileMengetahui,
            'snapshot_pengesahan' => $snapshot,
            'is_pengesahan_selesai' => true,
            'is_emailed' => false,
            'updated_by' => $this->karyawan,
            'updated_at' => Carbon::now(),
        ]);

        $obs->refresh();
        $pdfService = new ObservasiK3PengesahanPdfService();
        $pdfFilename = $pdfService->generate($obs, $snapshot);
        $obs->update(['file_pengesahan_pdf' => $pdfFilename]);

        InsertActivityFdl::by($this->user_id)
            ->action('input')
            ->target("Lembar pengesahan Observasi K3 #{$obs->id}")
            ->save();

        $emailMeta = $this->resolveEmailMetaObservasi($obs);
        $emailInfo = $this->mapEmailInfoObservasi($obs);

        return response()->json([
            'message' => 'Lembar pengesahan berhasil disimpan',
            'status' => 'success',
            'email_required' => $emailInfo['email_required'],
            'data' => [
                'id' => $obs->id,
                'file_pengesahan_pdf' => $pdfFilename,
                'pdf_path' => 'dokumen/observasi_k3/' . $pdfFilename,
                'email_meta' => $emailMeta,
            ],
        ]);
    }

    public function sendEmailPengesahan(Request $request)
    {
        $id = $request->id ?? $request->observasi_k3_id;
        if (!$id) {
            return response()->json(['message' => 'ID observasi wajib diisi', 'status' => 'error'], 422);
        }

        $obs = ObservasiK3::where('id', $id)->where('is_active', true)->first();
        if (!$obs || !$obs->is_pengesahan_selesai) {
            return response()->json(['message' => 'Lembar pengesahan belum lengkap', 'status' => 'error'], 422);
        }

        if ($this->resolveIsEmailed($obs)) {
            return response()->json(['message' => 'Email lembar pengesahan sudah pernah dikirim', 'status' => 'error'], 422);
        }

        $pdfFilename = $request->input('attachments.0') ?? $request->input('pdf_file') ?? $obs->file_pengesahan_pdf;
        if (!$pdfFilename || $pdfFilename !== $obs->file_pengesahan_pdf) {
            return response()->json(['message' => 'File PDF tidak valid', 'status' => 'error'], 422);
        }

        $base = realpath(public_path('dokumen/observasi_k3'));
        $path = realpath(public_path('dokumen/observasi_k3/' . $pdfFilename));
        if (!$base || !$path || dirname($path) !== $base || !is_file($path)) {
            return response()->json(['message' => 'File PDF tidak ditemukan', 'status' => 'error'], 422);
        }

        $subject = trim((string) $request->input('subject', ''));
        $content = (string) $request->input('content', '');
        $to = trim((string) $request->input('to', ''));
        $cc = $request->input('cc', '');

        if ($subject === '' || $content === '' || $to === '') {
            return response()->json(['message' => 'Subject, isi email, dan penerima wajib diisi', 'status' => 'error'], 422);
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'Email penerima tidak valid', 'status' => 'error'], 422);
        }

        $ccArray = [];
        if (is_array($cc)) {
            $ccArray = $cc;
        } elseif (is_string($cc) && trim($cc) !== '') {
            $ccArray = array_filter(array_map('trim', explode(',', $cc)));
        }
        foreach ($ccArray as $address) {
            if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
                return response()->json(['message' => 'CC email tidak valid', 'status' => 'error'], 422);
            }
        }

        $bcc = ['faidhah@intilab.com'];
        $emailInstance = SendEmail::where('to', $to)
            ->where('cc', $ccArray)
            ->where('bcc', $bcc)
            ->where('subject', $subject)
            ->where('body', $content)
            ->noReply()
            ->where('attachment', [$path]);

        $sent = $emailInstance->send();
        if ($sent !== true) {
            return response()->json(['message' => 'Gagal mengirim email', 'status' => 'error'], 500);
        }

        $now = Carbon::now();
        $obs->update([
            'is_emailed' => true,
            'emailed_by' => $this->karyawan,
            'emailed_at' => $now,
            'emailed_to' => $to,
            'emailed_cc' => count($ccArray) ? implode(',', $ccArray) : null,
            'email_pengesahan_sent_at' => $now,
            'updated_by' => $this->karyawan,
            'updated_at' => $now,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Email berhasil dikirim',
            'filename' => $pdfFilename,
            'observasi_k3_id' => $obs->id,
            'email' => $this->mapEmailInfoObservasi($obs->fresh()),
        ]);
    }

    private function resolveIsEmailed(ObservasiK3 $obs): bool
    {
        if (Schema::hasColumn('observasi_k3', 'is_emailed')) {
            return (bool) $obs->is_emailed;
        }

        return !empty($obs->email_pengesahan_sent_at);
    }

    private function mapEmailInfoObservasi(ObservasiK3 $obs): array
    {
        $isEmailed = $this->resolveIsEmailed($obs);

        return [
            'is_emailed' => $isEmailed,
            'emailed_by' => $obs->emailed_by,
            'emailed_at' => $obs->emailed_at ?? $obs->email_pengesahan_sent_at,
            'emailed_to' => $obs->emailed_to,
            'emailed_cc' => $obs->emailed_cc,
            'email_required' => (bool) $obs->is_pengesahan_selesai && !$isEmailed,
        ];
    }

    private function resolveEmailMetaObservasi(ObservasiK3 $obs): array
    {
        $header = OrderHeader::query()
            ->where('is_active', true)
            ->where(function ($q) use ($obs) {
                if ($obs->no_quotation) {
                    $q->where('no_document', $obs->no_quotation);
                }
                if ($obs->no_order) {
                    $q->orWhere('no_order', $obs->no_order);
                }
            })
            ->orderByDesc('id')
            ->first([
                'no_order',
                'no_document',
                'nama_perusahaan',
                'nama_pic_sampling',
                'email_pic_sampling',
                'tanggal_order',
            ]);

        return [
            'no_order' => $header->no_order ?? $obs->no_order,
            'no_quotation' => $header->no_document ?? $obs->no_quotation,
            'nama_perusahaan' => $header->nama_perusahaan ?? $obs->nama_perusahaan,
            'nama_pic_sampling' => $header->nama_pic_sampling ?? null,
            'email_pic_sampling' => $header->email_pic_sampling ?? null,
            'tanggal_observasi' => $obs->tanggal_observasi,
        ];
    }

    private function buildSnapshotPengesahan(ObservasiK3 $obs): array
    {
        $lokasi = $obs->lokasi ?? [];
        if (!is_array($lokasi) || count($lokasi) === 0) {
            $lokasi = ['—'];
        }

        $kategoriTerpilih = $obs->kategori_terpilih ?? [];
        $jawaban = $obs->form_jawaban ?? [];
        $uraian = [];

        foreach ($lokasi as $titik) {
            foreach ($kategoriTerpilih as $kat) {
                $kid = (string) ($kat['kategori_id'] ?? '');
                if ($kid === '') {
                    continue;
                }

                $kategori = ObservasiK3Kategori::with([
                    'template.hirarki' => function ($q) {
                        $q->where('is_active', true)->whereNull('deleted_at')->orderBy('urutan');
                    },
                    'template.hirarki.items' => function ($q) {
                        $q->where('is_active', true)->whereNull('deleted_at')->orderBy('urutan');
                    },
                ])->find($kid);

                if (!$kategori || !$kategori->template) {
                    continue;
                }

                $byPoin = [];
                $nomorPoin = 1;
                $templateKode = (string) ($kategori->template->kode ?? '');

                foreach ($kategori->template->hirarki as $h) {
                    $lines = [];
                    foreach ($h->items as $item) {
                        $ans = $jawaban[$kid][$item->id] ?? $jawaban[$kid][(string) $item->id] ?? null;
                        if (!$ans || !is_array($ans)) {
                            continue;
                        }
                        if (!$this->isPositiveObservasiAnswer($ans, $item->tipe_input)) {
                            continue;
                        }

                        if ($item->tipe_input === 'pilih_opsi' && stripos($item->pertanyaan, 'Pilih Jenis AC') !== false) {
                            $opsi = trim((string) ($ans['opsi_terpilih'] ?? ''));
                            $ket = trim((string) ($ans['keterangan'] ?? ''));
                            $acParts = [];
                            if ($opsi !== '') {
                                $acParts[] = "Penggunaan AC {$opsi}";
                            }
                            if ($ket !== '') {
                                $acParts[] = $ket;
                            }
                            $acDetail = implode(', ', $acParts);
                            $lastIdx = count($lines) - 1;
                            if ($lastIdx >= 0 && stripos($lines[$lastIdx], 'Sistem ventilasi/pendingin') !== false) {
                                if ($acDetail !== '') {
                                    $lines[$lastIdx] .= " ({$acDetail})";
                                }
                            } elseif ($acDetail !== '') {
                                $lines[] = $acDetail;
                            }
                            continue;
                        }

                        $lines[] = $this->formatObservasiAnswerLine($item->pertanyaan, $ans, $item->tipe_input);
                    }
                    if (count($lines) > 0) {
                        $judulPoin = $this->formatJudulPoinPengesahan($templateKode, (string) $h->kode, (string) $h->label);
                        $byPoin[] = [
                            'poin' => $nomorPoin . '. ' . $judulPoin,
                            'pengendalian' => $lines,
                        ];
                        $nomorPoin++;
                    }
                }

                if (count($byPoin) > 0) {
                    $uraian[] = [
                        'nama_titik' => $titik,
                        'kategori' => mb_strtoupper((string) ($kat['nama'] ?? $kategori->nama), 'UTF-8'),
                        'detail' => $byPoin,
                    ];
                }
            }
        }

        return [
            'format_version' => 2,
            'identitas' => [
                'nama_perusahaan' => $obs->nama_perusahaan,
                'nomor_ticket_sk_k3' => $obs->nomor_ticket_sk_k3,
                'no_quotation' => $obs->no_quotation,
                'no_order' => $obs->no_order,
                'nomor_sampel' => $obs->nomor_sampel,
                'lokasi' => $obs->lokasi,
                'jenis_kegiatan' => $obs->jenis_kegiatan,
                'tanggal_observasi' => $obs->tanggal_observasi,
                'waktu_observasi' => $obs->waktu_observasi,
                'petugas_observasi' => $obs->petugas_observasi,
                'jabatan_petugas' => $obs->jabatan_petugas,
            ],
            'uraian' => $uraian,
            'pembuka' => 'Yang bertanda tangan di bawah ini menyatakan bahwa kegiatan Observasi dan Pendataan Pengendalian K3 di Tempat Kerja telah dilaksanakan dengan benar sesuai dengan prosedur yang berlaku dan berdasarkan kondisi aktual di lapangan.',
            'pernyataan_list' => [
                'Data yang tercantum dalam Form Observasi K3 merupakan hasil pengamatan langsung di lapangan.',
                'Pengisian form dilakukan secara objektif, sistematis, dan dapat dipertanggungjawabkan.',
                'Hasil observasi digunakan sebagai dasar pelaksanaan pendampingan dan pengendalian K3.',
            ],
            'pernyataan' => 'Demikian lembar pengesahan ini dibuat untuk digunakan sebagaimana mestinya.',
            'generated_at' => Carbon::now('Asia/Jakarta')->toIso8601String(),
        ];
    }

    private function formatJudulPoinPengesahan(string $templateKode, string $kode, string $rawLabel): string
    {
        $kodeUpper = strtoupper(trim($kode));

        if ($templateKode === 'higiene_bangunan') {
            $mapHigiene = [
                'A' => 'Halaman',
                'B' => 'Gedung',
                'C' => 'Bangunan Bawah Tanah',
            ];
            if (isset($mapHigiene[$kodeUpper])) {
                return $mapHigiene[$kodeUpper];
            }
        }

        if ($templateKode === 'fasilitas_toilet') {
            $mapToilet = [
                'C' => 'Toilet Laki-Laki',
                'D' => 'Toilet Perempuan',
                'E' => 'Toilet Penyandang Cacat',
            ];
            if (isset($mapToilet[$kodeUpper])) {
                return $mapToilet[$kodeUpper];
            }
        }

        $standardMap = [
            'A' => 'Pengendalian Eliminasi',
            'B' => 'Substitusi Alat/Bahan/Proses',
            'C' => $templateKode === 'kebisingan'
                ? 'Rekayasa Teknik (Pembatas/Peredam/Penutupan)'
                : 'Rekayasa Teknik',
            'D' => $templateKode === 'kebisingan'
                ? 'Pengaturan/Pembatasan Pajanan (Administratif)'
                : 'Pengaturan/Pembatasan Pajanan',
            'E' => 'Alat Pelindung Diri (APD)',
            'F' => 'Pengendalian Lainnya',
        ];

        if (isset($standardMap[$kodeUpper])) {
            return $standardMap[$kodeUpper];
        }

        $cleaned = preg_replace('/^POIN\s+[A-Z0-9]+\s*[–\-]\s*/iu', '', trim($rawLabel));

        return mb_convert_case(mb_strtolower($cleaned, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    private function isPositiveObservasiAnswer($ans, string $tipeInput): bool
    {
        if ($tipeInput === 'input_teks') {
            $text = trim((string) ($ans['keterangan'] ?? $ans['jawaban'] ?? ''));

            return $text !== '';
        }

        if ($tipeInput === 'catatan_tambahan') {
            $text = trim((string) ($ans['keterangan'] ?? ''));

            return ($ans['jawaban'] ?? false) === true && $text !== '';
        }

        return ($ans['jawaban'] ?? false) === true;
    }

    private function formatObservasiAnswerLine(string $pertanyaan, array $ans, string $tipeInput): string
    {
        $ket = trim((string) ($ans['keterangan'] ?? ''));
        $opsi = trim((string) ($ans['opsi_terpilih'] ?? ''));

        if ($tipeInput === 'catatan_tambahan') {
            return $ket !== '' ? $ket : $pertanyaan;
        }

        if ($tipeInput === 'input_teks') {
            return $ket !== '' ? rtrim($pertanyaan, '.') . ': ' . $ket : $pertanyaan;
        }

        if ($tipeInput === 'pilih_opsi') {
            $detail = implode(', ', array_filter([$opsi, $ket], fn ($v) => $v !== ''));

            return $detail !== '' ? "{$pertanyaan} ({$detail})" : $pertanyaan;
        }

        if ($ket !== '') {
            return "{$pertanyaan} ({$ket})";
        }

        return $pertanyaan;
    }

    private function decodeJsonField($value)
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

  /** Kolom aman jika migrasi email belum jalan di environment tertentu */
    private function columnsObservasiList(): array
    {
        $columns = [
            'id',
            'no_quotation',
            'nomor_ticket_sk_k3',
            'no_order',
            'nama_perusahaan',
            'tanggal_observasi',
            'waktu_observasi',
            'petugas_observasi',
            'jenis_kegiatan',
            'is_pengesahan_selesai',
            'created_at',
        ];

        foreach (['is_emailed', 'emailed_by', 'emailed_at', 'emailed_to', 'email_pengesahan_sent_at', 'file_pengesahan_pdf'] as $col) {
            if (Schema::hasColumn('observasi_k3', $col)) {
                $columns[] = $col;
            }
        }

        return $columns;
    }

    private function validateObservasiStore(Request $request): ?string
    {
        $noQt = trim((string) ($request->no_quotation ?? $request->no_qt ?? ''));
        if ($noQt === '') {
            return 'Nomor QT wajib diisi';
        }
        if (trim((string) ($request->no_order ?? '')) === '') {
            return 'Nomor order wajib diisi';
        }
        if (trim((string) ($request->nomor_ticket_sk_k3 ?? '')) === '') {
            return 'Nomor ticket SK K3 wajib diisi';
        }
        if (trim((string) ($request->nama_perusahaan ?? '')) === '') {
            return 'Nama perusahaan wajib diisi';
        }
        if (trim((string) ($request->jenis_kegiatan ?? '')) === '') {
            return 'Jenis kegiatan wajib diisi';
        }
        if (!$request->tanggal_observasi) {
            return 'Tanggal observasi wajib diisi';
        }
        if (!$request->waktu_observasi) {
            return 'Waktu observasi wajib diisi';
        }
        if (trim((string) ($request->petugas_observasi ?? '')) === '') {
            return 'Petugas observasi wajib diisi';
        }

        $nomorSampel = $this->decodeJsonField($request->nomor_sampel);
        if (!is_array($nomorSampel) || count($nomorSampel) === 0) {
            return 'Pilih minimal 1 nomor sampel';
        }

        $lokasi = $this->decodeJsonField($request->lokasi);
        if (!is_array($lokasi) || count($lokasi) === 0) {
            return 'Pilih minimal 1 lokasi/area kerja';
        }

        $wajibKategori = ObservasiK3Kategori::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereNotNull('observasi_k3_template_id')
            ->orderBy('urutan')
            ->get(['id', 'nama', 'observasi_k3_template_id']);

        if ($wajibKategori->isEmpty()) {
            return 'Master kategori formulir belum tersedia';
        }

        $kategoriTerpilih = $this->decodeJsonField($request->kategori_terpilih);
        if (!is_array($kategoriTerpilih) || count($kategoriTerpilih) === 0) {
            return 'Pilih semua kategori formulir pemeriksaan';
        }

        $selectedIds = collect($kategoriTerpilih)->map(function ($row) {
            return (string) ($row['kategori_id'] ?? '');
        })->filter()->values()->all();

        foreach ($wajibKategori as $kat) {
            $kid = (string) $kat->id;
            if (!in_array($kid, $selectedIds, true)) {
                return "Kategori wajib belum dipilih: {$kat->nama}";
            }
        }

        $jawaban = $this->decodeJsonField($request->form_jawaban);
        if (!is_array($jawaban)) {
            return 'Jawaban formulir tidak valid';
        }

        foreach ($wajibKategori as $kat) {
            $kid = (string) $kat->id;
            $template = ObservasiK3Template::with([
                'hirarki' => function ($q) {
                    $q->where('is_active', true)->whereNull('deleted_at')->orderBy('urutan');
                },
                'hirarki.items' => function ($q) {
                    $q->where('is_active', true)->whereNull('deleted_at')->orderBy('urutan');
                },
            ])->where('id', $kat->observasi_k3_template_id)->where('is_active', true)->first();

            if (!$template) {
                return "Form kategori {$kat->nama} tidak ditemukan";
            }

            foreach ($template->hirarki as $section) {
                foreach ($section->items as $item) {
                    $ans = $jawaban[$kid][$item->id] ?? $jawaban[$kid][(string) $item->id] ?? null;
                    $err = $this->validateObservasiItemAnswer($item, is_array($ans) ? $ans : null);
                    if ($err !== null) {
                        return $err;
                    }
                }
            }
        }

        for ($i = 1; $i <= 4; $i++) {
            $foto = $request->input("foto_lokasi_{$i}");
            if (!is_string($foto) || trim($foto) === '') {
                return "Foto dokumentasi {$i} wajib diisi";
            }
        }

        return null;
    }

    private function validateObservasiItemAnswer($item, ?array $ans): ?string
    {
        $label = (string) ($item->pertanyaan ?? 'Pertanyaan');
        $tipe = (string) ($item->tipe_input ?? 'ya_tidak');

        if ($tipe === 'input_teks') {
            return null;
        }

        if (!is_array($ans) || !array_key_exists('jawaban', $ans) || !is_bool($ans['jawaban'])) {
            return "Pilih Ya atau Tidak: {$label}";
        }

        return null;
    }

    public function convertImg($foto = '', $type = '', $user = '')
    {
        $isPng = is_string($foto) && stripos($foto, 'image/png') !== false;
        $img = preg_replace('#^data:image/\w+;base64,#i', '', (string) $foto);
        $file = base64_decode($img);
        $ext = $isPng ? 'png' : 'jpeg';
        $safeName = date('YmdHis') . '_' . $user . $type . '.' . $ext;
        $destinationPath = public_path() . '/dokumentasi/sampling/';
        if (!is_dir($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }
        file_put_contents($destinationPath . $safeName, $file);

        return $safeName;
    }
}
