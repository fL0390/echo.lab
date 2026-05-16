#!/usr/bin/env python3
import argparse
import json
import os
import platform
import signal
import socket
import subprocess
import sys
import threading
import time
import uuid
from http.server import HTTPServer, BaseHTTPRequestHandler

try:
    import psutil
except ImportError:
    print("[!] psutil required:  pip3 install psutil")
    sys.exit(1)

try:
    import requests as req_lib
except ImportError:
    print("[!] requests required:  pip3 install requests")
    sys.exit(1)

# Globals

running    = True
http_port  = 5150
secret     = "echo-node-secret"
server_url = ""

# Stats

_stats      = {}
_stats_lock = threading.Lock()


def get_ip():
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.connect(("8.8.8.8", 80))
        ip = s.getsockname()[0]
        s.close()
        return ip
    except Exception:
        return "127.0.0.1"


def get_uptime():
    try:
        d = int(time.time() - psutil.boot_time())
        days, r  = divmod(d, 86400)
        hrs, r   = divmod(r, 3600)
        mins, _  = divmod(r, 60)
        p = []
        if days: p.append(f"{days}d")
        if hrs:  p.append(f"{hrs}h")
        p.append(f"{mins}m")
        return " ".join(p)
    except Exception:
        return "-"


def get_mac():
    try:
        ip = get_ip()
        for name, addrs in psutil.net_if_addrs().items():
            found_ip = False
            mac = None
            for addr in addrs:
                if addr.family == socket.AF_INET and addr.address == ip:
                    found_ip = True
                if addr.family == psutil.AF_LINK:
                    mac = addr.address
            if found_ip and mac and mac != '00:00:00:00:00:00':
                return mac.upper()
    except Exception:
        pass
    try:
        h = uuid.getnode()
        return ':'.join(f'{(h >> (8 * (5 - i))) & 0xFF:02X}' for i in range(6))
    except Exception:
        return '—'


def collect_stats():
    cpu  = psutil.cpu_percent(interval=1)
    ram  = psutil.virtual_memory()
    disk = psutil.disk_usage("/")
    net  = psutil.net_io_counters()
    return {
        "node_id":       socket.gethostname(),
        "hostname":      socket.gethostname(),
        "ip":            get_ip(),
        "mac":           get_mac(),
        "os":            f"{platform.system()} {platform.release()}",
        "uptime":        get_uptime(),
        "cpu_percent":   round(cpu, 1),
        "ram_percent":   round(ram.percent, 1),
        "ram_used_gb":   round(ram.used  / (1024**3), 2),
        "ram_total_gb":  round(ram.total / (1024**3), 2),
        "disk_percent":  round(disk.percent, 1),
        "disk_used_gb":  round(disk.used  / (1024**3), 2),
        "disk_total_gb": round(disk.total / (1024**3), 2),
        "net_sent_mb":   round(net.bytes_sent / (1024**2), 1),
        "net_recv_mb":   round(net.bytes_recv / (1024**2), 1),
        "port":          http_port,
        "timestamp":     time.time(),
    }


def stats_loop():
    global _stats
    while running:
        try:
            data = collect_stats()
            with _stats_lock:
                _stats = data
        except Exception as e:
            print(f"[WARN] Stats collection error: {e}")
        for _ in range(2):
            if not running:
                return
            time.sleep(1)



def register_with_server():
    if not server_url:
        return
    url = server_url.rstrip("/") + "/api/nodes_manage.php"
    ip  = get_ip()
    try:
        r = req_lib.post(url, json={
            "action":   "register",
            "ip":       ip,
            "port":     http_port,
            "hostname": socket.gethostname(),
            "secret":   secret,
        }, timeout=5)

        if r.status_code == 200:
            print(f"[OK] Registered with echo server ({server_url})")
        else:
            try:
                body = r.json()
                err  = body.get('error', '')
                hint = body.get('hint', '')
                msg  = f"HTTP {r.status_code} — {err}"
                if hint:
                    msg += f"\n       Hint: {hint}"
            except Exception:
                msg = f"HTTP {r.status_code} — {r.text[:200]}"
            print(f"[WARN] Server registration failed: {msg}")

    except req_lib.exceptions.ConnectionError:
        print(f"[WARN] Cannot reach server at {url} — is php -S running?")
    except req_lib.exceptions.Timeout:
        print(f"[WARN] Server timed out ({url})")
    except Exception as e:
        print(f"[WARN] Registration error: {e}")


def register_loop():
    """Re-register every 30 s so the server knows we're still alive."""
    while running:
        register_with_server()
        for _ in range(30):
            if not running:
                return
            time.sleep(1)


# HTTP server

class Handler(BaseHTTPRequestHandler):

    def log_message(self, fmt, *args):
        pass  # silent

    def _cors(self):
        self.send_header("Access-Control-Allow-Origin",  "*")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type, X-Node-Secret")

    def _json(self, code, data):
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self._cors()
        self.end_headers()
        self.wfile.write(json.dumps(data).encode())

    def do_OPTIONS(self):
        self.send_response(204)
        self._cors()
        self.end_headers()

    def do_GET(self):
        if self.path == "/stats":
            with _stats_lock:
                data = dict(_stats)
            self._json(200, data)

        elif self.path == "/ping":
            self._json(200, {"ok": True, "hostname": socket.gethostname()})

        elif self.path.startswith("/processes"):
            from urllib.parse import urlparse, parse_qs
            qs      = parse_qs(urlparse(self.path).query)
            sort_by = qs.get('sort', ['cpu'])[0]
            limit   = min(int(qs.get('limit', ['50'])[0]), 200)
            search  = qs.get('q', [''])[0].lower()

            procs = []
            for p in psutil.process_iter(['pid','name','username','cpu_percent',
                                          'memory_percent','memory_info','status']):
                try:
                    info = p.info
                    if search and search not in (info['name'] or '').lower() \
                               and search not in str(info['pid']):
                        continue
                    procs.append({
                        'pid':    info['pid'],
                        'name':   info['name'] or '?',
                        'user':   info['username'] or '—',
                        'cpu':    round(info['cpu_percent']    or 0, 1),
                        'mem':    round(info['memory_percent'] or 0, 1),
                        'mem_mb': round((info['memory_info'].rss
                                         if info['memory_info'] else 0) / (1024*1024), 1),
                        'status': info['status'] or '?',
                    })
                except (psutil.NoSuchProcess, psutil.AccessDenied):
                    continue

            key = 'cpu' if sort_by == 'cpu' else 'mem'
            procs.sort(key=lambda x: x[key], reverse=True)
            self._json(200, {"processes": procs[:limit], "total": len(procs)})

        else:
            self._json(404, {"error": "not found"})

    def do_POST(self):
        if self.path == "/command":
            hdr_secret = self.headers.get("X-Node-Secret", "")
            if hdr_secret != secret:
                self._json(403, {"error": "invalid secret"})
                return

            length = int(self.headers.get("Content-Length", 0))
            body   = json.loads(self.rfile.read(length)) if length else {}
            cmd    = body.get("command", "")

            self._json(200, {"ok": True, "executing": cmd})

            is_windows = platform.system().lower() == "windows"

            if cmd == "shutdown":
                def do_shutdown():
                    cmds = [["shutdown","/s","/t","1"]] if is_windows else [
                        ["poweroff"], ["shutdown","-h","now"],
                        ["sudo","poweroff"], ["systemctl","poweroff"],
                    ]
                    for c in cmds:
                        try:
                            if subprocess.run(c, timeout=10,
                                              capture_output=True).returncode == 0:
                                break
                        except Exception:
                            continue
                threading.Timer(1, do_shutdown).start()

            elif cmd == "reboot":
                def do_reboot():
                    cmds = [["shutdown","/r","/t","1"]] if is_windows else [
                        ["reboot"], ["shutdown","-r","now"],
                        ["sudo","reboot"], ["systemctl","reboot"],
                    ]
                    for c in cmds:
                        try:
                            if subprocess.run(c, timeout=10,
                                              capture_output=True).returncode == 0:
                                break
                        except Exception:
                            continue
                threading.Timer(1, do_reboot).start()

            elif cmd == "kill":
                pid = body.get("pid", 0)
                try:
                    proc = psutil.Process(int(pid))
                    name = proc.name()
                    proc.terminate()
                    try:
                        proc.wait(timeout=3)
                    except psutil.TimeoutExpired:
                        proc.kill()
                    self._json(200, {"ok": True, "killed": pid, "name": name})
                except psutil.NoSuchProcess:
                    pass
                except Exception as e:
                    print(f"[CMD] Kill failed: {e}")
        else:
            self._json(404, {"error": "not found"})




def main():
    global running, http_port, secret, server_url

    p = argparse.ArgumentParser(description="echo Node Agent")
    p.add_argument("--port",   type=int, default=5150,
                   help="HTTP port (default 5150)")
    p.add_argument("--secret", type=str,
                   default=os.environ.get("NODE_SECRET", "echo-node-secret"),
                   help="Shared secret (must match NODE_API_SECRET in config.php)")
    p.add_argument("--server", type=str,
                   default=os.environ.get("ECHO_SERVER_URL", ""),
                   help="echo server base URL, e.g. http://192.168.1.10:8080")
    args = p.parse_args()

    http_port  = args.port
    secret     = args.secret
    server_url = args.server
    ip         = get_ip()

    W = 48
    print("╔" + "═"*W + "╗")
    print("║" + "  echo Node Agent".center(W) + "║")
    print("╠" + "═"*W + "╣")
    print(f"║  Hostname : {socket.gethostname():<{W-13}}║")
    print(f"║  IP       : {ip:<{W-13}}║")
    print(f"║  Port     : {http_port:<{W-13}}║")
    print(f"║  Server   : {(server_url or '(none)'):<{W-13}}║")
    print(f"║  Secret   : {'(set)' if secret else '(none)':<{W-13}}║")
    print("╚" + "═"*W + "╝")
    print()

    def stop(sig, frame):
        global running
        print("\n[INFO] Stopping...")
        running = False

    signal.signal(signal.SIGINT,  stop)
    signal.signal(signal.SIGTERM, stop)

    # Recolecta datos en segundo plano
    threading.Thread(target=stats_loop, daemon=True).start()
    time.sleep(2)  

    # Se registra automáticamente con el servidor
    if server_url:
        threading.Thread(target=register_loop, daemon=True).start()

    # Servidor HTTP
    srv = HTTPServer(("0.0.0.0", http_port), Handler)
    srv.timeout = 1
    print(f"[OK] Listening on http://0.0.0.0:{http_port}")
    print(f"     Stats : http://{ip}:{http_port}/stats")
    print(f"     Ping  : http://{ip}:{http_port}/ping")
    print()

    while running:
        srv.handle_request()

    srv.server_close()
    print("[INFO] Stopped.")


if __name__ == "__main__":
    main()
