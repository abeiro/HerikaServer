<?php

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."tokenizer_helper_functions.php");

// Cached version of openrouterjson connector with Anthropic/OpenAI/Gemini cache support
// Based on CHIM 2.0 architecture with additional caching and response format features

class openrouterjsoncached
{
    public $primary_handler;
    public $name;

    // Core properties from base connector
    private $_functionName;
    private $_parameterBuff;
    private $_commandBuffer;
    private $_numOutputTokens;
    private $_dataSent;
    private $_fid;
    private $_buffer;
    private $_stopProc;
    public $_extractedbuffer;
    private $_rawbuffer;
    private $_forcedClose=false;
    private $_is_nanogpt_com;
    private $_is_mistral_ai;
    private $_is_streaming;
    private $_is_reasoning;
    private $_is_openai;
    private $_model="";
    private $_fallback_models;
    private $_providers_sort;
    private $_provider_quantizations;
    private $_providers2ignore;
    private $_provider_max_price;
    private $_url;
    private $_websearch=false;
    private $_websearch_text="";
    private $_websearch_index=0;
    private $_webbackup_func=false;
    private $_remove_cot;
    private $_cot_tag_base;
    private $_output_buffer;
    private $_timeout;
    private $_is_grok;
    private $_lastStreamedObject;

    // Caching-specific properties
    private $_provider_caching;
    private $_responseFormat;
    private $_includeMood;
    private $_includeActions;
    private $_includeTarget;
    private $_includeListener;
    private $_defaultTarget;
    private $_simpleFormatParsed;
    private $_usedPrefill;
    private $_prefillContent;
    public $_jsonResponsesEncoded = array();

    public function __construct()
    {
        $this->name="openrouterjsoncached";
        $this->_commandBuffer=[];
        $this->_stopProc=false;
        $this->_extractedbuffer="";
        $this->_buffer="";
        $this->_forcedClose=false;
        $this->_is_nanogpt_com=false;
        $this->_is_mistral_ai=false;
        $this->_model="";
        $this->_fallback_models=null;
        $this->_providers_sort="";
        $this->_provider_quantizations=null;
        $this->_providers2ignore=null;
        $this->_provider_max_price=null;
        $this->_url="";
        $this->_is_streaming=true;
        $this->_is_reasoning=false;
        $this->_remove_cot=true;
        $this->_cot_tag_base="think";
        $this->_output_buffer="";
        $this->_timeout=30;
        $this->_is_grok=false;
        $this->_is_openai=false;
        $this->_websearch=false;
        $this->_websearch_text="";
        $this->_websearch_index=0;
        $this->_webbackup_func=false;

        // Initialize caching properties
        $this->_provider_caching = 'Anthropic';
        $this->_responseFormat = 'json';
        $this->_includeMood = true;
        $this->_includeActions = true;
        $this->_includeTarget = true;
        $this->_includeListener = true;
        $this->_defaultTarget = '';
        $this->_simpleFormatParsed = false;
        $this->_usedPrefill = false;
        $this->_prefillContent = '';
        $this->_jsonResponsesEncoded = array();

        require_once(__DIR__."/__jpd.php");
        require_once(__DIR__."/openrouterjsoncached_helpers.php");
    }

    // Utility methods
    private function isWebSearchInMessage($s_msg="") {
        $b_res = false;
        if (strlen($s_msg) > 7) {
            $i_pos = stripos($s_msg, "Skyrim search");
            if ($i_pos === false)
                $i_pos = stripos($s_msg, "Search Skyrim");
            if ($i_pos === false)
                $i_pos = stripos($s_msg, "Find knowledge in Skyrim");
            if ($i_pos === false)
                $i_pos = stripos($s_msg, "Search Elder Scrolls");
            if ($i_pos === false)
                $i_pos = stripos($s_msg, "Find knowledge in Elder Scrolls");
            $b_res = (!($i_pos === false));
        }
        return $b_res;
    }

    private function isReasoningModel($s_model="") {
        $b_res = false;
        if (strlen($s_model) > 0) {
            $i_pos = stripos($s_model, "deepseek-r");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "qwq-32b");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "qwq-max");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "-thinking");
            if ($i_pos === false)
                $i_pos = stripos($s_model, ":thinking");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "-reasoning");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "grok-3-mini");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "sonar-deep-research");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "r1-1776");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "dolphin3.0-r1-mistral");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "aion-1.0");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "reka-flash-3");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "olympiccoder-");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "MAI-DS-R1");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "qwen3-235b-a22b");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "qwen3-30b-a3b");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "qwen3-32b");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "openai/o3");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "openai/o4");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "openai/o1");
            $b_res = (!($i_pos === false));
        }
        return $b_res;
    }

    private function isOpenAIModel($s_model="") {
        $b_res = false;
        if (strlen($s_model) > 0) {
            $i_pos = stripos($s_model, "openai/o1");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "openai/o3");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "openai/o4-mini");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "azure-o1");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "azure-o3");
            if ($i_pos === false) {
                if (($s_model == "o1") || ($s_model == "o1-mini") || ($s_model == "o1-preview") ||
                    ($s_model == "o3") || (strpos($s_model, "o3-mini") === 0) || (strpos($s_model, "o3-pro") === 0) ||
                    (strpos($s_model, "o4-mini") === 0)) {
                    $i_pos = 1;
                }
            }
            $b_res = (!($i_pos === false));
        }
        return $b_res;
    }

    public function getHttpStatusCode() {
        if (isset($GLOBALS['mockConnectorResponseMetaData'])) {
            $responseInfo = call_user_func($GLOBALS['mockConnectorResponseMetaData']);
        } else {
            $responseInfo = stream_get_meta_data($this->primary_handler);
        }

        $statusLine = $responseInfo['wrapper_data'][0];
        preg_match('/\d{3}/', $statusLine, $matches);
        return isset($matches[0]) ? intval($matches[0]) : null;
    }

    // Main open method - Part 1: Initialization and Configuration
    public function open($contextData, $customParms) {
        $start_time = microtime(true);
        require_once(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "functions" . DIRECTORY_SEPARATOR . "json_response.php");

        $herikaName = isset($GLOBALS["HERIKA_NAME"]) ? $GLOBALS["HERIKA_NAME"] : 'default_herika';
        $n_ctxsize = count($contextData);

        logMessage("[{$this->name}:{$herikaName}] OPEN START: Received contextData with {$n_ctxsize} elements.");

        // Load configuration
        $this->_url = isset($GLOBALS["CONNECTOR"][$this->name]["url"]) ? $GLOBALS["CONNECTOR"][$this->name]["url"] : '';
        if (empty($this->_url)) {
            logMessage("{$this->name} connector - missing url!");
            return null;
        }

        $MAX_TOKENS = ((isset($GLOBALS["CONNECTOR"][$this->name]["max_tokens"]) ? $GLOBALS["CONNECTOR"][$this->name]["max_tokens"] : 4096) + 0);
        $this->_model = (isset($GLOBALS["CONNECTOR"][$this->name]["model"])) ? $GLOBALS["CONNECTOR"][$this->name]["model"] : 'anthropic/claude-3-haiku-20240307';

        // Model can be overridden by custom params
        $this->_model = isset($customParms["model"]) ? $customParms["model"] : $this->_model;

        $max_dialogue_cache_size = ((isset($GLOBALS["CONNECTOR"][$this->name]["max_dialogue_cache_context_size"]) ? $GLOBALS["CONNECTOR"][$this->name]["max_dialogue_cache_context_size"] : $n_ctxsize * 4) + 0);
        $customInstruction = isset($GLOBALS["CONNECTOR"][$this->name]["custom_last_instruction"]) ? $GLOBALS["CONNECTOR"][$this->name]["custom_last_instruction"] : '';
        $lastCustomInstruction = isset($GLOBALS["CONNECTOR"][$this->name]["custom_last_user_instruction"]) ? $GLOBALS["CONNECTOR"][$this->name]["custom_last_user_instruction"] : '';

        $toggleThinking = isset($GLOBALS["CONNECTOR"][$this->name]["toggle_thinking"]) ? $GLOBALS["CONNECTOR"][$this->name]["toggle_thinking"] : false;
        $thinkingTokens = isset($GLOBALS["CONNECTOR"][$this->name]["thinking_tokens"]) ? $GLOBALS["CONNECTOR"][$this->name]["thinking_tokens"] : 1000;
        $effort_level = isset($GLOBALS["CONNECTOR"][$this->name]["effort_level"]) ? $GLOBALS["CONNECTOR"][$this->name]["effort_level"] : "low";

        // Cache provider configuration
        $this->_provider_caching = isset($GLOBALS["CONNECTOR"][$this->name]["provider_caching"]) ? $GLOBALS["CONNECTOR"][$this->name]["provider_caching"] : "Anthropic";
        logMessage("provider caching: {$this->_provider_caching}");

        $CONTEXTHISTORY = isset($GLOBALS['CONTEXT_HISTORY']) ? $GLOBALS['CONTEXT_HISTORY'] : 50;
        logMessage("CONTEXT HISTORY: $CONTEXTHISTORY");

        // New configuration options for response format and content control
        $dialogue_cache_uncached_count = isset($GLOBALS["CONNECTOR"][$this->name]["dialogue_cache_uncached_count"])
            ? (int)$GLOBALS["CONNECTOR"][$this->name]["dialogue_cache_uncached_count"]
            : 4;

        $this->_responseFormat = isset($GLOBALS["CONNECTOR"][$this->name]["response_format"])
            && in_array($GLOBALS["CONNECTOR"][$this->name]["response_format"], ['json', 'simple'])
            ? $GLOBALS["CONNECTOR"][$this->name]["response_format"]
            : 'json';

        $this->_includeActions = isset($GLOBALS["CONNECTOR"][$this->name]["include_actions_list"])
            ? (bool)$GLOBALS["CONNECTOR"][$this->name]["include_actions_list"]
            : true;

        $this->_includeMood = isset($GLOBALS["CONNECTOR"][$this->name]["include_mood_requirement"])
            ? (bool)$GLOBALS["CONNECTOR"][$this->name]["include_mood_requirement"]
            : true;

        $this->_includeTarget = isset($GLOBALS["CONNECTOR"][$this->name]["include_target_requirement"])
            ? (bool)$GLOBALS["CONNECTOR"][$this->name]["include_target_requirement"]
            : true;

        $this->_includeListener = isset($GLOBALS["CONNECTOR"][$this->name]["include_listener_requirement"])
            ? (bool)$GLOBALS["CONNECTOR"][$this->name]["include_listener_requirement"]
            : true;

        // Enforce dependency: target required if actions enabled
        if ($this->_includeActions) {
            $this->_includeTarget = true;
        }

        logMessage("Response Format Config: format={$this->_responseFormat}, actions={$this->_includeActions}, mood={$this->_includeMood}, target={$this->_includeTarget}, listener={$this->_includeListener}, uncached={$dialogue_cache_uncached_count}");

        // Continue to Part 2...
        return $this->_openPart2($contextData, $customParms, $herikaName, $MAX_TOKENS, $max_dialogue_cache_size,
                                  $customInstruction, $lastCustomInstruction, $toggleThinking, $thinkingTokens,
                                  $effort_level, $CONTEXTHISTORY, $dialogue_cache_uncached_count, $start_time);
    }

    // Part 2: System Prompt Processing with Caching
    private function _openPart2($contextData, $customParms, $herikaName, $MAX_TOKENS, $max_dialogue_cache_size,
                                 $customInstruction, $lastCustomInstruction, $toggleThinking, $thinkingTokens,
                                 $effort_level, $CONTEXTHISTORY, $dialogue_cache_uncached_count, $start_time) {

        $cacheSystemFile = "system_cache_json_{$herikaName}.tmp";
        $cacheCombinedDialogueFile = "combined_dialogue_cache_json_{$herikaName}.tmp";
        $cacheControlType = ["type" => "ephemeral", "ttl" => "1h"];

        // Build actions and response format instruction
        if (isset($GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]) && $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]) {
            $prefix = "{$GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]}";
        } else {
            $prefix = "";
        }

        if (isset($GLOBALS["HERIKA_SPEECHSTYLE"]) && !empty($GLOBALS["HERIKA_SPEECHSTYLE"])) {
            $speechReinforcement = "Use #SpeechStyle.";
        } else {
            $speechReinforcement = "";
        }

        $zonosTones = (isset($GLOBALS["TTSFUNCTION"]) && $GLOBALS["TTSFUNCTION"] == "zonos_gradio") ? " (Response tones are mandatory in the response)" : "";

        // Build actions list if enabled
        $availableActions = "";
        if ($this->_includeActions && isset($GLOBALS["COMMAND_PROMPT"])) {
            $availableActions = preg_replace('/\(available targets:[^\n]*/', '', $GLOBALS["COMMAND_PROMPT"]);
        }

        // Build response format instruction based on format type
        $formatInstruction = "";
        if ($this->_responseFormat === 'json') {
            $template = isset($GLOBALS["responseTemplate"]) ? $GLOBALS["responseTemplate"] : [];

            if (!$this->_includeMood && is_array($template) && isset($template['mood'])) {
                unset($template['mood']);
            }
            if (!$this->_includeActions && is_array($template) && isset($template['action'])) {
                unset($template['action']);
            }
            if (!$this->_includeTarget && is_array($template) && isset($template['target'])) {
                unset($template['target']);
            }
            if (!$this->_includeListener && is_array($template) && isset($template['listener'])) {
                unset($template['listener']);
            }

            $formatInstruction = "{$prefix} $speechReinforcement $customInstruction Use ONLY this JSON object to give your answer. Do not send any other characters outside of this JSON structure$zonosTones: " . json_encode($template);
        } else {
            $formatInstruction = buildSimpleFormatInstruction(
                $this->_includeMood,
                $this->_includeListener,
                $this->_includeActions,
                $this->_includeTarget,
                "{$prefix} $speechReinforcement $customInstruction"
            );
        }

        $actionsText = "";
        if (!empty($availableActions)) {
            $actionsText .= "\n" . $availableActions . "\n";
        }
        $actionsText .= $formatInstruction;

        $dynamicEnvironment = "";
        $systemEntries = [];

        // Process system prompts and extract dynamic sections
        foreach ($contextData as $n => $element) {
            if (isset($element["role"]) && $element["role"] == "system") {
                $systemContentString = '';
                if (is_string($element['content'])) {
                    $systemContentString = $element['content'];
                } elseif (is_array($element['content']) && isset($element['content'][0]['type']) &&
                          $element['content'][0]['type'] === 'text' && isset($element['content'][0]['text'])) {
                    $systemContentString = $element['content'][0]['text'];
                }

                $systemContentCurrent = trim($systemContentString);

                // Extract dynamic sections that change frequently
                $environmental = extract_and_remove_section($systemContentCurrent, 'Environmental Context');
                $additional = extract_and_remove_section($systemContentCurrent, 'Additional Information');
                $equipment = extract_any_subsection($systemContentCurrent, 'Equipment', true);
                $appearance = extract_any_subsection($systemContentCurrent, 'Physical Appearance', false);
                $cleanliness = extract_any_subsection($systemContentCurrent, 'Cleanliness', true);
                $additionalCharacter = extract_specific_section($systemContentCurrent, 'Additional Character Information');
                $combatStatus = extract_specific_section($systemContentCurrent, 'Combat Vitals');
                $arousal = extract_specific_section($systemContentCurrent, 'Arousal Status');

                $dynamicEnvironment = $environmental . "\n\n" . $additional . "\n\n" . $additionalCharacter . "\n\n" .
                                     $combatStatus . "\n\n" . $arousal . "\n\n" . $equipment . "\n\n" .
                                     $appearance . "\n\n" . $cleanliness;

                $finalSend = $systemContentCurrent . "\n" . $actionsText;

                $content = ['type' => 'text', 'text' => $finalSend];
                if ($this->_provider_caching !== "OpenAI") {
                    $content['cache_control'] = $cacheControlType;
                }
                $systemEntries[] = array("role" => "system", "content" => array($content));
            }
        }

        $finalMessagesToSend = writeArrayToFileWithCache($systemEntries, $cacheSystemFile);

        // Continue to Part 3...
        return $this->_openPart3($contextData, $customParms, $herikaName, $MAX_TOKENS, $max_dialogue_cache_size,
                                  $lastCustomInstruction, $toggleThinking, $thinkingTokens, $effort_level,
                                  $CONTEXTHISTORY, $dialogue_cache_uncached_count, $start_time,
                                  $finalMessagesToSend, $cacheCombinedDialogueFile, $cacheControlType, $dynamicEnvironment);
    }

    public function process() {
        // TODO: Implement
        return "";
    }

    public function close() {
        // TODO: Implement
        return "";
    }

    public function processActions() {
        // TODO: Implement
        return array();
    }

    public function isDone() {
        if ($this->_forcedClose)
            return true;
        return !$this->primary_handler || feof($this->primary_handler);
    }

    public function setDone() {
        $this->_forcedClose=true;
    }

    public function send($url, $context) {
        if (isset($GLOBALS['mockConnectorSend'])) {
            return call_user_func($GLOBALS['mockConnectorSend'], $url, $context);
        }
        return fopen($url, 'r', false, $context);
    }
}
