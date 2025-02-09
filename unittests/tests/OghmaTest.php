<?php declare(strict_types=1);

require_once 'DatabaseTestCase.php';
require_once 'CallableMock.php';

// setUp and tearDown for the test database are in DatabaseTestCase.php
final class OghmaTest extends DatabaseTestCase
{
    public function testOghma_WhenNoKeywordMatch_ContextShouldNotContainLore(): void
    {
        // default test config
        require("conf.php");

        $this->insertPotionLore();
        
        $GLOBALS["mockMinimeTopic"] = function($text) {
            return '{"input_text": "'.$text.'", "generated_tags": "Commander Shepard", "elapsed_time": "0.05 seconds"}';
        };
        
        // input topic = 0
        // oghma topic = 0
        // location = 0
        // context = 0

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('https://openrouter.ai/api/v1/chat/completions'),
            $this->callback(function ($streamContext) {
                $options = stream_context_get_options($streamContext);
                $this->assertStringNotContainsString("#Lore related info", $options['http']['content']);
                return true;
            })
        )
        ->willReturnCallback(function($url, $context) {
            return $this->defaultConnectorResponse($url, $context);
        });

        // comm.php?data=inputtext|100|200|Tell me about Commander Shepard. (base64 encoded)
        $encodedData = base64_encode("inputtext|100|200|Tell me about Commander Shepard.");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");
    }

    public function testOghma_WhenInputTopicKeywordsFound_ContextShouldContainLore(): void
    {
        // default test config
        require("conf.php");

        $this->insertPotionLore();
        
        $GLOBALS["mockMinimeTopic"] = function($text) {
            return '{"input_text": "'.$text.'", "generated_tags": "Potion Seller", "elapsed_time": "0.05 seconds"}';
        };
        
        // input topic = 6.9
        // oghma topic = 0
        // location = 0
        // context = 0

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('https://openrouter.ai/api/v1/chat/completions'),
            $this->callback(function ($streamContext) {
                $expectedPrompt = ["role"=>"system", "content"=>"Let's roleplay in the Universe of Skyrim.\nI'm Prisoner".
                    "#Lore related info: The potion seller is an alchemist who brews and sells potions. However, he refuses to sell his strongest potions to any but the strongest beings. ".
                    "He has little respect for knights, because his potions can do anything that they can.\n".
                    "You are The Narrator in a Skyrim adventure. You will only talk to Prisoner. You refer to yourself as 'The Narrator'. Only Prisoner can hear you. ".
                    "Your goal is to comment on Prisoner's playthrough, and occasionally give hints. NO SPOILERS. Talk about quests and last events.\n\nDon't write narrations.\nNo active quests right now."];
                $this->expectPromptInContext($streamContext, $expectedPrompt);
                return true;
            })
        )
        ->willReturnCallback(function($url, $context) {
            return $this->defaultConnectorResponse($url, $context);
        });

        // comm.php?data=inputtext|100|200|Tell me about the potion seller. (base64 encoded)
        $encodedData = base64_encode("inputtext|100|200|Tell me about the potion seller.");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");
    }

    public function testOghma_WhenInsufficientOghmaTopicKeywordsFound_ContextShouldNotContainLore(): void
    {
        // default test config
        require("conf.php");

        $this->insertPotionLore();

        $testDb = new sql();
        $testDb->insert(
            'conf_opts',
            array(
                'id' => 'current_oghma_topic',
                'value' => 'Potion Seller'
            )
        );
        $testDb->close();
        
        // input topic = 0
        // oghma topic = 3.4
        // location = 0
        // context = 0

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('https://openrouter.ai/api/v1/chat/completions'),
            $this->callback(function ($streamContext) {
                $options = stream_context_get_options($streamContext);
                $this->assertStringNotContainsString("#Lore related info", $options['http']['content']);
                return true;
            })
        )
        ->willReturnCallback(function($url, $context) {
            return $this->defaultConnectorResponse($url, $context);
        });

        // comm.php?data=inputtext|100|200|Tell me about the potion seller. (base64 encoded)
        $encodedData = base64_encode("inputtext|100|200|I am the Dragonborn. Surely I must be worthy.");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");
    }

    public function testOghma_WhenOghmaTopicPlusContextKeywordsFound_ContextShouldContainLore(): void
    {
        // default test config
        require("conf.php");

        $this->insertPotionLore();
        $testDb = new sql();
        $testDb->insert(
            'conf_opts',
            array(
                'id' => 'current_oghma_topic',
                'value' => 'Potion Seller'
            )
        );
        $testDb->insert(
            'speech',
            array(
                'sess' => 'pending',
                'speaker' => 'Prisoner',
                'speech' => "Tell me about the potion seller.",
                'location' => "Riften ,Hold: The Rift",
                'listener' => "The Narrator",
                'localts' => 0,
                'gamets' => 0
            )
        );
        // Knights is the helpful keyword
        $testDb->insert(
            'speech',
            array(
                'sess' => 'pending',
                'speaker' => 'The Narrator',
                'speech' => "Ah, an enigmatic figure indeed. You may acquire potions from him - but only if he deems you worthy. Knights need not apply, for he respects them not.",
                'location' => "Riften ,Hold: The Rift",
                'listener' => "Prisoner",
                'localts' => 10,
                'gamets' => 10
            )
        );
        $testDb->close();
        
        // input topic = 0
        // oghma topic = 3.4
        // location = 0
        // context = 0.1

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('https://openrouter.ai/api/v1/chat/completions'),
            $this->callback(function ($streamContext) {
                $expectedPrompt = ["role"=>"system", "content"=>"Let's roleplay in the Universe of Skyrim.\nI'm Prisoner".
                    "#Lore related info: The potion seller is an alchemist who brews and sells potions. However, he refuses to sell his strongest potions to any but the strongest beings. ".
                    "He has little respect for knights, because his potions can do anything that they can.\n".
                    "You are The Narrator in a Skyrim adventure. You will only talk to Prisoner. You refer to yourself as 'The Narrator'. Only Prisoner can hear you. ".
                    "Your goal is to comment on Prisoner's playthrough, and occasionally give hints. NO SPOILERS. Talk about quests and last events.\n\nDon't write narrations.\nNo active quests right now."];
                $this->expectPromptInContext($streamContext, $expectedPrompt);
                return true;
            })
        )
        ->willReturnCallback(function($url, $context) {
            return $this->defaultConnectorResponse($url, $context);
        });

        // comm.php?data=inputtext|100|200|Tell me about the potion seller. (base64 encoded)
        $encodedData = base64_encode("inputtext|100|200|I am the Dragonborn. Surely I must be worthy.");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");
    }

    public function testOghma_WhenOghmaTopicPlusLocationKeywordsFound_ContextShouldContainLore(): void
    {
        // default test config
        require("conf.php");

        $this->insertPotionLore();
        $testDb = new sql();
        $testDb->insert(
            'conf_opts',
            array(
                'id' => 'current_oghma_topic',
                'value' => 'Potion Seller'
            )
        );
        // Hold regex breaks on spaces, so only the Potion keyword is used
        $testDb->insert(
            'eventlog',
            array(
                'ts' => "0",
                'gamets' => "0",
                'type' => "infoloc",
                'data' => "(Context location: Lair of the Potion Seller ,Hold: Potion Seller's Lair, Buildings to go:The Ragged Flagon,, Current Date in Skyrim World: Loredas, 2:20 PM, 18th of Frostfall, 4E 201)",
                'sess' => 'pending',
                'localts' => 0,
                'location'=> "(Context location: Lair of the Potion Seller ,Hold: Potion Seller's Lair, buildings to go:The Ragged Flagon,, Current Date in Skyrim World: Loredas, 2:18 PM, 18th of Frostfall, 4E 201)"
            )
        );
        $testDb->close();
        
        // input topic = 0
        // oghma topic = 3.4
        // location = 0.7
        // context = 0

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('https://openrouter.ai/api/v1/chat/completions'),
            $this->callback(function ($streamContext) {
                $expectedPrompt = ["role"=>"system", "content"=>"Let's roleplay in the Universe of Skyrim.\nI'm Prisoner".
                    "#Lore related info: The potion seller is an alchemist who brews and sells potions. However, he refuses to sell his strongest potions to any but the strongest beings. ".
                    "He has little respect for knights, because his potions can do anything that they can.\n".
                    "You are The Narrator in a Skyrim adventure. You will only talk to Prisoner. You refer to yourself as 'The Narrator'. Only Prisoner can hear you. ".
                    "Your goal is to comment on Prisoner's playthrough, and occasionally give hints. NO SPOILERS. Talk about quests and last events.\n\nDon't write narrations.\nNo active quests right now."];
                $this->expectPromptInContext($streamContext, $expectedPrompt);
                return true;
            })
        )
        ->willReturnCallback(function($url, $context) {
            return $this->defaultConnectorResponse($url, $context);
        });

        // comm.php?data=inputtext|100|200|Tell me about the potion seller. (base64 encoded)
        $encodedData = base64_encode("inputtext|100|200|I am the Dragonborn. Surely I must be worthy.");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
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
        $response = 'data: {"choices":[{"delta":{"content": "{\"character\": \"The Narrator\", \"listener\": \"Prisoner\", \"message\": \"Unit test message\", \"mood\": \"default\", \"action\": \"Talk\", \"target\": \"Prisoner\"}"}}]}';
        $resourceMock = fopen('php://temp', 'r+');
        fwrite($resourceMock, $response);
        rewind($resourceMock);
        return $resourceMock;
    }

    private function insertPotionLore() {
        $testDb = new sql();
        $testDb->insert(
            'oghma',
            array(
                'topic' => 'potion_seller',
                'topic_desc' => 'The potion seller is an alchemist who brews and sells potions. However, he refuses to sell his strongest potions to any but the strongest beings. He has little respect for knights, because his potions can do anything that they can.',
                'native_vector' => "'alchemist':8B 'anyth':39B 'be':27B 'brew':10B 'howev':14B 'knight':33B 'littl':30B 'potion':1A,4B,13B,21B,36B 'refus':16B 'respect':31B 'sell':12B,18B 'seller':2A,5B 'strongest':20B,26B"
            )
        );
        $testDb->close();
    }
}