<?php 

function buildContent($id)
	{
	switch ($id)
		{
		case $HOME:
			include("views\home.php");
		break;  
		
		case $TIMETABLES:
			include("views\timetables.php");
		break;

		default:
			include("views\home.php");
		}
	}
?>