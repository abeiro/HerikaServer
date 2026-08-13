# Skyrim Oghma factory catalog curation

This review applies the conservative parts of the ALMSIVI Morrowind catalog workflow to Skyrim's
factory Oghma data. It keeps ordinary object and creature coverage, removes only deterministic
duplicates and walkthrough-only material, and adds durable lore subjects missing from the catalog.

## Inventory result

- Baseline rows: **1849**
- Final rows: **1807**
- Retired or merged rows: **87**
- Corrected canonical keys: **4**
- Added canonical articles: **45**
- Accepted aliases: **377**
- Rejected alias collisions or canonical variants: **45**

The catalog remains intentionally broad because ordinary creature and object mentions are expected
to retrieve Oghma context. This pass does not remove generic ingredients, equipment, or spells as a
class. It consolidates exact duplicates, shout-word duplicates, potion strength tiers, and dragon-claw
solutions while preserving their spoken names as aliases.

## Editorial rules

- Anchor historical and cultural prose to Skyrim's 4E 201 starting state.
- Exclude puzzle solutions and walkthrough instructions.
- Prefer one canonical article plus aliases over duplicate prose.
- Preserve advanced and basic knowledge tiers.
- Keep aliases collision-free against canonical topics and other aliases.
- Do not automatically overwrite an existing user's database; the bundled catalog is applied only
  to new installs or an explicit Oghma Factory Reset.

## New lore coverage

aedra, aetherius, aldmeri_pantheon, alteration, ancestral_worship, anu, aurbis, azurah, battle_of_moesring, conjuration, daedra, daedra_worship, destruction, disappearance_of_the_dwemer, earthbones, ehlnofey, enchanting, illusion, ka_po_tun, kamal, khajiiti_pantheon, lorkhaj, lunar_lattice, magicka, mundus, necromancy, nordic_pantheon, padomay, potema, redguard_pantheon, restoration, septim_dynasty, soul_gems, tang_mo, tonal_architecture, tsaesci, underking, war_of_the_red_diamond, wild_hunt, wulfharth, zurin_arctus

## Skyrim consolidation articles

dragon_claws, healing_potions, magicka_potions, stamina_potions

## Removed walkthrough-only topics

arkngthamz_puzzle, dimhollow_cavern_puzzle, fahlbtharz_puzzle, ustengrav_puzzle

## Category counts

{
  "": 10,
  "artifacts": 58,
  "creatures": 63,
  "eastmarch": 60,
  "equipment": 235,
  "falkreath": 51,
  "figures": 86,
  "haafingar": 53,
  "hjaalmarch": 37,
  "items": 263,
  "locationother": 50,
  "lore": 237,
  "pale": 47,
  "reach": 64,
  "rift": 81,
  "solstheim": 67,
  "spells": 244,
  "whiterun": 62,
  "winterhold": 39
}

## Review provenance

The topic inventory and stable draft prose for the new lore subjects were adapted from the reviewed
`morrowind-official-3e427-v4` ALMSIVI catalog. 15 setting-sensitive articles were rewritten for the
Skyrim 4E 201 setting. Skyrim-specific consolidation prose was authored for dragon claws and the
three common restorative potion families. No official game book text is copied into this repository.
