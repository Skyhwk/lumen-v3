<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class SamplerTrackingScheduleIdentity
{
    /** Infer continuity only from rows participating in one explicit edit. */
    public static function replacements($before, $after, callable $keyFor)
    {
        $beforeById = $before->keyBy('id');
        $afterById = $after->keyBy('id');
        $pairs = [];
        foreach ($beforeById as $id => $row) {
            if ($afterById->has($id)) {
                $pairs[] = [$keyFor($row), $keyFor($afterById->get($id))];
            }
        }

        $removed = $beforeById->diffKeys($afterById)->groupBy($keyFor);
        $added = $afterById->diffKeys($beforeById)->groupBy($keyFor);
        if ($removed->isNotEmpty() && $added->isNotEmpty()) {
            // JadwalServices replaces the selected team's rows on an edit.
            // Multiple unrelated groups cannot safely share their attendance.
            if ($removed->count() !== 1 || $added->count() !== 1) {
                self::ambiguous();
            }
            $pairs[] = [$removed->keys()->first(), $added->keys()->first()];
        }

        $oldKeys = $before->map($keyFor)->unique();
        $newKeys = $after->map($keyFor)->unique();
        $mapping = [];
        foreach ($pairs as [$oldKey, $newKey]) {
            if ($oldKey === $newKey) {
                continue;
            }
            if ($newKeys->contains($oldKey) || $oldKeys->contains($newKey)
                || (isset($mapping[$oldKey]) && $mapping[$oldKey] !== $newKey)
                || (in_array($newKey, $mapping, true) && ($mapping[$oldKey] ?? null) !== $newKey)) {
                self::ambiguous();
            }
            $mapping[$oldKey] = $newKey;
        }

        return $mapping;
    }

    private static function ambiguous()
    {
        throw ValidationException::withMessages([
            'jadwal' => ['Perubahan memecah atau menggabungkan beberapa activity. Ubah satu penugasan utuh agar riwayat tetap sesuai.'],
        ]);
    }
}
