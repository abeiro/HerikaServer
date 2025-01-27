<?php declare(strict_types=1);

require_once 'DatabaseTestCase.php';
require_once 'CallableMock.php';

// setUp and tearDown for the test database are in DatabaseTestCase.php
final class CommTest extends DatabaseTestCase
{
    public function testComm_WhenInputText_LLMPostShouldContainAPIToken(): void
    {
        // default test config
        require("conf.php");

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('https://openrouter.ai/api/v1/chat/completions'),
            $this->callback(function ($streamContext) {
                $options = stream_context_get_options($streamContext);
                
                // Assert method
                $this->assertEquals('POST', $options['http']['method']);

                // Assert headers
                $headers = explode("\r\n", $options['http']['header']);
                $expectedHeaders = [
                    'Content-Type: application/json',
                    "Authorization: Bearer openrouterjson_key"
                ];
                foreach ($expectedHeaders as $expectedHeader) {
                    $this->assertContains($expectedHeader, $headers);
                }
                
                return true;
            })
        )
        ->willReturnCallback(function($url, $context) {
            return $this->defaultConnectorResponse($url, $context);
        });

        // comm.php?data=inputtext|100|200|Hey Narrator, attack that monster! (base64 encoded)
        $encodedData = base64_encode("inputtext|100|200|Hey Narrator, attack that monster!");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");
    }

    public function testComm_WhenInputText_ContextShouldContainSystemPrompt(): void
    {
        // default test config
        require("conf.php");

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('https://openrouter.ai/api/v1/chat/completions'),
            $this->callback(function ($streamContext) {
                $expectedPrompt = [
                    "role"=>"system",
                    "content"=>"Let's roleplay in the Universe of Skyrim.\nI'm Prisoner\nYou are The Narrator in a Skyrim adventure. You will only talk to Prisoner. You refer to yourself as 'The Narrator'. Only Prisoner can hear you. Your goal is to comment on Prisoner's playthrough, and occasionally give hints. NO SPOILERS. Talk about quests and last events.\n\nDon't write narrations.\nNo active quests right now."
                ];
                $this->expectPromptInContext($streamContext, $expectedPrompt);
                
                return true;
            })
        )
        ->willReturnCallback(function($url, $context) {
            return $this->defaultConnectorResponse($url, $context);
        });

        // comm.php?data=inputtext|100|200|Hey Narrator, attack that monster! (base64 encoded)
        $encodedData = base64_encode("inputtext|100|200|Hey Narrator, attack that monster!");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");
    }

    public function testComm_WhenOpenRouterAndHasAssistantMessage_ContextShouldNotContainContentExample(): void
    {
        // default test config
        require("conf.php");
        $GLOBALS["HERIKA_NAME"] = "Lydia";
        $GLOBALS["HERIKA_PERS"] = "Roleplay as Lydia.";

        // add chat history in order to create assistant role
        $testDb = new sql();
        $testDb->insert(
            'eventlog',
            array(
                'ts' => "0",
                'gamets' => "0",
                'type' => "inputtext",
                'data' => "Prisoner:Make sure there are no more enemies nearby. (Talking to Lydia)",
                'sess' => 'pending',
                'localts' => 0,
                'people'=> "|Lydia|",
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
                'data' => "Lydia: Very well my Thane, I'll take a look around. (talking to Prisoner)",
                'sess' => 'pending',
                'localts' => 2,
                'people'=> "|Lydia|",
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
                $expectedPrompt = ["role"=>"user", "content"=>"The Narrator: Prisoner looks at The Narrator"];
                $this->expectPromptNotInContext($streamContext, $expectedPrompt);
                
                return true;
            })
        )
        ->willReturnCallback(function($url, $context) {
            return $this->defaultConnectorResponse($url, $context);
        });

        // comm.php?data=funcret|100|200|command@InspectSurroundings@@Ghost(hostile),Skeleton(dead),Lydia, (base64 encoded)
        $encodedData = base64_encode("funcret|100|200|command@InspectSurroundings@@Ghost(hostile),Skeleton(dead),Lydia,");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");
    }

    public function testComm_WhenOpenRouterAndNoAssistantMessage_ContextShouldContainContentExample(): void
    {
        // default test config
        require("conf.php");

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('https://openrouter.ai/api/v1/chat/completions'),
            $this->callback(function ($streamContext) {
                $expectedPrompt = ["role"=>"user", "content"=>"The Narrator: Prisoner looks at The Narrator"];
                $this->expectPromptInContext($streamContext, $expectedPrompt);

                $expectedPrompt = [
                    "role"=>"assistant",
                    "content"=>"{\"character\": \"The Narrator\",\"listener\": \"Prisoner\", \"mood\": \"default\", \"action\": \"Talk\",\"target\": \"\", \"message\": \"What are you looking at?\"}"
                ];
                $this->expectPromptInContext($streamContext, $expectedPrompt);
                
                return true;
            })
        )
        ->willReturnCallback(function($url, $context) {
            return $this->defaultConnectorResponse($url, $context);
        });

        // comm.php?data=inputtext|100|200|Hey Narrator, attack that monster! (base64 encoded)
        $encodedData = base64_encode("inputtext|100|200|Hey Narrator, attack that monster!");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");
    }

    public function testComm_WhenInputText_ContextShouldContainFollowers(): void
    {
        // default test config
        require("conf.php");

        // add followers in database
        $testDb = new sql();
        $testDb->insert(
            'conf_opts',
            array(
                'id' => "CurrentParty",
                'value' => '{"level":6,"name":"Lydia","race":"Nord","gender":"female","isVampire":"no"},',
            )
        );
        $testDb->close();

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('https://openrouter.ai/api/v1/chat/completions'),
            $this->callback(function ($streamContext) {
                $expectedPrompt = ["role"=>"user", "content"=>'Current followers:{"Lydia":{"level":6,"name":"Lydia","race":"Nord","gender":"female","isVampire":"no"}}'];
                $this->expectPromptInContext($streamContext, $expectedPrompt);
                
                return true;
            })
        )
        ->willReturnCallback(function($url, $context) {
            return $this->defaultConnectorResponse($url, $context);
        });

        // comm.php?data=inputtext|100|200|Hey Narrator, attack that monster! (base64 encoded)
        $encodedData = base64_encode("inputtext|100|200|Hey Narrator, attack that monster!");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");
    }

    public function testComm_WhenInputText_ContextShouldContainUserInput(): void
    {
        // default test config
        require("conf.php");

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('https://openrouter.ai/api/v1/chat/completions'),
            $this->callback(function ($streamContext) {
                $expectedPrompt = ["role"=>"user", "content"=>"Hey Narrator, attack that monster! (Talking to The Narrator)"];
                $this->expectPromptInContext($streamContext, $expectedPrompt);
                
                return true;
            })
        )
        ->willReturnCallback(function($url, $context) {
            return $this->defaultConnectorResponse($url, $context);
        });

        // comm.php?data=inputtext|100|200|Hey Narrator, attack that monster! (base64 encoded)
        $encodedData = base64_encode("inputtext|100|200|Hey Narrator, attack that monster!");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");
    }

    public function testComm_WhenInputText_ContextShouldContainPromptCue(): void
    {
        // default test config
        require("conf.php");

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('https://openrouter.ai/api/v1/chat/completions'),
            $this->callback(function ($streamContext) {
                $expectedPrompt = ["role"=>"user", "content"=>" The Narrator replies to Prisoner. write The Narrator's next dialogue lines. Avoid narrations. "];
                $this->expectPromptInContext($streamContext, $expectedPrompt);
                
                return true;
            })
        )
        ->willReturnCallback(function($url, $context) {
            return $this->defaultConnectorResponse($url, $context);
        });

        // comm.php?data=inputtext|100|200|Hey Narrator, attack that monster! (base64 encoded)
        $encodedData = base64_encode("inputtext|100|200|Hey Narrator, attack that monster!");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");
    }

    public function testComm_WhenInputText_ContextShouldContainCommandPrompt(): void
    {
        // default test config
        require("conf.php");

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('https://openrouter.ai/api/v1/chat/completions'),
            $this->callback(function ($streamContext) {
                $expectedPrompt = [
                    "role"=>"user",
                    "content"=>"Choose coherent ACTION to obey Prisoner..  Use this JSON object to give your answer: {\"character\":\"The Narrator\",\"listener\":\"specify who The Narrator is talking to\",\"mood\":\"sassy|assertive|sexy|smug|kindly|lovely|seductive|sarcastic|sardonic|smirking|amused|default|assisting|irritated|playful|neutral|teasing|mocking\",\"action\":\"\",\"target\":\"action's target|destination name\",\"message\":\"lines of dialogue\"}"
                ];
                $this->expectPromptInContext($streamContext, $expectedPrompt);
                
                return true;
            })
        )
        ->willReturnCallback(function($url, $context) {
            return $this->defaultConnectorResponse($url, $context);
        });

        // comm.php?data=inputtext|100|200|Hey Narrator, attack that monster! (base64 encoded)
        $encodedData = base64_encode("inputtext|100|200|Hey Narrator, attack that monster!");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");
    }

    public function testComm_WhenOpenRouter_POSTShouldContainLLMSettings(): void
    {
        // default test config
        require("conf.php");

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('https://openrouter.ai/api/v1/chat/completions'),
            $this->callback(function ($streamContext) {
                $options = stream_context_get_options($streamContext);
                $content = json_decode($options['http']['content']);
                $this->assertSame("meta-llama/llama-3.1-70b-instruct", $content->model);
                $this->assertSame(true, $content->stream);
                $this->assertSame(512, $content->max_tokens);
                $this->assertSame(["USER"], $content->stop);
                $this->assertSame(0.8, $content->temperature);
                $this->assertSame(0, $content->frequency_penalty);
                $this->assertSame(0, $content->presence_penalty);
                $this->assertSame(1.1, $content->repetition_penalty);
                $this->assertSame(0, $content->min_p);
                $this->assertSame(0, $content->top_a);
                $this->assertSame(40, $content->top_k);
                $this->assertSame(1, $content->top_p);
                $this->assertEquals((object)["type"=>"json_object"], $content->response_format);
                $this->assertSame([], $content->transforms);
                
                return true;
            })
        )
        ->willReturnCallback(function($url, $context) {
            return $this->defaultConnectorResponse($url, $context);
        });

        // comm.php?data=inputtext|100|200|Hey Narrator, attack that monster! (base64 encoded)
        $encodedData = base64_encode("inputtext|100|200|Hey Narrator, attack that monster!");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");
    }

    public function testComm_WhenInputText_LLMResponseShouldBeAddedToEventLog(): void
    {
        // default test config
        require("conf.php");

        // comm.php?data=inputtext|100|200|Hey Narrator, attack that monster! (base64 encoded)
        $encodedData = base64_encode("inputtext|100|200|Hey Narrator, attack that monster!");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");

        // confirm LLM response added to eventlog as chat
        $testDb = new sql();
        $rows=$testDb->fetchAll("SELECT * FROM eventlog ORDER BY rowid DESC LIMIT 1;");
        $testDb->close();
        $this->assertSame("chat", $rows[0]["type"]);
        $this->assertSame("The Narrator: Unit test message (talking to Prisoner)", $rows[0]["data"]);
        $this->assertSame("pending", $rows[0]["sess"]);
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
        $response = 'data: {"choices":[{"delta":{"content": "{\"character\": \"The Narrator\", \"listener\": \"Prisoner\", \"message\": \"Unit test message\", \"mood\": \"default\", \"action\": \"Talk\", \"target\": \"Prisoner\"}"}}]}';
        $resourceMock = fopen('php://temp', 'r+');
        fwrite($resourceMock, $response);
        rewind($resourceMock);
        return $resourceMock;
    }
}