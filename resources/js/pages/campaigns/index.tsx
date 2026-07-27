import { Head, useForm, router } from '@inertiajs/react';
import React, { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    FileSpreadsheet,
    Search,
    Loader2,
    CheckCircle2,
    XCircle,
    Ban,
    Trash2,
    AlertCircle,
    Globe,
    Layers,
    Activity,
    Info,
} from 'lucide-react';

interface CampaignItem {
    id: number;
    name: string;
    type: 'excel' | 'google';
    status: 'pending' | 'processing' | 'completed' | 'failed' | 'cancelled';
    status_message: string | null;
    search_query: string | null;
    file_path: string | null;
    total_stores: number;
    processed_stores: number;
    success_count: number;
    failure_count: number;
    already_exists_count: number;
    google_links_found: number;
    google_links_processed: number;
    google_pages_scraped: number;
    error_message: string | null;
    errors_count?: number;
    created_at: string;
}

interface CampaignError {
    id: number;
    store_id: string | null;
    store_url: string | null;
    error_message: string | null;
    created_at: string;
}

interface Props {
    campaigns: {
        data: CampaignItem[];
        links: any[];
        current_page: number;
        last_page: number;
    };
    stats: {
        total: number;
        processing: number;
        completed: number;
        failed: number;
        cancelled: number;
        total_success: number;
        total_failure: number;
        total_exists: number;
    };
}

export default function CampaignsIndex({ campaigns: initialCampaigns, stats: initialStats }: Props) {
    const [campaignList, setCampaignList] = useState<CampaignItem[]>(initialCampaigns.data);
    const [excelModalOpen, setExcelModalOpen] = useState(false);
    const [googleModalOpen, setGoogleModalOpen] = useState(false);
    const [selectedErrorCampaign, setSelectedErrorCampaign] = useState<CampaignItem | null>(null);
    const [campaignErrors, setCampaignErrors] = useState<CampaignError[]>([]);
    const [loadingErrors, setLoadingErrors] = useState(false);

    // Sync initial state
    useEffect(() => {
        setCampaignList(initialCampaigns.data);
    }, [initialCampaigns.data]);

    // Poll active processing campaigns every 5 seconds
    useEffect(() => {
        const hasProcessing = campaignList.some(c => c.status === 'processing' || c.status === 'pending');
        if (!hasProcessing) return;

        const interval = setInterval(() => {
            campaignList.forEach(campaign => {
                if (campaign.status === 'processing' || campaign.status === 'pending') {
                    fetch(`/campaigns/${campaign.id}/stats`)
                        .then(res => res.json())
                        .then(data => {
                            if (data?.campaign) {
                                setCampaignList(prev =>
                                    prev.map(item => (item.id === data.campaign.id ? data.campaign : item))
                                );
                            }
                        })
                        .catch(() => {});
                }
            });
        }, 5000);

        return () => clearInterval(interval);
    }, [campaignList]);

    // Excel Form
    const excelForm = useForm<{ name: string; file: File | null }>({
        name: '',
        file: null,
    });

    const handleExcelSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        excelForm.post('/campaigns/excel', {
            onSuccess: () => {
                setExcelModalOpen(false);
                excelForm.reset();
            },
        });
    };

    // Google Form
    const googleForm = useForm<{ name: string; query: string }>({
        name: '',
        query: 'site:salla.sa ',
    });

    const handleGoogleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        googleForm.post('/campaigns/google', {
            onSuccess: () => {
                setGoogleModalOpen(false);
                googleForm.reset();
            },
        });
    };

    const handleCancel = (id: number) => {
        if (confirm('هل أنت تأكد من إيقاف وإلغاء هذه الحملة؟')) {
            router.post(`/campaigns/${id}/cancel`);
        }
    };

    const handleDelete = (id: number) => {
        if (confirm('هل أنت تأكد من حذف هذه الحملة بالكامل؟')) {
            router.delete(`/campaigns/${id}`);
        }
    };

    const openErrorModal = (campaign: CampaignItem) => {
        setSelectedErrorCampaign(campaign);
        setLoadingErrors(true);
        fetch(`/campaigns/${campaign.id}/errors`)
            .then(res => res.json())
            .then(data => {
                setCampaignErrors(data.errors?.data || data.errors || []);
            })
            .finally(() => setLoadingErrors(false));
    };

    const getStatusBadge = (status: CampaignItem['status']) => {
        switch (status) {
            case 'processing':
                return (
                    <Badge variant="outline" className="bg-amber-500/10 text-amber-600 border-amber-300 dark:border-amber-700 flex items-center gap-1.5 px-3 py-1">
                        <Loader2 className="h-3.5 w-3.5 animate-spin" />
                        <span>يتم المعالجة</span>
                    </Badge>
                );
            case 'completed':
                return (
                    <Badge variant="outline" className="bg-emerald-500/10 text-emerald-600 border-emerald-300 dark:border-emerald-700 flex items-center gap-1.5 px-3 py-1">
                        <CheckCircle2 className="h-3.5 w-3.5" />
                        <span>مكتملة</span>
                    </Badge>
                );
            case 'failed':
                return (
                    <Badge variant="outline" className="bg-red-500/10 text-red-600 border-red-300 dark:border-red-700 flex items-center gap-1.5 px-3 py-1">
                        <XCircle className="h-3.5 w-3.5" />
                        <span>فشلت</span>
                    </Badge>
                );
            case 'cancelled':
                return (
                    <Badge variant="outline" className="bg-neutral-500/10 text-neutral-500 border-neutral-300 dark:border-neutral-700 flex items-center gap-1.5 px-3 py-1">
                        <Ban className="h-3.5 w-3.5" />
                        <span>ملغاة</span>
                    </Badge>
                );
            default:
                return (
                    <Badge variant="outline" className="bg-blue-500/10 text-blue-600 border-blue-300 dark:border-blue-700 flex items-center gap-1.5 px-3 py-1">
                        <Loader2 className="h-3.5 w-3.5 animate-spin" />
                        <span>بالانتظار</span>
                    </Badge>
                );
        }
    };

    return (
        <>
            <Head title="إدارة حملات الاستخراج والفحص" />

            <div dir="rtl" className="p-6 space-y-6 max-w-7xl mx-auto w-full">

                {/* Top Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 p-6 rounded-2xl text-white shadow-xl">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <Layers className="h-7 w-7 text-indigo-400" />
                            <h1 className="text-2xl font-bold tracking-tight">حملات استخراج وفحص المتاجر</h1>
                        </div>
                        <p className="text-sm text-slate-300">
                            قم برفع ملفات Excel أو ابحث المباشر في Google لاستخراج المتاجر وفحصها تلقائياً بالخلفية
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        {/* Excel Upload Modal Trigger */}
                        <Dialog open={excelModalOpen} onOpenChange={setExcelModalOpen}>
                            <DialogTrigger asChild>
                                <Button className="bg-emerald-600 hover:bg-emerald-700 text-white gap-2 font-medium shadow-md">
                                    <FileSpreadsheet className="h-4 w-4" />
                                    <span>إضافة ملف Excel</span>
                                </Button>
                            </DialogTrigger>
                            <DialogContent dir="rtl" className="sm:max-w-md">
                                <form onSubmit={handleExcelSubmit}>
                                    <DialogHeader>
                                        <DialogTitle className="flex items-center gap-2 text-emerald-600">
                                            <FileSpreadsheet className="h-5 w-5" />
                                            <span>إنشاء حملة استيراد Excel</span>
                                        </DialogTitle>
                                        <DialogDescription>
                                            قم برفع ملف إكسيل يحتوي على عمود لمعرفات المتاجر (مثل "معرف المتجر" أو "Store ID").
                                        </DialogDescription>
                                    </DialogHeader>

                                    <div className="space-y-4 py-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="excel_name">اسم الحملة</Label>
                                            <Input
                                                id="excel_name"
                                                placeholder="مثال: متاجر التجميل - 2026"
                                                value={excelForm.data.name}
                                                onChange={e => excelForm.setData('name', e.target.value)}
                                                required
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="excel_file">اختر ملف Excel (.xlsx, .xls, .csv)</Label>
                                            <Input
                                                id="excel_file"
                                                type="file"
                                                accept=".xlsx,.xls,.csv"
                                                onChange={e => excelForm.setData('file', e.target.files?.[0] || null)}
                                                required
                                            />
                                        </div>
                                    </div>

                                    <DialogFooter className="gap-2 sm:gap-0">
                                        <Button type="submit" disabled={excelForm.processing} className="bg-emerald-600 hover:bg-emerald-700 text-white">
                                            {excelForm.processing ? <Loader2 className="h-4 w-4 animate-spin ml-2" /> : null}
                                            بدء الحملة
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>

                        {/* Google Search Modal Trigger */}
                        <Dialog open={googleModalOpen} onOpenChange={setGoogleModalOpen}>
                            <DialogTrigger asChild>
                                <Button className="bg-indigo-600 hover:bg-indigo-700 text-white gap-2 font-medium shadow-md">
                                    <Globe className="h-4 w-4" />
                                    <span>حملة بحث Google</span>
                                </Button>
                            </DialogTrigger>
                            <DialogContent dir="rtl" className="sm:max-w-md">
                                <form onSubmit={handleGoogleSubmit}>
                                    <DialogHeader>
                                        <DialogTitle className="flex items-center gap-2 text-indigo-600">
                                            <Search className="h-5 w-5" />
                                            <span>إنشاء حملة بحث Google</span>
                                        </DialogTitle>
                                        <DialogDescription>
                                            اكتب استعلام البحث المتقدم لكشط محرك البحث واستخراج المتاجر منها.
                                        </DialogDescription>
                                    </DialogHeader>

                                    <div className="space-y-4 py-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="google_name">اسم الحملة</Label>
                                            <Input
                                                id="google_name"
                                                placeholder="مثال: بحث تمور سلة"
                                                value={googleForm.data.name}
                                                onChange={e => googleForm.setData('name', e.target.value)}
                                                required
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="google_query">استعلام البحث في Google</Label>
                                            <Input
                                                id="google_query"
                                                placeholder="site:salla.sa عطور"
                                                value={googleForm.data.query}
                                                onChange={e => googleForm.setData('query', e.target.value)}
                                                required
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                يمكنك استخدام عوامل تصفية مثل: <code className="bg-muted px-1 py-0.5 rounded">site:salla.sa "تمور"</code>
                                            </p>
                                        </div>
                                    </div>

                                    <DialogFooter className="gap-2 sm:gap-0">
                                        <Button type="submit" disabled={googleForm.processing} className="bg-indigo-600 hover:bg-indigo-700 text-white">
                                            {googleForm.processing ? <Loader2 className="h-4 w-4 animate-spin ml-2" /> : null}
                                            بدء كشط Google
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>

                {/* Overall Stats Cards */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <Card className="bg-card shadow-sm border-slate-200 dark:border-slate-800">
                        <CardHeader className="p-4 pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">إجمالي الحملات</CardTitle>
                        </CardHeader>
                        <CardContent className="p-4 pt-0">
                            <div className="text-2xl font-bold">{initialStats.total}</div>
                            <p className="text-xs text-muted-foreground mt-1">
                                {initialStats.processing} نشطة الآن
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="bg-emerald-500/5 border-emerald-200/60 dark:border-emerald-900/40">
                        <CardHeader className="p-4 pb-2">
                            <CardTitle className="text-sm font-medium text-emerald-600 dark:text-emerald-400">متاجر تم جلبها بنجاح</CardTitle>
                        </CardHeader>
                        <CardContent className="p-4 pt-0">
                            <div className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{initialStats.total_success}</div>
                            <p className="text-xs text-emerald-600/70 dark:text-emerald-400/70 mt-1">فحص مكتمل البيانات</p>
                        </CardContent>
                    </Card>

                    <Card className="bg-blue-500/5 border-blue-200/60 dark:border-blue-900/40">
                        <CardHeader className="p-4 pb-2">
                            <CardTitle className="text-sm font-medium text-blue-600 dark:text-blue-400">متاجر موجودة بالفعل</CardTitle>
                        </CardHeader>
                        <CardContent className="p-4 pt-0">
                            <div className="text-2xl font-bold text-blue-600 dark:text-blue-400">{initialStats.total_exists}</div>
                            <p className="text-xs text-blue-600/70 dark:text-blue-400/70 mt-1">تم تخطيها تلقائياً</p>
                        </CardContent>
                    </Card>

                    <Card className="bg-red-500/5 border-red-200/60 dark:border-red-900/40">
                        <CardHeader className="p-4 pb-2">
                            <CardTitle className="text-sm font-medium text-red-600 dark:text-red-400">فشل الفحص</CardTitle>
                        </CardHeader>
                        <CardContent className="p-4 pt-0">
                            <div className="text-2xl font-bold text-red-600 dark:text-red-400">{initialStats.total_failure}</div>
                            <p className="text-xs text-red-600/70 dark:text-red-400/70 mt-1">تعذر الوصول/خطأ في المتجر</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Campaigns List */}
                <div className="space-y-4">
                    <h2 className="text-lg font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                        <Activity className="h-5 w-5 text-indigo-500" />
                        <span>قائمة الحملات المباشرة</span>
                    </h2>

                    {campaignList.length === 0 ? (
                        <Card className="p-12 text-center border-dashed">
                            <div className="max-w-sm mx-auto space-y-3">
                                <Layers className="h-12 w-12 text-muted-foreground mx-auto" />
                                <h3 className="text-lg font-semibold">لا توجد حملات حتى الآن</h3>
                                <p className="text-sm text-muted-foreground">
                                    قم ببدء حملة استيراد ملف Excel أو كشط محرك بحث Google لتبدأ العمليات التلقائية بالخلفية.
                                </p>
                            </div>
                        </Card>
                    ) : (
                        <div className="grid grid-cols-1 gap-4">
                            {campaignList.map(campaign => {
                                const total = campaign.total_stores || 0;
                                const processed = campaign.processed_stores || 0;
                                const percent = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;

                                return (
                                    <Card key={campaign.id} className="relative overflow-hidden border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md transition-shadow">
                                        <CardHeader className="p-5 pb-3">
                                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                                <div className="flex items-center gap-3">
                                                    {campaign.type === 'excel' ? (
                                                        <div className="p-2.5 rounded-xl bg-emerald-500/10 text-emerald-600">
                                                            <FileSpreadsheet className="h-5 w-5" />
                                                        </div>
                                                    ) : (
                                                        <div className="p-2.5 rounded-xl bg-indigo-500/10 text-indigo-600">
                                                            <Globe className="h-5 w-5" />
                                                        </div>
                                                    )}

                                                    <div>
                                                        <CardTitle className="text-base font-bold flex items-center gap-2">
                                                            <span>{campaign.name}</span>
                                                            <Badge variant="outline" className="text-xs font-normal">
                                                                {campaign.type === 'excel' ? 'ملف Excel' : 'بحث Google'}
                                                            </Badge>
                                                        </CardTitle>
                                                        <CardDescription className="text-xs mt-1">
                                                            {campaign.search_query ? (
                                                                <span>الاستعلام: <code className="text-indigo-600 dark:text-indigo-400">{campaign.search_query}</code></span>
                                                            ) : (
                                                                <span>الملف: {campaign.file_path}</span>
                                                            )}
                                                            <span className="mr-3 text-muted-foreground">• {campaign.created_at}</span>
                                                        </CardDescription>
                                                    </div>
                                                </div>

                                                <div className="flex items-center gap-2 self-start sm:self-auto">
                                                    {getStatusBadge(campaign.status)}

                                                    {campaign.status === 'processing' || campaign.status === 'pending' ? (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => handleCancel(campaign.id)}
                                                            className="text-red-600 hover:text-red-700 hover:bg-red-50 border-red-200 text-xs"
                                                        >
                                                            إلغاء
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
                                            {/* Status Message */}
                                            {campaign.status_message ? (
                                                <div className="text-xs font-medium text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800/60 p-2.5 rounded-lg flex items-center gap-2">
                                                    <Info className="h-4 w-4 text-indigo-500 shrink-0" />
                                                    <span>{campaign.status_message}</span>
                                                </div>
                                            ) : null}

                                            {/* Progress bar */}
                                            {total > 0 ? (
                                                <div className="space-y-1.5">
                                                    <div className="flex justify-between text-xs text-muted-foreground">
                                                        <span>نسبة الفحص المكتملة ({processed} من أصل {total})</span>
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

                                            {/* Stats breakdown grid */}
                                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                                                <div className="bg-slate-50 dark:bg-slate-900/50 p-2.5 rounded-lg border border-slate-100 dark:border-slate-800">
                                                    <span className="text-muted-foreground block">إجمالي اكتشاف المتاجر</span>
                                                    <span className="text-base font-bold text-slate-800 dark:text-slate-200">{campaign.total_stores}</span>
                                                </div>

                                                <div className="bg-emerald-500/5 p-2.5 rounded-lg border border-emerald-500/20">
                                                    <span className="text-emerald-600 dark:text-emerald-400 block">فحص ناجح</span>
                                                    <span className="text-base font-bold text-emerald-600 dark:text-emerald-400">{campaign.success_count}</span>
                                                </div>

                                                <div className="bg-blue-500/5 p-2.5 rounded-lg border border-blue-500/20">
                                                    <span className="text-blue-600 dark:text-blue-400 block">موجود بالفعل (تم تخطيه)</span>
                                                    <span className="text-base font-bold text-blue-600 dark:text-blue-400">{campaign.already_exists_count}</span>
                                                </div>

                                                <div className="bg-red-500/5 p-2.5 rounded-lg border border-red-500/20 flex items-center justify-between">
                                                    <div>
                                                        <span className="text-red-600 dark:text-red-400 block">فشل الفحص</span>
                                                        <span className="text-base font-bold text-red-600 dark:text-red-400">{campaign.failure_count}</span>
                                                    </div>
                                                    {campaign.failure_count > 0 ? (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => openErrorModal(campaign)}
                                                            className="text-xs h-7 px-2 border-red-200 hover:bg-red-50 text-red-600"
                                                        >
                                                            الأخطاء
                                                        </Button>
                                                    ) : null}
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                );
                            })}
                        </div>
                    )}
                </div>

                {/* Errors Inspection Modal */}
                <Dialog open={!!selectedErrorCampaign} onOpenChange={open => !open && setSelectedErrorCampaign(null)}>
                    <DialogContent dir="rtl" className="sm:max-w-2xl max-h-[80vh] overflow-y-auto">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2 text-red-600">
                                <AlertCircle className="h-5 w-5" />
                                <span>سجل الأخطاء التفصيلي - {selectedErrorCampaign?.name}</span>
                            </DialogTitle>
                            <DialogDescription>
                                تفاصيل المشاكل التي حدثت أثناء فحص المتاجر في هذه الحملة
                            </DialogDescription>
                        </DialogHeader>

                        {loadingErrors ? (
                            <div className="py-8 text-center text-muted-foreground flex items-center justify-center gap-2">
                                <Loader2 className="h-5 w-5 animate-spin text-indigo-500" />
                                <span>جارٍ تحميل سجل الأخطاء...</span>
                            </div>
                        ) : campaignErrors.length === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">
                                لا توجد أخطاء مسجلة لهذه الحملة.
                            </div>
                        ) : (
                            <div className="space-y-3 py-2">
                                {campaignErrors.map(err => (
                                    <div key={err.id} className="p-3 bg-red-500/5 border border-red-200 dark:border-red-900/40 rounded-lg text-xs space-y-1">
                                        <div className="flex justify-between font-semibold text-slate-800 dark:text-slate-200">
                                            <span>معرف المتجر / الرابط: {err.store_id || err.store_url || 'غير محدد'}</span>
                                            <span className="text-muted-foreground font-normal">{err.created_at}</span>
                                        </div>
                                        <div className="text-red-600 dark:text-red-400 font-mono text-[11px] bg-red-100/50 dark:bg-red-950/30 p-2 rounded break-all">
                                            {err.error_message}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

