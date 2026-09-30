<p>
    Kepada yang terhormat, <br />
    <b>{{ $namaPicOrder }} <br />
        {{ $namaPerusahaan }}
    </b>
</p>
<p>{{ $greeting }}</p>
<p>
    Berikut kami lampirkan : <br />
    <b>Surat Penawaran ({{ $noDocument }}) - {{ $statusSamplingLabel }} </b>
</p>
<p>Kategori Pengujian : {!! $kategoriHtml !!}</p>
<p>
    Mohon agar file yang kami kirim melalui link berikut : <a href="{{ $portalLink }}">Click Here</a>, dapat diperiksa lebih lanjut. Sehubungan mengenai konfirmasi penjadwalan dan hal yang ingin ditanyakan atau didiskusikan, dapat langsung menghubungi pihak
    kami melalui {{ $salesName }} ({{ $salesPhone }})
</p>
<p>Terima kasih atas perhatian, kepercayaan, serta kerjasama yang sangat baik.</p>
<p>
    <u>
        <strong>CATATAN PENTING</strong>
    </u>
</p>
<p>Mohon Bapak/Ibu dapat meninjau kembali form penawaran ini untuk memastikan seluruh data kebutuhan telah sesuai. Sebagai bentuk persetujuan, mohon menandatangani dan mengirimkan kembali kepada kami melalui email: <a href="mailto:sales@intilab.com">sales@intilab.com</a></p>
<p>
    <u>
        <strong>INFORMASI PENTING</strong>
    </u>
</p>
<p>
    <em>E-mail</em> ini dikirimkan secara otomatis oleh sistem PT Inti Surya Laboratorium (INTILAB) melalui <br />
    <strong>
        <u>alamat <i>E-mail</i> resmi perusahaan, yaitu
            <span style="color: rgb(255, 0, 0)">
                <strong><u>{{ $officialEmail }}</u></strong>
            </span>
        </u>
    </strong>. Untuk menjaga keamanan data dan <br />informasi, disarankan agar penerima <em>E-mail</em> : <br />a. <strong>Memastikan kembali alamat pengirim </strong>
    <em>
        <strong>E-mail</strong>
    </em>
    <strong> ini adalah sesuai alamat </strong>
    <em>
        <strong>E-mail</strong>
    </em>
    <strong> resmi perusahaan; dan,</strong>
    <br />b. <strong>Tidak mengklik tautan dan/atau mengunduh lampiran apapun jika </strong>
    <em>
        <strong>E-mail</strong>
    </em>
    <strong> ini dikirimkan selain</strong>
    <br />
    <strong>dari alamat </strong>
    <em>
        <strong>E-mail</strong>
    </em>
    <strong> resmi perusahaan.</strong>
</p>
<p><em>E-mail</em> dan dokumen lampiran ini bersifat rahasia (berisi data dan informasi rahasia) yang ditujukan <br />secara eksklusif kepada penerima <em>E-mail</em>.</p>
{!! $signatureHtml !!}
