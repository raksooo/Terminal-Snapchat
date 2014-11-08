<?php require_once('php-snapchat/src/snapchat.php');
date_default_timezone_set("Europe/Stockholm");

const IMAGE_APPLICATION = "qlmanage";
const VIDEO_APPLICATION = "open";
const VIEWERAPPLICATION_STARTUP_TIME = 2;

const UPDATE_FREQUENCY = 5;
const PROMPT_SLEEP = 50;
const RECONNECTION_SLEEP = 5;

$scriptpath = realpath(dirname(__FILE__)) . "/";
$path = getcwd();

$TYPE_ALL = 0; $TYPE_RECEIVED = 1; $TYPE_SENT = 2;
$status = Array("none", "sent", "delivered", "opened", "saved");
$media = Array("image", "video", "video", "friend", "image", "video", "video");

$snapchat;
$username = "imalittlerascal";
$password = null;
$auth_token = trim(file_get_contents($scriptpath . "auth_token.txt"));
checkConnectivity(true);
$snapchat = new Snapchat($username, $password, $auth_token);
$snaps;
$unread = 0;
update();

unset($argv[0]);
$single = false;
if (isset($argv[1]) && $argv[1] === "-s") {
	$single = true;
}
$first = true;
while (!$single || $first) {
	$first = false;
	output("\r                                                                        ");
	output("\r");
	if (!$single) {
		$input = prompt("\r" . formatPath() . " snapchat" . ($unread > 0 ? " (" . $unread . ")" : "") . ": ", UPDATE_FREQUENCY);
	} else {
		unset($argv[1]);
		$input = join(" ", $argv);
	}
	if ($input === true)
		output("\r");
	else {
		switch ($input) {
			case "h":
			case "help":
				echo file_get_contents($scriptpath . "help.txt");
				break;
			case "logout":
				$snapchat->logout();
			case "e":
			case "exit":
				die();
				break;
			case "raw":
				print_r($snaps);
				break;
			case "s":
			case "sent":
				printSnaps($TYPE_SENT);
				break;
			case "r":
			case "received":
				printSnaps($TYPE_RECEIVED);
				break;
			case "a":
			case "all":
				printSnaps($TYPE_ALL);
				break;
			case "f":
			case "friends":
				printFriends();
				break;
			case "u":
			case "update":
				update();
				break;
			case "ls":
				printFiles();
				break;
			case "c":
			case "clr":
			case "clear":
				for ($i=0; $i<1000; $i++)
					output("\n");
				break;
			default:
				$input_arr = explode(" ", $input);
				if (strpos($input, "v ") === 0 || strpos($input, "view ") === 0 || $input === "v")
					openSnap(isset($input_arr[1]) ? $input_arr[1] : 0);
				else if (strpos($input, "p ") === 0 || strpos($input, "send ") === 0)
					sendSnap($input_arr[1], $input_arr[2], empty($input_arr[3]) ? 10 : $input_arr[3]);
				else if (strpos($input, "cd ") === 0 && isset($input_arr[1]))
					changeDirectory($input_arr[1]);
				else if (strpos($input, "read ") === 0 && isset($input_arr[1]))
					markread(getSnapByIndex(isset($input_arr[2]) ? $input_arr[2] : 0, $input_arr[1] === "s" ? $TYPE_SENT : $TYPE_RECEIVED)->id, true);
				break;
		}
	}
}

function prompt($message, $timeout = -1) {
	output($message);
	$time = time();

	$fp = fopen("php://stdin", "r");
	stream_set_blocking($fp, false);
	$line = "";
	$r = "";
	do {
		$line .= $r;
		usleep(PROMPT_SLEEP*1000);
		if ($timeout !== -1 && time()-$time>$timeout) {
			$update = update();
			if ($update)
				return true;
			else if ($update === null) {
				checkConnectivity();
				return true;
			}
			$time = time();
		}
		$r = fread($fp, 1);
	} while(strpos($r, "\n") === false);
	pclose($fp);

	return trim($line);
}

function longestlength($array) {
	$longest = 0;
	$length;
	foreach ($array as $v)
		if (($length=strlen($v)) > $longest)
			$longest = $length;
	return $longest;
}

function printlength($string, $length, $extraspaces = 0, $return = false) {
	$returnstring = "";
	for ($i=0; $i<$length; $i++) {
		if ($i < strlen($string))
			$returnstring .= $string{$i};
		else
			$returnstring .= " ";
	}

	for ($i=0; $i<$extraspaces; $i++)
		$returnstring .= " ";

	if ($return === true)
		return $returnstring;
	else
		output($returnstring);
}

function getSnaps() {
	global $scriptpath, $snapchat, $snaps;

	$tmpsnaps = $snapchat->getSnaps();
	if (is_array($tmpsnaps) && sizeof($tmpsnaps) > 0) {
		foreach ($tmpsnaps as $snap) {
			$snapid = $snap->id;
			$extension = $snap->media_type === Snapchat::MEDIA_IMAGE || $snap->media_type === Snapchat::MEDIA_FRIEND_REQUEST_IMAGE ? ".jpg" : ".mov";
			if (!file_exists($scriptpath . 'snaps/' . $snapid . $extension) && $snap->status === Snapchat::STATUS_DELIVERED && $snap->media_type !== Snapchat::MEDIA_FRIEND_REQUEST) {
				$data = $snapchat->getMedia($snapid);
				if ($data !== false)
					file_put_contents($scriptpath . 'snaps/' . $snapid . $extension, $data);
			}
		}
	}
	
	return $tmpsnaps;
}

function cleanSnaps() {
	global $scriptpath, $snaps;

	foreach ($snaps as $snap) {
		$extension = $snap->media_type === Snapchat::MEDIA_IMAGE || $snap->media_type === Snapchat::MEDIA_FRIEND_REQUEST_IMAGE ? ".jpg" : ".mov";
		$file = $scriptpath . "snaps/" . $snap->id . $extension;
		if (file_exists($file) && $snap->status !== Snapchat::STATUS_DELIVERED)
			unlink($file);
	}
}

function printSnaps($type, $unreadonly = false) {
	global $scriptpath, $snaps, $status, $media;

	$strings = Array();

	$i = 0;
	foreach ($snaps as $snap) {
		$r = empty($snap->media_id);
		$extension = $snap->media_type === Snapchat::MEDIA_IMAGE || $snap->media_type === Snapchat::MEDIA_FRIEND_REQUEST_IMAGE ? ".jpg" : ".mov";
		if ($snap->media_type !== Snapchat::MEDIA_FRIEND_REQUEST && (!$type || ($r xor $type-1))) {
			if (!$unreadonly || $snap->status === Snapchat::STATUS_DELIVERED) {
				$strings[$i] = printlength(file_exists($scriptpath . "snaps/" . $snap->id . $extension) ? "+" : "", 1, 3, true);
				$strings[$i] .= printlength($snap->status === Snapchat::STATUS_DELIVERED && $r ? "(" . $i .")" : ((string) $i), 4, 3, true);
				$strings[$i] .= printlength(($r ? $snap->sender : $snap->recipient), 15, 4, true);
				$strings[$i] .= printlength($media[$snap->media_type], longestlength($media), 4, true);
				$strings[$i] .= printlength($status[$snap->status+1], longestlength($status), 4, true);
				$strings[$i] .= printlength(date("H:i", $snap->sent/1000), 5, 1, true);
				$strings[$i] .= printlength(!$r && $snap->status > 1 ? date("(H:i)", $snap->opened/1000) : "", 7, 4, true);
				//$strings[$i] .= $snap->id;
				$i++;
			}
		}
	}

	$strings = array_reverse($strings);
	$string = implode("\n", $strings);
	output($string);
	output("\n\n");
}

function openSnap($index) {
	global $scriptpath, $TYPE_RECEIVED, $single;

	$snap = getSnapByIndex($index, $TYPE_RECEIVED);
	if ($snap->status === Snapchat::STATUS_DELIVERED || time() - $snap->opened/1000 < $snap->time) {
		$extension = $snap->media_type === Snapchat::MEDIA_IMAGE || $snap->media_type === Snapchat::MEDIA_FRIEND_REQUEST_IMAGE ? ".jpg" : ".mov";
		$file = $scriptpath . "snaps/" . $snap->id . $extension;
		if (file_exists($file)) {
            if ($extension === ".mov") {
                openVideo($file, $snap->time);
            } else {
                openImage($file, $snap->time);
            }
			markread($snap->id);
			unlink($file);
			if (!$single) {
				update();
			}
		} else {
			output("Media not available");
			output("\n");
		}
	} else {
		output("Too late!");
		output("\n");
	}
}

function openImage($file, $time) {
    $cmd = "(";
    if (IMAGE_APPLICATION === "qlmanage") {
        $cmd .= "qlmanage -p ";
        $cmd .= $file;
        $cmd .= " & osascript -e 'tell application \"qlmanage\"' -e 'activate' -e 'end tell'";
    } else {
        $cmd .= IMAGE_APPLICATION . " ";
        $cmd .= $file;
    }
    $cmd .= " & sleep " . ($time + VIEWERAPPLICATION_STARTUP_TIME) . " && killall ";
    $cmd .= IMAGE_APPLICATION;
	$cmd .= ") 2> /dev/null";
	exec($cmd);
}

function openVideo($file, $time) {
    $cmd = "(";
    $cmd .= VIDEO_APPLICATION . " ";
    $cmd .= $file;
    $cmd .= " & sleep " . ($time + VIEWERAPPLICATION_STARTUP_TIME) . " && killall ";
    $cmd .= VIDEO_APPLICATION;
	$cmd .= ") 2> /dev/null";
	exec($cmd);
}

function markread($id, $update = false) {
	global $snapchat;
	
	$return = $snapchat->markSnapViewed($id);
	if ($update)
		update();
	return $return;
}

function getSnapByIndex($index, $type) {
	global $snaps;

	$i = 0;
	foreach ($snaps as $snap)
		if ($snap->media_type !== Snapchat::MEDIA_FRIEND_REQUEST && (empty($snap->media_id) xor ($type-1)) && $i++ == $index)
			return $snap;
}

function update() {
	global $snapchat, $snaps, $unread, $TYPE_RECEIVED, $single;


	if (!isOnline())
		return null;

	$count = 0;
	$snaps;
	$i = 0;
	do {
		$snaps = getSnaps();
	} while (!is_array($snaps) && $i++ < 5);

	if (!is_array($snaps) && isOnline())
		die("\nYou were logged out :O");
	else if (!isOnline())
		return null;

	cleanSnaps();

	foreach ($snaps as $snap)
		if (empty($snap->media_id) && $snap->status === Snapchat::STATUS_DELIVERED && $snap->media_type !== Snapchat::MEDIA_FRIEND_REQUEST)
			$count++;
	if ($unread != $count) {
		if ($count > $unread) {
			playSound("update");
			if ($single) {
				output("\n\n");
				printSnaps($TYPE_RECEIVED, true);
			}
		}
		$unread = $count;
		return true;
	} else
		return false;
}

function playSound($action) {
	switch ($action) {
		case "update":
			exec("afplay /System/Library/Sounds/Ping.aiff");
			break;
	}
}

function sendSnap($file, $to, $duration) {
	global $scriptpath, $snapchat, $snaps, $single;

	checkConnectivity();

	if (is_numeric($file)) {
		$n = $file;
		$file = getFileByNumber($n);
		if ($file === false) {
			output("Invalid file index: " . $n);
			output("\n");
			return;
		}
	}

	if ((!file_exists($file) && !stristr($file, "http://")) || is_dir($file)) {
		output("File not found!");
		output("\n");
		return;
	}

	$to = explode(",", $to);
	for ($i=0; $i<sizeof($to); $i++) {
		if (is_numeric($to[$i])) {
			$n = $to[$i];
			$to[$i] = getFriendFromNumber($n);
			if ($to[$i] === false) {
				output("Invalid friend index: " . $n);
				output("\n");
				return;
			}
		}
	}
	
	$extension = strtolower(substr($file, strrpos($file, ".")+1));
	if ($extension !== "jpg" && $extension !== "mov") {
		output("Wrong format!");
		output("\n");
		return;
	}

	output("Send " . substr($file, strrpos($file, "/")) . " to " . implode(", ", $to) . "? [Y/n]: ");
	$handle = fopen ("php://stdin","r");
	stream_set_blocking($handle, true);
	$line = trim(fgets($handle));
	fclose($handle);
	if ($line !== "y" && $line !== "Y" && $line !== "")
		return;

	$mediaType = $extension === "jpg" ? Snapchat::MEDIA_IMAGE : Snapchat::MEDIA_VIDEO;
	$id = $snapchat->upload(
    	$mediaType,
    	file_get_contents($file)
	);
	if (!$snapchat->send($id, $to, $duration)) {
		output("Failed to send snap :(");
		output("\n");
		return;
	}

	if (!$single) {
		update();
	}
}

function printFriends() {
	global $snapchat;

	$friends = $snapchat->getFriends();
	$i = 0;
	foreach ($friends as $friend) {
		output("\t" . $i . "\t" . getFriendFromNumber($i));
		output("\n");
		$i++;
	}
	output("\n");
}

function getFriendFromNumber($n) {
	global $snapchat;

	$friends = $snapchat->getFriends();
	$i = 0;
	foreach ($friends as $friend)
		if ($i++ == $n)
			return $friend->name;
	return false;
}

function changeDirectory($dir) {
	global $path;

	if (file_exists($dir)) {
		chdir($dir);
		$path = getcwd();
	}
}

function printFiles() {
	$files = scandir(".");
	$i = 0;
	foreach ($files as $file) {
		$extension = strtolower(substr($file, strrpos($file, ".")+1));
		if ($file{0} != "." && ($extension == "jpg" || $extension == "mov" || is_dir($file))) {
			output("\t" . $i++ . "\t" . $file);
			output("\n");
		}
	}
	output("\n");
}

function getFileByNumber($n) {
	$files = scandir(".");
	$i = 0;
	foreach ($files as $file) {
		$extension = strtolower(substr($file, strrpos($file, ".")+1));
		if ($file{0} != "." && ($extension == "jpg" || $extension == "mov" || is_dir($file)) && $i++ == $n)
			return $file;
	}
	return false;
}

function formatPath() {
	global $path;

	$return = "(";
	if (stristr($path, "/Users/" . get_current_user()))
		$return .= str_ireplace("/Users/" . get_current_user(), "~", $path);
	else
		$return .= $path;
	return $return . ")";
}

function isOnline() {
	$socket = @fsockopen("www.google.se", 80);
	return $socket && fclose($socket);
}

function checkConnectivity($dontreconnect = false) {
	$online = isOnline();
	if (!$online && !$dontreconnect) {
		output("\rNo internet connectivity :( Trying to reconnect...");
		reconnect();
	} else if (!$online)
		die(output("No internet connection"));
}

function reconnect() {
	sleep(RECONNECTION_SLEEP);
	if (isOnline()) {
		output("\r                                                                        ");
		output("\r");
		update();
	}
	else
		reconnect();
}

function output($string) {
	/*$width = getWidth();
	if (strlen($string) > $width)
		$string = substr($string, 0, $width-3) . "...";*/
	echo $string;
}

function getWidth() { 
	preg_match_all("/rows.([0-9]+);.columns.([0-9]+);/", strtolower(exec('stty -a |grep columns')), $output);
	if(sizeof($output) == 3)
		return $output[1][0];
}

?>
