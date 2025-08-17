<?php
namespace App\Services;

use App\Models\{MasterKaryawan,MailSchedule,MailList,JobTask};
use App\Jobs\{JobEmailBlast};
use Illuminate\Support\Facades\Log;
use Repository;
class EmailBlast {
    public static function sendEmailBlast()
    {
        try {
            // $currentDate = date('Y-m-d', strtotime('-2 days')); //debuging
            $currentDate = date('Y-m-d');
            $currentDay = date('l'); // Get current day in full format (e.g., Monday)
            $currentTime = date('H:i:s');

            $dayMapping = [
                'Senin' => 'Monday',
                'Selasa' => 'Tuesday',
                'Rabu' => 'Wednesday',
                'Kamis' => 'Thursday',
                'Jumat' => 'Friday',
                'Sabtu' => 'Saturday',
                'Minggu' => 'Sunday',
            ];
            $schedules = MailSchedule::get();
            foreach ($schedules as $schedule) {
                    $startDate = $schedule->start_date;
                    $endDate = $schedule->end_date;
                    $days = ($schedule->days != null) ? json_decode($schedule->days, true) : [];
                    $time = $schedule->time;
                    // target email
                    $mailist=MailList::where('id',$schedule->mail_id)->first();
                    $content = Repository::dir('blast_mail_template')->key($mailist->name)->get();
                    if ($currentDate >= $startDate && $currentDate <= $endDate) { //chek current date
                        $filteredDays = array_filter($days, fn($day) => $dayMapping[$day] === $currentDay);
                        $filteredDays = array_values($filteredDays);
                        if (!empty($filteredDays)) { //chek current day
                            if ($currentTime === $time) { //chek current time
                                Log::channel('custom_email_log')->info('preparing to execute job EmailBlast ' . \date('Y-m-d H:i:s'));
                                JobTask::insert([
                                    'job' => 'JobEmailBlast',
                                    'status' => 'processing',
                                    'timestamp' => date('Y-m-d H:i:s')
                                ]);
                                $subject_bersih = preg_replace('/[^\p{L}\p{N}\s!]/u', '', $mailist->subject);
                                $replayto = json_decode($mailist->reply_to, true);
                                dispatch(new JobEmailBlast($mailist->email_to, $subject_bersih , $replayto, $content));
                                JobTask::insert([
                                    'job' => 'JobEmailBlast',
                                    'status' => 'processed',
                                    'timestamp' => date('Y-m-d H:i:s')
                                ]);
                            }
                         }
                }
            }

        } catch (\Exception $e) {
            //throw $th;
            Log::channel('custom_email_log')->error(
                "RenderEmailBlast job failed: {$e->getMessage()} in {$e->getFile()} on line {$e->getLine()}");
            return response()->json([
                'message' => 'Email gagal dikirim',
                'status' => '500',
            ], 500);
        }
    }

    public function prepareSubject($subject) {
        if (!mb_detect_encoding($subject, 'UTF-8', true)) {
            $subject = mb_convert_encoding($subject, 'UTF-8');
        }
        return mb_encode_mimeheader($subject, 'UTF-8', 'B');
    }
}
