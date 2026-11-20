<?php

namespace App\Livewire;

use Livewire\Component;

/** Cloche de la barre du haut : alertes non lues, lecture, « tout marquer comme lu ». */
class NotificationBell extends Component
{
    public function open(string $id)
    {
        $n = auth()->user()->notifications()->findOrFail($id);
        $n->markAsRead();

        return $this->redirect($n->data['url'] ?? route('home'), navigate: false);
    }

    public function markAll(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function render()
    {
        $u = auth()->user();

        return view('livewire.notification-bell', ['unread' => $u->unreadNotifications()->count(), 'items' => $u->notifications()->latest()->limit(15)->get()]);
    }
}
