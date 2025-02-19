<?php declare(strict_types=1);

require_once 'DatabaseTestCase.php';
require_once 'CallableMock.php';

// setUp and tearDown for the test database are in DatabaseTestCase.php
final class MemoryTest extends DatabaseTestCase
{
    public function testMemory_WhenDumbContextAndNoMemoryExists_ContextShouldNotContainMemory(): void
    {
        // default test config
        require("conf.php");
		$GLOBALS["MINIME_T5"] = false;

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('https://openrouter.ai/api/v1/chat/completions'),
            $this->callback(function ($streamContext) {
                $options = stream_context_get_options($streamContext);
				$this->assertStringNotContainsString("remembers this:", $options['http']['content']);
                return true;
            })
        )
        ->willReturnCallback(function($url, $context) {
            return $this->defaultConnectorResponse($url, $context);
        });

        // comm.php?data=inputtext|100|200|Hopefully we can buy some ale in town. (base64 encoded)
        $encodedData = base64_encode("inputtext|100|200|Hopefully we can buy some ale in town.");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");
    }

    public function testMemory_WhenDumbContextOffersNoMemory_ContextShouldNotContainMemory(): void
    {
        // default test config
        require("conf.php");
		$GLOBALS["MINIME_T5"] = false;

		// add summarized memory
		$this->insertPotionMemory();

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('https://openrouter.ai/api/v1/chat/completions'),
            $this->callback(function ($streamContext) {
                $options = stream_context_get_options($streamContext);
				$this->assertStringNotContainsString("remembers this:", $options['http']['content']);
                return true;
            })
        )
        ->willReturnCallback(function($url, $context) {
            return $this->defaultConnectorResponse($url, $context);
        });

        // comm.php?data=inputtext|100|200|Hopefully we can buy some ale in town. (base64 encoded)
        $encodedData = base64_encode("inputtext|100|200|Hopefully we can buy some ale in town.");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");
    }

    public function testMemory_WhenDumbContextOffersMemory_ContextShouldContainMemory(): void
    {
        // default test config
        require("conf.php");
		$GLOBALS["MINIME_T5"] = false;

		// add summarized memory
		$this->insertPotionMemory();

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('https://openrouter.ai/api/v1/chat/completions'),
            $this->callback(function ($streamContext) {
                $expectedPrompt = ["role"=>"user", "content"=>"##\nMEMORY\n: The Narrator remembers this: [0 days ago ....  #Summary: Prisoner attempted to buy strong potions from a merchant but was rudely turned away.\\n\\n]\n##\n"];
                $this->expectPromptInContext($streamContext, $expectedPrompt);
                return true;
            })
        )
        ->willReturnCallback(function($url, $context) {
            return $this->defaultConnectorResponse($url, $context);
        });

        // comm.php?data=inputtext|100|200|Hopefully we can buy some potions in town. (base64 encoded)
        $encodedData = base64_encode("inputtext|100|200|Hopefully we can buy some potions in town.");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");
    }

    public function testMemory_WhenMinimeT5AndNoMemoryExists_ContextShouldNotContainMemory(): void
    {
        // default test config
        require("conf.php");
		
        $GLOBALS["mockMinimeExtract"] = function($text) {
            return '{"is_memory_recall": "Yes", "generated_tags": "Ale|Town", "elapsed_time": "0.05 seconds"}';
        };

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('https://openrouter.ai/api/v1/chat/completions'),
            $this->callback(function ($streamContext) {
                $options = stream_context_get_options($streamContext);
				$this->assertStringNotContainsString("remembers this:", $options['http']['content']);
                return true;
            })
        )
        ->willReturnCallback(function($url, $context) {
            return $this->defaultConnectorResponse($url, $context);
        });

        // comm.php?data=inputtext|100|200|Hopefully we can buy some ale in town. (base64 encoded)
        $encodedData = base64_encode("inputtext|100|200|Hopefully we can buy some ale in town.");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");
    }

    public function testMemory_WhenMinimeT5OffersNoMemory_ContextShouldNotContainMemory(): void
    {
        // default test config
        require("conf.php");

		// add summarized memory
		$this->insertPotionMemory();
		
        $GLOBALS["mockMinimeExtract"] = function($text) {
            return '{"is_memory_recall": "Yes", "generated_tags": "Ale|Town", "elapsed_time": "0.05 seconds"}';
        };

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('https://openrouter.ai/api/v1/chat/completions'),
            $this->callback(function ($streamContext) {
                $options = stream_context_get_options($streamContext);
				$this->assertStringNotContainsString("remembers this:", $options['http']['content']);
                return true;
            })
        )
        ->willReturnCallback(function($url, $context) {
            return $this->defaultConnectorResponse($url, $context);
        });

        // comm.php?data=inputtext|100|200|Hopefully we can buy some ale in town. (base64 encoded)
        $encodedData = base64_encode("inputtext|100|200|Hopefully we can buy some ale in town.");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");
    }

    public function testMemory_WhenMinimeT5OffersMemory_ContextShouldContainMemory(): void
    {
        // default test config
        require("conf.php");

		// add summarized memory
		$this->insertPotionMemory();
		
        $GLOBALS["mockMinimeExtract"] = function($text) {
            return '{"is_memory_recall": "Yes", "generated_tags": "Potions|Town", "elapsed_time": "0.05 seconds"}';
        };

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('https://openrouter.ai/api/v1/chat/completions'),
            $this->callback(function ($streamContext) {
                $expectedPrompt = ["role"=>"user", "content"=>"##\nMEMORY\n: The Narrator remembers this: [0 days ago ....  #Summary: Prisoner attempted to buy strong potions from a merchant but was rudely turned away.\\n\\n]\n##\n"];
                $this->expectPromptInContext($streamContext, $expectedPrompt);
                return true;
            })
        )
        ->willReturnCallback(function($url, $context) {
            return $this->defaultConnectorResponse($url, $context);
        });

        // comm.php?data=inputtext|100|200|Hopefully we can buy some potions in town. (base64 encoded)
        $encodedData = base64_encode("inputtext|100|200|Hopefully we can buy some potions in town.");
        $_SERVER["QUERY_STRING"] = "data={$encodedData}";
        require(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."comm.php");
    }

    public function testMemory_WhenNotPlayerInput_ContextShouldNotContainMemory(): void
    {
        // default test config
        require("conf.php");

		// add summarized memory
		$this->insertPotionMemory();
		
        $GLOBALS["HERIKA_NAME"]="Unit Test";
        $GLOBALS["HERIKA_PERS"]="You are a Unit Test.";

		// should not be used, but if it is then generate tags
        $GLOBALS["mockMinimeExtract"] = function($text) {
            return '{"is_memory_recall": "Yes", "generated_tags": "Potions|Town", "elapsed_time": "0.05 seconds"}';
        };

        $GLOBALS["mockConnectorSend"]=$this->createMock(CallableMock::class);
        $GLOBALS["mockConnectorSend"]->expects($this->once())
        ->method('__invoke')
        ->with(
            $this->equalTo('https://openrouter.ai/api/v1/chat/completions'),
            $this->callback(function ($streamContext) {
                $options = stream_context_get_options($streamContext);
				$this->assertStringNotContainsString("remembers this:", $options['http']['content']);
                return true;
            })
        )
        ->willReturnCallback(function($url, $context) {
            $response = 'data: {"choices":[{"delta":{"content": "{\"character\": \"Unit Test\", \"listener\": \"Prisoner\", \"message\": \"You should have tried buying some weaker potions\", \"mood\": \"default\", \"action\": \"Talk\", \"target\": \"Prisoner\"}"}}]}';
			$resourceMock = fopen('php://temp', 'r+');
			fwrite($resourceMock, $response);
			rewind($resourceMock);
			return $resourceMock;
        });

        // comm.php?data=bored|100|200|Unit Test (base64 encoded)
        $encodedData = base64_encode("bored|100|200|Unit Test");
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

	private function insertPotionMemory() {
		$testDb = new sql();
		$testDb->insert(
			'memory_summary',
			array(
				'gamets_truncated' => 0,
				'n' => 0,
				'packed_message' => '(Context Location:Riften ,Hold: The Rift) Prisoner: Potion seller. I need your strongest potions.\n'.
					'(Context Location:Riften ,Hold: The Rift) Potion Seller: You can\'t handle my strongest potions, traveler.',
				'summary' => '#Summary: Prisoner attempted to buy strong potions from a merchant but was rudely turned away.\n\n'.
					'#Tags: #PotionSeller #Potions',
				'uid' => 0,
				'companions' => 'Unit Test,Potion Seller',
				'tags' => '#PotionSeller #Potions',
				'native_vec'=> "'potion':2A,9B,22B 'seller':1A,21B"
			)
		);
		$testDb->close();
	}
}