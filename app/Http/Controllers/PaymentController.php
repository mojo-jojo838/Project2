<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->role === 'admin', 403);
        $filters = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $selectedDate = $filters['date'] ?? null;

        $received = Payment::where('status', 'paid');
        return view('payments.index', [
            'payments' => Payment::with(['user', 'membership.plan', 'attendance'])
                ->when($selectedDate, fn ($query) => $query->whereDate('paid_at', $selectedDate))
                ->latest('paid_at')->latest('id')->paginate(20)->withQueryString(),
            'todayTotal' => (clone $received)->whereDate('paid_at', today())->sum('amount'),
            'weekTotal' => (clone $received)->whereDate('paid_at', '>=', today()->startOfWeek())
                ->whereDate('paid_at', '<=', today())->sum('amount'),
            'monthTotal' => (clone $received)->whereDate('paid_at', '>=', today()->startOfMonth())
                ->whereDate('paid_at', '<=', today())->sum('amount'),
            'yearTotal' => (clone $received)->whereDate('paid_at', '>=', today()->startOfYear())
                ->whereDate('paid_at', '<=', today())->sum('amount'),
            'selectedDate' => $selectedDate,
            'selectedDateTotal' => $selectedDate
                ? (clone $received)->whereDate('paid_at', $selectedDate)->sum('amount')
                : null,
        ]);
    }
}
