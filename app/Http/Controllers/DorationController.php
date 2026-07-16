<?php
namespace App\Http\Controllers;

use App\Exports\DonationExport;
use App\Http\Requests\StoreDonationRequest;
use App\Models\Donation;
use App\Models\DonorProfile;
use App\Models\Doration;
use App\Models\User;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class DorationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
public function export()
{
    Excel::store(new DonationExport, 'Donation.xlsx', 'public');
    return response()->json([
        'message' => 'تم حفظ الملف بنجاح في السيرفر',
        'file_url' => asset('storage/Donation.xlsx')
    ]);
}
    public function index()
    {
        $donations = Doration::with('donorProfile.user')->latest()->get();
        return response()->json($donations);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }
    public function store(StoreDonationRequest $request)
    {
       if ($request->has('donor_email')) {
        $user = User::firstOrCreate(
            ['email' => $request->donor_email],
            [
                'name'      => $request->name,
                'password'  => bcrypt(str()->random(16)),
                'role'      => 'Donor',
                'is_active' => false,
            ]
        );
    } else {
        $user = User::firstOrCreate(
            ['name' => $request->name],
            [
                'email'     => 'guest_' . str()->random(8) . '@anonymous.local',
                'password'  => bcrypt(str()->random(16)),
                'role'      => 'Donor',
                'is_active' => false,
            ]
        );
    }
        $donorProfile = DonorProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'donor_type'   => $request->donor_type ?? 'فردي',
                'is_anonymous' => $request->is_anonymous ?? false,
                'bio'          => null,
            ]
        );
        $donation = $donorProfile->dorations()->create([
            'name'   => $request->name,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'cat'    => $request->cat,
            'date'   => $request->date,
            'notes'  => $request->notes ?? '',
        ]);
        return response()->json([
            'message'  => 'تم إضافة التبرع بنجاح',
            'donation' => $donation,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
       $donation = Doration::with('donorProfile.user')->find($id);

    if (!$donation) {
        return response()->json([
            'status' => false,
            'message' => 'التبرع غير موجود'
        ], 404);
    }

    return response()->json([
        'status' => true,
        'message' => 'تم ارجاع البيانات بنجاح',
        'data' => $donation
    ], 200);
    }
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Doration $donation)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Doration $donation)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
     public function destroy($id)
    {
        $donation = Doration::find($id);

    if (!$donation) {
        return response()->json([
            'status' => false,
            'message' => 'التبرع غير موجود'
        ], 404);
    }
    $donation->delete();
    return response()->json([
        'status' => true,
        'message' => 'تم حذف التبرع بنجاح'
    ], 200);
    }
}
