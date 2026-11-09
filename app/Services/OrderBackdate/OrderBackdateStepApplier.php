<?php

namespace App\Services\OrderBackdate;

use App\Models\OrderBackdateAuditLog;
use App\Models\OrderBackdatePackage;
use App\Models\OrderBackdateStep;
use App\Models\QrDocument;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OrderBackdateStepApplier
{
    public function applyValues(array $items, array $valuesByKey): array
    {
        $applied = [];
        foreach ($items as $item) {
            $key = $item['key'];
            if (!array_key_exists($key, $valuesByKey)) {
                continue;
            }
            $newValue = $valuesByKey[$key];
            $this->writeField($item, $newValue);
            $applied[$key] = $newValue;
        }

        return $applied;
    }

    public function restoreFromSnapshot(array $snapshotItems): void
    {
        foreach ($snapshotItems as $item) {
            $original = $item['value'] ?? null;
            $this->writeField($item, $original);
        }
    }

    private function writeField(array $item, $value): void
    {
        $table = $item['table'];
        $id = $item['id'];
        $column = $item['column'];

        if ($column === 'data.Tanggal_Pengesahan') {
            $qr = QrDocument::find($id);
            if (!$qr) {
                return;
            }
            $payload = json_decode($qr->data ?? '{}', true);
            if (!is_array($payload)) {
                $payload = [];
            }
            if ($value && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)) {
                $value = Carbon::parse($value)->locale('id')->isoFormat('DD MMMM YYYY');
            }
            $payload['Tanggal_Pengesahan'] = $value;
            $qr->data = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $qr->save();

            return;
        }

        DB::table($table)->where('id', $id)->update([$column => $value]);
    }

    public function syncPackageStatus(OrderBackdatePackage $package): void
    {
        $steps = $package->steps()->get();
        $saved = $steps->where('step_status', 'saved')->count();
        $total = count(OrderBackdatePackage::STEP_KEYS);

        if ($saved === 0) {
            $package->package_status = 'draft';
        } elseif ($saved < $total) {
            $package->package_status = 'partial';
        } else {
            $package->package_status = 'completed';
        }

        $package->updated_at = Carbon::now();
        $package->save();
    }

    public function log(
        OrderBackdatePackage $package,
        string $action,
        ?string $stepKey,
        array $meta,
        ?int $actorId,
        ?string $actorName
    ): void {
        OrderBackdateAuditLog::insert([
            'package_id' => $package->id,
            'step_key' => $stepKey,
            'action' => $action,
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'created_at' => Carbon::now(),
        ]);
    }

    public function documentStatusForStep(string $stepKey): string
    {
        return in_array($stepKey, ['quotation', 'order', 'sampling_doc', 'lhp', 'qr'], true)
            ? 'pending'
            : 'not_required';
    }
}
