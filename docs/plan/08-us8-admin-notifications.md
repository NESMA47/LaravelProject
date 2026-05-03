# User Story 8 — Admin Moderation & Notifications

> **Day:** 4 (Morning)  
> **Priority:** 🟡 Medium — Required for platform integrity, but does not block core hiring flow  
> **Prerequisite:** ALL previous USs (US1-US7) — admin needs data to moderate

---

## Goal

Build the admin dashboard for platform moderation: user management, job approval/rejection, category/skill management, review moderation, and the in-app notification system.

---

## Tables Needed

### New Migrations (2 tables)

1. **`reports`**
2. **`notifications`**

> **Note:** No new tables for admin — admin uses existing tables (`users`, `jobs`, `categories`, `skills`, `company_reviews`).

### Migration Details

#### `reports`
```php
Schema::create('reports', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('reporter_id')->constrained('users')->cascadeOnDelete();
    $table->enum('target_type', ['job', 'review', 'user', 'employer']);
    $table->uuid('target_id'); // polymorphic FK (no DB-level FK since target tables differ)
    $table->enum('reason', ['spam', 'fraudulent', 'misleading', 'inappropriate', 'discriminatory', 'other']);
    $table->text('details')->nullable();
    $table->enum('status', ['pending', 'investigating', 'resolved', 'dismissed'])->default('pending');
    $table->foreignUuid('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->text('resolution_notes')->nullable();
    $table->timestamps();
});
```

#### `notifications`
```php
Schema::create('notifications', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
    $table->enum('type', [
        'application_status_changed',
        'new_application',
        'interview_scheduled',
        'saved_job_expiring',
        'job_flagged',
        'job_approved',
        'job_rejected',
        'review_approved',
        'system_announcement'
    ]);
    $table->string('title');
    $table->text('message');
    $table->json('data')->nullable(); // polymorphic payload: { application_id, job_id, status, ... }
    $table->string('action_url', 500)->nullable();
    $table->boolean('is_read')->default(false);
    $table->timestamp('read_at')->nullable();
    $table->timestamps();

    $table->index(['user_id', 'is_read', 'created_at']);
});
```

---

## Backend Endpoints

### Admin Endpoints (Bearer + role=admin)

| # | Method | Endpoint | Description |
|---|--------|----------|-------------|
| 8.1 | `GET` | `/api/v1/admin/dashboard` | Stats cards: total users, jobs, pending jobs, active jobs, applications, employers |
| 8.2 | `GET` | `/api/v1/admin/users` | List all users (paginated, filterable by role, status, search by name/email) |
| 8.3 | `GET` | `/api/v1/admin/users/:id` | User detail + role-specific activity (candidate: applications count; employer: jobs count) |
| 8.4 | `PATCH` | `/api/v1/admin/users/:id/status` | Activate/deactivate user |
| 8.5 | `GET` | `/api/v1/admin/jobs` | All jobs with moderation queue (filterable by status) |
| 8.6 | `GET` | `/api/v1/admin/jobs/:id` | Job detail + application list |
| 8.7 | `PATCH` | `/api/v1/admin/jobs/:id/status` | Approve, reject, close, re-activate a job |
| 8.8 | `DELETE` | `/api/v1/admin/jobs/:id` | Hard delete job (admin override) |
| 8.9 | `POST` | `/api/v1/admin/jobs` | Post job on behalf of an employer |
| 8.10 | `GET` | `/api/v1/admin/reviews` | Pending review moderation queue |
| 8.11 | `PATCH` | `/api/v1/admin/reviews/:id/approve` | Approve review (recalculates employer aggregates) |
| 8.12 | `PATCH` | `/api/v1/admin/reviews/:id/reject` | Reject review |
| 8.13 | `GET` | `/api/v1/admin/categories` | List categories |
| 8.14 | `POST` | `/api/v1/admin/categories` | Create category |
| 8.15 | `PUT` | `/api/v1/admin/categories/:id` | Update category |
| 8.16 | `GET` | `/api/v1/admin/skills` | List skills |
| 8.17 | `POST` | `/api/v1/admin/skills` | Create skill |
| 8.18 | `PUT` | `/api/v1/admin/skills/:id` | Update/merge skill |
| 8.19 | `GET` | `/api/v1/admin/reports` | All reports |
| 8.20 | `PATCH` | `/api/v1/admin/reports/:id` | Update investigation status |

### Notification Endpoints (Bearer — any authenticated role)

| # | Method | Endpoint | Description |
|---|--------|----------|-------------|
| 8.21 | `GET` | `/api/v1/notifications` | My notifications (paginated, newest first) |
| 8.22 | `PATCH` | `/api/v1/notifications/:id/read` | Mark notification as read |
| 8.23 | `PATCH` | `/api/v1/notifications/read-all` | Mark all notifications as read |
| 8.24 | `GET` | `/api/v1/notifications/unread-count` | Count of unread notifications (for badge) |

### Endpoint Details

#### 8.1 Admin Dashboard Stats
```json
// GET /api/v1/admin/dashboard
{
  "success": true,
  "data": {
    "stats": {
      "total_users": 124,
      "total_candidates": 98,
      "total_employers": 24,
      "total_admins": 2,
      "total_jobs": 56,
      "pending_jobs": 8,
      "active_jobs": 42,
      "closed_jobs": 6,
      "total_applications": 342,
      "total_reviews_pending": 12
    },
    "recent_jobs": [ /* last 5 jobs submitted for review */ ],
    "recent_users": [ /* last 5 registered users */ ]
  }
}
```

#### 8.7 Admin Update Job Status
```json
// PATCH /api/v1/admin/jobs/{id}/status
{
  "status": "active",
  "rejection_reason": null
}

// OR reject:
{
  "status": "rejected",
  "rejection_reason": "Job description contains discriminatory language. Please revise and resubmit."
}
```

**Business Rules:**
- Admin can transition any job to any status (no restriction)
- On `approve` (`status → active`): set `expires_at` to 30 days from now (or keep existing)
- On `reject`: store `rejection_reason` in `jobs.rejection_reason`
- Fire `JobApproved` or `JobRejected` event → notify employer
- On `delete`: hard delete (removes from DB entirely). Only admin can do this.

#### 8.9 Admin Create Job (On Behalf of Employer)
```json
// POST /api/v1/admin/jobs
{
  "employer_id": "uuid",
  "title": "...",
  "category_id": "uuid",
  // ... same fields as employer job creation
  "status": "active" // admin can set directly to active
}
```

#### 8.21 Notifications List
```json
// GET /api/v1/notifications
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "type": "application_status_changed",
      "title": "Your application status has been updated",
      "message": "Vodafone Egypt updated your application for Senior Frontend Developer to Shortlisted.",
      "data": {
        "application_id": "uuid",
        "job_id": "uuid",
        "status": "shortlisted"
      },
      "action_url": "/candidate/applications",
      "is_read": false,
      "read_at": null,
      "created_at": "2026-05-03T14:05:00Z"
    }
  ],
  "meta": { "current_page": 1, "last_page": 5, "total": 87, "unread_count": 3 }
}
```

---

## Notification System Architecture

### Events → Listeners

Register these event/listener pairs:

| Event | Listener | Who Gets Notified |
|-------|----------|-------------------|
| `ApplicationSubmitted` | `NotifyEmployerNewApplication` | Job poster + employer team members |
| `ApplicationStatusChanged` | `NotifyCandidateStatusChange` | The candidate who applied |
| `JobApproved` | `NotifyEmployerJobApproved` | Job poster |
| `JobRejected` | `NotifyEmployerJobRejected` | Job poster (with reason) |
| `ReviewSubmitted` | `NotifyAdminNewReview` | All admins (for moderation queue) |
| `ReviewApproved` | `NotifyCandidateReviewApproved` | Review author |

### Listener Implementation Pattern
```php
class NotifyCandidateStatusChange implements ShouldQueue
{
    public function handle(ApplicationStatusChanged $event)
    {
        $application = $event->application;
        $candidate = $application->candidate;

        Notification::create([
            'user_id' => $candidate->user_id,
            'type' => 'application_status_changed',
            'title' => 'Your application status has been updated',
            'message' => sprintf(
                '%s updated your application for %s to %s.',
                $application->employer_snapshot['company_name'],
                $application->job_snapshot['title'],
                $application->status
            ),
            'data' => [
                'application_id' => $application->id,
                'job_id' => $application->job_id,
                'status' => $application->status,
            ],
            'action_url' => '/candidate/applications',
        ]);
    }
}
```

> **Queue:** Use Laravel queues with Redis/driver for async notification creation. In development, use `sync` driver so you see immediate results.

---

## Frontend Refactoring

### Files to Modify

| File | Change |
|------|--------|
| `src/api/services/api.js` | **Add admin endpoints:** `getDashboardStats`, `getUsers`, `getUser`, `updateUserStatus`, `getAdminJobs`, `updateJobStatus`, `adminDeleteJob`, `adminCreateJob`, `getPendingReviews`, `approveReview`, `rejectReview`, `getReports`, `updateReport`, `getCategories`, `createCategory`, `updateCategory`, `getSkills`, `createSkill`, `updateSkill`. Add notification endpoints: `getNotifications`, `markRead`, `markAllRead`, `getUnreadCount`. |
| `src/stores/admin.store.js` | **Major refactor.** Current store fetches ALL data at once (`fetchAllData()`). New backend has paginated endpoints. Split into: `fetchDashboard()`, `fetchUsers(page, filters)`, `fetchJobs(page, status)`, `fetchPendingReviews()`, `fetchReports()`. |
| `src/features/admin/views/AdminDashboardView.vue` | Stats cards now populated from `GET /admin/dashboard`. |
| `src/features/admin/views/ManageUsersView.vue` | User list is paginated. Activate/deactivate calls `PATCH /admin/users/:id/status`. |
| `src/features/admin/views/ManageJobsView.vue` | Job moderation queue with approve/reject/close actions. Rejection requires reason input. |
| `src/features/admin/views/AdminUserDetailsView.vue` | Fetch individual user via `GET /admin/users/:id`. |
| `src/features/admin/views/AdminJobDetailsView.vue` | Fetch job with applications. |
| `src/components/layout/CandidateLayout.vue` / `EmployerLayout.vue` | Add notification bell icon with unread count badge (`GET /notifications/unread-count`). |
| `src/features/candidate/views/DashboardView.vue` | Display recent notifications widget. |

### Data Shape Changes

Old json-server admin data:
```javascript
// admin store fetched ALL tables at once
this.users = (await usersApi.getAll()).data
this.jobs = (await jobsApi.getAll()).data
// everything loaded into memory
```

New Laravel:
```javascript
// paginated, filtered
const res = await adminApi.getUsers({ page: 1, role: 'candidate', status: 'active' })
// res.data.data = users array
// res.data.meta = pagination info
```

**Action:** Update all admin views to use paginated lists with frontend pagination controls.

---

## Testing Checklist

- [ ] `GET /admin/dashboard` returns accurate aggregate counts
- [ ] `GET /admin/users` is paginated and filterable by role
- [ ] `PATCH /admin/users/:id/status` toggles `is_active`
- [ ] Admin cannot deactivate another admin account → 403
- [ ] Admin approves pending job → status=active, employer receives notification
- [ ] Admin rejects job with reason → status=rejected, reason stored, employer receives notification
- [ ] Admin hard-deletes job → job removed from DB, but applications still exist (with snapshots)
- [ ] Admin approves review → `is_approved=true`, employer aggregates updated
- [ ] Application status change → candidate receives notification
- [ ] New application → employer receives notification
- [ ] Notification badge shows unread count
- [ ] Mark notification as read → `is_read=true`, badge count decreases
- [ ] Mark all as read → all user's notifications updated

---

*Next: Read `09-us9-frontend-integration.md` — the final integration step.*
