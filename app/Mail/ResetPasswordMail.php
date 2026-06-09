<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;
    public $email;

    public function __construct($otp, $email = null)
    {
        $this->otp = $otp;
        $this->email = $email;
    }

    
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kode OTP Reset Kata Sandi - EventIn',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.reset_password', 
            with: [
                'otp' => $this->otp,
                'email' => $this->email,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}