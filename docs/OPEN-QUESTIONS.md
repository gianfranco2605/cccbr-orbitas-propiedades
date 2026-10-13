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

**Status:** ANSWERED by measurement in Task 1 (13 Oct 2026) — see `docs/schema.md` §8.1–8.2

- **Associate number: it exists.** User meta key `numero-de-asociado`, on 1623 users, defined as a
  `text` field in the JetEngine user meta box "CorredorUserBOX". Exposed as `associate_id`.
- **Marketplace / Orbitas ID: it does not exist.** A key-name search over all of `wp_usermeta` and
  `wp_postmeta` for `%market%`, `%orbit%`, `%azure%`, `%crm%`, `%extern%` returns nothing. The only
  third-party identifiers are Wasi CRM's (`wasi`, `wasi_company`, `wasi_token`) — a different
  system, and `wasi_token` is a credential. `marketplace_id` is therefore **always `null`**.
  Nothing is inferred from `wasi`. It carries **no** per-property issue code: an issue identical on
  all 3055 records would be noise, so the absence is documented in `docs/contract.md` §4.10 instead.

**Needs confirmation:** if Orbitas expects a Marketplace id to round-trip, someone has to create and
populate that field in WordPress first. This plugin will not invent one.

---

## Q4 — `associate_id` is user meta, and the decisions table bans "any other user meta"

**Status:** OPEN — decision applied, confirmation wanted

`LOOP_PROMPT.md` §2.3 says user data is limited to "user ID, display name, email … never … any
other user meta". The same table also says the associate id is exposed "only if a real source field
is found", and the ticket requires "the link to the associate ID" for mapping. `numero-de-asociado`
is user meta, so the two rules collide.

**Applied:** `associate_id` is exposed from `numero-de-asociado`, treated as an explicitly
allow-listed organisational identifier rather than "other user meta" — it is the field the ticket
asks for, and without it Orbitas cannot map a property to a CCCBR associate. No other user meta key
is read: no national ID (`cedula_corredor`), phone, birth date, payment status or Wasi credential.

**If this is not wanted:** `associate_id` becomes `null` with an issue code; one line in
`Mapper\PeopleMapper` and one row in `docs/contract.md`.

---

## Q5 — the shape of numeric fields

**Status:** ANSWERED by Gianfranco in change request 1 (13 Oct 2026)

**Decision: plain values.** Every numeric field — the three prices and all seven features — is a
number or `null`. There is no `{ raw, value }` wrapper.

When a non-empty source value cannot be parsed, the field is `null` and the **original string
travels in the issue's `detail`** (`number_ambiguous` or `number_unparsable`). That is now the only
place a raw source value appears in the response.

Why this is the right trade: the wrapper put an object on 10 fields of all 3055 properties — 30 550
objects — to carry information that only **436** values actually needed, and Orbitas has not
validated the contract yet, so the payload should start lean. Nothing is lost: the raw string is
still delivered, at the point where the problem is reported.

Implemented in 0.2.0 (`Mapper\PropertyMapper`, `Rest\PropertySchema`, `docs/contract.md` §4.3–4.5).
Superseded the earlier "decision applied, confirmation wanted" from Task 6.

---

## Q6 — Origin of the 540 properties without `_wpuf_form_id`

**Status:** OPEN (data limitation, not a decision)

2515 properties carry `_wpuf_form_id` (2512 of them form 9536, "PublicarPropiedades"). The other
**540** (539 created in 2026) carry **no origin marker at all**: there is no JetFormBuilder post
meta on the CPT and `wp_jet_fb_records` is empty.

`LOOP_PROMPT.md` §4 says recent properties come from the JetFormBuilder form "Publicar Propiedad
CCCBR". That is plausible but **not recorded in the data**, so `origin.source` reports `unknown`
for those 540 rather than claiming JetFormBuilder. If CCCBR can confirm the form, the audit trail
can be improved — but only by writing new meta, which is out of scope.

---

## Q7 — 108 of 499 geocoded properties hold a JetEngine geocoding error marker

**Status:** OPEN (data quality, no decision needed to proceed)

`coordinates` comes from JetEngine's geocode cache for the `mapa` field
(`md5('mapa')_lat/_lng/_hash`). Measured: **499** properties carry the cache, of which **391** have
numeric coordinates and **108 hold the error marker `je-coord-error…`** (geocoding failed or
returned nothing). A further **7** have a `_hash` that no longer matches the current `mapa` value
(stale cache); 5 of those 7 also hold an error marker, so only **2** are returned with coordinates
plus a staleness warning.

**Applied:** error markers → `coordinates: null` + `coordinates_geocode_error`; stale cache →
coordinates returned + `coordinates_stale`. Re-geocoding is out of scope and would touch another
plugin's data.

---

## Q3 — Media file-existence checks cannot be validated locally

**Status:** OPEN (environment limitation, not a decision)

`wp-content/uploads` exists locally but holds **no media** — only 28 plugin-generated files — and
every media request is redirected (302) to production, which is off limits. So the plugin cannot
tell a deleted file from an absent library here.

**Applied:** `media_file_missing` is implemented but **runs only where
`wp_get_environment_type()` is not `local`**, so on the replica it reports nothing rather than
reporting every file as missing. The flag is injected into `Mapper\MediaMapper`, so both branches
are unit-testable and the decision is visible at the composition root.

**Still needed:** one verification run on a staging copy that has the real uploads directory, before
Orbitas relies on `media_file_missing`. Note that **broken attachment *references* are already
verified** (301 ids, Task 8) — that check needs no files, only `wp_posts`.

---

## Q8 — The existing public endpoint exposes associate full names

**Status:** OPEN — observation only, **outside this ticket's scope**

While cross-checking our output against a run of the old plugin
(`cccbr-api-propiedades`, public route `cccbr/v1/propiedades`), its payload was seen to include the
property author's **full name** in clear text, on a route that needs no authentication.

That plugin is explicitly **not** to be modified, reused or deactivated by this work, and nothing
was changed about it. It is recorded here only because the ticket is partly about data protection:
CCCBR may want to review whether an unauthenticated route should return personal names, since the
new endpoint deliberately requires an Application Password plus a dedicated capability for the same
class of data.

No action taken, and none proposed inside this task.
