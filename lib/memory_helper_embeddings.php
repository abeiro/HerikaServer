<?php



function getEmbedding($text) {
	
	if ($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["TEXT2VEC_PROVIDER"]=="local") {
		
		return getEmbeddingLocal($text);
		
		
	} else if ($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["TEXT2VEC_PROVIDER"]=="remote") {
		
		return getEmbeddingRemote($text);

		
	}
}


function getEmbeddingLocal($text)
{

	$url = $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["TEXT2VEC_URL"]."/pipeline/feature-extraction/sentence-transformers/all-MiniLM-L6-v2";

	$data = [
		"inputs" => ["$text"]

	];

	$headers = array(
		'Content-Type: application/json'
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

	$buffer = "";
	$c = 0;
	while (!feof($handle)) {
		$line = fgetc($handle);
		$buffer .= $line;
		if ($line == "]")
			$c++;

		if ($c > 1)
			break;


	}

	$responseParsed = json_decode($buffer, true);

	$embedData = $responseParsed[0];


	return $embedData;

}

function getEmbeddingRemote($text)
{

	global $db;


	//if (!$db) {
	//	$db = new sql();
	//}
	//// OPENAI CODE
	$data = [
		"model" => "text-embedding-ada-002",
		"input" => $text
	];



	$headers = array(
		'Content-Type: application/json',
		"Authorization: Bearer {$GLOBALS["CONNECTOR"]["openai"]["API_KEY"]}"
	);

	$options = array(
		'http' => array(
			'method' => 'POST',
			'header' => implode("\r\n", $headers),
			'content' => json_encode($data),
			'timeout' => ($GLOBALS["HTTP_TIMEOUT"]) ?: 30
		)
	);


	$url = 'https://api.openai.com/v1/embeddings';

	$context = stream_context_create($options);
	$response = file_get_contents($url, false, $context);
	$responseParsed = json_decode($response, true);

	if (isset($GLOBALS["GPTMODEL"]) && isset($GLOBALS["COST_MONITOR_ENABLED"]) && $GLOBALS["COST_MONITOR_ENABLED"]) {

		$costPerThousandInputTokens = 0.0001;
		$numInputTokens = $responseParsed["usage"]["total_tokens"];

		$cost = ($numInputTokens * 0.0001 * 0.001);
		$db->insert_and_calc_totals(
			'openai_token_count',
			array(
				'input_tokens' => $numInputTokens,
				'output_tokens' => 0,
				'cost_USD' => $cost,
				'localts' => time(),
				'datetime' => date("Y-m-d H:i:s"),
				'model' => 'text-embedding-ada-002'
			)
		);

	}
	//print_r($responseParsed);
	$embedData = $responseParsed["data"][0]["embedding"];

	//echo "Size of embedding array".sizeof($embedData);

	return $embedData;

}
?>
