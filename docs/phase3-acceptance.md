# Prospak Academy — Phase 3 verification and acceptance record

**Date:** 7 November 2026  
**Scope:** Academic organisation management (faculties, departments, programmes, academic levels, student and lecturer placement).  
**Status:** Accepted by project owner on 7 November 2026.

## Verification summary

- PHP syntax checks: passed on the user's Ubuntu installation.
- Phase 1 unit tests: 25/25 passed; Phase 2: 43/43 passed; Phase 3: 38/38 passed (106/106 combined).
- Phase 1–3 static test scripts: passed.
- Phase 2 and Phase 3 read-only MySQL checks: passed, including final regression runs.
- Phase 3 manual browser tests: 20/20 reported passed by the project owner, including role restrictions, CSRF, validation, inactivation, placements, pagination, audit logging, transactions, and Phase 2 regression.
- Git feature branch at the last verification: `feature/phase-3-academic-organisation`, with implementation/report commit `6e0e02f` and a clean working tree before adding this record.

## Approved programme code clarification

The guide uses `BSC-CS` solely as a **fictional test example**. The project owner has confirmed that **`BSC-SC` is the intended programme code** and must remain unchanged. This difference does **not** constitute a defect or require a database migration.

At final database review, programme ID `1` was `BSC-SC` — **BSc Computer Science**, active, linked to department `CSC`. It had **two existing student assignments**. The parent faculty `SCI` — **Faculty of Applied Science** was active. Do not delete/recreate programme ID `1`, or alter student assignments to match the guide's example.

## Scope notes

Phase 3 does not implement destructive deletion, student programme/level history, or multiple primary lecturer departments. These are known scope boundaries, not failed checks. Any fictional pagination or negative-test records should be reviewed for suitable retention before production use.

## Final acceptance

The project owner approved Phase 3 on 7 November 2026
following successful manual testing, automated verification,
database reconciliation and post-testing backup.

Phase 3 is accepted. Phase 4 requires separate authorization.
