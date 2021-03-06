<?php
################################################################################
#                          CGE Create User Login                               #
################################################################################
// This is the script which:
//   -> Checks the recieved user login input for malicious entries
//   -> Checks the recieved user login is not already taken
//   -> Creates a user with the user login info in the SQL db
//   -> Starts a session
//   -> Returns the status session id and username

// ERROR LOGGING
error_reporting(E_ALL);
ini_set('error_log','/srv/www/htdocs/services/error_log');
ini_set('log_errors','true');

// FUNCTIONS
function _INPUT($name){
   // QUERY HANDLER: Used to get form elements and queries in a simple manner
   // AUTHOR: Martin Thomsen
   // USAGE: $form_text = _INPUT('form_text_name');
   if ($_SERVER['REQUEST_METHOD'] == 'POST' and isset($_POST[$name]))
      return strip_tags($_POST[$name]);
   elseif ($_SERVER['REQUEST_METHOD'] == 'GET' and isset($_GET[$name]))
      return strip_tags($_GET[$name]);
   else return NULL;
}

function respond($status, $SESSIONID, $USERNAME, $EXIT=false){
	echo "<?xml version='1.0' encoding='UTF-8'?><SESSION><STATUS>$status</STATUS><SESSIONID>$SESSIONID</SESSIONID><USERNAME>$USERNAME</USERNAME></SESSION>";
   if($EXIT == true) exit();
}

// MAIN
if (count($_POST)>0 or count($_GET)>0) {
	// VALIDATE INPUTS
   $USERNAME = _INPUT("USERNAME");
   $ACTIVATE = _INPUT("ACTIVATE");
	if (preg_match("/[^A-Za-z0-9\_\-\.\@\,]/", $USERNAME)){ respond("BADUSER", '', '', true); }
	if (preg_match("/[^A-fa-f0-9]/", $ACTIVATE)){ respond("BADHASH", '', '', true); }
   
	// CONNECT TO THE DATABASE
	$mysqli = new mysqli('cge', 'cgeclient', 'www', 'cge');
   
	// CHECK CONNECTION
	if (mysqli_connect_errno()) { respond("Connect failed: %s\n", mysqli_connect_error(), '', '', true); }
   
	// CHECK ACTIVATION CODE AND STATUS
	$stmt = $mysqli->prepare("SELECT tmp, status FROM users WHERE usr = ?");
	$stmt->bind_param('s', $USERNAME);
	// EXECUTE AND GET RESULTS
	$stmt->execute();
	$stmt->bind_result($tmp, $stat);

	// VALIDATE ACTIVATION CODE AND STATUS
	$status = 'NOUSER';
	while($stmt->fetch()){
      if ($tmp == $ACTIVATE and $stat == 'CREATED'){
      	$status = "ACCEPTED";
      }elseif($stat != 'CREATED'){
      	$status = "ACTIVATED";
      }else{
      	$status = "REJECTED";
      }
   }
   
	// CLOSE STATEMENT
	$stmt->close();
   
	if($status=="ACCEPTED"){
		// CREATE SESSIONID VARS
		$key1 = "biasgkBJHGAdk2%/2g177=(maj12s&7h";
		$key2 = ")/6jb8HilkjWdu&R/2gjHG(&nbkgkP)2";
		$DATE = date('d-m-Y H:i:s');
		$IP = $_SERVER['REMOTE_ADDR'];
      $SESSIONID = sha1($key1.$DATE.$IP.$key2);
      // UPDATE USER DATA
		$stmt = $mysqli->prepare(" UPDATE users".
                               " SET session_id = ?,".
                               "     last_login = ?,".
                               "     ip = ?,".
                               "     status = 'ACCEPTED',".
                               "     tmp = NULL".
                               " WHERE usr = ?".
                               " ;");
		$stmt->bind_param('ssss', $SESSIONID, $DATE, $IP, $USERNAME);
      // EXECUTE AND CLOSE STATEMENT
	 	$stmt->execute();
		$stmt->close();	 
	 
	 	// CREATE SESSION
      if(!isset($_SESSION)) session_start(); // START SESSION
		$_SESSION = array(); // UNSET ALL SESSION VARIABLES
		
		// REGISTER $SESSIONID AND $USERNAME
		$_SESSION['SESSIONID'] = $SESSIONID;
		$_SESSION['USERNAME']  = $USERNAME;
		
		// RESPOND WITH SUCCES
		respond($status, $SESSIONID, $USERNAME);
	}else{
		// RESPOND WITH REJECTION
		respond($status, '', '');
	}
	// CLOSING CONNECTION
	$mysqli->close();
} else { echo "<html><head><title>Unauthorized Usage!</title></head><body>Get Lost!!!</body></html>"; }
?>
