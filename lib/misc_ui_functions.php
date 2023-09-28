<?php


function print_array_as_table($data)
{
    // Start the HTML table

    if (sizeof($data) < 1) {
        return;
    }
    echo "<div class='datatable'>";
    echo "<table border='1' width='100%' class='table table-striped table-bordered table-sm'>";


    // Print the first row with array keys
    echo "<tr class='primary'>";
    foreach (array_keys($data[0]) as $key) {
        echo "<th>" . $key . "</th>";
    }
    echo "</tr>";

    // Print the remaining rows with array values
    foreach ($data as $i => $row) {
        $colorClass = "";

        // if we have an "url" column, paint the rows different colors
        if (isset($row["url"])) {
            $colorClasses = ["table-primary", "table-secondary", "table-info", "table-light", "table-dark"];
            $colorIndex = abs(crc32(preg_replace('/in \d+ secs/', '', $row["url"]))) % 5;
            $colorClass = $colorClasses[$colorIndex];
        }

        echo "<tr>";
        foreach ($row as $n => $cell) {
            if ($n == "prompt") {
                /* This is fucking slow
                 * echo "<td class='{$colorClass}'>
                    <span data-bs-toggle='collapse' data-bs-target='.prompt-$i' style='cursor:pointer'>[+]</span>
                    <pre class='collapse prompt-$i'>" . $cell . "</pre>
                </td>";
                */
                echo "<td><span class='foldableCtl' onclick='togglePre(this)' style='cursor:pointer'>[+]</span><pre class='foldable'>" . $cell . "</pre></td>";

            } elseif ($n == "rowid") {
                echo "<td class='$colorClass'>
                    <a class='icon-link' href='cmd/deleteRow.php?table={$_GET["table"]}&rowid=$cell'>
                        " . $cell . "
                        <i class='bi-trash'></i>
                    </a>
                </td>";
            } elseif (strpos($cell, 'background chat') !== false) {
                echo "<td class='$colorClass'><em>" . $cell . "</em></td>";
            } elseif (strpos($cell, $GLOBALS["PLAYER_NAME"] . ':') !== false) {
                echo "<td class='$colorClass'>" . $cell . "</td>";
            } elseif (strpos($cell, 'obtains a quest') !== false) {
                echo "<td class='$colorClass'><strong>" . $cell . "</strong></td>";
            } elseif (strpos($cell, "{$GLOBALS["HERIKA_NAME"]}:") !== false) {
                echo "<td  class='$colorClass'>" . $cell . "</td>";

            } elseif ($n == "cost_USD" || $n == "total_cost_so_far_USD") {
                $formatted_cell = (is_numeric($cell)) ? number_format($cell, 6) : $cell;
                echo "<td class='$colorClass'>" . $formatted_cell . "</td>";
            } elseif ($n == "rowid") {
                echo "<td class='$colorClass'>
                    <a class='icon-link' href='cmd/deleteRow.php?table={$_GET["table"]}&rowid=$cell'>
                        " . $cell . "
                        <i class='bi-trash'></i>
                    </a>
                </td>";

            } else {
                echo "<td class='$colorClass'>" . $cell . "</td>";
            }
        }
        echo "</tr>";
    }

    // End the HTML table
    echo "</table></div>";
}



?>
