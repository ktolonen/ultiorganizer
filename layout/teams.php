<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" 
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
<head>
<title>Liitokiekkoliiton Pelikone</title>
<link rel="stylesheet" href="styles/layout.css" type="text/css" />
<link rel="stylesheet" href="styles/font.css" type="text/css" />
<?php
include("view_ids.inc.php");
$LAYOUT_ID = $TEAMS;
?>

</head>
<body>
<div class="page">
	<div class="page_top">
		<?php include("layout/header.php"); ?>
		<?php include("layout/topmenu.php"); ?>
	</div>
	<div class="page_middle">
			<?php include("layout/leftmenu.php"); ?>
			<?php include("layout/content.php"); ?>
	</div>

	<div class="page_bottom">
		<?php include("layout/footer.php"); ?>
	</div>
</div>
</body>
</html>