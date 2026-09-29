# Programming Skills Test — Platform

A full-stack PHP web application for creating and administering programming-skill examinations. Built with a custom MVC architecture using PDO, session-based auth, RBAC, and a hybrid question-selection engine.

---

## Technology Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.1+ |
| Database | MySQL 8 / MariaDB |
| Architecture | Custom MVC (no Composer/frameworks) |
| Auth | Session-based, bcrypt, CSRF protection |
| Tests | Standalone PHP test scripts |
| Web server | Apache via XAMPP (`public/index.php`) |

---

## Directory Layout

```
programming-skills-test/
├── app/
│   ├── Controllers/     # HTTP controllers (Auth, Exam, Question, Attempt, …)
│   ├── Core/            # Framework kernel (Router, Session, CSRF, Database)
│   ├── Middleware/       # Auth, Role, Permission middleware
│   ├── Models/          # PDO-based active-record models
│   ├── Services/        # Business logic (Auth, ExamBuilder, Grading, …)
│   └── Views/           # PHP view templates (layouts, auth, exams, attempts, …)
├── config/
│   └── database.php     # DB config (reads env vars, falls back to defaults)
├── database/
│   └── database.sql     # Full DDL + seed data
├── public/
│   └── index.php        # Application entry point / front controller
├── routes/
│   └── web.php          # All registered routes (52 routes)
├── tests/               # Standalone PHP test scripts (687 total assertions)
│   ├── auth_verification_test.php
│   ├── rbac_test.php
│   ├── question_bank_test.php
│   ├── exam_builder_test.php
│   ├── attempt_test.php
│   └── grading_test.php
└── README.md
```

---

## Setup

### 1. Database

```sql
-- Import the full schema and seed
mysql -u root -p < database/database.sql
```

### 2. Environment (optional)

The app reads standard env vars for database credentials:

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=programming_skills_test
DB_USERNAME=root
DB_PASSWORD=your_password
```

If env vars are not set, values in `config/database.php` are used as fallback.

### 3. Web Server

Point your Apache `DocumentRoot` (or a virtual host) to the `public/` directory, or configure an `.htaccess` rewrite so all requests go through `public/index.php`.

Example XAMPP virtual host:
```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/myproject/programming-skills-test/public"
    ServerName skills.test
</VirtualHost>
```

---

## Running Tests

```bash
php tests/auth_verification_test.php
php tests/rbac_test.php
php tests/question_bank_test.php
php tests/exam_builder_test.php
php tests/attempt_test.php
php tests/grading_test.php
```

All tests create and clean up their own data. Expected: **0 failures** across 687 assertions.

---

## Feature Overview (Phases 1–8)

| Phase | Feature |
|---|---|
| 1–3 | DB schema, PDO singleton, SPL autoloader, custom router, session |
| 4A | Secure registration/login (bcrypt, session fixation prevention, CSRF) |
| 4B | RBAC: 3 roles (admin, teacher, student), 20 permissions, middleware |
| 5 | Question Bank: languages, categories, questions, options, CRUD, safe-delete |
| 6 | Exam Builder: manual, random, and hybrid question selection, rule engine |
| 7 | Exam Taking Engine: snapshots, server timer, AJAX auto-save, ownership checks |
| 8 | Grading & Results: auto-evaluation, pass/fail, visibility controls |

---

## Roles & Permissions Summary

| Role | Key Permissions |
|---|---|
| **student** | `exams.view`, `exams.take`, `results.view`, `categories.view`, `languages.view` |
| **teacher** | All question/exam CRUD except `exams.take`; no user management |
| **admin** | All 20 permissions including `users.*`, `roles.manage`, `audit.view` |

---

## Security

- **CSRF protection** on every POST (token via `App\Core\Csrf`, `hash_equals` comparison)
- **Session hardening**: `use_strict_mode`, `use_only_cookies`, `httponly`, `SameSite=Lax`, session ID regeneration on login
- **Password hashing**: `password_hash` / `password_verify` (bcrypt, `PASSWORD_DEFAULT`)
- **Timing-safe login**: dummy hash compared even if user not found to prevent user enumeration
- **XSS prevention**: all view output via `htmlspecialchars(…, ENT_QUOTES, 'UTF-8')`
- **SQL injection prevention**: 100% PDO prepared statements (`ATTR_EMULATE_PREPARES = false`)
- **Open redirect protection**: `$_POST['back']` validated to relative internal paths only
- **Ownership enforcement**: attempt/answer actions verify `user_id` ownership server-side
- **Correct-answer non-exposure**: `is_correct` never included in taking-interface responses

---

## Legacy Files Notice

The following files in the project root and legacy folders (`logne_page/`, `page_qiuz/`, `tgr/`) are from a previous prototype and are **not used** by the new application:

- `index.php`, `android.php`, `connhome.php`, `data.php`, `logeeeen.php`, `showqiuz.php`, `websiet.php`

They are retained for reference but should be removed before production deployment.

---

## API / Route Reference

| Method | Path | Controller Action | Auth Required |
|---|---|---|---|
| GET | `/` | `HomeController::index` | No |
| GET | `/login` | `AuthController::showLoginForm` | No |
| POST | `/login` | `AuthController::login` | No |
| GET | `/register` | `AuthController::showRegisterForm` | No |
| POST | `/register` | `AuthController::register` | No |
| POST | `/logout` | `AuthController::logout` | CSRF |
| GET | `/dashboard` | `HomeController::dashboard` | Auth |
| GET | `/languages` | `LanguageController::index` | Auth |
| GET | `/categories` | `CategoryController::index` | Auth |
| GET | `/questions` | `QuestionController::index` | Auth + `questions.view` |
| POST | `/questions/store` | `QuestionController::store` | Auth + `questions.create` |
| GET | `/exams` | `ExamController::index` | Auth + `exams.view` |
| POST | `/exams/store` | `ExamController::store` | Auth + `exams.create` |
| POST | `/attempts/start` | `AttemptController::start` | Auth + `exams.take` |
| GET | `/attempts/take` | `AttemptController::take` | Auth + `exams.take` |
| POST | `/attempts/save-answer` | `AttemptController::saveAnswer` | Auth + `exams.take` |
| POST | `/attempts/submit` | `AttemptController::submit` | Auth + `exams.take` |
| GET | `/attempts/result` | `AttemptController::result` | Auth + `results.view` |
| … | … (52 routes total) | … | … |

---

## License

See [LICENSE](LICENSE).
