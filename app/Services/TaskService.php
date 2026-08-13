<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Task status transitions. Pulled out of TaskController::update() so the board
 * (BoardService) can drive the same status side effects — started_at,
 * completed_at, progress_percent — that a form submission produces, rather than
 * writing task_status_id directly and letting those fields drift out of sync.
 */
class TaskService
{
    public function __construct(
        private AuditLogger $audit,
        private NotificationService $notifications,
    ) {
    }

    public function changeStatus(Task $task, int $statusId, User $actor, ?string $note = null): Task
    {
        return DB::connection('oracle')->transaction(function () use ($task, $statusId, $actor, $note) {
            $from   = $task->status;
            $to     = TaskStatus::findOrFail($statusId);
            $update = ['task_status_id' => $to->id, 'updated_by' => $actor->id];

            if ($to->is_completed_state && $task->completed_at === null) {
                $update['completed_at']     = now();
                $update['completed_by']     = $actor->id;
                $update['progress_percent'] = 100;
            }

            if ($to->code === TaskStatus::IN_PROGRESS && $task->started_at === null) {
                $update['started_at'] = now();
            }

            $task->update($update);

            $this->audit->log(
                'STATUS_CHANGE',
                'TASK',
                $task->id,
                "Task {$task->task_no}: {$from?->name} -> {$to->name}" . ($note ? " ({$note})" : ''),
                ['status' => $from?->name],
                ['status' => $to->name]
            );

            if ($task->assigned_to) {
                $this->notifications->notify(
                    (int) $task->assigned_to,
                    "Task {$task->task_no} is now {$to->name}",
                    $task->title,
                    'TASK',
                    $task->id,
                    route('tasks.show', $task->id)
                );
            }

            return $task->refresh();
        });
    }
}
