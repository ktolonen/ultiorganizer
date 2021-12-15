<?php 
include '../../lib/database.php';

OpenConnection();

$sql="SHOW TABLES";

$tables = DBQueryToArray($sql, false);

echo "<?php\n\n";
foreach ($tables as $table) {
	$next = implode($table);
	echo "\$tables['".$next."'] = array( ";
	$columns = GetTableColumns($next);
	$first = true;
	foreach ($columns as $column => $type) {
		if ($first) {
			$first = false;
		} else {
			echo ", ";
		}
		echo "'".$column."' => '".$type."'";
	}
	echo " );\n";
}

echo "\n\n?>";
?>