<?php

namespace App\Services;

use App\Models\DataLapanganErgonomi;
use App\Models\DataLapanganIsokinetikBeratMolekul;
use App\Models\DataLapanganIsokinetikHasil;
use App\Models\DataLapanganIsokinetikKadarAir;
use App\Models\DataLapanganIsokinetikPenentuanKecepatanLinier;
use App\Models\DataLapanganIsokinetikPenentuanPartikulat;
use App\Models\DataLapanganIsokinetikSurveiLapangan;
use App\Models\DataLapanganLingkunganHidup;
use App\Models\DataLapanganLingkunganKerja;
use App\Models\DataLapanganMicrobiologi;
use App\Models\DataLapanganSenyawaVolatile;
use App\Models\DetailLingkunganHidup;
use App\Models\DetailLingkunganKerja;
use App\Models\DetailMicrobiologi;
use App\Models\DetailSenyawaVolatile;
use App\Models\OrderDetail;

class FdlOrderDetailService
{
    /**
     * Set tanggal_terima menjadi null pada order detail aktif untuk nomor sampel terkait.
     */
    public static function nullTanggalTerimaByNoSampel($noSampel): void
    {
        $no = self::normalizeNoSampel($noSampel);
        if ($no === null) {
            return;
        }

        OrderDetail::where('no_sampel', $no)
            ->where('is_active', 1)
            ->update(['tanggal_terima' => null]);
    }

    /**
     * Null tanggal_terima hanya jika tidak ada baris tersisa (count = 0).
     */
    public static function nullTanggalTerimaIfNoRemaining($noSampel, int $remainingCount): void
    {
        if ($remainingCount === 0) {
            self::nullTanggalTerimaByNoSampel($noSampel);
        }
    }

    /**
     * Null jika tidak ada data lapangan tersisa di model tertentu untuk no sampel.
     */
    public static function nullTanggalTerimaIfNoLapanganRemaining($noSampel, string $modelClass): void
    {
        $no = self::normalizeNoSampel($noSampel);
        if ($no === null) {
            return;
        }

        $remaining = $modelClass::where('no_sampel', $no)->count();
        self::nullTanggalTerimaIfNoRemaining($noSampel, $remaining);
    }

    /**
     * Partikulat isokinetik: null jika semua tabel lapangan isokinetik kosong untuk no sampel.
     */
    public static function nullTanggalTerimaIfNoIsokinetikRemaining($noSampel): void
    {
        $no = self::normalizeNoSampel($noSampel);
        if ($no === null) {
            return;
        }

        $models = [
            DataLapanganIsokinetikSurveiLapangan::class,
            DataLapanganIsokinetikPenentuanKecepatanLinier::class,
            DataLapanganIsokinetikBeratMolekul::class,
            DataLapanganIsokinetikKadarAir::class,
            DataLapanganIsokinetikPenentuanPartikulat::class,
            DataLapanganIsokinetikHasil::class,
        ];

        $remaining = 0;
        foreach ($models as $modelClass) {
            $remaining += $modelClass::where('no_sampel', $no)->count();
        }

        self::nullTanggalTerimaIfNoRemaining($noSampel, $remaining);
    }

    /**
     * Ergonomi (semua method): null jika tidak ada baris DataLapanganErgonomi tersisa.
     */
    public static function nullTanggalTerimaIfNoErgonomiRemaining($noSampel): void
    {
        self::nullTanggalTerimaIfNoLapanganRemaining($noSampel, DataLapanganErgonomi::class);
    }

    /**
     * Setelah hapus parameter/shift: hapus header jika detail kosong, lalu null tanggal_terima jika FDL kosong.
     */
    public static function finalizePartialFdlDelete($noSampel, string $headerModel, string $detailModel): void
    {
        $no = self::normalizeNoSampel($noSampel);
        if ($no === null) {
            return;
        }

        if ($detailModel::where('no_sampel', $no)->count() === 0) {
            $headerModel::where('no_sampel', $no)->delete();
        }

        $remaining = $headerModel::where('no_sampel', $no)->count()
            + $detailModel::where('no_sampel', $no)->count();

        self::nullTanggalTerimaIfNoRemaining($noSampel, $remaining);
    }

    public static function finalizeMicrobiologiPartialDelete($noSampel): void
    {
        self::finalizePartialFdlDelete($noSampel, DataLapanganMicrobiologi::class, DetailMicrobiologi::class);
    }

    public static function finalizeSenyawaVolatilePartialDelete($noSampel): void
    {
        self::finalizePartialFdlDelete($noSampel, DataLapanganSenyawaVolatile::class, DetailSenyawaVolatile::class);
    }

    public static function finalizeLingkunganKerjaPartialDelete($noSampel): void
    {
        self::finalizePartialFdlDelete($noSampel, DataLapanganLingkunganKerja::class, DetailLingkunganKerja::class);
    }

    public static function finalizeLingkunganHidupPartialDelete($noSampel): void
    {
        self::finalizePartialFdlDelete($noSampel, DataLapanganLingkunganHidup::class, DetailLingkunganHidup::class);
    }

    /**
     * Microbiologi udara: null jika header + detail sudah kosong (tanpa menghapus header).
     */
    public static function nullTanggalTerimaIfNoMicrobiologiRemaining($noSampel): void
    {
        self::nullTanggalTerimaIfNoHeaderDetailRemaining(
            $noSampel,
            DataLapanganMicrobiologi::class,
            DetailMicrobiologi::class
        );
    }

    /**
     * Senyawa volatile: null jika header + detail sudah kosong (tanpa menghapus header).
     */
    public static function nullTanggalTerimaIfNoSenyawaVolatileRemaining($noSampel): void
    {
        self::nullTanggalTerimaIfNoHeaderDetailRemaining(
            $noSampel,
            DataLapanganSenyawaVolatile::class,
            DetailSenyawaVolatile::class
        );
    }

    private static function nullTanggalTerimaIfNoHeaderDetailRemaining(
        $noSampel,
        string $headerModel,
        string $detailModel
    ): void {
        $no = self::normalizeNoSampel($noSampel);
        if ($no === null) {
            return;
        }

        $remaining = $headerModel::where('no_sampel', $no)->count()
            + $detailModel::where('no_sampel', $no)->count();

        self::nullTanggalTerimaIfNoRemaining($noSampel, $remaining);
    }

    private static function normalizeNoSampel($noSampel): ?string
    {
        if ($noSampel === null || trim((string) $noSampel) === '') {
            return null;
        }

        return strtoupper(trim((string) $noSampel));
    }
}
