<?php

namespace App\Http\Controllers;

use App\Http\Requests\CampaignRequest;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class CampaignController extends Controller
{
    /**
     * Display a listing of the resource.
     */
   public function getAll(Request $request): JsonResponse
{
    $query = Campaign::query()
        ->withSum(['donations as achieved_amount' => function ($q) {
            $q->where('status', 'completed');
        }], 'amount')
        ->withCount(['donations as donors_count' => function ($q) {
            $q->where('status', 'completed');
        }]);

    if ($request->has('category')) {
        $query->where('category', $request->category);
    }

    if ($request->has('status')) {
        $query->where('status', $request->status);
    }

    if ($request->has('is_emergency')) {
        $query->where('is_emergency', $request->boolean('is_emergency'));
    }

    $campaigns = $query->latest()->get()->map(function ($campaign) {
        $campaign->progress_percentage = $campaign->goal_amount > 0
            ? round(($campaign->achieved_amount / $campaign->goal_amount) * 100, 2)
            : 0;
        return $campaign;
    });

    return response()->json($campaigns);
}
    // POST /api/campaigns
    public function store(CampaignRequest $request): JsonResponse
    {
        $campaign = Campaign::create($request->validated());

        return response()->json($campaign, 201);
    }

    // GET /api/campaigns/{id}
  public function showCampaign($id): JsonResponse
{
    $campaign = Campaign::withSum(['donations as achieved_amount' => function ($q) {
            $q->where('status', 'completed');
        }], 'amount')
        ->withCount(['donations as donors_count' => function ($q) {
            $q->where('status', 'completed');
        }])
        ->find($id);

    if (!$campaign) {
        return response()->json([
            'message' => 'الحملة غير موجودة'
        ], 404);
    }

    $campaign->progress_percentage = $campaign->goal_amount > 0 ?round(($campaign->achieved_amount / $campaign->goal_amount) * 100, 2):0;
    return response()->json($campaign);
}
    // PUT /api/campaigns/{id}
    public function update(CampaignRequest $request,$id): JsonResponse
    {
        $campaign = Campaign::find($id);

    if (!$campaign) {
        return response()->json([
            'message' => 'الحملة غير موجودة'
        ], 404);
    }

    $campaign->update($request->validated());

    return response()->json($campaign);
    }
    // DELETE /api/campaigns/{id}
    public function destroy($id): JsonResponse
    {
        $campaign = Campaign::find($id);
        if (!$campaign) {
            return response()->json([
                'message' => 'الحملة غير موجودة'
            ], 404);
    }

    $campaign->delete();

    return response()->json([
        'message' => 'تم حذف الحملة بنجاح'
    ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */

    /**
     * Display the specified resource.
     */
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Campaign $campaign)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */

    /**
     * Remove the specified resource from storage.
     */
}
