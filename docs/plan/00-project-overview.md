# HireMasr — 4-Day Implementation Plan (Laravel + Vue)

> **Backend:** Laravel 11 + MySQL 8.0 + Sanctum API Auth  
> **Frontend:** Existing Vue 3 + Pinia + Axios (refactored to consume real API)  
> **Plan Generated:** 2026-05-03  
> **Estimated Duration:** 4 working days (8 user stories, sequential where marked)

---

## Legend

| Symbol | Meaning |
|--------|---------|
| `→` | **Sequential dependency** — must be fully implemented & tested before next US |
| `∥` | **Parallel** — can be developed simultaneously after shared prerequisite |
| 🔴 | Critical path — blocks multiple downstream stories |
| 🟡 | Medium risk — some downstream dependency |
| 🟢 | Low risk — mostly additive, minimal blocking |

---

## Dependency Graph

```
US1: Authentication & File Upload (Day 1 AM)     🔴
     →
US2: Core Taxonomy APIs (Day 1 PM)              🟡
     →
     ├→ US3: Candidate Profile (Day 2 AM)        🟡
     └→ US4: Employer Profile & Jobs (Day 2 PM) 🟡
          →
     US3 + US4 ──→ US5: Public Discovery (Day 3 AM)   🟡
     US3 + US4 ──→ US6: Application Pipeline (Day 3 PM) 🔴
     US3 + US4 ──→ US7: Saved Jobs & Reviews (Day 3 PM)  🟢
          →
     US6 ──→ US8: Admin & Notifications (Day 4 AM)       🟡
     ALL ──→ US9: Frontend API Integration (Day 4 PM)    🔴
```

---

## Day-by-Day Schedule

### Day 1 — Foundation
| Morning (US1) | Afternoon (US2) |
|---------------|-----------------|
| Auth system (register, login, logout, refresh, me, forgot/reset password, email verify) | Categories, Skills seeders & public APIs. File upload endpoint (avatars, logos, resumes) |

### Day 2 — Profiles & Job Posting
| Morning (US3) | Afternoon (US4) |
|---------------|-----------------|
| Candidate profile (bio, headline, socials, education CRUD, experience CRUD, skills sync, resumes upload with title & default flag) | Employer company profile, team members, job CRUD with skills sync, status toggle |

### Day 3 — Discovery & Hiring
| Morning (US5) | Afternoon (US6 + US7) |
|---------------|------------------------|
| Public job listing with search/filters/sort, job detail, company public pages, approved reviews | Application pipeline (apply, withdraw, accept/reject, stage history, interviews), saved jobs, company reviews |

### Day 4 — Admin & Integration
| Morning (US8) | Afternoon (US9) |
|---------------|-----------------|
| Admin moderation (users, jobs, categories, skills, reports), notifications (in-app only) | Refactor `api.js`, update all Pinia stores, fix router guards, end-to-end testing |

---

## Shared Conventions (Apply to ALL User Stories)

### Laravel Conventions
- **Auth:** Laravel Sanctum (API token-based, not session-based — stateless for SPA)
- **Response Format:** Wrap all API responses in a consistent envelope:
  ```json
  {
    "success": true,
    "data": { ... },
    "message": "Optional human message"
  }
  ```
- **Error Format:**
  ```json
  {
    "success": false,
    "message": "Validation failed",
    "errors": { "field": ["error message"] }
  }
  ```
- **Validation:** Use `FormRequest` classes. Do NOT validate in controllers.
- **Authorization:** Use `Policy` classes for every resource (`view`, `create`, `update`, `delete`).
- **Resources:** Use `JsonResource` classes to shape API output (hide internal IDs like `user_id` where appropriate).
- **Database:** MySQL 8.0+, UUID primary keys via `$table->uuid('id')->primary()`, use `$table->foreignId('xxx_id')->constrained('table')->cascadeOnDelete()`.
- **Soft Deletes:** Use `SoftDeletes` trait on `users`, `employers`, `jobs`.
- **Events:** Fire Laravel Events for: `ApplicationSubmitted`, `ApplicationStatusChanged`, `JobPosted`, `JobApproved`, `JobRejected`. Notifications (US8) will listen to these.
- **Queue:** Configure Redis queue driver for email notifications (even if just local log driver for dev).
- **Snapshots:** On application creation, use a service class to build and store JSON snapshots from live Eloquent models at that exact moment.

### Frontend Conventions
- **Base URL:** Move `axios.defaults.baseURL` to `import.meta.env.VITE_API_BASE_URL`
- **Auth Header:** Interceptor reads `localStorage.getItem('token')` and sets `Authorization: Bearer <token>`
- **Error Handling:** Global axios interceptor catches 401 → clear store → redirect to `/auth/login`
- **Data Shapes:** New API may return snake_case from Laravel. Frontend can either: (a) normalize in api service layer, or (b) update component bindings to use snake_case. **Recommendation:** Keep frontend camelCase and write a small normalizer utility in `api.js` to convert keys on every response.

### Testing Checklist (Per User Story)
- [ ] All listed endpoints return correct response format
- [ ] Authentication middleware rejects unauthenticated requests with 401
- [ ] Role middleware rejects wrong-role requests with 403
- [ ] Validation errors return 422 with field-level messages
- [ ] Ownership middleware prevents cross-tenant access (employer A cannot see employer B's jobs)
- [ ] Soft deletes work and are excluded by default from queries
- [ ] Frontend store actions correctly call new endpoints
- [ ] UI displays updated data without page refresh

---

## MySQL vs PostgreSQL Notes

Since the DB spec was written for PostgreSQL, here are the MySQL adaptations:

| PostgreSQL Feature | MySQL Equivalent |
|-------------------|------------------|
| `UUID` PK | `$table->uuid('id')->primary()` (use ` Ramsey\Uuid\Uuid` or Laravel's `Str::uuid()`) |
| `TIMESTAMPTZ` | `$table->timestamp('created_at')` — store UTC, convert in app layer |
| `JSONB` | `$table->json('column')` — MySQL 5.7+ supports JSON (but no GIN index). For filtering, use generated columns + indexes if needed. |
| `INET` | `$table->string('ip_address', 45)` (IPv6 compatible) |
| `CITEXT` | `$table->string('email')->unique()` + lowercase normalization in app layer |
| Full-text GIN | `$table->fullText(['title', 'description'])` (MySQL 5.6+ supports FULLTEXT indexes) |
| Partial unique index | MySQL does NOT support partial unique indexes. Enforce "one default resume per candidate" in application code (transaction + query) instead. |
| `gen_random_uuid()` | Use Laravel model boot or database default `UUID()` in MySQL 8+ |

> **Recommendation:** Use Laravel's `HasUuids` trait on models for automatic UUID generation.

---

## Files in This Plan

| File | Purpose |
|------|---------|
| `00-project-overview.md` | This file — timeline, conventions, dependencies |
| `01-us1-auth.md` | User Story 1 — Authentication & File Upload |
| `02-us2-core-data.md` | User Story 2 — Core Taxonomy (Categories, Skills) |
| `03-us3-candidate-profile.md` | User Story 3 — Candidate Profile Builder |
| `04-us4-employer-jobs.md` | User Story 4 — Employer Profile & Job CRUD |
| `05-us5-public-discovery.md` | User Story 5 — Public Job Discovery & Search |
| `06-us6-application-pipeline.md` | User Story 6 — Application Pipeline & Interviews |
| `07-us7-saved-jobs-reviews.md` | User Story 7 — Saved Jobs & Company Reviews |
| `08-us8-admin-notifications.md` | User Story 8 — Admin Moderation & Notifications |
| `09-us9-frontend-integration.md` | User Story 9 — Frontend API Refactoring |

---

*Next: Read `01-us1-auth.md` and start implementing in order.*
