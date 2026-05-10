<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    // 8.19: List all reports
    public function index(Request $request): JsonResponse
    {
        $query = Report::with(['reporter', 'resolvedBy']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('target_type')) {
            $query->where('target_type', $request->input('target_type'));
        }

        $reports = $query->latest()->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'data' => $reports->items(),
                'meta' => [
                    'current_page' => $reports->currentPage(),
                    'last_page' => $reports->lastPage(),
                    'per_page' => $reports->perPage(),
                    'total' => $reports->total(),
                ],
            ],
        ]);
    }

    // 8.20: Update investigation status
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,investigating,resolved,dismissed'],
            'resolution_notes' => ['nullable', 'string'],
        ]);

        $report = Report::find($id);

        if (! $report) {
            return response()->json([
                'success' => false,
                'message' => 'Report not found.',
            ], 404);
        }

        $update = [
            'status' => $validated['status'],
            'resolution_notes' => $validated['resolution_notes'] ?? $report->resolution_notes,
        ];

        if (in_array($validated['status'], ['resolved', 'dismissed'])) {
            $update['resolved_by_user_id'] = Auth::id();
        }

        $report->update($update);

        return response()->json([
            'success' => true,
            'data' => $report->fresh()->load(['reporter', 'resolvedBy']),
        ]);
    }
}
