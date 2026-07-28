import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    CheckCircle2,
    Clock,
    Globe,
    Key,
    Loader2,
    Mail,
    Send,
    Server,
    Settings,
    ShieldCheck,
    UserCheck,
    XCircle,
} from 'lucide-react';

interface SettingsData {
    mail_host: string;
    mail_port: string;
    mail_username: string;
    mail_password: string;
    mail_encryption: string;
    mail_from_address: string;
    mail_from_name: string;
    mail_delay_ms: number;
}

interface Props {
    settings: SettingsData;
}

export default function MailSettings({ settings }: Props) {
    const form = useForm<SettingsData>({
        mail_host: settings.mail_host || '',
        mail_port: settings.mail_port || '587',
        mail_username: settings.mail_username || '',
        mail_password: settings.mail_password || '',
        mail_encryption: settings.mail_encryption || 'tls',
        mail_from_address: settings.mail_from_address || '',
        mail_from_name: settings.mail_from_name || '',
        mail_delay_ms: settings.mail_delay_ms || 500,
    });

    const [testModalOpen, setTestModalOpen] = useState(false);
    const [testEmail, setTestEmail] = useState('');
    const [testing, setTesting] = useState(false);
    const [testResult, setTestResult] = useState<{ success: boolean; message: string } | null>(null);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/notification-settings');
    };

    const handleRunTest = (e: React.FormEvent) => {
        e.preventDefault();
        if (!testEmail) return;

        setTesting(true);
        setTestResult(null);

        fetch('/notification-settings/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({
                email: testEmail,
                ...form.data,
            }),
        })
            .then((res) => res.json())
            .then((data) => {
                setTestResult({
                    success: data.success || false,
                    message: data.message || 'حدث أخطاء غير متوقعة أثناء الاتصال',
                });
            })
            .catch((err) => {
                setTestResult({
                    success: false,
                    message: err.message || 'فشل الاتصال بالخادم',
                });
            })
            .finally(() => setTesting(false));
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'حملات الإشعارات', href: '/notification-campaigns' }, { title: 'إعدادات البريد الإلكتروني' }]}>
            <Head title="إعدادات خادم البريد الـ SMTP" />

            <div dir="rtl" className="p-6 space-y-6 max-w-4xl mx-auto w-full">
                <div className="bg-gradient-to-r from-slate-900 to-indigo-950 p-6 rounded-2xl text-white shadow-lg border border-indigo-900/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <Settings className="h-6 w-6 text-indigo-400" />
                            <h1 className="text-xl font-bold">إعدادات خادم البريد SMTP وسرعة الإرسال</h1>
                        </div>
                        <p className="text-xs text-slate-300">
                            قم بضبط خادم البريد لتضمن وصول رسائل الحملات إلى المتاجر بدون الانزلاق إلى مجلد Spam
                        </p>
                    </div>

                    <Button
                        type="button"
                        onClick={() => setTestModalOpen(true)}
                        className="bg-indigo-600 hover:bg-indigo-700 text-white gap-2 font-bold shadow-md"
                    >
                        <Send className="h-4 w-4" />
                        <span>اختبار اتصالات البريد الآن</span>
                    </Button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* SMTP Credentials Card */}
                    <Card className="border-slate-200 dark:border-slate-800 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-base font-bold flex items-center gap-2 text-indigo-600">
                                <Server className="h-5 w-5" />
                                <span>بيانات الاتصال بخادم البريد (SMTP Server Setup)</span>
                            </CardTitle>
                            <CardDescription>
                                أدخل تفاصيل خادم الـ SMTP الخاص بك (Mailgun, SendGrid, Amazon SES, Google Workspace, إلخ)
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div className="space-y-2 sm:col-span-2">
                                    <Label htmlFor="mail_host" className="text-xs font-bold">عنوان خادم البريد (Host) *</Label>
                                    <Input
                                        id="mail_host"
                                        value={form.data.mail_host}
                                        onChange={(e) => form.setData('mail_host', e.target.value)}
                                        placeholder="مثال: smtp.mailtrap.io أو smtp.gmail.com"
                                        required
                                        className="h-10 text-xs font-mono"
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="mail_port" className="text-xs font-bold">المنفذ (Port) *</Label>
                                    <Input
                                        id="mail_port"
                                        value={form.data.mail_port}
                                        onChange={(e) => form.setData('mail_port', e.target.value)}
                                        placeholder="587 أو 465"
                                        required
                                        className="h-10 text-xs font-mono"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="mail_encryption" className="text-xs font-bold">نوع التشفير (Encryption) *</Label>
                                    <select
                                        id="mail_encryption"
                                        value={form.data.mail_encryption}
                                        onChange={(e) => form.setData('mail_encryption', e.target.value)}
                                        className="w-full h-10 px-3 rounded-md border border-slate-200 dark:border-slate-800 bg-background text-xs"
                                    >
                                        <option value="tls">TLS (موصى به - Port 587)</option>
                                        <option value="ssl">SSL (Port 465)</option>
                                        <option value="none">بدون تشفير None</option>
                                    </select>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="mail_username" className="text-xs font-bold">اسم المستخدم (Username)</Label>
                                    <Input
                                        id="mail_username"
                                        value={form.data.mail_username}
                                        onChange={(e) => form.setData('mail_username', e.target.value)}
                                        placeholder="اسم مستخدم الـ SMTP..."
                                        className="h-10 text-xs font-mono"
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="mail_password" className="text-xs font-bold">كلمة المرور (Password)</Label>
                                    <Input
                                        id="mail_password"
                                        type="password"
                                        value={form.data.mail_password}
                                        onChange={(e) => form.setData('mail_password', e.target.value)}
                                        placeholder="••••••••••••"
                                        className="h-10 text-xs font-mono"
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Sender Identity & Anti-Spam Throttling */}
                    <Card className="border-slate-200 dark:border-slate-800 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-base font-bold flex items-center gap-2 text-indigo-600">
                                <ShieldCheck className="h-5 w-5" />
                                <span>هوية المرسل وحماية السمعة (Anti-Spam Throttling)</span>
                            </CardTitle>
                            <CardDescription>
                                تحديد الاسم والإيميل الذي سيظهر للمتاجر، وتحديد فترة التأخير بين كل رسالة لمنع حظر البريد
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="mail_from_address" className="text-xs font-bold">بريد المرسل (From Email) *</Label>
                                    <Input
                                        id="mail_from_address"
                                        type="email"
                                        value={form.data.mail_from_address}
                                        onChange={(e) => form.setData('mail_from_address', e.target.value)}
                                        placeholder="info@yourcompany.com"
                                        required
                                        className="h-10 text-xs font-mono"
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="mail_from_name" className="text-xs font-bold">اسم المرسل الظاهر (From Name) *</Label>
                                    <Input
                                        id="mail_from_name"
                                        value={form.data.mail_from_name}
                                        onChange={(e) => form.setData('mail_from_name', e.target.value)}
                                        placeholder="منصة المتاجر - قسم العلاقات"
                                        required
                                        className="h-10 text-xs"
                                    />
                                </div>
                            </div>

                            <div className="space-y-2 pt-2 border-t">
                                <div className="flex items-center justify-between">
                                    <Label htmlFor="mail_delay_ms" className="text-xs font-bold flex items-center gap-1.5">
                                        <Clock className="h-4 w-4 text-indigo-500" />
                                        <span>فترة التأخير بين كل رسالة بريدية (بالمللي ثانية) *</span>
                                    </Label>
                                    <span className="text-xs font-mono font-bold text-indigo-600">
                                        {form.data.mail_delay_ms} ms ({form.data.mail_delay_ms / 1000} ثانية)
                                    </span>
                                </div>
                                <Input
                                    id="mail_delay_ms"
                                    type="number"
                                    min={0}
                                    step={100}
                                    value={form.data.mail_delay_ms}
                                    onChange={(e) => form.setData('mail_delay_ms', parseInt(e.target.value) || 0)}
                                    className="h-10 text-xs font-mono"
                                />
                                <p className="text-[11px] text-muted-foreground">
                                    توصية: يفضل ضبط التأخير بين 500ms إلى 2000ms للحفاظ على سمعة السيرفر وضمان تسليم البريد في الـ Inbox.
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex items-center justify-between pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setTestModalOpen(true)}
                            className="gap-2 text-xs"
                        >
                            <Send className="h-4 w-4" /> فحص وإرسال إيميل تجريبي
                        </Button>

                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-8 h-11 shadow-md gap-2"
                        >
                            {form.processing ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
                            <span>حفظ الإعدادات</span>
                        </Button>
                    </div>
                </form>

                {/* Test SMTP Dialog */}
                <Dialog open={testModalOpen} onOpenChange={setTestModalOpen}>
                    <DialogContent dir="rtl" className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2 text-indigo-600">
                                <Send className="h-5 w-5" />
                                <span>اختبار اتصال خادم البريد SMTP</span>
                            </DialogTitle>
                            <DialogDescription>
                                سيتم إرسال رسالة بريد إلكتروني تجريبية للتأكد من صحة البيانات
                            </DialogDescription>
                        </DialogHeader>

                        <form onSubmit={handleRunTest} className="space-y-4 py-2">
                            <div className="space-y-2">
                                <Label htmlFor="test_email" className="text-xs font-bold">البريد الإلكتروني التجريبي المستلم *</Label>
                                <Input
                                    id="test_email"
                                    type="email"
                                    value={testEmail}
                                    onChange={(e) => setTestEmail(e.target.value)}
                                    placeholder="أدخل إيميلك الشخصي لاختبار وصول الرسالة..."
                                    required
                                    className="h-10 text-xs font-mono"
                                />
                            </div>

                            {testResult ? (
                                <div
                                    className={`p-3 rounded-xl border text-xs space-y-1 ${
                                        testResult.success
                                            ? 'bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300'
                                            : 'bg-red-50 text-red-800 border-red-200 dark:bg-red-950/40 dark:text-red-300'
                                    }`}
                                >
                                    <div className="font-bold flex items-center gap-1.5">
                                        {testResult.success ? (
                                            <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                                        ) : (
                                            <XCircle className="h-4 w-4 text-red-600" />
                                        )}
                                        <span>{testResult.success ? 'نجاح الاتصال والإرسال!' : 'فشل الاتصال!'}</span>
                                    </div>
                                    <p className="font-mono text-[11px] break-words">{testResult.message}</p>
                                </div>
                            ) : null}

                            <div className="flex justify-end gap-2 pt-2">
                                <Button type="button" variant="outline" onClick={() => setTestModalOpen(false)}>
                                    إغلاق
                                </Button>
                                <Button type="submit" disabled={testing || !testEmail} className="bg-indigo-600 hover:bg-indigo-700 text-white gap-2 font-bold">
                                    {testing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                                    <span>إرسال تجريبي</span>
                                </Button>
                            </div>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
