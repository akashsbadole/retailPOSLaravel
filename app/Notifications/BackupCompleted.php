<?php

namespace App\Notifications;

use App\Models\Backup;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BackupCompleted extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param Backup $backup
     * @return void
     */
    public function __construct(
        protected Backup $backup
    ) {}

    /**
     * Get the notification's delivery channels.
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
            ->subject('Backup Completed Successfully')
            ->greeting('Hello ' . $notifiable->name)
            ->line("Your {$this->backup->type_label} backup has been completed successfully.")
            ->line("Backup Name: {$this->backup->name}")
            ->line("File Size: {$this->backup->formatted_file_size}")
            ->line("Duration: {$this->backup->duration}")
            ->line("Records Processed: {$this->backup->processed_records}")
            ->action('Download Backup', $this->backup->download_url)
            ->line('Thank you for using SuperMart POS!');
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
            'title' => 'Backup Completed',
            'message' => "Your {$this->backup->type_label} backup has been completed successfully.",
            'backup_id' => $this->backup->id,
            'backup_name' => $this->backup->name,
            'backup_type' => $this->backup->type,
            'file_size' => $this->backup->formatted_file_size,
            'duration' => $this->backup->duration,
            'download_url' => $this->backup->download_url,
            'type' => 'success',
        ];
    }
}