<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class InvoiceController extends Controller
{
    public function index(Request $request): InertiaResponse|JsonResponse
    {
        $query = Invoice::with('user');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->where('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->where('created_at', '<=', $dateTo);
        }

        $invoices = $query->latest()->paginate($request->input('per_page', 15));

        $stats = [
            'total_revenue' => (float) Invoice::where('status', 'paid')->sum('total'),
            'pending_amount' => (float) Invoice::where('status', 'pending')->sum('total'),
            'paid_amount' => (float) Invoice::where('status', 'paid')->sum('total'),
            'overdue_amount' => (float) Invoice::where('status', 'overdue')->sum('total'),
            'total_count' => Invoice::count(),
            'pending_count' => Invoice::where('status', 'pending')->count(),
            'paid_count' => Invoice::where('status', 'paid')->count(),
            'overdue_count' => Invoice::where('status', 'overdue')->count(),
        ];

        if ($request->expectsJson()) {
            return response()->json(['data' => $invoices, 'stats' => $stats]);
        }

        return Inertia::render('Admin/Billing/Invoices', [
            'invoices' => $invoices,
            'stats' => $stats,
            'filters' => $request->only(['status', 'date_from', 'date_to']),
        ]);
    }

    public function show(Request $request, Invoice $invoice): InertiaResponse|JsonResponse
    {
        $invoice->load(['user', 'items.subscriptionPackage', 'paymentMethod']);

        if ($request->expectsJson()) {
            return response()->json(['data' => $invoice]);
        }

        return Inertia::render('Admin/Billing/InvoiceDetail', [
            'invoice' => $invoice,
        ]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:paid,void,pending',
        ]);

        $invoice->update($validated);

        if ($validated['status'] === 'paid' && !$invoice->paid_at) {
            $invoice->update(['paid_at' => now()]);
        }

        return back()->with('success', 'Invoice updated successfully.');
    }

    public function destroy(Request $request, Invoice $invoice): RedirectResponse
    {
        $invoice->items()->delete();
        $invoice->delete();

        return redirect()->route('admin.invoices.index')
            ->with('success', 'Invoice deleted successfully.');
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = Invoice::with('user', 'items');

        if ($dateFrom = $request->input('date_from')) {
            $query->where('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->where('created_at', '<=', $dateTo);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $invoices = $query->latest()->get();

        $callback = function () use ($invoices) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Invoice Number',
                'User',
                'Email',
                'Subtotal',
                'Tax',
                'Total',
                'Status',
                'Payment Method',
                'Paid At',
                'Created At',
                'Items',
            ]);

            foreach ($invoices as $invoice) {
                $items = $invoice->items->map(function ($item) {
                    return "{$item->description} x{$item->quantity} @ {$item->unit_price}";
                })->implode('; ');

                fputcsv($handle, [
                    $invoice->invoice_number,
                    $invoice->user?->username ?? 'N/A',
                    $invoice->user?->email ?? 'N/A',
                    $invoice->subtotal,
                    $invoice->tax,
                    $invoice->total,
                    $invoice->status,
                    $invoice->paymentMethod?->name ?? 'N/A',
                    $invoice->paid_at?->format('Y-m-d H:i:s') ?? '',
                    $invoice->created_at->format('Y-m-d H:i:s'),
                    $items,
                ]);
            }

            fclose($handle);
        };

        $filename = 'invoices_' . now()->format('Y-m-d_His') . '.csv';

        return Response::stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
