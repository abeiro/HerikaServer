<?php declare(strict_types=1);

require_once 'DatabaseTestCase.php';

// setUp and tearDown for the test database are in DatabaseTestCase.php
final class CommTest extends DatabaseTestCase
{
    public function testComm_WhenInputTextIsNormal_ShouldAddTalkingToNPC(): void
    {	
		// default test config
		require("conf.php");

		// build the query parameter
		$dataParts = ["inputtext", "100", "200", "Hey Narrator, attack that monster!"];
		$data = implode("|", $dataParts);
		$encodedData = base64_encode($data);
		$_SERVER["QUERY_STRING"] = "data={$encodedData}";

		// comm.php?data=inputtext|100|200|Hey Narrator, attack that monster! (base64 encoded)
		require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");

		$this->assertSame("inputtext", $GLOBALS["gameRequest"][0]);
		$this->assertSame("100", $GLOBALS["gameRequest"][1]);
		$this->assertSame("200", $GLOBALS["gameRequest"][2]);
		$this->assertSame("Hey Narrator, attack that monster! (Talking to The Narrator)", $GLOBALS["gameRequest"][3]);
    }
}