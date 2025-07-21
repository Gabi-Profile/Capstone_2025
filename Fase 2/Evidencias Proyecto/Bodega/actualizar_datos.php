<?php
$output = shell_exec("python datos_actualizar.py");
echo "<pre>$output</pre>";
echo "<a href='index.php'>Volver</a>"; // o redirige automáticamente
?>
