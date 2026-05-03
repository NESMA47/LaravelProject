# HireMasr — Database Design & Backend API Specification (Updated)

> **Project:** HireMasr (Vue 3 Frontend)  
> **Goal:** Replace the static `json-server` backend with a production-ready relational schema and REST API contract.  
> **Target DB:** PostgreSQL (recommended) or any ANSI-SQL relational engine.  
> **Backend Language Agnostic:** Can be implemented in Laravel, Node.js/Express + Prisma, Django, Spring Boot, etc.

---

## 1. Executive Summary

The current frontend (`db.json` + `json-server`) uses a flat, denormalized document store that causes data integrity issues (e.g., `employer_id` sometimes stores a `user.id`, sometimes an `employer.id`), lacks transactional consistency, has no real auth, and stores passwords in plaintext. This document proposes a **strictly normalized, auth-enabled, audit-friendly relational design** with a focus on the core platform features.

---

## 2. Entity Relationship Diagram (Mermaid)

```mermaid
erDiagram
    USERS {
        uuid id PK
        string email UK
        string password_hash
        string first_name
        string last_name
        string phone
        string avatar_url
        enum role "candidate, employer, admin"
        boolean is_active
        timestamp email_verified_at
        timestamp last_login_at
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    PASSWORD_RESETS {
        uuid id PK
        uuid user_id FK
        string token_hash
        timestamp expires_at
        timestamp used_at
        timestamp created_at
    }

    REFRESH_TOKENS {
        uuid id PK
        uuid user_id FK
        string token_hash
        timestamp expires_at
        timestamp revoked_at
        timestamp created_at
    }

    CANDIDATES {
        uuid id PK
        uuid user_id FK UK
        string headline
        text bio
        string location
        string city
        string country
        int experience_years
        enum education_level
        string linkedin_url
        string github_url
        string portfolio_url
        boolean is_open_to_work
        enum preferred_job_type
        jsonb preferred_locations
        int expected_salary_min
        int expected_salary_max
        string currency
        smallint profile_completion_score
        timestamp created_at
        timestamp updated_at
    }

    RESUMES {
        uuid id PK
        uuid candidate_id FK
        string title
        uuid file_id FK
        boolean is_default
        timestamp created_at
        timestamp updated_at
    }

    CANDIDATE_EDUCATION {
        uuid id PK
        uuid candidate_id FK
        string degree
        string institution
        string field_of_study
        smallint start_year
        smallint end_year
        string grade
        boolean is_current
        text description
        timestamp created_at
        timestamp updated_at
    }

    CANDIDATE_EXPERIENCE {
        uuid id PK
        uuid candidate_id FK
        string title
        string company_name
        string location
        enum employment_type
        date start_date
        date end_date
        boolean is_current
        text description
        timestamp created_at
        timestamp updated_at
    }

    CANDIDATE_SKILLS {
        uuid id PK
        uuid candidate_id FK
        uuid skill_id FK
        enum proficiency_level
        smallint years_experience
        timestamp created_at
        timestamp updated_at
    }

    SKILLS {
        uuid id PK
        string name UK
        string slug UK
        uuid category_id FK
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    CATEGORIES {
        uuid id PK
        string name UK
        string slug UK
        string icon
        text description
        smallint display_order
        boolean is_active
        int jobs_count
        timestamp created_at
        timestamp updated_at
    }

    EMPLOYERS {
        uuid id PK
        uuid user_id FK UK
        string company_name
        string slug UK
        string logo_url
        string cover_image_url
        string industry
        string company_size
        smallint founded_year
        string website
        text description
        string headquarters
        string address
        string city
        string country
        boolean is_verified
        decimal average_rating
        int total_reviews
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    EMPLOYER_TEAM_MEMBERS {
        uuid id PK
        uuid employer_id FK
        uuid user_id FK
        string role_in_company
        boolean is_primary
        timestamp created_at
        timestamp updated_at
    }

    JOBS {
        uuid id PK
        uuid employer_id FK
        uuid posted_by_user_id FK
        uuid category_id FK
        string title
        string slug UK
        text description
        text requirements
        text responsibilities
        text benefits
        enum type
        enum workplace_type
        enum experience_level
        string career_level
        enum education_level
        int salary_min
        int salary_max
        string currency
        boolean is_salary_visible
        string location
        string city
        string country
        smallint vacancies
        enum status
        timestamp expires_at
        int views_count
        int applications_count
        boolean is_featured
        timestamp featured_until
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
        text rejection_reason
    }

    JOB_SKILLS {
        uuid id PK
        uuid job_id FK
        uuid skill_id FK
        boolean is_required
        enum min_proficiency
        timestamp created_at
    }

    APPLICATIONS {
        uuid id PK
        uuid job_id FK
        uuid candidate_id FK
        text cover_letter
        jsonb job_snapshot
        jsonb employer_snapshot
        jsonb candidate_snapshot
        enum status
        string current_stage
        timestamp withdrawn_at
        string withdrawn_reason
        timestamp applied_at
        timestamp updated_at
    }

    APPLICATION_STAGES {
        uuid id PK
        uuid application_id FK
        enum stage
        text notes
        uuid changed_by_user_id FK
        timestamp created_at
    }

    INTERVIEWS {
        uuid id PK
        uuid application_id FK
        timestamp scheduled_at
        smallint duration_minutes
        enum location_type
        string location_details
        text notes
        enum status
        uuid created_by_user_id FK
        timestamp created_at
        timestamp updated_at
    }

    SAVED_JOBS {
        uuid id PK
        uuid candidate_id FK
        uuid job_id FK
        text notes
        timestamp saved_at
    }

    COMPANY_REVIEWS {
        uuid id PK
        uuid employer_id FK
        uuid candidate_id FK
        string job_title_at_time
        enum employment_type
        boolean is_current_employee
        boolean is_anonymous
        smallint rating_overall
        smallint rating_work_life_balance
        smallint rating_salary
        smallint rating_culture
        smallint rating_management
        smallint rating_career_growth
        string title
        text pros
        text cons
        text advice
        boolean is_approved
        uuid approved_by FK
        timestamp approved_at
        timestamp created_at
        timestamp updated_at
    }

    NOTIFICATIONS {
        uuid id PK
        uuid user_id FK
        enum type
        string title
        text message
        jsonb data
        string action_url
        boolean is_read
        timestamp read_at
        timestamp created_at
    }

    REPORTS {
        uuid id PK
        uuid reporter_id FK
        enum target_type
        uuid target_id
        enum reason
        text details
        enum status
        uuid resolved_by_user_id FK
        text resolution_notes
        timestamp created_at
        timestamp updated_at
    }

    FILES {
        uuid id PK
        uuid owner_id FK
        string file_name
        string original_name
        string mime_type
        bigint size_bytes
        string storage_path
        string url
        enum file_type
        enum entity_type
        uuid entity_id
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    USERS ||--o| CANDIDATES : extends
    USERS ||--o| EMPLOYERS : extends
    USERS ||--o{ PASSWORD_RESETS : requests
    USERS ||--o{ REFRESH_TOKENS : owns
    USERS ||--o{ NOTIFICATIONS : receives

    CANDIDATES ||--o{ CANDIDATE_EDUCATION : has
    CANDIDATES ||--o{ CANDIDATE_EXPERIENCE : has
    CANDIDATES ||--o{ CANDIDATE_SKILLS : has
    CANDIDATES ||--o{ RESUMES : uploads
    CANDIDATES ||--o{ APPLICATIONS : submits
    CANDIDATES ||--o{ SAVED_JOBS : saves
    CANDIDATES ||--o{ COMPANY_REVIEWS : writes

    EMPLOYERS ||--o{ JOBS : posts
    EMPLOYERS ||--o{ COMPANY_REVIEWS : receives
    EMPLOYERS ||--o{ EMPLOYER_TEAM_MEMBERS : has

    SKILLS }o--|| CATEGORIES : belongs_to
    SKILLS ||--o{ CANDIDATE_SKILLS : measured_in
    SKILLS ||--o{ JOB_SKILLS : required_by

    JOBS }o--|| CATEGORIES : classified_under
    JOBS ||--o{ JOB_SKILLS : requires
    JOBS ||--o{ APPLICATIONS : receives
    JOBS ||--o{ REPORTS : flagged_as

    APPLICATIONS ||--o{ APPLICATION_STAGES : progresses_through
    APPLICATIONS ||--o| INTERVIEWS : schedules

    FILES }o--|| USERS : owned_by
    FILES }o--o| CANDIDATES : attached_to
    FILES }o--o| EMPLOYERS : attached_to
    FILES }o--o| APPLICATIONS : attached_to
    FILES ||--o{ RESUMES : stored_in
```

---

## 3. Table Specifications

### 3.1 Core Identity & Access Management

#### `users`

| Column              | Type         | Constraints                   | Notes                                                           |
| ------------------- | ------------ | ----------------------------- | --------------------------------------------------------------- |
| `id`                | UUID         | PK, default gen_random_uuid() |                                                                 |
| `email`             | VARCHAR(255) | UNIQUE, NOT NULL, indexed     | Normalized to lowercase                                         |
| `password_hash`     | VARCHAR(255) | NOT NULL                      | bcrypt / Argon2id                                               |
| `first_name`        | VARCHAR(100) | NOT NULL                      |                                                                 |
| `last_name`         | VARCHAR(100) | NOT NULL                      |                                                                 |
| `avatar_url`        | VARCHAR(500) | nullable                      | CDN URL                                                         |
| `avatar_file_id`    | UUID         | FK → files.id, nullable       |                                                                 |
| `phone`             | VARCHAR(30)  | nullable                      | E.164 format preferred                                          |
| `role`              | VARCHAR(20)  | NOT NULL, DEFAULT 'candidate' | `candidate`, `employer`, `admin` — immutable after registration |
| `is_active`         | BOOLEAN      | DEFAULT true                  | Admin can deactivate                                            |
| `email_verified_at` | TIMESTAMPTZ  | nullable                      |                                                                 |
| `last_login_at`     | TIMESTAMPTZ  | nullable                      |                                                                 |
| `created_at`        | TIMESTAMPTZ  | DEFAULT now()                 |                                                                 |
| `updated_at`        | TIMESTAMPTZ  | DEFAULT now()                 |                                                                 |
| `deleted_at`        | TIMESTAMPTZ  | nullable                      | Soft delete                                                     |

**Indexes:** `email` (unique), `role`, `is_active` + `deleted_at` (composite), `created_at`.

**Important:** `role` is immutable. Once a user signs up as `candidate` or `employer`, it never changes. This is enforced at the application layer (API rejects role change requests).

#### `password_resets`

| Column       | Type         | Constraints             | Notes                |
| ------------ | ------------ | ----------------------- | -------------------- |
| `id`         | UUID         | PK                      |                      |
| `user_id`    | UUID         | FK → users.id, NOT NULL |                      |
| `token_hash` | VARCHAR(255) | NOT NULL                | SHA-256 of raw token |
| `expires_at` | TIMESTAMPTZ  | NOT NULL                | 1 hour from creation |
| `used_at`    | TIMESTAMPTZ  | nullable                |                      |
| `created_at` | TIMESTAMPTZ  | DEFAULT now()           |                      |

#### `refresh_tokens`

| Column       | Type         | Constraints             | Notes           |
| ------------ | ------------ | ----------------------- | --------------- |
| `id`         | UUID         | PK                      |                 |
| `user_id`    | UUID         | FK → users.id, NOT NULL |                 |
| `token_hash` | VARCHAR(255) | NOT NULL, indexed       |                 |
| `expires_at` | TIMESTAMPTZ  | NOT NULL                | 30 days typical |
| `revoked_at` | TIMESTAMPTZ  | nullable                |                 |
| `ip_address` | INET         | nullable                |                 |
| `user_agent` | VARCHAR(500) | nullable                |                 |
| `created_at` | TIMESTAMPTZ  | DEFAULT now()           |                 |

---

### 3.2 Candidate Module

#### `candidates`

| Column                     | Type         | Constraints                     | Notes                                                           |
| -------------------------- | ------------ | ------------------------------- | --------------------------------------------------------------- |
| `id`                       | UUID         | PK                              |                                                                 |
| `user_id`                  | UUID         | FK → users.id, UNIQUE, NOT NULL | One-to-one                                                      |
| `headline`                 | VARCHAR(150) | nullable                        | e.g. "Senior Frontend Developer \| Vue.js"                      |
| `bio`                      | TEXT         | nullable                        | Professional summary                                            |
| `location`                 | VARCHAR(150) | nullable                        | Human-readable city/area                                        |
| `city`                     | VARCHAR(100) | nullable                        | Normalized for filtering                                        |
| `country`                  | VARCHAR(2)   | DEFAULT 'EG'                    | ISO-3166 alpha-2                                                |
| `experience_years`         | INT          | nullable                        | Total years                                                     |
| `education_level`          | VARCHAR(20)  | nullable                        | `high_school`, `bachelor`, `master`, `phd`, `diploma`           |
| `linkedin_url`             | VARCHAR(500) | nullable                        |                                                                 |
| `github_url`               | VARCHAR(500) | nullable                        |                                                                 |
| `portfolio_url`            | VARCHAR(500) | nullable                        |                                                                 |
| `website_url`              | VARCHAR(500) | nullable                        |                                                                 |
| `is_open_to_work`          | BOOLEAN      | DEFAULT true                    |                                                                 |
| `preferred_job_type`       | VARCHAR(20)  | nullable                        | `full_time`, `part_time`, `contract`, `freelance`, `internship` |
| `preferred_locations`      | JSONB        | DEFAULT '[]'                    | Array of city strings                                           |
| `expected_salary_min`      | INT          | nullable                        | EGP                                                             |
| `expected_salary_max`      | INT          | nullable                        | EGP                                                             |
| `currency`                 | VARCHAR(3)   | DEFAULT 'EGP'                   |                                                                 |
| `profile_completion_score` | SMALLINT     | DEFAULT 0, CHECK 0-100          | Computed field                                                  |
| `created_at`               | TIMESTAMPTZ  | DEFAULT now()                   |                                                                 |
| `updated_at`               | TIMESTAMPTZ  | DEFAULT now()                   |                                                                 |

**Indexes:** `user_id` (unique), `is_open_to_work`, `city`, `country`, `preferred_job_type`.

#### `candidate_education`

| Column           | Type         | Constraints                  | Notes                               |
| ---------------- | ------------ | ---------------------------- | ----------------------------------- |
| `id`             | UUID         | PK                           |                                     |
| `candidate_id`   | UUID         | FK → candidates.id, NOT NULL |                                     |
| `degree`         | VARCHAR(150) | NOT NULL                     | e.g. "Bachelor of Computer Science" |
| `institution`    | VARCHAR(200) | NOT NULL                     |                                     |
| `field_of_study` | VARCHAR(150) | NOT NULL                     |                                     |
| `start_year`     | SMALLINT     | NOT NULL                     |                                     |
| `end_year`       | SMALLINT     | nullable                     | NULL if `is_current`                |
| `grade`          | VARCHAR(50)  | nullable                     | e.g. "Very Good", "GPA 3.5"         |
| `is_current`     | BOOLEAN      | DEFAULT false                |                                     |
| `description`    | TEXT         | nullable                     |                                     |
| `created_at`     | TIMESTAMPTZ  | DEFAULT now()                |                                     |
| `updated_at`     | TIMESTAMPTZ  | DEFAULT now()                |                                     |

#### `candidate_experience`

| Column            | Type         | Constraints                  | Notes                                                           |
| ----------------- | ------------ | ---------------------------- | --------------------------------------------------------------- |
| `id`              | UUID         | PK                           |                                                                 |
| `candidate_id`    | UUID         | FK → candidates.id, NOT NULL |                                                                 |
| `title`           | VARCHAR(150) | NOT NULL                     | Job title                                                       |
| `company_name`    | VARCHAR(150) | NOT NULL                     |                                                                 |
| `location`        | VARCHAR(150) | nullable                     |                                                                 |
| `employment_type` | VARCHAR(20)  | NOT NULL                     | `full_time`, `part_time`, `contract`, `freelance`, `internship` |
| `start_date`      | DATE         | NOT NULL                     |                                                                 |
| `end_date`        | DATE         | nullable                     |                                                                 |
| `is_current`      | BOOLEAN      | DEFAULT false                |                                                                 |
| `description`     | TEXT         | nullable                     |                                                                 |
| `created_at`      | TIMESTAMPTZ  | DEFAULT now()                |                                                                 |
| `updated_at`      | TIMESTAMPTZ  | DEFAULT now()                |                                                                 |

#### `candidate_skills`

| Column              | Type        | Constraints                  | Notes                                            |
| ------------------- | ----------- | ---------------------------- | ------------------------------------------------ |
| `id`                | UUID        | PK                           |                                                  |
| `candidate_id`      | UUID        | FK → candidates.id, NOT NULL |                                                  |
| `skill_id`          | UUID        | FK → skills.id, NOT NULL     |                                                  |
| `proficiency_level` | VARCHAR(20) | DEFAULT 'intermediate'       | `beginner`, `intermediate`, `advanced`, `expert` |
| `years_experience`  | SMALLINT    | nullable                     |                                                  |
| `created_at`        | TIMESTAMPTZ | DEFAULT now()                |                                                  |
| `updated_at`        | TIMESTAMPTZ | DEFAULT now()                |                                                  |

**Unique:** (`candidate_id`, `skill_id`)

#### `resumes`

| Column         | Type         | Constraints                  | Notes                                                         |
| -------------- | ------------ | ---------------------------- | ------------------------------------------------------------- |
| `id`           | UUID         | PK                           |                                                               |
| `candidate_id` | UUID         | FK → candidates.id, NOT NULL |                                                               |
| `title`        | VARCHAR(150) | NOT NULL                     | e.g. "General CV", "Frontend Specialist", "Arabic/English CV" |
| `file_id`      | UUID         | FK → files.id, NOT NULL      | Actual file reference                                         |
| `is_default`   | BOOLEAN      | DEFAULT false                | Only one default per candidate                                |
| `created_at`   | TIMESTAMPTZ  | DEFAULT now()                |                                                               |
| `updated_at`   | TIMESTAMPTZ  | DEFAULT now()                |                                                               |

**Indexes:** `candidate_id`, `candidate_id` + `is_default` (composite).
**Unique:** Partial unique index on (`candidate_id`, `is_default`) where `is_default = true` — ensures only one default resume per candidate.

> **Resume Management Rules:**
>
> - A candidate can upload unlimited resumes, each with a custom title.
> - One resume can be marked as `is_default = true` and is auto-selected when applying unless another is specified.
> - When applying, the candidate may optionally pass `resume_id` in the request body. If omitted, the default resume is used.
> - Deleting the default resume should either: (a) require choosing a new default first, or (b) automatically promote the most recent remaining resume to default.

---

### 3.3 Employer Module

#### `employers`

| Column                     | Type         | Constraints                     | Notes                                                                                 |
| -------------------------- | ------------ | ------------------------------- | ------------------------------------------------------------------------------------- |
| `id`                       | UUID         | PK                              |                                                                                       |
| `user_id`                  | UUID         | FK → users.id, UNIQUE, NOT NULL | Primary account owner                                                                 |
| `company_name`             | VARCHAR(150) | NOT NULL                        |                                                                                       |
| `slug`                     | VARCHAR(150) | UNIQUE, NOT NULL, indexed       | SEO-friendly URL                                                                      |
| `logo_url`                 | VARCHAR(500) | nullable                        |                                                                                       |
| `logo_file_id`             | UUID         | FK → files.id, nullable         |                                                                                       |
| `cover_image_url`          | VARCHAR(500) | nullable                        |                                                                                       |
| `cover_image_file_id`      | UUID         | FK → files.id, nullable         |                                                                                       |
| `industry`                 | VARCHAR(100) | nullable                        | e.g. "Fintech", "E-commerce"                                                          |
| `company_size`             | VARCHAR(20)  | nullable                        | `1-10`, `11-50`, `51-200`, `201-500`, `501-1000`, `1001-5000`, `5001-10000`, `10001+` |
| `founded_year`             | SMALLINT     | nullable                        |                                                                                       |
| `website`                  | VARCHAR(255) | nullable                        |                                                                                       |
| `description`              | TEXT         | nullable                        | Company bio                                                                           |
| `headquarters`             | VARCHAR(255) | nullable                        |                                                                                       |
| `address`                  | VARCHAR(255) | nullable                        |                                                                                       |
| `city`                     | VARCHAR(100) | nullable                        |                                                                                       |
| `country`                  | VARCHAR(2)   | DEFAULT 'EG'                    |                                                                                       |
| `is_verified`              | BOOLEAN      | DEFAULT false                   | Admin/manual verification                                                             |
| `verification_document_id` | UUID         | FK → files.id, nullable         | Trade license, etc.                                                                   |
| `average_rating`           | DECIMAL(2,1) | DEFAULT 0.0, CHECK 0-5          | Denormalized from reviews                                                             |
| `total_reviews`            | INT          | DEFAULT 0                       | Denormalized                                                                          |
| `created_at`               | TIMESTAMPTZ  | DEFAULT now()                   |                                                                                       |
| `updated_at`               | TIMESTAMPTZ  | DEFAULT now()                   |                                                                                       |
| `deleted_at`               | TIMESTAMPTZ  | nullable                        | Soft delete                                                                           |

**Indexes:** `slug` (unique), `is_verified`, `industry`, `city`, `country`.

#### `employer_team_members`

| Column            | Type        | Constraints                 | Notes                          |
| ----------------- | ----------- | --------------------------- | ------------------------------ |
| `id`              | UUID        | PK                          |                                |
| `employer_id`     | UUID        | FK → employers.id, NOT NULL |                                |
| `user_id`         | UUID        | FK → users.id, NOT NULL     | Invited team member            |
| `role_in_company` | VARCHAR(50) | NOT NULL                    | e.g. "Recruiter", "HR Manager" |
| `is_primary`      | BOOLEAN     | DEFAULT false               | Owner flag                     |
| `invited_by`      | UUID        | FK → users.id               |                                |
| `created_at`      | TIMESTAMPTZ | DEFAULT now()               |                                |
| `updated_at`      | TIMESTAMPTZ | DEFAULT now()               |                                |

**Unique:** (`employer_id`, `user_id`)

---

### 3.4 Job Taxonomy

#### `categories`

| Column          | Type         | Constraints      | Notes                        |
| --------------- | ------------ | ---------------- | ---------------------------- |
| `id`            | UUID         | PK               |                              |
| `name`          | VARCHAR(100) | UNIQUE, NOT NULL | e.g. "Software Development"  |
| `slug`          | VARCHAR(100) | UNIQUE, NOT NULL |                              |
| `icon`          | VARCHAR(50)  | nullable         | Icon name from design system |
| `description`   | TEXT         | nullable         |                              |
| `display_order` | SMALLINT     | DEFAULT 0        |                              |
| `is_active`     | BOOLEAN      | DEFAULT true     |                              |
| `jobs_count`    | INT          | DEFAULT 0        | Denormalized counter         |
| `created_at`    | TIMESTAMPTZ  | DEFAULT now()    |                              |
| `updated_at`    | TIMESTAMPTZ  | DEFAULT now()    |                              |

#### `skills`

| Column        | Type         | Constraints                  | Notes         |
| ------------- | ------------ | ---------------------------- | ------------- |
| `id`          | UUID         | PK                           |               |
| `name`        | VARCHAR(100) | UNIQUE, NOT NULL             | e.g. "Vue.js" |
| `slug`        | VARCHAR(100) | UNIQUE, NOT NULL             |               |
| `category_id` | UUID         | FK → categories.id, nullable |               |
| `is_active`   | BOOLEAN      | DEFAULT true                 |               |
| `created_at`  | TIMESTAMPTZ  | DEFAULT now()                |               |
| `updated_at`  | TIMESTAMPTZ  | DEFAULT now()                |               |

**Indexes:** `category_id`, `is_active`.

---

### 3.5 Job Postings

#### `jobs`

| Column               | Type         | Constraints                  | Notes                                                                          |
| -------------------- | ------------ | ---------------------------- | ------------------------------------------------------------------------------ |
| `id`                 | UUID         | PK                           |                                                                                |
| `employer_id`        | UUID         | FK → employers.id, NOT NULL  | **NOT** user.id — normalized                                                   |
| `posted_by_user_id`  | UUID         | FK → users.id, NOT NULL      | Team member who clicked "Post"                                                 |
| `category_id`        | UUID         | FK → categories.id, nullable |                                                                                |
| `title`              | VARCHAR(200) | NOT NULL                     |                                                                                |
| `slug`               | VARCHAR(250) | UNIQUE, NOT NULL, indexed    | Auto-generated                                                                 |
| `description`        | TEXT         | NOT NULL                     | Rich text / markdown                                                           |
| `requirements`       | TEXT         | NOT NULL                     | Bullet list stored as text                                                     |
| `responsibilities`   | TEXT         | nullable                     | New enhancement                                                                |
| `benefits`           | TEXT         | nullable                     |                                                                                |
| `type`               | VARCHAR(20)  | NOT NULL                     | `full_time`, `part_time`, `contract`, `freelance`, `internship`                |
| `workplace_type`     | VARCHAR(20)  | NOT NULL                     | `remote`, `on_site`, `hybrid`                                                  |
| `experience_level`   | VARCHAR(20)  | NOT NULL                     | `junior`, `mid`, `senior`, `lead`, `executive`                                 |
| `career_level`       | VARCHAR(50)  | nullable                     | e.g. "Junior-Mid", "Mid-Senior"                                                |
| `education_level`    | VARCHAR(20)  | nullable                     | `high_school`, `bachelor`, `master`, `phd`, `diploma`, `any`                   |
| `salary_min`         | INT          | nullable                     | EGP                                                                            |
| `salary_max`         | INT          | nullable                     | EGP                                                                            |
| `currency`           | VARCHAR(3)   | DEFAULT 'EGP'                |                                                                                |
| `is_salary_visible`  | BOOLEAN      | DEFAULT true                 |                                                                                |
| `location`           | VARCHAR(200) | NOT NULL                     | Human-readable                                                                 |
| `city`               | VARCHAR(100) | nullable                     | Normalized for geo filters                                                     |
| `country`            | VARCHAR(2)   | DEFAULT 'EG'                 |                                                                                |
| `vacancies`          | SMALLINT     | DEFAULT 1, CHECK > 0         |                                                                                |
| `status`             | VARCHAR(20)  | DEFAULT 'draft'              | `draft`, `pending_review`, `active`, `paused`, `closed`, `rejected`, `expired` |
| `expires_at`         | TIMESTAMPTZ  | nullable                     | Auto-close trigger                                                             |
| `views_count`        | INT          | DEFAULT 0                    | Denormalized; updated by batch worker                                          |
| `applications_count` | INT          | DEFAULT 0                    | Denormalized; updated by trigger                                               |
| `is_featured`        | BOOLEAN      | DEFAULT false                |                                                                                |
| `featured_until`     | TIMESTAMPTZ  | nullable                     |                                                                                |
| `created_at`         | TIMESTAMPTZ  | DEFAULT now()                |                                                                                |
| `updated_at`         | TIMESTAMPTZ  | DEFAULT now()                |                                                                                |
| `deleted_at`         | TIMESTAMPTZ  | nullable                     | Soft delete                                                                    |
| `rejection_reason`   | TEXT         | nullable                     | If admin rejects                                                               |

**Indexes:**

- `slug` (unique)
- `employer_id` + `status` (composite)
- `category_id` + `status` (composite)
- `status` + `is_featured` + `created_at` (composite, for public listings)
- `city` + `country` + `status` (composite, for geo search)
- Full-text search index on (`title`, `description`, `requirements`) using GIN (PostgreSQL) or equivalent.

#### `job_skills`

| Column            | Type        | Constraints              | Notes                                            |
| ----------------- | ----------- | ------------------------ | ------------------------------------------------ |
| `id`              | UUID        | PK                       |                                                  |
| `job_id`          | UUID        | FK → jobs.id, NOT NULL   |                                                  |
| `skill_id`        | UUID        | FK → skills.id, NOT NULL |                                                  |
| `is_required`     | BOOLEAN     | DEFAULT true             | `false` = nice-to-have                           |
| `min_proficiency` | VARCHAR(20) | nullable                 | `beginner`, `intermediate`, `advanced`, `expert` |
| `created_at`      | TIMESTAMPTZ | DEFAULT now()            |                                                  |

**Unique:** (`job_id`, `skill_id`)

---

### 3.6 Applications & Hiring Pipeline

#### `applications`

| Column               | Type         | Constraints                  | Notes                                                                                            |
| -------------------- | ------------ | ---------------------------- | ------------------------------------------------------------------------------------------------ |
| `id`                 | UUID         | PK                           |                                                                                                  |
| `job_id`             | UUID         | FK → jobs.id, NOT NULL       |                                                                                                  |
| `candidate_id`       | UUID         | FK → candidates.id, NOT NULL |                                                                                                  |
| `cover_letter`       | TEXT         | nullable                     |                                                                                                  |
| `job_snapshot`       | JSONB        | NOT NULL                     | Immutable snapshot of job at time of application                                                 |
| `employer_snapshot`  | JSONB        | NOT NULL                     | Immutable snapshot of employer at time of application                                            |
| `candidate_snapshot` | JSONB        | NOT NULL                     | Immutable snapshot of candidate at time of application                                           |
| `status`             | VARCHAR(20)  | DEFAULT 'applied'            | `applied`, `reviewed`, `shortlisted`, `interviewed`, `offered`, `hired`, `rejected`, `withdrawn` |
| `current_stage`      | VARCHAR(50)  | nullable                     | Human-readable stage label                                                                       |
| `withdrawn_at`       | TIMESTAMPTZ  | nullable                     |                                                                                                  |
| `withdrawn_reason`   | VARCHAR(255) | nullable                     |                                                                                                  |
| `applied_at`         | TIMESTAMPTZ  | DEFAULT now()                |                                                                                                  |
| `updated_at`         | TIMESTAMPTZ  | DEFAULT now()                |                                                                                                  |

**Indexes:** `job_id` + `status`, `candidate_id` + `applied_at`.
**Unique:** (`job_id`, `candidate_id`) — prevent duplicate applications.

> **Snapshot Strategy (Critical):**
>
> When a candidate applies to a job, the system **must** capture immutable snapshots of the **job**, **employer**, and **candidate** at that exact moment. This ensures that even if:
>
> - The employer deletes the job
> - The employer renames the company
> - The candidate updates their profile
> - The job requirements change
>
> ... the application record still accurately reflects what the candidate applied for.
>
> **job_snapshot JSONB Schema:**
>
> ```json
> {
>     "title": "Senior Frontend Developer",
>     "description": "...",
>     "requirements": "...",
>     "benefits": "...",
>     "type": "full_time",
>     "workplace_type": "remote",
>     "experience_level": "senior",
>     "salary_min": 25000,
>     "salary_max": 40000,
>     "currency": "EGP",
>     "location": "Cairo, Egypt",
>     "skills": ["Vue.js", "React", "TypeScript"]
> }
> ```
>
> **employer_snapshot JSONB Schema:**
>
> ```json
> {
>     "company_name": "Vodafone Egypt",
>     "slug": "vodafone-egypt",
>     "logo_url": "https://...",
>     "industry": "Telecommunications",
>     "website": "https://vodafone.com.eg",
>     "headquarters": "Smart Village, Giza, Egypt",
>     "is_verified": true
> }
> ```
>
> **candidate_snapshot JSONB Schema:**
>
> ```json
> {
>     "name": "Ahmed Khaled",
>     "email": "ahmed@example.com",
>     "headline": "Senior Frontend Developer | Vue.js",
>     "location": "Cairo, Egypt",
>     "bio": "...",
>     "skills": ["Vue.js", "React"],
>     "experience_summary": "5 years at Orange Egypt...",
>     "education_summary": "B.Sc. CS, Cairo University",
>     "linkedin_url": "...",
>     "github_url": "...",
>     "portfolio_url": "...",
>     "expected_salary_min": 25000,
>     "expected_salary_max": 40000,
>     "resume_url": "...",
>     "resume_title": "General CV"
> }
> ```

#### `application_stages`

| Column               | Type        | Constraints                    | Notes                              |
| -------------------- | ----------- | ------------------------------ | ---------------------------------- |
| `id`                 | UUID        | PK                             |                                    |
| `application_id`     | UUID        | FK → applications.id, NOT NULL |                                    |
| `stage`              | VARCHAR(20) | NOT NULL                       | Same enum as `applications.status` |
| `notes`              | TEXT        | nullable                       | Internal recruiter notes           |
| `changed_by_user_id` | UUID        | FK → users.id, nullable        |                                    |
| `created_at`         | TIMESTAMPTZ | DEFAULT now()                  |                                    |

**Index:** `application_id` + `created_at`.

#### `interviews`

| Column               | Type         | Constraints                    | Notes                                            |
| -------------------- | ------------ | ------------------------------ | ------------------------------------------------ |
| `id`                 | UUID         | PK                             |                                                  |
| `application_id`     | UUID         | FK → applications.id, NOT NULL |                                                  |
| `scheduled_at`       | TIMESTAMPTZ  | NOT NULL                       |                                                  |
| `duration_minutes`   | SMALLINT     | DEFAULT 60                     |                                                  |
| `location_type`      | VARCHAR(20)  | NOT NULL                       | `video_call`, `phone`, `in_person`               |
| `location_details`   | VARCHAR(255) | nullable                       | Zoom link, address, etc.                         |
| `notes`              | TEXT         | nullable                       | Agenda / prep notes                              |
| `status`             | VARCHAR(20)  | DEFAULT 'scheduled'            | `scheduled`, `completed`, `cancelled`, `no_show` |
| `created_by_user_id` | UUID         | FK → users.id, NOT NULL        |                                                  |
| `created_at`         | TIMESTAMPTZ  | DEFAULT now()                  |                                                  |
| `updated_at`         | TIMESTAMPTZ  | DEFAULT now()                  |                                                  |

---

### 3.7 Candidate Engagement

#### `saved_jobs`

| Column         | Type        | Constraints                  | Notes         |
| -------------- | ----------- | ---------------------------- | ------------- |
| `id`           | UUID        | PK                           |               |
| `candidate_id` | UUID        | FK → candidates.id, NOT NULL |               |
| `job_id`       | UUID        | FK → jobs.id, NOT NULL       |               |
| `notes`        | TEXT        | nullable                     | Personal note |
| `saved_at`     | TIMESTAMPTZ | DEFAULT now()                |               |

**Unique:** (`candidate_id`, `job_id`)

---

### 3.8 Reviews & Reputation

#### `company_reviews`

| Column                     | Type         | Constraints                  | Notes            |
| -------------------------- | ------------ | ---------------------------- | ---------------- |
| `id`                       | UUID         | PK                           |                  |
| `employer_id`              | UUID         | FK → employers.id, NOT NULL  |                  |
| `candidate_id`             | UUID         | FK → candidates.id, NOT NULL |                  |
| `job_title_at_time`        | VARCHAR(150) | nullable                     |                  |
| `employment_type`          | VARCHAR(20)  | nullable                     |                  |
| `is_current_employee`      | BOOLEAN      | DEFAULT false                |                  |
| `is_anonymous`             | BOOLEAN      | DEFAULT false                |                  |
| `rating_overall`           | SMALLINT     | NOT NULL, CHECK 1-5          |                  |
| `rating_work_life_balance` | SMALLINT     | nullable, CHECK 1-5          |                  |
| `rating_salary`            | SMALLINT     | nullable, CHECK 1-5          |                  |
| `rating_culture`           | SMALLINT     | nullable, CHECK 1-5          |                  |
| `rating_management`        | SMALLINT     | nullable, CHECK 1-5          |                  |
| `rating_career_growth`     | SMALLINT     | nullable, CHECK 1-5          |                  |
| `title`                    | VARCHAR(200) | NOT NULL                     | Review headline  |
| `pros`                     | TEXT         | nullable                     |                  |
| `cons`                     | TEXT         | nullable                     |                  |
| `advice`                   | TEXT         | nullable                     | To management    |
| `is_approved`              | BOOLEAN      | DEFAULT false                | Moderation queue |
| `approved_by`              | UUID         | FK → users.id, nullable      |                  |
| `approved_at`              | TIMESTAMPTZ  | nullable                     |                  |
| `created_at`               | TIMESTAMPTZ  | DEFAULT now()                |                  |
| `updated_at`               | TIMESTAMPTZ  | DEFAULT now()                |                  |

**Index:** `employer_id` + `is_approved` + `created_at`.
**Unique:** (`employer_id`, `candidate_id`) — one review per candidate per employer.

---

### 3.9 Notifications

#### `notifications`

| Column       | Type         | Constraints             | Notes                                                                                                                              |
| ------------ | ------------ | ----------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| `id`         | UUID         | PK                      |                                                                                                                                    |
| `user_id`    | UUID         | FK → users.id, NOT NULL |                                                                                                                                    |
| `type`       | VARCHAR(50)  | NOT NULL                | `application_status_changed`, `new_application`, `interview_scheduled`, `saved_job_expiring`, `job_flagged`, `system_announcement` |
| `title`      | VARCHAR(200) | NOT NULL                |                                                                                                                                    |
| `message`    | TEXT         | NOT NULL                |                                                                                                                                    |
| `data`       | JSONB        | nullable                | Polymorphic payload (ids, urls)                                                                                                    |
| `action_url` | VARCHAR(500) | nullable                | Deep link                                                                                                                          |
| `is_read`    | BOOLEAN      | DEFAULT false           |                                                                                                                                    |
| `read_at`    | TIMESTAMPTZ  | nullable                |                                                                                                                                    |
| `created_at` | TIMESTAMPTZ  | DEFAULT now()           |                                                                                                                                    |

**Index:** `user_id` + `is_read` + `created_at`.

---

### 3.10 Moderation & Reporting

#### `reports`

| Column                | Type        | Constraints             | Notes                                                                          |
| --------------------- | ----------- | ----------------------- | ------------------------------------------------------------------------------ |
| `id`                  | UUID        | PK                      |                                                                                |
| `reporter_id`         | UUID        | FK → users.id, NOT NULL |                                                                                |
| `target_type`         | VARCHAR(50) | NOT NULL                | `job`, `review`, `user`, `employer`                                            |
| `target_id`           | UUID        | NOT NULL                | Polymorphic ID                                                                 |
| `reason`              | VARCHAR(50) | NOT NULL                | `spam`, `fraudulent`, `misleading`, `inappropriate`, `discriminatory`, `other` |
| `details`             | TEXT        | nullable                |                                                                                |
| `status`              | VARCHAR(20) | DEFAULT 'pending'       | `pending`, `investigating`, `resolved`, `dismissed`                            |
| `resolved_by_user_id` | UUID        | FK → users.id, nullable |                                                                                |
| `resolution_notes`    | TEXT        | nullable                |                                                                                |
| `created_at`          | TIMESTAMPTZ | DEFAULT now()           |                                                                                |
| `updated_at`          | TIMESTAMPTZ | DEFAULT now()           |                                                                                |

---

### 3.11 File Management

#### `files`

| Column          | Type         | Constraints             | Notes                                                                         |
| --------------- | ------------ | ----------------------- | ----------------------------------------------------------------------------- |
| `id`            | UUID         | PK                      |                                                                               |
| `owner_id`      | UUID         | FK → users.id, NOT NULL | Uploader                                                                      |
| `file_name`     | VARCHAR(255) | NOT NULL                | Stored filename (UUID.ext)                                                    |
| `original_name` | VARCHAR(255) | NOT NULL                | User-facing name                                                              |
| `mime_type`     | VARCHAR(100) | NOT NULL                |                                                                               |
| `size_bytes`    | BIGINT       | NOT NULL                |                                                                               |
| `storage_path`  | VARCHAR(500) | NOT NULL                | S3 / MinIO / local path                                                       |
| `url`           | VARCHAR(500) | NOT NULL                | Public or signed URL                                                          |
| `file_type`     | VARCHAR(20)  | NOT NULL                | `resume`, `avatar`, `company_logo`, `company_cover`, `document`, `attachment` |
| `entity_type`   | VARCHAR(50)  | nullable                | Polymorphic: `candidate`, `employer`, `application`                           |
| `entity_id`     | UUID         | nullable                |                                                                               |
| `created_at`    | TIMESTAMPTZ  | DEFAULT now()           |                                                                               |
| `updated_at`    | TIMESTAMPTZ  | DEFAULT now()           |                                                                               |
| `deleted_at`    | TIMESTAMPTZ  | nullable                | Soft delete for orphaned file cleanup                                         |

---

## 4. REST API Contract

### 4.1 Authentication Endpoints

| Method  | Endpoint                           | Auth                   | Description                    |
| ------- | ---------------------------------- | ---------------------- | ------------------------------ |
| `POST`  | `/api/v1/auth/register`            | Public                 | Register candidate or employer |
| `POST`  | `/api/v1/auth/login`               | Public                 | Email + password → tokens      |
| `POST`  | `/api/v1/auth/refresh`             | Public (refresh token) | Rotate access token            |
| `POST`  | `/api/v1/auth/logout`              | Bearer                 | Revoke refresh token           |
| `POST`  | `/api/v1/auth/logout-all`          | Bearer                 | Revoke all user sessions       |
| `POST`  | `/api/v1/auth/forgot-password`     | Public                 | Send reset email               |
| `POST`  | `/api/v1/auth/reset-password`      | Public                 | Consume reset token            |
| `POST`  | `/api/v1/auth/verify-email`        | Public                 | Consume verification token     |
| `POST`  | `/api/v1/auth/resend-verification` | Bearer                 | Resend email verification      |
| `GET`   | `/api/v1/auth/me`                  | Bearer                 | Current user + role + profile  |
| `PATCH` | `/api/v1/auth/me`                  | Bearer                 | Update own name, phone, avatar |

#### Register Request Body

```json
{
    "first_name": "Ahmed",
    "last_name": "Khaled",
    "email": "ahmed@example.com",
    "password": "SecurePass123!",
    "role": "candidate"
}
```

> **Note:** `role` is validated against enum `['candidate', 'employer']`. Once set, it is immutable. Admin accounts are created manually or via seeded data.

#### Login Response

```json
{
    "access_token": "eyJhbGciOiJSUzI1NiIs...",
    "token_type": "Bearer",
    "expires_in": 900,
    "refresh_token": "def50200...",
    "user": {
        "id": "uuid",
        "email": "ahmed@example.com",
        "first_name": "Ahmed",
        "last_name": "Khaled",
        "avatar_url": "...",
        "role": "candidate",
        "profile": {
            /* candidate or employer profile */
        }
    }
}
```

---

### 4.2 Public / Unauthenticated Endpoints

| Method | Endpoint                          | Description                                              |
| ------ | --------------------------------- | -------------------------------------------------------- |
| `GET`  | `/api/v1/jobs`                    | List active jobs (paginated, filterable)                 |
| `GET`  | `/api/v1/jobs/:slug`              | Job detail (increments view asynchronously)              |
| `GET`  | `/api/v1/categories`              | Active categories with job counts                        |
| `GET`  | `/api/v1/skills`                  | Skill autocomplete                                       |
| `GET`  | `/api/v1/employers`               | Verified employers list                                  |
| `GET`  | `/api/v1/employers/:slug`         | Employer public profile + active jobs + approved reviews |
| `GET`  | `/api/v1/employers/:slug/reviews` | Paginated approved reviews                               |

#### Job List Query Parameters

```
GET /api/v1/jobs?category=software-development
              &type=full_time
              &workplace=remote
              &experience=senior
              &location=Cairo
              &salary_min=20000&salary_max=50000
              &search=vue+frontend
              &skills=1,2,5
              &page=1&per_page=20
              &sort=created_at:desc
```

---

### 4.3 Candidate Endpoints

All require `Bearer` token + `role === 'candidate'`.

| Method   | Endpoint                                      | Description                                                     |
| -------- | --------------------------------------------- | --------------------------------------------------------------- |
| `GET`    | `/api/v1/candidate/profile`                   | Full profile + education + experience + skills                  |
| `PUT`    | `/api/v1/candidate/profile`                   | Upsert candidate profile (bio, headline, socials, preferences)  |
| `POST`   | `/api/v1/candidate/education`                 | Add education entry                                             |
| `PUT`    | `/api/v1/candidate/education/:id`             | Update entry                                                    |
| `DELETE` | `/api/v1/candidate/education/:id`             | Remove entry                                                    |
| `POST`   | `/api/v1/candidate/experience`                | Add work experience                                             |
| `PUT`    | `/api/v1/candidate/experience/:id`            | Update entry                                                    |
| `DELETE` | `/api/v1/candidate/experience/:id`            | Remove entry                                                    |
| `POST`   | `/api/v1/candidate/skills`                    | Batch sync candidate skills (replaces entire set)               |
| `GET`    | `/api/v1/candidate/resumes`                   | List all uploaded resumes                                       |
| `POST`   | `/api/v1/candidate/resumes`                   | Upload a new resume (title + file)                              |
| `PUT`    | `/api/v1/candidate/resumes/:id`               | Update resume title or set as default                           |
| `DELETE` | `/api/v1/candidate/resumes/:id`               | Delete a resume (cannot delete last resume without replacement) |
| `PATCH`  | `/api/v1/candidate/resumes/:id/default`       | Set a resume as the default                                     |
| `GET`    | `/api/v1/candidate/applications`              | My applications with job snapshot summary                       |
| `POST`   | `/api/v1/candidate/applications`              | Apply to a job (cover_letter, optional resume_id)               |
| `PATCH`  | `/api/v1/candidate/applications/:id/withdraw` | Withdraw application                                            |
| `GET`    | `/api/v1/candidate/saved-jobs`                | Bookmarked jobs                                                 |
| `POST`   | `/api/v1/candidate/saved-jobs`                | Save a job                                                      |
| `DELETE` | `/api/v1/candidate/saved-jobs/:job_id`        | Unsave                                                          |
| `GET`    | `/api/v1/candidate/notifications`             | My notifications (paginated)                                    |
| `PATCH`  | `/api/v1/candidate/notifications/:id/read`    | Mark as read                                                    |
| `PATCH`  | `/api/v1/candidate/notifications/read-all`    | Bulk read                                                       |

#### Batch Sync Skills Request

```json
{
    "skills": [
        {
            "skill_id": "uuid",
            "proficiency_level": "expert",
            "years_experience": 5
        },
        { "name": "Nuxt.js", "proficiency_level": "advanced" } // auto-create skill if missing
    ]
}
```

---

### 4.4 Employer Endpoints

Require `Bearer` token + `role === 'employer'` (or team member via `employer_team_members`).

| Method   | Endpoint                                                     | Description                                                                          |
| -------- | ------------------------------------------------------------ | ------------------------------------------------------------------------------------ |
| `GET`    | `/api/v1/employer/profile`                                   | Company profile + team members                                                       |
| `PUT`    | `/api/v1/employer/profile`                                   | Update company details                                                               |
| `GET`    | `/api/v1/employer/jobs`                                      | All jobs posted by this employer                                                     |
| `POST`   | `/api/v1/employer/jobs`                                      | Create job (status = `pending_review` by default)                                    |
| `GET`    | `/api/v1/employer/jobs/:id`                                  | Job detail with applicant stats                                                      |
| `PUT`    | `/api/v1/employer/jobs/:id`                                  | Edit job (if not closed)                                                             |
| `PATCH`  | `/api/v1/employer/jobs/:id/status`                           | Toggle `active` ↔ `closed` or `paused`                                               |
| `DELETE` | `/api/v1/employer/jobs/:id`                                  | Soft delete (admin only hard delete)                                                 |
| `GET`    | `/api/v1/employer/applications`                              | All applications across employer jobs                                                |
| `GET`    | `/api/v1/employer/applications/:id`                          | Full application + candidate snapshot + education + experience                       |
| `PATCH`  | `/api/v1/employer/applications/:id/status`                   | Move to next stage (`reviewed`, `shortlisted`, `interviewed`, `offered`, `rejected`) |
| `POST`   | `/api/v1/employer/applications/:id/interviews`               | Schedule interview                                                                   |
| `PUT`    | `/api/v1/employer/applications/:id/interviews/:interview_id` | Reschedule / update                                                                  |
| `GET`    | `/api/v1/employer/analytics`                                 | Job views, application conversion rates, top sources                                 |
| `GET`    | `/api/v1/employer/reviews`                                   | Reviews about this company (with moderation status)                                  |
| `POST`   | `/api/v1/employer/reviews/:id/reply`                         | Official employer reply to a review                                                  |

---

### 4.5 Admin Endpoints

Require `Bearer` token + `role === 'admin'`.

| Method   | Endpoint                            | Description                                            |
| -------- | ----------------------------------- | ------------------------------------------------------ |
| `GET`    | `/api/v1/admin/dashboard`           | Stats cards, charts                                    |
| `GET`    | `/api/v1/admin/users`               | List all users (paginated, filterable by role, status) |
| `GET`    | `/api/v1/admin/users/:id`           | User detail + role-specific activity                   |
| `PATCH`  | `/api/v1/admin/users/:id/status`    | Activate / deactivate                                  |
| `GET`    | `/api/v1/admin/jobs`                | All jobs with moderation queue                         |
| `GET`    | `/api/v1/admin/jobs/:id`            | Job detail + application list                          |
| `PATCH`  | `/api/v1/admin/jobs/:id/status`     | Approve, reject, close, re-activate                    |
| `DELETE` | `/api/v1/admin/jobs/:id`            | Hard delete (with cascade)                             |
| `POST`   | `/api/v1/admin/jobs`                | Post on behalf of employer                             |
| `GET`    | `/api/v1/admin/reviews`             | Pending review moderation queue                        |
| `PATCH`  | `/api/v1/admin/reviews/:id/approve` | Approve / reject review                                |
| `GET`    | `/api/v1/admin/reports`             | All reports                                            |
| `PATCH`  | `/api/v1/admin/reports/:id`         | Update investigation status                            |
| `GET`    | `/api/v1/admin/audit-logs`          | Filterable audit trail                                 |
| `GET`    | `/api/v1/admin/categories`          | List categories                                        |
| `POST`   | `/api/v1/admin/categories`          | Create category                                        |
| `PUT`    | `/api/v1/admin/categories/:id`      | Update category                                        |
| `GET`    | `/api/v1/admin/skills`              | Skill management                                       |
| `POST`   | `/api/v1/admin/skills`              | Add skill                                              |
| `PUT`    | `/api/v1/admin/skills/:id`          | Merge / rename skill                                   |

---

## 5. Authorization & Role-Based Access Control

### 5.1 Role Hierarchy

The system uses a **simple, flat role model**. A user's role is set at registration and never changes.

| Role        | Capabilities                                                                                              |
| ----------- | --------------------------------------------------------------------------------------------------------- |
| `candidate` | Manage own profile, apply to jobs, save jobs, write company reviews, receive notifications                |
| `employer`  | Manage company profile, post/manage jobs, review applications, schedule interviews, reply to reviews      |
| `admin`     | Full platform control: manage users, moderate jobs & reviews, manage categories & skills, resolve reports |

> **Important:** There is no `moderator` role. Admins handle all moderation tasks.

### 5.2 Middleware Stack (Conceptual)

1. **Authenticate** — Verify JWT access token (RS256 recommended; store public key in API Gateway).
2. **AuthorizeRole** — Check `req.user.role === required_role`. Reject if mismatch.
3. **ResourceOwnership** — For employer routes, verify `job.employer_id` matches `req.user.employer_id` (or team membership via `employer_team_members`).

---

## 6. Critical Data Integrity Fixes vs. Current JSON Server

| Current Problem                                                                     | Proposed Fix                                                                                                                   |
| ----------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| `employer_id` in `jobs` stores `user.id` instead of `employer.id`                   | Foreign key `jobs.employer_id → employers.id`. Posted-by user stored separately in `posted_by_user_id`.                        |
| Plaintext passwords (`123456`)                                                      | `password_hash` with bcrypt cost 12+ or Argon2id.                                                                              |
| No email verification                                                               | `email_verified_at` + verification token workflow.                                                                             |
| No session management                                                               | `refresh_tokens` table with revocation, expiry, device tracking.                                                               |
| `candidate_id` in applications sometimes stores `user.id`, sometimes `candidate.id` | Strict FK to `candidates.id`. Snapshot JSONB preserves human-readable data.                                                    |
| `applications.status` is just a string with no history                              | `application_stages` table provides full audit trail.                                                                          |
| `users.role` is a single string — but no enforcement                                | `role` column with `CHECK(role IN ('candidate','employer','admin'))` and application-layer immutability.                       |
| No file management                                                                  | `files` table with polymorphic attachment (`entity_type` + `entity_id`).                                                       |
| Skills stored as string array on candidate                                          | Normalized `candidate_skills` join table with proficiency.                                                                     |
| `job.technologies` string array                                                     | Normalized `job_skills` join table with `is_required` flag.                                                                    |
| No search indexing                                                                  | GIN / full-text index on `jobs.title`, `description`, `requirements`.                                                          |
| Employer deletes job — application loses context                                    | **Triple snapshot strategy:** `job_snapshot`, `employer_snapshot`, `candidate_snapshot` stored immutably on every application. |
| Candidate updates profile — old applications lose original info                     | `candidate_snapshot` frozen at time of application.                                                                            |

---

## 7. Backend Implementation Recommendations

### 7.1 Preferred Stack Options

| Layer        | Recommendation                                                                              |
| ------------ | ------------------------------------------------------------------------------------------- |
| **Runtime**  | Node.js 20+ (Express / NestJS) or PHP 8.3 (Laravel 11) or Python (Django / FastAPI)         |
| **ORM**      | Prisma (Node), Eloquent (Laravel), or SQLAlchemy (Python)                                   |
| **Database** | PostgreSQL 15+ (for JSONB, full-text search, row-level security)                            |
| **Cache**    | Redis (sessions, rate limiting, job queues, featured jobs cache)                            |
| **Queue**    | Redis + BullMQ (Node) or Laravel Queues / Celery (Python)                                   |
| **Search**   | PostgreSQL `tsvector` for MVP; migrate to Elasticsearch/OpenSearch if > 100k jobs           |
| **Storage**  | AWS S3 or MinIO (self-hosted) for resumes, avatars, logos                                   |
| **Email**    | AWS SES, Mailgun, or Resend for transactional emails                                        |
| **Auth**     | Stateless JWT (access) + Redis-backed refresh tokens; avoid sessions for horizontal scaling |

### 7.2 Key Database Triggers / Business Rules

1. **Auto-Close Expired Jobs** — Cron or pg_cron: `UPDATE jobs SET status='expired' WHERE expires_at < NOW() AND status = 'active'`.
2. **Application Counter Trigger** — After insert/delete on `applications`, recalc `jobs.applications_count` for that job.
3. **Review Aggregate Trigger** — After insert/update on `company_reviews` (where `is_approved=true`), recalc `employers.average_rating` and `employers.total_reviews`.
4. **Profile Completion Score** — Computed nightly or on-demand based on filled fields, education count, experience count, skills count.
5. **Slug Uniqueness** — If collision on `jobs.slug` or `employers.slug`, append `-2`, `-3`, etc.
6. **Email Normalization** — Always store lowercase; enforce uniqueness with `citext` (PostgreSQL) or functional index.
7. **Snapshot Creation on Application** — Application service layer must atomically create the application row and populate `job_snapshot`, `employer_snapshot`, `candidate_snapshot` from the live records at that moment.

### 7.3 API Rate Limiting Suggestions

| Endpoint Group                          | Limit                               |
| --------------------------------------- | ----------------------------------- |
| Auth (login, register, forgot password) | 5 requests / minute / IP            |
| Job applications                        | 20 applications / day / candidate   |
| Job postings                            | Based on subscription plan (future) |
| Public job search                       | 100 requests / minute / IP          |
| Admin endpoints                         | 500 requests / minute / user        |

---

## 8. Entity-Attribute Summary (Quick Reference)

| Entity                  | Count Est.          | Core Purpose                                  |
| ----------------------- | ------------------- | --------------------------------------------- |
| `users`                 | 1 per person        | Identity, auth, role (immutable), soft-delete |
| `password_resets`       | Low                 | Password reset tokens                         |
| `refresh_tokens`        | 1-5 per user        | Session management                            |
| `candidates`            | 1 per candidate     | Profile, preferences, socials                 |
| `candidate_education`   | 1-5 per candidate   | Academic history                              |
| `candidate_experience`  | 2-8 per candidate   | Work history                                  |
| `candidate_skills`      | 5-20 per candidate  | Skill inventory with proficiency              |
| `resumes`               | 1-5 per candidate   | Multiple CVs with titles, one default         |
| `employers`             | 1 per company       | Company profile, verification                 |
| `employer_team_members` | 1-10 per company    | Multi-user company accounts                   |
| `categories`            | ~20                 | Job taxonomy                                  |
| `skills`                | ~500-2000           | Skill taxonomy                                |
| `jobs`                  | 100-10k+            | Job postings with full-text search            |
| `job_skills`            | 3-10 per job        | Required/nice-to-have skills                  |
| `applications`          | 10-100 per job      | Candidate applications with triple snapshot   |
| `application_stages`    | 1-8 per application | Hiring pipeline history                       |
| `interviews`            | 0-3 per application | Scheduled meetings                            |
| `saved_jobs`            | 0-50 per candidate  | Bookmarks                                     |
| `company_reviews`       | 5-200 per employer  | Employee reviews                              |
| `notifications`         | 100-1000 per user   | In-app notification inbox                     |
| `reports`               | Low volume          | Moderation queue                              |
| `files`                 | Medium              | Resumes, avatars, logos                       |

---

_Document Version: 1.0 (Updated)_  
_Generated for: HireMasr Project_  
_Next Steps: Implement backend API using this spec, then update frontend `api.js` service layer to consume real endpoints._
