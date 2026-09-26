<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests\Features;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Jasnita\Monitor\Laravel\Tests\TestCase;
use Jasnita\Monitor\Sdk\Tracing\Span;
use Jasnita\Monitor\Sdk\Tracing\SpanStatus;

class NotificationsIntegrationTest extends TestCase
{
    protected $defaultSetupConfig = [
        'jasnita.tracing.views' => false,
    ];

    public function testSpanIsRecorded(): void
    {
        $span = $this->sendNotificationAndRetrieveSpan();

        $this->assertEquals('mail', $span->getDescription());
        $this->assertEquals('mail', $span->getData()['channel']);
        $this->assertEquals('notification.send', $span->getOp());
        $this->assertEquals(SpanStatus::ok(), $span->getStatus());
    }

    public function testSpanIsNotRecordedWhenDisabled(): void
    {
        $this->resetApplicationWithConfig([
            'jasnita.tracing.notifications.enabled' => false,
        ]);

        $this->sendNotificationAndExpectNoSpan();
    }

    public function testBreadcrumbIsRecorded(): void
    {
        $this->sendTestNotification();

        $this->assertCount(1, $this->getCurrentJasnitaBreadcrumbs());

        $breadcrumb = $this->getLastJasnitaBreadcrumb();

        $this->assertEquals('notification.sent', $breadcrumb->getCategory());
    }

    public function testBreadcrumbIsNotRecordedWhenDisabled(): void
    {
        $this->resetApplicationWithConfig([
            'jasnita.breadcrumbs.notifications.enabled' => false,
        ]);

        $this->sendTestNotification();

        $this->assertCount(0, $this->getCurrentJasnitaBreadcrumbs());
    }

    private function sendTestNotification(): void
    {
        // We fake the mail so that no actual email is sent but the notification is still sent with all it's events
        Mail::fake();

        Notification::route('mail', 'jasnita@example.com')->notifyNow(new NotificationsIntegrationTestNotification);
    }

    private function sendNotificationAndRetrieveSpan(): Span
    {
        $transaction = $this->startTransaction();

        $this->sendTestNotification();

        $spans = $transaction->getSpanRecorder()->getSpans();

        $this->assertCount(2, $spans);

        return $spans[1];
    }

    private function sendNotificationAndExpectNoSpan(): void
    {
        $transaction = $this->startTransaction();

        $this->sendTestNotification();

        $spans = $transaction->getSpanRecorder()->getSpans();

        $this->assertCount(1, $spans);
    }
}

class NotificationsIntegrationTestNotification extends \Illuminate\Notifications\Notification
{
    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return new \Illuminate\Notifications\Messages\MailMessage;
    }
}
