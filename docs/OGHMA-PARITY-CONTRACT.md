# Oghma parity contract v1

`oghma-parity-v1` freezes the observable behavior independently implemented by CHIM and ALMSIVI.
Each server owns its PHP implementation, schema, game catalog, and UI; no runtime package or code is shared.

## Retrieval

- Eligible requests are player/conversation input, rechat, continue, instruction, and suggestion.
  Timer, combat, action, and result families do not run deterministic retrieval.
- Input normalization is UTF-8 aware. Canonical topics, reviewed aliases, compact forms, guarded STT
  recovery, speaker-label suppression, generic/homonym guards, ordering, limits, and deduplication are
  deterministic and auditable.
- Exact ordinary creature and object mentions are valid retrieval signals. Generic single-word senses
  still require concrete context.
- Multiword tags are considered only after topic/alias extraction abstains. A tag must have bounded,
  unambiguous ownership; otherwise retrieval records the rejection and abstains.
- A selected connector may be called at most once, only after deterministic abstention on an explicit
  unresolved knowledge request. Its bounded suggestion must resolve to exactly one catalog topic.
- Public outcomes are `grounded`, `no_match`, `fallback_succeeded`, `fallback_unresolved`,
  `fallback_failed`, `fallback_disabled`, `fallback_unconfigured`, `disabled`, `ineligible`, and
  `unavailable`; `not_run` and `legacy` are read-only compatibility states.

## Settings and access

The effective order is Global, then Core Profile, then NPC. Diagnostics record both effective values
and the supplying layer for enablement, topic count, result limit, race/location context, fallback,
fallback timeout, and connector selection.

Knowledge-class negatives are evaluated before positive classes. Advanced access is preferred, then
basic access, then denied. Every packaged basic description carries `common`, which new NPCs receive;
specialist classes grant advanced descriptions. Legacy or custom rows with an empty class remain
unrestricted. `knowall` remains an explicit advanced override. Denied selections are represented as
structured denials rather than fabricated lore.

## Prompt and native Skyrim context

Prompt output is one canonical XML fragment: `<oghma contract="oghma-parity-v1">`, containing ordered
`<article>` elements with either `<content>` or `<denial>`. Existing surrounding CHIM prompt sections
remain unchanged.

HerikaServer intentionally keeps native Skyrim behavior: canonical race aliases, stable plugin/FormID
identity for vanilla races and known locations, location/region/hold hierarchy, and text fallback when
identity is unavailable. Dynamic Oghma remains a separate subsystem.

## Catalog lifecycle and evidence

Factory packages are immutable, versioned, UTF-8 validated, and checksum verified before writes.
Import does not activate. Activation and rollback replace only the factory projection in one database
transaction. User-authored rows, custom collisions, and factory hide choices survive by default.

The frozen fixture, catalog manifest, contract manifest, lifecycle tests, and latency harness are the
review evidence. The harness enforces deterministic p95 below 25 ms on 500 representative extractions;
provider fallback time is reported separately and is never part of that deterministic budget.
