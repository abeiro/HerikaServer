# Shared Oghma canonical knowledge vocabulary v1

Frozen: 2026-08-14

This document defines one semantic vocabulary for ALMSIVI and CHIM while leaving their generators,
runtime code, catalogs, migrations, and validation independent. The byte-identical machine-readable
source is `resources/oghma/canonical-knowledge-vocabulary-v1.json` in each repository.

## Meaning of a knowledge tag

A knowledge tag is an access permission: it says what lore an NPC can plausibly use. It is not a
general biography label. Race, durable organization membership, stable region familiarity, and a
practiced knowledge-bearing occupation may grant tags. Descriptors such as farmer, miner, peasant,
child, servant, prisoner, and animal remain profile facts and do not grant Oghma access.

`common` and `esoteric` are article-only access markers, never NPC tags. A basic article marked
`common` is available to every NPC after negative exclusions are applied. An empty article class
remains unrestricted for backward compatibility. `common` is not valid in an advanced class.
`knowall` is a deliberate advanced override, never an inferred biography tag.

## Shared classes

The products use identical IDs for the same concepts:

- Article markers: common, esoteric.
- Roles: scholar, traveler, mage, alchemist, healer, priest, blacksmith, hunter, merchant, sailor,
  guard, warrior, thief.
- Races: argonian, breton, dunmer, altmer, bosmer, imperial, khajiit, nord, orc, redguard.
- Cultures: daedra, dwemer, skaal.
- Organizations: blades, dark_brotherhood, east_empire_company, imperial_legion, morag_tong,
  thieves_guild, house_hlaalu, house_redoran, house_telvanni.

The organization list is historical identity, not a claim that membership is equally common in both
eras. A product may have zero factory NPC members while still having reviewed articles for the
organization.

## Similar but not equivalent

`mages_guild` and `college_of_winterhold` are different organizations. They must never normalize
to one another. The same rule applies to `fighters_guild` and `companions`. Analogy may be useful
for editorial review, but analogy never grants access or rewrites identity.

## Occupation decision

The shared role classes above are the only cross-product occupation permissions. CHIM additionally
keeps bard, fisher, innkeeper, and noble because its reviewed Skyrim catalog contains useful advanced
articles for each. They are not added to ALMSIVI until Morrowind has reviewed article coverage.

Synonymous jobs translate to the narrowest supported role: apothecary to alchemist, physician to
healer, smith to blacksmith, trader or shopkeeper to merchant, soldier or archer to warrior, and
sorcerer or conjurer to mage. Mixed practiced roles may produce more than one tag. A class name alone
does not justify scholar; scholarship must be explicit.

## Compatibility and frozen GLM boundary

Legacy IDs normalize at access boundaries and during catalog/biography revision. This includes the
old race IDs, compact faction IDs, `smith`, `daedric`, and `skall`. Old `skyrimall`/`common` NPC
values are accepted but ignored during access checks and removed when profiles are revised.
Compatibility is one-way: new data is always written with canonical IDs.

GLM runs only after deterministic audits and article-access matrices pass against this frozen file.
It extracts evidence-bearing occupation, organization, and stable-residence claims, or abstains.
Independent deterministic mappers in each repository make the final tag decision.

For CHIM regions, an evidence-backed GLM region replaces a conflicting legacy region. If GLM
abstains, the existing biography region is preserved; overlapping GLM and legacy regions are
merged. This keeps established location familiarity without restoring known misclassifications.
