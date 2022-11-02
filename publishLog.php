#!/usr/bin/php
<?php

require_once __DIR__.'/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;


//create connection to rbmq for logging
$connection = new AMQPStreamConnection('172.23.46.192', '5672', 'test', 'test', 'testHost');
//create channel
$channel = $connection->channel();
//connect to logs exchange

$channel->exchange_declare('logs', 'fanout', false, false, false);

$errorMsg = $argv[1];
echo "i like cheese"; 

$logMsg = ($errorMsg. " on " . date("Y.m.d"). " @ ". date("h:i:sa"). " @ ". gethostname());	

//set push msg to error message
$msg = new AMQPMessage($logMsg);
// send msg to log file(s)
$channel -> basic_publish($msg, 'logs');


$connection->close();

exit();
?>