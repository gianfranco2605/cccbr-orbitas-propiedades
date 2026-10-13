# Security and privacy review

Carried out on **2026-10-13** against the committed repository and the running local replica. Every
line below is a check that was executed, with its result — nothing is asserted from reading the code
alone.

The endpoint's whole purpose is to hand property data, including the creator's name and email, to an
authenticated and authorized consumer. This review therefore asks two separate questions:

1. **Does it leak anything beyond what the contract promises?** (secrets, other user meta, private
   documents, lead content, raw meta.)
2. **Is the data it does expose properly gated?** (authentication, authorization, no caching, no
   write path.)

---

## 1. Nothing secret is committed

| Check | Command | Result |
|---|---|---|
| Files under version control | `git ls-files` | **51**, none from `vendor/`, no `.sql`, `.sql.gz` or `.env` |
| Secret-like assignments in code, config, tests and the Postman collection | `grep -rnIE "(password\|passwd\|secret\|api[_-]?key\|token\|private[_-]?key)[[:space:]]*[:=][[:space:]]*['\"][^'\"]{6,}"` | **none** |
| The four test credential variables | `grep -rnI "CCCBR_API_"` | appear **only as variable names** in `CLAUDE.md`, `docs/progress.md` and `.claude/settings.json`; **no value anywhere** |
| Personal Claude settings | `git ls-files \| grep settings.local` | **not tracked** (present on disk, ignored) |
| Git history | `git log -p --all` scanned for credential-shaped literals | **0 matches** across 13 commits |
| Debug leftovers in shipped code | `grep -rnIE "\b(var_dump\|print_r\|error_log\|phpinfo)\b" src cccbr-orbitas-propiedades.php uninstall.php` | **none** |

The Postman collection ships with empty `base_url`, `username` and `app_password` variables and an
overview telling the user to put real values in a Postman *environment*, which `.gitignore` excludes
(`/postman/*.postman_environment.json`).

---

## 2. No personal data is committed

| Check | Result |
|---|---|
| Email addresses in any committed file | **none** beyond the plugin author's domain and tooling addresses (`noreply@anthropic.com`, `example.org` in samples) |
| Costa Rican identity-number-shaped digit runs | **none** |
| Phone-number-shaped patterns | **none** |
| A real associate's name | **none**. The only person named anywhere is **Gianfranco Navas**, the developer who owns the repository — named by `LOOP_PROMPT.md` itself, not a data subject from the database |
| Examples in `docs/contract.md` and `docs/consumer-guide.md` | **synthetic**: invented ids, `example.jpg`, public place names. Each document states this at the top |
| Test fixtures | option labels and property vocabulary only (`Casa independiente`, `Piscina`, `Alquiler`…), no record copied from the database |

Every figure in `docs/schema.md`, `docs/reconciliation.md` and `docs/progress.md` is a **count, a key
name, a value *shape* or an identifier**. No personal value was ever read into a document, and the
one third-party export inspected during Task 8 was quoted back only as attachment ids, never as the
author name it contained.

---

## 3. The response allow-list, proven from the code side

Personal data can only enter the response through `Mapper\PeopleMapper`. That is the **only** file in
`src/` that touches a user:

```
src/Mapper/PeopleMapper.php:228   get_user_meta( $author_id, self::ASSOCIATE_META_KEY, true )
src/Mapper/PeopleMapper.php:252   get_userdata( $user_id )
src/Mapper/PeopleMapper.php:260   $user->display_name
src/Mapper/PeopleMapper.php:264   $user->user_email
```

- `ASSOCIATE_META_KEY` is `numero-de-asociado`, and a repository-wide grep confirms it is the
  **only user meta key named anywhere in the plugin**.
- No `$user->data`, no `get_object_vars()`, no loop over user meta: a field added to a CCCBR profile
  tomorrow cannot appear in the response.
- `company` is built without the email branch, so it carries `id` and `display_name` only.

Property meta is read the same way: `PropertyMapper` asks for the keys it knows by name and never
iterates the meta array, so the 40-odd Astra, Elementor, Yoast, WPUF and oEmbed keys documented in
`docs/schema.md` §3.2 have no path into the output.

## 3.1 …and from the response side

A full page of 100 properties was fetched and scanned recursively:

| Check | Result |
|---|---|
| Distinct key names in the payload | **93** |
| Of the 40 forbidden names (`user_pass`, `user_login`, `hash`, `token`, `secret`, `session_tokens`, `wp_capabilities`, `cedula_corredor`, `wasi_token`, `telefono`, `sexo`, `fecha-de-nacimiento`, `foto-del-contrato`, `documentos-anexos`, `leads`, …) | **0 present** |
| `creator` / `responsible` object keys | exactly `id`, `display_name`, `email` on all 3055 properties |
| `company` object keys | exactly `id`, `display_name` — **no email** |
| `private_documents` | exactly two booleans; never a file, URL or attachment id |
| `leads_count` | an integer; no lead field is reachable |
| `marketplace_id` | always `null`; nothing inferred from the Wasi CRM fields, and `wasi_token` is never read |

The same scan is automated as the *Privacy* folder of the Postman collection, so it runs on every
future change.

---

## 4. The data that **is** exposed, and its gate

`creator.display_name` and `creator.email` are personal data, exposed on purpose because the ticket
requires Orbitas to map a property to the associate who created it. The gate:

| Check | Result |
|---|---|
| No credentials | **401** `cccbr_orbitas_not_authenticated` |
| Valid Application Password, no capability | **403** `cccbr_orbitas_forbidden` |
| `permission_callback` | a real callback; `__return_true` appears **nowhere** in the plugin |
| Error bodies | `401`/`403` carry only `code`, `message` and `data.status`; the `400` body does not echo the rejected value back |
| Caching | the response sets `no-store`, which WordPress merges into `no-cache, must-revalidate, max-age=0, no-store, private` |
| Who holds the capability | `wp_user_roles` contains it **once** (the `administrator` role) and **0** users hold it directly in `wp_usermeta` |

The plugin never creates a user or an Application Password, and `docs/OPEN-QUESTIONS.md` Q8 notes —
without acting on it — that the pre-existing public route of the old plugin returns author names
without any authentication.

---

## 5. No write path

A grep across `src/`, the main plugin file and `tools/` for every WordPress and `$wpdb` write
function — `update_post_meta`, `add_post_meta`, `delete_post_meta`, `wp_insert_post`,
`wp_update_post`, `wp_delete_post`, `update_user_meta`, `wp_insert_user`, `wp_update_user`,
`wp_delete_user`, `update_option`, `add_option`, `delete_option`, `wp_set_object_terms`,
`$wpdb->query/insert/update/delete/replace` — returns **nothing**.

The only persistence in the whole plugin is the capability:

```
src/Lifecycle/Activator.php:46   $role->add_cap( Permissions::CAPABILITY )   // activation
uninstall.php:48                 $role->remove_cap( $capability )            // uninstall, every role
```

`uninstall.php` is guarded by `WP_UNINSTALL_PLUGIN` and touches nothing else — no property, no user,
no option, no table. Only a readable method is registered on the route, and `POST` to it returns
**404 `rest_no_route`**.

---

## 6. Injection surface

| Check | Result |
|---|---|
| Superglobals (`$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, `$_SERVER`, `$_FILES`) | **none**; input arrives only as `WP_REST_Request` parameters |
| Argument validation | `per_page`, `after_id` and `status` each have a `validate_callback` **and** are re-validated in `QueryArgs::from_request()` at the point of use |
| SQL | three places assemble SQL text, each with a documented exemption: `PropertyRepository::prepare_with_statuses()`, `RelationsRepository::query()` and `FieldMap`. In all of them the only runtime-built fragment is a run of `%s`/`%d` derived from a **count**, table names are bound with the `%i` identifier placeholder, and every value — statuses and ids included — is bound by `$wpdb->prepare()` |
| Output filtering | `apply_filters( 'the_content' )` appears **nowhere**: `content` is the raw stored value, so no shortcode or page-builder hook can execute while the response is built |
| Outbound HTTP | no `wp_remote_*`, no `curl_*`, no `file_get_contents('http…')` — the plugin never contacts anything |

---

## 7. Residual risks, stated plainly

1. **The payload is personal data.** Each page is up to ~1.5 MB containing creator names and emails.
   It must travel over HTTPS in production (the replica uses plain HTTP only because it is local),
   and the Application Password given to Orbitas should belong to a dedicated account whose sole
   capability is `cccbr_orbitas_read_propiedades`. The plugin cannot enforce either.
2. **`administrator` gets the capability on activation.** That is the decision recorded in
   `CLAUDE.md` §2; it means every existing administrator can read the endpoint. Narrowing it to a
   dedicated role is a one-line change in `Lifecycle\Activator` if CCCBR prefers.
3. **Media file existence is unverified** in this environment (`docs/OPEN-QUESTIONS.md` Q3). It is a
   data-quality gap, not a security one.
4. **Creating the Orbitas credential is explicitly out of scope** and was not done.
