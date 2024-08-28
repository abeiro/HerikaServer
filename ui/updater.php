<div class="container-fluid">
    <div class="row">
        <div class="col">
            <h4 class="my-2"><strong>You can update this servering using the Update.bat script in the Tools folder</strong></h4>

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
        Our Discord:
        <a class="icon-link" href="https://discord.com/invite/NDn9qud2ug">
            https://discord.com/invite/NDn9qud2ug <i class='bi-discord'></i>
        </a>
        <br>
        AI-FF Server source code: <a href="https://github.com/abeiro/HerikaServer">https://github.com/abeiro/HerikaServer</a>
    </h4>

</div>



