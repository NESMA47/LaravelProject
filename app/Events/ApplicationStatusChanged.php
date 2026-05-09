<?php

namespace App\Events;

use App\Models\Application;
use App\Models\ApplicationStage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplicationStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Application $application,
        public ApplicationStage $stage,
        public string $previousStatus,
    ) {
    }
}
