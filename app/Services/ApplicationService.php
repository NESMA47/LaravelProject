namespace App\Services;

use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\JobListing;
use App\Notifications\ApplicationStatusChanged;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
class ApplicationService
{
    /**
     * Candidate يتقدم لوظيفة
     */
    public function apply(
        CandidateProfile $candidate,
        JobListing $job,
        array $data
    ): Application {
        // ① الوظيفة موجودة ومعتمدة؟
        if ($job->status !== 'approved') {
            throw ValidationException::withMessages([
                'job' => 'This job is not open for applications.',
            ]);
        }
        // ② منتهية المدة؟
        if ($job->deadline && $job->deadline->isPast()) {
            throw ValidationException::withMessages([
                'job' => 'Application deadline has passed.',
            ]);
        }

        // ③ رفع الـ Resume (PDF فقط)
        $resumePath = null;
        if (isset($data['resume']) && $data['resume'] instanceof UploadedFile) {
            $resumePath = $this->uploadResume($data['resume'], $candidate->id);
        }

 // ④ إنشاء الطلب — unique constraint يمنع التكرار تلقائيًا
        return Application::create([
            'candidate_id'   => $candidate->id,
            'job_id'          => $job->id,
            'resume_path'     => $resumePath,
            'contact_email'   => $data['contact_email'] ?? null,
            'contact_phone'   => $data['contact_phone'] ?? null,
            'status'          => 'pending',
        ]);
    }
/**
     * Employer يغير حالة الطلب
     */
    public function changeStatus(
        Application $application,
        string $status,
        ?string $notes = null
    ): Application {
        $application->update([
            'status' => $status,
            'notes'  => $notes,
        ]);

        // إرسال Notification للـ Candidate
        $application->candidate->user->notify(
            new ApplicationStatusChanged($application)
        );
 return $application->fresh();
    }

    /**
     * Candidate يلغي طلبه (SoftDelete)
     */
    public function cancel(Application $application): void
    {
        if ($application->status === 'accepted') {
            throw ValidationException::withMessages([
                'application' => 'Cannot cancel an accepted application.',
            ]);
        }

        $application->update(['status' => 'cancelled']);
        $application->delete(); // SoftDelete
    }

    // ──────────── Private Helpers ────────────

    private function uploadResume(UploadedFile $file, int $candidateId): string
    {
        // تحقق PDF فقط
        if ($file->getClientMimeType() !== 'application/pdf') {
            throw ValidationException::withMessages([
                'resume' => 'Resume must be a PDF file.',
            ]);
        }
         $filename = sprintf(
            'resumes/candidate_%d_%s.pdf',
            $candidateId,
            now()->timestamp()
        );

        return Storage::disk('private')->putFileAs(
            'resumes', $file, basename($filename)
        );
    }
}

