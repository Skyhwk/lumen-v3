<?php

namespace App\Http\Controllers\mobile;

use App\Services\MpdfService as Mpdf;
use Carbon\Carbon;

Carbon::setLocale('id');

use App\Models\OrderDetail;
use App\Models\QuotationKontrakD;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use App\Models\QuotationKontrakH;
use Illuminate\Support\Facades\DB;
use App\Models\QuotationNonKontrak;
use App\Http\Controllers\Controller;
use App\Models\PersiapanSampelHeader;
use App\Models\PersiapanSampelDetail;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\DataLapanganEmisiCerobong;
use App\Models\DataLapanganEmisiKendaraan;
use App\Models\DataLapanganMicrobiologi;
use App\Models\DataLapanganSenyawaVolatile;
use App\Models\DataLapanganSwab;
use App\Models\DataLapanganPartikulatMeter;
use App\Models\DataLapanganKebisingan;
use App\Models\DataLapanganCahaya;
use App\Models\DataLapanganGetaran;
use App\Models\DataLapanganGetaranPersonal;
use App\Models\DataLapanganIklimPanas;
use App\Models\DataLapanganIklimDingin;
use App\Models\DataLapanganAir;
use App\Models\DataLapanganKebisinganPersonal;
use App\Models\DataLapanganDirectLain;
use App\Models\DetailLingkunganHidup;
use App\Models\DetailLingkunganKerja;
use App\Models\DetailSenyawaVolatile;
use App\Models\DataLapanganIsokinetikHasil;
use App\Models\DataLapanganDebuPersonal;
use App\Models\DataLapanganMedanLM;
use App\Models\DataLapanganSinarUV;
use App\Models\DetailMicrobiologi;
use App\Models\DataLapanganErgonomi;
use App\Models\DataLapanganPsikologi;
use App\Models\MasterKaryawan;
use Illuminate\Support\Str; // Pastikan sudah di-import

class DokumenFdlController extends Controller
{
    public function index(Request $request) {

        $periode_akhir = Carbon::parse($request->periode_akhir);
        $periode_awal = $periode_akhir->copy()->subWeek(); // atau subDays(7)

        $periode_awal->toDateString();
        $periode_akhir->toDateString();

        $isProgrammer = MasterKaryawan::where('nama_lengkap', $this->karyawan)
            ->whereIn('id_jabatan', [41, 42])
            ->exists();

        $startDate = $isProgrammer
            ? Carbon::now()->subDays(20)->toDateString()
            : Carbon::now()->subDays(8)->toDateString();

        $endDate = Carbon::now()->toDateString();

        $data = OrderDetail::with([
            'orderHeader:id,tanggal_order,nama_perusahaan,konsultan,no_document,alamat_sampling,nama_pic_order,nama_pic_sampling,no_tlp_pic_sampling,jabatan_pic_sampling,jabatan_pic_order,is_revisi',
            'orderHeader.samplingPlan',
            'orderHeader.samplingPlan.jadwal' => function ($query) {
                $query->where('is_active', true);
            },
        ])
        ->select(['id_order_header', 'no_order', 'kategori_2', 'kategori_3', 'periode', 'tanggal_sampling'])
        ->where('is_active', true)
        ->whereBetween('tanggal_sampling', [$startDate, $endDate])
        ->groupBy(['id_order_header', 'no_order', 'kategori_2', 'kategori_3', 'periode', 'tanggal_sampling'])
        ->get();

        // Inisialisasi koleksi kosong
        $jadwalData = collect();
        $orderDetailData = collect();

        // Looping utama untuk menampung OrderDetail dan jadwal
        foreach ($data as $orderDetail) {
            $orderDetailData->push($orderDetail);

            $samplingPlans = optional($orderDetail->orderHeader)->samplingPlan;

            if ($samplingPlans) {
                $samplingPlans->each(function ($samplingPlan) use ($orderDetail, &$jadwalData) {
                    $samplingPlan->jadwal->each(function ($jadwal) use ($orderDetail, &$jadwalData) {
                        if ($jadwal->tanggal == $orderDetail->tanggal_sampling) {
                            $jadwal->no_order = $orderDetail->no_order;
                            $jadwalData->push($jadwal);
                        }
                    });
                });
            }
        }
        // dd($jadwalData);
        // Grouping berdasarkan kunci unik gabungan field
        $grouped = $jadwalData->groupBy(function ($item) {
            return implode('|', [
                $item->id_sampling,
                $item->parsial,
                $item->no_quotation,
                $item->tanggal,
                $item->periode,
                $item->nama_perusahaan,
                $item->durasi,
                $item->kategori,
                $item->status,
                $item->jam_mulai,
                $item->jam_selesai,
                $item->warna,
                $item->note,
                $item->urutan,
                $item->wilayah
            ]);
        });
        // dd($grouped);
        // Format akhir data
        $final = $grouped->map(function ($group) {
            $first = $group->first();

            return [
                'id_sampling' => $first->id_sampling,
                'no_order' => $first->no_order,
                'parsial' => $first->parsial,
                'nomor_quotation' => $first->no_quotation,
                'nama_perusahaan' => $first->nama_perusahaan,
                'jadwal' => $first->tanggal,
                'periode' => $first->periode,
                'jam_mulai' => $first->jam_mulai,
                'jam_selesai' => $first->jam_selesai,
                'kategori' => implode(', ', json_decode(html_entity_decode($first->kategori), true) ?? []),
                'durasi' => $first->durasi,
                'status' => $first->status,
                'warna' => $first->warna,
                'note' => $first->note,
                'urutan' => $first->urutan,
                'wilayah' => $first->wilayah,

                // Simulasi group_concat
                'sampler' => $group->pluck('sampler')->implode(','),
                'batch_id' => $group->pluck('id')->implode(','),
                'batch_user' => $group->pluck('userid')->implode(','),
            ];
        })->values()->toArray();
        // dd($final);
        // Filter berdasarkan nama karyawan (case-insensitive)
        if (!$isProgrammer) {
            $arrayFinal = array_filter($final, function ($item) {
                if (empty($item['sampler'])) return false;

                $samplerArray = array_map('trim', explode(',', $item['sampler']));
                
                foreach ($samplerArray as $name) {
                    if (strcasecmp($name, $this->karyawan) === 0) {
                        return true;
                    }
                }

                return false;
            });
        } else {
            // Programmer, tidak difilter
            // $arrayFinal = $final;
            // Programmer, ambil semua data tapi pilih salah satu sampler
            $arrayFinal = array_map(function ($item) {
                if (!empty($item['sampler'])) {
                    $samplerArray = array_map('trim', explode(',', $item['sampler']));
                    // Ambil sampler pertama (atau bisa diubah ke random jika mau)
                    $item['sampler'] = $samplerArray[0];
                }
                return $item;
            }, $final);
        }
        // Ambil no_order untuk query selanjutnya
        $orderNos = array_column($arrayFinal, 'no_order');

        // Ambil data persiapan header berdasarkan no_order
        $persiapanHeaders = PersiapanSampelHeader::whereIn('no_order', $orderNos)
            ->where('is_active', true)
            ->orderBy('id', 'desc')
            ->get()
            ->keyBy('no_order');

        // dd($persiapanHeaders);

        
        foreach ($arrayFinal as &$item) {
            if (isset($persiapanHeaders[$item['no_order']])) {
                $header = $persiapanHeaders[$item['no_order']];

                if ($header->detail_cs_documents) {
                    $item['detail_cs_documents'] = json_decode($header->detail_cs_documents, true);
        
                    foreach ($item['detail_cs_documents'] as $docIndex => $document) {
                        if (isset($document['tanda_tangan']) && is_array($document['tanda_tangan'])) {
                            foreach ($document['tanda_tangan'] as $key => $ttd) {
                                if (strpos($ttd['tanda_tangan'], 'data:') === 0) {
                                    $item['detail_cs_documents'][$docIndex]['tanda_tangan'][$key]['tanda_tangan_lama'] = $ttd['tanda_tangan'];
                                } else {
                                    $sign = $this->decodeImageToBase64($ttd['tanda_tangan']);
                                    if ($sign->status != 'error') {
                                        $item['detail_cs_documents'][$docIndex]['tanda_tangan'][$key]['tanda_tangan_lama'] = $ttd['tanda_tangan'];
                                        $item['detail_cs_documents'][$docIndex]['tanda_tangan'][$key]['tanda_tangan'] = $sign->base64;
                                    } else {
                                        $item['detail_cs_documents'][$docIndex]['tanda_tangan'][$key]['tanda_tangan_lama'] = $ttd['tanda_tangan'];
                                    }
                                }
                            }
                        }
                    }
                } else {
                    $item['detail_cs_documents'] = [];
                }
            } else {
                $item['detail_cs_documents'] = [];
            }
        }
        unset($item);

        // dd($arrayFinal);
        return DataTables::of($arrayFinal)->make(true);
    }

    public function detailsData(Request $request)
    {
        try {
            $loggedInUser = $this->karyawan;

            $isProgrammer = MasterKaryawan::where('nama_lengkap', $this->karyawan)
                ->whereIn('id_jabatan', [41, 42])
                ->exists();

            // Deteksi apakah jenis quotation kontrak (QTC) atau non-kontrak (QT)
            $isKontrak = Str::contains($request->no_quotation, '/QTC/');

            // Mulai query
            $data = OrderDetail::with([
                'orderHeader:id,tanggal_order,nama_perusahaan,konsultan,no_document,alamat_sampling,nama_pic_order,nama_pic_sampling,no_tlp_pic_sampling,jabatan_pic_sampling,jabatan_pic_order,is_revisi',
                'orderHeader.samplingPlan',
                'orderHeader.samplingPlan.jadwal' => function ($query) {
                    $query->where('is_active', true);
                },
            ])
            ->where('is_active', true)
            ->where('no_order', $request->no_order);

            // Jika kontrak, filter juga berdasarkan periode
            if ($isKontrak) {
                $data = $data->where('tanggal_sampling', $request->tangal_sampling);
                // $data->where('tanggal_sampling', Carbon::parse($request->tanggal_sampling)->format('Y-m'));
            }

            // Eksekusi query
            $data = $data->get();
            // dd($data);
            $flatData = [];

            // foreach ($data as $orderDetail) {
            //     if (!$orderDetail->orderHeader || !$orderDetail->orderHeader->samplingPlan) {
            //         continue;
            //     }

            //     $orderDetail->orderHeader->samplingPlan->each(function ($samplingPlan) use (&$orderDetail, &$flatData, $loggedInUser, $isProgrammer) {
            //         $jadwals = $samplingPlan->jadwal ?? collect();

            //         $jadwals->each(function ($jadwal) use (&$orderDetail, &$flatData, $loggedInUser, $isProgrammer) {
            //             if ($jadwal->tanggal == $orderDetail->tanggal_sampling) {
            //                 $allowPush = false;

            //                 if ($isProgrammer) {
            //                     // Programmer bisa lihat semua
            //                     // $allowPush = true;
            //                     // Programmer bisa lihat semua, tapi ambil salah satu sampler
            //                     $allowPush = true;

            //                     if (!empty($jadwal->sampler)) {
            //                         $samplerList = array_map('trim', explode(',', $jadwal->sampler));
            //                         // Ambil satu sampler (pertama)
            //                         $loggedInUser = $samplerList[0];
            //                         // Jika mau acak: $loggedInUser = $samplerList[array_rand($samplerList)];
            //                     }
            //                 } else {
            //                     // Non-programmer hanya kalau termasuk dalam sampler
            //                     $samplerList = array_map('trim', explode(',', $jadwal->sampler));
            //                     if (in_array($loggedInUser, $samplerList)) {
            //                         $allowPush = true;
            //                     }
            //                 }

            //                 if ($allowPush) {
            //                     $crf = explode("-", $orderDetail->kategori_3);
            //                     $nama_kategori = explode("/", $orderDetail->no_sampel ?? '');

            //                     $flatData[] = [
            //                         'sampler' => $loggedInUser,
            //                         'nomor_quotation' => $orderDetail->no_quotation,
            //                         'kategori' => implode(" - ", [($crf[1] ?? ''), ($nama_kategori[1] ?? '')]),
            //                         'no_order' => $orderDetail->no_order,
            //                         'deskripsi' => $orderDetail->keterangan_1,
            //                         'no_sampel' => $orderDetail->no_sampel,
            //                         'tanggal_sampling' => $orderDetail->tanggal_sampling,
            //                         'sample' => $nama_kategori[1] ?? ''
            //                     ];
            //                 }
            //             }
            //         });
            //     });
            // }
            foreach ($data as $orderDetail) {
                if (!$orderDetail->orderHeader || !$orderDetail->orderHeader->samplingPlan) {
                    continue;
                }

                $allowPush = false;
                $samplerTerpilih = $loggedInUser;

                if ($isProgrammer) {
                    // Programmer bisa lihat semua, ambil salah satu sampler
                    $allowPush = true;

                    foreach ($orderDetail->orderHeader->samplingPlan as $samplingPlan) {
                        $jadwal = $samplingPlan->jadwal->first() ?? null;
                        if ($jadwal && !empty($jadwal->sampler)) {
                            $samplerList = array_map('trim', explode(',', $jadwal->sampler));
                            $samplerTerpilih = $samplerList[0]; // ambil yang pertama
                            // atau pakai acak:
                            // $samplerTerpilih = $samplerList[array_rand($samplerList)];
                            break;
                        }
                    }
                } else {
                    // Non-programmer: cek apakah user termasuk di sampler jadwal
                    foreach ($orderDetail->orderHeader->samplingPlan as $samplingPlan) {
                        foreach ($samplingPlan->jadwal ?? [] as $jadwal) {
                            if ($jadwal->tanggal == $orderDetail->tanggal_sampling) {
                                $samplerList = array_map('trim', explode(',', $jadwal->sampler));
                                if (in_array($loggedInUser, $samplerList)) {
                                    $allowPush = true;
                                    break 2; // keluar dari dua loop
                                }
                            }
                        }
                    }
                }

                if ($allowPush) {
                    $crf = explode("-", $orderDetail->kategori_3);
                    $nama_kategori = explode("/", $orderDetail->no_sampel ?? '');

                    $flatData[] = [
                        'sampler' => $samplerTerpilih,
                        'nomor_quotation' => $orderDetail->no_quotation,
                        'kategori' => implode(" - ", [($crf[1] ?? ''), ($nama_kategori[1] ?? '')]),
                        'no_order' => $orderDetail->no_order,
                        'deskripsi' => $orderDetail->keterangan_1,
                        'no_sampel' => $orderDetail->no_sampel,
                        'tanggal_sampling' => $orderDetail->tanggal_sampling,
                        'sample' => $nama_kategori[1] ?? ''
                    ];
                }
            }
            
            $persiapan = PersiapanSampelHeader::select('detail_cs_documents')
                ->where('no_quotation', $request->no_quotation)
                ->where('is_active', true)
                ->orderBy('id', 'desc')
                ->first();
    
            // Lakukan pencocokan signature per baris data
            if ($persiapan && $persiapan->detail_cs_documents) {
                $csDocuments = json_decode($persiapan->detail_cs_documents, true);
                foreach ($flatData as &$row) {
                    // Ambil sample number dari row (contoh: "001")
                    $rowSample = $row['sample'];
                    $matchingDocs = [];
                    if (is_array($csDocuments)) {
                        foreach ($csDocuments as $doc) {
                            // Pastikan dokumen memiliki field no_sampel berbentuk array
                            if (isset($doc['no_sampel']) && is_array($doc['no_sampel'])) {
                                // Cek apakah sample row ada di dalam array dokumen
                                if (in_array($rowSample, $doc['no_sampel'])) {
                                    $matchingDocs[] = $doc;
                                }
                            }
                        }
                    }
                    
                    if (!empty($matchingDocs)) {
                        // Jika ada lebih dari satu, coba pilih berdasarkan tanggal yang sesuai
                        $selectedDoc = null;
                        foreach ($matchingDocs as $doc) {
                            if (isset($doc['tanggal']) && $doc['tanggal'] == $row['tanggal_sampling']) {
                                $selectedDoc = $doc;
                                break;
                            }
                        }
                        if (!$selectedDoc) {
                            // Jika tidak ada yang tanggalnya persis cocok, pilih yang paling baru
                            usort($matchingDocs, function($a, $b) {
                                return strtotime($b['tanggal']) - strtotime($a['tanggal']);
                            });
                            $selectedDoc = $matchingDocs[0];
                        }
                        // Tambahkan properti signature ke baris data
                        $row['nama_sampler'] = $selectedDoc['nama_sampler_cs'] ?? null;
                        $row['ttd_sampler']  = $selectedDoc['ttd_sampler_cs'] ?? null;
                        $row['nama_pic']     = $selectedDoc['nama_pic_cs'] ?? null;
                        $row['ttd_pic']      = $selectedDoc['ttd_pic_cs'] ?? null;
                        $row['filename_cs']  = $selectedDoc['filename_cs'] ?? null;
                    } else {
                        // Jika tidak ditemukan dokumen yang cocok, set field signature null
                        $row['nama_sampler'] = null;
                        $row['ttd_sampler']  = null;
                        $row['nama_pic']     = null;
                        $row['ttd_pic']      = null;
                        $row['filename_cs']  = null;
                    }
                }
                unset($row);
            }

            // Urutkan berdasarkan no_sampel ASC
            $sorted = collect($flatData)
                ->sortBy('no_sampel')
                ->values()
                ->toArray();
            
            return DataTables::of($sorted)->make(true);

        } catch (\Throwable $th) {
            \Log::error('Gagal memuat detail data sampler login', [
                'error' => $th->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan saat memproses data.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function updateData(Request $request)
    {
    
        // dd($request->all());
        if ($request->has('data') && !empty($request->data)) {
            DB::beginTransaction();
            try {
                // Update keterangan and collect no_sampel
                $noSampel = [];
                foreach ($request->data as $item) {
                    if (!isset($item['no_sampel']) || !isset($item['deskripsi'])) {
                        continue;
                    }
                    $po = OrderDetail::where('no_sampel', $item['no_sampel'])
                        ->where('tanggal_sampling', $item['tanggal_sampling'])
                        ->where('no_order', $item['no_order'])
                        ->where('is_active', 1)
                        ->first();
                    
                    if ($po) {
                        $isContract = str_contains($item['nomor_quotation'], 'QTC');
                        if (!$isContract) {
                            $qt = QuotationNonKontrak::where('no_document', $item['nomor_quotation'])->first();
                            if ($qt) {
                                $data_pendukung_sampling = json_decode($qt->data_pendukung_sampling);
                                foreach ($data_pendukung_sampling as &$dps) {
                                    foreach ($dps->penamaan_titik as &$pt) {
                                        $nomor = key((array) $pt);
                                        if ($nomor == explode('/', $item['no_sampel'])[1]) {
                                            $pt->$nomor = $item['deskripsi'];
                                        }
                                    }
                                }

                                $qt->data_pendukung_sampling = json_encode($data_pendukung_sampling);
                                $qt->save();
                            }
                        } else {
                            // dd('masuk ke kontrak');
                            $groupedNamedPoints = [];

                            $qtcHeader = QuotationKontrakH::where('no_document', $item['nomor_quotation'])->first();
                            $qtcDetail = QuotationKontrakD::where('id_request_quotation_kontrak_h', $qtcHeader->id)->get();

                            // UPDATE QTCD
                            if ($qtcDetail) {
                                foreach ($qtcDetail as $qtD) {
                                    $data_pendukung_sampling = json_decode($qtD->data_pendukung_sampling);
                                    foreach ($data_pendukung_sampling as &$dps) {
                                        foreach ($dps->data_sampling as &$ds) {
                                            foreach ($ds->penamaan_titik as &$pt) {
                                                $nomor = key((array) $pt);
                                                if ($nomor == explode('/', $item['no_sampel'])[1]) {
                                                    $pt->$nomor = $item['deskripsi'];
                                                }
                                                $props = get_object_vars($pt);
                                                $nomor = key($props);
                                                $titik = $props[$nomor];
                                                // $fullGroupKey = $ds->kategori_1 . ';' . $ds->kategori_2 . ';' . empty($ds->regulasi) ? json_encode([]) : json_encode($ds->regulasi) . ';' . json_encode($ds->parameter);
                                                $fullGroupKey = $ds->kategori_1 . ';' . $ds->kategori_2 . ';' . (
                                                    empty($ds->regulasi) 
                                                        ? json_encode([]) 
                                                        : json_encode($ds->regulasi)
                                                ) . ';' . json_encode($ds->parameter);
                                                // dd($fullGroupKey);
                                                $groupedNamedPoints[$fullGroupKey][$dps->periode_kontrak][] = [
                                                    $nomor => $titik
                                                ];
                                            }
                                        }
                                    }
                                    // dd($data_pendukung_sampling);
                                    $qtD->data_pendukung_sampling = json_encode($data_pendukung_sampling);
                                    $qtD->save();
                                }
                            }

                            
                            // UPDATE QTCH
                            // dd(array_keys($groupedNamedPoints));
                            // if ($qtcHeader) {
                            //     $data_pendukung_sampling = json_decode($qtcHeader->data_pendukung_sampling);
                            //     foreach ($data_pendukung_sampling as &$dps) {

                            //         $fullGroupKey = $dps->kategori_1 . ';' . $dps->kategori_2 . ';' . json_encode($dps->regulasi) . ';' . json_encode($dps->parameter);

                            //         // Filter penamaan titik
                            //         dump( $groupedNamedPoints[$fullGroupKey]);
                            //         $penamaan_sampling_all = array_filter($groupedNamedPoints[$fullGroupKey], function ($group) {
                            //             if (!is_array($group))
                            //                 return false;
                            //             foreach ($group as $item) {
                            //                 if (is_array($item) || is_object($item)) {
                            //                     foreach ($item as $value) {
                            //                         if (!empty($value))
                            //                             return true;
                            //                     }
                            //                 }
                            //             }
                            //             return false;
                            //         });

                            //         // Proses penamaan titik Header
                            //         if ($penamaan_sampling_all) {
                            //             $penamaan_sampling = array_map(function ($item) {
                            //                 return array_values($item)[0] ?? "";
                            //             }, reset($penamaan_sampling_all));
                            //         } else {
                            //             $penamaan_sampling = array_fill(0, $dps->jumlah_titik, "");
                            //         }

                            //         $dps->penamaan_titik = $penamaan_sampling;
                            //     }

                            //     $qtcHeader->data_pendukung_sampling = json_encode($data_pendukung_sampling);
                            //     $qtcHeader->save();
                            // }
                        }
                    }

                    $po->keterangan_1 = $item['deskripsi'];
                    
                    $po->save();
                    $noSampel[] = $item['no_sampel'];
                }

                // Quotation number and sampling date
                $nomorQuotation  = $request->data[0]['nomor_quotation'] ?? null;
                $tanggalSampling = $request->data[0]['tanggal_sampling'] ?? date('Y-m-d');
                if (! $nomorQuotation) {
                    return response()->json(['message' => 'Nomor quotation tidak ditemukan'], 400);
                }

                // PersiapanSampelHeader
                $persiapanSampel = PersiapanSampelHeader::where('no_quotation', $nomorQuotation)->where('tanggal_sampling', $tanggalSampling)->where('is_active', 1)->orderBy('id', 'desc')->first();
                // dd($persiapanSampel);
                if (! $persiapanSampel) {
                    return response()->json(['message'=>'Persiapan Belum Disiapkan Harap Menghubungi Admin Sampling Untuk Melakukan Update Persiapan'],401);
                    // $persiapanSampel = new PersiapanSampelHeader();
                    // $persiapanSampel->no_quotation      = $nomorQuotation;
                    // $persiapanSampel->detail_cs_documents = json_encode([]);
                }

                // Sampler
                if ($request->ttd_sampler) {
                    if ($request->filled('ttd_sampler_old')) {
                        $oldSamplerPath = public_path('dokumen/cs/signatures/' . $request->ttd_sampler_old);
                        if (file_exists($oldSamplerPath)) {
                            unlink($oldSamplerPath);
                        }
                    }
                    $ttd_sampler = $this->convertBase64ToImage($request->ttd_sampler);
                    if ($ttd_sampler->status === 'error') {
                        return response()->json(['message' => $ttd_sampler->message], 400);
                    }
                } else {
                    $ttd_sampler = null;
                }

                // PIC
                if ($request->ttd_pic) {
                    if ($request->filled('ttd_pic_old')) {
                        $oldPicPath = public_path('dokumen/cs/signatures/' . $request->ttd_pic_old);
                        if (file_exists($oldPicPath)) {
                            unlink($oldPicPath);
                        }
                    }
                    $ttd_pic = $this->convertBase64ToImage($request->ttd_pic);
                    if ($ttd_pic->status === 'error') {
                        return response()->json(['message' => $ttd_pic->message], 400);
                    }
                } else {
                    $ttd_pic = null;
                }

                // Update detail_cs_documents JSON
                $csDocuments = $persiapanSampel->detail_cs_documents
                    ? json_decode($persiapanSampel->detail_cs_documents, true)
                    : [];

                $newDocument = [
                    'tanggal'         => $tanggalSampling,
                    'nama_sampler_cs' => $request->nama_sampler,
                    'ttd_sampler_cs'  => $ttd_sampler ? $ttd_sampler->filename : null,
                    'nama_pic_cs'     => $request->nama_pic,
                    'ttd_pic_cs'      => $ttd_pic ? $ttd_pic->filename : null,
                    'filename_cs'     => $request->filename_cs,
                    'no_sampel'       => $request->no_sampel,
                ];

                $found = false;
                foreach ($csDocuments as $idx => $doc) {
                    if (isset($doc['no_sampel']) && $doc['no_sampel'] == $request->no_sampel) {
                        $csDocuments[$idx] = $newDocument;
                        $found = true;
                        break;
                    }
                }
                if (! $found) {
                    $csDocuments[] = $newDocument;
                }
                $persiapanSampel->detail_cs_documents = json_encode($csDocuments);
                $persiapanSampel->save();

                // Load quotation model
                $qtType  = explode('/', $nomorQuotation)[1] ?? '';
                $qtModel = $qtType == 'QTC'
                    ? QuotationKontrakH::class
                    : QuotationNonKontrak::class;

                $quotation = $qtModel::with(['order', 'sampling'])
                    ->where('no_document', $nomorQuotation)
                    ->first();

                if ($quotation) {
                    // Ambil semua PersiapanSampelDetail sesuai no_sampel
                    $psDetails = PersiapanSampelDetail::whereIn('no_sampel', $noSampel)->get();
                    // dd($psDetails);
                    // Ambil orderDetail yang mau di-PDF
                    $orderDetails = $quotation->order->orderDetail()
                        // ->where('tanggal_sampling', $tanggalSampling)
                        ->whereIn('no_sampel', $noSampel)
                        ->get();
                    // dd($orderDetails);

                    // Hitung jumlah botol & label per item
                    foreach ($orderDetails as $item) {
                        $jumlahBotol  = 0;
                        $jumlahLabel  = 0;
                        foreach ($psDetails as $psd) {
                            if ($item->no_sampel == $psd->no_sampel) {
                                $parameters = json_decode($psd->parameters, true);
                                // dd($parameters);
                                if($parameters != null) {
                                    foreach ($parameters as $kategori => $sub) {
                                        // dd($sub);
                                        foreach ($sub as $val) {
                                            $d = (int) $val['disiapkan'];
                                            $jumlahBotol += $d;
                                            $jumlahLabel += $d * 2;
                                        }
                                    }
                                    break;
                                }
                            }
                        }
                        
                        if($item->kategori_2 == "1-Air") {
                            if (DataLapanganAir::where('no_sampel', $item->no_sampel)->exists()) {
                                $item->status_c1 = 1;
                            }
                        }else {
                            $item->status_c1 = $this->checkLengthData($item->kategori_2, $item->kategori_3, json_decode($item->parameter), $item->no_sampel);
                        }
                        $item->jumlah_botol  = $jumlahBotol;
                        $item->jumlah_label = $jumlahLabel;
                    }
                    // dd('masuk');
                    // Siapkan data signature untuk PDF
                    $signatureData = (object) [
                        'ttd_sampler' => $ttd_sampler ? $ttd_sampler->filename : null,
                        'ttd_pic'     => $ttd_pic ? $ttd_pic->filename : null,
                        'nama_pic'    => $request->nama_pic,
                        'nama_sampler'=> $request->nama_sampler,
                        'filename_cs' => $request->filename_cs,
                        'no_sampel'   => $request->no_sampel,
                    ];

                    // Hapus PDF lama
                    // if ($request->filled('filename_cs_old')) {
                    //     $oldPdfPath = public_path('dokumen/cs/' . $request->filename_cs_old);
                    //     if (file_exists($oldPdfPath)) {
                    //         unlink($oldPdfPath);
                    //     }
                    // }

                    // Generate PDF baru
                    $newFilename = $this->cetakPDF($orderDetails, $signatureData, $orderDetails);

                    if(! $newFilename) {
                        throw new \Exception('Gagal membuat dokumen PDF Coding Sample');
                    }

                    // Update CS document filename di JSON
                    foreach ($csDocuments as $idx => $doc) {
                        if (isset($doc['no_sampel']) && $doc['no_sampel'] == $request->no_sampel) {
                            $csDocuments[$idx]['filename_cs'] = $newFilename;
                            break;
                        }
                    }
                    $persiapanSampel->detail_cs_documents = json_encode($csDocuments);
                    $persiapanSampel->save();
                }

                DB::commit();
                return response()->json(['message' => 'Berhasil menyimpan data'], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Gagal menyimpan data : ' . $e->getMessage(),
                    'line'    => $e->getLine()
                ], 500);
            }
        }
        return response()->json(['message' => 'Data tidak ada'], 400);
    }
    
    public function cetakPDF($orderDetail, $signatureData, $orderDetails)
    {
        // dd($orderDetails);
        try {
            $pdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_header' => 3,
                'margin_bottom' => 3,
                'margin_footer' => 3,
                'setAutoTopMargin' => 'stretch',
                'setAutoBottomMargin' => 'stretch',
                'orientation' => 'P'
            ]);

            $konsultan = '';
            if ($orderDetail->first()->konsultan)
                $konsultan = ' (' . $orderDetail->first()->konsultan . ')';

            //Nama PDF Gunakan NoSampel
            // $noSampelString = is_array($signatureData->no_sampel) ? implode('', $signatureData->no_sampel) : '';
            // $filename = 'DOC_CS_' . ($orderDetail->first()->no_order ?? 'NO_ORDER') . '_' . $noSampelString . '.pdf';

            $filename = $signatureData->filename_cs;
            // dd($filename);

            $pdf->setFooter([
                'odd' => [
                    'C' => [
                        'content' => 'Hal {PAGENO} dari {nbpg}',
                        'font-size' => 6,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#606060'
                    ],
                    'R' => [
                        'content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem <br> {DATE YmdGi}',
                        'font-size' => 5,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#000000'
                    ],
                    'L' => [
                        'font-size' => 4,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#000000'
                    ],
                    'line' => -1,
                ]
            ]);
                
            $pdf->WriteHTML('
                <!DOCTYPE html>
                    <html>
                        <head>
                            <style>
                                .custom1 { font-size: 12px; font-weight: bold; }
                                .custom2 { font-size: 15px; font-weight: bold; text-align: center; padding: 5px; }
                                .custom3 { font-size: 12px; font-weight: bold; text-align: center; border: 1px solid #000000; padding: 5px;}
                                .custom4 { font-size: 12px; font-weight: bold; border: 1px solid #000000;padding: 5px;}
                                .custom5 { font-size: 10px; border: 1px solid #000000; padding: 5px;}
                                .custom6 { font-size: 10px; font-weight: bold; text-align: center; border: 1px solid #000000; padding: 5px;}
                            </style>
                        </head>
                        <body>
                        <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                            <tr>
                                <td class="custom1" width="200">PT INTI SURYA LABORATORIUM</td>
                                <td class="custom2" width="320">CODING SAMPLE</td>
                                <td class="custom3">' . Carbon::parse($orderDetail->first()->tanggal_sampling)->translatedFormat('d F Y') . '</td>
                            </tr>
                            <tr><td colspan="3" style="padding: 2px;"></td></tr>
                            <tr>
                                <td class="custom4">
                                    <table width="100%">
                                        <tr><td style="font-size: 9px;">NO QUOTE :</td></tr>
                                        <tr><td style="text-align: center;">' . $orderDetail->first()->no_quotation . '</td></tr>
                                    </table>
                                </td>
                                <td width="120" class="custom4" style="text-align: center;">' . $orderDetail->first()->nama_perusahaan . $konsultan . '</td>
                                <td class="custom3">' . $orderDetail->first()->no_order . '</td>
                            </tr>
                            <tr><td colspan="3" style="padding: 2px;"></td></tr>
                        </table>
            ');

            $pdf->defaultheaderline = 0;
            $pdf->SetHeader('
                        <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                            <tr>
                                <td class="custom1" width="200">PT INTI SURYA LABORATORIUM</td>
                                <td class="custom2" width="320">CODING SAMPLING</td>
                                <td class="custom3">' . Carbon::parse($orderDetail->first()->tanggal_sampling)->translatedFormat('d F Y') . '</td>
                            </tr>
                            <tr><td colspan="3" style="padding: 2px;"></td></tr>
                            <tr>
                                <td class="custom4">
                                    <table width="100%">
                                        <tr><td style="font-size: 9px;">NO QUOTE :</td></tr>
                                        <tr><td style="text-align: center;">' . $orderDetail->first()->no_quotation . '</td></tr>
                                    </table>
                                </td>
                                <td width="120" class="custom4">' . $orderDetail->first()->nama_perusahaan . $konsultan . '</td>
                                <td class="custom3">' . $orderDetail->first()->no_order . '</td>
                            </tr>
                            
                            <tr><td colspan="3" style="padding: 2px;"></td></tr>
                        </table>
            ');

            $pdf->WriteHTML('
                        <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                            <tr>
                                <th class="custom6" width="90">CS</th>
                                <th class="custom6" width="70">KATEGORI</th>
                                <th class="custom6">DESKRIPSI</th>
                                <th class="custom6" width="128">QRCODE</th>
                                <th class="custom6" width="28">CS</t>
                                <th class="custom6" width="28">C-1</th>
                                <th class="custom6" width="28">C-2</t>
                                <th class="custom6" width="28">C-3</th>
                            </tr>
            ');

            foreach ($orderDetails as $item) {
                // <td class="custom5" width="28" style="text-align: center;">' . ($signatureData->ttd_sampler ? '✔' : '') . '</td>
                $pdf->WriteHTML('
                    <tr>
                        <td class="custom5" width="90">' . $item->no_sampel . '</td>
                        <td class="custom5" width="70">' . explode("-", $item->kategori_3)[1] . '</td>
                        <td class="custom5" height="60">' . $item->keterangan_1 . '</td>
                        <td class="custom5" width="128"><img src="' . public_path() . '/barcode/sample/' . $item->file_koding_sampel . '" style="height: 30px; width:180px;"></td>
                        <td class="custom5" width="28">' . $item->jumlah_botol . '</td>
                        <td class="custom5" width="28" style="text-align: center;">' . ($item->status_c1 == 1 ? '✔' : '') . '</td>
                        <td class="custom5" width="28"></td>
                        <td class="custom5" width="28"></td>
                    </tr>
                ');
            }
            $sign_sampler = $this->decodeImageToBase64($signatureData->ttd_sampler);
            $sign_pic = null;
            if($signatureData->ttd_pic != null)$sign_pic = $this->decodeImageToBase64($signatureData->ttd_pic);
            if($sign_sampler->status === 'error' || $sign_pic && $sign_pic->status === 'error'){
                return response()->json([
                    'message' => $sign_pic->message ?? $sign_sampler->message
                ],400);
            }
            $ttd_sampler = $signatureData->ttd_sampler && $sign_sampler->status !== 'error' ? '<img src="' . $sign_sampler->base64 . '" style="height: 60px; max-width: 150px;">' : '';
            $ttd_pic = $signatureData->ttd_pic && $sign_pic->status !== 'error' ? '<img src="' . $sign_pic->base64 . '" style="height: 60px; max-width: 150px;">' : '';

            $pdf->WriteHTML('</table>
                            <table class="table" width="100%" style="border: none;margin-top: 20px">
                                <tr>
                                    <td style="border: none;width: 30%; text-align: center;height: 80px;">' . $ttd_sampler . '</td>
                                    <td style="border: none;width: 20%; text-align: center;height: 80px;"></td>
                                    <td style="border: none;width: 20%; text-align: center;height: 80px;"></td>
                                    <td style="border: none;width: 30%; text-align: center;height: 80px;">' . $ttd_pic . '</td>
                                </tr>
                                <tr>
                                    <td style="border: none;width: 30%;text-align: center;"><p><strong>' . strtoupper($signatureData->nama_sampler) . '</strong></p></td>
                                    <td style="border: none;width: 20%;text-align: center;"></td>
                                    <td style="border: none;width: 20%;text-align: center;"></td>
                                    <td style="border: none;width: 30%;text-align: center;"><p><strong>' . strtoupper($signatureData->nama_pic) . '</strong></p></td>
                                </tr>
                                <tr>
                                    <td style="border: none;width: 30%; text-align: center;"><p><strong>Sampler</strong></p></td>
                                    <td style="border: none;width: 20%; text-align: center;"></td>
                                    <td style="border: none;width: 20%; text-align: center;"></td>
                                    <td style="border: none;width: 30%; text-align: center;"><p><strong>Penanggung Jawab</strong></p></td>
                                </tr>
                            </table>');


            $pdf->WriteHTML('</body></html>');
            

            $pdf->Output(public_path() . '/dokumen/' . '/cs/' . $filename);

            return $filename;
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 500);
        }
    }

    public function convertBase64ToImage($base64Input)
    {
        $file = $this->cleanBase64($base64Input);
        // Pastikan input adalah string base64 yang valid
        if (!base64_decode($file, true)) {
            return (object) [
                'status' => 'error',
                'message' => 'Input base64 tidak valid'
            ];
        }

        // Decode base64
        $imageContent = base64_decode($file);

        // Deteksi tipe file berdasarkan header
        $fileType = self::detectFileType($imageContent);

        // Generate nama file unik
        $filename = 'SIGN_CS_' . uniqid() . '.' . $fileType;
        
        // Path penyimpanan
        $path = public_path('dokumen/cs/signatures');
        
        // Pastikan direktori tersedia
        if ( !file_exists($path)) {
            mkdir($path, 0777, true);
        }

        // Path file lengkap
        $filePath = $path . '/' . $filename;

        // Simpan file
        file_put_contents($filePath, $imageContent);

        // Kembalikan respons
        return (object) [
            'status' => 'success',
            'filename' => $filename,
            'path' => $filePath,
            'file_type' => $fileType
        ];
    }

    private function detectFileType($fileContent)
    {
        // Signature file untuk berbagai format
        $signatures = [
            'png'  => "\x89PNG\x0D\x0A\x1A\x0A",
            'jpg'  => "\xFF\xD8\xFF",
            'gif'  => "GIF87a",
            'webp' => "RIFF",
            'svg'  => '<?xml'
        ];

        foreach ($signatures as $type => $signature) {
            if (strpos($fileContent, $signature) === 0) {
                return $type;
            }
        }

        return 'bin'; // Default ke binary jika tidak dikenali
    }

    /**
     * Membersihkan base64 dari header tidak perlu
     *
     * @param string $base64Input
     * @return string
     */
    public function cleanBase64($base64Input)
    {
        // Hapus header data URI jika ada
        $base64Input = preg_replace('/^data:image\/(png|jpeg|gif|webp);base64,/', '', $base64Input);
        
        // Hapus whitespace
        $base64Input = preg_replace('/\s+/', '', $base64Input);

        return $base64Input;
    }

    public function decodeImageToBase64($filename)
    {
        // Path penyimpanan
        $path = public_path('dokumen/cs/signatures');
        
        // Path file lengkap
        $filePath = $path . '/' . $filename;

        // Periksa apakah file ada
        if (!file_exists($filePath)) {
            return (object) [
                'status' => 'error',
                'message' => 'File tidak ditemukan'
            ];
        }

        // Baca konten file
        $imageContent = file_get_contents($filePath);
        if($imageContent === false) {
            return (object) [
                'status' => 'error',
                'message' => 'Gagal membaca file'
            ];
        }

        // Konversi ke base64
        $base64Image = base64_encode($imageContent);

        // Deteksi tipe file
        $fileType = $this->detectFileType($imageContent);

        // Tambahkan data URI header sesuai tipe file
        $base64WithHeader = 'data:image/' . $fileType . ';base64,' . $base64Image;

        // Kembalikan respons
        return (object) [
            'status' => 'success',
            'base64' => $base64WithHeader,
            'file_type' => $fileType
        ];
    }

    public function checkLengthData($category2, $category3, $parameters, $no_sampel) {
        $parameters = array_reduce($parameters, function ($carry, $item) {
            $parameterName = explode(";", $item)[1];
            $carry[$parameterName] = $this->getRequiredCount($parameterName);
            return $carry;
        }, []);
        // dd($parameters);

        if($category2 == "4-Udara") {
            foreach ($parameters as $parameter => $requiredCount) {
                if (in_array($category3, ["11-Udara Ambient", "27-Udara Lingkungan Kerja", "12-Udara Angka Kuman"])) {
                    $partikulatMeter = DataLapanganPartikulatMeter::where('no_sampel', $no_sampel)->count();
                    if($partikulatMeter < $requiredCount){
                        if ($category3 == "11-Udara Ambient") {
                            if ($parameter == "C O") {
                                if (DataLapanganDirectLain::where('no_sampel', $no_sampel)->where('parameter', $parameter)->count() < $requiredCount) return 0;
                            } else if ($parameter == "HCNM (3 Jam)" || $parameter == "HC (3 Jam)") {
                                if (DetailSenyawaVolatile::where('no_sampel', $no_sampel)->where('parameter', $parameter)->count() < $requiredCount) return 0;
                            } else {
                                if (DetailLingkunganHidup::where('no_sampel', $no_sampel)->where('parameter', $parameter)->count() < $requiredCount) return 0;
                            }
                        }
        
                        if ($category3 == "27-Udara Lingkungan Kerja") {
                            if ($parameter == "C O") {
                                if (DataLapanganDirectLain::where('no_sampel', $no_sampel)->where('parameter', $parameter)->count() < $requiredCount) return 0;
                            } else {
                                if (DetailLingkunganKerja::where('no_sampel', $no_sampel)->where('parameter', $parameter)->count() < $requiredCount) return 0;
                            }
                        }
        
                        if ($category3 == "12-Udara Angka Kuman") {
                            if (DetailMicrobiologi::where('no_sampel', $no_sampel)->where('parameter', $parameter)->count() < $requiredCount) return 0;
                        }
                    }else {
                        return 0;
                    }
                }

                else if ($category3 == "23-Kebisingan") {
                    if ($parameter == "Kebisingan (8 Jam)") {
                        if (DataLapanganKebisinganPersonal::where('no_sampel', $no_sampel)->count() < $requiredCount) return 0;
                    } else {
                        if (DataLapanganKebisingan::where('no_sampel', $no_sampel)->count() < $requiredCount) return 0;
                    }
                }
        
                else if ($category3 == "24-Kebisingan (24 Jam)") {
                    if (DataLapanganKebisingan::where('no_sampel', $no_sampel)->count() < $requiredCount) return 0;
                }
        
                else if ($category3 == "28-Pencahayaan") {
                    $jumlah = DataLapanganCahaya::where('no_sampel', $no_sampel)->count();

                    if($jumlah < $requiredCount) return 0;

                }
        
                else if (in_array($category3, ["19-Getaran (Mesin)", "15-Getaran (Kejut Bangunan)", "13-Getaran", "14-Getaran (Bangunan)", "18-Getaran (Lingkungan)"])) {
                    if (DataLapanganGetaran::where('no_sampel', $no_sampel)->count() < $requiredCount) return 0;
                }
        
                else if (in_array($category3, ["17-Getaran (Lengan & Tangan)", "20-Getaran (Seluruh Tubuh)"])) {
                    if (DataLapanganGetaranPersonal::where('no_sampel', $no_sampel)->count() < $requiredCount) return 0;
                }
        
                else if ($category3 == "21-Iklim Kerja") {
                    if ($parameter == "ISBB") {
                        if (DataLapanganIklimPanas::where('no_sampel', $no_sampel)->count() < $requiredCount) return 0;
                    } elseif ($parameter == "IKD (CS)") {
                        if (DataLapanganIklimDingin::where('no_sampel', $no_sampel)->count() < $requiredCount) return 0;
                    }
                }
                
                else if ($category3 == "46-Udara Swab Test") {
                    if (DataLapanganSwab::where('no_sampel', $no_sampel)->count() < $requiredCount) return 0;
                }

                else if($category3 == "53-Ergonomi") {
                    if(DataLapanganErgonomi::where('no_sampel', $no_sampel)->count() < $requiredCount) return 0;
                }
                else if($category3 == "53-Ergonomi") {
                    if(DataLapanganErgonomi::where('no_sampel', $no_sampel)->count() < $requiredCount) return 0;
                }

                else {
                    if(in_array($parameter, ["Debu (P8J)", "PM 10 (Personil)", "PM 2.5 (Personil)", "Karbon Hitam (8 jam)"])) {
                        if(DataLapanganDebuPersonal::where('no_sampel', $no_sampel)->count() < $requiredCount) return 0;
                    }

                    else if(in_array($parameter, ["Medan Magnit Statis", "Power Density", "Medan Listrik", "Gelombang Elektro"])) {
                        if(DataLapanganMedanLM::where('no_sampel', $no_sampel)->count() < $requiredCount) return 0;
                    }

                    else if($parameter == "Sinar UV") {
                        if(DataLapanganSinarUV::where('no_sampel', $no_sampel)->count() < $requiredCount) return 0;
                    }

                    else if($parameter == "Psikologi") {
                        if(DataLapanganPsikologi::where('no_sampel', $no_sampel)->count() < $requiredCount) return 0;
                    }

                    else {
                        return 0;
                    }
                }
        
            }
        }else if($category2 == "5-Emisi") {
            foreach ($parameters as $parameter => $requiredCount) {
                if (in_array($category3, ["32-Emisi Kendaraan (Solar)", "31-Emisi Kendaraan (Bensin)", "116-Emisi Kendaraan (Gas)"])) {
                    if (DataLapanganEmisiKendaraan::where('no_sampel', $no_sampel)->count() < $requiredCount) return 0;
                }
                if ($category3 == "34-Emisi Sumber Tidak Bergerak") {
                    $emisiCerobong = DataLapanganEmisiCerobong::where('no_sampel', $no_sampel)->count();
                    if ($emisiCerobong < $requiredCount) {
                        if (DataLapanganIsokinetikHasil::where('no_sampel', $no_sampel)->count() < $requiredCount) return 0;
                    }
                }

                // return 1;
            }
        } else if($category2 == "1-Air") {
            return 1;
        } else {
            return 0;
        }

        return 1;
    }

    private function getRequiredCount($parameter)
    {
        $map = [
            'Debu (P8J)' => 2,
            'Dustfall' => 2,
            'Dustfall (S)' => 2,
            'Kebisingan (P8J)' => 2,
            'PM 10 (Personil)' => 2,
            'PM 2.5 (Personil)' => 2,

            'CO (6 Jam)' => 3,
            'CO (8 Jam)' => 3,
            'CO2 (8 Jam)' => 3,
            'H2S (3 Jam)' => 3,
            'H2S (8 Jam)' => 3,
            'HC (3 Jam)' => 3,
            'HC (6 Jam)' => 3,
            'HC (8 Jam)' => 3,
            'HCHO (8 Jam)' => 3,
            'HCNM (3 Jam)' => 3,
            'HCNM (6 Jam)' => 3,
            'HCNM (8 Jam)' => 3,
            'ISBB (8 Jam)' => 3,
            'Metil Merkaptan (8 Jam)' => 3,
            'Metil Sulfida (8 Jam)' => 3,
            'NH3 (8 Jam)' => 3,
            'NO2 (6 Jam)' => 3,
            'NO2 (8 Jam)' => 3,
            'O3 (8 Jam)' => 3,
            'Pb (6 Jam)' => 3,
            'Pb (8 Jam)' => 3,
            'PM 10 (8 Jam)' => 3,
            'PM 2.5 (8 Jam)' => 3,
            'SO2 (6 Jam)' => 3,
            'SO2 (8 Jam)' => 3,
            'Stirena (8 Jam)' => 3,
            'Toluene (8 Jam)' => 3,
            'TSP (6 Jam)' => 3,
            'TSP (8 Jam)' => 3,
            'VOC (8 Jam)' => 3,
            'Xylene (8 Jam)' => 3,
            'HCl (8 Jam)' => 3,
            'Fe (8 Jam)' => 3,
            'T.Bakteri (8 Jam)' => 3,
            'T. Jamur (8 Jam)' => 3,
            'Laju Ventilasi (8 Jam)' => 3,
            'Iklim Kerja Dingin (Cold Stress) - 8 Jam' => 3,
            'Al. Hidrokarbon (8 Jam)' => 3,
            'T. Bakteri (KUDR - 8 Jam)' => 3,
            'T. Jamur (KUDR - 8 Jam)' => 3,
            'Karbon Hitam (8 jam)' => 3,
            'N-Hexane Personil (8 Jam)' => 3,
            'Siklohexane - 8 Jam' => 3,
            'Silica Crystaline 8 Jam' => 3,

            'CH4 (24 Jam)' => 4,
            'CO (24 Jam)' => 4,
            'CO2 (24 Jam)' => 4,
            'Get. Bangunan (24J)' => 4,
            'H2S (24 Jam)' => 4,
            'NH3 (24 Jam)' => 4,
            'NO2 (24 Jam)' => 4,
            'SO2 (24 Jam)' => 4,
            'Cl2 (24 Jam)' => 4,

            'Pb (24 Jam)' => 5,
            'PM 10 (24 Jam)' => 5,
            'PM 2.5 (24 Jam)' => 5,
            'TSP (24 Jam)' => 5,

            'Kebisingan (24 Jam)' => 7,

            'Kebisingan (8 Jam)' => 8,
        ];

        return $map[$parameter] ?? 1;
    }
}


