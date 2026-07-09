<?php

namespace App\Services;

use App\Models\Jadwal;
use App\Models\OrderHeader;
use App\Models\PersiapanSampelHeader;
use App\Models\SamplerTrackingEvent;
use App\Models\SamplerTrackingMember;
use App\Models\SamplerTrackingSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SamplerTrackingService
{
    protected $columnsByTable = [];

    public function sync($date = null)
    {
        $date = $date ?: Carbon::now()->toDateString();
        $now = Carbon::now();

        $jadwals = Jadwal::where('is_active', true)
            ->whereDate('tanggal', $date)
            ->get();

        $sessions = [];
        $activeTeamKeys = [];

        DB::transaction(function () use ($jadwals, $now, &$sessions, &$activeTeamKeys, $date) {
            $jadwals->groupBy(function ($row) {
                return $this->makeTeamKey($row);
            })->each(function ($rows, $teamKey) use ($now, &$sessions, &$activeTeamKeys) {
                $activeTeamKeys[] = $teamKey;
                $first = $rows->first();
                $orderHeader = OrderHeader::where('no_document', $first->no_quotation)
                    ->where('is_active', true)
                    ->first();

                $session = $this->findSession($teamKey);
                $session->fill($this->onlyExistingColumns($session->getTable(), [
                    'id_sampling' => $first->id_sampling,
                    'parsial' => $first->parsial,
                    'no_quotation' => $first->no_quotation,
                    'no_order' => $orderHeader->no_order ?? null,
                    'tanggal_sampling' => $first->tanggal,
                    'jam_mulai' => $first->jam_mulai,
                    'jam_selesai' => $first->jam_selesai,
                    'durasi' => $first->durasi,
                    'kendaraan' => $first->kendaraan,
                    'driver' => $first->driver,
                    'nama_perusahaan' => $first->nama_perusahaan,
                    'alamat_sampling' => $first->alamat ?? ($orderHeader->alamat_sampling ?? null),
                    'kategori' => $this->normalizeJson($first->kategori),
                    'status' => $session->status ?: 'scheduled',
                    'is_active' => true,
                    'synced_at' => $now,
                ]));
                $session->save();

                $activeMemberIds = [];

                foreach ($rows as $row) {
                    $member = $this->findMember($session->id, $row);
                    $effectiveDuration = $this->firstFilledValue([$row->durasi_personal, $row->durasi]);

                    $movementGroup = $this->makeMovementGroupCodeFromRow($row);

                    $memberValues = $this->onlyExistingColumns($member->getTable(), [
                        'sampler_tracking_session_id' => $session->id,
                        'sampler_id' => $row->userid,
                        'sampler_name' => $row->sampler,
                        'duration' => $row->durasi,
                        'durasi' => $row->durasi,
                        'durasi_personal' => $row->durasi_personal,
                        'effective_duration' => $effectiveDuration,
                        'current_movement_group' => $movementGroup,
                        'is_active' => true,
                    ]);

                    if ($member->exists) {
                        $member->fill($memberValues);
                        $member->save();
                    } else {
                        try {
                            $member = SamplerTrackingMember::create($memberValues);
                        } catch (\Illuminate\Database\QueryException $exception) {
                            $member = $this->findMember($session->id, $row);
                            if (!$member->exists) {
                                throw $exception;
                            }

                            $member->fill($memberValues);
                            $member->save();
                        }
                    }

                    $activeMemberIds[] = $member->id;
                }

                $inactiveUpdate = $this->onlyExistingColumns((new SamplerTrackingMember())->getTable(), ['is_active' => false]);
                if (count($inactiveUpdate) > 0) {
                    SamplerTrackingMember::where('sampler_tracking_session_id', $session->id)
                        ->when(count($activeMemberIds) > 0, function ($query) use ($activeMemberIds) {
                            $query->whereNotIn('id', $activeMemberIds);
                        })
                        ->update($inactiveUpdate);
                }

                $sessions[] = $session;
            });

            $sessionInactiveUpdate = $this->onlyExistingColumns((new SamplerTrackingSession())->getTable(), ['is_active' => false]);
            if (count($sessionInactiveUpdate) > 0) {
                SamplerTrackingSession::where('tanggal_sampling', $date)
                    ->when(count($activeTeamKeys) > 0, function ($query) use ($activeTeamKeys) {
                        $query->whereNotIn('team_key', $activeTeamKeys);
                    })
                    ->update($sessionInactiveUpdate);
            }
        }, 5);

        return collect($sessions);
    }

    public function listByDate($date = null, $samplerId = null, $samplerName = null)
    {
        $date = $date ?: Carbon::now()->toDateString();
        $this->sync($date);

        $hasSamplerFilter = !empty($samplerId) || !empty($samplerName);
        $memberFilter = function ($query) use ($samplerId, $samplerName) {
            if ($samplerId) {
                $query->where('sampler_id', $samplerId);
            }

            if ($samplerName) {
                $query->where('sampler_name', 'like', '%' . $samplerName . '%');
            }
        };

        $sessions = SamplerTrackingSession::with([
                'activeMembers.events' => function ($query) {
                    $query->orderBy('event_at')->orderBy('id');
                },
            ])
            ->where('is_active', true)
            ->whereHas('activeMembers', $memberFilter)
            ->where(function ($query) use ($date, $hasSamplerFilter, $memberFilter) {
                $query->whereDate('tanggal_sampling', $date);

                if ($hasSamplerFilter) {
                    $query->orWhere(function ($ongoingQuery) use ($date, $memberFilter) {
                        $ongoingQuery->whereDate('tanggal_sampling', '<', $date)
                            ->whereHas('activeMembers', function ($memberQuery) use ($date, $memberFilter) {
                                $memberFilter($memberQuery);
                                $memberQuery->whereRaw(
                                    "DATE_ADD(sampler_tracking_sessions.tanggal_sampling, INTERVAL GREATEST(CAST(COALESCE(NULLIF(sampler_tracking_members.effective_duration, ''), NULLIF(sampler_tracking_members.durasi_personal, ''), NULLIF(sampler_tracking_members.duration, ''), 0) AS SIGNED) - 1, 0) DAY) >= ?",
                                    [$date]
                                );
                                $memberQuery->whereDoesntHave('events', function ($eventQuery) {
                                    $eventQuery->where('event_type', 'return');
                                });
                            });
                    });
                }
            })
            ->orderBy('tanggal_sampling')
            ->orderBy('jam_mulai')
            ->orderBy('nama_perusahaan')
            ->get();

        return $this->applyRouteOverrides($sessions, $date, $samplerId, $samplerName);
    }
    public function dataTableByDate($request, $samplerId = null, $samplerName = null)
    {
        $date = $request->tanggal ?: Carbon::now()->toDateString();
        $rows = $this->buildTrackingRows($this->listByDate($date, $samplerId, $samplerName));
        $recordsTotal = $rows->count();

        $rows = $this->filterTrackingRows($rows, $request);
        $recordsFiltered = $rows->count();
        $rows = $this->sortTrackingRows($rows, $request)->values();

        $start = (int) ($request->start ?? 0);
        $length = (int) ($request->length ?? 25);
        if ($length > -1) {
            $rows = $rows->slice($start, $length)->values();
        }

        return [
            'draw' => (int) ($request->draw ?? 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows->values(),
        ];
    }

    protected function buildTrackingRows($sessions)
    {
        $sessionsByMember = collect();

        foreach ($sessions as $session) {
            foreach ($session->activeMembers as $member) {
                $sampler = $member->sampler_name ?: ($member->sampler_id ?: '-');
                $memberKey = $member->sampler_id ?: $sampler;
                $date = $session->tanggal_sampling ?: '-';
                $key = $date . '|' . $memberKey;

                if (!$sessionsByMember->has($key)) {
                    $sessionsByMember->put($key, [
                        'group_key' => $key,
                        'date' => $date,
                        'sampler' => $sampler,
                        'member_key' => $memberKey,
                        'session' => $session,
                        'member' => $member,
                        'sessions' => collect(),
                        'members' => collect(),
                        'events' => collect(),
                        'no_orders' => collect(),
                        'perusahaan' => collect(),
                        'durations' => collect(),
                        'movement_groups' => collect(),
                        'statuses' => collect(),
                        'jam_mulai' => null,
                        'jam_selesai' => null,
                    ]);
                }

                $item = $sessionsByMember->get($key);
                $item['sessions']->push($session);
                $item['members']->push($member);
                $item['events'] = $item['events']->merge($member->events ?: collect());
                $item['no_orders']->push($session->no_order ?: ($session->no_quotation ?: '-'));
                $item['perusahaan']->push($session->nama_perusahaan ?: '-');
                $item['durations']->push($this->durationLabel($this->firstFilledValue([$member->effective_duration, $member->durasi_personal, $member->duration, $member->durasi])));
                $item['movement_groups']->push($member->current_movement_group ?: '-');
                $item['statuses']->push($session->status ?: '-');

                if ($session->jam_mulai && (!$item['jam_mulai'] || $session->jam_mulai < $item['jam_mulai'])) {
                    $item['jam_mulai'] = $session->jam_mulai;
                }

                if ($session->jam_selesai && (!$item['jam_selesai'] || $session->jam_selesai > $item['jam_selesai'])) {
                    $item['jam_selesai'] = $session->jam_selesai;
                }

                $sessionsByMember->put($key, $item);
            }
        }

        return $sessionsByMember->values()->map(function ($row) {
            $noOrders = $this->uniqueValues($row['no_orders']);
            $perusahaan = $this->uniqueValues($row['perusahaan']);
            $durations = $this->uniqueValues($row['durations']);
            $statuses = $this->uniqueValues($row['statuses']);
            $movementGroups = $this->uniqueValues($row['movement_groups']);
            $lastEvent = $this->latestEvent($row['events']);
            $sampler = $row['sampler'] ?: '-';

            return [
                'row_id' => $row['date'] . '-' . $row['member_key'],
                'group_key' => $row['group_key'],
                'session' => $row['session'],
                'member' => $row['member'],
                'sessions' => $row['sessions']->values(),
                'members' => $row['members']->unique('id')->values(),
                'events' => $row['events']->values(),
                'tanggal_sampling' => $row['date'],
                'jam' => ($row['jam_mulai'] ?: '-') . ' - ' . ($row['jam_selesai'] ?: '-'),
                'jam_mulai' => $row['jam_mulai'],
                'jam_selesai' => $row['jam_selesai'],
                'no_order' => count($noOrders) > 0 ? implode(', ', $noOrders) : '-',
                'perusahaan_list' => $perusahaan,
                'nama_perusahaan' => count($perusahaan) > 0 ? implode(', ', $perusahaan) : '-',
                'sampler_list' => [$sampler],
                'sampler' => $sampler,
                'durasi' => count($durations) > 0 ? implode(', ', $durations) : '-',
                'movement_group' => $this->teamMovementCode($row['date'], $row['group_key']),
                'internal_movement_groups' => $movementGroups,
                'total_event' => $row['events']->count(),
                'last_event' => $lastEvent ? (($lastEvent->event_type ?: '-') . ' - ' . ($lastEvent->event_at ?: '-')) : '-',
                'status' => count($statuses) > 0 ? implode(', ', $statuses) : '-',
            ];
        });
    }
    protected function filterTrackingRows($rows, $request)
    {
        $globalSearch = strtolower(trim($request->input('search.value', '')));
        $columns = $request->input('columns', []);

        return $rows->filter(function ($row) use ($globalSearch, $columns) {
            $searchText = strtolower(implode(' ', [
                $row['tanggal_sampling'] ?? '',
                $row['jam'] ?? '',
                $row['no_order'] ?? '',
                $row['nama_perusahaan'] ?? '',
                $row['sampler'] ?? '',
                $row['durasi'] ?? '',
                $row['movement_group'] ?? '',
                $row['last_event'] ?? '',
                $row['status'] ?? '',
            ]));

            if ($globalSearch && strpos($searchText, $globalSearch) === false) {
                return false;
            }

            foreach ($columns as $column) {
                $columnSearch = strtolower(trim($column['search']['value'] ?? ''));
                $data = $column['data'] ?? null;

                if (!$columnSearch || !$data || !array_key_exists($data, $row)) {
                    continue;
                }

                $value = strtolower(is_array($row[$data]) ? implode(' ', $row[$data]) : (string) $row[$data]);
                if (strpos($value, $columnSearch) === false) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    protected function sortTrackingRows($rows, $request)
    {
        $order = $request->input('order.0');
        $columns = $request->input('columns', []);

        if (!$order) {
            return $rows->sortBy(function ($row) {
                return ($row['jam_mulai'] ?: '') . '|' . ($row['nama_perusahaan'] ?: '');
            });
        }

        $columnIndex = (int) ($order['column'] ?? 0);
        $direction = strtolower($order['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $data = $columns[$columnIndex]['data'] ?? 'tanggal_sampling';

        return $direction === 'desc'
            ? $rows->sortByDesc(function ($row) use ($data) { return $row[$data] ?? ''; })
            : $rows->sortBy(function ($row) use ($data) { return $row[$data] ?? ''; });
    }

    protected function uniqueValues($values)
    {
        return collect($values)->filter(function ($value) {
                return $value !== null && $value !== '' && $value !== '-';
            })
            ->unique()
            ->values()
            ->all();
    }

    protected function latestEvent($events)
    {
        return collect($events)->sortByDesc(function ($event) {
            return $event->event_at ? strtotime($event->event_at) : 0;
        })->first();
    }

    protected function firstFilledValue(array $values)
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }
    protected function durationLabel($value)
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (!is_numeric($value)) {
            return $value;
        }

        $numberValue = (int) $value;
        if ($numberValue === 0) {
            return 'Sesaat';
        }

        if ($numberValue === 1) {
            return '8 Jam';
        }

        return ($numberValue - 1) . ' x 24 Jam';
    }

    protected function teamRouteKey($date, $sessions)
    {
        $route = collect($sessions)->map(function ($session) {
                return implode('~', [
                    $session->jam_mulai ?: '-',
                    $session->jam_selesai ?: '-',
                    $session->no_order ?: ($session->no_quotation ?: '-'),
                    $session->nama_perusahaan ?: '-',
                ]);
            })
            ->sort()
            ->values()
            ->implode('|');

        return ($date ?: '-') . '|' . $route;
    }

    protected function teamMovementCode($date, $groupKey)
    {
        $compactDate = $date ? substr(str_replace('-', '', $date), 2) : Carbon::now()->format('ymd');

        return 'TRK-' . $compactDate . '-' . strtoupper(substr(sha1($groupKey), 0, 8));
    }
    public function storeEvent(array $payload)
    {
        return DB::transaction(function () use ($payload) {
            $member = SamplerTrackingMember::with('session')
                ->where('id', $payload['member_id'])
                ->where('is_active', true)
                ->firstOrFail();

            $eventType = $payload['event_type'];
            $movementGroup = $member->current_movement_group ?: $this->makeMovementGroupCode($member->session);

            $members = SamplerTrackingMember::where('sampler_tracking_session_id', $member->sampler_tracking_session_id)
                ->where('is_active', true)
                ->when($eventType === 'checkout', function ($query) use ($member) {
                    $query->where('effective_duration', $member->effective_duration);
                })
                ->get();

            $basWarning = $eventType === 'checkout' ? $this->checkoutBasWarning($member->id) : null;
            $forceBasCheckout = filter_var($payload['force_bas_checkout'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $events = [];
            $eventModel = new SamplerTrackingEvent();
            $photos = $this->storePhotos($payload['photos'] ?? null);

            if (count($photos) === 0 && !empty($payload['photo'])) {
                $singlePhoto = $this->storePhoto($payload['photo']);
                if ($singlePhoto) {
                    $photos[] = $singlePhoto;
                }
            }

            $photo = $photos[0] ?? null;
            $latitude = $this->normalizeCoordinate($payload['latitude'] ?? $payload['lat'] ?? null);
            $longitude = $this->normalizeCoordinate($payload['longitude'] ?? $payload['longi'] ?? $payload['long'] ?? null);

            foreach ($members as $targetMember) {
                $events[] = SamplerTrackingEvent::create($this->onlyExistingColumns($eventModel->getTable(), [
                    'sampler_tracking_session_id' => $targetMember->sampler_tracking_session_id,
                    'sampler_tracking_member_id' => $targetMember->id,
                    'triggered_by_member_id' => $member->id,
                    'event_type' => $eventType,
                    'movement_group' => $movementGroup,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'photo' => $photo,
                    'photos' => count($photos) > 0 ? json_encode($photos) : null,
                    'note' => $payload['note'] ?? null,
                    'vehicle_plate' => $payload['vehicle_plate'] ?? null,
                    'bas_not_completed' => $basWarning ? 1 : 0,
                    'bas_forced_checkout' => ($basWarning && $forceBasCheckout) ? 1 : 0,
                    'bas_warning_message' => $basWarning['message'] ?? null,
                    'is_auto' => $targetMember->id !== $member->id,
                    'sequence_no' => $this->nextSequence($targetMember->id),
                    'event_at' => $payload['event_at'] ?? Carbon::now(),
                ]));
            }

            return collect($events);
        });
    }

    protected function normalizeCoordinate($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }

        return is_numeric($value) ? $value : null;
    }

    public function checkoutBasWarning($memberId)
    {
        $member = SamplerTrackingMember::with('session')
            ->where('id', $memberId)
            ->where('is_active', true)
            ->first();

        if (!$member || !$member->session) {
            return null;
        }

        $session = $member->session;
        $query = PersiapanSampelHeader::where('is_active', true)
            ->whereDate('tanggal_sampling', $session->tanggal_sampling);

        if ($session->no_order) {
            $query->where('no_order', $session->no_order);
        } elseif ($session->no_quotation) {
            $query->where('no_quotation', $session->no_quotation);
        }

        $table = (new PersiapanSampelHeader())->getTable();
        if ($member->sampler_name && $this->hasColumn($table, 'sampler_jadwal')) {
            $query->where('sampler_jadwal', 'like', '%' . $member->sampler_name . '%');
        }

        $headers = $query->get();
        $hasCompletedBas = $headers->contains(function ($header) {
            return $this->isBasDocumentFilled($header->detail_bas_documents);
        });

        if ($hasCompletedBas) {
            return null;
        }

        return [
            'message' => 'BAS untuk lokasi ini belum selesai. Kamu tetap bisa checkout, tapi akan tercatat sebagai checkout paksa tanpa BAS.',
            'no_order' => $session->no_order,
            'tanggal_sampling' => $session->tanggal_sampling,
            'sampler' => $member->sampler_name,
        ];
    }

    protected function isBasDocumentFilled($value)
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '' || $trimmed === '[]' || strtolower($trimmed) === 'null') {
                return false;
            }

            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return count($decoded) > 0;
            }

            return true;
        }

        if (is_array($value)) {
            return count($value) > 0;
        }

        return !empty($value);
    }

    public function updateMovementGroup(array $memberIds, $movementGroup = null)
    {
        $movementGroup = $movementGroup ?: ('TRK-' . Carbon::now()->format('ymd') . '-GRP-' . mt_rand(100, 999));
        $member = new SamplerTrackingMember();
        $movementUpdate = $this->onlyExistingColumns($member->getTable(), ['current_movement_group' => $movementGroup]);

        if (count($movementUpdate) > 0) {
            SamplerTrackingMember::whereIn('id', $memberIds)
                ->where('is_active', true)
                ->update($movementUpdate);
        }

        return $movementGroup;
    }

    public function updateRouteOrder(array $payload, $actorName = null)
    {
        $table = 'sampler_tracking_route_overrides';
        if (!Schema::hasTable($table)) {
            throw new \Exception('Tabel sampler_tracking_route_overrides belum ada.');
        }

        $date = $payload['tanggal'] ?? Carbon::now()->toDateString();
        $samplerId = $payload['sampler_id'] ?? null;
        $samplerName = $payload['sampler_name'] ?? $actorName;
        $samplerKey = $this->samplerRouteKey($samplerId, $samplerName);
        $reason = trim($payload['reason'] ?? '');
        $items = collect($payload['items'] ?? [])->values();
        $now = Carbon::now();

        if (!$samplerKey || $items->isEmpty() || $reason === '') {
            throw new \Exception('Urutan tujuan dan keterangan wajib diisi.');
        }

        DB::transaction(function () use ($table, $date, $samplerKey, $samplerId, $samplerName, $reason, $items, $now, $actorName) {
            DB::table($table)
                ->where('tanggal_sampling', $date)
                ->where('sampler_key', $samplerKey)
                ->update([
                    'is_active' => 0,
                    'updated_by' => $actorName,
                    'updated_at' => $now,
                ]);

            foreach ($items as $index => $item) {
                $sessionId = $item['session_id'] ?? null;
                if (!$sessionId) {
                    continue;
                }

                $keys = [
                    'tanggal_sampling' => $date,
                    'sampler_key' => $samplerKey,
                    'sampler_tracking_session_id' => $sessionId,
                ];

                $values = [
                    'sampler_id' => $samplerId,
                    'sampler_name' => $samplerName,
                    'route_order' => (int) ($item['route_order'] ?? ($index + 1)),
                    'reason' => $reason,
                    'is_active' => 1,
                    'updated_by' => $actorName,
                    'updated_at' => $now,
                ];

                $existing = DB::table($table)->where($keys)->first();
                if ($existing) {
                    DB::table($table)->where('id', $existing->id)->update($values);
                    continue;
                }

                DB::table($table)->insert(array_merge($keys, $values, [
                    'created_by' => $actorName,
                    'created_at' => $now,
                ]));
            }
        }, 5);

        return $this->listByDate($date, $samplerId, $samplerName);
    }

    protected function applyRouteOverrides($sessions, $date, $samplerId = null, $samplerName = null)
    {
        $table = 'sampler_tracking_route_overrides';
        $samplerKey = $this->samplerRouteKey($samplerId, $samplerName);

        if (!$samplerKey || !Schema::hasTable($table)) {
            return $sessions;
        }

        $orders = DB::table($table)
            ->where('tanggal_sampling', $date)
            ->where('sampler_key', $samplerKey)
            ->where('is_active', 1)
            ->pluck('route_order', 'sampler_tracking_session_id');

        if ($orders->isEmpty()) {
            return $sessions;
        }

        return $sessions->sortBy(function ($session) use ($orders) {
            $order = $orders[$session->id] ?? 999999;

            return str_pad($order, 6, '0', STR_PAD_LEFT)
                . '|' . ($session->jam_mulai ?: '')
                . '|' . ($session->nama_perusahaan ?: '')
                . '|' . str_pad($session->id, 10, '0', STR_PAD_LEFT);
        })->values();
    }

    protected function samplerRouteKey($samplerId = null, $samplerName = null)
    {
        if ($samplerId) {
            return (string) $samplerId;
        }

        return $samplerName ? trim((string) $samplerName) : null;
    }
    protected function makeMovementGroupCode($session, $suffix = null)
    {
        $date = $session && $session->tanggal_sampling
            ? Carbon::parse($session->tanggal_sampling)->format('ymd')
            : Carbon::now()->format('ymd');

        $sequence = $session && $session->id
            ? str_pad($session->id, 4, '0', STR_PAD_LEFT)
            : mt_rand(1000, 9999);

        $code = 'TRK-' . $date . '-' . $sequence;

        return $suffix ? $code . '-' . $suffix . '-' . mt_rand(100, 999) : $code;
    }

    protected function makeMovementGroupCodeFromRow($row)
    {
        $date = $row && $row->tanggal
            ? Carbon::parse($row->tanggal)->format('ymd')
            : Carbon::now()->format('ymd');

        $samplerKey = $row && ($row->userid || $row->sampler)
            ? ($row->userid ?: $row->sampler)
            : 'sampler-null';

        return 'TRK-' . $date . '-' . strtoupper(substr(sha1($date . '|' . $samplerKey), 0, 8));
    }
    protected function storePhotos($photos)
    {
        if (!$photos) {
            return [];
        }

        if (is_string($photos)) {
            $decoded = json_decode($photos, true);
            $photos = json_last_error() === JSON_ERROR_NONE ? $decoded : [$photos];
        }

        if (!is_array($photos)) {
            return [];
        }

        $stored = [];
        foreach ($photos as $photo) {
            $path = $this->storePhoto($photo);
            if ($path) {
                $stored[] = $path;
            }
        }

        return $stored;
    }

    protected function storePhoto($photo)
    {
        if (!$photo || strpos($photo, 'data:image') !== 0) {
            return $photo;
        }

        if (strpos($photo, ',') === false) {
            return null;
        }

        [$meta, $data] = explode(',', $photo, 2);
        $extension = strpos($meta, 'image/png') !== false ? 'png' : 'jpg';
        $directory = public_path('sampler_tracking');

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $fileName = 'tracking_' . Carbon::now()->format('YmdHis') . '_' . uniqid() . '.' . $extension;
        $path = $directory . DIRECTORY_SEPARATOR . $fileName;

        file_put_contents($path, base64_decode($data));

        return 'sampler_tracking/' . $fileName;
    }
    protected function onlyExistingColumns($table, array $values)
    {
        if (!isset($this->columnsByTable[$table])) {
            $this->columnsByTable[$table] = Schema::getColumnListing($table);
        }

        return array_intersect_key($values, array_flip($this->columnsByTable[$table]));
    }

    protected function hasColumn($table, $column)
    {
        if (!isset($this->columnsByTable[$table])) {
            $this->columnsByTable[$table] = Schema::getColumnListing($table);
        }

        return in_array($column, $this->columnsByTable[$table], true);
    }

    protected function makeTeamKey($row)
    {
        $sourceKey = implode('|', [
            $row->id_sampling ?: 'sampling-null',
            $row->parsial ?: 'parsial-null',
            $row->no_quotation ?: 'qt-null',
            $row->tanggal ?: 'date-null',
            $row->jam_mulai ?: 'start-null',
            $row->jam_selesai ?: 'end-null',
            $row->kendaraan ?: 'vehicle-null',
            $row->id_cabang ?: 'branch-null',
        ]);

        return sha1($sourceKey);
    }
    protected function findSession($teamKey)
    {
        $sessions = SamplerTrackingSession::withCount('events')
            ->where('team_key', $teamKey)
            ->orderByDesc('events_count')
            ->orderBy('id')
            ->get();

        if ($sessions->isEmpty()) {
            $session = new SamplerTrackingSession();
            $session->team_key = $teamKey;

            return $session;
        }

        $keeper = $sessions->first();
        $duplicates = $sessions->where('id', '!=', $keeper->id);

        foreach ($duplicates as $duplicate) {
            $this->mergeDuplicateSession($keeper, $duplicate);
        }

        return $keeper;
    }

    protected function mergeDuplicateSession($keeper, $duplicate)
    {
        DB::transaction(function () use ($keeper, $duplicate) {
            $duplicateMembers = SamplerTrackingMember::where('sampler_tracking_session_id', $duplicate->id)->get();

            foreach ($duplicateMembers as $duplicateMember) {
                $targetMember = SamplerTrackingMember::where('sampler_tracking_session_id', $keeper->id)
                    ->when($duplicateMember->sampler_id, function ($query) use ($duplicateMember) {
                        $query->where('sampler_id', $duplicateMember->sampler_id);
                    }, function ($query) use ($duplicateMember) {
                        $query->where('sampler_name', $duplicateMember->sampler_name);
                    })
                    ->first();

                if ($targetMember) {
                    SamplerTrackingEvent::where('sampler_tracking_member_id', $duplicateMember->id)
                        ->update([
                            'sampler_tracking_session_id' => $keeper->id,
                            'sampler_tracking_member_id' => $targetMember->id,
                        ]);

                    $duplicateMember->fill($this->onlyExistingColumns($duplicateMember->getTable(), ['is_active' => false]));
                    $duplicateMember->save();
                } else {
                    $duplicateMember->sampler_tracking_session_id = $keeper->id;
                    $duplicateMember->save();

                    SamplerTrackingEvent::where('sampler_tracking_member_id', $duplicateMember->id)
                        ->update(['sampler_tracking_session_id' => $keeper->id]);
                }
            }

            SamplerTrackingEvent::where('sampler_tracking_session_id', $duplicate->id)
                ->update(['sampler_tracking_session_id' => $keeper->id]);

            $duplicate->fill($this->onlyExistingColumns($duplicate->getTable(), ['is_active' => false]));
            $duplicate->save();
        });
    }
    protected function findMember($sessionId, $row)
    {
        $query = SamplerTrackingMember::where('sampler_tracking_session_id', $sessionId);

        if ($row->userid) {
            return $query->where('sampler_id', $row->userid)->first() ?: new SamplerTrackingMember();
        }

        return $query->where('sampler_name', $row->sampler)->first() ?: new SamplerTrackingMember();
    }

    protected function normalizeJson($value)
    {
        if (!$value) {
            return null;
        }

        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE ? $value : json_encode($value);
    }

    protected function nextSequence($memberId)
    {
        return ((int) SamplerTrackingEvent::where('sampler_tracking_member_id', $memberId)->max('sequence_no')) + 1;
    }
}



