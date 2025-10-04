<?php
session_start();

date_default_timezone_set('UTC');
error_reporting(E_ALL);
ini_set('display_errors', '1');

// DB
$host = 'localhost';
$port = '5432';
$dbname = 'dwemer';
$schema = 'public';
$username = 'dwemer';
$password = 'dwemer';

require_once(dirname(__DIR__).DIRECTORY_SEPARATOR."lib/utils_game_timestamp.php");

$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath));
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

$conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");
if (!$conn) {
    http_response_code(500);
    echo "<p>DB connection failed.</p>";
    exit;
}

$person = isset($_GET['person']) ? trim(urldecode($_GET['person'])) : '';
if ($person === '') {
    echo "<p>Missing person.</p>";
    exit;
}

$result = pg_query_params($conn, "SELECT rowid, content, people, location, localts, gamets FROM {$schema}.diarylog WHERE people LIKE $1", ['%'.$person.'%']);
$entries = [];
if ($result) {
    while ($row = pg_fetch_assoc($result)) { $entries[] = $row; }
}

usort($entries, function($a, $b) { return ((int)$a['localts']) - ((int)$b['localts']); });

pg_close($conn);

$safeTitle = htmlspecialchars($person);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>The Diary of <?php echo $safeTitle; ?></title>
    <link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
    <link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/diary_adventure.css">
    <style>
        @font-face {
            font-family: 'MagicCards';
            src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: 'SkyrimBooks_Handwritten_Bold';
            src: url('<?php echo $webRoot; ?>/ui/css/font/SkyrimBooks_Handwritten_Bold-Regular.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        body { background: #1a1a1a; color: #f8f9fa; margin: 0; }
        .book { max-width: 980px; margin: 0 auto; padding: 30px 16px 60px; }
        .book-header { text-align: center; padding: 20px 0 10px; }
        .book-header h1 { font-family: 'MagicCards', serif; color: rgb(242, 124, 17); text-shadow: 2px 2px 4px rgba(0,0,0,0.5); margin: 0; font-size: 32px; letter-spacing: 1px; }
        .actions { position: sticky; top: 0; background: rgba(26,26,26,.92); padding: 8px 0; border-bottom: 1px solid #333; display: flex; gap: 8px; justify-content: flex-end; z-index: 2; }
        .btn { background: rgb(212, 94, 0); color: #fff; border: 2px solid rgb(172, 76, 0); padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 14px; }
        .btn:hover { background: rgb(172, 76, 0); }

        /* Entry parchment block, matching CHIM modal look */
        .entry { page-break-inside: avoid; margin: 18px auto; max-width: 900px; }
        .entry .paper {
            background: url('<?php echo $webRoot; ?>/ui/images/paper.jpg') center/cover;
            padding: 36px;
            border-radius: 6px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.5);
            color: #000;
            font-family: SkyrimBooks_Handwritten_Bold, Arial, sans-serif !important;
            font-size: 1.2em;
            line-height: 1.75;
            white-space: pre-wrap;
        }

        @media print {
            .actions { display: none; }
            body { background: #fff; color: #000; }
            .entry .paper { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="book">
        <div class="actions">
            <button class="btn" onclick="window.print()">Print / Save as PDF</button>
        </div>
        <div class="book-header">
            <h1>The Diary of <?php echo $safeTitle; ?></h1>
        </div>
        <?php foreach ($entries as $e): ?>
            <?php $content = (string)$e['content']; ?>
            <div class="entry">
                <div class="paper"><?php echo nl2br(htmlspecialchars($content)); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>


