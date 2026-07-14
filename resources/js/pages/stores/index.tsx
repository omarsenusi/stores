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
    LayoutGrid,
    List,
    SearchX
} from 'lucide-react';

interface Store {
    id: number;
    store_id: string;
    domain: string | null;
    product_name: string | null;
    product_description: string | null;
    product_url: string | null;
    error_log: string | null;
    is_found: boolean;
    created_at: string;
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
    stats: {
        total: number;
        found: number;
        not_found: number;
    };
}

export default function StoresIndex({ stores, filter, stats }: Props) {
    const [currentFilter, setCurrentFilter] = useState(filter);
    const [viewMode, setViewMode] = useState<'grid' | 'table'>('grid');

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

    const handleFilterChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const value = e.target.value;
        setCurrentFilter(value);

        router.get(
            '/stores',
            { filter: value },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    };

    return (
        <>
            <Head title="Stores Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                
                {/* Stats Section */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="flex items-center gap-4 rounded-xl border border-sidebar-border/70 bg-card p-6 shadow-sm dark:border-sidebar-border">
                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <StoreIcon className="h-6 w-6" />
                        </div>
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Total Stores Checked</p>
                            <h3 className="text-2xl font-bold">{stats.total.toLocaleString()}</h3>
                        </div>
                    </div>
                    
                    <div className="flex items-center gap-4 rounded-xl border border-sidebar-border/70 bg-card p-6 shadow-sm dark:border-sidebar-border">
                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-500">
                            <CheckCircle2 className="h-6 w-6" />
                        </div>
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Active Stores (Found)</p>
                            <h3 className="text-2xl font-bold">{stats.found.toLocaleString()}</h3>
                        </div>
                    </div>

                    <div className="flex items-center gap-4 rounded-xl border border-sidebar-border/70 bg-card p-6 shadow-sm dark:border-sidebar-border">
                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                            <SearchX className="h-6 w-6" />
                        </div>
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Inactive / Failed</p>
                            <h3 className="text-2xl font-bold">{stats.not_found.toLocaleString()}</h3>
                        </div>
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

                    <div className="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                        {/* View Toggle */}
                        <div className="flex items-center rounded-md border border-input bg-background p-1">
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

                        {/* Filter */}
                        <div className="relative w-full sm:w-auto">
                            <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <Filter className="h-4 w-4 text-muted-foreground" />
                            </div>
                            <select 
                                value={currentFilter}
                                onChange={handleFilterChange}
                                className="flex h-10 w-full sm:w-[200px] items-center justify-between rounded-md border border-input bg-background pl-10 pr-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                            >
                                <option value="">All Stores</option>
                                <option value="found">Active Stores (Found)</option>
                                <option value="not_found">Inactive / Failed</option>
                            </select>
                        </div>
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

                                            <div className="flex-1 space-y-2">
                                                {store.product_name ? (
                                                    <>
                                                        <h3 className="font-semibold leading-none tracking-tight line-clamp-1" title={store.product_name}>
                                                            {store.product_name}
                                                        </h3>
                                                        {store.domain && (
                                                            <div className="flex items-center text-xs text-muted-foreground gap-1">
                                                                <LinkIcon className="h-3 w-3" />
                                                                <span className="truncate">{store.domain}</span>
                                                            </div>
                                                        )}
                                                        {store.product_description && (
                                                            <p className="text-sm text-muted-foreground line-clamp-2 mt-2">
                                                                {store.product_description}
                                                            </p>
                                                        )}
                                                    </>
                                                ) : (
                                                    <div className="flex h-full flex-col items-center justify-center space-y-2 py-6 text-muted-foreground/50">
                                                        <StoreIcon className="h-8 w-8" />
                                                        <span className="text-xs font-medium">No Product Data</span>
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
                                                    {store.product_url ? (
                                                        <a 
                                                            href={store.product_url} 
                                                            target="_blank" 
                                                            rel="noreferrer" 
                                                            className="inline-flex items-center gap-1 font-medium text-primary hover:underline"
                                                        >
                                                            Visit <ExternalLink className="h-3 w-3" />
                                                        </a>
                                                    ) : store.domain ? (
                                                        <a 
                                                            href={`https://${store.domain}`} 
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
                                            <th className="px-4 py-3 font-medium">Product Name</th>
                                            <th className="px-4 py-3 font-medium">Domain</th>
                                            <th className="px-4 py-3 font-medium">Error / Log</th>
                                            <th className="px-4 py-3 font-medium">Date</th>
                                            <th className="px-4 py-3 font-medium text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {stores.data.map((store) => (
                                            <tr key={store.id} className="bg-card hover:bg-muted/30 transition-colors">
                                                <td className="px-4 py-3 font-medium">
                                                    {store.store_id}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {store.is_found ? (
                                                        <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2 py-1 text-xs font-medium text-emerald-500">
                                                            <CheckCircle2 className="h-3.5 w-3.5" /> Active
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1.5 rounded-full bg-destructive/10 px-2 py-1 text-xs font-medium text-destructive">
                                                            <AlertCircle className="h-3.5 w-3.5" /> Failed
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 max-w-[200px] truncate" title={store.product_name || ''}>
                                                    {store.product_name || <span className="text-muted-foreground/50">-</span>}
                                                </td>
                                                <td className="px-4 py-3 max-w-[150px] truncate" title={store.domain || ''}>
                                                    {store.domain ? (
                                                        <span className="inline-flex items-center gap-1 text-muted-foreground">
                                                            <LinkIcon className="h-3 w-3 shrink-0" />
                                                            {store.domain}
                                                        </span>
                                                    ) : <span className="text-muted-foreground/50">-</span>}
                                                </td>
                                                <td className="px-4 py-3 max-w-[200px]">
                                                    {store.error_log ? (
                                                        <div className="text-xs text-destructive truncate" title={store.error_log}>
                                                            {store.error_log}
                                                        </div>
                                                    ) : <span className="text-muted-foreground/50">-</span>}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground whitespace-nowrap">
                                                    {new Date(store.created_at).toLocaleDateString()}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    {store.product_url ? (
                                                        <a 
                                                            href={store.product_url} 
                                                            target="_blank" 
                                                            rel="noreferrer" 
                                                            className="inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors hover:text-primary"
                                                        >
                                                            <ExternalLink className="h-4 w-4" />
                                                        </a>
                                                    ) : store.domain ? (
                                                        <a 
                                                            href={`https://${store.domain}`} 
                                                            target="_blank" 
                                                            rel="noreferrer" 
                                                            className="inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors hover:text-primary"
                                                        >
                                                            <ExternalLink className="h-4 w-4" />
                                                        </a>
                                                    ) : (
                                                        <span className="text-muted-foreground/30"><ExternalLink className="h-4 w-4" /></span>
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
