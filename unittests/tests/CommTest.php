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
                'party'=> "[]",
                'delivery_state' => 'emitted'
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

    public function testComm_WhenLatestAssistantMessageIsPending_ContextShouldStillContainContentExample(): void
    {
        require("conf.php");
        $GLOBALS["HERIKA_NAME"] = "Lydia";
        $GLOBALS["HERIKA_PERS"] = "Roleplay as Lydia.";

        $testDb = new sql();
        $testDb->insert(
            'eventlog',
            array(
                'ts' => "0",
                'gamets' => "0",
                'type' => "inputtext",
                'data' => "Prisoner:Inspect the area again. (Talking to Lydia)",
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
                'data' => "Lydia: This line should stay hidden until emitted. (talking to Prisoner)",
                'sess' => 'pending',
                'localts' => 2,
                'people'=> "|Lydia|",
                'location'=> "",
                'party'=> "[]",
                'delivery_state' => 'pending'
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
                $this->expectPromptInContext($streamContext, $expectedPrompt);

                return true;
            })
        )
        ->willReturnCallback(function($url, $context) {
            return $this->defaultConnectorResponse($url, $context);
        });

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
                $expectedPrompt = ["role"=>"user", "content"=>" The Narrator replies to Prisoner. Write The Narrator's next prose/narration. "];
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
                $options = stream_context_get_options($streamContext);
                $content = json_decode($options['http']['content']);
                foreach ($content->messages as $actual) {
                    if (isset($actual->role) && $actual->role === "user" && isset($actual->content) && strpos($actual->content, ".  Use ONLY this JSON object to give your answer. Do not send any other characters outside of this JSON structure: ") === 0)
                    {
                        $jsonString = preg_match('/\{(.*)\}/', $actual->content, $matches);
                        $jsonString = $matches[0];
                        $data = json_decode($jsonString, true);
                        $moodPrefix = 'choose exactly one mood while speaking from this list, never combine moods: ';
                        $this->assertStringStartsWith($moodPrefix, $data['mood']);
                        $moodString = substr($data['mood'], strlen($moodPrefix));
                        $moodArray = explode('|', $moodString);
                        sort($moodArray);
                        $this->assertSame('The Narrator', $data['character']);
                        $this->assertSame('specify who The Narrator is talking to', $data['listener']);
                        $this->assertSame(
                            [
                                'amused',
                                'angry',
                                'assertive',
                                'desperate',
                                'drunk',
                                'happy',
                                'irritated',
                                'kindly',
                                'lovely',
                                'neutral',
                                'playful',
                                'pleading',
                                'sad',
                                'sarcastic',
                                'sassy',
                                'scared',
                                'seductive',
                                'sexy',
                                'shy',
                                'smirking',
                                'smug',
                                'surprised',
                                'teasing',
                            ],
                            $moodArray
                        );
                        $this->assertSame('', $data['action']);
                        $this->assertSame("action's target|destination name", $data['target']);
                        $this->assertSame('lines of dialogue', $data['message']);
                        return true;
                    }
                }

                return false;
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
        $this->assertSame("emitted", $rows[0]["delivery_state"]);
    }

    public function testComm_WhenSpeechIsAborted_EmittedChatRowShouldBeMarkedAborted(): void
    {
        require("conf.php");

        $testDb = new sql();
        $testDb->insert(
            'eventlog',
            array(
                'ts' => "100",
                'gamets' => "200",
                'type' => "chat",
                'data' => "Lydia: Hold there. (talking to Prisoner)",
                'sess' => 'pending',
                'localts' => 10,
                'people'=> "|Lydia|Prisoner|",
                'location'=> "",
                'party'=> "[]",
                'delivery_state' => 'emitted',
                'utterance_id' => 'utt_abort_1'
            )
        );
        $testDb->close();

        $encodedData = base64_encode('_speech_abort|101|201|{"utterance_ids":["utt_abort_1"]}');
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");

        $testDb = new sql();
        $rows = $testDb->fetchAll("SELECT delivery_state FROM eventlog WHERE utterance_id='utt_abort_1' ORDER BY rowid DESC LIMIT 1;");
        $testDb->close();

        $this->assertSame("aborted", $rows[0]["delivery_state"]);
    }

    public function testComm_WhenNarratorInputTextAndHideFromContextDisabled_NarratorReplyShouldKeepNearbyPeopleInEventLog(): void
    {
        require("conf.php");
        $GLOBALS["HIDE_NARRATOR_DIALOGUE"] = false;

        $testDb = new sql();
        $testDb->insert(
            'eventlog',
            array(
                'ts' => "99",
                'gamets' => "199",
                'type' => "infonpc_close",
                'data' => "beings in range:Hulda/Jon Battle-Born (far away)/Herika (busy)/Prisoner",
                'sess' => 'pending',
                'localts' => 0,
                'people'=> "|Hulda|Jon Battle-Born (far away)|Herika (busy)|Prisoner|",
                'location'=> "",
                'party'=> "[]"
            )
        );
        $testDb->close();

        $encodedData = base64_encode("narrator_inputtext|100|200|Who am I?");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");

        $testDb = new sql();
        $rows = $testDb->fetchAll("SELECT * FROM eventlog ORDER BY rowid DESC LIMIT 1;");
        $testDb->close();

        $this->assertSame("chat", $rows[0]["type"]);
        $this->assertSame("The Narrator: Unit test message (talking to Prisoner)", $rows[0]["data"]);
        $this->assertSame("|Hulda|Jon Battle-Born|Herika|Prisoner|The Narrator|", $rows[0]["people"]);
    }

    public function testComm_WhenNarratorInputTextAndHideFromContextEnabled_NarratorReplyShouldStayPrivateInEventLog(): void
    {
        require("conf.php");
        $GLOBALS["HIDE_NARRATOR_DIALOGUE"] = true;

        $testDb = new sql();
        $testDb->insert(
            'eventlog',
            array(
                'ts' => "99",
                'gamets' => "199",
                'type' => "infonpc_close",
                'data' => "beings in range:Hulda/Jon Battle-Born/Herika/Prisoner",
                'sess' => 'pending',
                'localts' => 0,
                'people'=> "|Hulda|Jon Battle-Born|Herika|Prisoner|",
                'location'=> "",
                'party'=> "[]"
            )
        );
        $testDb->close();

        $encodedData = base64_encode("narrator_inputtext|100|200|Who am I?");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");

        $testDb = new sql();
        $rows = $testDb->fetchAll("SELECT * FROM eventlog ORDER BY rowid DESC LIMIT 1;");
        $testDb->close();

        $this->assertSame("chat", $rows[0]["type"]);
        $this->assertSame("The Narrator: Unit test message (talking to Prisoner)", $rows[0]["data"]);
        $this->assertSame("|The Narrator|Prisoner|", $rows[0]["people"]);
    }

    public function testComm_WhenNarratorInputTextHasSpatialSpeechContextAndHideFromContextDisabled_InputEventShouldKeepNearbyPeople(): void
    {
        require("conf.php");
        $GLOBALS["HIDE_NARRATOR_DIALOGUE"] = false;

        $testDb = new sql();
        $testDb->insert(
            'eventlog',
            array(
                'ts' => "99",
                'gamets' => "199",
                'type' => "infonpc_close",
                'data' => "beings in range:Hulda/Jon Battle-Born (far away)/Herika (busy)/Prisoner",
                'sess' => 'pending',
                'localts' => 0,
                'people'=> "|Hulda|Jon Battle-Born (far away)|Herika (busy)|Prisoner|",
                'location'=> "",
                'party'=> "[]"
            )
        );
        $testDb->insert(
            'speech',
            array(
                'ts' => "100",
                'gamets' => "200",
                'listener' => "The Narrator",
                'speaker' => "Prisoner",
                'speech' => "Who am I?",
                'location' => "",
                'companions' => "|Hulda|Jon Battle-Born (far away)|Herika (busy)|",
                'sess' => 'pending',
                'audios' => null,
                'topic' => 'debug|spatial:test',
                'localts' => time()
            )
        );
        $testDb->close();

        $encodedData = base64_encode("narrator_inputtext|100|200|Prisoner:Who am I?");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");

        $testDb = new sql();
        $rows = $testDb->fetchAll("SELECT * FROM eventlog WHERE type='narrator_inputtext' ORDER BY rowid DESC LIMIT 1;");
        $testDb->close();

        $this->assertSame("Prisoner:Who am I?", $rows[0]["data"]);
        $this->assertSame("|Hulda|Jon Battle-Born|Herika|Prisoner|The Narrator|", $rows[0]["people"]);
    }

    public function testComm_WhenNarratorInputTextHasSpatialSpeechContextAndHideFromContextEnabled_InputEventShouldStayScopedToPlayerAndNarrator(): void
    {
        require("conf.php");
        $GLOBALS["HIDE_NARRATOR_DIALOGUE"] = true;

        $testDb = new sql();
        $testDb->insert(
            'eventlog',
            array(
                'ts' => "99",
                'gamets' => "199",
                'type' => "infonpc_close",
                'data' => "beings in range:Hulda/Jon Battle-Born/Herika/Prisoner",
                'sess' => 'pending',
                'localts' => 0,
                'people'=> "|Hulda|Jon Battle-Born|Herika|Prisoner|",
                'location'=> "",
                'party'=> "[]"
            )
        );
        $testDb->insert(
            'speech',
            array(
                'ts' => "100",
                'gamets' => "200",
                'listener' => "The Narrator",
                'speaker' => "Prisoner",
                'speech' => "Who am I?",
                'location' => "",
                'companions' => "|Hulda|Jon Battle-Born|Herika|",
                'sess' => 'pending',
                'audios' => null,
                'topic' => 'debug|spatial:test',
                'localts' => time()
            )
        );
        $testDb->close();

        $encodedData = base64_encode("narrator_inputtext|100|200|Prisoner:Who am I?");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");

        $testDb = new sql();
        $rows = $testDb->fetchAll("SELECT * FROM eventlog WHERE type='narrator_inputtext' ORDER BY rowid DESC LIMIT 1;");
        $testDb->close();

        $this->assertSame("Prisoner:Who am I?", $rows[0]["data"]);
        $this->assertSame("|Prisoner|The Narrator|", $rows[0]["people"]);
    }

    public function testFilterHistoricContextForNarratorVisibility_WhenHideDisabled_KeepsNarratorSpeechVisible(): void
    {
        require("conf.php");
        require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."chat_helper_functions.php");

        $GLOBALS["HIDE_NARRATOR_DIALOGUE"] = false;
        $contextDataHistoric = [
            ["content" => "Prisoner:Who are you? (Talking to The Narrator)"],
            ["content" => "The Narrator: A torch flickers in the tunnel. (talking to Prisoner)"],
            ["content" => "Lydia: I heard that."]
        ];

        $filtered = filterHistoricContextForNarratorVisibility($contextDataHistoric, "Lydia");

        $this->assertSame(
            [
                "The Narrator: A torch flickers in the tunnel. (talking to Prisoner)",
                "Lydia: I heard that."
            ],
            array_values(array_map(static function ($entry) {
                return $entry["content"];
            }, $filtered))
        );
    }

    public function testFilterHistoricContextForNarratorVisibility_WhenHideEnabled_KeepsOnlyNarratorContextMarkers(): void
    {
        require("conf.php");
        require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."chat_helper_functions.php");

        $GLOBALS["HIDE_NARRATOR_DIALOGUE"] = true;
        $contextDataHistoric = [
            ["content" => "The Narrator: The torchlight reveals a blood trail. (talking to Prisoner)"],
            ["content" => "The Narrator: (A cold wind sweeps through the ruin.)"],
            ["content" => "Lydia: This place feels cursed."]
        ];

        $filtered = filterHistoricContextForNarratorVisibility($contextDataHistoric, "Lydia");

        $this->assertSame(
            [
                "The Narrator: (A cold wind sweeps through the ruin.)",
                "Lydia: This place feels cursed."
            ],
            array_values(array_map(static function ($entry) {
                return $entry["content"];
            }, $filtered))
        );
    }

    public function testComm_Init_ShouldPurgeNewEvents(): void
    {
        // default test config
        require("conf.php");

        // add chat history
        $testDb = new sql();
        $testDb->insert(
            'eventlog',
            array(
                'ts' => "100",
                'gamets' => "100",
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
                'ts' => "200",
                'gamets' => "200",
                'type' => "chat",
                'data' => "Lydia: Very well my Thane, I'll take a look around. (talking to Prisoner)",
                'sess' => 'pending',
                'localts' => 2,
                'people'=> "|Lydia|",
                'location'=> "",
                'party'=> "[]"
            )
        );

        // comm.php?data=init|150|150| (base64 encoded)
        $encodedData = base64_encode("init|150|150|");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");

        // confirm events after the 150 timestamp were purged
        $rows=$testDb->fetchAll("SELECT * FROM eventlog ORDER BY rowid ASC;");
        $testDb->close();
        $this->assertSame(3, sizeof($rows));
        $this->assertSame("Prisoner:Make sure there are no more enemies nearby. (Talking to Lydia)", $rows[0]["data"]);
        $this->assertSame("user_input", $rows[1]["type"]);
        $this->assertSame("init", $rows[1]["data"]);
        $this->assertSame("init", $rows[2]["type"]);
    }

    public function testComm_Init_ShouldNotPurgeEventsWhenGametsIs10000000(): void
    {
        // default test config
        require("conf.php");

        // add chat history
        $testDb = new sql();
        $testDb->insert(
            'eventlog',
            array(
                'ts' => "100",
                'gamets' => "100",
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
                'ts' => "200",
                'gamets' => "200",
                'type' => "chat",
                'data' => "Lydia: Very well my Thane, I'll take a look around. (talking to Prisoner)",
                'sess' => 'pending',
                'localts' => 2,
                'people'=> "|Lydia|",
                'location'=> "",
                'party'=> "[]"
            )
        );

        // comm.php?data=init|150|10000000| (base64 encoded)
        $encodedData = base64_encode("init|150|10000000|");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");

        // confirm events were not purged
        $rows=$testDb->fetchAll("SELECT * FROM eventlog ORDER BY rowid ASC;");
        $testDb->close();
        $this->assertSame(3, sizeof($rows));
        $this->assertSame("Prisoner:Make sure there are no more enemies nearby. (Talking to Lydia)", $rows[0]["data"]);
        $this->assertSame("Lydia: Very well my Thane, I'll take a look around. (talking to Prisoner)", $rows[1]["data"]);
        $this->assertSame("user_input", $rows[2]["type"]);
        $this->assertSame("init", $rows[2]["data"]);
    }

    public function testRechatPeopleSourceOfTruth_ShouldUseLatestMatchingPeopleScope(): void
    {
        // default test config
        require("conf.php");
        require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."chat_helper_functions.php");

        $testDb = new sql();
        $GLOBALS["db"] = $testDb;
        $now = time();

        $testDb->insert(
            'eventlog',
            array(
                'ts' => "100",
                'gamets' => "100",
                'type' => "chat",
                'data' => "Lisette: Older broad line. (talking to RANGROO)",
                'sess' => 'pending',
                'localts' => $now - 20,
                'people'=> "|Lisette|RANGROO|Pantea Ateia|Belrand|",
                'location'=> "",
                'party'=> "[]",
                'delivery_state' => 'spoken'
            )
        );
        $testDb->insert(
            'eventlog',
            array(
                'ts' => "200",
                'gamets' => "200",
                'type' => "chat",
                'data' => "Lisette: Latest tight line. (talking to RANGROO)",
                'sess' => 'pending',
                'localts' => $now - 10,
                'people'=> "|Lisette|RANGROO|",
                'location'=> "",
                'party'=> "[]",
                'delivery_state' => 'spoken'
            )
        );

        $people = lookupConversationPeopleSourceOfTruth("Lisette", "RANGROO", 300);
        $testDb->close();
        unset($GLOBALS["db"]);

        $this->assertSame("|Lisette|RANGROO|", $people);
    }

    public function testRechatTargetSelection_ShouldSkipSleepingBystanderButAllowDirectTarget(): void
    {
        require("conf.php");
        require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."chat_helper_functions.php");
        require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."core".DIRECTORY_SEPARATOR."npc_master.class.php");

        $testDb = new sql();
        $GLOBALS["db"] = $testDb;
        $previousPlayerName = $GLOBALS["PLAYER_NAME"] ?? null;
        $previousRechatMode = $GLOBALS["RECHAT_MODE"] ?? null;
        $GLOBALS["PLAYER_NAME"] = "Varek";
        $GLOBALS["RECHAT_MODE"] = "group";

        $npcMaster = new NpcMaster();
        $npcMaster->create(['npc_name' => 'Jaryra']);
        $npcMaster->create(['npc_name' => 'Karrie']);

        $now = time();
        $testDb->insert(
            'eventlog',
            array(
                'ts' => "100",
                'gamets' => "100",
                'type' => "chat",
                'data' => "Jaryra: Though faces tend to blur together. (talking to Varek)",
                'sess' => 'pending',
                'localts' => $now - 2,
                'people' => "|Jaryra|Varek|Karrie|",
                'location' => "",
                'party' => "[]",
                'delivery_state' => 'spoken'
            )
        );
        $testDb->insert(
            'eventlog',
            array(
                'ts' => "200",
                'gamets' => "200",
                'type' => "infonpc",
                'data' => "(beings in range:Jaryra,Karrie (sleeping),Catarina (busy),Ralof (unconscious),)",
                'sess' => 'pending',
                'localts' => $now,
                'people' => "|Jaryra|Karrie (sleeping)|",
                'location' => "",
                'party' => "[]"
            )
        );

        $stateMap = chimLatestRechatActorStateMap();
        $this->assertSame("sleeping", $stateMap["karrie"]);
        $this->assertSame("busy", chimRechatActorStateBlockReason("Catarina", $stateMap, true));
        $this->assertSame("unconscious", chimRechatActorStateBlockReason("Ralof", $stateMap, true));
        $this->assertSame("", chimRechatActorStateBlockReason("Karrie", $stateMap, true));

        $bystanderResult = chimResolveServerSideRechatTarget([
            'speaker' => 'Jaryra',
            'listener_hint' => 'Varek',
            'rechat_target_hint' => 'Varek',
            'origin_line' => 'Though faces tend to blur together.',
            'chain_id' => 'dump-scenario',
        ]);

        $testDb->insert(
            'eventlog',
            array(
                'ts' => "300",
                'gamets' => "300",
                'type' => "chat",
                'data' => "Jaryra: Karrie, are you awake? (talking to Karrie)",
                'sess' => 'pending',
                'localts' => $now,
                'people' => "|Jaryra|Karrie|",
                'location' => "",
                'party' => "[]",
                'delivery_state' => 'spoken'
            )
        );
        $directResult = chimResolveServerSideRechatTarget([
            'speaker' => 'Jaryra',
            'listener_hint' => 'Karrie',
            'rechat_target_hint' => 'Karrie',
            'origin_line' => 'Karrie, are you awake?',
            'chain_id' => 'direct-sleeping-target',
        ]);

        $testDb->insert(
            'eventlog',
            array(
                'ts' => "400",
                'gamets' => "400",
                'type' => "infonpc",
                'data' => "(beings in range:Jaryra (sleeping),Karrie,)",
                'sess' => 'pending',
                'localts' => $now,
                'people' => "|Jaryra (sleeping)|Karrie|",
                'location' => "",
                'party' => "[]"
            )
        );
        $sleepingSpeakerResult = chimResolveServerSideRechatTarget([
            'speaker' => 'Jaryra',
            'listener_hint' => 'Karrie',
            'rechat_target_hint' => 'Karrie',
            'origin_line' => 'Karrie, are you awake?',
            'chain_id' => 'sleeping-speaker',
        ]);

        $testDb->close();
        unset($GLOBALS["db"]);
        if ($previousPlayerName === null) {
            unset($GLOBALS["PLAYER_NAME"]);
        } else {
            $GLOBALS["PLAYER_NAME"] = $previousPlayerName;
        }
        if ($previousRechatMode === null) {
            unset($GLOBALS["RECHAT_MODE"]);
        } else {
            $GLOBALS["RECHAT_MODE"] = $previousRechatMode;
        }

        $this->assertSame(["Jaryra", "Varek", "Karrie"], $bystanderResult["audience"]);
        $this->assertSame("", $bystanderResult["selected"]);
        $this->assertSame("Karrie", $directResult["selected"]);
        $this->assertSame("", $sleepingSpeakerResult["selected"]);
    }

    public function testComm_WhenLinesAreNotJapanese_PhoneticTextShouldBeNotSentToScriptQueue(): void
    {
        // default test config
        require("conf.php");
        $GLOBALS["HERIKA_NAME"] = "Lydia";
        $GLOBALS["HERIKA_PERS"] = "Roleplay as Lydia.";

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->willReturnCallback(function($url, $context) {
            $response = 'data: {"choices":[{"delta":{"content": "{\"character\": \"The Narrator\", \"listener\": \"Prisoner\", \"message\": \"Of course I do.\", \"mood\": \"default\", \"action\": \"Talk\", \"target\": \"Prisoner\"}"}}]}';
            $resourceMock = fopen('php://temp', 'r+');
            fwrite($resourceMock, $response);
            rewind($resourceMock);
            return $resourceMock;
        });

        // comm.php?data=inputtext|100|200|Do you speak Japanese? (base64 encoded)
        $encodedData = base64_encode("inputtext|100|200|Do you speak Japanese?");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");

        $this->assertMatchesRegularExpression('/Lydia\|ScriptQueue\|Of course I do\.\/\/Prisoner\/(IdleDialogueExpressiveStart)?\/\/1(?:\.0)?\/[^\/\r\n]+\r\n/', $GLOBALS["DEBUG_DATA"]["OUTPUT_LOG"]);
    }

    public function testComm_WhenLinesAreJapanese_PhoneticTextShouldBeSentToScriptQueue(): void
    {
        // default test config
        require("conf.php");
        $GLOBALS["HERIKA_NAME"] = "Lydia";
        $GLOBALS["HERIKA_PERS"] = "Roleplay as Lydia.";

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->willReturnCallback(function($url, $context) {
            $response = 'data: {"choices":[{"delta":{"content": "{\"character\": \"The Narrator\", \"listener\": \"Prisoner\", \"message\": \"当たり前でしょう。\", \"mood\": \"default\", \"action\": \"Talk\", \"target\": \"Prisoner\"}"}}]}';
            $resourceMock = fopen('php://temp', 'r+');
            fwrite($resourceMock, $response);
            rewind($resourceMock);
            return $resourceMock;
        });

        // comm.php?data=inputtext|100|200|日本語が分かりますか？ (base64 encoded)
        $encodedData = base64_encode("inputtext|100|200|日本語が分かりますか？");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");

        $this->assertMatchesRegularExpression('/Lydia|ScriptQueue|当たり前でしょう。\/\/Prisoner\/(IdleDialogueExpressiveStart)?\/atarimae deshou\.\r\n/', $GLOBALS["DEBUG_DATA"]["OUTPUT_LOG"]);
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
