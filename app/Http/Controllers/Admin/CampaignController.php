<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessExcelCampaignJob;
use App\Jobs\ProcessGoogleCampaignJob;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CampaignController extends Controller
{
    public function index(Request $request)
    {
        $campaigns = Campaign::withCount('errors')
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        $statsRaw = Campaign::selectRaw('
            COUNT(*) as total,
            SUM(status = "processing") as processing,
            SUM(status = "completed") as completed,
            SUM(status = "failed") as failed,
            SUM(status = "cancelled") as cancelled,
            SUM(success_count) as total_success,
            SUM(failure_count) as total_failure,
            SUM(already_exists_count) as total_exists
        ')->first();

        $stats = [
            'total' => (int) ($statsRaw->total ?? 0),
            'processing' => (int) ($statsRaw->processing ?? 0),
            'completed' => (int) ($statsRaw->completed ?? 0),
            'failed' => (int) ($statsRaw->failed ?? 0),
            'cancelled' => (int) ($statsRaw->cancelled ?? 0),
            'total_success' => (int) ($statsRaw->total_success ?? 0),
            'total_failure' => (int) ($statsRaw->total_failure ?? 0),
            'total_exists' => (int) ($statsRaw->total_exists ?? 0),
        ];

        return Inertia::render('campaigns/index', [
            'campaigns' => $campaigns,
            'stats' => $stats,
        ]);
    }

    public function storeExcel(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'file' => 'required|file|mimes:xlsx,xls,csv|max:20480',
        ]);

        $file = $request->file('file');
        $path = $file->store('campaigns');

        $campaign = Campaign::create([
            'name' => $request->name,
            'type' => 'excel',
            'status' => 'pending',
            'status_message' => 'جارٍ تجهيز ملف Excel...',
            'file_path' => $path,
        ]);

        ProcessExcelCampaignJob::dispatch($campaign->id);

        return redirect()->back()->with('success', 'تم إنشاء حملة الاستيراد بنجاح وبدأت المعالجة');
    }

    public function storeGoogle(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'query' => 'required|string|max:255',
        ]);

        $campaign = Campaign::create([
            'name' => $request->name,
            'type' => 'google',
            'status' => 'pending',
            'status_message' => 'جارٍ الاتصال بمحرك البحث...',
            'search_query' => $request->input('query'),
        ]);

        ProcessGoogleCampaignJob::dispatch($campaign->id);

        return redirect()->back()->with('success', 'تم إنشاء حملة بحث Google بنجاح وبدأت المعالجة');
    }

    public function storeSerpApi(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'query' => 'required|string|max:255',
            'api_key' => 'nullable|string|max:255',
        ]);

        $campaign = Campaign::create([
            'name' => $request->name,
            'type' => 'serpapi',
            'status' => 'pending',
            'status_message' => 'جارٍ الاتصال بـ SerpApi...',
            'search_query' => $request->input('query'),
        ]);

        ProcessSerpApiCampaignJob::dispatch($campaign->id, $request->input('api_key'));

        return redirect()->back()->with('success', 'تم إنشاء حملة بحث SerpApi (Google) بنجاح وبدأت المعالجة');
    }


    public function stats(Campaign $campaign)
    {
        return response()->json([
            'campaign' => $campaign->fresh(),
            'errors' => $campaign->errors()->latest()->take(20)->get(),
        ]);
    }

    public function cancel(Campaign $campaign)
    {
        $campaign->update([
            'status' => 'cancelled',
            'status_message' => 'تم إلغاء الحملة بواسطة المستخدم',
        ]);

        return redirect()->back()->with('success', 'تم إلغاء الحملة بنجاح');
    }

    public function destroy(Campaign $campaign)
    {
        $campaign->delete();

        return redirect()->back()->with('success', 'تم حذف الحملة بنجاح');
    }

    public function errors(Campaign $campaign)
    {
        $errors = $campaign->errors()->orderBy('id', 'desc')->paginate(30);

        return response()->json([
            'campaign' => $campaign,
            'errors' => $errors,
        ]);
    }
}
