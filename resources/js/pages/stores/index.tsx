import { Head, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { 
    Store as StoreIcon, 
    ExternalLink, 
    AlertCircle, 
    CheckCircle2, 
    Filter,
    ShoppingBag,
    Link as LinkIcon,
    CalendarClock,
    List,
    SearchX,
    MessageCircle,
    Lock,
    Copy,
    Share2,
    Search,
    Unlock,
    Phone,
    Download,
    LayoutGrid
} from 'lucide-react';

interface Store {
    id: number;
    store_id: string;
    domain: string | null;
    store_name: string | null;
    store_logo: string | null;
    store_description: string | null;
    contacts: Record<string, string> | null;
    product_name: string | null;
    product_description: string | null;
    product_url: string | null;
    product_image: string | null;
    error_log: string | null;
    is_found: boolean;
    created_at: string;
    full_settings?: {
        data?: {
            store?: {
                url?: string;
                settings?: {
                    freelance_number?: string;
                };
                social?: Record<string, string>;
            };
            theme?: {
                name?: string;
                profile?: { id?: number; name?: string | null };
                mode?: string;
                translations_hash?: string;
                is_rtl?: boolean;
            };
            maintenance?: boolean;
        }
    };
}

interface Props {
    stores: {
        data: Store[];
        links: any[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filter: string;
    search: string;
    maintenance: string;
    stats: {
        total: number;
        found: number;
        not_found: number;
        maintenance: number;
        active: number;
    };
}

export default function StoresIndex({ stores, filter, search, maintenance, stats }: Props) {
    const [currentFilter, setCurrentFilter] = useState(filter);
    const [currentSearch, setCurrentSearch] = useState(search);
    const [currentMaintenance, setCurrentMaintenance] = useState(maintenance);
    const [viewMode, setViewMode] = useState<'grid' | 'table'>('grid');

    // Debounced search handling
    useEffect(() => {
        const timer = setTimeout(() => {
            if (currentSearch !== search) {
                router.get(
                    '/stores',
                    { filter: currentFilter, maintenance: currentMaintenance, search: currentSearch },
                    { preserveState: true, preserveScroll: true, replace: true }
                );
            }
        }, 500);
        return () => clearTimeout(timer);
    }, [currentSearch, search, currentFilter, currentMaintenance]);

    // Load view mode preference from localStorage
    useEffect(() => {
        const savedViewMode = localStorage.getItem('stores-view-mode');
        if (savedViewMode === 'table' || savedViewMode === 'grid') {
            setViewMode(savedViewMode);
        }
    }, []);

    const handleViewModeChange = (mode: 'grid' | 'table') => {
        setViewMode(mode);
        localStorage.setItem('stores-view-mode', mode);
    };

    // Auto-refresh data every 60 seconds
    useEffect(() => {
        const interval = setInterval(() => {
            router.reload({ only: ['stores', 'stats'], preserveScroll: true, preserveState: true });
        }, 60000);

        return () => clearInterval(interval);
    }, []);

    const handleFilterChange = (filterVal: string, maintVal: string) => {
        setCurrentFilter(filterVal);
        setCurrentMaintenance(maintVal);

        router.get(
            '/stores',
            { filter: filterVal, maintenance: maintVal, search: currentSearch },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    };

    return (
        <>
            <Head title="Stores Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                
                {/* Stats Section */}
                <div className="grid grid-cols-2 lg:grid-cols-5 gap-4">
                    <div className="flex flex-col gap-2 rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm dark:border-sidebar-border">
                        <div className="flex items-center gap-2">
                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <StoreIcon className="h-4 w-4" />
                            </div>
                            <p className="text-xs font-medium text-muted-foreground">Total Scraped</p>
                        </div>
                        <h3 className="text-xl font-bold">{stats.total.toLocaleString()}</h3>
                    </div>
                    
                    <div className="flex flex-col gap-2 rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm dark:border-sidebar-border">
                        <div className="flex items-center gap-2">
                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-500">
                                <CheckCircle2 className="h-4 w-4" />
                            </div>
                            <p className="text-xs font-medium text-muted-foreground">Store Found</p>
                        </div>
                        <h3 className="text-xl font-bold">{stats.found.toLocaleString()}</h3>
                    </div>

                    <div className="flex flex-col gap-2 rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm dark:border-sidebar-border">
                        <div className="flex items-center gap-2">
                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-500">
                                <Unlock className="h-4 w-4" />
                            </div>
                            <p className="text-xs font-medium text-muted-foreground">Store Active</p>
                        </div>
                        <h3 className="text-xl font-bold">{stats.active.toLocaleString()}</h3>
                    </div>

                    <div className="flex flex-col gap-2 rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm dark:border-sidebar-border">
                        <div className="flex items-center gap-2">
                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-amber-500/10 text-amber-500">
                                <Lock className="h-4 w-4" />
                            </div>
                            <p className="text-xs font-medium text-muted-foreground">Maintenance</p>
                        </div>
                        <h3 className="text-xl font-bold">{stats.maintenance.toLocaleString()}</h3>
                    </div>

                    <div className="flex flex-col gap-2 rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm dark:border-sidebar-border">
                        <div className="flex items-center gap-2">
                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                                <SearchX className="h-4 w-4" />
                            </div>
                            <p className="text-xs font-medium text-muted-foreground">Failed / Closed</p>
                        </div>
                        <h3 className="text-xl font-bold">{stats.not_found.toLocaleString()}</h3>
                    </div>
                </div>

                {/* Header & Controls Section */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 rounded-xl border border-sidebar-border/70 p-6 bg-card shadow-sm dark:border-sidebar-border">
                    <div className="flex items-center gap-3">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl border border-sidebar-border/70 bg-sidebar-accent text-foreground dark:border-sidebar-border">
                            <ShoppingBag className="h-6 w-6" />
                        </div>
                        <div>
                            <h2 className="text-xl font-bold tracking-tight">Scraped Data</h2>
                            <p className="text-sm text-muted-foreground">Manage and analyze your scraped e-commerce stores.</p>
                        </div>
                    </div>

                    <div className="flex flex-col md:flex-row gap-3 w-full md:w-auto">
                        {/* Search */}
                        <div className="relative w-full md:w-[250px]">
                            <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <Search className="h-4 w-4 text-muted-foreground" />
                            </div>
                            <input 
                                type="text"
                                placeholder="Search store name, phone, domain..."
                                value={currentSearch}
                                onChange={(e) => setCurrentSearch(e.target.value)}
                                className="flex h-10 w-full rounded-md border border-input bg-background pl-10 pr-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                            />
                        </div>

                        {/* Maintenance Filter */}
                        <div className="relative w-full md:w-[150px]">
                            <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <Lock className="h-4 w-4 text-muted-foreground" />
                            </div>
                            <select 
                                value={currentMaintenance}
                                onChange={(e) => handleFilterChange(currentFilter, e.target.value)}
                                className="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background pl-10 pr-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                            >
                                <option value="">Any Status</option>
                                <option value="yes">Under Maintenance</option>
                                <option value="no">Active</option>
                            </select>
                        </div>

                        {/* Status Filter */}
                        <div className="relative w-full md:w-[180px]">
                            <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <Filter className="h-4 w-4 text-muted-foreground" />
                            </div>
                            <select 
                                value={currentFilter}
                                onChange={(e) => handleFilterChange(e.target.value, currentMaintenance)}
                                className="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background pl-10 pr-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                            >
                                <option value="">All Scraped</option>
                                <option value="found">Active Stores (Found)</option>
                                <option value="not_found">Inactive / Failed</option>
                            </select>
                        </div>

                        {/* View Toggle */}
                        <div className="flex items-center rounded-md border border-input bg-background p-1 hidden sm:flex">
                            <button
                                onClick={() => handleViewModeChange('grid')}
                                className={`inline-flex h-8 items-center justify-center rounded-sm px-3 text-sm font-medium transition-colors ${
                                    viewMode === 'grid' 
                                    ? 'bg-muted text-foreground shadow-sm' 
                                    : 'text-muted-foreground hover:bg-muted/50'
                                }`}
                                title="Grid View"
                            >
                                <LayoutGrid className="h-4 w-4" />
                            </button>
                            <button
                                onClick={() => handleViewModeChange('table')}
                                className={`inline-flex h-8 items-center justify-center rounded-sm px-3 text-sm font-medium transition-colors ${
                                    viewMode === 'table' 
                                    ? 'bg-muted text-foreground shadow-sm' 
                                    : 'text-muted-foreground hover:bg-muted/50'
                                }`}
                                title="Table View"
                            >
                                <List className="h-4 w-4" />
                            </button>
                        </div>
                        
                        {/* Export Button */}
                        <a 
                            href={`/stores/export?filter=${currentFilter}&maintenance=${currentMaintenance}&search=${currentSearch}`}
                            className="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow transition-colors hover:bg-emerald-700 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring ml-auto shrink-0"
                        >
                            <Download className="h-4 w-4" />
                            <span className="hidden sm:inline">Export Excel</span>
                        </a>
                    </div>
                </div>

                {/* Content Section */}
                <div className="flex-1 rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border bg-background shadow-sm">
                    {stores.data.length > 0 ? (
                        viewMode === 'grid' ? (
                            // Grid View
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                {stores.data.map((store) => (
                                    <div 
                                        key={store.id} 
                                        className="group relative flex flex-col rounded-xl border border-border bg-card text-card-foreground shadow-sm transition-all hover:border-sidebar-accent hover:shadow-md"
                                    >
                                        <div className="p-5 flex-1 flex flex-col">
                                            <div className="flex items-center justify-between mb-3">
                                                <span className="inline-flex items-center rounded-md bg-secondary px-2 py-1 text-xs font-medium text-secondary-foreground">
                                                    ID: {store.store_id}
                                                </span>
                                                {store.is_found ? (
                                                    <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2 py-1 text-xs font-medium text-emerald-500">
                                                        <CheckCircle2 className="h-3.5 w-3.5" />
                                                        Active
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1.5 rounded-full bg-destructive/10 px-2 py-1 text-xs font-medium text-destructive">
                                                        <AlertCircle className="h-3.5 w-3.5" />
                                                        Failed
                                                    </span>
                                                )}
                                            </div>

                                            <div className="flex-1 space-y-2 mt-2">
                                                <div className="flex gap-2 mb-4">
                                                    {store.store_logo && (
                                                        <img src={store.store_logo} alt={store.store_name || 'Store Logo'} className="h-16 w-16 rounded-full object-cover border border-border bg-muted" title="Store Logo" />
                                                    )}
                                                    {store.product_image && (
                                                        <img src={store.product_image} alt={store.product_name || 'Product'} className="h-16 w-16 rounded-md object-cover border border-border bg-muted" title="Example Product" />
                                                    )}
                                                </div>
                                                {store.store_name || store.product_name || store.domain ? (
                                                    <>
                                                        <h3 className="font-semibold leading-none tracking-tight line-clamp-1" title={store.store_name || store.product_name || ''}>
                                                            {store.store_name || store.product_name || store.domain}
                                                        </h3>
                                                        {(store.full_settings?.data?.store?.url || store.domain) && (
                                                            <div className="flex items-center text-xs text-muted-foreground gap-1">
                                                                <LinkIcon className="h-3 w-3 shrink-0" />
                                                                <span className="truncate" title={store.full_settings?.data?.store?.url || store.domain || ''}>{store.full_settings?.data?.store?.url || store.domain}</span>
                                                            </div>
                                                        )}
                                                        {(store.store_description || store.product_description) && (
                                                            <p className="text-sm text-muted-foreground line-clamp-2 mt-2">
                                                                {store.store_description || store.product_description}
                                                            </p>
                                                        )}
                                                    </>
                                                ) : (
                                                    <div className="flex h-full flex-col items-center justify-center space-y-2 py-6 text-muted-foreground/50">
                                                        <StoreIcon className="h-8 w-8" />
                                                        <span className="text-xs font-medium">No Data Available</span>
                                                    </div>
                                                )}
                                            </div>

                                            <div className="mt-4 border-t pt-4 space-y-3">
                                                {store.error_log && (
                                                    <div className="flex items-start gap-2 rounded-md bg-destructive/10 p-2 text-xs text-destructive">
                                                        <AlertCircle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                                        <span className="line-clamp-2" title={store.error_log}>{store.error_log}</span>
                                                    </div>
                                                )}
                                                
                                                <div className="flex items-center justify-between text-xs text-muted-foreground">
                                                    <div className="flex items-center gap-1">
                                                        <CalendarClock className="h-3.5 w-3.5" />
                                                        {new Date(store.created_at).toLocaleDateString()}
                                                    </div>
                                                    <div className="flex items-center gap-3">
                                                        {store.contacts?.whatsapp && (
                                                            <a 
                                                                href={`https://wa.me/${store.contacts.whatsapp.replace(/[^0-9]/g, '')}`} 
                                                                target="_blank" 
                                                                rel="noreferrer" 
                                                                className="inline-flex items-center gap-1 font-medium text-emerald-500 hover:underline"
                                                                title="Contact on WhatsApp"
                                                            >
                                                                <MessageCircle className="h-4 w-4" />
                                                            </a>
                                                        )}
                                                        {store.product_url || store.full_settings?.data?.store?.url || store.domain ? (
                                                            <a 
                                                                href={store.product_url || store.full_settings?.data?.store?.url || `https://${store.domain}`} 
                                                                target="_blank" 
                                                                rel="noreferrer" 
                                                                className="inline-flex items-center gap-1 font-medium text-primary hover:underline"
                                                            >
                                                                Visit <ExternalLink className="h-3 w-3" />
                                                            </a>
                                                        ) : null}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            // Table View
                            <div className="overflow-x-auto rounded-lg border border-border">
                                <table className="w-full text-sm text-left">
                                    <thead className="text-xs text-muted-foreground uppercase bg-muted/50 border-b border-border">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">Store ID</th>
                                            <th className="px-4 py-3 font-medium">Status</th>
                                            <th className="px-4 py-3 font-medium">Store Info</th>
                                            <th className="px-4 py-3 font-medium">Domain</th>
                                            <th className="px-4 py-3 font-medium text-center">Maintenance</th>
                                            <th className="px-4 py-3 font-medium">Phone</th>
                                            <th className="px-4 py-3 font-medium">Socials & Other</th>
                                            <th className="px-4 py-3 font-medium">Theme</th>
                                            <th className="px-4 py-3 font-medium">Date</th>
                                            <th className="px-4 py-3 font-medium text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {stores.data.map((store) => (
                                            <tr key={store.id} className="bg-card hover:bg-muted/30 transition-colors">
                                                <td className="px-4 py-4 font-medium align-top">
                                                    {store.store_id}
                                                </td>
                                                <td className="px-4 py-4 align-top">
                                                    {store.is_found ? (
                                                        <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2 py-1 text-xs font-medium text-emerald-500 mb-1">
                                                            <CheckCircle2 className="h-3.5 w-3.5" /> Active
                                                        </span>
                                                    ) : (
                                                        <div className="flex flex-col gap-1">
                                                            <span className="inline-flex w-fit items-center gap-1.5 rounded-full bg-destructive/10 px-2 py-1 text-xs font-medium text-destructive">
                                                                <AlertCircle className="h-3.5 w-3.5" /> Failed
                                                            </span>
                                                            {store.error_log && (
                                                                <span className="text-[10px] text-destructive line-clamp-3 w-32 mt-1" title={store.error_log}>
                                                                    {store.error_log}
                                                                </span>
                                                            )}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-4 py-4 align-top">
                                                    <div className="flex gap-3">
                                                        {store.store_logo && (
                                                            <img src={store.store_logo} alt={store.store_name || ''} className="h-10 w-10 rounded-full object-cover border border-border bg-muted shrink-0" />
                                                        )}
                                                        <div className="flex flex-col min-w-0 max-w-[200px]">
                                                            <div className="flex items-center gap-2">
                                                            <span className="font-semibold truncate" title={store.store_name || store.domain || ''}>
                                                                {store.store_name || store.domain || '-'}
                                                            </span>
                                                            </div>
                                                            {store.full_settings?.data?.store?.settings?.freelance_number && (
                                                                <span className="text-[10px] text-muted-foreground mt-0.5 truncate">
                                                                    FL: {store.full_settings.data.store.settings.freelance_number}
                                                                </span>
                                                            )}
                                                            {store.store_description && (
                                                                <span className="text-[11px] text-muted-foreground mt-1.5 line-clamp-2" title={store.store_description}>
                                                                    {store.store_description}
                                                                </span>
                                                            )}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-4 align-top max-w-[150px]">
                                                    {store.full_settings?.data?.store?.url || store.domain ? (
                                                        <div className="flex items-center gap-1 text-xs text-muted-foreground">
                                                            <LinkIcon className="h-3 w-3 shrink-0" />
                                                            <span className="truncate" title={store.full_settings?.data?.store?.url || store.domain || ''}>
                                                                {store.full_settings?.data?.store?.url || store.domain}
                                                            </span>
                                                        </div>
                                                    ) : <span className="text-muted-foreground/50">-</span>}
                                                </td>
                                                <td className="px-4 py-4 align-top text-center">
                                                    {store.full_settings?.data?.maintenance === true ? (
                                                        <span className="inline-flex items-center gap-1 rounded-md bg-amber-500/10 px-2 py-1 text-xs font-medium text-amber-500" title="Maintenance Mode">
                                                            <Lock className="h-3.5 w-3.5" /> Yes
                                                        </span>
                                                    ) : store.is_found ? (
                                                        <span className="inline-flex items-center gap-1 rounded-md bg-emerald-500/10 px-2 py-1 text-xs font-medium text-emerald-500" title="Active">
                                                            <Unlock className="h-3.5 w-3.5" /> No
                                                        </span>
                                                    ) : <span className="text-muted-foreground/50">-</span>}
                                                </td>
                                                <td className="px-4 py-4 align-top max-w-[150px]">
                                                    {store.contacts?.whatsapp || store.contacts?.mobile ? (
                                                        <div className="flex flex-col gap-1.5">
                                                            {store.contacts.whatsapp && (
                                                                <div className="flex items-center gap-2 text-xs">
                                                                    <MessageCircle className="h-3.5 w-3.5 text-emerald-500 shrink-0" />
                                                                    <span className="truncate">{store.contacts.whatsapp}</span>
                                                                    <button onClick={() => navigator.clipboard.writeText(store.contacts?.whatsapp || '')} className="text-muted-foreground hover:text-primary shrink-0 ml-auto" title="Copy"><Copy className="h-3 w-3" /></button>
                                                                </div>
                                                            )}
                                                            {store.contacts.mobile && store.contacts.mobile !== store.contacts.whatsapp && (
                                                                <div className="flex items-center gap-2 text-xs">
                                                                    <Phone className="h-3.5 w-3.5 text-blue-500 shrink-0" />
                                                                    <span className="truncate">{store.contacts.mobile}</span>
                                                                    <button onClick={() => navigator.clipboard.writeText(store.contacts?.mobile || '')} className="text-muted-foreground hover:text-primary shrink-0 ml-auto" title="Copy"><Copy className="h-3 w-3" /></button>
                                                                </div>
                                                            )}
                                                        </div>
                                                    ) : <span className="text-muted-foreground/50">-</span>}
                                                </td>
                                                <td className="px-4 py-4 align-top max-w-[150px]">
                                                    <div className="space-y-2">
                                                        {store.contacts && Object.entries(store.contacts).filter(([k,v]) => k !== 'whatsapp' && k !== 'mobile').map(([key, value]) => (
                                                            <div key={key} className="flex items-center justify-between gap-3 text-xs border-b border-border/50 pb-1.5 last:border-0 last:pb-0">
                                                                <div className="flex items-center gap-1.5 capitalize font-medium text-muted-foreground shrink-0">
                                                                    <MessageCircle className="h-3 w-3" />
                                                                    {key}
                                                                </div>
                                                                <div className="flex items-center gap-2 min-w-0">
                                                                    <span className="truncate text-[10px]" title={value}>{value}</span>
                                                                    {value.startsWith('http') ? (
                                                                        <a href={value} target="_blank" rel="noreferrer" className="text-muted-foreground hover:text-primary shrink-0"><ExternalLink className="h-3 w-3" /></a>
                                                                    ) : (
                                                                        <button onClick={() => navigator.clipboard.writeText(value)} className="text-muted-foreground hover:text-primary shrink-0" title="Copy"><Copy className="h-3 w-3" /></button>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        ))}
                                                        {store.full_settings?.data?.store?.social && Object.entries(store.full_settings.data.store.social).map(([key, value]) => (
                                                            <div key={key} className="flex items-center justify-between gap-3 text-xs border-b border-border/50 pb-1.5 last:border-0 last:pb-0">
                                                                <div className="flex items-center gap-1.5 capitalize font-medium text-muted-foreground shrink-0">
                                                                    <Share2 className="h-3 w-3" />
                                                                    {key}
                                                                </div>
                                                                <div className="flex items-center gap-2 min-w-0">
                                                                    <span className="truncate text-[10px]" title={value}>{value}</span>
                                                                    <a href={value} target="_blank" rel="noreferrer" className="text-muted-foreground hover:text-primary shrink-0"><ExternalLink className="h-3 w-3" /></a>
                                                                </div>
                                                            </div>
                                                        ))}
                                                        {!store.full_settings?.data?.store?.social && !Object.keys(store.contacts || {}).some(k => k !== 'whatsapp' && k !== 'mobile') && (
                                                            <span className="text-muted-foreground/50 text-xs">-</span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-4 align-top w-48">
                                                    {store.full_settings?.data?.theme ? (
                                                        <div className="text-[11px] space-y-1.5 bg-muted/30 p-2 rounded-md">
                                                            <div className="flex items-center justify-between gap-2 border-b border-border/50 pb-1">
                                                                <span className="text-muted-foreground shrink-0">Name:</span>
                                                                <span className="font-medium truncate" title={store.full_settings.data.theme.name}>{store.full_settings.data.theme.name}</span>
                                                            </div>
                                                            <div className="flex items-center justify-between gap-2 border-b border-border/50 pb-1">
                                                                <span className="text-muted-foreground shrink-0">Profile ID:</span>
                                                                <span className="font-medium">{store.full_settings.data.theme.profile?.id || '-'}</span>
                                                            </div>
                                                            <div className="flex items-center justify-between gap-2">
                                                                <span className="text-muted-foreground shrink-0">Mode:</span>
                                                                <span className="font-medium capitalize">{store.full_settings.data.theme.mode}</span>
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <span className="text-muted-foreground/50 text-xs">-</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-4 text-muted-foreground text-xs whitespace-nowrap align-top">
                                                    {new Date(store.created_at).toLocaleDateString()}
                                                </td>
                                                <td className="px-4 py-4 text-right align-top">
                                                    {store.product_url ? (
                                                        <a 
                                                            href={store.product_url} 
                                                            target="_blank" 
                                                            rel="noreferrer" 
                                                            className="inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors hover:text-primary bg-primary/10 p-2"
                                                            title="Visit Product"
                                                        >
                                                            <ExternalLink className="h-4 w-4" />
                                                        </a>
                                                    ) : store.full_settings?.data?.store?.url || store.domain ? (
                                                        <a 
                                                            href={store.full_settings?.data?.store?.url || `https://${store.domain}`} 
                                                            target="_blank" 
                                                            rel="noreferrer" 
                                                            className="inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors hover:text-primary bg-primary/10 p-2"
                                                            title="Visit Store"
                                                        >
                                                            <ExternalLink className="h-4 w-4" />
                                                        </a>
                                                    ) : (
                                                        <span className="text-muted-foreground/30 inline-flex p-2"><ExternalLink className="h-4 w-4" /></span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )
                    ) : (
                        <div className="flex h-[300px] flex-col items-center justify-center rounded-xl border border-dashed border-sidebar-border/70 bg-sidebar-border/20">
                            <StoreIcon className="h-10 w-10 text-muted-foreground/50 mb-4" />
                            <h3 className="text-lg font-medium">No stores found</h3>
                            <p className="text-sm text-muted-foreground text-center mt-1">Adjust your filters or start a new scraping job.</p>
                        </div>
                    )}

                    {/* Pagination */}
                    {stores.links && stores.links.length > 3 && (
                        <div className="mt-6 flex items-center justify-between border-t border-border pt-4">
                            <p className="text-sm text-muted-foreground hidden sm:block">
                                Page <span className="font-medium text-foreground">{stores.current_page}</span> of{' '}
                                <span className="font-medium text-foreground">{stores.last_page}</span> 
                                <span className="mx-2 text-border">|</span>
                                Total records: <span className="font-medium text-foreground">{stores.total}</span>
                            </p>
                            
                            <div className="flex gap-1 overflow-x-auto w-full sm:w-auto justify-between sm:justify-end">
                                {stores.links.map((link, index) => {
                                    const isPrevious = index === 0;
                                    const isNext = index === stores.links.length - 1;
                                    const label = isPrevious ? 'Prev' : isNext ? 'Next' : link.label.replace('&laquo;', '').replace('&raquo;', '');
                                    
                                    if (!link.url && !link.active) {
                                        return (
                                            <span key={index} className="inline-flex h-9 items-center justify-center rounded-md border border-input bg-muted px-3 text-sm font-medium text-muted-foreground opacity-50 cursor-not-allowed">
                                                {label}
                                            </span>
                                        );
                                    }
                                    
                                    return (
                                        <button
                                            key={index}
                                            onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true, preserveState: true })}
                                            className={`inline-flex h-9 items-center justify-center rounded-md px-3 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring ${
                                                link.active 
                                                    ? 'bg-primary text-primary-foreground shadow hover:bg-primary/90' 
                                                    : 'border border-input bg-background hover:bg-accent hover:text-accent-foreground'
                                            }`}
                                        >
                                            {label}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

StoresIndex.layout = () => ({
    breadcrumbs: [
        {
            title: 'Stores Dashboard',
            href: '/stores',
        },
    ],
});
