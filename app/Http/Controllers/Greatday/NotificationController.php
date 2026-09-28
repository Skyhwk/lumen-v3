<?php

namespace App\Http\Controllers\Greatday;

use App\Support\Greatday\GreatdayAppData;
use App\Support\Greatday\NotificationCopy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /**
     * @param  object|array<string, mixed>  $notification
     * @return array<string, mixed>
     */
    protected function mapNotificationForApi($notification): array
    {
        $row = is_object($notification) && method_exists($notification, 'toArray')
            ? $notification->toArray()
            : (array) $notification;

        $extra = json_decode($row['extra_data'] ?? '{}', true);
        if (!is_array($extra)) {
            $extra = [];
        }

        $title = (string) ($row['title'] ?? '');
        $body = (string) ($row['body'] ?? '');

        foreach (['url', 'link'] as $key) {
            if (!empty($extra[$key]) && is_string($extra[$key])) {
                $extra[$key] = NotificationCopy::normalizeAppUrl($extra[$key], $title, $body);
            }
        }

        $row['extra_data'] = $extra;

        return $row;
    }
    public function index(Request $request)
    {
        $employeeId = $this->user_id;

        $allRead = GreatdayAppData::notificationQuery()->where('user_id', $employeeId)
            ->where('is_seen', false)
            ->doesntExist();

        $perPage = (int) $request->input('per_page', 10);
        $page = (int) $request->input('page', 1);
        $search = $request->input('search');

        $query = GreatdayAppData::notificationQuery()->where('user_id', $employeeId);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        $data = $query->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = collect($data->items())
            ->map(fn ($item) => $this->mapNotificationForApi($item))
            ->values()
            ->all();

        return response()->json([
            'data' => $items,
            'pagination' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'has_more' => $data->hasMorePages(),
            ],
            'all_read' => $allRead,
        ], 200);
    }

    public function read(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->mode == 'all') {
                GreatdayAppData::notificationQuery()->where('user_id', $this->user_id)
                    ->update(['is_seen' => true]);
            } else {
                GreatdayAppData::notificationQuery()->where('id', $request->id)
                    ->where('user_id', $this->user_id)
                    ->update(['is_seen' => true]);
            }

            DB::commit();

            return response()->json(['message' => 'Successfully read notification'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function latestUnread(Request $request)
    {
        $data = GreatdayAppData::notificationQuery()->where('user_id', $this->user_id)
            ->where('is_seen', false)
            ->orderByDesc('id')
            ->limit(3)
            ->get();

        $items = $data->map(fn ($item) => $this->mapNotificationForApi($item))->values()->all();

        return response()->json(['data' => $items], 200);
    }
}
