<?php

define("TOPIC_COVERED", 1);
define("TOPIC_UNCOVERED", 2);
define("WILL_DO_LATER", 3);
define("TOPIC_TOOEARLY", 4);

require_once __DIR__ . DIRECTORY_SEPARATOR . "quest_reference_data.php";

$GLOBALS["item_types"] = [
    "potion" => [0x2481f],    // From AIAgent.esp
    "necklace" => [0x2481d],    // From AIAgent.esp
    "amulet" => [0x2481e],    // From AIAgent.esp
    "ring" => [0x242b9],    // From AIAgent.esp
    "note" => [0],          // Will be changed. Generic Note From AIAgent.esp
    "book" => [0x000ce70b], // Should be changed. Generic Book From AIAgent.esp (pending)
    "armor" => [0x000c8861], // Vanilla
    "axe" => [0x000e72a6], // Vanilla
    "dagger" => [0x000aebf7], // Vanilla
];

$GLOBALS["weapons"] = [
    "default" => [0x00013989],
    "warrior" => [0x00013989],
    "soldier" => [0x00013989],
];

// Review this, this are used to copy appearance.

$GLOBALS["npc_templates"] = [
    "male_nord" => [0x0003de8a, 0x0003de6f, 0x0003cf5d, 0x00039cfd, 0x0003dee1, 0x0003dee4, 0x00039d01, 0x0003de91, 0x0003dea5, 0x0003de56, 0x0003deea, 0x0003deed, 0x00039d09, 0x0003de98, 0x0003de74, 0x0003de5b, 0x0003def5, 0x0003def8, 0x00039d11, 0x0003dea0, 0x0003de79, 0x0003de60, 0x0003deff, 0x0003df02, 0x00039d19, 0x0003deac, 0x0003de7e, 0x0003de65, 0x0003df09, 0x0003df0c, 0x00039d21, 0x0003deb3, 0x0003de83, 0x0003de6a, 0x00073fbf, 0x00037c00, 0x00037c2c, 0x00037c05, 0x00037c32, 0x00037c39, 0x00037c40, 0x00037c47],
    "female_nord" => [0x000955b6, 0x00039d36, 0x00039cf5, 0x0003de89, 0x0003de6e, 0x0003cf5c, 0x00037bff, 0x0003dee0, 0x00039d3d, 0x00039d00, 0x0003de90, 0x0003dea4, 0x0003de55, 0x00037c03, 0x0003dee9, 0x00039d48, 0x00039d08, 0x0003de97, 0x0003de73, 0x0003de5a, 0x00037c31, 0x0003def4, 0x00039d4f, 0x00039d10, 0x0003de9f, 0x0003de78, 0x0003de5f, 0x00037c38, 0x0003defe, 0x00039d56, 0x00039d18, 0x0003deab, 0x0003de7d, 0x0003de64, 0x00037c3f, 0x0003df08, 0x00039d5d, 0x00039d20, 0x0003deb2, 0x0003de82, 0x0003de69, 0x00037c46, 0x000bfb48, 0x00017167, 0x00017168, 0x00017169, 0x00107a9f, 0x00033424, 0x0003386f, 0x0003387d, 0x00033882, 0x00033887, 0x0003392f, 0x0010c453, 0x0010c470, 0x0010c478, 0x0010c47e, 0x0010c484, 0x00045c75, 0x000e1019, 0x00045c77, 0x000e101f, 0x00045c8b, 0x000e1023, 0x00045cb1, 0x000e1027, 0x00045cd6, 0x000e102b, 0x00045cdf, 0x001091c1, 0x00074f7e, 0x00074f8d, 0x000fe512, 0x000edf6d, 0x000328d6, 0x0001a772, 0x000cd644, 0x000cd642],
    "male_orc" => [0x00039cfa, 0x0003de8b, 0x0003de70, 0x0003cf5e, 0x0003dee5, 0x00039d03, 0x0003de92, 0x0003dea6, 0x0003de57, 0x0003deee, 0x00039d0b, 0x0003de99, 0x0003de75, 0x0003de5c, 0x0003def9, 0x00039d13, 0x0003dea1, 0x0003de7a, 0x0003de61, 0x0003df03, 0x00039d1b, 0x0003dead, 0x0003de7f, 0x0003de66, 0x0003df0d, 0x00039d23, 0x0003deb4, 0x0003de84, 0x0003de6b, 0x000d9448, 0x000d9449, 0x000d944a, 0x000d944b, 0x000d944d, 0x000d9448, 0x000d9449, 0x000d944a, 0x000d944b, 0x000d944d],
    "female_orc" => [0x000ce082, 0x00045806, 0x00105556, 0x00105562, 0x00105555, 0x00079f4e, 0x00079f25, 0x00079ee8, 0x00079ee6, 0x00099d5f, 0x0010ab8f, 0x0010ab90, 0x0010ab91, 0x0010ab92, 0x0010ab93, 0x00019e1a, 0x000ced01],

    "male_argonian" => [0x00103512],
    "female_argonian" => [0x000457fb, 0x000b2e11, 0x000b2e12, 0x000b2e13, 0x000b2e14, 0x000b2e15, 0x000b2e16, 0x0010d3be, 0x0010d3bf, 0x0010d3c0, 0x0010d3c1, 0x00103511],

    "male_altmer" => ["Skyrim.esm|000233D2", "Skyrim.esm|0002BA3C"],
    "female_altmer" => ["Skyrim.esm|00013269", "Skyrim.esm|0001C197"],
    "male_bosmer" => ["Skyrim.esm|0003983E", "Skyrim.esm|00013480"],
    "female_bosmer" => ["Skyrim.esm|00013349", "Skyrim.esm|00019DEF"],
    "male_dunmer" => ["Skyrim.esm|000BD759", "Skyrim.esm|0002427D"],
    "female_dunmer" => ["Skyrim.esm|0001C196", "Skyrim.esm|000B9982"],
    "male_khajiit" => ["Skyrim.esm|0001B1DB", "Skyrim.esm|0001B1D2"],
    "female_khajiit" => ["Skyrim.esm|000353C7", "Skyrim.esm|0001B1D6"],

    "female_breton" => [0x00064a77, 0x00064a75, 0x00064a3f, 0x00064a3d, 0x00064a8c, 0x00064a8a, 0x00064ac3, 0x00064ab2, 0x00064a7b, 0x00064a79, 0x00064a46, 0x00064a44, 0x00064aa0, 0x00064a96, 0x00064ac6, 0x00064ab4, 0x00064a7f, 0x00064a7d, 0x00064a4b, 0x00064a49, 0x00064aa2, 0x00064a98, 0x00064ac7, 0x00064abb, 0x00064a83, 0x00064a81, 0x00064a4e, 0x00064aa4, 0x00064a9a, 0x00064acd, 0x00064abd, 0x00064a87, 0x00064a85, 0x00064a55, 0x00064a53, 0x00064aa6, 0x00064a9c, 0x00064ac9, 0x00064abf, 0x00064a5a, 0x00064a58, 0x00064aa8, 0x00064a9e, 0x00064acb, 0x00064ac1, 0x000e36da, 0x00064a50, 0x00039d32, 0x00039d44, 0x00039d4b, 0x00039d52, 0x00039d59, 0x00043beb, 0x00043bec, 0x00043bed, 0x00043be1, 0x00043be0, 0x00043be2, 0x00043be4, 0x00043be5, 0x00043be6, 0x00043bf1, 0x00043bf2, 0x00043bf3, 0x00044256, 0x00044257, 0x00044258, 0x0004425c, 0x0004425d, 0x0004425e, 0x00044265, 0x00044266, 0x00044267, 0x00044268, 0x00044269, 0x0004426a, 0x0004426e, 0x0004426f, 0x00044270, 0x00044277, 0x00044278, 0x00044279, 0x0004427a, 0x0004427b, 0x0004427c, 0x00044280, 0x00044281, 0x00044282, 0x00044289, 0x0004428a, 0x0004428b, 0x0004428c, 0x0004428d, 0x0004428e, 0x00044292, 0x00044293, 0x00044294, 0x0004429b, 0x0004429c, 0x0004429d, 0x0004429e, 0x0004429f, 0x000442a0, 0x000442a4, 0x000442a5, 0x000442a6, 0x00107a9b, 0x0003300e, 0x00033853, 0x00033870, 0x0003387e, 0x00033883, 0x00033888, 0x0006d234, 0x000e0fe4, 0x0006d23b, 0x000e0fe8, 0x0006d243, 0x000e0fec, 0x0006d24b, 0x000e0ff0, 0x0006d253, 0x000e0ff4, 0x0006d25b, 0x001091b7, 0x001091b8, 0x00044cdc, 0x00045c62, 0x00045c7d, 0x00045ca3, 0x00045cc1, 0x00045cc8, 0x001091b9, 0x00045c51, 0x00045c6a, 0x00045c85, 0x00045cab, 0x00045cd1, 0x00045cd9, 0x001091ba, 0x000551b0, 0x000e1035, 0x000551b8, 0x000e1039, 0x000551c0, 0x000e103d, 0x000551c8, 0x000e1041, 0x000551d0, 0x000e1045, 0x000551d8, 0x001091c3, 0x001091bb, 0x00045c57, 0x000e1051, 0x00045c70, 0x000e1055, 0x00045c8d, 0x000e1059, 0x00045cb3, 0x000e105d, 0x00045ce1, 0x000e1061, 0x00045ce9, 0x001091c5, 0x001091bc, 0x00074f77, 0x00074f86, 0x00074f7b, 0x00074f8a, 0x00074f7f, 0x00074f8e, 0x000328e0, 0x0001a76e, 0x000b125f, 0x0004428d, 0x0004428e, 0x00044292, 0x00044293, 0x00044294, 0x0004429b, 0x0004429c, 0x0004429d, 0x0004429e, 0x0004429f, 0x000442a0, 0x000442a4, 0x000442a5, 0x000442a6, 0x0006d234, 0x000e0fe4, 0x0006d23b, 0x000e0fe8, 0x0006d243, 0x000e0fec, 0x0006d24b, 0x000e0ff0, 0x0006d253, 0x000e0ff4, 0x0006d25b, 0x001091b7, 0x001091b8, 0x00044cdc, 0x00045c62, 0x00045c7d, 0x00045ca3, 0x00045cc1, 0x00045cc8, 0x001091b9, 0x00045c51, 0x00045c6a, 0x00045c85, 0x00045cab, 0x00045cd1, 0x00045cd9, 0x001091ba, 0x000551b0, 0x000e1035, 0x000551b8, 0x000e1039, 0x000551c0, 0x000e103d, 0x000551c8, 0x000e1041, 0x000551d0, 0x000e1045, 0x000551d8, 0x001091c3, 0x001091bb, 0x00045c57, 0x000e1051, 0x00045c70, 0x000e1055, 0x00045c8d, 0x000e1059, 0x00045cb3, 0x000e105d, 0x00045ce1, 0x000e1061, 0x00045ce9, 0x001091c5, 0x001091bc, 0x00074f77, 0x00074f86, 0x00074f7b, 0x00074f8a, 0x00074f7f, 0x00074f8e, 0x0009655b, 0x000328e0, 0x0001b152, 0x0001a763, 0x000b125f, 0x000a9154, 0x000d80b4],
    "male_breton" => [0x00064a42, 0x00043ab8, 0x000bede5, 0x0006d22f, 0x000548ff, 0x001091ad, 0x001091a8, 0x001091b0, 0x001091ab, 0x0006a152, 0x000e0fcd, 0x0006d232, 0x000e0fd0, 0x000551ae, 0x0002bce8, 0x0004d8d5, 0x000457f6, 0x001034f3, 0x001034fc, 0x0009f844, 0x000e0fc8, 0x0006d231, 0x0006d230, 0x00043bdc, 0x0004430a, 0x0004430b, 0x0004430c, 0x0004430d, 0x0004430e, 0x0004430f, 0x00044310, 0x00044311, 0x00044312, 0x00044313, 0x00043bf0, 0x00043bee, 0x00043bef, 0x00044262, 0x00044263, 0x00044264, 0x00044274, 0x00044275, 0x00044276, 0x00044287, 0x00044288, 0x00044298, 0x00044299, 0x0004429a, 0x000844d0, 0x00013368, 0x000e0fd3, 0x0006d233, 0x000e0fd5, 0x000551af, 0x000e0fcb, 0x000551ad, 0x000e0fc6, 0x000551ac, 0x0006d22e, 0x000548fe, 0x00079f6a, 0x00079f64, 0x00079f60, 0x00079f5f, 0x00099d22, 0x0010ab67, 0x0010ab68, 0x0010ab69, 0x0010ab6a, 0x0010ab6b, 0x00074bd8, 0x0009f847, 0x0006f214, 0x000c3b25, 0x00064a78, 0x00064a76, 0x00064a40, 0x00064a3e, 0x00064a8d, 0x00064a8b, 0x00064ac4, 0x00064ab3, 0x00064a69, 0x00064a41, 0x00064aab, 0x00064a7c, 0x00064a7a, 0x00064a47, 0x00064a45, 0x00064aa1, 0x00064a97, 0x00064ac5, 0x00064ab5, 0x00064a6e, 0x00064a43, 0x00064ab7, 0x00064a80, 0x00064a7e, 0x00064a4c, 0x00064a4a, 0x00064aa3, 0x00064a99, 0x00064ace, 0x00064abc, 0x00064a6d, 0x00064a48, 0x00064ab6, 0x00064a84, 0x00064a82, 0x00064a51, 0x00064aa5, 0x00064a9b, 0x00064ac8, 0x00064abe, 0x00064a6c, 0x00064a4d, 0x00064ab8, 0x00064a86, 0x00064a56, 0x00064a54, 0x00064aa7, 0x00064a9d, 0x00064aca, 0x00064ac0, 0x00064a6b, 0x00064a52, 0x00064ab9, 0x00064a5b, 0x00064a59, 0x00064aa9, 0x00064a9f, 0x00064acc, 0x00064ac2, 0x00064a57, 0x00064aba, 0x0008443d, 0x000e16d4, 0x00064a4f, 0x00039d33, 0x00039d3a, 0x00039d45, 0x00039d4c, 0x00039d53, 0x00039d5a, 0x000f9616, 0x00043bdd, 0x00043bde, 0x00043bdf, 0x000ad7b4, 0x00043be7, 0x00043be8, 0x00043be9, 0x000ad7b5, 0x00023aa9, 0x00043be3, 0x000442d4, 0x000442d5, 0x000ad7bb, 0x000442d7, 0x000442d8, 0x000442d9, 0x000f9617, 0x00044259, 0x0004425a, 0x0004425b, 0x000ad7b6, 0x0004425f, 0x00044260, 0x00044261, 0x000ad7b7, 0x000442da, 0x000442db, 0x000ad7ba, 0x000442dd, 0x000442de, 0x000442df, 0x000f9618, 0x0004426b, 0x0004426c, 0x0004426d, 0x000ad7b8, 0x00044271, 0x00044272, 0x00044273, 0x000ad7b9, 0x000442e0, 0x000442e1, 0x000ad7bc, 0x000442e3, 0x000442e4, 0x000442e5, 0x000f9619, 0x0004427d, 0x0004427e, 0x0004427f, 0x000ad7bd, 0x00044283, 0x00044284, 0x00044285, 0x000ad7be, 0x000442e6, 0x000442e7, 0x000442e9, 0x000442ea, 0x000442eb, 0x000f961a, 0x0004428f, 0x00044290, 0x00044291, 0x000ad7bf, 0x00044295, 0x00044296, 0x00044297, 0x000ad7c0, 0x000442ec, 0x000442ed, 0x000ad7c1, 0x000442ef, 0x000442f0, 0x000442f1, 0x000f961b, 0x000442a1, 0x000442a2, 0x000442a3, 0x000ad7c2, 0x000442a7, 0x000442a8, 0x000442a9, 0x000ad7c3, 0x00017145, 0x00017146, 0x0002e1dc, 0x0002e1f1, 0x0002e509, 0x0002ea9b, 0x0002eabe, 0x0006d235, 0x000e0fe5, 0x0006d23c, 0x000e0fe9, 0x0006d244, 0x000e0fed, 0x0006d24c, 0x000e0ff1, 0x0006d254, 0x000e0ff5, 0x0006d25c, 0x00044cda, 0x00045c63, 0x00045c7e, 0x00045ca4, 0x00045cc2, 0x00045cc9, 0x00045c52, 0x00045c6b, 0x00045c86, 0x00045cac, 0x00045cd2, 0x00045cda, 0x000551b1, 0x000e1036, 0x000551b9, 0x000e103a, 0x000551c1, 0x000e103e, 0x000551c9, 0x000e1042, 0x000551d1, 0x000e1046, 0x000551d9, 0x00045c58, 0x000e1052, 0x00045c71, 0x000e1056, 0x00045c8e, 0x000e105a, 0x00045cb4, 0x000e105e, 0x00045ce2, 0x000e1062, 0x00045cea, 0x000328df, 0x0001b153, 0x0001a777, 0x0010611f, 0x00106120, 0x00106121, 0x000684cd, 0x000b3b95],

    "female_imperial" => [0x00013350, 0x0008a89d, 0x0008a89f, 0x00045802, 0x00103501, 0x00105ee2, 0x000dbd11, 0x000deedf, 0x00102d63, 0x000b5d5a, 0x00107572, 0x00079f66, 0x00079f57, 0x00079f56, 0x00079f55, 0x00099d4f, 0x0010ab80, 0x0010ab81, 0x0010ab82, 0x0010ab83, 0x0010ab84, 0x0007515e, 0x000b8149, 0x000c0401, 0x000b114f, 0x00039cf7, 0x0003de87, 0x00037bfc, 0x0003dede, 0x00039cfe, 0x0003de8e, 0x00037c01, 0x0003dee7, 0x00039d06, 0x0003de95, 0x00037c2f, 0x0003def2, 0x00039d0e, 0x0003de9d, 0x00037c36, 0x0003defc, 0x00039d16, 0x0003dea9, 0x00037c3d, 0x0003df06, 0x00039d1e, 0x0003deb0, 0x00037c44, 0x000bfb45, 0x000e0cdf, 0x000e0ce0, 0x00107a9e, 0x000332c4, 0x0003386e, 0x0003387c, 0x00033881, 0x00033886, 0x0003392e, 0x0006d238, 0x0006d241, 0x0006d249, 0x0006d252, 0x0006d259, 0x0006d261, 0x00044cea, 0x00045c68, 0x00045c83, 0x00045ca9, 0x00045cc6, 0x00045ccf, 0x00045c55, 0x00045c6e, 0x00045c89, 0x00045caf, 0x00045cd5, 0x00045cdd, 0x000551b6, 0x000551be, 0x000551c6, 0x000551ce, 0x000551d6, 0x000551de, 0x00045c5d, 0x00045c74, 0x00045c95, 0x00045cb9, 0x00045ce7, 0x00045cef, 0x00074f7a, 0x00074f89, 0x00074f7d, 0x00074f8c, 0x00074f82, 0x00074f91, 0x0001a766, 0x0001a771, 0x0001a76b, 0x000e77e7, 0x000e77e6],
    "male_imperial" => [0x0001c4e4, 0x0008a89c, 0x0008a89e, 0x001065f0, 0x000bd75e, 0x000d0577, 0x000aa7d6, 0x000f964a, 0x000457f8, 0x00103500, 0x00045be0, 0x000205c9, 0x0001ae44, 0x0001fc5d, 0x0004622a, 0x00084539, 0x000844b2, 0x0005cf3f, 0x000e0cdd, 0x00102d62, 0x000bbcd2, 0x000b8148, 0x000c03fe, 0x00026921, 0x00026927, 0x0002694e, 0x00026954, 0x00099d21, 0x0010ab85, 0x0010ab86, 0x0010ab87, 0x0010ab88, 0x0010ab89, 0x000a0e49, 0x0008c3ca, 0x00019a24, 0x0001a673, 0x000b9655, 0x000dc25d, 0x000770b2, 0x000770ba, 0x0001675d, 0x0010d4b2, 0x0010d4b3, 0x0010d4b4, 0x0010d4b5, 0x00045bdf, 0x00073fd4, 0x00073fd8, 0x000b08a1, 0x0009f358, 0x00039cf6, 0x0003de88, 0x00037bfe, 0x0003dedf, 0x0003def0, 0x00039cff, 0x0003de8f, 0x00037c02, 0x0003dee8, 0x0003def1, 0x00039d07, 0x0003de96, 0x00037c30, 0x0003def3, 0x0003defb, 0x00039d0f, 0x0003de9e, 0x00037c37, 0x0003defd, 0x0003df05, 0x00039d17, 0x0003deaa, 0x00037c3e, 0x0003df07, 0x0003df0f, 0x00039d1f, 0x0003deb1, 0x00037c45, 0x000f6f37, 0x00073fbd, 0x000e0cde, 0x000e0ce1, 0x0007d990, 0x0007d998, 0x0007d991, 0x0007d999, 0x0007d992, 0x0007d99a, 0x0007d993, 0x0007d99b, 0x0007d994, 0x0007d99d, 0x0007d995, 0x0007d99e, 0x000c6012, 0x00041b30, 0x0003377b, 0x0003377c, 0x00033828, 0x0003383e, 0x0003383f, 0x0006d23a, 0x0006d242, 0x0006d24a, 0x0006d251, 0x0006d25a, 0x0006d262, 0x00044ceb, 0x00045c69, 0x00045c84, 0x00045caa, 0x00045cc7, 0x00045cd0, 0x00045c56, 0x00045c6f, 0x00045c8a, 0x00045cb0, 0x00045cd8, 0x00045cde, 0x000551b7, 0x000551bf, 0x000551c7, 0x000551cf, 0x000551d7, 0x000551df, 0x00045c5e, 0x00045c79, 0x00045c96, 0x00045cba, 0x00045ce8, 0x00045cf0, 0x000e0d77, 0x0001a765, 0x000c49db, 0x0001a774, 0x000bf31e, 0x0008555c, 0x000e77e2, 0x000e77e1, 0x0005af2a],

    "female_redguard" => [0x000860c7, 0x00013ba9, 0x00079f67, 0x00079ee1, 0x00079e2f, 0x00079e2c, 0x00099d4e, 0x0010ab99, 0x0010ab9a, 0x0010ab9b, 0x0010ab9c, 0x0010ab9d, 0x0007514e, 0x00048117, 0x00103505, 0x001034f5, 0x000b85ab, 0x0006cd5a, 0x000d4ff9, 0x00039cf8, 0x0003de8c, 0x0003de71, 0x0003de53, 0x00037c06, 0x0003dee2, 0x00039d04, 0x0003de93, 0x0003dea7, 0x0003de58, 0x00037c08, 0x0003deeb, 0x00039d0c, 0x0003de9a, 0x0003de76, 0x0003de5d, 0x00037c33, 0x0003def6, 0x00039d14, 0x0003dea2, 0x0003de7b, 0x0003de62, 0x00037c3a, 0x0003df00, 0x00039d1c, 0x0003deae, 0x0003de80, 0x0003de67, 0x00037c41, 0x0003df0a, 0x00039d24, 0x0003deb5, 0x0003de85, 0x0003de6c, 0x00037c48, 0x000bfb47, 0x0010c455, 0x0010c476, 0x0010c47c, 0x0010c483, 0x0010c489, 0x000328d4],
    "male_redguard" => [0x0006762e, 0x00058b3f, 0x0010f5a1, 0x0010f5aa, 0x00020071, 0x00013baa, 0x00019a2a, 0x0004d8d4, 0x0001b3b5, 0x0005b4f8, 0x00026904, 0x000268fc, 0x00026915, 0x00024261, 0x0010ab9e, 0x0010ab9f, 0x0010aba0, 0x0010aba1, 0x0010aba2, 0x00048118, 0x00103504, 0x00013609, 0x0002e11f, 0x000215d5, 0x00067631, 0x00067642, 0x00067641, 0x00067645, 0x00067643, 0x00067646, 0x00067644, 0x00067647, 0x0006762f, 0x00067630, 0x0006764b, 0x00067648, 0x0006764c, 0x00067649, 0x0006764d, 0x0006764a, 0x0006764e, 0x00067632, 0x00067633, 0x00067634, 0x0006764f, 0x00067650, 0x00067653, 0x00067651, 0x00067654, 0x00067652, 0x00067655, 0x00067635, 0x00067636, 0x00067637, 0x00067656, 0x00067657, 0x0006765a, 0x00067658, 0x0006765b, 0x00067659, 0x0006765c, 0x00067638, 0x00067639, 0x0006763a, 0x0006765d, 0x0006765e, 0x00067665, 0x0006765f, 0x00067666, 0x00067660, 0x00067667, 0x0006763b, 0x0006763c, 0x0006763d, 0x00067661, 0x00067662, 0x00067668, 0x00067663, 0x00067669, 0x00067664, 0x0006766a, 0x0006763e, 0x0006763f, 0x00067640, 0x00039cf9, 0x0003de8d, 0x0003de72, 0x0003de54, 0x00037c07, 0x0003dee3, 0x0003dee6, 0x00039d05, 0x0003de94, 0x0003dea8, 0x0003de59, 0x00037c0c, 0x0003deec, 0x0003deef, 0x00039d0d, 0x0003de9b, 0x0003de77, 0x0003de5e, 0x00037c34, 0x0003def7, 0x0003defa, 0x00039d15, 0x0003dea3, 0x0003de7c, 0x0003de63, 0x00037c3b, 0x0003df01, 0x0003df04, 0x00039d1d, 0x0003deaf, 0x0003de81, 0x0003de68, 0x00037c42, 0x0003df0b, 0x0003df0e, 0x00039d25, 0x0003deb6, 0x0003de86, 0x0003de6d, 0x00037c49, 0x00073fc0, 0x000c6016, 0x00017143, 0x00017144, 0x00032860, 0x000c49dd, 0x000b9285],

    // Creatures
    "male_draugr" => [0x0005593b, 0x0003b549, 0x0005593d],
    "female_draugr" => [0x0003891d, 0x00055937],

    "male_elk" => [0x00023a91],
    "female_elk" => [0x00023a91],

    "male_frost_troll" => [0x00023abb],
    "female_frost_troll" => [0x00023abb],

    "male_frostbite_spider" => [0x00041fb4],
    "female_frostbite_spider" => [0x00041fb4],
    "male_dwarven_sphere_guardian" => [0x00023a97],
    "female_dwarven_sphere_guardian" => [0x00023a97],

    "male_falmer" => [0x0003b54c],
    "female_falmer" => [0x02003627],

    "male_giant" => [0x00030438],
    "female_giant" => [0x00023aae],


];

// From AIAgent.esp
$GLOBALS["npc_own_templates"] = [

    "female_breton_noble" => [0x25844], // AIAgentTemplateBretonFemaleCivil
    "female_breton_merchant" => [0x25844], // AIAgentTemplateBretonFemaleCivil
    "female_breton_warrior" => [0x25845], // AIAgentTemplateBretonFemaleWarrior
    "female_breton_assassin" => [0x25846], // AIAgentTemplateBretonFemaleAsassin
    "female_breton_mage" => [0x25847], // AIAgentTemplateBretonFemaleMage
    "female_breton_beggar" => [0x25848], // AIAgentTemplateBretonFemalePoor
    "female_breton_farmer" => [0x25848], // AIAgentTemplateBretonFemalePoor
    "female_breton_bard" => [0x25849], // AIAgentTemplateBretonFemaleBard
    "female_breton_soldier" => [0x2584a], // AIAgentTemplateBretonFemaleSoldier

    "female_breton_forsworn" => [0x25848], // AIAgentTemplateBretonFemaleSoldier


    "male_breton_noble" => [0x25daf], // AIAgentTemplateBretonMaleCivil
    "male_breton_merchant" => [0x25daf], // AIAgentTemplateBretonMaleCivil
    "male_breton_warrior" => [0x25db0], // AIAgentTemplateBretonMaleWarrior
    "male_breton_assassin" => [0x25db1], // AIAgentTemplateBretonMaleAsassin
    "male_breton_mage" => [0x25db3], // AIAgentTemplateBretonMaleMage
    "male_breton_beggar" => [0x25db2], // AIAgentTemplateBretonMalePoor
    "male_breton_farmer" => [0x25db2], // AIAgentTemplateBretonMalePoor
    "male_breton_bard" => [0x25db4], // AIAgentTemplateBretonMaleBard
    "male_breton_soldier" => [0x25db5], // AIAgentTemplateBretonMaleSoldier

    "male_breton_forsworn" => [0x25db2], // AIAgentTemplateBretonFemaleSoldier

    "male_nord_noble" => [0x25db6], // AIAgentTemplateNordMaleCivil
    "male_nord_merchant" => [0x25db6], // AIAgentTemplateNordMaleCivil
    "male_nord_warrior" => [0x2584d], // AIAgentTemplateNordMaleWarrior
    "male_nord_assassin" => [0x25db7], // AIAgentTemplateNordMaleAsassin
    "male_nord_mage" => [0x25db8], // AIAgentTemplateNordMaleMage
    "male_nord_beggar" => [0x25dbb], // AIAgentTemplateNordMalePoor
    "male_nord_farmer" => [0x25dbb], // AIAgentTemplateNordMalePoor
    "male_nord_bard" => [0x25db9], // AIAgentTemplateNordMaleBard
    "male_nord_soldier" => [0x25dba], // AIAgentTemplateNordMaleSoldier
    "female_nord_noble" => [0x25dbc], // AIAgentTemplateNordFemaleCivil
    "female_nord_merchant" => [0x25dbc], // AIAgentTemplateNordFemaleCivil
    "female_nord_warrior" => [0x25dbd], // AIAgentTemplateNordFemaleWarrior
    "female_nord_assassin" => [0x25dbe], // AIAgentTemplateNordFemaleAsassin
    "female_nord_mage" => [0x25dbf], // AIAgentTemplateNordFemaleMage
    "female_nord_beggar" => [0x25dc0], // AIAgentTemplateNordFemalePoor
    "female_nord_farmer" => [0x25dc0], // AIAgentTemplateNordFemalePoor
    "female_nord_bard" => [0x25dc2], // AIAgentTemplateNordFemaleBard
    "female_nord_soldier" => [0x25dc1], // AIAgentTemplateNordFemaleSoldier

    "male_imperial_noble" => [0x25dce], // AIAgentTemplateImperialMaleCivil
    "male_imperial_merchant" => [0x25dce], // AIAgentTemplateImperialMaleCivil
    "male_imperial_warrior" => [0x25dca], // AIAgentTemplateImperialMaleWarrior
    "male_imperial_assassin" => [0x25dd0], // AIAgentTemplateImperialMaleAsassin
    "male_imperial_mage" => [0x25dcd], // AIAgentTemplateImperialMaleMage
    "male_imperial_beggar" => [0x25dcc], // AIAgentTemplateImperialMalePoor
    "male_imperial_farmer" => [0x25dcc], // AIAgentTemplateImperialMalePoor
    "male_imperial_bard" => [0x25dcf], // AIAgentTemplateImperialMaleBard
    "male_imperial_soldier" => [0x25dcb], // AIAgentTemplateImperialMaleSoldier
    "female_imperial_noble" => [0x25dc7], // AIAgentTemplateImperialFemaleCivil
    "female_imperial_merchant" => [0x25dc7], // AIAgentTemplateImperialFemaleCivil
    "female_imperial_warrior" => [0x25dc3], // AIAgentTemplateImperialFemaleWarrior
    "female_imperial_assassin" => [0x25dc9], // AIAgentTemplateImperialFemaleAsassin
    "female_imperial_mage" => [0x25dc6], // AIAgentTemplateImperialFemaleMage
    "female_imperial_beggar" => [0x25dc5], // AIAgentTemplateImperialFemalePoor
    "female_imperial_farmer" => [0x25dc5], // AIAgentTemplateImperialFemalePoor
    "female_imperial_bard" => [0x25dc8], // AIAgentTemplateImperialFemaleBard
    "female_imperial_soldier" => [0x25dcb], // AIAgentTemplateImperialFemaleSoldier

    "male_redguard_noble" => [0x25dd6], // AIAgentTemplateRedguardMaleCivil
    "male_redguard_merchant" => [0x25dd6], // AIAgentTemplateRedguardMaleCivil
    "male_redguard_warrior" => [0x25dd2], // AIAgentTemplateRedguardMaleWarrior
    "male_redguard_assassin" => [0x25dd8], // AIAgentTemplateRedguardMaleAsassin
    "male_redguard_mage" => [0x25dd5], // AIAgentTemplateRedguardMaleMage
    "male_redguard_beggar" => [0x25dd4], // AIAgentTemplateRedguardMalePoor
    "male_redguard_farmer" => [0x25dd4], // AIAgentTemplateRedguardMalePoor
    "male_redguard_bard" => [0x25dd7], // AIAgentTemplateRedguardMaleBard
    "male_redguard_soldier" => [0x25dd3], // AIAgentTemplateRedguardMaleSoldier
    "female_redguard_noble" => [0x25ddd], // AIAgentTemplateRedguardFemaleCivil
    "female_redguard_merchant" => [0x25ddd], // AIAgentTemplateRedguardFemaleCivil
    "female_redguard_warrior" => [0x25dd9], // AIAgentTemplateRedguardFemaleWarrior
    "female_redguard_assassin" => [0x25ddf], // AIAgentTemplateRedguardFemaleAsassin
    "female_redguard_mage" => [0x25ddc], // AIAgentTemplateRedguardFemaleMage
    "female_redguard_beggar" => [0x25ddb], // AIAgentTemplateRedguardFemalePoor
    "female_redguard_farmer" => [0x25ddb], // AIAgentTemplateRedguardFemalePoor
    "female_redguard_bard" => [0x25dde], // AIAgentTemplateRedguardFemaleBard
    "female_redguard_soldier" => [0x25dda], // AIAgentTemplateRedguardFemaleSoldier

    "male_orc_noble" => [0x25de0], // AIAgentTemplateOrcMaleCivil
    "male_orc_merchant" => [0x25de0], // AIAgentTemplateOrcMaleCivil
    "male_orc_warrior" => [0x2584c], // AIAgentTemplateOrcMaleWarrior
    "male_orc_assassin" => [0x25de1], // AIAgentTemplateOrcMaleAsassin
    "male_orc_mage" => [0x25de2], // AIAgentTemplateOrcMaleMage
    "male_orc_beggar" => [0x25de3], // AIAgentTemplateOrcMalePoor
    "male_orc_farmer" => [0x25de3], // AIAgentTemplateOrcMalePoor
    "male_orc_bard" => [0x25de4], // AIAgentTemplateOrcMaleBard
    "male_orc_soldier" => [0x25de5], // AIAgentTemplateOrcMaleSoldier
    "female_orc_noble" => [0x25de6], // AIAgentTemplateOrcFemaleCivil
    "female_orc_merchant" => [0x25de6], // AIAgentTemplateOrcFemaleCivil
    "female_orc_warrior" => [0x25de7], // AIAgentTemplateOrcFemaleWarrior
    "female_orc_assassin" => [0x25de8], // AIAgentTemplateOrcFemaleAsassin
    "female_orc_mage" => [0x25de9], // AIAgentTemplateOrcFemaleMage
    "female_orc_beggar" => [0x25dea], // AIAgentTemplateOrcFemalePoor
    "female_orc_farmer" => [0x25dea], // AIAgentTemplateOrcFemalePoor
    "female_orc_bard" => [0x25deb], // AIAgentTemplateOrcFemaleBard
    "female_orc_soldier" => [0x25dec], // AIAgentTemplateOrcFemaleSoldier

    "male_argonian_noble" => [0x2584b], // AIAgentTemplateArgonianMaleAsassin
    "male_argonian_merchant" => [0x2584b], // AIAgentTemplateArgonianMaleAsassin
    "male_argonian_warrior" => [0x2584b], // AIAgentTemplateArgonianMaleAsassin
    "male_argonian_assassin" => [0x2584b], // AIAgentTemplateArgonianMaleAsassin
    "male_argonian_mage" => [0x2584b], // AIAgentTemplateArgonianMaleAsassin
    "male_argonian_beggar" => [0x2584b], // AIAgentTemplateArgonianMaleAsassin
    "male_argonian_farmer" => [0x2584b], // AIAgentTemplateArgonianMaleAsassin
    "male_argonian_bard" => [0x2584b], // AIAgentTemplateArgonianMaleAsassin
    "male_argonian_soldier" => [0x2584b], // AIAgentTemplateArgonianMaleAsassin
    "female_argonian_noble" => [0x25ded], // AIAgentTemplateArgonianFemaleAsassin
    "female_argonian_merchant" => [0x25ded], // AIAgentTemplateArgonianFemaleAsassin
    "female_argonian_warrior" => [0x25ded], // AIAgentTemplateArgonianFemaleAsassin
    "female_argonian_assassin" => [0x25ded], // AIAgentTemplateArgonianFemaleAsassin
    "female_argonian_mage" => [0x25ded], // AIAgentTemplateArgonianFemaleAsassin
    "female_argonian_beggar" => [0x25ded], // AIAgentTemplateArgonianFemaleAsassin
    "female_argonian_farmer" => [0x25ded], // AIAgentTemplateArgonianFemaleAsassin
    "female_argonian_bard" => [0x25ded], // AIAgentTemplateArgonianFemaleAsassin
    "female_argonian_soldier" => [0x25ded], // AIAgentTemplateArgonianFemaleAsassin

];

$questRaceSpawnBases = [
    "male_altmer" => "AIAgent.esp|00045CE7",
    "female_altmer" => "AIAgent.esp|00045CE8",
    "male_bosmer" => "AIAgent.esp|00045CE9",
    "female_bosmer" => "AIAgent.esp|00045CEA",
    "male_dunmer" => "AIAgent.esp|00045CEB",
    "female_dunmer" => "AIAgent.esp|00045CEC",
    "male_khajiit" => "AIAgent.esp|00045CED",
    "female_khajiit" => "AIAgent.esp|00045CEE",
];
$questSpawnAliases = [];
foreach (array_keys($GLOBALS["npc_own_templates"]) as $templateKey) {
    if (preg_match('/^male_argonian_(.+)$/', $templateKey, $matches)) {
        $questSpawnAliases[] = $matches[1];
    }
}
foreach ($questRaceSpawnBases as $templatePrefix => $formId) {
    foreach ($questSpawnAliases as $classAlias) {
        $GLOBALS["npc_own_templates"]["{$templatePrefix}_{$classAlias}"] = [$formId];
    }
}
unset($questRaceSpawnBases, $questSpawnAliases, $templateKey, $templatePrefix, $classAlias, $formId, $matches);

$GLOBALS["outfit"] = [
    "beggar" => [0x000a1983],
    "mage" => [0x0006e26f, 0x001034ef, 0x000a199c, 0x000d504c, 0x0007eab5, 0x0001703a, 0x000f3e7d, 0x00106114, 0x000fba59, 0x000e9ac4, 0x000b7a3e, 0x000b7a3f],
    "barbarian" => [0x00057a26],
    "warrior" => [0x00028b44],
    "soldier" => [0x000fd82b, 0x000abf55, 0x000abf57, 0x000e108f, 0x000964df, 0x000abf44, 0x000abf56, 0x000abf58, 0x000d29a0, 0x000abf45, 0x000abf46, 0x000cd6dd, 0x000d29a1],
    "assassin" => [0x000e1ec2, 0x0010350b, 0x00065c53],
    "rogue" => [0x000e1ec2, 0x0010350b, 0x00065c53],
    "farmer" => [0x0002d75e],
    "citizen" => [0x000a1983],
    "bard" => [0x0009d5e0, 0x000e40dd, 0x000dab74, 0x000dab75, 0x000f8716, 0x000f8717, 0x000f871a, 0x000f8718],
    "noble" => [0x0009d5e0, 0x000e40dd, 0x000dab74, 0x000dab75, 0x000f8716, 0x000f8717, 0x000f871a, 0x000f8718],
    "merchant" => [0x0009d5e0, 0x000e40dd, 0x000dab74, 0x000dab75, 0x000f8716, 0x000f8717, 0x000f871a, 0x000f8718],
    "forsworn" => [0x00043bd9],
];

if (!function_exists("initializeQuestReferenceData")) {
    function initializeQuestReferenceData()
    {
        if (!function_exists("quest_reference_tables_ready") || !quest_reference_tables_ready()) {
            return;
        }

        $defaults = [
            "item_types" => $GLOBALS["item_types"] ?? [],
            "npc_templates" => $GLOBALS["npc_templates"] ?? [],
            "npc_own_templates" => $GLOBALS["npc_own_templates"] ?? [],
            "outfit" => $GLOBALS["outfit"] ?? [],
            "weapons" => $GLOBALS["weapons"] ?? [],
        ];

        foreach ($defaults as $datasetName => $valueMap) {
            quest_reference_add_missing_dataset_entries($datasetName, $valueMap);
        }

        $activeData = quest_reference_load_all_active();
        foreach (array_keys($defaults) as $datasetName) {
            if (isset($activeData[$datasetName]) && is_array($activeData[$datasetName])) {
                $GLOBALS[$datasetName] = $activeData[$datasetName];
            } else {
                $GLOBALS[$datasetName] = [];
            }
        }
    }
}

if (!function_exists("pickActiveQuestLocationFormId")) {
    function pickActiveQuestLocationFormId($locations, $location, $default = 0)
    {
        $cnLocation = strtolower((string) $location);
        if (!isset($locations[$cnLocation]) || !is_array($locations[$cnLocation]) || empty($locations[$cnLocation])) {
            return $default;
        }

        return $locations[$cnLocation][array_rand($locations[$cnLocation])];
    }
}

initializeQuestReferenceData();

function checkHistory($npc)
{

    $historyData = "";
    $lastPlace = "";
    $lastListener = "";
    $n = 0;
    foreach (json_decode(DataSpeechJournal($npc, 50), true) as $element) {

        if ($lastListener != $element["listener"]) {
            if ($element["listener"] != "The Narrator") {
                $listener = " (talking to {$element["listener"]})";
            }

            $lastListener = $element["listener"];
        } else {
            $listener = "";
        }

        if ($lastPlace != $element["location"]) {
            $place = " (at {$element["location"]})";
            $lastPlace = $element["location"];
        } else {
            $place = "";
        }

        if (strpos($element["speaker"], $npc) !== false); // Only NPC lines
        $n++;
    }

    return $n;
}

function askLLMForTopic($npc, $topic, $last_llm_call)
{

    $enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;

    $connector = new LLMConnector();
    $currentConnectorData = $connector->getById($GLOBALS["CORE_CONNECTOR_MEDIUMTERM"]);
    $connector->setOldGlobals($currentConnectorData);

    if ((time() - $last_llm_call) < 60) {
        error_log("Skipping askLLMForTopic: " . ((time() - $last_llm_call)));
        Logger::info("Skipping askLLMForTopic: " . ((time() - $last_llm_call)));
        return ["res" => false, "missing" => "skip"];
    }

    $historyData = "";
    $lastPlace = "";
    $lastListener = "";
    foreach (json_decode(DataSpeechJournal($npc, 50), true) as $element) {

        if ($lastListener != $element["listener"]) {
            if ($element["listener"] != "The Narrator") {
                $listener = " (talking to {$element["listener"]})";
            }

            $lastListener = $element["listener"];
        } else {
            $listener = "";
        }

        if ($lastPlace != $element["location"]) {
            $place = " (at {$element["location"]})";
            $lastPlace = $element["location"];
        } else {
            $place = "";
        }

        if (strpos($element["speaker"], $npc) !== false); // Only NPC lines
        $historyData .= trim("{$element["speaker"]}:" . trim($element["speech"]) . " $listener $place") . PHP_EOL;
    }

    $partyConf = DataGetCurrentPartyConf();
    $partyConfA = json_decode($partyConf, true);

    if (isset($partyConfA["{$npc}"])) {
        $charDesc = print_r($partyConfA["{$npc}"], true) . PHP_EOL . $GLOBALS["HERIKA_PERS"];
        $currentProfile = $charDesc;
    } else {
        $currentProfile = $GLOBALS["HERIKA_PERS"];
    }

    $head[] = ["role" => "system", "content" => "You are an assistant. You will analyze a dialogue and determine if a topic has been fully or partially covered. "];
    $prompt[] = ["role" => "user", "content" => "* Dialogue history:\n" . $historyData];
    $prompt[] = [
        "role" => "user",
        "content" => "is this topic/intent fully or partially covered in the dialogue history? Topic/Intent:\"$topic\".\n" .
            "Answer yes,or give a score from 1 , (not covered) to 10 (fully covered), and then write a dialogue sentence as the speaker (hint) to provide the missing info. Use a JSON object to give reponse {\"score\":[0-9],\"hint\":\"\"}"
    ];
    $contextData = array_merge($head, $prompt);

    $connectionHandler = $connector->getConnector($currentConnectorData);
    $buffer = $connectionHandler->fast_request($contextData, ["MAX_TOKENS" => 1024, "temperature" => 0.7], 'rolemaster_helper_asktopic');
    $parsedbuffer = __jpd_decode_lazy($buffer);
    error_log(print_r($buffer, true));
    $res = false;
    if (is_array($parsedbuffer)) {
        if (isset($parsedbuffer[0]["score"])) {
            $score = $parsedbuffer[0]["score"];
            $hint = $parsedbuffer[0]["hint"];
        } else if (isset($parsedbuffer["score"])) {
            $score = $parsedbuffer["score"];
            $hint = $parsedbuffer["hint"];
        } else {
            error_log("Score not found in parsed buffer: " . print_r($parsedbuffer, true));
        }

        if ($score >= 6) {
            $res = true;
        }

        $buffer = $hint;
    } else {
        if (preg_match('/Score:\s*(\d+)\//i', $buffer, $matches)) {
            // Extracted score is in $matches[1]
            $score = $matches[1];
            echo "Extracted Score: " . $score . PHP_EOL;
        } else {
            echo "Score not found." . PHP_EOL;
        }
        if (strpos(strtoupper($buffer), "YES") === 0) {
            $res = true;
        }

        if (strpos(strtoupper($buffer), "MOSTLY YES") === 0) {
            $res = true;
        }

        if ($score >= 6) {
            $res = true;
        }

        $buffer = strtr($buffer, ["Partially" => ""]);
    }

    Logger::debug($buffer);

    //$res=true;
    return ["res" => $res, "missing" => $buffer];
}

function simpleTopicCheck($npc, $topic)
{

    $lastListener = "";
    $lastPlace = "";
    $historyData = "";

    foreach (json_decode(DataSpeechJournal($npc, 50), true) as $element) {

        if ($lastListener != $element["listener"]) {
            if ($element["listener"] != "The Narrator") {
                $listener = " (talking to {$element["listener"]})";
            }

            $lastListener = $element["listener"];
        } else {
            $listener = "";
        }

        if ($lastPlace != $element["location"]) {
            $place = " (at {$element["location"]})";
            $lastPlace = $element["location"];
        } else {
            $place = "";
        }

        if (strpos($element["speaker"], $npc) !== false); // Only NPC lines
        $historyData .= trim("{$element["speaker"]}:" . trim($element["speech"]) . " $listener $place") . PHP_EOL;
    }

    if (strpos($historyData, $topic) !== false) {
        return true;
    } else {
        return false;
    }
}

function testSpawnRandomNPC()
{

    $names = [
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
        "Malik Firebrand",
    ];
    $classes = explode("|", "beggar|warrior|assassin|mage|farmer|soldier|merchant|noble");
    $genders = ["male", "female"];
    $races = explode("|", "nord|imperial|argonian|redguard|orc|breton");

    $name = $names[array_rand($names)];
    $class = $classes[array_rand($classes)];
    $race = $races[array_rand($races)];
    $gender = $genders[array_rand($genders)];

    Logger::debug("$name,$class,$race,$gender");
    npcProfileBase($name, $class, $race, $gender, "nearby", "test");
    return $name;
}

// Will use references from locations table (mainly markers) to get more precise location for placing things  or NPCs, 
// 
function getLocationReferences($locationFormId)
{
    $parm4 = $locationFormId;
    $dbDestination = $GLOBALS["db"]->fetchOne("SELECT refs,name FROM locations where formid=$locationFormId");
    if ($dbDestination) {
        if ($dbDestination["refs"] != "") {
            // refs are populated when plugin send locations. 
            // they have info about markers we can use to place things, as markers are available as refs even if cells where they are placed are unloaded
            // refs is string ins this form (refTypeId:formid;refTypeId:formid...): '0x000130f7:0x1901f12b;0x000130f8:0x1901f128;0x000130f8:0x1901f128;0x000130f7:0x1901f12b'
            // we must get the the id from type 0x000130f8 or 0x000130fd if any  (0x000130f8 takes precedence over 0x000130fd), 
            // if none of them are present -> then $localItemPlace = $dbDestination["formid"];
            // if found, return the formid of the refTypeId found
            $refsRaw = (string) ($dbDestination["refs"] ?? '');
            $refPairs = array_filter(array_map('trim', explode(';', $refsRaw)));

            $candidates130f8 = [];
            $candidates130fd = [];

            foreach ($refPairs as $pair) {
                $parts = array_map('trim', explode(':', $pair, 2));
                if (count($parts) !== 2) {
                    continue;
                }

                [$refTypeId, $refFormId] = $parts;
                $refTypeId = strtolower($refTypeId);

                // LocationCenterMarker (0x0001bdf1)
                // BossTreasureMarker 0x000130f9
                // InsideEntranceMarker (0x000130fc)
                if ($refTypeId === '0x000130f9') {
                    $candidates130f8[] = $refFormId;
                } elseif ($refTypeId === '0x0001bdf1') {
                    $candidates130fd[] = $refFormId;
                }
            }

            if (!empty($candidates130f8)) {
                $localItemPlaceHex = $candidates130f8[array_rand($candidates130f8)];
                $unsignedInt = hexdec($localItemPlaceHex);
                // Convert to 32-bit signed integer
                if ($unsignedInt >= 0x80000000) {
                    $unsignedInt -= 0x100000000;
                }
                $parm4 = $unsignedInt;
            } elseif (!empty($candidates130fd)) {
                $localItemPlaceHex = $candidates130fd[array_rand($candidates130fd)];
                $unsignedInt = hexdec($localItemPlaceHex);
                // Convert to 32-bit signed integer
                if ($unsignedInt >= 0x80000000) {
                    $unsignedInt -= 0x100000000;
                }
                $parm4 = $unsignedInt;
            } else {
                $parm4 = $dbDestination["formid"];
            }
        } else
            $parm4 = $dbDestination["formid"];
    }

    return $parm4;
}


function npcProfileBase($name, $class, $race, $gender, $location, $taskId, $additionalData = [])
{

    /*
    SELECT STRING_AGG(formid,',') FROM "public"."npc_skyrim_data" where gender ilike 'male%' and race ilike 'nord%' and name='' and class ilike '%bandit%' and edid like 'Enc%' and achr='' and (not formid ilike '%0xDG%')  and (not  edid ilike '%magic%')
    */
    global $outfit;

    $class = strtolower($class);
    $race = strtolower($race);
    $gender = strtolower($gender);
    $location = strtolower($location);

    $masterDataTemplates = $GLOBALS["npc_templates"];
    $masterData = $GLOBALS["npc_own_templates"];


    $weapons = $GLOBALS["weapons"] ?? [];

    $locations = [];
    $locationRows = $GLOBALS["db"]->fetchAll("SELECT lower(name) AS location_key, formid FROM locations WHERE formid IS NOT NULL");
    foreach ($locationRows as $locRow) {
        $locationKey = strtolower(trim((string) ($locRow["location_key"] ?? "")));
        $locationFormId = quest_reference_normalize_formid($locRow["formid"] ?? null);
        if ($locationKey === "" || $locationFormId === null || $locationFormId <= 0) {
            continue;
        }

        if (!isset($locations[$locationKey])) {
            $locations[$locationKey] = [];
        }

        $locations[$locationKey][] = intval($locationFormId);
    }

    $addedLocs = $GLOBALS["db"]->fetchAll("SELECT lower(name) AS location_key, formid FROM locations WHERE name ilike '%" . $GLOBALS["db"]->escape($location) . "%' LIMIT 5");
    foreach ($addedLocs as $addedLoc) {
        $addedKey = strtolower(trim((string) ($addedLoc["location_key"] ?? $location)));
        $addedFormId = quest_reference_normalize_formid($addedLoc["formid"] ?? null);
        if ($addedKey === "" || $addedFormId === null || $addedFormId <= 0) {
            continue;
        }

        if (!isset($locations[$addedKey])) {
            $locations[$addedKey] = [];
        }

        $locations[$addedKey][] = intval($addedFormId);
    }

    error_log("Using masterDataTemplates[{$gender}_{$race}]");

    $dclass = $class;

    if ($class == "priest") {
        $dclass = "mage";
    }

    $templateKey = "{$gender}_{$race}";
    $classTemplateKey = "{$gender}_{$race}_{$dclass}";
    $parm5 = quest_reference_pick_random($masterDataTemplates, $templateKey, 0);
    error_log("Using masterData[{$classTemplateKey}] => $parm5");
    if ($parm5 === 0) {
        Logger::warn("[npcProfileBase] No active quest_npc_templates entry for {$templateKey}");
    }

    $humanoidRaces = quest_reference_playable_races();
    $parm1 = quest_reference_pick_safe_spawn_base($masterData, $gender, $race, $dclass);
    $usesChimOwnedSpawnBase = $parm1 !== 0;
    if ($parm1 === 0) {
        if (in_array($race, $humanoidRaces, true)) {
            Logger::warn("[npcProfileBase] Aborting humanoid spawn: no CHIM-owned base for {$classTemplateKey}");
            return;
        }
        // Generic creature records are complete spawn bases rather than appearance donors.
        $parm1 = $parm5;
    }
    if ($parm1 === 0) {
        Logger::warn("[npcProfileBase] Aborting spawn: no usable base for {$gender}_{$race}");
        return;
    }

    // Just for outfit

    if ($class == "priest") {
        $dclass = "bard";
    }

    if (isset($outfit["{$dclass}"])) {
        $parm2 = quest_reference_pick_random($outfit, $dclass, 0);
    } else {
        // Outfit 0 means creature
        $parm2 = 0;
    }

    if (in_array($race, ["draugr", "elk"])) {
        $parm2 = 0;
    }

    $rumors = false;
    $parm3 = quest_reference_pick_random(
        $weapons,
        $dclass,
        quest_reference_pick_random($weapons, "default", 0x00013989)
    );
    $patchedTaskid = $taskId;

    if ($location == "nearby") {
        $parm4 = 0;
    } else if ($location == "random") {
        $possibleLoc = [];
        foreach ($locations as $locKey => $locRefs) {
            if (is_array($locRefs) && !empty($locRefs)) {
                $possibleLoc[] = $locKey;
            }
        }
        if (!empty($possibleLoc)) {
            $location = $possibleLoc[array_rand($possibleLoc)];
            Logger::debug($location);
            $parm4 = pickActiveQuestLocationFormId($locations, $location, 0);
            $rumors = true;
        } else {
            Logger::warn("[npcProfileBase] No locations rows available for random spawn location");
            $parm4 = 0;
        }
    } else if (isset($locations[$location])) {
        $parm4 = pickActiveQuestLocationFormId($locations, $location, 0);
        if ($parm4 === 0) {
            Logger::warn("[npcProfileBase] No active reference found for location '{$location}'");
        }

        $locationRef = getLocationReferences($parm4);
        if ($locationRef)
            $parm4 = $locationRef;
    } else {
        $parm4 = 0;
        $locationCn = $GLOBALS["db"]->escape($location);
        $dbDestination = $GLOBALS["db"]->fetchOne("SELECT refs,name, similarity(name, '$locationCn') AS sim,formid FROM locations ORDER BY sim DESC LIMIT 1");
        if ($dbDestination) {
            $parm4 = quest_reference_normalize_formid($dbDestination["formid"] ?? 0) ?? 0;
            $locationRef = getLocationReferences($parm4);
            if ($locationRef) {
                $parm4 = $locationRef;
            }
        }
    }

    if (isset($additionalData["disposition"])) {
        if (in_array($additionalData["disposition"], ["aggressive"])) {
            $patchedTaskid = "1";
        } else {
            $patchedTaskid = $taskId;
        }
    } else if (isset($additionalData["disposition"])) {
        if (in_array($additionalData["disposition"], ["submissive"])) {
            $patchedTaskid = "2";
        } else {
            $patchedTaskid = $taskId;
        }
    }

    // SpawnAgent resolves CHIM-owned humanoid bases with GetFormFromFile,
    // which requires AIAgent.esp's local FormID rather than its runtime prefix.
    $wireParm1 = $usesChimOwnedSpawnBase
        ? quest_reference_formid_for_full_plugin_file($parm1)
        : quest_reference_formid_for_papyrus($parm1);
    $wireParm2 = quest_reference_formid_for_papyrus($parm2);
    $wireParm3 = quest_reference_formid_for_papyrus($parm3);
    $wireParm4 = quest_reference_formid_for_papyrus($parm4);
    $wireParm5 = quest_reference_formid_for_papyrus($parm5);

    $GLOBALS["db"]->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|spawnCharacter@{$name}@$wireParm1@$wireParm2@$wireParm3@$wireParm4@$patchedTaskid@$wireParm5",
            'tag' => "",
        ]
    );
    if ($rumors) {
        $GLOBALS["db"]->insert(
            'rumors',
            [
                'localts' => time(),
                'gamets' => $GLOBALS["gameRequest"][2],
                'ts' => $GLOBALS["gameRequest"][1],
                'location' => $location,
                'topic' => "$name has been seen nearby",
                'sess' => $taskId,
            ]
        );
    }
}

// basetype: note, book , or allowed $GLOBALS["item_types"] 
// name: the name of the item
// location: pocket, inventory, or a LCTN formid
// content: if book/note a descrption of the content (real conetent will be generated by AI)
// quest_id: the quest id to associate with the item (0 if not associated with a quest)
// npc_ref: name of the NPC having the item (null if not associated with an NPC)

function SkCreateItem($basetype, $name, $location, $content, $quest_id, $npc_ref = null)
{

    error_log("SkCreateItem($basetype, $name, $location, $content, $quest_id, $npc_ref ");

    $basetype = strtolower((string) $basetype);
    $masterData = $GLOBALS["item_types"];

    $localItemName = ($name);
    $localItemPlace = ($location);

    // Note is hardcoded. 
    // [warn] [SkCreateItem] Aborting item spawn for 'Faded Journal Page': no active quest_item_types entry for 'note'

    if ($basetype != "note") {  
        $localItemType = quest_reference_pick_random($masterData, $basetype, 0);
        if ($localItemType === 0) {
            Logger::warn("[SkCreateItem] Aborting item spawn for '{$name}': no active quest_item_types entry for '{$basetype}'");
            return;
        }
    }

    if ($localItemPlace == "nearby") {
        $localItemPlace = 0;
    } else if ($localItemPlace == "pocket") {
        $localItemPlace = 0;
        $npcMaster = new NpcMaster();
        $currentNpcData = $npcMaster->getByName($npc_ref);
        $unsignedInt = hexdec($currentNpcData["refid"]);
        // Convert to 32-bit signed integer
        if ($unsignedInt >= 0x80000000) {
            $unsignedInt -= 0x100000000;
        }
        $localItemPlace = $unsignedInt;
    } else if (preg_match('/^[a-zA-Z0-9\s\'-]+:0x[0-9a-fA-F]+$/', $location)) {
        $localItemPlace = 0;
        list($itemName, $refidHex) = explode(":", $location);
        $unsignedInt = hexdec($refidHex);
        // Convert to 32-bit signed integer
        if ($unsignedInt >= 0x80000000) {
            $unsignedInt -= 0x100000000;
        }
        $localItemPlace = $unsignedInt;
    } else {
        if (!is_numeric($localItemPlace)) {
            // Cells
            $locationCn = $GLOBALS["db"]->escape($location);
            $dbDestination = $GLOBALS["db"]->fetchOne("SELECT id FROM named_cell where cell_name='$location'");
            if ($dbDestination) {
                $localItemPlace = $dbDestination["id"];
            } else {
                // Location
                $dbDestination = $GLOBALS["db"]->fetchOne("SELECT refs,name, similarity(name, '$locationCn') AS sim,formid FROM locations ORDER BY sim DESC LIMIT 1");
                if ($dbDestination) {
                    $localItemPlace = $dbDestination["formid"];
                    $locationRef = getLocationReferences($localItemPlace);
                    if ($locationRef)
                        $localItemPlace = $locationRef;
                }
            }
        } else {; // ref is gonna be an NPC
        }
    }

    if ($basetype == "note" || $basetype == "book") {

        // Generate content for the book/note
        $connector = new LLMConnector();
        $currentConnectorData = $connector->getById($GLOBALS["CORE_CONNECTOR_MEDIUMTERM"]);
        $connector->setOldGlobals($currentConnectorData);

        $enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
        $questData = json_decode(file_get_contents($enginePath . "log" . DIRECTORY_SEPARATOR . "snqe_state.json"), true);
        $questData["journallist"] = $questData["journallist"] ?? [];
        $questData["nextlist"] = $questData["nextlist"] ?? [];
        $playerNameCn=$GLOBALS["db"]->escape($GLOBALS["PLAYER_NAME"]);
        $diaryRows = $GLOBALS["db"]->fetchOne("SELECT * FROM \"public\".\"diarylog\" where people='$playerNameCn' order by gamets desc limit 1");
        if ($diaryRows) {
            $lastDiaryEntry = "{$GLOBALS["PLAYER_NAME"]}'s last diary entry: " . $diaryRows["content"];
        } else {
            $lastDiaryEntry = "";
        }   
        $CONTEXT_INFO_SKYRIM_LORE = "
Player Character: {$GLOBALS["PLAYER_NAME"]}
$lastDiaryEntry
== Quest Data ==
{$questData["questtitle"]}
{$questData["briefing"]}
    
Quest Journal:
" . implode("\n", $questData["journallist"]) . "
" . implode("\n", $questData["nextlist"]) . "
== End of Quest Data ==

What is happening in the quest right now

{$questData["last_step"]}
";

        $head[] = ["role" => "system", "content" => "## You're an AI writer. Your must write a book/note involved in a storyline."];
        $prompt[] = ["role" => "user", "content" => $CONTEXT_INFO_SKYRIM_LORE];
        $prompt[] = [
            "role" => "user",
            "content" => "
## Writing Task

Read the quest context above and write the content of the in-game book/note titled **$name**.

### Content Requirements
- $content
- Current Owner: $npc_ref 
- Author: Full name. Infere from the quest context who could be the author (can be anonymous, or someone related to the book owner).

### Lore & Timeline
- The book was written **before the current quest events**.
- It should subtly foreshadow the quest’s mystery and dangers.
- You may reference **Skyrim lore** (vampires, curses, old ruins), but do **not** reference known vanilla NPCs or events directly.

### Writing Style
- Write in **first person**, as the original author (you must infere the autor given the quest context, can be anonymous, or someone related to the book owner).

### Format
- Begin with the title on its own line:  
  **$name**
- Follow with **up to 3 short paragraphs**.
- Total length must be **100 words or fewer**.
- Sign it (last paragraph)

### Hard Rules
- Do not describe current quest events or the player.
- Do not resolve the mystery completely—only provide hints.
- Keep everything grounded in the provided quest context.

### Main purpose.

- The book/note should provide **clues, foreshadowing, or context** for the quest, enhancing immersion and player engagement.
- Please, at least ONE CONCRETE CLUE for the player to investigate (Character, location or item) - highlight this clue in uppercase -
- Other clues can be subtle, atmospheric, or thematic, but must not give away the quest's resolution.

"
        ];

        $contextData = array_merge($head, $prompt);
        $connectionHandler = $connector->getConnector($currentConnectorData);
        $buffer = $connectionHandler->fast_request($contextData, ["MAX_TOKENS" => 2048, "temperature" => 1, "model" => "google/gemini-3.5-flash-lite"], 'rolemaster_helper_bookwriter');

        createBook($name, $buffer, $location, $quest_id, $npc_ref);
        return;
    }



    $GLOBALS["db"]->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|spawnItem@$localItemName@$localItemType@$localItemPlace@$quest_id",
            //'action' => "rolecommand|spawnItem@The Necklace of the Gods@necklace@Helgen@1",
            'tag' => "",
        ]
    );
}

function CreateItemNpc($basetype, $name, $npc)
{
    $basetype = strtolower((string) $basetype);
    $masterData = $GLOBALS["item_types"] ?? [];

    $localItemName = $GLOBALS["db"]->escape($name);
    $localItemNPC = $GLOBALS["db"]->escape($npc);
    $localItemType = quest_reference_pick_random($masterData, $basetype, 0);
    if ($localItemType === 0) {
        Logger::warn("[CreateItemNpc] Aborting item spawn for '{$name}': no active quest_item_types entry for '{$basetype}'");
        return;
    }

    $taskId = "";
    $GLOBALS["db"]->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|spawnItemNPC@$localItemName@$localItemType@$localItemNPC@$taskId",
            //'action' => "rolecommand|spawnItem@The Necklace of the Gods@necklace@Helgen@1",
            'tag' => "",
        ]
    );
}

function createQuestFromTemplate($template, $notes)
{

    $enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;

    $diaryConnector = function_exists('chimResolveDiaryConnectorName') ? chimResolveDiaryConnectorName() : null;
    if ($diaryConnector === null) {
        return false;
    }

    require_once $enginePath . "connector" . DIRECTORY_SEPARATOR . "{$diaryConnector}.php";

    $head[] = ["role" => "system", "content" => $GLOBALS["AIQUEST_TEMPLATE"]];
    $prompt[] = ["role" => "user", "content" => json_encode($template)];
    $prompt[] = [
        "role" => "user",
        "content" =>
        "Change this quest's characters and topics and title, but keep same stage structure. Characters must adhere to the definition of createCharacter. $notes. Output only JSON data",
    ];
    $contextData = array_merge($head, $prompt);

    //print_r($contextData);
    $connectionHandler = new $diaryConnector();
    $GLOBALS["FORCE_MAX_TOKENS"] = 2048;
    $connectionHandler->open($contextData, ["MAX_TOKENS" => 2048]);
    $buffer = "";
    $totalBuffer = "";
    $breakFlag = false;
    while (true) {

        if ($breakFlag) {
            break;
        }

        if ($connectionHandler->isDone()) {
            $breakFlag = true;
        }

        $buffer .= $connectionHandler->process();
        $totalBuffer .= $buffer;
        //$bugBuffer[]=$buffer;

    }
    $connectionHandler->close();

    $originalBuffer = $buffer;
    $parsedbuffer = __jpd_decode_lazy($buffer);

    Logger::debug($originalBuffer);

    if (is_array($parsedbuffer)) {
        return $parsedbuffer;
    } else {
        return false;
    }
}

function createBook($title, $content, $location, $quest_id, $npc_ref = null)
{

    $width = 371;
    $height = 471;

    $text = $content;
    $name = $title;

    // Ensure $localItemPlace is initialized from the provided $location parameter
    $localItemPlace = $location;

    if ($localItemPlace == "nearby") {
        $localItemPlace = 0;
    } else if ($localItemPlace == "pocket") {
        $localItemPlace = 0;
        $npcMaster = new NpcMaster();
        $currentNpcData = $npcMaster->getByName($npc_ref);
        $unsignedInt = hexdec($currentNpcData["refid"]);
        // Convert to 32-bit signed integer
        if ($unsignedInt >= 0x80000000) {
            $unsignedInt -= 0x100000000;
        }
        $localItemPlace = $unsignedInt;
    } else if (preg_match('/^[a-zA-Z0-9\s\'-]+:0x[0-9a-fA-F]+$/', $localItemPlace)) {
        $localItemPlace = 0;
        list($itemName, $refidHex) = explode(":", $localItemPlace);
        $unsignedInt = hexdec($refidHex);
        // Convert to 32-bit signed integer
        if ($unsignedInt >= 0x80000000) {
            $unsignedInt -= 0x100000000;
        }
        $localItemPlace = $unsignedInt;
    } else {
        if (!is_numeric($localItemPlace)) {
            $locationCn = $GLOBALS["db"]->escape($location);
            $dbDestination = $GLOBALS["db"]->fetchOne("SELECT refs,name, similarity(name, '$locationCn') AS sim,formid FROM locations ORDER BY sim DESC LIMIT 1");
            if ($dbDestination) {
                if ($dbDestination["refs"] != "") {
                    // refs are populated when plugin send locations. 
                    // they have info about markers we can use to place things, as markers are available as refs even if cells where they are placed are unloaded
                    // refs is string ins this form (refTypeId:formid;refTypeId:formid...): '0x000130f7:0x1901f12b;0x000130f8:0x1901f128;0x000130f8:0x1901f128;0x000130f7:0x1901f12b'
                    // we must get the the id from type 0x000130f8 or 0x000130fd if any  (0x000130f8 takes precedence over 0x000130fd), 
                    // if none of them are present -> then $localItemPlace = $dbDestination["formid"];
                    // if found, return the formid of the refTypeId found
                    $refsRaw = (string) ($dbDestination["refs"] ?? '');
                    $refPairs = array_filter(array_map('trim', explode(';', $refsRaw)));

                    $candidates130f8 = [];
                    $candidates130fd = [];

                    foreach ($refPairs as $pair) {
                        $parts = array_map('trim', explode(':', $pair, 2));
                        if (count($parts) !== 2) {
                            continue;
                        }

                        [$refTypeId, $refFormId] = $parts;
                        $refTypeId = strtolower($refTypeId);

                        // LocationCenterMarker (0x0001bdf1)
                        // BossTreasureMarker 0x000130f9
                        // InsideEntranceMarker (0x000130fc)
                        if ($refTypeId === '0x000130f9') {
                            $candidates130f8[] = $refFormId;
                        } elseif ($refTypeId === '0x0001bdf1') {
                            $candidates130fd[] = $refFormId;
                        }
                    }

                    if (!empty($candidates130f8)) {
                        $localItemPlaceHex = $candidates130f8[array_rand($candidates130f8)];
                        $unsignedInt = hexdec($localItemPlaceHex);
                        // Convert to 32-bit signed integer
                        if ($unsignedInt >= 0x80000000) {
                            $unsignedInt -= 0x100000000;
                        }
                        $localItemPlace = $unsignedInt;
                    } elseif (!empty($candidates130fd)) {
                        $localItemPlaceHex = $candidates130fd[array_rand($candidates130fd)];
                        $unsignedInt = hexdec($localItemPlaceHex);
                        // Convert to 32-bit signed integer
                        if ($unsignedInt >= 0x80000000) {
                            $unsignedInt -= 0x100000000;
                        }
                        $localItemPlace = $unsignedInt;
                    } else {
                        $localItemPlace = $dbDestination["formid"];
                    }
                } else
                    $localItemPlace = $dbDestination["formid"];
            }
        } else {; // ref is gonna be an NPC
        }
    }


    createLetter($title, $content);


    $GLOBALS["db"]->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|spawnBook@$name@0@$localItemPlace@{$GLOBALS["taskId"]}@$name",
            'tag' => "",
        ]
    );
    // This will force DLL plugin to download
    $GLOBALS["db"]->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|generateLetter@$name",
            'tag' => "",
        ]
    );

    $GLOBALS["db"]->insert(
        'books',
        array(
            'ts' => DataLastKnownTS(),
            'gamets' => DataLastKnownGameTS(),
            'content' => $content,
            'sess' => 'generated',
            'localts' => time(),
            'title' => $title
        )
    );
}

function createLetter($title, $content)
{

    $width = 371;
    $height = 471;

    $text = $content;
    $name = $title;

    $fontPath = __DIR__ . '/../data/fonts/GloriaHallelujah-Regular.ttf'; // Path to your TTF font file
    $fontSize = 12;                                                      // Initial font size (we'll adjust if needed)
    $minFontSize = 8;                                                       // smallest allowed font size
    $maxTextHeight = 271;                                                     // maximum allowed vertical space for text

    $backgroundPath = __DIR__ . '/../data/textures/chim.png';

    $background = imagecreatefrompng($backgroundPath);

    // Ensure the background has alpha transparency
    imagesavealpha($background, true);

    // Define the text color
    $textColor = imagecolorallocate($background, 0, 0, 0); // Black color

    // Split text into paragraphs based on newlines
    $paragraphs = explode("\n", $text);

    // We'll try decreasing font size until the rendered text height fits inside $maxTextHeight
    $fittingFontSize = $fontSize;
    while ($fittingFontSize >= $minFontSize) {
        $yTest = 10; // starting top margin for measurement
        $fits = true;

        foreach ($paragraphs as $paragraph) {
            $words = explode(" ", $paragraph);
            $line = "";

            foreach ($words as $word) {
                $testLine = $line . $word . " ";
                $bbox = imagettfbbox($fittingFontSize, 0, $fontPath, $testLine);
                $lineWidth = abs($bbox[4] - $bbox[0]);

                if ($lineWidth > $width) {
                    // new line
                    $line = $word . " ";
                    $yTest += $fittingFontSize * 1.9; // approximate line height for measurement
                } else {
                    $line = $testLine;
                }
            }

            if (trim($line) !== "") {
                $yTest += $fittingFontSize * 1.9;
            }

            // paragraph spacing
            $yTest += $fittingFontSize * 0.8;

            if ($yTest > $maxTextHeight) {
                $fits = false;
                break;
            }
        }

        if ($fits) {
            break; // $fittingFontSize fits
        }

        $fittingFontSize--;
    }

    // Use $fittingFontSize to actually render text
    $x = 10; // Small left margin
    $y = 10; // Small top margin
    $fontSize = $fittingFontSize + 2;

    foreach ($paragraphs as $paragraph) {
        // Split each paragraph into lines that fit within image width
        $words = explode(" ", $paragraph);
        $line = "";

        foreach ($words as $word) {
            $testLine = $line . $word . " ";
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $testLine);
            $lineWidth = abs($bbox[4] - $bbox[0]);

            if ($lineWidth > $width) {
                // Draw the current line and start a new line if it exceeds the boundary
                $x = round($x, 0);
                $y = round($y, 0);
                imagettftext($background, $fontSize, 0, $x, $y, $textColor, $fontPath, trim($line));
                $line = $word . " ";
                $y += $fontSize * 1.9; // Move down for the next line
            } else {
                $line = $testLine;
            }
        }

        // Draw the last line of the paragraph
        if (trim($line) !== "") {
            $x = round($x, 0);
            $y = round($y, 0);
            imagettftext($background, $fontSize, 0, $x, $y, $textColor, $fontPath, trim($line));
            $y += $fontSize * 1.9;
        }

        // Add extra space between paragraphs
        $y += $fontSize * 0.8;
    }

    // Output the final image with text overlay
    @mkdir(__DIR__ . "/../data/books");
    $filename = __DIR__ . "/../data/books/" . md5(string: strtolower(string: $name)) . ".png";
    imagepng($background, $filename);

    // Free up memory
    imagedestroy($background);

    echo "Image saved as $filename" . PHP_EOL;
}

function make_replacements($text)
{

    return strtr($text, [
        "#LOCATION#" => DataLastKnownLocationHuman(),
        "#PLAYER#" => $GLOBALS["PLAYER_NAME"],
    ]);
}



function convertSignedToUnsignedHex($signedInt)
{
    // Convert signed to unsigned using bitwise AND
    $unsignedInt = $signedInt & 0xFFFFFFFF;
    return "0x" . str_pad(dechex($unsignedInt), 8, "0", STR_PAD_LEFT);
}

function convertHex($signedInt)
{
    // Convert signed to unsigned using bitwise AND
    $unsignedInt = $signedInt;
    return "0x" . str_pad(dechex($unsignedInt), 8, "0", STR_PAD_LEFT);
}

function SkTopicCheck($character, $topic, $lastCall, $retries, $quest_id)
{
    $contextDataHistoric = checkHistory($character);

    if (simpleTopicCheck($character, $topic)) {
        return TOPIC_COVERED;
    }

    // To avoid call LLM all times
    if ((time() - $lastCall) < 60) {
        if ($GLOBALS["NPCS_ARE_NOT_TALKING"] == 1) {
            error_log("[SkTopicCheck]\t SHOULD DO LATER, but  NPCS_ARE_NOT_TALKING <$character>  <$retries> <$quest_id>, will call in " . ((time() - $lastCall - 90)));
        } else {
            error_log("[SkTopicCheck]\t WILL_DO_LATER <$character>  <$retries> <$quest_id>, will call in " . ((time() - $lastCall - 90)));
            return WILL_DO_LATER;
        }
    }

    error_log("[SkTopicCheck]\t <$character>  <$retries> <$quest_id>");

    $characterCn = $GLOBALS["db"]->escape($character);
    $checkDeath = $GLOBALS["db"]->fetchOne("SELECT 1 as n from eventlog where type='death' and data like '%defeated $characterCn%'");

    if (isset($checkDeath["n"]) && $checkDeath["n"]) {
        $contextDataHistoric = 4;
        error_log("[SkTopicCheck]\t NPC is dead <$character>  <$retries> <$quest_id>");
    }

    if (($contextDataHistoric) >= 4) {
        // But first, check if topic already has been covered
        error_log("[SkTopicCheck]\tMaking LLM request: $lastCall, interval <" . (time() - $lastCall) . ">");

        $topiCall = askLLMForTopic($character, $topic, $lastCall);

        if ($topiCall["res"]) {
            return TOPIC_COVERED;
        } else {
            // Make suggestion, topic not covered
            $sugggestionText = make_replacements("$character must talk to #PLAYER# about something like: $topic. but using own words and speech style, and following current dialogue context. If topic already said, rephrase and just follow up");
            //$hintData = ("{$quest_data["npcs"][$npc_ref]["name"]} should talk to {$GLOBALS["PLAYER_NAME"]} about this topic: \"{$quest_data["topics"][$topic_ref]["info"]}\", but using own words and speech style, and following current dialogue context. If topic already said, just follow up");
            if ($topiCall["missing"]) {
                $sugggestionText = make_replacements("$character must talk to #PLAYER# about something like: {$topiCall["missing"]}. but using own words and speech style, and following current dialogue context. If topic already said, rephrase and just follow up");
            }

            if (isset($topiCall["missing"]) && $topiCall["missing"] != "skip") {
                $GLOBALS["db"]->insert(
                    'responselog',
                    [
                        'localts' => time(),
                        'sent' => 0,
                        'actor' => "rolemaster",
                        'text' => "",
                        'action' => "rolecommand|Suggestion@{$character}@$sugggestionText@$quest_id",
                        'tag' => "",
                    ]
                );
                error_log("[SkTopicCheck] Topic enforced <$topic> <{$character}>");
                return TOPIC_UNCOVERED;
            } else {
                error_log("[SkTopicCheck] WILL_DO_LATER <$topic> <{$character}>");
                return WILL_DO_LATER;
            }
        }
    } else {
        error_log("[SkTopicCheck]\tNot enough dialogue with NPC <$topic> <{$character}> n:{$contextDataHistoric}");
        // Not enough dialogue with NPC
        if (($retries % 30) == 0) {
            // Make suggestion, dialogue is too small

            $sugggestionText = make_replacements("$character must talk to #PLAYER# about something like: <$topic> (using its own words)");
            $GLOBALS["db"]->insert(
                'responselog',
                [
                    'localts' => time(),
                    'sent' => 0,
                    'actor' => "rolemaster",
                    'text' => "",
                    'action' => "rolecommand|Suggestion@{$character}@$sugggestionText@$quest_id",
                    'tag' => "",
                ]
            );
            error_log("[SkTopicCheck]\tTopic enforced <$topic> <{$character}>");
        }
        return TOPIC_TOOEARLY;
    }
}


function speechStyleRandomizer($race, $class)
{

    $styles = [
        "nord" => [
            "beggar" => [
                "description" => "Simple, blunt, and weather-beaten. Speaks plainly with no patience for flowery words. Often bitter, sometimes angry, always tired of the cold and hunger.",
                "examples" => [
                    "Spare a coin, will you? Haven't eaten worth a damn in days.",
                    "Life don't care if you're strong or weak. It just kicks you anyway."
                ]
            ],
            "mage" => [
                "description" => "Wise and thoughtful, but grounded. Avoids pretension. Sounds like a scholar who’s seen battle and frostbite alike.",
                "examples" => [
                    "Magic is a tool, same as steel. Respect it, or it will gut you.",
                    "Knowledge without restraint is just another way to die."
                ]
            ],
            "barbarian" => [
                "description" => "Gruff, blunt, and unapologetically savage. Short sentences. Little patience. Swears freely.",
                "examples" => [
                    "Talk less. Fight more.",
                    "If it bleeds, I can kill the bastard."
                ]
            ],
            "warrior" => [
                "description" => "Honorable and straightforward. Speaks with pride, discipline, and a deep respect for combat and oaths.",
                "examples" => [
                    "I gave my word. That’s the end of it.",
                    "Stand your ground or die on your feet."
                ]
            ],
            "soldier" => [
                "description" => "Precise, disciplined, and efficient. No wasted words. Sounds like someone used to orders and chain of command.",
                "examples" => [
                    "State your business. Quickly.",
                    "We hold the line. No excuses."
                ]
            ],
            "assassin" => [
                "description" => "Cold, quiet, and efficient. Speaks only when necessary. Threats are subtle but unmistakable.",
                "examples" => [
                    "You won’t hear me when it matters.",
                    "This ends clean. For me."
                ]
            ],
            "rogue" => [
                "description" => "Cunning and sharp-tongued. Dry humor, casual lies, and playful mockery.",
                "examples" => [
                    "Relax. If I wanted you dead, you'd already be bleeding.",
                    "Trust me — or don’t. Either way, I get paid."
                ]
            ],
            "farmer" => [
                "description" => "Practical and no-nonsense. Talks about work, weather, and survival. Hates bullshit.",
                "examples" => [
                    "If the soil’s frozen, nothing grows. Same with people.",
                    "Hard work don’t care how you feel."
                ]
            ],
            "citizen" => [
                "description" => "Friendly but guarded. Polite, plain speech, avoids trouble when possible.",
                "examples" => [
                    "Good day to you. Cold one, isn’t it?",
                    "Best keep your head down around here."
                ]
            ],
            "bard" => [
                "description" => "Poetic, dramatic, and loud. Loves metaphor, drink, and exaggerated heroics.",
                "examples" => [
                    "Steel sang, blood fell, and legends were born that night!",
                    "Ah, but every hero bleeds eventually."
                ]
            ],
            "noble" => [
                "description" => "Refined but stern. Speaks with authority and restrained arrogance.",
                "examples" => [
                    "Mind your tone when addressing your betters.",
                    "Honor is not a luxury. It is a duty."
                ]
            ],
            "merchant" => [
                "description" => "Persuasive, sharp, and calculating. Friendly on the surface, ruthless underneath.",
                "examples" => [
                    "Fair price — for someone like you.",
                    "Gold talks. Everything else is noise."
                ]
            ],
            "forsworn" => [
                "description" => "Fierce, hateful, and unhinged. Filled with rage, resentment, and rebellion.",
                "examples" => [
                    "The cities will burn, stone by stone!",
                    "Your laws mean nothing in our hills!"
                ]
            ],
        ],

        "breton" => [
            "beggar" => [
                "description" => "Humble and polite, even when desperate. Soft voice, apologetic tone.",
                "examples" => [
                    "Forgive me for asking, but could you spare a coin?",
                    "I wouldn’t beg if I had any other choice."
                ]
            ],
            "mage" => [
                "description" => "Highly erudite and mystical. Uses academic language and arcane references.",
                "examples" => [
                    "The weave responds to discipline, not brute force.",
                    "Ignorance is the most dangerous spell of all."
                ]
            ],
            "barbarian" => [
                "description" => "Unrefined but passionate. Less savage than Nords, more emotional and reckless.",
                "examples" => [
                    "I don’t need fancy words to crack your skull.",
                    "Magic or muscle — pain is pain."
                ]
            ],
            "warrior" => [
                "description" => "Chivalrous and idealistic. Talks of honor, valor, and duty.",
                "examples" => [
                    "By my blade and my oath, I will see this done.",
                    "Courage defines us."
                ]
            ],
            "soldier" => [
                "description" => "Orderly, loyal, and procedural. Speaks like a trained professional.",
                "examples" => [
                    "Follow protocol and no one gets hurt.",
                    "Orders are orders."
                ]
            ],
            "assassin" => [
                "description" => "Soft-spoken, elegant, and deadly. Almost polite — which makes it worse.",
                "examples" => [
                    "You’ll feel nothing. I promise.",
                    "This is merely business."
                ]
            ],
            "rogue" => [
                "description" => "Charming, sly, and silver-tongued. Lies smoothly and smiles while doing it.",
                "examples" => [
                    "Now, now — let’s not make this unpleasant.",
                    "Trust is such a fragile thing."
                ]
            ],
            "farmer" => [
                "description" => "Honest and hardworking. Calm, plain, and focused on daily life.",
                "examples" => [
                    "Sun comes up, work gets done.",
                    "Land rewards those who respect it."
                ]
            ],
            "citizen" => [
                "description" => "Polite and sociable. Mild manners, avoids confrontation.",
                "examples" => [
                    "Good evening. How may I help you?",
                    "Let’s all stay civil, yes?"
                ]
            ],
            "bard" => [
                "description" => "Imaginative and romantic. Loves tales of love, tragedy, and magic.",
                "examples" => [
                    "Ah, love cuts deeper than any blade!",
                    "Every story deserves a beautiful ending."
                ]
            ],
            "noble" => [
                "description" => "Sophisticated, educated, and subtly condescending.",
                "examples" => [
                    "You may speak, but choose your words carefully.",
                    "Lineage matters more than you realize."
                ]
            ],
            "merchant" => [
                "description" => "Smooth, logical, and persuasive. Uses reason and charm interchangeably.",
                "examples" => [
                    "An investment, not an expense.",
                    "Think of the long-term gains."
                ]
            ],
            "forsworn" => [
                "description" => "Emotionally charged and defiant. Speaks with bitterness and revolutionary fire.",
                "examples" => [
                    "Your kingdoms rot from the inside!",
                    "We will take back what was stolen!"
                ]
            ],
        ],
    ];

    return $styles[$race][$class] ?? null;
}

function getSpeechStyleText($race, $class)
{
    $style = speechStyleRandomizer($race, $class);

    if (
        !$style ||
        !is_array($style) ||
        empty($style['description'])
    ) {
        return null;
    }

    $text = $style['description'];

    if (!empty($style['examples']) && is_array($style['examples'])) {

        $text .= PHP_EOL . " \"" . implode("\",\"", $style['examples']) . "\"";
    }

    return $text;
}

function speechFillerRandomizer()
{

    $fillers = [
        "common" => [
            "you know",
            "I mean",
            "listen",
            "look",
            "well",
            "right",
            "anyway",
            "so"
        ],

        "hesitation" => [
            "uh",
            "um",
            "erm",
            "hmm"
        ],

        "affirmation" => [
            "yeah",
            "aye",
            "mm-hm",
            "that’s right"
        ],

        "rough" => [
            "damn it",
            "bloody hell",
            "for fuck’s sake",
            "by the gods",
            "hells"
        ],

        "folk" => [
            "if you ask me",
            "mark my words",
            "as sure as rain",
            "truth be told"
        ],

        "ending" => [
            "if you catch my drift",
            "you get me",
            "that’s how it is",
            "so there you have it"
        ],
    ];

    return $fillers;
}

function getRandomSpeechFillers(
    int $min = 1,
    int $max = 3
) {
    $data = speechFillerRandomizer();

    if (!$data || !is_array($data)) {
        return null;
    }

    $all = [];

    foreach ($data as $group) {
        if (is_array($group)) {
            $all = array_merge($all, $group);
        }
    }

    if (empty($all)) {
        return null;
    }

    shuffle($all);

    $count = rand($min, min($max, count($all)));
    $selected = array_slice($all, 0, $count);

    return PHP_EOL . "Usual expressions & filler words: " . implode(", ", $selected) . PHP_EOL;
}


function getQuestDataTxt($quest_data)
{

    if (empty($quest_data) || !is_array($quest_data)) {
        return null;
    }

    $lines = [];

    // --- NPCs ---
    if (!empty($quest_data['npcs']) && is_array($quest_data['npcs'])) {
        $npcLines = [];
        foreach ($quest_data['npcs'] as $npcKey => $npc) {
            if (empty($npc['name'])) continue;

            $disposition = strtolower($npc['disposition'] ?? '');
            $role = in_array($disposition, ['furious', 'hostile', 'aggressive', 'enemy']) ? 'foe' : 'friend';

            $parts = [$npc['name'] . ': ' . $role];

            if (!empty($npc['background'])) {
                $parts[] = $npc['background'];
            }
            if (!empty($npc['goal'])) {
                $parts[] = 'Goal: ' . $npc['goal'];
            }

            $npcLines[] = implode('. ', $parts);
        }
        if (!empty($npcLines)) {
            $lines[] = 'NPCs involved:';
            foreach ($npcLines as $l) {
                $lines[] = '- ' . $l;
            }
        }
    }

    // --- Items ---
    if (!empty($quest_data['items']) && is_array($quest_data['items'])) {
        $itemLines = [];
        foreach ($quest_data['items'] as $itemKey => $item) {
            if (empty($item['name'])) continue;

            $entry = $item['name'];
            if (!empty($item['description'])) {
                $entry .= ': ' . $item['description'];
            }
            $itemLines[] = $entry;
        }
        if (!empty($itemLines)) {
            $lines[] = 'Items involved:';
            foreach ($itemLines as $l) {
                $lines[] = '- ' . $l;
            }
        }
    }

    // --- Topics ---
    if (!empty($quest_data['topics']) && is_array($quest_data['topics'])) {
        // Build a name lookup for NPCs so we can say "only known by <name>"
        $npcNames = [];
        if (!empty($quest_data['npcs']) && is_array($quest_data['npcs'])) {
            foreach ($quest_data['npcs'] as $npcKey => $npc) {
                $npcNames[$npcKey] = $npc['name'] ?? $npcKey;
            }
        }

        $topicLines = [];
        foreach ($quest_data['topics'] as $topicKey => $topic) {
            if (empty($topic['name']) || empty($topic['info'])) continue;

            $entry = $topic['name'];
            if (!empty($topic['giver']) && isset($npcNames[$topic['giver']])) {
                $entry .= ' (only known by ' . $npcNames[$topic['giver']] . ')';
            }
            $entry .= ': ' . $topic['info'];
            $topicLines[] = $entry;
        }
        if (!empty($topicLines)) {
            $lines[] = 'Topics involved:';
            foreach ($topicLines as $l) {
                $lines[] = '- ' . $l;
            }
        }
    }

    if (empty($lines)) {
        return null;
    }

    return PHP_EOL . implode(PHP_EOL, $lines) . PHP_EOL;
}

function enhanceProfileUsingQuestData($quest_data, $npc)
{

    $questDataTxt = getQuestDataTxt($quest_data);
    $questData = json_decode(file_get_contents($GLOBALS["ENGINE_PATH"] . "log" . DIRECTORY_SEPARATOR . "snqe_state.json"), true);

    $questTitle = $questData["questtitle"] ?? "Unknown Quest";
    $questBriefing = $questData["briefing"] ?? "Unknown Quest";
    $quest1stStep = $questData["nextlist"][0] ?? "Unknown Quest Step";
    $lorePrompt = "

Title: {$questTitle}

Intro: {$questBriefing}

1st quest step: {$quest1stStep}

$questDataTxt

I need you to generate a background lore for {$npc['name']} in this quest, based on the above information.

Format: three short paragraphs

Past biography – Briefly describe {$npc['name']}'s background, history, and any relevant personal traits or experiences that shaped her.
Reason for involvement – Explain why she/he is involved in this quest and what motivates her participation.
Knowledge about the quest – Describe what she/he actually knows about the quest. Avoid mention what she/he doesn't know
";

    // Generate content for the book/note
    $connector = new LLMConnector();
    $currentConnectorData = $connector->getById($GLOBALS["CORE_CONNECTOR_MEDIUMTERM"]);
    $connector->setOldGlobals($currentConnectorData);



    $head[] = ["role" => "system", "content" => "## You're an AI writer. "];
    $prompt[] = ["role" => "user", "content" => $lorePrompt];

    $contextData = array_merge($head, $prompt);
    $connectionHandler = $connector->getConnector($currentConnectorData);
    $buffer = $connectionHandler->fast_request($contextData, ["MAX_TOKENS" => 2048, "temperature" => 0.7, "model" => "google/gemini-2.5-flash-lite"], 'rolemaster_helper_bookwriter');

    $npcmaster = new NpcMaster();
    $npcProfile = $npcmaster->getByName($npc['name']);
    $npcProfile["npc_static_bio"] .= "\n$buffer";
    $npcmaster->updateByArray($npcProfile);
}

function getLocationsNearNpcCoords($npcName)
{

    $npcMaster = new NpcMaster();
    $npcData = $npcMaster->getByName($npcName);
    if (empty($npcData)) {
        return [];
    }

    $metadata = $npcMaster->getMetadata($npcData);
    if (is_string($metadata)) {
        $metadata = json_decode($metadata, true);
    }

    if (!is_array($metadata)) {
        return [];
    }

    $lastCoords = $metadata['last_coords'] ?? null;

    // Fallback to the latest historical coords when last_coords is missing.
    if (!is_array($lastCoords) && !empty($metadata['last_coords_history']) && is_array($metadata['last_coords_history'])) {
        $history = $metadata['last_coords_history'];
        $lastCoords = end($history);
    }

    if (!is_array($lastCoords)) {
        return [];
    }

    $x = $lastCoords[0] ?? null;
    $y = $lastCoords[1] ?? null;

    if (!is_numeric($x) || !is_numeric($y)) {
        return [];
    }

    $x = floatval($x);
    $y = floatval($y);

    $db = $GLOBALS['db'];
    $pointLiteral = '(' . $x . ',' . $y . ')';
    $pointEsc = $db->escape($pointLiteral);
    $worldEsc = $db->escape($lastCoords["world"] ?? '');

    // Abandoned Shack locations is bugged as is child of Batte-Born Farm.
    $closestLocations = $db->fetchAll(
        "SELECT
            name,
            formid,
            region,
            hold,
            coords,
            tags,
            is_interior,
            coords <-> '{$pointEsc}'::point AS distance
         FROM locations
         WHERE coords IS NOT NULL
         and name<>'Abandoned Shack'
         and coords <-> '{$pointEsc}'::point < 6000
         and world IN ('{$worldEsc}','')
         ORDER BY case when world = '{$worldEsc}' then coords <-> '{$pointEsc}'::point else (coords <-> '{$pointEsc}'::point) + 100000 end ASC
         LIMIT 35"
    );

    $closestLocationsNames = [];

    foreach ($closestLocations as &$location) {
        //$key=$location['name'].' '.$location['distance'];
        $key = $location['name'];
        if (checkInterior($location['is_interior'])) { // If any reference is interior, we append "(Interior)" to the name for clarity and duplicate the entry.
            $key .= ' (Interior)';
            if ($location['tags'])
                $label = " \"$key\" ({$location['tags']})";
            else
                $label = " \"$key\"";
            $closestLocationsNames[$key] = $label;
        }
        if ($location['tags'])
            $label = " \"$key\" ({$location['tags']})";
        else
            $label = " \"$key\"";
        $closestLocationsNames[$key] = $label;
    }

    return is_array($closestLocationsNames) ? $closestLocationsNames : [];
}
