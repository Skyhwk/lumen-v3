<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Data\WaMessageTemplateVariants;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed / refresh template WA recruitment + variants.
 *
 * php artisan db:seed --class=WaMessageTemplateSeeder
 *
 * Perilaku:
 * - Pastikan tabel ada (hasTable)
 * - Upsert template by `code`
 * - Ganti ulang semua variant per code (biar isi seeder jadi sumber kebenaran)
 */
class WaMessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('wa_message_templates') || !Schema::hasTable('wa_message_template_variants')) {
            if ($this->command) {
                $this->command->warn('Tabel wa_message_templates / variants belum ada. Jalankan migration dulu.');
            }
            return;
        }

        $now = Carbon::now();
        $variantsMap = WaMessageTemplateVariants::all();

        foreach ($this->templateDefinitions() as $def) {
            $code = $def['code'];
            if (!isset($variantsMap[$code])) {
                continue;
            }

            $existingId = DB::table('wa_message_templates')->where('code', $code)->value('id');

            $payload = [
                'name' => $def['name'],
                'module' => $def['module'],
                'variables' => json_encode($def['variables']),
                'description' => $def['description'],
                'pick_strategy' => 'weighted_random',
                'cooldown_hours' => 72,
                'is_active' => true,
                'updated_at' => $now,
            ];

            if ($existingId) {
                DB::table('wa_message_templates')->where('id', $existingId)->update($payload);
                $templateId = (int) $existingId;
                DB::table('wa_message_template_variants')->where('template_id', $templateId)->delete();
            } else {
                $payload['code'] = $code;
                $payload['created_at'] = $now;
                $templateId = (int) DB::table('wa_message_templates')->insertGetId($payload);
            }

            $rows = [];
            foreach ($variantsMap[$code] as $variant) {
                $rows[] = [
                    'template_id' => $templateId,
                    'label' => $variant['label'],
                    'body' => $variant['body'],
                    'weight' => 1,
                    'is_active' => true,
                    'use_count' => 0,
                    'last_used_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows !== []) {
                DB::table('wa_message_template_variants')->insert($rows);
            }

            if ($this->command) {
                $this->command->info(sprintf(
                    'OK %s -> %d variants',
                    $code,
                    count($rows)
                ));
            }
        }
    }

    private function templateDefinitions(): array
    {
        $vBase = [
            ['key' => 'sapaan', 'label' => 'Sapaan waktu', 'required' => true],
            ['key' => 'saudara', 'label' => 'Saudara / Saudari (dari jenis_kelamin)', 'required' => true],
            ['key' => 'nama', 'label' => 'Nama kandidat', 'required' => true],
            ['key' => 'posisi', 'label' => 'Posisi dilamar', 'required' => true],
        ];

        $vHrd = array_merge($vBase, [
            ['key' => 'bagian', 'label' => 'Nama jabatan/bagian', 'required' => true],
            ['key' => 'hari', 'label' => 'Hari', 'required' => true],
            ['key' => 'tanggal', 'label' => 'Tanggal', 'required' => true],
            ['key' => 'jam', 'label' => 'Jam', 'required' => true],
            ['key' => 'metode', 'label' => 'Online / Offline', 'required' => true],
            ['key' => 'detail_lokasi', 'label' => 'Link GMeet ATAU alamat + catatan', 'required' => true],
            ['key' => 'kode', 'label' => 'Blok kode offline (boleh kosong)', 'required' => false],
        ]);

        $vUser = array_merge($vBase, [
            ['key' => 'waktu', 'label' => 'Waktu interview', 'required' => true],
            ['key' => 'tipe', 'label' => 'Online / Offline', 'required' => true],
            ['key' => 'detail_lokasi', 'label' => 'Link atau ruangan', 'required' => true],
            ['key' => 'instruksi', 'label' => 'Instruksi hadir', 'required' => false],
        ]);

        return [
            [
                'code' => 'assessment_invitation',
                'name' => 'Undangan Assessment',
                'module' => 'assessment',
                'description' => 'GenerateMessageAtsWhatsapp::Assessment',
                'variables' => array_merge($vBase, [
                    ['key' => 'link', 'label' => 'URL assessment', 'required' => true],
                    ['key' => 'masa_berlaku', 'label' => 'Masa berlaku link', 'required' => true],
                ]),
            ],
            [
                'code' => 'interview_hrd_invite',
                'name' => 'Undangan Interview HRD',
                'module' => 'recruitment',
                'description' => 'PassedCandidateSelection ATS + legacy',
                'variables' => $vHrd,
            ],
            [
                'code' => 'interview_hrd_reschedule',
                'name' => 'Reschedule Interview HRD',
                'module' => 'recruitment',
                'description' => 'GenerateMessageWhatsapp::RescheduleHRD',
                'variables' => $vHrd,
            ],
            [
                'code' => 'interview_user_invite',
                'name' => 'Undangan User Interview',
                'module' => 'recruitment',
                'description' => 'UserInterviewScheduleCandidate / PassedHRD',
                'variables' => $vUser,
            ],
            [
                'code' => 'interview_user_reschedule',
                'name' => 'Reschedule User Interview',
                'module' => 'recruitment',
                'description' => 'GenerateMessageWhatsapp::RescheduleUser',
                'variables' => $vUser,
            ],
            [
                'code' => 'complete_profile',
                'name' => 'Lengkapi Data Diri',
                'module' => 'recruitment',
                'description' => 'CompleteProfileCandidate',
                'variables' => array_merge($vBase, [
                    ['key' => 'link', 'label' => 'URL complete profile', 'required' => true],
                ]),
            ],
            [
                'code' => 'candidate_rejection',
                'name' => 'Penolakan Kandidat',
                'module' => 'recruitment',
                'description' => 'RejectedCandidateSelection / RejectedHRD',
                'variables' => [
                    ['key' => 'nama', 'label' => 'Nama kandidat', 'required' => true],
                    ['key' => 'posisi', 'label' => 'Posisi dilamar', 'required' => true],
                    ['key' => 'saudara', 'label' => 'Saudara / Saudari (dari jenis_kelamin)', 'required' => true],
                    ['key' => 'sapaan', 'label' => 'Sapaan (opsional di beberapa variant)', 'required' => false],
                ],
            ],
            [
                'code' => 'salary_offering_letter',
                'name' => 'Salary Offering Letter',
                'module' => 'hrd',
                'description' => 'SalaryOfferingLetter',
                'variables' => array_merge($vBase, [
                    ['key' => 'gaji', 'label' => 'Penawaran gaji', 'required' => true],
                    ['key' => 'email', 'label' => 'Email tujuan PDF', 'required' => false],
                ]),
            ],
            [
                'code' => 'hiring_letter',
                'name' => 'Hiring Letter',
                'module' => 'hrd',
                'description' => 'HiringLetter',
                'variables' => array_merge($vBase, [
                    ['key' => 'gaji', 'label' => 'Gaji pokok', 'required' => true],
                    ['key' => 'tanggal_mulai', 'label' => 'Tanggal mulai kerja', 'required' => true],
                    ['key' => 'email', 'label' => 'Email tujuan PDF', 'required' => false],
                ]),
            ],
            [
                'code' => 'onboarding_start_work',
                'name' => 'Pemberitahuan Mulai Kerja',
                'module' => 'hrd',
                'description' => 'GenerateMessageWhatsapp::PassedOS',
                'variables' => array_merge($vBase, [
                    ['key' => 'hari', 'label' => 'Hari hadir', 'required' => true],
                    ['key' => 'tanggal', 'label' => 'Tanggal mulai', 'required' => true],
                    ['key' => 'jam', 'label' => 'Jam hadir', 'required' => true],
                    ['key' => 'alamat', 'label' => 'Alamat perusahaan', 'required' => true],
                ]),
            ],
        ];
    }
}
