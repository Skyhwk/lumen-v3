<?php

return [
    'header' => [
        'quotation' => 'Quotation',
        'contract' => 'Contract',
        'office' => 'Alamat Kantor',
        'sampling' => 'Alamat Sampling',
    ],
    'footer' => [
        'center_content' => 'Hal :page dari :total_pages',
        'right_content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem'
    ],
    'status_sampling' => [
        'S24' => 'Sampling 24 Jam',
        'SD' => 'Sample Diantar',
        'S' => 'Sampling',
        'RS' => 'Re-Sample',
        'SP' => 'Sample Pickup',
    ],
    'table' => [
        'header' => [
            'no' => 'No',
            'description' => 'Keterangan Pengujian',
            'quantity' => 'Qty',
            'unit_price' => 'Harga Satuan',
            'total_price' => 'Total Harga',
        ],
        'item' => [
            'volume' => 'Volume',
            'total_parameter' => 'Total Parameter',
            'transport' => 'Transportasi - Wilayah Sampling',
            'manpower' => 'Perdiem ',
            'manpower24' => 'Termasuk Perdiem (24 Jam)',
            'expenses' => [
                'other' => 'Biaya Lain - Lain',
                'preparation' => 'Biaya Preparasi',
                'aftex_tax' => 'Biaya Setelah Pajak',
                'non_taxable' => 'Biaya Di Luar Pajak',
                'cost' => 'Biaya',
            ]
        ]
    ],
    'total' => [
        'sub' => 'Sub Total',
        'after_tax' => 'Total Setelah Pajak',
        'price' => 'Total Harga',
        'after_discount' => 'Total Setelah Diskon',
        'total' => 'Total',
        'analysis' => 'Total Analisa',
        'transport' => 'Total Transport',
        'manpower' => 'Total Perdiem ',
        'analysis_price' => 'Total Harga Pengujian',
        'price_after_discount' => 'Total Harga Setelah Discount',
        'grand' => 'Total Biaya Akhir',
    ],
    'terms_conditions' => [
        'payment' => [
            'title' => 'Syarat dan Ketentuan Pembayaran',
            'cash_discount' => '- Cash Discount berlaku apabila pelunasan keseluruhan sebelum sampling.',
            '1' => "Pembayaran :days Hari setelah Laporan Hasil Pengujian dan Invoice diterima lengkap oleh pihak pelanggan.",
            '2' => "Pembayaran :percent% lunas sebelum sampling dilakukan.",
            '3' => 'Masa berlaku penawaran :days hari.',
            '4' => "Pembayaran Lunas saat sampling dilakukan oleh pihak pelanggan.",
            '5' => "Pembayaran :amount Down Payment (DP), Pelunasan saat :text",
            '6' => "Pembayaran I sebesar :amount, Pelunasan saat :text",
            '7' => "Pembayaran dilakukan dalam :count tahap, Tahap I sebesar :amount1, Tahap II sebesar :amount2, Tahap III sebesar :amount3 dari total order.",
            '8' => "Pembayaran :percent% DP, Pelunasan saat draft Laporan Hasil Pengujian diterima pelanggan.",
        ],
        'additional' => [
            'title' => 'Keterangan Lain / Tambahan',
        ],
        'general' => [
            'title' => 'Syarat dan Ketentuan Umum',
            'accreditation' => '- Parameter dengan simbol <sup style="font-size: 14px;"><u>x</u></sup> belum terakreditasi oleh Komite Akreditasi Nasional ( KAN ).',
            '1' => "Untuk kategori Udara, <b>harga sudah termasuk</b> parameter <b>Suhu - Kecepatan Angin - Arah Angin - Kelembaban - Cuaca.</b>",
            '2' => "Sumber listrik disediakan oleh pihak pelanggan.",
            '3' => "Harga di atas untuk jumlah titik sampling yang tertera dan dapat berubah disesuaikan dengan kondisi lapangan dan permintaan pelanggan.",
            '4' => "Pembatalan atau penjadwalan ulang oleh pihak pelanggan akan dikenakan biaya transportasi dan/atau perdiem.",
            '5' => "Pekerjaan akan dilaksanakan setelah pihak kami menerima konfirmasi berupa dokumen PO / SPK dari pihak pelanggan.",
            '6' => "Bagi perusahaan yang tidak menerbitkan PO / SPK, dapat menandatangani penawaran harga sebagai bentuk persetujuan pelaksanaan pekerjaan.",
            '7' => "Laporan Hasil Pengujian akan dikeluarkan dalam jangka waktu 10 hari kerja, terhitung sejak tanggal sampel diterima di laboratorium (Tidak termasuk parameter khusus).",
            '8' => "Optimal perhari 1 (satu) tim sampling (2 orang) bisa mengerjakan 6 titik udara (Ambient / Lingkungan Kerja).",
            '9' => "Jangka waktu pembuatan dokumen dikerjakan selama 2 - 3 bulan, dengan kewajiban pelanggan melengkapi dokumen sebelum sampling dilakukan.",
            '10' => "Biaya sudah termasuk :costs.",
        ],
    ],
    'tax' => [
        'vat' => 'Ppn ',
        'income' => 'Pph ',
    ],
    'discount' => [
        'contract' => [
            'water' => 'Disc. Air ',
            'non_water' => 'Disc. Non Air ',
            'air' => 'Disc. Udara ',
            'emission' => 'Disc. Emisi ',
            'transport' => 'Disc. Transport ',
            'manpower' => 'Disc. Perdiem ',
            'manpower24' => 'Disc. Perdiem 24 Jam ',
            'operational' => 'Disc. Analisa + Operasional ',
            'consultant' => 'Disc. Consultant ',
            'group' => 'Disc. Group ',
            'percent' => 'Cash Disc. ',
            'cash' => 'Cash Disc.',
            'custom' => 'Custom Disc.',
        ],
        'non_taxable' => [
            'transport' => 'Disc. Transportasi',
            'manpower' => 'Disc. Perdiem',
            'manpower24' => 'Disc. Perdiem 24 Jam',
        ]
    ],
    'approval' => [
        'proof' => 'Sebagai tanda persetujuan, agar dapat menandatangani serta mengirimkan kembali kepada pihak kami melalui email : sales@intilab.com',
        'administration' => 'Administrasi',
        'status' => 'Status',
        'sampling' => 'Tanggal Sampling',
        'pic' => 'PIC Sales',
        'approving' => 'Menyetujui',
        'name' => 'Nama',
        'position' => 'Jabatan',
    ],
];
