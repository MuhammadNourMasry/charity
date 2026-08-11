<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VolunteerReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $userName;

    public function __construct(string $userName)
    {
        $this->userName = $userName;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تم استلام طلب التطوع بنجاح - شكراً لاهتمامك',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "
                <div style='font-family: Arial, sans-serif; direction: rtl; text-align: right; padding: 20px;'>
                    <h2>أهلاً بك يا {$this->userName}!</h2>
                    <p>نشكر اهتمامك بالانضمام إلى فريق المتطوعين لدينا.</p>
                    <p>تم استلام بياناتك بنجاح، وسيقوم فريق الموارد البشرية بمسارعة مراجعة طلبك والتواصل معك في أقرب وقت.</p>
                    <br>
                    <p>مع تحيات فريق العمل.</p>
                </div>
            "
        );
    }
}
