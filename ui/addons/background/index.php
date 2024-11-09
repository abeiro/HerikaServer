<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
</head>
<body>

<?php

error_reporting(E_ALL);
$enginePath =__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."memory_helper_vectordb.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."memory_helper_embeddings.php");

echo file_get_contents('template.html');

$db = new sql();

if ($_POST["doit"]) {

  
  $head[] = array('role' => 'system', 'content' => stripslashes($_POST["prompt"]));
  $prompt[] = array('role' => 'user', 'content' => stripslashes($_POST["intro"]));
  

  $parms=array_merge($head,$prompt);
  
	require_once $enginePath . "connector" . DIRECTORY_SEPARATOR . "{$GLOBALS["CONNECTORS_DIARY"]}.php";
  
    $connectionHandler=new connector();
    $GLOBALS["gameRequest"][0]="diary"; // HAck to force diary grammar
    $connectionHandler->open($parms,["MAX_TOKENS"=>$_POST["MAX_TOKENS"]]);

    $buffer="";
    $totalBuffer="";
    $breakFlag=false;
    
     while (true) {

        if ($breakFlag) {
            break;
        }

        $buffer.=$connectionHandler->process();
        $totalBuffer.=$buffer;

        if ($connectionHandler->isDone()) {
            $breakFlag=true;
        }
        
     }
     
     $rawResponse=$buffer;
     
     $connectionHandler->close();

} else if ($_POST["store"]) {
  
  $db->delete("diarylog", "gamets=0 or gamets is null");

  $db->insert(
			'diarylog',
			array(
				'ts' => time(),
				'gamets' => 0,
				'topic' => SQLite3::escapeString("$HERIKA_NAME's background story"),
				'content' => SQLite3::escapeString($_POST["bgstory"]),
				'tags' => SQLite3::escapeString("$HERIKA_NAME, birth, story, background, past, birthplace, origins, childhood"),
				'people' => SQLite3::escapeString($HERIKA_NAME),
				'location' => "",
				'sess' => 'pending',
				'localts' => time()
			)
		);
  $db->delete("diarylogv2", "true");
  $db->execQuery("insert into diarylogv2 select topic,content,tags,people,location from diarylog");
  
}


$SUBSTITUTIONS=[
  "#BOOK_NAME#"=>"$HERIKA_NAME's diary",
  "#HERIKA_NAME#"=>"$HERIKA_NAME",
  "##PAGES##"=>"$pageElements"
];

echo "
<form action='index.php' method='post'>
<p>Current system prompt</p>
<textarea name='prompt' style='width:80%;height:300px'>
".(($_POST["prompt"])?($_POST["prompt"]):($PROMPT_HEAD.$HERIKA_PERS))."
</textarea>
<p>Generated background story</p>
<textarea name='bgstory' style='width:80%;height:300px' placeholder=''>
$rawResponse
</textarea>
<p>Prompt</p>
<input type='text' value='".(($_POST["intro"])?:"Generate a background story for $HERIKA_NAME, telling birthplace, childhood, family , writen in first person, and how she ended in Whiterun, as she would write it into her diary in a summarized way")."' name='intro' size='200'/>
<br/>
<p>Token limit</p>
<input type='range' min='100' max='1024' value='".(($_POST["MAX_TOKENS"])?:500)."'  name='MAX_TOKENS' oninput='this.nextElementSibling.value = this.value'>
<output>".(($_POST["MAX_TOKENS"])?:500)."</output>
<br/>
<input type='submit' name='doit' value='Generate background story' />
<input type='submit' name='store' value='Store background story' onclick='return confirm(\"Are you sure?\")'/>
</form>
</body>
</html>
";
?>
