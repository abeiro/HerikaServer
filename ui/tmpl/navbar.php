<nav class="navbar navbar-expand-lg bg-primary-subtle">
    <div class="container-fluid mx-1">
        <a class="navbar-brand mr-2 Title" href="./index.php?notes=true" title="Go to Home Page"><img src="images/DwemerDynamics.png" alt=" Herika Server" style="vertical-align:bottom;"/> Herika Server
        <a class="navbar-brand mr-2 button" href="./index.php?togglemodel=true" title="Click to change active connector">
        Active LLM/AI: <?php echo trim(json_decode(file_get_contents(__DIR__.DIRECTORY_SEPARATOR.'../../data/CurrentModel.json'), true)); ?>
        </a>

        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Events</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="index.php?table=eventlog">Events</a></li>
                    <li><a class="dropdown-item" href="index.php?table=responselog" title="">Responses</a></li>
                    <li><a class="dropdown-item" href="index.php?table=log">AI Log</a></li>
                    <li><a class="dropdown-item" href="index.php?table=quests">Current Active Quests</a></li>
                    <li><a class="dropdown-item" href="index.php?table=eventlog&autorefresh=true">Monitor events</a></li>
                </ul>
            </li>
            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Memories</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="index.php?table=currentmission">Current AI Task/Goal</a></li>
                    <li><a class="dropdown-item" href="index.php?table=diarylog">Diary Log</a></li>
                    <li><a class="dropdown-item" href="index.php?table=books">Book Log</a></li>
                    <li><a class="dropdown-item" href="index.php?table=memory">Memories</a></li>
                    <li><a class="dropdown-item" href="index.php?table=memory_summary">Memories Summarized</a></li>
                </ul>
            </li>
            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Server Actions</a>
                <ul class="dropdown-menu">
                    <li>
                        <a class="dropdown-item" href="index.php?clean=true&table=response" title="Delete sent responses." onclick="return confirm('Sure?')">
                            Clean Sent
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="index.php?sendclean=true&table=response" title="Marks unsent responses from queue." onclick="return confirm('Sure?')">
                            Reset Sent
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="index.php?reset=true&table=event" title="Delete all events." onclick="return confirm('Sure?')">
                            Reset Events
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="index.php?cleanlog=true" title="Clean log table" onclick="return confirm('Sure?')">
                            Clean AI Log
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="index.php?export=log" title="Export Log (debugging purposes)" target="_blank">
                            Export AI Log
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="index.php?export=diary" title="Diary Log" target="_blank">
                            Export Diary
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="import_db.php" title="Import Database" >
                            Import SQLITE3 Database
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="index.php?reinstall=true" title="Create new tables if needed." onclick="return confirm('Will reinstall all database tables. Are you Sure?')">
                            Install Server Tables
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="index.php?reinstall=true&delete=true" title="Fully reinstalls the Herika Server." onclick="return confirm('This will wipe the entire server!!! Ignore this message if this is your initial installation. Are you really sure?')">
                            Reinitialize Herika Server
                        </a>
                    </li>
                </ul>
            </li>

            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Troubleshooting</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="../soundcache/" target="_blank">Text-to-Speech Cache</a></li>
                    <li><a class="dropdown-item" href="updater.php">Update Server</a></li>
                    <li><a class="dropdown-item" href="tests.php" target="_blank">Test ChatGPT/KoboldCPP Connection</a></li>
                    <li><a class="dropdown-item" href="tests/tts-test-azure.php" target="_blank">Test Azure TTS Connection</a></li>
                    <li><a class="dropdown-item" href="tests/tts-test-mimic3.php" target="_blank">Test MIMIC3 TTS Connection</a></li>
                    <li><a class="dropdown-item" href="tests/tts-test-11labs.php" target="_blank">Test ElevenLabs TTS Connection</a></li>
                    <li><a class="dropdown-item" href="tests/tts-test-gcp.php" target="_blank">Test GCP TTS Connection</a></li>
                    <li><a class="dropdown-item" href="tests/tts-test-coqui-ai.php" target="_blank">Test Coqui.AI TTS Connection</a></li>
                    <li><a class="dropdown-item" href="tests/vector-test-chromadb.php" target="_blank">Test ChromaDB Memories</a></li>
                </ul>
            </li>

            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Configuration</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="conf_editor.php" target="_blank">Configuration Editor</a></li>
                    <li><a class="dropdown-item" href="conf_wizard.php">Configuration Wizard</a></li>
                    <li><a class="dropdown-item" href="conf_export.php" title="Export Configuration" target="_blank">Export Configuration</a></li>
                    <li><a class="dropdown-item" href="conf_import.php" title="Import Configuration" target="_blank">Import Configuration</a></li>
                    <li><a class="dropdown-item" href="index.php?table=openai_token_count">OpenAI Token Pricing</a></li>
                </ul>
            </li>

            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Immersion</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="addons/background" target="_blank">Background Story Generator</a></li>
                    <li><a class="dropdown-item" href="addons/diary" target="_blank">Herika's Diary</a></li>
                    <li><a class="dropdown-item" href="addons/chatsim" target="_blank">Chat Simulation</a></li>
                </ul>
            </li>

            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Server Plugins</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href='index.php?plugins_show=true'>Installed Plugins</a></li>
                </ul>
            </li>

            <li class="nav-item dropdown mx-2">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Please Read!</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href='index.php?notes=true'>Developer's Note</a></li>
                </ul>
            </li>
            

        </ul>
    </div>
</nav>
