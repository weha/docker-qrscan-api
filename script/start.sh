#!/bin/sh
# Expose Apache (listening inside the container only) on a unix socket in /run/qrscan,
# which is bind-mounted from the host so the ERP can reach it without an open port.
socat UNIX-LISTEN:/run/qrscan/qrscan.sock,fork,unlink-early,mode=660 TCP:127.0.0.1:80 &
exec apache2-foreground
