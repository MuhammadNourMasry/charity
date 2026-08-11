<?php

namespace App\Mail;

use App\Models\Campaign;
use App\Models\Donation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DonationReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $donation;
    public $campaign;
    public $userName;

    public function __construct(Donation $donation, Campaign $campaign, string $userName)
    {
        $this->donation = $donation;
        $this->campaign = $campaign;
        $this->userName = $userName;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'شكراً لتبرعك - تم استلام مساهمتك بنجاح',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "
                <div style='font-family: Arial, sans-serif; direction: rtl; text-align: right; padding: 20px; background-color: #f9f9f9; border-radius: 8px;'>
                    <h2 style='color: #2c3e50;'>أهلاً بك يا {$this->userName}!</h2>
                    <p style='font-size: 16px; color: #333;'>نشكرك جزيل الشكر على مساهمتك القيمة وتبرعك الكريم.</p>

                    <div style='background-color: #ffffff; padding: 15px; border-right: 4px solid #27ae60; margin: 15px 0;'>
                        <p style='margin: 5px 0;'><strong>اسم الحملة:</strong> {$this->campaign->title}</p>
                        <p style='margin: 5px 0;'><strong>المبلغ المتبرَع به:</strong> {$this->donation->amount} USD</p>
                        <p style='margin: 5px 0;'><strong>طريقة الدفع:</strong> {$this->donation->payment_method}</p>
                        <p style='margin: 5px 0;'><strong>تاريخ التبرع:</strong> {$this->donation->created_at->format('Y-m-d H:i')}</p>
                    </div>

                    <p style='font-size: 14px; color: #7f8c8d;'>مساهمتك تُحدث فارقاً حقيقياً وتساعدنا على تحقيق أهدافنا.</p>
                    <br>
                    <p style='margin: 0; font-weight: bold;'>مع خالص الشكر والتقدير،</p>
                    <p style='margin: 0; color: #7f8c8d;'>فريق منصة التبرعات</p>
                </div>
            "
        );
    }
}
