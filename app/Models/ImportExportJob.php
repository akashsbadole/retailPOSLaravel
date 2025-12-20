<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ImportExportJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'resource_type',
        'status',
        'user_id',
        'file_path',
        'total_records',
        'processed_records',
        'failed_records',
        'errors',
        'options',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'errors' => 'array',
        'options' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the user that owns the job.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the job is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the job is processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if the job is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the job is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get the progress percentage.
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_records === 0) {
            return 0;
        }

        return ($this->processed_records / $this->total_records) * 100;
    }

    /**
     * Update the progress of the job.
     */
    public function updateProgress(int $processed, int $total = null): void
    {
        $this->update([
            'processed_records' => $processed,
            'total_records' => $total ?? $this->total_records,
        ]);
    }

    /**
     * Add an error to the job.
     */
    public function addError(int $row, string $field, string $message, string $severity = 'error', $value = null): void
    {
        $errors = $this->errors ?? [];
        $errors[] = [
            'row' => $row,
            'field' => $field,
            'message' => $message,
            'severity' => $severity,
            'value' => $value,
        ];

        $this->update([
            'errors' => $errors,
            'failed_records' => $this->failed_records + 1,
        ]);
    }

    /**
     * Mark the job as started.
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark the job as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the job as failed.
     */
    public function markAsFailed(): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Get the download URL for the file.
     */
    public function getDownloadUrlAttribute(): ?string
    {
        if (!$this->file_path || $this->type !== 'export') {
            return null;
        }

        return route('download.import-export', $this->id);
    }

    /**
     * Get the formatted file size.
     */
    public function getFormattedFileSizeAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }

        $size = \Storage::disk('local')->size($this->file_path);
        return \App\Helpers\ImportExportHelper::formatFileSize($size);
    }

    /**
     * Get the status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'processing' => 'info',
            'completed' => 'success',
            'failed' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Get the duration of the job.
     */
    public function getDurationAttribute(): ?string
    {
        if (!$this->started_at) {
            return null;
        }

        $end = $this->completed_at ?? now();
        $duration = $end->diffInSeconds($this->started_at);

        if ($duration < 60) {
            return $duration . 's';
        }

        $minutes = floor($duration / 60);
        $seconds = $duration % 60;

        return "{$minutes}m {$seconds}s";
    }

    /**
     * Scope to get only pending jobs.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get only processing jobs.
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope to get only completed jobs.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get only failed jobs.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get jobs by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get jobs by resource type.
     */
    public function scopeByResource($query, string $resourceType)
    {
        return $query->where('resource_type', $resourceType);
    }

    /**
     * Scope to get jobs for the current user.
     */
    public function scopeForCurrentUser($query)
    {
        return $query->where('user_id', auth()->id());
    }
}