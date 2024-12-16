<?php
session_start();

// Enable error reporting (for development purposes)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Paths
$rootPath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
$enginePath = $rootPath . ".." . DIRECTORY_SEPARATOR;
$configFilepath = $rootPath . "conf" . DIRECTORY_SEPARATOR;

// Database connection details
$host = 'localhost';
$port = '5432';
$dbname = 'dwemer';
$schema = 'public';
$username = 'dwemer';
$password = 'dwemer';

// Profile selection
$GLOBALS["PROFILES"] = []; // Initialize the PROFILES array
foreach (glob($configFilepath . 'conf_????????????????????????????????.php') as $mconf) {
    if (file_exists($mconf)) {
        $filename = basename($mconf);
        $pattern = '/conf_([a-f0-9]+)\.php/';
        if (preg_match($pattern, $filename, $matches)) {
            $hash = $matches[1];
            $GLOBALS["PROFILES"][$hash] = $mconf;
        }
    }
}

function compareFileModificationDate($a, $b) {
    return filemtime($b) - filemtime($a);
}

if (is_array($GLOBALS["PROFILES"])) {
    usort($GLOBALS["PROFILES"], 'compareFileModificationDate');
} else {
    $GLOBALS["PROFILES"] = [];
}

$GLOBALS["PROFILES"] = array_merge(["default" => "$configFilepath/conf.php"], $GLOBALS["PROFILES"]);

if (isset($_SESSION["PROFILE"]) && in_array($_SESSION["PROFILE"], $GLOBALS["PROFILES"])) {
    require_once($_SESSION["PROFILE"]);
} else {
    $_SESSION["PROFILE"] = "$configFilepath/conf.php";
}

// Connect to the database
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");

if (!$conn) {
    echo "<div class='message'>Failed to connect to database: " . pg_last_error() . "</div>";
    exit;
}

// Pagination settings
$limit = 200;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $limit;

// Count total rows
$countQuery = "SELECT COUNT(*) FROM {$schema}.event_log";
$countResult = pg_query($conn, $countQuery);
$totalRows = 0;
if ($countResult) {
    $totalRows = pg_fetch_result($countResult, 0, 0);
}

// Fetch the entries
$query = "
    SELECT people, location, gamets
    FROM {$schema}.event_log
    ORDER BY gamets DESC
    LIMIT $limit OFFSET $offset
";

$result = pg_query($conn, $query);

if (!$result) {
    echo "<div class='message'>Query error: " . pg_last_error($conn) . "</div>";
    exit;
}

// Display the table
echo "<h1>CHIM Adventure Log</h1>";
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>People</th><th>Location</th><th>Gamets</th></tr>";
while ($row = pg_fetch_assoc($result)) {
    $people = htmlspecialchars($row['people']);
    $location = htmlspecialchars($row['location']);
    $gamets = htmlspecialchars($row['gamets']);
    echo "<tr><td>{$people}</td><td>{$location}</td><td>{$gamets}</td></tr>";
}
echo "</table>";

// Pagination controls
$totalPages = ceil($totalRows / $limit);

echo "<div style='margin-top:10px;'>";
if ($page > 1) {
    $prev = $page - 1;
    echo "<a href='?page={$prev}'>&laquo; Previous</a> ";
}

if ($page < $totalPages) {
    $next = $page + 1;
    echo "<a href='?page={$next}'>Next &raquo;</a>";
}
echo "</div>";

// Close connection
pg_close($conn);
