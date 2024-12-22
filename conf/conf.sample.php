<?php

//[Main Configuration]
$PLAYER_NAME="Prisoner"; //Player's current character name.
$DBDRIVER="postgresql"; //Database - Do not change.
$HERIKA_NAME="The Narrator"; //NPC name. MUST MATCH their Skyrim in-game NPC name!
$PROMPT_HEAD="Let's roleplay in the Universe of Skyrim."; //System Prompt. Defines the rules of the roleplay.
$PLAYER_BIOS="I'm #PLAYER_NAME#"; //Player character description. 
$HERIKA_PERS="You are The Narrator in a Skyrim adventure. You will only talk to #PLAYER_NAME#. "
    . "You refer to yourself as 'The Narrator'. "
    . "Only #PLAYER_NAME# can hear you. "
    . "Your goal is to comment on #PLAYER_NAME#'s playthrough, and occasionally give hints. NO SPOILERS. " 
    . "Talk about quests and last events."; //NPC personality.
$DYNAMIC_PROFILE=false; //Dynamic profile updates during certain ingame events.
$MINIME_T5=false; //Assists smaller weight LLMs with action and memory functions.

//[Advanced Configuration]
$RECHAT_H=2; //Rechat Rounds. Higher values will increase the amount of rounds NPC's will talk amongst themselves.
$RECHAT_P=50; //Rechat Probability.
$BORED_EVENT=5; //Bored Event Probability. Chance of an NPC starting a random conversation after a set period of time.
$CONTEXT_HISTORY="50"; //Amount of context history (dialogue and events) that will be sent to LLM.
$HTTP_TIMEOUT=15; //Timeout for AI requests.
$CORE_LANG=""; //Custom languages. - language folder
$NEWQUEUE=true; //Leave as is - read only
$MAX_WORDS_LIMIT=0; //Enforce a word limit for AI's responses. 0 = unlimited.
$BOOK_EVENT_FULL=true; //Sends full contents of books to the AI
$BOOK_EVENT_ALWAYS_NARRATOR=false; //Only The Narrator summarizes books.
$NARRATOR_TALKS=true; //Enables the Narrator.
$NARRATOR_WELCOME=false; //The Narrator will recap previous events after a save is loaded.
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
$SUMMARY_PROMPT=''; //Instructions added when generating summaries for memories and other features.

//[AI/LLM Service Selection]
$CONNECTORS=["openrouterjson","openaijson","google_openaijson","koboldcppjson"]; //AI Service(s).
$CONNECTORS_DIARY=["openrouter","openai","google_openaijson","koboldcpp"]; //Creates diary entries and memories.

//[AI/LLM Connectors]
//OpenRouter JSON
$CONNECTOR["openrouterjson"]["url"]="https://openrouter.ai/api/v1/chat/completions"; //API endpoint.
$CONNECTOR["openrouterjson"]["model"]="meta-llama/llama-3.1-70b-instruct"; //LLM model.
$CONNECTOR["openrouterjson"]["max_tokens"]='512'; //Maximum tokens to generate.
$CONNECTOR["openrouterjson"]["temperature"]=0.8; //LLM parameter temperature.
$CONNECTOR["openrouterjson"]["presence_penalty"]=0; //LLM parameter presence_penalty.
$CONNECTOR["openrouterjson"]["frequency_penalty"]=0; //LLM parameter frequency_penalty.
$CONNECTOR["openrouterjson"]["repetition_penalty"]=1.1;	//LLM parameter repetition_penalty.
$CONNECTOR["openrouterjson"]["top_p"]=1; //LLM parameter top_p.
$CONNECTOR["openrouterjson"]["top_k"]=40; //LLM parameter top_k.
$CONNECTOR["openrouterjson"]["min_p"]=0; //LLM parameter min_p.
$CONNECTOR["openrouterjson"]["top_a"]=0; //LLM parameter top_a.
$CONNECTOR["openrouterjson"]["ENFORCE_JSON"]=true; //Attempts to enforce JSON. Only valid for some models.
$CONNECTOR["openrouterjson"]["PREFILL_JSON"]=false; //Prefill JSON, Only valid for some models.
$CONNECTOR["openrouterjson"]["MAX_TOKENS_MEMORY"]='1024'; //Maximum tokens to generate when summarizing.
$CONNECTOR["openrouterjson"]["API_KEY"]=""; //API key.
$CONNECTOR["openrouterjson"]["xreferer"]="https://www.nexusmods.com/skyrimspecialedition/mods/89931"; //Stub needed header.
$CONNECTOR["openrouterjson"]["xtitle"]="Skyrim AI Follower Framework"; //Stub needed header.
$CONNECTOR["openrouterjson"]["json_schema"]=false; //Enable OpenRouter JSON schema.
//OpenRouter (Legacy)
$CONNECTOR["openrouter"]["url"]="https://openrouter.ai/api/v1/chat/completions"; //API endpoint.
$CONNECTOR["openrouter"]["model"]="meta-llama/llama-3.1-8b-instruct"; //LLM model.
$CONNECTOR["openrouter"]["max_tokens"]=1024; //Maximum tokens to generate.
$CONNECTOR["openrouter"]["temperature"]=0.9; //LLM parameter temperature.
$CONNECTOR["openrouter"]["presence_penalty"]=0;	//LLM parameter presence_penalty.
$CONNECTOR["openrouter"]["frequency_penalty"]=0; //LLM parameter frequency_penalty.
$CONNECTOR["openrouter"]["repetition_penalty"]=0.9;	//LLM parameter repetition_penalty.
$CONNECTOR["openrouter"]["top_k"]=0; //LLM parameter top_k.
$CONNECTOR["openrouter"]["top_p"]=1; //LLM parameter top_p.
$CONNECTOR["openrouter"]["min_p"]=0.1; //LLM parameter min_p.
$CONNECTOR["openrouter"]["top_a"]=0; //LLM parameter top_a.
$CONNECTOR["openrouter"]["MAX_TOKENS_MEMORY"]="1024"; //Maximum tokens to generate when summarizing.
$CONNECTOR["openrouter"]["API_KEY"]=""; //API key.
$CONNECTOR["openrouter"]["xreferer"]="https://www.nexusmods.com/skyrimspecialedition/mods/89931"; //Stub needed header.
$CONNECTOR["openrouter"]["xtitle"]="Skyrim AI Follower Framework"; //Stub needed header.
//OpenAI JSON
$CONNECTOR["openaijson"]["url"]="https://api.openai.com/v1/chat/completions"; //API endpoint.
$CONNECTOR["openaijson"]["model"]='gpt-4o-mini'; //LLM model.
$CONNECTOR["openaijson"]["max_tokens"]='512'; //Maximum tokens to generate.
$CONNECTOR["openaijson"]["temperature"]=1; //LLM parameter temperature.
$CONNECTOR["openaijson"]["presence_penalty"]=1; //LLM parameter presence_penalty.
$CONNECTOR["openaijson"]["frequency_penalty"]=0; //LLM parameter frequency_penalty.
$CONNECTOR["openaijson"]["top_p"]=1; //LLM parameter top_p.
$CONNECTOR["openaijson"]["API_KEY"]=""; //API key.
$CONNECTOR["openaijson"]["MAX_TOKENS_MEMORY"]="1024"; //Maximum tokens to generate when summarizing.
$CONNECTOR["openaijson"]["json_schema"]=false; //Enable OpenAI JSON schema.
//OpenAI (Legacy)
$CONNECTOR["openai"]["url"]="https://api.openai.com/v1/chat/completions";
$CONNECTOR["openai"]["model"]='gpt-4o-mini'; //LLM model.
$CONNECTOR["openai"]["max_tokens"]='1024'; //Maximum tokens to generate.
$CONNECTOR["openai"]["temperature"]=1; //LLM parameter temperature.
$CONNECTOR["openai"]["presence_penalty"]=1; //LLM parameter presence_penalty.
$CONNECTOR["openai"]["frequency_penalty"]=0; //LLM parameter frequency_penalty.
$CONNECTOR["openai"]["top_p"]=1; //LLM parameter top_p.
$CONNECTOR["openai"]["API_KEY"]=""; //API key.
$CONNECTOR["openai"]["MAX_TOKENS_MEMORY"]="1024"; //Maximum tokens to generate when summarizing.
//Google OpenAI JSON
$CONNECTOR["google_openaijson"]["url"]="https://generativelanguage.googleapis.com/v1beta/openai/chat/completions"; //API endpoint.
$CONNECTOR["google_openaijson"]["model"]='gemini-1.5-flash'; //LLM model.
$CONNECTOR["google_openaijson"]["max_tokens"]='512'; //Maximum tokens to generate.
$CONNECTOR["google_openaijson"]["temperature"]=1; //LLM parameter temperature.
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
$CONNECTOR["llamacpp"]["eos_token"]='</s>';	//EOS token LLM uses.
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
//MIMIC3
$TTS["MIMIC3"]["URL"]="http://127.0.0.1:59125"; //API endpoint. 
$TTS["MIMIC3"]["voice"]="en_UK/apope_low#default"; //Voice ID.
$TTS["MIMIC3"]["rate"]="1"; //Speech speed.
$TTS["MIMIC3"]["volume"]="60"; //Speech volume.
//xVASynth
$TTS["XVASYNTH"]["url"]='http://192.168.0.1:8008';	//xVASynth must be run in same machine as DwemerDistro, so this must be http://your-local-ip:8008
$TTS["XVASYNTH"]["base_lang"]='en';	//Base language.
$TTS["XVASYNTH"]["modelType"]='xVAPitch'; //ModelType.
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

//[Player TTS]
$TTSFUNCTION_PLAYER="none";
$TTSFUNCTION_PLAYER_VOICE="malenord";

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
$ITT["LLAMACPP"]["URL"]="http://127.0.0.1:8007"; //Server endpoint.		
$ITT["LLAMACPP"]["AI_VISION_PROMPT"]="USER:Context, roleplay In Skyrim universe, #HERIKA_NPC1# watchs this scene:[img-1]. "
    . "Describe the vision while keeping roleplay. Describe COLORS and SHAPES";	//Prompt to sent to the Vision AI.
$ITT["LLAMACPP"]["AI_PROMPT"]=''; //Prompt sent to the LLM.

//[Memory Configuration]
//Memory Settings
$FEATURES["MEMORY_EMBEDDING"]["ENABLED"]=true; //Long term memory embedding.
$FEATURES["MEMORY_EMBEDDING"]["TXTAI_URL"]='http://127.0.0.1:8083'; //NOT FUNCTIONAL CURRENTLY. JUST LEAVE AS IS!
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

?>
