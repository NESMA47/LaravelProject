<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reports = Report::with([
            'reporter:id,first_name,last_name,email',
            'resolvedBy:id,first_name,last_name',
        ])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->type,   fn($q, $t) => $q->where('target_type', $t))
            ->latest()->paginate(20);

        return response()->json(['success' => true, 'data' => $reports]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status'           => ['required', Rule::in(['pending','investigating','resolved','dismissed'])],
            'resolution_notes' => 'nullable|string|max:1000',
        ]);

        $report  = Report::findOrFail($id);
        $updates = ['status' => $validated['status']];

        if (in_array($validated['status'], ['resolved', 'dismissed'])) {
            $updates['resolved_by_user_id'] = $request->user()->id;
            $updates['resolution_notes']    = $validated['resolution_notes'] ?? null;
        }

        $report->update($updates);

        return response()->json(['success' => true, 'data' => $report->fresh()]);
    }
}
