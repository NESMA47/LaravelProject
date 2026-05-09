<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationStage;
use App\Models\Candidate;
use App\Models\Job;
use Illuminate\Support\Facades\DB;

class ApplicationStageService
{
    private const TERMINAL_STAGES = ['hired', 'rejected', 'withdrawn', 'job_removed'];

    private const ALLOWED_TRANSITIONS = [
        'applied' => ['reviewed', 'shortlisted', 'rejected'],
        'reviewed' => ['shortlisted', 'interviewed', 'rejected'],
        'shortlisted' => ['interviewed', 'rejected'],
        'interviewed' => ['offered', 'rejected'],
        'offered' => ['hired', 'rejected'],
        'hired' => [],
        'rejected' => [],
        'withdrawn' => [],
        'job_removed' => [],
    ];

    public static function isTerminal(string $stage): bool
    {
        return in_array($stage, self::TERMINAL_STAGES, true);
    }

    public static function canTransition(string $current, string $next): bool
    {
        return in_array($next, self::ALLOWED_TRANSITIONS[$current] ?? [], true);
    }

    public static function getErrorMessage(string $current, string $next): ?string
    {
        if (self::isTerminal($current)) {
            return "Application is already in terminal stage '{$current}'. No further transitions allowed.";
        }

        if (! self::canTransition($current, $next)) {
            return "Invalid transition: application cannot move from {$current} to {$next}.";
        }

        return null;
    }

    public static function insertStage(Application $application, string $stage, ?string $notes = null, ?string $changedByUserId = null, bool $isSystem = false): ApplicationStage
    {
        $stageRecord = ApplicationStage::create([
            'application_id' => $application->id,
            'stage' => $stage,
            'notes' => $notes,
            'changed_by_user_id' => $changedByUserId,
            'is_system' => $isSystem,
        ]);

        // Update denormalized current_status cache
        $application->update(['current_status' => $stage]);

        return $stageRecord;
    }

    public static function getStageLabel(string $stage): string
    {
        return match ($stage) {
            'applied' => 'Candidate applied to this job',
            'reviewed' => 'Employer reviewed this application',
            'shortlisted' => 'Employer shortlisted this candidate',
            'interviewed' => 'Employer moved this application to interview stage',
            'offered' => 'Employer extended an offer to the candidate',
            'hired' => 'Candidate was hired',
            'rejected' => 'Employer rejected this application',
            'withdrawn' => 'Candidate withdrew their application',
            'job_removed' => 'Employer removed the job listing',
            default => 'Status updated',
        };
    }

    public static function getActorRole(?User $user, bool $isSystem): string
    {
        if ($isSystem) {
            return 'system';
        }
        if (! $user) {
            return 'unknown';
        }
        return $user->role;
    }
}
