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
	if(($stmt = $iMysqli->prepare($iSqlRequest)) == FALSE) throw new Exception("Impossible to prepare sql request: " . $iMysqli->error);

	// bind sql parameters
	call_user_func_array(array($stmt, "bind_param"), $iSqlInputArgs);

	// execute sql query
	if($stmt->execute() == FALSE)  throw new Exception("Impossible to execute sql request: " . $iMysqli->error);

	$stmt->close();
}

// APPLICATIVE FUNCTIONS ////////////////////////////////////

// insert new spreadsheet in DB
function addMessage(&$iMysqli, &$iRequest, &$oReply) {
	
	$text = $iRequest['txt'];
	$author = (array_key_exists('author', $iRequest) ? $iRequest['author'] : $_SERVER['REMOTE_ADDR']);
	$tags = (array_key_exists('tags', $iRequest) ? implode(',', $iRequest['tags']) : NULL);
	$date = gmdate('Y-m-d\TH:i:s\Z');
	
	$sqlQuery = "INSERT INTO msg_message (text, author, tags, date) VALUES (?,?,?,STR_TO_DATE(?,'%Y-%m-%dT%H:%i:%sZ'))";
	$sqlBindParams = array('ssss', &$text, &$author, &$tags, &$date);
	writeInDb($iMysqli, $oReponse, $sqlQuery, $sqlBindParams);
}

// retrieve messages from DB according to input criteria
function getMessages(&$iMysqli, &$iRequest, &$oReply) {

	$sqlQuery = "SELECT text, author, tags, DATE_FORMAT(date, '%Y-%m-%dT%H:%i:%sZ') AS date FROM msg_message ORDER BY id DESC LIMIT 20";
	$res = readInDb($iMysqli, $oReply, $sqlQuery);

	$oReply = array();
	foreach ($res as &$message) {
		$aReply = array(
			"txt" => $message['text'],
			"author" => $message['author'],
			"tags" => (array_key_exists('tags', $message) ? explode(',', $message['tags']) : NULL),
			"date" => $message['date']);
		array_unshift($oReply, $aReply);
	}
	if(!empty($oReply)) $oReply[0]["sdate"] = gmdate('Y-m-d\TH:i:s\Z');
}

// ENTRY /////////////////////////////////////////////////////

function entry() {
	$reply = array();
	try {
		$request = json_decode(file_get_contents("php://input"), true);
		$mysqli = connectToDb($reply);
		if(is_array($request) && isAssoc($request)) parseRequest($mysqli, $request, $reply);
		else if(is_array($request) && isAssoc($request)==false) foreach ($request as &$req) parseRequest($mysqli, $req, $reply);
		else throw new Exception("Unknown type of request: " . gettype($request));
		disconnectFromDb($mysqli);
	} catch (Exception $e) {
		$reply = array("error" => $e->getMessage());
	}
	echo json_encode($reply);
}

function isAssoc($arr) {
	return array_keys($arr) !== range(0, count($arr) - 1);
}

function parseRequest(&$iMysqli, &$iRequest, &$oReply) {
	switch ($iRequest['act']) {
		case 'get': getMessages($iMysqli, $iRequest, $oReply); break;
		case 'add': addMessage($iMysqli, $iRequest, $oReply); break;
		default: throw new Exception("Unknown request: " . $iRequest['act']);
	}
}

entry();

?>
