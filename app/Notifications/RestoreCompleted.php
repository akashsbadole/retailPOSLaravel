<?php

namespace App\Notifications;

use App\Models\Backup;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RestoreCompleted extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param Backup $backup
     * @param array $options
     * @return void
     */
    public function __construct(
        protected Backup $backup,
        protected array $options = []
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
        $backupDate = $this->backup->created_at->format('F j, Y \a\t g:i A');
        $backupSize = $this->backup->formatted_file_size;
        $backupType = $this->backup->type_label;
        
        return (new MailMessage)
            ->subject('Database Restore Completed Successfully')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your database restore has been completed successfully.')
            ->line('**Restore Details:**')
            ->line("- Backup: {$this->backup->name}")
            ->line("- Type: {$backupType}")
            ->line("- Size: {$backupSize}")
            ->line("- Created: {$backupDate}")
            ->line("- Restored at: " . now()->format('F j, Y \a\t g:i A'))
            ->line('**Restore Options:**')
            ->line("- Create Restore Point: " . ($this->options['create_restore_point'] ?? true ? 'Yes' : 'No'))
            ->line("- Truncate Tables: " . ($this->options['truncate_tables'] ?? true ? 'Yes' : 'No'))
            ->line("- Disable Foreign Keys: " . ($this->options['disable_foreign_keys'] ?? true ? 'Yes' : 'No'))
            ->action('View Backups', route('filament.admin.resources.backups.index'))
            ->line('If you did not initiate this restore, please contact your system administrator immediately.');
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
            'title' => 'Database Restore Completed',
            'message' => "Database restore from '{$this->backup->name}' has been completed successfully.",
            'type' => 'success',
            'backup_id' => $this->backup->id,
            'backup_name' => $this->backup->name,
            'backup_type' => $this->backup->type,
            'restored_at' => now()->toISOString(),
            'options' => $this->options,
            'action_url' => route('filament.admin.resources.backups.index'),
        ];
    }
}