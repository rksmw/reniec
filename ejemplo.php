<?php
	require ("curl.php");
	require ("reniec.php");

	$a = new Reniec();

	$dni="00000000"; //8 digits
	header('Content-type: application/json');
	echo json_encode( $a->BuscaDatosReniec($dni), JSON_PRETTY_PRINT );
?>
