# User-Side Enhancements Design Spec

**Date:** 2026-03-31
**Scope:** User-side features and UI redesign for eDoc Document Requesting System

---

## 1. CSS Foundation & Font Overhaul

### Fonts
- **Headings:** Bebas Neue via Google Fonts CDN
- **Body:** Metropolis via local `@font-face` (files in `/assets/fonts/`). Source: open-source font by Chris Simpson — download .woff2/.woff files and place in `/assets/fonts/`
- **Fallbacks:** Arial, Impact, sans-serif

### CSS Strategy
- Per-page CSS files (no shared base CSS) — each page is self-contained
- Each file defines its own CSS variables and font imports at the top
- Variables to add per page:
  - `--font-heading: 'Bebas Neue', Arial, Impact, sans-serif`
  - `--font-body: 'Metropolis', Arial, sans-serif`
- Keep existing color variables: `--primary: #002699`, `--secondary: #29ABE2`

### Icons
- Bootstrap Icons via CDN link
- Used consistently for: edit (pencil-square), password (key), delete/cancel (x-circle), notifications (bell), profile (person), etc.

### Card Styling Standard
- White background
- Subtle box-shadow
- `border-radius: 12px`
- Consistent padding (24px)

---

## 2. Personalized Welcome Banner

### Location
Replace the existing `.panel.welcome` section in `dashboard.php` (currently lines ~200-203).

### Layout
- Full-width banner with blue-to-purple gradient (`#002699` left to purple right)
- **Left side:**
  - "Welcome back, {FIRST_NAME}!" — Bebas Neue, all caps, white, large
  - Current date below in Metropolis (e.g., "Tuesday, March 31, 2026.")
- **Right side:**
  - Badge showing "CURRENT ROLE" label + "Student" value
  - Semi-transparent background, small icon

### Data Sources
- First name: queried from `users` table via `$_SESSION["user_id"]` (already fetched on dashboard)
- Date: PHP `date('l, F j, Y.')` formatted
- Role: hardcoded "Student" for user-side (all USER-role accounts are treated as students; alumni distinction is not needed for now)

---

## 3. Cancel Request Feature

### Trigger Locations
1. **Dashboard (`dashboard.php`):** Cancel button/icon per request row, only visible when `status = 'PENDING'`
2. **Track page (`track.php`):** Cancel button in request details area, only when `status = 'PENDING'`

### User Flow
1. User clicks cancel button
2. SweetAlert2 confirmation: "Are you sure you want to cancel this request?" (warning icon)
3. On confirm: POST to `request_cancel.php`

### Backend (`request_cancel.php`)
1. CSRF token verification
2. Validate request belongs to current user (`user_id` check)
3. Confirm request status is still `PENDING`
4. UPDATE `requests` SET `status = 'CANCELLED'`
5. INSERT into `request_logs`: "Request cancelled by user"
6. Record `audit_log` entry
7. Redirect back to HTTP referer (dashboard or track page) with SweetAlert success message

### Important
- No hard delete — record stays in database as CANCELLED
- Registrar-side cleanup will be a separate future task

---

## 4. Change Password

### Location
New card section at the bottom of `profile.php`, below the personal info form.

### Layout
- Card with key icon (Bootstrap Icons `bi-key`) and "Change Password" heading (Bebas Neue)
- Three fields:
  1. Current Password
  2. New Password
  3. Confirm New Password
- Each field has an eye toggle icon to show/hide password
- "Save Password" button

### Frontend Validation
- All fields required
- New password minimum 8 characters
- Confirm must match new password
- Real-time validation feedback

### Backend (`change_password.php`)
1. CSRF token verification
2. Verify current password: `password_verify($current, $stored_hash)`
3. Validate new password minimum length (8 chars)
4. Validate new password !== current password
5. Hash: `password_hash($new, PASSWORD_DEFAULT)`
6. UPDATE `users` SET `password = $hash`
7. Record `audit_log` entry
8. SweetAlert success/error feedback via redirect

---

## 5. Editable Information in Request Review

### Location
`request_review.php` — the personal information display section.

### Current Behavior
Shows user info as read-only text before submitting a document request.

### New Behavior
1. "Edit" button (pencil icon `bi-pencil-square`) at top of personal info section
2. Clicking toggles read-only text to editable input fields (inline editing)
3. Warning banner appears:
   > "Updating information here will also change your main profile information. This ensures you don't have to re-enter details every time you make a request."
4. "Save" and "Cancel" buttons appear in edit mode
5. **Save:** POST to `profile_update.php` → updates `users` table immediately → refreshes displayed info
6. **Cancel:** reverts to read-only view, no changes saved

### Editable Fields
- first_name, middle_name, last_name, suffix
- student_id, course, major, year_graduated
- gender, contact_number, address

### Not Editable
- email (too sensitive for casual inline editing)

### Backend
Reuse the existing `user/profile_update.php` endpoint (already handles profile updates from `profile.php`). Modifications needed:
1. Add JSON response support: if request includes `Accept: application/json` header or an `ajax=1` parameter, return JSON instead of redirect
2. Exclude `email` from the editable fields when called from request_review context
3. Convert empty/"N/A" values to NULL before saving (fix existing data pollution bug)

### Inline Edit Approach
- Use AJAX (fetch API) to submit the form — avoids losing `$_SESSION["req"]` data from a full page redirect
- Include CSRF token in the fetch body (using existing `csrf_token()` from helpers.php)
- On success: update displayed text values in-place, switch back to read-only mode
- On error: show SweetAlert error message

---

## 6. UI Redesign & UX Polish

### Dashboard (`dashboard.php` / `user_dashboard.css`)
- Welcome banner (Section 2)
- Action cards restyled: Bebas Neue headings, shadows, rounded corners, Bootstrap Icons
- Request table: cleaner spacing, status pills, cancel button for PENDING rows

### Profile (`profile.php` / `profile.css`)
- Card-based layout for ID images and personal info sections
- Change password card (Section 4)
- Consistent button styling with hover/focus states
- Edit pencil icons for inline editing

### Request Flow (`request.php`, `request_review.php`)
- Form inputs: rounded corners, clear focus states (blue outline), consistent sizing
- Editable review section (Section 5)
- Step indicator: `request.php` = "Step 1 of 2", `request_review.php` = "Step 2 of 2"

### Track Page (`track.php` / currently uses `dashboard.css`)
- Create a new `assets/css/track.css` for track-page-specific styles
- Card layout for request details
- Timeline with better visual hierarchy
- Cancel button for PENDING requests
- Add `CANCELLED` case to the `status_class()` function in track.php

### Navigation Standardization
- Ensure all user pages have consistent topbar (brand, notification bell, profile link, logout)
- Currently `track.php` lacks notification bell and profile link — add them

### UX Consistency (All User Pages)
- **Buttons:** consistent padding, rounded corners, hover (darken/lighten), focus ring (outline)
- **Inputs:** rounded corners, border transitions on focus, consistent height
- **Icons:** Bootstrap Icons used consistently
- **Transitions:** `transition: all 0.2s ease` on interactive elements
- **Responsive:** works on mobile and desktop

---

## Implementation Order

1. CSS foundation: fonts, variables, Bootstrap Icons CDN (per-page updates)
2. Welcome banner on dashboard
3. Dashboard UI restyle (action cards, request table)
4. Cancel request feature (dashboard + track page)
5. Profile page restyle + change password section
6. Request flow restyle + editable review
7. Track page restyle
8. Final UX polish pass (hover states, focus rings, icon consistency)

---

## Files to Create
- `assets/fonts/` — Metropolis font files (.woff2, .woff)
- `user/request_cancel.php` — cancel request endpoint
- `user/change_password.php` — change password endpoint
- `assets/css/track.css` — track page styles (currently uses dashboard.css)

## Files to Modify
- `user/dashboard.php` — welcome banner, cancel buttons, UI restyle
- `user/profile.php` — change password section, UI restyle
- `user/profile_update.php` — add JSON response support, fix N/A bug, exclude email from request_review context
- `user/request.php` — UI restyle, step indicator
- `user/request_review.php` — editable info section (AJAX), UI restyle
- `user/track.php` — cancel button, CANCELLED status_class, standardize topbar, UI restyle
- `assets/css/user_dashboard.css` — full restyle
- `assets/css/profile.css` — full restyle
- Per-page CSS for request flow pages
