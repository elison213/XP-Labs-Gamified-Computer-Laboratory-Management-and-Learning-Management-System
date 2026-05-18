# XPLabs — Gamified Computer Lab & LMS

XPLabs is a **web-based LMS** plus a **Windows lab client** (lockscreen, widget, PowerShell agent) for managing computer-lab PCs: sign-in, remote lock/unlock, messaging, quizzes, attendance, and analytics.

**Base URL (example):** `http://local.xplabs.com/xplabs`  
**API prefix:** `/api/...` (JSON over HTTP)

---

## Technology stack & versions

| Layer | Technology | Version / notes |
|--------|------------|-----------------|
| **Web server** | Apache (XAMPP) | Bundled with [XAMPP](https://www.apachefriends.org/) (typical: Apache 2.4.x) |
| **Runtime** | PHP | **≥ 8.0** (`composer.json`) |
| **Database** | MySQL / MariaDB | **5.7+** or **10.3+** (InnoDB, UTF-8) |
| **PHP dependencies** | Composer | `phpoffice/phpspreadsheet` **^2.0 \| ^3.0** (user import / spreadsheets) |
| **App** | Custom PHP | PSR-4-style autoload (`lib/`, `services/`), no full framework |
| **Sessions** | PHP sessions | Cookie `XPLABS_SESSION` (`config/app.php`) |
| **Lab agent** | PowerShell | Windows 10/11; modules in `windows/agent/` (**AGENT_VERSION** in `windows/agent/AGENT_VERSION.txt`) |
| **Lockscreen / widget** | C# WPF | **.NET Framework 4.8** (`net48`), SDK-style projects |
| **Optional reference** | MeshCentral (folder) | Third-party RMM reference only; **not** required to run XPLabs |

**Typical lab URLs**

| Role | IP example |
|------|------------|
| Server (XAMPP) | `192.168.100.22` |
| Lab PC client | `192.168.100.23` |

Client config: `C:\ProgramData\XPLabsAgent\agent.config.json` → `server_base_url`

---

## How APIs are built

- Each endpoint is a **PHP file** under `api/` (e.g. `api/pc/heartbeat.php`).
- Shared logic lives in **`services/`** (e.g. `PCService`, `QuizService`).
- **JSON** request/response; errors use HTTP status codes (`400`, `401`, `403`, `404`, `429`, `500`).
- **CORS** for lab PCs: `api/middleware/CorsMiddleware.php`.

### Authentication types (easy to explain)

| Type | Who uses it | How |
|------|-------------|-----|
| **Session** | Browser (admin, teacher, student) | Log in via `POST /api/auth/login`; cookie sent on later requests |
| **Machine key** | Lab PC agent, lockscreen, widget | Header **`X-Machine-Key`** (stored in `machine_key.txt` on the PC) |
| **CSRF token** | Browser POST/PUT/DELETE that changes data | Required with session for many admin/teacher actions |
| **Kiosk token** | Door/tablet kiosk | Header **`X-Kiosk-Token`** (or legacy LAN IP allowlist in `config/app.php`) |
| **Open registration** | First agent contact | `POST /api/pc/register` (no key yet; returns a key) |

---

## API catalog (by area)

Below, paths are relative to the app root (e.g. `/xplabs/api/...`).  
**Auth** column: Session · Machine · CSRF · Kiosk · Public.

### Authentication (`api/auth/`)

| Method | Path | What it does | Auth |
|--------|------|--------------|------|
| POST | `/api/auth/login.php` | Log in with email/LRN + password; starts PHP session | Public |
| GET | `/api/auth/me.php` | Return current logged-in user profile | Session |
| GET/POST | `/api/auth/logout.php` | End session (web logout) | Session |

---

### Lab PC — agent & desktop (`api/pc/`)

Used by the **PowerShell agent** (`Run-AgentLoop.ps1`) and sometimes the **widget**.

| Method | Path | What it does | Auth |
|--------|------|--------------|------|
| POST | `/api/pc/register.php` | Register hostname/MAC; get **machine key** | Public |
| POST | `/api/pc/heartbeat.php` | Check-in, status, pending **remote commands** | Machine |
| GET | `/api/pc/commands.php` | List pending commands (`?after_cursor=`) | Machine |
| POST | `/api/pc/commands.php` | Acknowledge command executed/failed | Machine |
| GET | `/api/pc/config.php` | Intervals, floor/station, server-driven agent settings | Machine |
| POST | `/api/pc/activity.php` | Ingest **desktop activity** (foreground app, idle) from widget | Machine |
| GET | `/api/pc/activity.php` | List recent activity events for this PC | Machine |
| GET | `/api/pc/messages.php` | Message threads for student widget | Machine |
| POST | `/api/pc/message-reply.php` | Student reply from widget | Machine |
| GET | `/api/pc/debug-events.php` | Protocol/debug events for admins | Session (admin/teacher) |
| POST | `/api/pc/discover.php` | Discover PCs on network (DHCP scan helper) | Session + CSRF |
| POST | `/api/pc/discovery-sync.php` | Sync discovered hosts into DB | Session (admin) + CSRF |
| POST | `/api/pc/assign.php` | Assign PC to floor/station | Session + CSRF |
| POST | `/api/pc/unassign.php` | Remove floor/station assignment | Session + CSRF |
| GET | `/api/pc/unassigned.php` | List unassigned PCs | Session |
| GET | `/api/pc/deploy-status.php` | Deployment job status | Session |
| GET | `/api/pc/deploy-evaluate.php` | Evaluate auto-deploy rules | Session (admin) |
| POST | `/api/pc/deploy-policy.php` | Set deploy policy | Session (admin) + CSRF |
| POST | `/api/pc/deploy-queue.php` | Queue deploy job | Session (admin) + CSRF |
| POST | `/api/pc/deploy-run.php` | Run deploy job on server | Session (admin) + CSRF |
| POST | `/api/pc/deploy-retry.php` | Retry failed deploy | Session (admin) + CSRF |
| POST | `/api/pc/update-queue.php` | Queue agent/UI update | Session (admin) + CSRF |
| POST | `/api/pc/update-run.php` | Run update job | Session (admin) + CSRF |
| POST | `/api/pc/update-retry.php` | Retry update | Session (admin) + CSRF |
| GET | `/api/pc/update-status.php` | Update job status | Session |

**Remote commands** (queued via `/api/lab/queue-command.php`, delivered on heartbeat/poll): `lock`, `unlock`, `restart`, `shutdown`, `message`, `screenshot`.

---

### Lab management (`api/lab/`)

| Method | Path | What it does | Auth |
|--------|------|--------------|------|
| GET/POST | `/api/lab/floors.php` | List/create lab floors | Session (admin/teacher); POST + CSRF |
| GET/POST | `/api/lab/layout.php` | Floor seat layout | Session; POST + CSRF |
| GET/PATCH | `/api/lab/stations.php` | Lab stations; PATCH updates station | Session; PATCH + CSRF |
| POST | `/api/lab/queue-command.php` | Queue lock/unlock/restart/message to PC(s) | Session (admin/teacher) + CSRF |
| POST | `/api/lab/pc-message.php` | Instructor sends message to a PC (opens widget) | Session + CSRF |
| GET | `/api/lab/pc-messages.php` | List threads/messages (`pc_id` or `thread_id`) | Session |

---

### PC sessions (`api/session/`)

| Method | Path | What it does | Auth |
|--------|------|--------------|------|
| POST | `/api/session/pc-student-login.php` | **Lockscreen** LRN + password → PC session | Machine |
| POST | `/api/session/pc-checkin.php` | Machine-authenticated check-in | Machine |
| POST | `/api/session/pc-checkout.php` | End session on this PC | Machine |
| GET | `/api/session/validate.php` | Agent validates active session / grace | Machine |
| POST | `/api/session/override-unlock.php` | Instructor override code on lockscreen | Machine |
| POST | `/api/session/force-logout.php` | Force-end student session + queue lock | Session + CSRF |

---

### Access control for lab PCs (`api/access/`)

| Method | Path | What it does | Auth |
|--------|------|--------------|------|
| GET | `/api/access/drive-maps.php` | Network drive mappings for role | Machine or Session |
| GET | `/api/access/folder-rules.php` | Folder allow/deny rules for floor | Machine |

---

### Kiosk (`api/kiosk/`)

| Method | Path | What it does | Auth |
|--------|------|--------------|------|
| POST | `/api/kiosk/unlock.php` | QR/kiosk: attendance + assign PC + unlock | Kiosk / IP |
| POST | `/api/kiosk/pair.php` | Pair kiosk device | Kiosk |
| GET/POST/DELETE | `/api/kiosk/devices.php` | Manage kiosk devices | Session + CSRF (mutations) |

---

### Courses & LMS (`api/courses/`, `api/assignments/`, `api/submissions/`)

| Method | Path | What it does | Auth |
|--------|------|--------------|------|
| GET | `/api/courses/list.php` | List courses for user | Session |
| GET | `/api/courses/detail.php` | Course detail | Session |
| POST | `/api/courses/create.php` | Create course | Session (admin) |
| POST | `/api/courses/enroll.php` | Enroll students | Session (admin/teacher) |
| GET | `/api/courses/students.php` | Students in a course | Session |
| GET | `/api/assignments/list.php` | List assignments | Session |
| POST | `/api/assignments/create.php` | Create assignment | Session (admin/teacher) |
| POST | `/api/submissions/submit.php` | Submit assignment work | Session |

---

### Quizzes & gamification (`api/quizzes/`)

| Method | Path | What it does | Auth |
|--------|------|--------------|------|
| GET | `/api/quizzes/list.php` | List quizzes | Session |
| POST | `/api/quizzes/create.php` | Create quiz | Session (admin/teacher) + CSRF |
| GET | `/api/quizzes/questions.php` | Questions for a quiz | Session |
| POST | `/api/quizzes/join.php` | Start/join attempt | Session |
| POST | `/api/quizzes/submit-answer.php` | Submit one answer (optional powerup) | Session |
| POST | `/api/quizzes/finish-attempt.php` | Finish attempt, scoring | Session |
| GET | `/api/quizzes/results.php` | Results for quiz/user | Session |
| GET | `/api/quizzes/leaderboard.php` | Quiz leaderboard | Session |
| GET | `/api/quizzes/hint/dictionary.php` | Hint dictionary lookup | Session (student) |
| GET | `/api/quizzes/powerups/list.php` | Powerups available for question | Session (student) |
| GET | `/api/quizzes/powerups/state.php` | Powerup state for attempt | Session (student) |
| POST | `/api/quizzes/powerups/activate.php` | Use powerup in attempt | Session + CSRF |
| POST | `/api/quizzes/powerups/redeem.php` | Buy/redeem powerup | Session + CSRF |

---

### Attendance (`api/attendance/`)

| Method | Path | What it does | Auth |
|--------|------|--------------|------|
| GET | `/api/attendance/sessions.php` | List class attendance sessions | Session (admin/teacher) |
| POST | `/api/attendance/start-session.php` | Teacher starts class session | Session (admin/teacher) |
| POST | `/api/attendance/qr-checkin.php` | QR check-in (LRN) | Session |
| POST | `/api/attendance/qr-checkout.php` | QR check-out | Session |

---

### Users & imports (`api/users/`)

| Method | Path | What it does | Auth |
|--------|------|--------------|------|
| GET | `/api/users/list.php` | List users | Session (admin/teacher) |
| POST | `/api/users/import-preview.php` | Preview spreadsheet import | Session (admin) + CSRF |
| POST | `/api/users/import.php` | Import users from spreadsheet | Session (admin) + CSRF |

---

### Analytics (`api/analytics/`)

| Method | Path | What it does | Auth |
|--------|------|--------------|------|
| GET | `/api/analytics/attendance.php` | Attendance stats | Session (admin/teacher) |
| GET | `/api/analytics/quizzes.php` | Quiz performance stats | Session (admin/teacher) |
| GET | `/api/analytics/feedback.php` | Feedback analytics | Session (admin/teacher) |
| GET | `/api/analytics/lab-usage.php` | Lab PC usage / sessions | Session (admin/teacher) |

---

### Incidents, announcements, notifications, awards, leaderboard

| Method | Path | What it does | Auth |
|--------|------|--------------|------|
| GET | `/api/incidents/list.php` | List incidents | Session (admin/teacher) |
| GET | `/api/incidents/detail.php` | Incident detail | Session (admin/teacher) |
| POST | `/api/incidents/create.php` | Report incident | Session (admin/teacher) |
| GET | `/api/announcements/list.php` | Announcements | Session |
| POST | `/api/announcements/create.php` | Create announcement | Session (admin/teacher) |
| GET | `/api/notifications/list.php` | User notifications | Session |
| POST | `/api/awards/create.php` | Award points/badge | Session (admin/teacher) + CSRF |
| GET | `/api/leaderboard/list.php` | Global/course leaderboard | Session |

---

## Data flow examples (for presentations)

### 1) Student signs in at lab PC

```
Lockscreen → POST /api/session/pc-student-login.php (Machine key)
         → PCService creates pc_sessions row
         → Agent unlocks UI, starts widget
```

### 2) Teacher locks a PC from dashboard

```
Browser → POST /api/lab/queue-command.php (Session + CSRF, command_type=lock)
       → remote_commands row (pending)
       → Agent heartbeat/poll → POST /api/pc/commands.php (ack)
       → Lockscreen shown in user session
```

### 3) Desktop activity (what app student uses)

```
Widget samples foreground window every ~15s
       → POST /api/pc/activity.php (Machine key)
       → pc_activity_events table
       → Admin UI: Activity logs → Lab desktop activity
```

---

## Project layout (short)

| Path | Purpose |
|------|---------|
| `api/` | JSON HTTP endpoints |
| `services/` | Business logic |
| `lib/` | Auth, Database, CSRF, autoload |
| `database/migrations/` | SQL schema versions |
| `windows/agent/` | Lab PC PowerShell agent |
| `windows/lockscreen/`, `windows/widget/` | .NET 4.8 desktop apps |
| `tools/` | CLI helpers (migrations, queue reset) |

---

## Setup & docs

| Task | Command / doc |
|------|----------------|
| Database migrations | `php database/migrate.php` |
| PC activity table | `php tools/ensure-pc-activity-table.php` |
| Lab agent build/deploy | [`windows/AGENT_BUILD.md`](windows/AGENT_BUILD.md) |
| Windows client config | [`windows/config/README.md`](windows/config/README.md) |
| Quiz powerups QA | [`docs/QUIZ_POWERUPS_QA.md`](docs/QUIZ_POWERUPS_QA.md) |

---

## License & repository

See repository license. Issues and contributions via GitHub.
