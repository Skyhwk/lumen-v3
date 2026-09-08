<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class JobpostCategorySeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('jobpost_categories')) {
            return;
        }

        $now = now();
        foreach ([
            'Finance', 'Accounting', 'Sales', 'Sales Support', 'Marketing', 'Sampling',
            'Analis', 'K3', 'Technical Laboratorium', 'Laboran', 'Legal Corporate',
            'Legal Admin', 'General Affair', 'Teknologi Informasi', 'HRD',
            'Desain Grafis', 'Content Creator',
        ] as $name) {
            DB::table('jobpost_categories')->updateOrInsert(
                ['name' => $name],
                ['is_active' => 1, 'updated_at' => $now, 'created_at' => $now]
            );
        }
    }
}
