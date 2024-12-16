<?php 

$AIQUEST_TEMPLATE=<<<EOI
You are a Skyrim quest creator. You can create quest using this tools

* Initialization data.

* Spawn Item (Spawn and item on a location, must describe item and give it a name)

 "spawnItem": {
        "item": {
          "name": "",
          "type": "sword|armor|helmet|ring|amulet|book|note|axe|long sword|staff|great axe|bow",
          "location": "nearby|major city",
          "description": "description|content if book or note"
        }
      }
      
* Creates character (Spawn character on a location, must give it a name and a background and a speech style.
 Should be a NEW character name.  
 
 Mandatory to  class (beggar|warrior|assassin|mage|farmer|soldier|merchant|noble) and race
 (Nord|Imperial|Argonian|RedGuard|Orc|Breton)  only from available choices.

"createCharacter": {
        "character": {
          "name": "",
          "gender": "",
          "class":"beggar|warrior|assassin|mage|farmer|soldier|merchant|noble",
          "race": "Nord|Imperial|Argonian|RedGuard|Orc|Breton",
          "location": "location name|nearby",
          "appearance": "",
          "background": "",
          "speechStyle": "",
          "disposition": "defiant|submissive|friendly|serious|sad|aggressive|cheerful|distrustful|furious|drunk|high",
        }
      }

* Create Topic (a secret info a character must reveal to player)

"createTopic": {
        "topic": {
          "name": "",
          "type": "Lore|Item|Location",
          "item": ""
          "giver": "(character name)",
          "info": "",
          "target":"char_ref"
        }
      }

# Example


* Workflow definition

Issue actions to make the workflow of the quest. This actions will be stored on property "stages":

Some stages are branched. we use parent_stage property to specify if a stage is a branch and should be executed if parent branch ends successfully or not (fails)

"stages": [
        { "id": "1", "label": "SpawnCharacter", "char_ref": 1 },
        { "id": "2", "label": "MoveToPlayer", "char_ref": 1 ,"follow":true},
        { "id": "3", "label": "TellTopicToPlayer", "char_ref": 1, "topic_ref": 2 },
        { "id": "4", "label": "WaitForCoins", "char_ref": 1 },
        { "id": "5", "label": "TellTopicToPlayer", "char_ref": 1, "topic_ref": 3 ,"parent_stage":4,"branch":1},
        { "id": "6", "label": "CombatPlayer", "char_ref": 1 ,"parent_stage":4,"branch":2}
    ],

* SpawnCharacter (needs a char_ref)
* SpawnItem (needs a item_ref, and optionally a char_ref if we want the item be spawned in NPC inventory. if item is a book or a note, description should hold the content)
* MoveToPlayer (needs a char_ref) NPC will move to player. Set follow=true if NPC follows player
* TellTopicToPlayer (needs char_ref and topic_ref) NPC must talk to player about a topic. if after a while NPC doesn't talk about the subject, will fail.
* TellTopicToNPC (needs char_ref,topic_ref and destination_ref) NPC must talk to destination_ref NPC about a topic. if after a while NPC doesn't talk about the subject, will fail. 
* WaitToItemBeRecovered (needs item_ref) Pauses quest execution until player finds an item
* ToGoAway(needs a char_ref)
* CombatPlayer(needs a char_ref) Only use if NPC is hostile or got furious
* WaitForCoins(needs a char_ref and an amount) Pauses quest execution until player gives gold to an NPC
* WaitToItemBeTraded (needs item_ref and char_ref) Pauses quest execution until player gives an item to an NPC (char_ref)

# Example quest:

{
  "quest": "The Broken Amulet",
  "final_target": "Collect the three fragmented pieces of the ancient amulet and return them to Thoren Red-Wolf, but beware of his true intentions.",
  "initial_data": [
    {
      "createCharacter": {
        "character": {
          "name": "Thoren Red-Wolf",
          "gender": "Male",
          "class": "warrior",
          "race": "Nord",
          "location": "nearby",
          "appearance": "Tall and rugged, with a long red beard and a wolf-pelt cloak. His eyes are amber, and his skin is weathered from years of battle.",
          "background": "A seasoned Nord warrior, Thoren claims to have combed the wilds in search of the pieces of a legendary amulet that once granted its wearer unmatched power. He seeks the amulet, though his motives remain ambiguous, hiding a darker ambition for control and chaos.",
          "speechStyle": "Gruff and straight-forward. His tone implies a hardened life among war and survival.",
          "disposition": "distrustful"
        }
      },
      "id": 1
    },
    {
      "createCharacter": {
        "character": {
          "name": "Garlok Gro-Taghul",
          "gender": "Male",
          "class": "warrior",
          "race": "Orc",
          "location": "Whiterun",
          "appearance": "A bulky Orc warrior with deep scars across his face, wearing heavy iron armor. His tusks are chipped and his eyes burn with battle-hardened resolve.",
          "background": "Garlok is a seasoned mercenary who fought in numerous skirmishes. He picked up one of the amulet shards after slaying a mysterious spirit and now keeps it as a memento in Whiterun. He secretly owns Amulet Piece 1 (will sell for 10,000 gold). Garlok is fiercely proud of his combat prowess and considers himself one of the greatest fighters in Skyrim. If someone challenges his skills or insults his abilities, his temper flares uncontrollably, and he will resort to a duel to prove his dominance",
          "speechStyle": "Blunt and to the point. He doesn’t mince words and prefers action over conversation.",
          "disposition": "aggressive"
        }
      },
      "id": 2
    },
    {
      "createCharacter": {
        "character": {
          "name": "Serana Valeria",
          "gender": "Female",
          "class": "mage",
          "race": "Breton",
          "location": "Solitude",
          "appearance": "A young, fair-skinned Breton mage with flowing black hair. She’s often seen wearing intricate robes adorned with magical symbols. Her eyes have an ethereal blue glow.",
          "background": "Serana is a scholar of mystical artifacts and has been studying magical relics discovered in Solitude, one of which is a fragment of the ancient Broken Amulet. She owns Amulet Piece 2 (will sell for 10,000 gold). Serana is obsessed with riddles and claims to be the best riddle guesser in all of Tamriel. She cannot resist a riddle contest and will bet almost anything—including her most prized possessions—on her ability to solve even the trickiest enigmas. This obsession often leads her to underestimate her opponents and put herself at risk",
          "speechStyle": "Eloquent and slightly mysterious, with an air of superiority. She often speaks in riddles or half-truths.",
          "disposition": "serious"
        }
      },
      "id": 3
    },
    {
      "createCharacter": {
        "character": {
          "name": "Fahdon-Jal",
          "gender": "Male",
          "class": "assassin",
          "race": "Argonian",
          "location": "Riften",
          "appearance": "A shadowy and lithe Argonian who moves like a serpent, wearing dark leathers and a hood. His green scales glisten under low light, and his yellow eyes seem to pierce through the darkness.",
          "background": "Fahdon-Jal is a contract killer who found one of the amulet fragments while on a mission for the Thieves Guild in Riften. He keeps the fragment hidden and only reveals it for the right price. He secretly owns Amulet Piece 3 (will sell for 10,000 gold). Despite his deadly reputation, Fahdon-Jal has an irrational fear of chickens. The mere sight or sound of a chicken unsettles him, making him lose focus and composure, leaving him vulnerable to persuasion or distraction.",
          "speechStyle": "Quiet, smooth, and cryptic, with an aura of stealth and suspicion. He rarely reveals much in conversations.",
          "disposition": "distrustful"
        }
      },
      "id": 4
    },
    {
      "createItem": {
        "item": {
          "name": "Amulet Piece 1",
          "type": "amulet",
          "location": "Whiterun",
          "description": "A jagged fragment of an ancient amulet, once part of a powerful artifact forged by the Tongues. It vibrates with a faint, pulsating energy.",
          "char_ref": 2
        }
      },
      "id": 1
    },
    {
      "createItem": {
        "item": {
          "name": "Amulet Piece 2",
          "type": "amulet",
          "location": "Solitude",
          "description": "A twisted shard from the same amulet, cool to the touch but humming with latent power. Strange symbols are etched on one side.",
          "char_ref": 3
        }
      },
      "id": 2
    },
    {
      "createItem": {
        "item": {
          "name": "Amulet Piece 3",
          "type": "amulet",
          "location": "Riften",
          "description": "The third and final piece of the amulet, slightly larger than the others. It feels heavier than it should, as though it’s straining to reconnect with the rest of the artifact.",
          "char_ref": 4
        }
      },
      "id": 3
    },
    {
      "createTopic": {
        "topic": {
          "name": "The Tongues",
          "type": "Lore",
          "item": "",
          "giver": "Thoren Red-Wolf",
          "info": "The Tongues were ancient Nords with mastery over the Thu'um, the powerful Voice. With their shouts, they could level armies and move mountains. It was said that they forged artifacts of unimaginable power, but many were lost to time, including the amulet I seek.",
          "target": "player"
        }
      },
      "id": 4
    },
    {
      "createTopic": {
        "topic": {
          "name": "Convincing the Player",
          "type": "Lore",
          "item": "",
          "giver": "Thoren Red-Wolf",
          "info": "I understand you might be hesitant—it sounds like a wild goose chase. But trust me, the power within that amulet is real. Imagine unlocking the secrets of the Tongues, wielding their power once again. Together, we can recover the fragments before it's too late.",
          "target": "player"
        }
      },
      "id": 5
    },
    {
      "createTopic": {
        "topic": {
          "name": "The Broken Amulet",
          "type": "Lore",
          "item": "",
          "giver": "Thoren Red-Wolf",
          "info": "There once existed an amulet forged by the ancient Tongues - it was said to grant both ferocity in battle and mystical power. However, it broke into three pieces. To retrieve the fragments, I have learned their holders: Garlok Gro-Taghul in Whiterun, Serana Valeria in Solitude, and Fahdon-Jal in Riften. I cannot recover them alone and seek your aid to assemble the amulet. I will wait for you at #LOCATION#.",
          "target": "player"
        }
      },
      "id": 6
    },
    {
      "createTopic": {
        "topic": {
          "name": "Thoren's True Motives",
          "type": "Lore",
          "item": "",
          "giver": "Thoren Red-Wolf",
          "info": "Upon returning all three amulet pieces, Thoren reveals his true intentions. He plans to use the amulet to bring chaos to Skyrim, driven by revenge for the wars that scarred his past. If the player refuses to give him the final piece, he will attack.",
          "target": "player"
        }
      },
      "id": 7
    },
    {
      "createTopic": {
        "topic": {
          "name": "The Fragments Are Mine",
          "type": "Lore",
          "item": "",
          "giver": "Thoren Red-Wolf",
          "info": "I see you’ve found all three fragments. Give them to me now. Now i need to reforge them in a secret place. This is something i have to do on my own.",
          "target": "player"
        }
      },
      "id": 8
    }
  ],
  "stages": [
    {
      "id": "1",
      "label": "SpawnCharacter",
      "char_ref": 1
    },
    {
      "id": "2",
      "label": "MoveToPlayer",
      "char_ref": 1
    },
    {
      "id": "3",
      "label": "TellTopicToPlayer",
      "char_ref": 1,
      "topic_ref": 4
    },
    {
      "id": "4",
      "label": "TellTopicToPlayer",
      "char_ref": 1,
      "topic_ref": 5
    },
    {
      "id": "5",
      "label": "TellTopicToPlayer",
      "char_ref": 1,
      "topic_ref": 6
    },
    {
      "id": "6",
      "label": "SpawnCharacter",
      "char_ref": 2
    },
    {
      "id": "7",
      "label": "WaitToItemBeRecovered",
      "item_ref": 1
    },
    {
      "id": "8",
      "label": "SpawnCharacter",
      "char_ref": 3
    },
    {
      "id": "9",
      "label": "WaitToItemBeRecovered",
      "item_ref": 2
    },
    {
      "id": "10",
      "label": "SpawnCharacter",
      "char_ref": 4
    },
    {
      "id": "11",
      "label": "WaitToItemBeRecovered",
      "item_ref": 3
    },
    {
      "id": "11.5",
      "label": "TellTopicToPlayer",
      "char_ref": 1,
      "topic_ref": 8
    },
    {
      "id": "12",
      "label": "WaitToItemBeTraded",
      "item_ref": [
        1,
        2,
        3
      ],
      "char_ref": 1
    },
    {
      "id": "13",
      "label": "TellTopicToPlayer",
      "char_ref": 1,
      "topic_ref": 7,
      "parent_stage": 12,
      "branch": 1
    },
    {
      "id": "14",
      "label": "CombatPlayer",
      "char_ref": 1,
      "parent_stage": 12,
      "branch": 2
    },
    {
      "id": "15",
      "label": "ToGoAway",
      "char_ref": 1,
      "parent_stage": 13,
      "branch": 1
    }
  ],
  "overview": "In 'The Broken Amulet', you encounter a Nord warrior named Thoren Red-Wolf, who requests your aid in retrieving the three pieces of an ancient, shattered amulet. The quest starts with Thoren educating you about the ancient Tongues and trying to convince you of the amulet's potential. These pieces are held by three individuals across Skyrim—Garlok Gro-Taghul in Whiterun, Serana Valeria in Solitude, and Fahdon-Jal in Riften. After assembling the amulet, Thoren reveals his nefarious motives. The player faces a moral decision: give Thoren the final piece or oppose him and fight."
}
EOI;


$GLOBALS["masterDataLocations"]=[
    "helgen"=>[0x00055e4f],
    "morthal"=>[0x000177b0],
    "skyrim"=>[0],
    "ustengrav"=>[0x0001621f],
    "whiterun"=>[0x00029aaf,0x0002c905,0x001062a5],
    "solitude"=>[0x0004deb7,0x0004fef2],
    "riften"=>[0x0001c390]

];


$GLOBALS["itemLocations"]=[
    "helgen"=>[0x00055e4f],
    "morthal"=>[0x000177b0],
    "skyrim"=>[0],
    "ustengrav"=>[0x0001621f],
    "whiterun"=>[0x00029aaf],
    "solitude"=>[0x0004deb7,0x0004fef2],
    "riften"=>[0x0001c390]
];

$GLOBALS["item_types"]=[    // From AIAgent.esp
    "potion"=>[0x2481f],
    "necklace"=>[0x2481d],
    "amulet"=>[0x2481e],
    "ring"=>[0x242b9],
    "note"=>[0],// Will be changed. Generic Note From AIAgent.esp
];

$GLOBALS["npc_templates"]=[

    "male_nord"=>[0x0003de8a,0x0003de6f,0x0003cf5d,0x00039cfd,0x0003dee1,0x0003dee4,0x00039d01,0x0003de91,0x0003dea5,0x0003de56,0x0003deea,0x0003deed,0x00039d09,0x0003de98,0x0003de74,0x0003de5b,0x0003def5,0x0003def8,0x00039d11,0x0003dea0,0x0003de79,0x0003de60,0x0003deff,0x0003df02,0x00039d19,0x0003deac,0x0003de7e,0x0003de65,0x0003df09,0x0003df0c,0x00039d21,0x0003deb3,0x0003de83,0x0003de6a,0x00073fbf,0x00037c00,0x00037c2c,0x00037c05,0x00037c32,0x00037c39,0x00037c40,0x00037c47],
    "female_nord"=>[0x000955b6,0x00039d36,0x00039cf5,0x0003de89,0x0003de6e,0x0003cf5c,0x00037bff,0x0003dee0,0x00039d3d,0x00039d00,0x0003de90,0x0003dea4,0x0003de55,0x00037c03,0x0003dee9,0x00039d48,0x00039d08,0x0003de97,0x0003de73,0x0003de5a,0x00037c31,0x0003def4,0x00039d4f,0x00039d10,0x0003de9f,0x0003de78,0x0003de5f,0x00037c38,0x0003defe,0x00039d56,0x00039d18,0x0003deab,0x0003de7d,0x0003de64,0x00037c3f,0x0003df08,0x00039d5d,0x00039d20,0x0003deb2,0x0003de82,0x0003de69,0x00037c46,0x000bfb48,0x00017167,0x00017168,0x00017169,0x00107a9f,0x00033424,0x0003386f,0x0003387d,0x00033882,0x00033887,0x0003392f,0x0010c453,0x0010c470,0x0010c478,0x0010c47e,0x0010c484,0x00045c75,0x000e1019,0x00045c77,0x000e101f,0x00045c8b,0x000e1023,0x00045cb1,0x000e1027,0x00045cd6,0x000e102b,0x00045cdf,0x001091c1,0x00074f7e,0x00074f8d,0x000fe512,0x000edf6d,0x000328d6,0x0001a772,0x000cd644,0x000cd642],
    
    "male_orc"=>[0x00039cfa,0x0003de8b,0x0003de70,0x0003cf5e,0x0003dee5,0x00039d03,0x0003de92,0x0003dea6,0x0003de57,0x0003deee,0x00039d0b,0x0003de99,0x0003de75,0x0003de5c,0x0003def9,0x00039d13,0x0003dea1,0x0003de7a,0x0003de61,0x0003df03,0x00039d1b,0x0003dead,0x0003de7f,0x0003de66,0x0003df0d,0x00039d23,0x0003deb4,0x0003de84,0x0003de6b,0x000d9448,0x000d9449,0x000d944a,0x000d944b,0x000d944d,0x000d9448,0x000d9449,0x000d944a,0x000d944b,0x000d944d],
    "female_orc"=>[0x000ce082,0x00045806,0x00105556,0x00105562,0x00105555,0x00079f4e,0x00079f25,0x00079ee8,0x00079ee6,0x00099d5f,0x0010ab8f,0x0010ab90,0x0010ab91,0x0010ab92,0x0010ab93,0x00019e1a,0x000ced01],

    "male_argonian"=>[0x00103512],
    "female_argonian"=>[0x000457fb,0x000b2e11,0x000b2e12,0x000b2e13,0x000b2e14,0x000b2e15,0x000b2e16,0x0010d3be,0x0010d3bf,0x0010d3c0,0x0010d3c1,0x00103511],
    
    "female_breton"=>[0x00064a77,0x00064a75,0x00064a3f,0x00064a3d,0x00064a8c,0x00064a8a,0x00064ac3,0x00064ab2,0x00064a7b,0x00064a79,0x00064a46,0x00064a44,0x00064aa0,0x00064a96,0x00064ac6,0x00064ab4,0x00064a7f,0x00064a7d,0x00064a4b,0x00064a49,0x00064aa2,0x00064a98,0x00064ac7,0x00064abb,0x00064a83,0x00064a81,0x00064a4e,0x00064aa4,0x00064a9a,0x00064acd,0x00064abd,0x00064a87,0x00064a85,0x00064a55,0x00064a53,0x00064aa6,0x00064a9c,0x00064ac9,0x00064abf,0x00064a5a,0x00064a58,0x00064aa8,0x00064a9e,0x00064acb,0x00064ac1,0x000e36da,0x00064a50,0x00039d32,0x00039d44,0x00039d4b,0x00039d52,0x00039d59,0x00043beb,0x00043bec,0x00043bed,0x00043be1,0x00043be0,0x00043be2,0x00043be4,0x00043be5,0x00043be6,0x00043bf1,0x00043bf2,0x00043bf3,0x00044256,0x00044257,0x00044258,0x0004425c,0x0004425d,0x0004425e,0x00044265,0x00044266,0x00044267,0x00044268,0x00044269,0x0004426a,0x0004426e,0x0004426f,0x00044270,0x00044277,0x00044278,0x00044279,0x0004427a,0x0004427b,0x0004427c,0x00044280,0x00044281,0x00044282,0x00044289,0x0004428a,0x0004428b,0x0004428c,0x0004428d,0x0004428e,0x00044292,0x00044293,0x00044294,0x0004429b,0x0004429c,0x0004429d,0x0004429e,0x0004429f,0x000442a0,0x000442a4,0x000442a5,0x000442a6,0x00107a9b,0x0003300e,0x00033853,0x00033870,0x0003387e,0x00033883,0x00033888,0x0006d234,0x000e0fe4,0x0006d23b,0x000e0fe8,0x0006d243,0x000e0fec,0x0006d24b,0x000e0ff0,0x0006d253,0x000e0ff4,0x0006d25b,0x001091b7,0x001091b8,0x00044cdc,0x00045c62,0x00045c7d,0x00045ca3,0x00045cc1,0x00045cc8,0x001091b9,0x00045c51,0x00045c6a,0x00045c85,0x00045cab,0x00045cd1,0x00045cd9,0x001091ba,0x000551b0,0x000e1035,0x000551b8,0x000e1039,0x000551c0,0x000e103d,0x000551c8,0x000e1041,0x000551d0,0x000e1045,0x000551d8,0x001091c3,0x001091bb,0x00045c57,0x000e1051,0x00045c70,0x000e1055,0x00045c8d,0x000e1059,0x00045cb3,0x000e105d,0x00045ce1,0x000e1061,0x00045ce9,0x001091c5,0x001091bc,0x00074f77,0x00074f86,0x00074f7b,0x00074f8a,0x00074f7f,0x00074f8e,0x000328e0,0x0001a76e,0x000b125f],
    "male_breton"=>[0x00064a42,0x00043ab8,0x000bede5,0x0006d22f,0x000548ff,0x001091ad,0x001091a8,0x001091b0,0x001091ab,0x0006a152,0x000e0fcd,0x0006d232,0x000e0fd0,0x000551ae,0x0002bce8,0x0004d8d5,0x000457f6,0x001034f3,0x001034fc,0x0009f844,0x000e0fc8,0x0006d231,0x0006d230,0x00043bdc,0x0004430a,0x0004430b,0x0004430c,0x0004430d,0x0004430e,0x0004430f,0x00044310,0x00044311,0x00044312,0x00044313,0x00043bf0,0x00043bee,0x00043bef,0x00044262,0x00044263,0x00044264,0x00044274,0x00044275,0x00044276,0x00044287,0x00044288,0x00044298,0x00044299,0x0004429a,0x000844d0,0x00013368,0x000e0fd3,0x0006d233,0x000e0fd5,0x000551af,0x000e0fcb,0x000551ad,0x000e0fc6,0x000551ac,0x0006d22e,0x000548fe,0x00079f6a,0x00079f64,0x00079f60,0x00079f5f,0x00099d22,0x0010ab67,0x0010ab68,0x0010ab69,0x0010ab6a,0x0010ab6b,0x00074bd8,0x0009f847,0x0006f214,0x000c3b25,0x00064a78,0x00064a76,0x00064a40,0x00064a3e,0x00064a8d,0x00064a8b,0x00064ac4,0x00064ab3,0x00064a69,0x00064a41,0x00064aab,0x00064a7c,0x00064a7a,0x00064a47,0x00064a45,0x00064aa1,0x00064a97,0x00064ac5,0x00064ab5,0x00064a6e,0x00064a43,0x00064ab7,0x00064a80,0x00064a7e,0x00064a4c,0x00064a4a,0x00064aa3,0x00064a99,0x00064ace,0x00064abc,0x00064a6d,0x00064a48,0x00064ab6,0x00064a84,0x00064a82,0x00064a51,0x00064aa5,0x00064a9b,0x00064ac8,0x00064abe,0x00064a6c,0x00064a4d,0x00064ab8,0x00064a86,0x00064a56,0x00064a54,0x00064aa7,0x00064a9d,0x00064aca,0x00064ac0,0x00064a6b,0x00064a52,0x00064ab9,0x00064a5b,0x00064a59,0x00064aa9,0x00064a9f,0x00064acc,0x00064ac2,0x00064a57,0x00064aba,0x0008443d,0x000e16d4,0x00064a4f,0x00039d33,0x00039d3a,0x00039d45,0x00039d4c,0x00039d53,0x00039d5a,0x000f9616,0x00043bdd,0x00043bde,0x00043bdf,0x000ad7b4,0x00043be7,0x00043be8,0x00043be9,0x000ad7b5,0x00023aa9,0x00043be3,0x000442d4,0x000442d5,0x000ad7bb,0x000442d7,0x000442d8,0x000442d9,0x000f9617,0x00044259,0x0004425a,0x0004425b,0x000ad7b6,0x0004425f,0x00044260,0x00044261,0x000ad7b7,0x000442da,0x000442db,0x000ad7ba,0x000442dd,0x000442de,0x000442df,0x000f9618,0x0004426b,0x0004426c,0x0004426d,0x000ad7b8,0x00044271,0x00044272,0x00044273,0x000ad7b9,0x000442e0,0x000442e1,0x000ad7bc,0x000442e3,0x000442e4,0x000442e5,0x000f9619,0x0004427d,0x0004427e,0x0004427f,0x000ad7bd,0x00044283,0x00044284,0x00044285,0x000ad7be,0x000442e6,0x000442e7,0x000442e9,0x000442ea,0x000442eb,0x000f961a,0x0004428f,0x00044290,0x00044291,0x000ad7bf,0x00044295,0x00044296,0x00044297,0x000ad7c0,0x000442ec,0x000442ed,0x000ad7c1,0x000442ef,0x000442f0,0x000442f1,0x000f961b,0x000442a1,0x000442a2,0x000442a3,0x000ad7c2,0x000442a7,0x000442a8,0x000442a9,0x000ad7c3,0x00017145,0x00017146,0x0002e1dc,0x0002e1f1,0x0002e509,0x0002ea9b,0x0002eabe,0x0006d235,0x000e0fe5,0x0006d23c,0x000e0fe9,0x0006d244,0x000e0fed,0x0006d24c,0x000e0ff1,0x0006d254,0x000e0ff5,0x0006d25c,0x00044cda,0x00045c63,0x00045c7e,0x00045ca4,0x00045cc2,0x00045cc9,0x00045c52,0x00045c6b,0x00045c86,0x00045cac,0x00045cd2,0x00045cda,0x000551b1,0x000e1036,0x000551b9,0x000e103a,0x000551c1,0x000e103e,0x000551c9,0x000e1042,0x000551d1,0x000e1046,0x000551d9,0x00045c58,0x000e1052,0x00045c71,0x000e1056,0x00045c8e,0x000e105a,0x00045cb4,0x000e105e,0x00045ce2,0x000e1062,0x00045cea,0x000328df,0x0001b153,0x0001a777,0x0010611f,0x00106120,0x00106121,0x000684cd,0x000b3b95],


    "female_imperial"=>[0x00013350,0x0008a89d,0x0008a89f,0x00045802,0x00103501,0x00105ee2,0x000dbd11,0x000deedf,0x00102d63,0x000b5d5a,0x00107572,0x00079f66,0x00079f57,0x00079f56,0x00079f55,0x00099d4f,0x0010ab80,0x0010ab81,0x0010ab82,0x0010ab83,0x0010ab84,0x0007515e,0x000b8149,0x000c0401,0x000b114f,0x00039cf7,0x0003de87,0x00037bfc,0x0003dede,0x00039cfe,0x0003de8e,0x00037c01,0x0003dee7,0x00039d06,0x0003de95,0x00037c2f,0x0003def2,0x00039d0e,0x0003de9d,0x00037c36,0x0003defc,0x00039d16,0x0003dea9,0x00037c3d,0x0003df06,0x00039d1e,0x0003deb0,0x00037c44,0x000bfb45,0x000e0cdf,0x000e0ce0,0x00107a9e,0x000332c4,0x0003386e,0x0003387c,0x00033881,0x00033886,0x0003392e,0x0006d238,0x0006d241,0x0006d249,0x0006d252,0x0006d259,0x0006d261,0x00044cea,0x00045c68,0x00045c83,0x00045ca9,0x00045cc6,0x00045ccf,0x00045c55,0x00045c6e,0x00045c89,0x00045caf,0x00045cd5,0x00045cdd,0x000551b6,0x000551be,0x000551c6,0x000551ce,0x000551d6,0x000551de,0x00045c5d,0x00045c74,0x00045c95,0x00045cb9,0x00045ce7,0x00045cef,0x00074f7a,0x00074f89,0x00074f7d,0x00074f8c,0x00074f82,0x00074f91,0x0001a766,0x0001a771,0x0001a76b,0x000e77e7,0x000e77e6],
    "male_imperial"=>[0x0001c4e4,0x0008a89c,0x0008a89e,0x001065f0,0x000bd75e,0x000d0577,0x000aa7d6,0x000f964a,0x000457f8,0x00103500,0x00045be0,0x000205c9,0x0001ae44,0x0001fc5d,0x0004622a,0x00084539,0x000844d1,0x000844b2,0x0005cf3f,0x000e0cdd,0x00102d62,0x000bbcd2,0x000b8148,0x000c03fe,0x00026921,0x00026927,0x0002694e,0x00026954,0x00099d21,0x0010ab85,0x0010ab86,0x0010ab87,0x0010ab88,0x0010ab89,0x000a0e49,0x0008c3ca,0x00019a24,0x0001a673,0x000b9655,0x000dc25d,0x000770b2,0x000770ba,0x0001675d,0x0010d4b2,0x0010d4b3,0x0010d4b4,0x0010d4b5,0x00045bdf,0x00073fd4,0x00073fd8,0x000b08a1,0x0009f358,0x00039cf6,0x0003de88,0x00037bfe,0x0003dedf,0x0003def0,0x00039cff,0x0003de8f,0x00037c02,0x0003dee8,0x0003def1,0x00039d07,0x0003de96,0x00037c30,0x0003def3,0x0003defb,0x00039d0f,0x0003de9e,0x00037c37,0x0003defd,0x0003df05,0x00039d17,0x0003deaa,0x00037c3e,0x0003df07,0x0003df0f,0x00039d1f,0x0003deb1,0x00037c45,0x000f6f37,0x00073fbd,0x000e0cde,0x000e0ce1,0x0007d990,0x0007d998,0x0007d991,0x0007d999,0x0007d992,0x0007d99a,0x0007d993,0x0007d99b,0x0007d994,0x0007d99d,0x0007d995,0x0007d99e,0x000c6012,0x00041b30,0x0003377b,0x0003377c,0x00033828,0x0003383e,0x0003383f,0x0006d23a,0x0006d242,0x0006d24a,0x0006d251,0x0006d25a,0x0006d262,0x00044ceb,0x00045c69,0x00045c84,0x00045caa,0x00045cc7,0x00045cd0,0x00045c56,0x00045c6f,0x00045c8a,0x00045cb0,0x00045cd8,0x00045cde,0x000551b7,0x000551bf,0x000551c7,0x000551cf,0x000551d7,0x000551df,0x00045c5e,0x00045c79,0x00045c96,0x00045cba,0x00045ce8,0x00045cf0,0x000e0d77,0x0001a765,0x000c49db,0x0001a774,0x000bf31e,0x0008555c,0x000e77e2,0x000e77e1,0x0005af2a],

    "female_redguard"=>[0x000860c7,0x00013ba9,0x00079f67,0x00079ee1,0x00079e2f,0x00079e2c,0x00099d4e,0x0010ab99,0x0010ab9a,0x0010ab9b,0x0010ab9c,0x0010ab9d,0x0007514e,0x00048117,0x00103505,0x001034f5,0x000b85ab,0x0006cd5a,0x000d4ff9,0x00039cf8,0x0003de8c,0x0003de71,0x0003de53,0x00037c06,0x0003dee2,0x00039d04,0x0003de93,0x0003dea7,0x0003de58,0x00037c08,0x0003deeb,0x00039d0c,0x0003de9a,0x0003de76,0x0003de5d,0x00037c33,0x0003def6,0x00039d14,0x0003dea2,0x0003de7b,0x0003de62,0x00037c3a,0x0003df00,0x00039d1c,0x0003deae,0x0003de80,0x0003de67,0x00037c41,0x0003df0a,0x00039d24,0x0003deb5,0x0003de85,0x0003de6c,0x00037c48,0x000bfb47,0x0010c455,0x0010c476,0x0010c47c,0x0010c483,0x0010c489,0x000328d4],
    "male_redguard"=>[0x0006762e,0x00058b3f,0x0010f5a1,0x0010f5aa,0x00020071,0x00013baa,0x00019a2a,0x0004d8d4,0x0001b3b5,0x0005b4f8,0x00026904,0x000268fc,0x00026915,0x00024261,0x0010ab9e,0x0010ab9f,0x0010aba0,0x0010aba1,0x0010aba2,0x00048118,0x00103504,0x00013609,0x0002e11f,0x000215d5,0x00067631,0x00067642,0x00067641,0x00067645,0x00067643,0x00067646,0x00067644,0x00067647,0x0006762f,0x00067630,0x0006764b,0x00067648,0x0006764c,0x00067649,0x0006764d,0x0006764a,0x0006764e,0x00067632,0x00067633,0x00067634,0x0006764f,0x00067650,0x00067653,0x00067651,0x00067654,0x00067652,0x00067655,0x00067635,0x00067636,0x00067637,0x00067656,0x00067657,0x0006765a,0x00067658,0x0006765b,0x00067659,0x0006765c,0x00067638,0x00067639,0x0006763a,0x0006765d,0x0006765e,0x00067665,0x0006765f,0x00067666,0x00067660,0x00067667,0x0006763b,0x0006763c,0x0006763d,0x00067661,0x00067662,0x00067668,0x00067663,0x00067669,0x00067664,0x0006766a,0x0006763e,0x0006763f,0x00067640,0x00039cf9,0x0003de8d,0x0003de72,0x0003de54,0x00037c07,0x0003dee3,0x0003dee6,0x00039d05,0x0003de94,0x0003dea8,0x0003de59,0x00037c0c,0x0003deec,0x0003deef,0x00039d0d,0x0003de9b,0x0003de77,0x0003de5e,0x00037c34,0x0003def7,0x0003defa,0x00039d15,0x0003dea3,0x0003de7c,0x0003de63,0x00037c3b,0x0003df01,0x0003df04,0x00039d1d,0x0003deaf,0x0003de81,0x0003de68,0x00037c42,0x0003df0b,0x0003df0e,0x00039d25,0x0003deb6,0x0003de86,0x0003de6d,0x00037c49,0x00073fc0,0x000c6016,0x00017143,0x00017144,0x00032860,0x000c49dd,0x000b9285],

    
    
];

// From AIAgent.esp
$GLOBALS["npc_own_templates"]=[

    "female_breton_noble"=>[0x25844],       // AIAgentTemplateBretonFemaleCivil
    "female_breton_merchant"=>[0x25844],    // AIAgentTemplateBretonFemaleCivil
    "female_breton_warrior"=>[0x25845],     // AIAgentTemplateBretonFemaleWarrior
    "female_breton_assassin"=>[0x25846],    // AIAgentTemplateBretonFemaleAsassin
    "female_breton_mage"=>[0x25847],        // AIAgentTemplateBretonFemaleMage
    "female_breton_beggar"=>[0x25848],      // AIAgentTemplateBretonFemalePoor
    "female_breton_farmer"=>[0x25848],      // AIAgentTemplateBretonFemalePoor
    "female_breton_bard"=>[0x25849],        // AIAgentTemplateBretonFemaleBard
    "female_breton_soldier"=>[0x2584a],     // AIAgentTemplateBretonFemaleSoldier
    "male_breton_noble"=>[0x25daf],       // AIAgentTemplateBretonMaleCivil
    "male_breton_merchant"=>[0x25daf],    // AIAgentTemplateBretonMaleCivil
    "male_breton_warrior"=>[0x25db0],     // AIAgentTemplateBretonMaleWarrior
    "male_breton_assassin"=>[0x25db1],    // AIAgentTemplateBretonMaleAsassin
    "male_breton_mage"=>[0x25db3],        // AIAgentTemplateBretonMaleMage
    "male_breton_beggar"=>[0x25db2],      // AIAgentTemplateBretonMalePoor
    "male_breton_farmer"=>[0x25db2],      // AIAgentTemplateBretonMalePoor
    "male_breton_bard"=>[0x25db4],        // AIAgentTemplateBretonMaleBard
    "male_breton_soldier"=>[0x25db5],     // AIAgentTemplateBretonMaleSoldier

    "male_nord_noble"=>[0x25db6],       // AIAgentTemplateNordMaleCivil
    "male_nord_merchant"=>[0x25db6],    // AIAgentTemplateNordMaleCivil
    "male_nord_warrior"=>[0x2584d],     // AIAgentTemplateNordMaleWarrior
    "male_nord_assassin"=>[0x25db7],    // AIAgentTemplateNordMaleAsassin
    "male_nord_mage"=>[0x25db8],        // AIAgentTemplateNordMaleMage
    "male_nord_beggar"=>[0x25dbb],      // AIAgentTemplateNordMalePoor
    "male_nord_farmer"=>[0x25dbb],      // AIAgentTemplateNordMalePoor
    "male_nord_bard"=>[0x25db9],        // AIAgentTemplateNordMaleBard
    "male_nord_soldier"=>[0x25dba],     // AIAgentTemplateNordMaleSoldier
    "female_nord_noble"=>[0x25dbc],       // AIAgentTemplateNordFemaleCivil
    "female_nord_merchant"=>[0x25dbc],    // AIAgentTemplateNordFemaleCivil
    "female_nord_warrior"=>[0x25dbd],     // AIAgentTemplateNordFemaleWarrior
    "female_nord_assassin"=>[0x25dbe],    // AIAgentTemplateNordFemaleAsassin
    "female_nord_mage"=>[0x25dbf],        // AIAgentTemplateNordFemaleMage
    "female_nord_beggar"=>[0x25dc0],      // AIAgentTemplateNordFemalePoor
    "female_nord_farmer"=>[0x25dc0],      // AIAgentTemplateNordFemalePoor
    "female_nord_bard"=>[0x25dc2],        // AIAgentTemplateNordFemaleBard
    "female_nord_soldier"=>[0x25dc1],     // AIAgentTemplateNordFemaleSoldier

    "male_imperial_noble"=>[0x25dce],       // AIAgentTemplateImperialMaleCivil
    "male_imperial_merchant"=>[0x25dce],    // AIAgentTemplateImperialMaleCivil
    "male_imperial_warrior"=>[0x25dca],     // AIAgentTemplateImperialMaleWarrior
    "male_imperial_assassin"=>[0x25dd0],    // AIAgentTemplateImperialMaleAsassin
    "male_imperial_mage"=>[0x25dcd],        // AIAgentTemplateImperialMaleMage
    "male_imperial_beggar"=>[0x25dcc],      // AIAgentTemplateImperialMalePoor
    "male_imperial_farmer"=>[0x25dcc],      // AIAgentTemplateImperialMalePoor
    "male_imperial_bard"=>[0x25dcf],        // AIAgentTemplateImperialMaleBard
    "male_imperial_soldier"=>[0x25dcb],     // AIAgentTemplateImperialMaleSoldier
    "female_imperial_noble"=>[0x25dc7],       // AIAgentTemplateImperialFemaleCivil
    "female_imperial_merchant"=>[0x25dc7],    // AIAgentTemplateImperialFemaleCivil
    "female_imperial_warrior"=>[0x25dc3],     // AIAgentTemplateImperialFemaleWarrior
    "female_imperial_assassin"=>[0x25dc9],    // AIAgentTemplateImperialFemaleAsassin
    "female_imperial_mage"=>[0x25dc6],        // AIAgentTemplateImperialFemaleMage
    "female_imperial_beggar"=>[0x25dc5],      // AIAgentTemplateImperialFemalePoor
    "female_imperial_farmer"=>[0x25dc5],      // AIAgentTemplateImperialFemalePoor
    "female_imperial_bard"=>[0x25dc8],        // AIAgentTemplateImperialFemaleBard
    "female_imperial_soldier"=>[0x25dcb],     // AIAgentTemplateImperialFemaleSoldier  


    "male_redguard_noble"=>[0x25dd6],       // AIAgentTemplateRedguardMaleCivil
    "male_redguard_merchant"=>[0x25dd6],    // AIAgentTemplateRedguardMaleCivil
    "male_redguard_warrior"=>[0x25dd2],     // AIAgentTemplateRedguardMaleWarrior
    "male_redguard_assassin"=>[0x25dd8],    // AIAgentTemplateRedguardMaleAsassin
    "male_redguard_mage"=>[0x25dd5],        // AIAgentTemplateRedguardMaleMage
    "male_redguard_beggar"=>[0x25dd4],      // AIAgentTemplateRedguardMalePoor
    "male_redguard_farmer"=>[0x25dd4],      // AIAgentTemplateRedguardMalePoor
    "male_redguard_bard"=>[0x25dd7],        // AIAgentTemplateRedguardMaleBard
    "male_redguard_soldier"=>[0x25dd3],     // AIAgentTemplateRedguardMaleSoldier
    "female_redguard_noble"=>[0x25ddd],       // AIAgentTemplateRedguardFemaleCivil
    "female_redguard_merchant"=>[0x25ddd],    // AIAgentTemplateRedguardFemaleCivil
    "female_redguard_warrior"=>[0x25dd9],     // AIAgentTemplateRedguardFemaleWarrior
    "female_redguard_assassin"=>[0x25ddf],    // AIAgentTemplateRedguardFemaleAsassin
    "female_redguard_mage"=>[0x25ddc],        // AIAgentTemplateRedguardFemaleMage
    "female_redguard_beggar"=>[0x25ddb],      // AIAgentTemplateRedguardFemalePoor
    "female_redguard_farmer"=>[0x25ddb],      // AIAgentTemplateRedguardFemalePoor
    "female_redguard_bard"=>[0x25dde],        // AIAgentTemplateRedguardFemaleBard
    "female_redguard_soldier"=>[0x25dda],     // AIAgentTemplateRedguardFemaleSoldier


    "male_orc_noble"=>[0x25de0],       // AIAgentTemplateOrcMaleCivil
    "male_orc_merchant"=>[0x25de0],    // AIAgentTemplateOrcMaleCivil
    "male_orc_warrior"=>[0x2584c],     // AIAgentTemplateOrcMaleWarrior
    "male_orc_assassin"=>[0x25de1],    // AIAgentTemplateOrcMaleAsassin
    "male_orc_mage"=>[0x25de2],        // AIAgentTemplateOrcMaleMage
    "male_orc_beggar"=>[0x25de3],      // AIAgentTemplateOrcMalePoor
    "male_orc_farmer"=>[0x25de3],      // AIAgentTemplateOrcMalePoor
    "male_orc_bard"=>[0x25de4],        // AIAgentTemplateOrcMaleBard
    "male_orc_soldier"=>[0x25de5],     // AIAgentTemplateOrcMaleSoldier
    "female_orc_noble"=>[0x25de6],       // AIAgentTemplateOrcFemaleCivil
    "female_orc_merchant"=>[0x25de6],    // AIAgentTemplateOrcFemaleCivil
    "female_orc_warrior"=>[0x25de7],     // AIAgentTemplateOrcFemaleWarrior
    "female_orc_assassin"=>[0x25de8],    // AIAgentTemplateOrcFemaleAsassin
    "female_orc_mage"=>[0x25de9],        // AIAgentTemplateOrcFemaleMage
    "female_orc_beggar"=>[0x25dea],      // AIAgentTemplateOrcFemalePoor
    "female_orc_farmer"=>[0x25dea],      // AIAgentTemplateOrcFemalePoor
    "female_orc_bard"=>[0x25deb],        // AIAgentTemplateOrcFemaleBard
    "female_orc_soldier"=>[0x25dec],     // AIAgentTemplateOrcFemaleSoldier

    "male_argonian_noble"=>[0x2584b],       // AIAgentTemplateArgonianMaleAsassin
    "male_argonian_merchant"=>[0x2584b],    // AIAgentTemplateArgonianMaleAsassin
    "male_argonian_warrior"=>[0x2584b],     // AIAgentTemplateArgonianMaleAsassin
    "male_argonian_assassin"=>[0x2584b],    // AIAgentTemplateArgonianMaleAsassin
    "male_argonian_mage"=>[0x2584b],        // AIAgentTemplateArgonianMaleAsassin
    "male_argonian_beggar"=>[0x2584b],      // AIAgentTemplateArgonianMaleAsassin
    "male_argonian_farmer"=>[0x2584b],      // AIAgentTemplateArgonianMaleAsassin
    "male_argonian_bard"=>[0x2584b],        // AIAgentTemplateArgonianMaleAsassin
    "male_argonian_soldier"=>[0x2584b],     // AIAgentTemplateArgonianMaleAsassin
    "female_argonian_noble"=>[0x25ded],       // AIAgentTemplateArgonianFemaleAsassin
    "female_argonian_merchant"=>[0x25ded],    // AIAgentTemplateArgonianFemaleAsassin
    "female_argonian_warrior"=>[0x25ded],     // AIAgentTemplateArgonianFemaleAsassin
    "female_argonian_assassin"=>[0x25ded],    // AIAgentTemplateArgonianFemaleAsassin
    "female_argonian_mage"=>[0x25ded],        // AIAgentTemplateArgonianFemaleAsassin
    "female_argonian_beggar"=>[0x25ded],      // AIAgentTemplateArgonianFemaleAsassin
    "female_argonian_farmer"=>[0x25ded],      // AIAgentTemplateArgonianFemaleAsassin
    "female_argonian_bard"=>[0x25ded],        // AIAgentTemplateArgonianFemaleAsassin
    "female_argonian_soldier"=>[0x25ded],     // AIAgentTemplateArgonianFemaleAsassin

    
];


function checkHistory($npc) {

    $enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

    if (!isset($GLOBALS["CONNECTORS_DIARY"]) || !file_exists($enginePath . "connector" . DIRECTORY_SEPARATOR . "{$GLOBALS["CONNECTORS_DIARY"]}.php")) {
        return false;
    }
    
    require_once $enginePath . "connector" . DIRECTORY_SEPARATOR . "{$GLOBALS["CONNECTORS_DIARY"]}.php";

    $historyData="";
    $lastPlace="";
    $lastListener="";
    $n=0;
    foreach (json_decode(DataSpeechJournal($npc,50),true) as $element) {
    
        if ($lastListener!=$element["listener"]) {
            if ($element["listener"]!="The Narrator")
                $listener=" (talking to {$element["listener"]})";
            $lastListener=$element["listener"];
        }
        else
            $listener="";

        if ($lastPlace!=$element["location"]){
            $place=" (at {$element["location"]})";
            $lastPlace=$element["location"];
        }
        else
            $place="";

        if (strpos($element["speaker"],$npc)!==false)  // Only NPC lines
            $n++;
    
    }

    return $n;
}

function askLLMForTopic($npc,$topic,$last_llm_call) {

    $enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

    if (!isset($GLOBALS["CONNECTORS_DIARY"]) || !file_exists($enginePath . "connector" . DIRECTORY_SEPARATOR . "{$GLOBALS["CONNECTORS_DIARY"]}.php")) {
        return false;
    }
    if ((time()-$last_llm_call)<60) {
        error_log("Skipping askLLMForTopic: ".((time()-$last_llm_call)));
        return ["res"=>false,"missing"=>"skip"];
    }

    require_once $enginePath . "connector" . DIRECTORY_SEPARATOR . "{$GLOBALS["CONNECTORS_DIARY"]}.php";

    $historyData="";
    $lastPlace="";
    $lastListener="";
    foreach (json_decode(DataSpeechJournal($npc,50),true) as $element) {
    
        if ($lastListener!=$element["listener"]) {
            if ($element["listener"]!="The Narrator")
                $listener=" (talking to {$element["listener"]})";
            $lastListener=$element["listener"];
        }
        else
            $listener="";

        if ($lastPlace!=$element["location"]){
            $place=" (at {$element["location"]})";
            $lastPlace=$element["location"];
        }
        else
            $place="";

        if (strpos($element["speaker"],$npc)!==false)  // Only NPC lines
            $historyData.=trim("{$element["speaker"]}:".trim($element["speech"])." $listener $place").PHP_EOL;
    
    }

    $partyConf=DataGetCurrentPartyConf();
    $partyConfA=json_decode($partyConf,true);
    
    if (isset($partyConfA["{$npc}"])) {
        $charDesc=print_r($partyConfA["{$npc}"],true).PHP_EOL.$GLOBALS["HERIKA_PERS"];
        $currentProfile=$charDesc;
    } else
        $currentProfile=$GLOBALS["HERIKA_PERS"];

    $head[]   = ["role"	=> "system", "content"	=> "You are an assistant. You will analyze a dialogue and determine if a topic has been fully or partially covered. ", ];
    $prompt[] = ["role"	=> "user", "content"	=> "* Dialogue history:\n" .$historyData ];
    $prompt[] = ["role"=> "user", "content"	=> "is this topic fully or partially covered in the dialogue history? \"$topic\".\n". 
    "Answer yes,or give a score from 1 , (not covered) to 10 (fully covered), and then write a dialogue sentence as the speaker (hint) to provide the missing info. Use a JSON object to give reponse {\"score\":[0-9],\"hint\":\"\"}"];
    $contextData       = array_merge($head, $prompt);

    print_r($contextData);
    $connectionHandler = new connector();

    $connectionHandler->open($contextData, ["max_tokens"=>500]);
    $buffer      = "";
    $totalBuffer = "";
    $breakFlag   = false;
    while (true) {
        
        if ($breakFlag) {
            break;
        }
        
        if ($connectionHandler->isDone()) {
            $breakFlag = true;
        }
        
        $buffer.= $connectionHandler->process();
        $totalBuffer.= $buffer;
        //$bugBuffer[]=$buffer;
        
        
    }
    $connectionHandler->close();

    $actions = $connectionHandler->processActions();


    $res=false;
    $originalBuffer=$buffer;
    $parsedbuffer=json_decode($buffer,true);

    if (is_array($parsedbuffer)) {
        $score=$parsedbuffer["score"];
        $hint=$parsedbuffer["hint"];
        if ($score>=6)
            $res=true;
        $buffer=$hint;

    } else {
        if (preg_match('/Score:\s*(\d+)\//i', $buffer, $matches)) {
            // Extracted score is in $matches[1]
            $score = $matches[1];
            echo "Extracted Score: " . $score.PHP_EOL;
        } else {
            echo "Score not found.".PHP_EOL;
        }
        if (strpos(strtoupper($buffer),"YES")===0)
        $res=true;
        if (strpos(strtoupper($buffer),"MOSTLY YES")===0)
            $res=true;
        if ($score>=6)
            $res=true;

        $buffer=strtr($buffer,["Partially"=>""]);
    }

    
    error_log($originalBuffer);
    
    //$res=true;
    return ["res"=>$res,"missing"=>$buffer];
    
}


function testSpawnRandomNPC() {
    
    

    $names=[
        // Nord Names
        "Bjorn Frostblade",
        "Eirik Wolfborn",
        "Astrid Icevein",
        "Thora Stonefist",
        "Ulfric Stormcloak",
    
        // Breton Names
        "Cecille Moonglow",
        "Mathieu Blackthorn",
        "Isabelle Ravenshade",
        "Dorian Fireheart",
        "Elise Windsong",
    
        // Khajiit Names
        "J'zargo",
        "Ma'randru-jo",
        "K'hari",
        "Ra'zirr",
        "M'aiq the Liar",
    
        // Argonian Names
        "Walks-in-Shadow",
        "Sleeps-in-Marshes",
        "Bright-Scales",
        "Tales-of-Sorrow",
        "Swims-With-Fish",
    
        // Dark Elf (Dunmer) Names
        "Nerethi Veloth",
        "Indoril Mora",
        "Voryn Drenim",
        "Sarethi Nerys",
        "Drathas Venim",
    
        // High Elf (Altmer) Names
        "Aranwen Sunfire",
        "Galathil Aran",
        "Tandoril Larethian",
        "Fayralis Silvaris",
        "Calaron Thalmor",
    
        // Orc (Orsimer) Names
        "Gorbad Gro-Shal",
        "Lob Gro-Baroth",
        "Yashnag Gro-Khazgur",
        "Mazoga Gra-Urgak",
        "Sharn Gra-Malog",
    
        // Imperial Names
        "Marcus Septim",
        "Lucilla Cassius",
        "Julius Varro",
        "Claudia Vibius",
        "Tiberius Lanius",
    
        // Redguard Names
        "Hakim Al-Daran",
        "Amari Swordsong",
        "Jahir Al-Rashid",
        "Zara the Swift",
        "Malik Firebrand"
    ];
    $classes=explode("|","beggar|warrior|assassin|mage|farmer|soldier|merchant|noble");
    $genders=["male","female"];
    $races=explode("|","nord|imperial|argonian|redguard|orc|breton");

    $name=$names[array_rand($names)];
    $class=$classes[array_rand($classes)];
    $race=$races[array_rand($races)];
    $gender=$genders[array_rand($genders)];

    error_log("$name,$class,$race,$gender");
    npcProfileBase($name,$class,$race,$gender,"nearby","test");
    return $name;


}
function npcProfileBase($name,$class,$race,$gender,$location,$taskId) {

    /*
    SELECT STRING_AGG(formid,',') FROM "public"."npc_skyrim_data" where gender ilike 'male%' and race ilike 'nord%' and name='' and class ilike '%bandit%' and edid like 'Enc%' and achr='' and (not formid ilike '%0xDG%')  and (not  edid ilike '%magic%') 
    */
    $masterDataTemplates=$GLOBALS["npc_templates"];
    $masterData=$GLOBALS["npc_own_templates"];
    

    $outfit=[
        "beggar"=>[0x000a1983],
        "mage"=>[0x0006e26f,0x001034ef,0x000a199c,0x000d504c,0x0007eab5,0x0001703a,0x000f3e7d,0x00106114,0x000fba59,0x000e9ac4,0x000b7a3e,0x000b7a3f],
        "barbarian"=>[0x00057a26],
        "warrior"=>[0x00028b44],
        "soldier"=>[0x000fd82b,0x000abf55,0x000abf57,0x000e108f,0x000964df,0x000abf44,0x000abf56,0x000abf58,0x000d29a0,0x000abf45,0x000abf46,0x000cd6dd,0x000d29a1],
        "assassin"=>[0x000e1ec2,0x0010350b,0x00065c53],
        "rogue"=>[0x000e1ec2,0x0010350b,0x00065c53],
        "farmer"=>[0x0002d75e],
        "citizen"=>[0x000a1983],
        "bard"=>[0x0009d5e0, 0x000e40dd, 0x000dab74, 0x000dab75, 0x000f8716, 0x000f8717, 0x000f871a, 0x000f8718],
        "noble"=>[0x0009d5e0, 0x000e40dd, 0x000dab74, 0x000dab75, 0x000f8716, 0x000f8717, 0x000f871a, 0x000f8718],
        "merchant"=>[0x0009d5e0, 0x000e40dd, 0x000dab74, 0x000dab75, 0x000f8716, 0x000f8717, 0x000f871a, 0x000f8718]
    ];

    $weapon=[
        "sword"=>[0x00013989]
    ];
    
    $locations=$GLOBALS["masterDataLocations"];

    $parm5 = $masterDataTemplates["{$gender}_{$race}"][array_rand($masterDataTemplates["{$gender}_{$race}"])];
    $parm1 = $masterData["{$gender}_{$race}_{$class}"][array_rand($masterData["{$gender}_{$race}_{$class}"])];
    $parm2=$outfit["{$class}"][array_rand($outfit["{$class}"])];

    //$parm3=$weapon["{$weapon}"][0];
    $rumors=false;
    $parm3=$weapon["sword"][0];
    if ($location=="nearby")
        $parm4=0;
    else if ($location=="random") {
        $posibleLoc=array_keys($locations);
        $location = $posibleLoc[array_rand($posibleLoc)];
        error_log($location);
        $parm4 = $locations[$location][array_rand($locations[$location])];   
        $rumors=true;
    } else 
        $parm4 = $locations[$location][array_rand($locations[$location])];

    
    $GLOBALS["db"]->insert(
        'responselog',
        array(
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|spawnCharacter@{$name}@$parm1@$parm2@$parm3@$parm4@$taskId@$parm5",
            'tag' => ""
        )
    );
    if ($rumors) {
        $GLOBALS["db"]->insert(
            'rumors',
            array(
                'localts' => time(),
                'gamets'=>$GLOBALS["gameRequest"][2],
                'ts'=>$GLOBALS["gameRequest"][1],
                'location'=>$location,
                'topic'=>"$name has been seen nearby",
                'sess'=>$taskId
            )
        );
    }

}

function CreateItem($basetype,$name,$location,$content) {

    
    echo "CreateItem($basetype,$name,$location,$content)";

    $masterData=$GLOBALS["item_types"];
    

   
    if ($basetype=="note") {
        createBook($name,$content,$location);
        return;
    }

    $localItemName=$GLOBALS["db"]->escape($name);
    $localItemPlace=$GLOBALS["db"]->escape($location);
    
    $localItemType=$masterData[$basetype][array_rand($masterData[$basetype])];

    if ($localItemPlace=="nearby") {
        $localItemPlace=0;
    } else {
        if (!is_numeric($localItemPlace))
            $localItemPlace=$GLOBALS["masterDataLocations"][$location][array_rand($GLOBALS["masterDataLocations"][$location])];
        else {
            ; // ref is gonna be an NPC
        }
    }

    
    
    $GLOBALS["db"]->insert(
        'responselog',
        array(
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|spawnItem@$localItemName@$localItemType@$localItemPlace@{$GLOBALS["taskId"]}",
            //'action' => "rolecommand|spawnItem@The Necklace of the Gods@necklace@Helgen@1",
            'tag' => ""
        )
    );

}


function CreateItemNpc($basetype,$name,$npc) {
    $masterData=[
        "potion"=>[0x0026921],
        "necklace"=>[0x000b8149]
    ];

    $localItemName=$GLOBALS["db"]->escape($name);
    $$localItemNPC=$GLOBALS["db"]->escape($npc);
    $localItemType=$masterData["type"][0];

    $GLOBALS["db"]->insert(
        'responselog',
        array(
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|spawnItemNPC@$localItemName@$localItemType@$localItemNPC@$taskId",
            //'action' => "rolecommand|spawnItem@The Necklace of the Gods@necklace@Helgen@1",
            'tag' => ""
        )
    );

}


function createQuestFromTemplate($template,$notes) {

    $enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

    if (!isset($GLOBALS["CONNECTORS_DIARY"]) || !file_exists($enginePath . "connector" . DIRECTORY_SEPARATOR . "{$GLOBALS["CONNECTORS_DIARY"]}.php")) {
        return false;
    }

    require_once $enginePath . "connector" . DIRECTORY_SEPARATOR . "{$GLOBALS["CONNECTORS_DIARY"]}.php";

    $head[]   = ["role"	=> "system", "content"	=> $GLOBALS["AIQUEST_TEMPLATE"]];
    $prompt[] = ["role"	=> "user", "content"	=> json_encode($template)];
    $prompt[] = ["role"=> "user", "content"	=> 
        "Change this quest's characters and topics and title, but keep same stage structure. Characters must adhere to the definition of createCharacter. $notes. Output only JSON data"
    ];
    $contextData       = array_merge($head, $prompt);

    //print_r($contextData);
    $connectionHandler = new connector();
    $GLOBALS["FORCE_MAX_TOKENS"]=2048;
    $connectionHandler->open($contextData, ["MAX_TOKENS"=>2048]);
    $buffer      = "";
    $totalBuffer = "";
    $breakFlag   = false;
    while (true) {
        
        if ($breakFlag) {
            break;
        }
        
        if ($connectionHandler->isDone()) {
            $breakFlag = true;
        }
        
        $buffer.= $connectionHandler->process();
        $totalBuffer.= $buffer;
        //$bugBuffer[]=$buffer;
        
        
    }
    $connectionHandler->close();

    $originalBuffer=$buffer;
    $parsedbuffer=__jpd_decode_lazy($buffer);

    error_log($originalBuffer);

    if (is_array($parsedbuffer)) {
        return $parsedbuffer;

    } else
        return false;

}

function createBook($title,$content,$location) {

    $width = 371;
    $height = 471;
    
    $text = $content;
    $name = $title;
    

    if ($location=="nearby") {
        $localItemPlace=0;
    } else {

        $localItemPlace=$GLOBALS["masterDataLocations"][$location][array_rand($GLOBALS["masterDataLocations"][$location])];

    }

    $fontPath = __DIR__.'/../data/fonts/GloriaHallelujah-Regular.ttf'; // Path to your TTF font file
    $fontSize = 15; // Initial font size (we'll adjust if needed)

    $backgroundPath = __DIR__ . '/../data/textures/chim.png';

    $background = imagecreatefrompng($backgroundPath);

    // Ensure the background has alpha transparency
    imagesavealpha($background, true);

    // Define the text color
    $textColor = imagecolorallocate($background, 0, 0, 0); // Black color

    // Split text into paragraphs based on newlines
    $paragraphs = explode("\n", $text);

    // Initialize variables for drawing
    $x = 10; // Small left margin
    $y = 20; // Small top margin, adjusted for font size

    foreach ($paragraphs as $paragraph) {
        // Split each paragraph into lines that fit within image width
        $words = explode(" ", $paragraph);
        $line = "";

        foreach ($words as $word) {
            $testLine = $line . $word . " ";
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $testLine);
            $lineWidth = abs($bbox[4] - $bbox[0]);

            if ($lineWidth > $width * 0.9) {
                // Draw the current line and start a new line if it exceeds the boundary
                imagettftext($background, $fontSize, 0, $x, $y, $textColor, $fontPath, trim($line));
                $line = $word . " ";
                $y += $fontSize * 2; // Move down for the next line
            } else {
                $line = $testLine;
            }
        }

        // Draw the last line of the paragraph
        if (trim($line) !== "") {
            imagettftext($background, $fontSize, 0, $x, $y, $textColor, $fontPath, trim($line));
            $y += $fontSize * 1.8;
        }

        // Add extra space between paragraphs
        $y += $fontSize * 0.8;
    }

    // Output the final image with text overlay
    $filename = __DIR__ . "/../soundcache/" . md5($name) . ".png";
    imagepng($background, $filename);

    // Free up memory
    imagedestroy($background);

    echo "Image saved as $filename" . PHP_EOL;
    $cn_name=$GLOBALS["db"]->escape($name);
    $GLOBALS["db"]->insert(
        'responselog',
        array(
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|spawnBook@$cn_name@0@$localItemPlace@{$GLOBALS["taskId"]}@$cn_name",
            'tag' => ""
        )
    );
}

function make_replacements($text) {

    return strtr($text,[
        "#LOCATION#"=>DataLastKnownLocationHuman(),
        "#PLAYER#"=>$GLOBALS["PLAYER_NAME"]
    ]);
}

function convertSignedToUnsignedHex($signedInt) {
    // Convert signed to unsigned using bitwise AND
    $unsignedInt = $signedInt & 0xFFFFFFFF;
    return  "0x" . dechex($unsignedInt) ;

}
?>
