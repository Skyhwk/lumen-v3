<?php

namespace App\Http\Controllers\Greatday;

use App\Models\Absensi;
use App\Models\Greatday\AbsensiAndroid;
use App\Support\Greatday\GreatdayAssetPaths;
use App\Models\Greatday\LiburPerusahaan;
use App\Models\MasterCabang;
use App\Models\RfidCard;
use App\Models\ShiftKaryawan;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    private function produksi(): string
    {
        return config('greatday.produksi_connection', config('database.default', 'mysql'));
    }

    private function karyawanOrFail()
    {
        if (!$this->karyawan || !$this->user_id) {
            return null;
        }

        return $this->karyawan;
    }

    public function liburPerusahaan()
    {
        try {
            $data = LiburPerusahaan::where('is_active', true)
                ->select('tanggal', 'keterangan', 'tgl_ganti', 'tipe', DB::raw('1 as is_cuti'))
                ->get();

            return response()->json($data, 200);
        } catch (Exception $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function shift(Request $request)
    {
        try {
            $userid = $request->userid;
            $tahun = $request->year;
            $bulan = sprintf('%02d', $request->month);

            $result = ShiftKaryawan::on($this->produksi())
                ->where('karyawan_id', $userid)
                ->whereYear('tanggal', $tahun)
                ->whereMonth('tanggal', $bulan)
                ->get();

            if (!empty($result->toArray())) {
                foreach ($result as $value) {
                    $value['hari'] = $this->getDayName($value->tanggal);
                }

                return response()->json(['status' => 'success', 'message' => 'Absen berhasil', 'data' => $result], 200);
            }

            $jumlahHari = cal_days_in_month(CAL_GREGORIAN, (int) $bulan, (int) $tahun);
            $result = [];
            for ($hari = 1; $hari <= $jumlahHari; $hari++) {
                $tanggal = "$tahun-$bulan-" . sprintf('%02d', $hari);
                $namaHari = $this->getDayName($tanggal);
                if ($namaHari != 'Sabtu' && $namaHari != 'Minggu') {
                    $result[] = [
                        'userid' => $userid,
                        'tanggal' => $tanggal,
                        'shift' => 'SHREGULAR',
                        'time_in' => '08:00',
                        'time_out' => '17:00',
                        'hari' => $namaHari,
                    ];
                }
            }

            return response()->json(['status' => 'success', 'message' => 'Absen berhasil', 'data' => $result], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function add(Request $request)
    {
        if (!$this->karyawanOrFail()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        DB::connection($this->produksi())->beginTransaction();
        try {
            $id_jabatan = $this->karyawan->id_jabatan ?? null;
            $branch = MasterCabang::on($this->produksi())
                ->where('id', $this->karyawan->id_cabang)
                ->where('is_active', true)
                ->first();

            if (!$branch || $branch->attendance_latitude === null || $branch->attendance_longitude === null) {
                DB::connection($this->produksi())->rollBack();

                return response()->json(['status' => 'error', 'message' => 'Koordinat cabang belum dikonfigurasi'], 422);
            }

            if (!is_numeric($request->lat) || !is_numeric($request->long)) {
                DB::connection($this->produksi())->rollBack();

                return response()->json(['status' => 'error', 'message' => 'Koordinat absensi tidak valid'], 422);
            }

            $distance = $this->calculateDistance(
                (float) $request->lat,
                (float) $request->long,
                (float) $branch->attendance_latitude,
                (float) $branch->attendance_longitude
            );

            if ($distance > 70 && (int) $id_jabatan !== 94) {
                DB::connection($this->produksi())->rollBack();

                return response()->json(['status' => 'error', 'message' => 'Anda berada di luar area kerja'], 401);
            }

            $fileName = null;
            if ($request->hasFile('selfie')) {
                $selfieFile = $request->file('selfie');
                $fileName = str_replace('.', '', (string) microtime(true)) . '.webp';
                $targetDir = GreatdayAssetPaths::absensiPublicDir();
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }
                $selfieFile->move($targetDir, $fileName);
            }

            $jam = $request->jam;
            if (!empty($jam) && strpos($jam, '.') !== false) {
                $jam = str_replace('.', ':', $jam);
            }

            $tanggal = $request->tgl;

            $absen = AbsensiAndroid::create([
                'karyawan_id' => $this->user_id,
                'tanggal' => $tanggal,
                'jam' => $jam,
                'latitude' => $request->lat,
                'longitude' => $request->long,
                'distance' => round($distance, 2),
                'selfie' => $fileName,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            if ($absen) {
                $dataRfidCard = RfidCard::on($this->produksi())
                    ->where('userid', $this->user_id)
                    ->where('status', 0)
                    ->first();

                if (!$dataRfidCard) {
                    $this->insertProduksiAbsensiRow($tanggal, $jam);
                }

                if ($dataRfidCard) {
                    $absenApi = config('greatday.absen_api_url');
                    if (!$absenApi) {
                        throw new Exception('ABSEN_API not configured properly');
                    }

                    try {
                        $client = new Client(['timeout' => 15]);
                        $client->request('GET', $absenApi, [
                            'query' => [
                                'userid' => $this->user_id,
                                'device' => 'ISL05',
                                'rfid' => $dataRfidCard->kode_kartu,
                                'absen' => $request->tgl . 'T' . $jam,
                                'token' => 'intilab_jaya',
                            ],
                        ]);
                    } catch (GuzzleException $e) {
                        Log::warning('Greatday absen RFID sync failed', [
                            'user_id' => $this->user_id,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            }

            DB::connection($this->produksi())->commit();

            return response()->json(['status' => 'success', 'message' => 'Absen Success'], 200);
        } catch (Exception $e) {
            DB::connection($this->produksi())->rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Absensi tidak berhasil',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function get(Request $request)
    {
        if (!$this->karyawanOrFail()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        try {
            $currentDate = $request->tgl ?: date('Y-m-d');
            $shift = ShiftKaryawan::on($this->produksi())
                ->where('karyawan_id', $this->user_id)
                ->where('tanggal', $currentDate)
                ->first();

            $data = Absensi::on($this->produksi())
                ->where('karyawan_id', $this->user_id)
                ->where('tanggal', $currentDate)
                ->get();

            $absen_android = AbsensiAndroid::logsForKaryawanOnDate((int) $this->user_id, $currentDate);
            $data = $this->mergeAbsensiWithMobileLogs($data, $absen_android);

            $daftarShift = $this->daftarShift();

            $shiftName = $shift ? $shift->shift : 'SHREGULAR';
            $inTimeStr = $shift->time_in ?? null;
            $outTimeStr = $shift->time_out ?? null;

            if (!$inTimeStr || !$outTimeStr) {
                $shiftDetails = collect($daftarShift)->firstWhere('text', $shiftName);
                if (!$shiftDetails) {
                    $shiftDetails = ['value' => ['IN' => '08:00', 'OUT' => '17:00'], 'text' => $shiftName];
                }
                $inTimeStr = $inTimeStr ?: ($shiftDetails['value']['IN'] ?? '08:00');
                $outTimeStr = $outTimeStr ?: ($shiftDetails['value']['OUT'] ?? '17:00');
            }

            $isCrossDay = false;
            if ($inTimeStr && $outTimeStr) {
                $isCrossDay = strtotime($outTimeStr) <= strtotime($inTimeStr);
            }

            $checkinTime = null;
            $checkoutTime = null;
            $imageCheckin = null;
            $imageCheckout = null;

            if ($shiftName === 'OFF') {
                $checkinTime = null;
                $checkoutTime = null;
            } elseif ($isCrossDay) {
                $checkinThreshold = date('H:i:s', strtotime($inTimeStr . ' +4 hours'));
                $checkinTime = $data->where('jam', '<=', $checkinThreshold)->where('jam', '>=', date('H:i:s', strtotime($inTimeStr . ' -4 hours')))->min('jam')
                    ?? $data->where('jam', '>=', date('H:i:s', strtotime($inTimeStr . ' -4 hours')))->min('jam')
                    ?? null;
                $imageCheckin = $data->where('jam', $checkinTime)->min('selfie') ?? null;

                $nextDay = date('Y-m-d', strtotime($currentDate . ' +1 day'));
                $nextDayAbsensi = Absensi::on($this->produksi())
                    ->where('karyawan_id', $this->user_id)
                    ->where('tanggal', $nextDay)
                    ->get();
                $nextDayAndroid = AbsensiAndroid::logsForKaryawanOnDate((int) $this->user_id, $nextDay);
                $nextDayAbsensi = $this->mergeAbsensiWithMobileLogs($nextDayAbsensi, $nextDayAndroid);

                $checkoutTime = $nextDayAbsensi->where('jam', '<=', date('H:i:s', strtotime($outTimeStr . ' +5 hours')))->max('jam') ?? null;
                $imageCheckout = $nextDayAbsensi->where('jam', $checkoutTime)->min('selfie') ?? null;
            } else {
                $classified = $this->classifySameDayAbsensi(
                    $data->sortBy('jam')->values(),
                    $inTimeStr,
                    $outTimeStr,
                    $isCrossDay
                );
                $checkinTime = $classified['checkin_time'];
                $checkoutTime = $classified['checkout_time'];
                $imageCheckin = $classified['image_checkin'];
                $imageCheckout = $classified['image_checkout'];
            }

            $midHourStr = $this->resolveMidHourStr($inTimeStr, $outTimeStr, $isCrossDay);

            $imageCheckin = $this->resolveSelfieFilename($imageCheckin, $checkinTime, $absen_android);
            if (!$imageCheckin) {
                $imageCheckin = $this->fallbackSelfieInWindow(
                    $absen_android,
                    $checkinTime,
                    $midHourStr,
                    'checkin'
                );
            }

            $checkoutMobile = isset($nextDayAndroid) ? $nextDayAndroid : $absen_android;
            $imageCheckout = $this->resolveSelfieFilename($imageCheckout, $checkoutTime, $checkoutMobile);
            if (!$imageCheckout) {
                $imageCheckout = $this->fallbackSelfieInWindow(
                    $checkoutMobile,
                    $checkoutTime,
                    $midHourStr,
                    'checkout'
                );
            }

            $checkinDiff = ($checkinTime && $inTimeStr) ? (strtotime($checkinTime) - strtotime($inTimeStr)) / 60 : null;
            $checkoutDiff = ($checkoutTime && $outTimeStr) ? (strtotime($checkoutTime) - strtotime($outTimeStr)) / 60 : null;

            $nowTimeStr = date('H:i:s');
            $suggestedAction = (strtotime($nowTimeStr) >= strtotime($midHourStr)) ? 'checkout' : 'checkin';

            $attendanceData = [
                'shift' => $shiftName,
                'time_in' => $inTimeStr,
                'time_out' => $outTimeStr,
                'is_cross_day' => $isCrossDay,
                'suggested_action' => $suggestedAction,
                'checkin_time' => $checkinTime,
                'checkout_time' => $checkoutTime,
                'checkin_diff' => $checkinDiff,
                'checkout_diff' => $checkoutDiff,
                'date' => $currentDate,
                'image_checkin' => $imageCheckin,
                'image_checkout' => $imageCheckout,
                'image_checkin_url' => $this->absensiPublicUrl($imageCheckin),
                'image_checkout_url' => $this->absensiPublicUrl($imageCheckout),
            ];

            return response()->json(['status' => 'success', 'data' => $attendanceData], 200);
        } catch (\Throwable $th) {
            return response()->json(['status' => 'error', 'error' => $th->getMessage()], 500);
        }
    }

    public function getAttendance(Request $request)
    {
        if (!$this->karyawanOrFail()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        try {
            $startDate = date('Y-m-01', strtotime($request->year . '-' . $request->month . '-01'));
            $endDate = date('Y-m-t', strtotime($startDate));
            $attendanceData = [];

            $dates = new \DatePeriod(
                new \DateTime($startDate),
                new \DateInterval('P1D'),
                (new \DateTime($endDate))->modify('+1 day')
            );

            $daftarShift = $this->daftarShift();

            foreach ($dates as $date) {
                $currentDate = $date->format('Y-m-d');
                $dayName = $this->getDayName($currentDate);
                $shift = ShiftKaryawan::on($this->produksi())
                    ->where('karyawan_id', $this->user_id)
                    ->where('tanggal', $currentDate)
                    ->first();

                $data = Absensi::on($this->produksi())
                    ->where('karyawan_id', $this->user_id)
                    ->where('tanggal', $currentDate)
                    ->get();

                $shiftName = $shift ? $shift->shift : 'SHREGULAR';
                $inTimeStr = $shift->time_in ?? null;
                $outTimeStr = $shift->time_out ?? null;

                if (!$inTimeStr || !$outTimeStr) {
                    $shiftDetails = collect($daftarShift)->firstWhere('text', $shiftName);
                    if (!$shiftDetails) {
                        $shiftDetails = ['value' => ['IN' => '08:00', 'OUT' => '17:00'], 'text' => $shiftName];
                    }
                    $inTimeStr = $inTimeStr ?: ($shiftDetails['value']['IN'] ?? '08:00');
                    $outTimeStr = $outTimeStr ?: ($shiftDetails['value']['OUT'] ?? '17:00');
                }

                $isCrossDay = false;
                if ($inTimeStr && $outTimeStr) {
                    $isCrossDay = strtotime($outTimeStr) <= strtotime($inTimeStr);
                }

                $checkinTime = null;
                $checkoutTime = null;

                if ($shiftName === 'OFF') {
                    $checkinTime = null;
                    $checkoutTime = null;
                } elseif ($isCrossDay) {
                    $checkinThreshold = date('H:i:s', strtotime($inTimeStr . ' +4 hours'));
                    $checkinTime = $data->where('jam', '<=', $checkinThreshold)->where('jam', '>=', date('H:i:s', strtotime($inTimeStr . ' -4 hours')))->min('jam')
                        ?? $data->where('jam', '>=', date('H:i:s', strtotime($inTimeStr . ' -4 hours')))->min('jam')
                        ?? null;

                    $nextDay = date('Y-m-d', strtotime($currentDate . ' +1 day'));
                    $nextDayAbsensi = Absensi::on($this->produksi())
                        ->where('karyawan_id', $this->user_id)
                        ->where('tanggal', $nextDay)
                        ->get();
                    $checkoutTime = $nextDayAbsensi->where('jam', '<=', date('H:i:s', strtotime($outTimeStr . ' +5 hours')))->max('jam') ?? null;
                } else {
                    $classified = $this->classifySameDayAbsensi(
                        $data->sortBy('jam')->values(),
                        $inTimeStr,
                        $outTimeStr,
                        $isCrossDay
                    );
                    $checkinTime = $classified['checkin_time'];
                    $checkoutTime = $classified['checkout_time'];
                }

                $checkinDiff = ($checkinTime && $inTimeStr) ? (strtotime($checkinTime) - strtotime($inTimeStr)) / 60 : null;
                $checkoutDiff = ($checkoutTime && $outTimeStr) ? (strtotime($checkoutTime) - strtotime($outTimeStr)) / 60 : null;

                $attendanceData[] = [
                    'shift' => $shiftName,
                    'checkin_time' => $checkinTime,
                    'checkout_time' => $checkoutTime,
                    'checkin_diff' => $checkinDiff,
                    'checkout_diff' => $checkoutDiff,
                    'date' => $currentDate,
                    'day_name' => $dayName,
                ];
            }

            return response()->json(['status' => 'success', 'data' => $attendanceData], 200);
        } catch (\Throwable $th) {
            return response()->json(['status' => 'error', 'message' => $th->getMessage()], 500);
        }
    }

    public function getCoordinateOffice(Request $request)
    {
        if (!$this->karyawanOrFail()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $branchId = $this->karyawan->id_cabang ?? $request->id_cabang;
        $branch = MasterCabang::on($this->produksi())
            ->where('id', $branchId)
            ->where('is_active', true)
            ->first();

        if (!$branch || $branch->attendance_latitude === null || $branch->attendance_longitude === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Koordinat cabang belum dikonfigurasi',
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $branch->id,
                'name' => $branch->nama_cabang,
                'lat' => (float) $branch->attendance_latitude,
                'long' => (float) $branch->attendance_longitude,
            ],
        ], 200);
    }

    private function getDayName($date)
    {
        $days = [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ];

        return $days[date('l', strtotime($date))];
    }

    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371e3;
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $deltaPhi = deg2rad($lat2 - $lat1);
        $deltaLambda = deg2rad($lon2 - $lon1);
        $a = sin($deltaPhi / 2) ** 2
            + cos($phi1) * cos($phi2) * sin($deltaLambda / 2) ** 2;

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    private function resolveMidHourStr($inTimeStr, $outTimeStr, $isCrossDay)
    {
        $midHourStr = '13:00:00';

        if ($inTimeStr && $outTimeStr) {
            if ($isCrossDay) {
                $midTimestamp = strtotime($inTimeStr) + (24 * 3600 + strtotime($outTimeStr) - strtotime($inTimeStr)) / 2;
            } else {
                $midTimestamp = (strtotime($inTimeStr) + strtotime($outTimeStr)) / 2;
            }

            $midHourStr = date('H:i:s', $midTimestamp);
        }

        return $midHourStr;
    }

    private function classifySameDayAbsensi($sortedData, $inTimeStr, $outTimeStr, $isCrossDay)
    {
        $checkinTime = null;
        $checkoutTime = null;
        $imageCheckin = null;
        $imageCheckout = null;

        if ($sortedData->isEmpty()) {
            return [
                'checkin_time' => $checkinTime,
                'checkout_time' => $checkoutTime,
                'image_checkin' => $imageCheckin,
                'image_checkout' => $imageCheckout,
            ];
        }

        $midHourStr = $this->resolveMidHourStr($inTimeStr, $outTimeStr, $isCrossDay);
        $checkinRecords = collect();
        $checkoutRecords = collect();

        foreach ($sortedData as $record) {
            if (strtotime($record->jam) >= strtotime($midHourStr)) {
                $checkoutRecords->push($record);
            } else {
                $checkinRecords->push($record);
            }
        }

        if ($checkinRecords->isNotEmpty()) {
            $earliestCheckin = $checkinRecords->sortBy('jam')->first();
            $checkinTime = $earliestCheckin->jam;
            $imageCheckin = $earliestCheckin->selfie ?? null;
        }

        if ($checkoutRecords->isNotEmpty()) {
            $latestCheckout = $checkoutRecords->sortByDesc('jam')->first();
            $checkoutTime = $latestCheckout->jam;
            $imageCheckout = $latestCheckout->selfie ?? null;
        }

        return [
            'checkin_time' => $checkinTime,
            'checkout_time' => $checkoutTime,
            'image_checkin' => $imageCheckin,
            'image_checkout' => $imageCheckout,
        ];
    }

    private function insertProduksiAbsensiRow(string $tanggal, string $jam): void
    {
        Absensi::on($this->produksi())->create([
            'karyawan_id' => $this->user_id,
            'tanggal' => $tanggal,
            'jam' => $jam,
            'hari' => $this->getDayName($tanggal),
            'kode_mesin' => 'GREATDAY',
            'status' => 'Masuk',
        ]);
    }

    /**
     * @param \Illuminate\Support\Collection $absensiRows
     * @param \Illuminate\Support\Collection $mobileRows
     */
    private function mergeAbsensiWithMobileLogs($absensiRows, $mobileRows)
    {
        $mobileRows = collect($mobileRows);

        foreach ($absensiRows as $item) {
            $selfie = $this->selfieFromMobileLogs($mobileRows, $item->jam ?? null);
            if ($selfie !== null) {
                $item->selfie = $selfie;
            }
        }

        if ($absensiRows->isEmpty() && $mobileRows->isNotEmpty()) {
            return $mobileRows->map(function ($row) {
                return (object) [
                    'jam' => $this->normalizeJam($row->jam) ?? $row->jam,
                    'selfie' => $row->selfie,
                ];
            });
        }

        $existingJams = $absensiRows
            ->map(fn ($item) => $this->normalizeJam($item->jam ?? null))
            ->filter()
            ->values()
            ->all();

        foreach ($mobileRows as $row) {
            $jam = $this->normalizeJam($row->jam ?? null);
            if ($jam === null || in_array($jam, $existingJams, true)) {
                continue;
            }
            $absensiRows->push((object) [
                'jam' => $jam,
                'selfie' => $row->selfie,
            ]);
            $existingJams[] = $jam;
        }

        return $absensiRows;
    }

    private function normalizeJam($jam): ?string
    {
        if ($jam === null || $jam === '') {
            return null;
        }

        $ts = strtotime((string) $jam);

        return $ts ? date('H:i:s', $ts) : null;
    }

    private function selfieFromMobileLogs($mobileRows, ?string $jam): ?string
    {
        $target = $this->normalizeJam($jam);
        if ($target === null) {
            return null;
        }

        foreach (collect($mobileRows) as $row) {
            if ($this->normalizeJam($row->jam ?? null) === $target && !empty($row->selfie)) {
                return (string) $row->selfie;
            }
        }

        return null;
    }

    private function resolveSelfieFilename(?string $current, ?string $jam, $mobileRows): ?string
    {
        if ($current !== null && $current !== '') {
            return basename($current);
        }

        $fromMobile = $this->selfieFromMobileLogs($mobileRows, $jam);

        return $fromMobile !== null ? basename($fromMobile) : null;
    }

    private function absensiPublicUrl(?string $fileName): ?string
    {
        if ($fileName === null || $fileName === '') {
            return null;
        }

        return GreatdayAssetPaths::absensiPublicUrl($fileName);
    }

    /**
     * Tap mesin RFID bisa lebih awal dari selfie mobile — cari foto terdekat di window masuk/keluar.
     *
     * @param 'checkin'|'checkout' $window
     */
    private function fallbackSelfieInWindow($mobileRows, ?string $anchorTime, ?string $midHourStr, string $window): ?string
    {
        $midTs = $midHourStr ? strtotime($midHourStr) : null;
        $anchorTs = $anchorTime ? strtotime($this->normalizeJam($anchorTime) ?? $anchorTime) : null;

        $candidates = [];
        foreach (collect($mobileRows) as $row) {
            if (empty($row->selfie)) {
                continue;
            }
            $jam = $this->normalizeJam($row->jam ?? null);
            if ($jam === null) {
                continue;
            }
            $ts = strtotime($jam);
            if ($window === 'checkin') {
                if ($midTs !== null && $ts >= $midTs) {
                    continue;
                }
            } elseif ($midTs !== null && $ts < $midTs) {
                continue;
            }
            $candidates[] = ['ts' => $ts, 'selfie' => (string) $row->selfie];
        }

        if ($candidates === []) {
            return null;
        }

        if ($anchorTs !== null) {
            usort($candidates, fn ($a, $b) => abs($a['ts'] - $anchorTs) <=> abs($b['ts'] - $anchorTs));

            return basename($candidates[0]['selfie']);
        }

        if ($window === 'checkout') {
            usort($candidates, fn ($a, $b) => $b['ts'] <=> $a['ts']);
        } else {
            usort($candidates, fn ($a, $b) => $a['ts'] <=> $b['ts']);
        }

        return basename($candidates[0]['selfie']);
    }

    private function daftarShift(): array
    {
        return [
            ['value' => ['IN' => '08:00', 'OUT' => '17:00'], 'text' => 'SHREGULAR'],
            ['value' => ['IN' => '10:00', 'OUT' => '19:00'], 'text' => 'SHTEKNISI'],
            ['value' => ['IN' => '07:00', 'OUT' => '16:00'], 'text' => 'SHANALYST'],
            ['value' => ['IN' => '07:00', 'OUT' => '16:00'], 'text' => 'SHADMSAMPLING'],
            ['value' => ['IN' => '06:00', 'OUT' => '15:00'], 'text' => 'SHOB'],
            ['value' => ['IN' => '09:00', 'OUT' => '18:00'], 'text' => 'SHOB2'],
            ['value' => ['IN' => '07:00', 'OUT' => '12:00'], 'text' => 'SWOB'],
            ['value' => ['IN' => '09:00', 'OUT' => '18:00'], 'text' => 'SWOB2'],
            ['value' => ['IN' => '08:00', 'OUT' => '20:00'], 'text' => 'SHSECURITY'],
            ['value' => ['IN' => '20:00', 'OUT' => '08:00'], 'text' => 'SHSECURITY2'],
            ['value' => ['IN' => '07:00', 'OUT' => '19:00'], 'text' => 'SHSECURITYGO1'],
            ['value' => ['IN' => '07:00', 'OUT' => '15:00'], 'text' => 'SHSECURITYGO2'],
            ['value' => ['IN' => null, 'OUT' => null], 'text' => 'OFF'],
        ];
    }
}
