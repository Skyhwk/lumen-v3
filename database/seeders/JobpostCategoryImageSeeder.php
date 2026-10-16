<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class JobpostCategoryImageSeeder extends Seeder
{
    /**
     * Isi kolom jobpost_categories.image berdasarkan name (exact match). Tidak ada baris = skip.
     *
     * @return array<string, string>
     */
    private function imageByCategoryName(): array
    {
        return [
            'Finance' => 'FAT.png',
            'Accounting' => 'FAT.png',
            'Sales' => 'Sales.png',
            'Sales Support' => 'Sales.png',
            'Sales Supporrt' => 'Sales.png',
            'Marketing' => 'Marketing.png',
            'Sampling' => 'Sampler.png',
            'Analis' => 'Analis.png',
            'K3' => 'TA.png',
            'Technical Laboratorium' => 'TC.png',
            'Tecknical Laboratorium' => 'TC.png',
            'Laboran' => 'TC.png',
            'Legal Corporate' => 'Legal.png',
            'Legal Admin' => 'Legal.png',
            'General Affair' => 'GA.png',
            'Teknologi Informasi' => 'IT.png',
            'HRD' => 'HRD.png',
            'Desain Grafis' => 'Marketing.png',
            'Content Creator' => 'Marketing.png',
        ];
    }

    public function run(): void
    {
        if (!Schema::hasTable('jobpost_categories') || !Schema::hasColumn('jobpost_categories', 'image')) {
            return;
        }

        $now = Carbon::now();

        foreach ($this->imageByCategoryName() as $name => $filename) {
            DB::table('jobpost_categories')
                ->where('name', $name)
                ->update([
                    'image' => $filename,
                    'updated_at' => $now,
                ]);
        }
    }
}
