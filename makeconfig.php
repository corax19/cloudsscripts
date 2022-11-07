<?php

include("dbconnect.php");
include("lib.php");

$maxn = 0;
if($argv[1] == "force" ){$maxn = -1;}

$sipnum=getChangedNum($pdo,"SIP");
$routnum=getChangedNum($pdo,"ROUTES");
$voicenum=getChangedNum($pdo,"VOICE");

getCDRForRec($pdo);

echo "$sipnum $routnum $voicenum\n";

if($voicenum >$maxn){
doSounds($pdo);
doMOHDirs($pdo);
system("/usr/sbin/asterisk -rx \"moh reload\"");
}

if($sipnum >$maxn){
makeExten($pdo);
makeSIP($pdo);
system("/usr/sbin/asterisk -rx \"sip reload\"");
}

if($routnum >$maxn){
makeRoutes($pdo);
system("/usr/sbin/asterisk -rx \"dialplan reload\"");
}

exit;



?>