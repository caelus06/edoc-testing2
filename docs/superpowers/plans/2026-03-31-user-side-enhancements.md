# User-Side Enhancements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enhance the user-side of the eDoc system with cancel requests, change password, editable review info, personalized welcome banner, and a full UI redesign using Bebas Neue/Metropolis fonts with Bootstrap Icons.

**Architecture:** PHP procedural backend with vanilla JS frontend. Per-page CSS files (no shared base). SweetAlert2 for dialogs. CSRF protection on all POST endpoints. Audit logging on all state changes.

**Tech Stack:** PHP 8+, MySQL/MariaDB, vanilla JavaScript (fetch API), CSS3 custom properties, Google Fonts CDN (Bebas Neue), local @font-face (Metropolis), Bootstrap Icons CDN, SweetAlert2 CDN.

**Spec:** `docs/superpowers/specs/2026-03-31-user-side-enhancements-design.md`

---

## File Map

### Files to Create
| File | Responsibility |
|------|---------------|
| `assets/fonts/Metropolis-Regular.woff2` | Body font (regular weight) |
| `assets/fonts/Metropolis-Medium.woff2` | Body font (medium weight) |
| `assets/fonts/Metropolis-Bold.woff2` | Body font (bold weight) |
| `assets/fonts/Metropolis-Light.woff2` | Body font (light weight) |
| `user/request_cancel.php` | Cancel PENDING request endpoint |
| `user/change_password.php` | Change password endpoint |
| `assets/css/track.css` | Track page dedicated styles (file exists but is unused; will be rewritten) |

### Files to Modify
| File | Changes |
|------|---------|
| `user/dashboard.php` | Welcome banner, cancel buttons in table, Bootstrap Icons CDN, topbar icons |
| `user/profile.php` | Change password section, Bootstrap Icons CDN, restyle |
| `user/profile_update.php` | JSON response mode, N/A→NULL fix, exclude email option |
| `user/request.php` | Step indicator, Bootstrap Icons CDN, restyle |
| `user/request_review.php` | Editable info section (AJAX), warning banner, step indicator, Bootstrap Icons CDN |
| `user/track.php` | Cancel button, CANCELLED status_class, standardize topbar, new CSS file ref |
| `assets/css/user_dashboard.css` | Full restyle: fonts, cards, welcome banner, table, responsive |
| `assets/css/profile.css` | Full restyle: fonts, cards, change password section |
| `assets/css/user_request.css` | Restyle: fonts, step indicator, form inputs |
| `assets/css/user_request_review.css` | Restyle: fonts, editable info section, warning banner |

---

## Task 1: Font Setup — Download Metropolis & Verify Google Fonts

**Files:**
- Create: `assets/fonts/Metropolis-Regular.woff2`, `Metropolis-Medium.woff2`, `Metropolis-Bold.woff2`, `Metropolis-Light.woff2`

- [ ] **Step 1: Create fonts directory and download Metropolis**

```bash
mkdir -p assets/fonts
```

Download Metropolis font files from the open-source repository (https://github.com/chrismsimpson/Metropolis). We need .woff2 files for: Regular, Medium, Bold, Light. Place them in `assets/fonts/`.

If .woff2 is not available directly, download .otf/.ttf and convert using an online tool or `woff2_compress`.

- [ ] **Step 2: Verify font files exist**

```bash
ls -la assets/fonts/
```

Expected: At least Metropolis-Regular.woff2 and Metropolis-Bold.woff2 present.

- [ ] **Step 3: Commit**

```bash
git add assets/fonts/
git commit -m "feat: add Metropolis font files for UI redesign"
```

---

## Task 2: Dashboard CSS Restyle

**Files:**
- Modify: `assets/css/user_dashboard.css` (442 lines)

This task rewrites the dashboard CSS with new fonts, welcome banner gradient, card styles, and table improvements.

- [ ] **Step 1: Replace CSS variables and global styles (lines 1-40)**

Replace the `:root` block and global styles. Add Google Fonts import for Bebas Neue, @font-face for Metropolis, Bootstrap Icons CDN import, and updated variables:

```css
@import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap');

@font-face {
  font-family: 'Metropolis';
  src: url('../fonts/Metropolis-Regular.woff2') format('woff2');
  font-weight: 400;
  font-style: normal;
  font-display: swap;
}
@font-face {
  font-family: 'Metropolis';
  src: url('../fonts/Metropolis-Medium.woff2') format('woff2');
  font-weight: 500;
  font-style: normal;
  font-display: swap;
}
@font-face {
  font-family: 'Metropolis';
  src: url('../fonts/Metropolis-Bold.woff2') format('woff2');
  font-weight: 700;
  font-style: normal;
  font-display: swap;
}
@font-face {
  font-family: 'Metropolis';
  src: url('../fonts/Metropolis-Light.woff2') format('woff2');
  font-weight: 300;
  font-style: normal;
  font-display: swap;
}

:root {
  --primary: #002699;
  --primary-hover: #001f80;
  --secondary: #29ABE2;
  --bg-color: #F8FAFC;
  --surface: #FFFFFF;
  --text-main: #1E293B;
  --text-muted: #64748B;
  --border: #E2E8F0;
  --shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);
  --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -4px rgba(0,0,0,0.1);
  --radius: 12px;
  --font-heading: 'Bebas Neue', Arial, Impact, sans-serif;
  --font-body: 'Metropolis', Arial, sans-serif;
  --status-pending: #F59E0B;
  --status-approved: #10B981;
  --status-processing: #3B82F6;
  --status-returned: #EF4444;
  --status-completed: #6366F1;
}

*, *::before, *::after { box-sizing: border-box; }

body {
  margin: 0;
  font-family: var(--font-body);
  background: var(--bg-color);
  color: var(--text-main);
  line-height: 1.6;
}

h1, h2, h3, h4, h5, h6 {
  font-family: var(--font-heading);
  letter-spacing: 0.5px;
}
```

- [ ] **Step 2: Update welcome panel CSS (lines 219-231)**

Replace the `.panel.welcome` styles with a new `.welcome-banner` class for the gradient banner:

```css
.welcome-banner {
  background: linear-gradient(135deg, #002699 0%, #1a0a5c 50%, #4a1a8a 100%);
  border-radius: var(--radius);
  padding: 32px 40px;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
  box-shadow: var(--shadow-lg);
}

.welcome-banner h2 {
  font-family: var(--font-heading);
  font-size: 2rem;
  margin: 0 0 4px;
  letter-spacing: 1px;
}

.welcome-banner .date {
  font-family: var(--font-body);
  font-size: 0.95rem;
  opacity: 0.85;
}

.welcome-banner .role-badge {
  background: rgba(255,255,255,0.15);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255,255,255,0.2);
  border-radius: 10px;
  padding: 12px 20px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}

.welcome-banner .role-badge .label {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  opacity: 0.8;
}

.welcome-banner .role-badge .value {
  font-family: var(--font-heading);
  font-size: 1.3rem;
  letter-spacing: 0.5px;
}

.welcome-banner .role-badge i {
  font-size: 1.2rem;
  opacity: 0.8;
  margin-bottom: 2px;
}
```

- [ ] **Step 3: Update action cards CSS (lines 234-264)**

Update card styling with new fonts, shadows, rounded corners:

```css
.actions {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 20px;
  margin-bottom: 28px;
}

.actions .card {
  background: var(--surface);
  border-radius: var(--radius);
  padding: 28px 24px;
  text-align: center;
  text-decoration: none;
  color: var(--text-main);
  box-shadow: var(--shadow);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  border: 1px solid var(--border);
}

.actions .card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-lg);
  border-color: var(--secondary);
}

.actions .card i {
  font-size: 2rem;
  color: var(--primary);
  margin-bottom: 12px;
  display: block;
}

.actions .card h3 {
  font-family: var(--font-heading);
  font-size: 1.25rem;
  margin: 0 0 6px;
  letter-spacing: 0.5px;
}

.actions .card p {
  font-size: 0.85rem;
  color: var(--text-muted);
  margin: 0;
}
```

- [ ] **Step 4: Update table section CSS (lines 319-406)**

Restyle the request table with cleaner spacing, updated fonts, and a cancel button style:

```css
.table-section {
  background: var(--surface);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  overflow: hidden;
  border: 1px solid var(--border);
}

.table-section table {
  width: 100%;
  border-collapse: collapse;
}

.table-section th {
  font-family: var(--font-heading);
  font-size: 0.95rem;
  letter-spacing: 0.5px;
  background: var(--primary);
  color: #fff;
  padding: 14px 16px;
  text-align: left;
}

.table-section td {
  padding: 12px 16px;
  border-bottom: 1px solid var(--border);
  font-size: 0.9rem;
}

.table-section tr:last-child td {
  border-bottom: none;
}

.table-section tr:hover {
  background: #f1f5f9;
}

.btn-cancel {
  background: none;
  border: 1px solid var(--status-returned);
  color: var(--status-returned);
  padding: 6px 14px;
  border-radius: 6px;
  cursor: pointer;
  font-family: var(--font-body);
  font-size: 0.8rem;
  font-weight: 500;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.btn-cancel:hover {
  background: var(--status-returned);
  color: #fff;
}
```

- [ ] **Step 5: Update topbar, toolbar, pagination, modal, and responsive CSS**

Update remaining sections with var(--font-body), var(--font-heading), var(--radius) references. Ensure consistent button/input styles with rounded corners, focus rings (`outline: 2px solid var(--secondary); outline-offset: 2px`), and transition effects.

- [ ] **Step 6: Verify the CSS file compiles correctly**

Open dashboard in browser and visually inspect. No broken layouts.

- [ ] **Step 7: Commit**

```bash
git add assets/css/user_dashboard.css
git commit -m "feat: restyle dashboard CSS with Bebas Neue, Metropolis, card layouts"
```

---

## Task 3: Dashboard PHP — Welcome Banner & Cancel Button

> **Dependency:** Task 4 (request_cancel.php) should be completed before or alongside this task, since the cancel button posts to that endpoint.

**Files:**
- Modify: `user/dashboard.php` (391 lines)

- [ ] **Step 1: Add Bootstrap Icons CDN in head (after line 159)**

```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
```

- [ ] **Step 2: Fetch user first name for welcome banner**

In the PHP backend section (around line 7), add a query to get the user's first name:

```php
$nameStmt = $conn->prepare("SELECT first_name FROM users WHERE id = ?");
$nameStmt->bind_param("i", $userId);
$nameStmt->execute();
$firstName = $nameStmt->get_result()->fetch_assoc()["first_name"] ?? "User";
$nameStmt->close();
```

- [ ] **Step 3: Replace welcome section (lines 200-203)**

Replace the existing `.panel.welcome` with the new gradient banner:

```php
<section class="welcome-banner">
  <div>
    <h2>Welcome back, <?= h(strtoupper($firstName)) ?>!</h2>
    <p class="date"><?= date('l, F j, Y.') ?></p>
  </div>
  <div class="role-badge">
    <i class="bi bi-person-badge"></i>
    <span class="label">Current Role</span>
    <span class="value">Student</span>
  </div>
</section>
```

- [ ] **Step 4: Update action cards with Bootstrap Icons (lines 205-221)**

Replace the action card links. Add icons:

```php
<section class="actions">
  <a href="request.php" class="card">
    <i class="bi bi-file-earmark-plus"></i>
    <h3>Request Document</h3>
    <p>Submit a new document request</p>
  </a>
  <a href="upload_requirements.php" class="card">
    <i class="bi bi-cloud-arrow-up"></i>
    <h3>Upload Requirements</h3>
    <p>Upload required files for your request</p>
  </a>
  <a href="application_process.php" class="card">
    <i class="bi bi-list-check"></i>
    <h3>Application Process</h3>
    <p>View requirements for document types</p>
  </a>
</section>
```

- [ ] **Step 5: Add cancel button column to request table (around lines 249-292)**

In the table header, add an "Action" column. In each row, if status is PENDING, show a cancel button:

```php
<!-- In thead -->
<th>Action</th>

<!-- In tbody per row -->
<td>
  <?php if ($row["status"] === STATUS_PENDING): ?>
    <button class="btn-cancel" onclick="cancelRequest(<?= (int)$row['id'] ?>, '<?= h($row['reference_no']) ?>')">
      <i class="bi bi-x-circle"></i> Cancel
    </button>
  <?php else: ?>
    —
  <?php endif; ?>
</td>
```

- [ ] **Step 6: Update topbar icons to Bootstrap Icons (lines 164-182)**

Replace emoji/text icons in the topbar with Bootstrap Icons:

```php
<button class="icon-btn" id="notifBtn" title="Notifications">
  <i class="bi bi-bell"></i>
  <?php if ($badgeCount > 0): ?>
    <span class="badge"><?= $badgeCount > NOTIF_BADGE_CAP ? '99+' : $badgeCount ?></span>
  <?php endif; ?>
</button>
<a href="profile.php" class="icon-btn" title="Profile"><i class="bi bi-person-circle"></i></a>
<a href="../auth/logout.php" class="icon-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
```

- [ ] **Step 7: Add cancel request JavaScript (in the script section, around line 345)**

```javascript
function cancelRequest(requestId, refNo) {
  Swal.fire({
    title: 'Cancel Request?',
    text: `Are you sure you want to cancel request ${refNo}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#EF4444',
    confirmButtonText: 'Yes, cancel it',
    cancelButtonText: 'No, keep it'
  }).then((result) => {
    if (result.isConfirmed) {
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = 'request_cancel.php';

      const csrfInput = document.createElement('input');
      csrfInput.type = 'hidden';
      csrfInput.name = '_csrf_token';
      csrfInput.value = '<?= csrf_token() ?>';
      form.appendChild(csrfInput);

      const idInput = document.createElement('input');
      idInput.type = 'hidden';
      idInput.name = 'request_id';
      idInput.value = requestId;
      form.appendChild(idInput);

      document.body.appendChild(form);
      form.submit();
    }
  });
}
```

- [ ] **Step 8: Verify in browser**

Load dashboard.php. Check: welcome banner with name/date/role, action cards with icons, cancel button on PENDING requests, topbar icons.

- [ ] **Step 9: Commit**

```bash
git add user/dashboard.php
git commit -m "feat: welcome banner, cancel button, Bootstrap Icons on dashboard"
```

---

## Task 4: Cancel Request Backend

**Files:**
- Create: `user/request_cancel.php`

- [ ] **Step 1: Create request_cancel.php**

```php
<?php
require_once __DIR__ . "/../includes/helpers.php";
require_role(ROLE_USER);
csrf_verify();

$userId    = $_SESSION["user_id"];
$requestId = (int)($_POST["request_id"] ?? 0);

if ($requestId <= 0) {
    swal_flash("error", "Error", "Invalid request.");
    header("Location: dashboard.php");
    exit;
}

// Verify request belongs to user and is PENDING
$stmt = $conn->prepare("SELECT id, reference_no, status FROM requests WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $requestId, $userId);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$request) {
    swal_flash("error", "Error", "Request not found.");
    header("Location: dashboard.php");
    exit;
}

if ($request["status"] !== STATUS_PENDING) {
    swal_flash("error", "Error", "Only pending requests can be cancelled.");
    header("Location: dashboard.php");
    exit;
}

// Cancel the request
$update = $conn->prepare("UPDATE requests SET status = ? WHERE id = ?");
$cancelled = STATUS_CANCELLED;
$update->bind_param("si", $cancelled, $requestId);
$update->execute();
$update->close();

// Log it
add_log($conn, $requestId, "Request cancelled by user");
audit_log($conn, "CANCEL", "requests", $requestId, "Cancelled request " . $request["reference_no"]);

swal_flash("success", "Cancelled", "Your request has been cancelled.");

// Redirect back to referer or dashboard
$referer = $_SERVER["HTTP_REFERER"] ?? "dashboard.php";
$allowed = ["dashboard.php", "track.php"];
$redirectTo = "dashboard.php";
foreach ($allowed as $page) {
    if (strpos($referer, $page) !== false) {
        $redirectTo = $referer;
        break;
    }
}
header("Location: " . $redirectTo);
exit;
```

- [ ] **Step 2: Verify STATUS_CANCELLED constant exists in helpers.php**

Check `includes/helpers.php` lines 22-31 for status constants. If `STATUS_CANCELLED` is not defined, add it:

```php
define("STATUS_CANCELLED", "CANCELLED");
```

- [ ] **Step 3: Test cancel flow**

1. Log in as a user with a PENDING request
2. Click cancel on dashboard → confirm → should redirect with success message
3. Request status should now be CANCELLED in database
4. Cancel button should no longer appear for that request

- [ ] **Step 4: Commit**

```bash
git add user/request_cancel.php includes/helpers.php
git commit -m "feat: add cancel request endpoint for PENDING requests"
```

---

## Task 5: Track Page — Restyle, Cancel Button, Standardize Topbar

**Files:**
- Create: `assets/css/track.css`
- Modify: `user/track.php` (268 lines)

- [ ] **Step 1: Create track.css**

Create a full CSS file for the track page with the same font imports, @font-face declarations, and CSS variables as user_dashboard.css (copy the root block). Then add track-specific styles:

```css
/* Same @import, @font-face, :root, *, body, h1-h6 block as user_dashboard.css */

.topbar { /* same topbar styles */ }

.container {
  max-width: 900px;
  margin: 100px auto 40px;
  padding: 0 20px;
}

.card {
  background: var(--surface);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  border: 1px solid var(--border);
  padding: 28px;
  margin-bottom: 24px;
}

.card h2 {
  font-family: var(--font-heading);
  font-size: 1.5rem;
  margin: 0 0 20px;
  letter-spacing: 0.5px;
}

.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.info-item label {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--text-muted);
  display: block;
  margin-bottom: 4px;
}

.info-item span {
  font-size: 0.95rem;
  font-weight: 500;
}

/* Status pill */
.status-pill {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 600;
}

/* Cancel button (same as dashboard) */
.btn-cancel { /* same styles as dashboard btn-cancel */ }

/* Requirements section */
.req-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 0;
  border-bottom: 1px solid var(--border);
}

.req-row:last-child { border-bottom: none; }

/* Timeline */
.timeline-item {
  display: flex;
  gap: 16px;
  padding: 16px 0;
  border-left: 2px solid var(--border);
  margin-left: 8px;
  padding-left: 24px;
  position: relative;
}

.timeline-item::before {
  content: '';
  width: 10px;
  height: 10px;
  background: var(--primary);
  border-radius: 50%;
  position: absolute;
  left: -6px;
  top: 20px;
}

.timeline-item .time {
  font-size: 0.8rem;
  color: var(--text-muted);
  white-space: nowrap;
  min-width: 140px;
}

.timeline-item .message {
  font-size: 0.9rem;
}
```

- [ ] **Step 2: Update track.php — change CSS reference (line 147)**

Replace `../assets/css/dashboard.css` with `../assets/css/track.css`.

- [ ] **Step 3: Add Bootstrap Icons CDN in head**

```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
```

- [ ] **Step 4: Add CANCELLED to status_class function (lines 78-86)**

```php
function status_class($s) {
    return match($s) {
        'PENDING'          => 'status-pending',
        'RETURNED'         => 'status-returned',
        'VERIFIED','APPROVED' => 'status-approved',
        'PROCESSING'       => 'status-processing',
        'READY FOR PICKUP','RELEASED','COMPLETED' => 'status-completed',
        'CANCELLED'        => 'status-returned',
        default            => '',
    };
}
```

- [ ] **Step 5: Standardize topbar (lines 153-163)**

Replace the minimal topbar with the full topbar including notification bell, profile link, and logout — matching dashboard.php's topbar. This requires fetching notification data in the PHP backend section (add notification query similar to dashboard.php).

- [ ] **Step 6: Add cancel button in request details section**

After the request info display (around line 184), add a cancel button if status is PENDING:

```php
<?php if ($reqData["status"] === STATUS_PENDING): ?>
  <button class="btn-cancel" onclick="cancelRequest(<?= (int)$reqData['id'] ?>, '<?= h($reqData['reference_no']) ?>')">
    <i class="bi bi-x-circle"></i> Cancel Request
  </button>
<?php endif; ?>
```

- [ ] **Step 7: Add cancel JavaScript and notification modal**

Add the same `cancelRequest()` function as dashboard.php (posting to request_cancel.php with CSRF token). Add notification modal markup and JS if topbar now includes the bell.

- [ ] **Step 8: Restyle HTML structure**

Update the HTML to use `.card`, `.info-grid`, `.info-item`, `.req-row`, `.timeline-item` classes matching the new CSS. Remove inline styles (lines 101-140).

- [ ] **Step 9: Verify in browser**

Load track.php with a valid reference number. Check: topbar with all icons, request info card, cancel button (PENDING only), requirements section, timeline.

- [ ] **Step 10: Commit**

```bash
git add assets/css/track.css user/track.php
git commit -m "feat: restyle track page, add cancel button, standardize topbar"
```

---

## Task 6: Profile Page — Restyle & Change Password Section

**Files:**
- Modify: `user/profile.php` (260 lines)
- Modify: `assets/css/profile.css` (215 lines)
- Create: `user/change_password.php`

- [ ] **Step 1: Update profile.css with new fonts and variables**

Replace the global styles and add the same @import, @font-face, :root block as other CSS files. Update card styling, info rows, buttons with var(--font-body), var(--font-heading), var(--radius). Add change password section styles:

```css
.password-card {
  background: var(--surface);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  border: 1px solid var(--border);
  padding: 28px;
  margin-top: 24px;
}

.password-card h2 {
  font-family: var(--font-heading);
  font-size: 1.5rem;
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0 0 20px;
}

.password-field {
  position: relative;
  margin-bottom: 16px;
}

.password-field label {
  display: block;
  font-size: 0.85rem;
  font-weight: 500;
  margin-bottom: 6px;
  color: var(--text-main);
}

.password-field input {
  width: 100%;
  padding: 10px 40px 10px 14px;
  border: 1px solid var(--border);
  border-radius: 8px;
  font-family: var(--font-body);
  font-size: 0.9rem;
  transition: border-color 0.2s;
}

.password-field input:focus {
  outline: none;
  border-color: var(--secondary);
  box-shadow: 0 0 0 3px rgba(41, 171, 226, 0.15);
}

.password-toggle {
  position: absolute;
  right: 12px;
  top: 36px;
  background: none;
  border: none;
  cursor: pointer;
  color: var(--text-muted);
  font-size: 1.1rem;
}

.password-toggle:hover { color: var(--text-main); }

.btn-save-password {
  background: var(--primary);
  color: #fff;
  border: none;
  padding: 10px 24px;
  border-radius: 8px;
  font-family: var(--font-body);
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-save-password:hover { background: var(--primary-hover); }

.password-error {
  color: var(--status-returned);
  font-size: 0.8rem;
  margin-top: 4px;
}
```

- [ ] **Step 2: Add Bootstrap Icons CDN to profile.php head (after line 60)**

```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
```

- [ ] **Step 3: Add change password HTML section to profile.php**

After the main wrapper closing tag (after line 173), add:

```php
<div class="password-card">
  <h2><i class="bi bi-key"></i> Change Password</h2>
  <form id="changePasswordForm" method="POST" action="change_password.php">
    <?= csrf_field() ?>
    <div class="password-field">
      <label for="current_password">Current Password</label>
      <input type="password" id="current_password" name="current_password" required>
      <button type="button" class="password-toggle" onclick="togglePassword('current_password', this)">
        <i class="bi bi-eye"></i>
      </button>
    </div>
    <div class="password-field">
      <label for="new_password">New Password</label>
      <input type="password" id="new_password" name="new_password" required minlength="8">
      <button type="button" class="password-toggle" onclick="togglePassword('new_password', this)">
        <i class="bi bi-eye"></i>
      </button>
      <div class="password-error" id="newPwError"></div>
    </div>
    <div class="password-field">
      <label for="confirm_password">Confirm New Password</label>
      <input type="password" id="confirm_password" name="confirm_password" required>
      <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', this)">
        <i class="bi bi-eye"></i>
      </button>
      <div class="password-error" id="confirmPwError"></div>
    </div>
    <button type="submit" class="btn-save-password">Save Password</button>
  </form>
</div>
```

- [ ] **Step 4: Add password JavaScript to profile.php**

In the script section, add:

```javascript
function togglePassword(fieldId, btn) {
  const input = document.getElementById(fieldId);
  const icon = btn.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'bi bi-eye';
  }
}

document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
  const newPw = document.getElementById('new_password').value;
  const confirmPw = document.getElementById('confirm_password').value;
  const newErr = document.getElementById('newPwError');
  const confirmErr = document.getElementById('confirmPwError');
  newErr.textContent = '';
  confirmErr.textContent = '';

  if (newPw.length < 8) {
    e.preventDefault();
    newErr.textContent = 'Password must be at least 8 characters.';
    return;
  }
  if (newPw !== confirmPw) {
    e.preventDefault();
    confirmErr.textContent = 'Passwords do not match.';
    return;
  }
});
```

- [ ] **Step 5: Create change_password.php**

```php
<?php
require_once __DIR__ . "/../includes/helpers.php";
require_role(ROLE_USER);
csrf_verify();

$userId = $_SESSION["user_id"];

$currentPw = $_POST["current_password"] ?? "";
$newPw     = $_POST["new_password"] ?? "";
$confirmPw = $_POST["confirm_password"] ?? "";

// Validate
if ($newPw !== $confirmPw) {
    swal_flash("error", "Error", "New passwords do not match.");
    header("Location: profile.php");
    exit;
}

if (strlen($newPw) < 8) {
    swal_flash("error", "Error", "New password must be at least 8 characters.");
    header("Location: profile.php");
    exit;
}

// Fetch current hash
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || !password_verify($currentPw, $row["password"])) {
    swal_flash("error", "Error", "Current password is incorrect.");
    header("Location: profile.php");
    exit;
}

if (password_verify($newPw, $row["password"])) {
    swal_flash("error", "Error", "New password must be different from current password.");
    header("Location: profile.php");
    exit;
}

// Update
$hash = password_hash($newPw, PASSWORD_DEFAULT);
$update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$update->bind_param("si", $hash, $userId);
$update->execute();
$update->close();

audit_log($conn, "CHANGE_PASSWORD", "users", $userId, "User changed their password");

swal_flash("success", "Success", "Password changed successfully.");
header("Location: profile.php");
exit;
```

- [ ] **Step 6: Update profile.php topbar icons to Bootstrap Icons**

Replace any emoji/text icons with Bootstrap Icon `<i>` tags, matching dashboard topbar pattern.

- [ ] **Step 7: Verify in browser**

Load profile.php. Check: restyled cards, change password form at bottom, eye toggles work, validation messages show, topbar icons consistent.

- [ ] **Step 8: Commit**

```bash
git add user/profile.php user/change_password.php assets/css/profile.css
git commit -m "feat: change password section and profile page restyle"
```

---

## Task 7: Profile Update Endpoint — JSON Support & N/A Fix

**Files:**
- Modify: `user/profile_update.php` (52 lines)

- [ ] **Step 1: Add JSON response mode and N/A fix**

Rewrite profile_update.php to support both redirect (from profile.php) and JSON response (from request_review.php AJAX). Fix N/A data pollution:

```php
<?php
require_once __DIR__ . "/../includes/helpers.php";
require_role(ROLE_USER);
csrf_verify();

$userId = $_SESSION["user_id"];
$isAjax = !empty($_POST["ajax"]);

// Collect fields — exclude email if called from request review
$excludeEmail = !empty($_POST["exclude_email"]);

$fields = [
    "first_name"     => trim($_POST["first_name"] ?? ""),
    "middle_name"    => trim($_POST["middle_name"] ?? ""),
    "last_name"      => trim($_POST["last_name"] ?? ""),
    "suffix"         => trim($_POST["suffix"] ?? ""),
    "student_id"     => trim($_POST["student_id"] ?? ""),
    "course"         => trim($_POST["course"] ?? ""),
    "major"          => trim($_POST["major"] ?? ""),
    "year_graduated" => trim($_POST["year_graduated"] ?? ""),
    "gender"         => trim($_POST["gender"] ?? ""),
    "contact_number" => trim($_POST["contact_number"] ?? ""),
    "address"        => trim($_POST["address"] ?? ""),
];

if (!$excludeEmail && !empty($_POST["email"])) {
    $email = trim($_POST["email"]);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if ($isAjax) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Invalid email address."]);
            exit;
        }
        swal_flash("error", "Error", "Invalid email address.");
        header("Location: profile.php");
        exit;
    }
    $fields["email"] = $email;
}

// Convert empty strings and "N/A" to NULL
foreach ($fields as $key => &$value) {
    if ($value === "" || strtoupper($value) === "N/A") {
        $value = null;
    }
}
unset($value);

// Build dynamic UPDATE
$setParts = [];
$types = "";
$values = [];
foreach ($fields as $col => $val) {
    $setParts[] = "$col = ?";
    $types .= $val === null ? "s" : "s";
    $values[] = $val;
}
$types .= "i";
$values[] = $userId;

$sql = "UPDATE users SET " . implode(", ", $setParts) . " WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$values);
$stmt->execute();
$stmt->close();

audit_log($conn, "UPDATE", "users", $userId, "User updated profile information");

if ($isAjax) {
    header("Content-Type: application/json");
    echo json_encode(["success" => true, "message" => "Profile updated successfully."]);
    exit;
}

swal_flash("success", "Updated", "Profile updated successfully.");
header("Location: profile.php");
exit;
```

- [ ] **Step 2: Verify profile.php still works with the updated endpoint**

Load profile.php, edit a field, save. Should redirect with success message as before.

- [ ] **Step 3: Commit**

```bash
git add user/profile_update.php
git commit -m "feat: add JSON response mode and fix N/A data pollution in profile update"
```

---

## Task 8: Request Flow Restyle — request.php

**Files:**
- Modify: `user/request.php` (258 lines)
- Modify: `assets/css/user_request.css` (351 lines)

- [ ] **Step 1: Update user_request.css with new fonts and variables**

Replace the `:root` block and global styles with the same font imports/@font-face/variables block. Update form input styles, button styles, and add step indicator styles:

```css
.step-indicator {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  margin-bottom: 24px;
}

.step-indicator .step {
  display: flex;
  align-items: center;
  gap: 8px;
  font-family: var(--font-body);
  font-size: 0.85rem;
  color: var(--text-muted);
}

.step-indicator .step.active {
  color: var(--primary);
  font-weight: 600;
}

.step-indicator .step .num {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
  font-weight: 700;
  background: var(--border);
  color: var(--text-muted);
}

.step-indicator .step.active .num {
  background: var(--primary);
  color: #fff;
}

.step-indicator .divider {
  width: 40px;
  height: 2px;
  background: var(--border);
}
```

- [ ] **Step 2: Add Bootstrap Icons CDN and step indicator to request.php**

In the head section (after line 76), add Bootstrap Icons CDN link.

After the banner section (around line 107), add the step indicator:

```php
<div class="step-indicator">
  <div class="step active">
    <span class="num">1</span>
    <span>Select Document</span>
  </div>
  <div class="divider"></div>
  <div class="step">
    <span class="num">2</span>
    <span>Review & Submit</span>
  </div>
</div>
```

- [ ] **Step 3: Update topbar icons to Bootstrap Icons**

Same pattern as dashboard — replace emoji icons with Bootstrap Icon `<i>` tags.

- [ ] **Step 4: Verify in browser**

Load request.php. Check: step indicator shows Step 1 active, form inputs restyled, topbar icons consistent.

- [ ] **Step 5: Commit**

```bash
git add user/request.php assets/css/user_request.css
git commit -m "feat: restyle request page with step indicator and new fonts"
```

---

## Task 9: Request Review — Editable Info Section

**Files:**
- Modify: `user/request_review.php` (225 lines)
- Modify: `assets/css/user_request_review.css` (324 lines)

- [ ] **Step 1: Update user_request_review.css with new fonts, variables, and editable info styles**

Replace :root and globals with the standard font block. Add:

```css
.info-section {
  position: relative;
}

.btn-edit-info {
  position: absolute;
  top: 0;
  right: 0;
  background: none;
  border: 1px solid var(--primary);
  color: var(--primary);
  padding: 6px 14px;
  border-radius: 6px;
  cursor: pointer;
  font-family: var(--font-body);
  font-size: 0.8rem;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 4px;
  transition: all 0.2s;
}

.btn-edit-info:hover {
  background: var(--primary);
  color: #fff;
}

.edit-warning {
  background: #FEF3C7;
  border: 1px solid #F59E0B;
  border-radius: 8px;
  padding: 12px 16px;
  margin-bottom: 16px;
  font-size: 0.85rem;
  color: #92400E;
  display: none;
}

.edit-warning.visible { display: block; }

.edit-warning i { margin-right: 6px; }

.info-row input,
.info-row select {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid var(--border);
  border-radius: 6px;
  font-family: var(--font-body);
  font-size: 0.9rem;
  display: none;
}

.info-row input:focus,
.info-row select:focus {
  outline: none;
  border-color: var(--secondary);
  box-shadow: 0 0 0 3px rgba(41, 171, 226, 0.15);
}

.info-row.editing input,
.info-row.editing select { display: block; }
.info-row.editing .value-text { display: none; }

.edit-actions {
  display: none;
  gap: 10px;
  margin-top: 16px;
}

.edit-actions.visible { display: flex; }

.btn-save-info {
  background: var(--primary);
  color: #fff;
  border: none;
  padding: 8px 20px;
  border-radius: 6px;
  cursor: pointer;
  font-family: var(--font-body);
  font-weight: 600;
  transition: background 0.2s;
}

.btn-save-info:hover { background: var(--primary-hover); }

.btn-cancel-edit {
  background: none;
  border: 1px solid var(--border);
  color: var(--text-muted);
  padding: 8px 20px;
  border-radius: 6px;
  cursor: pointer;
  font-family: var(--font-body);
  transition: all 0.2s;
}

.btn-cancel-edit:hover {
  border-color: var(--text-muted);
  color: var(--text-main);
}

/* Step indicator (same as request.css) */
.step-indicator { /* same styles */ }
```

- [ ] **Step 2: Add Bootstrap Icons CDN to request_review.php head**

```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
```

- [ ] **Step 3: Add step indicator (Step 2 active)**

After banner, before the info section:

```php
<div class="step-indicator">
  <div class="step">
    <span class="num">1</span>
    <span>Select Document</span>
  </div>
  <div class="divider"></div>
  <div class="step active">
    <span class="num">2</span>
    <span>Review & Submit</span>
  </div>
</div>
```

- [ ] **Step 4: Refactor personal info section (lines 128-141) for inline editing**

Replace the static info display with editable rows. Each row has a `value-text` span and a hidden input:

```php
<div class="info-section">
  <button type="button" class="btn-edit-info" id="editInfoBtn">
    <i class="bi bi-pencil-square"></i> Edit
  </button>
  <h3>Personal Information</h3>

  <div class="edit-warning" id="editWarning">
    <i class="bi bi-exclamation-triangle"></i>
    Updating information here will also change your main profile information.
    This ensures you don't have to re-enter details every time you make a request.
  </div>

  <?php
  $infoFields = [
      "first_name"     => ["First Name",     $user["first_name"]],
      "middle_name"    => ["Middle Name",     $user["middle_name"]],
      "last_name"      => ["Last Name",       $user["last_name"]],
      "suffix"         => ["Suffix",          $user["suffix"]],
      "student_id"     => ["Student ID",      $user["student_id"]],
      "course"         => ["Course",          $user["course"]],
      "major"          => ["Major",           $user["major"]],
      "year_graduated" => ["Year Graduated",  $user["year_graduated"]],
      "gender"         => ["Gender",          $user["gender"]],
      "contact_number" => ["Contact Number",  $user["contact_number"]],
      "address"        => ["Address",         $user["address"]],
  ];
  foreach ($infoFields as $field => $meta): ?>
    <div class="info-row" data-field="<?= $field ?>">
      <span class="label"><?= $meta[0] ?></span>
      <span class="value-text"><?= h($meta[1] ?? '—') ?></span>
      <?php if ($field === "gender"): ?>
        <select name="<?= $field ?>">
          <option value="">—</option>
          <option value="Male" <?= ($meta[1] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
          <option value="Female" <?= ($meta[1] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
        </select>
      <?php else: ?>
        <input type="text" name="<?= $field ?>" value="<?= h($meta[1] ?? '') ?>">
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <div class="edit-actions" id="editActions">
    <button type="button" class="btn-save-info" id="saveInfoBtn">Save Changes</button>
    <button type="button" class="btn-cancel-edit" id="cancelEditBtn">Cancel</button>
  </div>
</div>
```

- [ ] **Step 5: Add inline edit JavaScript**

```javascript
const editBtn = document.getElementById('editInfoBtn');
const saveBtn = document.getElementById('saveInfoBtn');
const cancelBtn = document.getElementById('cancelEditBtn');
const warning = document.getElementById('editWarning');
const actions = document.getElementById('editActions');
const infoRows = document.querySelectorAll('.info-row');

let originalValues = {};

editBtn.addEventListener('click', () => {
  // Store original values
  infoRows.forEach(row => {
    const field = row.dataset.field;
    const input = row.querySelector('input, select');
    originalValues[field] = input.value;
    row.classList.add('editing');
  });
  warning.classList.add('visible');
  actions.classList.add('visible');
  editBtn.style.display = 'none';
});

cancelBtn.addEventListener('click', () => {
  infoRows.forEach(row => {
    const field = row.dataset.field;
    const input = row.querySelector('input, select');
    input.value = originalValues[field];
    row.classList.remove('editing');
  });
  warning.classList.remove('visible');
  actions.classList.remove('visible');
  editBtn.style.display = '';
});

saveBtn.addEventListener('click', () => {
  const formData = new FormData();
  formData.append('_csrf_token', '<?= csrf_token() ?>');
  formData.append('ajax', '1');
  formData.append('exclude_email', '1');

  infoRows.forEach(row => {
    const field = row.dataset.field;
    const input = row.querySelector('input, select');
    formData.append(field, input.value);
  });

  fetch('profile_update.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        // Update display values
        infoRows.forEach(row => {
          const input = row.querySelector('input, select');
          const text = row.querySelector('.value-text');
          text.textContent = input.value || '—';
          row.classList.remove('editing');
        });
        warning.classList.remove('visible');
        actions.classList.remove('visible');
        editBtn.style.display = '';
        Swal.fire({ icon: 'success', title: 'Updated', text: data.message, timer: 2000, showConfirmButton: false });
      } else {
        Swal.fire({ icon: 'error', title: 'Error', text: data.message });
      }
    })
    .catch(() => {
      Swal.fire({ icon: 'error', title: 'Error', text: 'Something went wrong. Please try again.' });
    });
});
```

- [ ] **Step 6: Update topbar icons to Bootstrap Icons**

Same pattern as other pages.

- [ ] **Step 7: Verify in browser**

Load request_review.php (must go through request.php first to set session). Check: step indicator shows Step 2, edit button toggles fields, warning appears, save calls AJAX and updates display, cancel reverts.

- [ ] **Step 8: Commit**

```bash
git add user/request_review.php assets/css/user_request_review.css
git commit -m "feat: editable info section with AJAX save on request review page"
```

---

## Task 10: Final UX Polish Pass

**Files:**
- Modify: all user CSS files and PHP files as needed

- [ ] **Step 1: Audit all button styles across pages**

Ensure all primary action buttons have consistent padding (10px 24px), border-radius (8px), font-family (var(--font-body)), font-weight (600), hover states, and focus rings:

```css
button:focus-visible, a:focus-visible {
  outline: 2px solid var(--secondary);
  outline-offset: 2px;
}
```

Add this to each page's CSS.

- [ ] **Step 2: Audit all input styles**

Ensure form inputs across all pages have consistent border-radius (8px), padding (10px 14px), border transitions, and focus states with the blue glow.

- [ ] **Step 3: Verify Bootstrap Icons are loaded on all user pages**

Pages that need the CDN link: dashboard.php, profile.php, request.php, request_review.php, track.php, upload_requirements.php, upload_requirements_upload.php, application_process.php.

- [ ] **Step 4: Test responsive behavior**

Check all pages at mobile widths (375px). Ensure: welcome banner stacks vertically, action cards stack, tables scroll horizontally, forms are full-width, modals fit screen.

- [ ] **Step 5: Test complete user flows**

1. Login → dashboard (welcome banner visible)
2. Dashboard → Request Document → fill form → Review (step indicators work, edit info, save)
3. Dashboard → cancel a PENDING request
4. Dashboard → Track → cancel from track page
5. Profile → edit info → change password
6. All pages → notification bell works

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: final UX polish pass — consistent buttons, inputs, focus states, responsive"
```

---

## Summary

| Task | Description | Priority |
|------|-------------|----------|
| 1 | Font setup (Metropolis files) | Foundation |
| 2 | Dashboard CSS restyle | High |
| 3 | Dashboard PHP (welcome banner, cancel, icons) | High |
| 4 | Cancel request backend | High-Mid |
| 5 | Track page restyle + cancel + topbar | High |
| 6 | Profile restyle + change password | High |
| 7 | Profile update endpoint (JSON, N/A fix) | High |
| 8 | Request page restyle + step indicator | Low |
| 9 | Request review — editable info (AJAX) | Low |
| 10 | Final UX polish pass | Low |
