<?php

namespace App\Notifications;

use App\Models\Backup;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BackupFailed extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param Backup $backup
     * @param \Throwable $exception
     * @return void
     */
    public function __construct(
        protected Backup $backup,
        protected \Throwable $exception
    ) {}

    /**
     * Get notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable): array
    {
        $channels = [];
        
        if (config('backup.notifications.channels.database', true)) {
            $channels[] = 'database';
        }
        
        if (config('backup.notifications.channels.mail', true)) {
            $channels[] = 'mail';
        }
        
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Backup Failed')
            ->greeting('Hello ' . $notifiable->name)
            ->line("Your {$this->backup->type_label} backup has failed.")
            ->line("Backup Name: {$this->backup->name}")
            ->line("Error: {$this->exception->getMessage()}")
            ->line("Started At: {$this->backup->started_at}")
            ->line("Failed At: {$this->backup->completed_at}")
            ->action('View Backups', route('filament.admin.resources.backups.index'))
            ->line('Please check the error details and try again.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable): array
    {
        return [
            'title' => 'Backup Failed',
            'message' => "Your {$this->backup->type_label} backup has failed.",
            'backup_id' => $this->backup->id,
            'backup_name' => $this->backup->name,
            'backup_type' => $this->backup->type,
            'error_message' => $this->exception->getMessage(),
            'started_at' => $this->backup->started_at,
            'failed_at' => $this->backup->completed_at,
            'type' => 'error',
        ];
    }
}