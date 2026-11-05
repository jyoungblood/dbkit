# Changelog

## 2.0.1 — 2026-11-04

**Breaking API change:** replace the separate-parameters API from 2.0.0 with simpler criteria maps and SQL tuples. Existing 2.0.0 callers must migrate before upgrading.

- Restore the original CRUD argument positions, with read options third.
- Accept column/value criteria maps with bound equality checks joined by AND; null values use IS NULL.
- Accept `[SQL, ...values]` tuples for custom SQL with positional bindings. Trusted SQL strings still run as written.
- Reject empty criteria arrays and blank SQL. Keep proper PDO binding, value types, and throwing error behavior.
- Accept legacy `false` read options.
- Remove repository tests and the Composer test script.
- Update the README with the new API and migration examples.

### Migrating from 2.0.0

- Read methods: `($table, $where, $params, $options)` becomes `($table, $criteria, $options)`.
- Update: `($table, $input, $where, $params)` becomes `($table, $input, $criteria)`.
- Delete: `($table, $where, $params)` becomes `($table, $criteria)`.
- Replace SQL plus a separate parameter array with a column/value map or `[SQL, ...values]`. Replace named bindings with maps or positional tuples.
- `init()`, `insert()`, and successful return shapes are unchanged.

Packagist does not allow replacing published stable versions. The attempted replacement of 2.0.0 was blocked there; this API is published as 2.0.1, and the original 2.0.0 tag/release is restored.

## 2.0.0 — 2026-11-04

- **Breaking:** Replace SQL interpolation with explicit positional or named parameters. Removed `create_placeholders()` and regex extraction.
- **Migration:** Read methods use `($table, $where, $params = [], $options = [])`. Move old options to argument four.
- **Migration:** Use `update($table, $input, $where, $params = [])` and `delete($table, $where, $params = [])`.
- Database failures now throw without printing errors or returning success. Callers handle errors.
- Preserve zeros, booleans, empty strings, and nulls. Prevent collisions between update and WHERE bindings.
- Added 25 regression tests and a full migration guide in the README. Empty results and zero-row writes remain successful.

Legacy interpolated SQL does **not** become safe automatically. Keep identifiers and SQL fragments developer-controlled.
