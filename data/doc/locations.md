# Skyrim Location Geoposition Problem

## Overview

In my mod, I track Skyrim locations using in-game data collected through a custom DLL (CHIM). The DLL extracts location information from the game and stores it in a PostgreSQL database.

The database contains a table with all known Skyrim locations. Relevant fields include:

* `location_name`
* `formid`
* `is_interior`

(Other columns exist but are not relevant to this discussion.)

The goal is to use this location database to inject nearby or contextually relevant locations into an AI system that controls NPC behavior. To do this reliably, I need an accurate way to determine the geographical representation and context of each location.

The difficulty is that Skyrim's location system is not consistent enough to map every location to a single, well-defined point.

---

# Problem Description

A Skyrim location can contain multiple marker and reference types, and those references do not always represent the same concept.

A location may:

1. Have a map marker located in an exterior cell.
2. Have a map marker located in a cell that is not part of the main worldspace.
3. Have a `LocationCenterMarker`.
4. Have multiple entrance or reference markers, often located in different cells.

The reference types currently available are:

```cpp
auto insideEntranceMarkerRefType =
    RE::TESForm::LookupByID<RE::BGSLocationRefType>(0x000130fc);

auto locationCenterRefType =
    RE::TESForm::LookupByID<RE::BGSLocationRefType>(0x0001bdf1);

auto outsideEntranceMarkerRefType =
    RE::TESForm::LookupByID<RE::BGSLocationRefType>(0x000130fb);

auto mapMarkerRefType =
    RE::TESForm::LookupByID<RE::BGSLocationRefType>(0x00010f63c);
```

These references frequently disagree about what should be considered the "real" position of a location.

---

# Different Location Cases

## Case 1: Exterior Locations

Some locations have their map marker placed in an exterior cell, such as:

* Wilderness areas.
* Locations placed directly in the main worldspace.

These are relatively straightforward because the map marker closely represents the physical location.

---

## Case 2: Map Markers Outside the Main Worldspace

Some locations have their map marker in a cell that is not part of the main worldspace.

Examples include cities or areas implemented using their own worldspaces.

In these cases, the map marker does not necessarily represent where an NPC actually enters or interacts with the location.

---

## Case 3: Location Center Marker

Some locations define a `LocationCenterMarker`.

Although this marker is intended to represent the center of the location, it can be misleading because:

* It may exist inside an interior cell.
* It may not correspond to the exterior entrance.
* It can cause an otherwise exterior location to be classified as an interior location.

---

## Case 4: Multiple Markers

Many locations contain several references, including:

* Inside entrance markers.
* Outside entrance markers.
* Location center markers.
* Map markers.

These references are often located in different cells and may represent different aspects of the same location.

This creates ambiguity when trying to determine which coordinate should represent the location.

---

# Main Issue

The primary question is:

**When should a location be considered an interior location versus an exterior location?**

The current database model assumes each location has a single identity:

```
Location
 ├── name
 ├── formid
 └── is_interior
```

However, this assumption does not match Skyrim's data model.

A single location may simultaneously have:

* An exterior entrance.
* An interior entrance.
* An interior center marker.
* A world map marker.

Each of these may represent a different physical position while still belonging to the same gameplay location.

---

# Duplicate Location Entries (by name)

## Whistling Mine
Example:

```
Whistling Mine

Entry 1
- FormID: XXXXA
- Type: Exterior
- Position: Outside entrance marker

Entry 2
- FormID: XXXXB
- Type: Interior
- Position: Inside entrance marker
```

This allows the AI system to choose the most appropriate representation depending on context.

Whistling Mine is a good example of this model. Skyrim already defines two distinct locations:

* One representing the exterior mine entrance.
* One representing the mine interior.

These locations have different FormIDs, allowing them to be treated independently. One is naturally classified as exterior, while the other is interior.

---

## Heartwood Mill

Heartwood Mill illustrates a more problematic case.

There are two separate location records, but they share the same name, different formid

In addition, both locations reference some of the same markers.

For example:

```
LocationCenterMarker (0x0001bdf1)
Reference: 0x000226b8
```

That same reference is also used as the map marker of the other location.

With the current import logic, a location is classified as interior if **any** of its references point to an interior cell.

As a result:

* Both locations are imported into the database.
* Both are classified as interior.
* This classification is incorrect because one should represent the exterior area.

This suggests that the current rule for determining whether a location is interior may be too simplistic.

It is also possible that some Skyrim locations should not be classified as strictly interior or exterior. Instead, they naturally span both interior and exterior cells.

---

# Open Questions

## 1. Should locations be duplicated?

When a location contains both interior and exterior representations, should the database create two logical entries?

Some implementation considerations:

* `formid` is currently the primary key. If locations are duplicated, the primary key may need to become `(formid, location_name)` or another composite identifier.
* `is_interior` could be replaced with an `interiors` field storing the number of references located in interior cells.
* The plugin only sends each Skyrim location once. Any duplication would therefore need to happen during the server-side import process.
* Imported locations are processed independently. If duplication is performed, the importer must also handle the possibility that another location with the same name is imported later.

## 2. How should the AI distinguish duplicated locations?

The tracking system reports only the current location FormID.

However, it also knows whether the NPC is currently in an interior or exterior cell.

For example:

* An NPC at Bleak Falls Barrow always reports the location "Bleak Falls Barrow".
* If the NPC is inside the dungeon, `is_interior = 1`.
* If the NPC is outside the entrance, `is_interior = 0`.

This additional information could be used to select the correct logical representation of the location.

## 3. How should locations be classified?

Should classification depend on:

* Map marker position?
* Cell type?
* Presence of entrance markers?
* Presence of references in interior cells?
* NPC current cell (when available)?
* A combination of all of the above?

## 4. How should inconsistent locations such as Heartwood Mill be handled?

Some locations appear to contain inconsistent or contradictory data. The import process should define a deterministic strategy for handling these cases.

---

# Conclusion

Skyrim's location system does not consistently represent locations as single geographical points. A location may contain multiple references, span both interior and exterior cells, and even contain internally inconsistent data.

Because of this, a simple "one location equals one coordinate" model is insufficient for AI-driven NPC navigation and contextual reasoning.

The solution will likely require storing richer information about each location—including all relevant references—and selecting the most appropriate representation dynamically based on the NPC's current context, particularly whether it is currently inside or outside the location.



# Update 1.

We have enriched information sent from DLL:

SELECT
    name,formid,region,hold,

    CASE (is_interior & 3)
        WHEN 0 THEN 'Exterior'
        WHEN 1 THEN 'Interior'
        WHEN 2 THEN 'Missing'
    END AS inside_entrance,

    CASE ((is_interior>>2) & 3)
        WHEN 0 THEN 'Exterior'
        WHEN 1 THEN 'Interior'
        WHEN 2 THEN 'Missing'
    END AS location_center,

    CASE ((is_interior>>4) & 3)
        WHEN 0 THEN 'Exterior'
        WHEN 1 THEN 'Interior'
        WHEN 2 THEN 'Missing'
    END AS raw_location_marker,

    CASE ((is_interior>>6) & 3)
        WHEN 0 THEN 'Exterior'
        WHEN 1 THEN 'Interior'
        WHEN 2 THEN 'Missing'
    END AS outside_entrance

FROM public.locations

is_interior now represents a bitwise obtained result:

| Value | Meaning                        |
| ----: | ------------------------------ |
|  `00` | Exists, exterior               |
|  `01` | Exists, interior               |
|  `10` | Doesn't exist                  |
|  `11` | Reserved (or treat as invalid) |


| Bits | Field                      |
| ---- | -------------------------- |
| 0-1  | `inside_entrance`                |
| 2-3  | `location_center`  |
| 4-5  | `raw_location_marker`        |
| 6-7  | `outside_entrance` |

Locations obtained:

"name","formid","region","hold","inside_entrance","location_center","raw_location_marker","outside_entrance"
"Hollyfrost Farm","94299","Hollyfrost Farm","Eastmarch","Missing","Interior","Exterior","Missing"
"Northwind Mine","1039327","The Rift","Tamriel","Interior","Interior","Exterior","Exterior"
"Arcwind Point","1037957","The Rift","Tamriel","Missing","Missing","Exterior","Missing"
"Shrine of Boethiah","1006503","Eastmarch","Tamriel","Missing","Exterior","Exterior","Missing"
"Boulderfall Cave","1004250","The Rift","Tamriel","Missing","Missing","Exterior","Missing"
"Hunter's Rest","1004208","Falkreath","Tamriel","Missing","Missing","Exterior","Missing"
"Journeyman's Nook","980300","Winterhold","Tamriel","Missing","Missing","Exterior","Missing"
"Ironback Hideout","980294","Haafingar","Tamriel","Missing","Exterior","Exterior","Missing"
"Pinefrost Tower","979132","Haafingar","Tamriel","Missing","Missing","Exterior","Missing"
"Skytemple Ruins","979120","Winterhold","Tamriel","Missing","Missing","Exterior","Missing"
"Widow's Watch Ruins","979113","Haafingar","Tamriel","Missing","Exterior","Exterior","Missing"
"Hamvir's Rest","979106","Whiterun","Tamriel","Missing","Missing","Exterior","Missing"
"Mara's Eye Pond","970579","Eastmarch","Tamriel","Missing","Interior","Exterior","Exterior"
"Riverside Shack","970573","Eastmarch","Tamriel","Missing","Missing","Exterior","Missing"
"Abandoned Prison","970572","Eastmarch","Tamriel","Missing","Interior","Exterior","Missing"
"Honningbrew Meadery","895880","Whiterun","Whiterun","Missing","Missing","Missing","Missing"
"Anise's Cabin","890167","Falkreath","Tamriel","Missing","Missing","Exterior","Missing"
"Hall of the Vigilant","799789","the Pale","Tamriel","Interior","Interior","Exterior","Exterior"
"Yorgrim Overlook","799784","the Pale","Tamriel","Missing","Missing","Exterior","Missing"
"Wayward Pass","798460","Winterhold","Tamriel","Missing","Missing","Exterior","Missing"
"Snowpoint Beacon","798456","Winterhold","Tamriel","Missing","Exterior","Exterior","Missing"
"Kjenstag Ruins","798449","Hjaalmarch","Tamriel","Missing","Missing","Exterior","Missing"
"Windward Ruins","798445","the Pale","Tamriel","Missing","Missing","Exterior","Missing"
"Traitor's Post","798439","Eastmarch","Tamriel","Missing","Exterior","Exterior","Missing"
"Brandy-Mug Farm","781806","Brandy-Mug Farm","Eastmarch","Missing","Interior","Exterior","Missing"
"Weynon Stones","770220","the Pale","Tamriel","Missing","Missing","Exterior","Missing"
"Cold Rock Pass","770211","Hjaalmarch","Tamriel","Missing","Interior","Missing","Missing"
"Four Skull Lookout","730028","The Reach","Tamriel","Missing","Exterior","Exterior","Missing"
"Reachwind Eyrie","730021","The Reach","Tamriel","Missing","Missing","Exterior","Missing"
"Cradle Stone Tower","730015","The Reach","Tamriel","Missing","Exterior","Exterior","Exterior"
"Dragon Bridge Overlook","730005","The Reach","Tamriel","Missing","Exterior","Exterior","Missing"
"Deep Folk Crossing","730001","The Reach","Tamriel","Missing","Missing","Exterior","Missing"
"Cliffside Retreat","729997","The Reach","Tamriel","Missing","Missing","Exterior","Missing"
"Black-Briar Lodge","416863","Riften","The Rift","Missing","Missing","Missing","Missing"
"Calcelmo's Laboratory","380734","Markarth","The Reach","Missing","Missing","Exterior","Missing"
"Kagrenzel","144366","Eastmarch","Tamriel","Missing","Missing","Exterior","Exterior"
"Drelas Cottage","943787","Whiterun","Tamriel","Missing","Missing","Exterior","Missing"
"Crab Shack","943785","Hjaalmarch","Tamriel","Missing","Missing","Exterior","Missing"
"Stony Creek Cave","528168","Eastmarch","Tamriel","Interior","Interior","Exterior","Exterior"
"Angi's Camp","1006498","Falkreath","Tamriel","Missing","Missing","Exterior","Missing"
"Shrine of Peryite","1006501","The Reach","Tamriel","Missing","Missing","Exterior","Missing"
"Lund's Hut","943781","Whiterun","Tamriel","Missing","Missing","Exterior","Missing"
"Whitewatch Tower","943776","Whiterun","Tamriel","Missing","Missing","Exterior","Missing"
"The Steed Doomstone","874109","Haafingar","Tamriel","Missing","Missing","Exterior","Missing"
"The Shadow Doomstone","874108","The Rift","Tamriel","Missing","Missing","Exterior","Missing"
"The Serpent Doomstone","874107","Winterhold","Tamriel","Missing","Missing","Exterior","Missing"
"The Ritual Doomstone","874106","Whiterun","Tamriel","Missing","Missing","Exterior","Missing"
"The Lord Doomstone","874103","the Pale","Tamriel","Missing","Missing","Exterior","Missing"
"The Atronach Doomstone","874101","Eastmarch","Tamriel","Missing","Missing","Exterior","Missing"
"The Apprentice Doomstone","874099","Hjaalmarch","Tamriel","Missing","Missing","Exterior","Missing"
"The Thief Doomstone","874097","The Rift","Tamriel","Missing","Missing","Exterior","Missing"
"Bthalft","874095","The Rift","Tamriel","Missing","Missing","Exterior","Missing"
"Alchemist's Shack","874093","The Rift","Tamriel","Missing","Missing","Exterior","Missing"
"Lost Prospect Mine","874091","The Rift","Tamriel","Missing","Missing","Exterior","Missing"
"Shor's Watchtower","874089","The Rift","Tamriel","Missing","Missing","Exterior","Missing"
"Rkund","874087","The Rift","Tamriel","Missing","Missing","Exterior","Missing"
"Rift Watchtower","874085","The Rift","Tamriel","Missing","Exterior","Exterior","Missing"
"Arch Mage's Quarters","857830","Winterhold College","Winterhold","Missing","Missing","Interior","Missing"
"Shriekwind Bastion","658992","Falkreath","Tamriel","Interior","Interior","Exterior","Exterior"
"Sunderstone Gorge","400099","Falkreath","Tamriel","Interior","Interior","Exterior","Exterior"
"Bloodlet Throne","94085","Falkreath","Tamriel","Missing","Interior","Exterior","Missing"
"North Skybound Watch","308857","Falkreath","Tamriel","Missing","Missing","Exterior","Missing"
"Falkreath Watchtower","293299","Falkreath","Tamriel","Missing","Missing","Exterior","Missing"
"Peak's Shade Tower","292998","Falkreath","Tamriel","Missing","Exterior","Exterior","Missing"
"Roadside Ruins","292987","Falkreath","Tamriel","Missing","Exterior","Exterior","Missing"
"Bannermist Tower","292901","Falkreath","Tamriel","Missing","Exterior","Exterior","Missing"
"Knifepoint Ridge","582071","Falkreath","Tamriel","Missing","Exterior","Exterior","Exterior"
"Dainty Sload","506138","Haafingar","Tamriel","Missing","Missing","Exterior","Missing"
"Brinewater Grotto","390184","Haafingar","Tamriel","Missing","Missing","Exterior","Missing"
"Deekus Camp","358769","Winterhold","Tamriel","Missing","Missing","Exterior","Missing"
"Abandoned Shack","337227","Hjaalmarch","Tamriel","Missing","Missing","Exterior","Missing"
"Riften Grand Plaza","290325","Riften","The Rift","Missing","Missing","Exterior","Missing"
"Chillfurrow Farm Interior","241642","Chillfurrow Farm","Whiterun","Missing","Interior","Exterior","Missing"
"Battle-Born Farm","241640","Battle-Born Farm","Whiterun","Missing","Interior","Exterior","Missing"
"Solitude","219320","Solitude","Haafingar","Missing","Missing","Exterior","Missing"
"Solitude","219319","Solitude","Haafingar","Missing","Missing","Exterior","Missing"
"Soljund's Sinkhole","198042","Soljund's Sinkhole","The Reach","Interior","Interior","Interior","Missing"
"Miner's House","198039","Soljund's Sinkhole","The Reach","Missing","Interior","Interior","Missing"
"The Lady Doomstone","874102","Falkreath","Tamriel","Missing","Missing","Exterior","Missing"
"Froki's Shack","874083","The Rift","Tamriel","Missing","Missing","Exterior","Missing"
"Ironbind Barrow","217276","Winterhold","Tamriel","Interior","Missing","Missing","Missing"
"Solitude","219321","Solitude","Haafingar","Missing","Missing","Exterior","Missing"
"The Midden","179435","Winterhold College","Winterhold","Missing","Interior","Interior","Missing"
"Shrine of Mehrunes Dagon","147686","the Pale","Tamriel","Missing","Missing","Missing","Missing"
"Klimmek's House","140964","Ivarstead","The Rift","Missing","Interior","Interior","Missing"
"Solitude Lighthouse","131228","","Haafingar","Missing","Interior","Interior","Missing"
"Proudspire Manor","131224","Solitude","Solitude","Missing","Interior","Interior","Missing"
"Evette San's House","131218","Solitude","Solitude","Missing","Interior","Interior","Missing"
"Erikur's House","131216","Solitude","Solitude","Missing","Interior","Interior","Missing"
"East Empire Company Warehouse","131214","Haafingar","Tamriel","Missing","Exterior","Exterior","Missing"
"Bryling's House","131208","Solitude","Solitude","Missing","Interior","Interior","Missing"
"Dragon Bridge Lumber Camp","131192","Dragon Bridge","Haafingar","Missing","Interior","Interior","Missing"
"Horgeir's House","131190","Dragon Bridge","Haafingar","Missing","Interior","Interior","Missing"
"Dragon Bridge Garrison","131188","Dragon Bridge","Haafingar","Missing","Interior","Interior","Missing"
"Rorik's Manor","129157","Rorikstead","Whiterun","Missing","Interior","Interior","Missing"
"Lemkil's Farmhouse","129156","Rorikstead","Whiterun","Missing","Interior","Exterior","Missing"
"Cowflop Farmhouse","129154","Rorikstead","Whiterun","Missing","Interior","Interior","Missing"
"Ysolda's House","129153","Whiterun","Whiterun","Missing","Interior","Interior","Missing"
"Whiterun Stables","129148","Whiterun","Whiterun","Missing","Interior","Interior","Missing"
"Olava the Feebles House","129145","Whiterun","Whiterun","Missing","Interior","Interior","Missing"
"Heimskr's House","129139","Whiterun","Whiterun","Missing","Interior","Interior","Missing"
"Carlotta Valentia's House","129136","Whiterun","Whiterun","Missing","Interior","Interior","Missing"
"The Ravens Breezehome","129135","Whiterun","Whiterun","Missing","Interior","Interior","Missing"
"Vittoria Vici's House","131222","Solitude","Solitude","Missing","Interior","Interior","Missing"
"Castle Fletcher","131220","Solitude","Solitude","Missing","Interior","Interior","Missing"
"Lylvieve Family's House","131078","Dragon Bridge","Haafingar","Missing","Interior","Interior","Missing"
"Addvar's House","131198","Solitude","Solitude","Missing","Interior","Interior","Missing"
"Temple of Kynareth","129149","Whiterun","Whiterun","Missing","Interior","Interior","Missing"
"Septimus Signus' Outpost","185628","Winterhold","Tamriel","Missing","Interior","Exterior","Missing"
"The Blue Palace","131206","Solitude","Solitude","Missing","Interior","Interior","Missing"
"Dragonsreach","129137","Whiterun","Whiterun","Missing","Interior","Interior","Missing"
"House of Clan Battle-Born","129141","Whiterun","Whiterun","Missing","Interior","Interior","Missing"
"Radiant Raiments","131230","Solitude","Solitude","Missing","Interior","Interior","Missing"
"Jala's House","131226","Solitude","Solitude","Missing","Interior","Interior","Missing"
"Fellstar Farm","140967","Ivarstead","The Rift","Missing","Interior","Interior","Missing"
"Bits and Pieces","131204","Solitude","Solitude","Missing","Interior","Interior","Missing"
"Hall of the Dead","129142","Whiterun","Whiterun","Missing","Interior","Interior","Missing"
"The Bards College","131202","Solitude","Solitude","Missing","Interior","Interior","Missing"
"Frostfruit Inn","129155","Rorikstead","Whiterun","Missing","Interior","Interior","Missing"
"Drunken Huntsman","129138","Whiterun","Whiterun","Missing","Interior","Interior","Missing"
"Hall of the Dead","131233","Solitude","Solitude","Missing","Interior","Interior","Missing"
"Angeline's Aromatics","131200","Solitude","Solitude","Missing","Interior","Interior","Missing"
"Four Shields Tavern","131080","Dragon Bridge","Haafingar","Missing","Interior","Interior","Missing"
"The Winking Skeever","131237","Solitude","Solitude","Missing","Interior","Interior","Missing"
"Salvius Farmhouse","129029","Salvius Farm","Markarth","Missing","Interior","Exterior","Missing"
"Thonnir's House","125854","Morthal","Hjaalmarch","Missing","Interior","Interior","Missing"
"Guardhouse","125848","Morthal","Hjaalmarch","Missing","Interior","Interior","Missing"
"Jorgen and Lami's House","125844","Morthal","Hjaalmarch","Missing","Interior","Interior","Missing"
"Alvor and Sigrid's House","117640","Riverwood","Whiterun","Missing","Interior","Interior","Missing"
"Movarth's Lair","114172","Hjaalmarch","Tamriel","Interior","Interior","Exterior","Missing"
"Yngvild","103114","Winterhold","Tamriel","Interior","Missing","Exterior","Missing"
"Yngol Barrow","103113","Eastmarch","Tamriel","Interior","Interior","Exterior","Missing"
"Wolfskull Cave","103112","Haafingar","Tamriel","Interior","Interior","Exterior","Missing"
"Witchmist Grove","103111","Eastmarch","Tamriel","Missing","Exterior","Exterior","Missing"
"White River Watch","103109","Whiterun","Tamriel","Missing","Interior","Exterior","Exterior"
"Serpent's Bluff Redoubt","103108","The Reach","Tamriel","Missing","Interior","Missing","Exterior"
"Volunruud","103107","the Pale","Tamriel","Interior","Interior","Exterior","Missing"
"Volskygge","103106","Haafingar","Tamriel","Interior","Interior","Exterior","Exterior"
"Ustengrav","103103","Hjaalmarch","Tamriel","Missing","Missing","Exterior","Missing"
"Tumble Arch Pass","103102","the Pale","Tamriel","Missing","Exterior","Exterior","Missing"
"Belethor's General Goods","129134","Whiterun","Whiterun","Missing","Interior","Interior","Missing"
"Korri's House","125822","Winterhold","Winterhold","Missing","Interior","Interior","Missing"
"The Bannered Mare","129133","Whiterun","Whiterun","Missing","Interior","Interior","Missing"
"Thaumaturgist's Hut","125852","Morthal","Hjaalmarch","Missing","Interior","Interior","Missing"
"Ranmir's House","125819","Winterhold","Winterhold","Missing","Interior","Interior","Missing"
"Faendal's House","117641","Riverwood","Whiterun","Missing","Interior","Interior","Missing"
"Sleeping Giant Inn","117644","Riverwood","Whiterun","Missing","Interior","Interior","Missing"
"Amren's House","129131","Whiterun","Whiterun","Missing","Interior","Interior","Missing"
"Arcadia's Cauldron","129132","Whiterun","Whiterun","Missing","Interior","Interior","Missing"
"Hall of the Elements","125818","Winterhold College","Winterhold","Missing","Interior","Interior","Missing"
"Moorside Inn","125846","Morthal","Hjaalmarch","Missing","Interior","Interior","Missing"
"Uttering Hills Camp","103104","Eastmarch","Tamriel","Interior","Interior","Exterior","Exterior"
"The Frozen Hearth","125820","Winterhold","Winterhold","Missing","Interior","Interior","Missing"
"Sven's House","117643","Riverwood","Whiterun","Missing","Interior","Interior","Missing"
"Valtheim Towers","103105","Whiterun","Tamriel","Missing","Exterior","Exterior","Missing"
"Warmaiden's","129130","Whiterun","Whiterun","Missing","Interior","Interior","Missing"
"Jarl's Longhouse","125821","Winterhold","Winterhold","Missing","Interior","Interior","Missing"
"Sorli's House","125835","Stonehills","Hjaalmarch","Missing","Interior","Interior","Missing"
"The Arcanaeum","125817","Winterhold College","Winterhold","Missing","Interior","Interior","Missing"
"Hod and Gerdur's House","117642","Riverwood","Whiterun","Missing","Interior","Interior","Missing"
"Highmoon Hall","125850","Morthal","Hjaalmarch","Missing","Interior","Interior","Missing"
"Treva's Watch","103101","The Rift","Tamriel","Interior","Interior","Exterior","Missing"
"Tolvald's Cave","103100","The Rift","Tamriel","Interior","Interior","Exterior","Exterior"
"Throat of the World","103099","Whiterun","Tamriel","Missing","Missing","Exterior","Missing"
"Talking Stone Camp","103098","Hjaalmarch","Tamriel","Missing","Exterior","Exterior","Exterior"
"Nightcaller Temple","103097","the Pale","Tamriel","Missing","Missing","Exterior","Missing"
"Frostmere Crypt","103096","the Pale","Tamriel","Missing","Interior","Exterior","Exterior"
"Stonehill Bluff","103095","Hjaalmarch","Tamriel","Missing","Exterior","Exterior","Missing"
"Stillborn Cave","103094","Winterhold","Tamriel","Interior","Missing","Exterior","Missing"
"Steepfall Burrow","103093","Haafingar","Tamriel","Interior","Interior","Exterior","Exterior"
"Snow Veil Sanctum","103087","Winterhold","Tamriel","Interior","Interior","Missing","Missing"
"Snapleg Cave","103086","The Rift","Tamriel","Interior","Interior","Missing","Missing"
"Sleeping Tree Camp","103085","Whiterun","Tamriel","Interior","Interior","Exterior","Exterior"
"Eldergleam Sanctuary","103084","Eastmarch","Tamriel","Missing","Missing","Exterior","Missing"
"Silent Moons Camp","103081","Whiterun","Tamriel","Interior","Interior","Exterior","Missing"
"Sightless Pit","103080","Winterhold","Tamriel","Missing","Interior","Exterior","Missing"
"Shrouded Grove","103078","the Pale","Tamriel","Interior","Interior","Exterior","Missing"
"Orphan's Tear","103077","Haafingar","Tamriel","Missing","Exterior","Exterior","Missing"
"Winter War","103076","Eastmarch","Tamriel","Missing","Exterior","Missing","Missing"
"Pride of Tel Vos","103075","Winterhold","Tamriel","Missing","Exterior","Exterior","Missing"
"Pilgrim's Trench","103074","Winterhold","Tamriel","Missing","Missing","Exterior","Missing"
"Hela's Folly","103073","Winterhold","Tamriel","Missing","Exterior","Exterior","Missing"
"Brinehammer","103072","the Pale","Tamriel","Missing","Interior","Exterior","Exterior"
"Shadowgreen Cavern","103068","Haafingar","Tamriel","Exterior","Exterior","Exterior","Exterior"
"Robber's Gorge","103067","Hjaalmarch","Tamriel","Missing","Exterior","Exterior","Exterior"
"Secunda's Kiss","103066","Whiterun","Tamriel","Missing","Exterior","Exterior","Missing"
"Saarthal","103065","Winterhold","Tamriel","Missing","Missing","Exterior","Missing"
"Redoran's Retreat","103061","Whiterun","Tamriel","Interior","Interior","Exterior","Missing"
"Red Road Pass","103060","the Pale","Tamriel","Missing","Exterior","Exterior","Missing"
"Reachwater Rock","103059","The Reach","Tamriel","Missing","Missing","Exterior","Missing"
"Reachcliff Cave","103058","The Reach","Tamriel","Interior","Interior","Exterior","Exterior"
"Ravenscar Hollow","103057","Haafingar","Tamriel","Interior","Interior","Exterior","Exterior"
"Rannveig's Fast","103056","Whiterun","Tamriel","Interior","Interior","Exterior","Exterior"
"Ragnvald","103055","The Reach","Tamriel","Interior","Interior","Exterior","Exterior"
"Pinepeak Cavern","103049","The Rift","Tamriel","Interior","Interior","Exterior","Exterior"
"Pinemoon Cave","103048","Haafingar","Tamriel","Interior","Interior","Exterior","Exterior"
"Orphan Rock","103047","Falkreath","Tamriel","Missing","Exterior","Exterior","Exterior"
"Orotheim","103046","Hjaalmarch","Tamriel","Interior","Interior","Exterior","Exterior"
"Northwatch Keep","103045","Haafingar","Tamriel","Missing","Interior","Exterior","Missing"
"Nilheim","103044","The Rift","Tamriel","Missing","Missing","Exterior","Missing"
"Mzinchaleft","103040","the Pale","Tamriel","Interior","Exterior","Exterior","Missing"
"Mount Anthor","103039","Winterhold","Tamriel","Missing","Exterior","Exterior","Missing"
"Morvunskar","103037","Eastmarch","Tamriel","Interior","Interior","Exterior","Exterior"
"Sons of Skyrim Military Camp","103030","Hjaalmarch","Tamriel","Missing","Exterior","Missing","Missing"
"Sons of Skyrim Military Camp","103023","Falkreath","Tamriel","Missing","Exterior","Missing","Missing"
"Imperial Military Camp","103022","The Rift","Tamriel","Missing","Exterior","Missing","Missing"
"Sons of Skyrim Military Camp","103019","The Reach","Tamriel","Missing","Exterior","Missing","Missing"
"Lost Valley Redoubt","103016","The Reach","Tamriel","Exterior","Exterior","Exterior","Exterior"
"Lost Knife Hideout","103015","Eastmarch","Tamriel","Interior","Interior","Exterior","Exterior"
"Lost Echo Cave","103014","Haafingar","Tamriel","Interior","Interior","Exterior","Exterior"
"Ysgramor's Tomb","103013","Winterhold","Tamriel","Interior","Missing","Exterior","Missing"
"Liar's Retreat","103012","The Reach","Tamriel","Interior","Interior","Exterior","Exterior"
"Korvanjund","103009","the Pale","Tamriel","Missing","Missing","Exterior","Missing"
"Kilkreath Ruins","103008","Haafingar","Tamriel","Missing","Missing","Exterior","Missing"
"Karthspire","103007","The Reach","Tamriel","Missing","Missing","Exterior","Missing"
"Irkngthand","102813","the Pale","Tamriel","Missing","Missing","Exterior","Missing"
"Ilinalta's Deep","102812","Falkreath","Tamriel","Interior","Interior","Exterior","Exterior"
"Greenspring Hollow","102811","Whiterun","Tamriel","Missing","Exterior","Exterior","Missing"
"Honeystrand Cave","102809","The Rift","Tamriel","Missing","Interior","Exterior","Exterior"
"Mistwatch","103035","Eastmarch","Tamriel","Missing","Interior","Exterior","Missing"
"Mzulft","103041","Eastmarch","Tamriel","Missing","Missing","Exterior","Missing"
"Bilegulch Mine","103017","Falkreath","Tamriel","Exterior","Exterior","Exterior","Missing"
"Mor Khazgur","103036","The Reach","Tamriel","Missing","Exterior","Exterior","Missing"
"Purewater Run","103053","The Reach","Tamriel","Missing","Missing","Exterior","Missing"
"Moss Mother Cavern","103038","Falkreath","Tamriel","Exterior","Exterior","Exterior","Exterior"
"Labyrinthian","103010","Hjaalmarch","Tamriel","Missing","Missing","Exterior","Missing"
"Brittleshin Pass","102807","Falkreath","Tamriel","Interior","Interior","Exterior","Exterior"
"Hillgrund's Tomb","102806","Eastmarch","Tamriel","Interior","Interior","Exterior","Exterior"
"High Gate Ruins","102805","the Pale","Tamriel","Interior","Interior","Exterior","Missing"
"Harmugstahl","102804","The Reach","Tamriel","Missing","Missing","Exterior","Missing"
"Halted Stream Camp","102803","Whiterun","Tamriel","Interior","Interior","Exterior","Exterior"
"Halldir's Cairn","102802","Falkreath","Tamriel","Interior","Interior","Exterior","Exterior"
"Hag's End","102801","The Reach","Tamriel","Missing","Interior","Missing","Missing"
"Hag Rock Redoubt","102800","The Reach","Tamriel","Interior","Interior","Exterior","Missing"
"Haemar's Shame","102799","Falkreath","Tamriel","Interior","Interior","Exterior","Missing"
"Guldun Rock","102798","Whiterun","Tamriel","Interior","Interior","Exterior","Missing"
"Cracked Tusk Keep","102797","Falkreath","Tamriel","Missing","Interior","Exterior","Exterior"
"Greywater Grotto","102796","Falkreath","Tamriel","Missing","Missing","Exterior","Missing"
"Graywinter Watch","102795","Whiterun","Tamriel","Interior","Interior","Exterior","Missing"
"Gloomreach","102794","The Reach","Tamriel","Interior","Interior","Exterior","Exterior"
"Glenmoril Coven","102793","Falkreath","Tamriel","Exterior","Interior","Exterior","Missing"
"Geirmund's Hall","102792","The Rift","Tamriel","Interior","Interior","Exterior","Exterior"
"Gallows Rock","102791","Eastmarch","Tamriel","Interior","Interior","Exterior","Missing"
"Frostflow Lighthouse","102790","Winterhold","Tamriel","Interior","Interior","Exterior","Exterior"
"Fort Sungard","102789","The Reach","Tamriel","Missing","Exterior","Exterior","Missing"
"Fort Snowhawk","102788","Hjaalmarch","Tamriel","Missing","Exterior","Exterior","Missing"
"Fort Neugrad","102787","Falkreath","Tamriel","Missing","Exterior","Exterior","Missing"
"Fort Kastav","102786","Winterhold","Tamriel","Missing","Exterior","Exterior","Missing"
"Fort Hraggstad","102785","Haafingar","Tamriel","Missing","Exterior","Exterior","Missing"
"Fort Greenwall","102784","The Rift","Tamriel","Interior","Exterior","Exterior","Missing"
"Fort Fellhammer","102783","the Pale","Tamriel","Exterior","Exterior","Exterior","Missing"
"Fort Dunstad","102782","the Pale","Tamriel","Missing","Exterior","Exterior","Missing"
"Fort Greymoor","102781","Whiterun","Tamriel","Missing","Exterior","Exterior","Missing"
"Fort Amol","102780","Eastmarch","Tamriel","Missing","Exterior","Exterior","Missing"
"Forsaken Cave","102779","the Pale","Tamriel","Interior","Interior","Exterior","Missing"
"Folgunthur","102777","Hjaalmarch","Tamriel","Interior","Interior","Exterior","Exterior"
"Hob's Fall Cave","102808","Winterhold","Tamriel","Interior","Interior","Exterior","Exterior"
"Fallowstone Cave","102774","The Rift","Tamriel","Exterior","Exterior","Exterior","Exterior"
"Faldar's Tooth","102773","The Rift","Tamriel","Interior","Interior","Exterior","Exterior"
"Evergreen Grove","102772","Falkreath","Tamriel","Missing","Exterior","Exterior","Missing"
"Dustman's Cairn","102771","Whiterun","Tamriel","Interior","Interior","Missing","Exterior"
"Duskglow Crevice","102770","the Pale","Tamriel","Interior","Interior","Exterior","Exterior"
"Red Eagle Redoubt","102768","The Reach","Tamriel","Missing","Exterior","Exterior","Exterior"
"Druadach Redoubt","102767","The Reach","Tamriel","Interior","Interior","Exterior","Exterior"
"Driftshade Sanctuary","102766","Winterhold","Tamriel","Interior","Interior","Exterior","Exterior"
"Steamcrag Camp","102765","Eastmarch","Tamriel","Missing","Exterior","Exterior","Missing"
"Shearpoint","102764","the Pale","Tamriel","Missing","Exterior","Exterior","Missing"
"Bonestrewn Crest","102763","Eastmarch","Tamriel","Missing","Exterior","Exterior","Missing"
"Lost Tongue Overlook","102762","The Rift","Tamriel","Missing","Exterior","Exterior","Missing"
"Northwind Summit","102761","The Rift","Tamriel","Missing","Exterior","Exterior","Exterior"
"Autumnwatch Tower","102760","The Rift","Tamriel","Missing","Exterior","Exterior","Missing"
"Skyborn Altar","102757","Hjaalmarch","Tamriel","Missing","Exterior","Exterior","Exterior"
"Eldersblood Peak","102756","Hjaalmarch","Tamriel","Missing","Exterior","Exterior","Missing"
"Dragontooth Crater","102755","The Reach","Tamriel","Missing","Exterior","Exterior","Missing"
"Deepwood Redoubt","102754","The Reach","Tamriel","Missing","Exterior","Exterior","Exterior"
"Dead Men's Respite","102753","Hjaalmarch","Tamriel","Interior","Interior","Exterior","Exterior"
"Dead Crone Rock","102752","The Reach","Tamriel","Interior","Interior","Exterior","Missing"
"Darkshade Copse","102750","Eastmarch","Tamriel","Interior","Interior","Exterior","Missing"
"Crystaldrift Cave","102748","The Rift","Tamriel","Interior","Interior","Exterior","Exterior"
"Cronvangr Hall","102747","Eastmarch","Tamriel","Interior","Interior","Exterior","Exterior"
"Cragwallow Slope","102746","Eastmarch","Tamriel","Interior","Interior","Exterior","Missing"
"Cragslane Cavern","102745","Eastmarch","Tamriel","Missing","Interior","Exterior","Exterior"
"Cradlecrush Rock","102744","Eastmarch","Tamriel","Missing","Exterior","Exterior","Missing"
"Clearspring Tarn","102743","The Rift","Tamriel","Interior","Interior","Exterior","Exterior"
"Dushnikh Yal","102769","The Reach","Tamriel","Exterior","Exterior","Exterior","Exterior"
"Darkwater Pass","102751","The Rift","Tamriel","Exterior","Exterior","Exterior","Missing"
"Clearpine Pond","102742","Haafingar","Tamriel","Missing","Exterior","Exterior","Exterior"
"Chillwind Depths","102740","Hjaalmarch","Tamriel","Interior","Interior","Exterior","Exterior"
"Broken Oar Grotto","102739","Haafingar","Tamriel","Interior","Interior","Exterior","Exterior"
"Bruca's Leap Redoubt","102737","The Reach","Tamriel","Interior","Exterior","Exterior","Exterior"
"Brood Cavern","102736","Hjaalmarch","Tamriel","Interior","Interior","Exterior","Exterior"
"Broken Tower Redoubt","102735","The Reach","Tamriel","Interior","Interior","Exterior","Missing"
"Broken Helm Hollow","102734","The Rift","Tamriel","Interior","Interior","Exterior","Exterior"
"Broken Fang Cave","102733","Whiterun","Tamriel","Interior","Interior","Exterior","Exterior"
"Southfringe Sanctum","102732","Falkreath","Tamriel","Exterior","Exterior","Exterior","Missing"
"Twilight Sepulcher","102731","Falkreath","Tamriel","Missing","Missing","Exterior","Missing"
"Blizzard Rest","102126","the Pale","Tamriel","Missing","Exterior","Exterior","Exterior"
"Bleakwind Bluff","102124","The Reach","Tamriel","Missing","Exterior","Missing","Missing"
"Bleakwind Basin","102123","Whiterun","Tamriel","Missing","Exterior","Exterior","Exterior"
"Bleakcoast Cave","102122","Winterhold","Tamriel","Interior","Interior","Exterior","Exterior"
"Autumnshade Clearing","102116","The Rift","Tamriel","Missing","Exterior","Exterior","Exterior"
"Swindler's Den","102114","Whiterun","Tamriel","Interior","Interior","Exterior","Missing"
"Whistling Mine","101957","Winterhold","Tamriel","Missing","Exterior","Exterior","Missing"
"Pelagia Farm","101951","Whiterun","Whiterun","Missing","Exterior","Exterior","Missing"
"Salvius Farm","101952","Markarth","The Reach","Missing","Exterior","Exterior","Missing"
"Blind Cliff Cave","102125","The Reach","Tamriel","Interior","Interior","Exterior","Exterior"
"Angarvunde","102115","The Rift","Tamriel","Interior","Interior","Exterior","Exterior"
"Merryfair Farm","101948","Riften","The Rift","Missing","Exterior","Interior","Missing"
"Bthardamz","102738","The Reach","Tamriel","Missing","Missing","Exterior","Missing"
"Mixwater Mill","101949","Eastmarch","Tamriel","Missing","Exterior","Exterior","Missing"
"Half-Moon Mill","101950","Falkreath","Tamriel","Missing","Exterior","Exterior","Missing"
"Solitude Sawmill","101956","Solitude","Haafingar","Missing","Exterior","Interior","Missing"
"Alftand","102113","Winterhold","Tamriel","Interior","Interior","Exterior","Exterior"
"Snow-Shod Farm","101955","The Rift","Tamriel","Missing","Exterior","Interior","Missing"
"Sarethi Farm","101953","The Rift","Tamriel","Missing","Exterior","Interior","Missing"
"Kolskeggr Mine","101945","The Reach","Tamriel","Missing","Exterior","Exterior","Missing"
"Hlaalu Farm","101941","Eastmarch","Tamriel","Missing","Exterior","Exterior","Missing"
"Goldenglow Estate","101938","The Rift","Tamriel","Missing","Interior","Interior","Missing"
"Brandy-Mug Farm","101519","Eastmarch","Tamriel","Missing","Exterior","Exterior","Missing"
"Nightgate Inn","100943","the Pale","Tamriel","Missing","Exterior","Missing","Missing"
"Heartwood Mill","101939","The Rift","Tamriel","Missing","Exterior","Interior","Missing"
"Hollyfrost Farm","101942","Eastmarch","Tamriel","Missing","Exterior","Exterior","Missing"
"Kynesgrove","100942","Eastmarch","Tamriel","Missing","Exterior","Missing","Missing"
"Katla's Farm","101944","Solitude","Haafingar","Missing","Exterior","Exterior","Missing"
"Old Hroldan","100949","The Reach","Tamriel","Missing","Exterior","Exterior","Missing"
"Karthwasten","100948","The Reach","Tamriel","Missing","Exterior","Exterior","Missing"
"Darkwater Crossing","100941","Eastmarch","Tamriel","Missing","Exterior","Exterior","Missing"
"Solitude","100954","Haafingar","Tamriel","Missing","Exterior","Missing","Missing"
"Left Hand Mine","101946","Markarth","The Reach","Missing","Exterior","Exterior","Missing"
"Ivarstead","100939","The Rift","Tamriel","Missing","Exterior","Exterior","Missing"
"Stonehills","100946","Hjaalmarch","Tamriel","Missing","Exterior","Exterior","Missing"
"Windhelm","100951","Eastmarch","Tamriel","Missing","Exterior","Exterior","Missing"
"Shor's Stone","100940","The Rift","Tamriel","Missing","Exterior","Missing","Missing"
"Morthal","100947","Hjaalmarch","Tamriel","Missing","Exterior","Exterior","Missing"
"Winterhold","100945","Winterhold","Tamriel","Missing","Exterior","Exterior","Missing"
"Gloombound Mine","101937","Narzulbur","Eastmarch","Interior","Interior","Exterior","Missing"
"Helgen","100938","Falkreath","Tamriel","Missing","Exterior","Missing","Missing"
"Markarth","100953","The Reach","Tamriel","Exterior","Exterior","Exterior","Missing"
"High Hrothgar","101940","Whiterun","Tamriel","Missing","Missing","Exterior","Missing"
"Whiterun","100950","Whiterun","Tamriel","Missing","Exterior","Exterior","Missing"
"Falkreath","92015","Tamriel","","Missing","Missing","Exterior","Missing"
"Moldering Ruins","33570045","The Reach","Tamriel","Interior","Interior","Exterior","Exterior"
"Forgotten Valley","33597564","Tamriel","","Missing","Exterior","Missing","Exterior"
"Darkfall Cave","33597562","Tamriel","","Missing","Missing","Exterior","Missing"
"Ruunvald Excavation","33585953","The Rift","Tamriel","Missing","Missing","Exterior","Missing"
"The Aetherium Forge","33576998","Bthalft","The Rift","Missing","Missing","Exterior","Missing"
"Redwater Den","33574255","The Rift","Tamriel","Interior","Interior","Exterior","Exterior"
"Name TBD","33574254","Falkreath","Tamriel","Missing","Missing","Exterior","Missing"
"Arkngthamz","33574253","The Reach","Tamriel","Missing","Missing","Exterior","Missing"
"Windstad Manor","50380298","Windstad Manor","Hjaalmarch","Missing","Interior","Exterior","Missing"
"Heljarchen Hall","50352650","the Pale","Tamriel","Missing","Exterior","Exterior","Missing"
"Windstad Manor","50352649","Hjaalmarch","Tamriel","Missing","Exterior","Exterior","Missing"
"Lakeview Manor","50344082","Lakeview Manor","Falkreath","Missing","Interior","Exterior","Missing"
"Lakeview Manor","50344074","Falkreath","Tamriel","Missing","Exterior","Exterior","Missing"
"Headwaters of Harstrad","67346328","Solstheim","","Missing","Missing","Missing","Exterior"
"Attius Farm Location","67223524","Solstheim","","Missing","Exterior","Missing","Missing"
"Saering's Watch","67214635","Solstheim","","Missing","Exterior","Missing","Exterior"
"Hrothmund's Barrow","67206061","Solstheim","","Missing","Interior","Missing","Exterior"
"Thirsk","67191738","","Solstheim","Missing","Exterior","Missing","Missing"
"Water Stone","67191518","Solstheim","","Missing","Missing","Missing","Missing"
"Moesring Pass","67191514","Solstheim","","Missing","Exterior","Missing","Missing"
"Bujold's Retreat","67191498","","Solstheim","Missing","Exterior","Missing","Missing"
"Strident Squall","67191497","Solstheim","","Missing","Exterior","Missing","Missing"
"Northshore Landing","67191495","Solstheim","","Missing","Missing","Missing","Missing"
"Ashfallow Citadel","67191493","Solstheim","","Missing","Interior","Missing","Exterior"
"Glacial Cave","67191492","Solstheim","","Interior","Missing","Missing","Exterior"
"Hrodulf's House","67191491","Solstheim","","Missing","Interior","Missing","Exterior"
"Dayspring Canyon","33573919","Tamriel","","Missing","Missing","Exterior","Missing"
"Dimhollow Crypt","33574632","the Pale","Tamriel","Missing","Missing","Exterior","Missing"
"Raven Rock","67191737","Solstheim","","Missing","Exterior","Missing","Missing"
"Sun Stone","67191516","Tel Mithryn","Solstheim","Missing","Missing","Missing","Missing"
"Wind Stone","67191519","Skaal Village","Solstheim","Missing","Missing","Missing","Missing"
"Falkreath","100937","Falkreath","Tamriel","Missing","Exterior","Missing","Missing"
"Castle Volkihar","33573920","Tamriel","","Missing","Missing","Exterior","Missing"
"Dragon Bridge","100934","Haafingar","Tamriel","Missing","Exterior","Exterior","Missing"
"Skaal Village","67191739","Solstheim","","Missing","Exterior","Missing","Missing"
"Haknir's Shoal","67191490","Solstheim","","Missing","Exterior","Missing","Exterior"
"Broken Tusk Mine","67191489","Solstheim","","Missing","Interior","Missing","Exterior"
"Abandoned Lodge","67191488","Solstheim","","Interior","Interior","Missing","Exterior"
"White Ridge Barrow","67191484","Solstheim","","Missing","Missing","Missing","Missing"
"Nchardak","67191476","Solstheim","","Exterior","Interior","Missing","Exterior"
"Horker Island","67191466","Solstheim","","Missing","Missing","Missing","Exterior"
"Gyldenhul Barrow","67191464","Solstheim","","Interior","Interior","Missing","Exterior"
"Frossel","67191462","Solstheim","","Interior","Missing","Missing","Exterior"
"Kagrumez","67191458","Solstheim","","Missing","Interior","Missing","Exterior"
"Fahlbtharz","67191456","Solstheim","","Interior","Interior","Missing","Exterior"
"Castle Karstaag Ruins","67191454","Solstheim","","Interior","Interior","Missing","Exterior"
"Brodir Grove","67191452","Solstheim","","Missing","Exterior","Missing","Missing"
"Beast Stone","67191446","","Solstheim","Missing","Missing","Missing","Missing"
"Altar of Thrond","67191444","Solstheim","","Interior","Interior","Missing","Exterior"
"Fort Frostmoth","67191443","Solstheim","","Missing","Missing","Missing","Exterior"
"The Gwylim Annex","305064800","Haafingar","Tamriel","Missing","Missing","Exterior","Missing"
"Outlaw's Stone","402676037","Whiterun","Tamriel","Missing","Exterior","Missing","Missing"
"Thickness Hideout","402696940","Whiterun","Tamriel","Missing","Exterior","Missing","Missing"
"Meadow's Knife","402717731","Whiterun","Tamriel","Missing","Exterior","Missing","Missing"
"Wastelands Bandits","402738496","Whiterun","Tamriel","Missing","Exterior","Missing","Missing"
"Hill of Anguish","402738554","Falkreath","Tamriel","Missing","Missing","Missing","Missing"
"Frosted Edge","402759347","Winterhold","Tamriel","Missing","Exterior","Missing","Missing"
"Grove Refuge","402759432","Eastmarch","Tamriel","Missing","Exterior","Missing","Missing"
"Honrich's Crossroads","402759467","The Rift","Tamriel","Missing","Exterior","Missing","Missing"
"Swamp Hideout","402759506","Hjaalmarch","Tamriel","Missing","Exterior","Missing","Missing"
"Tundra Hideout","402759561","","","Missing","Exterior","Missing","Missing"
"Waterfalls Hideout","402780406","The Reach","Tamriel","Missing","Exterior","Missing","Missing"
"Corner of the Trail","402801280","Solstheim","","Missing","Exterior","Missing","Missing"
"Traitor's Cliff","402801343","The Rift","Tamriel","Missing","Exterior","Missing","Missing"
"Karth's Vigil","402801482","Haafingar","Tamriel","Missing","Exterior","Missing","Missing"
"Golden Ice","402822344","the Pale","Tamriel","Missing","Exterior","Missing","Missing"
"Valley Hideout","402843251","Forgotten Valley","Tamriel","Missing","Exterior","Missing","Missing"
"Frozen Grove","402864046","Forgotten Valley","Tamriel","Missing","Exterior","Missing","Missing"
"Elysium Estate","436420629","Whiterun","Whiterun","Missing","Exterior","Exterior","Missing"
"Windhelm Docks","654417161","Windhelm","Eastmarch","Missing","Missing","Exterior","Missing"
"Earth Stone","67191460","Raven Rock","Solstheim","Missing","Missing","Missing","Missing"
"Largashbur","103011","The Rift","Tamriel","Missing","Exterior","Exterior","Missing"
"New Gnisis Cornerclub","133616","Windhelm","Eastmarch","Missing","Interior","Missing","Missing"
"Largashbur's Longhouse","244779","Largashbur","The Rift","Missing","Interior","Missing","Missing"
"Fort Dawnguard","33630462","Dayspring Canyon","Tamriel","Missing","Interior","Missing","Missing"
"Windhelm Stables","133621","Windhelm","Eastmarch","Missing","Interior","Missing","Missing"
"Abandoned Shack","337228","Abandoned Shack","Hjaalmarch","Missing","Missing","Exterior","Missing"
"House Gray-Mane","129140","Whiterun","Whiterun","Missing","Interior","Interior","Missing"
"Falion's House","125842","Morthal","Hjaalmarch","Missing","Interior","Interior","Missing"
"Temple of the Divines","131235","Solitude","Solitude","Missing","Interior","Interior","Missing"
"Sky Haven Temple","101954","The Reach","Tamriel","Missing","Missing","Exterior","Missing"
"Windpeak Inn","131173","Dawnstar","the Pale","Missing","Interior","Missing","Missing"
"Candlehearth Hall","133610","Windhelm","Eastmarch","Missing","Interior","Missing","Missing"
"Solitude Stables","459882","Katla's Farm","Solitude","Missing","Interior","Missing","Missing"
"Whiterun Market","654437898","Whiterun","Whiterun","Missing","Exterior","Missing","Missing"
"Shoal's Rest Farm","1426460030","Rorikstead","Whiterun","Missing","Exterior","Exterior","Missing"
"Gray Pine Luxuries","131269","Falkreath","Falkreath","Missing","Interior","Interior","Missing"
"Loreius Farm","101947","the Pale","Tamriel","Missing","Exterior","Exterior","Missing"
"Heartwood Mill","385275","Heartwood Mill","The Rift","Missing","Interior","Missing","Missing"
"Ansilvund","114173","Eastmarch","Tamriel","Interior","Interior","Exterior","Exterior"
"Riverwood","78179","Whiterun","Tamriel","Missing","Exterior","Exterior","Missing"
"Nepos' House","127765","Markarth","The Reach","Missing","Interior","Missing","Missing"
"Goldenrock Mine","133648","Darkwater Crossing","Eastmarch","Missing","Interior","Missing","Missing"
"Volkihar Keep","33568125","Castle Volkihar","Tamriel","Missing","Interior","Missing","Missing"
"The White Hall","131170","Dawnstar","the Pale","Missing","Interior","Missing","Missing"
"Thalmor Embassy","101522","Haafingar","Tamriel","Missing","Missing","Missing","Missing"
"Dawnstar","100944","the Pale","Tamriel","Missing","Exterior","Exterior","Missing"
"Riften","100952","The Rift","Tamriel","Missing","Exterior","Exterior","Missing"
"Haelga's Bunkhouse","140861","Riften","The Rift","Missing","Interior","Missing","Missing"
"Bee and Barb","140414","Riften","The Rift","Missing","Interior","Missing","Missing"
"Black-Briar Meadery","140418","Riften","The Rift","Missing","Interior","Missing","Missing"
"Anga's Mill","101515","the Pale","Tamriel","Missing","Exterior","Exterior","Missing"
"Jorrvaskr","147037","Jorrvaskr","Whiterun","Missing","Interior","Interior","Missing"
"Lost Valkygg","961122","Hjaalmarch","Tamriel","Missing","Missing","Exterior","Missing"
"Jarl's Longhouse","131273","Falkreath","Falkreath","Missing","Interior","Interior","Missing"
"Mor Khazgur Mine","129026","Mor Khazgur","The Reach","Missing","Interior","Missing","Missing"
"Forelhost","102778","The Rift","Tamriel","Interior","Interior","Exterior","Exterior"
"Raldbthar","591222","the Pale","Tamriel","Interior","Interior","Exterior","Exterior"
"Riften Mistveil Keep","140870","Riften","The Rift","Missing","Interior","Missing","Missing"
"East Empire Company","133612","Windhelm","Eastmarch","Missing","Interior","Missing","Missing"
"Temple of Mara","140878","Riften","The Rift","Missing","Interior","Missing","Missing"
"Temple of Dibella","127767","Markarth","The Reach","Missing","Interior","Missing","Missing"
"Jorrvaskr","129143","Whiterun","Whiterun","Missing","Interior","Interior","Missing"
"Lucan's Dry Goods","117645","Riverwood","Whiterun","Missing","Interior","Interior","Missing"
"Endon's House","127756","Markarth","The Reach","Missing","Interior","Missing","Missing"
"Castle Dour","131210","Solitude","Haafingar","Missing","Interior","Missing","Missing"
"Dengeir's Hall","131263","Falkreath","Falkreath","Missing","Interior","Interior","Missing"
"Rockwallow Mine","190320","Stonehills","Hjaalmarch","Missing","Interior","Missing","Missing"
"Sadri's Used Wares","133620","Windhelm","Eastmarch","Missing","Interior","Missing","Missing"
"The White Phial","133625","Windhelm","Eastmarch","Missing","Interior","Missing","Missing"
"Elgrim's Elixirs","140423","Riften","The Rift","Missing","Interior","Missing","Missing"
"Black-Briar Manor","140416","Riften","The Rift","Missing","Interior","Missing","Missing"
"Steamscorch Gully Mine","133639","Kynesgrove","Eastmarch","Missing","Interior","Missing","Missing"
"The Pawned Prawn","140872","Riften","The Rift","Missing","Interior","Missing","Missing"
"Nightgate Inn","131156","Nightgate Inn","the Pale","Missing","Interior","Missing","Missing"
"Solitude Jail","781719","Castle Dour","Solitude","Interior","Interior","Missing","Missing"
"Hag's Cure","127759","Markarth","The Reach","Missing","Interior","Missing","Missing"
"Understone Keep","127766","Markarth","The Reach","Missing","Interior","Missing","Missing"
"Silver-Blood Inn","127758","Markarth","The Reach","Missing","Interior","Missing","Missing"
"Arnleif and Sons Trading Company","127760","Markarth","The Reach","Missing","Interior","Missing","Missing"
"Dead Man's Drink","379430","Falkreath","Falkreath","Missing","Interior","Missing","Missing"
"The Ragged Flagon","140874","Riften","The Rift","Missing","Interior","Missing","Missing"
"Aretino Residence","133579","Windhelm","Eastmarch","Missing","Interior","Missing","Missing"
"Thieves Guild Headquarters","140880","Riften","The Rift","Missing","Interior","Missing","Missing"
"Winterhold College","487226","Winterhold","Winterhold","Missing","Exterior","Exterior","Missing"
"The Retching Netch","67213860","Raven Rock","Solstheim","Missing","Interior","Missing","Missing"
"Rorikstead","100935","Whiterun","Tamriel","Missing","Exterior","Exterior","Missing"
"Cidhna Mine","101521","Markarth","The Reach","Missing","Interior","Missing","Missing"
"Treasury House","127764","Markarth","The Reach","Missing","Interior","Missing","Missing"
"Katla's Farm","94300","Katla's Farm","Solitude","Missing","Interior","Missing","Missing"
"Braidwood Inn","133634","Kynesgrove","Eastmarch","Missing","Interior","Missing","Missing"
"Temple of Miraak","67191478","Solstheim","","Interior","Interior","Missing","Exterior"
"The Warrens","127757","Markarth","The Reach","Missing","Interior","Missing","Missing"
"Old Hroldan Inn","128971","Old Hroldan","The Reach","Missing","Interior","Missing","Missing"
"Palace of the Kings","133618","Windhelm","Eastmarch","Missing","Interior","Missing","Missing"
"Temple of Talos","133622","Windhelm","Eastmarch","Missing","Interior","Missing","Missing"
"Riften Fishery","140859","Riften","The Rift","Missing","Interior","Missing","Missing"
"Dormitory","125812","Winterhold College","Winterhold","Missing","Interior","Missing","Missing"
"Darklight Tower","102749","The Rift","Tamriel","Interior","Missing","Exterior","Exterior"
"House of Arkay","131271","Falkreath","Falkreath","Missing","Interior","Interior","Missing"
"The Scorched Hammer","353825","Riften","The Rift","Missing","Interior","Missing","Missing"
"Avanchnzel","102117","The Rift","Tamriel","Missing","Missing","Exterior","Missing"
"Beggar's Row","385276","Riften","The Rift","Missing","Interior","Missing","Missing"
"Hall of the Dead","147478","Markarth","The Reach","Missing","Interior","Missing","Missing"
"Bleak Falls Barrow","102121","Whiterun","Tamriel","Interior","Interior","Exterior","Exterior"
"Solitude Blacksmith","644515","Solitude","Haafingar","Missing","Interior","Missing","Missing"
"Calixto's House of Curiosities","133609","Windhelm","Eastmarch","Missing","Interior","Missing","Missing"
"Honningbrew Meadery","301920","Honningbrew Meadery","Whiterun","Interior","Interior","Missing","Missing"
"Riften Jail","691334","Riften","The Rift","Interior","Interior","Missing","Missing"
"Sylgja's House","140976","Shor's Stone","The Rift","Missing","Interior","Interior","Missing"
"Falkreath Jail","1061807","Falkreath","Falkreath","Missing","Interior","Missing","Missing"
"Chillfurrow Farm","101520","Whiterun","Whiterun","Missing","Exterior","Exterior","Missing"
"Rimerock Burrow","103062","Haafingar","Tamriel","Interior","Interior","Exterior","Missing"
"Whistling Mine","469789","Whistling Mine","Winterhold","Interior","Interior","Exterior","Missing"
"Windhelm Hall of the Dead","133615","Windhelm","Eastmarch","Missing","Interior","Missing","Missing"
"The Mortar and Pestle","131163","Dawnstar","the Pale","Missing","Interior","Missing","Missing"
"Narzulbur","103042","Eastmarch","Tamriel","Exterior","Exterior","Exterior","Exterior"
"Honorhall Orphanage","140863","Riften","The Rift","Missing","Interior","Missing","Missing"
"Kolbjorn Barrow","67191483","Solstheim","","Missing","Interior","Missing","Exterior"
"Tel Mithryn","67191740","Solstheim","","Missing","Exterior","Missing","Missing"
"Tel Mithryn Tower","67227114","Tel Mithryn","Solstheim","Missing","Interior","Missing","Missing"
"Thirsk Mead Hall","67203070","Thirsk","","Missing","Interior","Missing","Missing"
"House of Clan Snow-Shod","140864","Riften","The Rift","Missing","Interior","Missing","Missing"
"Riften Hall of the Dead","140868","Riften","The Rift","Missing","Interior","Missing","Missing"
"Riften Ratway","243825","Riften","The Rift","Missing","Interior","Interior","Missing"
"Alva's House","125838","Morthal","Hjaalmarch","Missing","Interior","Interior","Missing"

# Proposed solution.

With this data we should be able to:

1) Offer a confident list of locations to LLM. We don't need to duplicate entries on DB, but we can duplicate "offerings" to LLM.
   Example: Obtain a result like this
```
available/near locations:

- Alftand 
- Alftand (inside)
```
* When LLM choses Alftand, we select formid and send command to NPC to TravelTo to than formid. When location is reached, Track information will return same formid and name "Alftand", and is_interior=0
* When LLM choses Alftand (inside), we select insideEntranceMarkerRef( or locationCenterMarkerRef ref) (this data is on another column) and send command to NPC to TravelTo that reference. When location is reached, Track information will return Alftan formid and name "Alftand", and is_interior=1, so we can build the status "NPC is at Alftand (inside)"


# Proposed solution (AI review)

I've analyzed the document and, based solely on the information you've provided, I think the **proposed solution is not only doable, but it is significantly better than trying to force Skyrim's location model into a single "location = coordinate" abstraction.**

The new bitfield information coming from the DLL changes the problem completely.

---

# The original problem

Initially the importer had only one boolean:

```
is_interior
```

which effectively answered

> "Did I see any interior reference?"

That is not enough because Skyrim locations are **not** either interior or exterior.

They are collections of references.

For example a dungeon may contain

```
Location
    |
    +-- Map marker (outside)
    +-- Outside entrance
    +-- Inside entrance
    +-- Center marker
```

Each one answers a different question.

The previous importer collapsed all of this into

```
is_interior = true
```

which loses nearly all useful information.

Heartwood Mill is exactly the consequence of that. 

---

# The DLL update is the real solution

Now the DLL exports

```
inside entrance
location center
map marker
outside entrance
```

independently.

This is a huge improvement.

Instead of asking

> Is this location interior?

you can ask

> Which references exist, and where are they?

That is a much more Skyrim-like model.

---

# I actually wouldn't duplicate the database

This is the only point where I'd slightly change the proposal.

I would **not** duplicate rows.

Instead I'd keep

```
Location
--------
formid
name
region
hold

inside_entrance_ref
outside_entrance_ref
location_center_ref
map_marker_ref

inside_entrance_type
outside_entrance_type
location_center_type
map_marker_type
```

One Skyrim Location.

Multiple representations.

That matches the engine.

---

# Duplicate only the AI offerings

This part I completely agree with.

Suppose database contains

```
Bleak Falls Barrow
```

Internally

```
Map marker -> exterior

Outside entrance -> exterior

Inside entrance -> interior

Center -> interior
```

The LLM never needs to know all this complexity.

Instead generate

```
Available locations

Bleak Falls Barrow
Bleak Falls Barrow (inside)
```

Those are **virtual entries**.

They don't exist in PostgreSQL.

They are generated.

That keeps the DB normalized.

---

# Travel becomes deterministic

Suppose LLM chooses

```
Bleak Falls Barrow
```

Importer resolves

```
outside entrance
```

or

```
map marker
```

depending on priority.

Travel command

```
TravelTo(reference)
```

Later tracker returns

```
Location
Bleak Falls Barrow

Current cell
Exterior
```

Status

```
NPC is at Bleak Falls Barrow.
```

---

Now suppose LLM selects

```
Bleak Falls Barrow (inside)
```

Importer resolves

Priority

```
Inside entrance

else

Location center

else

Exterior marker
```

Travel

```
TravelTo(reference)
```

NPC enters dungeon.

Tracker later returns

```
Location = Bleak Falls Barrow

Current cell = Interior
```

Status becomes

```
NPC is inside Bleak Falls Barrow.
```

This is perfectly consistent.

---

# The nice thing is the tracking system already gives the answer

Your document mentions

> the tracker already knows if NPC is in an interior cell.

This means you never have to infer the state.

Instead

```
Current FormID

+

Current Cell Interior
```

already uniquely determines

```
Alftand

outside
```

or

```
Alftand

inside
```

No ambiguity.

---

# Heartwood Mill stops being a problem

Right now Heartwood Mill becomes

```
Location A

interior

Location B

interior
```

because

```
ANY reference interior
```

wins.

With the new model

Location A

```
Map marker

Exterior

Center

Exterior
```

Location B

```
Center

Interior
```

Even if they share references, you no longer ask

> "Is this location interior?"

You ask

> "Which representation am I trying to use?"

Those are different questions.

The ambiguity disappears.

---

# I would introduce representation priorities

For example

Exterior representation

```
1 Outside entrance

2 Map marker

3 Center if exterior

4 Inside entrance (last resort)
```

Interior representation

```
1 Inside entrance

2 Location center

3 Outside entrance

4 Map marker
```

This is deterministic.

No heuristics.

---

# PostgreSQL example

Instead of

```text
Location

is_interior
```

I'd expose a computed view.

```sql
SELECT
    formid,
    name,

    CASE
        WHEN outside_entrance_ref IS NOT NULL THEN outside_entrance_ref
        WHEN map_marker_ref IS NOT NULL THEN map_marker_ref
        WHEN location_center_type='Exterior' THEN location_center_ref
    END AS exterior_target,

    CASE
        WHEN inside_entrance_ref IS NOT NULL THEN inside_entrance_ref
        WHEN location_center_type='Interior' THEN location_center_ref
    END AS interior_target

FROM locations;
```

Now every location has

```
Exterior target

Interior target
```

No duplication.

---

# PHP generation for the LLM

Very straightforward.

```php
function buildOfferings(array $location): array
{
    $result = [];

    if ($location['exterior_target'] !== null) {
        $result[] = [
            'label'  => $location['name'],
            'target' => $location['exterior_target'],
            'inside' => false
        ];
    }

    if ($location['interior_target'] !== null) {
        $result[] = [
            'label'  => $location['name'] . ' (inside)',
            'target' => $location['interior_target'],
            'inside' => true
        ];
    }

    return $result;
}
```

The database still contains

```
Alftand
```

The LLM receives

```
Alftand

Alftand (inside)
```

---

# I would avoid "(inside)" being part of the database

This is another small design suggestion.

Instead of persisting

```
Alftand (inside)
```

generate it dynamically.

For example

```php
class LocationChoice
{
    public string $displayName;

    public int $formId;

    public bool $inside;

    public int $travelReference;
}
```

Display name becomes

```
inside
    ? "$name (inside)"
    : $name;
```

No duplicated text anywhere.

---

# One thing I'd still add

The current proposal assumes only two useful representations:

```
Outside

Inside
```

But your bitfield already supports more nuanced concepts.

For example

```
Map marker

Outside entrance

Inside entrance

Center
```

I would preserve all four internally, even if today's AI only exposes two choices. That leaves room for future features such as:

* "Meet me at the entrance of Bleak Falls Barrow."
* "Go to the center of Blackreach."
* "Fast travel to the map marker."
* "Wait outside while I clear the dungeon."

Internally, those become simple reference selections rather than new data structures.

# Overall assessment

I think the proposal is **very feasible** and aligns much better with Skyrim's actual data model than the original approach. My only architectural adjustment would be:

* **Do not duplicate database rows.**
* **Store one location with multiple reference types.**
* **Generate virtual "outside" and "inside" choices only when building the LLM context.**
* **Use deterministic reference-priority rules to resolve travel targets.**
* **Continue using the tracker (`formid` + current cell interior/exterior) to determine the NPC's current representation.**

This preserves normalization, avoids synchronization issues, naturally handles problematic cases like **Heartwood Mill**, and remains extensible if you later expose more than just "inside" and "outside" to the AI.
