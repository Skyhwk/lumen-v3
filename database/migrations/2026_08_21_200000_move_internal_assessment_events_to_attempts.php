<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MoveInternalAssessmentEventsToAttempts extends Migration
{
    public function up()
    {
        if (Schema::hasTable('assessment_internal_attempts') && !Schema::hasColumn('assessment_internal_attempts', 'activity_meta')) {
            Schema::table('assessment_internal_attempts', function (Blueprint $table) {
                $table->json('activity_meta')->nullable()->after('completion_email_sent_at');
            });
        }

        if (Schema::hasTable('assessment_internal_attempts') && Schema::hasTable('assessment_internal_events')) {
            DB::table('assessment_internal_attempts')->orderBy('id')->chunk(100, function ($attempts) {
                foreach ($attempts as $attempt) {
                    $events = DB::table('assessment_internal_events')
                        ->where('assessment_internal_attempt_id', $attempt->id)
                        ->orderBy('created_at')
                        ->get()
                        ->map(function ($event) {
                            return [
                                'session_id' => $event->assessment_internal_session_id,
                                'event' => $event->event,
                                'metadata' => json_decode($event->metadata_json ?: 'null', true),
                                'created_at' => $event->created_at,
                            ];
                        })->values()->all();
                    DB::table('assessment_internal_attempts')->where('id', $attempt->id)->update([
                        'activity_meta' => json_encode($events),
                    ]);
                }
            });
            Schema::drop('assessment_internal_events');
        }
    }

    public function down()
    {
        // Non-destructive: metadata aktivitas tetap disimpan pada attempt.
    }
}
