#!/bin/bash

sudo /sbin/iptables -I INPUT -s 192.168.1.33 -j DROP
echo Done.
