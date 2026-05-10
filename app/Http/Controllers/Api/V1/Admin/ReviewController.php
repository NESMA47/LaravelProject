<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyReviewResource;
use App\Models\CompanyReview;
use App\Models\Employer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    // 7.11 / 8.10: Pending review moderation queue
    public function index(): JsonResponse
    {
        $status = request('status', 'pending');

        $query = CompanyReview::with(['employer', 'candidate.user']);

        if ($status === 'pending') {
            $query->where('is_approved', false)->whereNull('approved_at');
        } elseif ($status === 'approved') {
            $query->where('is_approved', true);
        } elseif ($status === 'rejected') {
            $query->where('is_approved', false)->whereNotNull('approved_at');
        }

        $reviews = $query->orderBy('created_at', 'desc')
            ->paginate(request('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'data' => CompanyReviewResource::collection($reviews),
                'meta' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                ],
            ],
        ]);
    }

    // 7.12 / 8.11: Approve a review
    public function approve(string $id): JsonResponse
    {
        $review = CompanyReview::find($id);

        if (! $review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found.',
            ], 404);
        }

        DB::transaction(function () use ($review) {
            $review->update([
                'is_approved' => true,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            $employer = Employer::find($review->employer_id);
            if ($employer) {
                $approvedReviews = $employer->reviews()->where('is_approved', true);
                $employer->update([
                    'average_rating' => $approvedReviews->avg('rating_overall'),
                    'total_reviews' => $approvedReviews->count(),
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'data' => new CompanyReviewResource($review->fresh()->load(['employer', 'candidate.user'])),
        ]);
    }

    // 7.13 / 8.12: Reject a review
    public function reject(string $id): JsonResponse
    {
        $review = CompanyReview::find($id);

        if (! $review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found.',
            ], 404);
        }

        $review->update([
            'is_approved' => false,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => new CompanyReviewResource($review->fresh()->load(['employer', 'candidate.user'])),
        ]);
    }
}
