#!/usr/bin/env python3
import argparse
import json
import platform
import re
import signal
import socket
import subprocess
import sys
import time
from http.server import BaseHTTPRequestHandler, HTTPServer

WOL_MACS = {
    '192.168.248.25:5150': '40:A8:F0:4E:3D:D4',   # PVE-Node-1
    '192.168.248.30:5150': 'EC:B1:D7:5E:75:92',   # PVE-Node-2
    '192.168.248.2:5150':  'EC:B1:D7:50:BC:29',   # PVE-Node-3
}

WOL_SECRET = 'echo-wol-secret'

IS_WINDOWS = platform.system().lower() == 'windows'

def get_local_ip() -> str:
    with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as s:
        s.connect(("8.8.8.8", 80))
        return s.getsockname()[0]


def build_packet(mac: str) -> bytes:
    clean = re.sub(r'[^0-9A-Fa-f]', '', mac)
    if len(clean) != 12:
        raise ValueError(f"Invalid MAC '{mac}'")
    mac_bytes = bytes(int(clean[i:i+2], 16) for i in range(0, 12, 2))
    return b'\xff' * 6 + mac_bytes * 16


def send_wol(mac: str, target_ip: str) -> dict:
    try:
        packet = build_packet(mac)
    except ValueError as e:
        return {'ok': False, 'error': str(e)}

    fmt_mac = ':'.join(re.sub(r'[^0-9A-Fa-f]', '', mac)[i:i+2].upper()
                       for i in range(0, 12, 2))

    subnet_bc = '.'.join(target_ip.split('.')[:3]) + '.255'
    destinations = [
        (subnet_bc,        9),
        (subnet_bc,        7),
        ('255.255.255.255', 9),
        (target_ip,        9),
    ]

    sent_ok, sent_err = [], []
    for dest_ip, dest_port in destinations:
        try:
            with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as s:
                s.setsockopt(socket.SOL_SOCKET, socket.SO_BROADCAST, 1)
                s.connect((dest_ip, dest_port))
                s.send(packet)
            sent_ok.append(f"{dest_ip}:{dest_port}")
        except OSError as e:
            sent_err.append(f"{dest_ip}:{dest_port} ({e})")

    if sent_ok:
        print(f"[WoL] {fmt_mac} → {', '.join(sent_ok)}")
        return {'ok': True, 'mac': fmt_mac, 'sent_to': sent_ok}

    return {'ok': False, 'error': 'All destinations failed: ' + ', '.join(sent_err)}


def ping(ip: str) -> bool:
    """Returns True if the machine responds to ping."""
    try:
        if IS_WINDOWS:
            cmd = ['ping', '-n', '1', '-w', '1000', ip]
        else:
            cmd = ['ping', '-c', '1', '-W', '1', ip]
        result = subprocess.run(cmd, stdout=subprocess.DEVNULL,
                                     stderr=subprocess.DEVNULL, timeout=3)
        return result.returncode == 0
    except Exception:
        return False


class Handler(BaseHTTPRequestHandler):

    def log_message(self, fmt, *args):
        pass  # suppress default log

    def _json(self, code: int, data: dict):
        body = json.dumps(data).encode()
        self.send_response(code)
        self.send_header('Content-Type', 'application/json')
        self.send_header('Content-Length', str(len(body)))
        self.send_header('Access-Control-Allow-Origin', '*')
        self.end_headers()
        self.wfile.write(body)

    def _check_secret(self) -> bool:
        if self.headers.get('X-WoL-Secret', '') != WOL_SECRET:
            print(f"[Auth] Rejected — bad secret from {self.client_address[0]}")
            self._json(403, {'error': 'Invalid secret'})
            return False
        return True

    def _read_body(self):
        length = int(self.headers.get('Content-Length', 0))
        try:
            return json.loads(self.rfile.read(length)) if length else {}
        except Exception:
            self._json(400, {'error': 'Invalid JSON'})
            return None

    def do_OPTIONS(self):
        self.send_response(204)
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Headers', 'Content-Type, X-WoL-Secret')
        self.end_headers()

    def do_GET(self):
        if self.path == '/ping':
            self._json(200, {'ok': True, 'service': 'echo-wakeonlan-agent',
                             'machines': list(WOL_MACS.keys())})
        elif self.path.startswith('/status'):
            ip = ''
            if '?' in self.path:
                for part in self.path.split('?', 1)[1].split('&'):
                    if part.startswith('ip='):
                        ip = part[3:]
            if not ip:
                self._json(400, {'error': 'Missing ip'})
                return
            online = ping(ip)
            print(f"[Status] {ip} → {'online' if online else 'offline'}")
            self._json(200, {'ok': True, 'ip': ip,
                             'status': 'online' if online else 'offline'})
        else:
            self._json(404, {'error': 'not found'})

    def do_POST(self):
        if not self._check_secret():
            return

        body = self._read_body()
        if body is None:
            return

        if self.path == '/wol':
            node_key = body.get('key', '')
            if not node_key:
                self._json(400, {'error': 'Missing key'})
                return
            if node_key not in WOL_MACS:
                self._json(404, {'ok': False,
                    'error': f"No MAC for '{node_key}'. Add it to WOL_MACS in wakeonlan.py."})
                return
            target_ip = node_key.split(':')[0]
            self._json(200, send_wol(WOL_MACS[node_key], target_ip))
        else:
            self._json(404, {'error': 'not found'})


def main():
    p = argparse.ArgumentParser(description='echo Wake-on-LAN Agent')
    p.add_argument('--serve', action='store_true',         help='Run as HTTP server instead of one-shot')
    p.add_argument('--port',  type=int, default=9999,      help='HTTP port (default 9999)')
    p.add_argument('--bind',  type=str, default='127.0.0.1', help='Bind address')
    args = p.parse_args()

    if not args.serve:
        # One-shot: wake all nodes and exit
        source_ip = get_local_ip()
        print(f"\nSending magic packets from {source_ip}\n")
        for key, mac in WOL_MACS.items():
            ip = key.split(':')[0]
            send_wol(mac, ip)
            time.sleep(0.1)
        print("\nDone. Give nodes ~30s to boot.\n")
        return

    # Server mode
    print(f"\n  echo Wake-on-LAN Agent  ({'Windows' if IS_WINDOWS else 'Linux'})")
    print(f"  {'─'*46}")
    print(f"  Listening : http://{args.bind}:{args.port}")
    print(f"  Machines  : {len(WOL_MACS)}")
    for key, mac in WOL_MACS.items():
        ip = key.split(':')[0]
        bc = '.'.join(ip.split('.')[:3]) + '.255'
        print(f"    {key:28s}  {mac}  →  {bc}")
    print()

    try:
        with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as s:
            s.connect(("8.8.8.8", 80))
            my_ip = s.getsockname()[0]
        my_sub = '.'.join(my_ip.split('.')[:3])
        for key in WOL_MACS:
            tgt_ip  = key.split(':')[0]
            tgt_sub = '.'.join(tgt_ip.split('.')[:3])
            if my_sub != tgt_sub:
                print(f"  !! WARNING: This machine ({my_ip}) is on {my_sub}.0/24")
                print(f"  !!          but target {tgt_ip} is on {tgt_sub}.0/24")
                print(f"  !!          WoL packets WON'T cross subnets.")
                print(f"  !!          Run this script on a machine in {tgt_sub}.0/24")
                print()
    except Exception:
        pass

    srv = HTTPServer((args.bind, args.port), Handler)

    def stop(sig, frame):
        print('\n[INFO] Stopping...')
        srv.server_close()
        sys.exit(0)

    signal.signal(signal.SIGINT,  stop)
    signal.signal(signal.SIGTERM, stop)
    srv.serve_forever()


if __name__ == '__main__':
    main()