# Open questions

Everything here needs a decision from Gianfranco or from the Orbitas team. Each entry records what
was found, the safe default already applied in the code/contract, and what a different answer would
change.

Status values: **OPEN** (needs an answer), **ANSWERED** (answer recorded, implementation follows),
**CLOSED** (no longer relevant).

---

## Q1 — PHPStan level

**Status:** ANSWERED (decided in Task 0, 13 Oct 2026)

`LOOP_PROMPT.md` does not fix a PHPStan level; the home-directory convention mentions level 5.
**Chosen: level 8**, because the core risk of this plugin is type coercion of source fields that are
stored as strings and "may hold non-numeric values: never cast blindly" (`LOOP_PROMPT.md` §4).
Level 8 makes every nullable/mixed path explicit. Recorded in `CLAUDE.md` §2.

---

## Q2 — Associate number and Marketplace / Orbitas ID source fields

**Status:** OPEN (to be investigated in Task 1)

The contract has `associate_id` and `marketplace_id`. Per the decisions table they are exposed only
if a real source field exists; otherwise `null` and documented. Task 1 searches user meta **key
names and counts only**.

Safe default until answered: both `null`, with an `issues` code on the property.

---

## Q3 — Media file-existence checks cannot be validated locally

**Status:** OPEN (environment limitation, not a decision)

`wp-content/uploads` is not present locally: every request is redirected (302) to production, and
contacting production is forbidden. Broken-reference detection for media is therefore implemented
but **not verifiable** on the replica. Needs one verification run on a staging copy that has the
real uploads directory before Orbitas relies on the `issues` codes for media.
