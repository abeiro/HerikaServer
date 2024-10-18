<?php

$DBDRIVER="postgresql";

$PLAYER_NAME="Prisoner";
$HERIKA_NAME="The Narrator";

$PROMPT_HEAD="Let's roleplay in the Universe of Skyrim.";
$HERIKA_PERS="You are The Narrator in a Skyrim adventure. You will only talk to #PLAYER_NAME#. You refer to yourself as 'The Narrator'. Only #PLAYER_NAME# can hear you. Your goal is to comment on #PLAYER_NAME#'s playthrough, and occasionally, give some hints. NO SPOILERS. Talk about quests and last events.";

$BOOK_EVENT_ALWAYS_NARRATOR=false;
//The Narrator will talk to you.
$NARRATOR_TALKS=true;
$BOOK_EVENT_FULL=false;

$HERIKA_ANIMATIONS=true;
$DYNAMIC_PROFILE=false;
$NARRATOR_WELCOME=false;
$LANG_LLM_XTTS=false;
$EMOTEMOODS="sassy,assertive,sexy,smug,kindly,lovely,seductive,sarcastic,sardonic,smirking,amused,default,assisting,irritated,playful,neutral,teasing,mocking";
$MINIME_T5=false;

$RECHAT_H=2;
$RECHAT_P=50;
$BORED_EVENT=5;
$CORE_LANG="";

$NEWQUEUE=true;

$MAX_WORDS_LIMIT=0;

$CONTEXT_HISTORY="50";
$HTTP_TIMEOUT=15;                       // How long we will wait for openai response


$TTS["AZURE"]["fixedMood"]="";
$TTS["AZURE"]["region"]="westeurope";			
$TTS["AZURE"]["voice"]="en-US-NancyNeural";	
$TTS["AZURE"]["volume"]="20";					
$TTS["AZURE"]["rate"]="1.25";					
$TTS["AZURE"]["countour"]="(11%, +15%) (60%, -23%) (80%, -34%)";							
$TTS["AZURE"]["validMoods"]=array("whispering","default","dazed");			
$TTS["AZURE"]["API_KEY"]="";


$TTS["MIMIC3"]["URL"]="http://127.0.0.1:59125";   
$TTS["MIMIC3"]["voice"]="en_UK/apope_low#default";
$TTS["MIMIC3"]["rate"]="1";
$TTS["MIMIC3"]["volume"]="60";

$TTS["ELEVEN_LABS"]["voice_id"]="EXAVITQu4vr4xnSDxMaL";	//https://api.elevenlabs.io/v1/voices
$TTS["ELEVEN_LABS"]["optimize_streaming_latency"]="0";
$TTS["ELEVEN_LABS"]["model_id"]="eleven_monolingual_v1";
$TTS["ELEVEN_LABS"]["stability"]="0.75";
$TTS["ELEVEN_LABS"]["similarity_boost"]="0.75";
$TTS["ELEVEN_LABS"]["style"]=0.0;
$TTS["ELEVEN_LABS"]["API_KEY"]="";


$TTS["GCP"]["GCP_SA_FILEPATH"]="meta-chassis-391906-122bdf85aa6f.json";
$TTS["GCP"]["voice_name"]="en-GB-Neural2-C";
$TTS["GCP"]["voice_languageCode"]="en-GB";
$TTS["GCP"]["ssml_rate"]=1.15;
$TTS["GCP"]["ssml_pitch"]="+3.6st";

$TTS["COQUI_AI"]["voice_id"]='f05c5b91-7540-4b26-b534-e820d43065d1';	//Voice code
$TTS["COQUI_AI"]["speed"]=1;	//Speed
$TTS["COQUI_AI"]["language"]='en';	//Language to speak
$TTS["COQUI_AI"]["API_KEY"]='';	//Coqui.ai API key.

$TTS["openai"]["endpoint"]='https://api.openai.com/v1/audio/speech';	//End point
$TTS["openai"]["API_KEY"]='';	//API KEY
$TTS["openai"]["voice"]='nova';	//Voice ID
$TTS["openai"]["model_id"]='tts-1';	//Model


$STT["LOCALWHISPER"]["URL"]="http://127.0.0.1:9876/api/v0/transcribe";
$STT["LOCALWHISPER"]["FORMFIELD"]="audio_file";

$STT["AZURE"]["LANG"]="en-US";
$STT["AZURE"]["API_KEY"]="";
$STT["AZURE"]["profanity"]="masked";

$STT["WHISPER"]["LANG"]="en";
$STT["WHISPER"]["API_KEY"]="";
$STT["WHISPER"]["TRANSLATE"]=false;


$ITT["AZURE"]["ENDPOINT"]="";			
$ITT["AZURE"]["API_KEY"]="";


$ITT["LLAMACPP"]["URL"]="http://127.0.0.1:8007";			
$ITT["LLAMACPP"]["AI_VISION_PROMPT"]='USER:Context, roleplay In Skyrim universe, #HERIKA_NPC1# watchs this scene:[img-1]. Describe the vision while keeping roleplay.Describe COLORS and SHAPES';	
$ITT["LLAMACPP"]["AI_PROMPT"]='';



$ITT["openai"]["url"]='https://api.openai.com/v1/chat/completions';	//OpenAI API endpoint
$ITT["openai"]["model"]='gpt-4o-mini';	//Model to use
$ITT["openai"]["max_tokens"]=512;	//Maximum tokens to generate
$ITT["openai"]["detail"]='low';	//Low or high fidelity image understanding
$ITT["openai"]["API_KEY"]='';	//OpenAI API key
// Prompt send to GTP-V
$ITT["openai"]["AI_VISION_PROMPT"]='Let\'s roleplay in the world of Skyrim.  Describe this Skyrim image as if it is real life.  Describe the objects and people you see in a fifth grade reading level.  Ignore video game HUD and UI elements in your description.  ';	//Prompt to send to the Vision AI
// Prompt send to LLM
$ITT["openai"]["AI_PROMPT"]='#HERIKA_NPC1# describes what is seeing';


$STTFUNCTION="whisper";								// Valid options are azure or whisper so far
$TTSFUNCTION="mimic3";								// Valid options are azure or mimic3, or 11labs so far
$ITTFUNCTION="none";								// Valid options are azure or mimic3, or 11labs so far

$FEATURES["MISC"]["ITT_QUALITY"]=90;



//$CORE_LANG="es";

$FEATURES["MEMORY_EMBEDDING"]["ENABLED"]=true;
$FEATURES["MEMORY_EMBEDDING"]["TXTAI_URL"]='http://127.0.0.1:8083';
$FEATURES["MEMORY_EMBEDDING"]["MEMORY_TIME_DELAY"]=10;
$FEATURES["MEMORY_EMBEDDING"]["MEMORY_CONTEXT_SIZE"]=1;
$FEATURES["MEMORY_EMBEDDING"]["AUTO_CREATE_SUMMARYS"]=false;
$FEATURES["MEMORY_EMBEDDING"]["AUTO_CREATE_SUMMARY_INTERVAL"]=10;
$FEATURES["MEMORY_EMBEDDING"]["MEMORY_BIAS_A"]=33;
$FEATURES["MEMORY_EMBEDDING"]["MEMORY_BIAS_B"]=66;

$CONNECTORS=["openrouterjson","openaijson","koboldcppjson"];  
$CONNECTORS_DIARY=["openrouter","openai","koboldcpp"];  

$CONNECTOR["openai"]["url"]="https://api.openai.com/v1/chat/completions";
$CONNECTOR["openai"]["model"]='gpt-4o-mini';	//Model to use
$CONNECTOR["openai"]["max_tokens"]='512';
$CONNECTOR["openai"]["temperature"]=1;
$CONNECTOR["openai"]["presence_penalty"]=1;
$CONNECTOR["openai"]["API_KEY"]="";
$CONNECTOR["openai"]["MAX_TOKENS_MEMORY"]="512";
$CONNECTOR["openai"]["frequency_penalty"]=0;    		//LLM parameter frequency_penalty
$CONNECTOR["openai"]["top_p"]=1;        			//LLM parameter top_p

$CONNECTOR["openaijson"]["url"]="https://api.openai.com/v1/chat/completions";
$CONNECTOR["openaijson"]["model"]='gpt-4o-mini';	//Model to use
$CONNECTOR["openaijson"]["max_tokens"]='512';
$CONNECTOR["openaijson"]["temperature"]=1;
$CONNECTOR["openaijson"]["presence_penalty"]=1;
$CONNECTOR["openaijson"]["API_KEY"]="";
$CONNECTOR["openaijson"]["MAX_TOKENS_MEMORY"]="800";
$CONNECTOR["openaijson"]["frequency_penalty"]=0;    		//LLM parameter frequency_penalty
$CONNECTOR["openaijson"]["top_p"]=1;        			//LLM parameter top_p

$CONNECTOR["koboldcpp"]["url"]='http://127.0.0.1:5001';	//KoboldCPP API Endpoint
$CONNECTOR["koboldcpp"]["max_tokens"]='512';	//Maximum tokens to generate
$CONNECTOR["koboldcpp"]["temperature"]=1;	//LLM parameter temperature
$CONNECTOR["koboldcpp"]["rep_pen"]=1;	//LLM parameter rep_pen
$CONNECTOR["koboldcpp"]["top_p"]=1;	//LLM parameter top_p
$CONNECTOR["koboldcpp"]["min_p"]=0.01;	//LLM parameter min_p
$CONNECTOR["koboldcpp"]["top_k"]=0;	//LLM parameter top_k
$CONNECTOR["koboldcpp"]["MAX_TOKENS_MEMORY"]='512';	//Maximum tokens to generate when summarizing, such as writing to diary.
$CONNECTOR["koboldcpp"]["newline_as_stopseq"]=false;	//A newline in the output that will be considered a stop sequence. Recommended to leave it as default.
$CONNECTOR["koboldcpp"]["use_default_badwordsids"]=false;	//Unban End of Sentence (EOS) tokens. If set to false the LLM will stop generating when it detects an EOS token.
$CONNECTOR["koboldcpp"]["eos_token"]='<|im_end|>';	//EOS token LLM uses. Only works if use_default_badwordsids is enabled.
$CONNECTOR["koboldcpp"]["template"]='chatml';	//Prompt Format. Specified in the HuggingFace model card


$CONNECTOR["koboldcppjson"]["url"]='http://127.0.0.1:5001';	//KoboldCPP API Endpoint
$CONNECTOR["koboldcppjson"]["max_tokens"]='512';	//Maximum tokens to generate
$CONNECTOR["koboldcppjson"]["temperature"]=0.9;	//LLM parameter temperature
$CONNECTOR["koboldcppjson"]["rep_pen"]=1.12;	//LLM parameter rep_pen
$CONNECTOR["koboldcppjson"]["top_p"]=0.9;	//LLM parameter top_p
$CONNECTOR["koboldcppjson"]["min_p"]=0;	//LLM parameter min_p
$CONNECTOR["koboldcppjson"]["top_k"]=0;	//LLM parameter top_k
$CONNECTOR["koboldcppjson"]["PREFILL_JSON"]=false;	//Will prefill JSON, which is usefull for some models, and destroy others (WIP)
$CONNECTOR["koboldcppjson"]["MAX_TOKENS_MEMORY"]='256';	//Maximum tokens to generate when summarizing, such as writing to diary.
$CONNECTOR["koboldcppjson"]["newline_as_stopseq"]=false;	//A newline in the output that will be considered a stop sequence. Recommended to leave it as default.
$CONNECTOR["koboldcppjson"]["use_default_badwordsids"]=true;	//Unban End of Sentence (EOS) tokens. If set to false the LLM will stop generating when it detects an EOS token.
$CONNECTOR["koboldcppjson"]["eos_token"]='<|eot_id|>';	//EOS token LLM uses. Only works if use_default_badwordsids is enabled.
$CONNECTOR["koboldcppjson"]["template"]='chatml';	//Prompt Format. Specified in the HuggingFace model card
$CONNECTOR["koboldcppjson"]["grammar"]=false;	//Enforces use of JSON grammar. True to enforce (generation speed loss, but json format guaranteed). if false, the generation speed will be better but will depend on the model to produce valid JSON output.



$CONNECTOR["openrouter"]["url"]="https://openrouter.ai/api/v1/chat/completions";
$CONNECTOR["openrouter"]["model"]="meta-llama/llama-3.1-8b-instruct";
$CONNECTOR["openrouter"]["max_tokens"]=512;
$CONNECTOR["openrouter"]["xreferer"]="https://www.nexusmods.com/skyrimspecialedition/mods/89931";
$CONNECTOR["openrouter"]["xtitle"]="Skyrim AI Follower Framework";
$CONNECTOR["openrouter"]["API_KEY"]="";
$CONNECTOR["openrouter"]["MAX_TOKENS_MEMORY"]="512";
$CONNECTOR["openrouter"]["temperature"]=0.9;	//LLM parameter temperature
$CONNECTOR["openrouter"]["top_k"]=0;	//LLM parameter top_k
$CONNECTOR["openrouter"]["top_p"]=1;	//LLM parameter top_p
$CONNECTOR["openrouter"]["presence_penalty"]=0;	//LLM parameter presence_penalty
$CONNECTOR["openrouter"]["frequency_penalty"]=0;	//LLM parameter frequency_penalty
$CONNECTOR["openrouter"]["repetition_penalty"]=0.9;	//LLM parameter repetition_penalty
$CONNECTOR["openrouter"]["min_p"]=0.1;	//LLM parameter min_p
$CONNECTOR["openrouter"]["top_a"]=0;	//LLM parameter top_a


$CONNECTOR["openrouterjson"]["url"]="https://openrouter.ai/api/v1/chat/completions";
$CONNECTOR["openrouterjson"]["model"]="meta-llama/llama-3.1-70b-instruct";
$CONNECTOR["openrouterjson"]["max_tokens"]='512';	//Maximum tokens to generate.
$CONNECTOR["openrouterjson"]["temperature"]=0.8;	//LLM parameter temperature
$CONNECTOR["openrouterjson"]["presence_penalty"]=0;	//LLM parameter presence_penalty
$CONNECTOR["openrouterjson"]["frequency_penalty"]=0;	//LLM parameter frequency_penalty
$CONNECTOR["openrouterjson"]["repetition_penalty"]=1.1;	//LLM parameter repetition_penalty
$CONNECTOR["openrouterjson"]["top_p"]=1;	//LLM parameter top_p
$CONNECTOR["openrouterjson"]["top_k"]=40;	//LLM parameter top_k
$CONNECTOR["openrouterjson"]["min_p"]=0;	//LLM parameter min_p
$CONNECTOR["openrouterjson"]["top_a"]=0;	//LLM parameter top_a
$CONNECTOR["openrouterjson"]["ENFORCE_JSON"]=true;	//Will try to force JSON. Some models acept this. Other's dont.
$CONNECTOR["openrouterjson"]["PREFILL_JSON"]=false;	//Will prefill JSON, which is usefull for some models, and destroy others
$CONNECTOR["openrouterjson"]["MAX_TOKENS_MEMORY"]='512';	//Maximum tokens to generate when summarizing, such as writing to diary.
$CONNECTOR["openrouterjson"]["xreferer"]="https://www.nexusmods.com/skyrimspecialedition/mods/89931";
$CONNECTOR["openrouterjson"]["xtitle"]="Skyrim AI Follower Framework";
$CONNECTOR["openrouterjson"]["API_KEY"]="";


$CONNECTOR["oobabooga"]["HOST"]="127.0.0.1";
$CONNECTOR["oobabooga"]["PORT"]="5005";
$CONNECTOR["oobabooga"]["MAX_TOKENS_MEMORY"]="512";
$CONNECTOR["oobabooga"]["max_tokens"]=100;
$CONNECTOR["oobabooga"]["temperature"]=0.7;
$CONNECTOR["oobabooga"]["rep_pen"]=1.18;

$CONNECTOR["llamacpp"]["url"]='http://127.0.0.1:8007';	//Llama.cpp server API
$CONNECTOR["llamacpp"]["max_tokens"]="75";	//Maximum tokens to generate (n_predict)
$CONNECTOR["llamacpp"]["temperature"]=0.7;	//LLM parameter temperature
$CONNECTOR["llamacpp"]["rep_pen"]=1.12;	//LLM parameter rep_pen
$CONNECTOR["llamacpp"]["top_p"]=0.9;	//LLM parameter top_p
$CONNECTOR["llamacpp"]["MAX_TOKENS_MEMORY"]='512';	//Maximum tokens to generate when summarizing, such as writing to diary.
$CONNECTOR["llamacpp"]["eos_token"]='</s>';	//EOS token LLM uses.
$CONNECTOR["llamacpp"]["template"]='alpaca';	//Prompt Format. Specified in the HuggingFace model card


$FEATURES["MISC"]["ADD_TIME_MARKS"]=false;
$FEATURES["EXPERIMENTAL"]["KOBOLDCPP_ACTIONS"]=false;	

$TTS["XVASYNTH"]["url"]='http://172.16.1.128:8008';	//xVASynth must be run in same machine as DwemerDistro, so this must be http://your-local-ip:8008
$TTS["XVASYNTH"]["base_lang"]='en';	//Base language
$TTS["XVASYNTH"]["modelType"]='xVAPitch';	//modelType
$TTS["XVASYNTH"]["model"]='sk_femaleyoungeager';	//Model
$TTS["XVASYNTH"]["pace"]=1.0;	//Pace
$TTS["XVASYNTH"]["waveglowPath"]='resources/app/models/waveglow_256channels_universal_v4.pt';	//waveglowPath (relative)
$TTS["XVASYNTH"]["vocoder"]='n/a';	//vocoder
$TTS["XVASYNTH"]["distroname"]='DwemerAI4Skyrim2';	

$TTS["CONVAI"]["endpoint"]='https://api.convai.com/tts';	//End point
$TTS["CONVAI"]["API_KEY"]='';	//API KEY
$TTS["CONVAI"]["language"]='en-US';	//Language
$TTS["CONVAI"]["voiceid"]='WUFemale3';	//VoiceId

$TTS["XTTS"]["endpoint"]='';	//End point
$TTS["XTTS"]["language"]='en';	//
$TTS["XTTS"]["voiceid"]='11labs_diane';	//Voice json file

$TTS["XTTSFASTAPI"]["endpoint"]='http://127.0.0.1:8020';	//End point
$TTS["XTTSFASTAPI"]["language"]='en';	//
$TTS["XTTSFASTAPI"]["voiceid"]='TheNarrator';	//Voice json file

$TTS["STYLETTSV2"]["endpoint"]='http://127.0.0.1:5050/';	//End point
$TTS["STYLETTSV2"]["voice"]='';	//WAV file with source voice to clone. Should be localte at /var/www/html/HerikaServer/data/voices/
$TTS["STYLETTSV2"]["alpha"]=0.3;	//From 0.0 to 1.0 - The higher the value of `alpha`, the more suitable the style it is to the text but less similar to the reference. `alpha` determines the timbre of the speaker
$TTS["STYLETTSV2"]["beta"]=0.7;	//From 0.0 to 1.0 - The higher the value of `beta` the more suitable the style it is to the text but less similar to the reference. Using higher beta makes the synthesized speech more emotional, at the cost of lower similarity to the reference. `beta` determines the prosody of the speaker.
$TTS["STYLETTSV2"]["diffusion_steps"]=15;	//From 5 - Since the sampler is ancestral, the higher the steps, the more diverse the samples are, with the cost of slower synthesis speed
$TTS["STYLETTSV2"]["embedding_scale"]=1.5;	//From 0.0 to 1.0 - This is the classifier-free guidance scale. The higher the scale, the more conditional the style is to the input text and hence more emotional.


$TTS["MELOTTS"]["endpoint"]='http://127.0.0.1:8084';
$TTS["MELOTTS"]["voiceid"]='malenord';
$TTS["MELOTTS"]["language"]='EN';
$TTS["MELOTTS"]["speed"]=1.0;

$SUMMARY_PROMPT='';

$TTSFUNCTION_PLAYER="none";
$TTSFUNCTION_PLAYER_VOICE="malenord";


?>
