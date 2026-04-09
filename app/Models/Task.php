<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = [
        'org_id', 'title', 'description', 'priority', 'status',
        'due_date', 'document_id', 'created_by_id',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public const STATUSES = [
        1 => 'جديدة',
        2 => 'قيد التنفيذ',
        3 => 'مراجعة',
        4 => 'مكتملة',
        5 => 'ملغاة',
    ];

    public const PRIORITIES = [
        1 => 'منخفضة',
        2 => 'متوسطة',
        3 => 'عالية',
        4 => 'عاجلة',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'document_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function changeStatus(int $newStatus, int $actorId): void
    {
        if ($this->status === $newStatus) return;
        $prev = $this->status;
        $this->status = $newStatus;
        $this->save();

        $this->activities()->create([
            'user_id' => $actorId,
            'action' => 'task.status_changed',
            'metadata' => ['from' => self::STATUSES[$prev] ?? $prev, 'to' => self::STATUSES[$newStatus] ?? $newStatus],
        ]);

        // Notify all assignees (except actor) and creator
        $userIds = $this->assignments()->pluck('user_id')->push($this->created_by_id)
            ->unique()->reject(fn ($id) => $id === $actorId);
        foreach ($userIds as $uid) {
            AppNotification::notify(
                $uid,
                'task.status_changed',
                'تغيرت حالة مهمة',
                "{$this->title} → " . (self::STATUSES[$newStatus] ?? ''),
                ['task_id' => $this->id, 'link' => route('tasks.show', $this->id)]
            );
        }
    }
}
