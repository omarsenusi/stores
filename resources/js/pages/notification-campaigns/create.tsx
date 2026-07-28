import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    CheckCircle2,
    Eye,
    FileEdit,
    Globe,
    Info,
    Loader2,
    Mail,
    MessageSquare,
    Plus,
    Rocket,
    Save,
    Search,
    Send,
    Store,
    Trash2,
    UserCheck,
    X,
} from 'lucide-react';

interface StoreItem {
    id: number;
    store_name: string | null;
    domain: string;
    store_logo: string | null;
    contacts: {
        email?: string;
    } | null;
}

interface TargetItem {
    id: number;
    email: string;
    store_name: string | null;
    scraped_store_id: number | null;
}

interface CampaignData {
    id: number;
    name: string;
    channel: 'email' | 'sms' | 'whatsapp';
    status: string;
    step: number;
    subject: string | null;
    content: string | null;
    custom_emails: string[] | null;
    total_targets: number;
    targets?: TargetItem[];
}

interface Props {
    campaign?: CampaignData | null;
}

export default function CreateNotificationCampaign({ campaign }: Props) {
    const searchParams = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;
    const initialStepParam = searchParams ? parseInt(searchParams.get('step') || '1') : 1;

    const [currentStep, setCurrentStep] = useState<number>(
        campaign ? Math.max(1, campaign.step || initialStepParam) : 1
    );

    // Step 1 Form
    const step1Form = useForm({
        campaign_id: campaign?.id || null,
        name: campaign?.name || '',
        channel: campaign?.channel || 'email',
    });

    // Step 2 State (Dual List + Custom Emails)
    const [availableStores, setAvailableStores] = useState<StoreItem[]>([]);
    const [selectedStoresMap, setSelectedStoresMap] = useState<Map<number, StoreItem>>(new Map());
    const [customEmails, setCustomEmails] = useState<string[]>(campaign?.custom_emails || []);
    const [newCustomEmail, setNewCustomEmail] = useState('');
    const [customEmailError, setCustomEmailError] = useState('');

    const [searchQuery, setSearchQuery] = useState('');
    const [loadingStores, setLoadingStores] = useState(false);
    const [storePagination, setStorePagination] = useState({
        current_page: 1,
        last_page: 1,
        total: 0,
    });

    // Initialize selected stores from campaign targets if resuming draft
    useEffect(() => {
        if (campaign?.targets) {
            const initialMap = new Map<number, StoreItem>();
            campaign.targets.forEach((target) => {
                if (target.scraped_store_id) {
                    initialMap.set(target.scraped_store_id, {
                        id: target.scraped_store_id,
                        store_name: target.store_name,
                        domain: '',
                        store_logo: null,
                        contacts: { email: target.email },
                    });
                }
            });
            setSelectedStoresMap(initialMap);
        }
    }, [campaign]);

    // Fetch stores for dual-list available box with pagination & search
    const fetchAvailableStores = (page = 1, query = searchQuery) => {
        setLoadingStores(true);
        const url = `/api/stores-for-notifications?page=${page}&q=${encodeURIComponent(query)}`;
        fetch(url)
            .then((res) => res.json())
            .then((data) => {
                setAvailableStores(data.data || []);
                setStorePagination({
                    current_page: data.current_page,
                    last_page: data.last_page,
                    total: data.total,
                });
            })
            .finally(() => setLoadingStores(false));
    };

    useEffect(() => {
        if (currentStep === 2) {
            fetchAvailableStores(1, searchQuery);
        }
    }, [currentStep]);

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        fetchAvailableStores(1, searchQuery);
    };

    // Step 3 Form (Subject & HTML Content Editor)
    const step3Form = useForm({
        subject: campaign?.subject || 'عرض خاص لمتجركم المتميز 🚀',
        content:
            campaign?.content ||
            `<div dir="rtl" style="font-family: Arial, sans-serif; padding: 25px; background-color: #f8fafc; color: #1e293b;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0;">
        <h2 style="color: #4f46e5; font-size: 22px; margin-bottom: 16px;">مرحباً متجر {store_name}،</h2>
        <p style="font-size: 15px; line-height: 1.6; color: #475569;">
            يسعدنا تواصلكم ونقدم لكم تحديثات مميزة لتطوير أعمال متجركم وزيادة مبيعاتكم بكفاءة.
        </p>
        <div style="margin: 25px 0; text-align: center;">
            <a href="https://mahally.com" style="background-color: #4f46e5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;">
                استكشف الخدمات الآن
            </a>
        </div>
        <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 25px 0;" />
        <p style="font-size: 12px; color: #94a3b8; text-align: center;">
            وصلتك هذه الرسالة بصفتك متجراً مسجلاً لدينا • البريد: {email}
        </p>
    </div>
</div>`,
    });

    const [previewModalOpen, setPreviewModalOpen] = useState(false);

    // Toggle Store Selection
    const toggleSelectStore = (store: StoreItem) => {
        setSelectedStoresMap((prev) => {
            const next = new Map(prev);
            if (next.has(store.id)) {
                next.delete(store.id);
            } else {
                next.set(store.id, store);
            }
            return next;
        });
    };

    const selectAllCurrentPage = () => {
        setSelectedStoresMap((prev) => {
            const next = new Map(prev);
            availableStores.forEach((store) => {
                if (store.contacts?.email) {
                    next.set(store.id, store);
                }
            });
            return next;
        });
    };

    const clearAllSelected = () => {
        setSelectedStoresMap(new Map());
    };

    const handleAddCustomEmail = (e: React.FormEvent) => {
        e.preventDefault();
        const email = newCustomEmail.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            setCustomEmailError('يرجى إدخال بريد إلكتروني صحيح');
            return;
        }
        if (customEmails.includes(email)) {
            setCustomEmailError('هذا البريد تم إضافته بالفعل');
            return;
        }
        setCustomEmails([...customEmails, email]);
        setNewCustomEmail('');
        setCustomEmailError('');
    };

    const handleRemoveCustomEmail = (emailToRemove: string) => {
        setCustomEmails(customEmails.filter((e) => e !== emailToRemove));
    };

    // Submissions
    const submitStep1 = (e: React.FormEvent) => {
        e.preventDefault();
        step1Form.post('/notification-campaigns/step-1', {
            onSuccess: () => setCurrentStep(2),
        });
    };

    const submitStep2 = () => {
        const storeIds = Array.from(selectedStoresMap.keys());
        if (storeIds.length === 0 && customEmails.length === 0) {
            alert('يرجى اختيار متجر واحد على الأقل أو إضافة بريد إلكتروني مخصص.');
            return;
        }

        const campaignId = campaign?.id || step1Form.data.campaign_id;
        if (!campaignId) {
            alert('خطأ: لم يتم العثور على معرّف الحملة المسودة.');
            return;
        }

        router.post(
            `/notification-campaigns/${campaignId}/step-2`,
            {
                store_ids: storeIds,
                custom_emails: customEmails,
            },
            {
                onSuccess: () => setCurrentStep(3),
            }
        );
    };

    const submitStep3 = (e: React.FormEvent) => {
        e.preventDefault();
        const campaignId = campaign?.id || step1Form.data.campaign_id;
        if (!campaignId) return;

        step3Form.post(`/notification-campaigns/${campaignId}/step-3`, {
            onSuccess: () => setCurrentStep(4),
        });
    };

    const launchCampaign = () => {
        const campaignId = campaign?.id || step1Form.data.campaign_id;
        if (!campaignId) return;

        router.post(`/notification-campaigns/${campaignId}/launch`);
    };

    const renderRenderedPreview = () => {
        const sampleStoreName = Array.from(selectedStoresMap.values())[0]?.store_name || 'متجر الأناقة الرياضية';
        const sampleEmail = Array.from(selectedStoresMap.values())[0]?.contacts?.email || customEmails[0] || 'info@store.com';

        let html = step3Form.data.content || '';
        html = html.replace(/\{store_name\}/g, sampleStoreName);
        html = html.replace(/\{email\}/g, sampleEmail);
        return html;
    };

    const selectedStoresList = Array.from(selectedStoresMap.values());

    return (
        <>
            <Head title="معالج إنشاء حملة إشعارات متكاملة" />

            <div dir="rtl" className="p-6 space-y-6 max-w-6xl mx-auto w-full">
                {/* Stepper Header */}
                <div className="bg-card border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-xl font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                                <Send className="h-5 w-5 text-indigo-600" />
                                <span>معالج إنشاء حملة البريد الإلكتروني والإشعارات</span>
                            </h1>
                            <p className="text-xs text-muted-foreground mt-1">
                                يتم حفظ الحملة تلقائياً كـ <strong className="text-indigo-600">Draft</strong> لاستكمالها في أي وقت
                            </p>
                        </div>

                        {campaign?.id ? (
                            <Badge variant="outline" className="bg-indigo-50 text-indigo-700 border-indigo-200">
                                مسودة رقم #{campaign.id}
                            </Badge>
                        ) : null}
                    </div>

                    {/* Progress Steps Nav */}
                    <div className="grid grid-cols-4 gap-2 pt-2">
                        {[
                            { step: 1, title: '1. البيانات الأساسية', icon: Send },
                            { step: 2, title: '2. اختيار المتاجر', icon: Store },
                            { step: 3, title: '3. محتوى الرسالة', icon: Mail },
                            { step: 4, title: '4. المراجعة والإطلاق', icon: Rocket },
                        ].map((s) => {
                            const Icon = s.icon;
                            const isActive = currentStep === s.step;
                            const isDone = currentStep > s.step;

                            return (
                                <button
                                    key={s.step}
                                    type="button"
                                    onClick={() => campaign?.id && s.step <= (campaign.step || 1) + 1 && setCurrentStep(s.step)}
                                    disabled={!campaign?.id && s.step > 1}
                                    className={`flex items-center justify-center gap-2 p-3 rounded-xl text-xs font-bold transition-all text-center border ${
                                        isActive
                                            ? 'bg-indigo-600 text-white border-indigo-600 shadow-md'
                                            : isDone
                                            ? 'bg-emerald-500/10 text-emerald-600 border-emerald-300 dark:border-emerald-700'
                                            : 'bg-slate-50 dark:bg-slate-900 text-slate-400 border-slate-200 dark:border-slate-800'
                                    }`}
                                >
                                    <Icon className="h-4 w-4" />
                                    <span className="hidden md:inline">{s.title}</span>
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* STEP 1: Basic Campaign Details */}
                {currentStep === 1 ? (
                    <Card className="border-slate-200 dark:border-slate-800 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-lg font-bold text-indigo-600 flex items-center gap-2">
                                <Send className="h-5 w-5" />
                                <span>الخطوة الأولى: بيانات الحملة والقناة</span>
                            </CardTitle>
                            <CardDescription>
                                حدد اسم الحملة وقناة التواصل، وسوف يتم إنشاء المسودة فوراً في قاعدة البيانات.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submitStep1} className="space-y-6 max-w-xl">
                                <div className="space-y-2">
                                    <Label htmlFor="name" className="text-sm font-bold">اسم الحملة *</Label>
                                    <Input
                                        id="name"
                                        value={step1Form.data.name}
                                        onChange={(e) => step1Form.setData('name', e.target.value)}
                                        placeholder="مثال: حملة تخفيضات الصيف - متاجر الملابس"
                                        required
                                        className="h-11"
                                    />
                                    {step1Form.errors.name && (
                                        <p className="text-xs text-red-500 font-medium">{step1Form.errors.name}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label className="text-sm font-bold">قناة التواصل الإشعاري *</Label>
                                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                        <button
                                            type="button"
                                            onClick={() => step1Form.setData('channel', 'email')}
                                            className={`p-4 rounded-xl border text-right transition-all flex flex-col gap-2 ${
                                                step1Form.data.channel === 'email'
                                                    ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/30 ring-2 ring-indigo-500'
                                                    : 'border-slate-200 dark:border-slate-800'
                                            }`}
                                        >
                                            <div className="flex items-center justify-between">
                                                <Mail className="h-5 w-5 text-indigo-600" />
                                                <Badge className="bg-indigo-600">نشط</Badge>
                                            </div>
                                            <div className="font-bold text-sm">البريد الإلكتروني</div>
                                            <div className="text-xs text-muted-foreground">إرسال كود HTML تفاعلي مخصص</div>
                                        </button>

                                        <button
                                            type="button"
                                            disabled
                                            className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 opacity-60 text-right cursor-not-allowed bg-slate-50 dark:bg-slate-900 flex flex-col gap-2"
                                        >
                                            <div className="flex items-center justify-between">
                                                <MessageSquare className="h-5 w-5 text-slate-400" />
                                                <Badge variant="outline" className="text-[10px]">قريباً</Badge>
                                            </div>
                                            <div className="font-bold text-sm text-slate-400">رسائل SMS</div>
                                            <div className="text-xs text-muted-foreground">إرسال نصي مباشر للجوال</div>
                                        </button>

                                        <button
                                            type="button"
                                            disabled
                                            className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 opacity-60 text-right cursor-not-allowed bg-slate-50 dark:bg-slate-900 flex flex-col gap-2"
                                        >
                                            <div className="flex items-center justify-between">
                                                <Globe className="h-5 w-5 text-slate-400" />
                                                <Badge variant="outline" className="text-[10px]">قريباً</Badge>
                                            </div>
                                            <div className="font-bold text-sm text-slate-400">واتساب WhatsApp</div>
                                            <div className="text-xs text-muted-foreground">إرسال رسائل التفاعلية</div>
                                        </button>
                                    </div>
                                </div>

                                <div className="pt-4 flex justify-end">
                                    <Button type="submit" disabled={step1Form.processing} className="bg-indigo-600 hover:bg-indigo-700 text-white gap-2 px-6 h-11 font-bold">
                                        {step1Form.processing ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
                                        <span>حفظ وحفظ كمسودة ➔ الخطوة التالية</span>
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                ) : null}

                {/* STEP 2: Target Stores Dual-List Transfer Box */}
                {currentStep === 2 ? (
                    <Card className="border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                        <CardHeader className="pb-2">
                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div>
                                    <CardTitle className="text-lg font-bold text-indigo-600 flex items-center gap-2">
                                        <Store className="h-5 w-5" />
                                        <span>الخطوة الثانية: تحديد المتاجر المستهدفة (Dual-List / 200k+ Ready)</span>
                                    </CardTitle>
                                    <CardDescription>
                                        يتم فقط إظهار المتاجر التي تمتلك بريد إلكتروني مسجل في النظام <code className="text-indigo-600 font-mono">contacts.email</code>
                                    </CardDescription>
                                </div>

                                <Badge variant="secondary" className="text-sm px-3 py-1 bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300">
                                    المحدد حتى الآن: {selectedStoresMap.size + customEmails.length} بريد مستهدف
                                </Badge>
                            </div>
                        </CardHeader>

                        <CardContent className="space-y-6">
                            {/* Dual List Transfer Box Container */}
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                {/* AVAILABLE STORES BOX (Right) */}
                                <div className="space-y-3 p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
                                    <div className="flex items-center justify-between">
                                        <h3 className="font-bold text-sm flex items-center gap-2">
                                            <Store className="h-4 w-4 text-indigo-500" />
                                            <span>المتاجر المتاحة بريدياً ({storePagination.total})</span>
                                        </h3>

                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={selectAllCurrentPage}
                                            className="text-xs h-8 text-indigo-600 border-indigo-200"
                                        >
                                            تحديد كل الصفحة الحالية
                                        </Button>
                                    </div>

                                    {/* Search Bar */}
                                    <form onSubmit={handleSearchSubmit} className="flex gap-2">
                                        <div className="relative flex-1">
                                            <Search className="absolute right-3 top-3 h-4 w-4 text-muted-foreground" />
                                            <Input
                                                value={searchQuery}
                                                onChange={(e) => setSearchQuery(e.target.value)}
                                                placeholder="البحث باسم المتجر، النطاق، أو الإيميل..."
                                                className="pr-9 h-10 text-xs"
                                            />
                                        </div>
                                        <Button type="submit" size="sm" variant="secondary" className="h-10 text-xs">
                                            بحث
                                        </Button>
                                    </form>

                                    {/* Stores Available List */}
                                    {loadingStores ? (
                                        <div className="py-12 text-center text-xs text-muted-foreground flex items-center justify-center gap-2">
                                            <Loader2 className="h-5 w-5 animate-spin text-indigo-600" />
                                            <span>جاري جلب المتاجر المفهرسة...</span>
                                        </div>
                                    ) : availableStores.length === 0 ? (
                                        <div className="py-12 text-center text-xs text-muted-foreground">
                                            لا توجد متاجر تطابق البحث أو تحتوي على بريد إلكتروني.
                                        </div>
                                    ) : (
                                        <div className="space-y-2 max-h-80 overflow-y-auto pr-1">
                                            {availableStores.map((store) => {
                                                const isSelected = selectedStoresMap.has(store.id);
                                                const email = store.contacts?.email;

                                                return (
                                                    <div
                                                        key={store.id}
                                                        onClick={() => toggleSelectStore(store)}
                                                        className={`p-3 rounded-lg border text-xs cursor-pointer transition-all flex items-center justify-between ${
                                                            isSelected
                                                                ? 'bg-indigo-50 dark:bg-indigo-950/40 border-indigo-300 dark:border-indigo-700'
                                                                : 'bg-white dark:bg-slate-950 border-slate-200 dark:border-slate-800 hover:border-indigo-200'
                                                        }`}
                                                    >
                                                        <div className="space-y-0.5">
                                                            <div className="font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                                                                <span>{store.store_name || store.domain}</span>
                                                            </div>
                                                            <div className="text-muted-foreground font-mono text-[11px]">
                                                                {email}
                                                            </div>
                                                        </div>

                                                        <div>
                                                            {isSelected ? (
                                                                <Badge className="bg-indigo-600 text-white gap-1 text-[10px]">
                                                                    <Check className="h-3 w-3" /> تم الاختيار
                                                                </Badge>
                                                            ) : (
                                                                <Button size="sm" variant="ghost" className="h-7 text-[11px] text-indigo-600">
                                                                    + اختيار
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}

                                    {/* Pagination Controls */}
                                    <div className="flex items-center justify-between pt-2 border-t text-xs">
                                        <span>صفحة {storePagination.current_page} من {storePagination.last_page}</span>
                                        <div className="flex gap-1">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                disabled={storePagination.current_page <= 1 || loadingStores}
                                                onClick={() => fetchAvailableStores(storePagination.current_page - 1)}
                                                className="h-8 px-2"
                                            >
                                                السابقة
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                disabled={storePagination.current_page >= storePagination.last_page || loadingStores}
                                                onClick={() => fetchAvailableStores(storePagination.current_page + 1)}
                                                className="h-8 px-2"
                                            >
                                                التالية
                                            </Button>
                                        </div>
                                    </div>
                                </div>

                                {/* SELECTED STORES BOX (Left) */}
                                <div className="space-y-3 p-4 rounded-xl border border-indigo-200 dark:border-indigo-900/60 bg-indigo-50/20 dark:bg-indigo-950/20">
                                    <div className="flex items-center justify-between">
                                        <h3 className="font-bold text-sm flex items-center gap-2 text-indigo-600">
                                            <CheckCircle2 className="h-4 w-4" />
                                            <span>المتاجر المختارة بالحملة ({selectedStoresList.length})</span>
                                        </h3>

                                        {selectedStoresList.length > 0 ? (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                onClick={clearAllSelected}
                                                className="text-xs h-8 text-red-600 hover:text-red-700"
                                            >
                                                إلغاء اختيار الكل
                                            </Button>
                                        ) : null}
                                    </div>

                                    {selectedStoresList.length === 0 ? (
                                        <div className="py-20 text-center text-xs text-muted-foreground border border-dashed rounded-lg">
                                            قم باختيار المتاجر من القائمة المتاحة على اليمين لإضافتها للحملة.
                                        </div>
                                    ) : (
                                        <div className="space-y-2 max-h-96 overflow-y-auto pr-1">
                                            {selectedStoresList.map((store) => (
                                                <div
                                                    key={store.id}
                                                    className="p-3 rounded-lg border border-indigo-200 dark:border-indigo-800 bg-white dark:bg-slate-950 flex items-center justify-between text-xs"
                                                >
                                                    <div className="space-y-0.5">
                                                        <div className="font-bold text-slate-900 dark:text-slate-100">
                                                            {store.store_name || store.domain}
                                                        </div>
                                                        <div className="text-indigo-600 font-mono text-[11px]">
                                                            {store.contacts?.email}
                                                        </div>
                                                    </div>

                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => toggleSelectStore(store)}
                                                        className="h-7 text-xs text-red-500 hover:bg-red-50"
                                                    >
                                                        إزالة
                                                    </Button>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* MANUAL CUSTOM EMAILS INPUT */}
                            <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-card space-y-3">
                                <h3 className="font-bold text-sm flex items-center gap-2">
                                    <Mail className="h-4 w-4 text-indigo-500" />
                                    <span>إضافة إيميلات خاصة مخصصة يدوياً ({customEmails.length})</span>
                                </h3>

                                <form onSubmit={handleAddCustomEmail} className="flex gap-2 max-w-lg">
                                    <div className="flex-1">
                                        <Input
                                            type="email"
                                            value={newCustomEmail}
                                            onChange={(e) => setNewCustomEmail(e.target.value)}
                                            placeholder="أدخل بريد إلكتروني مخصص (مثال: admin@company.com)..."
                                            className="h-10 text-xs"
                                        />
                                        {customEmailError && (
                                            <p className="text-[11px] text-red-500 mt-1">{customEmailError}</p>
                                        )}
                                    </div>
                                    <Button type="submit" size="sm" className="bg-slate-800 text-white h-10 gap-1 text-xs">
                                        <Plus className="h-4 w-4" /> إضافة
                                    </Button>
                                </form>

                                {customEmails.length > 0 ? (
                                    <div className="flex flex-wrap gap-2 pt-2">
                                        {customEmails.map((email) => (
                                            <Badge
                                                key={email}
                                                variant="secondary"
                                                className="px-3 py-1 flex items-center gap-2 text-xs bg-slate-100 dark:bg-slate-800"
                                            >
                                                <span>{email}</span>
                                                <X
                                                    className="h-3 w-3 cursor-pointer text-slate-500 hover:text-red-500"
                                                    onClick={() => handleRemoveCustomEmail(email)}
                                                />
                                            </Badge>
                                        ))}
                                    </div>
                                ) : null}
                            </div>

                            {/* Actions Nav */}
                            <div className="flex items-center justify-between pt-4 border-t">
                                <Button type="button" variant="outline" onClick={() => setCurrentStep(1)}>
                                    السابقة (البيانات الأساسية)
                                </Button>

                                <Button
                                    type="button"
                                    onClick={submitStep2}
                                    className="bg-indigo-600 hover:bg-indigo-700 text-white gap-2 font-bold px-6 h-11"
                                >
                                    <span>حفظ وتنسيق كود الرسالة الـ HTML ➔</span>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                ) : null}

                {/* STEP 3: HTML Editor & Preview */}
                {currentStep === 3 ? (
                    <Card className="border-slate-200 dark:border-slate-800 shadow-sm">
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="text-lg font-bold text-indigo-600 flex items-center gap-2">
                                        <Mail className="h-5 w-5" />
                                        <span>الخطوة الثالثة: محتوى وكود الـ HTML للرسالة</span>
                                    </CardTitle>
                                    <CardDescription>
                                        صمم قالب البريد الإلكتروني مع دعم متغيرات التخصيص ومعاينة حية للمظهر
                                    </CardDescription>
                                </div>

                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setPreviewModalOpen(true)}
                                    className="bg-indigo-50 text-indigo-700 border-indigo-200 gap-2 text-xs font-bold"
                                >
                                    <Eye className="h-4 w-4" />
                                    <span>معاينة شكل الرسالة التفاعلية</span>
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submitStep3} className="space-y-6">
                                <div className="space-y-2">
                                    <Label htmlFor="subject" className="text-sm font-bold">عنوان بريد الرسالة (Subject Line) *</Label>
                                    <Input
                                        id="subject"
                                        value={step3Form.data.subject}
                                        onChange={(e) => step3Form.setData('subject', e.target.value)}
                                        placeholder="أدخل عنوان موضوع البريد الإلكتروني..."
                                        required
                                        className="h-11 font-medium"
                                    />
                                </div>

                                {/* Supported variables helper */}
                                <div className="p-3 bg-indigo-50/50 dark:bg-indigo-950/40 rounded-xl border border-indigo-200 dark:border-indigo-900 text-xs flex items-center gap-3">
                                    <span className="font-bold text-indigo-700 dark:text-indigo-300">المتغيرات المدعومة في النص:</span>
                                    <button
                                        type="button"
                                        onClick={() => step3Form.setData('content', step3Form.data.content + ' {store_name} ')}
                                        className="px-2 py-1 bg-white dark:bg-slate-900 border border-indigo-300 rounded font-mono font-bold text-indigo-600 text-[11px]"
                                    >
                                        + {'{store_name}'} (اسم المتجر)
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => step3Form.setData('content', step3Form.data.content + ' {email} ')}
                                        className="px-2 py-1 bg-white dark:bg-slate-900 border border-indigo-300 rounded font-mono font-bold text-indigo-600 text-[11px]"
                                    >
                                        + {'{email}'} (البريد الإلكتروني)
                                    </button>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="content" className="text-sm font-bold">كود ومحتوى الـ HTML للرسالة *</Label>
                                    <textarea
                                        id="content"
                                        rows={14}
                                        value={step3Form.data.content}
                                        onChange={(e) => step3Form.setData('content', e.target.value)}
                                        className="w-full p-4 rounded-xl border border-slate-200 dark:border-slate-800 font-mono text-xs bg-slate-950 text-slate-100 focus:ring-2 focus:ring-indigo-500"
                                        required
                                    />
                                </div>

                                <div className="flex items-center justify-between pt-4 border-t">
                                    <Button type="button" variant="outline" onClick={() => setCurrentStep(2)}>
                                        السابقة (تحديد المتاجر)
                                    </Button>

                                    <div className="flex gap-2">
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            onClick={() => setPreviewModalOpen(true)}
                                            className="gap-2"
                                        >
                                            <Eye className="h-4 w-4" /> معاينة
                                        </Button>
                                        <Button type="submit" disabled={step3Form.processing} className="bg-indigo-600 hover:bg-indigo-700 text-white gap-2 font-bold px-6 h-11">
                                            {step3Form.processing ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
                                            <span>حفظ والانتقال للمراجعة والإطلاق ➔</span>
                                        </Button>
                                    </div>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                ) : null}

                {/* STEP 4: Review & Launch */}
                {currentStep === 4 ? (
                    <Card className="border-slate-200 dark:border-slate-800 shadow-sm space-y-6">
                        <CardHeader>
                            <CardTitle className="text-lg font-bold text-indigo-600 flex items-center gap-2">
                                <Rocket className="h-5 w-5" />
                                <span>الخطوة الرابعة: المراجعة والإطلاق النهائي</span>
                            </CardTitle>
                            <CardDescription>
                                راجع ملخص الحملة قبل بدء الإرسال عبر طابور Horizon
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div className="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border space-y-1">
                                    <div className="text-xs text-muted-foreground">اسم الحملة</div>
                                    <div className="font-bold text-slate-900 dark:text-slate-100">{step1Form.data.name}</div>
                                </div>

                                <div className="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border space-y-1">
                                    <div className="text-xs text-muted-foreground">إجمالي المتاجر والمستهدفين</div>
                                    <div className="font-bold text-indigo-600 text-lg">
                                        {selectedStoresMap.size + customEmails.length} مستهدف بريدي
                                    </div>
                                </div>

                                <div className="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border space-y-1">
                                    <div className="text-xs text-muted-foreground">قناة التواصل وتدفق الإرسال</div>
                                    <div className="font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                                        <Badge className="bg-indigo-600">Horizon Dedicated Queue</Badge>
                                    </div>
                                </div>
                            </div>

                            {/* Rendered HTML Email Box */}
                            <div className="space-y-2">
                                <h4 className="text-sm font-bold flex items-center gap-2">
                                    <Eye className="h-4 w-4 text-indigo-500" />
                                    <span>الشكل النهائي للرسالة التي ستصل للمستلم:</span>
                                </h4>
                                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 overflow-x-auto min-h-48">
                                    <div dangerouslySetInnerHTML={{ __html: renderRenderedPreview() }} />
                                </div>
                            </div>

                            <div className="flex items-center justify-between pt-4 border-t">
                                <Button type="button" variant="outline" onClick={() => setCurrentStep(3)}>
                                    تعديل محتوى الرسالة
                                </Button>

                                <div className="flex gap-3">
                                    <Link href="/notification-campaigns">
                                        <Button type="button" variant="secondary" className="gap-2">
                                            <Save className="h-4 w-4" /> حفظ كمسودة للمستقبل
                                        </Button>
                                    </Link>

                                    <Button
                                        type="button"
                                        onClick={launchCampaign}
                                        className="bg-emerald-600 hover:bg-emerald-700 text-white gap-2 font-bold px-8 h-12 shadow-lg text-base"
                                    >
                                        <Rocket className="h-5 w-5" />
                                        <span>إطلاق الحملة الآن عبر Horizon 🚀</span>
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ) : null}

                {/* HTML Live Preview Modal */}
                <Dialog open={previewModalOpen} onOpenChange={setPreviewModalOpen}>
                    <DialogContent dir="rtl" className="sm:max-w-2xl max-h-[85vh] overflow-y-auto">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2 text-indigo-600">
                                <Eye className="h-5 w-5" />
                                <span>معاينة حية لشكل رسالة البريد الإلكتروني</span>
                            </DialogTitle>
                            <DialogDescription>
                                يعرض كيفية ظهور كود الـ HTML للتاجر مع استبدال المتغيرات التفاعلية
                            </DialogDescription>
                        </DialogHeader>

                        <div className="p-4 rounded-xl border bg-slate-100 dark:bg-slate-900 my-2">
                            <div className="text-xs text-muted-foreground mb-2">العنوان (Subject): <strong>{step3Form.data.subject}</strong></div>
                            <div className="bg-white dark:bg-slate-950 p-4 rounded-lg border">
                                <div dangerouslySetInnerHTML={{ __html: renderRenderedPreview() }} />
                            </div>
                        </div>
            </div>
        </>
    );
}

CreateNotificationCampaign.layout = {
    breadcrumbs: [
        { title: 'حملات الإشعارات', href: '/notification-campaigns' },
        { title: 'إنشاء حملة' },
    ],
};
