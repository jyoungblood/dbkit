# Changelog

## 2.0.0 — 2026-11-04

- **Breaking:** Replace SQL interpolation with explicit positional or named parameters. Removed `create_placeholders()` and regex extraction.
- **Migration:** Read methods use `($table, $where, $params = [], $options = [])`. Move old options to argument four.
- **Migration:** Use `update($table, $input, $where, $params = [])` and `delete($table, $where, $params = [])`.
- Database failures now throw without printing errors or returning success. Callers handle errors.
- Preserve zeros, booleans, empty strings, and nulls. Prevent collisions between update and WHERE bindings.
- Added 25 regression tests and a full migration guide in the README. Empty results and zero-row writes remain successful.

Legacy interpolated SQL does **not** become safe automatically. Keep identifiers and SQL fragments developer-controlled.
