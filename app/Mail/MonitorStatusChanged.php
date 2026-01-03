<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\MonitorStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class MonitorStatusChanged extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Monitor $monitor,
        public MonitorCheck $check,
        public MonitorStatus $status
    ) {}

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-Entity-Ref-ID' => Str::uuid()->toString(),
            ],
        );
    }

    public function envelope(): Envelope
    {
        $isDown = $this->status === MonitorStatus::Down;
        $emoji = $isDown ? '🔴' : '🟢';
        $statusText = $isDown ? 'Down' : 'Up';

        return new Envelope(
            subject: "{$emoji} {$this->monitor->name} is {$statusText}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.monitor-status-changed',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
