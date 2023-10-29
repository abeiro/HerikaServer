<div class="container-fluid">
    <div class="row">
        <div class="col">
            <h4 class="my-2"><strong>Now, the server update is performed through the Update.bat script</strong></h4>

             <p>This is your current version</p> 
<pre>
<?php
$gitDirectory = __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.".git".DIRECTORY_SEPARATOR;


$lastCommit = shell_exec("git --git-dir=$gitDirectory log -1");

echo $lastCommit;
?>

</pre>
        </div>
    </div>

    <h4 class="my-3">
        You can find us at Discord.
        <a class="icon-link" href="https://discord.com/invite/NDn9qud2ug">
            https://discord.com/invite/NDn9qud2ug <i class='bi-discord'></i>
        </a>
        Server code: <a href="https://github.com/abeiro/HerikaServer">https://github.com/abeiro/HerikaServer</a>
    </h4>

</div>



