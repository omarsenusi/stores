<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MailSettingsController extends Controller
{
    public function index(): Response
    {
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

        return Inertia::render('notification-campaigns/settings', [
            'settings' => [
                'mail_host' => $settings['mail_host'] ?? config('mail.mailers.smtp.host', '127.0.0.1'),
                'mail_port' => $settings['mail_port'] ?? config('mail.mailers.smtp.port', '587'),
                'mail_username' => $settings['mail_username'] ?? config('mail.mailers.smtp.username', ''),
                'mail_password' => $settings['mail_password'] ?? config('mail.mailers.smtp.password', ''),
                'mail_encryption' => $settings['mail_encryption'] ?? 'tls',
                'mail_from_address' => $settings['mail_from_address'] ?? config('mail.from.address', ''),
                'mail_from_name' => $settings['mail_from_name'] ?? config('mail.from.name', 'أدمن المتاجر'),
                'mail_delay_ms' => $settings['mail_delay_ms'] ?? 500,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mail_host' => ['required', 'string'],
            'mail_port' => ['required', 'numeric'],
            'mail_username' => ['nullable', 'string'],
            'mail_password' => ['nullable', 'string'],
            'mail_encryption' => ['required', 'in:tls,ssl,none'],
            'mail_from_address' => ['required', 'email'],
            'mail_from_name' => ['required', 'string', 'max:255'],
            'mail_delay_ms' => ['required', 'numeric', 'min:0'],
        ]);

        foreach ($validated as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => (string) $value]
            );
        }

        return back()->with('success', 'تم حفظ إعدادات خادم البريد الـ SMTP بنجاح.');
    }

    public function test(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'mail_host' => ['required', 'string'],
            'mail_port' => ['required', 'numeric'],
            'mail_username' => ['nullable', 'string'],
            'mail_password' => ['nullable', 'string'],
            'mail_encryption' => ['required', 'in:tls,ssl,none'],
            'mail_from_address' => ['required', 'email'],
            'mail_from_name' => ['required', 'string'],
        ]);

        try {
            Config::set('mail.mailers.test_smtp', [
                'transport' => 'smtp',
                'host' => $validated['mail_host'],
                'port' => (int) $validated['mail_port'],
                'encryption' => ($validated['mail_encryption'] !== 'none') ? $validated['mail_encryption'] : null,
                'username' => $validated['mail_username'],
                'password' => $validated['mail_password'],
                'timeout' => 15,
            ]);

            Mail::mailer('test_smtp')->html(
                '<div dir="rtl" style="font-family: sans-serif; padding: 20px;">'.
                '<h2 style="color: #4f46e5;">اختبار اتصال بريد نظام المتاجر</h2>'.
                '<p>مبروك! تم اختبار خادم البريد الإلكتروني SMTP بنجاح والاتصال يعمل بكفاءة عالية.</p>'.
                '</div>',
                function ($message) use ($validated) {
                    $message->to($validated['email'])
                        ->subject('اختبار خادم البريد SMTP - نظام المتاجر')
                        ->from($validated['mail_from_address'], $validated['mail_from_name']);
                }
            );

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال رسالة الاختبار بنجاح إلى البريد المحدد.',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل الاتصال بخادم البريد: '.$e->getMessage(),
            ], 422);
        }
    }
}
