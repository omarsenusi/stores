import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { useState } from 'react';

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

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <div className="flex justify-between items-center mb-6">
                                <h2 className="text-xl font-semibold">Scraped Stores</h2>
                                
                                <select 
                                    value={currentFilter}
                                    onChange={handleFilterChange}
                                    className="border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">All Stores</option>
                                    <option value="found">Found Stores (Active)</option>
                                    <option value="not_found">Not Found (404/405)</option>
                                </select>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Store ID</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product Info</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Errors</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Added</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {stores.data.length > 0 ? (
                                            stores.data.map((store) => (
                                                <tr key={store.id}>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {store.store_id}
                                                    </td>
                                                    <td className="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                                                        {store.product_name ? (
                                                            <div>
                                                                <div className="font-semibold text-gray-900 truncate" title={store.product_name}>{store.product_name}</div>
                                                                {store.product_url && (
                                                                    <a href={store.product_url} target="_blank" rel="noreferrer" className="text-blue-600 hover:underline block truncate" title={store.product_url}>
                                                                        {store.domain || 'View Product'}
                                                                    </a>
                                                                )}
                                                            </div>
                                                        ) : store.domain ? (
                                                            <a href={`https://${store.domain}`} target="_blank" rel="noreferrer" className="text-blue-600 hover:underline">
                                                                {store.domain}
                                                            </a>
                                                        ) : (
                                                            <span className="text-gray-400">N/A</span>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm">
                                                        {store.is_found ? (
                                                            <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                                Found
                                                            </span>
                                                        ) : (
                                                            <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                                Not Found
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4 text-sm text-red-500 max-w-xs truncate" title={store.error_log || ''}>
                                                        {store.error_log || '-'}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        {new Date(store.created_at).toLocaleDateString()}
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={4} className="px-6 py-4 text-center text-sm text-gray-500">
                                                    No stores found.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {/* Pagination (Simple) */}
                            <div className="mt-4 flex justify-between items-center">
                                <div className="text-sm text-gray-700">
                                    Page {stores.current_page} of {stores.last_page}
                                </div>
                                <div className="flex gap-2">
                                    {stores.links.map((link, index) => (
                                        <button
                                            key={index}
                                            onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true, preserveState: true })}
                                            disabled={!link.url || link.active}
                                            className={`px-3 py-1 border rounded ${
                                                link.active ? 'bg-indigo-50 border-indigo-500 text-indigo-600' : 
                                                !link.url ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'
                                            }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
