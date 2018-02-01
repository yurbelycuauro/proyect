<html>
    <head><title>Prueba</title></head>
    <body>
<?php
    $host="server04.viralcreation.cl";
    $port="5432";
    $user="stitchdata";
    $pass="claveRara123!";
    $dbname="stitchdata";

    $connect = pg_connect("host=$host port=$port dbname=$dbname user=$user  password=$pass ");

    if(!$connect)
        echo "<p><i>No me conecte</i></p>";
    else
        echo "<p><i>Me conecte</i></p>";

    pg_close($connect);
?>
    </body>
</html>