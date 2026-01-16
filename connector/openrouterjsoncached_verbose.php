<?php

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."tokenizer_helper_functions.php");

// Cached version of openrouterjson connector with Anthropic/OpenAI/Gemini cache support
// Based on CHIM 2.0 architecture with additional caching and response format features
// VERBOSE LOGGING VERSION - includes comprehensive debugging logs for testing phase

class openrouterjsoncached_verbose
{
    // ⚠️ IMPORTANT: Please update version number, date, and CHIM version after making changes
    const VERSION = 'OpenRouter Cache Connector v1.3.4 for CHIM 2.0.3 | 2026/01/16 (VERBOSE)';

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
    private $_simpleFormatMessageStart;
    private $_lastReturnedLength;
    private $_verboseLogging;
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
        $this->_simpleFormatMessageStart = 0;
        $this->_lastReturnedLength = 0;
        $this->_verboseLogging = true; // Default enabled for testing phase
        $this->_jsonResponsesEncoded = array();

        require_once(__DIR__."/__jpd.php");
        require_once(__DIR__."/openrouterjsoncached_helpers.php");

        // VERBOSE_LOGGING_START - Constructor initialization
        if ($this->_verboseLogging) {
            logMessage("[CACHE-VERBOSE] OpenRouter Cached Connector v" . self::VERSION . " (VERBOSE) initialized");
            logMessage("[CACHE-VERBOSE] Default provider_caching: {$this->_provider_caching}");
            logMessage("[CACHE-VERBOSE] Default response_format: {$this->_responseFormat}");
            logMessage("[CACHE-VERBOSE] Helper files loaded");
        }
        // VERBOSE_LOGGING_END
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
        // Detects OpenAI reasoning models that require special parameter handling
        // These models use max_completion_tokens and require parameter stripping
        $b_res = false;
        if (strlen($s_model) > 0) {
            // OpenRouter prefixed models
            $i_pos = stripos($s_model, "openai/o1");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "openai/o3");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "openai/o4");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "azure-o1");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "azure-o3");
            if ($i_pos === false)
                $i_pos = stripos($s_model, "azure-o4");

            // Direct model names (o1, o3, o4 series)
            if ($i_pos === false) {
                if (($s_model == "o1") || ($s_model == "o1-mini") || ($s_model == "o1-preview") ||
                    ($s_model == "o3") || (strpos($s_model, "o3-mini") === 0) || (strpos($s_model, "o3-pro") === 0) ||
                    ($s_model == "o4") || (strpos($s_model, "o4-mini") === 0)) {
                    $i_pos = 1;
                }
            }

            // GPT-5 series (but NOT gpt-5-chat)
            if ($i_pos === false) {
                if ((stripos($s_model, "gpt-5") !== false || stripos($s_model, "openai/gpt-5") !== false) &&
                    stripos($s_model, "gpt-5-chat") === false) {
                    // Matches: gpt-5, gpt-5-pro, gpt-5-codex, gpt-5-mini, gpt-5-nano, openai/gpt-5*
                    // But NOT: gpt-5-chat
                    $i_pos = 1;
                }
            }

            $b_res = (!($i_pos === false));
        }
        return $b_res;
    }

    private function isAlwaysReasoningModel($s_model="") {
        // Detects models that ALWAYS have reasoning enabled (cannot be disabled)
        // These models will always output reasoning tokens regardless of settings
        $b_res = false;
        if (strlen($s_model) > 0) {
            // OpenAI reasoning models (o1, o3, o4, gpt-5*)
            if ($this->isOpenAIModel($s_model)) {
                $b_res = true;
            }

            // DeepSeek R1 (older version, always reasons)
            if (!$b_res) {
                $i_pos = stripos($s_model, "deepseek-r1");
                if ($i_pos === false)
                    $i_pos = stripos($s_model, "r1-1776");
                $b_res = (!($i_pos === false));
            }
        }
        return $b_res;
    }

    public function getHttpStatusCode() {
        if (isset($GLOBALS['mockConnectorResponseMetaData'])) {
            $responseInfo = call_user_func($GLOBALS['mockConnectorResponseMetaData']);
        } else {
            if (!is_resource($this->primary_handler)) {
                logMessage("[{$this->name}] getHttpStatusCode: primary_handler is null or not a resource");
                return null;
            }
            $responseInfo = stream_get_meta_data($this->primary_handler);
        }

        if (!isset($responseInfo['wrapper_data'][0])) {
            return null;
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

        $MAX_TOKENS = intval(isset($GLOBALS["CONNECTOR"][$this->name]["max_tokens"]) ? $GLOBALS["CONNECTOR"][$this->name]["max_tokens"] : 4096);
        $this->_model = (isset($GLOBALS["CONNECTOR"][$this->name]["model"])) ? $GLOBALS["CONNECTOR"][$this->name]["model"] : 'anthropic/claude-3-haiku-20240307';

        // Model can be overridden by custom params
        $this->_model = isset($customParms["model"]) ? $customParms["model"] : $this->_model;

        $max_dialogue_cache_size = intval(isset($GLOBALS["CONNECTOR"][$this->name]["max_dialogue_cache_context_size"]) ? $GLOBALS["CONNECTOR"][$this->name]["max_dialogue_cache_context_size"] : $n_ctxsize * 4);
        $customInstruction = isset($GLOBALS["CONNECTOR"][$this->name]["custom_system_instruction"]) ? $GLOBALS["CONNECTOR"][$this->name]["custom_system_instruction"] : '';
        $lastCustomInstruction = isset($GLOBALS["CONNECTOR"][$this->name]["custom_last_instruction"]) ? $GLOBALS["CONNECTOR"][$this->name]["custom_last_instruction"] : '';

        $toggleThinking = isset($GLOBALS["CONNECTOR"][$this->name]["toggle_thinking"]) ? $GLOBALS["CONNECTOR"][$this->name]["toggle_thinking"] : false;
        $thinkingTokens = isset($GLOBALS["CONNECTOR"][$this->name]["thinking_tokens"]) ? $GLOBALS["CONNECTOR"][$this->name]["thinking_tokens"] : 1000;
        $effort_level = isset($GLOBALS["CONNECTOR"][$this->name]["effort_level"]) ? $GLOBALS["CONNECTOR"][$this->name]["effort_level"] : "low";

        // Cache provider configuration
        $this->_provider_caching = isset($GLOBALS["CONNECTOR"][$this->name]["provider_caching"]) ? $GLOBALS["CONNECTOR"][$this->name]["provider_caching"] : "Anthropic";
        logMessage("provider caching: {$this->_provider_caching}");

        // Load verbose logging setting from config
        $this->_verboseLogging = isset($GLOBALS["CONNECTOR"][$this->name]["verbose_logging"])
            ? (bool)$GLOBALS["CONNECTOR"][$this->name]["verbose_logging"]
            : true; // Default enabled for testing

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

        // VERBOSE_LOGGING_START - open() Part 1: Configuration loaded
        if ($this->_verboseLogging) {
            logMessage("[CACHE-VERBOSE] ===== OPEN() PART 1: CONFIGURATION =====");
            logMessage("[CACHE-VERBOSE] URL: {$this->_url}");
            logMessage("[CACHE-VERBOSE] Model: {$this->_model}");
            logMessage("[CACHE-VERBOSE] Max Tokens: {$MAX_TOKENS}");
            logMessage("[CACHE-VERBOSE] Provider Caching: {$this->_provider_caching}");
            logMessage("[CACHE-VERBOSE] Response Format: {$this->_responseFormat}");
            logMessage("[CACHE-VERBOSE] Include Actions: " . ($this->_includeActions ? 'YES' : 'NO'));
            logMessage("[CACHE-VERBOSE] Include Mood: " . ($this->_includeMood ? 'YES' : 'NO'));
            logMessage("[CACHE-VERBOSE] Include Target: " . ($this->_includeTarget ? 'YES' : 'NO'));
            logMessage("[CACHE-VERBOSE] Include Listener: " . ($this->_includeListener ? 'YES' : 'NO'));
            logMessage("[CACHE-VERBOSE] Max Dialogue Cache Size: {$max_dialogue_cache_size}");
            logMessage("[CACHE-VERBOSE] Dialogue Uncached Count: {$dialogue_cache_uncached_count}");
            logMessage("[CACHE-VERBOSE] Context History Size: {$CONTEXTHISTORY}");
            logMessage("[CACHE-VERBOSE] Toggle Thinking: " . ($toggleThinking ? 'YES' : 'NO'));
            if ($toggleThinking) {
                logMessage("[CACHE-VERBOSE] Thinking Tokens: {$thinkingTokens}");
                logMessage("[CACHE-VERBOSE] Effort Level: {$effort_level}");
            }
            if (!empty($customInstruction)) {
                logMessage("[CACHE-VERBOSE] Custom Instruction: " . substr($customInstruction, 0, 100) . "...");
            }
            if (!empty($lastCustomInstruction)) {
                logMessage("[CACHE-VERBOSE] Last Custom Instruction: " . substr($lastCustomInstruction, 0, 100) . "...");
            }
            logMessage("[CACHE-VERBOSE] Context Data Elements: {$n_ctxsize}");
            logMessage("[CACHE-VERBOSE] Verbose Logging: ENABLED");
        }
        // VERBOSE_LOGGING_END

        // Continue to Part 2...
        return $this->_openPart2($contextData, $customParms, $herikaName, $MAX_TOKENS, $max_dialogue_cache_size,
                                  $customInstruction, $lastCustomInstruction, $toggleThinking, $thinkingTokens,
                                  $effort_level, $CONTEXTHISTORY, $dialogue_cache_uncached_count, $start_time);
    }

    // Part 2: System Prompt Processing with Caching
    private function _openPart2($contextData, $customParms, $herikaName, $MAX_TOKENS, $max_dialogue_cache_size,
                                 $customInstruction, $lastCustomInstruction, $toggleThinking, $thinkingTokens,
                                 $effort_level, $CONTEXTHISTORY, $dialogue_cache_uncached_count, $start_time) {

        // VERBOSE_LOGGING_START - _openPart2: Entry
        if ($this->_verboseLogging) {
            logMessage("[CACHE-VERBOSE] ===== _OPENPART2: SYSTEM PROMPT PROCESSING =====");
            logMessage("[CACHE-VERBOSE] Herika Name: {$herikaName}");
        }
        // VERBOSE_LOGGING_END

        // BUG#2 FIX: Include response format in cache filename so different formats use different cache files
        $cacheSystemFile = "system_cache_{$this->_responseFormat}_{$herikaName}.tmp";
        $cacheCombinedDialogueFile = "combined_dialogue_cache_{$this->_responseFormat}_{$herikaName}.tmp";
        $cacheControlType = ["type" => "ephemeral", "ttl" => "1h"];

        // VERBOSE_LOGGING_START - Cache files
        if ($this->_verboseLogging) {
            logMessage("[CACHE-VERBOSE] System cache file: {$cacheSystemFile}");
            logMessage("[CACHE-VERBOSE] Dialogue cache file: {$cacheCombinedDialogueFile}");
            logMessage("[CACHE-VERBOSE] Cache control type: ephemeral, TTL: 1h");
        }
        // VERBOSE_LOGGING_END

        // Build actions and response format instruction
        if (isset($GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]) && $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]) {
            $prefix = isset($GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]) ? "{$GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]}" : "";
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

            // VERBOSE_LOGGING_START - JSON format
            if ($this->_verboseLogging) {
                logMessage("[CACHE-VERBOSE] Response format: JSON");
                logMessage("[CACHE-VERBOSE] JSON template fields: " . implode(', ', array_keys($template)));
                logMessage("[CACHE-VERBOSE] Format instruction length: " . strlen($formatInstruction));
            }
            // VERBOSE_LOGGING_END
        } else {
            $formatInstruction = buildSimpleFormatInstruction(
                $this->_includeMood,
                $this->_includeListener,
                $this->_includeActions,
                $this->_includeTarget,
                "{$prefix} $speechReinforcement $customInstruction"
            );

            // VERBOSE_LOGGING_START - Simple format
            if ($this->_verboseLogging) {
                logMessage("[CACHE-VERBOSE] Response format: SIMPLE");
                logMessage("[CACHE-VERBOSE] Simple format instruction length: " . strlen($formatInstruction));
                logMessage("[CACHE-VERBOSE] Simple format preview: " . substr($formatInstruction, 0, 150) . "...");
            }
            // VERBOSE_LOGGING_END
        }

        $actionsText = "";
        if (!empty($availableActions)) {
            $actionsText .= "\n" . $availableActions . "\n";
        }
        $actionsText .= $formatInstruction;

        // VERBOSE_LOGGING_START - Actions text
        if ($this->_verboseLogging) {
            logMessage("[CACHE-VERBOSE] Actions included: " . (!empty($availableActions) ? 'YES' : 'NO'));
            logMessage("[CACHE-VERBOSE] Total actions+format length: " . strlen($actionsText));
        }
        // VERBOSE_LOGGING_END

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

                // VERBOSE_LOGGING_START - Dynamic content extraction
                if ($this->_verboseLogging) {
                    logMessage("[CACHE-VERBOSE] Dynamic content extracted:");
                    logMessage("[CACHE-VERBOSE]   Environmental: " . (strlen($environmental) > 0 ? strlen($environmental) . " chars" : "NONE"));
                    logMessage("[CACHE-VERBOSE]   Additional Info: " . (strlen($additional) > 0 ? strlen($additional) . " chars" : "NONE"));
                    logMessage("[CACHE-VERBOSE]   Equipment: " . (strlen($equipment) > 0 ? strlen($equipment) . " chars" : "NONE"));
                    logMessage("[CACHE-VERBOSE]   Appearance: " . (strlen($appearance) > 0 ? strlen($appearance) . " chars" : "NONE"));
                    logMessage("[CACHE-VERBOSE]   Combat Status: " . (strlen($combatStatus) > 0 ? strlen($combatStatus) . " chars" : "NONE"));
                    logMessage("[CACHE-VERBOSE]   Total dynamic environment length: " . strlen($dynamicEnvironment) . " chars");
                }
                // VERBOSE_LOGGING_END

                $finalSend = $systemContentCurrent . "\n" . $actionsText;

                $content = ['type' => 'text', 'text' => $finalSend];
                if ($this->_provider_caching !== "OpenAI") {
                    $content['cache_control'] = $cacheControlType;
                }
                $systemEntries[] = array("role" => "system", "content" => array($content));

                // VERBOSE_LOGGING_START - System entry
                if ($this->_verboseLogging) {
                    logMessage("[CACHE-VERBOSE] System entry created:");
                    logMessage("[CACHE-VERBOSE]   Static system content length: " . strlen($systemContentCurrent) . " chars");
                    logMessage("[CACHE-VERBOSE]   Total with actions: " . strlen($finalSend) . " chars");
                    logMessage("[CACHE-VERBOSE]   Cache control applied: " . ($this->_provider_caching !== "OpenAI" ? 'YES' : 'NO (OpenAI native)'));
                }
                // VERBOSE_LOGGING_END
            }
        }

        // VERBOSE_LOGGING_START - System cache write
        if ($this->_verboseLogging) {
            logMessage("[CACHE-VERBOSE] Writing " . count($systemEntries) . " system entries to cache file: {$cacheSystemFile}");
        }
        // VERBOSE_LOGGING_END

        $finalMessagesToSend = writeArrayToFileWithCache($systemEntries, $cacheSystemFile);

        // VERBOSE_LOGGING_START - System cache result
        if ($this->_verboseLogging) {
            logMessage("[CACHE-VERBOSE] System cache write complete. Messages to send: " . count($finalMessagesToSend));
        }
        // VERBOSE_LOGGING_END

        // Continue to Part 3...
        return $this->_openPart3($contextData, $customParms, $herikaName, $MAX_TOKENS, $max_dialogue_cache_size,
                                  $lastCustomInstruction, $toggleThinking, $thinkingTokens, $effort_level,
                                  $CONTEXTHISTORY, $dialogue_cache_uncached_count, $start_time,
                                  $finalMessagesToSend, $cacheCombinedDialogueFile, $cacheControlType, $dynamicEnvironment);
    }

    // Part 3: Dialogue History Caching and Cache Control Placement
    private function _openPart3($contextData, $customParms, $herikaName, $MAX_TOKENS, $max_dialogue_cache_size,
                                 $lastCustomInstruction, $toggleThinking, $thinkingTokens, $effort_level,
                                 $CONTEXTHISTORY, $dialogue_cache_uncached_count, $start_time,
                                 $finalMessagesToSend, $cacheCombinedDialogueFile, $cacheControlType, $dynamicEnvironment) {

        // VERBOSE_LOGGING_START - _openPart3: Entry
        if ($this->_verboseLogging) {
            logMessage("[CACHE-VERBOSE] ===== _OPENPART3: DIALOGUE HISTORY & CACHE CONTROL =====");
            logMessage("[CACHE-VERBOSE] Input context messages: " . count($contextData));
            logMessage("[CACHE-VERBOSE] Max dialogue cache size: {$max_dialogue_cache_size}");
            logMessage("[CACHE-VERBOSE] Dialogue uncached count: {$dialogue_cache_uncached_count}");
            logMessage("[CACHE-VERBOSE] Has custom instruction: " . (!empty($lastCustomInstruction) ? 'YES' : 'NO'));
        }
        // VERBOSE_LOGGING_END

        // Process dialogue history
        $contentTextToSend = [];
        foreach ($contextData as $n => $element) {
            if (!isset($element))
                continue;

            if (isset($element["role"]) && $element["role"] != "system") {
                $contentString = '';
                if (is_string($element['content'])) {
                    $contentString = $element['content'];
                } elseif (is_array($element['content']) && isset($element['content'][0]['type']) &&
                          $element['content'][0]['type'] === 'text' && isset($element['content'][0]['text'])) {
                    $contentString = $element['content'][0]['text'];
                }

                if (containsOnlySymbols($contentString)) {
                    continue;
                }

                if (!empty(trim($contentString))) {
                    $contentTextToSend[] = array('type' => 'text', 'text' => "$contentString");
                }
            }
        }

        // VERBOSE_LOGGING_START - _openPart3: Dialogue processing complete
        if ($this->_verboseLogging) {
            logMessage("[CACHE-VERBOSE] Extracted dialogue entries: " . count($contentTextToSend));
        }
        // VERBOSE_LOGGING_END

        // Remove first few items if list is large (optimization)
        if (count($contentTextToSend) > 4) {
            $contentTextToSend = array_slice($contentTextToSend, 4);

            // VERBOSE_LOGGING_START - _openPart3: List optimization
            if ($this->_verboseLogging) {
                logMessage("[CACHE-VERBOSE] List optimized by removing first 4 items, new count: " . count($contentTextToSend));
            }
            // VERBOSE_LOGGING_END
        }

        // Remove instruction to add back later
        $instruction = array_pop($contentTextToSend);

        // VERBOSE_LOGGING_START - _openPart3: Instruction extraction
        if ($this->_verboseLogging) {
            $instrPreview = is_array($instruction) && isset($instruction['text'])
                ? substr($instruction['text'], 0, 80)
                : 'N/A';
            logMessage("[CACHE-VERBOSE] Extracted instruction for later re-insertion (preview): " . $instrPreview . "...");
        }
        // VERBOSE_LOGGING_END

        // Manage cached event list
        $completeEventList = manageCharacterEventList($contentTextToSend, $cacheCombinedDialogueFile, $max_dialogue_cache_size);
        logMessage("New elements added to cache: {$completeEventList['new_count']}");

        // VERBOSE_LOGGING_START - _openPart3: Cache management result
        if ($this->_verboseLogging) {
            logMessage("[CACHE-VERBOSE] Cache file: {$cacheCombinedDialogueFile}");
            logMessage("[CACHE-VERBOSE] New elements added to cache: {$completeEventList['new_count']}");
            logMessage("[CACHE-VERBOSE] Total cached event list size: " . count($completeEventList['updated_list']));
        }
        // VERBOSE_LOGGING_END

        $completeEventList = $completeEventList['updated_list'];

        // Add custom instructions if present
        $addToIndex = 0;
        if (!empty($lastCustomInstruction)) {
            $addToIndex = 1;
            $completeEventList[] = ['type' => 'text', 'text' => $lastCustomInstruction];

            // VERBOSE_LOGGING_START - _openPart3: Custom instruction added
            if ($this->_verboseLogging) {
                $customPreview = substr($lastCustomInstruction, 0, 80);
                logMessage("[CACHE-VERBOSE] Custom instruction added (length: " . strlen($lastCustomInstruction) . " chars, preview): " . $customPreview . "...");
            }
            // VERBOSE_LOGGING_END
        }

        $completeEventList[] = $instruction;

        // VERBOSE_LOGGING_START - _openPart3: Instruction re-added
        if ($this->_verboseLogging) {
            logMessage("[CACHE-VERBOSE] Instruction re-added to end of list");
            logMessage("[CACHE-VERBOSE] Complete event list size after all additions: " . count($completeEventList));
        }
        // VERBOSE_LOGGING_END

        // Store default target for simple format
        $this->_defaultTarget = getLastUserMessageSpeaker($contextData);

        // VERBOSE_LOGGING_START - _openPart3: Default target
        if ($this->_verboseLogging) {
            logMessage("[CACHE-VERBOSE] Default target (for simple format): " . ($this->_defaultTarget ?: 'NONE'));
        }
        // VERBOSE_LOGGING_END

        // Calculate cache control index BEFORE adding to finalMessagesToSend
        $totalElements = count($completeEventList);
        $lastIndex = $totalElements - $dialogue_cache_uncached_count - 1 - $addToIndex;

        logMessage("Cache control calculation: totalElements=$totalElements, uncached=$dialogue_cache_uncached_count, addToIndex=$addToIndex, calculatedIndex=$lastIndex");

        // VERBOSE_LOGGING_START - _openPart3: Cache control calculation
        if ($this->_verboseLogging) {
            logMessage("[CACHE-VERBOSE] ----- CACHE CONTROL CALCULATION -----");
            logMessage("[CACHE-VERBOSE] Total elements in list: {$totalElements}");
            logMessage("[CACHE-VERBOSE] Uncached count setting: {$dialogue_cache_uncached_count}");
            logMessage("[CACHE-VERBOSE] Custom instruction offset: {$addToIndex}");
            logMessage("[CACHE-VERBOSE] Calculated standard cache index: {$lastIndex}");
            logMessage("[CACHE-VERBOSE] Provider caching mode: {$this->_provider_caching}");
        }
        // VERBOSE_LOGGING_END

        // Place cache control marker
        if ($lastIndex >= 0) {
            if ($this->_provider_caching == "Gemini") {
                logMessage("Using gemini caching (ignores dialogue_cache_uncached_count)");

                // VERBOSE_LOGGING_START - _openPart3: Gemini cache logic
                if ($this->_verboseLogging) {
                    logMessage("[CACHE-VERBOSE] Using GEMINI batch-based caching (ignores dialogue_cache_uncached_count)");
                }
                // VERBOSE_LOGGING_END

                $offset = 10;
                $elements = count($completeEventList);
                $batchSize = $CONTEXTHISTORY - $offset;
                $batchNumber = floor($elements / $batchSize);

                logMessage("elements: $elements, batchsize: $batchSize, batchnumber: $batchNumber");

                // VERBOSE_LOGGING_START - _openPart3: Gemini batch calculation
                if ($this->_verboseLogging) {
                    logMessage("[CACHE-VERBOSE] Gemini batch calculation:");
                    logMessage("[CACHE-VERBOSE]   Context history: {$CONTEXTHISTORY}");
                    logMessage("[CACHE-VERBOSE]   Offset: {$offset}");
                    logMessage("[CACHE-VERBOSE]   Batch size: {$batchSize}");
                    logMessage("[CACHE-VERBOSE]   Batch number: {$batchNumber}");
                }
                // VERBOSE_LOGGING_END

                $indexToCache = max(0, ($batchNumber * $CONTEXTHISTORY) - $offset);

                if ($indexToCache >= $elements) {
                    logMessage("index bigger or equal then elements size.");
                    $indexToCache = $elements - 1;

                    // VERBOSE_LOGGING_START - _openPart3: Gemini index adjustment
                    if ($this->_verboseLogging) {
                        logMessage("[CACHE-VERBOSE] Index adjusted to last element: {$indexToCache}");
                    }
                    // VERBOSE_LOGGING_END
                }

                if ($indexToCache == 0) {
                    $indexToCache = 33; // Gemini requires minimum 32 tokens for caching, use 33 to be safe

                    // VERBOSE_LOGGING_START - _openPart3: Gemini minimum tokens
                    if ($this->_verboseLogging) {
                        logMessage("[CACHE-VERBOSE] Index was 0, adjusted to 33 (Gemini minimum 32 tokens requirement)");
                    }
                    // VERBOSE_LOGGING_END
                }

                logMessage("Index to Cache: $indexToCache");

                // VERBOSE_LOGGING_START - _openPart3: Gemini final index
                if ($this->_verboseLogging) {
                    logMessage("[CACHE-VERBOSE] Final Gemini cache index: {$indexToCache}");
                }
                // VERBOSE_LOGGING_END

                if (isset($completeEventList[$indexToCache]) && $this->_provider_caching != "OpenAI") {
                    $completeEventList[$indexToCache]["cache_control"] = $cacheControlType;

                    // VERBOSE_LOGGING_START - _openPart3: Gemini cache marker placed
                    if ($this->_verboseLogging) {
                        logMessage("[CACHE-VERBOSE] ✓ Cache control marker placed at index {$indexToCache}");
                    }
                    // VERBOSE_LOGGING_END
                } else {
                    logMessage("Warning: Index $indexToCache not found in array");

                    // VERBOSE_LOGGING_START - _openPart3: Gemini cache marker failed
                    if ($this->_verboseLogging) {
                        logMessage("[CACHE-VERBOSE] ✗ FAILED to place cache marker at index {$indexToCache} (index not found)");
                    }
                    // VERBOSE_LOGGING_END
                }
            } else {
                logMessage("Using standard caching with dialogue_cache_uncached_count=$dialogue_cache_uncached_count");

                // VERBOSE_LOGGING_START - _openPart3: Standard cache logic
                if ($this->_verboseLogging) {
                    logMessage("[CACHE-VERBOSE] Using STANDARD caching (respects dialogue_cache_uncached_count)");
                    logMessage("[CACHE-VERBOSE] Target cache index: {$lastIndex}");
                }
                // VERBOSE_LOGGING_END

                if (isset($completeEventList[$lastIndex]) && $this->_provider_caching != "OpenAI") {
                    $completeEventList[$lastIndex]["cache_control"] = $cacheControlType;
                    logMessage("Cache control placed at index $lastIndex");

                    // VERBOSE_LOGGING_START - _openPart3: Standard cache marker placed
                    if ($this->_verboseLogging) {
                        logMessage("[CACHE-VERBOSE] ✓ Cache control marker placed at index {$lastIndex}");
                    }
                    // VERBOSE_LOGGING_END
                } else {
                    logMessage("Warning: Index $lastIndex not found in array for non gemini");

                    // VERBOSE_LOGGING_START - _openPart3: Standard cache marker failed
                    if ($this->_verboseLogging) {
                        logMessage("[CACHE-VERBOSE] ✗ FAILED to place cache marker at index {$lastIndex} (index not found)");
                    }
                    // VERBOSE_LOGGING_END
                }
            }
        } else {
            logMessage("Warning: Calculated cache index is negative ($lastIndex), skipping cache control");

            // VERBOSE_LOGGING_START - _openPart3: Negative cache index
            if ($this->_verboseLogging) {
                logMessage("[CACHE-VERBOSE] ✗ Cache index is negative ({$lastIndex}), skipping cache control placement");
                logMessage("[CACHE-VERBOSE] This may indicate insufficient dialogue history");
            }
            // VERBOSE_LOGGING_END
        }

        // Add dynamic environment context if available
        if (!containsOnlySymbols($dynamicEnvironment)) {
            $text = preg_replace('/^\s*#+.*$/m', '', $dynamicEnvironment);
            $text = preg_replace('/^\s*[-•]\s*/', '', $text);
            $text = preg_replace('/\s+/', ' ', $text);
            $text = preg_replace('/[.]{2,}/', '.', $text);
            $dynamicEnvironment = trim("ASSISTANT: Environmental Context: $text");

            array_splice($completeEventList, count($completeEventList) - 2, 0, [array('type' => 'text', 'text' => $dynamicEnvironment)]);

            // VERBOSE_LOGGING_START - _openPart3: Dynamic environment inserted
            if ($this->_verboseLogging) {
                logMessage("[CACHE-VERBOSE] Dynamic environment inserted at position " . (count($completeEventList) - 3));
                logMessage("[CACHE-VERBOSE] Dynamic environment length: " . strlen($dynamicEnvironment) . " chars");
            }
            // VERBOSE_LOGGING_END
        } else {
            // VERBOSE_LOGGING_START - _openPart3: No dynamic environment
            if ($this->_verboseLogging) {
                logMessage("[CACHE-VERBOSE] No dynamic environment to insert (empty or only symbols)");
            }
            // VERBOSE_LOGGING_END
        }

        $completeEventList = removeDuplicateMemories($completeEventList);

        // VERBOSE_LOGGING_START - _openPart3: Duplicate removal
        if ($this->_verboseLogging) {
            logMessage("[CACHE-VERBOSE] Duplicate memories removed, final list size: " . count($completeEventList));
        }
        // VERBOSE_LOGGING_END

        $tokenCount = countTokensByWords($completeEventList);
        logMessage("Estimated token count: $tokenCount");

        // VERBOSE_LOGGING_START - _openPart3: Token count
        if ($this->_verboseLogging) {
            logMessage("[CACHE-VERBOSE] Estimated token count (by words): {$tokenCount}");
        }
        // VERBOSE_LOGGING_END

        // NOW add to finalMessagesToSend after all modifications are complete
        // BUG#3 FIX: Enable prefill for all caching providers, not just Anthropic
        if ($this->_responseFormat === 'simple') {
            $finalMessagesToSend[] = array('role' => 'user', 'content' => $completeEventList);
            $prefillText = '(';
            $finalMessagesToSend[] = array('role' => 'assistant', 'content' => array(
                array('type' => 'text', 'text' => $prefillText)
            ));
            $this->_usedPrefill = true;
            $this->_prefillContent = $prefillText;

            // VERBOSE_LOGGING_START - _openPart3: Prefill added
            if ($this->_verboseLogging) {
                logMessage("[CACHE-VERBOSE] ----- FINAL MESSAGE CONSTRUCTION -----");
                logMessage("[CACHE-VERBOSE] Using PREFILL mode (simple format with '(' prefix)");
                logMessage("[CACHE-VERBOSE] Prefill text: '{$prefillText}'");
                logMessage("[CACHE-VERBOSE] Added user message with " . count($completeEventList) . " content elements");
                logMessage("[CACHE-VERBOSE] Added assistant prefill message");
                logMessage("[CACHE-VERBOSE] Total messages in payload: " . count($finalMessagesToSend));
            }
            // VERBOSE_LOGGING_END
        } else {
            $finalMessagesToSend[] = array('role' => 'user', 'content' => $completeEventList);
            $this->_usedPrefill = false;
            $this->_prefillContent = '';

            // VERBOSE_LOGGING_START - _openPart3: No prefill
            if ($this->_verboseLogging) {
                logMessage("[CACHE-VERBOSE] ----- FINAL MESSAGE CONSTRUCTION -----");
                logMessage("[CACHE-VERBOSE] Standard mode (no prefill)");
                logMessage("[CACHE-VERBOSE] Response format: {$this->_responseFormat}");
                logMessage("[CACHE-VERBOSE] Provider: {$this->_provider_caching}");
                logMessage("[CACHE-VERBOSE] Added user message with " . count($completeEventList) . " content elements");
                logMessage("[CACHE-VERBOSE] Total messages in payload: " . count($finalMessagesToSend));
            }
            // VERBOSE_LOGGING_END
        }

        // VERBOSE_LOGGING_START - _openPart3: Completion
        if ($this->_verboseLogging) {
            logMessage("[CACHE-VERBOSE] ===== _OPENPART3 COMPLETE =====");
            logMessage("[CACHE-VERBOSE] Proceeding to Part 4 (final payload construction and API request)...");
        }
        // VERBOSE_LOGGING_END

        // Continue to Part 4 for final payload construction...
        return $this->_openPart4($customParms, $herikaName, $MAX_TOKENS, $toggleThinking, $thinkingTokens,
                                  $effort_level, $start_time, $finalMessagesToSend);
    }

    // Part 4: Final Payload Construction and API Request
    private function _openPart4($customParms, $herikaName, $MAX_TOKENS, $toggleThinking, $thinkingTokens,
                                 $effort_level, $start_time, $finalMessagesToSend) {

        // VERBOSE_LOGGING_START - _openPart4: Entry
        if ($this->_verboseLogging) {
            logMessage("[CACHE-VERBOSE] ===== _OPENPART4: FINAL PAYLOAD & API REQUEST =====");
            logMessage("[CACHE-VERBOSE] Messages to send: " . count($finalMessagesToSend));
            logMessage("[CACHE-VERBOSE] Toggle thinking: " . ($toggleThinking ? 'YES' : 'NO'));
        }
        // VERBOSE_LOGGING_END

        // Detect model capabilities
        $isOpenAIReasoning = $this->isOpenAIModel($this->_model);
        $isAlwaysReasoning = $this->isAlwaysReasoningModel($this->_model);

        // VERBOSE_LOGGING_START - Model detection
        if ($this->_verboseLogging) {
            logMessage("[CACHE-VERBOSE] Model detection: OpenAI=" . ($isOpenAIReasoning ? 'YES' : 'NO') . ", AlwaysReasoning=" . ($isAlwaysReasoning ? 'YES' : 'NO'));
        }
        // VERBOSE_LOGGING_END

        // Build reasoning configuration
        // Always include exclude:true to strip reasoning tokens from output
        // Enable reasoning if: toggle is on OR model always reasons
        $reasoning = [
            "exclude" => true,
            "enabled" => ($toggleThinking || $isAlwaysReasoning),
        ];

        // Add effort level for OpenAI models (supports minimal/low/medium/high)
        if ($isOpenAIReasoning && $reasoning["enabled"]) {
            $reasoning["effort"] = $effort_level;

            // VERBOSE_LOGGING_START - _openPart4: OpenAI reasoning
            if ($this->_verboseLogging) {
                logMessage("[CACHE-VERBOSE] Reasoning config (OpenAI): exclude=true, enabled=" . ($reasoning["enabled"] ? 'true' : 'false') . ", effort={$effort_level}");
            }
            // VERBOSE_LOGGING_END
        } else if ($reasoning["enabled"]) {
            // For non-OpenAI models, use max_tokens instead of effort
            $reasoning["max_tokens"] = intval($thinkingTokens);

            // VERBOSE_LOGGING_START - _openPart4: Standard reasoning
            if ($this->_verboseLogging) {
                logMessage("[CACHE-VERBOSE] Reasoning config (Standard): exclude=true, enabled=true, max_tokens={$thinkingTokens}");
            }
            // VERBOSE_LOGGING_END
        } else {
            // VERBOSE_LOGGING_START - _openPart4: Reasoning disabled
            if ($this->_verboseLogging) {
                logMessage("[CACHE-VERBOSE] Reasoning config: exclude=true, enabled=false");
            }
            // VERBOSE_LOGGING_END
        }

        // Construct payload
        $data = array(
            'model' => $this->_model,
            'messages' => $finalMessagesToSend,
            'stream' => true,
            'temperature' => floatval((isset($GLOBALS["CONNECTOR"][$this->name]["temperature"])) ? $GLOBALS["CONNECTOR"][$this->name]["temperature"] : 1),
            'top_k' => floatval((isset($GLOBALS["CONNECTOR"][$this->name]["top_k"])) ? $GLOBALS["CONNECTOR"][$this->name]["top_k"] : 0),
            'top_p' => floatval((isset($GLOBALS["CONNECTOR"][$this->name]["top_p"])) ? $GLOBALS["CONNECTOR"][$this->name]["top_p"] : 1),
            'frequency_penalty' => floatval((isset($GLOBALS["CONNECTOR"][$this->name]["frequency_penalty"])) ? $GLOBALS["CONNECTOR"][$this->name]["frequency_penalty"] : 0),
            'presence_penalty' => floatval((isset($GLOBALS["CONNECTOR"][$this->name]["presence_penalty"])) ? $GLOBALS["CONNECTOR"][$this->name]["presence_penalty"] : 0),
            'repetition_penalty' => floatval((isset($GLOBALS["CONNECTOR"][$this->name]["repetition_penalty"])) ? $GLOBALS["CONNECTOR"][$this->name]["repetition_penalty"] : 1),
            'min_p' => floatval((isset($GLOBALS["CONNECTOR"][$this->name]["min_p"])) ? $GLOBALS["CONNECTOR"][$this->name]["min_p"] : 0),
            'top_a' => floatval((isset($GLOBALS["CONNECTOR"][$this->name]["top_a"])) ? $GLOBALS["CONNECTOR"][$this->name]["top_a"] : 0),
            'reasoning' => $reasoning
        );

        // VERBOSE_LOGGING_START - _openPart4: Payload constructed
        if ($this->_verboseLogging) {
            logMessage("[CACHE-VERBOSE] ----- PAYLOAD BASE PARAMETERS -----");
            logMessage("[CACHE-VERBOSE] Model: {$this->_model}");
            logMessage("[CACHE-VERBOSE] Stream: true");
            logMessage("[CACHE-VERBOSE] Temperature: {$data['temperature']}");
            logMessage("[CACHE-VERBOSE] Top K: {$data['top_k']}");
            logMessage("[CACHE-VERBOSE] Top P: {$data['top_p']}");
            logMessage("[CACHE-VERBOSE] Frequency penalty: {$data['frequency_penalty']}");
            logMessage("[CACHE-VERBOSE] Presence penalty: {$data['presence_penalty']}");
            logMessage("[CACHE-VERBOSE] Repetition penalty: {$data['repetition_penalty']}");
            logMessage("[CACHE-VERBOSE] Cache control: enabled=true, ttl=1h");
        }
        // VERBOSE_LOGGING_END

        // Handle max tokens
        $effectiveMaxTokens = null;
        if (isset($customParms["MAX_TOKENS"])) {
            $maxTokensValue = $customParms["MAX_TOKENS"] + 0;
            if ($maxTokensValue >= 0) {
                $effectiveMaxTokens = $maxTokensValue;
            }
        } else {
            if (isset($MAX_TOKENS))
                $effectiveMaxTokens = $MAX_TOKENS;
        }
        if (isset($GLOBALS["FORCE_MAX_TOKENS"])) {
            $forceMaxTokensValue = $GLOBALS["FORCE_MAX_TOKENS"] + 0;
            if ($forceMaxTokensValue >= 0) {
                $effectiveMaxTokens = $forceMaxTokensValue;
            }
        }
        if ($effectiveMaxTokens !== null) {
            if ($effectiveMaxTokens > 0) {
                $data["max_tokens"] = (int) $effectiveMaxTokens;

                // VERBOSE_LOGGING_START - _openPart4: Max tokens set
                if ($this->_verboseLogging) {
                    logMessage("[CACHE-VERBOSE] Max tokens: {$effectiveMaxTokens}");
                }
                // VERBOSE_LOGGING_END
            } else {
                unset($data["max_tokens"]);

                // VERBOSE_LOGGING_START - _openPart4: Max tokens unset (zero)
                if ($this->_verboseLogging) {
                    logMessage("[CACHE-VERBOSE] Max tokens: unset (value was 0)");
                }
                // VERBOSE_LOGGING_END
            }
        } else {
            if (isset($data["max_tokens"]))
                unset($data["max_tokens"]);

            // VERBOSE_LOGGING_START - _openPart4: Max tokens unset (null)
            if ($this->_verboseLogging) {
                logMessage("[CACHE-VERBOSE] Max tokens: unset (not specified)");
            }
            // VERBOSE_LOGGING_END
        }

        // Add provider information
        if (!empty($GLOBALS["CONNECTOR"][$this->name]["PROVIDER"])) {
            $providers = explode(",", $GLOBALS["CONNECTOR"][$this->name]["PROVIDER"]);
            $data["provider"] = array("order" => $providers);

            // VERBOSE_LOGGING_START - _openPart4: Custom providers
            if ($this->_verboseLogging) {
                logMessage("[CACHE-VERBOSE] Provider order (custom): " . implode(", ", $providers));
            }
            // VERBOSE_LOGGING_END
        } else {
            $data["provider"] = array("order" => array("Anthropic"));

            // VERBOSE_LOGGING_START - _openPart4: Default provider
            if ($this->_verboseLogging) {
                logMessage("[CACHE-VERBOSE] Provider order (default): Anthropic");
            }
            // VERBOSE_LOGGING_END
        }

        $data["transforms"] = array();

        // Handle OpenAI reasoning models - they require special parameter handling
        if ($isOpenAIReasoning) {
            // OpenAI models use max_completion_tokens instead of max_tokens
            if (isset($data["max_tokens"])) {
                $data["max_completion_tokens"] = $data["max_tokens"];
                unset($data["max_tokens"]);

                // VERBOSE_LOGGING_START - OpenAI token parameter switch
                if ($this->_verboseLogging) {
                    logMessage("[CACHE-VERBOSE] OpenAI model: Switched max_tokens to max_completion_tokens");
                }
                // VERBOSE_LOGGING_END
            }

            // If reasoning is enabled, OpenAI models ONLY accept these parameters
            // All other parameters (temperature, top_p, penalties, etc.) must be stripped
            if ($reasoning["enabled"]) {
                // VERBOSE_LOGGING_START - Parameter stripping for OpenAI reasoning
                if ($this->_verboseLogging) {
                    logMessage("[CACHE-VERBOSE] OpenAI reasoning model detected - stripping incompatible parameters");
                    logMessage("[CACHE-VERBOSE] Keeping only: model, messages, stream, reasoning, max_completion_tokens, provider, transforms");
                }
                // VERBOSE_LOGGING_END

                $cleanedData = [
                    'model' => $data['model'],
                    'messages' => $data['messages'],
                    'stream' => $data['stream'],
                    'reasoning' => $data['reasoning']
                ];

                // Only add max_completion_tokens if it was set
                if (isset($data['max_completion_tokens'])) {
                    $cleanedData['max_completion_tokens'] = $data['max_completion_tokens'];
                }

                // Preserve provider and transforms if they exist
                if (isset($data['provider'])) {
                    $cleanedData['provider'] = $data['provider'];
                }
                if (isset($data['transforms'])) {
                    $cleanedData['transforms'] = $data['transforms'];
                }

                $data = $cleanedData;

                // VERBOSE_LOGGING_START - Parameters stripped
                if ($this->_verboseLogging) {
                    logMessage("[CACHE-VERBOSE] Parameters stripped successfully - payload cleaned for OpenAI reasoning API");
                }
                // VERBOSE_LOGGING_END
            }
        }

        // Log request
        if (!isset($GLOBALS["DEBUG_DATA"])) {
            $GLOBALS["DEBUG_DATA"] = array();
        }
        $GLOBALS["DEBUG_DATA"]["full"] = ($data);
        $this->_dataSent = json_encode($data, JSON_PRETTY_PRINT);

        // VERBOSE_LOGGING_START - _openPart4: Payload finalized
        if ($this->_verboseLogging) {
            $payloadSize = strlen($this->_dataSent);
            logMessage("[CACHE-VERBOSE] ----- PAYLOAD FINALIZED -----");
            logMessage("[CACHE-VERBOSE] Payload JSON size: {$payloadSize} bytes");
            logMessage("[CACHE-VERBOSE] Total messages in payload: " . count($finalMessagesToSend));
        }
        // VERBOSE_LOGGING_END

        try {
            $finalMsgCount = isset($finalMessagesToSend) ? count($finalMessagesToSend) : 0;
            $logEntry = sprintf(
                "[%s] [%s:%s]\nPayload (%d msgs):\n%s\n---\n",
                date(DATE_ATOM),
                $this->name,
                $herikaName,
                $finalMsgCount,
                var_export($data, true)
            );
            @file_put_contents(__DIR__ . "/../log/context_sent_to_llm.log", $logEntry, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            logMessage("Context Log Err: " . $e->getMessage());
        }

        // Prepare API request
        $apiKey = isset($GLOBALS["CONNECTOR"][$this->name]["API_KEY"]) ? $GLOBALS["CONNECTOR"][$this->name]["API_KEY"] : '';
        if (empty($apiKey)) {
            logMessage("API Key missing!");

            // VERBOSE_LOGGING_START - _openPart4: API key missing
            if ($this->_verboseLogging) {
                logMessage("[CACHE-VERBOSE] ✗ API KEY MISSING - cannot proceed with request");
            }
            // VERBOSE_LOGGING_END

            return null;
        }

        $headers = array(
            'Content-Type: application/json',
            "Authorization: Bearer {$apiKey}",
            "HTTP-Referer:  https://dwemerdynamics.com/",
            "X-Title: Dwemer Dynamics",
            "anthropic-beta: extended-cache-ttl-2025-04-11"
        );

        // VERBOSE_LOGGING_START - _openPart4: Headers
        if ($this->_verboseLogging) {
            $maskedApiKey = substr($apiKey, 0, 8) . '...' . substr($apiKey, -4);
            logMessage("[CACHE-VERBOSE] ----- API REQUEST HEADERS -----");
            logMessage("[CACHE-VERBOSE] Authorization: Bearer {$maskedApiKey}");
            logMessage("[CACHE-VERBOSE] Anthropic beta: extended-cache-ttl-2025-04-11");
        }
        // VERBOSE_LOGGING_END

        $timeout = isset($GLOBALS["HTTP_TIMEOUT"]) ? (int) $GLOBALS["HTTP_TIMEOUT"] : 60;

        // VERBOSE_LOGGING_START - _openPart4: Timeout
        if ($this->_verboseLogging) {
            logMessage("[CACHE-VERBOSE] HTTP timeout: {$timeout} seconds");
        }
        // VERBOSE_LOGGING_END

        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => json_encode($data),
                'timeout' => $timeout,
                'ignore_errors' => true
            )
        );

        $context = stream_context_create($options);

        // Initialize stream state
        $this->primary_handler = null;
        $this->_rawbuffer = "";
        $this->_buffer = "";
        $this->_forcedClose = false;
        $this->_jsonResponsesEncoded = array();

        // VERBOSE_LOGGING_START - _openPart4: Stream state initialized
        if ($this->_verboseLogging) {
            logMessage("[CACHE-VERBOSE] Stream state initialized (buffers cleared)");
        }
        // VERBOSE_LOGGING_END

        $end_time = microtime(true);
        $execution_time = $end_time - $start_time;
        logMessage("Time for preparing cached request: $execution_time seconds");

        // VERBOSE_LOGGING_START - _openPart4: Preparation time
        if ($this->_verboseLogging) {
            logMessage("[CACHE-VERBOSE] Request preparation time: {$execution_time} seconds");
        }
        // VERBOSE_LOGGING_END

        // Open stream
        // VERBOSE_LOGGING_START - _openPart4: Opening stream
        if ($this->_verboseLogging) {
            logMessage("[CACHE-VERBOSE] ----- OPENING STREAM -----");
            logMessage("[CACHE-VERBOSE] Target URL: {$this->_url}");
            logMessage("[CACHE-VERBOSE] Attempting to establish streaming connection...");
        }
        // VERBOSE_LOGGING_END

        try {
            $this->primary_handler = $this->send($this->_url, $context);
        } catch (Exception $e) {
            logMessage("fopen Exception [{$this->name}:{$herikaName}]: " . $e->getMessage());

            // VERBOSE_LOGGING_START - _openPart4: Stream exception
            if ($this->_verboseLogging) {
                logMessage("[CACHE-VERBOSE] ✗ STREAM EXCEPTION: " . $e->getMessage());
                logMessage("[CACHE-VERBOSE] Request failed - returning null");
            }
            // VERBOSE_LOGGING_END

            return null;
        }

        if (!$this->primary_handler) {
            $error = error_get_last();
            $errMsg = isset($error['message']) ? $error['message'] : 'fopen returned false';
            logMessage("Stream Open Fail [{$this->name}:{$herikaName}]: {$errMsg}");
            if (isset($http_response_header) && is_array($http_response_header)) {
                logMessage("HTTP Headers on fail: " . implode("\n", $http_response_header));
            }

            // VERBOSE_LOGGING_START - _openPart4: Stream open failed
            if ($this->_verboseLogging) {
                logMessage("[CACHE-VERBOSE] ✗ STREAM OPEN FAILED: {$errMsg}");
                logMessage("[CACHE-VERBOSE] Request failed - returning null");
            }
            // VERBOSE_LOGGING_END

            return null;
        }

        // VERBOSE_LOGGING_START - _openPart4: Stream opened successfully
        if ($this->_verboseLogging) {
            logMessage("[CACHE-VERBOSE] ✓ Stream opened successfully");
            logMessage("[CACHE-VERBOSE] Ready to receive streaming response");
            logMessage("[CACHE-VERBOSE] ===== _OPENPART4 COMPLETE =====");
        }
        // VERBOSE_LOGGING_END

        return true;
    }

    public function process() {
        global $alreadysent;
        $herikaName = isset($GLOBALS["HERIKA_NAME"]) ? $GLOBALS["HERIKA_NAME"] : 'default_herika';

        // VERBOSE_LOGGING_START - process: First call
        static $firstProcessCall = true;
        if ($this->_verboseLogging && $firstProcessCall) {
            logMessage("[CACHE-VERBOSE] ===== PROCESS(): STREAMING RESPONSE =====");
            logMessage("[CACHE-VERBOSE] Beginning to process streaming response chunks");
            logMessage("[CACHE-VERBOSE] Response format: {$this->_responseFormat}");
            $firstProcessCall = false;
        }
        // VERBOSE_LOGGING_END

        if ($this->isDone())
            return "";

        $line = @fgets($this->primary_handler);
        if ($line === false) {
            if (feof($this->primary_handler)) {
                // VERBOSE_LOGGING_START - process: EOF reached
                if ($this->_verboseLogging) {
                    logMessage("[CACHE-VERBOSE] Stream EOF reached (end of response)");
                }
                // VERBOSE_LOGGING_END
                return "";
            } else {
                $error = error_get_last();
                $errMsg = isset($error['message']) ? $error['message'] : 'fgets error';
                logMessage("Read Err [{$this->name}:{$herikaName}]: {$errMsg}");
                $this->_rawbuffer .= "\nRead Err: {$errMsg}\n";
                $this->_forcedClose = true;

                // VERBOSE_LOGGING_START - process: Read error
                if ($this->_verboseLogging) {
                    logMessage("[CACHE-VERBOSE] ✗ Stream read error: {$errMsg}");
                }
                // VERBOSE_LOGGING_END

                return $errMsg;
            }
        }

        try {
            @file_put_contents(__DIR__ . "/../log/debugStream.log", $line, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
        }

        $this->_rawbuffer .= $line;
        $buffer = "";

        if (strpos($line, 'data: ') === 0) {
            $jsonData = trim(substr($line, 6));
            if ($jsonData === '[DONE]') {
                // VERBOSE_LOGGING_START - process: DONE marker
                if ($this->_verboseLogging) {
                    logMessage("[CACHE-VERBOSE] [DONE] marker received - stream complete");
                }
                // VERBOSE_LOGGING_END
                return "";
            }

            if (!empty($jsonData)) {
                $data = json_decode($jsonData, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                    // Handle Anthropic format
                    if (isset($data['type'])) {
                        // VERBOSE_LOGGING_START - process: Anthropic event type
                        static $eventTypeCounts = [];
                        if ($this->_verboseLogging) {
                            $eventType = $data['type'];
                            if (!isset($eventTypeCounts[$eventType])) {
                                logMessage("[CACHE-VERBOSE] First Anthropic event type '{$eventType}' received");
                                $eventTypeCounts[$eventType] = 1;
                            }
                        }
                        // VERBOSE_LOGGING_END

                        switch ($data['type']) {
                            case 'content_block_delta':
                                if (isset($data['delta']['type']) && $data['delta']['type'] === 'text_delta' &&
                                    isset($data['delta']['text'])) {
                                    $buffer = $data['delta']['text'];
                                    $this->_buffer .= $buffer;
                                }
                                break;

                            case 'content_block_start':
                                if (isset($data['content_block']['type']) && $data['content_block']['type'] === 'tool_use') {
                                    $this->_jsonResponsesEncoded[] = json_encode($data);

                                    // VERBOSE_LOGGING_START - process: Tool use detected
                                    if ($this->_verboseLogging) {
                                        logMessage("[CACHE-VERBOSE] Tool use content block detected");
                                    }
                                    // VERBOSE_LOGGING_END
                                }
                                break;

                            case 'message_delta':
                                if (isset($data['delta']['stop_reason']) && $data['delta']['stop_reason'] !== null) {
                                    logMessage("[{$this->name}:{$herikaName}] Stop (delta): " . $data['delta']['stop_reason']);

                                    // VERBOSE_LOGGING_START - process: Stop reason
                                    if ($this->_verboseLogging) {
                                        logMessage("[CACHE-VERBOSE] Stop reason (delta): " . $data['delta']['stop_reason']);
                                    }
                                    // VERBOSE_LOGGING_END

                                    $this->_forcedClose = true;
                                }
                                break;

                            case 'message_stop':
                                logMessage("[{$this->name}:{$herikaName}] Stop (message_stop). Usage:" .
                                    (isset($data['message']['usage']) ? json_encode($data['message']['usage']) : 'N/A'));

                                // Log cache efficiency
                                if (isset($data['message']['usage'])) {
                                    $usage = $data['message']['usage'];
                                    $cacheRead = isset($usage['cache_read_input_tokens']) ? $usage['cache_read_input_tokens'] : 0;
                                    $cacheCreate = isset($usage['cache_creation_input_tokens']) ? $usage['cache_creation_input_tokens'] : 0;
                                    $normalInput = isset($usage['input_tokens']) ? $usage['input_tokens'] : 0;
                                    $totalConsideredInput = $cacheRead + $cacheCreate + $normalInput;
                                    $efficiency = ($totalConsideredInput > 0) ? round(($cacheRead / $totalConsideredInput * 100), 1) : 0;
                                    $logPerfEntry = sprintf(
                                        "[%s] CACHE_PERF %s: Read:%d Create:%d New:%d Total:%d Efficiency:%.1f%%\n",
                                        date(DATE_ATOM),
                                        $herikaName,
                                        $cacheRead,
                                        $cacheCreate,
                                        $normalInput,
                                        $totalConsideredInput,
                                        $efficiency
                                    );
                                    @file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . "_cached_perf.log", $logPerfEntry, FILE_APPEND);

                                    // VERBOSE_LOGGING_START - process: Cache performance
                                    if ($this->_verboseLogging) {
                                        logMessage("[CACHE-VERBOSE] ----- CACHE PERFORMANCE METRICS -----");
                                        logMessage("[CACHE-VERBOSE] Cache read tokens: {$cacheRead}");
                                        logMessage("[CACHE-VERBOSE] Cache creation tokens: {$cacheCreate}");
                                        logMessage("[CACHE-VERBOSE] New input tokens: {$normalInput}");
                                        logMessage("[CACHE-VERBOSE] Total input tokens: {$totalConsideredInput}");
                                        logMessage("[CACHE-VERBOSE] Cache efficiency: {$efficiency}%");
                                        if (isset($usage['output_tokens'])) {
                                            logMessage("[CACHE-VERBOSE] Output tokens: {$usage['output_tokens']}");
                                        }
                                    }
                                    // VERBOSE_LOGGING_END
                                }

                                $this->_forcedClose = true;
                                break;

                            case 'error':
                                $eM = print_r((isset($data['error']) ? $data['error'] : $data), true);
                                logMessage("Stream Err (Anthropic): {$eM}");
                                $this->_rawbuffer .= "\nErr (Anthropic):{$eM}\n";
                                $this->_forcedClose = true;

                                // VERBOSE_LOGGING_START - process: Anthropic error
                                if ($this->_verboseLogging) {
                                    logMessage("[CACHE-VERBOSE] ✗ Anthropic error received: {$eM}");
                                }
                                // VERBOSE_LOGGING_END

                                return $eM;

                            case 'ping':
                                break;

                            default:
                                logMessage("[{$this->name}:{$herikaName}] Unhandled Anthropic Type: " . $data['type']);
                                break;
                        }
                    }
                    // Handle OpenAI format
                    elseif (isset($data["choices"][0]["delta"])) {
                        // VERBOSE_LOGGING_START - process: OpenAI format detected
                        static $openaiFormatLogged = false;
                        if ($this->_verboseLogging && !$openaiFormatLogged) {
                            logMessage("[CACHE-VERBOSE] OpenAI format detected");
                            $openaiFormatLogged = true;
                        }
                        // VERBOSE_LOGGING_END

                        if (isset($data["choices"][0]["delta"]["content"])) {
                            $buffer = $data["choices"][0]["delta"]["content"];
                            $this->_buffer .= $buffer;
                        }

                        if (isset($data["choices"][0]["delta"]["tool_calls"])) {
                            $this->_jsonResponsesEncoded[] = json_encode($data);

                            // VERBOSE_LOGGING_START - process: OpenAI tool calls
                            if ($this->_verboseLogging) {
                                logMessage("[CACHE-VERBOSE] OpenAI tool calls detected");
                            }
                            // VERBOSE_LOGGING_END
                        }

                        if (isset($data["choices"][0]["finish_reason"]) && $data["choices"][0]["finish_reason"] !== null) {
                            logMessage("[{$this->name}:{$herikaName}] Stop (choice): " . $data["choices"][0]["finish_reason"]);

                            // VERBOSE_LOGGING_START - process: OpenAI finish reason
                            if ($this->_verboseLogging) {
                                logMessage("[CACHE-VERBOSE] Finish reason: " . $data["choices"][0]["finish_reason"]);
                            }
                            // VERBOSE_LOGGING_END

                            $this->_forcedClose = true;
                        }
                    }
                    // Generic error
                    elseif (isset($data['error'])) {
                        $eM = print_r($data['error'], true);
                        logMessage("Stream Err (Generic): {$eM}");
                        $this->_rawbuffer .= "\nErr (Generic):{$eM}\n";
                        $this->_forcedClose = true;

                        // VERBOSE_LOGGING_START - process: Generic error
                        if ($this->_verboseLogging) {
                            logMessage("[CACHE-VERBOSE] ✗ Generic error received: {$eM}");
                        }
                        // VERBOSE_LOGGING_END

                        return $eM;
                    }
                } else {
                    logMessage("JSON Decode Err [{$this->name}:{$herikaName}]: " . json_last_error_msg());

                    // VERBOSE_LOGGING_START - process: JSON decode error
                    if ($this->_verboseLogging) {
                        logMessage("[CACHE-VERBOSE] ✗ JSON decode error: " . json_last_error_msg());
                    }
                    // VERBOSE_LOGGING_END
                }
            }
        } elseif (trim($line) === "event: message_stop") {
            logMessage("[{$this->name}:{$herikaName}] Explicit stream end event received.");
            $this->_forcedClose = true;

            // VERBOSE_LOGGING_START - process: Explicit stream end
            if ($this->_verboseLogging) {
                logMessage("[CACHE-VERBOSE] Explicit 'event: message_stop' received");
            }
            // VERBOSE_LOGGING_END
        }

        // Parse and return content based on format
        if (!empty($buffer)) {
            return $this->_parseAndReturnContent();
        }

        return "";
    }

    // Helper method to parse and return content based on format
    private function _parseAndReturnContent() {
        // VERBOSE_LOGGING_START - _parseAndReturnContent: Entry
        static $parseCallCount = 0;
        if ($this->_verboseLogging) {
            $parseCallCount++;
            if ($parseCallCount === 1) {
                logMessage("[CACHE-VERBOSE] ----- PARSING RESPONSE CONTENT -----");
                logMessage("[CACHE-VERBOSE] Response format: {$this->_responseFormat}");
                logMessage("[CACHE-VERBOSE] Buffer length: " . strlen($this->_buffer));
            }
        }
        // VERBOSE_LOGGING_END

        if ($this->_responseFormat === 'json') {
            // JSON format parsing
            $extracted_json_or_text = extractJson($this->_buffer);
            $tempJson = json_decode($extracted_json_or_text, true);

            // VERBOSE_LOGGING_START - _parseAndReturnContent: JSON extraction
            if ($this->_verboseLogging && $parseCallCount === 1) {
                logMessage("[CACHE-VERBOSE] Extracted JSON length: " . strlen($extracted_json_or_text));
                if (json_last_error() === JSON_ERROR_NONE) {
                    logMessage("[CACHE-VERBOSE] JSON decoded successfully");
                    logMessage("[CACHE-VERBOSE] JSON keys: " . (is_array($tempJson) ? implode(', ', array_keys($tempJson)) : 'N/A'));
                } else {
                    logMessage("[CACHE-VERBOSE] ✗ JSON decode failed: " . json_last_error_msg());
                }
            }
            // VERBOSE_LOGGING_END

            if (json_last_error() === JSON_ERROR_NONE && isset($tempJson['message']) && !empty($tempJson['message'])) {
                if (isset($tempJson["mood"])) {
                    $GLOBALS["SCRIPTLINE_ANIMATION"] = function_exists('GetAnimationHex') ? GetAnimationHex($tempJson["mood"]) : '';
                    $GLOBALS["SCRIPTLINE_EXPRESSION"] = function_exists('GetExpression') ? GetExpression($tempJson["mood"]) : '';

                    // VERBOSE_LOGGING_START - _parseAndReturnContent: Mood set
                    if ($this->_verboseLogging && $parseCallCount === 1) {
                        logMessage("[CACHE-VERBOSE] Mood extracted: {$tempJson['mood']}");
                    }
                    // VERBOSE_LOGGING_END
                }
                if (isset($tempJson["listener"])) {
                    if (isset($tempJson["action"]) && ($tempJson["action"] == "Talk") &&
                        lazyEmpty($tempJson["listener"]) && !lazyEmpty($tempJson["target"])) {
                        $GLOBALS["SCRIPTLINE_LISTENER"] = $tempJson["target"];
                    } else {
                        $GLOBALS["SCRIPTLINE_LISTENER"] = $tempJson["listener"];
                    }

                    // VERBOSE_LOGGING_START - _parseAndReturnContent: Listener set
                    if ($this->_verboseLogging && $parseCallCount === 1) {
                        logMessage("[CACHE-VERBOSE] Listener extracted: " . $GLOBALS["SCRIPTLINE_LISTENER"]);
                    }
                    // VERBOSE_LOGGING_END
                }

                // VERBOSE_LOGGING_START - _parseAndReturnContent: Message extracted
                if ($this->_verboseLogging && $parseCallCount === 1) {
                    $msgPreview = substr($tempJson['message'], 0, 100);
                    logMessage("[CACHE-VERBOSE] Message extracted (length: " . strlen($tempJson['message']) . ", preview): {$msgPreview}...");
                }
                // VERBOSE_LOGGING_END

                // Strip any reasoning tokens from final message before returning
                return stripReasoningTokens($tempJson['message']);
            }
        } else {
            // Simple format parsing
            if (!$this->_simpleFormatParsed) {
                // Prepend prefill content if used, since API doesn't return it in response
                $bufferToParse = $this->_usedPrefill ? $this->_prefillContent . $this->_buffer : $this->_buffer;

                $parsed = extractSimpleFormatFromBuffer(
                    $bufferToParse,
                    $this->_includeMood,
                    $this->_includeListener,
                    $this->_includeActions,
                    $this->_includeTarget
                );

                // VERBOSE_LOGGING_START - _parseAndReturnContent: Simple format extraction
                if ($this->_verboseLogging) {
                    logMessage("[CACHE-VERBOSE] Simple format extraction attempt, found: " . ($parsed['found'] ? 'YES' : 'NO'));
                }
                // VERBOSE_LOGGING_END

                if ($parsed['found']) {
                    $this->_simpleFormatParsed = true;

                    if ($this->_includeMood && !empty($parsed['mood'])) {
                        $GLOBALS["SCRIPTLINE_ANIMATION"] = function_exists('GetAnimationHex') ? GetAnimationHex($parsed["mood"]) : '';
                        $GLOBALS["SCRIPTLINE_EXPRESSION"] = function_exists('GetExpression') ? GetExpression($parsed["mood"]) : '';

                        // VERBOSE_LOGGING_START - _parseAndReturnContent: Simple mood
                        if ($this->_verboseLogging) {
                            logMessage("[CACHE-VERBOSE] Simple format mood: {$parsed['mood']}");
                        }
                        // VERBOSE_LOGGING_END
                    }

                    if ($this->_includeListener && !empty($parsed['listener'])) {
                        $GLOBALS["SCRIPTLINE_LISTENER"] = $parsed['listener'];

                        // VERBOSE_LOGGING_START - _parseAndReturnContent: Simple listener
                        if ($this->_verboseLogging) {
                            logMessage("[CACHE-VERBOSE] Simple format listener: {$parsed['listener']}");
                        }
                        // VERBOSE_LOGGING_END
                    }

                    // VERBOSE_LOGGING_START - _parseAndReturnContent: Simple message
                    if ($this->_verboseLogging) {
                        $msgPreview = substr($parsed['message'], 0, 100);
                        logMessage("[CACHE-VERBOSE] Simple format message (length: " . strlen($parsed['message']) . ", preview): {$msgPreview}...");
                    }
                    // VERBOSE_LOGGING_END

                    // Strip any reasoning tokens from final message before returning
                    return stripReasoningTokens($parsed['message']);
                }
            } else {
                // Simple format already parsed, just return accumulated message
                // VERBOSE_LOGGING_START - _parseAndReturnContent: Accumulating
                if ($this->_verboseLogging && strlen($this->_buffer) > ($this->_lastReturnedLength ?? 0)) {
                    logMessage("[CACHE-VERBOSE] Accumulating more content, buffer now: " . strlen($this->_buffer) . " chars");
                    $this->_lastReturnedLength = strlen($this->_buffer);
                }
                // VERBOSE_LOGGING_END

                // Strip any reasoning tokens from accumulated content before returning
                return stripReasoningTokens($this->_buffer);
            }
        }

        return "";
    }

    public function close() {
        if ($this->primary_handler) {
            @fclose($this->primary_handler);
            $this->primary_handler = null;
        }

        $herikaName = isset($GLOBALS["HERIKA_NAME"]) ? $GLOBALS["HERIKA_NAME"] : 'default_herika';

        try {
            $proc = isset($this->_buffer) ? $this->_buffer : '<empty>';
            $jsonResponses = isset($this->_jsonResponsesEncoded) && is_array($this->_jsonResponsesEncoded) ?
                implode("\n", $this->_jsonResponsesEncoded) : "<no JSON responses>";

            $logContent = sprintf(
                "Processed Text:\n%s\n\nJSON Responses:\n%s\n\n[%s] [%s:%s] END STREAM\n==\n",
                $proc,
                $jsonResponses,
                date(DATE_ATOM),
                $this->name,
                $herikaName
            );

            @file_put_contents(__DIR__ . "/../log/output_from_llm.log", $logContent, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            logMessage("[{$this->name}:{$herikaName}] Close Log Err: " . $e->getMessage());
        }

        $this->_rawbuffer = "";
        $this->_forcedClose = false;

        return "";
    }

    public function processActions() {
        global $alreadysent;
        $this->_commandBuffer = array();
        $herikaName = isset($GLOBALS["HERIKA_NAME"]) ? $GLOBALS["HERIKA_NAME"] : 'default_herika';

        logMessage("start process actions");

        // VERBOSE_LOGGING_START - processActions: Entry
        if ($this->_verboseLogging) {
            logMessage("[CACHE-VERBOSE] ----- PROCESSING ACTIONS -----");
            logMessage("[CACHE-VERBOSE] Response format: {$this->_responseFormat}");
            logMessage("[CACHE-VERBOSE] Buffer length: " . strlen($this->_buffer));
            logMessage("[CACHE-VERBOSE] Include actions: " . ($this->_includeActions ? 'YES' : 'NO'));
        }
        // VERBOSE_LOGGING_END

        if ($this->_responseFormat === 'json') {
            // JSON format action processing
            if (!empty($this->_buffer)) {
                $jsonStart = strpos($this->_buffer, '{');
                $jsonEnd = strrpos($this->_buffer, '}');

                if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd > $jsonStart) {
                    $possibleJson = substr($this->_buffer, $jsonStart, $jsonEnd - $jsonStart + 1);
                    $parsedResponse = json_decode($possibleJson, true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($parsedResponse)) {
                        logMessage("[{$this->name}:{$herikaName}] Parsed JSON from buffer: " . json_encode($parsedResponse));

                        // VERBOSE_LOGGING_START - processActions: JSON parsed for actions
                        if ($this->_verboseLogging) {
                            logMessage("[CACHE-VERBOSE] JSON parsed for action processing");
                            logMessage("[CACHE-VERBOSE] Has 'action' field: " . (isset($parsedResponse['action']) ? 'YES' : 'NO'));
                            if (isset($parsedResponse['action'])) {
                                logMessage("[CACHE-VERBOSE] Action value: {$parsedResponse['action']}");
                            }
                        }
                        // VERBOSE_LOGGING_END

                        if (isset($parsedResponse['action']) && !empty($parsedResponse['action'])) {
                            $target = isset($parsedResponse['target']) ? $parsedResponse['target'] : '';
                            $character = isset($parsedResponse['character']) ? $parsedResponse['character'] : $herikaName;
                            $commandKey = md5("{$character}|command|{$parsedResponse['action']}@{$target}\r\n");

                            if (!isset($alreadysent[$commandKey]) || empty($alreadysent[$commandKey])) {
                                $functionCodeName = function_exists('getFunctionCodeName') ? getFunctionCodeName($parsedResponse['action']) : $parsedResponse['action'];
                                $functionCodeName = empty($functionCodeName) ? $parsedResponse['action'] : $functionCodeName;

                                $commandString = "{$character}|command|{$functionCodeName}@{$target}\r\n";
                                $this->_commandBuffer[] = $commandString;
                                $alreadysent[$commandKey] = $commandString;

                                logMessage("[{$this->name}:{$herikaName}] Generated command: {$commandString}");

                                // VERBOSE_LOGGING_START - processActions: Command generated (JSON)
                                if ($this->_verboseLogging) {
                                    logMessage("[CACHE-VERBOSE] Command generated (JSON): {$commandString}");
                                    logMessage("[CACHE-VERBOSE] Target: " . ($target ?: 'NONE'));
                                    logMessage("[CACHE-VERBOSE] Character: {$character}");
                                }
                                // VERBOSE_LOGGING_END
                            } else {
                                // VERBOSE_LOGGING_START - processActions: Duplicate command skipped
                                if ($this->_verboseLogging) {
                                    logMessage("[CACHE-VERBOSE] Command skipped (duplicate): {$parsedResponse['action']}@{$target}");
                                }
                                // VERBOSE_LOGGING_END
                            }
                        }
                    } else {
                        logMessage("[{$this->name}:{$herikaName}] Failed to parse JSON: " . json_last_error_msg());

                        // VERBOSE_LOGGING_START - processActions: JSON parse failed
                        if ($this->_verboseLogging) {
                            logMessage("[CACHE-VERBOSE] ✗ Failed to parse JSON for actions: " . json_last_error_msg());
                        }
                        // VERBOSE_LOGGING_END
                    }
                } else {
                    // VERBOSE_LOGGING_START - processActions: No valid JSON found
                    if ($this->_verboseLogging) {
                        logMessage("[CACHE-VERBOSE] No valid JSON boundaries found in buffer");
                    }
                    // VERBOSE_LOGGING_END
                }
            }
        } else {
            // Simple format action processing
            $parsed = extractSimpleFormatFromBuffer(
                $this->_buffer,
                $this->_includeMood,
                $this->_includeListener,
                $this->_includeActions,
                $this->_includeTarget
            );

            // VERBOSE_LOGGING_START - processActions: Simple format parsed
            if ($this->_verboseLogging) {
                logMessage("[CACHE-VERBOSE] Simple format parsed for actions, found: " . ($parsed['found'] ? 'YES' : 'NO'));
                if ($parsed['found'] && $this->_includeActions) {
                    logMessage("[CACHE-VERBOSE] Action in simple format: " . (isset($parsed['action']) && !empty($parsed['action']) ? $parsed['action'] : 'NONE'));
                }
            }
            // VERBOSE_LOGGING_END

            if ($parsed['found'] && $this->_includeActions && !empty($parsed['action'])) {
                $action = validateActionName($parsed['action']);
                $target = $this->_includeTarget && !empty($parsed['target']) ? $parsed['target'] : $this->_defaultTarget;
                $character = $herikaName;

                $commandKey = md5("{$character}|command|{$action}@{$target}\r\n");

                if (!isset($alreadysent[$commandKey]) || empty($alreadysent[$commandKey])) {
                    $functionCodeName = function_exists('getFunctionCodeName') ? getFunctionCodeName($action) : $action;
                    $functionCodeName = empty($functionCodeName) ? $action : $functionCodeName;

                    $commandString = "{$character}|command|{$functionCodeName}@{$target}\r\n";
                    $this->_commandBuffer[] = $commandString;
                    $alreadysent[$commandKey] = $commandString;

                    logMessage("[{$this->name}:{$herikaName}] Generated command from simple format: {$commandString}");

                    // VERBOSE_LOGGING_START - processActions: Command generated (simple)
                    if ($this->_verboseLogging) {
                        logMessage("[CACHE-VERBOSE] Command generated (simple format): {$commandString}");
                        logMessage("[CACHE-VERBOSE] Validated action: {$action}");
                        logMessage("[CACHE-VERBOSE] Target: " . ($target ?: 'NONE'));
                    }
                    // VERBOSE_LOGGING_END
                } else {
                    // VERBOSE_LOGGING_START - processActions: Duplicate command skipped (simple)
                    if ($this->_verboseLogging) {
                        logMessage("[CACHE-VERBOSE] Command skipped (duplicate, simple format): {$action}@{$target}");
                    }
                    // VERBOSE_LOGGING_END
                }
            }
        }

        // Also process tool calls if any (JSON mode fallback)
        if (!empty($this->_jsonResponsesEncoded)) {
            // Note: Tool call processing would go here if needed
            // For now, focusing on JSON response format and simple format
        }

        $this->_jsonResponsesEncoded = array();

        if (!empty($this->_commandBuffer)) {
            logMessage("[{$this->name}:{$herikaName}] Final Command Buffer: " . implode(", ", $this->_commandBuffer));

            // VERBOSE_LOGGING_START - processActions: Commands finalized
            if ($this->_verboseLogging) {
                logMessage("[CACHE-VERBOSE] Total commands generated: " . count($this->_commandBuffer));
                logMessage("[CACHE-VERBOSE] Commands: " . implode(", ", $this->_commandBuffer));
            }
            // VERBOSE_LOGGING_END
        } else {
            logMessage("[{$this->name}:{$herikaName}] No commands generated.");

            // VERBOSE_LOGGING_START - processActions: No commands
            if ($this->_verboseLogging) {
                logMessage("[CACHE-VERBOSE] No commands generated from response");
            }
            // VERBOSE_LOGGING_END
        }

        // VERBOSE_LOGGING_START - processActions: Complete
        if ($this->_verboseLogging) {
            logMessage("[CACHE-VERBOSE] ===== ACTION PROCESSING COMPLETE =====");
        }
        // VERBOSE_LOGGING_END

        return empty($this->_commandBuffer) ? array() : $this->_commandBuffer;
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
