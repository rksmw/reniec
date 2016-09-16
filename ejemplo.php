<?php
    require ("curl.php");
    require ("reniec.php");

	$a = new Reniec();
	$dni="44274790";
    header('Content-type: application/json');
    echo json_encode( $a->BuscaDatosReniec($dni), JSON_PRETTY_PRINT );
?>
