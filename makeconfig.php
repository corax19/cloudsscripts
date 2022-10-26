<?php

include("dbconnect.php");
include("lib.php");

$sipnum=getChangedNum($pdo,"SIP");
$routnum=getChangedNum($pdo,"ROUTES");
$voicenum=getChangedNum($pdo,"VOICE");

echo "$sipnum $routnum $voicenum\n";

if($voicenum >0){
doSounds($pdo);
doMOHDirs($pdo);
system("/usr/sbin/asterisk -rx \"moh reload\"");
}

if($sipnum >0){
makeExten($pdo);
makeSIP($pdo);
system("/usr/sbin/asterisk -rx \"sip reload\"");
}

if($routnum >0){
makeRoutes($pdo);
system("/usr/sbin/asterisk -rx \"dialplan reload\"");
}

exit;



?>