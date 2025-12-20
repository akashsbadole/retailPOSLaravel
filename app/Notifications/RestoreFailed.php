<?php

namespace App\Notifications;

use App\Models\Backup;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RestoreFailed extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param Backup|null $backup
     * @param \Throwable $exception
     * @return void
     */
    public function __construct(
        protected ?Backup $backup,
        protected \Throwable $exception
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable): array
    {
        $channels = ['database'];
        
        if (config('backup.notifications.channels.mail', true)) {
            $channels[] = 'mail';
        }
        
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return MailMessage
     */
    public function toMail($notifiable): MailMessage
    {
        $backupName = $this->backup?->name ?? 'Unknown Backup';
        $backupDate = $this->backup?->created_at?->format('F j, Y \a\t g:i A') ?? 'Unknown';
        $errorMessage = $this->exception->getMessage();
        
        return (new MailMessage)
            ->subject('Database Restore Failed')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your database restore has failed.')
            ->line('**Restore Details:**')
            ->line("- Backup: {$backupName}")
            ->line("- Created: {$backupDate}")
            ->line("- Attempted at: " . now()->format('F j, Y \a\t g:i A'))
            ->line('**Error Details:**')
            ->line("- Error: {$errorMessage}")
            ->line("- File: " . $this->exception->getFile())
            ->line("- Line: " . $this->exception->getLine())
            ->action('View Backups', route('filament.admin.resources.backups.index'))
            ->line('Please check the backup file and try again. If the problem persists, contact your system administrator.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable): array
    {
        $backupName = $this->backup?->name ?? 'Unknown Backup';
        $errorMessage = $this->exception->getMessage();
        
        return [
            'title' => 'Database Restore Failed',
            'message' => "Database restore from '{$backupName}' has failed.",
            'type' => 'error',
            'backup_id' => $this->backup?->id,
            'backup_name' => $backupName,
            'error_message' => $errorMessage,
            'error_file' => $this->exception->getFile(),
            'error_line' => $this->exception->getLine(),
            'failed_at' => now()->toISOString(),
            'action_url' => route('filament.admin.resources.backups.index'),
        ];
    }
}