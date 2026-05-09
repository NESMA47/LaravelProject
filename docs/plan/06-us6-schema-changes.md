# US6 — Application Pipeline Schema Changes
**Document type:** Change specification for agent/LLM implementation
**Scope:** Adjustments to the original US6 schema before or via additional migrations
**Status:** Pending implementation

---

## Context

The original US6 user story defines the application pipeline schema with specific design choices that need to be revised. This document lists every change required, the reason behind each one, and the expected outcome. No code is included — the implementing agent should write new migrations to apply these changes on top of any existing US6 migration.

---

## Change 1 — Remove `status` column from `applications` table

**Original design:** The `applications` table had a `status` enum column (`applied`, `reviewed`, `shortlisted`, `interviewed`, `offered`, `hired`, `rejected`, `withdrawn`) as the primary tracker of application state.

**New design:** The `status` column is dropped entirely. The `application_stages` table becomes the source of truth for application state. Every state transition is recorded as a row in `application_stages`, and the sequence of those rows tells the full story.

**Reason:** Storing status in two places (the column and the stages table) creates the risk of them diverging. Removing the column forces all reads and writes to go through stages, which is the correct single source of truth.

**What to add instead:** A `current_status` string column (max 30 characters) is added to `applications` as a denormalized cache only. It mirrors the `stage` value of the most recently inserted `application_stages` row for that application. It exists purely for query performance on list endpoints (filtering and sorting without joining stages). It must never be written to directly by business logic — only updated automatically whenever a new stage row is inserted. Its default value is `applied`.

**Impact on indexes:** The original index on `(job_id, status)` should be replaced with an index on `(job_id, current_status)`.

---

## Change 2 — Change `job_id` foreign key behavior in `applications` table

**Original design:** `job_id` was a non-nullable foreign key with `cascadeOnDelete`, meaning if the job is deleted, all related applications are deleted.

**New design:** `job_id` becomes nullable. The foreign key constraint changes to `nullOnDelete`, meaning if the job is deleted (soft-deleted), the `job_id` column on existing applications is set to `null` rather than the application row being deleted.

**Reason:** Candidates should never lose their application history because an employer removed a job. The job snapshot stored on the application already preserves all job details at the time of application, so the live `job_id` reference is not needed for data integrity. Nullifying it on deletion keeps the application record intact.

**Additional column required:** A new column `job_removed_at` (nullable timestamp) must be added to the `applications` table. This is set to the current timestamp by a model observer when the job is soft-deleted. The frontend uses the presence of this value to display a message to the candidate such as "This job was removed by the employer" rather than showing a broken or missing job reference.

**Unique constraint caveat:** The original design had a unique constraint on `(job_id, candidate_id)` to prevent duplicate applications. Once `job_id` can be null, this database-level constraint no longer works reliably because MySQL and most databases allow multiple null values in a unique index. The duplicate-application enforcement must move to the application layer. Before inserting a new application, the service must check whether a row already exists where `job_id` matches the requested job and `candidate_id` matches the current candidate. A new column `original_job_id` (uuid, non-nullable, never modified after insert) should be added to `applications` and the unique constraint placed on `(original_job_id, candidate_id)` instead. This column is written once at application time and never touched again, so it is safe to constrain.

---

## Change 3 — Add `job_removed` as a valid stage value in `application_stages`

**Original design:** The `stage` enum in `application_stages` contained: `applied`, `reviewed`, `shortlisted`, `interviewed`, `offered`, `hired`, `rejected`, `withdrawn`.

**New design:** A new value `job_removed` is added to the enum. This stage is inserted automatically by the system (not by a human user) when a job is soft-deleted. It appears in the candidate's stage timeline with a note explaining what happened.

**Reason:** The candidate needs a visible, explicit record in their application timeline explaining why the status changed without any action on their part. A system-inserted stage with a clear note is the cleanest way to communicate this without requiring frontend-specific workarounds.

---

## Change 4 — Add `is_system` boolean column to `application_stages`

**Original design:** No distinction existed between stages inserted by human users and stages inserted automatically by the system.

**New design:** A new boolean column `is_system` is added to `application_stages` with a default value of `false`. It is set to `true` when a stage row is created automatically by an observer, event, or background process rather than by a user action (e.g., the initial `applied` stage on submission, the `job_removed` stage on job deletion).

**Reason:** Employer-facing views should be able to filter out system-generated stages when displaying "actions taken by employer" history. Candidate-facing views can display all stages including system ones, with appropriate labeling. Without this flag, the frontend has no reliable way to distinguish the two.

---

## Change 5 — Add `softDeletes` to `interviews` table

**Original design:** No soft delete on `interviews`. Deleting an interview record removed it permanently.

**New design:** A `deleted_at` nullable timestamp column is added to `interviews` (standard Laravel soft delete). Interviews are never hard-deleted. When an interview needs to be removed from view, it is soft-deleted so the record remains queryable.

**Reason:** Candidates should not experience interviews silently disappearing from their timeline. A soft-deleted interview is still readable by the application and can be displayed to the candidate with an explanation (see Change 6 below).

---

## Change 6 — Add `cancellation_reason` and `cancellation_note` columns to `interviews`

**Original design:** The `interviews` table had a `status` enum (`scheduled`, `completed`, `cancelled`, `no_show`) but no way to explain why a cancellation happened.

**New design:** Two new columns are added to `interviews`:

- `cancellation_reason`: a nullable enum with the following values: `job_removed`, `employer_cancelled`, `candidate_cancelled`, `other`. Set when `status` changes to `cancelled`.
- `cancellation_note`: a nullable text column for a human-readable explanation. For job-removal cancellations this is populated automatically with a standard message. For employer or candidate-initiated cancellations this can be left blank or filled manually.

**Reason:** When a candidate sees a cancelled interview, they need to know why. `job_removed` as a reason allows the frontend to show a specific message ("This interview was cancelled because the employer removed the job listing") rather than a generic cancellation notice. The other values cover manual cancellations with appropriate messaging.

---

## Change 7 — Add `resume_url` column to `applications` table

**Original design:** Resume URL was only stored inside the `candidate_snapshot` JSON blob.

**New design:** A nullable string column `resume_url` (max 500 characters) is added to the `applications` table at the top level. It is populated at application time with the URL of the resume the candidate submitted (either their default resume or the one explicitly chosen via `resume_id`).

**Reason:** Querying or displaying the resume URL from inside a JSON blob requires extracting JSON in SQL or loading the full snapshot in PHP. A top-level column makes this accessible for list views and search without parsing JSON. The value inside `candidate_snapshot` remains as well for full snapshot integrity.

---

## Change 8 — Add `original_job_id` column to `applications` table

**Described in Change 2 above** but listed separately here for clarity.

A non-nullable uuid column `original_job_id` is added to `applications`. It is written once at insert time with the `job_id` value and is never modified afterward. It exists solely to support the unique constraint on `(original_job_id, candidate_id)` after `job_id` becomes nullable. No foreign key constraint is placed on this column since the referenced job may be soft-deleted or otherwise gone.

---

## Change 9 — Observer behavior on job soft-delete (no migration, implementation note)

This is not a schema change but must be implemented alongside the migrations above.

When a job is soft-deleted, an observer on the `Job` model must do the following for every application linked to that job whose `current_status` is not `hired`, `rejected`, or `withdrawn`:

- Set `job_removed_at` to the current timestamp on the `applications` row.
- Insert a new row in `application_stages` with `stage = job_removed`, a standard note, `changed_by_user_id = null`, and `is_system = true`.
- Update `current_status` on the `applications` row to `job_removed`.
- For every interview linked to that application where `status = scheduled`: update `status` to `cancelled`, set `cancellation_reason` to `job_removed`, set `cancellation_note` to a standard message, then soft-delete the interview row.

All of the above must run inside a database transaction per application.

---

## Summary of all column-level changes

### `applications` table — columns added
- `original_job_id` — uuid, non-nullable, no FK constraint, written once at insert
- `current_status` — string(30), default `applied`, denormalized cache of latest stage
- `job_removed_at` — timestamp, nullable
- `resume_url` — string(500), nullable

### `applications` table — columns removed
- `status` — the enum column is dropped entirely

### `applications` table — foreign key change
- `job_id` changes from non-nullable with `cascadeOnDelete` to nullable with `nullOnDelete`

### `applications` table — constraint change
- Unique constraint on `(job_id, candidate_id)` is dropped
- New unique constraint on `(original_job_id, candidate_id)` is added

### `applications` table — index changes
- Index on `(job_id, status)` is replaced by index on `(job_id, current_status)`
- New index on `job_removed_at`
- New index on `(job_id, applied_at)`

### `application_stages` table — enum change
- `stage` enum gains a new value: `job_removed`

### `application_stages` table — columns added
- `is_system` — boolean, default `false`

### `interviews` table — columns added
- `cancellation_reason` — nullable enum: `job_removed`, `employer_cancelled`, `candidate_cancelled`, `other`
- `cancellation_note` — text, nullable
- `deleted_at` — timestamp, nullable (soft deletes)
