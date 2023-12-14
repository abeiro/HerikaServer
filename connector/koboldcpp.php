<?php

/* kolboldcpp connector */

class connector
{
    public $primary_handler;
    public $name;

    public $_functionMode;
    private $_ignoreRest;
    private $_functionRawName;
    private $_functionName;
    private $_parameterBuff;
    private $_commandBuffer;
    private $_jsonMode;
    private $_jsonBuffer;

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


        foreach ($contextData as $n=>$s_msg) {	// Have to mangle context format

            if (!isset($s_msg["content"]))
                return "";
            
            if (empty(trim($s_msg["content"]))) {
                unset($contextData[$n]);
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

        $stop_sequence=["{$GLOBALS["PLAYER_NAME"]}:","\n{$GLOBALS["PLAYER_NAME"]} ","Author's notes","###","```"];


        if ($GLOBALS["CONNECTOR"][$this->name]["template"]=="alpaca") {
           
            include(__DIR__.DIRECTORY_SEPARATOR."templates".DIRECTORY_SEPARATOR."{$GLOBALS["CONNECTOR"][$this->name]["template"]}.php");


        } elseif ($GLOBALS["CONNECTOR"][$this->name]["template"]=="vicuna-1") {

            include(__DIR__.DIRECTORY_SEPARATOR."templates".DIRECTORY_SEPARATOR."{$GLOBALS["CONNECTOR"][$this->name]["template"]}.php");
           

        }  elseif ($GLOBALS["CONNECTOR"][$this->name]["template"]=="vicuna-1.1") {

            include(__DIR__.DIRECTORY_SEPARATOR."templates".DIRECTORY_SEPARATOR."{$GLOBALS["CONNECTOR"][$this->name]["template"]}.php");


        } elseif ($GLOBALS["CONNECTOR"][$this->name]["template"]=="chatml") {

            include(__DIR__.DIRECTORY_SEPARATOR."templates".DIRECTORY_SEPARATOR."{$GLOBALS["CONNECTOR"][$this->name]["template"]}.php");


        } elseif ($GLOBALS["CONNECTOR"][$this->name]["template"]=="chatml-c") {

            include(__DIR__.DIRECTORY_SEPARATOR."templates".DIRECTORY_SEPARATOR."{$GLOBALS["CONNECTOR"][$this->name]["template"]}.php");


        } elseif ($GLOBALS["CONNECTOR"][$this->name]["template"]=="synthia") {


            include(__DIR__.DIRECTORY_SEPARATOR."templates".DIRECTORY_SEPARATOR."{$GLOBALS["CONNECTOR"][$this->name]["template"]}.php");



        } elseif ($GLOBALS["CONNECTOR"][$this->name]["template"]=="extended-alpaca") {

            include(__DIR__.DIRECTORY_SEPARATOR."templates".DIRECTORY_SEPARATOR."{$GLOBALS["CONNECTOR"][$this->name]["template"]}.php");


        } elseif ($GLOBALS["CONNECTOR"][$this->name]["template"]=="superHOT") {

            include(__DIR__.DIRECTORY_SEPARATOR."templates".DIRECTORY_SEPARATOR."{$GLOBALS["CONNECTOR"][$this->name]["template"]}.php");


        } elseif ($GLOBALS["CONNECTOR"][$this->name]["template"]=="zephyr") {

            include(__DIR__.DIRECTORY_SEPARATOR."templates".DIRECTORY_SEPARATOR."{$GLOBALS["CONNECTOR"][$this->name]["template"]}.php");


        } elseif ($GLOBALS["CONNECTOR"][$this->name]["template"]=="openchat") {

           
            include(__DIR__.DIRECTORY_SEPARATOR."templates".DIRECTORY_SEPARATOR."{$GLOBALS["CONNECTOR"][$this->name]["template"]}.php");

        } elseif ($GLOBALS["CONNECTOR"][$this->name]["template"]=="dreamgen") {

           
            include(__DIR__.DIRECTORY_SEPARATOR."templates".DIRECTORY_SEPARATOR."{$GLOBALS["CONNECTOR"][$this->name]["template"]}.php");

        } elseif (file_exists(__DIR__.DIRECTORY_SEPARATOR."templates".DIRECTORY_SEPARATOR."{$GLOBALS["CONNECTOR"][$this->name]["template"]}.php")) {

           
            include(__DIR__.DIRECTORY_SEPARATOR."templates".DIRECTORY_SEPARATOR."{$GLOBALS["CONNECTOR"][$this->name]["template"]}.php");

        }


        $TEMPERATURE=((isset($GLOBALS["CONNECTOR"][$this->name]["temperature"]) ? $GLOBALS["CONNECTOR"][$this->name]["temperature"] : 0.9)+0);
        $REP_PEN=((isset($GLOBALS["CONNECTOR"][$this->name]["rep_pen"]) ? $GLOBALS["CONNECTOR"][$this->name]["rep_pen"] : 1.12)+0);
        $TOP_P=((isset($GLOBALS["CONNECTOR"][$this->name]["top_p"]) ? $GLOBALS["CONNECTOR"][$this->name]["top_p"] : 0.9)+0);

        $MAX_TOKENS=((isset($GLOBALS["CONNECTOR"][$this->name]["max_tokens"]) ? $GLOBALS["CONNECTOR"][$this->name]["max_tokens"] : 48)+0);


        if ($GLOBALS["CONNECTOR"][$this->name]["newline_as_stopseq"]) {
            $stop_sequence[]="\n";
        }

        if (isset($GLOBALS["more_stopseq"])) {
            foreach ($GLOBALS["more_stopseq"] as $stopseq)
                $stop_sequence[]=$stopseq;
        }

        ///
        $stop_sequence[]=$GLOBALS["CONNECTOR"]["koboldcpp"]["eos_token"];
        ///
        $postData = array(

            "prompt"=>$context,
            "temperature"=> $TEMPERATURE,
            "top_p"=>$TOP_P,
            //"max_context_length"=>2048,
            "max_length"=>$MAX_TOKENS,
            "min_p"=>$GLOBALS["CONNECTOR"][$this->name]["min_p"]+0,
            "top_k"=>$GLOBALS["CONNECTOR"][$this->name]["top_k"]+0,
            "rep_pen"=>$REP_PEN,
            "stop_sequence"=>$stop_sequence,
            "use_default_badwordsids"=>$GLOBALS["CONNECTOR"][$this->name]["use_default_badwordsids"]

        );

        if ((isset($GLOBALS["CONNECTOR"]["koboldcpp"]["eos_token"]))&&!empty($GLOBALS["CONNECTOR"]["koboldcpp"]["eos_token"])) {
                $eos_token_allow_grammar='| "'.$GLOBALS["CONNECTOR"]["koboldcpp"]["eos_token"].'"';
        } else
            $eos_token_allow_grammar='';

        $moodsText='"';
        //  ("["whispering"|"dazed"|"default"]*")"
        if (@is_array($GLOBALS["TTS"]["AZURE"]["validMoods"]) &&  sizeof($GLOBALS["TTS"]["AZURE"]["validMoods"])>0)
            if ($GLOBALS["TTSFUNCTION"]=="azure")
                $moodsText='("["' . implode('","', $GLOBALS["TTS"]["AZURE"]["validMoods"]) . '"]*")"';


        // Grammar Sampling.
        if ($GLOBALS["gameRequest"][0]=="diary"){

            $postData["stop_sequence"]=["Author's notes","###","```"];
            
            $postData["grammar"]='
root ::= fullanswer
fullanswer ::= "\nDear Diary, " text
text ::= char text | char | '.$eos_token_allow_grammar.'
char ::= ANYTEXT
keywords ::= char keywords | char
ANYTEXT ::= [a-zA-Z0-9.,?!\' \n]
';

        } else if ($GLOBALS["gameRequest"][0]=="summary") {
            $eos_token_allow_grammar='';
            $postData["grammar"]='
root ::= fullanswer
fullanswer ::= "Location: " answer "\nPeople: " answer "\nMission: " answer "\nSummary: " answer
answer ::= sentence "." answer | sentence
sentence ::= words
words ::= word words | word '.$eos_token_allow_grammar.'
word ::= ANYTEXT
ANYTEXT ::= [a-zA-Z0-9.,?!\'\\" ]
';
            $postData["grammar"]='
root ::= fullanswer
fullanswer ::= "Location: " answer "\nPeople: " answer "\nMission: " answer "\nSummary: " answer
answer ::= sentence | '.$eos_token_allow_grammar.' | "\n"
sentence ::= [a-zA-Z0-9.,?!\' \\"]*
';
      
      //unset($postData["grammar"]);

        } else {

            $postData["grammar"]='
root ::= fullanswer
fullanswer ::= "'.$GLOBALS["HERIKA_NAME"].': '.$moodsText.' answer 
answer ::= sentence "." answer | sentence
sentence ::= words
words ::= word words | word '.$eos_token_allow_grammar.'
word ::= ANYTEXT
ANYTEXT ::= [a-zA-Z0-9.,?!\' ]
';
            $postData["grammar"]='
root ::= fullanswer
fullanswer ::= "'.$GLOBALS["HERIKA_NAME"].': '.$moodsText.' answer 
answer ::= sentence | sentence '.$eos_token_allow_grammar.' | sentence "\n"
sentence ::= [a-zA-Z0-9.,?!\' ]*
';
//ANYTEXT ::= [a-zA-Z0-9.,?!\' ]

        
        }


        if (isset($customParms["GRAMMAR_ACTIONS"])) {
            $postData["grammar"]='
root ::= fullanswer
fullanswer ::= "ExchangeItems()" | "SetCurrentPlan(" sentence ")" | "NoOp()" 
sentence ::= [a-zA-Z0-9.,?!\' ]*

';
        unset($postData["grammar"]);
        $postData["grammar"]=file_get_contents(__DIR__.DIRECTORY_SEPARATOR."grammar".DIRECTORY_SEPARATOR."json.bnf");
        }


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

        
        $GLOBALS["DEBUG_DATA"]["koboldcpp_prompt"]=$postData;

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

        
        //print_r($postData);
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
            fflush($this->primary_handler);
        } else {
             error_log("Unable to connect to koboldcpp backend!");
            return false;
        }

        
        // Initialize variables for response
        $responseHeaders = '';
        $responseBody = '';
        
        $this->_jsonBuffer="";
        
        return true;

    }


    public function process()
    {
        $line = fgets($this->primary_handler);
        $buffer="";
        $totalBuffer="";
        file_put_contents(__DIR__."/../log/debugStream.log", $line, FILE_APPEND);
       
        
        if (strpos($line, 'data: {') !== 0) {
            return "";
        }
        
        if ($this->_jsonMode) {
            $data=json_decode(substr($line, 6), true);
            $this->_jsonBuffer.=$data["token"];
            return "";
        }
        
        //$_ignoreRest

        $data=json_decode(substr($line, 6), true);


        if (!$data)
            return "";
        
        if (strpos($line, 'data: {"token": "{') !== false) {
            $this->_jsonBuffer.=$data["token"];
            $this->_jsonMode=true;
            return "";

        }

         /*
         if (strpos($line, 'data: {"token": "[') === 0) {

            $this->_functionMode=true;
            //$this->_functionRawName.=$data["token"];
            return "";
        }

         if (strpos($line, 'data: {"token": "]"}') === 0 || strpos($data["token"], ']')!==false) {

            $this->_functionMode=false;
            //$this->_functionRawName.=$data["token"];
            return "";
        }
        */


        /*
         if (strpos($line, 'data: {"token": "#"}') === 0) {

            $this->_functionMode=true;
            return "";
        }
        */


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
        // /api/extra/abort ?
        while (!feof($this->primary_handler))   // buffer flush?
            fgets($this->primary_handler);

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

        $jsonData=json_decode($this->_jsonBuffer,true);
        
        //print_r($jsonData);
        
        if ($this->_jsonMode) {
            
            if (isset($jsonData["command"])) {
                
                if (isset($jsonData["priority"])) 
                    $prob=$jsonData["priority"];
                else
                    $prob="";
                
                if (($prob!="urgent" && $prob!="high" && $prob!="now") && false)
                    ;
                else {
                    $intent=$jsonData["command"];
                    if ($intent=="DoNothing")
                        ;// nothing to do
                        
                    else if ($intent=="WriteIntoQuestJournal"||$intent=="UpdateQuestJournal") {
                        // bypass reponse.
                        if (isset($jsonData["topic"])) {
                            $this->_functionRawName="SetCurrentTask@{$jsonData["topic"]}";
                            $GLOBALS["db"]->insert(
                                'currentmission',
                                array(
                                    'ts' => $GLOBALS["gameRequest"][1],
                                    'gamets' => $GLOBALS["gameRequest"][2],
                                    'description' => $jsonData["topic"],
                                    'sess' => 'pending',
                                    'localts' => time()
                                )
                            );
                        }
                        //$alreadysent[md5("Herika|command|{$this->_functionRawName}\r\n")] = "Herika|command|{$this->_functionRawName}\r\n";
                    }    
                    else if ($intent=="OpenBackPack"||$intent=="OpenInventory"||$intent=="ExchangeItems") {

                        $this->_functionRawName="OpenInventory@";
                        $alreadysent[md5("Herika|command|{$this->_functionRawName}\r\n")] = "Herika|command|{$this->_functionRawName}\r\n";
                        
                    } else if ($intent=="TakeASeat") {
                        // bypass reponse.
                        $this->_functionRawName="TakeASeat@";
                        
                        $alreadysent[md5("Herika|command|{$this->_functionRawName}\r\n")] = "Herika|command|{$this->_functionRawName}\r\n";
                    } else if ($intent=="GatherInfo") {
                        // bypass reponse.
                        $this->_functionRawName="GatherInfo@{$jsonData["topic"]}";
                        
                        //$alreadysent[md5("Herika|command|{$this->_functionRawName}\r\n")] = "Herika|command|{$this->_functionRawName}\r\n";
                    }
                }
                    
                
                return $alreadysent;
                
                
            }
            
            
        } else {

            if ((isset($this->_functionMode))&&($this->_functionMode)) {
                
                $kobname=$this->_functionRawName;
                $kobname=strtr("$kobname",["("=>"@",")"=>"@"]);
                $kobParsed=explode("@",$kobname);
                
                $this->_functionRawName=$kobname;
                if ($kobParsed[0]=="NoOp")
                    ;// nothing to do
                    
                else if ($kobParsed[0]=="SetCurrentPlan") {
                    // bypass reponse.
                    $this->_functionRawName="SetCurrentTask@{$kobParsed[1]}";
                    $GLOBALS["db"]->insert(
                        'currentmission',
                        array(
                            'ts' => $GLOBALS["gameRequest"][1],
                            'gamets' => $GLOBALS["gameRequest"][2],
                            'description' => $kobParsed[1],
                            'sess' => 'pending',
                            'localts' => time()
                        )
                    );
                    $alreadysent[md5("Herika|command|{$this->_functionRawName}\r\n")] = "Herika|command|{$this->_functionRawName}\r\n";
                }    
                else if ($kobParsed[0]=="ExchangeItems") {
                    // bypass reponse.
                    $this->_functionRawName="OpenInventory@";
                    
                    $alreadysent[md5("Herika|command|{$this->_functionRawName}\r\n")] = "Herika|command|{$this->_functionRawName}\r\n";
                } else if ($kobParsed[0]=="TakeASeat") {
                    // bypass reponse.
                    $this->_functionRawName="TakeASeat@";
                    
                    $alreadysent[md5("Herika|command|{$this->_functionRawName}\r\n")] = "Herika|command|{$this->_functionRawName}\r\n";
                }   
                    
                
                return $alreadysent;
            }
        }
        
        return [];
        
    }


}
