<?php

function minimeCommand($text) {
	if (isset($GLOBALS["mockMinimeCommand"])) {
		return call_user_func($GLOBALS["mockMinimeCommand"], $text);
	}

	$url = "http://127.0.0.1:8082/command?text=" . urlencode($text);
	return file_get_contents($url);
}

function minimeExtract($text) {
	if (isset($GLOBALS["mockMinimeExtract"])) {
		return call_user_func($GLOBALS["mockMinimeExtract"], $text);
	}

	$url = "http://127.0.0.1:8082/extract?text=" . urlencode($text);
	return file_get_contents($url);
}

function minimePostTopic($text) {
	if (isset($GLOBALS["mockMinimePostTopic"])) {
		return call_user_func($GLOBALS["mockMinimePostTopic"], $text);
	}

	$url = "http://127.0.0.1:8082/posttopic?text=" . urlencode($text);
	return file_get_contents($url);
}

function minimeTask($text) {
	if (isset($GLOBALS["mockMinimeTask"])) {
		return call_user_func($GLOBALS["mockMinimeTask"], $text);
	}

	$url = "http://127.0.0.1:8082/task?text=" . urlencode($text);
	return file_get_contents($url);
}

function minimeTopic($text) {
	if (isset($GLOBALS["mockMinimeTopic"])) {
		return call_user_func($GLOBALS["mockMinimeTopic"], $text);
	}

	$url = "http://127.0.0.1:8082/topic?text=" . urlencode($text);
	return file_get_contents($url);
}

?>
