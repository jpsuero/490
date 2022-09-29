#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');


function doLogin($username,$password)
{    
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
	   return "\nLogin succesful!";
	}
	else
	{
	   echo" login unsuccesful bicho";
	   return "\nLogin unsuccesful bitcho";
	}
}

function createUser($UserID, $username, $password)
{
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
           echo "\nUser already exists!";
           return "\nUser already exists";
        }
        else
        {
	   $registerQuery = "INSERT into users
		   VALUES ('$UserID', '$username', '$password')";
	
	   $result   = mysqli_query($conn, $registerQuery);	
	   echo "\nUser created succesfully";
           return "\nUser created succesfully";
        }
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
      echo"login request recieved\n\n";
      return doLogin($request['username'],$request['password']);
  
  case "validate_session":
      echo "Session request recieved\n\n";
      return doValidate($request['sessionId']);
 
  case "create_user":
	  echo "Create User request recieved\n\n";
      return createUser($request['UserID'],$request['username'],$request['password']);
  }
  return array("returnCode" => '0', 'message'=>"Server received request and processed\n\n");
}

$server = new rabbitMQServer("testRabbitMQ.ini","dbServer");

echo "Database Server BEGIN\n\n".PHP_EOL;
$server->process_requests('requestProcessor');
echo "testRabbitMQServer END".PHP_EOL;
exit();
?>

