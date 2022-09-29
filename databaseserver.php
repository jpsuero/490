#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');



//create connection
$conn = new mysqli('127.0.0.1', 'testUser', '12345', 'testdb');


// Check connection
if ($conn->connect_error)
{
  die("Connection failed: " . $conn->connect_error);
}
echo "Connected successfully";


function doLogin($username,$password)
{
    //to prevent from mysqli injection  
        $username = stripcslashes($username);  
        $password = stripcslashes($password);  
        $username = mysqli_real_escape_string($conn, $username);  
	$password = mysqli_real_escape_string($conn, $password);

    // lookup username in database	
	$sql = "SELECT *from 'users' where username = '$username' and password = '$password'"; 
	$result = mysqli_query($conn, $sql);  
        $row = mysqli_fetch_array($result, MYSQLI_ASSOC);  
        $count = mysqli_num_rows($result);  
          
	if($count == 1)
	{
           echo "Login succesful!";
	   return true;
	}
	else
	{
	   echo" login unsuccesful bicho";
	   return false;
	}
}

function createUser($username, $password)
{
	//to prevent from mysqli injection  
        $username = stripcslashes($username);
        $password = stripcslashes($password);
        $username = mysqli_real_escape_string($conn, $username);
        $password = mysqli_real_escape_string($conn, $password);

    // lookup username in database      
        $sql = "SELECT *from 'users' where username = '$username' and password = '$password'";
        $result = mysqli_query($conn, $sql);
        $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
        $count = mysqli_num_rows($result);

        if($count == 1)
        {
           echo "User already exists!";
           return false;
        }
        else
        {
	   $sql = "INSERT into `users` (username, password)
 		   VALUES ('$username',($password')";
	
	   $result   = mysqli_query($con, $query);	
	   echo "User created succesfully";
           return true;
        }

function requestProcessor($request)
{
  echo "received request".PHP_EOL;
  var_dump($request);
  if(!isset($request['type']))
  {
    return "ERROR: unsupported message type";
  }
  switch ($request['type'])
  {
    case "login":
      return doLogin($request['username'],$request['password']);
    case "validate_session":
      return doValidate($request['sessionId']);
    case "create_user":
      return createUser($request['username'],$request['password']);
  }
  return array("returnCode" => '0', 'message'=>"Server received request and processed");
}

$server = new rabbitMQServer("testRabbitMQ.ini","dbServer");

echo "testRabbitMQServer BEGIN".PHP_EOL;
$server->process_requests('requestProcessor');
echo "testRabbitMQServer END".PHP_EOL;
exit();
?>

