<?php
require_once(__DIR__."/../../profile_loader.php");

// Database connection details
$host = 'localhost';
$port = '5432';
$dbname = 'dwemer';
$schema = 'public';
$username = 'dwemer';
$password = 'dwemer';

// Connect to the database
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");

if (!$conn) {
    echo "<p>Failed to connect to database</p>";
    exit;
}

$letter = isset($_GET['letter']) ? strtoupper($_GET['letter']) : '';

// Build query based on optional filter
if (!empty($letter) && ctype_alpha($letter) && strlen($letter) === 1) {
    // Filter by first letter
    $query_combined = "
        SELECT npc_name, npc_dynamic, npc_pers, npc_misc, melotts_voiceid, xtts_voiceid, xvasynth_voiceid
        FROM {$schema}.combined_npc_templates
        WHERE npc_name ILIKE $1
        ORDER BY npc_name ASC
    ";
    $params_combined = [$letter . '%'];
    $result_combined = pg_query_params($conn, $query_combined, $params_combined);
} else {
    // No filter: show all
    $query_combined = "
        SELECT npc_name, npc_dynamic, npc_pers, npc_misc, melotts_voiceid, xtts_voiceid, xvasynth_voiceid
        FROM {$schema}.combined_npc_templates
        ORDER BY npc_name ASC
    ";
    $result_combined = pg_query($conn, $query_combined);
}

if ($result_combined) {
    echo '<table>';
    echo '<tr>';
    echo '  <th>npc_name</th>';
    echo '  <th>npc_pers</th>';
    echo '  <th>npc_dynamic</th>';
    echo '  <th>npc_misc</th>';
    echo '  <th>melotts_voiceid</th>';
    echo '  <th>xtts_voiceid</th>';
    echo '  <th>xvasynth_voiceid</th>';
    echo '  <th>Actions</th>';
    echo '</tr>';

    $rowCountCombined = 0;
    while ($row = pg_fetch_assoc($result_combined)) {
        echo '<tr>';
        echo '  <td>' . htmlspecialchars($row['npc_name'] ?? '') . '</td>';
        echo '  <td>' . nl2br(htmlspecialchars($row['npc_pers'] ?? '')) . '</td>';
        echo '  <td>' . ($row['npc_dynamic'] !== null ? nl2br(htmlspecialchars($row['npc_dynamic'])) : '') . '</td>';
        echo '  <td>' . ($row['npc_misc'] !== null ? nl2br(htmlspecialchars($row['npc_misc'])) : '') . '</td>';
        echo '  <td>' . htmlspecialchars($row['melotts_voiceid'] ?? '') . '</td>';
        echo '  <td>' . htmlspecialchars($row['xtts_voiceid'] ?? '') . '</td>';
        echo '  <td>' . htmlspecialchars($row['xvasynth_voiceid'] ?? '') . '</td>';
        
        // Add Edit button
        echo '<td>';
        echo '<div class="button-group">';
        $jsData = [
            'npc_name' => $row['npc_name'],
            'npc_pers' => $row['npc_pers'],
            'npc_dynamic' => $row['npc_dynamic'] ?? '',
            'npc_misc' => $row['npc_misc'] ?? '',
            'melotts_voiceid' => $row['melotts_voiceid'] ?? '',
            'xtts_voiceid' => $row['xtts_voiceid'] ?? '',
            'xvasynth_voiceid' => $row['xvasynth_voiceid'] ?? ''
        ];
        echo '<button onclick="openEditModal(' . 
            htmlspecialchars(str_replace(
                ["\r", "\n", "'"],
                [' ', ' ', "\\'"],
                json_encode($jsData)
            ), ENT_QUOTES, 'UTF-8') . 
            ')" class="action-button edit">Edit</button>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
        
        $rowCountCombined++;
    }
    echo '</table>';

    if ($rowCountCombined === 0) {
        echo '<p>No NPC templates found.</p>';
    }
} else {
    echo '<p>Error fetching NPC templates</p>';
}
?> 