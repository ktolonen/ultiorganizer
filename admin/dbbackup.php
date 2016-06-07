<?php
include_once 'menufunctions.php';
include_once 'lib/club.functions.php';
include_once 'lib/reservation.functions.php';
$html = "";
if (isset($_POST['backup']) && !empty($_POST['tables']) && isSuperAdmin()){
	$tables = $_POST["tables"];
	$return = "SET NAMES 'utf8';\n\n";
	if(count($tables)==1){
		$filename = 'db-backup-'.date('Y-m-d-Hi').'-'.$tables[0].'.sql';
	}else{
		$filename = 'db-backup-'.date('Y-m-d-Hi').'-'.(md5(implode(',',$tables))).'.sql';
	}
	
	foreach($tables as $table){
		set_time_limit(120);
		$result = mysql_query('SELECT * FROM '.$table);
		$num_fields = mysql_num_fields($result);
		
		$return.= 'DROP TABLE IF EXISTS '.$table.';';
		$row2 = mysql_fetch_row(mysql_query('SHOW CREATE TABLE '.$table));
		$return.= "\n\n".$row2[1].";\n\n";
		
		for ($i = 0; $i < $num_fields; $i++){
			while($row = mysql_fetch_row($result)){
				$return.= 'INSERT INTO '.$table.' VALUES(';
				for($j=0; $j<$num_fields; $j++){
											
					if(mysql_field_type($result,$j)=='blob' && $table=='uo_image'){
						if (isset($row[$j]) && ($row[$j] != NULL)){ 
							$return .= '0x'.bin2hex($row[$j]);
						}else{ 
							$return.= 'NULL'; 
						}
					}elseif(mysql_field_type($result,$j)=='int'){
						if (isset($row[$j]) && ($row[$j] != NULL)){ 
							$return .= intval($row[$j]);
						}else{ 
							$return.= 'NULL'; 
						}
					}else{
						$row[$j] = addslashes($row[$j]);
						$row[$j] = preg_replace("/\n/", "\\n",$row[$j]);
					
						if (isset($row[$j]) && ($row[$j] != NULL)){ 
							$return.= '"'.$row[$j].'"' ; 
						}else{ 
							$return.= 'NULL'; 
						}
					}
					if ($j<($num_fields-1)) { $return.= ','; }
				}
				$return.= ");\n";
			}
		}
		$return.="\n\n\n";
	
	}	
	
   
	$gzipoutput = gzencode($return);
    
	header("Pragma: public");
	header("Expires: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Cache-Control: public"); 
	header("Content-Description: File Transfer");
	header('Content-Type: application/x-download');
    header('Content-Encoding: binary'); 
    header('Content-Length: '.strlen($gzipoutput)); 
	header("Content-Disposition: attachment; filename=$filename.gz;");

	echo $gzipoutput;

}

//common page
$title = _("Database backup");
$LAYOUT_ID = DBBACKUP;
pageTopHeadOpen($title);
include 'script/common.js.inc';
pageTopHeadClose($title, false);
leftMenu($LAYOUT_ID);
contentStart();
if(isSuperAdmin()){
	
	$html .= "<form method='post' id='tables' action='?view=admin/dbbackup'>\n";
	
	$html .= "<p><span class='profileheader'>"._("Select tables to backup").": </span></p>\n";
	$html .= "<table>";
	$html .= "<tr><th><input type='checkbox' onclick='checkAll(\"tables\");'/></th>";
	$html .= "<th>"._("Name")."</th>";
	$html .= "<th>"._("Data")."</th>";
	$html .= "<th>"._("Index")."</th>";
	$html .= "<th>"._("Rows")."</th>";
	$html .= "<th>"._("avg. row length")."</th>";
	$html .= "<th>"._("Auto Increment")."</th>";
	$html .= "<th>"._("Updated")."</th>";
	$html .= "</tr>\n";
	$total_size = 0;
	$result = mysql_query("SHOW TABLE STATUS");
	while($row = mysql_fetch_assoc($result)){
	    if (substr($row['Name'],0,3) == 'uo_'){
    		$html .= "<tr>";
    		$html .= "<td class='center'><input type='checkbox' name='tables[]' value='".utf8entities($row['Name'])."' /></td>";
    		$html .= "<td>". $row['Name'] ."</td>";
    		$html .= "<td>". $row['Data_length'] ."</td>";
    		$html .= "<td>". $row['Index_length'] ."</td>";
    		$html .= "<td>". $row['Rows'] ."</td>";
    		$html .= "<td>". $row['Avg_row_length'] ."</td>";
    		$html .= "<td>". $row['Auto_increment'] ."</td>";
    		$html .= "<td>". $row['Update_time'] ."</td>";
    		$html .= "</tr>\n";
    		$total_size += intval($row['Data_length']) + intval($row['Index_length']);
	    }
	}
	$html .= "</table>";
	$html .= "<p><span class='profileheader'>"._("Database size").": </span>".$total_size." "._("bytes")."</p>\n";
	$html .= "<p><input class='button' type='submit' name='backup' value='"._("Backup")."'/>";	
	$html .= "<input class='button' type='button' name='takaisin'  value='"._("Return")."' onclick=\"window.location.href='?view=admin/dbadmin'\"/></p>";
	$html .= "</form>";

}else{
	$html .= "<p>"._("User credentials does not match")."</p>\n";
}
echo $html;

contentEnd();
pageEnd();
?>