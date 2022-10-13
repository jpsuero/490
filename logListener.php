#!/usr/bin/php
<?php

require_once __DIR__.'/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;


require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$connection = new AMQPStreamConnection('localhost', '5672', 'guest', 'guest');
$channel = $connection->channel();

$channel->exchange_declare('logs', 'fanout', false, false, false);


$data = implode('', array_slice($argv, 1));
if (empty($data))
{
	$data = "they call me mr. bombtastic";
}
$msg = new AMQPMessage($data);

$channel->basic_publish($msg, 'logs');

echo '[x] Sent ', $data, "\n";

$channel->close();
$connection->close();
