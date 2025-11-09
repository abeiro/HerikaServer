<?php
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

    // Coordinate translation constants (world bounds)
    $WORLD_X_MIN = -225242;
    $WORLD_X_MAX = 217068;
    $WORLD_Y_MIN = -164195;
    $WORLD_Y_MAX = 204675;

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

        // X: map left-right
        $mapX = (($ingameX - $worldXMin) / $worldXRange) * $mapWidth;

        // Y: inverted (north at top, south at bottom)
        $mapY = (1 - (($ingameY - $worldYMin) / $worldYRange)) * $mapHeight;

        // Clamp coordinates to map bounds
        $mapX = max(0, min($mapWidth, $mapX));
        $mapY = max(0, min($mapHeight, $mapY));

        return [
            'x' => round($mapX),
            'y' => round($mapY),
        ];
    }

    // --- Query total cost grouped by request type ---
    $query = "
    SELECT
        npc_name,metadata,id,refid,
        metadata->>'last_coords' as last_coords
    FROM core_npc_master
    WHERE extended_data->>'background_life_enabled' = 'true'
    AND metadata->>'last_coords' IS NOT NULL
";

    $result = pg_query($adminConn, $query);

    // Generate random colors for markers
    function generateRandomColor()
    {
        return sprintf('#%06X', mt_rand(0, 0xFFFFFF));
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
            } else {
                error_log("[MAP] Skipping {$row["npc_name"]} {$coords}" . print_r($coordsData, true));
                continue; // Skip invalid coordinates
            }

            $meta      = json_decode($row['metadata'], true);
            $markers[] = [
                'name'     => $row['npc_name'],
                'ingame_x' => (int) $x,
                'ingame_y' => (int) $y,
                'color'    => generateRandomColor(),
                'size'     => 10,
                'tag'      => $coordsData[3],
                'figure'   => isset($meta["portrait"]) ? $meta["portrait"] : null,
                'id'       => $row["id"],
                'refid'    => $row["refid"],
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

        $translatedMarkers[] = [
            'name'     => $marker['name'],
            'x'        => $coords['x'],
            'y'        => $coords['y'],
            'color'    => $marker['color'],
            'size'     => $marker['size'],
            'ingame_x' => $marker['ingame_x'],
            'ingame_y' => $marker['ingame_y'],
            'tag'      => $marker['tag'],
            'figure'   => $marker["figure"] ? "../data/pictures/{$marker["figure"]}" : "images/races/default.png",
            'id'    => $marker['id'],
            'refid' => $marker['refid'],

        ];
    }

    // Apply grid offset to markers at the same location
    $locationKey = [];
    foreach ($translatedMarkers as $n=>$marker) {
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
    
?>
<!DOCTYPE html>
<html>
<head>
    <title>Skyrim Map Viewer</title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            background: #1a1a1a;
            font-family: 'Arial', sans-serif;
            color: #fff;
        }
        .container {
            max-width: 1600px;
            margin: 0 auto;
        }
        h1 {
            color: #ffcc00;
            text-align: center;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }
        .map-container {
            position: relative;
            display: inline-block;
            background: #111;
            padding: 10px;
            border: 3px solid #ffcc00;
            box-shadow: 0 0 20px rgba(255, 204, 0, 0.3);
            margin: 20px auto;
            width: 100%;
            box-sizing: border-box;
        }
        .map-container img {
            display: block;
            width: 100%;
            height: auto;
            border: 1px solid #666;
        }
        .marker {
            position: absolute;
            transform: translate(-50%, -50%);
            cursor: pointer;
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
            position:relative;
        }
        .marker-label {
            position: absolute;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 3px;
            white-space: nowrap;
            font-size: 22px;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            margin-top: 5px;
            border: 1px solid #ffcc00;
            display: none;
            z-index: 20;
        }
        .marker:hover{
            z-index: 200;
        }
        .marker:hover .marker-label {
            display: block;
        }
        .info-panel {
            background: #222;
            padding: 15px;
            border-left: 4px solid #ffcc00;
            margin: 20px 0;
            border-radius: 4px;
        }
        .marker-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .marker-item {
            background: #333;
            padding: 12px;
            border-left: 4px solid;
            border-radius: 4px;
            background-size: contain;
            background-repeat: no-repeat;
            background-position-x: right;
        }
        .marker-item-color {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            vertical-align: middle;
            margin-right: 10px;
            border: 2px solid white;
        }
        .marker-item h4 {
            margin: 8px 0;
            color: #ffcc00;
        }
        .marker-item-coords {
            font-size: 11px;
            color: #aaa;
            margin: 5px 0;
        }
        #mapImage {
            opacity:1;
        }
        .marker-item a {
            color:white;
            float:none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚔ Skyrim Map - Location Markers ⚔</h1>

        <div class="info-panel">
            <strong>Map Information:</strong><br>
            Dimensions:                        <?php echo $mapWidth; ?>×<?php echo $mapHeight; ?> pixels<br>
            Total Markers:                           <?php echo sizeof($translatedMarkers); ?><br>
            Coordinate System: In-game to Map Translation
        </div>

        <div class="map-container">
            <img src="<?php echo $mapImageUrl; ?>" alt="Skyrim Map" id="mapImage">



            <?php
                

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
                    echo '<div class="marker-label">'.PHP_EOL;
                    echo $marker['name'] . '<br>';
                    echo '<small>(' . $marker['x'] . ', ' . $marker['y'] . '),' . $marker['tag'] . '</small>';
                    echo '<img src="' . $marker['figure'] . '" />';
                    echo '</div>';
                    echo '</div>'.PHP_EOL;
                }
            ?>
        </div>

        <div class="info-panel">
            <strong>📍 Markers:</strong>
            <div class="marker-list">
                <?php foreach ($translatedMarkers as $marker) {?>
                    <div class="marker-item" style="border-left-color:<?php echo $marker['color']; ?>;background-image:url(<?php echo $marker['figure']; ?>)" >
                        <h4>
                            <span class="marker-item-color" style="background-color:                                                                                     <?php echo $marker['color']; ?>;"></span>
                            <a href="#mkr_<?php echo $marker['id'] ?>"><?php echo $marker['name']; ?></a>
                        </h4>
                        <div class="marker-item-coords">
                            <strong>In-game:</strong> x=<?php echo $marker['ingame_x']; ?>, y=<?php echo $marker['ingame_y']; ?><br>
                            <strong>Map:</strong> (<?php echo $marker['x']; ?>,<?php echo $marker['y']; ?>)
                            <strong>RefId:</strong> (<?php echo $marker['refid']; ?>)
                        </div>
                    </div>
                <?php }?>
            </div>
        </div>
    </div>
</body>
</html>
