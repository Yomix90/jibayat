<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Alerte Jibayat : enregistrée pour la cloche ; envoyée aussi par e-mail si l'utilisateur l'a demandé et a une adresse. */
class AlertNotification extends Notification
{
    public function __construct(public array $alert) {}

    public function via(object $notifiable): array
    {
        return $notifiable->email && $notifiable->email_alerts ? ['database', 'mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->alert;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Jibayat · '.$this->alert['title'])->greeting('Bonjour '.$notifiable->name.',')
            ->line($this->alert['title'].'.')->line($this->alert['message'])->action('Ouvrir Jibayat', $this->alert['url']);
    }
}
