<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WaitlistConfirmation extends Mailable
{
    public $user;
    public $confirmationUrl;

    public function __construct($user, $confirmationUrl)
    {
        $this->user = $user;
        $this->confirmationUrl = $confirmationUrl;
    }

    public function build()
    {
        return $this->subject('Confirm your spot on the Neumera waitlist')
            ->view('emails.waitlist_confirmation');
    }
}
