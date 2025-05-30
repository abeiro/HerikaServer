<?php
//error_reporting(0);
require_once("/var/www/html/HerikaServer/lib/logger.php");
require_once("/var/www/html/HerikaServer/lib/postgresql.class.php");
require_once("/var/www/html/HerikaServer/lib/misc_ui_functions.php");

echo "<style>
    /* Table Container Styles */
    .table-container {
        background-color: #2a2a2a;
        border-radius: 5px;
        padding: 15px;
        margin-bottom: 20px;
        overflow-x: auto;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    /* Table Styles */
    table {
        width: 100%;
        border-collapse: collapse;
        background-color: #3a3a3a;
        margin-bottom: 20px;
        font-size: small;
    }

    /* Header Cells */
    th {
        background-color: #1a1a1a;
        color: #fff;
        font-weight: bold;
        padding: 12px;
        text-align: left;
        border-bottom: 2px solid #444;
    }

    /* Data Cells */
    td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid #444;
        color: #f8f9fa;
    }

    /* Row Alternating Colors */
    tr:nth-child(even) {
        background-color:rgb(77, 77, 77);
    }

    /* Button Cell Alignment */
    td:has(button), td:has(.btn-base) {
        text-align: center;
    }

    /* Responsive Table */
    @media (max-width: 768px) {
        .table-container {
            margin: 10px -15px;
            border-radius: 0;
        }
        
        table {
            font-size: smaller;
        }
        
        th, td {
            padding: 8px;
        }
    }
</style>";

$db = new sql();

$b_res = true;
$response = "";

try {
	$s_res = $db->execQueryVerbose("VACUUM FULL ANALYZE");
	if ($s_res > "") {
		$b_res = false;
		$err = $s_res; //$db->GetLastError();
		$response = "<font color=\"red\">Operation failed.</font><br/>{$err}";
		Logger::warn("Database vacuum complete: Operation failed. {$err}");
	} else {
		$response = "<font color=\"green\">Success.</font> All tables are optimized.";
		Logger::debug(" Database vacuum complete: All tables are optimized.");
	}
} catch (Exception $e) {
	$b_res = false;
	$err = $db->GetLastError() . " " . $e->getMessage();
	$response = "<font color=\"red\">Operation failed.<br/>{$err}</font>";
	Logger::warn("Database vacuum complete: Operation failed. Exception raised {$err}");
} finally {
	echo "<h3>Maintenance operation complete.</h3>";
	echo "<p><strong>Result:</strong> {$response} </p>";
}

if ($b_res) {

	$sql_info = "
	WITH rel_set AS
	(   SELECT
			oid,
			CASE split_part(split_part(array_to_string(reloptions, ','), 'autovacuum_vacuum_threshold=', 2), ',', 1)
				WHEN '' THEN NULL
			ELSE split_part(split_part(array_to_string(reloptions, ','), 'autovacuum_vacuum_threshold=', 2), ',', 1)::BIGINT
			END AS rel_av_vac_threshold,
			CASE split_part(split_part(array_to_string(reloptions, ','), 'autovacuum_vacuum_scale_factor=', 2), ',', 1)
				WHEN '' THEN NULL
			ELSE split_part(split_part(array_to_string(reloptions, ','), 'autovacuum_vacuum_scale_factor=', 2), ',', 1)::NUMERIC
			END AS rel_av_vac_scale_factor
		FROM pg_class
	) 
	SELECT
		PSUT.relname AS table_name,
		to_char(PSUT.last_vacuum, 'YYYY-MM-DD HH24:MI')     AS last_vacuum,
		to_char(PSUT.last_autovacuum, 'YYYY-MM-DD HH24:MI') AS last_autovacuum,
		to_char(C.reltuples, '9G999G999G999')               AS n_tup,
		to_char(PSUT.n_dead_tup, '9G999G999G999')           AS dead_tup,
		to_char(coalesce(RS.rel_av_vac_threshold, current_setting('autovacuum_vacuum_threshold')::BIGINT) + coalesce(RS.rel_av_vac_scale_factor, current_setting('autovacuum_vacuum_scale_factor')::NUMERIC) * C.reltuples, '9G999G999G999') AS av_threshold,
		CASE
			WHEN (coalesce(RS.rel_av_vac_threshold, current_setting('autovacuum_vacuum_threshold')::BIGINT) + coalesce(RS.rel_av_vac_scale_factor, current_setting('autovacuum_vacuum_scale_factor')::NUMERIC) * C.reltuples) < PSUT.n_dead_tup
			THEN '*'
		ELSE ''
		END AS expect_av
	FROM
		pg_stat_user_tables PSUT
		JOIN pg_class C
			ON PSUT.relid = C.oid
		JOIN rel_set RS
			ON PSUT.relid = RS.oid
	ORDER BY C.reltuples DESC;
	";

	$arr_res = $db->fetchAll($sql_info);
	echo "<p>Tables:</p>";
	print_array_as_table($arr_res);
	echo "<p><nbsp></p>";
}
$db->close();

?>
