# US6 — Withdrawal Rules & Application History Log
**Document type:** Business logic and schema specification for agent/LLM implementation
**Scope:** Withdrawal rules, stage transition direction, and history log design
**Depends on:** us6-schema-changes.md

---

## Part 1 — Withdrawal Rules

### Who can withdraw
Only the candidate who owns the application. No other user, including the employer or admin, can trigger a withdrawal.

### Stages where withdrawal is allowed
- `applied`
- `reviewed`
- `shortlisted`
- `interviewed`
- `offered`

### Stages where withdrawal is blocked
- `hired` — the process is complete. Return 403 with message "You cannot withdraw from an application that has been accepted."
- `rejected` — the employer already closed it. Return 403 with message "This application has already been closed by the employer."
- `withdrawn` — already withdrawn. Return 409 with message "You have already withdrawn this application."
- `job_removed` — the job no longer exists. Return 403 with message "This application is no longer active because the job was removed."

### Withdrawal reason
Always optional. The candidate may provide a plain text reason. There is no enum for reason values — it is a free text field. If omitted, the history log note is recorded as "Candidate withdrew their application." If provided, the note is recorded as the reason the candidate submitted.

### What happens on withdrawal
The following must all happen inside a single database transaction:

1. Set `withdrawn_at` to the current timestamp on the `applications` row.
2. Set `withdrawn_reason` to the provided text if any.
3. Insert a new row in `application_stages` with `stage = withdrawn`, `is_system = false`, `changed_by_user_id = the candidate's user id`, and `notes = the reason or the default message`.
4. Update `current_status` on the `applications` row to `withdrawn`.
5. If any interviews linked to this application have `status = scheduled`, update each one: set `status = cancelled`, set `cancellation_reason = candidate_cancelled`, set `cancellation_note = "Candidate withdrew their application."`, then soft-delete the interview row.
6. Decrement `applications_count` on the `jobs` row by 1. This should be an atomic decrement, not a read-then-write. Do not decrement below zero.
7. Fire an `ApplicationWithdrawn` event.

### Once withdrawn, it is permanent
A withdrawn application cannot be reopened, reactivated, or transitioned to any other stage. Any attempt to update the status of a withdrawn application must return 403.

---

## Part 2 — Stage Transition Direction

Stages move forward only. No backward transitions are allowed under any circumstances.

### The allowed forward-only transition map

| Current stage | Allowed next stages |
|---|---|
| `applied` | `reviewed`, `shortlisted`, `rejected` |
| `reviewed` | `shortlisted`, `interviewed`, `rejected` |
| `shortlisted` | `interviewed`, `rejected` |
| `interviewed` | `offered`, `rejected` |
| `offered` | `hired`, `rejected` |
| `hired` | none — terminal |
| `rejected` | none — terminal |
| `withdrawn` | none — terminal |
| `job_removed` | none — terminal |

Any attempt to move to a stage not listed in the allowed next stages for the current stage must return 409 with message "Invalid transition: application cannot move from {current} to {requested}."

This validation must live in a dedicated service method, not in the controller. The controller passes the current stage and the requested stage to the service, which either approves and executes the transition or throws a domain exception.

---

## Part 3 — Application History Log

### Concept
Every meaningful event that happens to an application is recorded as a log entry. This is modeled after the activity timeline seen on GitHub issues and pull requests — a chronological list of actions, each with an actor, a label, a note, and a timestamp. Both the candidate and the employer see the full log with no filtering.

### What the log is built from
The history log is not a separate table. It is assembled from the existing `application_stages` table. Every row in `application_stages` is one log entry. The log is always ordered by `created_at` ascending so the candidate and employer read the story from top to bottom.

### What each log entry contains
Each entry in the rendered log must include the following fields:

- `stage` — the stage value (e.g. `applied`, `reviewed`, `shortlisted`, `withdrawn`, `job_removed`)
- `label` — a human-readable sentence describing what happened (see label map below)
- `actor` — who caused this entry. If `is_system = true`, the actor is displayed as "System". If `changed_by_user_id` is set, the actor is the full name of that user. If `changed_by_user_id` is null and `is_system = false`, display "Unknown".
- `notes` — the free text note attached to the stage, if any. Shown below the label.
- `created_at` — the timestamp of the event, formatted as a readable date and time.

### Label map
The frontend or API response should map each stage value to a default human-readable label. These labels are used when rendering the timeline.

| Stage value | Default label |
|---|---|
| `applied` | "Candidate applied to this job" |
| `reviewed` | "Employer reviewed this application" |
| `shortlisted` | "Employer shortlisted this candidate" |
| `interviewed` | "Employer moved this application to interview stage" |
| `offered` | "Employer extended an offer to the candidate" |
| `hired` | "Candidate was hired" |
| `rejected` | "Employer rejected this application" |
| `withdrawn` | "Candidate withdrew their application" |
| `job_removed` | "Employer removed the job listing" |

### What the API returns for the log
The application detail endpoint (both candidate-facing and employer-facing) must include a `history` array in its response. Each element of the array corresponds to one `application_stages` row and follows this shape:

- `id` — the stage row uuid
- `stage` — the raw stage value
- `label` — the human-readable label from the map above
- `actor_name` — full name of the user who caused this, or "System" if `is_system = true`
- `actor_role` — `candidate`, `employer`, or `system`
- `notes` — nullable free text
- `created_at` — ISO 8601 timestamp

### Determining `actor_role`
- If `is_system = true` → `actor_role = system`
- If `changed_by_user_id` belongs to a user whose role is `candidate` → `actor_role = candidate`
- If `changed_by_user_id` belongs to a user whose role is `employer` → `actor_role = employer`

This field allows the frontend to apply different visual styling per actor type — for example the mockup shows a timeline where each entry can be color-coded or icon-coded by who performed the action.

### Visibility
Both candidate and employer receive the full unfiltered history array. There is no per-role filtering of log entries. The `job_removed` system entry is visible to both sides.

---

## Part 4 — Schema additions required by this document

The following additions are needed on top of what was already specified in `us6-schema-changes.md`. These are incremental — write a new migration for them, do not modify the original.

### `application_stages` table — no new columns needed
The existing columns (`stage`, `notes`, `changed_by_user_id`, `is_system`, `created_at`) are sufficient to build the full history log. No additional columns required.

### `applications` table — confirm these columns exist
The following columns must be present from the previous change document. If not yet added, add them in the same migration:
- `withdrawn_at` — nullable timestamp
- `withdrawn_reason` — nullable string(255)
- `current_status` — string(30), default `applied`

---

## Part 5 — Summary of business rules in one place

| Rule | Detail |
|---|---|
| Withdrawal allowed stages | applied, reviewed, shortlisted, interviewed, offered |
| Withdrawal blocked stages | hired, rejected, withdrawn, job_removed |
| Withdrawal reason | Always optional, free text |
| Withdrawal is permanent | Cannot be undone or reopened |
| Interview auto-cancel on withdrawal | Yes — all scheduled interviews are soft-deleted with cancellation_reason = candidate_cancelled |
| applications_count decrement on withdrawal | Yes — atomic, cannot go below zero |
| Stage direction | Forward only, no reversals |
| Invalid transition response | 409 with descriptive message |
| History log source | application_stages table, ordered by created_at asc |
| History log visibility | Full log visible to both candidate and employer |
| System entries in log | Labeled as actor "System", actor_role "system" |
