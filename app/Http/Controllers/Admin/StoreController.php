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

        $stores = $query->orderBy('id', 'desc')->paginate(50)->withQueryString();

        return Inertia::render('stores/index', [
            'stores' => $stores,
            'filter' => $request->filter ?? '',
        ]);
    }
}
