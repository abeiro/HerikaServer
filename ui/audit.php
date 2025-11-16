<?php 

$enginePath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "utils_game_timestamp.php");
require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
if ($uiPos !== false) { $webRoot = substr($scriptPath, 0, $uiPos); } else { $webRoot = ''; }
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

$host = 'localhost';
$port = '5432';
$dbname = 'dwemer';
$schema = 'public';
$username = 'dwemer';
$password = 'dwemer';

$adminConn = @pg_connect("host={$host} port={$port} dbname={$dbname} user={$username} password={$password}");
if (!$adminConn) {
    echo json_encode([ 'ok'=>false ]);
    exit;
}

// --- Determine filter type and dates ---
$filter_type = isset($_GET['filter']) ? $_GET['filter'] : 'today';
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selected_week = isset($_GET['week']) ? $_GET['week'] : date('Y-\WW');

$where_clause = '';

if ($filter_type === 'date' && !empty($selected_date)) {
    // Filter by specific date
    $where_clause = "WHERE DATE(created_at) = '" . pg_escape_string($selected_date) . "'";
    $period_label = "Date: " . $selected_date;
} elseif ($filter_type === 'week' && !empty($selected_week)) {
    // Filter by week (ISO week)
    $where_clause = "WHERE TO_CHAR(created_at, 'YYYY-\"W\"WW') = '" . pg_escape_string($selected_week) . "'";
    $period_label = "Week: " . $selected_week;
} else {
    // Default: today
    $where_clause = "WHERE DATE(created_at) = CURRENT_DATE";
    $period_label = "Date: Today (" . date('Y-m-d') . ")";
    $filter_type = 'today';
}

// --- Query total cost grouped by request type ---
$query = "
    SELECT
        split_part(connector, '/', 2) AS type_of_request,
        SUM((usage->>'cost')::numeric) AS total_cost
    FROM audit_request
    $where_clause
    GROUP BY split_part(connector, '/', 2)
    ORDER BY total_cost DESC
";

$result = pg_query($adminConn, $query);

$labels = [];
$values = [];
$total_sum = 0;

if ($result) {
    while ($row = pg_fetch_assoc($result)) {
        $labels[] = $row['type_of_request'];
        $values[] = (float)$row['total_cost'];
        $total_sum += (float)$row['total_cost'];
    }
}

pg_close($adminConn);

$TITLE = "💰 Audit Log - Cost Distribution";
$isEmbed = (isset($_GET['embed']) && $_GET['embed'] == '1');

ob_start();
include(__DIR__.DIRECTORY_SEPARATOR."tmpl".DIRECTORY_SEPARATOR."head.html");
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
    @font-face {
        font-family: 'MagicCards';
        src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
    }

    main {
        padding-top: 20px;
        padding-bottom: 40px;
        padding-left: 5%;
        padding-right: 5%;
        width: 100%;
        margin: 0;
    }

    .page-header {
        text-align: center;
        margin-bottom: 30px;
        padding: 20px;
        background: #2a2a2a;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
    }

    .page-header h1 {
        margin-bottom: 10px;
        font-family: 'MagicCards', serif;
        word-spacing: 8px;
        font-size: 2.2em;
        color: rgb(242, 124, 17);
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
    }

    .page-header h3 {
        color: rgb(242, 124, 17);
        margin: 10px 0;
        font-size: 1.2em;
    }

    .filters {
        margin-bottom: 30px;
        padding: 25px;
        background: #2a2a2a;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
        box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    }

    .filters form {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        justify-content: center;
        align-items: center;
    }

    .filters label {
        display: flex;
        align-items: center;
        gap: 8px;
        white-space: nowrap;
        color: #f8f9fa;
        font-weight: 500;
    }

    .filters input[type="radio"] {
        cursor: pointer;
    }

    .filters input[type="date"],
    .filters input[type="week"] {
        padding: 8px 12px;
        border: 1px solid #4a4a4a;
        border-radius: 4px;
        font-size: 14px;
        background: #3a3a3a;
        color: #f8f9fa;
    }

    .filters button {
        padding: 8px 16px;
        background: rgb(242, 124, 17);
        color: #000;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .filters button:hover {
        background: rgb(255, 140, 30);
        box-shadow: 0 2px 8px rgba(242, 124, 17, 0.4);
    }

    .chart-section {
        background: #2a2a2a;
        padding: 30px;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
        box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    }

    .chart-container {
        position: relative;
        width: 100%;
        max-width: 700px;
        height: 700px;
        margin: 0 auto;
    }

    canvas {
        width: 100% !important;
        height: 100% !important;
    }

    @media (max-width: 768px) {
        main {
            padding-left: 3%;
            padding-right: 3%;
        }

        .page-header h1 {
            font-size: 1.6em;
        }

        .chart-container {
            height: 500px;
        }

        .filters form {
            flex-direction: column;
        }
    }
</style>

<main>
    <div class="page-header">
        <h1>💰 Cost Distribution by Request Type</h1>
        <h3><?php echo $period_label; ?></h3>
        <h3>Total Cost: $<?php echo number_format($total_sum, 2); ?></h3>
    </div>

    <div class="filters">
        <form method="GET" action="" id="filterForm">
            <label>
                <input type="radio" name="filter" value="today" <?php echo $filter_type === 'today' ? 'checked' : ''; ?>> 
                Today
            </label>
            
            <label>
                Filter by Date:
                <input type="date" name="date" value="<?php echo $selected_date; ?>" id="dateInput">
                <button type="button" onclick="setFilterToDate()">Apply Date</button>
            </label>
            
            <label>
                Filter by Week:
                <input type="week" name="week" value="<?php echo $selected_week; ?>" id="weekInput">
                <button type="button" onclick="setFilterToWeek()">Apply Week</button>
            </label>
        </form>
    </div>

    <div class="chart-section">
        <div class="chart-container">
            <canvas id="costChart"></canvas>
        </div>
    </div>

    <script>
        function setFilterToDate() {
            const dateInput = document.getElementById('dateInput').value;
            if (dateInput) {
                window.location.href = '?filter=date&date=' + encodeURIComponent(dateInput);
            }
        }

        function setFilterToWeek() {
            const weekInput = document.getElementById('weekInput').value;
            if (weekInput) {
                window.location.href = '?filter=week&week=' + encodeURIComponent(weekInput);
            }
        }

        // Handle radio button for "Today"
        document.querySelectorAll('input[name="filter"]').forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'today') {
                    window.location.href = '?filter=today';
                }
            });
        });

        const labels = <?php echo json_encode($labels); ?>;
        const dataValues = <?php echo json_encode($values, JSON_NUMERIC_CHECK); ?>;
        const labelWithCost = labels.map((label, i) => `${label} ($${dataValues[i].toFixed(2)})`);

        const ctx = document.getElementById('costChart').getContext('2d');
        const costChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labelWithCost,
                datasets: [{
                    label: 'Total Cost',
                    data: dataValues,
                    backgroundColor: [
                        'rgb(242, 124, 17)', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                        '#ec4899', '#14b8a6', '#6366f1', '#f97316', '#22c55e'
                    ],
                    borderColor: '#2a2a2a',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 20,
                            padding: 15,
                            color: '#f8f9fa',
                            font: {
                                size: 13
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#2a2a2a',
                        titleColor: 'rgb(242, 124, 17)',
                        bodyColor: '#f8f9fa',
                        borderColor: '#4a4a4a',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                return `${context.label}: $${context.parsed.toFixed(2)}`;
                            }
                        }
                    }
                }
            }
        });
    </script>
</main>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl".DIRECTORY_SEPARATOR."footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
