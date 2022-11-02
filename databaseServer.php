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
  echo "Connected to db successfully\n\n";


	// lookup username in database	
	$query = "SELECT *FROM users where username = '$username' and password = '$password'"; 
	$result = mysqli_query($conn, $query);  
        $row = mysqli_fetch_array($result, MYSQLI_ASSOC);  
        $count = mysqli_num_rows($result);  
          
	if($count == 1)
	{
    echo "Login succesful!";
	  $userQuery = "SELECT UID FROM users WHERE username = '$username'";
    $userID = mysqli_query($conn, $userQuery); 
    $row = mysqli_fetch_array($userID, MYSQLI_ASSOC);  

    //echo json_encode($row['UID']);
    return array(true, seshGen($row['UID']));
    
	}
	else
	{	
	   publishLog("invalid username or password homie");
	   return false;
	}
}

function createUser($email, $username, $password)
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
           publishLog("User already exists, please try with a different name.");
           echo "error msg sent to log file";          
	        return false;
        }
        else
        {
	          $registerQuery = "INSERT into users (userid, username, password)
		        VALUES ('$email', '$username', '$password')";
	
	          $result   = mysqli_query($conn, $registerQuery);	
	          echo "\nUser created succesfully";
            return true;
        }
}
function doValidate($sessionid)
{
	echo "validating sesh";
  $conn = new mysqli('127.0.0.1', 'testuser', '12345', 'main_help_db');
  //check database for session id
  $seshQuery = "SELECT *FROM Systems where sessions_ID = '$sessionid'";
  $result = mysqli_query($conn, $seshQuery);
  $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
  $count = mysqli_num_rows($result);
  if($count == 1)
  {
    echo "session is valid";
    return true;
  }
  else
  {
    echo "session not valid";
    return false;
  }
}


function apiData($rawData)
{
  $conn = new mysqli('127.0.0.1', 'testUser', '12345', 'testdb');

  foreach($rawData as $prod)
  {
    $PID = intval($prod['PID']);
    $name = $prod['name'];
    $image = $prod['img_url'];
    $gender = $prod['gender'];
    $color = $prod['color'];

    $addDataQuery = "INSERT INTO Products 
    VALUES ('$PID', '$name', '$image', '$gender', '$color')";

    //echo "\nproduct:\n$PID;. \n$name . \n$image . \n$gender . \n$color ";
    

    if(mysqli_query($conn, $addDataQuery))
    {
      echo "\nproducts added to db yoo";
    }
    else
    {
      publishLog("products could not be added to db");
    }
  }
}

function webRequestAll()
{
  $conn = new mysqli('127.0.0.1', 'testUser', '12345', 'testdb');
  $webQuery = "SELECT img_URL, name FROM Products";
  $result = mysqli_query($conn, $webQuery);
  //$row = mysqli_fetch_array($result, MYSQLI_ASSOC);
  $output = array();
  for($i = 0; $i < mysqli_num_rows($result); $i++)
  {
    $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
    $output["prod" . $i] = $row;
  }

  return $output;
  /*if(mysqli_query($conn, $webQuery))
  {
    echo "\nproducts returned";
  }
  else
  {
    publishLog("products could not be returned");
  }*/

}


function requestProcessor($request)
{
  echo "received request".PHP_EOL;
  var_dump($request);
  if(!isset($request['type']))
  {
  //create error message
	  echo "unsupported message type";
    return "ERROR: unsupported message type";
  }
  switch ($request['type'])
  {
  case "login":
      echo"login request recieved\n\n";
      return doLogin($request['username'],$request['password']);
  
  case "validate_session":
      echo "Session request recieved\n\n";
      return doValidate($request['sessionid']);
 
  case "create_user":
	  echo "Create User request recieved\n\n";
      return createUser($request['email'], $request['username'],$request['password']);
  case "apiData":
    apiData($request['data']);
  
  case "requestAllProducts":
    return webRequestAll();
  
  }
  return array("returnCode" => '0', 'message'=>"Server received request and processed\n\n");
}


function publishLog($errorMsg)
{
    global $channel;
    $logMsg = ($errorMsg. " on " . date("Y.m.d"). " @ ". date("h:i:sa"). " @ ". gethostname());	
   //set push msg to error message
    $msg = new AMQPMessage($logMsg);
    // send msg to log file(s)
    $channel -> basic_publish($msg, 'logs');
}

function seshGen($userID)
{
  $conn = new mysqli('127.0.0.1', 'testuser', '12345', 'main_help_db');
  $sessionid = rand(1000, 999999999);
  $addSeshQuery = "INSERT into Systems (UID, sessions_ID) VALUES ('$userID', '$sessionid')";
  $result = mysqli_query($conn, $addSeshQuery);
  return $sessionid;
    //publishLog("Error adding/updating session key: ");
  
}

$server = new rabbitMQServer("testRabbitMQ.ini","dbServer");


echo "Database Server BEGIN\n\n".PHP_EOL;
$server->process_requests('requestProcessor');
echo "testRabbitMQServer END".PHP_EOL;


$channel->close();
$connection->close();
exit();
?>
message.txt
5 KB