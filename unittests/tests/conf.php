<?php

//[Main Configuration]
$GLOBALS["PLAYER_NAME"]="Prisoner"; //Player's current character name.
$GLOBALS["DBDRIVER"]="phpunit"; //Database - Do not change.
$GLOBALS["HERIKA_NAME"]="The Narrator"; //NPC name. MUST MATCH their Skyrim in-game NPC name!
$GLOBALS["PROMPT_HEAD"]="Let's roleplay in the Universe of Skyrim."; //System Prompt. Defines the rules of the roleplay.
$GLOBALS["PLAYER_BIOS"]="I'm #PLAYER_NAME#"; //Player character description. 
$GLOBALS["HERIKA_PERS"]="You are The Narrator in a Skyrim adventure. You will only talk to #PLAYER_NAME#. "
    . "You refer to yourself as 'The Narrator'. "
    . "Only #PLAYER_NAME# can hear you. "
    . "Your goal is to comment on #PLAYER_NAME#'s playthrough, and occasionally give hints. NO SPOILERS. " 
    . "Talk about quests and last events."; //NPC personality.
$GLOBALS["DYNAMIC_PROFILE"]=false; //Dynamic profile updates during certain ingame events.
$GLOBALS["MINIME_T5"]=true; //Assists smaller weight LLMs with action and memory functions.

//[Advanced Configuration]
$GLOBALS["RECHAT_H"]=2; //Rechat Rounds. Higher values will increase the amount of rounds NPC's will talk amongst themselves.
$GLOBALS["RECHAT_P"]=50; //Rechat Probability.
$GLOBALS["BORED_EVENT"]=5; //Bored Event Probability. Chance of an NPC starting a random conversation after a set period of time.
$GLOBALS["CONTEXT_HISTORY"]="50"; //Amount of context history (dialogue and events) that will be sent to LLM.
$GLOBALS["HTTP_TIMEOUT"]=15; //Timeout for AI requests.
$GLOBALS["CORE_LANG"]=""; //Custom languages. - language folder
$GLOBALS["NEWQUEUE"]=true; //Leave as is - read only
$GLOBALS["MAX_WORDS_LIMIT"]=0; //Enforce a word limit for AI's responses. 0 = unlimited.
$GLOBALS["BOOK_EVENT_FULL"]=true; //Sends full contents of books to the AI
$GLOBALS["BOOK_EVENT_ALWAYS_NARRATOR"]=false; //Only The Narrator summarizes books.
$GLOBALS["NARRATOR_TALKS"]=true; //Enables the Narrator.
$GLOBALS["NARRATOR_WELCOME"]=false; //The Narrator will recap previous events after a save is loaded.
$GLOBALS["LANG_LLM_XTTS"]=false; //XTTS Only! Will offer a language field to LLM, and will try match to XTTSv2 language.
$GLOBALS["HERIKA_ANIMATIONS"]=true; //Issues animations to AI driven NPCs.
$GLOBALS["EMOTEMOODS"]="sassy,"
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
$GLOBALS["SUMMARY_PROMPT"]=''; //Instructions added when generating summaries for memories and other features.

//[AI/LLM Service Selection]
$GLOBALS["CONNECTORS"]=["openrouterjson"]; //AI Service(s).
$GLOBALS["CONNECTORS_DIARY"]=["openrouter","openai","google_openaijson","koboldcpp"]; //Creates diary entries and memories.

//[AI/LLM Connectors]
//OpenRouter JSON
$GLOBALS["CONNECTOR"]["openrouterjson"]["url"]="https://openrouter.ai/api/v1/chat/completions"; //API endpoint.
$GLOBALS["CONNECTOR"]["openrouterjson"]["model"]="meta-llama/llama-3.1-70b-instruct"; //LLM model.
$GLOBALS["CONNECTOR"]["openrouterjson"]["max_tokens"]='512'; //Maximum tokens to generate.
$GLOBALS["CONNECTOR"]["openrouterjson"]["temperature"]=0.8; //LLM parameter temperature.
$GLOBALS["CONNECTOR"]["openrouterjson"]["presence_penalty"]=0; //LLM parameter presence_penalty.
$GLOBALS["CONNECTOR"]["openrouterjson"]["frequency_penalty"]=0; //LLM parameter frequency_penalty.
$GLOBALS["CONNECTOR"]["openrouterjson"]["repetition_penalty"]=1.1;	//LLM parameter repetition_penalty.
$GLOBALS["CONNECTOR"]["openrouterjson"]["top_p"]=1; //LLM parameter top_p.
$GLOBALS["CONNECTOR"]["openrouterjson"]["top_k"]=40; //LLM parameter top_k.
$GLOBALS["CONNECTOR"]["openrouterjson"]["min_p"]=0; //LLM parameter min_p.
$GLOBALS["CONNECTOR"]["openrouterjson"]["top_a"]=0; //LLM parameter top_a.
$GLOBALS["CONNECTOR"]["openrouterjson"]["ENFORCE_JSON"]=true; //Attempts to enforce JSON. Only valid for some models.
$GLOBALS["CONNECTOR"]["openrouterjson"]["PREFILL_JSON"]=false; //Prefill JSON, Only valid for some models.
$GLOBALS["CONNECTOR"]["openrouterjson"]["MAX_TOKENS_MEMORY"]='1024'; //Maximum tokens to generate when summarizing.
$GLOBALS["CONNECTOR"]["openrouterjson"]["API_KEY"]=""; //API key.
$GLOBALS["CONNECTOR"]["openrouterjson"]["xreferer"]="https://www.nexusmods.com/skyrimspecialedition/mods/89931"; //Stub needed header.
$GLOBALS["CONNECTOR"]["openrouterjson"]["xtitle"]="Skyrim AI Follower Framework"; //Stub needed header.
$GLOBALS["CONNECTOR"]["openrouterjson"]["json_schema"]=false; //Enable OpenRouter JSON schema.
//OpenRouter (Legacy)
$GLOBALS["CONNECTOR"]["openrouter"]["url"]="https://openrouter.ai/api/v1/chat/completions"; //API endpoint.
$GLOBALS["CONNECTOR"]["openrouter"]["model"]="meta-llama/llama-3.1-8b-instruct"; //LLM model.
$GLOBALS["CONNECTOR"]["openrouter"]["max_tokens"]=1024; //Maximum tokens to generate.
$GLOBALS["CONNECTOR"]["openrouter"]["temperature"]=0.9; //LLM parameter temperature.
$GLOBALS["CONNECTOR"]["openrouter"]["presence_penalty"]=0;	//LLM parameter presence_penalty.
$GLOBALS["CONNECTOR"]["openrouter"]["frequency_penalty"]=0; //LLM parameter frequency_penalty.
$GLOBALS["CONNECTOR"]["openrouter"]["repetition_penalty"]=0.9;	//LLM parameter repetition_penalty.
$GLOBALS["CONNECTOR"]["openrouter"]["top_k"]=0; //LLM parameter top_k.
$GLOBALS["CONNECTOR"]["openrouter"]["top_p"]=1; //LLM parameter top_p.
$GLOBALS["CONNECTOR"]["openrouter"]["min_p"]=0.1; //LLM parameter min_p.
$GLOBALS["CONNECTOR"]["openrouter"]["top_a"]=0; //LLM parameter top_a.
$GLOBALS["CONNECTOR"]["openrouter"]["MAX_TOKENS_MEMORY"]="1024"; //Maximum tokens to generate when summarizing.
$GLOBALS["CONNECTOR"]["openrouter"]["API_KEY"]=""; //API key.
$GLOBALS["CONNECTOR"]["openrouter"]["xreferer"]="https://www.nexusmods.com/skyrimspecialedition/mods/89931"; //Stub needed header.
$GLOBALS["CONNECTOR"]["openrouter"]["xtitle"]="Skyrim AI Follower Framework"; //Stub needed header.
//OpenAI JSON
$GLOBALS["CONNECTOR"]["openaijson"]["url"]="https://api.openai.com/v1/chat/completions"; //API endpoint.
$GLOBALS["CONNECTOR"]["openaijson"]["model"]='gpt-4o-mini'; //LLM model.
$GLOBALS["CONNECTOR"]["openaijson"]["max_tokens"]='512'; //Maximum tokens to generate.
$GLOBALS["CONNECTOR"]["openaijson"]["temperature"]=1; //LLM parameter temperature.
$GLOBALS["CONNECTOR"]["openaijson"]["presence_penalty"]=1; //LLM parameter presence_penalty.
$GLOBALS["CONNECTOR"]["openaijson"]["frequency_penalty"]=0; //LLM parameter frequency_penalty.
$GLOBALS["CONNECTOR"]["openaijson"]["top_p"]=1; //LLM parameter top_p.
$GLOBALS["CONNECTOR"]["openaijson"]["API_KEY"]=""; //API key.
$GLOBALS["CONNECTOR"]["openaijson"]["MAX_TOKENS_MEMORY"]="1024"; //Maximum tokens to generate when summarizing.
$GLOBALS["CONNECTOR"]["openaijson"]["json_schema"]=false; //Enable OpenAI JSON schema.
//OpenAI (Legacy)
$GLOBALS["CONNECTOR"]["openai"]["url"]="https://api.openai.com/v1/chat/completions";
$GLOBALS["CONNECTOR"]["openai"]["model"]='gpt-4o-mini'; //LLM model.
$GLOBALS["CONNECTOR"]["openai"]["max_tokens"]='1024'; //Maximum tokens to generate.
$GLOBALS["CONNECTOR"]["openai"]["temperature"]=1; //LLM parameter temperature.
$GLOBALS["CONNECTOR"]["openai"]["presence_penalty"]=1; //LLM parameter presence_penalty.
$GLOBALS["CONNECTOR"]["openai"]["frequency_penalty"]=0; //LLM parameter frequency_penalty.
$GLOBALS["CONNECTOR"]["openai"]["top_p"]=1; //LLM parameter top_p.
$GLOBALS["CONNECTOR"]["openai"]["API_KEY"]=""; //API key.
$GLOBALS["CONNECTOR"]["openai"]["MAX_TOKENS_MEMORY"]="1024"; //Maximum tokens to generate when summarizing.
//Google OpenAI JSON
$GLOBALS["CONNECTOR"]["google_openaijson"]["url"]="https://generativelanguage.googleapis.com/v1beta/openai/chat/completions"; //API endpoint.
$GLOBALS["CONNECTOR"]["google_openaijson"]["model"]='gemini-1.5-flash'; //LLM model.
$GLOBALS["CONNECTOR"]["google_openaijson"]["max_tokens"]='512'; //Maximum tokens to generate.
$GLOBALS["CONNECTOR"]["google_openaijson"]["temperature"]=1; //LLM parameter temperature.
$GLOBALS["CONNECTOR"]["google_openaijson"]["top_p"]=0.95; //LLM parameter top_p.
$GLOBALS["CONNECTOR"]["google_openaijson"]["API_KEY"]=""; //API key.
$GLOBALS["CONNECTOR"]["google_openaijson"]["MAX_TOKENS_MEMORY"]="800"; //Maximum tokens to generate when summarizing.
$GLOBALS["CONNECTOR"]["google_openaijson"]["json_schema"]=false; //Enable OpenAI JSON schema.
//KoboldCPP JSON
$GLOBALS["CONNECTOR"]["koboldcppjson"]["url"]='http://127.0.0.1:5001';	//KoboldCPP API Endpoint.
$GLOBALS["CONNECTOR"]["koboldcppjson"]["max_tokens"]='512';	//Maximum tokens to generate.
$GLOBALS["CONNECTOR"]["koboldcppjson"]["temperature"]=0.9;	//LLM parameter temperature.
$GLOBALS["CONNECTOR"]["koboldcppjson"]["rep_pen"]=1.12;	//LLM parameter rep_pen.
$GLOBALS["CONNECTOR"]["koboldcppjson"]["top_p"]=0.9;	//LLM parameter top_p.
$GLOBALS["CONNECTOR"]["koboldcppjson"]["min_p"]=0;	//LLM parameter min_p.
$GLOBALS["CONNECTOR"]["koboldcppjson"]["top_k"]=0;	//LLM parameter top_k.
$GLOBALS["CONNECTOR"]["koboldcppjson"]["PREFILL_JSON"]=false; //Prefill JSON, Only valid for some models.
$GLOBALS["CONNECTOR"]["koboldcppjson"]["MAX_TOKENS_MEMORY"]='256';	//Maximum tokens to generate when summarizing.
$GLOBALS["CONNECTOR"]["koboldcppjson"]["newline_as_stopseq"]=false; //A newline in the output that will be considered a stop sequence. Recommended to leave it as default.
$GLOBALS["CONNECTOR"]["koboldcppjson"]["use_default_badwordsids"]=true; //Unban End of Sentence (EOS) tokens. If set to false the LLM will stop generating when it detects an EOS token.
$GLOBALS["CONNECTOR"]["koboldcppjson"]["eos_token"]='<|eot_id|>'; //EOS token LLM uses. Only works if use_default_badwordsids is enabled.
$GLOBALS["CONNECTOR"]["koboldcppjson"]["template"]='chatml'; //Prompt format specified in the HuggingFace model card.
$GLOBALS["CONNECTOR"]["koboldcppjson"]["grammar"]=false; //Enforces use of JSON grammar at the cost of slower generation speed. 
//KoboldCPP (Legacy)
$GLOBALS["CONNECTOR"]["koboldcpp"]["url"]='http://127.0.0.1:5001';	//KoboldCPP API Endpoint.
$GLOBALS["CONNECTOR"]["koboldcpp"]["max_tokens"]='512'; //Maximum tokens to generate.
$GLOBALS["CONNECTOR"]["koboldcpp"]["temperature"]=1; //LLM parameter temperature.
$GLOBALS["CONNECTOR"]["koboldcpp"]["rep_pen"]=1; //LLM parameter rep_pen.
$GLOBALS["CONNECTOR"]["koboldcpp"]["top_p"]=1;	//LLM parameter top_p.
$GLOBALS["CONNECTOR"]["koboldcpp"]["min_p"]=0.01; //LLM parameter min_p.
$GLOBALS["CONNECTOR"]["koboldcpp"]["top_k"]=0;	//LLM parameter top_k.
$GLOBALS["CONNECTOR"]["koboldcpp"]["MAX_TOKENS_MEMORY"]='512';	//Maximum tokens to generate when summarizing.
$GLOBALS["CONNECTOR"]["koboldcpp"]["newline_as_stopseq"]=false; //A newline in the output that will be considered a stop sequence. Recommended to leave it as default.
$GLOBALS["CONNECTOR"]["koboldcpp"]["use_default_badwordsids"]=false; //Unban End of Sentence (EOS) tokens. If set to false the LLM will stop generating when it detects an EOS token.
$GLOBALS["CONNECTOR"]["koboldcpp"]["eos_token"]='<|im_end|>'; //EOS token LLM uses. Only works if use_default_badwordsids is enabled.
$GLOBALS["CONNECTOR"]["koboldcpp"]["template"]='chatml'; //Prompt Format. Specified in the HuggingFace model card.
//Oobabooga
$GLOBALS["CONNECTOR"]["oobabooga"]["HOST"]="127.0.0.1"; //API Endpoint.
$GLOBALS["CONNECTOR"]["oobabooga"]["PORT"]="5005"; //API server port.
$GLOBALS["CONNECTOR"]["oobabooga"]["MAX_TOKENS_MEMORY"]="512"; //Maximum tokens to generate when summarizing.
$GLOBALS["CONNECTOR"]["oobabooga"]["max_tokens"]=100; //Maximum tokens to generate.
$GLOBALS["CONNECTOR"]["oobabooga"]["temperature"]=0.7; //LLM parameter temperature.
$GLOBALS["CONNECTOR"]["oobabooga"]["rep_pen"]=1.18; //LLM parameter rep_pen.
//LlamaCPP
$GLOBALS["CONNECTOR"]["llamacpp"]["url"]='http://127.0.0.1:8007';	//Llama.cpp server API
$GLOBALS["CONNECTOR"]["llamacpp"]["max_tokens"]="75"; //Maximum tokens to generate (n_predict).
$GLOBALS["CONNECTOR"]["llamacpp"]["temperature"]=0.7; //LLM parameter temperature.
$GLOBALS["CONNECTOR"]["llamacpp"]["rep_pen"]=1.12;	//LLM parameter rep_pen.
$GLOBALS["CONNECTOR"]["llamacpp"]["top_p"]=0.9; //LLM parameter top_p.
$GLOBALS["CONNECTOR"]["llamacpp"]["MAX_TOKENS_MEMORY"]='512'; //Maximum tokens to generate when summarizing.
$GLOBALS["CONNECTOR"]["llamacpp"]["eos_token"]='</s>';	//EOS token LLM uses.
$GLOBALS["CONNECTOR"]["llamacpp"]["template"]='alpaca'; //Prompt Format. Specified in the HuggingFace model card.

//[Text-to-Speech Service]
$GLOBALS["TTSFUNCTION"]="phpunit";

//[Text-to-Speech Endpoints]
//MeloTTS
$GLOBALS["TTS"]["MELOTTS"]["endpoint"]='http://127.0.0.1:8084'; //API endpoint.
$GLOBALS["TTS"]["MELOTTS"]["language"]='EN'; //Lanuguage model.
$GLOBALS["TTS"]["MELOTTS"]["speed"]=1.0; //Speech speed.
$GLOBALS["TTS"]["MELOTTS"]["voiceid"]='malenord'; //Voice ID.
//CHIM XTTS
$GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]='http://127.0.0.1:8020'; //API endpoint.
$GLOBALS["TTS"]["XTTSFASTAPI"]["language"]='en'; //Lanuguage.
$GLOBALS["TTS"]["XTTSFASTAPI"]["voiceid"]='TheNarrator'; //Generated voice file name.
//MIMIC3
$GLOBALS["TTS"]["MIMIC3"]["URL"]="http://127.0.0.1:59125"; //API endpoint. 
$GLOBALS["TTS"]["MIMIC3"]["voice"]="en_UK/apope_low#default"; //Voice ID.
$GLOBALS["TTS"]["MIMIC3"]["rate"]="1"; //Speech speed.
$GLOBALS["TTS"]["MIMIC3"]["volume"]="60"; //Speech volume.
//xVASynth
$GLOBALS["TTS"]["XVASYNTH"]["url"]='http://192.168.0.1:8008';	//xVASynth must be run in same machine as DwemerDistro, so this must be http://your-local-ip:8008
$GLOBALS["TTS"]["XVASYNTH"]["base_lang"]='en';	//Base language.
$GLOBALS["TTS"]["XVASYNTH"]["modelType"]='xVAPitch'; //ModelType.
$GLOBALS["TTS"]["XVASYNTH"]["model"]='sk_malenord'; //Model.
$GLOBALS["TTS"]["XVASYNTH"]["pace"]=1.0; //Pace.
$GLOBALS["TTS"]["XVASYNTH"]["waveglowPath"]='resources/app/models/waveglow_256channels_universal_v4.pt'; //waveglowPath (relative).
$GLOBALS["TTS"]["XVASYNTH"]["vocoder"]='n/a';	//vocoder.
$GLOBALS["TTS"]["XVASYNTH"]["distroname"]='DwemerAI4Skyrim3'; //Leave as default.
//Azure TTS
$GLOBALS["TTS"]["AZURE"]["fixedMood"]=""; //Voice Style.
$GLOBALS["TTS"]["AZURE"]["region"]="westeurope"; //API Region.
$GLOBALS["TTS"]["AZURE"]["voice"]="en-US-NancyNeural";	//Voice ID.
$GLOBALS["TTS"]["AZURE"]["volume"]="20"; //Voice volume.				
$GLOBALS["TTS"]["AZURE"]["rate"]="1.25"; //Speech rate.	
$GLOBALS["TTS"]["AZURE"]["countour"]="(11%, +15%) (60%, -23%) (80%, -34%)"; //Voice contour.							
$GLOBALS["TTS"]["AZURE"]["validMoods"]=array("whispering","default","dazed"); //Allowed voice styles.	
$GLOBALS["TTS"]["AZURE"]["API_KEY"]=""; //API key.
//OpenAI TTS
$GLOBALS["TTS"]["openai"]["endpoint"]='https://api.openai.com/v1/audio/speech'; //API endpoint.
$GLOBALS["TTS"]["openai"]["API_KEY"]=''; //API key.
$GLOBALS["TTS"]["openai"]["voice"]='nova';	//Voice ID.
$GLOBALS["TTS"]["openai"]["model_id"]='tts-1';	//Model.
//ElevenLabs TTS
$GLOBALS["TTS"]["ELEVEN_LABS"]["voice_id"]="EXAVITQu4vr4xnSDxMaL";	//Voice ID.
$GLOBALS["TTS"]["ELEVEN_LABS"]["optimize_streaming_latency"]="0"; //Optimize streaming latency.
$GLOBALS["TTS"]["ELEVEN_LABS"]["model_id"]="eleven_monolingual_v1"; //Model ID.
$GLOBALS["TTS"]["ELEVEN_LABS"]["stability"]="0.75"; //Stability.
$GLOBALS["TTS"]["ELEVEN_LABS"]["similarity_boost"]="0.75"; //Similarity boost.
$GLOBALS["TTS"]["ELEVEN_LABS"]["style"]=0.0; //Style.
$GLOBALS["TTS"]["ELEVEN_LABS"]["API_KEY"]=""; //API key.
//Google Cloud Platform TTS
$GLOBALS["TTS"]["GCP"]["GCP_SA_FILEPATH"]="meta-chassis-391906-122bdf85aa6f.json"; //Google Cloud Platform auth file.
$GLOBALS["TTS"]["GCP"]["voice_name"]="en-GB-Neural2-C"; //Voice ID.
$GLOBALS["TTS"]["GCP"]["voice_languageCode"]="en-GB"; //Language ID.
$GLOBALS["TTS"]["GCP"]["ssml_rate"]=1.15; //Speech rate.
$GLOBALS["TTS"]["GCP"]["ssml_pitch"]="+3.6st"; //Speech pitch.
//CONVAI TTS
$GLOBALS["TTS"]["CONVAI"]["endpoint"]='https://api.convai.com/tts'; //API endpoint.
$GLOBALS["TTS"]["CONVAI"]["API_KEY"]=''; //API key.
$GLOBALS["TTS"]["CONVAI"]["language"]='en-US';	//Language.
$GLOBALS["TTS"]["CONVAI"]["voiceid"]='WUFemale3'; //Voice ID.
//XTTS
$GLOBALS["TTS"]["XTTS"]["endpoint"]=''; //API endpoint.
$GLOBALS["TTS"]["XTTS"]["language"]='en'; //Launguage.
$GLOBALS["TTS"]["XTTS"]["voiceid"]='11labs_diane';	//Voice JSON file.
//StyleTTSv2
$GLOBALS["TTS"]["STYLETTSV2"]["endpoint"]='http://127.0.0.1:5050/'; //API endpoint.
$GLOBALS["TTS"]["STYLETTSV2"]["voice"]='';	//WAV file with source voice to clone. Should be localte at /var/www/html/HerikaServer/data/voices/
$GLOBALS["TTS"]["STYLETTSV2"]["alpha"]=0.3; //0.0-1.0 - Alpha determines the timbre of the speaker.
$GLOBALS["TTS"]["STYLETTSV2"]["beta"]=0.7;	//0.0-1.0 - Beta determines the prosody of the speaker.
$GLOBALS["TTS"]["STYLETTSV2"]["diffusion_steps"]=15; //5.0 > Vocal variety at the cost of slower synthesis speed.
$GLOBALS["TTS"]["STYLETTSV2"]["embedding_scale"]=1.5;//0.0-1.0 - This is the classifier-free guidance scale. Dictates emotional scale.
//CONQUI TTS
$GLOBALS["TTS"]["COQUI_AI"]["voice_id"]='f05c5b91-7540-4b26-b534-e820d43065d1'; //Voice ID.
$GLOBALS["TTS"]["COQUI_AI"]["speed"]=1; //Speech rate.
$GLOBALS["TTS"]["COQUI_AI"]["language"]='en'; //Language.
$GLOBALS["TTS"]["COQUI_AI"]["API_KEY"]='';	//Coqui.ai API key.

//[Player TTS]
$GLOBALS["TTSFUNCTION_PLAYER"]="none";
$GLOBALS["TTSFUNCTION_PLAYER_VOICE"]="malenord";

//[Speech-to-Text Service]
$GLOBALS["STTFUNCTION"]="whisper";

//[Speech-to-Text Endpoints]
//OpenAI Whisper STT
$GLOBALS["STT"]["WHISPER"]["LANG"]="en"; //Language.
$GLOBALS["STT"]["WHISPER"]["TRANSLATE"]=false; //Attempt to translate to English.
$GLOBALS["STT"]["WHISPER"]["API_KEY"]=""; //API Key.
//Azure STT
$GLOBALS["STT"]["AZURE"]["LANG"]="en-US"; //Language.
$GLOBALS["STT"]["AZURE"]["profanity"]="masked"; //Profanity handling filter.
$GLOBALS["STT"]["AZURE"]["API_KEY"]=""; //API key.
//Local Whisper STT
$GLOBALS["STT"]["LOCALWHISPER"]["URL"]="http://127.0.0.1:9876/api/v0/transcribe"; //API endpoint.
$GLOBALS["STT"]["LOCALWHISPER"]["FORMFIELD"]="audio_file"; //(audio_file,file) Form field name.

//[Image to Text (Soulgaze)]
$GLOBALS["ITTFUNCTION"]="llamacpp";
//OpenAI
$GLOBALS["ITT"]["openai"]["url"]='https://api.openai.com/v1/chat/completions';	//OpenAI API endpoint.
$GLOBALS["ITT"]["openai"]["model"]='gpt-4o-mini'; //LLM model.
$GLOBALS["ITT"]["openai"]["max_tokens"]=1024; //Maximum tokens to generate.
$GLOBALS["ITT"]["openai"]["detail"]='low';	//(Low|high) fidelity image understanding.
$GLOBALS["ITT"]["openai"]["API_KEY"]=''; //OpenAI API key.
$GLOBALS["ITT"]["openai"]["AI_VISION_PROMPT"]="Let\'s roleplay in the world of Skyrim. "
    . "Describe this Skyrim image as if it is real life. "
    . "Describe the environment, objects, and people you see at a fifth grade reading level. "
    . "Ignore video game HUD and UI elements in your description."; //Prompt to sent to the Vision AI.
$GLOBALS["ITT"]["openai"]["AI_PROMPT"]='#HERIKA_NPC1# describes what they are seeing'; //Prompt sent to the LLM.
//Google
$GLOBALS["ITT"]["google_openai"]["url"]='https://generativelanguage.googleapis.com/v1beta/openai/chat/completions'; //OpenAI API endpoint.
$GLOBALS["ITT"]["google_openai"]["model"]='gemini-1.5-flash'; //LLM model.
$GLOBALS["ITT"]["google_openai"]["max_tokens"]=1024; //Maximum tokens to generate.
$GLOBALS["ITT"]["google_openai"]["detail"]='low';	//(Low|high) fidelity image understanding.
$GLOBALS["ITT"]["google_openai"]["API_KEY"]=''; //OpenAI API key.
$GLOBALS["ITT"]["google_openai"]["AI_VISION_PROMPT"]="Let's roleplay in the world of Skyrim. "
    . "Describe this Skyrim image as if it is real life. "
    . "Describe the environment, objects, and people you see at a fifth grade reading level. "
    . "Ignore video game HUD and UI elements in your description."; //Prompt to sent to the Vision AI.
$GLOBALS["ITT"]["google_openai"]["AI_PROMPT"]='#HERIKA_NPC1# describes what they are seeing'; //Prompt sent to the LLM.
//Azure
$GLOBALS["ITT"]["AZURE"]["ENDPOINT"]=""; //API endpoint.
$GLOBALS["ITT"]["AZURE"]["API_KEY"]=""; //API key.
//Llama
$GLOBALS["ITT"]["llamacpp"]["URL"]="http://127.0.0.1:8007"; //Server endpoint.		
$GLOBALS["ITT"]["llamacpp"]["AI_VISION_PROMPT"]="USER:Context, roleplay In Skyrim universe, #HERIKA_NPC1# watchs this scene:[img-1]. "
    . "Describe the vision while keeping roleplay. Describe COLORS and SHAPES";	//Prompt to sent to the Vision AI.
$GLOBALS["ITT"]["llamacpp"]["AI_PROMPT"]=''; //Prompt sent to the LLM.

//[Memory Configuration]
//Memory Settings
$GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]=true; //Long term memory embedding.
$GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["TXTAI_URL"]='http://127.0.0.1:8083'; //NOT FUNCTIONAL CURRENTLY. JUST LEAVE AS IS!
$GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["MEMORY_TIME_DELAY"]=10; //Time in minutes to delay before using a memory in a prompt.
$GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["MEMORY_CONTEXT_SIZE"]=1; //Amount of memory records that will be injected into the prompt.
$GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["AUTO_CREATE_SUMMARYS"]=false; //Combines individual memory logs into larger ones at the cost of tokens.
$GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["AUTO_CREATE_SUMMARY_INTERVAL"]=10; //Time frame used to pack summary data.
$GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["MEMORY_BIAS_A"]=33; //0-100 - Minimal distance to offer memory.
$GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["MEMORY_BIAS_B"]=66; //0-100 - Minimal distance to endorse memory.
//Other Options
$GLOBALS["FEATURES"]["MISC"]["ADD_TIME_MARKS"]=false; //Add timestamps to the context logs. Assists with memory recollection.
$GLOBALS["FEATURES"]["MISC"]["ITT_QUALITY"]=90; //0-100 - Image compression and comprehension. Only for Soulgaze HD.
$GLOBALS["FEATURES"]["MISC"]["TTS_RANDOM_PITCH"]=false; //Adjusting the pitch when generating the voice for this actor will add variation.
$GLOBALS["FEATURES"]["MISC"]["OGHMA_INFINIUM"]=false;	//Skyrim context information will be added to the prompt. Use for small weight LLMs.
$GLOBALS["FEATURES"]["MISC"]["JSON_DIALOGUE_FORMAT_REORDER"]=false; //Reorders properties in the offered JSON schema.
$GLOBALS["FEATURES"]["EXPERIMENTAL"]["KOBOLDCPP_ACTIONS"]=false; //KoboldCPP Actions.

$GLOBALS["OGHMA_INFINIUM"]=true;

global $FUNCTIONS_ARE_ENABLED;
global $TEMPLATE_DIALOG;
global $TEMPLATE_ACTION;
global $MAXIMUM_WORDS;
global $FUNCTION_PARM_INSPECT;
global $COMMAND_PROMPT;
global $COMMAND_PROMPT_ENFORCE_ACTIONS;
global $gameRequest;

require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."phpunit.class.php");
$GLOBALS["db"] = new sql();
$GLOBALS["contextDataFull"]=[];
?>
