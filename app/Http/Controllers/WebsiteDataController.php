<?php

namespace App\Http\Controllers;

use App\Mail\AidApplicationReceivedMail;
use App\Mail\DonationReceivedMail;
use App\Mail\VolunteerReceivedMail;
use App\Models\AidApplication;
use App\Models\BeneficiaryProfile;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\DonorProfile;
use App\Models\Profile;
use App\Models\Type;
use App\Models\User;
use App\Models\VolunterProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class WebsiteDataController extends Controller
{
public function summaryStats(): JsonResponse
{
    return response()->json([
        'status' => 'success',
        'data' => [
            'volunteers_count' => VolunterProfile::count(),
            'donors_count' => DonorProfile::count(),
            'beneficiaries_count' => BeneficiaryProfile::count(),
            'total_donated_amount' => (float) Donation::where('status', 'مكتمل')->sum('amount'),
            'active_campaigns_count' => Campaign::active()->count(),
        ]
    ], 200);
}
public function index(Request $request): JsonResponse
    {
       $query = Campaign::query();
    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    if ($request->filled('category')) {
        $query->byCategory($request->category);
    }

    if ($request->has('is_emergency')) {
        $query->where('is_emergency', $request->boolean('is_emergency'));
    }

    if ($request->filled('search')) {
        $query->where('title', 'like', '%' . $request->search . '%');
    }

    $campaigns = $query->with('media')->latest()->get();
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
                $query->where('status', 'مكتمل')
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
public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'city_id'   => 'required|exists:cities,id',
            'domain_id' => 'required|exists:domains,id',
            'phone'     => 'nullable|string|unique:profiles,phone',
        ]);

        DB::beginTransaction();
        try {
            $user = User::create([
                'name'        => $validated['name'],
                'email'       => $validated['email'],
                'password'    => Hash::make(Str::random(12)),
                'role'        => 'volunteer',
                'is_active'   => false,
            ]);
            Profile::create([
                'user_id' => $user->id,
                'city_id' => $validated['city_id'],
                'phone'   => $validated['phone'] ?? '09' . rand(10000000, 99999999),
                'gender'  => 'ذكر',
            ]);
            $volunteerProfile = VolunterProfile::create([
                'user_id'         => $user->id,
                'Favorite_period' => 'صباحاً',
                'status'          => 'متاح',
                'Commitment_type' => 'منتظم',
                'Educational_level' => 'بكالوريوس',
            ]);
            $volunteerProfile->domains()->attach($validated['domain_id']);

            DB::commit();
            Mail::to($user->email)->send(new VolunteerReceivedMail($user->name));
            return response()->json([
                'status'  => 'success',
                'message' => 'تم تقديم طلب التطوع بنجاح وإرسال رسالة تأكيد إلى بريدك الإلكتروني.'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء تقديم الطلب، يرجى المحاولة لاحقاً.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    public function donate(Request $request): JsonResponse
    {
        // 1. التحقق من صحة البيانات المدخلة
        $validated = $request->validate([
            'campaign_id'    => 'required|exists:campaigns,id',
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|max:255',
            'amount'         => 'required|numeric|min:1',
            'payment_method' => 'required|in:stripe,paypal,tap,moyasar,mada,apple_pay,google_pay,crypto,payerurl',
            'is_anonymous'   => 'nullable|boolean',
        ]);

        // 2. جلب الحملة والتحقق من حالتها
        $campaign = Campaign::findOrFail($validated['campaign_id']);

        // التحقق مما إذا كانت الحملة نشطة (تدعم التسمية بالإنجليزية "active" أو بالعربية "نشطة")
        if (!in_array($campaign->status, ['active', 'نشطة'])) {
            $statusMessages = [
                'مكتمل'     => 'هذه الحملة مكتملة بالكامل ولم تعد تستقبل تبرعات.',
                'مغلقة'     => 'هذه الحملة مغلقة حالياً ولا تستقبل التبرعات.',
                'ملغاة'     => 'تم إلغاء هذه الحملة.',
            ];
            $message = $statusMessages[$campaign->status] ?? 'لا يمكن التبرع لهذه الحملة لأنها غير نشطة.';
            return response()->json([
                'status'  => 'error',
                'message' => $message
            ], 400);
        }

        DB::beginTransaction();
        try {
            $user = User::firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name'        => $validated['name'],
                    'password'    => Hash::make(Str::random(12)),
                    'role'        => 'Donor',
                    'is_active'   => true,
                    'is_verified' => true,
                ]
            );
            $donorProfile = DonorProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'donor_type'   => 'فردي',
                    'is_anonymous' => $validated['is_anonymous'] ?? false,
                    'bio'          => 'متبرع',
                ]
            );

            $donation = Donation::create([
                'donor_id'       => $donorProfile->id,
                'campaign_id'    => $campaign->id,
                'amount'         => $validated['amount'],
                'currency'       => 'USD',
                'payment_method' => $validated['payment_method'],
                'status'         => 'مكتمل',
                'is_anonymous'   => $validated['is_anonymous'] ?? false,
                'donated_at'     => now(),
            ]);

            $donorProfile->addDonation($validated['amount']);

            $campaign->updateCollectedAmount();

            DB::commit();
           Mail::to($user->email)->send(new DonationReceivedMail($donation, $campaign, $user->name));
            return response()->json([
                'status'  => 'success',
                'message' => 'تم التبرع بنجاح، شكراً لمساهمتك!',
                'data'    => [
                    'donation_id'        => $donation->id,
                    'amount'             => $donation->amount,
                    'campaign_title'     => $campaign->title,
                    'collected_amount'   => $campaign->collected_amount,
                    'progress_percentage'=> $campaign->progress_percentage,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء معالجة التبرع.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    public function apply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|max:255',
            'type_id'     => 'required|integer|exists:types,id',
            'description' => 'nullable|string',
            'is_urgent'   => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $typeModel = Type::findOrFail($validated['type_id']);
            $user = User::firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name'        => $validated['name'],
                    'password'    => Hash::make(Str::random(12)),
                    'role'        => 'Beneficiary',
                    'is_active'   => true,
                    'is_verified' => true,
                ]
            );
            $beneficiaryProfile = BeneficiaryProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'marital_status' => 'أعزب',
                    'status'         => 'قيد المراجعة',
                ]
            );

            // 4. إنشاء طلب المساعدة
            $application = AidApplication::create([
                'beneficiary_profile_id' => $beneficiaryProfile->id,
                'user_id'                => $user->id,
                'type'                  => $typeModel->name,
               'description'            => $validated['description'],
                'is_urgent'              => $validated['is_urgent'] ?? false,
                'status'                 => 'قيد المراجعة',
                'application_date'       => now()->toDateString(),
            ]);

            DB::commit();

            Mail::to($user->email)->send(new AidApplicationReceivedMail($application, $user->name));

            return response()->json([
                'status'  => 'success',
                'message' => 'تم تقديم طلب المساعدة بنجاح، وتم إرسال رسالة تأكيد إلى بريدك الإلكتروني.',
                'data'    => [
                    'application_id' => $application->id,
                    'type'           => $application->type,
                    'is_urgent'      => $application->is_urgent,
                    'status'         => $application->status,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء تقديم الطلب، يرجى المحاولة لاحقاً.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
