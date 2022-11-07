#!/bin/sh


/usr/sbin/iptables -t filter -A INPUT -i enp0s3 -p udp --dport 5060 -j DROP
/usr/sbin/iptables -t filter -A INPUT -i enp0s3 -p tcp -m multiport --dports 80,443 -j DROP





