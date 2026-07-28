import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import React, { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Activity,
    AlertCircle,
    CheckCircle2,
    Clock,
    FileEdit,
    Info,
    Loader2,
    Mail,
    MessageSquare,
    PauseCircle,
    PlayCircle,
    Plus,
    Send,
    Settings,
    Trash2,
    XCircle,
} from 'lucide-react';

interface NotificationCampaignItem {
    id: number;
    name: string;
    channel: 'email' | 'sms' | 'whatsapp';
    status: 'draft' | 'queued' | 'processing' | 'completed' | 'paused' | 'failed';
    step: number;
    subject: string | null;
    content: string | null;
    total_targets: number;
    sent_count: number;
    failed_count: number;
    started_at: string | null;
    completed_at: string | null;
    created_at: string;
}

interface TargetItem {
    id: number;
    email: string;
    store_name: string | null;
    status: 'pending' | 'sent' | 'failed';
    error_message: string | null;
    sent_at: string | null;
}

interface Props {
    campaigns: {
        data: NotificationCampaignItem[];
        links: any[];
        current_page: number;
        last_page: number;
    };
    stats: {
        total: number;
        processing: number;
        queued: number;
        completed: number;
        draft: number;
        total_sent: number;
        total_failed: number;
    };
}

export default function NotificationCampaignsIndex({ campaigns: initialCampaigns, stats }: Props) {
    const [campaignList, setCampaignList] = useState<NotificationCampaignItem[]>(initialCampaigns.data);
    const [selectedCampaign, setSelectedCampaign] = useState<NotificationCampaignItem | null>(null);
    const [targets, setTargets] = useState<TargetItem[]>([]);
    const [loadingTargets, setLoadingTargets] = useState(false);

    useEffect(() => {
        setCampaignList(initialCampaigns.data);
    }, [initialCampaigns.data]);

    // Poll active campaigns
    useEffect(() => {
        const hasActive = campaignList.some((c) => c.status === 'processing' || c.status === 'queued');
        if (!hasActive) return;

        const interval = setInterval(() => {
            campaignList.forEach((campaign) => {
                if (campaign.status === 'processing' || campaign.status === 'queued') {
                    fetch(`/notification-campaigns/${campaign.id}/stats`)
                        .then((res) => res.json())
                        .then((data) => {
                            if (data?.campaign) {
                                setCampaignList((prev) =>
                                    prev.map((item) => (item.id === data.campaign.id ? data.campaign : item))
                                );
                            }
                        })
                        .catch(() => {});
                }
            });
        }, 4000);

        return () => clearInterval(interval);
    }, [campaignList]);

    const handlePause = (id: number) => {
        router.post(`/notification-campaigns/${id}/pause`);
    };

    const handleResume = (id: number) => {
        router.post(`/notification-campaigns/${id}/resume`);
    };

    const handleDelete = (id: number) => {
        if (confirm('هل أنت متأكد من حذف هذه الحملة بالكامل؟')) {
            router.delete(`/notification-campaigns/${id}`);
        }
    };

    const openStatsModal = (campaign: NotificationCampaignItem) => {
        setSelectedCampaign(campaign);
        setLoadingTargets(true);
        fetch(`/notification-campaigns/${campaign.id}/stats`)
            .then((res) => res.json())
            .then((data) => {
                setTargets(data.targets?.data || []);
            })
            .finally(() => setLoadingTargets(false));
    };

    const getStatusBadge = (status: NotificationCampaignItem['status']) => {
        switch (status) {
            case 'processing':
                return (
                    <Badge variant="outline" className="bg-amber-500/10 text-amber-600 border-amber-300 dark:border-amber-700 flex items-center gap-1.5 px-3 py-1">
                        <Loader2 className="h-3.5 w-3.5 animate-spin" />
                        <span>جاري الإرسال (Horizon)</span>
                    </Badge>
                );
            case 'queued':
                return (
                    <Badge variant="outline" className="bg-blue-500/10 text-blue-600 border-blue-300 dark:border-blue-700 flex items-center gap-1.5 px-3 py-1">
                        <Clock className="h-3.5 w-3.5" />
                        <span>بالانتظار للطابور</span>
                    </Badge>
                );
            case 'completed':
                return (
                    <Badge variant="outline" className="bg-emerald-500/10 text-emerald-600 border-emerald-300 dark:border-emerald-700 flex items-center gap-1.5 px-3 py-1">
                        <CheckCircle2 className="h-3.5 w-3.5" />
                        <span>مكتملة بنجاح</span>
                    </Badge>
                );
            case 'paused':
                return (
                    <Badge variant="outline" className="bg-purple-500/10 text-purple-600 border-purple-300 dark:border-purple-700 flex items-center gap-1.5 px-3 py-1">
                        <PauseCircle className="h-3.5 w-3.5" />
                        <span>متوقفة مؤقتاً</span>
                    </Badge>
                );
            case 'draft':
                return (
                    <Badge variant="outline" className="bg-slate-500/10 text-slate-600 border-slate-300 dark:border-slate-700 flex items-center gap-1.5 px-3 py-1">
                        <FileEdit className="h-3.5 w-3.5" />
                        <span>مسودة</span>
                    </Badge>
                );
            case 'failed':
                return (
                    <Badge variant="outline" className="bg-red-500/10 text-red-600 border-red-300 dark:border-red-700 flex items-center gap-1.5 px-3 py-1">
                        <XCircle className="h-3.5 w-3.5" />
                        <span>فشلت</span>
                    </Badge>
                );
        }
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'حملات الإشعارات', href: '/notification-campaigns' }]}>
            <Head title="إدارة حملات الإشعارات والبريد الإلكتروني" />

            <div dir="rtl" className="p-6 space-y-6 max-w-7xl mx-auto w-full">
                {/* Clean Page Title Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <Mail className="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                            <h1 className="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100">حملات البريد والإشعارات (Horizon Queue)</h1>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            قم بإرسال حملات بريدية مستهدفة للمتاجر المفهرسة مع متابعة دقيقة للأداء الفوري ومنع حظر السيرفر
                        </p>
                    </div>

                    <div className="flex items-center gap-3 shrink-0">
                        <Link href="/notification-settings">
                            <Button variant="outline" className="gap-2 text-xs">
                                <Settings className="h-4 w-4" />
                                <span>إعدادات SMTP</span>
                            </Button>
                        </Link>

                        <Link href="/notification-campaigns/create">
                            <Button className="bg-indigo-600 hover:bg-indigo-700 text-white gap-2 text-xs font-medium">
                                <Plus className="h-4 w-4" />
                                <span>إنشاء حملة جديدة</span>
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Overall Stats Cards */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <Card className="bg-card shadow-sm border-slate-200 dark:border-slate-800">
                        <CardHeader className="p-4 pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">إجمالي الحملات</CardTitle>
                        </CardHeader>
                        <CardContent className="p-4 pt-0">
                            <div className="text-2xl font-bold">{stats.total}</div>
                            <p className="text-xs text-muted-foreground mt-1">
                                {stats.processing + stats.queued} نشطة / بالانتظار
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="bg-emerald-500/5 border-emerald-200/60 dark:border-emerald-900/40">
                        <CardHeader className="p-4 pb-2">
                            <CardTitle className="text-sm font-medium text-emerald-600 dark:text-emerald-400">رسائل أُرسلت بنجاح</CardTitle>
                        </CardHeader>
                        <CardContent className="p-4 pt-0">
                            <div className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{stats.total_sent}</div>
                            <p className="text-xs text-emerald-600/70 dark:text-emerald-400/70 mt-1">وصول مباشر للمستهدفين</p>
                        </CardContent>
                    </Card>

                    <Card className="bg-red-500/5 border-red-200/60 dark:border-red-900/40">
                        <CardHeader className="p-4 pb-2">
                            <CardTitle className="text-sm font-medium text-red-600 dark:text-red-400">رسائل فشل إرسالها</CardTitle>
                        </CardHeader>
                        <CardContent className="p-4 pt-0">
                            <div className="text-2xl font-bold text-red-600 dark:text-red-400">{stats.total_failed}</div>
                            <p className="text-xs text-red-600/70 dark:text-red-400/70 mt-1">أخطاء خادم / عناوين خاطئة</p>
                        </CardContent>
                    </Card>

                    <Card className="bg-blue-500/5 border-blue-200/60 dark:border-blue-900/40">
                        <CardHeader className="p-4 pb-2">
                            <CardTitle className="text-sm font-medium text-blue-600 dark:text-blue-400">حملات المسودة</CardTitle>
                        </CardHeader>
                        <CardContent className="p-4 pt-0">
                            <div className="text-2xl font-bold text-blue-600 dark:text-blue-400">{stats.draft}</div>
                            <p className="text-xs text-blue-600/70 dark:text-blue-400/70 mt-1">يمكن تعديلها وإطلاقها</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Campaigns List */}
                <div className="space-y-4">
                    <h2 className="text-lg font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                        <Activity className="h-5 w-5 text-indigo-500" />
                        <span>قائمة الحملات</span>
                    </h2>

                    {campaignList.length === 0 ? (
                        <Card className="p-12 text-center border-dashed">
                            <div className="max-w-sm mx-auto space-y-3">
                                <Mail className="h-12 w-12 text-muted-foreground mx-auto" />
                                <h3 className="text-lg font-semibold">لا توجد حملات إشعارات حالياً</h3>
                                <p className="text-sm text-muted-foreground">
                                    قم بإنشاء أول حملة إشعارات لك واختيار المتاجر وتصميم القالب بالخطوات التفاعلية.
                                </p>
                                <Link href="/notification-campaigns/create">
                                    <Button className="mt-2 bg-indigo-600 hover:bg-indigo-700 text-white">
                                        إنشاء حملة الآن
                                    </Button>
                                </Link>
                            </div>
                        </Card>
                    ) : (
                        <div className="grid grid-cols-1 gap-4">
                            {campaignList.map((campaign) => {
                                const total = campaign.total_targets || 0;
                                const processed = (campaign.sent_count || 0) + (campaign.failed_count || 0);
                                const percent = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;

                                return (
                                    <Card key={campaign.id} className="relative overflow-hidden border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md transition-shadow">
                                        <CardHeader className="p-5 pb-3">
                                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                                <div className="flex items-center gap-3">
                                                    <div className="p-2.5 rounded-xl bg-indigo-500/10 text-indigo-600">
                                                        <Mail className="h-5 w-5" />
                                                    </div>

                                                    <div>
                                                        <div className="flex items-center gap-2">
                                                            <CardTitle className="text-base font-bold">{campaign.name}</CardTitle>
                                                            <Badge variant="outline" className="text-xs px-2 py-0.5 capitalize">
                                                                {campaign.channel === 'email' ? 'بريد إلكتروني' : campaign.channel}
                                                            </Badge>
                                                        </div>
                                                        <CardDescription className="text-xs mt-1">
                                                            {campaign.subject ? (
                                                                <span>العنوان: <strong className="text-slate-800 dark:text-slate-200">{campaign.subject}</strong></span>
                                                            ) : (
                                                                <span>مسودة خطوة {campaign.step}</span>
                                                            )}
                                                            <span className="mr-3 text-muted-foreground">• {campaign.created_at}</span>
                                                        </CardDescription>
                                                    </div>
                                                </div>

                                                <div className="flex items-center gap-2 self-start sm:self-auto">
                                                    {getStatusBadge(campaign.status)}

                                                    {campaign.status === 'draft' ? (
                                                        <Link href={`/notification-campaigns/create/${campaign.id}?step=${campaign.step}`}>
                                                            <Button size="sm" variant="outline" className="text-indigo-600 border-indigo-200 hover:bg-indigo-50 text-xs">
                                                                <FileEdit className="h-3.5 w-3.5 ml-1" />
                                                                تعديل المسودة
                                                            </Button>
                                                        </Link>
                                                    ) : null}

                                                    {campaign.status === 'processing' || campaign.status === 'queued' ? (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => handlePause(campaign.id)}
                                                            className="text-purple-600 border-purple-200 hover:bg-purple-50 text-xs"
                                                        >
                                                            <PauseCircle className="h-3.5 w-3.5 ml-1" />
                                                            إيقاف مؤقت
                                                        </Button>
                                                    ) : null}

                                                    {campaign.status === 'paused' ? (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => handleResume(campaign.id)}
                                                            className="text-emerald-600 border-emerald-200 hover:bg-emerald-50 text-xs"
                                                        >
                                                            <PlayCircle className="h-3.5 w-3.5 ml-1" />
                                                            استئناف الإرسال
                                                        </Button>
                                                    ) : null}

                                                    {campaign.status !== 'draft' ? (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => openStatsModal(campaign)}
                                                            className="text-slate-700 dark:text-slate-300 text-xs"
                                                        >
                                                            <Info className="h-3.5 w-3.5 ml-1" />
                                                            التفاصيل والأداء
                                                        </Button>
                                                    ) : null}

                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => handleDelete(campaign.id)}
                                                        className="text-slate-400 hover:text-red-600 text-xs px-2"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                        </CardHeader>

                                        <CardContent className="p-5 pt-2 space-y-4">
                                            {/* Progress bar */}
                                            {total > 0 ? (
                                                <div className="space-y-1.5">
                                                    <div className="flex justify-between text-xs text-muted-foreground">
                                                        <span>نسبة تقدم الإرسال ({processed} من أصل {total})</span>
                                                        <span className="font-bold text-slate-900 dark:text-slate-100">{percent}%</span>
                                                    </div>
                                                    <div className="w-full bg-slate-100 dark:bg-slate-800 h-2.5 rounded-full overflow-hidden">
                                                        <div
                                                            className="bg-indigo-600 h-full transition-all duration-500 rounded-full"
                                                            style={{ width: `${percent}%` }}
                                                        />
                                                    </div>
                                                </div>
                                            ) : null}

                                            {/* Breakdown stats */}
                                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                                                <div className="bg-slate-50 dark:bg-slate-900/50 p-2.5 rounded-lg border border-slate-100 dark:border-slate-800">
                                                    <span className="text-muted-foreground block">إجمالي المستهدفين</span>
                                                    <span className="text-base font-bold text-slate-800 dark:text-slate-200">{campaign.total_targets}</span>
                                                </div>

                                                <div className="bg-emerald-500/5 p-2.5 rounded-lg border border-emerald-500/20">
                                                    <span className="text-emerald-600 dark:text-emerald-400 block">تم الإرسال بنجاح</span>
                                                    <span className="text-base font-bold text-emerald-600 dark:text-emerald-400">{campaign.sent_count}</span>
                                                </div>

                                                <div className="bg-red-500/5 p-2.5 rounded-lg border border-red-500/20">
                                                    <span className="text-red-600 dark:text-red-400 block">فشل الإرسال</span>
                                                    <span className="text-base font-bold text-red-600 dark:text-red-400">{campaign.failed_count}</span>
                                                </div>

                                                <div className="bg-blue-500/5 p-2.5 rounded-lg border border-blue-500/20">
                                                    <span className="text-blue-600 dark:text-blue-400 block">المتبقي بالانتظار</span>
                                                    <span className="text-base font-bold text-blue-600 dark:text-blue-400">
                                                        {Math.max(0, total - processed)}
                                                    </span>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                );
                            })}
                        </div>
                    )}
                </div>

                {/* Campaign Analytics & Recipients Modal */}
                <Dialog open={!!selectedCampaign} onOpenChange={(open) => !open && setSelectedCampaign(null)}>
                    <DialogContent dir="rtl" className="sm:max-w-3xl max-h-[85vh] overflow-y-auto">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2 text-indigo-600">
                                <Mail className="h-5 w-5" />
                                <span>أداء وتفاصيل الحملة: {selectedCampaign?.name}</span>
                            </DialogTitle>
                            <DialogDescription>
                                تفاصيل مستلمي الرسالة وسجل الإرسال عبر Horizon Queue
                            </DialogDescription>
                        </DialogHeader>

                        {selectedCampaign ? (
                            <div className="space-y-6 py-2">
                                {/* Message Subject & Content Preview */}
                                <div className="p-4 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 space-y-2">
                                    <div className="text-xs font-semibold text-muted-foreground">عنوان الرسالة:</div>
                                    <div className="text-sm font-bold text-slate-900 dark:text-slate-100">{selectedCampaign.subject || 'بدون عنوان'}</div>
                                    <div className="text-xs font-semibold text-muted-foreground mt-3">معاينة محتوى الـ HTML:</div>
                                    <div className="p-3 bg-white dark:bg-slate-950 rounded-lg border text-xs max-h-40 overflow-y-auto font-mono text-slate-700 dark:text-slate-300">
                                        {selectedCampaign.content || 'لا يوجد محتوى'}
                                    </div>
                                </div>

                                {/* Targets list */}
                                <div className="space-y-3">
                                    <h4 className="text-sm font-bold flex items-center gap-2">
                                        <Send className="h-4 w-4 text-indigo-500" />
                                        <span>سجل المستهدفين الإجمالي ({targets.length})</span>
                                    </h4>

                                    {loadingTargets ? (
                                        <div className="py-8 text-center text-muted-foreground flex items-center justify-center gap-2">
                                            <Loader2 className="h-5 w-5 animate-spin text-indigo-500" />
                                            <span>جاري جلب سجل الإرسال...</span>
                                        </div>
                                    ) : targets.length === 0 ? (
                                        <div className="py-6 text-center text-xs text-muted-foreground">
                                            لا يوجد مستهدفين مسجلين للحملة حتى الآن.
                                        </div>
                                    ) : (
                                        <div className="space-y-2 max-h-64 overflow-y-auto pr-1">
                                            {targets.map((target) => (
                                                <div
                                                    key={target.id}
                                                    className="p-3 rounded-lg border border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs bg-card"
                                                >
                                                    <div className="space-y-0.5">
                                                        <div className="font-semibold text-slate-900 dark:text-slate-100">
                                                            {target.store_name ? `${target.store_name} (` : ''}
                                                            <span className="text-indigo-600 dark:text-indigo-400">{target.email}</span>
                                                            {target.store_name ? ')' : ''}
                                                        </div>
                                                        {target.sent_at ? (
                                                            <div className="text-[11px] text-muted-foreground">تم الإرسال: {target.sent_at}</div>
                                                        ) : null}
                                                        {target.error_message ? (
                                                            <div className="text-[11px] text-red-500 font-mono mt-1">{target.error_message}</div>
                                                        ) : null}
                                                    </div>

                                                    <div>
                                                        {target.status === 'sent' ? (
                                                            <Badge className="bg-emerald-500/10 text-emerald-600 border-emerald-300">نجح</Badge>
                                                        ) : target.status === 'failed' ? (
                                                            <Badge className="bg-red-500/10 text-red-600 border-red-300">فشل</Badge>
                                                        ) : (
                                                            <Badge className="bg-amber-500/10 text-amber-600 border-amber-300">انتظار</Badge>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </div>
                        ) : null}
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
