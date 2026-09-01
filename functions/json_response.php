<?php
    require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."emote_moods.php");

    global $FUNC_LIST;
    global $responseTemplate;
    global $structuredOutputTemplate;
    global $grammar;
    $FUNC_LIST=[];
    $responseTemplate=[];
    $structuredOutputTemplate=array();
    $grammar = "";

    if (!function_exists('chimIsDirectNarratorDialogue')) {
        function chimIsDirectNarratorDialogue() {
            if (isset($GLOBALS["DIRECT_NARRATOR_DIALOGUE"])) {
                return (bool)$GLOBALS["DIRECT_NARRATOR_DIALOGUE"];
            }

            return isset($GLOBALS["gameRequest"][0]) && $GLOBALS["gameRequest"][0] === "narrator_inputtext";
        }
    }

    if (!function_exists('chimIsVisionRequest')) {
        function chimIsVisionRequest() {
            return isset($GLOBALS["gameRequest"][0]) && $GLOBALS["gameRequest"][0] === "vision";
        }
    }

    if (!function_exists('chimShouldExposePromptActions')) {
        function chimShouldExposePromptActions() {
            if (chimIsVisionRequest()) {
                return false;
            }

            if (chimIsDirectNarratorDialogue()) {
                return true;
            }

            return isset($GLOBALS["FUNCTIONS_ARE_ENABLED"]) && $GLOBALS["FUNCTIONS_ARE_ENABLED"];
        }
    }

    if (!function_exists('chimApplyJsonTemplateHooks')) {
        function chimApplyJsonTemplateHooks() {
            if (isset($GLOBALS["HOOKS"]) && isset($GLOBALS["HOOKS"]["JSON_TEMPLATE"]) && is_array($GLOBALS["HOOKS"]["JSON_TEMPLATE"])) {
                foreach ($GLOBALS["HOOKS"]["JSON_TEMPLATE"] as $hook) {
                    call_user_func($hook);
                }
            }
        }
    }

    if (!function_exists('chimEnsureRecursiveRequireHelper')) {
        function chimEnsureRecursiveRequireHelper() {
            if (!function_exists('requireFilesRecursively')) {
                require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."data_functions.php");
            }
        }
    }

    if (!function_exists('chimRefreshJsonResponseState')) {
        function chimRefreshJsonResponseState($loadExtensionCustomizers = false) {
            global $FUNC_LIST;
            global $responseTemplate;
            global $structuredOutputTemplate;
            global $grammar;

            $FUNC_LIST = [];
            $responseTemplate = [];
            $structuredOutputTemplate = array();
            $grammar = "";

            setActions();
            setResponseTemplate();
            setStructuredOutputTemplate();
            setGBNFGrammar();

            if ($loadExtensionCustomizers && empty($GLOBALS["CHIM_JSON_RESPONSE_EXT_LOADED"])) {
                // Allow one-time direct template edits from extensions on initial load.
                chimEnsureRecursiveRequireHelper();
                requireFilesRecursively(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR,"json_response_custom.php");
                $GLOBALS["CHIM_JSON_RESPONSE_EXT_LOADED"] = true;
            }

            chimApplyJsonTemplateHooks();
        }
    }

    if (!function_exists('chimGetNarratorJsonResponseStateSummary')) {
        function chimGetNarratorJsonResponseStateSummary(): array
        {
            $actionTemplate = trim(strval($GLOBALS["responseTemplate"]["action"] ?? ""));
            $funcList = array_values(array_filter(
                is_array($GLOBALS["FUNC_LIST"] ?? null) ? $GLOBALS["FUNC_LIST"] : [],
                function ($value) {
                    return trim(strval($value)) !== "";
                }
            ));

            $structuredActionProperty = $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["properties"]["action"] ?? null;
            $structuredActionEnum = [];
            if (is_array($structuredActionProperty) && isset($structuredActionProperty["enum"]) && is_array($structuredActionProperty["enum"])) {
                $structuredActionEnum = array_values(array_filter($structuredActionProperty["enum"], function ($value) {
                    return trim(strval($value)) !== "";
                }));
            }

            $hasOnlyTalkAction = count($funcList) === 1 && strcasecmp($funcList[0], "Talk") === 0;

            $needsRefresh = chimIsDirectNarratorDialogue() && (
                empty($GLOBALS["PROMPT_ACTIONS_LIST"])
                || empty($funcList)
                || $actionTemplate === ""
                || strcasecmp($actionTemplate, "Talk") === 0
                || empty($structuredActionEnum)
                || $hasOnlyTalkAction
            );

            return [
                "request" => strtolower(trim(strval($GLOBALS["gameRequest"][0] ?? ''))),
                "direct_flag" => !empty($GLOBALS["DIRECT_NARRATOR_DIALOGUE"]) ? '1' : '0',
                "herika" => strval($GLOBALS["HERIKA_NAME"] ?? ''),
                "func_count" => count($funcList),
                "prompt_actions_len" => strlen(strval($GLOBALS["PROMPT_ACTIONS_LIST"] ?? "")),
                "response_action" => $actionTemplate,
                "structured_action_count" => count($structuredActionEnum),
                "has_only_talk_action" => $hasOnlyTalkAction,
                "needs_refresh" => $needsRefresh,
            ];
        }
    }

    if (!function_exists('chimFormatNarratorJsonResponseStateSummary')) {
        function chimFormatNarratorJsonResponseStateSummary(?array $summary = null): string
        {
            $summary = $summary ?? chimGetNarratorJsonResponseStateSummary();

            return "request=" . strval($summary["request"] ?? '') .
                " direct_flag=" . strval($summary["direct_flag"] ?? '0') .
                " herika=" . strval($summary["herika"] ?? '') .
                " func_count=" . strval($summary["func_count"] ?? 0) .
                " prompt_actions_len=" . strval($summary["prompt_actions_len"] ?? 0) .
                " response_action=" . strval($summary["response_action"] ?? '') .
                " structured_action_count=" . strval($summary["structured_action_count"] ?? 0) .
                " has_only_talk_action=" . (!empty($summary["has_only_talk_action"]) ? '1' : '0');
        }
    }

    if (!function_exists('chimNarratorJsonResponseNeedsRefresh')) {
        function chimNarratorJsonResponseNeedsRefresh(): bool
        {
            if (!chimIsDirectNarratorDialogue()) {
                return false;
            }

            $summary = chimGetNarratorJsonResponseStateSummary();
            return !empty($summary["needs_refresh"]);
        }
    }

    if (!function_exists('chimEnsureNarratorJsonResponseState')) {
        function chimEnsureNarratorJsonResponseState($logContext = 'JSON_RESPONSE')
        {
            if (!function_exists('chimRefreshJsonResponseState')) {
                return;
            }

            $requestType = strtolower(trim(strval($GLOBALS["gameRequest"][0] ?? '')));
            $directNarratorDialogue = chimIsDirectNarratorDialogue();
            if (!$directNarratorDialogue) {
                if ($requestType === 'narrator_inputtext' || strcasecmp(trim(strval($GLOBALS["HERIKA_NAME"] ?? '')), 'The Narrator') === 0) {
                    Logger::warn("[{$logContext}] Skipping narrator JSON refresh because chimIsDirectNarratorDialogue() is false (" . chimFormatNarratorJsonResponseStateSummary() . ")");
                }
                return;
            }

            $stateSummary = chimGetNarratorJsonResponseStateSummary();
            if (empty($stateSummary["needs_refresh"])) {
                return;
            }

            Logger::warn("[{$logContext}] Rebuilding narrator JSON response state because prompt actions/schema were incomplete (" . chimFormatNarratorJsonResponseStateSummary($stateSummary) . ")");
            chimRefreshJsonResponseState();
            $stateSummary = chimGetNarratorJsonResponseStateSummary();
            if (!empty($stateSummary["needs_refresh"])) {
                Logger::warn("[{$logContext}] Narrator JSON response state still incomplete after rebuild (" . chimFormatNarratorJsonResponseStateSummary($stateSummary) . ")");
            }
        }
    }

    // specify the available actions which will be made available in the context
    Function setActions() {
        $promptCharacterName = function_exists('chimGetPromptCharacterName')
            ? chimGetPromptCharacterName()
            : ($GLOBALS["HERIKA_NAME"] ?? 'The Narrator');
        // Initialize actions list
        $GLOBALS["PROMPT_ACTIONS_LIST"] = "";
        
        // Narration-style requests should not browse the full action catalog, but
        // they still need a stable Talk action in the response schema.
        if (isset($GLOBALS["gameRequest"]) && in_array($GLOBALS["gameRequest"][0], ["narration", "vision"], true)) {
            $GLOBALS["FUNC_LIST"] = ["Talk"];
            return;
        }

        $shouldExposePromptActions = chimShouldExposePromptActions();
        if ($shouldExposePromptActions && empty($GLOBALS["FUNCTIONS_ARE_ENABLED"])) {
            $GLOBALS["FUNCTIONS_ARE_ENABLED"] = true;
        }

        // Build actions list separately (not in PROMPT_HEAD)
        if ($shouldExposePromptActions) {
            $GLOBALS["PROMPT_ACTIONS_LIST"] = "\n<available_actions_list>\n";
            $GLOBALS["PROMPT_ACTIONS_LIST"] .= $GLOBALS["COMMAND_PROMPT_FUNCTIONS"];
            
            foreach ($GLOBALS["FUNCTIONS"] as $index => $function) {
                if (!$function) {
                    continue;
                }
                
                $fname=getFunctionCodeName($function["name"]);

                if (!in_array($fname,$GLOBALS["ENABLED_FUNCTIONS"])) {
                    error_log("[FUNCTIONS] Skipping disabled function: {$function["name"]} <$fname>");
                    continue;
                } else {
                    error_log("[FUNCTIONS] NOT Skipping function: {$function["name"]} <$fname>");
                }

                $actionDescription = function_exists('herikaGetPromptActionDescription')
                    ? herikaGetPromptActionDescription($fname, $function["description"] ?? '')
                    : strval($function["description"] ?? '');

                $GLOBALS["FUNC_LIST"][]=$function["name"];
                if ($function["name"]==$GLOBALS["F_NAMES"]["Attack"] || $function["name"]==$GLOBALS["F_NAMES"]["Brawl"]) {
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$actionDescription})";
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="(available targets: ".implode(",",$GLOBALS["FUNCTION_PARM_INSPECT"]).")";
                } else if ($fname == "GiveGoldTo") {
                    require_once(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "data_functions.php");
                    $goldAmount = getGoldFromMetadata();
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$actionDescription}). You currently have {$goldAmount} gold. Put the amount in the 'item' field.";
                } else if ($fname == "HireCarriage") {
                    $majorDestinations = "Whiterun, Solitude, Markarth, Riften, Windhelm";
                    $minorDestinations = "Morthal, Dawnstar, Falkreath, Winterhold, Darkwater Crossing, Dragon Bridge, Ivarstead, Karthwasten, Kynesgrove, Old Hroldan, Riverwood, Rorikstead, Shor's Stone, Stonehills";
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$actionDescription}). Vanilla carriage costs: 20 gold for major destinations ({$majorDestinations}) and 50 gold for minor destinations ({$minorDestinations}). Put the destination in the 'target' field. Keep the spoken line short, accept payment, and do not ask questions.";
                } else if ($fname == "HireFerry") {
                    $fiftyGoldDestinations = "Windhelm, Dawnstar, Solitude, Giant's Tooth";
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$actionDescription}). Vanilla ferry costs: 50 gold for {$fiftyGoldDestinations}, 500 gold for Icewater Jetty, and free travel to Castle Volkihar. Put the destination in the 'target' field. Keep the spoken line short, accept payment when needed, and do not ask questions.";
                } else if ($fname == "AddBounty") {
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$actionDescription}). Crime types and vanilla bounty amounts: Assault=40 (violent), Murder=1000 (violent), Theft=100, Pickpocketing=25, Trespassing=5, Jailbreak=100, Custom (specify amount in 'item' field). Put the crime type in 'target'.";
                } else if ($fname == "PayBounty") {
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$actionDescription}). Use when {$GLOBALS["PLAYER_NAME"]} agrees to pay now. Bounty payment and stolen-item confiscation happen immediately in one step. After using it, reply with a short confirmation and end the conversation.";
                } else if ($fname == "ArrestPlayer") {
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$actionDescription}). Use for serious crimes or if {$GLOBALS["PLAYER_NAME"]} refuses to pay their bounty. {$GLOBALS["PLAYER_NAME"]} gets a submit/resist popup. Submit sends them to jail with inventory confiscated. Resist makes guards attack.";
                } else if ($fname == "ForgiveCrime") {
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$actionDescription}). Use when {$GLOBALS["PLAYER_NAME"]} successfully persuades, bribes, or invokes thane status to clear their bounty.";
                } else if ($fname == "CreateTasks") {
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$actionDescription}). When accepting, promising, remembering, or scheduling a future duty, use this action instead of Talk. Include any known details in 'action_params'; the server will complete the structured task in the background.";
                } else if ($fname == "ResolveTask") {
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$actionDescription}). Put task_id, status, and outcome in the 'action_params' object.";
                } else if ($fname == "CancelTask") {
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$actionDescription}). Put task_id and reason in the 'action_params' object.";
                } else if ($fname == "TeachRightHandSpell") {
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$actionDescription}). Do not put anything in the 'target' or 'item' field. This action automatically teaches whatever spell {$GLOBALS["PLAYER_NAME"]} currently has equipped in the right hand.";
                } else if ($fname == "Consume") {
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$actionDescription}). Put the exact BaseID:ItemName identifier from <inventory> in the 'target' field. Only use this for food, drinks, or potions already in inventory. Leave 'item' blank unless you need it as a fallback copy of the same BaseID:ItemName identifier. The spoken reply for this action happens after the item is consumed, so use it only when {$promptCharacterName} is actually going to eat or drink the item.";
                } else if ($fname == "SpawnItem") {
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$actionDescription}). Put the recipient in the 'target' field, the item name in the 'item' field, and the quantity in the 'amount' field. Use '{$GLOBALS["PLAYER_NAME"]}', 'PLAYER', or 'me' to give the item to the player.";
                } else if ($fname == "SpawnGold") {
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$actionDescription}). Put the recipient in the 'target' field, put the gold amount in the 'amount' field, and leave 'item' blank. Use '{$GLOBALS["PLAYER_NAME"]}', 'PLAYER', or 'me' to give the gold to the player.";
                } else if ($fname == "SpawnNPC") {
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$actionDescription}). Put the SNQE NPC template key in the 'target' field, leave 'item' blank, and put the spawn count in the 'amount' field.";
                } else if ($fname == "CreateNewNPC") {
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$actionDescription}). Put a short creation brief for the new NPC in the 'target' field. Leave 'item' and 'amount' blank.";
                } else if ($fname == "DirectorCommand") {
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$actionDescription}). Put a short freeform director brief in the 'target' field. Leave 'item' and 'amount' blank.";
                } else if ($fname == "TeleportNPC") {
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$actionDescription}). Put who to teleport in the 'target' field and the destination location name in the 'item' field. Use '{$GLOBALS["PLAYER_NAME"]}', 'PLAYER', or 'me' to teleport the player.";
                } else if ($fname == "KillTarget") {
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$actionDescription}). Put only the victim in the 'target' field. Leave 'item' and 'amount' blank.";
                } else {
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$actionDescription})";
                }
            }
            
            $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: Talk (default action, used when no other action is suitable)\n</available_actions_list>";
            $GLOBALS["FUNC_LIST"][]="Talk";
            shuffle($GLOBALS["FUNC_LIST"]);
        }
    }

    if (!function_exists('chimGetSupplementalActionParameterProperties')) {
        function chimGetSupplementalActionParameterProperties(): array
        {
            $standardProperties = array_fill_keys([
                'character', 'listener', 'message', 'mood', 'action',
                'target', 'item', 'amount', 'lang', 'emotion', 'emotion_intensity',
            ], true);
            $availableActionNames = array_fill_keys(
                array_map('strval', is_array($GLOBALS['FUNC_LIST'] ?? null) ? $GLOBALS['FUNC_LIST'] : []),
                true
            );
            $supplemental = [];

            foreach ((is_array($GLOBALS['FUNCTIONS'] ?? null) ? $GLOBALS['FUNCTIONS'] : []) as $function) {
                if (!is_array($function)) {
                    continue;
                }

                $actionName = trim(strval($function['name'] ?? ''));
                if ($actionName === '' || !isset($availableActionNames[$actionName])) {
                    continue;
                }

                $properties = $function['parameters']['properties'] ?? [];
                if (!is_array($properties)) {
                    continue;
                }

                foreach ($properties as $parameterName => $parameterSchema) {
                    $parameterName = trim(strval($parameterName));
                    if ($parameterName === '' || isset($standardProperties[$parameterName]) || !is_array($parameterSchema)) {
                        continue;
                    }

                    $schema = $parameterSchema;
                    $description = trim(strval($schema['description'] ?? ''));
                    $schema['description'] = "For {$actionName}: " . ($description !== '' ? $description : "value for {$parameterName}.");

                    if (!isset($supplemental[$parameterName])) {
                        $supplemental[$parameterName] = $schema;
                        continue;
                    }

                    $existingDescription = trim(strval($supplemental[$parameterName]['description'] ?? ''));
                    if ($existingDescription !== '' && strpos($existingDescription, $schema['description']) === false) {
                        $supplemental[$parameterName]['description'] = $existingDescription . ' ' . $schema['description'];
                    }
                    if (isset($schema['enum']) && is_array($schema['enum'])) {
                        $existingEnum = is_array($supplemental[$parameterName]['enum'] ?? null)
                            ? $supplemental[$parameterName]['enum']
                            : [];
                        $supplemental[$parameterName]['enum'] = array_values(array_unique(array_merge($existingEnum, $schema['enum'])));
                    }
                }
            }

            return $supplemental;
        }
    }

    if (!function_exists('chimBuildSupplementalActionParameterPrompt')) {
        function chimBuildSupplementalActionParameterPrompt(array $properties): array
        {
            $prompt = [];
            foreach ($properties as $parameterName => $schema) {
                $description = trim(strval($schema['description'] ?? ''));
                if (isset($schema['enum']) && is_array($schema['enum']) && count($schema['enum']) > 0) {
                    $description .= ' Allowed values: ' . implode('|', array_map('strval', $schema['enum'])) . '.';
                }
                $prompt[$parameterName] = $description !== '' ? $description : 'Action-specific value.';
            }
            return $prompt;
        }
    }

    chimRefreshJsonResponseState(true);

    // specify the json object that will be requested from the LLM (via prompt, not enforced)
    Function setResponseTemplate() {
        $promptCharacterName = function_exists('chimGetPromptCharacterName')
            ? chimGetPromptCharacterName()
            : ($GLOBALS["HERIKA_NAME"] ?? 'The Narrator');
        $moods=normalizeEmoteMoods($GLOBALS["EMOTEMOODS"] ?? "");
        shuffle($moods);
        $moodDescription = empty($moods)
            ? "choose exactly one mood while speaking, never combine moods"
            : "choose exactly one mood while speaking from this list, never combine moods: ".implode("|", $moods);
    
        // Auto-detect language from TTS config if LLM_LANG not set
        if (!isset($GLOBALS["LLM_LANG"]) && isset($GLOBALS["LANG_LLM_XTTS"]) && $GLOBALS["LANG_LLM_XTTS"]) {
            if (isset($GLOBALS["TTS"]["XTTSFASTAPI"]["language"])) {
                $GLOBALS["LLM_LANG"] = $GLOBALS["TTS"]["XTTSFASTAPI"]["language"];
            } elseif (isset($GLOBALS["TTS"]["OMNIVOICE"]["language"])) {
                $GLOBALS["LLM_LANG"] = $GLOBALS["TTS"]["OMNIVOICE"]["language"];
            } elseif (isset($GLOBALS["TTS"]["CHATTERBOX"]["language"])) {
                $GLOBALS["LLM_LANG"] = $GLOBALS["TTS"]["CHATTERBOX"]["language"];
            } elseif (isset($GLOBALS["TTS"]["POCKETTTS"]["language"])) {
                $GLOBALS["LLM_LANG"] = $GLOBALS["TTS"]["POCKETTTS"]["language"];
            } elseif (isset($GLOBALS["TTS"]["MELOTTS"]["language"])) {
                $ttsLang = strtolower($GLOBALS["TTS"]["MELOTTS"]["language"]);
                $GLOBALS["LLM_LANG"] = $ttsLang;
            }
        }
    
        // Build listener description - for rechat events, encourage addressing the previous speaker
            $listenerDesc = "specify who {$promptCharacterName} is talking to, comma separated, max two listeners, in addressing order";
        if (chimIsVisionRequest()) {
            $listenerDesc = "leave blank unless {$promptCharacterName} directly addresses someone while explaining the Soulgaze vision";
        } elseif (
            isset($GLOBALS["gameRequest"]) &&
            (
                (function_exists('chimIsStrictResponsePromptContext') && chimIsStrictResponsePromptContext()) ||
                in_array($GLOBALS["gameRequest"][0], ["rechat"], true)
            )
        ) {
            $listenerDesc = function_exists('chimLoadManagedRechatListenerPrompt')
                ? chimLoadManagedRechatListenerPrompt()
                : "specify who {$promptCharacterName} is talking to. Address whoever just spoke - can be any person in the conversation.";
        }
    
        // Determine message description based on inline narration mode.
        $inlineNarrationMode = strtolower(trim((string)($GLOBALS["INLINE_NARRATION_MODE"] ?? '')));
        if (!in_array($inlineNarrationMode, ['disabled', 'narrator', 'npc', 'text_only'], true)) {
            $inlineNarrationMode = (isset($GLOBALS["INLINE_NARRATION_ENABLED"]) && $GLOBALS["INLINE_NARRATION_ENABLED"]) ? 'narrator' : 'disabled';
        }
        if (chimIsDirectNarratorDialogue()) {
            $inlineNarrationMode = 'disabled';
        }
        $inlineNarrationEnabled = $inlineNarrationMode !== 'disabled';
        $messageDescription = "lines of dialogue";
        if (chimIsVisionRequest()) {
            $messageDescription = "{$promptCharacterName}'s spoken Soulgaze explanation of the current scene. Describe only what is visibly present right now through {$GLOBALS["PLAYER_NAME"]}'s eyes, focusing on people, environment, objects, and immediate activity. Do not continue unrelated conversation, do not answer stale dialogue, and do not invent unseen details.";
        } elseif ($inlineNarrationEnabled) {
            $messageDescription = "If needed, start with one brief third-person narration block in single asterisks, then put {$promptCharacterName}'s spoken text after it. Example: *She smiles* It's good to see you again, my friend! Do not wrap the entire reply in asterisks, and keep spoken dialogue outside the asterisks.";
        } elseif (chimIsDirectNarratorDialogue()) {
            $messageDescription = "plain spoken dialogue addressed directly to {$GLOBALS["PLAYER_NAME"]}. Keep the spoken reply consistent with the chosen narrator action when you use one. Do not include third-person narration, scene description, stage directions, or text in asterisks.";
        }
    
        if (isset($GLOBALS["FEATURES"]["MISC"]["JSON_DIALOGUE_FORMAT_REORDER"])&&($GLOBALS["FEATURES"]["MISC"]["JSON_DIALOGUE_FORMAT_REORDER"])) {
            if (isset($GLOBALS["LANG_LLM_XTTS"])&&($GLOBALS["LANG_LLM_XTTS"])) {
                $GLOBALS["responseTemplate"] = [
                    "character"=>$GLOBALS["HERIKA_NAME"],
                    "listener"=>$listenerDesc,
                    "message"=>$messageDescription,
                    "mood"=>$moodDescription,
                    "action"=>implode("|",$GLOBALS["FUNC_LIST"]),
                    "target"=>"action target actor (prefer exact Name [RefID: XXXXXXXX] from people_present, otherwise use actor name). For TeleportNPC, this is the actor to teleport. For SpawnItem and SpawnGold, this is the actor who should receive the spawned item or gold. For SpawnNPC, this is the SNQE NPC template key to spawn near {$GLOBALS["PLAYER_NAME"]}. For KillTarget, this is the actor to kill. For CreateNewNPC, this is a short creation brief for the new nearby NPC. For DirectorCommand, this is a short freeform director brief describing the scene instruction or event to stage. Use '{$GLOBALS["PLAYER_NAME"]}', PLAYER, or me for player-targeted narrator actions. Leave blank when the chosen action does not need a target.",
                    "item"=>"item identifier (REQUIRED for GiveItemTo: use exact BaseID:ItemName from inventory; for Take_Held_Item: use exact RefID:ItemName from shown in <held_items>, for PickupItem: use exact RefID:ItemName from nearby_items; for CastSpell: use exact spell name from spells) OR amount of gold (REQUIRED when action is GiveGoldTo - number as string, e.g. '50') OR destination location name (REQUIRED when action is TeleportNPC) OR item name from the descriptions database (REQUIRED when action is SpawnItem). Leave blank when the chosen action does not need an item, including SpawnGold and SpawnNPC and CreateNewNPC and DirectorCommand.",
                    "amount"=>"quantity to give or spawn only when the chosen action supports it. REQUIRED when action is SpawnItem or SpawnNPC or SpawnGold. Optional when action is GiveItemTo. Leave blank for other actions such as KillTarget or TeleportNPC or CreateNewNPC or DirectorCommand. Use a positive integer when needed.",
                    "lang"=>isset($GLOBALS["LLM_LANG"])?$GLOBALS["LLM_LANG"]:"en|es|fr|de|it|pt|ru|zh-cn|ja|ko|ar|pl|tr|cs|nl|hu|hi",
                ];
            } else {
                $GLOBALS["responseTemplate"] = [
                    "character"=>$GLOBALS["HERIKA_NAME"],
                    "listener"=>$listenerDesc,
                    "message"=>$messageDescription,
                    "mood"=>$moodDescription,
                    "action"=>implode("|",$GLOBALS["FUNC_LIST"]),
                    "target"=>"action target actor (prefer exact Name [RefID: XXXXXXXX] from people_present, otherwise use actor name). For TeleportNPC, this is the actor to teleport. For SpawnItem and SpawnGold, this is the actor who should receive the spawned item or gold. For SpawnNPC, this is the SNQE NPC template key to spawn near {$GLOBALS["PLAYER_NAME"]}. For KillTarget, this is the actor to kill. For CreateNewNPC, this is a short creation brief for the new nearby NPC. For DirectorCommand, this is a short freeform director brief describing the scene instruction or event to stage. Use '{$GLOBALS["PLAYER_NAME"]}', PLAYER, or me for player-targeted narrator actions. Leave blank when the chosen action does not need a target.",
                    "item"=>"item identifier (REQUIRED for GiveItemTo: use exact BaseID:ItemName from inventory; for Take_Held_Item: use exact RefID:ItemName from shown in <held_items>,for PickupItem: use exact RefID:ItemName from nearby_items; for CastSpell: use exact spell name from spells) OR amount of gold (REQUIRED when action is GiveGoldTo - number as string, e.g. '50') OR destination location name (REQUIRED when action is TeleportNPC) OR item name from the descriptions database (REQUIRED when action is SpawnItem). Leave blank when the chosen action does not need an item, including SpawnGold and SpawnNPC and CreateNewNPC and DirectorCommand.",
                    "amount"=>"quantity to give or spawn only when the chosen action supports it. REQUIRED when action is SpawnItem or SpawnNPC or SpawnGold. Optional when action is GiveItemTo. Leave blank for other actions such as KillTarget or TeleportNPC or CreateNewNPC or DirectorCommand. Use a positive integer when needed."
                ];
            }
        } else {
            if (isset($GLOBALS["LANG_LLM_XTTS"])&&($GLOBALS["LANG_LLM_XTTS"])) {
                $GLOBALS["responseTemplate"] = [
                    "character"=>$GLOBALS["HERIKA_NAME"],
                    "listener"=>$listenerDesc,
                    "mood"=>$moodDescription,
                    "action"=>implode("|",$GLOBALS["FUNC_LIST"]),
                    "target"=>"action target actor (prefer exact Name [RefID: XXXXXXXX] from people_present, otherwise use actor name). For TeleportNPC, this is the actor to teleport. For SpawnItem and SpawnGold, this is the actor who should receive the spawned item or gold. For SpawnNPC, this is the SNQE NPC template key to spawn near {$GLOBALS["PLAYER_NAME"]}. For KillTarget, this is the actor to kill. For CreateNewNPC, this is a short creation brief for the new nearby NPC. For DirectorCommand, this is a short freeform director brief describing the scene instruction or event to stage. Use '{$GLOBALS["PLAYER_NAME"]}', PLAYER, or me for player-targeted narrator actions. Leave blank when the chosen action does not need a target.",
                    "item"=>"item identifier (REQUIRED for GiveItemTo: use exact BaseID:ItemName from inventory; for Take_Held_Item: use exact RefID:ItemName from shown in <held_items>,for PickupItem: use exact RefID:ItemName from nearby_items; for CastSpell: use exact spell name from spells) OR amount of gold (REQUIRED when action is GiveGoldTo - number as string, e.g. '50') OR destination location name (REQUIRED when action is TeleportNPC) OR item name from the descriptions database (REQUIRED when action is SpawnItem). Leave blank when the chosen action does not need an item, including SpawnGold and SpawnNPC and CreateNewNPC and DirectorCommand.",
                    "amount"=>"quantity to give or spawn only when the chosen action supports it. REQUIRED when action is SpawnItem or SpawnNPC or SpawnGold. Optional when action is GiveItemTo. Leave blank for other actions such as KillTarget or TeleportNPC or CreateNewNPC or DirectorCommand. Use a positive integer when needed.",
                    "lang"=>isset($GLOBALS["LLM_LANG"])?$GLOBALS["LLM_LANG"]:"en|es|fr|de|it|pt|ru|zh-cn|ja|ko|ar|pl|tr|cs|nl|hu|hi",
                    "message"=>$messageDescription
                ];
            } else {
                $GLOBALS["responseTemplate"] = [
                    "character"=>$GLOBALS["HERIKA_NAME"],
                    "listener"=>$listenerDesc,
                    "mood"=>$moodDescription,
                    "action"=>implode("|",$GLOBALS["FUNC_LIST"]),
                    "target"=>"action target actor (prefer exact Name [RefID: XXXXXXXX] from people_present, otherwise use actor name). For TeleportNPC, this is the actor to teleport. For SpawnItem and SpawnGold, this is the actor who should receive the spawned item or gold. For SpawnNPC, this is the SNQE NPC template key to spawn near {$GLOBALS["PLAYER_NAME"]}. For KillTarget, this is the actor to kill. For CreateNewNPC, this is a short creation brief for the new nearby NPC. Use '{$GLOBALS["PLAYER_NAME"]}', PLAYER, or me for player-targeted narrator actions. Leave blank when the chosen action does not need a target.",
                    "item"=>"item identifier (REQUIRED for GiveItemTo: use exact BaseID:ItemName from inventory; for Take_Held_Item: use exact RefID:ItemName from shown in <held_items>, for PickupItem: use exact RefID:ItemName from nearby_items; for CastSpell: use exact spell name from spells) OR destination location name (REQUIRED when action is TeleportNPC) OR item name from the descriptions database (REQUIRED when action is SpawnItem). Leave blank when the chosen action does not need an item, including SpawnGold and SpawnNPC and CreateNewNPC.",
                    "amount"=>"quantity to give or spawn only when the chosen action supports it. REQUIRED when action is SpawnItem or SpawnNPC or SpawnGold. Optional when action is GiveItemTo. Leave blank for other actions such as KillTarget or TeleportNPC or CreateNewNPC. Use a positive integer when needed.",
                    "message"=>$messageDescription
                ];
            }
        }

        $supplementalActionParameters = chimGetSupplementalActionParameterProperties();
        if (count($supplementalActionParameters) > 0) {
            $GLOBALS["responseTemplate"]["action_params"] = chimBuildSupplementalActionParameterPrompt($supplementalActionParameters);
        }

        // emotions expression:
        if (isset($GLOBALS['use_emotions_expression']) && $GLOBALS['use_emotions_expression']) {
            if (!array_key_exists("emotion", $GLOBALS["responseTemplate"])) {
                $GLOBALS["responseTemplate"]["emotion"] = 
                "calm|surprised|aroused|desire|love|happy|amusement|gratitude|proud|anxious|fearful|panic|grieving|envious|jealous|sad|disappointed|ashamed|angry|offended|disgusted|sarcastic";
            }
            if (!array_key_exists("emotion_intensity", $GLOBALS["responseTemplate"])) {
                $GLOBALS["responseTemplate"]["emotion_intensity"] = "low|moderate|strong";
            }
        }
        
        // request speaking tones from the LLM when using zonos TTS
        if (zonosIsActive()) {
            $GLOBALS["responseTemplate"] = array_merge($GLOBALS["responseTemplate"], [
                "response_tone_happiness"=>"Value from 0-1",
                "response_tone_sadness"=>"Value from 0-1",
                "response_tone_disgust"=>"Value from 0-1",
                "response_tone_fear"=>"Value from 0-1",
                "response_tone_surprise"=>"Value from 0-1",
                "response_tone_anger"=>"Value from 0-1",
                "response_tone_other"=>"Value from 0-1",
                "response_tone_neutral"=>"Value from 0-1"
            ]);
        }
    }
    
    // for use with openai and openrouter providers that support structured outputs to enforce a json schema
    Function setStructuredOutputTemplate() {
        $promptCharacterName = function_exists('chimGetPromptCharacterName')
            ? chimGetPromptCharacterName()
            : ($GLOBALS["HERIKA_NAME"] ?? 'The Narrator');
        $moods=normalizeEmoteMoods($GLOBALS["EMOTEMOODS"] ?? "");
        shuffle($moods);
        $moodDescription = "choose exactly one mood while speaking, never combine moods";
        $listenerDescription = chimIsVisionRequest()
            ? "leave blank unless {$promptCharacterName} directly addresses someone while explaining the Soulgaze vision"
            : "specify who {$promptCharacterName} is talking to, comma separated, max two listeners, in addressing order";

        // Determine message description based on inline narration mode.
        $inlineNarrationMode = strtolower(trim((string)($GLOBALS["INLINE_NARRATION_MODE"] ?? '')));
        if (!in_array($inlineNarrationMode, ['disabled', 'narrator', 'npc', 'text_only'], true)) {
            $inlineNarrationMode = (isset($GLOBALS["INLINE_NARRATION_ENABLED"]) && $GLOBALS["INLINE_NARRATION_ENABLED"]) ? 'narrator' : 'disabled';
        }
        if (chimIsDirectNarratorDialogue()) {
            $inlineNarrationMode = 'disabled';
        }
        $inlineNarrationEnabled = $inlineNarrationMode !== 'disabled';
        $messageDescription = "lines of {$promptCharacterName}'s dialogue";
        if (chimIsVisionRequest()) {
            $messageDescription = "{$promptCharacterName}'s spoken Soulgaze explanation of the current scene. Describe only what is visibly present right now through {$GLOBALS["PLAYER_NAME"]}'s eyes, focusing on people, environment, objects, and immediate activity. Do not continue unrelated conversation, do not answer stale dialogue, and do not invent unseen details.";
        } elseif ($inlineNarrationEnabled) {
            $messageDescription = "If needed, start with one brief third-person narration block in single asterisks, then put {$promptCharacterName}'s spoken text after it. Example: *She smiles* It's good to see you again, my friend! Do not wrap the entire reply in asterisks, and keep spoken dialogue outside the asterisks.";
        } elseif (chimIsDirectNarratorDialogue()) {
            $messageDescription = "plain spoken dialogue addressed directly to {$GLOBALS["PLAYER_NAME"]}. Keep the spoken reply consistent with the chosen narrator action when you use one. Do not include third-person narration, scene description, stage directions, or text in asterisks.";
        }

        $GLOBALS["structuredOutputTemplate"] = array(
            "type" => "json_schema",
            "json_schema" => array(
                "name" => "response",
                "schema" => array(
                    "type" => "object",
                    "properties" => array(
                        "character" => array(
                            "type" => "string",
                            "description" => $GLOBALS["HERIKA_NAME"]
                        ),
                        "listener" => array(
                            "type" => "string",
                            "description" => $listenerDescription,
                        ),
                        "message" => array(
                            "type" => "string",
                            "description" => $messageDescription
                        ),
                        "mood" => empty($moods) ?
                            array(
                                "type" => "string",
                                "description" => $moodDescription
                            ) :
                            array(
                                "type" => "string",
                                "description" => $moodDescription,
                                "enum" => $moods
                            ),
                        "action" => empty($GLOBALS["FUNC_LIST"]) ? 
                            array(
                                "type" => "string",
                                "description" => "a valid action (refer to available actions list)"
                            ) :
                            array(
                                "type" => "string",
                                "description" => "a valid action (refer to available actions list)",
                                "enum" => $GLOBALS["FUNC_LIST"]
                            ),
                        "target" => array(
                            "type" => "string",
                "description" => "action target actor (prefer exact Name [RefID: XXXXXXXX] from people_present, otherwise use actor name)| destination when action is Travel_To|exact BaseID:ItemName inventory identifier when action is Consume| actor to teleport when action is TeleportNPC| actor to receive spawned gold when action is SpawnGold| actor to receive the spawned item when action is SpawnItem| SNQE NPC template key when action is SpawnNPC| actor to kill when action is KillTarget| short creation brief when action is CreateNewNPC| short freeform director brief when action is DirectorCommand. Use '{$GLOBALS["PLAYER_NAME"]}', PLAYER, or me for player-targeted narrator actions. Leave blank when the chosen action does not need a target. Also used for specifying destination when using Travel_To"
                        ),
                        "item" => array(
                            "type" => "string",
                "description" => "item identifier (REQUIRED for GiveItemTo: use exact BaseID:ItemName from inventory;for Take_Held_Item: use exact RefID:ItemName from shown in <held_items>, for PickupItem: use exact RefID:ItemName from nearby_items or the representative RefID:ItemName shown in grouped ITEM DESCRIPTIONS; for CastSpell: use exact spell name from spells) OR amount of gold (REQUIRED when action is GiveGoldTo - number as string, e.g. '50') OR destination location name (REQUIRED when action is TeleportNPC) OR item name from the descriptions database (REQUIRED when action is SpawnItem). For Consume, leave item blank unless target is empty and you need item as the same exact BaseID:ItemName inventory identifier fallback. Leave item blank for SpawnGold and SpawnNPC and CreateNewNPC and DirectorCommand."
                        ),
                        "amount" => array(
                            "type" => "integer",
                "description" => "quantity to give or spawn when the chosen action supports it. REQUIRED when action is SpawnItem or SpawnNPC or SpawnGold. Optional when action is GiveItemTo. Leave blank for CreateNewNPC and DirectorCommand. Use a positive integer."
                        )
                    ),
                    "required" => [
                        "character",
                        "listener",
                        "message",
                        "mood",
                        "action",
                        "target",
                        "item"
                    ],
                    "additionalProperties" => false
                ),
                "strict" => true
            )
        );

        $supplementalActionParameters = chimGetSupplementalActionParameterProperties();
        if (count($supplementalActionParameters) > 0) {
            $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["properties"]["action_params"] = array(
                "type" => "object",
                "description" => "Parameters for the selected action. Populate only fields described for that action.",
                "properties" => $supplementalActionParameters,
                "additionalProperties" => false,
            );
        }

        if (isset($GLOBALS["LANG_LLM_XTTS"])&&($GLOBALS["LANG_LLM_XTTS"])) {
            if (isset($GLOBALS["LLM_LANG"])) {

                $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["properties"] = array_merge(
                    $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["properties"], array(
                        "lang" => array(
                            "type" => "string",
                            "description" => "Language to use. Must be {$GLOBALS["LLM_LANG"]}"
                        )
                    ));
                $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["required"][]="lang";
            } else {
                $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["properties"] = array_merge(
                    $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["properties"], array(
                        "lang" => array(
                            "type" => "string",
                            "description" => "Language to use"
                        )
                    ));
                $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["required"][]="lang";    
            }

        }

        // emotions expression:
        if (isset($GLOBALS['use_emotions_expression']) && $GLOBALS['use_emotions_expression']) {
            $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["properties"] = array_merge(
                $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["properties"], array(
                    "emotion" => array(
                        "type" => "string",
                        "description" => "The emotion expressed."
                    ),
                    "emotion_intensity" => array(
                        "type" => "string",
                        "description" => "The intensity of the emotion expressed, possible values ​​'low', 'moderate' or 'strong'."
                    )
                ));
            $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["required"][]="emotion";
            $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["required"][]="emotion_intensity";
        }
        
        // request speaking tones from the LLM when using zonos TTS
        if (zonosIsActive()) {
            $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["properties"] = array_merge(
                $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["properties"], array(
                    "response_tone_happiness" => array(
                        "type" => "number",
                        "description" => "Value from 0-1",
                        "default" => 0
                    ),
                    "response_tone_sadness" => array(
                        "type" => "number",
                        "description" => "Value from 0-1",
                        "default" => 0
                    ),
                    "response_tone_disgust" => array(
                        "type" => "number",
                        "description" => "Value from 0-1",
                        "default" => 0
                    ),
                    "response_tone_fear" => array(
                        "type" => "number",
                        "description" => "Value from 0-1",
                        "default" => 0
                    ),
                    "response_tone_surprise" => array(
                        "type" => "number",
                        "description" => "Value from 0-1",
                        "default" => 0
                    ),
                    "response_tone_anger" => array(
                        "type" => "number",
                        "description" => "Value from 0-1",
                        "default" => 0
                    ),
                    "response_tone_other" => array(
                        "type" => "number",
                        "description" => "Value from 0-1",
                        "default" => 0
                    ),
                    "response_tone_neutral" => array(
                        "type" => "number",
                        "description" => "Value from 0-1",
                        "default" => 1.0
                    )
                )
            );
            $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["required"] = array_merge(
                $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["required"], [
                    "response_tone_happiness",
                    "response_tone_sadness",
                    "response_tone_disgust",
                    "response_tone_fear",
                    "response_tone_surprise",
                    "response_tone_anger",
                    "response_tone_other",
                    "response_tone_neutral"
                ]
            );
        }
    }

    // sets the grammar used by koboldcpp
    Function setGBNFGrammar() {
        // build the string for moods
        // should look like: ("\"playful\"" | "\"default\"" | ...)
        $moods=normalizeEmoteMoods($GLOBALS["EMOTEMOODS"] ?? "");
        shuffle($moods);

        $moods_quoted = [];
        foreach ($moods as $n=>$mood) {
            $moods_quoted[] = '"\"'.$mood.'\""';
        }
        $moods_str = "(".implode(' | ', $moods_quoted).")";

        if (sizeof($moods) == 0) {
            $moods_str = "string";
        }

        // build the string for actions
        // should look like: ("\"Talk\"" | "\"Attack\"" | ...)
        $actions_quoted = [];
        foreach ($GLOBALS["FUNC_LIST"] as $n=>$action) {
            $actions_quoted[] = '"\"'.$action.'\""';
        }
        $actions_str = "(".implode(' | ', $actions_quoted).")";

        if (sizeof($GLOBALS["FUNC_LIST"]) == 0) {
            $actions_str = "string";
        }

        // build the string for zonos tts tones
        $zonos_tones_str = zonosIsActive()
            ? '"," ws root-response-tone-happiness "," ws root-response-tone-sadness "," ws root-response-tone-disgust "," ws root-response-tone-fear ","'.
              ' ws root-response-tone-surprise "," ws root-response-tone-anger "," ws root-response-tone-other "," ws root-response-tone-neutral '
            : "";

        // using a quoted heredoc to avoid having to escape everything
        $GLOBALS["grammar"] = <<<'EOD'
        root ::= "{" ws root-character "," ws root-listener "," ws root-message "," ws root-mood "," ws root-action "," ws root-target {$ZONOS}"}" ws
        root-character ::= "\"character\"" ":" ws string
        root-listener ::= "\"listener\"" ":" ws string
        root-message ::= "\"message\"" ":" ws string
        root-mood ::= "\"mood\"" ":" ws {$MOODS}
        root-action ::= "\"action\"" ":" ws {$ACTIONS}
        root-target ::= "\"target\"" ":" ws string
        root-response-tone-happiness ::= "\"response-tone-happiness\"" ":" ws number
        root-response-tone-sadness ::= "\"response-tone-sadness\"" ":" ws number
        root-response-tone-disgust ::= "\"response-tone-disgust\"" ":" ws number
        root-response-tone-fear ::= "\"response-tone-fear\"" ":" ws number
        root-response-tone-surprise ::= "\"response-tone-surprise\"" ":" ws number
        root-response-tone-anger ::= "\"response-tone-anger\"" ":" ws number
        root-response-tone-other ::= "\"response-tone-other\"" ":" ws number
        root-response-tone-neutral ::= "\"response-tone-neutral\"" ":" ws number

        string ::=
        "\"" (
            [^"\\] |
            "\\" (["\\/bfnrt] | "u" [0-9a-fA-F] [0-9a-fA-F] [0-9a-fA-F] [0-9a-fA-F]) # escapes
        )* "\"" ws

        number ::= ("-"? ([0-9] | [1-9] [0-9]*)) ("." [0-9]+)? ([eE] [-+]? [0-9]+)? ws

        # Optional space: by convention, applied in this grammar after literal chars when allowed
        ws ::= ([ \t\n] ws)?
        EOD;

        // replace the mood and action templates with the strings built earlier
        $GLOBALS["grammar"]=str_replace('{$ZONOS}', $zonos_tones_str, $GLOBALS["grammar"]);
        $GLOBALS["grammar"]=str_replace('{$MOODS}', $moods_str, $GLOBALS["grammar"]);
        $GLOBALS["grammar"]=str_replace('{$ACTIONS}', $actions_str, $GLOBALS["grammar"]);
    }

    Function zonosIsActive() {
        return ($GLOBALS["TTSFUNCTION"] ?? "") === "zonos_gradio" && isset($GLOBALS["TTS"]["ZONOS_GRADIO"]["dynamic_tones"]) && $GLOBALS["TTS"]["ZONOS_GRADIO"]["dynamic_tones"];
    }

?>
