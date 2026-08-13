# Skyrim Oghma factory catalog curation

This review applies the conservative parts of the ALMSIVI Morrowind catalog workflow to Skyrim's
factory Oghma data. It keeps ordinary object and creature coverage, removes only deterministic
duplicates and walkthrough-only material, replaces repetitive equipment and bottled-effect templates
with useful family prose, and adds durable lore subjects missing from the catalog.

## Inventory result

- Baseline rows: **1849**
- Final rows: **1562**
- Retired or merged rows: **373**
- Corrected canonical keys: **7**
- Added canonical articles: **86**
- Accepted aliases: **819**
- Rejected alias collisions or canonical variants: **58**
- Knowledge-class corrections: **11**
- Missing categories restored: **8**
- Missing basic descriptions restored: **2**
- Equipment variants consolidated: **237** into **29** families
- Ingredient articles retaining explicit gameplay effects: **169**
- Distinctive named concoctions retained unchanged: **23**

The catalog remains intentionally broad because ordinary creature and object mentions are expected
to retrieve Oghma context. Every retired equipment or generic potion/poison name remains an alias of
a reviewed family article. Ingredient entries and their explicit `Alchemy effects:` lists remain
canonical and unchanged. Distinctive quest, regional, supernatural, and named concoctions also remain
individual articles.

## Editorial rules

- Anchor historical and cultural prose to Skyrim's 4E 201 starting state.
- Exclude puzzle solutions and walkthrough instructions.
- Prefer one canonical article plus aliases over duplicate prose.
- Preserve advanced and basic knowledge tiers.
- Preserve ingredient lists and gameplay effects rather than flattening alchemy ingredients.
- Keep distinctive named concoctions separate from mass-produced bottled effects.
- Keep aliases collision-free against canonical topics and other aliases.
- Accept only established alternate names and spellings as aliases; leave ordinary language and
  speculative STT variants to guarded fuzzy matching.
- Leave the legacy `tags` field empty until it has a defined namespaced vocabulary and a retrieval
  role; aliases and categories are the current retrieval metadata.
- Do not automatically overwrite an existing user's database; the bundled catalog is applied only
  to new installs or an explicit Oghma Factory Reset.

## New lore coverage

aedra, aetherius, aldmeri_pantheon, alteration, ancestral_worship, anu, aurbis, azurah, battle_of_moesring, conjuration, daedra, daedra_worship, destruction, disappearance_of_the_dwemer, earthbones, ehlnofey, enchanting, illusion, ka_po_tun, kamal, khajiiti_pantheon, lorkhaj, lunar_lattice, magicka, mundus, necromancy, nordic_pantheon, padomay, potema, redguard_pantheon, restoration, septim_dynasty, soul_gems, tang_mo, tonal_architecture, tsaesci, underking, war_of_the_red_diamond, wild_hunt, wulfharth, zurin_arctus

## Skyrim consolidation articles

blackreach, dragon_claws, healing_potions, magicka_potions, stamina_potions

## Equipment families

improved_bonemold_equipment, ancient_nord_equipment, banded_iron_equipment, bonemold_equipment, chitin_equipment, daedric_equipment, dawnguard_equipment, dragonbone_equipment, dragonplate_equipment, dragonscale_equipment, dwarven_equipment, ebony_equipment, elven_equipment, falmer_equipment, forsworn_equipment, fur_equipment, glass_equipment, hide_equipment, iron_equipment, leather_equipment, nordic_equipment, orcish_equipment, scaled_equipment, stalhrim_light_equipment, stalhrim_equipment, steel_plate_equipment, steel_equipment, thalmor_equipment, vampire_equipment

## Generic potion and poison families

skill_potions, regeneration_potions, resistance_potions, utility_potions, vitality_potions, curative_potions, damaging_poisons, magicka_poisons, stamina_poisons, control_poisons, aversion_poisons

## Distinctive concoctions retained unchanged

disguised_invisibility_potion, esberns_potion, falmer_blood_elixir, frostbite_venom, ice_wraith_bane, ice_wraith_essence, lotus_extract, mystic_venom, nightshade_extract, philter_of_the_phantom, potion_of_blood, potion_of_conflict, potion_of_escape, potion_of_keenshot, potion_of_larceny, potion_of_plunder, potion_of_waterwalking, potion_of_well_being, soul_husk_extract, stallions_potion, unknown_potion, vaerminas_torpor, white_phial

## Removed walkthrough-only topics

arkngthamz_puzzle, dimhollow_cavern_puzzle, fahlbtharz_puzzle, ustengrav_puzzle

## Category counts

{
  "artifacts": 59,
  "creatures": 63,
  "eastmarch": 60,
  "equipment": 29,
  "falkreath": 51,
  "figures": 86,
  "haafingar": 53,
  "hjaalmarch": 37,
  "items": 225,
  "locationother": 51,
  "lore": 237,
  "pale": 47,
  "reach": 64,
  "rift": 81,
  "solstheim": 67,
  "spells": 244,
  "whiterun": 69,
  "winterhold": 39
}

## Review provenance

The topic inventory and stable draft prose for the new lore subjects were adapted from the reviewed
`morrowind-official-3e427-v4` ALMSIVI catalog. 15 setting-sensitive articles were rewritten for the
Skyrim 4E 201 setting. Skyrim-specific consolidation prose was authored for dragon claws, restorative
potions, equipment families, and generic bottled effects. Alternate-name aliases were cross-checked
against that reviewed catalog and explicit names in Skyrim's existing article prose. No official game
book text is copied into this repository.
