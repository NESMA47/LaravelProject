# User Story 2 — Core Taxonomy (Categories & Skills)

> **Day:** 1 (Afternoon)  
> **Priority:** 🟡 Medium — Blocks job posting & filtering  
> **Prerequisite:** US1 (Authentication) — because the seeder may need to create admin-owned data, and employer job posting (US4) needs categories/skills to exist first.

---

## Goal

Create the foundational taxonomy tables that jobs and candidates will reference. These are read-mostly, publicly accessible lookup tables. Also create the seeder for initial categories and skills so the platform is usable immediately.

---

## Tables Needed

### New Migrations

1. **`categories`**
2. **`skills`**

> **Note:** Both tables should be created BEFORE `jobs`, `job_skills`, and `candidate_skills` because those tables reference them as foreign keys.

### Migration Details

#### `categories`
```php
Schema::create('categories', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name')->unique();
    $table->string('slug')->unique();
    $table->string('icon', 50)->nullable();
    $table->text('description')->nullable();
    $table->unsignedSmallInteger('display_order')->default(0);
    $table->boolean('is_active')->default(true);
    $table->unsignedInteger('jobs_count')->default(0);
    $table->timestamps();
});
```

#### `skills`
```php
Schema::create('skills', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name')->unique();
    $table->string('slug')->unique();
    $table->foreignUuid('category_id')->nullable()->constrained('categories')->nullOnDelete();
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->index(['category_id', 'is_active']);
});
```

---

## Backend Endpoints

### Public Endpoints (no auth required)

| # | Method | Endpoint | Auth | Description |
|---|--------|----------|------|-------------|
| 2.1 | `GET` | `/api/v1/categories` | Public | List active categories with `jobs_count` |
| 2.2 | `GET` | `/api/v1/categories/:slug` | Public | Single category detail |
| 2.3 | `GET` | `/api/v1/skills` | Public | List skills (with optional `?category_id=` filter) |
| 2.4 | `GET` | `/api/v1/skills/autocomplete` | Public | Autocomplete search `?q=vue` → returns matching skills |

### Admin Endpoints (requires Bearer + `role === admin`)

| # | Method | Endpoint | Auth | Description |
|---|--------|----------|------|-------------|
| 2.5 | `POST` | `/api/v1/admin/categories` | Admin | Create new category |
| 2.6 | `PUT` | `/api/v1/admin/categories/:id` | Admin | Update category |
| 2.7 | `DELETE` | `/api/v1/admin/categories/:id` | Admin | Soft delete category (if no jobs reference it) |
| 2.8 | `POST` | `/api/v1/admin/skills` | Admin | Create new skill |
| 2.9 | `PUT` | `/api/v1/admin/skills/:id` | Admin | Update skill (including re-categorizing) |
| 2.10 | `DELETE` | `/api/v1/admin/skills/:id` | Admin | Delete skill (only if not linked to any jobs/candidates) |

### Endpoint Details

#### 2.1 List Categories
```json
// GET /api/v1/categories
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "name": "Software Development",
      "slug": "software-development",
      "icon": "Code",
      "description": "Frontend, backend, mobile, and full-stack roles",
      "jobs_count": 5,
      "is_active": true
    }
  ]
}
```

#### 2.3 List Skills
```json
// GET /api/v1/skills?category_id=uuid
{
  "success": true,
  "data": [
    { "id": "uuid", "name": "Vue.js", "slug": "vue-js", "category_id": "uuid" },
    { "id": "uuid", "name": "React", "slug": "react", "category_id": "uuid" }
  ]
}
```

#### 2.4 Autocomplete
```json
// GET /api/v1/skills/autocomplete?q=vue
{
  "success": true,
  "data": [
    { "id": "uuid", "name": "Vue.js" },
    { "id": "uuid", "name": "Nuxt.js" }
  ]
}
```

#### 2.5 Create Category (Admin)
```json
// POST /api/v1/admin/categories
{
  "name": "Cybersecurity",
  "slug": "cybersecurity", // optional, auto-generated from name if omitted
  "icon": "Shield",
  "description": "Security engineering, pentesting, and compliance roles",
  "display_order": 10
}
```

#### 2.8 Create Skill (Admin)
```json
// POST /api/v1/admin/skills
{
  "name": "Nuxt.js",
  "category_id": "uuid-of-software-development"
}
```

> Auto-generate `slug` from `name` using `Str::slug()` if not provided.

---

## Laravel Implementation Guide

### Controllers
```
app/Http/Controllers/Api/V1/
  ├── CategoryController.php       (2.1, 2.2)
  └── SkillController.php          (2.3, 2.4)

app/Http/Controllers/Api/V1/Admin/
  ├── CategoryController.php       (2.5, 2.6, 2.7)
  └── SkillController.php          (2.8, 2.9, 2.10)
```

### Form Requests
```
app/Http/Requests/Admin/
  ├── CreateCategoryRequest.php
  ├── UpdateCategoryRequest.php
  ├── CreateSkillRequest.php
  └── UpdateSkillRequest.php
```

### Policies
```php
// CategoryPolicy, SkillPolicy
// Only admin can create/update/delete
public function create(User $user) { return $user->role === 'admin'; }
```

### Seeders
```
database/seeders/
  ├── CategorySeeder.php      // 8 categories from original db.json
  ├── SkillSeeder.php         // ~30 skills mapped to categories
  └── DatabaseSeeder.php      // Call CategorySeeder, SkillSeeder, AdminSeeder
```

**Seed categories (from original data):**
1. Software Development (icon: Code)
2. UI/UX Design (icon: Palette)
3. Product Management (icon: LayoutDashboard)
4. Digital Marketing (icon: TrendingUp)
5. Data & Analytics (icon: BarChart2)
6. Finance & Accounting (icon: DollarSign)
7. Human Resources (icon: Users)
8. Customer Support (icon: Headphones)

**Seed skills (sample for Software Development):**
Vue.js, React, Laravel, Node.js, TypeScript, Python, MySQL, PostgreSQL, Docker, AWS

---

## Frontend Refactoring

### Files to Modify

| File | Change |
|------|--------|
| `src/api/services/api.js` | **Add new endpoints:** `getAllCategories`, `getActiveCategories`, `getAllSkills`, `getSkillsAutocomplete`. Update base URL to Laravel. |
| `src/stores/jobs.js` | `fetchAllCategories` now calls `/api/v1/categories` instead of `/categories`. Same for skills. |
| `src/components/shared/SkillsSelector.vue` | If it previously created skills client-side in json-server, it now calls `POST /api/v1/admin/skills` (for admin) or relies on autocomplete. **For candidates/employers, skills are selected from existing list only.** |
| `src/features/employer/views/PostJobView.vue` | Category dropdown now fetches from new endpoint. |
| `src/features/candidate/views/ProfileView.vue` | Skills selector fetches from new endpoint. |

### Data Shape Changes

Old json-server response:
```json
{ "id": "1", "name": "Vue.js", "category_id": "1" }
```

New Laravel response:
```json
{ "id": "uuid-string", "name": "Vue.js", "slug": "vue-js", "category_id": "uuid-string" }
```

The `id` format changed from auto-increment integer to UUID. Vue components should already use `String(id)` since they had inconsistent types before — but verify no `===` number comparisons exist.

---

## Testing Checklist

- [ ] `GET /categories` returns all 8 seeded categories with correct `jobs_count: 0`
- [ ] `GET /skills` returns all seeded skills
- [ ] `GET /skills?category_id=X` returns only skills in that category
- [ ] `GET /skills/autocomplete?q=vu` returns Vue.js, Nuxt.js (fuzzy-ish match)
- [ ] Admin can create category → 201, slug auto-generated
- [ ] Admin can update category name → slug does NOT change (slug should be immutable after creation, or updateable via explicit field only)
- [ ] Non-admin POST /admin/categories → 403
- [ ] Delete category that has jobs → 409 "Cannot delete category with existing jobs" (or cascade? decide: **reject** to prevent data loss)
- [ ] Delete skill that is linked to a job → 409 "Skill in use"
- [ ] Frontend category dropdown populates correctly

---

## Known Issues to Avoid

1. **jobs_count denormalization:** `categories.jobs_count` starts at 0 and is incremented/decremented by job triggers (US4). For now, it's acceptable if it stays 0 until US4 implements the counter update.
2. **Slug regeneration:** When updating a category name, do NOT auto-regenerate the slug (it breaks existing links). Only update slug if explicitly passed.
3. **Skill creation by non-admins:** The frontend's `SkillsSelector` component previously created skills on-the-fly in json-server. In production, candidates and employers should ONLY select from the pre-defined skill list. If a skill doesn't exist, they can't add it — this is intentional (maintains taxonomy quality).

---

*Next: Read `03-us3-candidate-profile.md` after US2 is complete. Can run in parallel with US4.*
