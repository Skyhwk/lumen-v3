<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterDivisi;
use App\Models\MasterKaryawan;
use App\Models\PmColumn;
use App\Models\PmProject;
use App\Models\PmTask;
use App\Models\PmTaskActivity;
use App\Models\PmTaskComment;
use App\Services\GetBawahan;
use App\Services\Notification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProjectManagementController extends Controller
{
    private static $defaultColumns = [
        ['name' => 'To Do', 'color' => '#3b82f6', 'position' => 0, 'is_done' => false],
        ['name' => 'In Progress', 'color' => '#f59e0b', 'position' => 1, 'is_done' => false],
        ['name' => 'Review', 'color' => '#8b5cf6', 'position' => 2, 'is_done' => false],
        ['name' => 'Done', 'color' => '#22c55e', 'position' => 3, 'is_done' => true],
    ];

    public function board(Request $request)
    {
        if (!$this->tablesReady()) {
            return response()->json(['message' => 'Tabel project management belum ada. Jalankan migration.'], 503);
        }

        $ctx = $this->context();
        if (!$ctx) {
            return response()->json(['message' => 'Data karyawan tidak ditemukan'], 403);
        }

        $project = $this->ensureProject($ctx['division_id'], $ctx['user_id']);
        $visibleIds = $ctx['visible_assignee_ids'];

        $columns = PmColumn::where('project_id', $project->id)
            ->where('is_active', 1)
            ->orderBy('position')
            ->get();

        $tasksQuery = PmTask::where('project_id', $project->id)
            ->where('is_active', 1)
            ->with(['assignee:id,nama_lengkap,image,id_department', 'creator:id,nama_lengkap']);

        if (!$ctx['is_manager']) {
            $tasksQuery->where('assignee_id', $ctx['user_id']);
        } else {
            $tasksQuery->where(function ($q) use ($visibleIds) {
                $q->whereIn('assignee_id', $visibleIds)
                    ->orWhereNull('assignee_id');
            });
        }

        $tasks = $tasksQuery->orderBy('position')->orderBy('id')->get();
        $this->backfillCompletedAt($tasks, $columns);

        $archiveCutoff = Carbon::now()->subDays(2);
        $archivedCount = 0;
        $grouped = [];
        foreach ($columns as $col) {
            $grouped[$col->id] = [];
        }

        foreach ($tasks as $task) {
            $col = $columns->firstWhere('id', $task->column_id);
            $isDoneCol = $col && (bool) $col->is_done;
            $completedAt = $task->completed_at ? Carbon::parse($task->completed_at) : null;
            $isArchived = $isDoneCol && $completedAt && $completedAt->lt($archiveCutoff);

            if ($isArchived) {
                $archivedCount++;
                continue;
            }

            if (!isset($grouped[$task->column_id])) {
                $grouped[$task->column_id] = [];
            }
            $payload = $this->serializeTask($task, $ctx);
            $payload['is_archived'] = false;
            $grouped[$task->column_id][] = $payload;
        }

        $columnsOut = $columns->map(function ($col) use ($grouped) {
            return [
                'id' => $col->id,
                'name' => $col->name,
                'color' => $col->color,
                'position' => $col->position,
                'is_done' => (bool) $col->is_done,
                'tasks' => $grouped[$col->id] ?? [],
            ];
        })->values();

        $doneColumnIds = $columns->where('is_done', 1)->pluck('id')->all();
        $activeTasks = $tasks->filter(function ($t) use ($columns, $archiveCutoff) {
            $col = $columns->firstWhere('id', $t->column_id);
            if (!$col || !(bool) $col->is_done || !$t->completed_at) {
                return true;
            }
            return !Carbon::parse($t->completed_at)->lt($archiveCutoff);
        });

        return response()->json([
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'division_id' => $project->division_id,
                'division_name' => $ctx['division_name'],
            ],
            'me' => [
                'id' => $ctx['user_id'],
                'name' => $ctx['name'],
                'is_manager' => $ctx['is_manager'],
                'grade' => $ctx['grade'],
            ],
            'columns' => $columnsOut,
            'stats' => [
                'total' => $activeTasks->count(),
                'archived' => $archivedCount,
                'mine' => $activeTasks->where('assignee_id', $ctx['user_id'])->count(),
                'unassigned' => $activeTasks->where('assignee_id', null)->count(),
                'overdue' => $activeTasks->filter(function ($t) use ($doneColumnIds) {
                    return $t->due_date
                        && $t->due_date->lt(Carbon::today())
                        && !in_array($t->column_id, $doneColumnIds, true);
                })->count(),
            ],
        ]);
    }

    /**
     * Task Done yang sudah > 2 hari (disembunyikan dari board) — untuk cek ulang.
     */
    public function archivedTasks(Request $request)
    {
        if (!$this->tablesReady()) {
            return response()->json(['message' => 'Tabel project management belum ada. Jalankan migration.'], 503);
        }

        $ctx = $this->context();
        if (!$ctx) {
            return response()->json(['message' => 'Data karyawan tidak ditemukan'], 403);
        }

        $project = $this->ensureProject($ctx['division_id'], $ctx['user_id']);
        $visibleIds = $ctx['visible_assignee_ids'];

        $columns = PmColumn::where('project_id', $project->id)
            ->where('is_active', 1)
            ->get();
        $doneIds = $columns->where('is_done', 1)->pluck('id')->all();
        if (empty($doneIds)) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        $archiveCutoff = Carbon::now()->subDays(2);

        $tasksQuery = PmTask::where('project_id', $project->id)
            ->where('is_active', 1)
            ->whereIn('column_id', $doneIds)
            ->with(['assignee:id,nama_lengkap,image,id_department', 'creator:id,nama_lengkap']);

        if (Schema::hasColumn('pm_tasks', 'completed_at')) {
            $tasksQuery->whereNotNull('completed_at')
                ->where('completed_at', '<', $archiveCutoff);
        } else {
            $tasksQuery->where('updated_at', '<', $archiveCutoff);
        }

        if (!$ctx['is_manager']) {
            $tasksQuery->where('assignee_id', $ctx['user_id']);
        } else {
            $tasksQuery->where(function ($q) use ($visibleIds) {
                $q->whereIn('assignee_id', $visibleIds)
                    ->orWhereNull('assignee_id');
            });
        }

        $q = trim((string) $request->input('q', ''));
        if ($q !== '') {
            $tasksQuery->where(function ($qq) use ($q) {
                $qq->where('title', 'like', '%' . $q . '%')
                    ->orWhere('description', 'like', '%' . $q . '%');
            });
        }

        $tasks = $tasksQuery
            ->orderByDesc(Schema::hasColumn('pm_tasks', 'completed_at') ? 'completed_at' : 'updated_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $this->backfillCompletedAt($tasks, $columns);

        // Setelah backfill, filter ulang yang benar-benar sudah lewat cutoff
        $data = [];
        foreach ($tasks as $task) {
            $completedAt = $task->completed_at ? Carbon::parse($task->completed_at) : null;
            if (!$completedAt || !$completedAt->lt($archiveCutoff)) {
                continue;
            }
            $payload = $this->serializeTask($task, $ctx);
            $payload['is_archived'] = true;
            $payload['column_name'] = optional($columns->firstWhere('id', $task->column_id))->name;
            $data[] = $payload;
        }

        return response()->json([
            'data' => $data,
            'total' => count($data),
        ]);
    }

    public function team(Request $request)
    {
        $ctx = $this->context();
        if (!$ctx) {
            return response()->json(['message' => 'Data karyawan tidak ditemukan'], 403);
        }

        if (!$ctx['is_manager']) {
            return response()->json([
                'data' => [[
                    'id' => $ctx['user_id'],
                    'nama_lengkap' => $ctx['name'],
                    'image' => null,
                ]],
            ]);
        }

        $team = MasterKaryawan::whereIn('id', $ctx['visible_assignee_ids'])
            ->where('is_active', 1)
            ->where('id_department', $ctx['division_id'])
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'image', 'grade']);

        return response()->json(['data' => $team]);
    }

    public function storeTask(Request $request)
    {
        if (!$this->tablesReady()) {
            return response()->json(['message' => 'Tabel belum siap'], 503);
        }

        $ctx = $this->context();
        if (!$ctx) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            return response()->json(['message' => 'Judul task wajib diisi'], 422);
        }

        $project = $this->ensureProject($ctx['division_id'], $ctx['user_id']);
        $columnId = (int) $request->input('column_id');
        $column = PmColumn::where('project_id', $project->id)->where('id', $columnId)->where('is_active', 1)->first();
        if (!$column) {
            $column = PmColumn::where('project_id', $project->id)->where('is_active', 1)->orderBy('position')->first();
        }
        if (!$column) {
            return response()->json(['message' => 'Kolom tidak ditemukan'], 404);
        }

        $assigneeId = $request->input('assignee_id');
        $assigneeId = $assigneeId !== null && $assigneeId !== '' ? (int) $assigneeId : null;

        if ($assigneeId !== null && !$this->canAssignTo($ctx, $assigneeId)) {
            return response()->json(['message' => 'Tidak bisa assign ke karyawan tersebut'], 403);
        }

        if (!$ctx['is_manager']) {
            $assigneeId = $ctx['user_id'];
        }

        $maxPos = (int) PmTask::where('column_id', $column->id)->where('is_active', 1)->max('position');

        DB::beginTransaction();
        try {
            $task = PmTask::create([
                'project_id' => $project->id,
                'column_id' => $column->id,
                'title' => mb_substr($title, 0, 200),
                'description' => $request->input('description'),
                'assignee_id' => $assigneeId,
                'created_by' => $ctx['user_id'],
                'priority' => $this->normalizePriority($request->input('priority')),
                'due_date' => $request->input('due_date') ?: null,
                'position' => $maxPos + 1,
                'completed_at' => ($column->is_done && Schema::hasColumn('pm_tasks', 'completed_at'))
                    ? Carbon::now()
                    : null,
                'is_active' => true,
            ]);

            $this->logActivity($task->id, $ctx['user_id'], 'created', [
                'title' => $task->title,
                'assignee_id' => $assigneeId,
            ]);

            if ($assigneeId && $assigneeId !== $ctx['user_id']) {
                $this->notifyAssignee($assigneeId, $task, $ctx['name']);
            }

            DB::commit();

            $task->load(['assignee:id,nama_lengkap,image', 'creator:id,nama_lengkap']);

            return response()->json([
                'message' => 'Task berhasil dibuat',
                'data' => $this->serializeTask($task, $ctx),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal membuat task: ' . $e->getMessage()], 500);
        }
    }

    public function updateTask(Request $request)
    {
        $ctx = $this->context();
        if (!$ctx) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $task = PmTask::where('id', (int) $request->input('id'))->where('is_active', 1)->first();
        if (!$task || !$this->canAccessTask($ctx, $task)) {
            return response()->json(['message' => 'Task tidak ditemukan / tidak ada akses'], 404);
        }

        if (!$this->canEditTask($ctx, $task)) {
            $msg = $this->isTaskArchived($task)
                ? 'Task Done > 2 hari sudah dikunci. Tidak bisa diedit atau dihapus. Gunakan Riwayat → Kembalikan bila perlu dibuka lagi.'
                : 'Task ini di-assign atasan. Anda hanya bisa komentar / pindah kolom, tidak bisa mengubah detail.';
            return response()->json(['message' => $msg], 403);
        }

        $oldAssignee = $task->assignee_id;
        $title = trim((string) $request->input('title', $task->title));
        if ($title === '') {
            return response()->json(['message' => 'Judul wajib diisi'], 422);
        }

        $assigneeId = $request->has('assignee_id')
            ? ($request->input('assignee_id') !== null && $request->input('assignee_id') !== ''
                ? (int) $request->input('assignee_id')
                : null)
            : $task->assignee_id;

        if ($ctx['is_manager'] && $assigneeId !== null && !$this->canAssignTo($ctx, $assigneeId)) {
            return response()->json(['message' => 'Tidak bisa assign ke karyawan tersebut'], 403);
        }
        if (!$ctx['is_manager']) {
            $assigneeId = $task->assignee_id;
        }

        $task->title = mb_substr($title, 0, 200);
        if ($request->has('description')) {
            $task->description = $request->input('description');
        }
        if ($request->has('priority')) {
            $task->priority = $this->normalizePriority($request->input('priority'));
        }
        if ($request->has('due_date')) {
            $task->due_date = $request->input('due_date') ?: null;
        }

        $fromColumnId = (int) $task->column_id;
        if ($request->filled('column_id')) {
            $newColId = (int) $request->input('column_id');
            $newCol = PmColumn::where('id', $newColId)
                ->where('project_id', $task->project_id)
                ->where('is_active', 1)
                ->first();
            if ($newCol) {
                $task->column_id = $newColId;
                $this->syncCompletedAt($task, $newCol);
            }
        }

        $task->assignee_id = $assigneeId;
        $task->save();

        $this->logActivity($task->id, $ctx['user_id'], 'updated', [
            'assignee_id' => $assigneeId,
        ]);

        if ((int) $task->column_id !== $fromColumnId) {
            $this->logActivity($task->id, $ctx['user_id'], 'moved', [
                'from_column_id' => $fromColumnId,
                'to_column_id' => (int) $task->column_id,
                'from_column' => PmColumn::where('id', $fromColumnId)->value('name'),
                'to_column' => PmColumn::where('id', $task->column_id)->value('name'),
            ]);
        }

        if ($assigneeId && $assigneeId !== $oldAssignee && $assigneeId !== $ctx['user_id']) {
            $this->notifyAssignee($assigneeId, $task, $ctx['name']);
            $this->logActivity($task->id, $ctx['user_id'], 'assigned', [
                'assignee_id' => $assigneeId,
                'from' => $oldAssignee,
            ]);
        }

        $task->load(['assignee:id,nama_lengkap,image', 'creator:id,nama_lengkap']);

        return response()->json([
            'message' => 'Task diperbarui',
            'data' => $this->serializeTask($task, $ctx),
        ]);
    }

    public function moveTask(Request $request)
    {
        $ctx = $this->context();
        if (!$ctx) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $taskId = (int) $request->input('id');
        $columnId = (int) $request->input('column_id');
        $position = (int) $request->input('position', 0);

        $task = PmTask::where('id', $taskId)->where('is_active', 1)->first();
        if (!$task || !$this->canAccessTask($ctx, $task)) {
            return response()->json(['message' => 'Task tidak ditemukan / tidak ada akses'], 404);
        }

        $column = PmColumn::where('id', $columnId)
            ->where('project_id', $task->project_id)
            ->where('is_active', 1)
            ->first();
        if (!$column) {
            return response()->json(['message' => 'Kolom tujuan tidak valid'], 422);
        }

        $fromColumn = $task->column_id;
        $fromCol = PmColumn::where('id', $fromColumn)->first();
        $fromName = $fromCol ? $fromCol->name : null;
        $toName = $column->name;
        $wasDone = $fromCol && (bool) $fromCol->is_done;

        $task->column_id = $columnId;
        $task->position = max(0, $position);

        // Timer arsip hanya untuk stretch Done yang sedang aktif.
        // Keluar Done (mis. balik To Do) → reset; masuk Done lagi → mulai dari sekarang.
        if (Schema::hasColumn('pm_tasks', 'completed_at')) {
            if ($column->is_done) {
                if (!$wasDone || !$task->completed_at) {
                    $task->completed_at = Carbon::now();
                }
            } else {
                $task->completed_at = null;
            }
        }
        $task->save();

        $this->reorderColumn($columnId, $task->id, $position);

        $this->logActivity($task->id, $ctx['user_id'], 'moved', [
            'from_column_id' => $fromColumn,
            'to_column_id' => $columnId,
            'from_column' => $fromName,
            'to_column' => $toName,
            'position' => $position,
        ]);

        $task->load(['assignee:id,nama_lengkap,image', 'creator:id,nama_lengkap']);

        return response()->json([
            'message' => 'Task dipindahkan',
            'data' => $this->serializeTask($task, $ctx),
        ]);
    }

    public function destroyTask(Request $request)
    {
        $ctx = $this->context();
        if (!$ctx) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $task = PmTask::where('id', (int) $request->input('id'))->where('is_active', 1)->first();
        if (!$task || !$this->canAccessTask($ctx, $task)) {
            return response()->json(['message' => 'Task tidak ditemukan / tidak ada akses'], 404);
        }

        if (!$this->canEditTask($ctx, $task)) {
            $msg = $this->isTaskArchived($task)
                ? 'Task Done > 2 hari sudah dikunci. Tidak bisa dihapus.'
                : 'Tidak ada akses menghapus task ini';
            return response()->json(['message' => $msg], 403);
        }

        $task->is_active = false;
        $task->save();
        $this->logActivity($task->id, $ctx['user_id'], 'deleted', []);

        return response()->json(['message' => 'Task dihapus']);
    }

    public function taskDetail(Request $request)
    {
        $ctx = $this->context();
        if (!$ctx) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $task = PmTask::where('id', (int) $request->input('id'))->where('is_active', 1)->first();
        if (!$task || !$this->canAccessTask($ctx, $task)) {
            return response()->json(['message' => 'Task tidak ditemukan / tidak ada akses'], 404);
        }

        $task->load(['assignee:id,nama_lengkap,image', 'creator:id,nama_lengkap']);

        return response()->json([
            'data' => $this->serializeTask($task, $ctx),
            'activities' => $this->listActivities($task->id),
            'comments' => $this->listComments($task->id),
        ]);
    }

    public function storeComment(Request $request)
    {
        $ctx = $this->context();
        if (!$ctx) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!Schema::hasTable('pm_task_comments')) {
            return response()->json(['message' => 'Tabel komentar belum ada. Jalankan migration.'], 503);
        }

        $task = PmTask::where('id', (int) $request->input('id'))->where('is_active', 1)->first();
        if (!$task || !$this->canAccessTask($ctx, $task)) {
            return response()->json(['message' => 'Task tidak ditemukan / tidak ada akses'], 404);
        }

        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            return response()->json(['message' => 'Komentar tidak boleh kosong'], 422);
        }

        $comment = PmTaskComment::create([
            'task_id' => $task->id,
            'user_id' => $ctx['user_id'],
            'body' => $body,
            'is_active' => true,
        ]);

        $this->logActivity($task->id, $ctx['user_id'], 'commented', [
            'comment_id' => $comment->id,
        ]);

        $this->notifyTaskParticipants($task, $ctx['user_id'], $ctx['name'], $body);

        $comment->load('author:id,nama_lengkap,image');

        return response()->json([
            'message' => 'Komentar terkirim',
            'data' => $this->serializeComment($comment),
            'comments' => $this->listComments($task->id),
            'activities' => $this->listActivities($task->id),
        ], 201);
    }

    // ------------------------------------------------------------------

    private function tablesReady()
    {
        return Schema::hasTable('pm_projects')
            && Schema::hasTable('pm_columns')
            && Schema::hasTable('pm_tasks');
    }

    private function context()
    {
        if (!$this->user_id) {
            return null;
        }

        $me = MasterKaryawan::where('id', $this->user_id)->where('is_active', 1)->first();
        if (!$me) {
            return null;
        }

        $team = GetBawahan::where('id', $me->id)->get();
        $visibleIds = $team->pluck('id')->map(function ($id) {
            return (int) $id;
        })->unique()->values()->all();

        $isManager = count($visibleIds) > 1
            || in_array(strtoupper((string) $me->grade), ['MANAGER', 'ASSISTANT MANAGER', 'DIRECTOR', 'DIREKTUR'], true);

        if ($isManager && count($visibleIds) <= 1) {
            // Manager tanpa bawahan di tree: boleh lihat seluruh divisi aktif
            $visibleIds = MasterKaryawan::where('id_department', $me->id_department)
                ->where('is_active', 1)
                ->pluck('id')
                ->map(function ($id) {
                    return (int) $id;
                })
                ->all();
            if (!in_array((int) $me->id, $visibleIds, true)) {
                $visibleIds[] = (int) $me->id;
            }
        }

        $divName = MasterDivisi::where('id', $me->id_department)->value('nama_divisi') ?: 'Divisi';

        return [
            'user_id' => (int) $me->id,
            'name' => $me->nama_lengkap,
            'grade' => $me->grade,
            'division_id' => (int) $me->id_department,
            'division_name' => $divName,
            'is_manager' => $isManager,
            'visible_assignee_ids' => $visibleIds,
        ];
    }

    private function ensureProject($divisionId, $createdBy)
    {
        $divName = MasterDivisi::where('id', $divisionId)->value('nama_divisi') ?: 'Divisi';
        $project = PmProject::where('division_id', $divisionId)->where('is_active', 1)->orderBy('id')->first();

        if ($project) {
            if (PmColumn::where('project_id', $project->id)->where('is_active', 1)->count() === 0) {
                $this->seedColumns($project->id);
            }
            $this->retireBacklogColumn($project->id);
            return $project;
        }

        $project = PmProject::create([
            'division_id' => $divisionId,
            'name' => 'Board ' . $divName,
            'description' => 'Project board divisi ' . $divName,
            'created_by' => $createdBy,
            'is_active' => true,
        ]);
        $this->seedColumns($project->id);

        return $project;
    }

    private function retireBacklogColumn($projectId)
    {
        $backlog = PmColumn::where('project_id', $projectId)
            ->where('name', 'Backlog')
            ->where('is_active', 1)
            ->first();
        if (!$backlog) {
            return;
        }

        $todo = PmColumn::where('project_id', $projectId)
            ->where('name', 'To Do')
            ->where('is_active', 1)
            ->first();

        if ($todo) {
            PmTask::where('column_id', $backlog->id)
                ->where('is_active', 1)
                ->update(['column_id' => $todo->id]);
        }

        $backlog->is_active = false;
        $backlog->save();
    }

    private function seedColumns($projectId)
    {
        $now = Carbon::now();
        foreach (self::$defaultColumns as $col) {
            PmColumn::create([
                'project_id' => $projectId,
                'name' => $col['name'],
                'color' => $col['color'],
                'position' => $col['position'],
                'is_done' => $col['is_done'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function canAssignTo(array $ctx, $assigneeId)
    {
        $assigneeId = (int) $assigneeId;
        if (!in_array($assigneeId, $ctx['visible_assignee_ids'], true)) {
            return false;
        }

        $assignee = MasterKaryawan::where('id', $assigneeId)->where('is_active', 1)->first();
        if (!$assignee) {
            return false;
        }

        return (int) $assignee->id_department === (int) $ctx['division_id'];
    }

    private function canAccessTask(array $ctx, PmTask $task)
    {
        $project = PmProject::find($task->project_id);
        if (!$project || (int) $project->division_id !== (int) $ctx['division_id']) {
            return false;
        }

        if ($ctx['is_manager']) {
            if ($task->assignee_id === null) {
                return true;
            }
            return in_array((int) $task->assignee_id, $ctx['visible_assignee_ids'], true);
        }

        return (int) $task->assignee_id === (int) $ctx['user_id'];
    }

    private function normalizePriority($priority)
    {
        $p = strtolower(trim((string) $priority));
        if (in_array($p, ['low', 'medium', 'high', 'urgent'], true)) {
            return $p;
        }
        return 'medium';
    }

    private function reorderColumn($columnId, $movedTaskId, $newPosition)
    {
        $tasks = PmTask::where('column_id', $columnId)
            ->where('is_active', 1)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $ordered = $tasks->filter(function ($t) use ($movedTaskId) {
            return (int) $t->id !== (int) $movedTaskId;
        })->values();

        $moved = $tasks->firstWhere('id', $movedTaskId);
        if (!$moved) {
            return;
        }

        $pos = max(0, min($newPosition, $ordered->count()));
        $ordered->splice($pos, 0, [$moved]);

        foreach ($ordered as $i => $t) {
            if ((int) $t->position !== $i) {
                PmTask::where('id', $t->id)->update(['position' => $i]);
            }
        }
    }

    private function logActivity($taskId, $actorId, $action, array $meta)
    {
        PmTaskActivity::create([
            'task_id' => $taskId,
            'actor_id' => $actorId,
            'action' => $action,
            'meta' => $meta,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * completed_at = mulai stretch Done aktif.
     * - Di Done tanpa completed_at → set now (mulai hitung)
     * - Tidak di Done tapi masih punya completed_at → clear (sudah dibalik, jangan dihitung)
     */
    private function backfillCompletedAt($tasks, $columns)
    {
        if (!Schema::hasColumn('pm_tasks', 'completed_at')) {
            return;
        }

        $doneIds = $columns->where('is_done', 1)->pluck('id')->map(function ($id) {
            return (int) $id;
        })->all();

        foreach ($tasks as $task) {
            $inDone = in_array((int) $task->column_id, $doneIds, true);
            if ($inDone && !$task->completed_at) {
                $now = Carbon::now();
                PmTask::where('id', $task->id)->update(['completed_at' => $now]);
                $task->completed_at = $now;
            } elseif (!$inDone && $task->completed_at) {
                PmTask::where('id', $task->id)->update(['completed_at' => null]);
                $task->completed_at = null;
            }
        }
    }

    /**
     * Sinkron timer Done: hanya status TERAKHIR yang dihitung.
     * Balik ke To Do / non-Done → completed_at null (walau 5 hari di To Do tetap aman di board).
     */
    private function syncCompletedAt(PmTask $task, PmColumn $column)
    {
        if (!Schema::hasColumn('pm_tasks', 'completed_at')) {
            return;
        }

        if ($column->is_done) {
            if (!$task->completed_at) {
                $task->completed_at = Carbon::now();
            }
        } else {
            $task->completed_at = null;
        }
    }

    /**
     * Task terkunci bila status terakhir masih Done dan completed_at > 2 hari.
     * Creator (dan manager) tidak bisa edit/hapus sampai dikembalikan ke board.
     */
    private function isTaskArchived(PmTask $task)
    {
        if (!Schema::hasColumn('pm_tasks', 'completed_at') || !$task->completed_at) {
            return false;
        }

        $column = PmColumn::where('id', $task->column_id)->where('is_active', 1)->first();
        if (!$column || !(bool) $column->is_done) {
            return false;
        }

        return Carbon::parse($task->completed_at)->lt(Carbon::now()->subDays(2));
    }

    private function canEditTask(array $ctx, PmTask $task)
    {
        // Done > 2 hari: dikunci (termasuk creator)
        if ($this->isTaskArchived($task)) {
            return false;
        }

        // Pembuat atau atasan (manager scope) boleh edit.
        // Assignee yang hanya menerima assign dari atasan: tidak boleh edit detail.
        if ((int) $task->created_by === (int) $ctx['user_id']) {
            return true;
        }

        return !empty($ctx['is_manager']);
    }

    private function notifyAssignee($assigneeId, PmTask $task, $assignerName)
    {
        try {
            Notification::where('id', $assigneeId)
                ->title('Task baru untuk Anda')
                ->message($assignerName . ' menugaskan: "' . $task->title . '"')
                ->url('/task/task')
                ->send();
        } catch (\Exception $e) {
            // notifikasi gagal tidak menggagalkan transaksi utama
        }
    }

    private function notifyTaskParticipants(PmTask $task, $authorId, $authorName, $commentBody)
    {
        $ids = [];
        if ($task->created_by) {
            $ids[] = (int) $task->created_by;
        }
        if ($task->assignee_id) {
            $ids[] = (int) $task->assignee_id;
        }

        if (Schema::hasTable('pm_task_comments')) {
            $commenterIds = PmTaskComment::where('task_id', $task->id)
                ->where('is_active', 1)
                ->pluck('user_id')
                ->map(function ($id) {
                    return (int) $id;
                })
                ->all();
            $ids = array_merge($ids, $commenterIds);
        }

        $ids = array_values(array_unique(array_filter($ids, function ($id) use ($authorId) {
            return (int) $id !== (int) $authorId;
        })));

        if ($ids === []) {
            return;
        }

        $snippet = mb_substr($commentBody, 0, 80);
        try {
            Notification::whereIn('id', $ids)
                ->title('Komentar baru di task')
                ->message($authorName . ' berkomentar di "' . $task->title . '": ' . $snippet)
                ->url('/task/task')
                ->send();
        } catch (\Exception $e) {
            // ignore
        }
    }

    private function listActivities($taskId)
    {
        $rows = PmTaskActivity::where('task_id', $taskId)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $actorIds = $rows->pluck('actor_id')->filter()->unique()->values()->all();
        $actors = MasterKaryawan::whereIn('id', $actorIds)->get(['id', 'nama_lengkap'])->keyBy('id');

        return $rows->map(function ($row) use ($actors) {
            $actor = $actors->get($row->actor_id);
            $meta = is_array($row->meta) ? $row->meta : (json_decode($row->meta, true) ?: []);

            return [
                'id' => $row->id,
                'action' => $row->action,
                'message' => $this->formatActivityMessage($row->action, $meta, $actor ? $actor->nama_lengkap : 'Sistem'),
                'actor_name' => $actor ? $actor->nama_lengkap : null,
                'meta' => $meta,
                'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toDateTimeString() : null,
            ];
        })->values();
    }

    private function formatActivityMessage($action, array $meta, $actorName)
    {
        switch ($action) {
            case 'created':
                return $actorName . ' membuat task';
            case 'updated':
                return $actorName . ' memperbarui detail task';
            case 'assigned':
                return $actorName . ' mengubah assignee';
            case 'moved':
                $from = $meta['from_column'] ?? null;
                $to = $meta['to_column'] ?? null;
                if (!$from && !empty($meta['from_column_id'])) {
                    $from = PmColumn::where('id', $meta['from_column_id'])->value('name')
                        ?: ('#' . $meta['from_column_id']);
                }
                if (!$to && !empty($meta['to_column_id'])) {
                    $to = PmColumn::where('id', $meta['to_column_id'])->value('name')
                        ?: ('#' . $meta['to_column_id']);
                }
                $from = $from ?: '?';
                $to = $to ?: '?';
                return $actorName . ' memindahkan: ' . $from . ' → ' . $to;
            case 'commented':
                return $actorName . ' menambahkan komentar';
            case 'deleted':
                return $actorName . ' menghapus task';
            default:
                return $actorName . ' melakukan aksi: ' . $action;
        }
    }

    private function listComments($taskId)
    {
        if (!Schema::hasTable('pm_task_comments')) {
            return [];
        }

        $rows = PmTaskComment::where('task_id', $taskId)
            ->where('is_active', 1)
            ->with('author:id,nama_lengkap,image')
            ->orderBy('id')
            ->get();

        return $rows->map(function ($c) {
            return $this->serializeComment($c);
        })->values();
    }

    private function serializeComment(PmTaskComment $comment)
    {
        $author = $comment->author;

        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'user_id' => $comment->user_id,
            'author' => $author ? [
                'id' => $author->id,
                'nama_lengkap' => $author->nama_lengkap,
                'image' => $author->image ?? null,
            ] : null,
            'created_at' => $comment->created_at ? $comment->created_at->toDateTimeString() : null,
        ];
    }

    private function serializeTask(PmTask $task, $ctx = null)
    {
        $assignee = $task->assignee;
        $creator = $task->creator;
        $isArchived = $this->isTaskArchived($task);
        $canEdit = false;
        if (is_array($ctx)) {
            $canEdit = $this->canEditTask($ctx, $task);
        }

        return [
            'id' => $task->id,
            'project_id' => $task->project_id,
            'column_id' => $task->column_id,
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority,
            'due_date' => $task->due_date ? $task->due_date->format('Y-m-d') : null,
            'position' => $task->position,
            'assignee_id' => $task->assignee_id,
            'assignee' => $assignee ? [
                'id' => $assignee->id,
                'nama_lengkap' => $assignee->nama_lengkap,
                'image' => $assignee->image ?? null,
            ] : null,
            'created_by' => $task->created_by,
            'creator_name' => $creator ? $creator->nama_lengkap : null,
            'can_edit' => $canEdit,
            'is_archived' => $isArchived,
            'completed_at' => $task->completed_at ? Carbon::parse($task->completed_at)->toDateTimeString() : null,
            'updated_at' => $task->updated_at ? $task->updated_at->toDateTimeString() : null,
        ];
    }
}