<?php declare(strict_types=1);

require_once __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'DatabaseTestCase.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'CallableMock.php';

// setUp and tearDown for the test database are in DatabaseTestCase.php
final class KoboldCPPTest extends DatabaseTestCase
{
    public function testKoboldCPP_WhenInputText_ShouldStopOnPlayerName(): void
    {
        // default test config
        require(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR."conf.php");
        $GLOBALS["CONNECTORS"]=["koboldcpp"];

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('127.0.0.1'),
            $this->equalTo(5001),
            $this->equalTo(null),
            $this->equalTo(null),
            $this->equalTo(30),
            $this->callback(function ($streamContext) {
                $content = $this->getJSONPromptFromRequest($streamContext);
                $expected = [
                    "Prisoner:",
                    "\nPrisoner ",
                    "Author's notes",
                    "###",
                    '```',
                    "<|im_start|>",
                    "<|im_end|>",
                ];
                $this->assertSame($expected, $content->stop_sequence);

                return true;
            })
        )
        ->willReturnCallback(function($host, $port, $errno, $errstr, $timeout, $request) {
            return $this->defaultConnectorResponse($host, $port, $errno, $errstr, $timeout, $request);
        });

        // comm.php?data=inputtext|100|200|Hey Narrator, attack that monster! (base64 encoded)
        $encodedData = base64_encode("inputtext|100|200|Hey Narrator, attack that monster!");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");
    }

    public function testKoboldCPP_WhenBook_ShouldStopOnPlayerName(): void
    {
        // default test config
        require(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR."conf.php");
        $GLOBALS["HERIKA_NAME"]="Unit Test";
        $GLOBALS["HERIKA_PERS"]="You are a Unit Test.";
        $GLOBALS["CONNECTORS"]=["koboldcpp"];
        $GLOBALS["CONNECTORS_DIARY"]='koboldcpp';

        $book = 'Title: The Lusty Argonian Maid, Vol. 1\n[pagebreak] <p align="center"> The Lusty Argonian Maid Volume 1. ...';

        // add book events
        $testDb = new sql();
        $testDb->insert(
            'eventlog',
            array(
                'ts' => "0",
                'gamets' => "0",
                'type' => "itemfound",
                'data' => "Prisoner found 1 The Lusty Argonian Maid, Vol. 1 (a book)",
                'sess' => 'pending',
                'localts' => 0,
                'people'=> "|Unit Test|",
                'location'=> "",
                'party'=> "[]"
            )
        );
        $testDb->insert(
            'eventlog',
            array(
                'ts' => "1",
                'gamets' => "1",
                'type' => "contentbook",
                'data' => $book,
                'sess' => 'pending',
                'localts' => 1,
                'people'=> "|Unit Test|",
                'location'=> "",
                'party'=> "[]"
            )
        );
        $testDb->insert(
            'books',
            array(
                'ts' => "2",
                'gamets' => "2",
                'title' => "The Lusty Argonian Maid, Vol. 1",
                'content' => "",
                'sess' => 'pending',
                'localts' => 2
            )
        );
        $testDb->insert(
            'books',
            array(
                'ts' => "3",
                'gamets' => "3",
                'content' => $book,
                'localts' => 3
            )
        );
        $testDb->close();

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('127.0.0.1'),
            $this->equalTo(5001),
            $this->equalTo(null),
            $this->equalTo(null),
            $this->equalTo(30),
            $this->callback(function ($streamContext) {
                $content = $this->getJSONPromptFromRequest($streamContext);
                $expected = [
                    "Prisoner:",
                    "\nPrisoner ",
                    "Author's notes",
                    "###",
                    '```',
                    "<|im_start|>",
                    "<|im_end|>",
                ];
                $this->assertSame($expected, $content->stop_sequence);

                return true;
            })
        )
        ->willReturnCallback(function($host, $port, $errno, $errstr, $timeout, $request) {
            return $this->defaultConnectorResponse($host, $port, $errno, $errstr, $timeout, $request);
        });

        // comm.php?data=chatnf_book|100|200|Please, summarize this book i've just found. (base64 encoded)
        $encodedData = base64_encode("chatnf_book|100|200|Please, summarize this book i've just found.");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        $_GET["profile"] = md5($this->testNPCName);
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");
    }

    public function testKoboldCPP_WhenDiary_ShouldNotStopOnPlayerName(): void
    {
        // default test config
        require(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR."conf.php");
        $GLOBALS["HERIKA_NAME"]="Unit Test";
        $GLOBALS["HERIKA_PERS"]="You are a Unit Test.";
        $GLOBALS["CONNECTORS"]=["koboldcpp"];
        $GLOBALS["CONNECTORS_DIARY"]='koboldcpp';

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('127.0.0.1'),
            $this->equalTo(5001),
            $this->equalTo(null),
            $this->equalTo(null),
            $this->equalTo(30),
            $this->callback(function ($streamContext) {
                $content = $this->getJSONPromptFromRequest($streamContext);
                $expected = [
                    "Author's notes",
                    "###",
                    '```',
                    "<|im_start|>",
                    "<|im_end|>",
                ];
                $this->assertSame($expected, $content->stop_sequence);

                return true;
            })
        )
        ->willReturnCallback(function($host, $port, $errno, $errstr, $timeout, $request) {
            return $this->defaultConnectorResponse($host, $port, $errno, $errstr, $timeout, $request);
        });

        // comm.php?data=diary|100|200|Unit Test (base64 encoded)
        $encodedData = base64_encode("diary|100|200|Unit Test");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        $_GET["profile"] = md5($this->testNPCName);
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");
    }

    public function testKoboldCPP_WhenSummary_ShouldNotStopOnPlayerName(): void
    {
        // default test config
        require(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR."conf.php");
        $GLOBALS["HERIKA_NAME"]="Unit Test";
        $GLOBALS["HERIKA_PERS"]="You are a Unit Test.";
        $GLOBALS["CONNECTORS"]=["koboldcpp"];
        $GLOBALS["CONNECTORS_DIARY"]='koboldcpp';
        
        // add unsummarized memory
        $testDb = new sql();
        $testDb->insert(
            'memory_summary',
            array(
                'gamets_truncated' => 0,
                'n' => 0,
                'packed_message' => "text to be summarized",
                'classifier' => "past dialogues",
                'uid' => 0,
            )
        );
        $testDb->insert(
            'eventlog',
            array(
                'ts' => "0",
                'gamets' => "0",
                'type' => "inputtext",
                'data' => "Prisoner:Test message. (Talking to Unit Test)",
                'sess' => 'pending',
                'localts' => 0,
                'people'=> "|Unit Test|",
                'location'=> "",
                'party'=> "[]"
            )
        );
        $testDb->close();

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('127.0.0.1'),
            $this->equalTo(5001),
            $this->equalTo(null),
            $this->equalTo(null),
            $this->equalTo(30),
            $this->callback(function ($streamContext) {
                $content = $this->getJSONPromptFromRequest($streamContext);
                $expected = [
                    "Author's notes",
                    "###",
                    '```',
                    "<|im_start|>",
                    "<|im_end|>",
                ];
                $this->assertSame($expected, $content->stop_sequence);

                return true;
            })
        )
        ->willReturnCallback(function($host, $port, $errno, $errstr, $timeout, $request) {
            $response = 'data: {"token": "#Summary: This is a Unit Test.\n#Tags: #Test", "finish_reason": "length"}';
            $resourceMock = fopen('php://temp', 'r+');
            fwrite($resourceMock, $response);
            rewind($resourceMock);
            return $resourceMock;
        });

        $_GET["profile"] = md5($this->testNPCName);
        global $argv;
        $argv = ["util_memory_subsystem.php", "compact", "noembed", "2", "&"];
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."debug".DIRECTORY_SEPARATOR."util_memory_subsystem.php");
    }

    public function testKoboldCPP_WhenUpdateProfile_ShouldNotStopOnPlayerName(): void
    {
        // default test config
        require(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR."conf.php");
        $GLOBALS["HERIKA_NAME"]="Unit Test";
        $GLOBALS["HERIKA_PERS"]="You are a Unit Test.";
        $GLOBALS["CONNECTORS"]=["koboldcpp"];
        $GLOBALS["CONNECTORS_DIARY"]='koboldcpp';

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('127.0.0.1'),
            $this->equalTo(5001),
            $this->equalTo(null),
            $this->equalTo(null),
            $this->equalTo(30),
            $this->callback(function ($streamContext) {
                $content = $this->getJSONPromptFromRequest($streamContext);
                $expected = [
                    "Author's notes",
                    "###",
                    '```',
                    "<|im_start|>",
                    "<|im_end|>",
                ];
                $this->assertSame($expected, $content->stop_sequence);

                return true;
            })
        )
        ->willReturnCallback(function($host, $port, $errno, $errstr, $timeout, $request) {
            return $this->defaultConnectorResponse($host, $port, $errno, $errstr, $timeout, $request);
        });

        // comm.php?data=updateprofile|100|200|Unit Test (base64 encoded)
        $encodedData = base64_encode("updateprofile|100|200|Unit Test");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        $_GET["profile"] = md5($this->testNPCName);
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");
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

    private function defaultConnectorResponse($host, $port, $errno, $errstr, $timeout, $request) {
        $response = 'data: {"token": "KoboldCPP response here", "finish_reason": "length"}';
        $resourceMock = fopen('php://temp', 'r+');
        fwrite($resourceMock, $response);
        rewind($resourceMock);
        return $resourceMock;
    }

    private function getJSONPromptFromRequest($request) {
        $startPos = strpos($request, '{');
        if ($startPos === false) {
            return null; // No JSON object found
        }
    
        $jsonString = substr($request, $startPos);
        return json_decode($jsonString);
    }
}