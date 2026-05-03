# User Story 1 — Authentication & File Upload

> **Day:** 1 (Morning)  
> **Priority:** 🔴 Critical — Blocks ALL other stories  
> **Status:** Must complete before any other US can be tested end-to-end  
> **Prerequisite:** None

---

## Goal

Implement a complete authentication system using Laravel Sanctum + stateless Bearer tokens. Set up the foundational `users` table and the file upload infrastructure so all subsequent user stories can create profiles with avatars, company logos, and resumes.

---

## Tables Needed

### New Migrations (run in this order)

1. **`users`**
2. **`password_resets`**
3. **`personal_access_tokens`** (Sanctum default — run `php artisan sanctum:install`)
4. **`files`**

### Migration Details

#### `users`
```php
Schema::create('users', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('email')->unique();
    $table->string('password_hash'); // bcrypt
    $table->string('first_name');
    $table->string('last_name');
    $table->string('phone', 30)->nullable();
    $table->string('avatar_url', 500)->nullable();
    $table->foreignUuid('avatar_file_id')->nullable()->constrained('files')->nullOnDelete();
    $table->enum('role', ['candidate', 'employer', 'admin'])->default('candidate');
    $table->boolean('is_active')->default(true);
    $table->timestamp('email_verified_at')->nullable();
    $table->timestamp('last_login_at')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['role', 'is_active', 'deleted_at']);
});
```

> **Important:** `role` is immutable. Enforce this in the `RegisterRequest` and `User` model by throwing if anyone tries to mass-assign it after creation. Admin accounts are created via seeder only (not public registration).

#### `password_resets`
```php
Schema::create('password_resets', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
    $table->string('token_hash'); // sha256 of raw token
    $table->timestamp('expires_at');
    $table->timestamp('used_at')->nullable();
    $table->timestamps();
});
```

#### `files`
```php
Schema::create('files', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();
    $table->string('file_name');
    $table->string('original_name');
    $table->string('mime_type', 100);
    $table->unsignedBigInteger('size_bytes');
    $table->string('storage_path', 500);
    $table->string('url', 500);
    $table->enum('file_type', ['resume', 'avatar', 'company_logo', 'company_cover', 'document', 'attachment', 'verification_document']);
    $table->string('entity_type', 50)->nullable(); // polymorphic type
    $table->uuid('entity_id')->nullable(); // polymorphic id
    $table->timestamps();
    $table->softDeletes();
});
```

> **MySQL Note:** Foreign keys in `users` referencing `files` (avatar_file_id) may create a circular dependency during migration. Create `files` table first, OR make `avatar_file_id` nullable with no FK constraint initially and add the constraint in a later migration.

---

## Backend Endpoints

| # | Method | Endpoint | Auth | Request Body | Success | Error |
|---|--------|----------|------|--------------|---------|-------|
| 1.1 | `POST` | `/api/v1/auth/register` | Public | `first_name`, `last_name`, `email`, `password`, `role` (enum: candidate/employer) | `201` + user + token | `422` validation, `409` email exists |
| 1.2 | `POST` | `/api/v1/auth/login` | Public | `email`, `password` | `200` + access_token + user | `401` invalid credentials |
| 1.3 | `GET` | `/api/v1/auth/me` | Bearer | — | `200` current user + profile | `401` |
| 1.4 | `POST` | `/api/v1/auth/logout` | Bearer | — | `200` token revoked | `401` |
| 1.5 | `POST` | `/api/v1/auth/forgot-password` | Public | `email` | `200` (always return 200 to prevent email enumeration) | — |
| 1.6 | `POST` | `/api/v1/auth/reset-password` | Public | `token`, `email`, `password`, `password_confirmation` | `200` password updated | `400` invalid/expired token |
| 1.7 | `POST` | `/api/v1/auth/verify-email` | Public | `token` (from email link) | `200` email verified | `400` invalid token |
| 1.8 | `POST` | `/api/v1/auth/resend-verification` | Bearer | — | `200` email sent | `400` already verified |
| 1.9 | `PATCH` | `/api/v1/auth/me` | Bearer | `first_name`, `last_name`, `phone`, `avatar` (file) | `200` updated user | `422` |
| 1.10 | `POST` | `/api/v1/files/upload` | Bearer | `file` (multipart), `file_type` (enum), `entity_type`, `entity_id` | `201` file record | `422` |

### Endpoint Details

#### 1.1 Register
```json
// POST /api/v1/auth/register
{
  "first_name": "Ahmed",
  "last_name": "Khaled",
  "email": "ahmed@example.com",
  "password": "SecurePass123!",
  "password_confirmation": "SecurePass123!",
  "role": "candidate"
}

// Response 201
{
  "success": true,
  "data": {
    "access_token": "1|laravel_sanctum_token...",
    "token_type": "Bearer",
    "user": {
      "id": "uuid",
      "email": "ahmed@example.com",
      "first_name": "Ahmed",
      "last_name": "Khaled",
      "role": "candidate",
      "is_active": true,
      "avatar_url": null,
      "email_verified_at": null
    }
  }
}
```

**Business Rules:**
- Email normalized to lowercase before validation.
- Password min 8 chars, must contain uppercase + lowercase + number.
- `role` must be `candidate` or `employer`. Admin registration via public API is rejected (403).
- After successful registration:
  - If `role === 'candidate'`: auto-create empty `candidates` record (headline='', location='Egypt', bio='')
  - If `role === 'employer'`: auto-create empty `employers` record (company_name='', is_verified=false)
- Fire `Registered` event → listener sends verification email (queueable).

#### 1.2 Login
```json
// POST /api/v1/auth/login
{
  "email": "ahmed@example.com",
  "password": "SecurePass123!"
}

// Response 200
{
  "success": true,
  "data": {
    "access_token": "1|laravel_sanctum_token...",
    "token_type": "Bearer",
    "expires_in": null, // or config value
    "user": {
      "id": "uuid",
      "email": "ahmed@example.com",
      "first_name": "Ahmed",
      "last_name": "Khaled",
      "role": "candidate",
      "avatar_url": null,
      "email_verified_at": null,
      "profile": {
        // if candidate: candidate record
        // if employer: employer record (minimal)
      }
    }
  }
}
```

**Business Rules:**
- Update `users.last_login_at` on successful login.
- Reject login if `is_active === false` (403 "Account deactivated. Contact support.").
- Use Laravel's `Hash::check()` for password verification.
- Create Sanctum token with abilities based on role (optional but good practice).

#### 1.9 Update Profile (Auth/Me)
Accepts multipart form data for avatar upload. If `avatar` file is present:
1. Upload via FileService (same as 1.10)
2. Update `users.avatar_url` and `users.avatar_file_id`
3. Delete old avatar file from storage if replaced

#### 1.10 File Upload
Generic file upload endpoint used by ALL subsequent user stories.

```php
// FileService::upload(UploadedFile $file, User $owner, string $fileType, ?string $entityType, ?string $entityId)
```

Storage strategy (configurable):
- **Local dev:** `storage/app/public/{file_type}/{uuid}.{ext}` → symlinked to `public/storage`
- **Production:** AWS S3 bucket with pre-signed URLs

Response returns the `files` record so the frontend can immediately display the `url`.

---

## Laravel Implementation Guide

### Controllers
```
app/Http/Controllers/Api/V1/Auth/
  ├── AuthController.php      (register, login, logout, me, updateMe)
  ├── PasswordController.php  (forgot, reset)
  └── VerificationController.php (verify, resend)

app/Http/Controllers/Api/V1/
  └── FileController.php      (upload)
```

### Form Requests
```
app/Http/Requests/Auth/
  ├── RegisterRequest.php
  ├── LoginRequest.php
  ├── ForgotPasswordRequest.php
  ├── ResetPasswordRequest.php
  └── UpdateProfileRequest.php

app/Http/Requests/
  └── FileUploadRequest.php
```

### Services
```
app/Services/
  ├── AuthService.php         (register logic, token creation)
  ├── PasswordResetService.php (token generation, email sending)
  └── FileUploadService.php   (storage abstraction, S3 vs local)
```

### Models (with traits)
```php
// User model
class User extends Authenticatable
{
    use HasUuids, HasApiTokens, SoftDeletes;

    protected $fillable = ['email', 'password_hash', 'first_name', 'last_name', 'phone', 'avatar_url', 'avatar_file_id', 'role', 'is_active', 'email_verified_at'];
    protected $hidden = ['password_hash'];
    protected $casts = ['email_verified_at' => 'datetime', 'last_login_at' => 'datetime'];

    public function setEmailAttribute($value) { $this->attributes['email'] = strtolower($value); }
    public function candidate() { return $this->hasOne(Candidate::class); }
    public function employer() { return $this->hasOne(Employer::class); }
}
```

### Seeders
```
database/seeders/
  ├── AdminSeeder.php         // Create 1-2 admin accounts (not via public API)
  └── DatabaseSeeder.php
```

---

## Frontend Refactoring

### Files to Modify

| File | Change |
|------|--------|
| `src/api/services/api.js` | **Replace ALL auth endpoints** to point to new Laravel API. Add `baseURL: import.meta.env.VITE_API_BASE_URL` |
| `src/stores/auth.store.js` | **Adapt to Sanctum token shape.** Sanctum returns `plainTextToken` as the Bearer token. Store it as `token`. Update `setUser()` to handle new user response shape (which includes nested `profile` object). |
| `src/features/auth/views/LoginView.vue` | Ensure it uses new API. No structural change unless response shape changed (it didn't much). |
| `src/features/auth/views/RegisterView.vue` | Registration payload now sends `first_name`, `last_name` (split from `name`). Update form bindings. |
| `src/api/services/api.js` (interceptors) | Add global 401 handler: clear auth store + redirect to `/auth/login` |

### Register Payload Change

Old json-server payload:
```json
{ "name": "Ahmed Khaled", "email": "...", "password": "...", "role": "candidate" }
```

New Laravel payload:
```json
{ "first_name": "Ahmed", "last_name": "Khaled", "email": "...", "password": "...", "password_confirmation": "...", "role": "candidate" }
```

**Action:** Update `RegisterView.vue` to have separate First Name and Last Name inputs (or split the single `name` input before sending).

### Auth Store Changes

```javascript
// src/stores/auth.store.js
// Current:
state: () => ({
  user: localStorage.getItem('user') ? JSON.parse(localStorage.getItem('user')) : null,
  token: localStorage.getItem('token') || null,
})

// Adapted:
// user object from Laravel now has first_name/last_name instead of name
// getters should compute displayName = first_name + ' ' + last_name
```

---

## Testing Checklist

- [ ] Register as candidate → auto-creates candidate record in DB
- [ ] Register as employer → auto-creates employer record in DB
- [ ] Register with duplicate email → 422 "Email already taken"
- [ ] Register with `role: admin` → 403 rejected
- [ ] Login with wrong password → 401
- [ ] Login with deactivated account → 403
- [ ] `GET /auth/me` with valid token → returns user + nested profile
- [ ] `GET /auth/me` without token → 401
- [ ] Logout → token revoked, subsequent requests with same token return 401
- [ ] Upload avatar → file stored, user.avatar_url updated, old file deleted
- [ ] Upload 15MB file → 422 "File too large"
- [ ] Upload non-image as avatar → 422 "Must be an image"

---

## Known Issues to Avoid

1. **Circular FK dependency:** `users.avatar_file_id → files.id` and `files.owner_id → users.id`. Create `files` table in migration BEFORE `users`, or omit the FK on `users.avatar_file_id` and enforce in application code.
2. **Sanctum token expiry:** Sanctum tokens don't expire by default. For production, add a scheduled command that deletes tokens older than N days, or configure token expiration in `config/sanctum.php`.
3. **Email verification:** For development, use Laravel's `log` mail driver so verification emails are written to `storage/logs/laravel.log` instead of actually sending.

---

*Next: Read `02-us2-core-data.md` after US1 is complete.*
