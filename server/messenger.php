<?php

include 'config.php';

// DB GENERIC FUNCTIONS /////////////////////////////////////

function connectToDb(&$oReply) {
  global $dbServer, $dbUser, $dbPassword, $dbDatabase;
	$mysqli = mysqli_connect($dbServer, $dbUser, $dbPassword, $dbDatabase);
	if (mysqli_connect_errno()) throw new Exception("Impossible to connect to database.");

	return $mysqli;
}

function disconnectFromDb(&$iMysqli) {
	mysqli_close($iMysqli);
}

function readInDb(&$iMysqli, &$oReply, &$iSqlRequest, &$iSqlInputArgs=NULL) {
  
	// prepare sql query
  if(($stmt = $iMysqli->prepare($iSqlRequest)) == FALSE) throw new Exception("Impossible to prepare sql request: " . $iMysqli->error);
	
  // bind sql parameters
  if(isset($iSqlInputArgs))
    call_user_func_array(array($stmt, "bind_param"), $iSqlInputArgs);
  
  if($stmt->execute() == FALSE) throw new Exception("Impossible to execute sql request: " . $iMysqli->error);
  
  $stmt->store_result();
  
  // bind sql outpouts
  $sqlOutputArgs = array();
  $data = array();
  $meta = $stmt->result_metadata();
  while($field = $meta->fetch_field())
    $sqlOutputArgs[] = &$data[$field->name];
  call_user_func_array(array($stmt, 'bind_result'), $sqlOutputArgs);
  
  // fetch query
  $res = array();
  while ($stmt->fetch()) {
	$rowValues = array();
    foreach($data as $key => $val) {
        $rowValues[$key] = $val;
    }
    $res[] = $rowValues;
  }
  
  $stmt->close();
  
  return $res;
}

function writeInDb(&$iMysqli, &$oReply, &$iSqlRequest, &$iSqlInputArgs) {
  
  // prepare sql query
  if(($stmt = $iMysqli->prepare($iSqlRequest)) == FALSE)  throw new Exception("Impossible to prepare sql request: " . $iMysqli->error);
  
  // bind sql parameters
  call_user_func_array(array($stmt, "bind_param"), $iSqlInputArgs);
  
  // execute sql query
  if($stmt->execute() == FALSE)  throw new Exception("Impossible to execute sql request: " . $iMysqli->error);
	
  $stmt->close();
}

// APPLICATIVE FUNCTIONS ////////////////////////////////////

// retrieve messages from DB according to input criteria
function getMessages(&$iMysqli, &$iRequest, &$oReply) {
  /*$oReply = array();
  $oReply[] = (object) array("txt" =>"Coucou 1");
  $oReply[] = (object) array("txt" =>"Coucou 2");
  $oReply[] = (object) array("txt" =>"Coucou 3");*/
  
  $sqlQuery = "SELECT text, author, tags, date FROM msg_message LIMIT 20";
//  $sqlBindParams = array();
  $res = readInDb($iMysqli, $oReply, $sqlQuery);
  
  $oReply = array();
  foreach ($res as &$message) {
    $oReply[] = array(
		"txt" => $message['text'],
		"author" => $message['author'],
		"tags" => $message['tags'],
		"date" => $message['date'],);
  }
}

// insert new spreadsheet in DB
function addMessage(&$iMysqli, &$iRequest, &$oReply) {
	
	$text = $iRequest['txt'];
	$author = (array_key_exists('author', $iRequest) ? $iRequest['author'] : $_SERVER['REMOTE_ADDR']);
	$tags = $iRequest['tags'];
	$date = date('Y-m-d H:i:s');
	
	$sqlQuery = "INSERT INTO msg_message (text, author, tags, date) VALUES (?,?,?,STR_TO_DATE(?,'%Y-%m-%d %H:%i:%s'))";
	$sqlBindParams = array('ssss', &$text, &$author, &$tags, &$date);
	writeInDb($iMysqli, $oReponse, $sqlQuery, $sqlBindParams);
}

// ENTRY /////////////////////////////////////////////////////

$reply = array();
try {
  $request = json_decode(file_get_contents("php://input"), true);
  $mysqli = connectToDb($reply);
  foreach ($request as &$req) {
    switch ($req['act']) {
      case 'get':
        getMessages($mysqli, $req, $reply); break;
      case 'add':
		addMessage($mysqli, $req, $reply); break;
      default:
        $reply = array("error" => "Unknown request: " . $req['act']);
    }
  }
  disconnectFromDb($mysqli);
} catch (Exception $e) {
  $reply = array("error" => $e->getMessage());
}
echo json_encode($reply);

?>
