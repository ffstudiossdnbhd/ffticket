# Global Engineering Rules

## Codex Configuration (MANDATORY)
- Always use standard mode (fast off)

## Core Principles
- Prioritize security, correctness, and maintainability over speed
- Make the smallest safe change possible
- Do not rewrite large sections unless necessary
- Preserve all existing functionality and UI

## Code Quality
- Avoid duplication (DRY)
- Remove dead or unused code only when safe
- Keep functions modular and readable
- Follow existing project patterns and naming conventions

## Security Rules (STRICT)
- Treat ALL user input as untrusted
- Always validate and sanitize inputs
- Always escape output (HTML, JSON, etc.)
- Use prepared statements for ALL SQL queries
- NEVER use string concatenation in SQL

- Never hardcode:
  - API keys
  - passwords
  - tokens
  - secrets

- Always use environment variables (.env)

## High-Risk Areas (Extra Care Required)
- Authentication / login / sessions
- Role-based access (admin vs user)
- File uploads
- Database writes (INSERT, UPDATE, DELETE)
- External URLs / domains

For these:
- Identify risks BEFORE editing
- Explain impact BEFORE making changes

## Performance Rules
- Avoid unnecessary database queries
- Avoid N+1 query patterns
- Optimize loops and repeated logic
- Avoid redundant API calls
- Minimize heavy frontend scripts

## Change Control
- For risky changes:
  - Ask before proceeding
- Always explain:
  - what changed
  - why
  - risk level
  - expected improvement

## Validation
- After every change:
  - check logic correctness
  - check for security issues
  - check for performance regressions