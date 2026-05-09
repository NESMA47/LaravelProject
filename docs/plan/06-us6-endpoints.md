# US6 — Application Pipeline: Final Endpoints Design
**Document type:** API endpoint specification for agent/LLM implementation
**Scope:** All endpoints for the application pipeline, both candidate and employer sides
**Depends on:** us6-schema-changes.md, us6-withdrawal-and-history-log.md

---

## Overview of what the original US6 missed or got wrong

- No endpoint for employer to list applications scoped to a specific job. The original only had a global inbox across all jobs.
- No endpoint for candidate to view a single application in detail. The original only had a list endpoint.
- No endpoint for candidate to view their interviews. Interviews were employer-only in the original design.
- The interview reschedule and cancel were merged into one PUT endpoint. These are two distinct actions with different business rules and should be separate.
- The employer inbox had no sorting options specified. Sorting is important for a hiring workflow.
- No explicit 403/404/409 contract documented per endpoint.

---

## Auth conventions used throughout

All endpoints require a Bearer token.
- `role=candidate` means the token must belong to a user with the candidate role.
- `role=employer` means the token must belong to a user with the employer role.
- Ownership is always verified server-side regardless of what IDs are in the URL.

---

## Candidate Endpoints

---

### C-1 — List my applications
**`GET /api/v1/candidate/applications`**
**Auth:** Bearer, role=candidate

Returns a paginated list of all applications belonging to the authenticated candidate. Includes the job snapshot, employer snapshot, current status, and interview count so the candidate can see the state of each application at a glance without fetching each one individually.

**Query parameters:**
- `status` — optional, filter by a single current_status value
- `page` — page number, default 1
- `per_page` — results per page, default 15, max 50

**Response fields per item:**
- `id`
- `current_status`
- `job_removed_at` — null or timestamp; frontend uses this to show the "job was removed" notice
- `applied_at`
- `updated_at`
- `withdrawn_at` — null or timestamp
- `cover_letter`
- `resume_url`
- `interviews_count` — integer, count of non-deleted interviews on this application
- `job_snapshot` — full job snapshot object
- `employer_snapshot` — full employer snapshot object

**What this does NOT include:** the full history log and the full interview list. Those are in C-2 to keep list responses lean.

**Possible errors:**
- 401 — unauthenticated

---

### C-2 — Get a single application in detail
**`GET /api/v1/candidate/applications/:id`**
**Auth:** Bearer, role=candidate

Returns the full detail of one application. Includes the complete history log and all interviews. This is the screen the candidate lands on when they click into one application from their list.

**Ownership check:** The application's `candidate_id` must match the authenticated candidate. Return 404 if not found or not owned — do not return 403, to avoid leaking existence of other candidates' application IDs.

**Response fields:**
- `id`
- `current_status`
- `job_removed_at`
- `applied_at`
- `updated_at`
- `withdrawn_at`
- `withdrawn_reason`
- `cover_letter`
- `resume_url`
- `job_snapshot` — full object
- `employer_snapshot` — full object
- `candidate_snapshot` — full object
- `history` — array of all application_stages rows ordered by created_at asc, each containing:
  - `id`
  - `stage`
  - `label` — human-readable string from the label map
  - `actor_name` — full name or "System"
  - `actor_role` — candidate / employer / system
  - `notes`
  - `created_at`
- `interviews` — array of all interviews including soft-deleted ones, each containing:
  - `id`
  - `scheduled_at`
  - `duration_minutes`
  - `location_type`
  - `location_details`
  - `notes`
  - `status`
  - `cancellation_reason` — null or enum value
  - `cancellation_note` — null or string
  - `deleted_at` — null or timestamp; frontend uses this to visually mark cancelled interviews

**Possible errors:**
- 401 — unauthenticated
- 404 — application not found or does not belong to this candidate

---

### C-3 — Apply to a job
**`POST /api/v1/candidate/applications`**
**Auth:** Bearer, role=candidate

**Request body:**
- `job_id` — required, uuid
- `cover_letter` — optional, text
- `resume_id` — optional, uuid; if omitted the candidate's default resume is used

**Business rules (all validated before insert):**
- Job must exist and not be soft-deleted
- Job status must be `active`
- Job must not be expired (expires_at is null or in the future)
- Candidate must not have an existing application for this job (check via original_job_id + candidate_id)
- If resume_id is provided it must belong to the candidate
- If resume_id is omitted the candidate must have at least one resume; if none exist return 422

**On success:** runs inside a transaction — creates the application row, builds all three snapshots, inserts the initial application_stages row (stage=applied, is_system=true, notes="Candidate applied to this job"), increments jobs.applications_count atomically, fires ApplicationSubmitted event.

**Response:** 201, returns the same shape as C-2 (full detail) so the frontend can immediately render the application detail screen without a second request.

**Possible errors:**
- 401 — unauthenticated
- 404 — job not found or not active
- 409 — "You have already applied for this job"
- 422 — validation errors (no resume, invalid resume_id, missing job_id)

---

### C-4 — Withdraw an application
**`PATCH /api/v1/candidate/applications/:id/withdraw`**
**Auth:** Bearer, role=candidate

**Request body:**
- `reason` — optional, string, max 500 characters

**Ownership check:** Same as C-2 — return 404 if not found or not owned.

**Business rules:** See us6-withdrawal-and-history-log.md Part 1 for the full withdrawal rules. Summary:
- Allowed from: applied, reviewed, shortlisted, interviewed, offered
- Blocked from: hired (403), rejected (403), withdrawn (409), job_removed (403)
- Once withdrawn it is permanent

**Response:** 200
- `id`
- `current_status` — will be "withdrawn"
- `withdrawn_at`
- `withdrawn_reason`

**Possible errors:**
- 401 — unauthenticated
- 403 — withdrawal not allowed at current stage
- 404 — not found or not owned
- 409 — already withdrawn

---

## Employer Endpoints

---

### E-1 — List all applications across all my jobs (global inbox)
**`GET /api/v1/employer/applications`**
**Auth:** Bearer, role=employer

The employer's global inbox. Shows applications across every job they own. Useful for a dashboard-level view. Does not include the full history or interviews — list only.

**Query parameters:**
- `status` — optional, filter by a single current_status value
- `job_id` — optional, uuid; narrows results to one specific job (see E-2 for the dedicated per-job endpoint)
- `sort` — optional, one of: `applied_at_desc` (default), `applied_at_asc`, `updated_at_desc`
- `page` — default 1
- `per_page` — default 20, max 50

**Ownership check:** Results are always scoped to jobs where `employer_id` matches the authenticated employer. The `job_id` filter is also validated to ensure it belongs to this employer — return 403 if the job_id exists but belongs to another employer.

**Response fields per item:**
- `id`
- `current_status`
- `applied_at`
- `updated_at`
- `job_removed_at`
- `cover_letter` — truncated to 200 characters in list view
- `resume_url`
- `job_snapshot` — partial: title, location, type only
- `candidate_snapshot` — partial: name, email, headline, location, skills, resume_url only

**Possible errors:**
- 401 — unauthenticated
- 403 — job_id filter used but job belongs to another employer

---

### E-2 — List applications for a specific job
**`GET /api/v1/employer/jobs/:job_id/applications`**
**Auth:** Bearer, role=employer

Scoped to one job. This is the endpoint the employer uses on the job detail screen to manage the hiring pipeline for that position. Same item shape as E-1 but always filtered to one job and with a pipeline summary included in the response meta.

**Ownership check:** The job must belong to the authenticated employer. Return 404 if not found or not owned.

**Query parameters:**
- `status` — optional, filter by a single current_status value
- `sort` — optional, same options as E-1, default `applied_at_desc`
- `page` — default 1
- `per_page` — default 20, max 50

**Response:**
- `pipeline_summary` — object with a count per stage for this job. Computed with a single GROUP BY query on applications for that job. Fields: applied, reviewed, shortlisted, interviewed, offered, hired, rejected, withdrawn, job_removed. Stages with zero applications are included with value 0.
- `data` — paginated array of applications, same item shape as E-1
- `meta` — standard pagination meta: current_page, last_page, per_page, total

**Why this is separate from E-1:** E-1 is the global inbox. E-2 is the per-job pipeline view. They serve different UI screens. E-2 returns pipeline_summary which only makes sense in the context of a single job and would be meaningless or expensive to compute on a global inbox.

**Possible errors:**
- 401 — unauthenticated
- 404 — job not found or not owned by this employer

---

### E-3 — Get a single application in detail (employer view)
**`GET /api/v1/employer/applications/:id`**
**Auth:** Bearer, role=employer

Full application detail for the employer. Includes complete snapshots, full history log, and all interviews including soft-deleted ones.

**Ownership check:** The application's job must belong to the authenticated employer. Return 404 if not found or not owned.

**Response fields:**
- `id`
- `current_status`
- `job_removed_at`
- `applied_at`
- `updated_at`
- `cover_letter`
- `resume_url`
- `job_snapshot` — full object
- `employer_snapshot` — full object
- `candidate_snapshot` — full object
- `history` — same shape as C-2 history array
- `interviews` — same shape as C-2 interviews array, including soft-deleted ones with cancellation details

**Possible errors:**
- 401 — unauthenticated
- 404 — not found or application does not belong to this employer's jobs

---

### E-4 — Update application status
**`PATCH /api/v1/employer/applications/:id/status`**
**Auth:** Bearer, role=employer

Moves the application to the next stage in the pipeline.

**Ownership check:** Same as E-3.

**Request body:**
- `status` — required, the target stage value
- `notes` — optional, text; appears as the note on the new history log entry

**Business rules:**
- Transition must be valid per the forward-only map in us6-withdrawal-and-history-log.md Part 2
- Cannot update status of a withdrawn or job_removed application
- On success: inserts a new application_stages row (is_system=false, changed_by_user_id=authenticated user id), updates current_status on the application, fires ApplicationStatusChanged event

**Response:** 200
- `id`
- `current_status` — the new status
- `history` — full updated history array so the frontend can re-render the timeline without a second fetch

**Possible errors:**
- 401 — unauthenticated
- 403 — application is withdrawn or job_removed
- 404 — not found or not owned
- 409 — invalid transition: "Invalid transition: application cannot move from {current} to {requested}"
- 422 — status field missing or not a recognised stage value

---

### E-5 — Schedule an interview
**`POST /api/v1/employer/applications/:id/interviews`**
**Auth:** Bearer, role=employer

Creates a new interview linked to an application.

**Ownership check:** Same as E-3.

**Request body:**
- `scheduled_at` — required, ISO 8601 datetime, must be strictly in the future
- `duration_minutes` — optional, integer, default 60, min 15, max 480
- `location_type` — required, one of: video_call, phone, in_person
- `location_details` — optional, string max 255; strongly expected when location_type is video_call or in_person
- `notes` — optional, text

**Business rules:**
- Application current_status must be shortlisted or interviewed
- If application current_status is shortlisted, auto-advance it to interviewed by inserting a system stage row (stage=interviewed, is_system=true, notes="Interview scheduled") and updating current_status
- created_by_user_id is set to the authenticated user's id

**Response:** 201
- Full interview object
- `application_current_status` — string; lets the frontend know if the status was auto-advanced without needing to re-fetch the application

**Possible errors:**
- 401 — unauthenticated
- 403 — application status is not shortlisted or interviewed
- 404 — not found or not owned
- 422 — validation errors (past date, missing required fields)

---

### E-6 — Reschedule an interview
**`PATCH /api/v1/employer/applications/:id/interviews/:interview_id/reschedule`**
**Auth:** Bearer, role=employer

Changes the scheduled time, location, or notes of an existing interview. Separate from cancellation because the intent, validation, and side effects differ.

**Ownership check:** Interview must belong to an application owned by this employer.

**Request body:** At least one of:
- `scheduled_at` — must be in the future if provided
- `duration_minutes`
- `location_type`
- `location_details`
- `notes`

**Business rules:**
- Interview status must be `scheduled`
- Interview must not be soft-deleted
- At least one field must be provided, otherwise return 422

**Response:** 200 — full updated interview object

**Possible errors:**
- 401 — unauthenticated
- 403 — interview status is not scheduled
- 404 — interview not found or not owned
- 422 — no fields provided, or scheduled_at is in the past

---

### E-7 — Cancel an interview
**`PATCH /api/v1/employer/applications/:id/interviews/:interview_id/cancel`**
**Auth:** Bearer, role=employer

Cancels a scheduled interview. Separate from reschedule because it is a terminal action with different side effects.

**Ownership check:** Same as E-6.

**Request body:**
- `cancellation_note` — optional, text

**Business rules:**
- Interview status must be `scheduled`
- Sets status to `cancelled`, cancellation_reason to `employer_cancelled`, cancellation_note to provided text if any
- Soft-deletes the interview row so it remains visible in history

**Response:** 200 — full updated interview object including cancellation fields and deleted_at

**Possible errors:**
- 401 — unauthenticated
- 403 — interview is not in scheduled status
- 404 — not found or not owned

---

### E-8 — Mark interview outcome
**`PATCH /api/v1/employer/applications/:id/interviews/:interview_id/outcome`**
**Auth:** Bearer, role=employer

Records the result of an interview after it has taken place. Distinct from cancel because it is a post-fact record, not a change of plan. Does not soft-delete the interview.

**Ownership check:** Same as E-6.

**Request body:**
- `status` — required, one of: `completed`, `no_show`
- `notes` — optional, text; post-interview notes or feedback visible in the history

**Business rules:**
- Interview must currently have status `scheduled`
- Interview must not be soft-deleted
- Updates status and notes in place
- Does not automatically advance the application status — employer does that separately via E-4

**Response:** 200 — full updated interview object

**Possible errors:**
- 401 — unauthenticated
- 403 — interview is not in scheduled status
- 404 — not found or not owned
- 422 — status value is not completed or no_show

---

## Full endpoint index

### Candidate
| Ref | Method | Path | Description |
|-----|--------|------|-------------|
| C-1 | GET | `/api/v1/candidate/applications` | List my applications (paginated, filterable by status) |
| C-2 | GET | `/api/v1/candidate/applications/:id` | Single application detail with full history and interviews |
| C-3 | POST | `/api/v1/candidate/applications` | Apply to a job |
| C-4 | PATCH | `/api/v1/candidate/applications/:id/withdraw` | Withdraw an application |

### Employer
| Ref | Method | Path | Description |
|-----|--------|------|-------------|
| E-1 | GET | `/api/v1/employer/applications` | Global inbox across all jobs |
| E-2 | GET | `/api/v1/employer/jobs/:job_id/applications` | Applications for a specific job with pipeline summary |
| E-3 | GET | `/api/v1/employer/applications/:id` | Single application detail with full history and interviews |
| E-4 | PATCH | `/api/v1/employer/applications/:id/status` | Move application through the pipeline |
| E-5 | POST | `/api/v1/employer/applications/:id/interviews` | Schedule an interview |
| E-6 | PATCH | `/api/v1/employer/applications/:id/interviews/:interview_id/reschedule` | Reschedule an interview |
| E-7 | PATCH | `/api/v1/employer/applications/:id/interviews/:interview_id/cancel` | Cancel an interview |
| E-8 | PATCH | `/api/v1/employer/applications/:id/interviews/:interview_id/outcome` | Mark interview as completed or no_show |

---

## Notes for the implementing agent

**E-1 vs E-2:** E-2 is nested under `/employer/jobs/:job_id/applications` and requires its own route group in Laravel. The controller logic can live in the same ApplicationController but as a separate method. The key difference is E-2 always returns pipeline_summary and E-1 never does.

**E-6, E-7, E-8 are three separate PATCH actions.** Do not merge them. They have different required fields, different validations, and different side effects. Action-named sub-routes are intentional.

**Soft-deleted interviews must appear in C-2 and E-3.** Use `withTrashed()` when loading interviews for detail endpoints. The `deleted_at` field tells the frontend to render them with a visual indicator.

**pipeline_summary in E-2** must be computed with a single GROUP BY query, not separate count queries per stage.

**Ownership checks belong in Policy classes**, not inline in controllers. One ApplicationPolicy and one InterviewPolicy is the clean approach. All eight endpoints should go through these policies.

**404 over 403 for ownership failures.** When a resource exists but does not belong to the authenticated user, return 404. Returning 403 leaks the existence of the resource to unauthorized users.
