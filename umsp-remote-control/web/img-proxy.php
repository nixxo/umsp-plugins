<?php

header("Content-type: image/jpeg");
print(@file_get_contents($_GET['url']));

?>
