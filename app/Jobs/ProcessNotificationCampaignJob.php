<?php

namespace App\Jobs;

use App\Models\NotificationCampaign;
use App\Models\NotificationCampaignTarget;
use App\Models\Setting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ProcessNotificationCampaignJob implements ShouldQueue
{
    use Queueable;

    public $queue = 'notification-campaigns';

    public $timeout = 3600;

    public function __construct(public int $campaignId) {}

    public function tags(): array
    {
        return ['notifications', 'campaign:'.$this->campaignId];
    }

    public function handle(): void
    {
        $campaign = NotificationCampaign::find($this->campaignId);

        if (! $campaign || in_array($campaign->status, ['paused', 'completed', 'draft'])) {
            return;
        }

        $campaign->update([
            'status' => 'processing',
            'started_at' => $campaign->started_at ?? now(),
        ]);

        // Load SMTP Settings from DB
        $settings = Setting::whereIn('key', [
            'mail_host',
            'mail_port',
            'mail_username',
            'mail_password',
            'mail_encryption',
            'mail_from_address',
            'mail_from_name',
            'mail_delay_ms',
        ])->pluck('value', 'key');

        $host = $settings['mail_host'] ?? config('mail.mailers.smtp.host');
        $port = $settings['mail_port'] ?? config('mail.mailers.smtp.port');
        $username = $settings['mail_username'] ?? config('mail.mailers.smtp.username');
        $password = $settings['mail_password'] ?? config('mail.mailers.smtp.password');
        $encryption = $settings['mail_encryption'] ?? 'tls';
        $fromAddress = $settings['mail_from_address'] ?? config('mail.from.address');
        $fromName = $settings['mail_from_name'] ?? config('mail.from.name');
        $delayMs = (int) ($settings['mail_delay_ms'] ?? 500);

        // Configure Mailer on the fly
        Config::set('mail.mailers.campaign_smtp', [
            'transport' => 'smtp',
            'host' => $host,
            'port' => (int) $port,
            'encryption' => ($encryption !== 'none' && ! empty($encryption)) ? $encryption : null,
            'username' => $username,
            'password' => $password,
            'timeout' => 30,
        ]);

        $mailer = Mail::mailer('campaign_smtp');

        NotificationCampaignTarget::where('notification_campaign_id', $this->campaignId)
            ->where('status', 'pending')
            ->chunkById(50, function ($targets) use ($campaign, $mailer, $fromAddress, $fromName, $delayMs) {
                foreach ($targets as $target) {
                    // Check if campaign was paused or canceled mid-flight
                    $campaign->refresh();
                    if (in_array($campaign->status, ['paused', 'draft'])) {
                        return false; // Break chunking
                    }

                    try {
                        $storeName = $target->store_name ?: 'عزيزنا التاجر';
                        $body = str_replace(
                            ['{store_name}', '{email}'],
                            [$storeName, $target->email],
                            $campaign->content ?? ''
                        );

                        $subject = str_replace(
                            ['{store_name}', '{email}'],
                            [$storeName, $target->email],
                            $campaign->subject ?? 'إشعار مهم'
                        );

                        $mailer->html($body, function ($message) use ($target, $subject, $fromAddress, $fromName) {
                            $message->to($target->email)
                                ->subject($subject);

                            if ($fromAddress) {
                                $message->from($fromAddress, $fromName);
                            }
                        });

                        $target->update([
                            'status' => 'sent',
                            'sent_at' => now(),
                            'error_message' => null,
                        ]);

                        $campaign->increment('sent_count');
                    } catch (Throwable $e) {
                        Log::error("Campaign {$campaign->id} failed to send to {$target->email}: ".$e->getMessage());

                        $target->update([
                            'status' => 'failed',
                            'error_message' => $e->getMessage(),
                        ]);

                        $campaign->increment('failed_count');
                    }

                    if ($delayMs > 0) {
                        usleep($delayMs * 1000);
                    }
                }
            });

        // Check if finished
        $campaign->refresh();
        $pendingCount = NotificationCampaignTarget::where('notification_campaign_id', $this->campaignId)
            ->where('status', 'pending')
            ->count();

        if ($pendingCount === 0 && $campaign->status === 'processing') {
            $campaign->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }
    }
}
