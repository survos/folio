# survos/folio

Framework-free SQLite folio metadata access. Requires PDO SQLite and the shared
`survos/data-contracts` metadata value types; no Symfony or Doctrine dependency.

`PropertyStore::read()` returns provenance-bearing values from new folios and
legacy descriptive columns. Reading performs no schema writes. `values()` returns
the decoded values for catalog projections. `migrate()` converts metadata in one
transaction without changing records, pages, FTS or legacy columns. `put()` is a
low-level persistence operation; ownership-aware edits use the bundle's Folio API.

Open existing files with SQLite `mode=ro` for inspection. Only open writable files
when explicitly migrating. Metadata format 2 is distinct from SQLite user_version.

The Symfony bundle supplies entity hydration, property accessors, build integration,
commands and schema management. Further row/query extraction can happen independently
of this metadata migration.

## Page types

`Survos\Folio\Enum\PageType` is the shared page imagery/medium contract: photo,
scan, document, audio, video and other. Producers use enum cases in memory and
serialize their backed values at JSON/SQL boundaries. Consumers validate wire
values with `PageType::from()`; object content genres are not page types.
The legacy `Survos\FolioBundle\Enum\PageType` name remains an alias in the bundle.

## Distribution

Published as `survos/folio` by the mono package-split workflow. Consumers should
install the released Composer package; local source links alone do not register
its `Survos\Folio\` namespace with Composer.
