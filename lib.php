<?php


function getChangedNum($pdo,$changedpart){
$res=0;

if($changedpart == "SIP"){
$stmt = $pdo->query("select count(*) as changed from extens where updated_at>subdate(now(),interval 60 second);");
while ($row = $stmt->fetch()) {
$res+=$row['changed'];
}


$stmt = $pdo->query("select count(*) as changed from sips where updated_at>subdate(now(),interval 60 second);");
while ($row = $stmt->fetch()) {
$res+=$row['changed'];
}

$stmt = $pdo->query("select count(*) as changed from logs where created_at>subdate(now(),interval 60 second) and (event like '%sip' or event like '%exten');");
while ($row = $stmt->fetch()) {
$res+=$row['changed'];
}



}

if($changedpart == "ROUTES"){
$stmt = $pdo->query("select count(*) as changed from routes where updated_at>subdate(now(),interval 60 second);");
while ($row = $stmt->fetch()) {
$res+=$row['changed'];
}


$stmt = $pdo->query("select count(*) as changed from steps where updated_at>subdate(now(),interval 60 second);");
while ($row = $stmt->fetch()) {
$res+=$row['changed'];
}

$stmt = $pdo->query("select count(*) as changed from logs where created_at>subdate(now(),interval 60 second) and (event like '%sip' or event like '%route' or event like '%step' or event like 'createstep%' or event like 'updatestep%');");
while ($row = $stmt->fetch()) {
$res+=$row['changed'];
}

}


if($changedpart == "VOICE"){
$stmt = $pdo->query("select count(*) as changed from sounds where updated_at>subdate(now(),interval 60 second);");
while ($row = $stmt->fetch()) {
$res+=$row['changed'];
}


$stmt = $pdo->query("select count(*) as changed from mohs where updated_at>subdate(now(),interval 60 second);");
while ($row = $stmt->fetch()) {
$res+=$row['changed'];
}


$stmt = $pdo->query("select count(*) as changed from logs where created_at>subdate(now(),interval 60 second) and (event like '%sound' or event like '%moh');");
while ($row = $stmt->fetch()) {
$res+=$row['changed'];
}

}


return $res;
}

function getCDRForRec($pdo){
$res = 0;
$stmt = $pdo->query("select id,concat('/var/www/html/records/',accountcode) as dir,concat('/var/www/html/records/',accountcode,'/',uniqueid,'.wav') as filesrc,concat('/var/www/html/records/',accountcode,'/',replace(substring(start,1,7),'-',''),'/',replace(substring(start,1,10),'-',''),'/',uniqueid,'.mp3') as filedst,concat('/var/www/html/records/',accountcode,'/',replace(substring(start,1,7),'-',''),'/',replace(substring(start,1,10),'-','')) as dirdst,concat('/var/www/html/records/',accountcode,'/',replace(substring(start,1,7),'-','')) as dirdst2,if(dcontext='pbxin' and userfield is null,0,1) as setdid,dst,(select max(id) from cdrs b where b.uniqueid=a.uniqueid) as maxid,(select unix_timestamp(a.end)-unix_timestamp(c.start) from cdrs c where c.uniqueid=a.uniqueid order by id asc limit 1) as durdiff from cdrs a where created_at>subdate(now(),interval 6 minute);");
while ($row = $stmt->fetch()) {


 $id=$row['id'];
 $maxid=$row['maxid'];
 $durdiff=$row['durdiff'];
 $setdid=$row['setdid'];
 $dst=$row['dst'];
 if ($setdid == 0 ) {
 if ($id == $maxid ){
 $stmtdid = $pdo->query("update cdrs set duration=duration+$durdiff,billsec=billsec+$durdiff,userfield=dst,dst=(select number from sips where id=$dst) where id=$id limit 1;");
 } else {
 $stmtdid = $pdo->query("update cdrs set userfield=uniqueid,uniqueid='',accountcode=0 where id=$id limit 1;");
 }
 }

 $dir=$row['dir'];
 $filesrc=$row['filesrc'];
 $filedst=$row['filedst'];
 $dirdst=$row['dirdst'];
 $dirdst2=$row['dirdst2'];
 if((is_file($filesrc))  && (!is_file($filedst))){
  if(!is_dir($dirdst2)){mkdir($dirdst2);}
  if(!is_dir($dirdst)){mkdir($dirdst);}
  $cmd="/usr/bin/lame -h -b 16  \"".$filesrc."\" ".$filedst." &";
  exec($cmd);
  $cmd="/usr/bin/lame -h -b 16  \"".$filesrc."\" ".$filedst." &";
  exec($cmd);
  $cmd="rm -f \"".$filesrc."\"";system($cmd);
 }
}





return $res;
}

function buildTables($pdo,$ifname){
$res = 0;
$astbasedir="/root/pbxscripts/";
$filename="bodytables.sh";
$fileh=fopen($astbasedir.$filename,"w+");
$headstr="#!/bin/sh\n\n";fputs($fileh,$headstr);

$stmt = $pdo->query("select name,sipips,webips from accounts");
while ($row = $stmt->fetch()) {
 $name=$row['name'];
 $sipips=$row['sipips'];
 $webips=$row['webips'];
$headstr="#$name\n";fputs($fileh,$headstr);
$arrsip=explode("\n",$sipips);
$arrweb=explode("\n",$webips);
foreach ($arrsip as $value) {
if(strlen($value)>0){
$headstr="/usr/sbin/iptables -t filter -A INPUT -s ".trim($value)." -i $ifname -p udp --dport 5060 -j ACCEPT\n";
fputs($fileh,$headstr);
}
}

foreach ($arrweb as $value) {
if(strlen($value)>0){
$headstr="/usr/sbin/iptables -t filter -A INPUT -s ".trim($value)." -i $ifname -p tcp -m multiport --dports 80,443 -j ACCEPT\n";
fputs($fileh,$headstr);
}
}

#iptables -t filter -A INPUT -s 80.92.189.250 -i ens160 -p tcp -m multiport --dports 80,443 -j ACCEPT
#iptables -t filter -A INPUT -s 94.43.149.30 -i ens160 -p udp --dport 5060 -j ACCEPT
#echo "$name $sipips $webips\n";
$headstr="#end of $name\n\n";fputs($fileh,$headstr);
}

fclose($fileh);
exec("chmod 755 /root/pbxscripts/iptables.sh");
exec("/root/pbxscripts/iptables.sh");
return $res;
}


function makeExten($pdo){
$astbasedir="/root/pbxscripts/astconf/";
$filename="extens.conf";
$fileh=fopen($astbasedir.$filename,"w+");
$stmt = $pdo->query("select a.exten,a.secret,a.account_id,b.sipid,a.record,a.calllimit,a.webrtc,a.name from extens a left join sips b on a.sip_id=b.id;");
while ($row = $stmt->fetch()) {
$exten=$row['exten'];
$secret=$row['secret'];
$account_id=$row['account_id'];
$sipid=$row['sipid'];
$webrtc=$row['webrtc'];
$myname=$row['name'];
$inhouse=0;
if($inhouse == 1){
$extenid=$exten;
} else {
$extenid=$account_id.$exten;
}

$record=$row['record'];
$calllimit=$row['calllimit'];


//echo "$exten $secret $account_id $sipid\n";

$extenstr="
[$extenid]
type=friend
accountcode=$extenid
defaultuser=$extenid
callerid=$exten
username=$extenid
host=dynamic
secret=$secret
setvar=sipid=$sipid
setvar=myname=$myname
setvar=accountid=$account_id
setvar=record=$record
call-limit=$calllimit
busylevel=$calllimit
dtmfmode=rfc2833
insecure=invite,port
canreinvite=yes
nat=force_rport,comedia
qualify=yes
context=pbxout
disallow=all
allow=ulaw
allow=alaw
";
if($webrtc == "Yes"){
$extenstr.="rtcp_mux=yes
avpf=yes
icesupport=yes
dtlsenable=yes
dtlsverify=no
dtlsrekey=1160
dtlscafile = /etc/asterisk/keys/ssl_certificate.crt
dtlscertfile = /etc/asterisk/keys/ssl_certificate.pem
dtlssetup=actpass
";
}

fputs($fileh,$extenstr);
//echo "$extenstr\n";

}

fclose($fileh);
}

function makeSIP($pdo){
$astbasedir="/root/pbxscripts/astconf/";
$filename1="sips.conf";
$filename2="registartions.conf";
$fileh1=fopen($astbasedir.$filename1,"w+");
$fileh2=fopen($astbasedir.$filename2,"w+");

$stmt = $pdo->query("select id,sipid,secret, host,number,account_id from sips;");
while ($row = $stmt->fetch()) {
$id=$row['id'];
$number=$row['number'];
$host=$row['host'];
$secret=$row['secret'];
$account_id=$row['account_id'];
$sipid=$row['sipid'];


$sipstr=";Number $number Account $account_id
[$sipid]
type=friend
host=dynamic
username=$sipid
accountcode=$sipid
insecure=port,invite
secret=$secret
context=pbxin
fromuser=$sipid
fromdomain=$host
host=$host
port=5060
nat=force_rport,comedia
canreinvite=no
qualify=yes
dtmfmode=rfc2833
disallow=all
allow=ulaw
allow=alaw

";

$regstr="register => $sipid:$secret:$sipid@$host/$id\n";
//echo "$sipstr\n$regstr\n";
fputs($fileh1,$sipstr);
fputs($fileh2,$regstr);
}

fclose($fileh1);
fclose($fileh2);
}


function rrmdir($dir)
{
 if (is_dir($dir))
 {
  $objects = scandir($dir);

  foreach ($objects as $object)
  {
   if ($object != '.' && $object != '..')
   {
    if (filetype($dir.'/'.$object) == 'dir') {rrmdir($dir.'/'.$object);}
    else {unlink($dir.'/'.$object);}
   }
  }

  reset($objects);
  rmdir($dir);
 }
}


function doMOHDirs($pdo){
$mohdirs=array();
$mohbasedir="/root/pbxscripts/mohs/";

$astbasedir="/root/pbxscripts/astconf/";
$filename1="mohs.conf";
$fileh1=fopen($astbasedir.$filename1,"w+");


$stmt = $pdo->query("select id,name,account_id from mohs;");
while ($row = $stmt->fetch()) {
$id=$row['id'];
$name=$row['name'];
$account_id=$row['account_id'];
$mohdir=$account_id."_".$id;
$mohdirs[$mohdir]=array($id,$account_id);
}


$files1 = scandir($mohbasedir);
foreach ($files1 as $key=>$value) {
if(($value !=".") && ($value !="..")){
if(!isset($mohdirs[$value])){
rrmdir($mohbasedir.$value);
}
}
}

foreach ($mohdirs as $key=>$value) {
if(!is_dir($mohbasedir.$key)){
mkdir($mohbasedir.$key);
}
doMOHFiles($pdo,$value[0],$value[1]);

$str=";
[$key]
mode=files
directory=$mohbasedir"."$key
sort=random

";

$mohstr = $str;
fputs($fileh1,$mohstr);
}

fclose($fileh1);
}



function doMOHFiles($pdo,$moh_id,$accountid){
$mohdirs=array();
$mohdir=$accountid."_".$moh_id;
$mohbasedir="/root/pbxscripts/mohs/";
$soundbasedir="/var/www/html/clouds/public/uploads/sound/audio/";
$stmt = $pdo->prepare("select id,audio,account_id from sounds where id in(select sound_id from moh_entries where moh_id=?)");
$stmt->execute([$moh_id]);
$dstfiles=array();
while ($row = $stmt->fetch()) {
$id=$row['id'];
$audio=$row['audio'];
$account_id=$row['account_id'];

$filesrc=$soundbasedir.$id."/"."$audio";
$filesrcdst=$mohbasedir.$mohdir."/".$id."_tmp.wav";
$cmd1="ffmpeg -i $filesrc $filesrcdst";
$filedst=$mohbasedir.$mohdir."/".$id.".wav";
$cmd2="sox $filesrcdst  -b 16 -r 8000 -c 1 $filedst";
$cmd3="rm $filesrcdst";
if(!is_file($filedst)){
exec($cmd1);
exec($cmd2);
exec($cmd3);
}
$dstfiles[$filedst]=1;
}

$files1 = scandir($mohbasedir.$mohdir);
foreach ($files1 as $key=>$value) {
if(($value !=".") && ($value !="..")){
if(!isset($dstfiles[$mohbasedir.$mohdir."/".$value])){
unlink($mohbasedir.$mohdir."/".$value);
}
}
}

}



function doSounds($pdo){
$mohbasedir="/root/pbxscripts/pbxsounds/";
$soundbasedir="/var/www/html/clouds/public/uploads/sound/audio/";
$stmt = $pdo->prepare("select id,audio,account_id from sounds");
$stmt->execute([]);
$dstfiles=array();
while ($row = $stmt->fetch()) {
$id=$row['id'];
$audio=$row['audio'];
$account_id=$row['account_id'];

$filesrc=$soundbasedir.$id."/"."$audio";
$filesrcdst=$mohbasedir.$account_id."_".$id."_tmp.wav";
$cmd1="ffmpeg -i $filesrc $filesrcdst";
$filedst=$mohbasedir."/".$account_id."_".$id.".wav";
$cmd2="sox $filesrcdst  -b 16 -r 8000 -c 1 $filedst";
$cmd3="rm $filesrcdst";

if(!is_file($filedst)){
exec($cmd1);
exec($cmd2);
exec($cmd3);
}

$dstfiles[$filedst]=1;
}

$files1 = scandir($mohbasedir);
foreach ($files1 as $key=>$value) {
if(($value !=".") && ($value !="..")){
if(!isset($dstfiles[$mohbasedir."/".$value])){
unlink($mohbasedir."/".$value);
}
}
}

}








function makeRoutes($pdo){
$astbasedir="/root/pbxscripts/astconf/";
$filename1="pbxin.conf";
$fileh1=fopen($astbasedir.$filename1,"w+");
$sipstr=";
[pbxin]
";

//echo "$sipstr\n$regstr\n";
fputs($fileh1,$sipstr);

$stmt = $pdo->query("select id,sipid,secret, host,number,account_id from sips;");
while ($row = $stmt->fetch()) {
$id=$row['id'];
$number=$row['number'];
$host=$row['host'];
$secret=$row['secret'];
$account_id=$row['account_id'];
$sipid=$row['sipid'];


$sipstr=";Number $number Account $account_id
exten => _$id,1,Wait(0.25)
exten => _$id,n,Set(CDR(accountcode)=${account_id})
exten => _$id,n,Set(CHANNEL(accountcode)=${account_id})
exten => _$id,n,Macro(checkcaller,\${CALLERID(NUMBER)},${account_id})
exten => _$id,n,Answer()
";
$confstr="";
fputs($fileh1,$sipstr);
$stmt1 = $pdo->prepare("select id,name,record,day,daystart,daystop,hourstart,hourstop from routes where sip_id=? order by day desc,name;");
$stmt1->execute([$id]);
while ($row1 = $stmt1->fetch()) {
$routeid=$row1['id'];
$name=$row1['name'];
$day=$row1['day'];
$daystart=$row1['daystart'];
$daystop=$row1['daystop'];
$hourstart=$row1['hourstart'];
$hourstop=$row1['hourstop'];

$record=$row1['record'];
if((strlen($name)==0) || ($name == "DEFAULT") ){
if(strlen($day)==0){
$sipstr="exten => _$id,n,GotoIfTime($hourstart:00-$hourstop:00,$daystart-$daystop,*,*?routeitem".$routeid.")\n";
fputs($fileh1,$sipstr);
}

if(strlen($day)>0){
$sipstr="exten => _$id,n,GotoIfTime($hourstart:00-$hourstop:00,$daystart-$daystop,".date("j",strtotime($day)).",".strtolower(date("M",strtotime($day)))."?routeitem".$routeid.")\n";
fputs($fileh1,$sipstr);
}
}


$confstr.="exten => _$id,n(routeitem".$routeid."),NoOp\n";
if($record == "Yes"){
$confstr.="exten => _$id,n,Set(MONITORFILE=/var/www/html/records/".$account_id."/\${CDR(UNIQUEID)})\n";
$confstr.="exten => _$id,n,MixMonitor(\${MONITORFILE}.wav,b)\n";

}
$stmt2 = $pdo->prepare("select id,event,data from steps where route_id=? order by stepnum;");
$stmt2->execute([$routeid]);
while ($row2 = $stmt2->fetch()) {
$stepid=$row2['id'];
$event=$row2['event'];
$data=$row2['data'];

$jsonsarr=json_decode($data);
//echo "$stepid\n";
//print_r($jsonsarr);
//$confstr.="exten => _$id,n,$event $data\n";
if($event=="Playback"){
$playbacksound="pbxsounds/".$account_id."_".$jsonsarr[0][1];
$confstr.="exten => _$id,n,Macro(playback,$playbacksound)\n";
}

if($event=="Voicemail"){
$inhouse=0;
if($inhouse == 1){
$vmbox=substr($jsonsarr->vmbox,4);
} else {
$vmbox=$jsonsarr->vmbox;
}
$vmoptions=$jsonsarr->options;
$confstr.="exten => _$id,n,Macro(voicemaili,$vmbox,$vmoptions)\n";
}

if($event=="RingGroup"){
$extens=$jsonsarr->extens;
$timeout=$jsonsarr->timeout;
$options=$jsonsarr->options;
$moh_id=$jsonsarr->moh_id;
if($moh_id != "" ){
$options=$options."m(".$jsonsarr->mohclass.")";
}

$inhouse=0;
if($inhouse == 1){
$sipextens="SIP/".str_replace("\r\n", "&SIP/",$extens);
} else {
$sipextens="SIP/".$account_id.str_replace("\r\n", "&SIP/",$account_id, $extens);
}

$confstr.="exten => _$id,n,Macro(dialringgroup,$sipextens,$timeout,$options)\n";
}

if($event=="Menu"){
$menuitem="routeitem".$jsonsarr[0][1];
//$confstr.="exten => _$id,n,Macro(gotomenu,$menuitem)\n";
$confstr.="exten => _$id,n,Goto($menuitem)\n";
}

if($event=="Queue"){
//$queuename=$account_id."_".$jsonsarr[0][1];
$queuename=$jsonsarr[2][1];
$queueoptions=$jsonsarr[1][1];

$stmtq = $pdo->prepare("select maxtime from hotlines where name=?;");
$stmtq->execute([$queuename]);
$rowq = $stmtq->fetch();
$maxtime=$rowq['maxtime'];
if($maxtime == 0){$maxtime="7200";}
if(strlen($maxtime) == 0){$maxtime="7200";}

$confstr.="exten => _$id,n,Macro(queue,$queuename,$queueoptions,$maxtime)\n";
}


if($event=="Dial"){
$inhouse=0;
if($inhouse == 1){
$exten_num=$jsonsarr[3][1];
} else {
$exten_num=$account_id.$jsonsarr[3][1];
}

$dialtimeout=$jsonsarr[1][1];
$dialoptions=$jsonsarr[2][1];
$confstr.="exten => _$id,n,Macro(dialexten,$exten_num,$dialtimeout,$dialoptions)\n";
}

if($event=="ExternalDial"){
$external_num=$jsonsarr[0][1];
$sipid=$jsonsarr[4][1];
$dialtimeout=$jsonsarr[2][1];
$dialoptions=$jsonsarr[3][1];
$confstr.="exten => _$id,n,Macro(dialexternal,$external_num,$sipid,$dialtimeout,$dialoptions)\n";
}



if($event=="Read"){
$playbacksound="pbxsounds/".$account_id."_".$jsonsarr[0][1];
$maxlen=$jsonsarr[1][1];
$timeout=$jsonsarr[2][1];


$read0=$jsonsarr[3][1];
$read1=$jsonsarr[4][1];
$read2=$jsonsarr[5][1];
$read3=$jsonsarr[6][1];
$read4=$jsonsarr[7][1];

$read5=$jsonsarr[8][1];
$read6=$jsonsarr[9][1];
$read7=$jsonsarr[10][1];
$read8=$jsonsarr[11][1];
$read9=$jsonsarr[12][1];

$inhouse=0;
$account_id_tmp=$account_id;
if($inhouse == 1){$account_id="";}

if(substr($read0,0,6) == "Exten_" ){
$mread01="Dial";
$mread02=$account_id.substr($read0,6);
} elseif(substr($read0,0,6) == "Queue_" ){
$mread01="Queue";
$mread02=$account_id."_".substr($read0,6);
} elseif(substr($read0,0,5) == "Menu_" ){
$mread01="Menu";
//$mread02=substr($read0,5);
$mread02=$jsonsarr[3][2];
} else {
$mread01="";
$mread02="";
}

if(substr($read1,0,6) == "Exten_" ){
$mread11="Dial";
$mread12=$account_id.substr($read1,6);
} elseif(substr($read1,0,6) == "Queue_" ){
$mread11="Queue";
$mread12=$account_id."_".substr($read1,6);
} elseif(substr($read1,0,5) == "Menu_" ){
$mread11="Menu";
//$mread12=substr($read1,5);
$mread12=$jsonsarr[4][2];
} else {
$mread11="";
$mread12="";
}


if(substr($read2,0,6) == "Exten_" ){
$mread21="Dial";
$mread22=$account_id.substr($read2,6);
} elseif(substr($read2,0,6) == "Queue_" ){
$mread21="Queue";
$mread22=$account_id."_".substr($read2,6);
} elseif(substr($read2,0,5) == "Menu_" ){
$mread21="Menu";
//$mread22=substr($read2,5);
$mread22=$jsonsarr[5][2];
} else {
$mread21="";
$mread22="";
}


if(substr($read3,0,6) == "Exten_" ){
$mread31="Dial";
$mread32=$account_id.substr($read3,6);
} elseif(substr($read3,0,6) == "Queue_" ){
$mread31="Queue";
$mread32=$account_id."_".substr($read3,6);
} elseif(substr($read3,0,5) == "Menu_" ){
$mread31="Menu";
//$mread32=substr($read3,5);
$mread32=$jsonsarr[6][2];
} else {
$mread31="";
$mread32="";
}


if(substr($read4,0,6) == "Exten_" ){
$mread41="Dial";
$mread42=$account_id.substr($read4,6);
} elseif(substr($read4,0,6) == "Queue_" ){
$mread41="Queue";
$mread42=$account_id."_".substr($read4,6);
} elseif(substr($read4,0,5) == "Menu_" ){
$mread41="Menu";
//$mread42=substr($read4,5);
$mread42=$jsonsarr[7][2];
} else {
$mread41="";
$mread42="";
}


if(substr($read5,0,6) == "Exten_" ){
$mread51="Dial";
$mread52=$account_id.substr($read5,6);
} elseif(substr($read5,0,6) == "Queue_" ){
$mread51="Queue";
$mread52=$account_id."_".substr($read5,6);
} elseif(substr($read5,0,5) == "Menu_" ){
$mread51="Menu";
//$mread52=substr($read5,5);
$mread52=$jsonsarr[8][2];
} else {
$mread51="";
$mread52="";
}



if(substr($read6,0,6) == "Exten_" ){
$mread61="Dial";
$mread62=$account_id.substr($read6,6);
} elseif(substr($read6,0,6) == "Queue_" ){
$mread61="Queue";
$mread62=$account_id."_".substr($read6,6);
} elseif(substr($read6,0,5) == "Menu_" ){
$mread61="Menu";
//$mread62=substr($read6,5);
$mread62=$jsonsarr[9][2];
} else {
$mread61="";
$mread62="";
}


if(substr($read7,0,6) == "Exten_" ){
$mread71="Dial";
$mread72=$account_id.substr($read7,6);
} elseif(substr($read7,0,6) == "Queue_" ){
$mread71="Queue";
$mread72=$account_id."_".substr($read7,6);
} elseif(substr($read7,0,5) == "Menu_" ){
$mread71="Menu";
//$mread72=substr($read7,5);
$mread72=$jsonsarr[10][2];
} else {
$mread71="";
$mread72="";
}


if(substr($read8,0,6) == "Exten_" ){
$mread81="Dial";
$mread82=$account_id.substr($read8,6);
} elseif(substr($read8,0,6) == "Queue_" ){
$mread81="Queue";
$mread82=$account_id."_".substr($read8,6);
} elseif(substr($read8,0,5) == "Menu_" ){
$mread81="Menu";
//$mread62=substr($read6,5);
$mread82=$jsonsarr[11][2];
} else {
$mread81="";
$mread82="";
}


if(substr($read9,0,6) == "Exten_" ){
$mread91="Dial";
$mread92=$account_id.substr($read9,6);
} elseif(substr($read9,0,6) == "Queue_" ){
$mread91="Queue";
$mread92=$account_id."_".substr($read9,6);
} elseif(substr($read9,0,5) == "Menu_" ){
$mread91="Menu";
//$mread72=substr($read7,5);
$mread92=$jsonsarr[12][2];
} else {
$mread91="";
$mread92="";
}


$account_id=$account_id_tmp;

$confstr.="exten => _$id,n,Macro(mread,$playbacksound,$maxlen,$timeout,$mread01,$mread02,$mread11,$mread12,$mread21,$mread22,$mread31,$mread32,$mread41,$mread42,$mread51,$mread52,$mread61,$mread62,$mread71,$mread72,$mread81,$mread82,$mread91,$mread92,$id,$account_id)\n";
}

}

$confstr.="exten => _$id,n,Hangup\n";
//fputs($fileh1,$sipstr);

}
$sipstr="exten => _$id,n,Hangup\n";
fputs($fileh1,$sipstr);

fputs($fileh1,$confstr);

//$sipstr="exten => _$id,n,Hangup\n";fputs($fileh1,$sipstr);

//echo "$sipstr\n$regstr\n";

}


fclose($fileh1);
}

?>