<?php

use Lenton\Castaway\Server;

set_time_limit(0);

require 'vendor/autoload.php';

$server = new Server('localhost', 25394, 5);
$server->run();
?>
