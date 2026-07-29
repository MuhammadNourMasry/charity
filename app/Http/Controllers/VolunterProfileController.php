<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\VolunteerProfileRequest;
use App\Models\VolunteerCertificate;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\User;
use App\Models\VolunterProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class VolunterProfileController extends Controller
{
    public function completeProfile(VolunteerProfileRequest $request)
    {
        $user = $request->user();

        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'code' => 'EMAIL_NOT_VERIFIED',
                'success' => false,
                'message' => 'Please verify your email first using OTP.',
            ], 403);
        }

        if ($user->role !== 'volunteer') {
            return response()->json([
                'code' => 'INVALID_ROLE',
                'success' => false,
                'message' => 'Your role is not Volunteer.',
            ], 403);
        }

        if ($user->volunteer) {
            return response()->json([
                'code' => 'PROFILE_ALREADY_EXISTS',
                'success' => false,
                'message' => 'You already have a volunteer profile.',
            ], 400);
        }

        try {
            $validated = $request->validated();
            $personalPhotoPath = null;
            $certificatePaths = [];

            if ($request->hasFile('certificates')) {
                foreach ($request->file('certificates') as $index => $file) {
                    $certificatePaths[] = [
                        'path' => $file->store('certificates', 'public'),
                        'name' => $validated['certificates_names'][$index] ?? null,
                    ];
                }
            }

            if ($request->hasFile('Personal_photo')) {
                $personalPhotoPath = $request->file('Personal_photo')->store('profiles', 'public');
            }

            $photoIdPath = null;
            if ($request->hasFile('photo_id')) {
                $photoIdPath = $request->file('photo_id')->store('photo_ids', 'public');
            }

            $result = DB::transaction(function () use ($user, $validated, $personalPhotoPath, $photoIdPath, $certificatePaths) {

                $profile = Profile::create([
                    'user_id'        => $user->id,
                    'city_id'        => $validated['city_id'],
                    'phone'          => $validated['phone'],
                    'photo_id'       => $photoIdPath,
                    'birth_date'     => $validated['birth_date'],
                    'gender'         => $validated['gender'],
                    'Personal_photo' => $personalPhotoPath ?? null,
                ]);

                $volunteer = VolunterProfile::create([
                    'user_id'              => $user->id,
                    'Favorite_period'      => $validated['Favorite_period'],
                    'Commitment_type'      => $validated['Commitment_type'],
                    'Educational_level'    => $validated['Educational_level'],
                    'experience_years'     => $validated['experience_years'],
                    'car'                  => $validated['car'],
                    'previous_voluntering' => $validated['previous_voluntering'],
                    'previous_work_place'  => $validated['previous_voluntering'] ? ($validated['previous_work_place'] ?? null) : null,
                    'bio'                  => $validated['bio'] ?? null,
                    'facebook'             => $validated['facebook'] ?? null,
                    'linkedin'             => $validated['linkedin'] ?? null,
                    'total_hours'          => 0,
                    'status'               => 'متاح',
                ]);

                foreach ($certificatePaths as $cert) {
                    VolunteerCertificate::create([
                        'volunteer_id' => $volunteer->id,
                        'title' => $cert['name'] ?? 'شهادة تطوع',
                        'file_path' => $cert['path'],
                        'hours_required' => 0,
                        'hours_completed' => 0,
                        'is_active' => true,
                    ]);
                }

                $volunteer->domains()->sync($validated['domain_ids']);
                $volunteer->days()->sync($validated['day_ids']);
                $volunteer->categories()->sync($validated['category_ids']);
                $volunteer->languages()->sync($validated['language_ids']);
                $volunteer->skills()->sync($validated['skill_ids']);

                // ✅ ✅ ✅ تحديث user مع profile_completed = true
                 $user->update([
        'is_active' => true,
        'profile_completed' => true,  // ✅ هذا هو الحل
    ]);
                return ['profile' => $profile, 'volunteer' => $volunteer];
            });

            return response()->json([
                'code'    => '201',
                'success' => true,
                'message' => 'Volunteer profile completed successfully.',
                'data'    => [
                    'user' => $user->fresh()->only(['id', 'name', 'email', 'role', 'profile_completed']), // ✅ أضف profile_completed
                    'general_profile' => [
                        ...$result['profile']->toArray(),
                        'city_name' => $result['profile']->city->name,
                    ],
                    'volunteer_details' => $result['volunteer']->load(['domains', 'days', 'categories', 'languages', 'skills', 'certificates']),
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'code'    => '500',
                'success' => false,
                'message' => 'An error occurred while saving profile data.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    public function getVolunteers(Request $request)
{
    $volunteers = User::with([
        'profile.city',
        'volunteer.skills'
    ])
    ->where('role', 'volunteer')
    ->get();

    return response()->json([
        'success' => true,
        'data' => $volunteers->map(function ($user) {

            return [
                'id' => $user->id,
                'name' => $user->name,

                'city' => optional(optional($user->profile)->city)->name,

                'phone' => optional($user->profile)->phone,

                'skills' => optional($user->volunteer)
                    ? $user->volunteer->skills->pluck('name')->values()
                    : [],

                'total_hours' => optional($user->volunteer)->total_hours ?? 0,

                'status' => optional($user->volunteer)->status,

                'photo' => optional($user->profile)->personal_photo,
            ];
        })
    ]);
}
public function store(Request $request)
{
    $validated = $request->validate([
        'name'     => 'required|string|max:255',
        'phone'    => 'required|string|unique:profiles,phone',
        'city'     => 'required|exists:cities,id',
        'status'   => 'required|in:مشغول,متاح,غير متاح',
        'skills'   => 'nullable|array',
        'skills.*' => 'exists:skills,id',
    ]);

    $volunteerProfile = DB::transaction(function () use ($validated) {

        $tempPassword = Str::random(10);
        $email = 'vol_' . Str::random(6) . '@placeholder.local';

        $user = User::create([
            'name'      => $validated['name'],
            'email'     => $email,
            'password'  => Hash::make($tempPassword),
            'role'      => 'volunteer',
            'is_active' => true,
        ]);

        $user->profile()->create([
            'city_id' => $validated['city'],
            'phone'   => $validated['phone'],
        ]);

        $volunteerProfile = $user->volunterProfile()->create([
            'status'            => $validated['status'],
            'Favorite_period'   => 'صباحاً',
            'Commitment_type'   => 'مرة بمرة',
            'Educational_level' => 'بكالوريوس',
        ]);

        if (!empty($validated['skills'])) {
            $volunteerProfile->skills()->sync($validated['skills']); // ✅ مباشرة
        }

        return $volunteerProfile;
    });

    return response()->json([
        'message' => 'تمت إضافة المتطوع بنجاح',
        'data' => [
            'id'     => $volunteerProfile->id,
            'name'   => $volunteerProfile->user->name,
            'phone'  => $volunteerProfile->user->profile->phone,
            'city'   => $volunteerProfile->user->profile->city->name,
            'status' => $volunteerProfile->status,
            'skills' => $volunteerProfile->skills->pluck('name'),
        ],
    ], 201);
}
public function destroy($id)
{
    $volunteerProfile = VolunterProfile::find($id);

    if (!$volunteerProfile) {
        return response()->json([
            'message' => 'المتطوع غير موجود',
        ], 404);
    }

    $user = $volunteerProfile->user;

    DB::transaction(function () use ($volunteerProfile, $user) {
        $volunteerProfile->skills()->detach();
        optional($user->profile)->delete();
        $volunteerProfile->delete();
        $user->delete();
    });

    return response()->json([
        'message' => 'تم حذف المتطوع بنجاح',
    ]);
}
public function updateStatus(Request $request, $id)
{
    $validated = $request->validate([
        'status' => 'required|in:مشغول,متاح,غير متاح',
    ]);

    $volunteerProfile = VolunterProfile::find($id);

    if (!$volunteerProfile) {
        return response()->json([
            'message' => 'المتطوع غير موجود',
        ], 404);
    }

    $volunteerProfile->status = $validated['status'];
    $volunteerProfile->save();

    return response()->json([
        'message' => 'تم تحديث حالة المتطوع بنجاح',
        'data' => [
            'id'     => $volunteerProfile->id,
            'status' => $volunteerProfile->status,
        ],
    ]);
}
public function show($id)
{
    $volunteerProfile = VolunterProfile::with(['user.profile.city', 'skills'])->find($id);

    if (!$volunteerProfile) {
        return response()->json([
            'message' => 'المتطوع غير موجود',
        ], 404);
    }

    $user = $volunteerProfile->user;
    $profile = optional($user)->profile;

    return response()->json([
        'data' => [
            'id'                   => $volunteerProfile->id,
            'name'                 => optional($user)->name,
            'email'                => optional($user)->email,
            'phone'                => optional($profile)->phone,
            'city'                 => optional($profile->city ?? null)->name,
            'birth_date'           => optional($profile)->birth_date,
            'gender'               => optional($profile)->gender,
            'personal_photo'       => $profile && $profile->Personal_photo
                ? asset('storage/'.$profile->Personal_photo)
                : null,
            'bio'                  => $volunteerProfile->bio,
            'status'               => $volunteerProfile->status,
            'favorite_period'      => $volunteerProfile->Favorite_period,
            'commitment_type'      => $volunteerProfile->Commitment_type,
            'educational_level'    => $volunteerProfile->Educational_level,
            'total_hours'          => $volunteerProfile->total_hours,
            'previous_voluntering' => (bool) $volunteerProfile->previous_voluntering,
            'previous_work_place'  => $volunteerProfile->previous_work_place,
            'experience_years'     => $volunteerProfile->experience_years,
            'car'                  => (bool) $volunteerProfile->car,
            'facebook'             => $volunteerProfile->facebook,
            'linkedin'             => $volunteerProfile->linkedin,
            'points'               => $volunteerProfile->points,
            'rank'                 => $volunteerProfile->rank,
            'skills'               => $volunteerProfile->skills->pluck('name'),
            'created_at'           => $volunteerProfile->created_at->format('Y-m-d'),
        ],
    ]);
}
}
