# CONSULTA RENIEC
Obten los Nombres y apellidos de una Persona a partir de si DNI(Perú)

```sh
<?php
    require ("curl.php");
    require ("reniec.php");

    $a = new Reniec();
	$dni="00000000";
    header('Content-type: application/json');
    echo json_encode( $a->BuscaDatosReniec($dni), JSON_PRETTY_PRINT );
?>
```
