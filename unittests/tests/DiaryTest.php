<?php declare(strict_types=1);

require_once 'DatabaseTestCase.php';
require_once 'CallableMock.php';

// setUp and tearDown for the test database are in DatabaseTestCase.php
final class DiaryTest extends DatabaseTestCase
{
    public function testDiary_EmptyContentInContext_ShouldBeStrippedFromDiaryPrompt(): void
    {
        // default test config
        require("conf.php");
        $GLOBALS["HERIKA_NAME"]="Unit Test";
        $GLOBALS["HERIKA_PERS"]="You are a Unit Test.";
        $GLOBALS["CONNECTORS"]=["openrouter"];

        // add dialogue history
        $testDb = new sql();
        $testDb->insert(
            'eventlog',
            array(
                'ts' => "0",
                'gamets' => "0",
                'type' => "inputtext",
                'data' => "Prisoner:Silence. (Talking to Unit Test)",
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
                'ts' => "0",
                'gamets' => "0",
                'type' => "chat",
                'data' => "", // empty content
                'sess' => 'pending',
                'localts' => 2,
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
            $this->equalTo('https://openrouter.ai/api/v1/chat/completions'),
            $this->callback(function ($streamContext) {
                $expectedPrompt = ["role"=>"user", "content"=>""];
                $this->expectPromptNotInContext($streamContext, $expectedPrompt);

                $expectedPrompt = ["role"=>"user", "content"=>"Please write a short summary of Prisoner and Unit Tests last dialogues and events written above into Unit Tests diary . WRITE AS IF YOU WERE Unit Test."];
                $this->expectPromptInContext($streamContext, $expectedPrompt);
                return true;
            })
        )
        ->willReturnCallback(function($url, $context) {
            return $this->defaultConnectorResponse($url, $context);
        });

        // comm.php?data=diary|100|200|Unit Test (base64 encoded)
        $encodedData = base64_encode("diary|100|200|Unit Test");
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
        $response = 'data: {"choices":[{"delta":{"content": "Diary content here"}}]}';
        $resourceMock = fopen('php://temp', 'r+');
        fwrite($resourceMock, $response);
        rewind($resourceMock);
        return $resourceMock;
    }
}