# Changelog

## 2.0.0 — 2026-11-04

This revised 2.0.0 replaces the initial 2.0.0 release from the same day. It uses the simpler criteria API below; the initial separate-parameters API is no longer supported.

- Keep the original CRUD argument positions, with read options third.
- Accept column/value criteria maps with bound equality checks joined by AND; null values use IS NULL.
- Accept `[SQL, ...values]` tuples for custom SQL with positional bindings. Trusted SQL strings run as written.
- Remove `create_placeholders()` and regex extraction. Interpolated user input is not safe; migrate it to maps or tuples.
- Reject empty criteria arrays, blank SQL, unsupported parameter values, and empty insert/update input.
- Database failures throw without printing errors or returning success. Callers handle errors.
- Preserve zeros, booleans, empty strings, and nulls. Bind update values separately from criteria values.
- Require a truthy `raw` option; accept legacy `false` options.

### Migrating from the initial 2.0.0

- Read methods: `($table, $where, $params, $options)` becomes `($table, $criteria, $options)`.
- Update: `($table, $input, $where, $params)` becomes `($table, $input, $criteria)`.
- Delete: `($table, $where, $params)` becomes `($table, $criteria)`.
- Replace SQL plus a separate parameter array with a column/value map or `[SQL, ...values]`. Replace named bindings with maps or positional tuples.
- `init()`, `insert()`, and successful return shapes are unchanged from the initial 2.0.0.
- Tests and the Composer test script are not included in this release.

Existing Composer lockfiles referencing the initial release retain its commit until the dependency is updated. Check that the locked source/dist reference matches the revised `2.0.0` tag; the version number alone does not distinguish these releases.
