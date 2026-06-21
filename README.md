# Planora

An AI-powered study planner. Tell it your subjects, your exam dates, and the days you're free to study, and it builds a session-by-session schedule weighted by difficulty and how close each exam is.

**Live demo:** [planora-production-cf26.up.railway.app](https://planora-production-cf26.up.railway.app). Click "Try the demo," no signup.

![Planora dashboard](docs/screenshot-dashboard.png)

---

## The problem

A to-do list checks off tasks. It doesn't decide when you should study calculus versus when you should study history, or how many hours either one deserves before an exam. Planora makes that call using AI, then tracks what actually happened: completed sessions, missed ones, hours studied per subject.

## Features

- **AI-generated schedules.** Llama 3.3 70B (via Groq) builds the plan. Give it a course name and optionally the topics you need to cover, and it writes specific study notes per session ("Practice SQL joins and subqueries") instead of generic filler.
- **Availability constraints, enforced server-side.** The user picks free days and hours. If the AI schedules a session on a day the user didn't select, the backend rejects it before it reaches the database. The AI proposes; the server enforces.
- **Progress tracking.** Mark sessions completed or missed with one click. Completion rate, hours studied, and per-subject progress update immediately.
- **Demo mode.** A seeded guest account, so anyone can try the full product without creating one.

## Tech stack

| Layer | Tech |
|---|---|
| Backend | PHP 8, vanilla |
| Database | MySQL |
| AI | Groq API (Llama 3.3 70B) |
| Frontend | HTML, CSS, vanilla JS |
| Hosting | Railway |

No framework. The routing, auth, and data layer are all written by hand, which is the clearest way to show how the pieces actually connect.

## How the scheduling works

1. The user adds subjects with a difficulty rating (1–5), an exam date, and optionally a few key topics.
2. The user sets which days they're free and how many hours per day.
3. On "Generate schedule," the app builds a prompt from that data and sends it to Llama 3.3 via Groq.
4. The AI returns a list of sessions: date, duration, subject, and a specific note.
5. The backend validates every session before inserting it. Anything scheduled on a day the user didn't pick, or after a subject's exam date, gets dropped.
6. If a subject has no listed topics, the prompt asks the AI to infer the standard topics for a course with that title.

## Database schema

Five tables: `users`, `subjects`, `availability`, `schedules`, and `reschedule_log`. One user has many subjects, many availability slots, many scheduled sessions. Each session ties back to a subject.

## Running it locally

```bash
git clone https://github.com/naola-26/planora.git
cd planora

# Create the database
mysql -u root -p -e "CREATE DATABASE study_planner;"
mysql -u root -p study_planner < schema.sql

# Configure environment
cp .env.example .env
# Edit .env with your DB credentials and a Groq API key (free at console.groq.com)

# Run
php -S localhost:8000 -t public/
```

Visit `http://localhost:8000`.

## What's next

- Calendar export (ICS) so sessions show up in Google Calendar or Apple Calendar
- Email reminders before a scheduled session
- A per-session breakdown of the AI's reasoning
- A React frontend on the same backend, talking to it over a REST API

## Author

[Naol Shimelis](https://github.com/naola-26)
