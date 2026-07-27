<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WaitlistConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $entry;
    public $confirmationUrl;

    public function __construct($entry, $confirmationUrl)
    {
        $this->entry = $entry;
        $this->confirmationUrl = $confirmationUrl;
    }

    public function build()
    {
        return $this->subject('Confirm your spot on the Neumera waitlist')
            ->view('emails.waitlist_confirmation')
            ->with([
                'entry' => $this->entry,
                'confirmationUrl' => $this->confirmationUrl,
            ]);
    }
}
