<?php
// app/Http/Controllers/Admin/SponsorshipManagementController.php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Sponsorship;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SponsorshipManagementController extends Controller
{
    private function findSponsorship($id, array $with = [])
    {
        return Sponsorship::with($with)->find($id);
    }
    public function dashboard(Request $request)
    {
        $stats = [
            'total' => Sponsorship::count(),
            'pending' => Sponsorship::where('status', 'قيد الانتظار')->count(),
            'active' => Sponsorship::where('status', 'نشطة')->count(),
            'completed' => Sponsorship::where('status', 'مكتملة')->count(),
            'cancelled' => Sponsorship::where('status', 'ملغية')->count(),
            'suspended' => Sponsorship::where('status', 'معلقة')->count(),

            'total_amount_active' => Sponsorship::where('status', 'نشطة')->sum('amount'),
            'total_paid_all' => Sponsorship::sum('total_paid'),

            'by_type' => Sponsorship::selectRaw('type, count(*) as count')->groupBy('type')->pluck('count', 'type'),
            'pending_review' => Sponsorship::where('status', 'قيد الانتظار')
                ->with(['sponsor:id,name,email', 'beneficiary:id,name,email'])
                ->orderBy('created_at', 'asc')
                ->take(10)
                ->get(),
            'overdue_payments' => Sponsorship::where('status', 'نشطة')
                ->whereNotNull('next_payment_date')
                ->where('next_payment_date', '<', now())
                ->with(['sponsor:id,name', 'beneficiary:id,name'])
                ->get(),
        ];

        return response()->json([
            'code' => '200',
            'success' => true,
            'data' => $stats,
        ], 200);
    }
    public function approve(Request $request, $id)
    {
      $sponsorship = $this->findSponsorship($id, ['sponsor', 'beneficiary']);
        if (!$sponsorship) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'الكفالة غير موجودة',
            ], 404);
        }
        if ($sponsorship->status !== 'قيد الانتظار') {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'لا يمكن الموافقة إلا على كفالة قيد الانتظار',
            ], 400);
        }

        DB::transaction(function () use ($sponsorship, $request) {
            $sponsorship->status = 'نشطة';
            $sponsorship->approved_by = $request->user()->id;
            $sponsorship->approved_at = now();
            $sponsorship->next_payment_date = $sponsorship->type === 'مرة واحدة'
                ? null
                : now()->addMonth();
            $sponsorship->save();

            Notification::sendPushOnly($sponsorship->beneficiary_id,
                'تم الموافقة على كفالتك',
                "تمت الموافقة على كفالتك من {$sponsorship->sponsor->name}.",
                'sponsorship',
                ['sponsorship_id' => $sponsorship->id]
            );
            Notification::sendPushOnly(
                $sponsorship->sponsor_id,
                'تم الموافقة على طلب الكفالة',
                "تمت الموافقة على كفالة المستفيد {$sponsorship->beneficiary->name}.",
                'sponsorship',
                ['sponsorship_id' => $sponsorship->id]
            );
        });

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تمت الموافقة على الكفالة',
            'data' => $sponsorship,
        ], 200);
    }
    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $sponsorship = $this->findSponsorship($id, ['sponsor', 'beneficiary']);
        if (!$sponsorship) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'الكفالة غير موجودة',
            ], 404);
        }
        if ($sponsorship->status !== 'قيد الانتظار') {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'لا يمكن رفض كفالة إلا وهي قيد الانتظار',
            ], 400);
        }
        $sponsorship->status = 'ملغية';
        $sponsorship->cancelled_reason = $validated['reason'];
        $sponsorship->cancelled_at = now();
        $sponsorship->save();
        Notification::sendPushOnly(
            $sponsorship->sponsor_id,
            'تم رفض طلب الكفالة',
            "تم رفض طلب الكفالة. السبب: {$validated['reason']}",
            'sponsorship',
            ['sponsorship_id' => $sponsorship->id]
        );

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم رفض طلب الكفالة',
        ], 200);
    }
    public function suspend(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $sponsorship = $this->findSponsorship($id);
        if (!$sponsorship) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'الكفالة غير موجودة',
            ], 404);
        }

        if ($sponsorship->status !== 'نشطة') {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'يمكن تعليق الكفالات النشطة فقط',
            ], 400);
        }

        $sponsorship->status = 'معلقة';
        $sponsorship->admin_notes = $validated['reason'] ?? $sponsorship->admin_notes;
        $sponsorship->save();

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم تعليق الكفالة',
            'data' => $sponsorship,
        ], 200);
    }
    public function resume(Request $request, $id)
    {
       $sponsorship = $this->findSponsorship($id);
        if (!$sponsorship) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'الكفالة غير موجودة',
            ], 404);
        }

        if ($sponsorship->status !== 'معلقة') {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'يمكن إعادة تفعيل الكفالات المعلقة فقط',
            ], 400);
        }

        $sponsorship->status = 'نشطة';
        $sponsorship->save();

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تمت إعادة تفعيل الكفالة',
            'data' => $sponsorship,
        ], 200);
    }
    public function updateNotes(Request $request, $id)
    {
        $validated = $request->validate([
            'admin_notes' => 'required|string|max:1000',
        ]);

       $sponsorship = $this->findSponsorship($id);
        if (!$sponsorship) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'الكفالة غير موجودة',
            ], 404);
        }
        $sponsorship->admin_notes = $validated['admin_notes'];
        $sponsorship->save();

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم تحديث الملاحظات',
        ], 200);
    }
}
