<?php

$DBDRIVER="sqlite3";

$PLAYER_NAME="Prisoner";
$HERIKA_NAME="Herika";

$PROMPT_HEAD="Let's roleplay in the Universe of Skyrim. I'm Prisoner. You're Prisoner's companion.";
$HERIKA_PERS="You are Herika, a Breton female who likes jokes and sarcastic comments.";


$CORE_LANG="";

$TTS["AZURE"]["fixedMood"]="";
$TTS["AZURE"]["region"]="westeurope";			
$TTS["AZURE"]["voice"]="en-US-NancyNeural";	
$TTS["AZURE"]["volume"]="20";					
$TTS["AZURE"]["rate"]="1.25";					
$TTS["AZURE"]["countour"]="(11%, +15%) (60%, -23%) (80%, -34%)";							
$TTS["AZURE"]["validMoods"]=array("whispering","default","dazed");			
$TTS["AZURE"]["API_KEY"]="";


$TTS["MIMIC3"]["URL"]="http://127.0.0.1:59125";   
$TTS["MIMIC3"]["voice"]="en_US/hifi-tts_low#92";
$TTS["MIMIC3"]["rate"]="1.3";
$TTS["MIMIC3"]["volume"]="60";

$TTS["ELEVEN_LABS"]["voice_id"]="EXAVITQu4vr4xnSDxMaL";	//https://api.elevenlabs.io/v1/voices
$TTS["ELEVEN_LABS"]["optimize_streaming_latency"]="0";
$TTS["ELEVEN_LABS"]["model_id"]="eleven_monolingual_v1";
$TTS["ELEVEN_LABS"]["stability"]="0.75";
$TTS["ELEVEN_LABS"]["similarity_boost"]="0.75";
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


$STT["LOCALWHISPER"]["URL"]="http://127.0.0.1:3000/transcribe";

$STT["AZURE"]["LANG"]="en-US";
$STT["AZURE"]["API_KEY"]="";
$STT["AZURE"]["profanity"]="masked";

$STT["WHISPER"]["LANG"]="en";
$STT["WHISPER"]["API_KEY"]="";
$STT["WHISPER"]["TRANSLATE"]=false;


$ITT["AZURE"]["ENDPOINT"]="";			
$ITT["AZURE"]["API_KEY"]="";


$ITT["LLAMACPP"]["URL"]="http://127.0.0.1:8007";			



$STTFUNCTION="whisper";								// Valid options are azure or whisper so far
$TTSFUNCTION="none";								// Valid options are azure or mimic3, or 11labs so far
$ITTFUNCTION="none";								// Valid options are azure or mimic3, or 11labs so far


$CONTEXT_HISTORY="15";
$HTTP_TIMEOUT=15;                       // How long we will wait for openai response

//$CORE_LANG="es";

$FEATURES["MEMORY_EMBEDDING"]["ENABLED"]=false;
$FEATURES["MEMORY_EMBEDDING"]["CHROMADB_URL"]='http://127.0.0.1:8000';
$FEATURES["MEMORY_EMBEDDING"]["MEMORY_TIME_DELAY"]=10;
$FEATURES["MEMORY_EMBEDDING"]["MEMORY_CONTEXT_SIZE"]=1;
$FEATURES["MEMORY_EMBEDDING"]["TEXT2VEC_URL"]="http://127.0.0.1:7860";
$FEATURES["MEMORY_EMBEDDING"]["TEXT2VEC_PROVIDER"]="local";
$FEATURES["MEMORY_EMBEDDING"]["AUTO_CREATE_SUMMARYS"]=false;
$FEATURES["MEMORY_EMBEDDING"]["AUTO_CREATE_SUMMARY_INTERVAL"]=10;
$FEATURES["MEMORY_EMBEDDING"]["MEMORY_BIAS_A"]=33;
$FEATURES["MEMORY_EMBEDDING"]["MEMORY_BIAS_B"]=66;

$CONNECTORS=["koboldcpp","openai"];  

$CONNECTOR["openai"]["url"]="https://api.openai.com/v1/chat/completions";
$CONNECTOR["openai"]["model"]="gpt-3.5-turbo-0613";
$CONNECTOR["openai"]["max_tokens"]=100;
$CONNECTOR["openai"]["temperature"]=1;
$CONNECTOR["openai"]["presence_penalty"]=1;
$CONNECTOR["openai"]["API_KEY"]="";
$CONNECTOR["openai"]["MAX_TOKENS_MEMORY"]="512";

$CONNECTOR["koboldcpp"]["url"]="http://127.0.0.1:5001";
$CONNECTOR["koboldcpp"]["max_tokens"]=100;
$CONNECTOR["koboldcpp"]["temperature"]=0.9;
$CONNECTOR["koboldcpp"]["rep_pen"]=1.12;
$CONNECTOR["koboldcpp"]["top_p"]=0.9;
$CONNECTOR["koboldcpp"]["MAX_TOKENS_MEMORY"]=256;
$CONNECTOR["koboldcpp"]["newline_as_stopseq"]=true;
$CONNECTOR["koboldcpp"]["use_default_badwordsids"]=true;
$CONNECTOR["koboldcpp"]["template"]="alpaca";
$CONNECTOR["koboldcpp"]["eos_token"]='</s>';

$CONNECTOR["openrouter"]["url"]="https://openrouter.ai/api/v1/chat/completions";
$CONNECTOR["openrouter"]["model"]="meta-llama/llama-2-70b-chat";
$CONNECTOR["openrouter"]["max_tokens"]=100;
$CONNECTOR["openrouter"]["xreferer"]="http://localhost:8081/saig-gwserver/";
$CONNECTOR["openrouter"]["xtitle"]="Herika";
$CONNECTOR["openrouter"]["API_KEY"]="";
$CONNECTOR["openrouter"]["MAX_TOKENS_MEMORY"]="512";

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
$CONNECTOR["llamacpp"]["MAX_TOKENS_MEMORY"]='256';	//Maximum tokens to generate when summarizing, such as writing to diary.
$CONNECTOR["llamacpp"]["eos_token"]='</s>';	//EOS token LLM uses.
$CONNECTOR["llamacpp"]["template"]='alpaca';	//Prompt Format. Specified in the HuggingFace model card


$FEATURES["COST_MONITOR"]["ENABLED"]=true;	//Enable cost/token counter monitoring. Currently only supports OpenAI.
$FEATURES["COST_MONITOR"]["URL"]="http://127.0.0.1:8090";	//We can use 127.0.0.1 because server is on same machine by default.

$FEATURES["MISC"]["ADD_TIME_MARKS"]=true;
$FEATURES["EXPERIMENTAL"]["KOBOLDCPP_ACTIONS"]=false;	

$TTS["XVASYNTH"]["url"]='http://172.16.1.128:8008';	//xVASynth must be run in same machine as DwemerDistro, so this must be http://your-local-ip:8008
$TTS["XVASYNTH"]["base_lang"]='en';	//Base language
$TTS["XVASYNTH"]["modelType"]='xVAPitch';	//modelType
$TTS["XVASYNTH"]["model"]='sk_femaleyoungeager';	//Model
$TTS["XVASYNTH"]["pace"]=1.0;	//Pace
$TTS["XVASYNTH"]["waveglowPath"]='resources/app/models/waveglow_256channels_universal_v4.pt';	//waveglowPath (relative)
$TTS["XVASYNTH"]["vocoder"]='n/a';	//vocoder


?>
