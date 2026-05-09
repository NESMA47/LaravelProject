<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Interview;
use Illuminate\Support\Facades\DB;

class InterviewService
{
    public static function schedule(Application $application, array $data, string $createdByUserId): Interview
    {
        $interview = DB::transaction(function () use ($application, $data, $createdByUserId) {
            $interview = Interview::create([
                'application_id' => $application->id,
                'scheduled_at' => $data['scheduled_at'],
                'duration_minutes' => $data['duration_minutes'] ?? 60,
                'location_type' => $data['location_type'],
                'location_details' => $data['location_details'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'scheduled',
                'created_by_user_id' => $createdByUserId,
            ]);

            // Auto-advance application to interviewed if currently shortlisted
            if ($application->current_status === 'shortlisted') {
                ApplicationStageService::insertStage(
                    $application,
                    'interviewed',
                    'Interview scheduled',
                    null,
                    true
                );
            }

            return $interview;
        });

        return $interview;
    }

    public static function reschedule(Interview $interview, array $data): Interview
    {
        $interview->update($data);
        return $interview->fresh();
    }

    public static function cancel(Interview $interview, ?string $cancellationNote = null): Interview
    {
        $interview->update([
            'status' => 'cancelled',
            'cancellation_reason' => 'employer_cancelled',
            'cancellation_note' => $cancellationNote,
        ]);

        $interview->delete(); // soft delete

        return $interview->fresh();
    }

    public static function setOutcome(Interview $interview, string $status, ?string $notes = null): Interview
    {
        $interview->update([
            'status' => $status,
            'notes' => $notes ?? $interview->notes,
        ]);

        return $interview->fresh();
    }
}
