<?php

/*
Alleviate meloTTS English mispronunciation of contractions and compound words.
This could be removed if meloTTS is retrained.
If this file is missing from tts folder, meloTTS will revert to original behavior.
*/

$GLOBALS['MELOTTS_PRONUNCIATION_ADJUST'] = true; // set false to hear original pronunciation 	
$GLOBALS['MELOTTS_PRONUNCIATION_DEBUG'] = false; // for tests in tts-test.php, true to display changed text 	

$s_pronunciation_test=" I'd like better pronunciation. ";
$s_pronunciation_test.=" Let's hear contractions. 
 It's nice in Solitude, but isn't warm, I'll not wonder who's responsible. 
 Dragons wouldn't soar if Alduin couldn't rise - it's your fault, that's right! 
 You shouldn't do what you can't and I really mean don't! Tales can't tell what doesn't bind; why they aren't? 
 I couldn't and I didn't do anything and it doesn't matter. I hadn't, I hasn't, we haven't see any dragon up in the sky. 
 Sword it's new and I'm cold after I've slept in snow. This isn't good, they're frozen, they've risen, we're doomed. 
 We've plumes, we weren't good enough, so I'm querying who're they? He shan't care about bullseye! 
 You wouldn't dare because you're small and you've issues so I'm asking: who've headache? ";
//$s_pronunciation_test.=" ";  
$s_pronunciation_test.=" C'mon this is mindblowing. "; // test end

function adjust_pronunciation($s_text="", $language="EN") {
	// Specific corrections should precede generic fixes
	$s_clean = $s_text;
	if (strlen($s_text) > 0) {
		
		$lang = strtoupper(substr($language, 0, 2));
		if (strlen($lang) < 2) $lang = "EN";

		if ($lang == "EN") {

			$notgood = array( "don't", "can't",  "couldn't",  "didn't",  "doesn't",  "hadn't",  "hasn't",  "haven't",  "I've",   "aren't", 
			"I'd ", "I'll",   "it's", "I'm",  "isn't",  "they're",  "they've",   "we're",  "we've",   "weren't",  "who're", "wouldn't",   "you're",  "you've",   "who've",   
			"mightn't",  "mustn't",  "shan't",    "shouldn't",   "c'mon",  
			"plug",  "mindblowing", "earsplitting", "wellknown", "selfinterest",  "openhanded", "ablebodied", "airbag", "nightshift", "soysauce", "askout", 
			"spareribs", "sportscast", "onesided", "backspace", "fullmoon", "fulllength", "onrush", "getacross", "bantamweight", "barnyard", "giveaway", "battlecry", "goahead", 
			"stepbrother", "stepdaughter", "stepmother", "stepparent", "gearshift", "glowworm", "glowmushroom",
			"battleax", "goaround", "stateswoman", "battlecruiser", "goback",  
			"stepladder", "grammarschool", "grasshopper", "blockbuster", "overachiev", "groundcover", "strongbox", "blowup", 
			"groundskeeper", "overage", "sunbonnet", "sunburn", "overarching", "grownup", "gunmetal",  
			"earache", "stomachache", "preheadache", "bellyache", "heartache", "toothache", "backache", "headache",   
			"overcompensate", "hairbrush", "sunstroke", "superconductor", "brainstorm", "supercollider", "breadbasket", 
			"breadbox", "overeager", "superheavyweight", "superhuman", "superimpose", 
			"breakinto", "breakdancing", "overgrown", "breakdown", "handgun", "superwoman", "breakthrough", "breaststroke", 
			"browneye", "bucktoothed", "handwriting", "butterfinger", "hardworking", "tattletale", "buttonhole", 
			"sunup", "bowleg", "haircut", "overcrowd", "supercharge", "boxcutter", "hairdo", "overdo", "superglue", "overeat", "superhero", 
			"superhighway", "supernova", "breakfront", "superuser", "handover", "breastbone", "switchoff", "switchon", "overlook", 
			"overlying", "tableland", "tapeworm", "overrun", "overseas", "hayfork", "teacup",  "swallowtail", "hayloft", 
			"calloff", "hayride", "oversight", "teammate", "callback", "haystack", "oversleep", "teapot", 
			"overstretch", "tearjerker", "candytuft", "threedimensional", "chairperson", "throughout", "heartsick", "passon", "helpdesk", 
			"thunderstruck", "checkup", "hereafter", "passerby", "tideland", "cheerup", "heretofore", "passionflower", "tightrope", 
			"passionfruit", "timesaver", "chickpea", "childfree", "timescale", "childproof", "timesheet", "highflier", "peacekeeper", "tipoff", 
			"timesheet", "highchair", "payback", "timetable", "timeworn", "highhanded",
			"highjack", "cleancut", "closedminded", "closeminded", "hightail", "pickax", "clotheshorse", "highwayman", "piecrust", "toothbrush", "clothespin", 
			"topflight", "coldheart", "corndog", "horsefly", "pocketknife", "tumbleweed", "comeabout", "hogtie", 
			"coursework", "hothead", "underclothing", "undercook", "housefly", "crossroad", "housewarming", 
			"purebred", "hovercraft", "cupboard", "hunchback", "racecar", "upcoming", "cutup", "icecream", "racecourse", 
			"dancehall", "icecube", "racehorse", "daredevil", "indepth", "dinnerplate", "radiowave", "inchworm",  "dishcloth", 
			"jackknife", "diskdrive", "reportcard", "jawbone", "ribcage", "upturn", "dogwood", "jellybean", "ripsaw", "upward", 
			"doorknob", "upwind",  "jitterbug", "roadbed", "jumpingjack", "voltmeter",  
			"kingsize", "runinto", "downshift", "ladybird", "ladybug", "watercolor", "saltshaker", "dragonfly", 
			"laughingstock", "sawhorse", "wavelength", "levelheaded", "scatterbrain", "weatherman", "welleducated", 
			"dumbbell", "wellknown", "welltodo", "duststorm", "deepfreez", "deepthr", "deepness", "deepwat", 
			"earlobe", "lineman", "earthward", "login", "seaway", "wherewithal", "earthworm", "logoff", "seaweed", "earwig", "logon", 
			"seaworthy", "whirlpool", "eatout", "logout", "secondhand", "longlasting", "selfconcept", "selfservice", "shipshape", "widespread", 
			"shorttempered", "showoff", "eyesight", "litterbug", "lovesick", "shoehorn",  "makeover", 
			"shuteye", "farmyard", "sideswipe", "fastmoving",  "fatherinlaw", "woodcutter", "fillout", "singleminded", "findout", 
			"fingernail", "merrygoround", "middleschool", "wordofmouth", "sleepin", "worktable", "firsthand", "worldfamous", 
			"slipknot", "fisheye", "smallminded", "yachtsman", "fishhook", "yellowtail", "zigzag", "zookeeper",
			"armseye", "bigeye", "birdeye", "buckeye", "bugeye", "bullseye", "complicating", 
			"cockeye", "deadeye", "frogeye", "goldeneye", "goldeye", "grasseye", "hawkeye", "kilneye", "mooneye", "opaleye", "overeye", "oxeye", 
			"pinkeye", "rabbiteye", "silvereye", "sockeye", "speareye", "tigereye", "walleye", "watcheye", 
			// add here new mispronunciations:
			//"", "", "", 
			"hmpf", "hmm", "mmm", "brrr", "grrr", "shh", "zzz", 
			".",   ";",   ":",   "?",   "!",   "'t", "'s", "'d", "'m ","'ll", "'ve", "'re", "'nt"); 

			$ok      = array("donth",   "kanth",   "could not", "did not", "dosnth", "had not", "has not", "have not", "I have", "aanth", // variants: do not, can not
			"eyed ", "eyell", "its",  "eyem", "is not", "they are", "they have", "we are", "we have", "were not", "who are", "would not", "you are", "you have", "who have", 
			"might not", "must not", "shall not", " should not", "kamon",  
			"-plug", "mind-blowing", "ear-splitting", "well-known", "self-interest", "open-handed", "able-bodied", "air-bag", "night-shift", "soy-sauce", "ask-out", 
			"spare-ribs", "sports-cast", "one-sided", "back-space", "full-moon", "full-length", "on-rush", "get-across", "bantam-weight", "barn-yard", "give-away", "battle-cry", "go-ahead", 
			"step-brother", "step-daughter", "step-mother", "step-parent", "gear-shift", "glow-worm", "glow mushroom",
			"battle-ax", "go-around", "states-woman", "battle-cruiser", "go-back",  
			"step-ladder", "grammar school", "grass-hopper", "block-buster", "over-achiev", "ground-cover", "strong-box", "blow-up", 
			"grounds-keeper", "over-age", "sun-bonnet", "sun-burn", "over-arching", "grown-up", "gun-metal",  
			"ear-ache", "stomach-ache", "pre-head-ache", "belly-ache", "heart-ache", "tooth-ache", "back-ache", "head-ache",   
			"over-compensate", "hair-brush", "sun-stroke", "super-conductor", "brain-storm", "super-collider", "bread-basket", 
			"bread-box", "over-eager", "super-heavy-weight", "super-human", "super-impose", 
			"break-into", "break-dancing", "over-grown", "break-down", "hand-gun", "super-woman", "break-through", "breast-stroke", 
			"brown-eye", "buck-toothed", "hand-writing", "butter-finger", "hard-working", "tattle-tale", "button-hole", 
			"sun-up", "bow-leg", "hair-cut", "over-crowd", "super-charge", "box-cutter", "hair-do", "over-do", "super-glue", "over-eat", "super-hero", 
			"super-highway", "super-nova", "break-front", "super-iuser", "hand-over", "breast-bone", "switch-off", "switch-on", "over-look", 
			"over-lying", "table-land", "tape-worm", "over-run", "over-seas", "hay-fork", "tea-cup", "swallow-tail", "hay-loft",     
			"call-off", "hay-ride", "over-sight", "team-mate", "call-back", "hay-stack", "over-sleep", "tea-pot", 
			"over-stretch", "tear-jerk", "candy-tuft", "three-dimensional", "chair-person", "through-out", "heart-sick", "pass-on", "help-desk", 
			"thunder-struck", "check-up", "here-after", "passer-by", "tide-land", "cheer-up", "here-to-fore", "passion-flower", "tight-rope", 
			"passion-fruit", "time-saver", "chick-pea", "child-free", "time-scale", "child-proof", "time-sheet", "high-flier", "peace-keeper", "tip-off", 
			"time-sheet", "high-chair", "pay-back", "time-table", "time-worn", "high-handed",
			"high-jack", "clean-cut", "closed-minded", "close-minded", "high-tail", "pick-ax", "clothes-horse", "highway-man", "pie-crust", "tooth-brush", "clothes-pin", 
			"top-flight", "cold-heart", "corn-dog", "horse-fly", "pocket-knife", "tumble-weed", "come-about", "hog-tie", 
			"course-work", "hot-head", "under-clothing", "under-cook", "house-fly", "cross-road", "house-warming", 
			"pure-bred", "hover-craft", "cup-board", "hunch-back", "race-car", "up-coming", "cut-up", "ice-cream", "race-course", 
			"dance-hall", "ice-cube", "race-horse", "dare-devil", "in-depth", "dinner-plate", "radio-wave", "inch-worm",  "dish-cloth", 
			"jack-knife", "disk-drive", "report card", "jaw-bone", "rib-cage", "up-turn", "dog-wood", "jelly-bean", "rip-saw", "up-ward", 
			"door-knob", "up-wind",  "jitter-bug", "road-bed", "jumping-jack", "volt-meter",  
			"king-size", "run-into", "down-shift", "lady-bird", "lady-bug", "water-color", "salt-shaker", "dragon-fly", 
			"laughing-stock", "saw-horse", "wave-length", "level-headed", "scatter-brain", "weather-man", "well educated", 
			"dumb-bell", "well-known", "well-todo", "dust-storm", "deep-freez", "deep-thr", "deep-ness", "deep-wat", 
			"ear-lobe", "line-man", "earth-ward", "log-in", "sea-way", "where-withal", "earth-worm", "log-off", "sea-weed", "ear-wig", "log-on", 
			"sea-worthy", "whirl-pool", "eat-out", "log-out", "second-hand", "long-lasting", "self-concept", "self-service", "ship-shape", "wide-spread", 
			"short-tempered", "show-off",  "eye-sight", "litter-bug", "love-sick", "shoe-horn",  "make-over", 
			"shut-eye", "farm-yard", "side-swipe", "fast-moving",  "father-in-law", "wood-cutter", "fill-out", "single-minded", "find-out", 
			"finger-nail", "merry-go-round", "middle school", "word-of-mouth", "sleep-in", "work-table", "first-hand", "world-famous", 
			"slip-knot", "fish-eye", "small-minded", "yachts-man", "fish-hook", "yellow-tail", "zig-zag", "zoo-keeper",
			"arms-eye", "big-eye", "bird-eye", "buck-eye", "bug-eye", "bulls-eye", "compli-cating", 
			"cock-eye", "dead-eye", "frog-eye", "golden-eye", "gold-eye", "grass-eye", "hawk-eye", "kiln-eye", "moon-eye", "opal-eye", "over-eye", "ox-eye", 
			"pink-eye", "rabbit-eye", "silver-eye", "sock-eye", "spear-eye", "tiger-eye", "wall-eye", "watch-eye", 
			// add here corresponding fixes
			//"", "", "", 
			"humf", "humm", "muhum", "buhr", "guhr", "shish", "zuzz", 
			" . ", ".; ", ",: ", " ? ", " ! ", "t",  "s",  "d",  "m ", "ll",  "ve",  "re",  " not"); 

			$encoding = mb_detect_encoding($s_text); // some LLMs could send UTF-8 apostrophe
			if (($encoding === 'ASCII') || ($encoding === 'CP1252') || ($encoding === 'WINDOWS-1252') || ($encoding === 'ISO-8859-1')) { 
				$s_ascii = $s_text;
			} else {
				$s_ascii = str_replace("’", "'" , $s_text); // ’ this is an utf-8 apostrophe
				//$s_ascii = mb_convert_encoding($s_ascii, 'ISO-8859-1', $encoding); 
			}
			$s_clean = str_ireplace($notgood, $ok, $s_ascii); 
			//error_log("melotts adjusted " . $encoding . ": " . $s_clean);
		}  // --- endif EN

	} else
		error_log("melotts adjust contractions empty text! ");

	return $s_clean; 
}

function pronunciation_adjust_enabled() {
	return isset($GLOBALS['MELOTTS_PRONUNCIATION_ADJUST']) ? $GLOBALS['MELOTTS_PRONUNCIATION_ADJUST'] : true; //disable only when explicit false
}

function pronunciation_debug_enabled() {
	return isset($GLOBALS['MELOTTS_PRONUNCIATION_DEBUG']) ? $GLOBALS['MELOTTS_PRONUNCIATION_DEBUG'] : false;
}

?>



