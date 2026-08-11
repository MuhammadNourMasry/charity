<?php

namespace App\Mail;

use App\Models\AidApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AidApplicationReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $application;
    public $userName;

    public function __construct(AidApplication $application, string $userName)
    {
        $this->application = $application;
        $this->userName = $userName;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تم استلام طلب المساعدة بنجاح - منصة التكافل',
        );
    }

    public function content(): Content
    {
        $urgencyText = $this->application->is_urgent ? 'عاجل' : 'عادي';

        return new Content(
            htmlString: "
                <div style='font-family: Arial, sans-serif; direction: rtl; text-align: right; padding: 20px; background-color: #f8f9fa; border-radius: 8px;'>
                    <h2 style='color: #2c3e50;'>أهلاً بك يا {$this->userName}</h2>
                    <p style='font-size: 15px; color: #444;'>تم استلام طلب المساعدة الخاص بك بنجاح، نسأل الله أن يفرّج عنك ويرزقك من واسع فضله.</p>

                    <div style='background-color: #ffffff; padding: 15px; border-right: 4px solid #3498db; margin: 15px 0; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);'>
                        <p style='margin: 5px 0;'><strong>رقم الطلب:</strong> #{$this->application->id}</p>
                        <p style='margin: 5px 0;'><strong>نوع الحاجة:</strong> {$this->application->type}</p>
                        <p style='margin: 5px 0;'><strong>حالة الطلب:</strong> {$this->application->status}</p>
                        <p style='margin: 5px 0;'><strong>درجة الأهمية:</strong> {$urgencyText}</p>
                    </div>

                    <p style='font-size: 14px; color: #666;'>يقوم فريق دراسة الحالات حالياً بمراجعة تفاصيل طلبك، وسنوافيكم بالتحديثات فور اتخاذ الإجراء المناسب.</p>
                    <br>
                    <p style='margin: 0; font-weight: bold;'>مع أطيب التحيات،</p>
                    <p style='margin: 0; color: #7f8c8d;'>فريق تقديم الدعم والمساندة</p>
                </div>
            "
        );
    }
}
