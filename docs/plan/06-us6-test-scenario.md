# US6 — End-to-End Test Scenario: Full Application Pipeline Cycle
**Document type:** Manual testing scenario
**Purpose:** Walk through the entire application pipeline from job creation to hire, hitting every endpoint, every business rule, and every edge case in a logical sequence.

---

## Actors

- **Employer user:** Mostafa Ali — owns a company called "TechCorp Egypt"
- **Candidate user:** Ahmed Khaled — a frontend developer
- **Admin:** confirms the job so it goes active (outside US6 scope but needed as a prerequisite)

---

## Prerequisites before starting

- Mostafa is registered and has a verified employer profile
- Ahmed is registered, has a complete candidate profile, and has at least one resume uploaded (fe_dev.pdf) set as default
- Admin panel is accessible to confirm jobs

---

## Phase 1 — Job Creation (Prerequisite)

**Step 1.1 — Employer creates a job**
Mostafa creates a job posting for "Senior Frontend Developer". The job is saved with status `draft`.

**Step 1.2 — Employer submits job for review**
Mostafa changes the job status to `pending_review`. The job is now waiting for admin confirmation.

**Step 1.3 — Admin confirms the job**
Admin sets `is_confirmed = true` and status becomes `active`. The job is now publicly visible and accepting applications.

**What to verify after Phase 1:**
- Job exists with status `active` and `is_confirmed = true`
- `expires_at` is set in the future or is null
- `applications_count` on the job is 0

---

## Phase 2 — Candidate Applies (C-3)

**Step 2.1 — Ahmed applies for the job**
Ahmed calls `POST /api/v1/candidate/applications` with the job_id and a cover letter. He does not provide a resume_id so the system uses his default resume (fe_dev.pdf).

**What to verify:**
- Response is 201
- Application is created with `current_status = applied`
- `original_job_id` is set and matches the job_id
- `job_snapshot`, `employer_snapshot`, `candidate_snapshot` are all populated and non-empty
- `resume_url` on the application matches fe_dev.pdf URL
- `application_stages` has exactly one row: stage=applied, is_system=true, notes="Candidate applied to this job"
- `jobs.applications_count` incremented to 1
- ApplicationSubmitted event was fired

**Step 2.2 — Ahmed tries to apply again to the same job**
Ahmed calls `POST /api/v1/candidate/applications` again with the same job_id.

**What to verify:**
- Response is 409
- Message: "You have already applied for this job"
- No new application row created
- applications_count stays at 1

---

## Phase 3 — Candidate Views Their Application (C-1, C-2)

**Step 3.1 — Ahmed lists his applications**
Ahmed calls `GET /api/v1/candidate/applications`.

**What to verify:**
- Response contains 1 application
- `current_status` is "applied"
- `job_snapshot` has the correct job title and employer name
- `employer_snapshot` has TechCorp Egypt details
- `interviews_count` is 0
- `job_removed_at` is null

**Step 3.2 — Ahmed opens the application detail**
Ahmed calls `GET /api/v1/candidate/applications/:id`.

**What to verify:**
- Full snapshots present
- `history` array has exactly 1 entry: stage=applied, actor_role=system, actor_name="System", label="Candidate applied to this job"
- `interviews` array is empty

---

## Phase 4 — Employer Reviews the Application (E-1, E-2, E-3, E-4)

**Step 4.1 — Employer checks the global inbox**
Mostafa calls `GET /api/v1/employer/applications`.

**What to verify:**
- Ahmed's application appears in the list
- `candidate_snapshot` partial shows Ahmed's name, headline, skills
- `current_status` is "applied"

**Step 4.2 — Employer checks the per-job pipeline**
Mostafa calls `GET /api/v1/employer/jobs/:job_id/applications`.

**What to verify:**
- `pipeline_summary` shows: applied=1, all other stages=0
- Ahmed's application appears in the data array

**Step 4.3 — Employer opens the full application detail**
Mostafa calls `GET /api/v1/employer/applications/:id`.

**What to verify:**
- Full candidate_snapshot including resume_url
- history has 1 entry matching Step 3.2

**Step 4.4 — Employer moves application to reviewed**
Mostafa calls `PATCH /api/v1/employer/applications/:id/status` with status=reviewed and notes="Strong Vue.js background, worth a closer look."

**What to verify:**
- Response 200
- `current_status` in response is "reviewed"
- `history` in response now has 2 entries:
  - Entry 1: stage=applied, actor_role=system
  - Entry 2: stage=reviewed, actor_role=employer, actor_name="Mostafa Ali", notes="Strong Vue.js background, worth a closer look."
- application_stages table has 2 rows
- applications.current_status is "reviewed"

**Step 4.5 — Employer tries an invalid backward transition**
Mostafa calls `PATCH /api/v1/employer/applications/:id/status` with status=applied.

**What to verify:**
- Response is 409
- Message: "Invalid transition: application cannot move from reviewed to applied"
- application_stages still has only 2 rows
- current_status unchanged

**Step 4.6 — Employer tries to skip stages (reviewed to interviewed, skipping shortlisted)**
Mostafa calls `PATCH /api/v1/employer/applications/:id/status` with status=interviewed.

**What to verify:**
- Response is 200 — this is a valid transition per the transition map (reviewed to interviewed is allowed)
- current_status is now "interviewed"
- history has 3 entries

> Note: if you want to test the shortlisted stage too, rerun from Step 4.4 going reviewed to shortlisted to interviewed instead.

---

## Phase 5 — Employer Shortlists and Schedules Interview (E-4, E-5)

> Reset to after Step 4.4 (status=reviewed) for this phase, or run it as an alternate path.

**Step 5.1 — Employer moves to shortlisted**
Mostafa calls `PATCH /api/v1/employer/applications/:id/status` with status=shortlisted, notes="Inviting for technical screen."

**What to verify:**
- current_status = shortlisted
- history has 3 entries, newest is shortlisted with Mostafa as actor

**Step 5.2 — Employer schedules an interview**
Mostafa calls `POST /api/v1/employer/applications/:id/interviews` with:
- scheduled_at: a timestamp 5 days in the future
- duration_minutes: 60
- location_type: video_call
- location_details: "https://zoom.us/j/123456"
- notes: "Technical discussion with senior dev team"

**What to verify:**
- Response is 201
- Interview created with status=scheduled
- `application_current_status` in response is "interviewed" (auto-advanced from shortlisted)
- application_stages now has a new system row: stage=interviewed, is_system=true, notes="Interview scheduled"
- applications.current_status = "interviewed"

**Step 5.3 — Candidate checks their application and sees the interview**
Ahmed calls `GET /api/v1/candidate/applications/:id`.

**What to verify:**
- `interviews` array has 1 entry
- Interview shows scheduled_at, location_type, location_details
- `deleted_at` is null (interview is active)
- history shows the full trail including the system "Interview scheduled" entry

**Step 5.4 — Employer tries to schedule an interview on a non-eligible application**
To test the guard: use a separate application at status=applied and try to schedule an interview on it.

**What to verify:**
- Response is 403
- Message indicates application is not at shortlisted or interviewed stage

---

## Phase 6 — Interview Lifecycle (E-6, E-7, E-8)

**Step 6.1 — Employer reschedules the interview**
Mostafa calls `PATCH /api/v1/employer/applications/:id/interviews/:interview_id/reschedule` with a new scheduled_at 7 days in the future.

**What to verify:**
- Response 200
- Interview scheduled_at updated
- Interview status is still "scheduled"
- No new application_stages row created (reschedule does not generate a history entry)

**Step 6.2 — Employer tries to reschedule with a past date**
Mostafa calls reschedule with a scheduled_at in the past.

**What to verify:**
- Response 422
- Interview unchanged

**Step 6.3 — Interview takes place — employer marks it completed**
Mostafa calls `PATCH /api/v1/employer/applications/:id/interviews/:interview_id/outcome` with status=completed, notes="Strong technical skills, good culture fit."

**What to verify:**
- Response 200
- Interview status = completed
- notes updated
- Interview is NOT soft-deleted
- deleted_at is null

**Step 6.4 — Employer tries to cancel a completed interview**
Mostafa calls `PATCH /api/v1/employer/applications/:id/interviews/:interview_id/cancel`.

**What to verify:**
- Response 403
- Interview unchanged (cannot cancel a completed interview)

---

## Phase 7 — Offer and Hire (E-4)

**Step 7.1 — Employer moves to offered**
Mostafa calls `PATCH /api/v1/employer/applications/:id/status` with status=offered, notes="Offering 35,000 EGP per month."

**What to verify:**
- current_status = offered
- history updated with offered entry

**Step 7.2 — Employer moves to hired**
Mostafa calls `PATCH /api/v1/employer/applications/:id/status` with status=hired, notes="Candidate accepted the offer."

**What to verify:**
- current_status = hired
- history has the full trail from applied through to hired

**Step 7.3 — Ahmed tries to withdraw after being hired**
Ahmed calls `PATCH /api/v1/candidate/applications/:id/withdraw`.

**What to verify:**
- Response 403
- Message: "You cannot withdraw from an application that has been accepted."
- current_status unchanged

**Step 7.4 — Employer tries to change status after hired**
Mostafa calls `PATCH /api/v1/employer/applications/:id/status` with any value.

**What to verify:**
- Response 409
- hired is a terminal stage, no transitions allowed

---

## Phase 8 — Withdrawal Flow (Alternate Path, C-4)

> Run this as a separate scenario from the beginning, or in parallel with a second application.

**Step 8.1 — Ahmed applies for a second job**
Same as Phase 2 but with a different active job. Application created, status=applied.

**Step 8.2 — Employer moves it to shortlisted**
Same as Phase 5 Step 5.1.

**Step 8.3 — Employer schedules an interview**
Same as Phase 5 Step 5.2. Interview created, status=scheduled.

**Step 8.4 — Ahmed withdraws the application**
Ahmed calls `PATCH /api/v1/candidate/applications/:id/withdraw` with reason="I have accepted another offer."

**What to verify:**
- Response 200
- current_status = withdrawn
- withdrawn_at is set
- withdrawn_reason = "I have accepted another offer."
- application_stages has a new row: stage=withdrawn, is_system=false, changed_by_user_id=Ahmed's user id
- The scheduled interview is now: status=cancelled, cancellation_reason=candidate_cancelled, cancellation_note="Candidate withdrew their application.", deleted_at is set (soft-deleted)
- jobs.applications_count decremented by 1
- ApplicationWithdrawn event fired

**Step 8.5 — Ahmed tries to withdraw again**
Ahmed calls withdraw on the same application.

**What to verify:**
- Response 409
- Message: "You have already withdrawn this application."

**Step 8.6 — Employer tries to move status on a withdrawn application**
Mostafa calls `PATCH /api/v1/employer/applications/:id/status` with status=reviewed.

**What to verify:**
- Response 403
- current_status unchanged

**Step 8.7 — Ahmed views the withdrawn application**
Ahmed calls `GET /api/v1/candidate/applications/:id`.

**What to verify:**
- `interviews` array includes the cancelled interview with deleted_at set and cancellation_reason=candidate_cancelled
- history shows the full trail ending with the withdrawn entry

---

## Phase 9 — Job Removal Flow (Alternate Path)

> Run this as a separate scenario. Requires a third application in progress.

**Step 9.1 — Ahmed applies for a third job**
Application created, status=applied.

**Step 9.2 — Employer moves it to shortlisted and schedules an interview**
Application status advances to interviewed. One interview exists with status=scheduled.

**Step 9.3 — Employer soft-deletes the job**
Mostafa deletes the job from the employer dashboard (soft-delete triggered).

**What to verify — triggered automatically by observer:**
- applications.job_id is now null
- applications.job_removed_at is set to current timestamp
- applications.current_status = job_removed
- application_stages has a new system row: stage=job_removed, is_system=true, notes="The employer removed this job listing.", changed_by_user_id=null
- The scheduled interview: status=cancelled, cancellation_reason=job_removed, cancellation_note="This interview was cancelled because the employer removed the job listing.", deleted_at is set

**Step 9.4 — Ahmed views his applications list**
Ahmed calls `GET /api/v1/candidate/applications`.

**What to verify:**
- The affected application is still in the list (not deleted)
- `current_status` = job_removed
- `job_removed_at` is populated
- `job_snapshot` still has all the original job data intact (employer name, title, salary, etc.)

**Step 9.5 — Ahmed opens the application detail**
Ahmed calls `GET /api/v1/candidate/applications/:id`.

**What to verify:**
- Full job_snapshot and employer_snapshot intact despite the job being deleted
- history shows the complete trail ending with job_removed (actor_name="System", actor_role=system, label="Employer removed the job listing")
- interviews array shows the cancelled interview with cancellation_reason=job_removed and deleted_at set

**Step 9.6 — Ahmed tries to withdraw a job_removed application**
Ahmed calls `PATCH /api/v1/candidate/applications/:id/withdraw`.

**What to verify:**
- Response 403
- Message: "This application is no longer active because the job was removed."

---

## Phase 10 — Edge Cases

**Step 10.1 — Candidate tries to apply for a paused job**
Create a job with status=paused. Ahmed tries to apply.

**What to verify:**
- Response 404
- No application created

**Step 10.2 — Candidate tries to apply for an expired job**
Create a job with expires_at set to yesterday. Ahmed tries to apply.

**What to verify:**
- Response 404
- No application created

**Step 10.3 — Candidate tries to apply with no resume at all**
Remove all of Ahmed's resumes. Ahmed tries to apply.

**What to verify:**
- Response 422
- Message indicates he must upload a resume first

**Step 10.4 — Candidate tries to apply with someone else's resume_id**
Ahmed provides in the request body a resume_id that belongs to another candidate.

**What to verify:**
- Response 422
- No application created

**Step 10.5 — Employer tries to view another employer's application**
Create a second employer with their own job and application. First employer calls `GET /api/v1/employer/applications/:id` using the second employer's application id.

**What to verify:**
- Response 404 (not 403, existence must not be leaked)

**Step 10.6 — Employer uses job_id filter in E-1 with another employer's job**
Mostafa calls `GET /api/v1/employer/applications?job_id=<other_employer_job_id>`.

**What to verify:**
- Response 403

**Step 10.7 — Employer schedules interview with a past scheduled_at**
Mostafa calls E-5 with a scheduled_at timestamp in the past.

**What to verify:**
- Response 422
- No interview created

**Step 10.8 — Employer calls reschedule with an empty request body**
Mostafa calls E-6 with no fields in the body.

**What to verify:**
- Response 422
- Interview unchanged

---

## Final database state check

After running all phases, verify the following directly in the database:

- The hired application has a complete application_stages trail: applied, reviewed, shortlisted, interviewed, offered, hired — at minimum 6 rows
- The withdrawn application has: applied, shortlisted, system-interviewed, withdrawn — 4 rows
- The job_removed application has: applied, shortlisted, system-interviewed, system-job_removed — 4 rows
- No application row was hard-deleted in any scenario
- No interview row was hard-deleted in any scenario, only soft-deleted where applicable
- jobs.applications_count on the job from Phase 8 was decremented after withdrawal
- All three snapshots on every application remain intact and unchanged regardless of what happened to the job or candidate profile afterward
