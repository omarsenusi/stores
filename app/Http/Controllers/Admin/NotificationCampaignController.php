<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessNotificationCampaignJob;
use App\Models\NotificationCampaign;
use App\Models\NotificationCampaignTarget;
use App\Models\ScrapedStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationCampaignController extends Controller
{
    public function index(Request $request): Response
    {
        $campaigns = NotificationCampaign::withCount(['targets'])
            ->latest()
            ->paginate(15);

        $stats = [
            'total' => NotificationCampaign::count(),
            'processing' => NotificationCampaign::where('status', 'processing')->count(),
            'queued' => NotificationCampaign::where('status', 'queued')->count(),
            'completed' => NotificationCampaign::where('status', 'completed')->count(),
            'draft' => NotificationCampaign::where('status', 'draft')->count(),
            'total_sent' => NotificationCampaign::sum('sent_count'),
            'total_failed' => NotificationCampaign::sum('failed_count'),
        ];

        return Inertia::render('notification-campaigns/index', [
            'campaigns' => $campaigns,
            'stats' => $stats,
        ]);
    }

    public function create(Request $request, ?NotificationCampaign $campaign = null): Response
    {
        $initialCampaign = null;

        if ($campaign && $campaign->exists) {
            $initialCampaign = $campaign->load(['targets']);
        } elseif ($request->has('draft_id')) {
            $draft = NotificationCampaign::find($request->get('draft_id'));
            if ($draft && $draft->status === 'draft') {
                $initialCampaign = $draft->load(['targets']);
            }
        }

        return Inertia::render('notification-campaigns/create', [
            'campaign' => $initialCampaign,
        ]);
    }

    public function storeStep1(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'campaign_id' => ['nullable', 'exists:notification_campaigns,id'],
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'in:email,sms,whatsapp'],
        ]);

        if (! empty($validated['campaign_id'])) {
            $campaign = NotificationCampaign::findOrFail($validated['campaign_id']);
            $campaign->update([
                'name' => $validated['name'],
                'channel' => $validated['channel'],
            ]);
        } else {
            $campaign = NotificationCampaign::create([
                'name' => $validated['name'],
                'channel' => $validated['channel'],
                'status' => 'draft',
                'step' => 1,
            ]);
        }

        return redirect()->route('notification-campaigns.create', ['campaign' => $campaign->id, 'step' => 2]);
    }

    public function storeStep2(Request $request, NotificationCampaign $campaign): RedirectResponse
    {
        $validated = $request->validate([
            'store_ids' => ['nullable', 'array'],
            'store_ids.*' => ['integer', 'exists:scraped_stores,id'],
            'custom_emails' => ['nullable', 'array'],
            'custom_emails.*' => ['email'],
        ]);

        $storeIds = $validated['store_ids'] ?? [];
        $customEmails = array_values(array_unique(filter_var_array($validated['custom_emails'] ?? [], FILTER_VALIDATE_EMAIL)));

        // Remove existing targets for clean update
        $campaign->targets()->delete();

        $targetData = [];

        // Add valid scraped stores with emails
        if (! empty($storeIds)) {
            $stores = ScrapedStore::whereIn('id', $storeIds)->get();
            foreach ($stores as $store) {
                $email = is_array($store->contacts) ? ($store->contacts['email'] ?? null) : null;
                if ($email) {
                    $targetData[] = [
                        'notification_campaign_id' => $campaign->id,
                        'scraped_store_id' => $store->id,
                        'email' => $email,
                        'store_name' => $store->store_name ?: $store->domain,
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        // Add custom emails
        foreach ($customEmails as $email) {
            $targetData[] = [
                'notification_campaign_id' => $campaign->id,
                'scraped_store_id' => null,
                'email' => $email,
                'store_name' => 'مستلم مخصص',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($targetData)) {
            foreach (array_chunk($targetData, 500) as $chunk) {
                NotificationCampaignTarget::insert($chunk);
            }
        }

        $totalTargets = count($targetData);

        $campaign->update([
            'custom_emails' => $customEmails,
            'total_targets' => $totalTargets,
            'step' => max($campaign->step, 2),
        ]);

        return redirect()->route('notification-campaigns.create', ['campaign' => $campaign->id, 'step' => 3]);
    }

    public function storeStep3(Request $request, NotificationCampaign $campaign): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ]);

        $campaign->update([
            'subject' => $validated['subject'],
            'content' => $validated['content'],
            'step' => max($campaign->step, 3),
        ]);

        return redirect()->route('notification-campaigns.create', ['campaign' => $campaign->id, 'step' => 4]);
    }

    public function launch(NotificationCampaign $campaign): RedirectResponse
    {
        if ($campaign->total_targets === 0) {
            return back()->withErrors(['stores' => 'لا يوجد مستهدفين في هذه الحملة.']);
        }

        $campaign->update([
            'status' => 'queued',
            'step' => 4,
        ]);

        ProcessNotificationCampaignJob::dispatch($campaign->id);

        return redirect()->route('notification-campaigns.index')->with('success', 'تم إطلاق الحملة بنجاح وسيتم إرسالها بالخلفية عبر Horizon.');
    }

    public function pause(NotificationCampaign $campaign): RedirectResponse
    {
        $campaign->update(['status' => 'paused']);

        return back()->with('success', 'تم إيقاف الحملة مؤقتاً.');
    }

    public function resume(NotificationCampaign $campaign): RedirectResponse
    {
        $campaign->update(['status' => 'queued']);
        ProcessNotificationCampaignJob::dispatch($campaign->id);

        return back()->with('success', 'تم استئناف إرسال الحملة.');
    }

    public function destroy(NotificationCampaign $campaign): RedirectResponse
    {
        $campaign->delete();

        return redirect()->route('notification-campaigns.index')->with('success', 'تم حذف الحملة بنجاح.');
    }

    public function stats(NotificationCampaign $campaign): JsonResponse
    {
        $targets = $campaign->targets()
            ->latest()
            ->paginate(50);

        return response()->json([
            'campaign' => $campaign,
            'targets' => $targets,
        ]);
    }

    public function apiStores(Request $request): JsonResponse
    {
        $query = ScrapedStore::query()
            ->whereNotNull('contacts->email')
            ->where('contacts->email', '!=', '');

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('store_name', 'like', "%{$search}%")
                    ->orWhere('domain', 'like', "%{$search}%")
                    ->orWhere('contacts->email', 'like', "%{$search}%");
            });
        }

        $stores = $query->select(['id', 'store_name', 'domain', 'contacts', 'store_logo'])
            ->latest()
            ->paginate(20);

        return response()->json($stores);
    }
}
