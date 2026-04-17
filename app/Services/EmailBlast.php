<?php

namespace App\Services;

use App\Models\{MailSchedule, MailList, JobTask};
use App\Jobs\JobEmailBlast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Repository;
use Carbon\Carbon;

class EmailBlast
{
    public static function sendEmailBlast()
    {
        try {
            $currentDate = date('Y-m-d');
            $currentDay = date('l');
            $currentTime = date('H:i'); // 🔥 hanya menit

            // ================================
            // 🔒 ANTI DOUBLE EXECUTION (WAJIB)
            // ================================
            $executedKey = 'email_blast_executed_' . $currentTime;

            if (Cache::has($executedKey)) {
                return;
            }

            $lock = Cache::lock('email_blast_lock_' . $currentTime, 60);

            if (!$lock->get()) {
                return;
            }

            // set executed flag 1 menit
            Cache::put($executedKey, true, 60);

            // ================================
            // 🔄 CACHE SCHEDULE (HEMAT DB)
            // ================================
            $schedules = Cache::remember("mail_schedule_cache", 10, function () {
                return MailSchedule::all();
            });

            $dayMapping = [
                'Senin' => 'Monday',
                'Selasa' => 'Tuesday',
                'Rabu' => 'Wednesday',
                'Kamis' => 'Thursday',
                'Jumat' => 'Friday',
                'Sabtu' => 'Saturday',
                'Minggu' => 'Sunday',
            ];

            foreach ($schedules as $schedule) {

                // ================================
                // 📅 FILTER DATE
                // ================================
                if ($currentDate < $schedule->start_date || $currentDate > $schedule->end_date) {
                    continue;
                }

                // ================================
                // 📆 FILTER DAY
                // ================================
                $days = $schedule->days ? json_decode($schedule->days, true) : [];

                $filteredDays = array_filter($days, function ($day) use ($dayMapping, $currentDay) {
                    return ($dayMapping[$day] ?? null) === $currentDay;
                });

                if (empty($filteredDays)) {
                    continue;
                }

                // ================================
                // ⏰ FILTER TIME (HANYA MENIT)
                // ================================
                if (substr($schedule->time, 0, 5) !== $currentTime) {
                    continue;
                }

                // ================================
                // 📧 GET MAIL DATA
                // ================================
                $mailist = MailList::find($schedule->mail_id);
                if (!$mailist) {
                    continue;
                }

                $content = Repository::dir('blast_mail_template')
                    ->key($mailist->name)
                    ->get();

                Log::channel('custom_email_log')
                    ->info('[EmailBlast] Sending email at ' . Carbon::now());

                // ================================
                // 📝 LOG (1x saja, tidak spam)
                // ================================
                JobTask::insert([
                    'job' => 'JobEmailBlast',
                    'status' => 'processed',
                    'timestamp' => Carbon::now()
                ]);

                // ================================
                // 🚀 DISPATCH QUEUE
                // ================================
                dispatch(new JobEmailBlast(
                    $mailist->email_to,
                    preg_replace('/[^\p{L}\p{N}\s!]/u', '', $mailist->subject),
                    json_decode($mailist->reply_to, true),
                    $content
                ));
            }

            // release lock
            optional($lock)->release();

        } catch (\Throwable $e) {
            Log::channel('custom_email_log')->error(
                "[EmailBlast ERROR] {$e->getMessage()} in {$e->getFile()} line {$e->getLine()}"
            );
        }
    }
}