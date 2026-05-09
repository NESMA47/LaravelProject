<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReviewController extends Controller
{
    public function index(): JsonResponse
    {
        $reviews = CompanyReview::with([
            'candidate.user:id,first_name,last_name,email',
            'employer:id,company_name,slug',
        ])->where('is_approved', false)->latest()->paginate(20);

        return response()->json(['success' => true, 'data' => $reviews]);
    }

    public function approve(string $id): JsonResponse
    {
        $review = CompanyReview::findOrFail($id);

        DB::transaction(function () use ($review) {
            $review->update(['is_approved' => true, 'approved_at' => now()]);

            $employer = $review->employer;
            $employer->update([
                'average_rating' => CompanyReview::where('employer_id', $employer->id)
                    ->where('is_approved', true)->avg('rating'),
                'reviews_count'  => CompanyReview::where('employer_id', $employer->id)
                    ->where('is_approved', true)->count(),
            ]);
        });

        return response()->json(['success' => true, 'message' => 'Review approved.']);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate(['reason' => 'required|string|max:500']);
        $review    = CompanyReview::findOrFail($id);
        $review->update(['is_approved' => false, 'rejection_reason' => $validated['reason']]);

        return response()->json(['success' => true, 'message' => 'Review rejected.']);
    }
}
