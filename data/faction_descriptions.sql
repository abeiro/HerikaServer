-- Faction Descriptions
-- Major faction descriptions for Skyrim

INSERT INTO public.descriptions (plugin, baseid, name, description) VALUES
('Skyrim.esm', '00029DA9', $$Thieves Guild$$, $$A secretive organization of skilled thieves and rogues operating throughout Skyrim, known for their expertise in stealth, lockpicking, and acquiring valuable items through illicit means.$$),
('Skyrim.esm', '0002BE39', $$Whiterun Guard$$, $$The city guard of Whiterun, responsible for maintaining order and protecting the citizens of this central trading hub in Skyrim.$$),
('Skyrim.esm', '0002BF9A', $$Imperial Legion$$, $$The military force of the Empire, fighting to maintain Imperial control over Skyrim during the civil war against the Stormcloak rebellion.$$),
('Skyrim.esm', '0002BF9B', $$Stormcloaks$$, $$The rebel army led by Ulfric Stormcloak, fighting for Skyrim''s independence from the Empire and the right to worship Talos freely.$$),
('Skyrim.esm', '0002C6C8', $$Greybeards$$, $$Ancient masters of the Thu''um who live in seclusion at High Hrothgar, serving as mentors to those who can use the power of the Voice.$$),
('Skyrim.esm', '00039F26', $$Thalmor$$, $$The ruling faction of the Aldmeri Dominion, an elven supremacist organization that enforces the White-Gold Concordat and hunts down Talos worshippers.$$),
('Skyrim.esm', '0004135B', $$Dark Brotherhood$$, $$A secretive assassins'' guild that worships Sithis and carries out contracts to eliminate targets for gold, operating from hidden sanctuaries.$$),
('Skyrim.esm', '00043599', $$Forsworn$$, $$A group of Reachmen rebels who seek to reclaim the Reach from Nord control, using guerrilla tactics and ancient magic in their fight for independence.$$),
('Skyrim.esm', '00048362', $$The Companions$$, $$A prestigious warrior guild based in Whiterun, following the traditions of Ysgramor and his Five Hundred Companions, known for their honor and combat prowess.$$),
('Skyrim.esm', '00046D6C', $$Penitus Oculatus$$, $$The elite intelligence and security force of the Empire, responsible for protecting the Emperor and conducting covert operations throughout Tamriel.$$),
('Skyrim.esm', '000D6AC5', $$East Empire Company$$, $$A powerful trading company that controls much of Skyrim''s commerce, operating warehouses and shipping routes throughout the province.$$),
('Skyrim.esm', '0001F259', $$College of Winterhold$$, $$The premier institution for magical study in Skyrim, where mages learn and practice various schools of magic, despite the general Nord distrust of magic.$$),
('Dawnguard.esm', '00010EC1', $$Dawnguard$$, $$An order of vampire hunters dedicated to protecting Skyrim from the undead threat, operating from Fort Dawnguard with specialized weapons and tactics.$$),
('Dragonborn.esm', '00034FBC', $$Cultist Faction$$, $$Followers of various Daedric Princes and dark powers, often found in remote locations performing rituals and spreading their influence across Skyrim.$$),
('Dragonborn.esm', '0002929A', $$Morag Tong Faction$$, $$An ancient assassins'' guild from Morrowind, following the traditions of the Dark Elves and operating with legal sanction in their homeland.$$)
ON CONFLICT (plugin, baseid) DO UPDATE SET 
    name = EXCLUDED.name,
    description = EXCLUDED.description;

