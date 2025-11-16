<?php 

$enginePath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "utils_game_timestamp.php");

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Cost Distribution by Request Type</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body {
      font-family: system-ui, sans-serif;
      background: #f8fafc;
      padding: 40px;
      text-align: center;
    }
    h2 {
      margin-bottom: 20px;
    }
    h3 {
      margin-bottom: 20px;
      color: #374151;
    }
    .filters {
      margin-bottom: 30px;
      padding: 20px;
      background: white;
      border-radius: 8px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      display: inline-block;
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
    }
    .filters input[type="radio"],
    .filters input[type="date"],
    .filters input[type="week"] {
      padding: 6px 8px;
      border: 1px solid #d1d5db;
      border-radius: 4px;
      font-size: 14px;
    }
    .filters button {
      padding: 6px 12px;
      background: #3b82f6;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
    }
    .filters button:hover {
      background: #2563eb;
    }
    .chart-container {
      position: relative;
      width: 90%;
      max-width: 700px;
      height: 700px;
      margin: 0 auto;
    }
    canvas {
      width: 100% !important;
      height: 100% !important;
    }
  </style>
</head>
<body>

  <h2>Cost Distribution by Request Type</h2>
  
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

  <h3><?php echo $period_label; ?></h3>
  <h3>Total Cost: $<?php echo number_format($total_sum, 2); ?></h3>

  <div class="chart-container">
    <canvas id="costChart"></canvas>
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
            '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
            '#ec4899', '#14b8a6', '#6366f1', '#f97316', '#22c55e'
          ],
          borderColor: '#ffffff',
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              boxWidth: 20,
              padding: 15
            }
          },
          tooltip: {
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

</body>
</html>
