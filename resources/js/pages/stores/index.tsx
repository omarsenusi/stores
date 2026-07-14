import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { useState } from 'react';
import { 
    Store as StoreIcon, 
    ExternalLink, 
    AlertCircle, 
    CheckCircle2, 
    Filter,
    ShoppingBag,
    Link as LinkIcon,
    CalendarClock
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Stores Dashboard',
        href: '/stores',
    },
];

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
    };
    filter: string;
}

export default function StoresIndex({ stores, filter }: Props) {
    const [currentFilter, setCurrentFilter] = useState(filter);

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
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Stores Dashboard" />

            {/* Premium Hero Section */}
            <div className="relative overflow-hidden bg-gradient-to-br from-indigo-900 via-purple-900 to-indigo-800 pb-32 pt-12 sm:pt-16">
                <div className="absolute inset-0 bg-[url('https://grainy-gradients.vercel.app/noise.svg')] opacity-20 mix-blend-overlay"></div>
                <div className="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-center">
                    <div className="inline-flex items-center justify-center rounded-full bg-white/10 px-3 py-1 text-sm font-medium text-indigo-100 backdrop-blur-md mb-6 ring-1 ring-white/20">
                        <ShoppingBag className="mr-2 h-4 w-4" />
                        Salla Stores Scraper
                    </div>
                    <h1 className="text-4xl font-extrabold tracking-tight text-white sm:text-5xl lg:text-6xl mb-4">
                        Discover & Analyze
                        <span className="block text-transparent bg-clip-text bg-gradient-to-r from-pink-300 to-indigo-300">
                            {' '}Digital Storefronts
                        </span>
                    </h1>
                    <p className="mx-auto mt-4 max-w-2xl text-lg text-indigo-100/80">
                        Monitor the status, extract product data, and identify issues across thousands of e-commerce stores in real-time.
                    </p>
                </div>
            </div>

            {/* Main Content Area */}
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 -mt-24 relative z-10 pb-12">
                {/* Control Panel / Glassmorphism */}
                <div className="rounded-2xl bg-white/70 backdrop-blur-xl p-4 sm:p-6 shadow-2xl ring-1 ring-gray-900/5 mb-8 flex flex-col sm:flex-row justify-between items-center gap-4 dark:bg-gray-800/80 dark:ring-white/10">
                    <div className="flex items-center gap-3">
                        <div className="rounded-lg bg-indigo-100 p-2 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-300">
                            <StoreIcon className="h-6 w-6" />
                        </div>
                        <div>
                            <h2 className="text-xl font-bold text-gray-900 dark:text-white">Scraped Stores Directory</h2>
                            <p className="text-sm text-gray-500 dark:text-gray-400">Viewing page {stores.current_page} of {stores.last_page}</p>
                        </div>
                    </div>
                    
                    <div className="relative w-full sm:w-64 group">
                        <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400 group-focus-within:text-indigo-500 transition-colors">
                            <Filter className="h-4 w-4" />
                        </div>
                        <select 
                            value={currentFilter}
                            onChange={handleFilterChange}
                            className="block w-full rounded-xl border-0 py-2.5 pl-10 pr-10 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 bg-white/50 backdrop-blur-sm transition-all hover:bg-white dark:bg-gray-900/50 dark:text-white dark:ring-gray-700 cursor-pointer"
                        >
                            <option value="">All Stores</option>
                            <option value="found">Active Stores (Found)</option>
                            <option value="not_found">Inactive Stores (404/405)</option>
                        </select>
                    </div>
                </div>

                {/* Data Grid */}
                {stores.data.length > 0 ? (
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {stores.data.map((store) => (
                            <div 
                                key={store.id} 
                                className="group relative flex flex-col rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl dark:bg-gray-800 dark:ring-gray-700 overflow-hidden"
                            >
                                {/* Status Banner */}
                                <div className={`h-2 w-full ${store.is_found ? 'bg-gradient-to-r from-emerald-400 to-teal-500' : 'bg-gradient-to-r from-rose-400 to-red-500'}`}></div>
                                
                                <div className="p-6 flex-1 flex flex-col">
                                    <div className="flex justify-between items-start mb-4">
                                        <div className="flex items-center gap-2">
                                            <span className="inline-flex items-center justify-center rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                ID: {store.store_id}
                                            </span>
                                        </div>
                                        {store.is_found ? (
                                            <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20">
                                                <CheckCircle2 className="h-3.5 w-3.5" />
                                                Active
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-500/20">
                                                <AlertCircle className="h-3.5 w-3.5" />
                                                Not Found
                                            </span>
                                        )}
                                    </div>

                                    <div className="mb-4 flex-1">
                                        {store.product_name ? (
                                            <>
                                                <h3 className="text-lg font-bold text-gray-900 dark:text-white line-clamp-1 mb-1" title={store.product_name}>
                                                    {store.product_name}
                                                </h3>
                                                {store.domain && (
                                                    <div className="flex items-center text-sm text-gray-500 dark:text-gray-400 mb-3 gap-1">
                                                        <LinkIcon className="h-3.5 w-3.5" />
                                                        <span className="truncate">{store.domain}</span>
                                                    </div>
                                                )}
                                                {store.product_description && (
                                                    <p className="text-sm text-gray-600 dark:text-gray-300 line-clamp-2 leading-relaxed">
                                                        {store.product_description}
                                                    </p>
                                                )}
                                            </>
                                        ) : (
                                            <div className="flex flex-col items-center justify-center h-full text-center space-y-3 py-6 opacity-60">
                                                <StoreIcon className="h-10 w-10 text-gray-400" />
                                                <span className="text-sm font-medium text-gray-500">No Product Data Available</span>
                                            </div>
                                        )}
                                    </div>

                                    <div className="mt-auto border-t border-gray-100 dark:border-gray-700/50 pt-4 space-y-3">
                                        {store.error_log && (
                                            <div className="rounded-lg bg-red-50 p-2.5 text-xs text-red-700 flex items-start gap-2 dark:bg-red-500/10 dark:text-red-400">
                                                <AlertCircle className="h-4 w-4 shrink-0 mt-0.5" />
                                                <span className="line-clamp-2" title={store.error_log}>{store.error_log}</span>
                                            </div>
                                        )}
                                        
                                        <div className="flex items-center justify-between text-xs text-gray-400 font-medium">
                                            <div className="flex items-center gap-1">
                                                <CalendarClock className="h-3.5 w-3.5" />
                                                {new Date(store.created_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}
                                            </div>
                                            {store.product_url ? (
                                                <a 
                                                    href={store.product_url} 
                                                    target="_blank" 
                                                    rel="noreferrer" 
                                                    className="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 transition-colors"
                                                >
                                                    Visit Store <ExternalLink className="h-3 w-3" />
                                                </a>
                                            ) : store.domain ? (
                                                <a 
                                                    href={`https://${store.domain}`} 
                                                    target="_blank" 
                                                    rel="noreferrer" 
                                                    className="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 transition-colors"
                                                >
                                                    Visit Domain <ExternalLink className="h-3 w-3" />
                                                </a>
                                            ) : null}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="rounded-3xl border-2 border-dashed border-gray-200 bg-white/50 p-16 text-center backdrop-blur-sm dark:border-gray-700 dark:bg-gray-800/50">
                        <StoreIcon className="mx-auto h-12 w-12 text-gray-300" />
                        <h3 className="mt-4 text-lg font-semibold text-gray-900 dark:text-white">No stores found</h3>
                        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">Adjust your filters or start a new scraping job.</p>
                    </div>
                )}

                {/* Modern Pagination */}
                {stores.links && stores.links.length > 3 && (
                    <div className="mt-10 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 pt-6">
                        <div className="hidden sm:block">
                            <p className="text-sm text-gray-700 dark:text-gray-300">
                                Showing page <span className="font-semibold text-gray-900 dark:text-white">{stores.current_page}</span> of{' '}
                                <span className="font-semibold text-gray-900 dark:text-white">{stores.last_page}</span>
                            </p>
                        </div>
                        <div className="flex flex-1 justify-between sm:justify-end gap-2 overflow-x-auto pb-2 sm:pb-0">
                            {stores.links.map((link, index) => {
                                const isPrevious = index === 0;
                                const isNext = index === stores.links.length - 1;
                                
                                if (!link.url && !link.active) {
                                    return (
                                        <span key={index} className="inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium text-gray-400 bg-gray-50 dark:bg-gray-800/50 dark:text-gray-600 cursor-not-allowed">
                                            {isPrevious ? 'Previous' : isNext ? 'Next' : link.label.replace('&laquo;', '').replace('&raquo;', '')}
                                        </span>
                                    );
                                }
                                
                                return (
                                    <button
                                        key={index}
                                        onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true, preserveState: true })}
                                        className={`inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-all focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${
                                            link.active 
                                                ? 'bg-indigo-600 text-white shadow-md hover:bg-indigo-700 shadow-indigo-500/20' 
                                                : 'bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-600 dark:hover:bg-gray-700'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: isPrevious ? 'Previous' : isNext ? 'Next' : link.label.replace('&laquo;', '').replace('&raquo;', '') }}
                                    />
                                );
                            })}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
