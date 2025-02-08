<?php declare(strict_types=1);

require_once 'DatabaseTestCase.php';
require_once 'CallableMock.php';

// setUp and tearDown for the test database are in DatabaseTestCase.php
final class DynamicProfileTest extends DatabaseTestCase
{
    public function testDynamicProfile_WhenNoExistingDynamicProfile_ShouldCreateNewDynamicProfile(): void
    {
        // default test config
        require("conf.php");
        $GLOBALS["HERIKA_NAME"]="Unit Test";
        $GLOBALS["HERIKA_PERS"]="You are a Unit Test.";
        $GLOBALS["CONNECTORS"]=["openrouter"];

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with($this->equalTo('https://openrouter.ai/api/v1/chat/completions'))
        ->willReturnCallback(function($url, $context) {
            return $this->defaultConnectorResponse($url, $context);
        });

        // Assert that HERIKA_DYNAMIC is not set
        $testfilename = "conf_".md5("Unit Test").".php";
        $testpath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."conf".DIRECTORY_SEPARATOR.$testfilename;
        $this->assertTrue(file_exists($testpath));
        $content = file_get_contents($testpath);
        preg_match('/\$HERIKA_DYNAMIC=\'(.*?)\';/s', $content, $matches);
        $this->assertEquals(0, count($matches));

        // comm.php?data=updateprofile|100|200|Unit Test (base64 encoded)
        $encodedData = base64_encode("updateprofile|100|200|Unit Test");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        $_GET["profile"] = md5($this->testNPCName);
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");

        // Assert that HERIKA_DYNAMIC is set
        $this->assertTrue(file_exists($testpath));
        $content = file_get_contents($testpath);
        preg_match('/\$HERIKA_DYNAMIC=\'(.*?)\';/s', $content, $matches);
        $this->assertEquals(2, count($matches));
        $this->assertEquals("Dynamic profile here", $matches[1]);
    }

    public function testDynamicProfile_WhenDynamicProfileExists_ShouldUpdateDynamicProfile(): void
    {
        // default test config
        require("conf.php");
        $GLOBALS["HERIKA_NAME"]="Unit Test";
        $GLOBALS["HERIKA_PERS"]="You are a Unit Test.";
        $GLOBALS["CONNECTORS"]=["openrouter"];

        // set herika_dynamic in conf file
        $testfilename = "conf_".md5("Unit Test").".php";
        $testpath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."conf".DIRECTORY_SEPARATOR.$testfilename;
        $content = '<?php\n$HERIKA_DYNAMIC=\'Old dynamic profile\';\n?>';
        file_put_contents($testpath, $content);

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with($this->equalTo('https://openrouter.ai/api/v1/chat/completions'))
        ->willReturnCallback(function($url, $context) {
            return $this->defaultConnectorResponse($url, $context);
        });

        // Assert that HERIKA_DYNAMIC is set
        $this->assertTrue(file_exists($testpath));
        $content = file_get_contents($testpath);
        preg_match('/\$HERIKA_DYNAMIC=\'(.*?)\';/s', $content, $matches);
        $this->assertEquals(2, count($matches));
        $this->assertEquals("Old dynamic profile", $matches[1]);

        // comm.php?data=updateprofile|100|200|Unit Test (base64 encoded)
        $encodedData = base64_encode("updateprofile|100|200|Unit Test");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        $_GET["profile"] = md5($this->testNPCName);
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");

        // Assert that HERIKA_DYNAMIC is updated
        $this->assertTrue(file_exists($testpath));
        $content = file_get_contents($testpath);
        preg_match('/\$HERIKA_DYNAMIC=\'(.*?)\';/s', $content, $matches);
        $this->assertEquals(2, count($matches));
        $this->assertEquals("Dynamic profile here", $matches[1]);
    }

    public function testDynamicProfile_ShouldEscapeQuotes(): void
    {
        // default test config
        require("conf.php");
        $GLOBALS["HERIKA_NAME"]="Unit Test";
        $GLOBALS["HERIKA_PERS"]="You are a Unit Test.";
        $GLOBALS["CONNECTORS"]=["openrouter"];

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with($this->equalTo('https://openrouter.ai/api/v1/chat/completions'))
        ->willReturnCallback(function($url, $context) {
            $response = 'data: {"choices":[{"delta":{"content": "Let\'s use \"quotes.\""}}]}';
            $resourceMock = fopen('php://temp', 'r+');
            fwrite($resourceMock, $response);
            rewind($resourceMock);
            return $resourceMock;
        });

        // comm.php?data=updateprofile|100|200|Unit Test (base64 encoded)
        $encodedData = base64_encode("updateprofile|100|200|Unit Test");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        $_GET["profile"] = md5($this->testNPCName);
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");

        // Assert that HERIKA_DYNAMIC is updated
        $testfilename = "conf_".md5("Unit Test").".php";
        $testpath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."conf".DIRECTORY_SEPARATOR.$testfilename;
        $this->assertTrue(file_exists($testpath));
        $content = file_get_contents($testpath);
        preg_match('/\$HERIKA_DYNAMIC=\'(.*?)\';/s', $content, $matches);
        $this->assertEquals(2, count($matches));
        $this->assertEquals('Let\\\'s use "quotes."', $matches[1]); // $HERIKA_DYNAMIC='Let\'s use "quotes."';
    }

    public function testDynamicProfile_ShouldStoreEmptyDynamicProfile(): void
    {
        // default test config
        require("conf.php");
        $GLOBALS["HERIKA_NAME"]="Unit Test";
        $GLOBALS["HERIKA_PERS"]="You are a Unit Test.";
        $GLOBALS["CONNECTORS"]=["openrouter"];

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with($this->equalTo('https://openrouter.ai/api/v1/chat/completions'))
        ->willReturnCallback(function($url, $context) {
            $response = 'data: {"choices":[{"delta":{"content": ""}}]}';
            $resourceMock = fopen('php://temp', 'r+');
            fwrite($resourceMock, $response);
            rewind($resourceMock);
            return $resourceMock;
        });

        // comm.php?data=updateprofile|100|200|Unit Test (base64 encoded)
        $encodedData = base64_encode("updateprofile|100|200|Unit Test");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        $_GET["profile"] = md5($this->testNPCName);
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");

        // Assert that HERIKA_DYNAMIC is updated
        $testfilename = "conf_".md5("Unit Test").".php";
        $testpath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."conf".DIRECTORY_SEPARATOR.$testfilename;
        $this->assertTrue(file_exists($testpath));
        $content = file_get_contents($testpath);
        preg_match('/\$HERIKA_DYNAMIC=\'(.*?)\';/s', $content, $matches);
        $this->assertEquals(2, count($matches));
        $this->assertEquals('', $matches[1]); // $HERIKA_DYNAMIC='';
    }

    public function testDynamicProfile_ShouldUseQuotesInDynamicProfile(): void
    {
        // default test config
        require("conf.php");
        $GLOBALS["HERIKA_NAME"]="Unit Test";
        $GLOBALS["HERIKA_PERS"]="You are a Unit Test.";
        $GLOBALS["HERIKA_DYNAMIC"]='Let\'s use "quotes."'; // $HERIKA_DYNAMIC='Let\'s use "quotes."';

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('https://openrouter.ai/api/v1/chat/completions'),
            $this->callback(function ($streamContext) {
                $expectedPrompt = ["role"=>"system", "content"=>"Let's roleplay in the Universe of Skyrim.\nI'm Prisoner\nYou are a Unit Test.Let's use \"quotes.\"\n\nDon't write narrations.\nNo active quests right now."];
                $this->expectPromptInContext($streamContext, $expectedPrompt);
                
                return true;
            })
        )
        ->willReturnCallback(function($url, $context) {
            $response = 'data: {"choices":[{"delta":{"content": "{\"character\": \"Unit Test\", \"listener\": \"Prisoner\", \"message\": \"Unit test message\", \"mood\": \"default\", \"action\": \"Talk\", \"target\": \"Prisoner\"}"}}]}';
            $resourceMock = fopen('php://temp', 'r+');
            fwrite($resourceMock, $response);
            rewind($resourceMock);
            return $resourceMock;
        });

        // comm.php?data=inputtext|100|200|Hey Unit Test, attack that monster! (base64 encoded)
        $encodedData = base64_encode("inputtext|100|200|Hey Unit Test, attack that monster!");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");
    }

    public function testDynamicProfile_ShouldPreserveUnnecessarilyEscapedQuotesInDynamicProfile(): void
    {
        // default test config
        require("conf.php");
        $GLOBALS["HERIKA_NAME"]="Unit Test";
        $GLOBALS["HERIKA_PERS"]="You are a Unit Test.";
        $GLOBALS["HERIKA_DYNAMIC"]='Let\\\'s use \"quotes.\"'; // $HERIKA_DYNAMIC='Let\\\'s use \"quotes.\"';

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('https://openrouter.ai/api/v1/chat/completions'),
            $this->callback(function ($streamContext) {
                $expectedPrompt = ["role"=>"system", "content"=>"Let's roleplay in the Universe of Skyrim.\nI'm Prisoner\nYou are a Unit Test.Let\'s use \\\"quotes.\\\"\n\nDon't write narrations.\nNo active quests right now."];
                $this->expectPromptInContext($streamContext, $expectedPrompt);
                
                return true;
            })
        )
        ->willReturnCallback(function($url, $context) {
            $response = 'data: {"choices":[{"delta":{"content": "{\"character\": \"Unit Test\", \"listener\": \"Prisoner\", \"message\": \"Unit test message\", \"mood\": \"default\", \"action\": \"Talk\", \"target\": \"Prisoner\"}"}}]}';
            $resourceMock = fopen('php://temp', 'r+');
            fwrite($resourceMock, $response);
            rewind($resourceMock);
            return $resourceMock;
        });

        // comm.php?data=inputtext|100|200|Hey Unit Test, attack that monster! (base64 encoded)
        $encodedData = base64_encode("inputtext|100|200|Hey Unit Test, attack that monster!");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");
    }

    public function testDynamicProfile_ShouldSendDialogueHistoryToLLM(): void
    {
        // default test config
        require("conf.php");
        $GLOBALS["HERIKA_NAME"]="Unit Test";
        $GLOBALS["HERIKA_PERS"]="You are a Unit Test.";
        $GLOBALS["CONNECTORS"]=["openrouter"];

        // add dialogue history
        $testDb = new sql();
        $testDb->insert(
            'speech',
            array(
                'sess' => 'pending',
                'speaker' => 'Prisoner',
                'speech' => "I'm going to bed now.",
                'location' => 'Riften ,Hold: The Rift',
                'listener' => 'Unit Test',
                'topic' => null,
                'localts' => 0,
                'gamets' => 0,
                'ts' => 0,
                'companions' => 'Unit Test',
                'audios' => null
            )
        );
        $testDb->insert(
            'speech',
            array(
                'sess' => 'pending',
                'speaker' => 'Unit Test',
                'speech' => 'Good night.',
                'location' => 'Riften ,Hold: The Rift',
                'listener' => 'Prisoner',
                'topic' => null,
                'localts' => 1,
                'gamets' => 1,
                'ts' => 1,
                'companions' => 'Unit Test',
                'audios' => null
            )
        );
        $testDb->close();

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('https://openrouter.ai/api/v1/chat/completions'),
            $this->callback(function ($streamContext) {
                $expectedPrompt = ["role"=>"user", "content"=>"* Dialogue history:\nPrisoner:I'm going to bed now.  (talking to Unit Test)  (at Riften ,Hold: The Rift)\nUnit Test:Good night.  (talking to Prisoner)\n"];
                $this->expectPromptInContext($streamContext, $expectedPrompt);
                
                return true;
            })
        )
        ->willReturnCallback(function($url, $context) {
            return $this->defaultConnectorResponse($url, $context);
        });

        // comm.php?data=updateprofile|100|200|Unit Test (base64 encoded)
        $encodedData = base64_encode("updateprofile|100|200|Unit Test");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        $_GET["profile"] = md5($this->testNPCName);
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");
    }

    private function expectPromptInContext($streamContext, $expectedPrompt) {
        $options = stream_context_get_options($streamContext);
        $content = json_decode($options['http']['content']);
        $found=false;
        foreach ($content->messages as $message) {
            if (json_encode($message) === json_encode($expectedPrompt)) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found);
    }

    private function expectPromptNotInContext($streamContext, $expectedPrompt) {
        $options = stream_context_get_options($streamContext);
        $content = json_decode($options['http']['content']);
        $found=false;
        foreach ($content->messages as $message) {
            if (json_encode($message) === json_encode($expectedPrompt)) {
                $found = true;
                break;
            }
        }

        $this->assertFalse($found);
    }

    private function defaultConnectorResponse($url, $context) {
        $response = 'data: {"choices":[{"delta":{"content": "Dynamic profile here"}}]}';
        $resourceMock = fopen('php://temp', 'r+');
        fwrite($resourceMock, $response);
        rewind($resourceMock);
        return $resourceMock;
    }
}