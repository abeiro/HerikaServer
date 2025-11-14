<?php
// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

$TITLE = "🗺️ Background Life - Map Viewer";

ob_start();

$enginePath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "utils_game_timestamp.php";

$host     = 'localhost';
$port     = '5432';
$dbname   = 'dwemer';
$schema   = 'public';
$username = 'dwemer';
$password = 'dwemer';

$adminConn = @pg_connect("host={$host} port={$port} dbname={$dbname} user={$username} password={$password}");
if (! $adminConn) {
    echo json_encode(['ok' => false]);
    exit;
}

    // Handle AJAX request update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'request_update') {
            handleRequestUpdate();
            exit;
        } elseif ($_POST['action'] === 'request_action') {
            handleRequestAction();
            exit;
        } elseif ($_POST['action'] === 'request_reporting') {
            handleRequestReporting();
            exit;
        } elseif ($_POST['action'] === 'update_coords') {
            handleUpdateCoords();
            exit;
        }
    }

    function handleRequestUpdate() {
        global $enginePath;
        // Add your handler code here

        `php $enginePath/debug/simple_llm_request_with_context_life_command.php "The Narrator" TrackAll`;

        echo json_encode(['ok' => true, 'message' => 'Update request processed']);
    }

    function handleRequestAction() {
        global $enginePath;
        $npcName = isset($_POST['npc_name']) ? $_POST['npc_name'] : null;
        
        if (!$npcName) {
            echo json_encode(['ok' => false, 'message' => 'NPC name required']);
            return;
        }
        
        // Add your handler code here
        `php $enginePath/debug/simple_llm_request_with_context_life.php "$npcName" full`;

        echo json_encode(['ok' => true, 'message' => "Action request processed for $npcName"]);
    }

    function handleRequestReporting() {
        global $enginePath;
        $npcName = isset($_POST['npc_name']) ? $_POST['npc_name'] : null;
        
        if (!$npcName) {
            echo json_encode(['ok' => false, 'message' => 'NPC name required']);
            return;
        }
        
        // Add your handler code here
           // Add your handler code here
        `php $enginePath/debug/simple_llm_request_with_context_life.php "$npcName" `;
        echo json_encode(['ok' => true, 'message' => "Reporting request processed for $npcName"]);
    }

    function handleUpdateCoords() {
        global $enginePath;
        $npcName = isset($_POST['npc_name']) ? $_POST['npc_name'] : null;
        
        if (!$npcName) {
            echo json_encode(['ok' => false, 'message' => 'NPC name required']);
            return;
        }
        
        // Add your handler code here
        `php $enginePath/debug/simple_llm_request_with_context_life_command.php "$npcName" Track`;
        echo json_encode(['ok' => true, 'message' => "Coords update processed for $npcName"]);
    }

    // Coordinate translation constants (world bounds)
    // X: west (negative) to east (positive)
    // Y: south (negative) to north (positive)
    $WORLD_X_MIN = -225242;
    $WORLD_X_MAX = 217068;
    $WORLD_Y_MIN = -164195;  // SOUTH (negative)
    $WORLD_Y_MAX = 204675;   // NORTH (positive)

    // Map dimensions (from the actual image)
    $mapWidth  = 1950;
    $mapHeight = 1625;

    // Map file path (relative to web root)
    $mapImageUrl = '../data/maps/Map_of_Skyrim.png';

    // Function to translate in-game coordinates to map coordinates
    function translateCoords($ingameX, $ingameY, $mapWidth, $mapHeight, $worldXMin, $worldXMax, $worldYMin, $worldYMax)
    {
        // Linear mapping from world coordinates to map coordinates
        $worldXRange = $worldXMax - $worldXMin;
        $worldYRange = $worldYMax - $worldYMin;

        // X: west to east, left to right on map
        $mapX = (($ingameX - $worldXMin) / $worldXRange) * $mapWidth;

        // Y: south (negative) to north (positive), but image Y is top to bottom
        // So we need to invert: north (high Y) maps to top of image (low Y)
        $mapY = (($worldYMax - $ingameY) / $worldYRange) * $mapHeight;

        // Clamp coordinates to map bounds
        $mapX = max(0, min($mapWidth, $mapX));
        $mapY = max(0, min($mapHeight, $mapY));

        return [
            'x' => round($mapX),
            'y' => round($mapY),
        ];
    }

    $result  =pg_query($adminConn,"select max(gamets) as last_gamets from eventlog");
    $res = pg_fetch_assoc($result);
    $last_gamets = $res["last_gamets"];
    $currentDate=convert_gamets2skyrim_date($last_gamets);

    // --- Query total cost grouped by request type ---
    $query = "
    SELECT
        npc_name,metadata,id,refid,extended_data->>'background_life_last_updated' as last_report,
        metadata->>'last_coords' as last_coords,metadata->>'last_coords_history' as last_coords_history
    FROM core_npc_master
    WHERE extended_data->>'background_life_enabled' = 'true'
    order by npc_name asc
";

    $result = pg_query($adminConn, $query);

    // Generate random colors for markers
    function generateRandomColor($seed)
    {
        // Create a hash from the seed string
        $hash = crc32($seed);
        
        // Use the hash to generate a consistent color
        return sprintf('#%06X', abs($hash) % 0xFFFFFF);
    }

    // Build markers array from database results
    $markers = [];
    if ($result) {
        while ($row = pg_fetch_assoc($result)) {
            // Parse last_coords (assuming format like "x,y" or JSON)
            $coords = $row['last_coords'];

            // Try JSON format
            $coordsData = json_decode("{$coords}", true);
            if ($coordsData && isset($coordsData[0]) && isset($coordsData[1])) {
                $x = $coordsData[0];
                $y = $coordsData[1];
                $z = $coordsData[2];
            } else {
                error_log("[MAP] Skipping {$row["npc_name"]} {$coords}" . print_r($coordsData, true));
                //continue; // Skip invalid coordinates
                $x=$WORLD_X_MIN;
                $y=$WORLD_Y_MIN;
                $coordsData[3].=" missing coords";
            }

            $meta      = json_decode($row['metadata'], true);
            
            // Parse history coordinates
            $coordsHistory = [];
            if (!empty($row['last_coords_history'])) {
                $historyData = json_decode($row['last_coords_history'], true);
                if (is_array($historyData)) {
                    foreach ($historyData as $historicalCoord) {
      
                        if (isset($historicalCoord[0]) && isset($historicalCoord[1])) {
                            if (($last_gamets-$historicalCoord['last_updated'])*0.0000024 < 24) { // Only if last coord is recent
                                $coordsHistory[] = [
                                    'x' => (int) $historicalCoord[0],
                                    'y' => (int) $historicalCoord[1],
                                    'z' => isset($historicalCoord[2]) ? (int) $historicalCoord[2] : 0,
                                    'location' => isset($historicalCoord[3]) ? $historicalCoord[3] : '',
                                    'last_updated' => isset($historicalCoord['last_updated']) ? $historicalCoord['last_updated'] : null,
                                ];
                            }
                        }
                    }
                }
            }
            
            $markers[] = [
                'name'        => $row['npc_name'],
                'ingame_x'    => (int) $x,
                'ingame_y'    => (int) $y,
                'ingame_z'    => (int) $z,
                'color'       => generateRandomColor($row['npc_name']),
                'size'        => 10,
                'tag'         => $coordsData[3],
                'figure'      => isset($meta["portrait"]) ? $meta["portrait"] : null,
                'id'          => $row["id"],
                'refid'       => $row["refid"],
                'last_pos_ts' => $coordsData["last_updated"]?convert_gamets2skyrim_date($coordsData["last_updated"]).",hours ago:".round(($last_gamets-$coordsData["last_updated"]) *0.0000024,0):null,
                'last_report' => convert_gamets2skyrim_date($row["last_report"]).",hours ago:".round(($last_gamets-$row["last_report"]) *0.0000024,0),
                'coords_history' => $coordsHistory,
            ];

        }
    }

    // Translate all marker coordinates
    $translatedMarkers = [];
    $locationMap       = []; // Track markers at each location

    foreach ($markers as $marker) {
        $coords = translateCoords(
            $marker['ingame_x'],
            $marker['ingame_y'],
            $mapWidth,
            $mapHeight,
            $WORLD_X_MIN,
            $WORLD_X_MAX,
            $WORLD_Y_MIN,
            $WORLD_Y_MAX
        );

        // Translate history coordinates
        $translatedHistory = [];
        foreach ($marker['coords_history'] as $histCoord) {
            $histTranslated = translateCoords(
                $histCoord['x'],
                $histCoord['y'],
                $mapWidth,
                $mapHeight,
                $WORLD_X_MIN,
                $WORLD_X_MAX,
                $WORLD_Y_MIN,
                $WORLD_Y_MAX
            );
            $translatedHistory[] = [
                'x' => $histTranslated['x'],
                'y' => $histTranslated['y'],
                'ingame_x' => $histCoord['x'],
                'ingame_y' => $histCoord['y'],
                'location' => $histCoord['location'],
                'last_updated' => $histCoord['last_updated'] ? convert_gamets2skyrim_date($histCoord['last_updated']) . ", " . round(($last_gamets - $histCoord['last_updated']) * 0.0000024, 0) . " hours ago" : null,
            ];
        }

        $translatedMarkers[] = [
            'name'     => $marker['name'],
            'x'        => $coords['x'],
            'y'        => $coords['y'],
            'color'    => $marker['color'],
            'size'     => $marker['size'],
            'ingame_x' => $marker['ingame_x'],
            'ingame_z' => $marker['ingame_z'],
            'ingame_y' => $marker['ingame_y'],
            'tag'      => $marker['tag'],
            'figure'   => $marker["figure"] ? "../data/pictures/{$marker["figure"]}" : "images/races/default.png",
            'id'          => $marker['id'],
            'refid'       => $marker['refid'],
            'last_pos_ts' => $marker["last_pos_ts"],
            'last_report' => $marker["last_report"],
            'coords_history' => $translatedHistory,
        ];
    }

    // Apply grid offset to markers at the same location
    $locationKey = [];
    foreach ($translatedMarkers as $n => $marker) {
        $key = $translatedMarkers[$n]['x'] . ',' . $translatedMarkers[$n]['y'];

        if (! isset($locationKey[$key])) {
            $locationKey[$key] = 0;
        } else {
            $locationKey[$key]++;
        }

        // Calculate grid position (3 columns, rows as needed)
        $cols = 3;
        $row  = intdiv($locationKey[$key], $cols);
        $col  = $locationKey[$key] % $cols;

        // Apply offset (15px spacing)
        $offsetX = ($col - 1) * 15;
        $offsetY = ($row) * 15;

        $translatedMarkers[$n]['offset_x'] = $offsetX;
        $translatedMarkers[$n]['offset_y'] = $offsetY;
    }
    unset($marker);

    // Load passive location markers from JSON file
    $locationMarkersFile = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'location_markers.json';
    $passiveMarkers = [];
    
    if (file_exists($locationMarkersFile)) {
        $locationData = json_decode(file_get_contents($locationMarkersFile), true);
        
        if ($locationData && isset($locationData['locations'])) {
            foreach ($locationData['locations'] as $location) {
                $coords = translateCoords(
                    $location['coords']['x'],
                    $location['coords']['y'],
                    $mapWidth,
                    $mapHeight,
                    $WORLD_X_MIN,
                    $WORLD_X_MAX,
                    $WORLD_Y_MIN,
                    $WORLD_Y_MAX
                );
                
                $passiveMarkers[] = [
                    'name' => $location['name'],
                    'x' => $coords['x'],
                    'y' => $coords['y'],
                    'ingame_x' => $location['coords']['x'],
                    'ingame_y' => $location['coords']['y'],
                    'icon' => $location['icon'],
                    'type' => $location['type'],
                    'description' => $location['description'],
                    'editorID' => $location['editorID'],
                    'formID' => $location['formID'],
                    'locationID' => $location['locationID'],
                ];
            }
        }
    }

?>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
    /* Override main container styles */
    main {
        padding-top: 10px;
        padding-bottom: 20px;
        padding-left: 5%;
        padding-right: 5%;
        width: 100%;
        margin: 0;
    }
    
    /* Override footer styles */
    footer {
        position: fixed;
        bottom: 0;
        width: 100%;
        height: 20px;
        background: #031633;
        z-index: 100;
    }

    /* MagicCards font import */
    @font-face {
        font-family: 'MagicCards';
        src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
    }

    /* Header Styling */
    .page-header {
        text-align: center;
        margin-bottom: 30px;
        padding: 20px;
        background: #2a2a2a;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
    }

    .page-header h1 {
        margin: 0;
        font-family: 'MagicCards', serif;
        word-spacing: 8px;
        font-size: 2.2em;
        color: rgb(242, 124, 17);
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
    }

    .container {
        max-width: 100%;
        margin: 0 auto;
    }

    .map-container {
        position: relative;
        display: inline-block;
        background: #1a1a1a;
        padding: 15px;
        border: 3px solid rgb(242, 124, 17);
        box-shadow: 0 0 20px rgba(242, 124, 17, 0.3);
        margin: 20px auto;
        width: 75%;
        box-sizing: border-box;
        border-radius: 8px;
    }

    .map-container img {
        display: block;
        width: 100%;
        height: auto;
        border: 1px solid #4a4a4a;
        border-radius: 4px;
    }

    .marker {
        position: absolute;
        transform: translate(-50%, -50%);
        cursor: pointer;
        z-index:10;
    }

    .marker-dot {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        border: 2px solid white;
        box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
        align-items: center;
        justify-content: center;
        z-index: 10;
        position: relative;
    }

    .history-marker {
        position: absolute;
        transform: translate(-50%, -50%);
        border-radius: 50%;
        border: 1px solid rgba(255, 255, 255, 0.6);
        box-shadow: 0 0 5px rgba(255, 255, 255, 0.3);
        z-index: 5;
        opacity: 0.7;
        transition: opacity 0.2s ease;
    }

    .history-marker:hover {
        opacity: 1;
        box-shadow: 0 0 10px rgba(255, 255, 255, 0.6);
        z-index: 15;
    }

    .history-marker-label {
        position: absolute;
        background: rgba(0, 0, 0, 0.9);
        color: white;
        padding: 6px 10px;
        border-radius: 4px;
        white-space: nowrap;
        font-size: 12px;
        top: 12px;
        left: 50%;
        transform: translateX(-50%);
        margin-top: 3px;
        border: 1px solid rgba(255, 255, 255, 0.4);
        display: none;
        z-index: 20;
        box-shadow: 0 2px 6px rgba(0,0,0,0.4);
    }

    .history-marker:hover .history-marker-label {
        display: block;
    }

    .marker-label {
        position: absolute;
        background: rgba(0, 0, 0, 0.9);
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        white-space: nowrap;
        font-size: 14px;
        top: 15px;
        left: 50%;
        transform: translateX(-50%);
        margin-top: 5px;
        border: 2px solid rgb(242, 124, 17);
        display: none;
        z-index: 20;
        box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    }

    .marker:hover {
        z-index: 200;
    }

    .marker:hover .marker-label {
        display: block;
    }

    .info-panel {
        background: #2a2a2a;
        padding: 20px;
        border-left: 4px solid rgb(242, 124, 17);
        margin: 20px 0;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
    }

    .info-panel h3 {
        color: rgb(242, 124, 17);
        font-family: 'MagicCards', serif;
        margin-top: 0;
        word-spacing: 6px;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
    }

    .info-panel strong {
        color: rgb(242, 124, 17);
    }

    .marker-list {
        display: flex;
        gap: 15px;
        margin-top: 20px;
        justify-content: space-around;
        align-content: center;
        align-items: baseline;
        justify-items: stretch;
        flex-direction: row-reverse;
        flex-wrap: wrap;
    }

    .marker-item {
        background: #2a2a2a;
        padding: 15px;
        border-left: 4px solid;
        border-radius: 8px;
        background-size: contain;
        background-repeat: no-repeat;
        background-position-x: right;
        border: 1px solid #4a4a4a;
    }

    .marker-item-color {
        display: inline-block;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        vertical-align: middle;
        margin-right: 10px;
        border: 2px solid white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }

    .marker-item h4 {
        margin: 8px 0;
        color: rgb(242, 124, 17);
        font-family: 'MagicCards', serif;
        word-spacing: 4px;
    }

    .marker-item-coords {
        font-size: 12px;
        color: #bbb;
        margin: 5px 0;
    }

    .marker-item-coords ul {
        padding-left: 15px;
    }

    .marker-item-coords li {
        margin: 3px 0;
    }

    #mapImage {
        opacity: 1;
    }

    .marker-item a {
        color: white;
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .marker-item a:hover {
        color: rgb(242, 124, 17);
    }

    .map-controls {
        text-align: center;
        margin: 15px 0;
        padding: 15px;
        background: #2a2a2a;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
    }

    .map-controls button {
        background: rgb(242, 124, 17);
        color: #000;
        border: none;
        padding: 10px 20px;
        margin: 5px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: bold;
        font-size: 14px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }

    .map-controls button:hover {
        background: rgb(255, 140, 30);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(242, 124, 17, 0.4);
    }

    .map-controls button:active {
        transform: translateY(0);
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }

    .map-controls span {
        color: rgb(242, 124, 17);
        margin: 0 15px;
        font-weight: bold;
        font-size: 16px;
    }

    img.thumb {
        max-width: 100px;
        border-radius: 4px;
        margin-top: 5px;
    }

    .marker-action-btn {
        background: rgb(242, 124, 17);
        color: #000;
        border: none;
        padding: 8px 14px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
        font-size: 12px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }

    .marker-action-btn:hover {
        background: rgb(255, 140, 30);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(242, 124, 17, 0.4);
    }

    .marker-action-btn:active {
        transform: translateY(0);
    }

    /* Passive Location Markers */
    .location-marker {
        position: absolute;
        transform: translate(-50%, -50%);
        cursor: pointer;
        z-index: 5;
    }

    .location-marker-icon {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0.8;
        transition: all 0.3s ease;
    }

    .location-marker-icon img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.5));
    }

    .location-marker:hover .location-marker-icon {
        opacity: 1;
        transform: scale(1.3);
    }

    .location-marker:hover .location-marker-icon img {
        filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.8));
    }

    .location-marker-label {
        position: absolute;
        background: rgba(0, 0, 0, 0.9);
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        white-space: nowrap;
        font-size: 14px;
        top: 15px;
        left: 50%;
        transform: translateX(-50%);
        margin-top: 5px;
        border: 2px solid rgb(242, 124, 17);
        display: none;
        z-index: 30;
        box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    }

    .location-marker:hover {
        z-index: 250;
    }

    .location-marker:hover .location-marker-label {
        display: block;
    }

    .location-marker-label .location-name {
        font-weight: bold;
        font-size: 14px;
        margin-bottom: 5px;
        color: rgb(242, 124, 17);
    }

    .location-marker-label .location-desc {
        font-size: 11px;
        color: #bbb;
        font-style: italic;
        margin-top: 5px;
    }

    .location-marker-label .location-coords {
        font-size: 10px;
        color: #888;
        margin-top: 6px;
        border-top: 1px solid #444;
        padding-top: 5px;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        main {
            padding-left: 5%;
            padding-right: 5%;
        }

        .map-container {
            width: 95%;
        }

        .page-header h1 {
            font-size: 1.5em;
        }
    }
</style>

<main>
    <div class="page-header">
        <h1>🗺️ Background Life - Command Center</h1>
    </div>
    <div class="container">
        
        <div class="map-container" >
            <img src="<?php echo $mapImageUrl; ?>" alt="Skyrim Map" id="mapImage">

            <?php
                // Render NPC markers
                foreach ($translatedMarkers as $marker) {
                    // Calculate position as percentage for responsive scaling
                    $percentX = ($marker['x'] / $mapWidth) * 100;
                    $percentY = ($marker['y'] / $mapHeight) * 100;
                    // Apply grid offset
                    $offsetX = $marker['offset_x'];
                    $offsetY = $marker['offset_y'];

                    echo '<div class="marker" style="left: ' . $percentX . '%; top: ' . $percentY . '%; transform: translate(calc(-50% + ' . $offsetX . 'px), calc(-50% + ' . $offsetY . 'px));">';
                    echo '<div class="marker-dot" id="mkr_' . $marker['id'] . '" style="width: ' . ($marker['size'] * 2) . 'px; height: ' . ($marker['size'] * 2) . 'px; background-color: ' . $marker['color'] . '; opacity: 0.8;">';
                    echo '</div>';
                    echo '<div class="marker-label">' . PHP_EOL;
                    echo "<a style='color:white;text-decoration:none' href='#dtl_{$marker["id"]}'>{$marker["name"]} &nbsp; ↗️</a></br>";
                    echo '<small>(' . $marker['x'] . ', ' . $marker['y'] . '),' . $marker['tag'] . '</small>';
                    echo '<img class="thumb" src="' . $marker['figure'] . '" />';
                    echo '<br/><small>Last reported:' . $marker['last_report'] . '</small>';
                    echo '<br/><small>Last tracked:' . $marker['last_pos_ts'] . '</small>';
                    echo '</div>';
                    echo '</div>' . PHP_EOL;
                    
                    // Render history markers
                    if (!empty($marker['coords_history'])) {
                        $size_modifier=2;
                        foreach ($marker['coords_history'] as $index => $histCoord) {
                            if ($histCoord['x']== $marker['x'] && $histCoord['y']== $marker['y'] )
                                continue; // Skip same place
                            
                            $histPercentX = ($histCoord['x'] / $mapWidth) * 100;
                            $histPercentY = ($histCoord['y'] / $mapHeight) * 100;
                            // Make history markers smaller: 5px radius instead of 10px
                            $size_modifier+=0.5;
                            $histSize = round($size_modifier,0);

                            
                            echo '<div class="history-marker" style="left: ' . $histPercentX . '%; top: ' . $histPercentY . '%; width: ' . ($histSize * 2) . 'px; height: ' . ($histSize * 2) . 'px; background-color: ' . $marker['color'] . '; opacity: 0.3;">';
                            echo '<div class="history-marker-label">' . PHP_EOL;
                            echo "<strong>" . $marker['name'] . "</strong><br/>";
                            echo "In-game: (" . $histCoord['ingame_x'] . ", " . $histCoord['ingame_y'] . ")<br/>";
                            if (!empty($histCoord['location'])) {
                                echo "Location: " . $histCoord['location'] . "<br/>";
                            }
                            echo "Tracked: " . $histCoord['last_updated'] . "<br/>";
                            echo "</div>";
                            echo '</div>' . PHP_EOL;
                        }
                    }
                }

                // Render passive location markers
                foreach ($passiveMarkers as $location) {
                    $percentX = ($location['x'] / $mapWidth) * 100;
                    $percentY = ($location['y'] / $mapHeight) * 100;
                    $iconPath = $webRoot . '/ui/images/map icons/' . $location['icon'];

                    echo '<div class="location-marker" style="left: ' . $percentX . '%; top: ' . $percentY . '%;">';
                    echo '<div class="location-marker-icon">';
                    echo '<img src="' . htmlspecialchars($iconPath) . '" alt="' . htmlspecialchars($location['name']) . '" />';
                    echo '</div>';
                    echo '<div class="location-marker-label">';
                    echo '<div class="location-name">' . htmlspecialchars($location['name']) . '</div>';
                    echo '<div class="location-desc">' . htmlspecialchars($location['description']) . '</div>';
                    echo '<div class="location-coords">';
                    echo 'Type: ' . htmlspecialchars($location['type']) . '<br/>';
                    echo 'Coords: ' . $location['ingame_x'] . ', ' . $location['ingame_y'] . '<br/>';
                    echo 'FormID: ' . htmlspecialchars($location['formID']);
                    echo '</div>';
                    echo '</div>';
                    echo '</div>' . PHP_EOL;
                }
            ?>
        </div>
        <div style="width:20%;float:right">
            <div class="info-panel">
                <h3>⚔ Map Info ⚔</h3>
                <strong>Dimensions:</strong> <?php echo $mapWidth; ?>×<?php echo $mapHeight; ?> pixels<br>
                <strong>NPC Markers:</strong> <?php echo sizeof($translatedMarkers); ?><br>
                <strong>Location Markers:</strong> <?php echo sizeof($passiveMarkers); ?><br>
                <strong>Current Date:</strong> <?php echo $currentDate?><br/>
            </div>

            <div class="map-controls">
                <button onclick="zoomMap(0.8)">🔍− Shrink</button>
                <button onclick="zoomMap(1)">⟲ Reset</button>
                <button onclick="zoomMap(1.2)">🔍+ Expand</button>
                <span id="zoomLevel">100%</span>
                <button onclick="requestUpdate()" style="margin-left: 20px;">📤 Request Update</button>
            </div>
        </div>
        <br break="all"/>
        <div class="info-panel">
            <h3>📍 NPC Markers</h3>
            <div class="marker-list">
                <?php foreach ($translatedMarkers as $marker) {?>
                    <div id="dtl_<?php echo $marker['id'] ?>" class="marker-item" style="background-blend-mode: soft-light;border-left-color:<?php echo $marker['color']; ?>;background-image:url(<?php echo $marker['figure']; ?>)" >
                        <h4>
                            <span class="marker-item-color" style="background-color:                                                                                                                                                                         <?php echo $marker['color']; ?>;"></span>
                            <a href="#mkr_<?php echo $marker['id'] ?>"><?php echo $marker['name']; ?> &nbsp; ↗️</a>
                        </h4>
                        <div class="marker-item-coords">
                            <ul>
                            <li><strong>In-game:</strong> x=<?php echo $marker['ingame_x']; ?>, y=<?php echo $marker['ingame_y']; ?>, z=<?php echo $marker['ingame_z']; ?></li>
                            <li><strong>Map:</strong> (<?php echo $marker['x']; ?>,<?php echo $marker['y']; ?>)</li>
                            <li><strong>RefId:</strong> (<?php echo $marker['refid']; ?>)</li>
                            <li><strong>Last Pos Ts.:</strong> (<?php echo $marker['last_pos_ts']; ?>)</li>
                            <li><strong>Last reported:</strong> (<?php echo $marker['last_report']; ?>)</li>
                            </ul>
                        </div>
                        <div style="margin-top: 10px; display: flex; gap: 5px; flex-wrap: wrap;">
                            <button onclick="requestAction('<?php echo addslashes($marker['name']); ?>')" class="marker-action-btn">🎬 Request Action</button>
                            <button onclick="requestReporting('<?php echo addslashes($marker['name']); ?>')" class="marker-action-btn" style="background: #4488ff;">📋 Request Reporting</button>
                            <button onclick="updateCoords('<?php echo addslashes($marker['name']); ?>')" class="marker-action-btn" style="background: #44ff44;">📍 Update Coords</button>
                        </div>
                    </div>
                <?php }?>
            </div>
        </div>
    </div>

    <script>
        let currentScale = 1;

        function zoomMap(scale) {
            if (scale === 1) {
                currentScale = 1;
            } else {
                currentScale *= scale;
            }

            // Clamp scale between 0.5 and 2.5
            currentScale = Math.max(0.5, Math.min(2.5, currentScale));

            const mapContainer = document.querySelector('.map-container');
            const zoomDisplay = document.getElementById('zoomLevel');

            // Update width based on scale
            mapContainer.style.maxWidth = (100 * currentScale) + '%';
            mapContainer.style.margin = '20px auto';

            // Update zoom display
            zoomDisplay.textContent = Math.round(currentScale * 100) + '%';
        }

        function requestUpdate() {
            const formData = new FormData();
            formData.append('action', 'request_update');
            showProcessing()
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.ok) {
                    alert('Update request sent!');
                    // Optionally reload the page
                    // location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
                hideProcessing()
            })
            .catch(error => {
                console.error('Error:', error);
                hideProcessing()
                alert('Request failed');
            });
        }

        function requestAction(npcName) {
            const formData = new FormData();
            formData.append('action', 'request_action');
            formData.append('npc_name', npcName);
            showProcessing()

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.ok) {
                    alert(data.message || 'Action request sent!');
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
                hideProcessing()
            })
            .catch(error => {
                console.error('Error:', error);
                hideProcessing()
                alert('Request failed');
            });
        }

        function requestReporting(npcName) {
            const formData = new FormData();
            formData.append('action', 'request_reporting');
            formData.append('npc_name', npcName);
            showProcessing()
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.ok) {
                    alert(data.message || 'Reporting request sent!');
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
                hideProcessing();
            })
            .catch(error => {
                console.error('Error:', error);
                hideProcessing();
                alert('Request failed');
            });
        }

        function updateCoords(npcName) {
            const formData = new FormData();
            formData.append('action', 'update_coords');
            formData.append('npc_name', npcName);
            showProcessing()
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.ok) {
                    alert(data.message || 'Coords update sent!');
                    // Reload the page after a short delay to see updates
                    setTimeout(() => {
                        location.reload();
                    }, 200);
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
                hideProcessing();
             
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Request failed');
                hideProcessing();
            });
        }
        function showProcessing()
        {

            processingMessage                           = document . createElement('div');
            processingMessage . textContent             = 'Processing...';
            processingMessage . style . position        = 'fixed';
            processingMessage . style . top             = '50%';
            processingMessage . style . left            = '50%';
            processingMessage . style . transform       = 'translate(-50%, -50%)';
            processingMessage . style . backgroundColor = '#000';
            processingMessage . style . color           = '#fff';
            processingMessage . style . padding         = '10px 20px';
            processingMessage . style . borderRadius    = '8px';
            processingMessage . style . zIndex          = '10001';
            processingMessage . id                      = "processing_wheel";
            document . body . appendChild(processingMessage);
        }
        function hideProcessing()
        {
            processingMessage . innerHTML      = '';
            processingMessage . style . zIndex = '-10001';

        }

    var processingMessage;
    </script>
</main>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
