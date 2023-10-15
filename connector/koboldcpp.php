<?php

/* kolboldcpp connector */

class connector
{
    public $primary_handler;
    public $name;

    private $_functionMode;
    private $_ignoreRest;
    private $_functionRawName;
    private $_functionName;
    private $_parameterBuff;
    private $_commandBuffer;

    public function __construct()
    {
        $this->name="koboldcpp";
         $this->_ignoreRest=false;
    }


    public function open($contextData, $customParms)
    {
        $path='/api/extra/generate/stream/';
        $url=$GLOBALS["CONNECTOR"][$this->name]["url"].$path;
        $context="";


        foreach ($contextData as $s_role=>$s_msg) {	// Have to mangle context format

            if (empty(trim($s_msg["content"]))) {
                continue;
            } else {
                // This should be customizable per model
                /*
                if ($s_msg["role"]=="user")
                    $normalizedContext[]="### Instruction: ".$s_msg["content"];
                else if ($s_msg["role"]=="assistant")
                    $normalizedContext[]="### Response: ".$s_msg["content"];
                else if ($s_msg["role"]=="system")
                    $normalizedContext[]=$s_msg["content"];
                */
                $normalizedContext[]=$s_msg["content"];
            }
        }

        if ($GLOBALS["CONNECTOR"][$this->name]["template"]=="alpaca") {
            foreach ($normalizedContext as $n=>$s_msg) {
                if ($n==(sizeof($normalizedContext)-1)) {   // Last prompt line
                    $context.="### Instruction: ".$s_msg.". Write a single reply only.\n";
                    $GLOBALS["DEBUG_DATA"][]="### Instruction: ".$s_msg."";

                } else {
                    $s_msg_p = preg_replace('/^(The Narrator:)(.*)/m', '[Author\'s notes: $2 ]', $s_msg);
                    $context.="$s_msg_p\n";
                    $GLOBALS["DEBUG_DATA"][]=$s_msg_p;
                }

            }

            $context.="### Response:";
            $GLOBALS["DEBUG_DATA"][]="### Response:";
            $GLOBALS["DEBUG_DATA"]["prompt"]=$context;


        } elseif ($GLOBALS["CONNECTOR"][$this->name]["template"]=="vicuna-1") {


            $context="{$GLOBALS["PROMPT_HEAD"]}\n";
            $context.="{$GLOBALS["HERIKA_PERS"]}\n";
	    $context.="{$GLOBALS["COMMAND_PROMPT"]}\n";
            $context.="Dialogue history:\n";

            $contextHistory="";
            $n=0;
            foreach ($contextData as $s_role=>$s_msg) {	// Have to mangle context format

                if ($n==(sizeof($contextData)-1)) {   // Last prompt line

                    $instruction="USER: ".$s_msg["content"]."\n";

                } else {
                    if ($s_msg["role"]=="user") {
                        $contextHistory.="".$s_msg["content"]."\n";
                        $GLOBALS["DEBUG_DATA"][]=$s_msg["content"]."\n";

                    } elseif ($s_msg["role"]=="assistant") {
                        $contextHistory.="".$s_msg["content"]."\n";
                        $GLOBALS["DEBUG_DATA"][]=$s_msg["content"]."\n";
                    } elseif ($s_msg["role"]=="system") {
                    }  // Must rebuild this
                }

                $n++;
            }

            $context.="$contextHistory $instruction ASSISTANT: ";
            $GLOBALS["DEBUG_DATA"][]="ASSISTANT:";
            $GLOBALS["DEBUG_DATA"]["prompt"]=$context;

        }  elseif ($GLOBALS["CONNECTOR"][$this->name]["template"]=="vicuna-1.1") {

            $GLOBALS["more_stopseq"][]="USER:";
            $context="{$GLOBALS["PROMPT_HEAD"]}\n";
            $context.="{$GLOBALS["HERIKA_PERS"]}\n";
	    $context.="{$GLOBALS["COMMAND_PROMPT"]}\n";
            $context.="{$GLOBALS["HERIKA_NAME"]} IS THE ASSISTANT, {$GLOBALS["PLAYER_NAME"]} IS THE USER\n";

            
            $GLOBALS["DEBUG_DATA"][]=$context;
            
            $contextHistory="";
            $n=0;
            foreach ($contextData as $s_role=>$s_msg) {	// Have to mangle context format

                if ($n==(sizeof($contextData)-1)) {   // Last prompt line

                    $instruction="USER: ".$s_msg["content"]."\n";
                    $GLOBALS["DEBUG_DATA"][]=$instruction;
                } else {
                    if ($s_msg["role"]=="user") {
                        $contextHistory.="USER:".$s_msg["content"]."\n";
                          $GLOBALS["DEBUG_DATA"][]="USER:".$s_msg["content"]."\n";
                    } elseif ($s_msg["role"]=="assistant") {
                         $GLOBALS["DEBUG_DATA"][]="ASSISTANT:".$s_msg["content"]."\n";
                        $contextHistory.="ASSISTANT:".$s_msg["content"]."\n";
                    } elseif ($s_msg["role"]=="system") {
                    }  // Must rebuild this
                    
                     
                }

                $n++;
            }

            $context.="$contextHistory  $instruction ASSISTANT:{$GLOBALS["HERIKA_NAME"]}:";
            $GLOBALS["DEBUG_DATA"][]="ASSISTANT:";
            $GLOBALS["DEBUG_DATA"]["prompt"]=$context;

        } elseif ($GLOBALS["CONNECTOR"][$this->name]["template"]=="chatml") {

            $GLOBALS["more_stopseq"][]="<|im_start|>user";
            $context="<|im_start|>system\n{$GLOBALS["PROMPT_HEAD"]}\n";
            $context.="{$GLOBALS["HERIKA_PERS"]}\n";
	    $context.="{$GLOBALS["COMMAND_PROMPT"]}<|im_end|>\n";
           
            $GLOBALS["DEBUG_DATA"][]=$context;
            
            $contextHistory="";
            $n=0;
            foreach ($contextData as $s_role=>$s_msg) {	// Have to mangle context format

                if ($n==(sizeof($contextData)-1)) {   // Last prompt line

                    $instruction="<|im_start|>user\n".$s_msg["content"]."<|im_end|>\n";

                } else {
                    if ($s_msg["role"]=="user") {
                        $contextHistory.="<|im_start|>user\n".$s_msg["content"]."<|im_end|>\n";
                          $GLOBALS["DEBUG_DATA"][]="<|im_start|>user\n".$s_msg["content"]."<|im_end|>\n";
                    } elseif ($s_msg["role"]=="assistant") {
                         $GLOBALS["DEBUG_DATA"][]="<|im_start|>assistant\n".$s_msg["content"]."<|im_end|>\n";
                        $contextHistory.="<|im_start|>assistant\n".$s_msg["content"]."<|im_end|>\n";
                    } elseif ($s_msg["role"]=="system") {
                    }  // Must rebuild this
                    
                     
                }

                $n++;
            }

            $context.="$contextHistory  $instruction";
            $GLOBALS["DEBUG_DATA"][]="$instruction";
            $GLOBALS["DEBUG_DATA"]["prompt"]=$context;

        } elseif ($GLOBALS["CONNECTOR"][$this->name]["template"]=="synthia") {
            
            
            $context="SYSTEM: {$GLOBALS["PROMPT_HEAD"]}.";
            $context.="{$GLOBALS["HERIKA_PERS"]}\n";
            $context.="{$GLOBALS["COMMAND_PROMPT"]}\n";  
            $GLOBALS["DEBUG_DATA"][]=$context;
            
            $contextHistory="\nCONTEXT:\n";
            $n=0;
            foreach ($contextData as $s_role=>$s_msg) {	// Have to mangle context format

                if ($n==(sizeof($contextData)-1)) {   // Last prompt line

                    $instruction="USER: ".$s_msg["content"]."\n";

                } else {
                    if ($s_msg["role"]=="user") {
                        $contextHistory.="".$s_msg["content"]."\n";
                        $GLOBALS["DEBUG_DATA"][]="".$s_msg["content"]."\n";
                    } elseif ($s_msg["role"]=="assistant") {
                         
                        $contextHistory.="".$s_msg["content"]."\n";
                        $GLOBALS["DEBUG_DATA"][]="".$s_msg["content"]."\n";
                        
                    } elseif ($s_msg["role"]=="system") {
                    }  // Must rebuild this
                    
                     
                }

                $n++;
            }

            $context.="{$contextHistory}{$instruction}ASSISTANT:";
            $GLOBALS["DEBUG_DATA"][]="$instruction";
            $GLOBALS["DEBUG_DATA"][]="ASSISTANT:";
            
            $GLOBALS["DEBUG_DATA"]["prompt"]=$context;
            

        } elseif ($GLOBALS["CONNECTOR"][$this->name]["template"]=="extended-alpaca") {

            $context="{$GLOBALS["HERIKA_NAME"]}'s Persona: {$GLOBALS["HERIKA_PERS"]}\n";
            $context.="{$GLOBALS["PLAYER_NAME"]}'s Persona: {$GLOBALS["PLAYER_NAME"]}\n";
            $context.="Scenario: {$GLOBALS["PROMPT_HEAD"]} \n";
            $context.="Play the role of {$GLOBALS["HERIKA_NAME"]}. You must engage in a roleplaying chat with {$GLOBALS["PLAYER_NAME"]} below this line.Do not write dialogues for {$GLOBALS["PLAYER_NAME"]} and don't write narration.\n";
            $context.="{$GLOBALS["COMMAND_PROMPT"]}\n";

            $contextHistory="";
            $n=0;
            foreach ($contextData as $s_role=>$s_msg) {	// Have to mangle context format

                if ($n==(sizeof($contextData)-1)) {   // Last prompt line
                    $instruction="### Input:\n".$s_msg["content"]."\n";
                    $GLOBALS["DEBUG_DATA"][]=$instruction;

                } else {
                    if ($s_msg["role"]=="user") {
                        $contextHistory.="### Input:\n".$s_msg["content"];
                        $GLOBALS["DEBUG_DATA"][]="### Input:\n".$s_msg["content"];
                    } elseif ($s_msg["role"]=="assistant") {
                        $contextHistory.="### Response:\n".$s_msg["content"];
                        $GLOBALS["DEBUG_DATA"][]="### Response:\n".$s_msg["content"];

                    } elseif ($s_msg["role"]=="system") {
                    }  // Must rebuild this
                }

                $n++;
            }

            $context.="$contextHistory $instruction ### Response\n{$GLOBALS["HERIKA_NAME"]}:";
            $GLOBALS["DEBUG_DATA"][]="ASSISTANT:";
            $GLOBALS["DEBUG_DATA"]["prompt"]=$context;

        } elseif ($GLOBALS["CONNECTOR"][$this->name]["template"]=="superHOT") {

            $context="---\nstyle: roleplay\n";
            $context.="characters:\n   {$GLOBALS["HERIKA_NAME"]}:{$GLOBALS["HERIKA_PERS"]}\n   {$GLOBALS["PLAYER_NAME"]}:Human\n";
            $context.="summary: {$GLOBALS["PROMPT_HEAD"]} \n---\n";
	    $context.="{$GLOBALS["COMMAND_PROMPT"]}\n";
            $contextHistory="";
            $n=0;
            foreach ($contextData as $s_role=>$s_msg) {	// Have to mangle context format

                if ($n==(sizeof($contextData)-1)) {   // Last prompt line

                    $instruction=$s_msg["content"];


                } else {
                    if ($s_msg["role"]=="user") {
                        $contextHistory.=$s_msg["content"]."\n";
                    } elseif ($s_msg["role"]=="assistant") {
                        $contextHistory.=$s_msg["content"]."\n";
                    } elseif ($s_msg["role"]=="system") {
                    }  // Must rebuild this
                }

                $GLOBALS["DEBUG_DATA"][]=$s_msg["content"];

                $n++;
            }

            $context.="$contextHistory Human: $instruction\n{$GLOBALS["HERIKA_NAME"]}:";

            $GLOBALS["DEBUG_DATA"]["prompt"]=$context;

        }

        $TEMPERATURE=((isset($GLOBALS["CONNECTOR"][$this->name]["temperature"]) ? $GLOBALS["CONNECTOR"][$this->name]["temperature"] : 0.9)+0);
        $REP_PEN=((isset($GLOBALS["CONNECTOR"][$this->name]["rep_pen"]) ? $GLOBALS["CONNECTOR"][$this->name]["rep_pen"] : 1.12)+0);
        $TOP_P=((isset($GLOBALS["CONNECTOR"][$this->name]["top_p"]) ? $GLOBALS["CONNECTOR"][$this->name]["top_p"] : 0.9)+0);

        $MAX_TOKENS=((isset($GLOBALS["CONNECTOR"][$this->name]["max_tokens"]) ? $GLOBALS["CONNECTOR"][$this->name]["max_tokens"] : 48)+0);
        $stop_sequence=["{$GLOBALS["PLAYER_NAME"]}:","\n{$GLOBALS["PLAYER_NAME"]} ","Author's notes","###","```"];

        if ($GLOBALS["CONNECTOR"][$this->name]["newline_as_stopseq"]) {
            $stop_sequence[]="\n";
        }
        
        if (isset($GLOBALS["more_stopseq"])) {
            foreach ($GLOBALS["more_stopseq"] as $stopseq)
                $stop_sequence[]=$stopseq;
        }
        
        $postData = array(

            "prompt"=>$context,
            "temperature"=> $TEMPERATURE,
            "top_p"=>$TOP_P,
            //"max_context_length"=>2048,
            "max_length"=>$MAX_TOKENS,
            "rep_pen"=>$REP_PEN,
            "stop_sequence"=>$stop_sequence,
            "use_default_badwordsids"=>$GLOBALS["CONNECTOR"][$this->name]["use_default_badwordsids"]

        );

        if ($GLOBALS["gameRequest"][0]!="diary") {
            $postData["grammar"]='
root ::= fullanswer
fullanswer ::= "'.$GLOBALS["HERIKA_NAME"].':" answer "\n"
answer ::= sentence "." answer | sentence
sentence ::= words
words ::= word words | word
word ::= ANYTEXT
ANYTEXT ::= [a-zA-Z0-9.,?!\' ]
';

        } else {

            $postData["grammar"]='
root ::= fullanswer
fullanswer ::= "Dear Diary, " answer
answer ::= sentence "." answer | sentence
sentence ::= words "\n"
words ::= word words | word
word ::= ANYTEXT
ANYTEXT ::= [a-zA-Z0-9.,?!\' ]
';            
            
        }


        $GLOBALS["DEBUG_DATA"]["koboldcpp_prompt"]=$postData;


        if (isset($customParms["MAX_TOKENS"])) {
            if ($customParms["MAX_TOKENS"]==null) {
                unset($postData["max_length"]);
            } elseif ($customParms["MAX_TOKENS"]) {
                $postData["max_length"]=$customParms["MAX_TOKENS"]+0;
            }
        }

        if (isset($GLOBALS["FORCE_MAX_TOKENS"])) {
            if ($GLOBALS["FORCE_MAX_TOKENS"]==null) {
                unset($postData["max_length"]);
            } else {
                $postData["max_length"]=$GLOBALS["FORCE_MAX_TOKENS"]+0;
            }

        }


        $headers = array(
            'Content-Type: application/json'
        );

        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => json_encode($postData)
            )
        );
        error_reporting(E_ALL);
        $context = stream_context_create($options);


        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);


        // Data to send in JSON format
        $dataJson = json_encode($postData);

        $request = "POST $path HTTP/1.1\r\n";
        $request .= "Host: $host\r\n";
        $request .= "Content-Type: application/json\r\n";
        $request .= "Content-Length: " . strlen($dataJson) . "\r\n";
        $request .= "Connection: close\r\n\r\n";
        $request .= $dataJson;

        // Open a TCP connection
        $this->primary_handler = fsockopen('tcp://' . $host, $port, $errno, $errstr, 30);

        // Send the HTTP request
        if ($this->primary_handler !== false) {
            fwrite($this->primary_handler, $request);
        } else {
            return false;
        }

        // Initialize variables for response
        $responseHeaders = '';
        $responseBody = '';

        return true;

    }


    public function process()
    {
        $line = fgets($this->primary_handler);
        $buffer="";
        $totalBuffer="";
        file_put_contents(__DIR__."/../log/debugStream.log", $line, FILE_APPEND);

        if ($this->_ignoreRest)
            return "";

        if (strpos($line, 'data: {') !== 0) {
            return "";
        }
        //$_ignoreRest

        if (strpos($line, 'data: {"token": "{"}') !== false) {
            $this->_ignoreRest=true;
            return "";
            
        }
        
        /*
         if (strpos($line, 'data: {"token": "#"}') === 0) {

            $this->_functionMode=true;
            return "";
        }
        */

        $data=json_decode(substr($line, 6), true);

        if ((isset($this->_functionMode))&&($this->_functionMode)) {

            $this->_functionRawName.=$data["token"];
            return "";


        }

        if (isset($data["token"])) {
            if (strlen(trim($data["token"],"\t\0\x0B"))>0) {
                $buffer.=$data["token"];
            }
            $totalBuffer.=$data["token"];
        }

        return $buffer;
    }


    public function close()
    {
        fclose($this->primary_handler);
    }

    public function isDone()
    {
        if ($this->_ignoreRest)
            return true;
        
        return feof($this->primary_handler);
    }

    public function processActions()
    {
        global $alreadysent;
        if ((isset($this->_functionMode))&&($this->_functionMode)) {
            $alreadysent[md5("Herika|command|{$this->_functionRawName}r\r\n")] = "Herika|command|{$this->_functionRawName}\r\n";
            return $alreadysent;
        } else {
            return [];
        }
    }


}
