<?php 

error_reporting(E_ALL);
session_start();



$enginePath =__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

$configFilepath=realpath($enginePath."conf".DIRECTORY_SEPARATOR);

require_once($enginePath."conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath."lib".DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");

$GLOBALS["PROFILES"]["default"]="$configFilepath/conf.php";
foreach (glob($configFilepath . '/conf_????????????????????????????????.php') as $mconf ) {
    if (file_exists($mconf)) {
        $filename=basename($mconf);
        $pattern = '/conf_([a-f0-9]+)\.php/';
        preg_match($pattern, $filename, $matches);
        $hash = $matches[1];
        $GLOBALS["PROFILES"][$hash]=$mconf;
    }
}

if (isset($_SESSION["PROFILE"]) && in_array($_SESSION["PROFILE"],$GLOBALS["PROFILES"])) {
    require_once($_SESSION["PROFILE"]);

  } else {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>CHIM Diary</title>
        <link rel="icon" type="image/x-icon" href="images/favicon.ico">
        <style>
            /* Updated CSS for Dark Grey Background Theme */
            body {
                font-family: Arial, sans-serif;
                background-color: #2c2c2c; 
                color: #f8f9fa;
            }

            h1, h2 {
                color: #ffffff; /* White color for headings */
            }

            form {
                margin-bottom: 20px;
                background-color: #3a3a3a; /* Slightly lighter grey for form backgrounds */
                padding: 15px;
                border-radius: 5px;
                border: 1px solid #4a4a4a; /* Darker border for contrast */
                max-width: 600px;
            }

            label {
                font-weight: bold;
                color: #f8f9fa; /* Ensure labels are readable */
            }

            input[type="text"], input[type="file"], textarea {
                width: 100%;
                padding: 6px;
                margin-top: 5px;
                margin-bottom: 15px;
                border: 1px solid #4a4a4a; /* Darker borders */
                border-radius: 3px;
                background-color: #4a4a4a; /* Dark input backgrounds */
                color: #f8f9fa; /* Light text inside inputs */
                resize: vertical; /* Allows users to resize vertically if needed */
                font-family: Arial, sans-serif; /* Ensures consistent font */
                font-size: 16px; /* Sets a readable font size */
            }

            input[type="submit"] {
                background-color: #007bff;
                border: none;
                color: white;
                border-radius: 5px; /* Slightly larger border radius */
                cursor: pointer;
                padding: 5px 15px; /* Increased padding for larger button */
                font-size: 18px;    /* Increased font size */
                font-weight: bold;  /* Bold text for better visibility */
                transition: background-color 0.3s ease; /* Smooth hover transition */
            }

            input[type="submit"]:hover {
                background-color: #0056b3; /* Darker shade on hover */
            }

            .message {
                background-color: #444444; /* Darker background for messages */
                padding: 10px;
                border-radius: 5px;
                border: 1px solid #4a4a4a;
                max-width: 600px;
                margin-bottom: 20px;
                color: #f8f9fa; /* Light text in messages */
            }

            .message p {
                margin: 0;
            }

            .response-container {
                margin-top: 20px;
            }

            .indent {
                padding-left: 10ch; /* 10 character spaces */
            }

            .indent5 {
                padding-left: 5ch; /* 5 character spaces */
            }

            .button {
                padding: 8px 16px;
                margin-top: 10px;
                cursor: pointer;
                background-color: #007bff;
                border: none;
                color: white;
                border-radius: 3px;
            }

            .button:hover {
                background-color: #0056b3;
            }

            .filter-buttons {
                margin: 1em 0;
            }

            .alphabet-button {
                display: inline-block;
                margin-right: 5px;
                padding: 6px 10px;
                color: #fff;
                background-color: #007bff;
                text-decoration: none;
                border-radius: 4px;
                font-weight: bold;
            }

            .alphabet-button:hover {
                background-color: #0056b3;
            }

            .table-container {
                max-height: 800px;
                overflow-y: auto;
                margin-bottom: 20px;
                max-width: 1600px;
            }

            .table-container table {
                width: 100%;
                border-collapse: collapse;
                background-color: #3a3a3a; /* Base background color */
            }

            .table-container th, .table-container td {
                border: 1px solid #555555; /* Border color */
                padding: 8px;
                text-align: left;
                word-wrap: break-word;
                overflow-wrap: break-word;
                color: #f8f9fa; /* Text color */
            }

            .table-container th {
                background-color: #4a4a4a; /* Header background color */
                font-weight: bold;
            }

            /* Alternating row colors */
            .table-container tr:nth-child(even) {
                background-color: #2c2c2c; /* Dark grey for even rows */
            }

            .table-container tr:nth-child(odd) {
                background-color: #3a3a3a; /* Slightly lighter grey for odd rows */
            }

            /* Specific column widths */
            .table-container th:nth-child(1),
            .table-container td:nth-child(1) {
                width: 150px; /* Small */
            }

            .table-container th:nth-child(2),
            .table-container td:nth-child(2) {
                width: 600px; /* Large */
            }

            .table-container th:nth-child(3),
            .table-container td:nth-child(3) {
                width: 80px; /* Small */
            }

            .table-container th:nth-child(4),
            .table-container td:nth-child(4),
            .table-container th:nth-child(5),
            .table-container td:nth-child(5) {
                width: 100px;
            }

            .table-container th:nth-child(6),
            .table-container td:nth-child(6) {
                width: 180px;
            }

            input[type="submit"].btn-danger {
                background-color: rgb(200, 53, 69);
                color: #fff;
                border: 1px solid rgb(255, 255, 255);
                padding: 10px 20px;
                cursor: pointer;
                font-size: 16px;
                border-radius: 4px;
                transition: background-color 0.3s ease; 
                font-weight: bold;
            }

            input[type="submit"].btn-danger:hover {
                background-color: rgb(200, 35, 51);
            }
        </style>
    </head>
    <body>
    <?php
    if (!isset($_SESSION["PROFILE"])) {
        echo "<h2>Select a character before opening this page</h2>";
    } else {
      echo "<h2>Select a character that is not The Narrator before opening this page.</h2>";
      echo "<h2>E.G. Select Hulda to read Hulda's diary, if she has written any entries.</h2>";
    }
    ?>
    </body>
    </html>
    <?php
    die();
}
  

//print_r($GLOBALS);
$db = new sql();

$data=[];
$n=3; // Starting at page 3
$pageElements="";

echo <<<HEAD
<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
</head>
<body>
HEAD;

/**
 * Splits text into multiple chunks of up to $maxLength characters,
 * ensuring words aren't cut in half.
 *
 * @param string $text       The full text to chunk
 * @param int    $maxLength  The maximum characters allowed in each chunk
 * @return array             An array of text chunks
 */
function chunkTextByWords($text, $maxLength = 1000) {
    // Split the text on whitespace to get individual words
    $words = preg_split('/\s+/', $text);

    $chunks = [];
    $currentChunk = '';

    foreach ($words as $word) {
        // +1 for the space if $currentChunk is not empty
        $space = ($currentChunk === '') ? '' : ' ';
        // Check if adding this word would exceed our limit
        if (strlen($currentChunk) + strlen($space) + strlen($word) <= $maxLength) {
            // If it fits, add the word to current chunk
            $currentChunk .= $space . $word;
        } else {
            // If it doesn't fit, push the current chunk into array and start a new one
            $chunks[] = $currentChunk;
            $currentChunk = $word; // begin new chunk with current word
        }
    }

    // Add the last chunk if there's anything left in $currentChunk
    if (!empty($currentChunk)) {
        $chunks[] = $currentChunk;
    }

    return $chunks;
}

$maxChars = 890;

$cn=$db->escape($GLOBALS["HERIKA_NAME"]);
$results = $db->query("SELECT topic, content, tags, people FROM diarylog WHERE people='$cn' ORDER BY gamets ASC");

while ($row = $db->fetchArray($results)) {
    $topic   = $row["topic"];
    $content = $row["content"];
    
    // Split by words, ensuring no word is cut off
    $chunks = chunkTextByWords($content, $maxChars);

    // Build HTML pages from each chunk
    foreach ($chunks as $index => $chunk) {
        // Append "(continued)" if not the first chunk
        $title = $topic;
        if ($index > 0) {
            $title .= " (continued)";
        }

        // Build page content
        $pageElements .= "
            <div class=\"page text-page\" onclick=\"movePage(this, $n)\">
                <h3>{$title}</h3>
                <p>" . nl2br($chunk) . "</p>
                <span class='readbutton'
                      onclick='speak(document.querySelector(\"body > div.book > div:nth-child($n)\").innerHTML);event.stopPropagation()'>
                      read
                </span>
            </div>
        ";
        $n++;
    }
}



  


$SUBSTITUTIONS=[
  "#BOOK_NAME#"=>"$HERIKA_NAME's Diary",
  "#HERIKA_NAME#"=>"$HERIKA_NAME",
  "##PAGES##"=>"$pageElements"
];

$htmlData=file_get_contents("template.html");

$htmlDataMangled=str_replace(array_keys($SUBSTITUTIONS), array_values($SUBSTITUTIONS), $htmlData);

echo $htmlDataMangled;





?>
