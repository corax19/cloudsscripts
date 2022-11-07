#!/bin/sh

#Sasha LTD
/usr/sbin/iptables -t filter -A INPUT -s 10.0.0.14 -i enp0s3 -p udp --dport 5060 -j ACCEPT
/usr/sbin/iptables -t filter -A INPUT -s 10.0.0.14 -i enp0s3 -p tcp -m multiport --dports 80,443 -j ACCEPT
#end of Sasha LTD

#Sasha LTD PBX
#end of Sasha LTD PBX

