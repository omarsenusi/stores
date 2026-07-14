<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScrapedStore;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StoreController extends Controller
{
    public function index(Request $request)
    {
        $query = ScrapedStore::query();

        if ($request->filled('filter')) {
            if ($request->filter === 'found') {
                $query->where('is_found', true);
            } elseif ($request->filter === 'not_found') {
                $query->where('is_found', false);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('store_name', 'like', "%{$search}%")
                  ->orWhere('domain', 'like', "%{$search}%")
                  ->orWhereRaw('CAST(contacts AS CHAR) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('CAST(full_settings AS CHAR) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($request->filled('maintenance')) {
            if ($request->maintenance === 'yes') {
                $query->where('full_settings->data->maintenance', true);
            } elseif ($request->maintenance === 'no') {
                $query->where(function ($q) {
                    $q->where('full_settings->data->maintenance', false)
                      ->orWhereNull('full_settings->data->maintenance');
                });
            }
        }

        $stores = $query->orderBy('id', 'desc')->paginate(50)->withQueryString();

        $statsRaw = ScrapedStore::selectRaw('
            COUNT(*) as total,
            SUM(is_found = 1) as found_count,
            SUM(is_found = 0) as not_found,
            SUM(JSON_EXTRACT(full_settings, "$.data.maintenance") = true) as maintenance,
            SUM(is_found = 1 AND (JSON_EXTRACT(full_settings, "$.data.maintenance") = false OR JSON_EXTRACT(full_settings, "$.data.maintenance") IS NULL)) as active
        ')->first();

        $stats = [
            'total' => (int) $statsRaw->total,
            'found' => (int) $statsRaw->found_count,
            'not_found' => (int) $statsRaw->not_found,
            'maintenance' => (int) $statsRaw->maintenance,
            'active' => (int) $statsRaw->active,
        ];

        return Inertia::render('stores/index', [
            'stores' => $stores,
            'filter' => $request->filter ?? '',
            'search' => $request->search ?? '',
            'maintenance' => $request->maintenance ?? '',
            'stats' => $stats,
        ]);
    }

    public function export(Request $request)
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\StoresExport($request), 'active_stores.xlsx');
    }
}
