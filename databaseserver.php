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
//$errorMsg;

function doLogin($username,$password)
{    
  //global variable call
  //global $errorMsg;
  //global $channel;
  //database connection
  //create connection
 
  $conn = new mysqli('127.0.0.1', 'testuser', '12345', 'main_help_db');
 
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
	//global $errorMsg;
	//global $channel;

  //database connection
  $conn = new mysqli('127.0.0.1', 'testuser', '12345', 'main_help_db');


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
  $conn = new mysqli('127.0.0.1', 'testuser', '12345', 'main_help_db');

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
  //tested with jp db and gabe A
  
  $conn = new mysqli('127.0.0.1', 'testuser', '12345', 'main_help_db');
  $webQuery = "SELECT img_URL, name FROM Products";
  $result = mysqli_query($conn, $webQuery);
  //$row = mysqli_fetch_array($result, MYSQLI_ASSOC);
  $output = array();
  
  if(mysqli_query($conn, $webQuery))
  {
    echo "\nproducts returned\n";
    for($i = 0; $i < mysqli_num_rows($result); $i++)
    {
      $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
      $output["prod" . $i] = $row;
    }
  }
  else
  {
    publishLog("products could not be returned");
  }
  

  return $output;
}

function checkFavorites($UIDX, $PIDX)
{
  $conn = new mysqli('127.0.0.1', 'testuser', '12345', 'main_help_db');
  $UID = $UIDX;
  $PID= $PIDX;

  $checkForFavoriteQuery = "SELECT * FROM user_favorites WHERE PID = '$PID' and UID = '$UID'";
  $result = mysqli_query($conn, $checkForFavoriteQuery);
  $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
  $count = mysqli_num_rows($result);
  if($count >= 1)
  {
    return false;
  }
  else
  {
    return true;
  }
}


function addToFavorites($data)
{
  $conn = new mysqli('127.0.0.1', 'testuser', '12345', 'main_help_db');
  $name = $data['name'];
  echo $name;
  $UID = $data['uid'];
  $getPID = "SELECT PID FROM Products WHERE name = '$name'";
  $result = mysqli_query($conn, $getPID);
  $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
  $PID = $row['PID'];
  echo "$UID . $PID";
  if(checkFavorites($UID, $PID))
  {
    $addPIDQuery = "INSERT INTO user_favorites VALUES ('$PID', '$UID')";
    if(mysqli_query($conn, $addPIDQuery))
    {
      echo "product added to favorites";
    }
    else
    {
      echo "failed to add product into favoritaes!";
    }
  }
  else
  {
    echo "\nunfavorite triggered\n";
    //delete from favorites
    $deleteFromFavoritesQuery = "Delete FROM user_favorites WHERE PID = '$PID' and UID = '$UID'";
    if(mysqli_query($conn, $deleteFromFavoritesQuery))
    {
      echo "\nfavorite has been removed\n";
    }
    else
    {
      echo "could not remove favorite from table\n";
    }
  }
  
}

function getFavs($data)
{
  $conn = new mysqli('127.0.0.1', 'testuser', '12345', 'main_help_db');
  $UID = $data['uid'];
  $getFavesQuery = "SELECT PID FROM user_favorites WHERE UID = '$UID'";
  $result = mysqli_query($conn, $getFavesQuery);
  $pids = array();
  for($i = 0; $i < mysqli_num_rows($result); $i++)
  {
    $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
    $pids["prod" . $i] = $row;
  }
  $output = array();
  for($i = 0; $i < count($pids); $i++)
  {
    $thisPID = $pids["prod". $i]['PID'];
    $getProductInfoQuery = "SELECT img_URL, name FROM Products WHERE PID = '$thisPID'";
    $results = mysqli_query($conn, $getProductInfoQuery);
    $joe= mysqli_fetch_array($results, MYSQLI_ASSOC);
    $output["prod" . $i]= $joe;
  }
  return $output;
}

function recommend($data)
{
  $conn = new mysqli('127.0.0.1', 'testuser', '12345', 'main_help_db');
  $UID = $data['uid'];
  $getFavesQuery = "SELECT PID FROM user_favorites WHERE UID = '$UID'";
  $result = mysqli_query($conn, $getFavesQuery);
  $pids = array();
  for($i = 0; $i < mysqli_num_rows($result); $i++)
  {
    $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
    $pids["prod" . $i] = $row;
  }
  $colors = array();
  for($i = 0; $i < count($pids); $i++)
  {
    $thisPID = $pids["prod". $i]['PID'];
    $getProductInfoQuery = "SELECT color FROM Products WHERE PID = '$thisPID'";
    $results = mysqli_query($conn, $getProductInfoQuery);
    $joe= mysqli_fetch_array($results, MYSQLI_ASSOC);
    //print_r($joe['color'])
    $color = $joe['color'];
    print_r($color);
    $colors["coloroo" . $i]= $joe;
  }
  $newColors = array();
  

  $newColors = array_values($colors);

  $COLORS = array_unique($newColors, SORT_REGULAR);
  $newCOLORS = array_values($COLORS);
  print_r($colors);
  print_r($newColors);
  print_r($COLORS);
  print_r($newCOLORS);
  $output = array();
  $index = 0;
  for($j = 0; $j < count($newCOLORS); $j++)
  {
    $color = $newCOLORS[$j]['color'];
    //echo "\n$color";
    $getAllByColorQuery = "SELECT img_URL, name FROM Products WHERE color = '$color'";
    $resultX = mysqli_query($conn, $getAllByColorQuery);
    for($x = $index; $x < mysqli_num_rows($resultX) + $index; $x++)
    {
      $shmoe= mysqli_fetch_array($resultX, MYSQLI_ASSOC);
      
      $output["prod" .$x]= $shmoe;

      //print_r($output);
    }
    $index = $x;
  }
  //print_r($output);
  return $output;

}

function killSessions()
{
  $conn = new mysqli('127.0.0.1', 'testuser', '12345', 'main_help_db');
  $deleteSeshQuery = "DELETE FROM systems WHERE time_stamp < DATEADD(day, -1, GETDATE())";

  if (mysqli_query($conn, $deleteSeshQuery)) 
  {
      echo "Deleted session keys older than a day successfully";
  } 
  else 
  {
    publishLog("Error deleting session keys: " . mysqli_error($conn));
  }

 }

function createOutfit($data)
{
  $conn = new mysqli('127.0.0.1', 'testuser', '12345', 'main_help_db');
  $UID = $data['uid'];
  $topname = $data['top'];
  $bottomname = $data['bottom'];
  $outfitname = $data['outfitname'];
  $createOutfitQuery = "INSERT INTO outfits (UID, tops, bottoms, name) VALUES ('$UID', '$topname', '$bottomname', '$outfitname')";
  if(mysqli_query($conn, $createOutfitQuery))
  {
    echo "\noutfit created\n";
    /*$getOIDQuery = "SELECT OID FROM outfits where UID = '$UID' and name = '$outfitname'";
    $result = mysqli_query($conn, $getOIDQuery);
    $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
    return $row['OID'];*/
  }
  else
  {
    echo "\noutfit not created pleb\n";
    publishLog("could not create outfit");
  }
}

function getOutfits($data)
{
  $conn = new mysqli('127.0.0.1', 'testuser', '12345', 'main_help_db');
  $UID = $data['uid'];
  $getOIDQuery = "SELECT * FROM outfits WHERE UID = '$UID'";
  $result = mysqli_query($conn, $getOIDQuery);
  $oids = array();
  for($i = 0; $i < mysqli_num_rows($result); $i++)
  {
    $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
    $oids["prod" . $i] = $row;
  }
  $output = array();
  for($i = 0; $i < count($oids); $i++)
  {
    $top = $oids["prod". $i]['tops'];
    $bottom = $oids["prod". $i]['bottoms'];
    $name = $oids["prod". $i]['name'];
    print_r($top . $bottom);
    $getTopInfoQuery = "SELECT img_URL, name FROM Products WHERE img_URL = '$top'";
    
    $getBottomInfoQuery = "SELECT img_URL, name FROM Products WHERE img_URL = '$bottom'";

    $getOutfitName = "SELECT name FROM outfits WHERE name = '$name'";
    $resultTop = mysqli_query($conn, $getTopInfoQuery);
    
    $rowTop= mysqli_fetch_array($resultTop, MYSQLI_ASSOC);
    print_r($rowTop);
    $resultBottom = mysqli_query($conn, $getBottomInfoQuery);

    $rowBottom = mysqli_fetch_array($resultBottom, MYSQLI_ASSOC);
    print_r($rowBottom);

    $resultOutfitName = mysqli_query($conn, $getOutfitName);
    $rowOutfit = mysqli_fetch_array($resultOutfitName, MYSQLI_ASSOC);
    print_r($rowOutfit);

    $output["prod" . $i]['top'] = $rowTop;
    $output["prod" . $i]['bottom'] = $rowBottom;
    $output["prod" . $i]['outfitName'] = $rowOutfit;
  }
  print_r($output);
  return $output;
}

function deleteOutfits($data)
{
  $conn = new mysqli('127.0.0.1', 'testuser', '12345', 'main_help_db');
  $uid = $data["uid"];
  $outfitname = $data["oName"];
  
  $deleteOutfitQuery = "DELETE FROM outfits where UID = '$uid' and name = '$outfitname'";
  if(mysqli_query($conn, $deleteOutfitQuery))
  {
    echo "\n outfit deleted from database";
    return true;
  }
  else
  {
    publishLog("could not delete outfit from db");
    return false;
  }
}


function requestProcessor($request)
{
  echo "received request\n\n".PHP_EOL;
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
    break;
  case "requestAllProducts":
    return webRequestAll();
    break;
  case "addFav":
    echo "\nAdd to favorites request\n";
    addToFavorites($request);
    return true;

  case "getFavs":
    echo "\n Get faves request recieved";
    return getFavs($request);

  case "recommend":
    echo "\n recommend request recieved!\n";
    return recommend($request);

  case "createOutfit":
    echo "\n create outfit request recieved\n";
    createOutfit($request);
    break;

  case "getOutfits":
    echo "\n outfit request received\n";
    return getOutfits($request);

  case "deleteOutfit":
    echo "\n delete outfit request recieved";
    return deleteOutfits($request);
  }
  //return array("returnCode" => '0', 'message'=>"Server received request and processed\n\n");
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
//killSessions();


echo "Database Server BEGIN\n\n".PHP_EOL;
$server->process_requests('requestProcessor');
echo "testRabbitMQServer END".PHP_EOL;


$channel->close();
$connection->close();
exit();
?>
