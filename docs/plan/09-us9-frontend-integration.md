# User Story 9 — Frontend API Integration & End-to-End Testing

> **Day:** 4 (Afternoon)  
> **Priority:** 🔴 Critical — Ties everything together  
> **Prerequisite:** US1-US8 (ALL backend endpoints must be implemented and individually tested)

---

## Goal

Refactor the Vue 3 frontend to consume the real Laravel API instead of `json-server`. Update the API service layer, Pinia stores, router guards, and environment configuration. Perform end-to-end testing of all user flows.

---

## No New Backend Tables or Endpoints

This US is purely **frontend refactoring** and **integration testing**.

---

## Frontend Refactoring Tasks

### Task 1: Environment Configuration

Create `.env` and `.env.example`:
```
VITE_API_BASE_URL=http://localhost:8000/api/v1
```

Update `vite.config.js` to expose `process.env` variables if needed (Vite uses `import.meta.env` natively).

---

### Task 2: Rewrite `src/api/services/api.js`

This is the **most critical file**. Replace the entire file with a clean, organized API client.

**Current Problems:**
- Points to `http://localhost:3000` (json-server)
- No consistent error handling
- No request/response normalization
- Returns raw axios promises without wrapping

**New Structure:**
```javascript
// src/api/services/api.js
import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api/v1',
  timeout: 15000,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

// Request interceptor: attach Bearer token
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Response interceptor: normalize snake_case to camelCase, handle 401
api.interceptors.response.use(
  (response) => {
    // Laravel wraps in { success: true, data: ... }
    if (response.data && response.data.success) {
      response.data = response.data.data
    }
    return response
  },
  (error) => {
    if (error.response?.status === 401) {
      // Token expired or invalid
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      window.location.href = '/auth/login'
    }

    // Normalize validation errors
    if (error.response?.status === 422 && error.response.data?.errors) {
      const messages = Object.values(error.response.data.errors).flat().join(', ')
      error.message = messages
    }

    console.error('API Error:', error.response?.data || error.message)
    return Promise.reject(error)
  },
)

// Helper: snake_case → camelCase for nested objects
function toCamelCase(obj) { /* ... recursive implementation ... */ }
function toSnakeCase(obj) { /* ... recursive implementation ... */ }

export default api

// ========== AUTH ==========
export const authApi = {
  register: (data) => api.post('/auth/register', data),
  login: (data) => api.post('/auth/login', data),
  logout: () => api.post('/auth/logout'),
  me: () => api.get('/auth/me'),
  updateMe: (data) => api.patch('/auth/me', data),
  forgotPassword: (data) => api.post('/auth/forgot-password', data),
  resetPassword: (data) => api.post('/auth/reset-password', data),
  verifyEmail: (data) => api.post('/auth/verify-email', data),
  resendVerification: () => api.post('/auth/resend-verification'),
}

// ========== FILES ==========
export const filesApi = {
  upload: (formData) => api.post('/files/upload', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
}

// ========== PUBLIC ==========
export const publicApi = {
  getCategories: () => api.get('/categories'),
  getCategory: (slug) => api.get(`/categories/${slug}`),
  getSkills: (params) => api.get('/skills', { params }),
  getSkillsAutocomplete: (q) => api.get('/skills/autocomplete', { params: { q } }),
  getJobs: (params) => api.get('/jobs', { params }),
  getJobBySlug: (slug) => api.get(`/jobs/${slug}`),
  getEmployers: (params) => api.get('/employers', { params }),
  getEmployerBySlug: (slug) => api.get(`/employers/${slug}`),
  getEmployerReviews: (slug, params) => api.get(`/employers/${slug}/reviews`, { params }),
}

// ========== CANDIDATE ==========
export const candidateApi = {
  getProfile: () => api.get('/candidate/profile'),
  updateProfile: (data) => api.put('/candidate/profile', data),

  // Education
  createEducation: (data) => api.post('/candidate/education', data),
  updateEducation: (id, data) => api.put(`/candidate/education/${id}`, data),
  deleteEducation: (id) => api.delete(`/candidate/education/${id}`),

  // Experience
  createExperience: (data) => api.post('/candidate/experience', data),
  updateExperience: (id, data) => api.put(`/candidate/experience/${id}`, data),
  deleteExperience: (id) => api.delete(`/candidate/experience/${id}`),

  // Skills
  syncSkills: (data) => api.post('/candidate/skills', data),

  // Resumes
  getResumes: () => api.get('/candidate/resumes'),
  uploadResume: (formData) => api.post('/candidate/resumes', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
  updateResume: (id, data) => api.put(`/candidate/resumes/${id}`, data),
  deleteResume: (id) => api.delete(`/candidate/resumes/${id}`),
  setDefaultResume: (id) => api.patch(`/candidate/resumes/${id}/default`),

  // Applications
  getApplications: () => api.get('/candidate/applications'),
  applyForJob: (data) => api.post('/candidate/applications', data),
  withdrawApplication: (id, data) => api.patch(`/candidate/applications/${id}/withdraw`, data),

  // Saved Jobs
  getSavedJobs: () => api.get('/candidate/saved-jobs'),
  saveJob: (jobId) => api.post('/candidate/saved-jobs', { job_id: jobId }),
  unsaveJob: (jobId) => api.delete(`/candidate/saved-jobs/${jobId}`),

  // Reviews
  getMyReviews: () => api.get('/candidate/reviews'),
  submitReview: (data) => api.post('/candidate/reviews', data),
  updateReview: (id, data) => api.put(`/candidate/reviews/${id}`, data),
  deleteReview: (id) => api.delete(`/candidate/reviews/${id}`),
}

// ========== EMPLOYER ==========
export const employerApi = {
  getProfile: () => api.get('/employer/profile'),
  updateProfile: (data) => api.put('/employer/profile', data),

  // Jobs
  getJobs: () => api.get('/employer/jobs'),
  createJob: (data) => api.post('/employer/jobs', data),
  getJob: (id) => api.get(`/employer/jobs/${id}`),
  updateJob: (id, data) => api.put(`/employer/jobs/${id}`, data),
  updateJobStatus: (id, data) => api.patch(`/employer/jobs/${id}/status`, data),
  deleteJob: (id) => api.delete(`/employer/jobs/${id}`),

  // Applications
  getApplications: (params) => api.get('/employer/applications', { params }),
  getApplication: (id) => api.get(`/employer/applications/${id}`),
  updateApplicationStatus: (id, data) => api.patch(`/employer/applications/${id}/status`, data),

  // Interviews
  scheduleInterview: (applicationId, data) => api.post(`/employer/applications/${applicationId}/interviews`, data),
  updateInterview: (applicationId, interviewId, data) => api.put(`/employer/applications/${applicationId}/interviews/${interviewId}`, data),

  // Reviews
  getReviews: () => api.get('/employer/reviews'),
  replyToReview: (id, data) => api.post(`/employer/reviews/${id}/reply`, data),
}

// ========== ADMIN ==========
export const adminApi = {
  getDashboard: () => api.get('/admin/dashboard'),

  // Users
  getUsers: (params) => api.get('/admin/users', { params }),
  getUser: (id) => api.get(`/admin/users/${id}`),
  updateUserStatus: (id, data) => api.patch(`/admin/users/${id}/status`, data),

  // Jobs
  getJobs: (params) => api.get('/admin/jobs', { params }),
  getJob: (id) => api.get(`/admin/jobs/${id}`),
  updateJobStatus: (id, data) => api.patch(`/admin/jobs/${id}/status`, data),
  deleteJob: (id) => api.delete(`/admin/jobs/${id}`),
  createJob: (data) => api.post('/admin/jobs', data),

  // Reviews
  getPendingReviews: () => api.get('/admin/reviews'),
  approveReview: (id) => api.patch(`/admin/reviews/${id}/approve`),
  rejectReview: (id) => api.patch(`/admin/reviews/${id}/reject`),

  // Reports
  getReports: () => api.get('/admin/reports'),
  updateReport: (id, data) => api.patch(`/admin/reports/${id}`, data),

  // Categories & Skills
  getCategories: () => api.get('/admin/categories'),
  createCategory: (data) => api.post('/admin/categories', data),
  updateCategory: (id, data) => api.put(`/admin/categories/${id}`, data),
  getSkills: () => api.get('/admin/skills'),
  createSkill: (data) => api.post('/admin/skills', data),
  updateSkill: (id, data) => api.put(`/admin/skills/${id}`, data),
}

// ========== NOTIFICATIONS ==========
export const notificationsApi = {
  getNotifications: () => api.get('/notifications'),
  markRead: (id) => api.patch(`/notifications/${id}/read`),
  markAllRead: () => api.patch('/notifications/read-all'),
  getUnreadCount: () => api.get('/notifications/unread-count'),
}
```

---

### Task 3: Refactor `src/stores/auth.store.js`

```javascript
import { defineStore } from 'pinia'
import { authApi } from '@/api/services/api'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: localStorage.getItem('user') ? JSON.parse(localStorage.getItem('user')) : null,
    token: localStorage.getItem('token') || null,
    isLoading: false,
    error: null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.user,
    getUserRole: (state) => state.user?.role,
    displayName: (state) => {
      if (!state.user) return ''
      return `${state.user.first_name} ${state.user.last_name}`.trim()
    },
    profile: (state) => state.user?.profile || null,
  },

  actions: {
    async login(email, password) {
      this.isLoading = true
      this.error = null
      try {
        const res = await authApi.login({ email, password })
        const { access_token, user } = res.data
        this.setUser(user, access_token)
        return user
      } catch (err) {
        this.error = err.message || 'Login failed'
        throw err
      } finally {
        this.isLoading = false
      }
    },

    async register(data) {
      this.isLoading = true
      this.error = null
      try {
        // Split full name if frontend still uses single 'name' input
        if (data.name && !data.first_name) {
          const parts = data.name.trim().split(' ')
          data.first_name = parts[0]
          data.last_name = parts.slice(1).join(' ') || ''
        }
        const res = await authApi.register(data)
        const { access_token, user } = res.data
        this.setUser(user, access_token)
        return user
      } catch (err) {
        this.error = err.message || 'Registration failed'
        throw err
      } finally {
        this.isLoading = false
      }
    },

    async logout() {
      try {
        await authApi.logout()
      } catch (e) {
        // ignore API errors on logout
      }
      this.user = null
      this.token = null
      localStorage.removeItem('user')
      localStorage.removeItem('token')
    },

    setUser(userData, token) {
      this.user = userData
      this.token = token
      localStorage.setItem('user', JSON.stringify(userData))
      localStorage.setItem('token', token)
    },

    async fetchMe() {
      try {
        const res = await authApi.me()
        this.user = res.data
        localStorage.setItem('user', JSON.stringify(res.data))
      } catch (err) {
        this.logout()
      }
    },
  },
})
```

---

### Task 4: Refactor `src/stores/candidate.store.js`

Key changes:
- Remove complex manual diffing of education/experience/skills
- Backend handles batch sync for skills
- Applications include snapshots (no separate job fetch needed)
- Add resume management actions

```javascript
// Simplified updateProfile
async updateProfile(profileData, educationData, experienceData) {
  // 1. Update core profile
  await candidateApi.updateProfile(profileData)

  // 2. Sync education (individual API calls — simple CRUD)
  // For new entries without id: POST
  // For existing with id: PUT
  // For deleted: frontend tracks deleted IDs and sends DELETE

  // 3. Sync experience (same pattern)

  // 4. Refetch everything
  await this.fetchProfile()
}

// Simplified applyForJob
async applyForJob(jobId, coverLetter, resumeId = null) {
  const res = await candidateApi.applyForJob({
    job_id: jobId,
    cover_letter: coverLetter,
    resume_id: resumeId, // optional
  })
  this.applications.unshift(res.data)
  return res.data
}

// New: Resume management
async fetchResumes() { /* ... */ }
async uploadResume(title, file, isDefault = false) { /* ... */ }
async setDefaultResume(resumeId) { /* ... */ }
```

---

### Task 5: Refactor `src/stores/employer.store.js`

Key changes:
- `postJob()` sends full payload to `POST /employer/jobs`
- `updateJob()` uses `PUT`
- `toggleJobStatus()` uses `PATCH /status`
- `fetchApplicationDetails()` no longer needs multi-API merging — single call to `GET /employer/applications/:id`
- `updateApplicationStatus()` uses `PATCH /status`

```javascript
// fetchMyJobs — backend now returns only employer's jobs
async fetchMyJobs() {
  const res = await employerApi.getJobs()
  this.jobs = res.data.data || res.data
}

// fetchMyApplications — backend returns applications with candidate_snapshot
async fetchMyApplications(filters = {}) {
  const res = await employerApi.getApplications(filters)
  this.applications = res.data.data || res.data
}

// fetchApplicationDetails — single API call, no complex merging!
async fetchApplicationDetails(appId) {
  const res = await employerApi.getApplication(appId)
  return {
    application: res.data,
    candidate: res.data.candidate_snapshot,
    stages: res.data.stages,
    interviews: res.data.interviews,
  }
}
```

---

### Task 6: Refactor `src/stores/admin.store.js`

Key changes:
- Replace `fetchAllData()` with paginated, filtered fetches
- Add pagination state

```javascript
state: () => ({
  users: [],
  usersMeta: {},
  jobs: [],
  jobsMeta: {},
  dashboardStats: null,
  pendingReviews: [],
  reports: [],
  loading: false,
  error: null,
})

actions: {
  async fetchDashboard() {
    const res = await adminApi.getDashboard()
    this.dashboardStats = res.data
  },

  async fetchUsers(page = 1, filters = {}) {
    const res = await adminApi.getUsers({ page, ...filters })
    this.users = res.data.data
    this.usersMeta = res.data.meta
  },

  async fetchJobs(page = 1, status = null) {
    const params = { page }
    if (status) params.status = status
    const res = await adminApi.getJobs(params)
    this.jobs = res.data.data
    this.jobsMeta = res.data.meta
  },
}
```

---

### Task 7: Update Router Guards

Current router guard reads from `localStorage` directly:
```javascript
// router.beforeEach
token = localStorage.getItem('token')
user = JSON.parse(localStorage.getItem('user'))
```

**New approach:** Use Pinia auth store (already in state), but handle page refresh gracefully:
```javascript
import { useAuthStore } from '@/stores/auth.store'

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  // On first load or refresh, restore from localStorage
  // Pinia state already does this in its initial state, but:
  if (auth.token && !auth.user) {
    await auth.fetchMe()
  }

  // Existing redirect logic stays the same
  if (auth.isAuthenticated && auth.user && (to.name === 'home' || to.meta.guestOnly)) {
    return { name: `${auth.getUserRole}.dashboard` }
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.role && auth.user && to.meta.role !== auth.getUserRole) {
    return { name: `${auth.getUserRole}.dashboard` }
  }

  return true
})
```

---

### Task 8: Component-Level Changes

| Component | Change Required |
|-----------|---------------|
| `RegisterView.vue` | Split `name` input into `first_name` + `last_name`, or keep single input and split before API call |
| `LoginView.vue` | Verify it works with new API response shape |
| `ProfileView.vue` | Resume upload section: show list of resumes with default flag, upload new, set default, delete |
| `ApplyJob.vue` | Add resume dropdown (select from candidate's resumes, with default pre-selected) |
| `JobDetailsView.vue` | Use `slug` instead of `id` for API calls (keep route param as `:id` but pass slug value) |
| `ManageJobsView.vue` | Job status badges now use `pending_review`, `active`, `closed`, `paused`, `rejected`, `expired` |
| `AdminDashboardView.vue` | Populate stats from `dashboardStats` store state |
| `ManageUsersView.vue` | Add pagination controls |
| `ManageJobsView.vue` (admin) | Add pagination, status filter tabs, rejection reason input |

---

## End-to-End Testing Flows

Test these flows in sequence. Each must pass 100% before declaring US9 complete.

### Flow 1: Candidate Registration → Profile → Apply
1. Register as candidate → auto-creates empty profile
2. Update profile (headline, bio, location, salary expectations)
3. Add education (B.Sc. CS, Cairo University, 2015-2019)
4. Add experience (Frontend Dev at Orange, 2021-present)
5. Sync skills (Vue.js expert, React advanced)
6. Upload 2 resumes ("General CV", "Frontend Specialist") — set one as default
7. Browse jobs → search "frontend" → filter remote
8. Click job → view detail → click Apply
9. Select non-default resume → write cover letter → submit
10. View My Applications → see application with job snapshot

### Flow 2: Employer Registration → Post Job → Review Application
1. Register as employer → auto-creates empty company
2. Update company profile (Vodafone Egypt, telecom, logo, website)
3. Post job (title, category, type, salary, requirements, 3 skills)
4. Job appears in pending queue
5. Admin approves job (in admin panel)
6. Job appears in public listings
7. Candidate applies (from Flow 1)
8. Employer receives notification
9. Employer views application with full candidate snapshot
10. Employer updates status: applied → reviewed → shortlisted
11. Employer schedules interview for next week
12. Candidate receives notification at each status change

### Flow 3: Admin Moderation
1. Admin logs in
2. Views dashboard stats
3. Views pending jobs queue → approves one, rejects one with reason
4. Views pending reviews queue → approves 2, rejects 1
5. Deactivates a candidate user
6. Deactivated user tries to log in → gets "Account deactivated" message

### Flow 4: Public Discovery (No Auth)
1. Visit homepage → featured jobs displayed
2. Browse all jobs → search "Vue" → filter by remote + salary > 20k
3. Click job → view full detail with company info
4. View company profile (Vodafone Egypt) → see active jobs + approved reviews
5. Cannot apply without login → redirect to login with redirect param
6. After login as candidate → auto-redirect back to job detail

### Flow 5: Saved Jobs & Reviews
1. Candidate saves 3 jobs
2. Candidate views Saved Jobs page → sees all 3 with notes
3. Candidate writes review for employer (Vodafone) — 5 stars
4. Review appears in "My Reviews" as pending
5. Admin approves review
6. Review appears on company public page
7. Employer receives notification about new review

---

## Testing Checklist

- [ ] All API calls return 200/201 with correct data shape
- [ ] 401 errors redirect to login
- [ ] 403 errors show appropriate message (wrong role)
- [ ] 422 validation errors display field-level messages in forms
- [ ] Pagination works on job listings, admin tables
- [ ] Search filters correctly narrow job results
- [ ] File uploads (avatar, resume, logo) work and display preview
- [ ] Router guards prevent wrong-role access
- [ ] Auto-redirect after login to intended page works (`?redirect=`)
- [ ] Notifications badge updates in real-time (or on page navigation)
- [ ] Application pipeline status changes reflect immediately in UI
- [ ] Job snapshots in My Applications show correct data even if job was later edited
- [ ] Mobile responsive layout intact after refactoring
- [ ] Console has 0 unhandled API errors during normal usage

---

## Common Pitfalls to Avoid

1. **ID type mismatch:** Frontend may still compare IDs with `===`. Ensure all IDs are treated as strings.
2. **Missing eager loading:** Backend must eager-load relationships (`with(['employer', 'skills'])`) or frontend gets null data.
3. **Snake vs Camel case:** Decide on one convention. Recommended: convert at API layer so frontend stays camelCase.
4. **Token expiry:** Sanctum tokens don't expire by default, but if you configure expiry, frontend must handle refresh.
5. **Image URLs:** After upload, use the returned `url` directly. Don't construct URLs client-side.
6. **FormData for file uploads:** Always use `multipart/form-data` for file uploads, never JSON.
7. **Date formats:** Laravel returns `2026-05-03T10:00:00.000000Z` (ISO 8601). Vue date-fns or native `Date` parses this correctly.

---

## Sign-Off Criteria

US9 is complete when:
- [ ] All 5 E2E flows pass manually
- [ ] No console errors during any flow
- [ ] API service layer is fully migrated (no references to `localhost:3000`)
- [ ] `db.json` and `json-server` are no longer needed and can be removed from project
- [ ] `package.json` no longer needs `json-server` dependency

---

*End of Plan. Start implementing with `01-us1-auth.md`.*
