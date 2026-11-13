<?php

namespace Database\Seeders;

use App\Models\ObservasiK3Hirarki;
use App\Models\ObservasiK3Item;
use App\Models\ObservasiK3Kategori;
use App\Models\ObservasiK3Template;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ObservasiK3MasterSeeder extends Seeder
{
    private string $actor = 'system';

    public function run(): void
    {
        $now = Carbon::now();

        DB::transaction(function () use ($now) {
            $this->seedKategoriKosong($now);
            $this->seedTemplateKebisingan($now);
            $this->seedTemplateIklimKerja($now);
            $this->seedExtraTemplates($now);
        });
    }

    private function seedKategoriKosong(Carbon $now): void
    {
        $rows = [
            ['kode' => 'loker_ganti', 'nama' => 'Loker dan Ruang Ganti Pakaian Bagi Tenaga Kerja', 'urutan' => 12],
            ['kode' => 'tempat_sampah', 'nama' => 'Tempat Sampah', 'urutan' => 14],
            ['kode' => 'kualitas_udara', 'nama' => 'Kualitas Udara dalam Ruangan dan Ventilasi', 'urutan' => 16],
            ['kode' => 'ruang_udara', 'nama' => 'Ruang Udara / Cubic Space', 'urutan' => 18],
            ['kode' => 'gas_uap', 'nama' => 'Gas / Uap Berbahaya', 'urutan' => 19],
            ['kode' => 'housekeeping', 'nama' => 'Ketatarumahtanggaan (Housekeeping) peralatan dan Bahan', 'urutan' => 20],
        ];

        foreach ($rows as $row) {
            ObservasiK3Kategori::updateOrCreate(
                ['kode' => $row['kode']],
                [
                    'observasi_k3_template_id' => null,
                    'nama' => $row['nama'],
                    'urutan' => $row['urutan'],
                    'is_active' => true,
                    'created_by' => $this->actor,
                    'created_at' => $now,
                ]
            );
        }
    }

    private function seedTemplateKebisingan(Carbon $now): void
    {
        $template = ObservasiK3Template::updateOrCreate(
            ['kode' => 'kebisingan'],
            [
                'judul_form' => 'FORM OBSERVASI PENGENDALIAN KEBISINGAN',
                'deskripsi_program' => 'Program Pencegahan Penurunan Pendengaran (Hearing Conservation Program), berdasarkan ketentuan pengendalian kebisingan di tempat kerja.',
                'is_active' => true,
                'created_by' => $this->actor,
                'created_at' => $now,
            ]
        );

        ObservasiK3Kategori::updateOrCreate(
            ['kode' => 'kebisingan'],
            [
                'observasi_k3_template_id' => $template->id,
                'nama' => 'Kebisingan',
                'urutan' => 1,
                'is_active' => true,
                'created_by' => $this->actor,
                'created_at' => $now,
            ]
        );

        $sections = [
            [
                'kode' => 'A',
                'label' => 'POIN A – ELIMINASI SUMBER PAJANAN',
                'sub_label' => 'Menghilangkan sumber kebisingan dari tempat kerja',
                'items' => [
                    'Pekerja tidak perlu berada dekat sumber bising secara terus-menerus.',
                    'Proses kerja dapat dihentikan saat tidak diperlukan untuk mengurangi paparan kebisingan.',
                    'Penataan area kerja dilakukan agar pekerja tidak berada langsung di titik sumber kebisingan.',
                ],
            ],
            [
                'kode' => 'B',
                'label' => 'POIN B – SUBSTITUSI ALAT, BAHAN, ATAU PROSES',
                'sub_label' => 'Mengganti sumber kebisingan dengan alternatif yang lebih rendah',
                'items' => [
                    'Menggunakan mesin tipe low-noise.',
                    'Proses kerja disesuaikan agar lebih senyap.',
                    'Material/komponen yang digunakan dipilih dengan tingkat kebisingan lebih rendah.',
                ],
            ],
            [
                'kode' => 'C',
                'label' => 'POIN C – PEMBATAS / PEREDAM / PENUTUPAN SUMBER BISING',
                'sub_label' => 'Rekayasa teknik pengendalian kebisingan',
                'items' => [
                    'Tersedia enclosure/penutup mesin.',
                    'Peredam suara telah dipasang.',
                    'Pembatas area bising tersedia.',
                    'Kondisi peredam dalam keadaan baik dan berfungsi optimal.',
                ],
            ],
            [
                'kode' => 'D',
                'label' => 'POIN D – PENGATURAN / PEMBATASAN PAJANAN KEBISINGAN',
                'sub_label' => 'Pengendalian administratif',
                'items' => [
                    'Diterapkan pengaturan jam kerja di area bising.',
                    'Rotasi pekerja diterapkan untuk membatasi pajanan.',
                    'Waktu istirahat dinilai cukup.',
                    'Rambu batas kebisingan tersedia di area kerja.',
                ],
            ],
            [
                'kode' => 'E',
                'label' => 'POIN E – ALAT PELINDUNG DIRI (APD)',
                'sub_label' => 'Perlindungan pendengaran',
                'items' => [
                    ['t' => 'Earplug/earmuff tersedia sesuai kebutuhan.', 'tipe' => 'ya_tidak_nrr'],
                    'APD digunakan secara konsisten oleh pekerja.',
                    'Dilakukan pelatihan penggunaan APD.',
                ],
            ],
            [
                'kode' => 'F',
                'label' => 'POIN F – PENGENDALIAN LAINNYA',
                'sub_label' => 'Sesuai perkembangan IPTEK',
                'items' => [
                    'Monitoring kebisingan dilakukan secara berkala.',
                    'Pemeriksaan audiometri pekerja dilakukan.',
                    'Sistem peringatan kebisingan tersedia.',
                    'Dilakukan noise mapping sebagai evaluasi teknis.',
                    ['t' => 'Catatan Tambahan 1', 'tipe' => 'catatan_tambahan', 'wajib' => true],
                    ['t' => 'Catatan Tambahan 2', 'tipe' => 'catatan_tambahan', 'wajib' => true],
                    ['t' => 'Catatan Tambahan 3', 'tipe' => 'catatan_tambahan', 'wajib' => true],
                ],
            ],
        ];

        $this->syncSections($template->id, $sections, $now);
    }

    private function seedTemplateIklimKerja(Carbon $now): void
    {
        $template = ObservasiK3Template::updateOrCreate(
            ['kode' => 'iklim_kerja'],
            [
                'judul_form' => 'FORM OBSERVASI PENGENDALIAN IKLIM KERJA',
                'deskripsi_program' => 'Program Pengendalian Iklim Kerja (Heat & Cold Stress Control Program), berdasarkan ketentuan pengendalian iklim kerja di tempat kerja.',
                'is_active' => true,
                'created_by' => $this->actor,
                'created_at' => $now,
            ]
        );

        foreach ([
            ['kode' => 'iklim_kerja_panas', 'nama' => 'Iklim Kerja Panas', 'urutan' => 3],
            ['kode' => 'iklim_kerja_dingin', 'nama' => 'Iklim Kerja Dingin', 'urutan' => 5],
        ] as $kat) {
            ObservasiK3Kategori::updateOrCreate(
                ['kode' => $kat['kode']],
                [
                    'observasi_k3_template_id' => $template->id,
                    'nama' => $kat['nama'],
                    'urutan' => $kat['urutan'],
                    'is_active' => true,
                    'created_by' => $this->actor,
                    'created_at' => $now,
                ]
            );
        }

        $sections = [
            [
                'kode' => 'A',
                'label' => 'POIN A – ELIMINASI SUMBER PAJANAN',
                'sub_label' => 'Menghilangkan sumber bahaya dari tempat kerja',
                'items' => [
                    'Diterapkan pengaturan jam kerja di pajanan.',
                    'Rotasi pekerja diterapkan untuk membatasi pajanan.',
                    'Waktu istirahat dinilai cukup.',
                    'Air minum / ruang istirahat tersedia.',
                    'Rambu peringatan suhu ekstrem tersedia.',
                ],
            ],
            [
                'kode' => 'B',
                'label' => 'POIN B – SUBSTITUSI ALAT, BAHAN, ATAU PROSES',
                'sub_label' => 'Mengganti sumber pajanan dengan alternatif yang lebih rendah',
                'items' => [
                    'Mengganti peralatan lama dengan mesin yang dapat menurunkan pajanan.',
                    'Menggunakan material insulasi pada sumber pajanan.',
                    'Modifikasi proses untuk mengurangi pajanan.',
                ],
            ],
            [
                'kode' => 'C',
                'label' => 'POIN C – ISOLASI ATAU PEMBATASAN PAJANAN',
                'sub_label' => 'Rekayasa teknik pengendalian iklim kerja',
                'items' => [
                    'Pelindung/penyekat panas/dingin tersedia.',
                    'Pembatas jarak pekerja dari sumber.',
                    'Penyediaan area khusus suhu ekstrem yang terpisah.',
                    'Sistem ventilasi/pendingin berfungsi baik.',
                    [
                        't' => 'Pilih Jenis AC',
                        'tipe' => 'pilih_opsi',
                        'config' => [
                            'options' => ['Split', 'Central', 'Standing Floor'],
                            'allow_manual' => true,
                        ],
                    ],
                ],
            ],
            [
                'kode' => 'D',
                'label' => 'POIN D – PENGATURAN / PEMBATASAN PAJANAN',
                'sub_label' => 'Pengendalian administratif',
                'items' => [
                    'Diterapkan pengaturan jam kerja di pajanan.',
                    'Rotasi pekerja diterapkan untuk membatasi pajanan.',
                    'Waktu istirahat dinilai cukup.',
                    'Air minum / ruang istirahat tersedia.',
                    'Rambu peringatan suhu ekstrem tersedia.',
                ],
            ],
            [
                'kode' => 'E',
                'label' => 'POIN E – ALAT PELINDUNG DIRI (APD)',
                'sub_label' => 'Perlindungan pajanan',
                'items' => [
                    'APD suhu ekstrem tersedia.',
                    'APD digunakan konsisten.',
                    'Pelatihan penggunaan APD dilakukan.',
                ],
            ],
            [
                'kode' => 'F',
                'label' => 'POIN F – PENGENDALIAN LAINNYA',
                'sub_label' => 'Sesuai perkembangan IPTEK',
                'items' => [
                    'Pengukuran iklim kerja berkala.',
                    'Pemeriksaan kesehatan berkala.',
                    'Evaluasi efektivitas pengendalian.',
                    ['t' => 'Catatan Tambahan 1', 'tipe' => 'catatan_tambahan', 'wajib' => true],
                    ['t' => 'Catatan Tambahan 2', 'tipe' => 'catatan_tambahan', 'wajib' => true],
                    ['t' => 'Catatan Tambahan 3', 'tipe' => 'catatan_tambahan', 'wajib' => true],
                ],
            ],
        ];

        $this->syncSections($template->id, $sections, $now);
    }

    private function seedExtraTemplates(Carbon $now): void
    {
        /** @var array<string, array> $definitions */
        $definitions = require __DIR__ . '/data/observasi_k3_extra_templates.php';

        $urutanMap = [
            'mikrobiologi' => 2,
            'ergonomi' => 4,
            'psikologi' => 6,
            'getaran' => 7,
            'higiene_sanitasi' => 8,
            'sinar_uv' => 9,
            'fasilitas_toilet' => 10,
            'frekuensi_radio' => 11,
            'radiasi_uv' => 13,
            'pencahayaan' => 15,
            'debu' => 17,
        ];

        foreach ($definitions as $templateKode => $def) {
            $template = ObservasiK3Template::updateOrCreate(
                ['kode' => $templateKode],
                [
                    'judul_form' => $def['judul_form'],
                    'deskripsi_program' => $def['deskripsi_program'] ?? null,
                    'is_active' => true,
                    'created_by' => $this->actor,
                    'created_at' => $now,
                ]
            );

            foreach ($def['kategori_kode'] as $kategoriKode) {
                $nama = ObservasiK3Kategori::where('kode', $kategoriKode)->value('nama');
                if (!$nama) {
                    $nama = str_replace('_', ' ', ucwords($kategoriKode, '_'));
                }

                ObservasiK3Kategori::updateOrCreate(
                    ['kode' => $kategoriKode],
                    [
                        'observasi_k3_template_id' => $template->id,
                        'nama' => $nama,
                        'urutan' => $urutanMap[$kategoriKode] ?? 99,
                        'is_active' => true,
                        'created_by' => $this->actor,
                        'created_at' => $now,
                    ]
                );
            }

            $this->syncSections($template->id, $def['sections'], $now);
        }
    }

    private function syncSections(int $templateId, array $sections, Carbon $now): void
    {
        ObservasiK3Hirarki::where('observasi_k3_template_id', $templateId)->delete();

        foreach ($sections as $index => $section) {
            if (empty($section['items'])) {
                continue;
            }
            $hirarki = ObservasiK3Hirarki::create([
                'observasi_k3_template_id' => $templateId,
                'kode' => $section['kode'],
                'label' => $section['label'],
                'sub_label' => $section['sub_label'] ?? null,
                'urutan' => $index + 1,
                'is_active' => true,
                'created_by' => $this->actor,
                'created_at' => $now,
            ]);

            foreach ($section['items'] as $itemIndex => $item) {
                $pertanyaan = is_array($item) ? $item['t'] : $item;
                $tipe = is_array($item) ? ($item['tipe'] ?? 'ya_tidak') : 'ya_tidak';
                $wajib = is_array($item) ? (bool) ($item['wajib'] ?? ($tipe === 'catatan_tambahan')) : false;
                $config = is_array($item) ? ($item['config'] ?? null) : null;

                ObservasiK3Item::create([
                    'observasi_k3_hirarki_id' => $hirarki->id,
                    'urutan' => $itemIndex + 1,
                    'pertanyaan' => $pertanyaan,
                    'tipe_input' => $tipe,
                    'config' => $config,
                    'wajib_keterangan' => $wajib,
                    'is_active' => true,
                    'created_by' => $this->actor,
                    'created_at' => $now,
                ]);
            }
        }
    }
}
