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
            $colorClasses = [
                "table-primary-dark",   // Deep blue
                "table-success-dark",   // Deep green
                "table-info-dark",      // Deep cyan
                "table-warning-dark",   // Deep gold
                "table-danger-dark"     // Deep red
            ];
            $colorIndex = abs(crc32(preg_replace('/in \d+ secs/', '', $row["url"]))) % 5;
            $colorClass = $colorClasses[$colorIndex];
        }
        // Add coloring for response log table
        else if (isset($row["response"]) || isset($row["AI Response"])) {
            $colorClasses = [
                "table-primary-dark",   // Deep blue
                "table-info-dark",      // Deep cyan
                "table-success-dark"    // Deep green
            ];
            // Use the rowid or timestamp to determine color
            $seed = $row["rowid"] ?? $row["localts"] ?? $row["LocalTS"] ?? time();
            $colorIndex = abs(crc32((string)$seed)) % 3;
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
                    <a class='icon-link' href='#' 
                       style='color: red !important;' 
                       onclick='deleteRowAndRefresh(\"" . $_GET["table"] . "\", $cell)'>
                        " . $cell . "
                        <i class='bi-trash' style='color: red !important;'></i>
                    </a>
                </td>";
            } elseif ($n == "summary" || $n == "content") {
                echo "<td class='$colorClass'><em>
                    ".nl2br($cell)."</em>

                </td>";
            } elseif ($n == "packed_message") {
                echo "<td class='$colorClass'><p style='max-height:200px;overflow-y:scroll'>
                    ".nl2br($cell)."</p>

                </td>";
            } elseif (stripos($cell, 'background chat') !== false) {
                echo "<td class='$colorClass'><em>" . $cell . "</em></td>";
            } elseif (stripos($cell, ($GLOBALS["PLAYER_NAME"] ?? "_none") . ':') !== false) {
                echo "<td class='$colorClass'>" . $cell . "</td>";
            } elseif (stripos($cell, 'obtains a quest') !== false) {
                echo "<td class='$colorClass'><strong>" . $cell . "</strong></td>";
            } elseif (stripos($cell, ($GLOBALS["HERIKA_NAME"] ?? "_none") . ":") !== false) {
                echo "<td  class='$colorClass'>" . $cell . "</td>";

            } elseif ($n == "cost_USD" || $n == "total_cost_so_far_USD") {
                $formatted_cell = (is_numeric($cell)) ? number_format($cell, 6) : $cell;
                echo "<td class='$colorClass'>" . $formatted_cell . "</td>";
            } elseif ($n == "rowid") {
                echo "<td class='$colorClass'>
                    <a class='icon-link' href='#' 
                       style='color: red !important;' 
                       onclick='deleteRowAndRefresh(\"" . $_GET["table"] . "\", $cell)'>
                        " . $cell . "
                        <i class='bi-trash' style='color: red !important;'></i>
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



// Move below javascript code elsewhere
?>

<script>
function deleteRowAndRefresh(table, rowId) {
  // Make a background (AJAX) request to deleteRow.php
  fetch(`cmd/deleteRow.php?table=${table}&rowid=${rowId}`, {
    method: 'GET' // or 'POST' if your script expects POST
  })
  .then(response => {
    // Check if the request was successful
    if (!response.ok) {
      throw new Error('Network response was not OK, status: ' + response.status);
    }
    // Optionally handle response text/data:
    return response.text();
  })
  .then(data => {
    // Once successful, refresh the page
    window.location.reload();
  })
  .catch(error => {
    // Handle errors here, e.g.:
    console.error('Delete error:', error);
    alert('Failed to delete row!');
  });
}
</script>
