<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Backup extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'type',
        'status',
        'format',
        'file_path',
        'disk',
        'file_size',
        'total_records',
        'processed_records',
        'failed_records',
        'tables',
        'options',
        'errors',
        'description',
        'started_at',
        'completed_at',
        'user_id',
        'store_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'file_size' => 'integer',
        'total_records' => 'integer',
        'processed_records' => 'integer',
        'failed_records' => 'integer',
        'tables' => 'array',
        'options' => 'array',
        'errors' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Backup types
     */
    const TYPE_FULL = 'full';
    const TYPE_SELECTIVE = 'selective';
    const TYPE_INCREMENTAL = 'incremental';
    const TYPE_STRUCTURE = 'structure';

    /**
     * Backup statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    /**
     * Backup formats
     */
    const FORMAT_SQL = 'sql';
    const FORMAT_JSON = 'json';

    /**
     * Get the user that created the backup.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the store associated with the backup.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the download URL for the backup file.
     */
    public function getDownloadUrlAttribute(): ?string
    {
        if (!$this->file_path || $this->status !== self::STATUS_COMPLETED) {
            return null;
        }

        return Storage::disk($this->disk)->url($this->file_path);
    }

    /**
     * Get the human-readable file size.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (!$this->file_size) {
            return 'N/A';
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get the progress percentage.
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_records === 0) {
            return 0;
        }

        return min(100, ($this->processed_records / $this->total_records) * 100);
    }

    /**
     * Get the duration of the backup process.
     */
    public function getDurationAttribute(): ?string
    {
        if (!$this->started_at) {
            return null;
        }

        $endTime = $this->completed_at ?: now();
        $duration = $this->started_at->diffInSeconds($endTime);

        if ($duration < 60) {
            return $duration . ' seconds';
        }

        $minutes = floor($duration / 60);
        $seconds = $duration % 60;

        if ($minutes < 60) {
            return $minutes . 'm ' . $seconds . 's';
        }

        $hours = floor($minutes / 60);
        $minutes = $minutes % 60;

        return $hours . 'h ' . $minutes . 'm';
    }

    /**
     * Check if the backup is currently processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Check if the backup has completed successfully.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the backup has failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if the backup file exists.
     */
    public function fileExists(): bool
    {
        if (!$this->file_path) {
            return false;
        }

        return Storage::disk($this->disk)->exists($this->file_path);
    }

    /**
     * Get the backup type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_FULL => 'Full Backup',
            self::TYPE_SELECTIVE => 'Selective Backup',
            self::TYPE_INCREMENTAL => 'Incremental Backup',
            self::TYPE_STRUCTURE => 'Structure Only',
            default => 'Unknown',
        };
    }

    /**
     * Get the backup status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            default => 'Unknown',
        };
    }

    /**
     * Get the backup format label.
     */
    public function getFormatLabelAttribute(): string
    {
        return match ($this->format) {
            self::FORMAT_SQL => 'SQL',
            self::FORMAT_JSON => 'JSON',
            default => 'Unknown',
        };
    }

    /**
     * Get the color for the backup type badge.
     */
    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_FULL => 'danger',
            self::TYPE_SELECTIVE => 'warning',
            self::TYPE_INCREMENTAL => 'info',
            self::TYPE_STRUCTURE => 'gray',
            default => 'secondary',
        };
    }

    /**
     * Get the color for the backup status badge.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_PROCESSING => 'info',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_FAILED => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Mark the backup as processing.
     */
    public function markAsProcessing(): bool
    {
        return $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark the backup as completed.
     */
    public function markAsCompleted(array $data = []): bool
    {
        return $this->update(array_merge([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ], $data));
    }

    /**
     * Mark the backup as failed.
     */
    public function markAsFailed(string $error = null): bool
    {
        $errors = $this->errors ?? [];
        
        if ($error) {
            $errors[] = $error;
        }

        return $this->update([
            'status' => self::STATUS_FAILED,
            'completed_at' => now(),
            'errors' => $errors,
        ]);
    }

    /**
     * Add an error to the backup.
     */
    public function addError(string $error): bool
    {
        $errors = $this->errors ?? [];
        $errors[] = $error;

        return $this->update([
            'errors' => $errors,
            'failed_records' => $this->failed_records + 1,
        ]);
    }

    /**
     * Update the progress of the backup.
     */
    public function updateProgress(int $processed, int $total = null): bool
    {
        $data = ['processed_records' => $processed];

        if ($total !== null) {
            $data['total_records'] = $total;
        }

        return $this->update($data);
    }

    /**
     * Generate a unique backup filename.
     */
    public static function generateFilename(string $type, string $format = 'sql'): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $random = Str::random(6);
        
        return "backup_{$type}_{$timestamp}_{$random}.{$format}";
    }

    /**
     * Scope to get backups by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get backups by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get backups by user.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get backups by store.
     */
    public function scopeByStore($query, int $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * Scope to get completed backups.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope to get failed backups.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope to get processing backups.
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    /**
     * Scope to get recent backups.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Delete the backup file when the model is deleted.
     */
    protected static function booted()
    {
        static::deleting(function ($backup) {
            if ($backup->file_path && $backup->fileExists()) {
                Storage::disk($backup->disk)->delete($backup->file_path);
            }
        });
    }
}