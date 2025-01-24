<?php
$method = $_SERVER["REQUEST_METHOD"];

if ($method === "POST") {
    // Read JSON data from the request
    $jsonDataInput = json_decode(file_get_contents("php://input"), true);
    $profile = $jsonDataInput["profile"];
    error_reporting(0);
    ini_set("display_errors", 0);
    $enginePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . "../../" . DIRECTORY_SEPARATOR;
    require_once $enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php";
    $FEATURES["MEMORY_EMBEDDING"]["ENABLED"] = false;

    if (isset($profile)) {
        $OVERRIDES["BOOK_EVENT_ALWAYS_NARRATOR"] = $GLOBALS["BOOK_EVENT_ALWAYS_NARRATOR"];

        if (file_exists($profile)) {
            require_once $profile;
        } else {
            error_log(__FILE__ . ". Using default profile because GET PROFILE NOT EXISTS");
        }
        $GLOBALS["CURRENT_CONNECTOR"] = DMgetCurrentModel();
        $GLOBALS["BOOK_EVENT_ALWAYS_NARRATOR"] = $OVERRIDES["BOOK_EVENT_ALWAYS_NARRATOR"];
    } else {
        error_log(__FILE__ . ". Using default profile because NO GET PROFILE SPECIFIED");
        $GLOBALS["USING_DEFAULT_PROFILE"] = true;
    }
    $db = new sql();

    if (!$db) {
        die("DB error");
    }

    $FUNCTIONS_ARE_ENABLED = false;

    if (!isset($GLOBALS["CONNECTORS_DIARY"]) || !file_exists($enginePath . "connector" . DIRECTORY_SEPARATOR . "{$GLOBALS["CONNECTORS_DIARY"]}.php")) {
        die("{$GLOBALS["HERIKA_NAME"]}|AASPGQuestDialogue2Topic1B1Topic|I'm mindless. Choose a LLM model and connector." . PHP_EOL);
    } else {
        require $enginePath . "connector" . DIRECTORY_SEPARATOR . "{$GLOBALS["CONNECTORS_DIARY"]}.php";

        $historyData = "";
        $lastPlace = "";
        $lastListener = "";
        foreach (json_decode(DataSpeechJournal($jsonDataInput["HERIKA_NAME"], 100), true) as $element) {
            if ($lastListener != $element["listener"]) {
                $listener = " (talking to {$element["listener"]})";
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

            $historyData .= trim("{$element["speaker"]}:" . trim($element["speech"]) . " $listener $place") . PHP_EOL;
        }
        if ($_GET["short"] == "yes") {
            $SHORT = "25 keywords";
            $SHORTER = "5 keywords";
            $REMINDER = "SHORT";
            $SUMMARIZE = ",AND SUMMARIZE INTO 250 TOKENS,";
        } else {
            $SHORT = "75 words";
            $SHORTER = "15 keywords";
            $REMINDER = "";
            $SUMMARIZE = " and summarize";
        }

        $partyConf = DataGetCurrentPartyConf();
        $partyConfA = json_decode($partyConf, true);
        error_log($partyConf);

		// Use the global DYNAMIC_PROMPT
        $updateProfilePrompt = $GLOBALS["DYNAMIC_PROMPT"];

		$head[]   = ["role"	=> "system", "content"	=> "You are an assistant. Analyze this dialogue and then update the dynamic character profile based on the information provided. ", ];
		$prompt[] = ["role"	=> "user", "content"	=> "* Dialogue history:\n" .$historyData ];
		$prompt[] = ["role" => "user", "content" => "Current character profile you are updating:\n" . "Character name:\n"  . $jsonDataInput["HERIKA_NAME"] . "Character static biography:\n" . $jsonDataInput["HERIKA_PERS"] . "\n" ."Character dynamic biography (this is what you are updating):\n" . $jsonDataInput["HERIKA_DYNAMIC"]];
		$prompt[] = ["role"=> "user", "content"	=> $updateProfilePrompt, ];
		$contextData       = array_merge($head, $prompt);
		$connectionHandler = new connector();
        $GLOBALS["FORCE_MAX_TOKENS"]=1500;
		$connectionHandler->open($contextData, ["max_tokens"=>1500]);
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

            $buffer .= $connectionHandler->process();
            $totalBuffer .= $buffer;
        }
        $connectionHandler->close();

        $actions = $connectionHandler->processActions();

        $responseParsed["HERIKA_DYNAMIC"] = $buffer;

        // Custom function to process LLM output
        if (array_key_exists("CustomUpdateProfileFunction", $GLOBALS) && is_callable($GLOBALS["CustomUpdateProfileFunction"])) {
            $responseParsed["HERIKA_DYNAMIC"] = $GLOBALS["CustomUpdateProfileFunction"]($buffer);
        }

        echo json_encode($responseParsed);
    }
}
?>
