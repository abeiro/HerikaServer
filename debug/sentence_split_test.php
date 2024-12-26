<!DOCTYPE html>
<html>
<head>
    <title>Sentence Splitter Test</title>
    <meta charset="UTF-8">
    <style>
		.code-block {
			background-color: #f0f0f0;
			border: 1px solid #ccc;
			padding: 10px;
			margin-bottom: 15px;
		}
		.code-block .content {
			overflow: hidden; /* Hide content initially */
			transition: max-height 0.3s ease-out; /* Smooth transition for expanding/collapsing */
			max-height: 0; /* Initially collapsed */
		}
		.code-block.expanded .content {
			max-height: 500px; /* Adjust as needed */
		}
		.code-block .toggle {
			cursor: pointer;
			font-weight: bold;
			margin-bottom: 5px;
		}
		.pass { color: green; }
		.fail { color: red; }
    </style>

	<script>
		function toggleCodeBlock(element) {
			element.classList.toggle("expanded");
		}
	</script>
</head>
<body>

<h1>Sentence Splitter Test</h1>

<?php

$GLOBALS["HERIKA_NAME"]="Ysolda";

require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."chat_helper_functions.php");

// reduce the default values to make testing easier
define("MAXIMUM_SENTENCE_SIZE", 60);
define("MINIMUM_SENTENCE_SIZE", 25);

// approximately copies the logic in main.php
function mainFunction($inputText) {
    $buffer="";
	$returnSentences=[];

	// looping over each character of the input string to simulate a buffer from the LLM
    foreach (mb_str_split($inputText) as $char) {
        $buffer.=$char;

        $buffer=strtr($buffer, array("\""=>"",".)"=>")."));

        if (strlen($buffer)<MINIMUM_SENTENCE_SIZE) {
            continue;
        }

        $position = findDotPosition($buffer);

        if ($position !== false && $position>MINIMUM_SENTENCE_SIZE ) {
            $extractedData = substr($buffer, 0, $position + 1);
            $remainingData = substr($buffer, $position + 1);
            $returnSentences=array_merge($returnSentences, split_sentences_stream(cleanResponse($extractedData)));
            $buffer=$remainingData;
        }
    }
    
    
    if (trim($buffer)) {
        $returnSentences=array_merge($returnSentences, split_sentences_stream(cleanResponse(trim($buffer))));
    }

	return $returnSentences;
}

// Array of strings to be tested along with their expected outputs
$testStrings = [
	// regular paragraph
    "Upon the realm of Tamriel, an empire stands. With Iron will and disciplined hands. United under Mede, they strive to keep."
		=> ["Upon the realm of Tamriel, an empire stands.", "With Iron will and disciplined hands.", "United under Mede, they strive to keep."],
	// zero punctuation
	"Upon the realm of Tamriel an empire stands With Iron will and disciplined hands United under Mede they strive to keep"
		=> ["Upon the realm of Tamriel an empire stands With Iron will and disciplined hands United under Mede they strive to keep"],
	// incomplete ending
    "Upon the realm of Tamriel, an empire stands. With Iron will and disciplined hands. United under Mede, they strive to keep. And it will"
		=> ["Upon the realm of Tamriel, an empire stands.", "With Iron will and disciplined hands.", "United under Mede, they strive to keep.", "And it will"],
	// incomplete ending with no periods
	"Upon the realm of Tamriel, an empire stands! With Iron will and disciplined hands! United under Mede, they strive to keep! And it will"
		=> ["Upon the realm of Tamriel, an empire stands!", "With Iron will and disciplined hands!", "United under Mede, they strive to keep!", "And it will"],
	// incomplete ending (short)
	"Okay. And"
		=> ["Okay. And"],
	// double punctuation
	"Upon the realm of Tamriel, an empire stands?! With Iron will and disciplined hands!! United under Mede, they strive to keep!!"
		=> ["Upon the realm of Tamriel, an empire stands?", "With Iron will and disciplined hands!", "United under Mede, they strive to keep!"],
	// abbreviations - currently FAILS
	"I've been to Daggerfall, Morrowind, Cyrodiil, and Skyrim. But I've never been to the U.S. Is it as exciting as Nirn?"
		=> ["I've been to Daggerfall, Morrowind, Cyrodiil, and Skyrim.", "But I've never been to the U.S.", "Is it as exciting as Nirn?"],
	// ellipsis 
	"Upon the realm of Tamriel, an empire stands... With Iron will and disciplined hands... United under Mede, they strive to keep..."
		=> ["Upon the realm of Tamriel, an empire stands...", "With Iron will and disciplined hands...", "United under Mede, they strive to keep..."],

	// regular paragraph
    "ああ、カスティル。飲み物ならここにあるよ。何がいい？エールか、ミードか？それとも他に何か欲しいものがあるかい？食事もいかがですか？"
		=> ["ああ、カスティル。", "飲み物ならここにあるよ。", "何がいい？ エールか、ミードか？", "それとも他に何か欲しいものがあるかい？", "食事もいかがですか？"],
	// incomplete ending
    "ああ、カスティル。飲み物ならここにあるよ。何がいい？エールか、ミードか？それとも他に何か欲しいものがあるかい？食事もいかがですか？何でも"
		=> ["ああ、カスティル。", "飲み物ならここにあるよ。", "何がいい？ エールか、ミードか？", "それとも他に何か欲しいものがあるかい？", "食事もいかがですか？", "何でも"],
	// incomplete ending (short)
	"あ。それで"
		=> ["あ。それで"],
];

foreach ($testStrings as $input => $expected) {
    $pass = true;
    $sentences = mainFunction($input);

    if ($sentences !== $expected) {
        $pass = false;
    }

    $blockClass = "code-block";
    if (!$pass) {
        $blockClass .= " expanded"; // Expand if test fails
    }

    echo "<div class='" . $blockClass . "'>";
    echo "<div class='toggle' onclick='toggleCodeBlock(this.parentNode)'><span class='" . ($pass ? "pass" : "fail") . "'>#INPUT: </span>" . htmlspecialchars($input) . "</div>";
    echo "<div class='content'>"; // Content to be toggled

    echo "<p>#OUTPUTS:</p>";
    foreach ($sentences as $sentence) {
        echo "<p>" . htmlspecialchars($sentence) . "</p>";
    }

    if (!$pass) {
        // Show expected output on failure
        echo "<p>#EXPECTED:</p>";
		foreach ($expected as $sentence) {
			echo "<p>" . htmlspecialchars($sentence) . "</p>";
		}
    }

    echo "</div>"; // Close content div
    echo "</div>"; // Close code-block div
}
?>

</body>
</html>