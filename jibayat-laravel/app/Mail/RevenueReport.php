<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Rapport de recettes envoyé automatiquement (quotidien, hebdomadaire ou mensuel). */
class RevenueReport extends Mailable
{
    public function __construct(public array $report, public string $frequency) {}

    public function envelope(): Envelope
    {
        $f = $this->report['from']->format('d/m/Y');
        $t = $this->report['to']->format('d/m/Y');

        return new Envelope(subject: 'Jibayat · Rapport des recettes '.($f === $t ? "du $f" : "du $f au $t"));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.revenue-report');
    }
}
