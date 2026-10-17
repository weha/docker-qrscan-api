# qrscan-api

POST an image to `/qrscan.php`, get the decoded QR codes back as JSON (tries qrscan, OpenCV, zbarimg).

## Run on podman (cPanel) behind a unix socket

The container has no network (`--network none`). Apache listens on port 80 inside the
container only; `socat` exposes it as `/run/qrscan/qrscan.sock`, which is bind-mounted
from the host. Nothing listens on a host port.

### Why rootful

Rootless podman does not work for a cPanel account on CloudLinux: `/proc` is mounted with
`hidepid=2` (runc fails with `readlink /proc/<pid>/fd/0`) and CageFS breaks crun
(`Failed to re-execute libcrun via memory file descriptor`). Building on the server fails
for the same reason, so build elsewhere and copy the image.

### Build (any machine with docker)

```sh
docker build -t qrscan-api -f dockerfile .
docker save qrscan-api -o qrscan-api.tar
scp qrscan-api.tar user@server:~/
```

### Run (as root on the server)

The uid/gid maps put the container's `www-data` (33) on the cPanel account (`id user`,
1000 below) so the socket is owned `user:user`; everything else in the container maps to
the unused 200000+ range. Port 80 needs `ip_unprivileged_port_start=0` because Apache runs as
`www-data`; with `--network none` that only affects the container's own network namespace.

```sh
podman load -i /home/user/qrscan-api.tar
mkdir -p /home/user/qrscan && chown user:user /home/user/qrscan && chmod 700 /home/user/qrscan
podman run -d --replace --name qrscan --restart=always --network none \
  --sysctl net.ipv4.ip_unprivileged_port_start=0 \
  --uidmap 0:200000:33 --uidmap 33:1000:1 --uidmap 34:200034:65000 \
  --gidmap 0:200000:33 --gidmap 33:1000:1 --gidmap 34:200034:65000 \
  -v /home/user/qrscan:/run/qrscan:Z \
  docker.io/library/qrscan-api
```

Start on boot:

```sh
cd /etc/systemd/system && podman generate systemd --new --name qrscan --files
systemctl daemon-reload && systemctl enable container-qrscan.service
```

Test (as `user`; a non-image must give a 415 JSON reply):

```sh
curl -s --unix-socket ~/qrscan/qrscan.sock -X POST --data-binary @some_qr.png http://localhost/qrscan.php
```
