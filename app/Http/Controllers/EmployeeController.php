<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'full_name' => 'required|string',
            'password'  => 'required|string',
        ]);
        $employee = Employee::where('full_name', $request->full_name)->first();
        if (!$employee || !Hash::check($request->password,$employee->password)) {
            return response()->json([
                'message' => 'بيانات خاطئة'
            ], 401);
        }
        Auth::login($employee);
        return response()->json([
            'role'  => $employee->role,
            'name'  => $employee->full_name,
        ]);
    }
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['message' =>'تم تسجيل الخروج']);
    }
    public function index()
    {
        //
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
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Employee $employee)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Employee $employee)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Employee $employee)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee)
    {
        //
    }
}
