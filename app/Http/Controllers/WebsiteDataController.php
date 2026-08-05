<?php

namespace App\Http\Controllers;

use App\Models\BeneficiaryProfile;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\DonorProfile;
use App\Models\VolunterProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebsiteDataController extends Controller
{
    // داخل WebsiteDataController
public function summaryStats(): JsonResponse
{
    return response()->json([
        'status' => 'success',
        'data' => [
            'volunteers_count' => VolunterProfile::count(),
            'donors_count' => DonorProfile::count(),
            'beneficiaries_count' => BeneficiaryProfile::count(),
            'total_donated_amount' => (float) Donation::where('status', 'completed')->sum('amount'),
            'active_campaigns_count' => Campaign::active()->count(),
        ]
    ], 200);
}
public function index(Request $request): JsonResponse
    {
        $query = Campaign::active();

        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }
        if ($request->has('is_emergency')) {
            $query->where('is_emergency', $request->boolean('is_emergency'));
        }
        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $campaigns = $query->with('media')->latest()->get(); // افتراضياً 9 حملات في الصفحة
        return response()->json([
            'status' => 'success',
            'data'   => $campaigns
        ], 200);
    }
    public function show($id): JsonResponse
    {
        $campaign = Campaign::with([
            'media',
            'updates',
            'donations' => function ($query) {
                $query->completed()
                    ->latest()
                    ->take(5)
                    ->with('donor.user:id,name');
            }
        ])->find($id);

        if (!$campaign) {
            return response()->json([
                'status'  => 'error',
                'message' => 'الحملة المطلوبة غير موجودة'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $campaign
        ], 200);
    }
    public function topVolunteers(Request $request): JsonResponse
{
    // تحديد عدد المتطوعين المراد عرضهم (افتراضياً 5)
    $limit = $request->get('limit', 5);

    $topVolunteers = VolunterProfile::with([
            'user:id,name',                             // جلب اسم المستخدم
            'user.profile:id,user_id,personal_photo'
        ])
        ->select([
            'id', 'user_id', 'points', 'total_hours',
            'rank', 'Educational_level', 'bio'
        ])
        ->orderByDesc('points')
        ->orderByDesc('total_hours')
        ->take($limit)
        ->get();

    return response()->json([
        'status' => 'success',
        'data'   => $topVolunteers
    ], 200);
}
}

