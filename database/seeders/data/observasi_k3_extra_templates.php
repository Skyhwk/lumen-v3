<?php

/** Item akhir standar catatan tambahan */
$catatan = [
    ['t' => 'Catatan Tambahan 1', 'tipe' => 'catatan_tambahan', 'wajib' => true],
    ['t' => 'Catatan Tambahan 2', 'tipe' => 'catatan_tambahan', 'wajib' => true],
    ['t' => 'Catatan Tambahan 3', 'tipe' => 'catatan_tambahan', 'wajib' => true],
];

$programAfFn = function (string $aLabel, string $aSub, array $a, array $b, array $c, array $d, array $e, array $f) use ($catatan) {
    return [
        ['kode' => 'A', 'label' => 'POIN A – ELIMINASI SUMBER PAJANAN', 'sub_label' => $aSub ?: $aLabel, 'items' => $a],
        ['kode' => 'B', 'label' => 'POIN B – SUBSTITUSI ALAT, BAHAN, ATAU PROSES', 'sub_label' => 'Mengganti sumber pajanan dengan alternatif yang lebih rendah', 'items' => $b],
        ['kode' => 'C', 'label' => 'POIN C – REKAYASA TEKNIS', 'sub_label' => 'Rekayasa teknik pengendalian pajanan', 'items' => $c],
        ['kode' => 'D', 'label' => 'POIN D – PENGATURAN / PEMBATASAN PAJANAN', 'sub_label' => 'Pengendalian administratif', 'items' => $d],
        ['kode' => 'E', 'label' => 'POIN E – ALAT PELINDUNG DIRI (APD)', 'sub_label' => 'Perlindungan pajanan', 'items' => $e],
        [
            'kode' => 'F',
            'label' => 'POIN F – PENGENDALIAN LAINNYA',
            'sub_label' => 'Sesuai perkembangan IPTEK',
            'items' => array_merge($f, $catatan),
        ],
    ];
};

return [
    'mikrobiologi' => [
        'judul_form' => 'FORM OBSERVASI PENGENDALIAN MIKROBIOLOGI',
        'deskripsi_program' => 'Program Pengendalian Pajanan Mikrobiologi di Tempat Kerja, berdasarkan ketentuan pengendalian faktor bahaya mikrobiologi di lingkungan kerja.',
        'kategori_kode' => ['mikrobiologi'],
        'sections' => $programAfFn(
            '',
            'Menghilangkan sumber bahaya dari tempat kerja',
            [
                'Sumber kontaminasi biologis yang tidak diperlukan telah dihilangkan.',
                'Area kerja dijaga bebas dari limbah organik yang berpotensi menjadi media mikroba.',
            ],
            [
                'Penggunaan bahan yang berpotensi terkontaminasi diganti dengan bahan yang lebih aman.',
                'Metode kerja diperbaiki untuk meminimalkan kontak dengan agen biologis.',
            ],
            [
                'Ventilasi ruangan berfungsi dengan baik.',
                'Sistem pendingin udara (AC) dirawat secara berkala.',
                'Area kerja dijaga dalam kondisi bersih dan kering.',
                'Tersedia fasilitas sanitasi dan pembersihan yang memadai.',
            ],
            [
                'Terdapat SOP kebersihan dan sanitasi area kerja.',
                'Pekerja mendapatkan edukasi mengenai bahaya biologis.',
                'Tersedia prosedur penanganan kontaminasi biologis.',
            ],
            [
                'APD tersedia sesuai kebutuhan pekerjaan.',
                'APD digunakan konsisten.',
                'APD dibersihkan dan disimpan dengan baik.',
            ],
            [
                'Dilakukan pemantauan mikrobiologi lingkungan kerja secara berkala.',
                'Pemeriksaan kesehatan berkala sesuai jenis pajanan.',
                'Evaluasi efektivitas pengendalian.',
            ]
        ),
    ],

    'debu' => [
        'judul_form' => 'FORM OBSERVASI PENGENDALIAN DEBU',
        'deskripsi_program' => 'Program Pengendalian Pajanan Debu di Tempat Kerja, berdasarkan ketentuan pengendalian faktor bahaya debu di lingkungan kerja.',
        'kategori_kode' => ['debu'],
        'sections' => $programAfFn(
            '',
            'Menghilangkan sumber bahaya dari tempat kerja',
            [
                'Menghilangkan aktivitas yang menimbulkan debu berlebih yang tidak diperlukan.',
                'Mengurangi penyimpanan material terbuka yang berpotensi menghasilkan debu.',
            ],
            [
                'Menggunakan bahan dengan tingkat debu lebih rendah.',
                'Mengganti metode kerja kering dengan metode lembab/basah bila memungkinkan.',
            ],
            [
                'Pemasangan ventilasi umum yang memadai.',
                'Penggunaan exhaust fan pada area tertutup.',
                'Penerapan pembersihan menggunakan vacuum cleaner (bukan disapu).',
                'Program perawatan rutin sistem ventilasi dan pendingin ruangan.',
            ],
            [
                'Penjadwalan housekeeping rutin.',
                'Rotasi pekerja diterapkan untuk membatasi pajanan.',
                'Penyusunan SOP pembersihan area kerja.',
                'Edukasi pekerja mengenai bahaya debu dan kebersihan lingkungan kerja.',
                'Pembatasan akses pada area yang sedang dilakukan pekerjaan berdebu.',
            ],
            [
                'Penyediaan masker partikulat sesuai tingkat risiko.',
                'APD digunakan konsisten.',
                'Pelatihan penggunaan APD dilakukan.',
            ],
            [
                'Pengukuran pajanan debu berkala.',
                'Pemeriksaan kesehatan berkala.',
                'Evaluasi efektivitas pengendalian.',
            ]
        ),
    ],

    'ergonomi' => [
        'judul_form' => 'FORM OBSERVASI PENGENDALIAN ERGONOMI',
        'deskripsi_program' => 'Program Pengendalian Risiko Ergonomi di Tempat Kerja, berdasarkan ketentuan pengendalian faktor bahaya ergonomi di lingkungan kerja.',
        'kategori_kode' => ['ergonomi'],
        'sections' => $programAfFn(
            '',
            'Menghilangkan sumber bahaya dari tempat kerja',
            [
                'Aktivitas kerja yang berisiko ergonomi tinggi telah dihilangkan.',
                'Pekerjaan manual yang tidak diperlukan telah dikurangi.',
            ],
            [
                'Metode kerja manual diganti dengan metode yang lebih ergonomis.',
                'Penggunaan alat bantu kerja menggantikan pekerjaan manual berat.',
            ],
            [
                'Meja dan kursi kerja disesuaikan dengan tinggi pekerja.',
                'Dilakukan pengaturan waktu kerja dan istirahat.',
                'Dilakukan rotasi kerja untuk mengurangi kelelahan otot.',
                'Pekerja mendapatkan pelatihan ergonomi.',
            ],
            [
                'Tersedia SOP kerja ergonomis.',
                'Dilakukan pengaturan waktu kerja dan istirahat.',
                'Dilakukan rotasi kerja untuk mengurangi kelelahan otot.',
                'Pekerja mendapatkan pelatihan ergonomi.',
            ],
            [
                ['t' => 'Tersedia APD yang mendukung pekerjaan', 'tipe' => 'ya_tidak_nrr'],
                'APD digunakan konsisten.',
            ],
            [
                'Dilakukan evaluasi ergonomi secara berkala.',
                'Penggunaan alat bantu ergonomi sesuai perkembangan teknologi.',
                'Program pencegahan gangguan muskuloskeletal diterapkan.',
            ]
        ),
    ],

    'getaran' => [
        'judul_form' => 'FORM OBSERVASI PENGENDALIAN GETARAN',
        'deskripsi_program' => 'Program Pengendalian Getaran di Tempat Kerja (Vibration Control Program), berdasarkan ketentuan pengendalian getaran di tempat kerja.',
        'kategori_kode' => ['getaran'],
        'sections' => $programAfFn(
            'POIN A – ELIMINASI SUMBER PAJANAN GETARAN',
            'Menghilangkan sumber bahaya dari tempat kerja',
            [
                'Proses kerja yang menimbulkan getaran tinggi dieliminasi.',
                'Penggunaan alat manual bergetar tinggi dihentikan.',
                'Mesin lama dengan tingkat getaran tinggi diganti/dihapus dari operasional.',
            ],
            [
                'Mengganti mesin dengan tipe low-vibration.',
                'Menggunakan peralatan dengan sistem peredam internal.',
                'Mengganti metode kerja manual menjadi otomatis.',
            ],
            [
                'Pemasangan vibration damper / shock absorber.',
                'Perawatan dan balancing mesin dilakukan rutin.',
                'Pemisahan ruang mesin dari area kerja utama.',
                'Sistem monitoring getaran terpasang.',
            ],
            [
                'Pembatasan durasi kerja pada alat bergetar.',
                'Rotasi pekerja diterapkan untuk membatasi pajanan.',
                'Penerapan SOP penggunaan alat bergetar.',
                'Pelatihan risiko getaran dan pencegahannya.',
                'Rambu peringatan pajanan getaran.',
            ],
            [
                'APD anti-getar/peredam getar tersedia.',
                'APD digunakan konsisten.',
                'Pelatihan penggunaan APD dilakukan.',
            ],
            [
                'Pengukuran getaran berkala.',
                'Pemeriksaan kesehatan berkala.',
                'Evaluasi efektivitas pengendalian.',
            ]
        ),
    ],

    'sinar_uv' => [
        'judul_form' => 'FORM OBSERVASI PENGENDALIAN RADIASI ULTRA UNGU (UV)',
        'deskripsi_program' => 'Program Pengendalian Radiasi Ultra Ungu (UV) di Tempat Kerja, berdasarkan ketentuan pengendalian radiasi Ultra Ungu di tempat kerja.',
        'kategori_kode' => ['sinar_uv', 'radiasi_uv'],
        'sections' => [
            [
                'kode' => 'A',
                'label' => 'POIN A – ELIMINASI SUMBER PAJANAN ULTRA UNGU (UV)',
                'sub_label' => 'Menghilangkan sumber bahaya dari tempat kerja',
                'items' => [
                    'Menghilangkan proses kerja yang tidak memerlukan penggunaan sumber UV secara langsung.',
                    'Mengganti metode kerja berbasis UV dengan metode alternatif non-radiasi apabila memungkinkan.',
                ],
            ],
            [
                'kode' => 'B',
                'label' => 'POIN B – SUBSTITUSI ALAT, BAHAN, ATAU PROSES',
                'sub_label' => 'Mengganti sumber pajanan dengan alternatif yang lebih rendah',
                'items' => [
                    'Menggunakan peralatan dengan emisi UV lebih rendah.',
                    'Mengganti lampu UV konvensional dengan sistem UV tertutup (enclosed system).',
                ],
            ],
            [
                'kode' => 'C',
                'label' => 'POIN C – REKAYASA TEKNIS',
                'sub_label' => 'Rekayasa teknik pengendalian pajanan',
                'items' => [
                    'Pemasangan pelindung (shielding) dan enclosure pada sumber UV.',
                    'Perawatan dan balancing mesin dilakukan rutin.',
                    'Pengaturan tata letak area kerja untuk membatasi akses langsung ke sumber UV.',
                ],
            ],
            [
                'kode' => 'D',
                'label' => 'POIN D – PENGATURAN / PEMBATASAN PAJANAN',
                'sub_label' => 'Pengendalian administratif',
                'items' => [
                    'Pembatasan waktu pajanan pekerja terhadap sumber UV.',
                    'Rotasi pekerja diterapkan untuk membatasi pajanan.',
                    'Penetapan prosedur kerja aman (SOP) penggunaan peralatan UV.',
                    'Pelatihan dan sosialisasi bahaya radiasi UV serta gejala efek kesehatan.',
                    'Pemasangan rambu peringatan bahaya radiasi UV.',
                ],
            ],
            [
                'kode' => 'E',
                'label' => 'POIN E – ALAT PELINDUNG DIRI (APD)',
                'sub_label' => 'Perlindungan pajanan',
                'items' => [
                    [
                        't' => 'Kacamata pelindung khusus UV',
                        'tipe' => 'pilih_opsi',
                        'config' => ['options' => ['UV safety goggles', 'Face shield'], 'allow_manual' => false],
                    ],
                    'Sarung tangan dan pakaian lengan panjang anti-UV.',
                    'APD digunakan konsisten.',
                    'Pelatihan penggunaan APD dilakukan.',
                ],
            ],
            [
                'kode' => 'F',
                'label' => 'POIN F – PENGENDALIAN LAINNYA',
                'sub_label' => 'Sesuai perkembangan IPTEK',
                'items' => array_merge([
                    'Pengukuran pajanan UV berkala.',
                    'Pemeriksaan kesehatan berkala.',
                    'Evaluasi efektivitas pengendalian.',
                ], $catatan),
            ],
        ],
    ],

    'rf_gelombang_mikro' => [
        'judul_form' => 'FORM OBSERVASI FREKUENSI RADIO, GELOMBANG MIKRO, MEDAN MAGNET',
        'deskripsi_program' => 'Program Pengendalian Radiasi Non-Ionisasi (Frekuensi Radio, Gelombang Mikro, dan Medan Magnet) di Tempat Kerja.',
        'kategori_kode' => ['frekuensi_radio'],
        'sections' => $programAfFn(
            '',
            'Menghilangkan sumber bahaya dari tempat kerja',
            [
                'Menghilangkan penggunaan sumber pajanan yang tidak diperlukan dalam proses kerja.',
                'Menonaktifkan pemancar saat tidak digunakan atau saat kegiatan pemeliharaan dilakukan.',
            ],
            [
                'Menggunakan perangkat dengan daya pancar lebih rendah.',
                'Mengganti sistem lama dengan teknologi yang memiliki tingkat kebocoran radiasi lebih kecil.',
                'Menggunakan sistem antena terarah (directional antenna) untuk meminimalkan paparan ke area kerja.',
            ],
            [
                'Pemasangan shielding (pelindung logam/mesh konduktif) pada sumber radiasi.',
                'Penempatan antena dan pemancar pada ketinggian atau lokasi yang membatasi akses pekerja.',
                'Pemisahan ruang pajanan dari area kerja utama.',
                'Sistem monitoring pajanan terpasang.',
            ],
            [
                'Penetapan zona aman dan zona terbatas berdasarkan hasil pengukuran intensitas medan elektromagnetik.',
                'Pembatasan waktu pajanan pekerja (time limitation).',
                'Sistem izin kerja (work permit) untuk pekerjaan di area paparan tinggi.',
                'Pelatihan pekerja terkait bahaya radiasi non-ionisasi dan prosedur darurat.',
                'Pemasangan tanda peringatan bahaya radiasi.',
            ],
            [
                'Penggunaan pakaian pelindung khusus (RF protective suit) bila diperlukan pada area intensitas tinggi.',
                'Sarung tangan dan perlengkapan pelindung tambahan sesuai hasil analisis risiko.',
                'Pelatihan penggunaan APD dilakukan.',
            ],
            [
                'Pengukuran intensitas medan listrik dan medan magnet secara berkala.',
                'Pemeriksaan kesehatan berkala.',
                'Evaluasi efektivitas pengendalian.',
            ]
        ),
    ],

    'pencahayaan' => [
        'judul_form' => 'FORM OBSERVASI PENGENDALIAN PENCAHAYAAN',
        'deskripsi_program' => 'Pengendalian intensitas pencahayaan untuk memastikan tingkat penerangan sesuai standar serta mencegah kelelahan visual dan risiko kecelakaan kerja.',
        'kategori_kode' => ['pencahayaan'],
        'sections' => $programAfFn(
            '',
            'Menghilangkan sumber bahaya dari tempat kerja',
            [
                'Menghilangkan tata letak kerja yang menyebabkan area gelap atau bayangan berlebih pada titik kerja.',
                'Menghilangkan sumber silau langsung (direct glare) dari posisi operator.',
            ],
            [
                'Mengganti lampu yang tidak sesuai spesifikasi dengan lampu berintensitas dan temperatur warna yang sesuai jenis pekerja.',
                'Mengganti lampu yang mengalami penurunan lumen atau flicker dengan lampu baru yang lebih stabil dan efisien.',
            ],
            [
                'Penambahan titik lampu pada area dengan hasil pengukuran di bawah standar.',
                'Penataan ulang distribusi lampu untuk pemerataan cahaya.',
                [
                    't' => 'Optimalisasi pencahayaan alami dengan pengaturan bukaan dan tirai',
                    'tipe' => 'pilih_opsi',
                    'config' => ['options' => ['Terdapat Jendela', 'Terdapat atap translusan'], 'allow_manual' => true],
                ],
                'Program perawatan dan pembersihan rutin lampu.',
            ],
            [
                'Penetapan standar lux sesuai jenis pekerjaan (kantor, produksi, inspeksi, dll.).',
                'Rambu informasi hasil pengukuran pencayaan ditempat kerja.',
            ],
            [],
            [
                'Pengukuran pencahayaan berkala.',
                'Pemeriksaan kesehatan berkala.',
                'Evaluasi efektivitas pengendalian.',
            ]
        ),
    ],

    'psikologi' => [
        'judul_form' => 'FORM OBSERVASI PENGENDALIAN PSIKOLOGI',
        'deskripsi_program' => 'Program Pengendalian Risiko Psikologi Kerja di Tempat Kerja.',
        'kategori_kode' => ['psikologi'],
        'sections' => [
            [
                'kode' => 'A',
                'label' => 'POIN A – ELIMINASI SUMBER PAJANAN',
                'sub_label' => 'Menghilangkan sumber bahaya dari tempat kerja',
                'items' => [
                    'Menghilangkan sumber tekanan kerja yang tidak diperlukan.',
                    'Mengubah struktur organisasi, fungsi, atau merancang kembali pekerjaan yang ada.',
                ],
            ],
            [
                'kode' => 'B',
                'label' => 'POIN B – SUBSTITUSI ALAT, BAHAN, ATAU PROSES',
                'sub_label' => 'Mengganti sumber pajanan dengan alternatif yang lebih rendah',
                'items' => [
                    'Mengganti metode kerja yang menimbulkan tekanan tinggi dengan sistem kerja yang lebih seimbang.',
                ],
            ],
            [
                'kode' => 'C',
                'label' => 'POIN C – REKAYASA TEKNIS',
                'sub_label' => 'Rekayasa teknik pengendalian pajanan',
                'items' => [
                    'Melakukan pemilihan dan penempatan tenaga kerja sesuai kompetensi.',
                    'Penyediaan pelatihan yang memadai agar pekerja memahami tugas dengan baik.',
                    'Penyediaan program pelatihan dan pengembangan yang relevan.',
                ],
            ],
            [
                'kode' => 'D',
                'label' => 'POIN D – PENGATURAN / PEMBATASAN PAJANAN',
                'sub_label' => 'Pengendalian administratif',
                'items' => [
                    'Melakukan briefing kembali terkait tugas dan tanggung jawab pekerja secara jelas.',
                    'Mengadakan komunikasi organisasional secara memadai.',
                    'Memberikan kebebasan bagi pekerja untuk memberikan masukan dalam pengambilan keputusan.',
                    'Mengadakan pertemuan rutin untuk meninjau progres pekerjaan.',
                    'Memberikan umpan balik berkala mengenai kinerja pekerja.',
                ],
            ],
            [
                'kode' => 'F',
                'label' => 'POIN F – PENGENDALIAN LAINNYA',
                'sub_label' => 'Program Kesejahteraan dan Dukungan Pekerja',
                'items' => array_merge([
                    'Mengadakan program kebugaran bagi tenaga kerja.',
                    'Mengadakan program konseling bagi pekerja.',
                    'Mengadakan dukungan untuk kesehatan mental pekerja.',
                    'Menggunakan sistem pemberian imbalan atau penghargaan untuk meningkatkan motivasi kerja.',
                ], $catatan),
            ],
        ],
    ],

    'higiene_bangunan' => [
        'judul_form' => 'FORM OBSERVASI HIGIENE DAN SANITASI BANGUNAN TEMPAT KERJA',
        'deskripsi_program' => 'Program Pengendalian Higiene dan Sanitasi Bangunan Tempat Kerja.',
        'kategori_kode' => ['higiene_sanitasi'],
        'sections' => [
            [
                'kode' => 'A',
                'label' => 'POIN A – Halaman',
                'sub_label' => null,
                'items' => [
                    'Kondisi kebersihan halaman (bersih / tidak bersih).',
                    'Permukaan jalan (rata / tidak rata).',
                    'Saluran air pembuangan tersedia.',
                    'Penutup saluran air pembuangan ada.',
                    'Kondisi saluran air (mengalir / tergenang).',
                    'Pengelolaan halaman telah dilakukan (pembersihan, pengecatan, dll.).',
                ],
            ],
            [
                'kode' => 'B',
                'label' => 'POIN B – Gedung',
                'sub_label' => null,
                'items' => [
                    'Dinding dan langit-langit bersih.',
                    'Atap tidak bocor.',
                    'Struktur lantai keras.',
                    'Lantai kedap air.',
                    'Lantai tahan bahan kimia.',
                ],
            ],
            [
                'kode' => 'C',
                'label' => 'POIN C – Bangunan Bawah Tanah',
                'sub_label' => 'Apabila ada',
                'items' => [
                    'Struktur kuat.',
                    'Sistem ventilasi udara.',
                    'Sumber pencahayaan.',
                    'Saluran pembuangan air mengalir dengan baik.',
                ],
            ],
        ],
    ],

    'fasilitas_toilet' => [
        'judul_form' => 'FORM OBSERVASI FASILITAS KEBERSIHAN / TOILET',
        'deskripsi_program' => 'Program Pengendalian Higiene dan Sanitasi Fasilitas Kebersihan Toilet.',
        'kategori_kode' => ['fasilitas_toilet'],
        'sections' => toiletSections(),
    ],
];

function toiletSections(): array
{
    $fasilitasLaki = [
        'Jamban / WC.',
        'Alat pembilas.',
        'Tempat sampah kedap cairan.',
        'Tempat sampah berpenutup.',
        'Tempat sampah berlabel jelas.',
        'Tempat cuci tangan.',
        'Sabun.',
        'Fasilitas kebersihan.',
        'Penanda jenis toilet.',
        'Peturasan / urinoir tersedia.',
    ];
    $kondisiUmum = [
        'Bersih dan tidak menimbulkan bau.',
        'Tidak ada lalat, nyamuk, atau serangga lainnya.',
        'Saluran air mengalir dengan baik.',
        'Dilengkapi dengan pintu.',
        'Dapat digunakan selama jam kerja.',
        'Persediaan air bersih.',
        'Sirkulasi udara.',
        'Pembersihan periodik.',
    ];
    $ukur = [
        ['t' => 'Jumlah toilet', 'tipe' => 'input_teks'],
        ['t' => 'Panjang toilet (cm)', 'tipe' => 'input_teks'],
        ['t' => 'Lebar toilet (cm)', 'tipe' => 'input_teks'],
        ['t' => 'Tinggi toilet (cm)', 'tipe' => 'input_teks'],
        ['t' => 'Lebar pintu toilet (cm)', 'tipe' => 'input_teks'],
        ['t' => 'Mutu penerangan toilet (lux)', 'tipe' => 'input_teks'],
    ];

    $build = function (string $kode, string $label) use ($ukur, $fasilitasLaki, $kondisiUmum) {
        $items = $ukur;
        foreach ($fasilitasLaki as $f) {
            $items[] = $f;
        }
        foreach ($kondisiUmum as $k) {
            $items[] = $k;
        }
        return ['kode' => $kode, 'label' => $label, 'sub_label' => '(Ada / Tidak Ada)', 'items' => $items];
    };

    $perempuanExtra = ['Sanitary pad.', 'Tempat sampah pembalut.'];
    $cacatExtra = [
        'Luas ruang cukup untuk manuver kursi roda.',
        'Akses masuk dan keluar mudah dilalui.',
        'Pintu toilet plat tendang bagian bawah.',
        'Pegangan rambat tersedia.',
    ];

    $perempuan = $build('D', 'TOILET PEREMPUAN');
    foreach ($perempuanExtra as $e) {
        $perempuan['items'][] = $e;
    }

    $cacat = $build('E', 'TOILET PENYANDANG CACAT');
    foreach (array_merge($perempuanExtra, $cacatExtra) as $e) {
        $cacat['items'][] = $e;
    }

    return [
        $build('C', 'TOILET LAKI-LAKI'),
        $perempuan,
        $cacat,
    ];
}
