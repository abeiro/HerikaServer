-- Spell Descriptions - All Schools of Magic
-- Default spell descriptions for Skyrim spells

INSERT INTO public.descriptions (baseid, name, description) VALUES
-- Spell Descriptions - Alteration School
('00043324', $$Candlelight$$, $$Creates a hovering ball of light that follows you.$$),
('0005AD5C', $$Oakflesh$$, $$Hardens your skin to protect against physical attacks.$$),
('000A26E2', $$Magelight$$, $$A ball of light that sticks to surfaces and illuminates the area.$$),
('0005AD5D', $$Stoneflesh$$, $$Turns your skin hard as stone for protection.$$),
('000211EE', $$Detect Life$$, $$Reveals the presence of living beings nearby.$$),
('00051B16', $$Ironflesh$$, $$Transforms your skin into iron-like armor.$$),
('0001A4CC', $$Telekinesis$$, $$Allows you to move distant objects with your mind.$$),
('00109111', $$Transmute$$, $$Transmutes base metals into more valuable ores.$$),
('0005D175', $$Waterbreathing$$, $$Allows you to breathe underwater.$$),
('000211EF', $$Detect Dead$$, $$Reveals the presence of undead creatures nearby.$$),
('0005AD5E', $$Ebonyflesh$$, $$Transforms your skin into a powerful ebony-like armor.$$),
('0005AD5F', $$Paralyze$$, $$Freezes a target in place, rendering them immobile.$$),
('000CDB70', $$Dragonhide$$, $$Transforms your skin into near-impenetrable dragonhide.$$),
('000B62E6', $$Mass Paralysis$$, $$Paralyzes multiple targets in the area.$$),
('000DA746', $$Equilibrium$$, $$Converts your life force into magical energy.$$),

-- Spell Descriptions - Conjuration School
('000211EB', $$Bound Sword$$, $$Conjures a magical sword from Oblivion.$$),
('000640B6', $$Conjure Familiar$$, $$Summons a ghostly wolf to fight for you.$$),
('0007E8E1', $$Raise Zombie$$, $$Reanimates a weak corpse to serve you.$$),
('000211EC', $$Bound Battleaxe$$, $$Conjures a magical battleaxe from Oblivion.$$),
('000204C3', $$Conjure Flame Atronach$$, $$Summons a fiery elemental creature.$$),
('0009CE26', $$Flaming Familiar$$, $$Summons a flaming spirit that charges enemies and explodes.$$),
('00065BD7', $$Reanimate Corpse$$, $$Reanimates a more powerful corpse to serve you.$$),
('0004DBA4', $$Soul Trap$$, $$Captures the soul of a dying creature.$$),
('0006D22C', $$Banish Daedra$$, $$Sends summoned daedra back to Oblivion.$$),
('000211ED', $$Bound Bow$$, $$Conjures a magical bow and arrows from Oblivion.$$),
('000204C4', $$Conjure Frost Atronach$$, $$Summons an icy elemental creature.$$),
('00096D94', $$Revenant$$, $$Reanimates a powerful corpse to serve you.$$),
('0006F953', $$Command Daedra$$, $$Takes control of summoned or conjured creatures.$$),
('0010FC16', $$Conjure Dragon Priest$$, $$Summons a powerful undead dragon priest.$$),
('0010DDEC', $$Conjure Dremora Lord$$, $$Summons a mighty daedric warrior.$$),
('000204C5', $$Conjure Storm Atronach$$, $$Summons a crackling lightning elemental.$$),
('00096D95', $$Dread Zombie$$, $$Reanimates a very powerful corpse to serve you.$$),
('0006F952', $$Expel Daedra$$, $$Forcefully sends powerful summoned daedra back to Oblivion.$$),
('0007E5D5', $$Flame Thrall$$, $$Summons a permanent flame atronach companion.$$),
('0007E8DF', $$Dead Thrall$$, $$Reanimates a corpse to serve you indefinitely.$$),
('0007E5D6', $$Frost Thrall$$, $$Summons a permanent frost atronach companion.$$),
('0007E5D7', $$Storm Thrall$$, $$Summons a permanent storm atronach companion.$$),
('000AB23D', $$Spectral Arrow$$, $$Fires a ghostly arrow at your target.$$),
('0006A153', $$Summon Arniel''s Shade$$, $$Summons the ghostly shade of Arniel Gane.$$),
('00099F39', $$Summon Unbound Dremora$$, $$Summons an unbound dremora to perform a task.$$),

-- Spell Descriptions - Destruction School (Fire)
('00012FCD', $$Flames$$, $$A gout of fire that burns targets.$$),
('00012FD0', $$Firebolt$$, $$A bolt of fire that ignites enemies.$$),
('0005DB90', $$Fire Rune$$, $$A magical fire trap that explodes when enemies approach.$$),
('0001C789', $$Fireball$$, $$A fiery explosion that damages nearby enemies.$$),
('0003AE9F', $$Flame Cloak$$, $$Surrounds you with flames that burn nearby enemies.$$),
('0010F7ED', $$Incinerate$$, $$A powerful blast of searing fire.$$),
('00035D7F', $$Wall of Flames$$, $$Creates a wall of fire on the ground.$$),
('0007A82B', $$Fire Storm$$, $$A massive fiery explosion centered on you.$$),

-- Spell Descriptions - Destruction School (Frost)
('0002B96B', $$Frostbite$$, $$A blast of cold that freezes targets.$$),
('0002B96C', $$Ice Spike$$, $$A spike of ice that pierces and slows enemies.$$),
('0006796F', $$Frost Rune$$, $$A magical frost trap that explodes when enemies approach.$$),
('0003AEA2', $$Frost Cloak$$, $$Surrounds you with frost that freezes nearby enemies.$$),
('00045F9C', $$Ice Storm$$, $$A freezing whirlwind of ice and snow.$$),
('0010F7EC', $$Icy Spear$$, $$A spear of ice that impales enemies.$$),
('00035D80', $$Wall of Frost$$, $$Creates a wall of frost on the ground.$$),
('0007E8E4', $$Blizzard$$, $$A massive blizzard centered on you.$$),

-- Spell Descriptions - Destruction School (Shock)
('0002DD2A', $$Sparks$$, $$Lightning that shocks targets.$$),
('0002DD29', $$Lightning Bolt$$, $$A bolt of lightning that strikes enemies.$$),
('00067970', $$Lightning Rune$$, $$A magical lightning trap that explodes when enemies approach.$$),
('0003AEA3', $$Lightning Cloak$$, $$Surrounds you with lightning that shocks nearby enemies.$$),
('00045F9D', $$Chain Lightning$$, $$Lightning that leaps between multiple targets.$$),
('0010F7EE', $$Thunderbolt$$, $$A powerful bolt of thunder and lightning.$$),
('00035D81', $$Wall of Storms$$, $$Creates a wall of lightning on the ground.$$),
('0007E8E5', $$Lightning Storm$$, $$A continuous stream of devastating lightning.$$),

-- Spell Descriptions - Destruction School (Special)
('0006A104', $$Arniel''s Convection$$, $$A minor flame used for heating objects.$$),
('0008D5BF', $$Vampiric Drain$$, $$Absorbs health from living targets.$$),
('0008D5C0', $$Vampiric Drain$$, $$Absorbs health from living targets.$$),
('0008D5C1', $$Vampiric Drain$$, $$Absorbs health from living targets.$$),
('0008D5C2', $$Vampiric Drain$$, $$Absorbs health from living targets.$$),

-- Spell Descriptions - Illusion School
('00021143', $$Clairvoyance$$, $$Shows the path to your current objective.$$),
('0004DEE8', $$Courage$$, $$Emboldens a target, making them braver in combat.$$),
('0004DEEB', $$Fury$$, $$Enrages a target to attack anyone nearby.$$),
('0004DEE9', $$Calm$$, $$Calms aggressive creatures and people.$$),
('0004DEEA', $$Fear$$, $$Causes creatures and people to flee in terror.$$),
('0008F3EB', $$Muffle$$, $$Muffles the sound of your movements.$$),
('0004DEEE', $$Frenzy$$, $$Causes multiple targets to attack anyone nearby.$$),
('0004DEEC', $$Rally$$, $$Emboldens multiple targets for battle.$$),
('00027EB6', $$Invisibility$$, $$Renders you invisible.$$),
('0004DEED', $$Pacify$$, $$Calms aggressive creatures and people in an area.$$),
('0004DEEF', $$Rout$$, $$Causes multiple targets to flee in terror.$$),
('0007E8DD', $$Call to Arms$$, $$Strengthens allies for combat.$$),
('0007E8DB', $$Harmony$$, $$Calms all nearby creatures and people.$$),
('0007E8DE', $$Hysteria$$, $$Causes all nearby targets to flee in terror.$$),
('0007E8DA', $$Mayhem$$, $$Causes all nearby targets to attack each other.$$),
('000B323E', $$Vision of the Tenth Eye$$, $$Reveals hidden things.$$),

-- Spell Descriptions - Restoration School (Healing)
('00012FCC', $$Healing$$, $$Heals the caster over time.$$),
('0002F3B8', $$Fast Healing$$, $$Quickly heals the caster.$$),
('000B62EF', $$Close Wounds$$, $$Heals the caster significantly.$$),
('0004D3F2', $$Healing Hands$$, $$Heals another target over time.$$),
('00012FD2', $$Heal Other$$, $$Heals another target.$$),
('000B62EE', $$Grand Healing$$, $$Heals everyone near the caster.$$),

-- Spell Descriptions - Restoration School (Wards)
('00013018', $$Lesser Ward$$, $$Creates a protective barrier against magic.$$),
('000211F1', $$Steadfast Ward$$, $$Creates a stronger magical barrier.$$),
('000211F0', $$Greater Ward$$, $$Creates a powerful magical barrier.$$),

-- Spell Descriptions - Restoration School (Turn Undead)
('0004B146', $$Turn Lesser Undead$$, $$Causes weak undead to flee.$$),
('0005DD5D', $$Turn Undead$$, $$Causes undead to flee.$$),
('0005DD5E', $$Turn Greater Undead$$, $$Causes powerful undead to flee.$$),
('0004D3F8', $$Repel Lesser Undead$$, $$Causes all nearby weak undead to flee.$$),
('0005DD60', $$Repel Undead$$, $$Causes all nearby undead to flee.$$),
('0005312D', $$Circle of Protection$$, $$Creates a circle that repels undead.$$),
('0008C1AB', $$Bane of the Undead$$, $$Sets undead on fire and causes them to flee.$$),
('000E0CCF', $$Guardian Circle$$, $$Creates a circle that heals and repels undead.$$)
ON CONFLICT (baseid) DO NOTHING;

