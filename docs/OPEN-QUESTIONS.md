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
  system, and `wasi_token` is a credential. `marketplace_id` is therefore **always `null`**, with an
  `issues` code. Nothing is inferred from `wasi`.

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

## Q5 — `{ raw, value }` shape for every numeric field

**Status:** OPEN — decision applied, confirmation wanted

The contract in `LOOP_PROMPT.md` §6 promises plain `number`-or-`null` for `bedrooms`, `bathrooms`,
`garage`, `floors`, and "raw text + parsed number only when unambiguous" for `land_area`,
`built_area`, `year_built`.

Measurement (see `docs/schema.md` §2.4) shows **all ten** numeric-ish source fields contain
non-numeric values, including the four `number`-typed ones, and that values like `150.000` /
`150,000` are genuinely ambiguous (thousands separator vs decimal).

**Applied:** every numeric-ish field — the three prices and all seven features — is exposed as
`{ "raw": "<stored string>", "value": <number|null> }`. A number is produced only for `^-?\d+$`
and `^-?\d+\.\d{1,2}$`; ambiguous and unparsable values give `value: null` plus an `issues` code,
with the raw string always preserved.

**If Orbitas wants flat numbers instead**, they lose the **436** values that cannot be parsed
safely (329 ambiguous + 107 unparsable, measured across the ten fields). That is an import
decision, not a technical one.

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

## Q7 — 107 of 498 geocoded properties hold a JetEngine geocoding error marker

**Status:** OPEN (data quality, no decision needed to proceed)

`coordinates` comes from JetEngine's geocode cache for the `mapa` field
(`md5('mapa')_lat/_lng/_hash`). Measured: 391 properties have numeric coordinates, **107 hold the
error marker `je-coord-error…`** (geocoding failed or returned nothing), and **7** have coordinates
whose `_hash` no longer matches the current `mapa` value (stale cache).

**Applied:** error markers → `coordinates: null` + `coordinates_geocode_error`; stale cache →
coordinates returned + `coordinates_stale`. Re-geocoding is out of scope and would touch another
plugin's data.

---

## Q3 — Media file-existence checks cannot be validated locally

**Status:** OPEN (environment limitation, not a decision)

`wp-content/uploads` is not present locally: every request is redirected (302) to production, and
contacting production is forbidden. Broken-reference detection for media is therefore implemented
but **not verifiable** on the replica. Needs one verification run on a staging copy that has the
real uploads directory before Orbitas relies on the `issues` codes for media.
