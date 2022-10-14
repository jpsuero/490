#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');


//logging stuff
require_once __DIR__.'/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;


//create connection to rbmq for logging
$connection = new AMQPStreamConnection('172.23.46.192', '5672', 'test', 'test', 'testHost');
//create channel
$channel = $connection->channel();
//connect to logs exchange
$channel->exchange_declare('logs', 'fanout', false, false, false);



//global error msg variable
$errorMsg;

function doLogin($username,$password)
{    
  //global variable call
  global $errorMsg;
  global $channel;
  //database connection
  //create connection
 
  $conn = new mysqli('127.0.0.1', 'testUser', '12345', 'testdb');

 
  // Check connection
  if ($conn->connect_error)
  {
    die("Connection failed: " . $conn->connect_error);
  }
  echo "Connected successfully\n\n";


	// lookup username in database	
	$query = "SELECT *FROM users where username = '$username' and password = '$password'"; 
	$result = mysqli_query($conn, $query);  
        $row = mysqli_fetch_array($result, MYSQLI_ASSOC);  
        $count = mysqli_num_rows($result);  
          
	if($count == 1)
	{
           echo "Login succesful!";
	   return true;
	}
	else
	{	
	   //create error message	
		$errorMsg = "invalid username or password";
	   //set push msg to error message
		$msg = new AMQPMessage($errorMsg);
	   // send msg to log file(s)
           $channel -> basic_publish($msg, 'logs');
	   echo "error msg sent to log file";
	   return false;
	}
}

function createUser($UserID, $username, $password)
{
  //global variable call
	global $errorMsg;
	global $channel;

  //database connection
  $conn = new mysqli('127.0.0.1', 'testUser', '12345', 'testdb');


  // Check connection
  if ($conn->connect_error)
  {
    die("Connection failed: " . $conn->connect_error);
  }
  echo "Connected successfully\n\n";

    // lookup username in database      
        $query = "SELECT *FROM users where username = '$username' and password = '$password'";
        $result = mysqli_query($conn, $query);
        $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
        $count = mysqli_num_rows($result);

        if($count == 1)
        {
           //create error message       
                $errorMsg = "username already exists in database";
           //set push msg to error message
                $msg = new AMQPMessage($errorMsg);
           // send msg to log file(s)
                $channel -> basic_publish($msg, 'logs');
           echo "error msg sent to log file";          
	   return false;
        }
        else
        {
	   $registerQuery = "INSERT into users
		   VALUES ('$email', '$username', '$password')";
	
	   $result   = mysqli_query($conn, $registerQuery);	
	   echo "\nUser created succesfully";
           return true;
        }
}
function requestProcessor($request)
{
  echo "received request".PHP_EOL;
  var_dump($request);
  if(!isset($request['type']))
  {
	  //create error message       
                $errorMsg = "invalid request type sent to database server";
           //set push msg to error message
                $msg = new AMQPMessage($errorMsg);
           // send msg to log file(s)
           $channel -> basic_publish($msg, 'logs');
           echo "error msg sent to log file";
	  return "ERROR: unsupported message type";
  }
  switch ($request['type'])
  {
  case "login":
      echo"login request recieved\n\n";
      return doLogin($request['username'],$request['password']);
  
  case "validate_session":
      echo "Session request recieved\n\n";
      return doValidate($request['sessionId']);
 
  case "create_user":
	  echo "Create User request recieved\n\n";
      return createUser($request['email'],$request['username'],$request['password']);
  }
  return array("returnCode" => '0', 'message'=>"Server received request and processed\n\n");
}

$server = new rabbitMQServer("testRabbitMQ.ini","dbServer");


echo "Database Server BEGIN\n\n".PHP_EOL;
$server->process_requests('requestProcessor');
echo "testRabbitMQServer END".PHP_EOL;


$channel->close();
$connection->close();
exit();
?>

