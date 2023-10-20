
<nav class="navbar navbar-expand-lg bg-primary-subtle">
    <div class="container-fluid mx-1">
        <a class="navbar-brand mr-2" href="./index.php" title="Go to main">Herika Server</a>&nbsp;|&nbsp;
        <a class="navbar-brand mr-2" href="./index.php?togglemodel=true" title="Click to change active connector">
          Active LLM/AI: <?php echo trim(json_decode(file_get_contents(__DIR__.DIRECTORY_SEPARATOR.'../../data/CurrentModel.json'),true)); ?>
        </a>

        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Data Tables</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="index.php?table=responselog" title="">Responses</a></li>
                    <li><a class="dropdown-item" href="index.php?table=eventlog">Events</a></li>
                    <li><a class="dropdown-item" href="index.php?table=log">Log</a></li>
                    <li><a class="dropdown-item" href="index.php?table=quests">Quest journal</a></li>
                    <li><a class="dropdown-item" href="index.php?table=currentmission">Current mission</a></li>
                    <li><a class="dropdown-item" href="index.php?table=diarylog">Diary</a></li>
                    <li><a class="dropdown-item" href="index.php?table=books">Books</a></li>

                    <li><a class="dropdown-item" href="index.php?table=openai_token_count">OpenAI Token Pricing</a></li>
                    <li><a class="dropdown-item" href="index.php?table=memory">Memories</a></li>
                    <li><a class="dropdown-item" href="index.php?table=memory_summary">Memories Summarized</a></li>
                    <li><a class="dropdown-item" href="index.php?table=eventlog&autorefresh=true">Monitor events</a></li>
                </ul>
            </li>
            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Operations</a>
                <ul class="dropdown-menu">
                    <li>
                        <a class="dropdown-item" href="index.php?clean=true&table=response" title="Delete sent responses" onclick="return confirm('Sure?')">
                            Clean sent
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="index.php?sendclean=true&table=response" title="Marks unsent responses from queue What do you think about?" onclick="return confirm('Sure?')">
                            Reset sent
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="index.php?reset=true&table=event" title="Delete all events." onclick="return confirm('Sure?')">
                            Reset events
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="index.php?reinstall=true" title="Create new tables if needed" onclick="return confirm('Sure?')">
                            Create tables
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="index.php?reinstall=true&delete=true" title="Drop all tables and then create them" onclick="return confirm('Sure?')">
                            Full Database Reinstall
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="index.php?cleanlog=true" title="Clean log table" onclick="return confirm('Sure?')">
                            Clean Log
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="index.php?export=log" title="Export Log (debugging purposes)" target="_blank">
                            Export Log
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="index.php?export=diary" title="Diary Log" target="_blank">
                            Export Diary
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="import_db.php" title="Import Database" >
                            Import Database
                        </a>
                    </li>
                </ul>
            </li>

            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Troubleshooting</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="../soundcache/" target="_blank">TTS cache</a></li>
                    <li><a class="dropdown-item" href="updater.php">Updater</a></li>
                    <li><a class="dropdown-item" href="tests.php" target="_blank">Test ChatGPT/KoboldCPP connection</a></li>
                    <li><a class="dropdown-item" href="tests/tts-test-azure.php" target="_blank">Test Azure TTS connection</a></li>
                    <li><a class="dropdown-item" href="tests/tts-test-mimic3.php" target="_blank">Test MIMIC3 TTS connection</a></li>
                    <li><a class="dropdown-item" href="tests/tts-test-11labs.php" target="_blank">Test ElevenLabs TTS connection</a></li>
                    <li><a class="dropdown-item" href="tests/tts-test-gcp.php" target="_blank">Test GCP TTS connection</a></li>
                    <li><a class="dropdown-item" href="tests/tts-test-coqui-ai.php" target="_blank">Test Coqui.AI TTS connection</a></li>
                </ul>
            </li>

            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Configuration</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="conf_editor.php" target="_blank">Configuration Editor</a></li>
                    <li><a class="dropdown-item" href="conf_wizard.php">Configuration Wizard</a></li>
                    <li><a class="dropdown-item" href="conf_export.php" title="Export Configuration" target="_blank">Export Configuration</a></li>
                    <li><a class="dropdown-item" href="conf_import.php" title="Import Configuration" target="_blank">Import Configuration</a></li>
                </ul>
            </li>

            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Immersion</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="addons/background" target="_blank">Background story generator</a></li>
                    <li><a class="dropdown-item" href="addons/diary" target="_blank">AI's diary</a></li>
                    <li><a class="dropdown-item" href="addons/chatsim" target="_blank">Chat Simulation</a></li>
                </ul>
            </li>

            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Please read</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href='index.php?notes=true'>Notes from developers</a></li>
                </ul>
            </li>
            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Server plugins</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href='index.php?plugins_show=true'>Installed plugins</a></li>
                </ul>
            </li>

        </ul>
    </div>
</nav>
