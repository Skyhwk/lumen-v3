<?php

namespace App\Services;

use App\Models\EmployeeHealthCheck;
use App\Models\MasterCabang;
use Carbon\Carbon;
use Mpdf\Mpdf;

class EmployeeHealthCheckDocumentService
{
    public function generateSkkPdf(object $record): string
    {
        $record = $this->prepareRecord($record);

        Carbon::setLocale('id');

        $checkDate = $record->check_date
            ? Carbon::parse($record->check_date)->locale('id')
            : Carbon::now()->locale('id');

        $company = $this->resolveCompanyHeader();

        $html = view('pdf.skk-kesehatan', [
            'record' => $record,
            'company' => $company,
            'skkNumber' => trim((string) ($record->skk_number ?? '')) ?: null,
            'checkDateLong' => $checkDate->translatedFormat('l, d F Y'),
            'issuedDateLong' => $checkDate->translatedFormat('d F Y'),
            'saturasiLabel' => $this->formatDecimal($record->saturasi),
            'suhuLabel' => $this->formatDecimal($record->suhu),
            'hasOptionalLabs' => $this->hasOptionalLabs($record),
            'conclusionText' => $this->buildConclusionText($record),
            'petugasName' => trim((string) ($record->created_by ?? '')) ?: '-',
        ])->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 18,
            'margin_right' => 18,
            'margin_top' => 18,
            'margin_bottom' => 18,
            'default_font' => 'times',
        ]);

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    private function resolveCompanyHeader(): array
    {
        $logoPath = file_exists(public_path('isl_logo.png'))
            ? public_path('isl_logo.png')
            : public_path('img/isl_logo.png');

        $cabang = MasterCabang::query()
            ->where('is_active', 1)
            ->where(function ($query) {
                $query->where('id', 1)
                    ->orWhereRaw('UPPER(nama_cabang) LIKE ?', ['%HEAD OFFICE%']);
            })
            ->orderBy('id')
            ->first();

        if ($cabang === null) {
            $cabang = MasterCabang::query()
                ->where('is_active', 1)
                ->orderBy('id')
                ->first();
        }

        return [
            'logo_path' => $logoPath,
            'name' => 'PT INTI SURYA LABORATORIUM',
            'address' => trim((string) ($cabang->alamat_cabang ?? '')),
            'phone' => trim((string) ($cabang->tlp_cabang ?? '')),
            'email' => 'sales@intilab.com',
            'website' => 'www.intilab.com',
            'city' => $this->resolveCityLabel($cabang->nama_cabang ?? null),
        ];
    }

    private function resolveCityLabel(?string $branchName): string
    {
        $normalized = strtoupper(trim((string) $branchName));

        if ($normalized === '' || str_contains($normalized, 'HEAD OFFICE')) {
            return 'Tangerang';
        }

        if (str_contains($normalized, 'KARAWANG')) {
            return 'Karawang';
        }

        if (str_contains($normalized, 'PEMALANG')) {
            return 'Pemalang';
        }

        return 'Tangerang';
    }

    private function prepareRecord(object $record): object
    {
        $record->karyawan = $record->karyawan ?: '-';
        $record->nama_divisi = $record->nama_divisi ?: '-';
        $record->nik_karyawan = $record->nik_karyawan ?: '-';
        $record->keterangan_label = $this->mapKeteranganLabel($record->keterangan ?? null);
        $record->stetoskop_label = $this->mapStetoskopLabel($record->stetoskop ?? null);
        $record->check_time_label = $record->check_time
            ? substr((string) $record->check_time, 0, 5)
            : '-';
        $record->gula_darah_label = $this->formatOptionalNumber($record->gula_darah ?? null);
        $record->asam_urat_label = $this->formatOptionalNumber($record->asam_urat ?? null);
        $record->kolesterol_label = $this->formatOptionalNumber($record->kolesterol ?? null);
        $record->keluhan = trim((string) ($record->keluhan ?? '')) ?: '-';
        $record->tensi_label = EmployeeHealthCheck::formatTensiLabel(
            $record->tensi_sistolik ?? null,
            $record->tensi_diastolik ?? null
        );

        return $record;
    }

    private function buildConclusionText(object $record): string
    {
        $name = $record->karyawan !== '-' ? $record->karyawan : 'Karyawan tersebut';
        $boldName = '<strong>' . e($name) . '</strong>';
        $keterangan = strtolower(trim((string) ($record->keterangan ?? '')));

        $map = [
            EmployeeHealthCheck::KET_SEHAT => "Berdasarkan hasil pemeriksaan kesehatan yang telah dilakukan pada tanggal di atas, "
                . "dengan memperhatikan seluruh parameter vital yang tercantum, maka {$boldName} dinyatakan berada dalam kondisi "
                . "sehat, tidak ditemukan kelainan yang membahayakan, serta dinyatakan layak dan "
                . "diperbolehkan untuk melaksanakan tugas dan pekerjaannya.",
            EmployeeHealthCheck::KET_PERLU_OBSERVASI => "Berdasarkan hasil pemeriksaan kesehatan yang telah dilakukan pada tanggal di atas, "
                . "ditemukan kondisi kesehatan pada {$boldName} yang perlu mendapat observasi dan pemantauan lanjutan. "
                . "Karyawan tersebut disarankan untuk istirahat, "
                . "serta melaporkan kembali kondisinya apabila terdapat keluhan yang berkelanjutan atau memburuk.",
            EmployeeHealthCheck::KET_PERLU_RUJUKAN => "Berdasarkan hasil pemeriksaan kesehatan yang telah dilakukan pada tanggal di atas, "
                . "ditemukan kondisi kesehatan pada {$boldName} yang memerlukan pemeriksaan dan penanganan lebih lanjut "
                . "di fasilitas pelayanan kesehatan. Karyawan tersebut disarankan untuk segera mendapatkan rujukan "
                . "ke tenaga medis yang kompeten guna dilakukan evaluasi, diagnosis, dan tindakan medis yang sesuai.",
            EmployeeHealthCheck::KET_IZIN => "Berdasarkan hasil pemeriksaan kesehatan yang telah dilakukan pada tanggal di atas, "
                . "dinyatakan bahwa {$boldName} dalam kondisi yang tidak memungkinkan untuk melaksanakan tugas pekerjaan "
                . "pada saat ini. Oleh karena itu, karyawan tersebut perlu diberikan izin tidak masuk kerja "
                . "sampai kondisi kesehatannya membaik dan dinyatakan layak kembali bekerja oleh petugas kesehatan.",
            EmployeeHealthCheck::KET_SAKIT => "Berdasarkan hasil pemeriksaan kesehatan yang telah dilakukan pada tanggal tersebut di atas, "
                . "dinyatakan bahwa {$boldName} sedang dalam kondisi sakit dan memerlukan istirahat total untuk proses "
                . "pemulihan. Karyawan tersebut tidak diperkenankan melaksanakan aktivitas pekerjaan hingga kondisi "
                . "kesehatannya membaik dan dapat dinilai kembali oleh petugas paramedis.",
            EmployeeHealthCheck::KET_LAINNYA => "Berdasarkan hasil pemeriksaan kesehatan yang telah dilakukan pada tanggal tersebut di atas, "
                . "telah dilakukan penilaian terhadap kondisi kesehatan {$boldName} dengan keterangan: {$record->keterangan_label}. "
                . "Surat keterangan ini diterbitkan sebagai bukti bahwa pemeriksaan kesehatan telah dilaksanakan "
                . "dan hasilnya dicatat sesuai dengan kondisi yang ditemukan pada saat pemeriksaan dilakukan.",
        ];

        return $map[$keterangan] ?? "Demikian surat keterangan kesehatan ini dibuat dengan sebenarnya berdasarkan hasil "
            . "pemeriksaan yang telah dilakukan, untuk dapat dipergunakan sebagaimana mestinya.";
    }

    private function hasOptionalLabs(object $record): bool
    {
        foreach (['gula_darah', 'asam_urat', 'kolesterol'] as $field) {
            $value = $record->{$field} ?? null;
            if ($value !== null && $value !== '' && $value !== '-') {
                return true;
            }
        }

        return false;
    }

    private function formatDecimal($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        $num = (float) $value;

        return fmod($num, 1.0) === 0.0
            ? (string) (int) $num
            : rtrim(rtrim(number_format($num, 1, '.', ''), '0'), '.');
    }

    private function formatOptionalNumber($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return $this->formatDecimal($value);
    }

    private function mapKeteranganLabel($value): string
    {
        $map = [
            EmployeeHealthCheck::KET_SEHAT => 'Sehat',
            EmployeeHealthCheck::KET_PERLU_OBSERVASI => 'Perlu Observasi',
            EmployeeHealthCheck::KET_PERLU_RUJUKAN => 'Perlu Rujukan',
            EmployeeHealthCheck::KET_IZIN => 'Izin',
            EmployeeHealthCheck::KET_SAKIT => 'Sakit',
            EmployeeHealthCheck::KET_LAINNYA => 'Lainnya',
        ];

        return $map[$value] ?? ($value ?: '-');
    }

    private function mapStetoskopLabel($value): string
    {
        $map = [
            EmployeeHealthCheck::STOK_NORMAL => 'Normal',
            EmployeeHealthCheck::STOK_ADA_KELAINAN => 'Ada Kelainan',
        ];

        return $map[$value] ?? ($value ?: '-');
    }
}
