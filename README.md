# HND--ICT--Quiz-System
An online quiz platform for HND ICT practice. Students sign up, pass a quiz in each of 8 modules and download a certificate for every module. Pass all 8 and the *programme certificate* is issued automatically.

Mobile friendly:


## Features

*Students*
- 8 modules, each a quiz task: Programming Fundamentals, Web Design and Development, Database Management Systems, Computer Networks, Operating Systems and Computer Architecture, Systems Analysis and Design, Software Engineering and Testing, Cyber Security Fundamentals
- 96 original questions with an explanation for every answer
- 10 random questions per attempt, shuffled options, 15 minute timer
- Answer sheet panel, flag for review, keyboard shortcuts (A to D, arrow keys, F)
- Answers save as you go, so a refresh or dropped connection resumes the same attempt
- Unlimited retakes, best score counts, pass mark 50%
- Module certificate on first pass, programme certificate when all modules are passed
- A4 landscape certificate with a unique number, print or save as PDF
- Public certificate check at verify.php

*Admin*
- Overview with students, attempts, pass rate per module and certificates issued
- Question bank: search, add, edit, delete (questions already used in quizzes are hidden instead of deleted)
- Student progress and attempt history with full answer review

*Security*
- Correct answers never reach the browser during a quiz
- bcrypt password hashes, prepared statements, CSRF protection, login throttling
- Timer enforced on the server

## Tech stack

| Layer | Tech |
|---|---|
| Frontend | HTML, CSS, vanilla JavaScript (no frameworks, fonts bundled locally) |
| Backend | PHP 7.4+ with PDO |
| Database | MySQL or MariaDB (hnd_quiz_db) |

## Quick start (WAMP)

1. Clone or download into your web root:
   bash
   cd C:\wamp64\www
   git clone https://github.com/<your-username>/HND-ICT-Quiz-System.git
   
2. Start WAMP (tray icon must be *green*).
3. Open *http://localhost/HND-ICT-Quiz-System/setup.php* and click *Install database now*.  
   It checks PHP and MySQL, then creates hnd_quiz_db with 8 modules and 96 questions.  
   (Prefer phpMyAdmin? Import database/hnd_quiz_db.sql instead.)
4. Open *http://localhost/HND-ICT-Quiz-System/*

| Role | Sign in |
|---|---|
| Admin | admin / admin123 |
| Student | Click "Create an account" |

Change the admin password before using this anywhere public.

## Settings

Everything is at the top of config.php.

| Constant | Default | Meaning |
|---|---|---|
| DB_HOST, DB_PORT, DB_USER, DB_PASS | 127.0.0.1, 3306, root, empty | MySQL login (ports 3306 to 3308 are tried automatically) |
| QUIZ_QUESTIONS | 10 | Questions per attempt |
| QUIZ_MINUTES | 15 | Time limit |
| PASS_PERCENT | 50 | Pass mark |
| CERT_ISSUER | HND ICT Quiz Academy | Name printed on certificates |

## Project structure


HND-ICT-Quiz-System/
├── index.php          Sign in
├── register.php       Student sign up
├── dashboard.php      Modules and progress
├── quiz.php           Starts or resumes an attempt, shows the quiz player
├── result.php         Score and answer review
├── certificates.php   A student's certificates
├── certificate.php    Printable certificate
├── verify.php         Public certificate check
├── setup.php          One-click installer and connection check
├── config.php  auth.php  lib.php
├── api/               attempt.php  answer.php  submit.php  (JSON for the quiz player)
├── admin/             index.php  questions.php  students.php  attempts.php
├── partials/          header.php  footer.php
├── assets/            css/  js/  fonts/
├── database/          hnd_quiz_db.sql
└── docs/              README screenshots


## Database

| Table | Purpose |
|---|---|
| users | Students and admins (bcrypt hashes) |
| modules | The 8 quiz tasks |
| questions | Question bank (4 options, correct index, explanation) |
| attempts | One row per quiz attempt with score and result |
| attempt_questions | Questions drawn for an attempt, shuffled order and chosen answer |
| certificates | Issued certificates with unique numbers |

## How marking works

- Options are shuffled per attempt and stored, so the server marks the position the student clicked.
- If time runs out, only answers already saved on the server count.
- A module certificate is issued on the first pass. The programme certificate is issued when every active module has been passed.

## Troubleshooting

- *Database error on the sign-in page*: open setup.php. It lists each check and says what to fix.
- *"MySQL is not reachable"*: WAMP is not green, or the port differs. Set DB_PORT in config.php.
- *"MySQL refused the login"*: set DB_PASS in config.php if you gave root a password.
- *Certificate prints on two pages*: in the print dialog set margins to None and untick headers and footers.

## About the content

The questions are original practice questions written for these topic areas. They are not official examination papers, and the certificates are practice certificates, not an official qualification.

## License

MIT. Use it freely for learning and your own projects

Chandrakumar Vishanan SLIATE (HNDIT)
