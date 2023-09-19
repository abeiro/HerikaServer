<?php

error_reporting(E_ALL);
require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."conf.php");
require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."sql.class.php");

echo file_get_contents('template.html');

$db = new sql();

if ($_POST["doit"]) {
  
  
  $head[] = array('role' => 'system', 'content' => stripslashes($_POST["prompt"]));
  $prompt[] = array('role' => 'user', 'content' => "Generate a background story for $HERIKA_NAME, in first person, and how she ended in Whiterun, as she would write it into her diary");
  

  $parms=array_merge($head,$prompt);
  
  
  
  $url = 'https://api.openai.com/v1/chat/completions';

  $data = array(
		'model' => 'gpt-3.5-turbo-0613',
		'messages' =>$parms,
		'stream' => false,
		//'max_tokens' => ((isset($GLOBALS["OPENAI_MAX_TOKENS_MEMORY"]) ? $GLOBALS["OPENAI_MAX_TOKENS_MEMORY"] : 1024) + 0),
        'max_tokens' => $_POST["OPENAI_MAX_TOKENS_MEMORY"]+0,
		'temperature' => 1,
		'presence_penalty' => 1
	);

  $headers = array(
      'Content-Type: application/json',
      "Authorization: Bearer {$GLOBALS["OPENAI_API_KEY"]}"
  );

  $options = array(
      'http' => array(
          'method' => 'POST',
          'header' => implode("\r\n", $headers),
          'content' => json_encode($data),
          'timeout' => ($GLOBALS["HTTP_TIMEOUT"]) ?: 30
      )
  );


  $context = stream_context_create($options);  
  $handle = fopen($url, 'r', false, $context);
  if ($handle === false) {
    
  } else {
    $buffer="";
    while (!feof($handle)) 
      $buffer.=fread($handle,1024);
		
    $response=json_decode($buffer,true);
    
    $rawResponse = $response["choices"][0]["message"]["content"];
    //echo $rawResponse;
  }

} else if ($_POST["store"]) {
  
  $db->delete("diarylog", "gamets=0 or gamets is null");

  $db->insert(
			'diarylog',
			array(
				'ts' => $finalParsedData[1],
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
<input type='range' min='100' max='1024' value='".(($_POST["OPENAI_MAX_TOKENS_MEMORY"])?:200)."'  name='OPENAI_MAX_TOKENS_MEMORY' oninput='this.nextElementSibling.value = this.value'>
<output>".(($_POST["OPENAI_MAX_TOKENS_MEMORY"])?:200)."</output>
<br/>
<input type='submit' name='doit' value='Generate background story' />
<input type='submit' name='store' value='Store background story' onclick='return confirm(\"Are you sure?\")'/>
</form>
</body>
</html>
";
?>
