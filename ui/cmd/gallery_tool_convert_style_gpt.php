<?php
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST') {

        // Read JSON data from the request
        $jsonDataInput = json_decode(file_get_contents("php://input"), true);

        $startTime = microtime(true);

        error_reporting(0);
        ini_set('display_errors', 0);
        

        $enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR. "..".DIRECTORY_SEPARATOR;
        require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php";
        chimRuntimeBootstrap($enginePath, [
            'load_general_settings' => true,
            'load_player_name' => true,
            'load_narrator' => true,
        ]);

        require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php";
        require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php";
        require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php";
        require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php";

        $GLOBALS["ENGINE_PATH"] = $enginePath;

        $db = $GLOBALS["db"];

        require_once $enginePath . "lib/core/npc_master.class.php";
        require_once $enginePath . "lib/core/api_badge.class.php";
        require_once $enginePath . "lib/core/core_profiles.class.php";
        require_once $enginePath . "lib/core/llm_connector.class.php";

        $api_key = getenv("OPENAI_API_KEY");

        $apiBadge   = new ApiBadge();
        $apiKeyData = $apiBadge->getByLabel("openai");

        $api_key = $apiKeyData["api_key"];

        $url = "https://api.openai.com/v1/images/edits";

        $headers = [
            "Authorization: Bearer " . $api_key,
        ];

        $refid = null;
        $gender = null;
        $race = null;
        $hints = '';

        // Check if filename matches the pattern: 8 hex characters + extension (e.g., 0001C1A4.jpg)
        $filename = pathinfo($jsonDataInput["source"], PATHINFO_FILENAME);
        if (preg_match('/^[0-9A-F]{8}$/i', $filename)) {
            $refid = strtoupper($filename); // Normalize to uppercase if needed

            $npcData = $db->fetchOne("SELECT gender, race FROM core_npc_master WHERE refid = '$refid'");

            if ($npcData) {
                $gender = $npcData['gender'];
                $race = $npcData['race'];
                $hints = "Hint: The person in the picture is a $gender $race";
            }
        }
        $userHint=$jsonDataInput["userhint"];
    // Read file contents into memory
        $source = file_get_contents($jsonDataInput["source"]);
        $extra="";
    // Prepare files using CURLStringFile
        $fields = [
            "image[0]"       => new CURLStringFile($source, "source.png", "image/png"),
            "model"          => "gpt-image-1.5",
            "input_fidelity" => "high",
            "prompt"         => "Convert image-0 to a semi-realistic style, like a high-quality CGI render. Reimagine the whole picture, while preserving concepts like tattos, eye color, hair style, hair color, clothing, make-up and environment. $extra. $hints. $userHint",
        ];

        error_log(print_r($fields["prompt"],true));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            die(json_encode(["status"=>"error","message"=>print_r(curl_error($ch),true)]));
            
        }

    // Decode JSON response
        $result = json_decode($response, true);

        if (isset($result["error"])) {
            die(json_encode(["status"=>"error","message"=>print_r($result,true)]));
        }
    // Save image
        $b64       = $result["data"][0]["b64_json"];
        $img_bytes = base64_decode($b64);
        $filename  = '/var/www/html/HerikaServer/data/pictures/gallery/' . uniqid('image_gpt_', true) . '.png';
        file_put_contents($filename, $img_bytes);

        die(json_encode(["status"=>"success"]));
}

die(json_encode(["status"=>"error"]));
