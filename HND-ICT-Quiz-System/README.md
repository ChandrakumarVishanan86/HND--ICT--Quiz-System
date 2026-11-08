# HND ICT Quiz System

An online quiz platform for HND ICT practice. Students sign up, pass a quiz in each of 8 modules, and get a printable certificate for every module. Pass all 8 and the programme certificate is issued automatically.

Built with PHP 7.4+, MySQL, vanilla JavaScript and CSS. No frameworks, no internet needed (fonts are bundled).

## Setup on WAMP (3 steps)

1. Copy this folder to `C:\wamp64\www\HND-ICT-Quiz-System`
2. Start WAMP (tray icon must be **green**), then open  
   **http://localhost/HND-ICT-Quiz-System/setup.php** and click **Install database now**.  
   It checks PHP and MySQL, creates `hnd_quiz_db` and loads 8 modules with 96 questions.  
   (Prefer phpMyAdmin? Import `database/hnd_quiz_db.sql` instead.)
3. Open **http://localhost/HND-ICT-Quiz-System/**

| Role | Sign in |
|---|---|
| Admin | `admin` / `admin123` |
| Student | Click "Create an account" |

Change the admin password before using this anywhere public.

## What students get

- Eight modules: Programming Fundamentals, Web Design and Development, Database Management Systems, Computer Networks, Operating Systems and Computer Architecture, Systems Analysis and Design, Software Engineering and Testing, Cyber Security Fundamentals
- 10 random questions per attempt, options shuffled, 15 minute timer
- Answer sheet panel, flag for review, keyboard shortcuts (A to D, arrows, F)
- Answers are saved as they go, so a refresh or dropped connection resumes the same attempt
- Result page with a full review and the reason behind every answer
- Unlimited retakes, best score counts, pass mark 50%
- Module certificate on first pass, programme certificate when all modules are passed
- A4 landscape certificate with a unique number. Use **Print or save as PDF**
- Public check at `verify.php`: anyone can confirm a certificate number

## Admin

- Overview: students, attempts, pass rate per module, certificates issued
- Questions: search, add, edit, delete (a question already used in quizzes is hidden instead of deleted)
- Students: progress per student, delete accounts
- Attempts: every submitted attempt with a link to its full review

## Settings (`config.php`)

| Constant | Default | Meaning |
|---|---|---|
| `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS` | 127.0.0.1, 3306, root, empty | MySQL login (ports 3306 to 3308 are tried automatically) |
| `QUIZ_QUESTIONS` | 10 | Questions per attempt |
| `QUIZ_MINUTES` | 15 | Time limit |
| `PASS_PERCENT` | 50 | Pass mark |
| `CERT_ISSUER` | HND ICT Quiz Academy | Name printed on certificates |

## Folder structure

```
HND-ICT-Quiz-System/
├── index.php          Sign in
├── register.php       Student sign up
├── dashboard.php      Modules and progress
├── quiz.php           Starts or resumes an attempt, shows the quiz player
├── result.php         Score and answer review
├── certificates.php   A student's certificates
├── certificate.php    The printable certificate
├── verify.php         Public certificate check
├── setup.php          One-click installer and connection check
├── config.php  auth.php  lib.php
├── api/               attempt.php  answer.php  submit.php  (JSON, used by the quiz player)
├── admin/             index.php  questions.php  students.php  attempts.php
├── partials/          header.php  footer.php
├── assets/            css/  js/  fonts/
└── database/          hnd_quiz_db.sql
```

## How marking works

- Correct answers never leave the server while a quiz is running.
- Options are shuffled per attempt and stored, so the server can mark the position the student clicked.
- If time runs out, only answers already saved on the server count.
- Passwords are stored as bcrypt hashes. All queries use prepared statements. Forms and API calls are CSRF protected.

## Troubleshooting

- **Database error on the sign-in page**: open `setup.php`. It lists each check and says what to fix.
- **"MySQL is not reachable"**: WAMP is not green, or the port differs. Set `DB_PORT` in `config.php`.
- **"MySQL refused the login"**: set `DB_PASS` in `config.php` if you gave root a password.
- **Certificate prints on two pages**: in the print dialog set margins to None and untick headers and footers.

## About the content

The questions are original practice questions written for these topic areas. They are not official examination papers, and the certificates are practice certificates, not an official qualification.
