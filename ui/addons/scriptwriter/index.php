<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
</head>
<body>

<?php

error_reporting(E_ALL);
$enginePath =__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."memory_helper_vectordb.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."memory_helper_embeddings.php");

echo file_get_contents('template.html');


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["savescript"])) {
        $scriptContent = $_POST["script"];
        file_put_contents('script.json', $scriptContent);
        echo "Script Saved";
    } else if (isset($_POST["runscript"])) {
        sleep(5);
        $output = shell_exec('php HerikaScriptWriter.php');
        echo "<pre>$output</pre>";
    } else if (isset($_POST["savetemplate"])) {
            $scriptContent = $_POST["scriptgenerator"];
            file_put_contents('scriptgenerator.txt', $scriptContent);
            echo "Template Saved";
    } else if (isset($_POST["restoretemplate"])) {
            $scriptgeneratortemplate = file_get_contents('scriptgeneratortemplate.txt');
            file_put_contents('scriptgenerator.txt', $scriptgeneratortemplate);
            echo "Template Restored!";
    } else if (isset($_POST["generatorrun"])) {
        $head[] = array('role' => 'system', 'content' => "Generate a video script following in .json format using these rules.");
        $prompt[] = array('role' => 'user', 'content' => $scriptgenerator);
        
        $parms=array_merge($head,$prompt);

        require($enginePath.DIRECTORY_SEPARATOR."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CURRENT_CONNECTOR"]}.php");
        $connectionHandler=new connector();
        $connectionHandler->open($parms,["MAX_TOKENS"=>$_POST["OPENAI_MAX_TOKENS_MEMORY"]]);

        $buffer="";
        $totalBuffer="";
        $breakFlag=false;

        while (true) {

            if ($breakFlag) {
                break;
            }

            $buffer.=$connectionHandler->process();
            $totalBuffer.=$buffer;

            if ($connectionHandler->isDone()) {
                $breakFlag=true;
            }

        }
        $rawResponse=$buffer;

        $connectionHandler->close();
    }
}


$scriptjson = trim(file_get_contents('script.json'));
$scriptgenerator = file_get_contents('scriptgenerator.txt');
$scriptgeneratortemplate = file_get_contents('scriptgeneratortemplate.txt');

echo "
<form action='' method='post'>
<h1>Herika Script Writer ✒</h1>
<h2>Script (.json Format)</h2>
<textarea name='script' style='width:90%;height:500px'>
$scriptjson
</textarea>
<br>
<input type='submit' name='savescript' value='Save Script' />
<input type='submit' name='runscript' value='Run Script' />

<div class='divider'></div>

<h2>Script Generator Template</h2>
<textarea name='scriptgenerator' style='width:90%;height:500px'>
$scriptgenerator
</textarea>
<br>
<input type='submit' name='savetemplate' value='Save Template' />
<input type='submit' name='restoretemplate' value='Restore Default Template' />
<h2>Generator Output</h2>
<textarea name='generatoroutput' style='width:90%;height:300px' placeholder=''>
$rawResponse
</textarea>
<h3>Token limit</h3>
<br>
<input type='range' min='100' max='1024' value='".(($_POST["OPENAI_MAX_TOKENS_MEMORY"])?:200)."'  name='OPENAI_MAX_TOKENS_MEMORY' oninput='this.nextElementSibling.value = this.value'>
<output>".(($_POST["OPENAI_MAX_TOKENS_MEMORY"])?:200)."</output>
<br>
<input type='submit' name='generatorrun' value='Generate Script' />
</form>

</body>
</html>
";

?>
