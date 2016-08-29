<?php
set_time_limit(0);

passthru('clear');

include 'classes/class_server.php';
include 'classes/class_client.php';
include 'classes/class_chunk.php';
include 'classes/class_npc.php';

$server = new server('localhost', 25394, 5);
$server->run();
?>
