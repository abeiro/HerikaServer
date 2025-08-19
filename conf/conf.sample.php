<?php

//[Main Configuration]
$PLAYER_NAME="Prisoner"; //Player's current character name.
$DBDRIVER="postgresql"; //Database - Do not change.
$HERIKA_NAME="The Narrator"; //NPC name. MUST MATCH their Skyrim in-game NPC name!
$LOCK_PROFILE=false; //NPC name. MUST MATCH their Skyrim in-game NPC name!
$PROMPT_HEAD="Let's roleplay in the Universe of Skyrim. If the game director gives you an instruction, you must follow it."; //System Prompt. Defines the rules of the roleplay.
$PLAYER_BIOS="I'm #PLAYER_NAME#"; //Player character description. 
$HERIKA_PERS="You are The Narrator in a Skyrim adventure. You will only talk to #PLAYER_NAME#. "
    . "You refer to yourself as 'The Narrator'. "
    . "Only #PLAYER_NAME# can hear you. "
    . "Your goal is to comment on #PLAYER_NAME#'s playthrough, and occasionally give hints. NO SPOILERS. " 
    . "Talk about quests and last events."; //NPC personality.
$HERIKA_DYNAMIC=''; //Split Biography for information to be changed dynamically. 
$DIARY_COOLDOWN=120; //Cooldown period in seconds between diary entries to prevent spam. If a diary hotkey is pressed within this time period, the request will be ignored.
$DYNAMIC_PROFILE=false; //Dynamic profile updates using a timer system.
$AUTO_DIARY=false; //Automatically create diary entries for all current followers when sleeping. Wait events are controlled by AUTO_DIARY_WAIT setting. Each follower respects their individual diary cooldown timer.
$AUTO_DIARY_WAIT=true; //When AUTO_DIARY is enabled, this controls whether diary entries are created during wait events. If false, auto diary will only trigger on sleep events.
$MINIME_T5=false; //Assists smaller weight LLMs with action and memory functions.
$OGHMA_KNOWLEDGE="knowall"; //Assists smaller weight LLMs with action and memory functions.
$OGHMA_AMOUNT=1; //Number of Oghma keywords to extract from each response. More keyword extraction will mean longer response times.
$PLAYER_RESPEECH=true; //Use default diary connector AI to rewrite player speech. Currently only triggers when starting speech with **.
$PLAYER_SPEECH_STYLE=""; //Instructions for how the player character speaks and communicates. Used as context when rewriting player dialogue.

//[Advanced Configuration]
$RECHAT_H=2; //Rechat Rounds. Higher values will increase the amount of rounds NPC's will talk amongst themselves.
$RECHAT_P=50; //Rechat Probability.
$BORED_EVENT=30; //Bored Event Probability. Chance of an NPC starting a random conversation after a set period of time.
$CONTEXT_HISTORY="50"; //Amount of context history (dialogue and events) that will be sent to LLM.
$CONTEXT_HISTORY_DIARY="100"; //Amount of context history specifically for diary entries. Set to 0 to use regular CONTEXT_HISTORY value.
$CONTEXT_HISTORY_DYNAMIC_PROFILE="50"; //Amount of context history specifically for dynamic profile updates. Set to 0 to use regular CONTEXT_HISTORY value.
$HTTP_TIMEOUT=15; //Timeout for AI requests.
$CORE_LANG=""; //Custom languages. - language folder
$ALIVE_MESSAGE=false; //Leave as is - read only
$TIME_AWARENESS=false; //Overwrites the prompt to the AI to make it more aware of the passage of time
$MAX_WORDS_LIMIT=0; //Enforce a word limit for AI's responses. 0 = unlimited.
$BOOK_EVENT_FULL=true; //Sends full contents of books to the AI
$BOOK_EVENT_ALWAYS_NARRATOR=false; //Only The Narrator summarizes books.
$NARRATOR_TALKS=true; //Enables the Narrator.
$NARRATOR_WELCOME=true;
$QUEST_COMMENT = false;
$QUEST_COMMENT_CHANCE= "10%";
$CURRENT_TASK=false; //Sends current plan/quest to the AI
 //The Narrator will recap previous events after a save is loaded.
$LANG_LLM_XTTS=false; //XTTS Only! Will offer a language field to LLM, and will try match to XTTSv2 language.
$HERIKA_ANIMATIONS=true; //Issues animations to AI driven NPCs.
$EMOTEMOODS="sassy,"
    . "assertive,"
    . "sexy,"
    . "smug,"
    . "kindly,"
    . "lovely,"
    . "seductive,"
    . "sarcastic,"
    . "sardonic,"
    . "smirking,"
    . "amused,"
    . "default,"
    . "assisting,"
    . "irritated,"
    . "playful,"
    . "neutral,"
    . "teasing,"
    . "mocking"; //List of moods passed to LLM (comma separated). Triggers animations if enabled.

$REMOVE_ASTERISKS_FROM_OUTPUT=true;
$ENFORCE_ACTIONS_PROMPT=false;
$SUMMARY_PROMPT= 'Focus on key events, tagging characters, locations, and factions accurately. Ensure memories align and maintain chronological order while foreshadowing future arcs. Prioritize player agency, and use environmental cues to enhance storytelling and continuity.'; 
$DYNAMIC_PROMPT = "(LEGACY - Use individual field prompts instead) "
    . "Last in-game date/time found: [date or \"No date\"] "
    . "1. RECENT HIGHLIGHTS (3–5 bullet points) "
    . "   - Write one sentence per bullet with objective facts (locations, quest progress, important decisions). Re-list older relevant events DO NOT REMOVE ENTRIES that are still important. "
    . "2. EMOTIONAL/RELATIONAL UPDATES (1–2 lines per key person/faction) "
    . "   - Describe the NPC's evolving feelings or stance toward the dragonborn, key individuals or groups. Always re-list unchanged but relevant relationships. "
    . "3. CONTINUING GOALS, CONFLICTS OR FEELINGS (2–3 bullet points) "
    . "   - List ongoing arcs, dilemmas, objectives and goals with clear facts. Remove items only if resolved.";

$DYNAMIC_PROFILE_FIELDS = ["relationships", "goals"];

$DYNAMIC_PROMPT_PERSONALITY = "Based on the dialogue history and recent events, update #HERIKA_NAME# personality traits. "
    . "Maintain all existing relevant personality traits and add new ones based on recent experiences. "
    . "Focus on behavioral changes, emotional growth/regression, new traits that emerged, and changes in confidence or outlook. "
    . "Return ONLY the updated personality description in 3-5 sentences. Do not include any introductory text, meta-commentary, or phrases like 'Here is the updated personality' or 'The character's personality is'. "
    . "Start directly with the personality content.";

$DYNAMIC_PROMPT_RELATIONSHIPS = "Based on recent interactions, update #HERIKA_NAME# relationships with other people and factions. "
    . "Maintain all existing relevant relationships and add new ones or modify existing ones based on recent interactions. "
    . "Focus on changed relationships, new relationships formed, evolved existing ones, and only remove relationships that are clearly no longer relevant. "
    . "Return ONLY a bulleted list using * Name/Faction - Description format. Do not include any introductory text, meta-commentary, or phrases like 'Here are the updated relationships' or 'The character's relationships include'. "
    . "Start directly with the first bullet point.";

$DYNAMIC_PROMPT_OCCUPATION = "Based on story progression and events, update #HERIKA_NAME# occupation and role. "
    . "Maintain the current occupation unless significant changes have occurred. Add new responsibilities, changes in social status, and professional affiliations. "
    . "Focus on job changes, new duties, and evolving professional relationships. "
    . "Return ONLY the updated occupation description in 2-3 sentences. Do not include any introductory text, meta-commentary, or phrases like 'The character's occupation is' or 'Here is the updated occupation'. "
    . "Start directly with the occupation content.";

$DYNAMIC_PROMPT_SKILLS = "Based on experiences and training, update #HERIKA_NAME# skills and abilities. "
    . "Maintain all existing relevant skills and add new ones based on recent experiences. "
    . "Focus on new skills learned, existing skills improved, any skills that deteriorated, and combat/magical knowledge gained. "
    . "Return ONLY a bulleted list using * Skill - Description format. Do not include any introductory text, meta-commentary, or phrases like 'Here are the updated skills' or 'The character's skills include'. "
    . "Start directly with the first bullet point.";

$DYNAMIC_PROMPT_SPEECHSTYLE = "Based on recent interactions, update how #HERIKA_NAME# speaks and communicates. "
    . "Maintain existing consistent speech patterns and add new ones based on recent interactions. "
    . "Focus on changes in vocabulary, new mannerisms, accent changes, and confidence level in speech. "
    . "Return ONLY the updated speech style description in 2-3 sentences. Do not include any introductory text, meta-commentary, or phrases like 'The character speaks' or 'Here is the updated speech style'. "
    . "Start directly with the speech style content.";

$DYNAMIC_PROMPT_GOALS = "Based on story developments and achievements, update the #HERIKA_NAME# goals and aspirations. "
    . "Maintain existing relevant goals and add new ones. Only remove goals that have been clearly completed or are no longer applicable. "
    . "Focus on new aspirations that emerged, modified existing goals due to circumstances, and updated long-term objectives. "
    . "Return ONLY a bulleted list using * Goal description as actionable aspiration format. Do not include any introductory text, meta-commentary, or phrases like 'Here are the updated goals' or 'The character's goals are'. "
    . "Start directly with the first bullet point.";
$DIARY_PROMPT = "Please write a short summary of {\$GLOBALS[\"PLAYER_NAME\"]} and {\$GLOBALS[\"HERIKA_NAME\"]}s last dialogues and events written above into {\$GLOBALS[\"HERIKA_NAME\"]}s diary . WRITE AS IF YOU WERE {\$GLOBALS[\"HERIKA_NAME\"]}. Start the diary entry with the current date and time.";

// Dynamic profile utility button
$dynamic_profile_b1 = false; // Utility button for updating all dynamic profile fields

$RPG_COMMENTS=["levelup","learn_shout","learn_word","absorb_soul", "bleedout", "combat_end", "lockpick", "sleep", "keepmechecked"]; //AI Service(s).
$DETECT_MAGIC_EVENT=true; //Enable detection and logging of NPC spellcasting events.
$MAGIC_EVENT_BLACKLIST=""; //Comma-separated list of magic events to exclude from logging.

//[AI/LLM Service Selection]
$CONNECTORS=["openrouterjson","openaijson","koboldcppjson"]; //AI Service(s).
$CONNECTORS_DIARY=["openrouter","openai","google_openaijson","koboldcpp","player2"]; //Creates diary entries and memories.

//[AI/LLM Connectors]
//OpenRouter JSON
$CONNECTOR["openrouterjson"]["url"]="https://openrouter.ai/api/v1/chat/completions"; //API endpoint.
$CONNECTOR["openrouterjson"]["model"]="meta-llama/llama-3.3-70b-instruct"; //LLM model.
$CONNECTOR["openrouterjson"]["reasoning_model"]=false; //This is a reasoning model, could output CoT.
$CONNECTOR["openrouterjson"]["fallback_models"]=""; //comma separated models.
$CONNECTOR["openrouterjson"]["PROVIDER"]=""; //use only this list of providers from OpenRouter
$CONNECTOR["openrouterjson"]["providers_sort"]="default"; //Prioritize providers on selected attribute.
$CONNECTOR["openrouterjson"]["providers_to_ignore"]=""; //list of providers to ignore
$CONNECTOR["openrouterjson"]["provider_quantizations"]=""; //use only providers that have the quant. level
$CONNECTOR["openrouterjson"]["provider_max_price_input"]=0.0; //use only providers that have lower input price
$CONNECTOR["openrouterjson"]["provider_max_price_output"]=0.0; //use only providers that have lower output price
$CONNECTOR["openrouterjson"]["max_tokens"]='1024'; //Maximum tokens to generate.
$CONNECTOR["openrouterjson"]["temperature"]=0.6; //LLM parameter temperature.
$CONNECTOR["openrouterjson"]["presence_penalty"]=0; //LLM parameter presence_penalty.
$CONNECTOR["openrouterjson"]["frequency_penalty"]=0; //LLM parameter frequency_penalty.
$CONNECTOR["openrouterjson"]["repetition_penalty"]=1;	//LLM parameter repetition_penalty.
$CONNECTOR["openrouterjson"]["top_p"]=1; //LLM parameter top_p.
$CONNECTOR["openrouterjson"]["min_p"]=0; //LLM parameter min_p.
$CONNECTOR["openrouterjson"]["top_k"]=0; //LLM parameter top_k.
$CONNECTOR["openrouterjson"]["top_a"]=0; //LLM parameter top_a.
$CONNECTOR["openrouterjson"]["ENFORCE_JSON"]=true; //Attempts to enforce JSON. Only valid for some models.
$CONNECTOR["openrouterjson"]["PREFILL_JSON"]=false; //Prefill JSON, Only valid for some models.
$CONNECTOR["openrouterjson"]["MAX_TOKENS_MEMORY"]='1024'; //Maximum tokens to generate when summarizing.
$CONNECTOR["openrouterjson"]["API_KEY"]=""; //API key.
$CONNECTOR["openrouterjson"]["xreferer"]="https://www.nexusmods.com/skyrimspecialedition/mods/89931"; //Stub needed header.
$CONNECTOR["openrouterjson"]["xtitle"]="CHIM"; //Stub needed header.
$CONNECTOR["openrouterjson"]["json_schema"]=false; //Enable OpenRouter JSON schema.
// Utility buttons for autofilling parameters
$CONNECTOR["openrouterjson"]["get_parms1"] = false; // Utility button for low randomness parameters
$CONNECTOR["openrouterjson"]["get_parms5"] = false; // Utility button for medium randomness parameters  
$CONNECTOR["openrouterjson"]["get_parms9"] = false; // Utility button for high randomness parameters
//OpenRouter (Legacy)
$CONNECTOR["openrouter"]["url"]="https://openrouter.ai/api/v1/chat/completions"; //API endpoint.
$CONNECTOR["openrouter"]["model"]="meta-llama/llama-3.1-8b-instruct"; //LLM model.
$CONNECTOR["openrouter"]["reasoning_model"]=false; //This is a reasoning model, could output CoT.
$CONNECTOR["openrouter"]["fallback_models"]=""; //comma separated models.
$CONNECTOR["openrouter"]["PROVIDER"]=""; //select a list of providers from OpenRouter
$CONNECTOR["openrouter"]["providers_sort"]="default"; //Prioritize providers on selected attribute.
$CONNECTOR["openrouter"]["providers_to_ignore"]=""; //list of providers to ignore
$CONNECTOR["openrouter"]["provider_quantizations"]=""; //use only providers that have the quant. level
$CONNECTOR["openrouter"]["provider_max_price_input"]=0.0; //use only providers that have lower input price
$CONNECTOR["openrouter"]["provider_max_price_output"]=0.0; //use only providers that have lower otput price
$CONNECTOR["openrouter"]["max_tokens"]=1024; //Maximum tokens to generate.
$CONNECTOR["openrouter"]["temperature"]=0.6; //LLM parameter temperature.
$CONNECTOR["openrouter"]["presence_penalty"]=0;	//LLM parameter presence_penalty.
$CONNECTOR["openrouter"]["frequency_penalty"]=0; //LLM parameter frequency_penalty.
$CONNECTOR["openrouter"]["repetition_penalty"]=1;	//LLM parameter repetition_penalty.
$CONNECTOR["openrouter"]["top_p"]=1; //LLM parameter top_p.
$CONNECTOR["openrouter"]["min_p"]=0; //LLM parameter min_p.
$CONNECTOR["openrouter"]["top_k"]=0; //LLM parameter top_k.
$CONNECTOR["openrouter"]["top_a"]=0; //LLM parameter top_a.
$CONNECTOR["openrouter"]["MAX_TOKENS_MEMORY"]="1024"; //Maximum tokens to generate when summarizing.
$CONNECTOR["openrouter"]["API_KEY"]=""; //API key.
$CONNECTOR["openrouter"]["xreferer"]="https://www.nexusmods.com/skyrimspecialedition/mods/89931"; //Stub needed header.
$CONNECTOR["openrouter"]["xtitle"]="CHIM"; //Stub needed header.
// Utility buttons for autofilling parameters
$CONNECTOR["openrouter"]["get_parms1"] = false; // Utility button for low randomness parameters
$CONNECTOR["openrouter"]["get_parms5"] = false; // Utility button for medium randomness parameters  
$CONNECTOR["openrouter"]["get_parms9"] = false; // Utility button for high randomness parameters
//OpenAI JSON
$CONNECTOR["openaijson"]["url"]="https://api.openai.com/v1/chat/completions"; //API endpoint.
$CONNECTOR["openaijson"]["model"]='gpt-4o-mini'; //LLM model.
$CONNECTOR["openaijson"]["reasoning_model"]=false; //This is a reasoning model, could output CoT.
$CONNECTOR["openaijson"]["max_tokens"]='512'; //Maximum tokens to generate.
$CONNECTOR["openaijson"]["temperature"]=0.6; //LLM parameter temperature.
$CONNECTOR["openaijson"]["presence_penalty"]=0; //LLM parameter presence_penalty.
$CONNECTOR["openaijson"]["frequency_penalty"]=0; //LLM parameter frequency_penalty.
$CONNECTOR["openaijson"]["top_p"]=1; //LLM parameter top_p.
$CONNECTOR["openaijson"]["API_KEY"]=""; //API key.
$CONNECTOR["openaijson"]["MAX_TOKENS_MEMORY"]="1024"; //Maximum tokens to generate when summarizing.
$CONNECTOR["openaijson"]["json_schema"]=false; //Enable OpenAI JSON schema.
//OpenAI (Legacy)
$CONNECTOR["openai"]["url"]="https://api.openai.com/v1/chat/completions";
$CONNECTOR["openai"]["model"]='gpt-4o-mini'; //LLM model.
$CONNECTOR["openai"]["reasoning_model"]=false; //This is a reasoning model, could output CoT.
$CONNECTOR["openai"]["max_tokens"]='1024'; //Maximum tokens to generate.
$CONNECTOR["openai"]["temperature"]=0.6; //LLM parameter temperature.
$CONNECTOR["openai"]["presence_penalty"]=0; //LLM parameter presence_penalty.
$CONNECTOR["openai"]["frequency_penalty"]=0; //LLM parameter frequency_penalty.
$CONNECTOR["openai"]["top_p"]=1; //LLM parameter top_p.
$CONNECTOR["openai"]["API_KEY"]=""; //API key.
$CONNECTOR["openai"]["MAX_TOKENS_MEMORY"]="1024"; //Maximum tokens to generate when summarizing.
//Player2 JSON
$CONNECTOR["player2json"]["url"]="http://localhost:4315/v1/chat/completions"; //API endpoint.
//Google OpenAI JSON
$CONNECTOR["google_openaijson"]["url"]="https://generativelanguage.googleapis.com/v1beta/openai/chat/completions"; //API endpoint.
$CONNECTOR["google_openaijson"]["model"]='gemini-1.5-flash'; //LLM model.
$CONNECTOR["google_openaijson"]["max_tokens"]='1024'; //Maximum tokens to generate.
$CONNECTOR["google_openaijson"]["temperature"]=0.75; //LLM parameter temperature.
$CONNECTOR["google_openaijson"]["top_p"]=0.95; //LLM parameter top_p.
$CONNECTOR["google_openaijson"]["API_KEY"]=""; //API key.
$CONNECTOR["google_openaijson"]["MAX_TOKENS_MEMORY"]="800"; //Maximum tokens to generate when summarizing.
$CONNECTOR["google_openaijson"]["json_schema"]=false; //Enable OpenAI JSON schema.
//KoboldCPP JSON
$CONNECTOR["koboldcppjson"]["url"]='http://127.0.0.1:5001';	//KoboldCPP API Endpoint.
$CONNECTOR["koboldcppjson"]["max_tokens"]='512';	//Maximum tokens to generate.
$CONNECTOR["koboldcppjson"]["temperature"]=0.9;	//LLM parameter temperature.
$CONNECTOR["koboldcppjson"]["rep_pen"]=1.12;	//LLM parameter rep_pen.
$CONNECTOR["koboldcppjson"]["top_p"]=0.9;	//LLM parameter top_p.
$CONNECTOR["koboldcppjson"]["min_p"]=0;	//LLM parameter min_p.
$CONNECTOR["koboldcppjson"]["top_k"]=0;	//LLM parameter top_k.
$CONNECTOR["koboldcppjson"]["PREFILL_JSON"]=false; //Prefill JSON, Only valid for some models.
$CONNECTOR["koboldcppjson"]["MAX_TOKENS_MEMORY"]='256';	//Maximum tokens to generate when summarizing.
$CONNECTOR["koboldcppjson"]["newline_as_stopseq"]=false; //A newline in the output that will be considered a stop sequence. Recommended to leave it as default.
$CONNECTOR["koboldcppjson"]["use_default_badwordsids"]=true; //Unban End of Sentence (EOS) tokens. If set to false the LLM will stop generating when it detects an EOS token.
$CONNECTOR["koboldcppjson"]["eos_token"]='<|eot_id|>'; //EOS token LLM uses. Only works if use_default_badwordsids is enabled.
$CONNECTOR["koboldcppjson"]["template"]='chatml'; //Prompt format specified in the HuggingFace model card.
$CONNECTOR["koboldcppjson"]["grammar"]=false; //Enforces use of JSON grammar at the cost of slower generation speed. 
//KoboldCPP (Legacy)
$CONNECTOR["koboldcpp"]["url"]='http://127.0.0.1:5001';	//KoboldCPP API Endpoint.
$CONNECTOR["koboldcpp"]["max_tokens"]='512'; //Maximum tokens to generate.
$CONNECTOR["koboldcpp"]["temperature"]=1; //LLM parameter temperature.
$CONNECTOR["koboldcpp"]["rep_pen"]=1; //LLM parameter rep_pen.
$CONNECTOR["koboldcpp"]["top_p"]=1;	//LLM parameter top_p.
$CONNECTOR["koboldcpp"]["min_p"]=0.01; //LLM parameter min_p.
$CONNECTOR["koboldcpp"]["top_k"]=0;	//LLM parameter top_k.
$CONNECTOR["koboldcpp"]["MAX_TOKENS_MEMORY"]='512';	//Maximum tokens to generate when summarizing.
$CONNECTOR["koboldcpp"]["newline_as_stopseq"]=false; //A newline in the output that will be considered a stop sequence. Recommended to leave it as default.
$CONNECTOR["koboldcpp"]["use_default_badwordsids"]=false; //Unban End of Sentence (EOS) tokens. If set to false the LLM will stop generating when it detects an EOS token.
$CONNECTOR["koboldcpp"]["eos_token"]='<|im_end|>'; //EOS token LLM uses. Only works if use_default_badwordsids is enabled.
$CONNECTOR["koboldcpp"]["template"]='chatml'; //Prompt Format. Specified in the HuggingFace model card.
//Player2 (Summary)
$CONNECTOR["player2"]["url"]="http://localhost:4315/v1/chat/completions"; //API endpoint.
//Oobabooga
$CONNECTOR["oobabooga"]["HOST"]="127.0.0.1"; //API Endpoint.
$CONNECTOR["oobabooga"]["PORT"]="5005"; //API server port.
$CONNECTOR["oobabooga"]["MAX_TOKENS_MEMORY"]="512"; //Maximum tokens to generate when summarizing.
$CONNECTOR["oobabooga"]["max_tokens"]=100; //Maximum tokens to generate.
$CONNECTOR["oobabooga"]["temperature"]=0.7; //LLM parameter temperature.
$CONNECTOR["oobabooga"]["rep_pen"]=1.18; //LLM parameter rep_pen.
//LlamaCPP
$CONNECTOR["llamacpp"]["url"]='http://127.0.0.1:8007';	//Llama.cpp server API
$CONNECTOR["llamacpp"]["max_tokens"]="75"; //Maximum tokens to generate (n_predict).
$CONNECTOR["llamacpp"]["temperature"]=0.7; //LLM parameter temperature.
$CONNECTOR["llamacpp"]["rep_pen"]=1.12;	//LLM parameter rep_pen.
$CONNECTOR["llamacpp"]["top_p"]=0.9; //LLM parameter top_p.
$CONNECTOR["llamacpp"]["MAX_TOKENS_MEMORY"]='512'; //Maximum tokens to generate when summarizing.
$CONNECTOR["llamacpp"]["eos_token"]='';	//EOS token LLM uses.
$CONNECTOR["llamacpp"]["template"]='alpaca'; //Prompt Format. Specified in the HuggingFace model card.

//[Text-to-Speech Service]
$TTSFUNCTION="mimic3";

//[Text-to-Speech Endpoints]
//MeloTTS
$TTS["MELOTTS"]["endpoint"]='http://127.0.0.1:8084'; //API endpoint.
$TTS["MELOTTS"]["language"]='EN'; //Lanuguage model.
$TTS["MELOTTS"]["speed"]=1.0; //Speech speed.
$TTS["MELOTTS"]["voiceid"]='malenord'; //Voice ID.
//CHIM XTTS
$TTS["XTTSFASTAPI"]["endpoint"]='http://127.0.0.1:8020'; //API endpoint.
$TTS["XTTSFASTAPI"]["language"]='en'; //Lanuguage.
$TTS["XTTSFASTAPI"]["voiceid"]='TheNarrator'; //Generated voice file name.
$TTS["XTTSFASTAPI"]["voicelogic"]='voicetype';
//MIMIC3
$TTS["MIMIC3"]["URL"]="http://127.0.0.1:59125"; //API endpoint. 
$TTS["MIMIC3"]["voice"]="en_UK/apope_low#default"; //Voice ID.
$TTS["MIMIC3"]["rate"]="1"; //Speech speed.
$TTS["MIMIC3"]["volume"]="60"; //Speech volume.
//xVASynth
$TTS["XVASYNTH"]["url"]='http://192.168.0.1:8008';	//xVASynth must be run in same machine as DwemerDistro, so this must be http://your-local-ip:8008
$TTS["XVASYNTH"]["base_lang"]='en';	//Base language.
$TTS["XVASYNTH"]["modelType"]='xVAPitch'; //ModelType.
$TTS["XVASYNTH"]["version"]='3.0'; //Version.
$TTS["XVASYNTH"]["game"]='skyrim'; //Game.
$TTS["XVASYNTH"]["model"]='sk_malenord'; //Model.
$TTS["XVASYNTH"]["pace"]=1.0; //Pace.
$TTS["XVASYNTH"]["waveglowPath"]='resources/app/models/waveglow_256channels_universal_v4.pt'; //waveglowPath (relative).
$TTS["XVASYNTH"]["vocoder"]='n/a';	//vocoder.
$TTS["XVASYNTH"]["distroname"]='DwemerAI4Skyrim3'; //Leave as default.
//Azure TTS
$TTS["AZURE"]["fixedMood"]=""; //Voice Style.
$TTS["AZURE"]["region"]="westeurope"; //API Region.
$TTS["AZURE"]["voice"]="en-US-NancyNeural";	//Voice ID.
$TTS["AZURE"]["volume"]="20"; //Voice volume.				
$TTS["AZURE"]["rate"]="1.25"; //Speech rate.	
$TTS["AZURE"]["countour"]="(11%, +15%) (60%, -23%) (80%, -34%)"; //Voice contour.							
$TTS["AZURE"]["validMoods"]=array("whispering","default","dazed"); //Allowed voice styles.	
$TTS["AZURE"]["API_KEY"]=""; //API key.
//OpenAI TTS
$TTS["openai"]["endpoint"]='https://api.openai.com/v1/audio/speech'; //API endpoint.
$TTS["openai"]["API_KEY"]=''; //API key.
$TTS["openai"]["voice"]='nova';	//Voice ID.
$TTS["openai"]["model_id"]='tts-1';	//Model.
//ElevenLabs TTS
$TTS["ELEVEN_LABS"]["voice_id"]="EXAVITQu4vr4xnSDxMaL";	//Voice ID.
$TTS["ELEVEN_LABS"]["optimize_streaming_latency"]="0"; //Optimize streaming latency.
$TTS["ELEVEN_LABS"]["model_id"]="eleven_monolingual_v1"; //Model ID.
$TTS["ELEVEN_LABS"]["stability"]="0.75"; //Stability.
$TTS["ELEVEN_LABS"]["similarity_boost"]="0.75"; //Similarity boost.
$TTS["ELEVEN_LABS"]["style"]=0.0; //Style.
$TTS["ELEVEN_LABS"]["API_KEY"]=""; //API key.
//Google Cloud Platform TTS
$TTS["GCP"]["GCP_SA_FILEPATH"]="meta-chassis-391906-122bdf85aa6f.json"; //Google Cloud Platform auth file.
$TTS["GCP"]["voice_name"]="en-GB-Neural2-C"; //Voice ID.
$TTS["GCP"]["voice_languageCode"]="en-GB"; //Language ID.
$TTS["GCP"]["ssml_rate"]=1.15; //Speech rate.
$TTS["GCP"]["ssml_pitch"]="+3.6st"; //Speech pitch.
//CONVAI TTS
$TTS["CONVAI"]["endpoint"]='https://api.convai.com/tts'; //API endpoint.
$TTS["CONVAI"]["API_KEY"]=''; //API key.
$TTS["CONVAI"]["language"]='en-US';	//Language.
$TTS["CONVAI"]["voiceid"]='WUFemale3'; //Voice ID.
//XTTS
$TTS["XTTS"]["endpoint"]=''; //API endpoint.
$TTS["XTTS"]["language"]='en'; //Launguage.
$TTS["XTTS"]["voiceid"]='11labs_diane';	//Voice JSON file.
//StyleTTSv2
$TTS["STYLETTSV2"]["endpoint"]='http://127.0.0.1:5050/'; //API endpoint.
$TTS["STYLETTSV2"]["voice"]='';	//WAV file with source voice to clone. Should be localte at /var/www/html/HerikaServer/data/voices/
$TTS["STYLETTSV2"]["alpha"]=0.3; //0.0-1.0 - Alpha determines the timbre of the speaker.
$TTS["STYLETTSV2"]["beta"]=0.7;	//0.0-1.0 - Beta determines the prosody of the speaker.
$TTS["STYLETTSV2"]["diffusion_steps"]=15; //5.0 > Vocal variety at the cost of slower synthesis speed.
$TTS["STYLETTSV2"]["embedding_scale"]=1.5;//0.0-1.0 - This is the classifier-free guidance scale. Dictates emotional scale.
//CONQUI TTS
$TTS["COQUI_AI"]["voice_id"]='f05c5b91-7540-4b26-b534-e820d43065d1'; //Voice ID.
$TTS["COQUI_AI"]["speed"]=1; //Speech rate.
$TTS["COQUI_AI"]["language"]='en'; //Language.
$TTS["COQUI_AI"]["API_KEY"]='';	//Coqui.ai API key.
//KoboldCPP TTS
$TTS["koboldcpp"]["endpoint"]='http://127.0.0.1:5001/api/extra/tts'; //API endpoint.
$TTS["koboldcpp"]["voice"]='kobo';

// KOKORO

$TTS["KOKORO"]["endpoint"]='http://127.0.0.1:8880'; //API endpoint.
$TTS["KOKORO"]["voiceid"]='af_bella'; //Voice ID.
$TTS["KOKORO"]["speed"]=1.0; //Speech speed.

// ZONOS_GRADIO TTS
$TTS["ZONOS_GRADIO"]["endpoint"]='http://127.0.0.1:7860';	//Endpoint URL
$TTS["ZONOS_GRADIO"]["language"]='en-us';	//Language
$TTS["ZONOS_GRADIO"]["model"]='Zyphra/Zonos-v0.1-hybrid';	//Zonos model type
$TTS["ZONOS_GRADIO"]["dynamic_tones"]=true;	//Zonos dynamic tones
$TTS["ZONOS_GRADIO"]["voiceid"]='TheNarrator';	//Voice id
$TTS["ZONOS_GRADIO"]["pitch_std"]=45;	//Pitch standard deviation
$TTS["ZONOS_GRADIO"]["speaking_rate"]=14.6;	//Speaking rate
$TTS["ZONOS_GRADIO"]["cfg_scale"]=4.5;	//Context-free guidance scale

//PiperTTS
$TTS["PIPERTTS"]["endpoint"]='http://127.0.0.1:5000'; //piper-tts API endpoint.
$TTS["PIPERTTS"]["voiceid"]='en_US-amy-low'; //Voice ID.
$TTS["PIPERTTS"]["length_scale"]=1.0; //speaking speed; defaults to 1
$TTS["PIPERTTS"]["noise_scale"]=0.0; //speaking variability - default 0.667
$TTS["PIPERTTS"]["noise_w_scale"]=0.0; //phoneme width variability - default 0.8
$TTS["PIPERTTS"]["speaker"]=''; // name of speaker for multi-speaker voices
$TTS["PIPERTTS"]["speaker_id"]=0; //id of speaker for multi-speaker voices; overrides speaker

//[Player TTS]
$TTSFUNCTION_PLAYER="none";
$TTSFUNCTION_PLAYER_VOICE="malenord";
$TTSFUNCTION_PLAYER_VOICE_ID=0; // id for multivoice models
$TTSFUNCTION_PLAYER_LANGUAGE="";

//[Translation]
$TRANSLATION_FUNCTION="none";
//settings
$TRANSLATION["settings"]["translate_audio"]=false; //translate audio
$TRANSLATION["settings"]["translate_text"]=false; //translate text
$TRANSLATION["settings"]["save_translated_text"]=false; //replace npc's speech in context history with the translation
$TRANSLATION["settings"]["translate_player_text"]=false; //translate player text
$TRANSLATION["settings"]["save_translated_player_text"]=false; //replace player input in context history with the translation
//DeepL
$TRANSLATION["DeepL"]["source_language"]=""; //source language
$TRANSLATION["DeepL"]["target_language"]=""; //target language
$TRANSLATION["DeepL"]["url"]="https://api-free.deepl.com/v2/translate"; //DeepL endpoint url
$TRANSLATION["DeepL"]["player_source_language"]=""; //player source language
$TRANSLATION["DeepL"]["player_target_language"]=""; //player target language
$TRANSLATION["DeepL"]["API_KEY"]=""; //DeepL API key

//[Speech-to-Text Service]
$STTFUNCTION="whisper";

//[Speech-to-Text Endpoints]
//OpenAI Whisper STT
$STT["WHISPER"]["LANG"]="en"; //Language.
$STT["WHISPER"]["TRANSLATE"]=false; //Attempt to translate to English.
$STT["WHISPER"]["API_KEY"]=""; //API Key.
//Azure STT
$STT["AZURE"]["LANG"]="en-US"; //Language.
$STT["AZURE"]["profanity"]="masked"; //Profanity handling filter.
$STT["AZURE"]["API_KEY"]=""; //API key.
//Local Whisper STT
$STT["LOCALWHISPER"]["URL"]="http://127.0.0.1:9876/api/v0/transcribe"; //API endpoint.
$STT["LOCALWHISPER"]["FORMFIELD"]="audio_file"; //(audio_file,file) Form field name.

//[Image to Text (Soulgaze)]
$ITTFUNCTION="none";
//OpenAI
$ITT["openai"]["url"]='https://api.openai.com/v1/chat/completions';	//OpenAI API endpoint.
$ITT["openai"]["model"]='gpt-4o-mini'; //LLM model.
$ITT["openai"]["max_tokens"]=1024; //Maximum tokens to generate.
$ITT["openai"]["detail"]='low';	//(Low|high) fidelity image understanding.
$ITT["openai"]["API_KEY"]=''; //OpenAI API key.
$ITT["openai"]["AI_VISION_PROMPT"]="Let\'s roleplay in the world of Skyrim. "
    . "Describe this Skyrim image as if it is real life. "
    . "Describe the environment, objects, and people you see at a fifth grade reading level. "
    . "Ignore video game HUD and UI elements in your description."; //Prompt to sent to the Vision AI.
$ITT["openai"]["AI_PROMPT"]='#HERIKA_NPC1# describes what they are seeing'; //Prompt sent to the LLM.
//Google
$ITT["google_openai"]["url"]='https://generativelanguage.googleapis.com/v1beta/openai/chat/completions'; //OpenAI API endpoint.
$ITT["google_openai"]["model"]='gemini-1.5-flash'; //LLM model.
$ITT["google_openai"]["max_tokens"]=1024; //Maximum tokens to generate.
$ITT["google_openai"]["detail"]='low';	//(Low|high) fidelity image understanding.
$ITT["google_openai"]["API_KEY"]=''; //OpenAI API key.
$ITT["google_openai"]["AI_VISION_PROMPT"]="Let's roleplay in the world of Skyrim. "
    . "Describe this Skyrim image as if it is real life. "
    . "Describe the environment, objects, and people you see at a fifth grade reading level. "
    . "Ignore video game HUD and UI elements in your description."; //Prompt to sent to the Vision AI.
$ITT["google_openai"]["AI_PROMPT"]='#HERIKA_NPC1# describes what they are seeing'; //Prompt sent to the LLM.
//Azure
$ITT["AZURE"]["ENDPOINT"]=""; //API endpoint.
$ITT["AZURE"]["API_KEY"]=""; //API key.
//Llama
$ITT["llamacpp"]["URL"]="http://127.0.0.1:8007"; //Server endpoint.		
$ITT["llamacpp"]["AI_VISION_PROMPT"]="USER:Context, roleplay In Skyrim universe, #HERIKA_NPC1# watchs this scene:[img-1]. "
    . "Describe the vision while keeping roleplay. Describe COLORS and SHAPES";	//Prompt to sent to the Vision AI.
$ITT["llamacpp"]["AI_PROMPT"]=''; //Prompt sent to the LLM.

//[Memory Configuration]
//Memory Settings
$FEATURES["MEMORY_EMBEDDING"]["ENABLED"]=true; //Long term memory embedding.
$FEATURES["MEMORY_EMBEDDING"]["TXTAI_URL"]='http://127.0.0.1:8082'; //Text2Vec service
$FEATURES["MEMORY_EMBEDDING"]["USE_TEXT2VEC"]=false; //NOT FUNCTIONAL CURRENTLY. JUST LEAVE AS IS!

$FEATURES["MEMORY_EMBEDDING"]["MEMORY_TIME_DELAY"]=10; //Time in minutes to delay before using a memory in a prompt.
$FEATURES["MEMORY_EMBEDDING"]["MEMORY_CONTEXT_SIZE"]=1; //Amount of memory records that will be injected into the prompt.
$FEATURES["MEMORY_EMBEDDING"]["AUTO_CREATE_SUMMARYS"]=false; //Combines individual memory logs into larger ones at the cost of tokens.
$FEATURES["MEMORY_EMBEDDING"]["AUTO_CREATE_SUMMARY_INTERVAL"]=10; //Time frame used to pack summary data.
$FEATURES["MEMORY_EMBEDDING"]["MEMORY_BIAS_A"]=33; //0-100 - Minimal distance to offer memory.
$FEATURES["MEMORY_EMBEDDING"]["MEMORY_BIAS_B"]=66; //0-100 - Minimal distance to endorse memory.
//Other Options
$FEATURES["MISC"]["ADD_TIME_MARKS"]=false; //Add timestamps to the context logs. Assists with memory recollection.
$FEATURES["MISC"]["ITT_QUALITY"]=90; //0-100 - Image compression and comprehension. Only for Soulgaze HD.
$FEATURES["MISC"]["TTS_RANDOM_PITCH"]=false; //Adjusting the pitch when generating the voice for this actor will add variation.
$FEATURES["MISC"]["OGHMA_INFINIUM"]=false;	//Skyrim context information will be added to the prompt. Use for small weight LLMs.
$FEATURES["MISC"]["JSON_DIALOGUE_FORMAT_REORDER"]=false; //Reorders properties in the offered JSON schema.
$FEATURES["EXPERIMENTAL"]["KOBOLDCPP_ACTIONS"]=false; //KoboldCPP Actions.

$OGHMA_INFINIUM=false;

$FEATURES["MISC"]["LIFE_LINK_PLUGIN"]=false; // WIP. Use life link plugin for dynamic profiles

$BORED_EVENT_SERVERSIDE=false;
$RECHAT_ALLOW_ACTIONS=false;

?>
