<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WaitlistInvite extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $link;

    public function __construct($link)
    {
        $this->link = $link;

    }

    public function build()
    {
        return $this->subject('Confirm your spot on the Neumera waitlist')
            ->view('emails.waitlist_invite')
            ->with([
                'link' => $this->link,
            ]);
    }
}
