<?php
require_once __DIR__ . '/../lang.php';

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/logger.php';
requirePermission('manage_nodes');
$me = currentUser();
$myRole = (int)$me['role'];
$pageTitle = t('admin_tab_nodes') . ' — Admin';
$wideLayout = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-header" style="margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;">
    <div>
        <h1 style="font-size:1.3rem;font-weight:700;letter-spacing:-.03em;"><?= t('admin_nodes_title') ?></h1>
        <p style="color:var(--text-2);font-size:.78rem;margin-top:.1rem;"><?= t('admin_nodes_desc') ?></p>
    </div>
    <div id="wake-all-wrap" style="display:none;align-items:center;gap:.45rem;flex-shrink:0;">
        <span style="font-size:.58rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--green);white-space:nowrap;">WAKE ALL</span>
        <div id="wake-all-track" class="ps-track" style="width:200px;flex:none;">
            <div class="ps-glow"></div>
            <div class="ps-tick"></div>
            <div class="ps-thumb" style="left:2px;"></div>
            <span class="ps-hint" style="right:10px;">POWER ON →</span>
        </div>
    </div>
</div>
<div class="admin-tabs">
    <a href="index.php"><?= t('admin_tab_users') ?></a>
    <?php if (can('change_roles') && $myRole === ROLE_ADMIN): ?>
        <a href="groups.php"><?= t('admin_tab_groups') ?></a>
    <?php endif; ?>
    <a href="nodes.php" class="active"><?= t('admin_tab_nodes') ?></a><?php if (can('manage_isos')): ?>
            <?php if ($myRole >= ROLE_ADMIN): ?>
            <a href="db_admin.php" class="<?= basename($_SERVER['PHP_SELF'])==='db_admin.php'?'active':'' ?>">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                DB Admin
            </a>
            <?php endif; ?>
        <a href="isos.php"><?= t('admin_tab_isos') ?></a>
    <?php endif; ?>
</div>

<div id="nodes-container"></div>

<div id="cmd-modal" class="modal-overlay" style="display:none;">
    <div class="card" style="max-width:380px;margin:auto;animation:nodeIn .25s var(--spring) both;">
        <h2 id="cmd-modal-title" style="font-size:.95rem;"></h2>
        <p id="cmd-modal-text" style="color:var(--text-2);font-size:.78rem;margin-bottom:1rem;"></p>
        <div style="display:flex;gap:.35rem;justify-content:flex-end;">
            <button class="btn btn-sm btn-outline" onclick="cancelCmd()"><?= t('cancel') ?></button>
            <button class="btn btn-sm" id="cmd-modal-btn" onclick="runNodeCommand()"><?= t('confirm') ?></button>
        </div>
    </div>
</div>
<div id="kill-modal" class="modal-overlay" style="display:none;">
    <div class="card" style="max-width:380px;margin:auto;animation:nodeIn .25s var(--spring) both;">
        <h2 style="color:var(--red);font-size:.95rem;"><?= t('kill_process') ?></h2>
        <p id="kill-modal-text" style="color:var(--text-2);font-size:.78rem;margin-bottom:1rem;"></p>
        <div style="display:flex;gap:.35rem;justify-content:flex-end;">
            <button class="btn btn-sm btn-outline" onclick="cancelKill()"><?= t('cancel') ?></button>
            <button class="btn btn-sm" style="background:var(--red);" onclick="confirmKill()">Kill</button>
        </div>
    </div>
</div>

<style>
@keyframes nodeIn{from{opacity:0;transform:scale(.95) translateY(8px);}}
@keyframes nodeOut{to{opacity:0;transform:scale(.95) translateY(4px);}}
@keyframes fadeIn{from{opacity:0;transform:translateY(8px);}}
@keyframes pulse{0%,100%{box-shadow:0 0 0 0 rgba(48,209,88,.4);}50%{box-shadow:0 0 0 5px rgba(48,209,88,0);}}
.node-card{animation:nodeIn .4s var(--spring) both;margin-bottom:0;}
.node-card:hover{border-color:var(--sep-bold);}
.node-card.removing{animation:nodeOut .25s var(--ease) both;}
.empty-state{animation:fadeIn .4s var(--ease) both;}
.node-stat{margin-bottom:.25rem;}.node-stat:last-child{margin-bottom:0;}
.node-stat-header{font-weight:500;}
.node-status-dot.online{animation:pulse 2s ease-in-out infinite;}

.ps-wrap{display:flex;align-items:center;justify-content:center;gap:0;padding-top:.65rem;border-top:.5px solid var(--sep);transition:gap .5s var(--spring);}
.ps-wrap.expanded{gap:.45rem;}
.ps-label{font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;text-align:center;user-select:none;overflow:hidden;white-space:nowrap;max-width:0;opacity:0;transition:max-width .5s var(--spring),opacity .4s .1s,text-shadow .2s;}
.ps-label.show{max-width:48px;opacity:1;width:48px;}
.ps-track{height:34px;background:var(--bg);border-radius:17px;position:relative;border:.5px solid var(--sep-bold);overflow:hidden;cursor:grab;touch-action:none;transition:width .5s var(--spring),flex .5s var(--spring);}
.ps-track:active{cursor:grabbing;}
.ps-glow{position:absolute;inset:0;border-radius:17px;opacity:0;pointer-events:none;transition:opacity .1s;}
.ps-thumb{position:absolute;top:2px;width:30px;height:30px;border-radius:50%;background:var(--surface2);border:1.5px solid var(--sep-bold);z-index:2;pointer-events:none;transition:left .4s cubic-bezier(.34,1.56,.64,1),border-color .1s,box-shadow .1s;}
.ps-track.dragging .ps-thumb{transition:border-color .1s,box-shadow .1s;}
.ps-tick{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:2px;height:10px;background:var(--sep-bold);border-radius:1px;z-index:1;opacity:0;transition:opacity .4s .2s;}
.ps-tick.show{opacity:1;}
.ps-hint{position:absolute;top:50%;transform:translateY(-50%);font-size:.58rem;color:var(--text-3);font-weight:600;letter-spacing:.3px;z-index:1;pointer-events:none;white-space:nowrap;}

.proc-section{margin-top:.55rem;}
.proc-toggle{display:flex;align-items:center;gap:.35rem;padding:.35rem .6rem;background:transparent;border:.5px solid var(--sep);border-radius:var(--r-sm);color:var(--text-3);font-size:.68rem;font-weight:600;letter-spacing:.02em;cursor:pointer;width:100%;transition:all .15s;user-select:none;font-family:var(--font);}
.proc-toggle:hover{border-color:var(--sep-bold);color:var(--text-2);background:rgba(255,255,255,.02);}
.proc-toggle.open{border-color:rgba(10,132,255,.25);color:var(--blue);background:rgba(10,132,255,.04);border-radius:var(--r-sm) var(--r-sm) 0 0;border-bottom-color:rgba(10,132,255,.1);}
.proc-toggle .arrow{transition:transform .25s var(--spring);font-size:.5rem;}.proc-toggle.open .arrow{transform:rotate(180deg);}
.proc-body{max-height:0;overflow:hidden;transition:max-height .4s var(--spring),opacity .25s;opacity:0;border:.5px solid transparent;border-top:none;border-radius:0 0 var(--r-sm) var(--r-sm);}
.proc-body.open{max-height:520px;opacity:1;border-color:rgba(10,132,255,.15);background:rgba(10,132,255,.015);}
.proc-inner{padding:.45rem .55rem;}
.proc-toolbar{display:flex;gap:.3rem;align-items:center;margin-bottom:.35rem;flex-wrap:wrap;}
.proc-search{flex:1;min-width:100px;padding:.28rem .5rem;background:rgba(255,255,255,.03);border:.5px solid var(--sep-bold);border-radius:var(--r-sm);color:var(--text);font-size:.7rem;font-family:var(--font);outline:none;transition:border-color .15s;}
.proc-search::placeholder{color:var(--text-3);}.proc-search:focus{border-color:var(--blue);}
.proc-sort-btn{padding:.22rem .45rem;background:rgba(255,255,255,.03);border:.5px solid var(--sep);border-radius:var(--r-sm);color:var(--text-3);font-size:.62rem;font-weight:600;cursor:pointer;transition:all .12s;white-space:nowrap;font-family:var(--font);}
.proc-sort-btn:hover{border-color:var(--sep-bold);color:var(--text-2);}
.proc-sort-btn.active{border-color:var(--blue);color:var(--blue);background:var(--blue-bg);}
.proc-table-wrap{max-height:360px;overflow-y:auto;border-radius:var(--r-sm);border:.5px solid var(--sep);background:var(--bg);}
.proc-table-wrap::-webkit-scrollbar{width:3px;}.proc-table-wrap::-webkit-scrollbar-track{background:transparent;}.proc-table-wrap::-webkit-scrollbar-thumb{background:rgba(255,255,255,.08);border-radius:2px;}
.proc-table{width:100%;border-collapse:collapse;font-size:.68rem;}
.proc-table thead{position:sticky;top:0;z-index:1;}
.proc-table th{background:var(--surface);color:var(--text-3);font-weight:600;font-size:.55rem;text-transform:uppercase;letter-spacing:.05em;padding:.35rem .45rem;text-align:left;border-bottom:.5px solid var(--sep);}
.proc-table th.r,.proc-table td.r{text-align:right;}
.proc-table td{padding:.25rem .45rem;border-bottom:.5px solid rgba(255,255,255,.02);color:var(--text-2);white-space:nowrap;}
.proc-table tr:hover td{background:rgba(255,255,255,.02);}
.proc-table .proc-name{max-width:200px;overflow:hidden;text-overflow:ellipsis;color:var(--text);font-weight:500;}
.proc-table .proc-user{color:var(--text-3);max-width:80px;overflow:hidden;text-overflow:ellipsis;}
.proc-table .cpu-high{color:var(--red);font-weight:600;}.proc-table .mem-high{color:var(--orange);font-weight:600;}
.proc-kill{background:none;border:.5px solid rgba(255,69,58,.25);color:var(--red);border-radius:4px;padding:.08rem .32rem;font-size:.58rem;cursor:pointer;transition:all .12s;font-family:var(--font);}
.proc-kill:hover{background:var(--red);color:#fff;border-color:var(--red);}
.proc-status{font-size:.58rem;padding:.08rem .3rem;border-radius:4px;font-weight:600;}
.proc-status.running{color:var(--green);background:var(--green-bg);}
.proc-status.sleeping{color:var(--text-3);background:rgba(255,255,255,.03);}
.proc-status.zombie{color:var(--red);background:var(--red-bg);}
.proc-count{font-size:.62rem;color:var(--text-3);margin-left:auto;white-space:nowrap;}
</style>

<script>
const NODE_SECRET=<?=json_encode(NODE_API_SECRET)?>;
let knownNodes={},renderedKeys=new Set(),pendingCmd=null,emptyShown=false;
let activeSliders={},sliderCleanups={},prevOnline={};
let openProcPanels={},pendingKill=null,offlineSkipCount={};
let cardBuiltState={};

async function loadNodes(){try{const d=await(await fetch('../api/nodes_manage.php')).json();if(d.ok&&d.nodes){knownNodes=d.nodes;for(const k in knownNodes)knownNodes[k]._online=null;}}catch(e){}}
async function removeNode(key){const card=document.querySelector(`[data-node-key="${CSS.escape(key)}"]`);if(card){card.classList.add('removing');await new Promise(r=>setTimeout(r,250));}try{await fetch('../api/nodes_manage.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'remove',key})});}catch(e){}delete knownNodes[key];renderedKeys.delete(key);delete prevOnline[key];delete cardBuiltState[key];closeProcPanel(key);if(sliderCleanups[key]){sliderCleanups[key]();delete sliderCleanups[key];}renderAll();}
async function sendWoL(nodeKey){try{const r=await fetch('../api/wol.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'wol',key:nodeKey})});const d=await r.json();if(d.ok){showToast('success','✓ Magic packet sent — machine waking up');}else{showToast('error',d.error||'Wake-on-LAN failed.');}}catch(e){showToast('error','Network error sending WoL packet.');}}
async function pollAgent(ip,port){const c=new AbortController(),t=setTimeout(()=>c.abort(),3000);try{const r=await fetch(`http://${ip}:${port}/stats`,{signal:c.signal});clearTimeout(t);return await r.json();}catch(e){clearTimeout(t);return null;}}
async function syncNodes(){try{const d=await(await fetch('../api/nodes_manage.php')).json();if(d.ok&&d.nodes)for(const k in d.nodes)if(!knownNodes[k]){knownNodes[k]=d.nodes[k];knownNodes[k]._online=null;}}catch(e){}}
async function pollAll(){await syncNodes();const keys=Object.keys(knownNodes);if(!keys.length){renderAll();return;}const results=await Promise.all(keys.map(async k=>{if(knownNodes[k]._online===false){offlineSkipCount[k]=(offlineSkipCount[k]||0)+1;if(offlineSkipCount[k]<5)return{key:k,stats:null};offlineSkipCount[k]=0;}return{key:k,stats:await pollAgent(knownNodes[k].ip,knownNodes[k].port||5150)};}));for(const{key,stats}of results){if(!knownNodes[key])continue;knownNodes[key]._stats=stats;knownNodes[key]._online=!!stats;if(stats){knownNodes[key].hostname=stats.hostname||knownNodes[key].hostname;delete offlineSkipCount[key];}}renderAll();}

function renderAll(){
    const container=document.getElementById('nodes-container'),keys=Object.keys(knownNodes);
    if(!keys.length){if(!emptyShown){renderedKeys.clear();container.innerHTML=`<div class="card empty-state" style="text-align:center;padding:2.5rem 1.25rem;"><div style="width:40px;height:40px;border-radius:10px;background:var(--surface2);display:flex;align-items:center;justify-content:center;margin:0 auto .65rem;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="color:var(--text-3);"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><circle cx="6" cy="6" r="1" fill="currentColor"/><circle cx="6" cy="18" r="1" fill="currentColor"/></svg></div><h3 style="font-size:.9rem;margin-bottom:.25rem;font-weight:600;">No Nodes</h3><p style="color:var(--text-3);font-size:.78rem;max-width:400px;margin:0 auto .6rem;">Nodes will appear here automatically when agents register.</p><code style="display:block;background:var(--bg);border:.5px solid var(--sep);padding:.45rem .75rem;border-radius:var(--r-sm);font-size:.72rem;color:var(--blue);max-width:440px;margin:0 auto;font-family:var(--mono);">python3 node_agent.py --server http://your-url</code></div>`;emptyShown=true;}return;}
    emptyShown=false;updateWakeAllBtn();let grid=container.querySelector('.nodes-grid');if(!grid){container.innerHTML='<div class="nodes-grid"></div>';grid=container.querySelector('.nodes-grid');renderedKeys.clear();}
    grid.querySelectorAll('.node-card').forEach(c=>{if(!knownNodes[c.dataset.nodeKey]){c.remove();renderedKeys.delete(c.dataset.nodeKey);delete cardBuiltState[c.dataset.nodeKey];}});
    for(const key of keys){const n=knownNodes[key],s=n._stats||{},online=n._online;const bc=v=>v>90?'danger':v>70?'warning':'';const card=grid.querySelector(`[data-node-key="${CSS.escape(key)}"]`);const needsRebuild=!card||cardBuiltState[key]!==online;if(!needsRebuild&&card&&online){patchStats(card,s,bc);continue;}if(activeSliders[key])continue;if(sliderCleanups[key]){sliderCleanups[key]();delete sliderCleanups[key];}const wasOff=prevOnline[key]===false;const justOn=wasOff&&online===true;prevOnline[key]=online;const procOpen=!!openProcPanels[key];const sid=key.replace(/[^a-zA-Z0-9]/g,'_');const cpu=s.cpu_percent||0,ram=s.ram_percent||0,ramU=s.ram_used_gb||0,ramT=s.ram_total_gb||0,dsk=s.disk_percent||0,dskU=s.disk_used_gb||0,dskT=s.disk_total_gb||0,netS=s.net_sent_mb||0,netR=s.net_recv_mb||0,mac=s.mac||'—';
    const html=`<div class="node-header"><div><div class="node-name"><span class="node-status-dot ${online?'online':'offline'}"></span>${esc(s.hostname||n.hostname||n.ip)}</div><div class="node-meta">${esc(n.ip)}:${n.port||5150} · ${esc(mac)} · ${esc(s.os||(online===null?'':'—'))}</div></div><div style="display:flex;align-items:center;gap:.4rem;"><span class="node-uptime" data-field="uptime">${online?esc(s.uptime||''):(online===null?'':'Offline')}</span><button class="btn btn-sm btn-outline" onclick="removeNode('${escAttr(key)}')" title="Remove" style="padding:.15rem .4rem;font-size:.62rem;"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div></div>${online?`<div class="node-stats"><div class="node-stat"><div class="node-stat-header"><span>CPU</span><span data-field="cpu">${cpu.toFixed(1)}%</span></div><div class="progress-bar"><div class="progress-fill ${bc(cpu)}" data-bar="cpu" style="width:${Math.min(cpu,100)}%"></div></div></div><div class="node-stat"><div class="node-stat-header"><span>RAM</span><span data-field="ram">${ramU.toFixed(1)}/${ramT.toFixed(1)} GB (${ram.toFixed(1)}%)</span></div><div class="progress-bar"><div class="progress-fill ${bc(ram)}" data-bar="ram" style="width:${Math.min(ram,100)}%"></div></div></div><div class="node-stat"><div class="node-stat-header"><span>Disk</span><span data-field="dsk">${dskU.toFixed(1)}/${dskT.toFixed(1)} GB (${dsk.toFixed(1)}%)</span></div><div class="progress-bar"><div class="progress-fill ${bc(dsk)}" data-bar="dsk" style="width:${Math.min(dsk,100)}%"></div></div></div><div class="node-stat"><div class="node-stat-header"><span>Network</span><span data-field="net">↑ ${netS.toFixed(1)} MB · ↓ ${netR.toFixed(1)} MB</span></div></div></div>`:(online===null?`<div style="padding:.45rem 0;color:var(--text-3);font-size:.78rem;">Connecting…</div>`:`<div style="padding:.45rem 0;color:var(--text-3);font-size:.78rem;">Agent not responding.</div>`)}
    <div class="ps-wrap${online?' expanded':''}" data-nodekey="${escAttr(key)}"><span class="ps-label${online?' show':''}" data-role="left" style="color:var(--red);">OFF</span><div class="ps-track" data-key="${escAttr(key)}" data-ip="${escAttr(n.ip)}" data-port="${n.port||5150}" data-mode="${online?'online':'offline'}" style="${online?'flex:1;':'width:160px;flex:none;'}"><div class="ps-glow"></div><div class="ps-tick${online?' show':''}"></div><div class="ps-thumb" style="left:${online?'calc(50% - 15px)':'2px'};"></div>${!online&&online!==null?'<span class="ps-hint" style="right:10px;">POWER ON →</span>':''}</div><span class="ps-label${online?' show':''}" data-role="right" style="color:var(--orange);">REBOOT</span></div>
    ${online?`<div class="proc-section"><button class="proc-toggle${procOpen?' open':''}" onclick="toggleProc('${escAttr(key)}')"><span class="arrow">▼</span> Processes <span class="proc-count" id="proc-count-${sid}"></span></button><div class="proc-body${procOpen?' open':''}" id="proc-panel-${sid}"><div class="proc-inner"><div class="proc-toolbar"><input type="text" class="proc-search" placeholder="Filter processes…" oninput="filterProc('${escAttr(key)}',this.value)" id="proc-q-${sid}" value="${esc(openProcPanels[key]?.query||'')}"><button class="proc-sort-btn${(!openProcPanels[key]||openProcPanels[key]?.sort==='cpu')?' active':''}" data-sort="cpu" onclick="sortProc('${escAttr(key)}','cpu',this)">CPU ↓</button><button class="proc-sort-btn${openProcPanels[key]?.sort==='mem'?' active':''}" data-sort="mem" onclick="sortProc('${escAttr(key)}','mem',this)">MEM ↓</button></div><div class="proc-table-wrap"><table class="proc-table"><thead><tr><th style="width:46px;">PID</th><th>Name</th><th>User</th><th class="r" style="width:50px;">CPU%</th><th class="r" style="width:50px;">MEM%</th><th class="r" style="width:55px;">MB</th><th style="width:50px;">Status</th><th style="width:32px;"></th></tr></thead><tbody id="proc-body-${sid}"><tr><td colspan="8" style="text-align:center;color:var(--text-3);padding:.75rem;">Loading…</td></tr></tbody></table></div></div></div></div>`:''}`;
    if(card){card.innerHTML=html;card.className='card node-card';card.style.animation='none';}else{const el=document.createElement('div');el.className='card node-card';el.dataset.nodeKey=key;el.style.animationDelay=`${renderedKeys.size*.06}s`;el.innerHTML=html;grid.appendChild(el);}renderedKeys.add(key);cardBuiltState[key]=online;
    const newCard=grid.querySelector(`[data-node-key="${CSS.escape(key)}"]`);if(justOn){const wrap=newCard.querySelector('.ps-wrap'),trk=newCard.querySelector('.ps-track'),lblL=wrap.querySelector('[data-role="left"]'),lblR=wrap.querySelector('[data-role="right"]'),tick=trk.querySelector('.ps-tick'),thb=trk.querySelector('.ps-thumb');wrap.classList.remove('expanded');trk.style.flex='none';trk.style.width='160px';lblL.classList.remove('show');lblR.classList.remove('show');tick.classList.remove('show');thb.style.left='2px';requestAnimationFrame(()=>{setTimeout(()=>{wrap.classList.add('expanded');trk.style.flex='1';trk.style.width='';lblL.classList.add('show');lblR.classList.add('show');tick.classList.add('show');thb.style.left='calc(50% - 15px)';},50);});}const track=newCard.querySelector('.ps-track');if(track)initSlider(track,key);if(procOpen)fetchProcesses(key);}
}
function patchStats(card,s,bc){const cpu=s.cpu_percent||0,ram=s.ram_percent||0,ramU=s.ram_used_gb||0,ramT=s.ram_total_gb||0,dsk=s.disk_percent||0,dskU=s.disk_used_gb||0,dskT=s.disk_total_gb||0,netS=s.net_sent_mb||0,netR=s.net_recv_mb||0;const f=(sel,txt)=>{const el=card.querySelector(sel);if(el)el.textContent=txt;};f('[data-field="cpu"]',cpu.toFixed(1)+'%');f('[data-field="ram"]',`${ramU.toFixed(1)}/${ramT.toFixed(1)} GB (${ram.toFixed(1)}%)`);f('[data-field="dsk"]',`${dskU.toFixed(1)}/${dskT.toFixed(1)} GB (${dsk.toFixed(1)}%)`);f('[data-field="net"]',`↑ ${netS.toFixed(1)} MB · ↓ ${netR.toFixed(1)} MB`);f('[data-field="uptime"]',s.uptime||'');for(const[k,v]of Object.entries({cpu,ram,dsk})){const bar=card.querySelector(`[data-bar="${k}"]`);if(bar){bar.style.width=Math.min(v,100)+'%';bar.className=`progress-fill ${bc(v)}`;}}}
function toggleProc(key){const sid=key.replace(/[^a-zA-Z0-9]/g,'_');const panel=document.getElementById('proc-panel-'+sid);const btn=panel?.previousElementSibling;if(!panel)return;if(openProcPanels[key]){closeProcPanel(key);panel.classList.remove('open');btn?.classList.remove('open');}else{openProcPanels[key]={sort:'cpu',query:''};panel.classList.add('open');btn?.classList.add('open');fetchProcesses(key);openProcPanels[key].interval=setInterval(()=>fetchProcesses(key),3000);}}
function closeProcPanel(key){if(openProcPanels[key]?.interval)clearInterval(openProcPanels[key].interval);delete openProcPanels[key];}
function sortProc(key,sort,btn){if(!openProcPanels[key])return;openProcPanels[key].sort=sort;btn.closest('.proc-toolbar').querySelectorAll('.proc-sort-btn').forEach(b=>b.classList.toggle('active',b.dataset.sort===sort));fetchProcesses(key);}
function filterProc(key,q){if(!openProcPanels[key])return;openProcPanels[key].query=q;fetchProcesses(key);}
async function fetchProcesses(key){if(!openProcPanels[key])return;const n=knownNodes[key];if(!n)return;const{sort,query}=openProcPanels[key];const sid=key.replace(/[^a-zA-Z0-9]/g,'_');const body=document.getElementById('proc-body-'+sid);const countEl=document.getElementById('proc-count-'+sid);if(!body)return;try{const url=`http://${n.ip}:${n.port||5150}/processes?sort=${sort}&limit=80${query?'&q='+encodeURIComponent(query):''}`;const c=new AbortController(),t=setTimeout(()=>c.abort(),4000);const r=await fetch(url,{signal:c.signal});clearTimeout(t);const d=await r.json();if(!d.processes)return;if(countEl)countEl.textContent=`${d.processes.length}${d.total>d.processes.length?' / '+d.total:''}`;if(!d.processes.length){body.innerHTML='<tr><td colspan="8" style="text-align:center;color:var(--text-3);padding:.75rem;">No processes</td></tr>';return;}body.innerHTML=d.processes.map(p=>{const cpuCls=p.cpu>50?'cpu-high':'';const memCls=p.mem>50?'mem-high':'';const stCls=p.status==='running'?'running':p.status==='sleeping'||p.status==='idle'?'sleeping':p.status==='zombie'?'zombie':'';return`<tr><td style="color:var(--text-3);font-family:var(--mono);font-size:.62rem;">${p.pid}</td><td class="proc-name">${esc(p.name)}</td><td class="proc-user">${esc(p.user)}</td><td class="r ${cpuCls}">${p.cpu.toFixed(1)}</td><td class="r ${memCls}">${p.mem.toFixed(1)}</td><td class="r" style="color:var(--text-3);">${p.mem_mb.toFixed(1)}</td><td><span class="proc-status ${stCls}">${esc(p.status)}</span></td><td><button class="proc-kill" onclick="askKill('${escAttr(key)}',${p.pid},'${escAttr(p.name)}')" title="Kill">✕</button></td></tr>`;}).join('');}catch(e){body.innerHTML='<tr><td colspan="8" style="text-align:center;color:var(--text-3);padding:.75rem;">Cannot reach agent</td></tr>';}}
function askKill(key,pid,name){pendingKill={key,pid,name};document.getElementById('kill-modal-text').textContent=`Kill "${name}" (PID ${pid})?`;document.getElementById('kill-modal').style.display='flex';}
function cancelKill(){document.getElementById('kill-modal').style.display='none';pendingKill=null;}
async function confirmKill(){if(!pendingKill)return;const{key,pid,name}=pendingKill;document.getElementById('kill-modal').style.display='none';pendingKill=null;const n=knownNodes[key];if(!n)return;try{const r=await fetch('../api/node_command.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ip:n.ip,port:n.port||5150,command:'kill',pid})});const d=await r.json();if(d.error)showToast('error',d.error);else{showToast('success',`Killed "${name}" (${pid})`);fetchProcesses(key);}}catch(e){showToast('error','Failed.');}}
document.getElementById('kill-modal').addEventListener('click',e=>{if(e.target.id==='kill-modal')cancelKill();});
function initSlider(track,nodeKey){const thumb=track.querySelector('.ps-thumb'),glow=track.querySelector('.ps-glow'),wrap=track.closest('.ps-wrap');const labelL=wrap.querySelector('[data-role="left"]'),labelR=wrap.querySelector('[data-role="right"]');const mode=track.dataset.mode;let dragging=false,ratio=mode==='online'?0.5:0;function updateVisual(r){const w=track.offsetWidth;thumb.style.left=(r*(w-36)+2)+'px';if(mode==='online'){if(r<0.4){const i=(0.5-r)/0.5;glow.style.background=`linear-gradient(to right,rgba(255,69,58,${i*.6}),transparent 70%)`;glow.style.opacity=i;thumb.style.borderColor=`rgba(255,69,58,${.3+i*.7})`;thumb.style.boxShadow=`0 0 ${i*18}px rgba(255,69,58,${i*.6})`;if(labelL){labelL.style.textShadow=`0 0 ${i*12}px var(--red)`;labelL.style.opacity=.5+i*.5;}if(labelR){labelR.style.opacity=1-i*.4;labelR.style.textShadow='none';}}else if(r>0.6){const i=(r-0.5)/0.5;glow.style.background=`linear-gradient(to left,rgba(255,159,10,${i*.6}),transparent 70%)`;glow.style.opacity=i;thumb.style.borderColor=`rgba(255,159,10,${.3+i*.7})`;thumb.style.boxShadow=`0 0 ${i*18}px rgba(255,159,10,${i*.6})`;if(labelR){labelR.style.textShadow=`0 0 ${i*12}px var(--orange)`;labelR.style.opacity=.5+i*.5;}if(labelL){labelL.style.opacity=1-i*.4;labelL.style.textShadow='none';}}else{glow.style.opacity=0;thumb.style.borderColor='var(--sep-bold)';thumb.style.boxShadow='none';if(labelL){labelL.style.opacity=1;labelL.style.textShadow='none';}if(labelR){labelR.style.opacity=1;labelR.style.textShadow='none';}}}else{glow.style.background=`linear-gradient(to left,rgba(48,209,88,${r*.6}),transparent 60%)`;glow.style.opacity=r;thumb.style.borderColor=`rgba(48,209,88,${.3+r*.7})`;thumb.style.boxShadow=`0 0 ${r*18}px rgba(48,209,88,${r*.6})`;}}function getRatio(e){const rect=track.getBoundingClientRect(),x=e.touches?e.touches[0].clientX:e.clientX;return Math.max(0,Math.min(1,(x-rect.left)/rect.width));}function springBack(){const tgt=mode==='online'?0.5:0;ratio=tgt;thumb.style.transition='left .4s cubic-bezier(.34,1.56,.64,1)';glow.style.transition='opacity .3s';updateVisual(tgt);setTimeout(()=>{thumb.style.transition='';glow.style.transition='';},400);}const onStart=e=>{e.preventDefault();dragging=true;track.classList.add('dragging');activeSliders[nodeKey]=true;ratio=getRatio(e);updateVisual(ratio);};const onMove=e=>{if(!dragging)return;e.preventDefault();ratio=getRatio(e);updateVisual(ratio);};const onEnd=()=>{if(!dragging)return;dragging=false;track.classList.remove('dragging');delete activeSliders[nodeKey];const ip=track.dataset.ip,port=parseInt(track.dataset.port);if(mode==='online'){if(ratio<=0.05){showCommand(ip,port,'shutdown','Power Off','var(--red)',springBack);return;}if(ratio>=0.95){showCommand(ip,port,'reboot','Reboot','var(--orange)',springBack);return;}}else{if(ratio>=0.9)sendWoL(nodeKey);}springBack();};track.addEventListener('mousedown',onStart);track.addEventListener('touchstart',onStart,{passive:false});document.addEventListener('mousemove',onMove);document.addEventListener('touchmove',onMove,{passive:false});document.addEventListener('mouseup',onEnd);document.addEventListener('touchend',onEnd);sliderCleanups[nodeKey]=()=>{document.removeEventListener('mousemove',onMove);document.removeEventListener('touchmove',onMove);document.removeEventListener('mouseup',onEnd);document.removeEventListener('touchend',onEnd);};updateVisual(ratio);}
let onCancelCmd=null;
function showCommand(ip,port,command,label,color,onCancel){pendingCmd={ip,port,command};onCancelCmd=onCancel;document.getElementById('cmd-modal-title').textContent=label+' Node';document.getElementById('cmd-modal-title').style.color=color;document.getElementById('cmd-modal-text').textContent=`Are you sure you want to ${command} ${ip}:${port}?`;const btn=document.getElementById('cmd-modal-btn');btn.style.background=color;btn.textContent='Confirm';document.getElementById('cmd-modal').style.display='flex';}
function cancelCmd(){document.getElementById('cmd-modal').style.display='none';if(onCancelCmd){onCancelCmd();onCancelCmd=null;}pendingCmd=null;}
async function runNodeCommand(){if(!pendingCmd)return;const{ip,port,command}=pendingCmd;document.getElementById('cmd-modal').style.display='none';if(onCancelCmd){onCancelCmd();onCancelCmd=null;}pendingCmd=null;try{const r=await fetch('../api/node_command.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ip,port,command})});const d=await r.json();if(r.ok&&!d.error)showToast('success',`"${command}" sent to ${ip}:${port}`);else showToast('error',d.error||`Failed (${r.status})`);}catch(e){showToast('error','Network error.');}}
document.getElementById('cmd-modal').addEventListener('click',e=>{if(e.target.id==='cmd-modal')cancelCmd();});
function esc(s){const d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}
function escAttr(s){return(s||'').replace(/'/g,"\\'").replace(/"/g,'&quot;');}

// Wake all up, igual que el de los nodos offline. Solo se muestra si 2 o mas nodos están apagados.
function updateWakeAllBtn(){
  const wrap=document.getElementById('wake-all-wrap');
  if(!wrap)return;
  const hasOffline=Object.values(knownNodes).some(n=>n._online===false||n._online===null);
  wrap.style.display=hasOffline?'flex':'none';
}

async function wakeAllNodes(){
  const keys=Object.keys(knownNodes);
  if(!keys.length)return;
  showToast('success',`Sending WoL to ${keys.length} machine${keys.length!==1?'s':''}…`);
  await Promise.all(keys.map(k=>sendWoL(k)));
  setTimeout(pollAll, 5000);
}

function initWakeAllSlider(){
  const track=document.getElementById('wake-all-track');
  if(!track)return;
  const thumb=track.querySelector('.ps-thumb'),glow=track.querySelector('.ps-glow');
  let dragging=false,ratio=0;

  function updateVisual(r){
    const w=track.offsetWidth;
    thumb.style.left=(r*(w-36)+2)+'px';
    const i=r;
    glow.style.background=`linear-gradient(to left,rgba(48,209,88,${i*.7}),transparent 60%)`;
    glow.style.opacity=i;
    thumb.style.borderColor=`rgba(48,209,88,${.3+i*.7})`;
    thumb.style.boxShadow=`0 0 ${i*18}px rgba(48,209,88,${i*.6})`;
  }

  function springBack(){
    ratio=0;
    thumb.style.transition='left .4s cubic-bezier(.34,1.56,.64,1)';
    glow.style.transition='opacity .3s';
    updateVisual(0);
    setTimeout(()=>{thumb.style.transition='';glow.style.transition='';},400);
  }

  function getRatio(e){
    const rect=track.getBoundingClientRect(),x=e.touches?e.touches[0].clientX:e.clientX;
    return Math.max(0,Math.min(1,(x-rect.left)/rect.width));
  }

  const onStart=e=>{e.preventDefault();dragging=true;track.classList.add('dragging');ratio=getRatio(e);updateVisual(ratio);};
  const onMove=e=>{if(!dragging)return;e.preventDefault();ratio=getRatio(e);updateVisual(ratio);};
  const onEnd=()=>{
    if(!dragging)return;
    dragging=false;track.classList.remove('dragging');
    if(ratio>=0.9){wakeAllNodes();}
    springBack();
  };

  track.addEventListener('mousedown',onStart);
  track.addEventListener('touchstart',onStart,{passive:false});
  document.addEventListener('mousemove',onMove);
  document.addEventListener('touchmove',onMove,{passive:false});
  document.addEventListener('mouseup',onEnd);
  document.addEventListener('touchend',onEnd);
  updateVisual(0);
}

(async()=>{await loadNodes();renderAll();updateWakeAllBtn();initWakeAllSlider();await pollAll();setInterval(pollAll,2000);})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
