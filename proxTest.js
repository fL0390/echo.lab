const http = require('http');
const httpProxy = require('http-proxy');
const fs = require('fs');
const path = require('path');

// Lee el archivo de configuración
const configPath = path.join(__dirname, 'proxy_config.json');
if (!fs.existsSync(configPath)) {
    console.error('[ERROR] proxy_config.json not found.');
    console.error('        Create it manually or visit the admin panel to generate it:');
    console.error('        {');
    console.error('          "pve_ip":      "192.168.248.30",');
    console.error('          "pve_port":    8006,');
    console.error('          "token_id":    "root@pam!echo",');
    console.error('          "token_secret": "your-uuid-here",');
    console.error('          "proxy_port":  8081');
    console.error('        }');
    process.exit(1);
}

const cfg = JSON.parse(fs.readFileSync(configPath, 'utf8'));
const PVE_IP = cfg.pve_ip;
const PVE_PORT = cfg.pve_port || 8006;
const TOKEN_ID = cfg.token_id;
const TOKEN_SEC = cfg.token_secret;
const PROXY_PORT = cfg.proxy_port || 8081;
const AUTH_HEADER = `PVEAPIToken=${TOKEN_ID}=${TOKEN_SEC}`;

const NGINX_PREFIX = '/novnc-proxy';

console.log('════════════════════════════════════════════════');
console.log(' echo — Proxmox WebSocket Proxy');
console.log('════════════════════════════════════════════════');
console.log(` Proxmox  : https://${PVE_IP}:${PVE_PORT}`);
console.log(` Token    : ${TOKEN_ID}`);
console.log(` Listening: ws://127.0.0.1:${PROXY_PORT}`);
console.log(` Nginx    : ws://echo.lab${NGINX_PREFIX}/`);
console.log('════════════════════════════════════════════════');


const proxy = httpProxy.createProxyServer({
    target: `wss://${PVE_IP}:${PVE_PORT}`,
    ws: true,
    secure: false,
    changeOrigin: true,
});

proxy.on('proxyReqWs', function (proxyReq, req) {
    proxyReq.setHeader('Authorization', AUTH_HEADER);

    if (proxyReq.path && proxyReq.path.startsWith(NGINX_PREFIX)) {
        proxyReq.path = proxyReq.path.slice(NGINX_PREFIX.length) || '/';
    }
});

proxy.on('error', function (err, req, res) {
    console.error(`[PROXY ERROR] ${err.message}`);
    try { if (res && res.end) res.end(); } catch (_) { }
});

//Servidor HTTP
const server = http.createServer((req, res) => {
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type');
    if (req.method === 'OPTIONS') { res.writeHead(204); res.end(); return; }
    res.writeHead(200, { 'Content-Type': 'text/plain' });
    res.end(`echo Proxmox WebSocket Proxy — forwarding to ${PVE_IP}:${PVE_PORT}\n`);
});

server.on('upgrade', (req, socket, head) => {
    // quita el prefijo /novnc-proxy de la URL antes de reenviar
    const originalUrl = req.url;
    if (req.url.startsWith(NGINX_PREFIX)) {
        req.url = req.url.slice(NGINX_PREFIX.length) || '/';
    }

    console.log(`[WS] ${req.socket.remoteAddress}  ${originalUrl}  →  wss://${PVE_IP}:${PVE_PORT}${req.url}`);
    proxy.ws(req, socket, head);
});

server.listen(PROXY_PORT, '127.0.0.1', () => {
    console.log(`\n[OK] Ready. Nginx should forward /novnc-proxy/ → this process.\n`);
});
