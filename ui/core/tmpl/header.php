<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?? 'Admin Panel' ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding-top: 60px;
            background-color: #f9f9f9;
        }
        nav {
            background-color: #333;
            color: white;
            position: fixed;
            top: 0;
            padding: 12px 20px 20px 0px;
            z-index: 999;
            display: flex;
            justify-content: space-around;
            align-items: center;
            width: 100%;
        }
        nav .title {
            font-size: 1.2em;
            font-weight: bold;
          
        }
        nav a {
            color: white;
            margin-left: 20px;
            text-decoration: none;
        }
        nav a:hover {
            text-decoration: underline;
        }
        main {
            padding: 20px 40px;
        }
        table {
            background-color: white;
        }
        form {
            background-color: white;
            padding: 20px;
            border-radius: 6px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
        }

        body { font-family: Arial; margin: 0px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { padding: 8px; border: 1px solid #ccc; text-align: left; }
        form { margin-top: 20px; }
        input[type="text"] { width: 100%; padding: 6px; margin: 4px 0; }
        textarea { width: 100%; padding: 6px; margin: 4px 0; min-height:90px}

        input[type="submit"] { padding: 8px 16px; }
        .actions a { margin-right: 10px; }

        td.truncate-multiline {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 300px;
        }

        label {display:block;margin-top:10px}
        br {margin-bottom:10px}

    </style>
</head>
<body>

<nav>
    <div class="title">🧠 Admin Tools</div>
    <div>
        
        <a href="api_badge.php">API Badges</a>
        <a href="llm_connectors.php">LLM Connectors</a>
        <a href="core_profiles.php">Core Profiles</a>
        <a href="npc_master.php">NPC Master</a>
    </div>
</nav>

<main>
