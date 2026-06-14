<?php
/**
 * WRD Jharkhand — Technical Presentation Deck (reveal.js)
 * Present:  http://localhost/WRD/deck/
 *   F = fullscreen · S = speaker notes · ← → navigate · O = overview · Esc
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
$pdo = db();
$projects   = (int)$pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn();
$onTrack    = (int)$pdo->query("SELECT COUNT(*) FROM projects WHERE status='On Track'")->fetchColumn();
$sanctioned = (float)$pdo->query('SELECT COALESCE(SUM(sanctioned_amount),0) FROM projects')->fetchColumn();
$revenue    = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='Success'")->fetchColumn();
$contractors= (int)$pdo->query("SELECT COUNT(*) FROM contractors")->fetchColumn();
$consumers  = (int)$pdo->query('SELECT COUNT(*) FROM consumers')->fetchColumn();
$divisions  = (int)$pdo->query('SELECT COUNT(*) FROM divisions')->fetchColumn();
function demo($p){ return base_url($p); }

// inline wave disabled — replaced by a single fixed wave element (see body) to avoid
// fighting reveal.js slide positioning. Kept as '' so the 8 in-slide echoes are harmless.
$WAVE = '';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>WRD Jharkhand · Technical Presentation</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Mukta:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/dist/reveal.css">
<style>
:root{--ink:#06314a;--ink2:#0a4763;--brand:#0E7C86;--branddeep:#0a5d65;--brandsoft:#e6f4f4;--gold:#B45309;--paper:#f5f8f9;--line:#e3eaed;}
*{box-sizing:border-box;}
.reveal{font-family:'Mukta',system-ui,sans-serif;font-size:24px;color:#1f2d3a;}
.reveal h1,.reveal h2,.reveal h3{font-family:'Fraunces',Georgia,serif;text-transform:none;letter-spacing:-.015em;color:var(--ink);margin:0 0 .3em;line-height:1.03;font-weight:600;}
.reveal h1{font-size:2.35em;} .reveal h2{font-size:1.55em;} .reveal h3{font-size:1.02em;margin-bottom:.25em;}
.reveal p{line-height:1.42;margin:0 0 .45em;}
.reveal ul{margin:0;padding:0;list-style:none;}

/* let reveal handle vertical centring (center:true); we only set insets + alignment */
.reveal .slides>section{padding:0 84px;text-align:left;}

/* light vs hero */
section.lt{background:
  radial-gradient(900px 380px at 110% -10%, #eaf4f4 0%, transparent 60%),
  radial-gradient(700px 320px at -10% 115%, #eef3f5 0%, transparent 55%), var(--paper);}
section.hero{color:#dceef0;}
section.hero h1,section.hero h2,section.hero h3{color:#fff;}
section.hero .kicker{color:#67e8f9;}
section.hero .muted{color:#a8cdd5;}
/* single fixed decorative wave at viewport bottom, only on hero slides */
.wavefix{position:fixed;left:0;right:0;bottom:0;width:100%;height:140px;pointer-events:none;z-index:2;display:none;}
body.hero-active .wavefix{display:block;}
.ripple{position:absolute;pointer-events:none;}

.kicker{font-size:.52em;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:var(--brand);margin-bottom:.7em;}
.title-rule{width:64px;height:5px;border-radius:3px;background:var(--brand);margin:.05em 0 .7em;}
section.hero .title-rule{background:#22d3ee;}
.muted{color:#5b6b78;} .small{font-size:.62em;line-height:1.45;} .xsmall{font-size:.5em;line-height:1.5;}
.reveal a{color:var(--brand);text-decoration:none;}

.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:.75em .9em;box-shadow:0 12px 30px -24px rgba(6,49,74,.55);}
.card h3{color:var(--ink);}
.card.dark{background:rgba(255,255,255,.07);border-color:rgba(255,255,255,.16);box-shadow:none;}
.card.dark h3{color:#fff;}
.card.accent{background:linear-gradient(135deg,#0E7C86,#0a5d65);border:0;color:#fff;}
.card.accent h3{color:#fff;}
.grid{display:grid;gap:.6em;}
.g2{grid-template-columns:1fr 1fr;} .g3{grid-template-columns:repeat(3,1fr);} .g4{grid-template-columns:repeat(4,1fr);}
.kpi{font-family:'Fraunces',serif;font-weight:600;color:var(--ink);font-size:1.45em;line-height:1;}
.card.dark .kpi,section.hero .kpi{color:#fff;}
.chip{display:inline-block;background:var(--brandsoft);color:var(--branddeep);border-radius:999px;padding:.18em .8em;font-size:.5em;font-weight:600;margin:.18em .2em 0 0;}
section.hero .chip{background:rgba(255,255,255,.12);color:#cffafe;}
.flow{display:flex;align-items:center;gap:.45em;font-size:.58em;font-weight:600;flex-wrap:wrap;}
.node{background:#fff;border:1px solid var(--line);border-radius:11px;padding:.42em .72em;color:#334;}
section.hero .node{background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.18);color:#e2f3f5;}
.node.on{background:#ecfdf5;border-color:#a7f3d0;color:#047857;}
.node.fin{background:#065f46;color:#fff;border:0;}
.arrow{color:var(--brand);font-weight:700;} section.hero .arrow{color:#5eead4;}
.bignum{font-family:'Fraunces',serif;font-size:1.15em;color:var(--brand);font-weight:600;line-height:1;}
.demo-btn{display:inline-flex;align-items:center;gap:.45em;background:#fff;color:var(--ink)!important;font-weight:700;font-size:.56em;padding:.65em 1.2em;border-radius:12px;box-shadow:0 12px 28px -14px rgba(0,0,0,.5);}
.demo-btn:hover{background:#ecfeff;}
.checkrow{display:flex;gap:.55em;align-items:flex-start;font-size:.64em;margin-bottom:.45em;}
.tick{color:var(--brand);font-weight:700;font-size:1.1em;line-height:1;}
.tl li{position:relative;margin-bottom:.5em;font-size:.64em;padding-left:1.2em;}
.tl li::before{content:"";position:absolute;left:0;top:.42em;width:.6em;height:.6em;border-radius:50%;background:var(--brand);box-shadow:0 0 0 4px var(--brandsoft);}
.bi{font-size:.5em;color:#7dd3da;font-weight:500;letter-spacing:.01em;}
table.mini{font-size:.6em;border-collapse:collapse;width:100%;}
table.mini td{padding:.34em .6em;border-bottom:1px solid var(--line);}
table.mini td:last-child{text-align:right;font-weight:600;color:var(--ink);}
.lead{font-size:.74em;max-width:20em;}

/* cover two-column */
.cover .cwrap{display:flex;align-items:center;gap:2em;width:100%;}
.cover .cleft{flex:1.3;min-width:0;}
.cover .cright{flex:1;display:flex;align-items:center;justify-content:center;position:relative;height:420px;}
.ring{position:absolute;border-radius:50%;border:1.5px solid rgba(255,255,255,.16);}
.dropbig{width:150px;height:150px;border-radius:50% 50% 50% 8px;transform:rotate(45deg);
  background:linear-gradient(135deg,rgba(255,255,255,.95),#9be7ee);box-shadow:0 30px 70px -20px rgba(0,0,0,.5);display:grid;place-items:center;}
.dropbig span{transform:rotate(-45deg);font-family:'Fraunces',serif;font-weight:600;color:#06314a;font-size:34px;}

/* footer bar */
.foot{position:fixed;left:26px;right:26px;bottom:16px;z-index:31;display:flex;align-items:center;justify-content:space-between;font-size:13px;color:#7a8b96;pointer-events:none;}
.foot .fl{display:flex;align-items:center;gap:.5em;font-family:'Fraunces',serif;font-weight:600;color:var(--ink);}
.foot .drop{width:24px;height:24px;border-radius:7px;background:linear-gradient(160deg,#06314a,#0E7C86);display:grid;place-items:center;}
body.hero-active .foot{color:#9fd0d8;} body.hero-active .foot .fl{color:#fff;}
.reveal .slide-number{background:transparent!important;color:#94a3b8;font-family:'Mukta';font-size:13px;top:14px;right:20px;left:auto;bottom:auto;}
body.hero-active .reveal .slide-number{color:#9fd0d8;}
.reveal .controls{color:var(--brand);} body.hero-active .reveal .controls{color:#a5f3fc;}
</style>
</head>
<body>
<div class="reveal"><div class="slides">

<!-- 1 COVER -->
<section class="hero cover" data-background-gradient="linear-gradient(150deg,#04293f 0%,#0a4763 50%,#0E7C86 125%)">
  <?= $WAVE ?>
  <div class="cwrap">
    <div class="cleft">
      <div class="kicker">Government of Jharkhand · Water Resources Department</div>
      <h1>One WRD,<br>One Digital Backbone</h1>
      <div class="title-rule"></div>
      <p class="muted lead">Re-Design, Re-Development, Implementation, Hosting &amp; Operation of an Integrated Software Solution — five platforms, one secure, citizen-centric ecosystem.</p>
      <p class="bi" style="margin-top:.4em;">एक डब्ल्यूआरडी · एक एकीकृत डिजिटल मंच</p>
      <div style="margin-top:1em;"><span class="chip">PPMS</span><span class="chip">Contractor Registration</span><span class="chip">Water Allocation</span><span class="chip">E-Tariff &amp; Billing</span><span class="chip">CMS Website</span></div>
      <p class="muted" style="font-size:.5em;margin-top:1.4em;letter-spacing:.04em;">PRESENTED BY [YOUR COMPANY] · LOCAL OFFICE: RANCHI</p>
    </div>
    <div class="cright">
      <div class="ring" style="width:380px;height:380px;"></div>
      <div class="ring" style="width:290px;height:290px;border-color:rgba(255,255,255,.22);"></div>
      <div class="ring" style="width:205px;height:205px;border-color:rgba(255,255,255,.28);"></div>
      <div class="dropbig"><span>जल</span></div>
    </div>
  </div>
  <aside class="notes">
    Greet the committee, acknowledging WRD, NIC and JAPIT representatives by role. Opening pitch (≈3 min):
    "Honourable committee, WRD doesn't need five new websites — it needs one trustworthy digital backbone where every rupee is traceable, every drop of water is billed and recovered to the right division, and every citizen is served in Hindi or English on any device, hosted securely on Jharkhand's own data centre. In the next 40 minutes we won't just describe it — we'll show it running." State that ~60% of the session is a LIVE working system.
  </aside>
</section>

<!-- 2 UNDERSTANDING -->
<section class="lt">
  <div class="kicker">Our Understanding of WRD's Vision</div>
  <h2>We read the RFP as one mission, not five tenders</h2>
  <div class="title-rule"></div>
  <div class="grid g2" style="margin-top:.5em;">
    <div class="card"><h3>Where WRD is today</h3><ul class="small muted" style="margin-top:.3em;">
      <li>• No end-to-end fund requisition tied to project metadata</li>
      <li>• No BI/MIS dashboard; weak GIS visualisation</li>
      <li>• No digital tariff billing or division-wise revenue credit</li>
      <li>• No GIGW 3.0 bilingual website; CERT-In / DR gaps</li>
    </ul></div>
    <div class="card" style="border-color:#a7f3d0;background:linear-gradient(135deg,#f0fdf9,#ffffff);"><h3 style="color:var(--branddeep)">Where this project takes WRD</h3><ul class="small" style="margin-top:.3em;color:#0f766e;">
      <li>✓ Public money traceable, fund flow automated</li>
      <li>✓ Real-time MIS, GIS &amp; executive command centre</li>
      <li>✓ Water revenue recovered to the <b>right division account</b></li>
      <li>✓ Bilingual, accessible, secure, citizen-centric services</li>
    </ul></div>
  </div>
  <p class="small muted" style="margin-top:.7em;">Our north star: make public money <b>traceable</b>, water revenue <b>recoverable</b>, services <b>reachable</b> — in Hindi &amp; English, on any device, on Jharkhand's own infrastructure.</p>
  <aside class="notes">Mirror §2 objectives and §3 pain points in WRD's own words — make evaluators feel heard. 60 seconds; sets the emotional frame. Emphasise "single integrated backbone" — the phrase the RFP uses 20+ times.</aside>
</section>

<!-- 3 ECOSYSTEM -->
<section class="lt">
  <div class="kicker">The Integrated Ecosystem</div>
  <h2>Five components — organs of one body</h2>
  <div class="title-rule"></div>
  <div class="grid g3" style="margin-top:.5em;">
    <div class="card"><span class="bignum">A</span><h3>PPMS</h3><p class="xsmall muted">Fund requisition, MIS, BI, GIS project monitoring</p></div>
    <div class="card"><span class="bignum">B</span><h3>Contractor Registration</h3><p class="xsmall muted">e-KYC, ASO→EIC workflow, QR cert, DigiLocker</p></div>
    <div class="card"><span class="bignum">C</span><h3>Water Allocation</h3><p class="xsmall muted">Allocation engine, AE→Secretary, JE-GRASS, SWCS</p></div>
    <div class="card"><span class="bignum">D</span><h3>E-Tariff &amp; Billing</h3><p class="xsmall muted">Drawal→bill→pay, division-wise revenue credit</p></div>
    <div class="card"><span class="bignum">E</span><h3>CMS Website</h3><p class="xsmall muted">Bilingual, GIGW 3.0, citizen services, live feeds</p></div>
    <div class="card accent"><h3>Bound together by</h3><p class="xsmall" style="color:#d7fbff;">Shared SSO · one design system · API gateway · common security &amp; audit</p></div>
  </div>
  <p class="small muted" style="margin-top:.6em;">Competitors present five disconnected apps. We present <b>one nervous system</b> — SSO, shared design language, and the website pulling <b>live PPMS data</b> via API.</p>
  <aside class="notes">Kills the "silo" perception. Integration is the RFP's central theme. Foreshadow: "You'll see the public website show live project counts straight from PPMS."</aside>
</section>

<!-- 4 APPROACH -->
<section class="lt">
  <div class="kicker">Approach &amp; Methodology</div>
  <h2>Phase-gated Agile, governed for Government</h2>
  <div class="title-rule"></div>
  <div class="flow" style="margin:.7em 0;">
    <span class="node">As-Is Study</span><span class="arrow">→</span>
    <span class="node">SRS sign-off</span><span class="arrow">→</span>
    <span class="node">SDD (HLD/LLD)</span><span class="arrow">→</span>
    <span class="node">Agile Dev · CI/CD</span><span class="arrow">→</span>
    <span class="node">SIT / UAT</span><span class="arrow">→</span>
    <span class="node">CERT-In VAPT</span><span class="arrow">→</span>
    <span class="node fin">Go-Live + HOTO</span><span class="arrow">→</span>
    <span class="node">3-yr AMC</span>
  </div>
  <div class="grid g3" style="margin-top:.4em;">
    <div class="card"><h3>Two-phase delivery</h3><p class="xsmall muted">Phase-1: PPMS, Allocation, Contractor. Phase-2: E-Tariff, CMS. Integrated Go-Live in 12 months.</p></div>
    <div class="card"><h3>Sign-offs at every gate</h3><p class="xsmall muted">SRS, SDD, UI/UX prototypes, migration reconciliation — each formally signed before proceeding.</p></div>
    <div class="card"><h3>Fortnightly cadence</h3><p class="xsmall muted">Milestone reviews, Git branching, CI/CD pipelines, demoable increments.</p></div>
  </div>
  <aside class="notes">Banks the 3 Approach &amp; Methodology marks (2.1.1). Stress the RFP-mandated 30-day As-Is study and SRS/SDD sign-offs. Government evaluators reward process discipline over speed.</aside>
</section>

<!-- 5 ARCHITECTURE -->
<section class="lt">
  <div class="kicker">Solution &amp; Reference Architecture</div>
  <h2>Layered, open-source, RFP-compliant</h2>
  <div class="title-rule"></div>
  <div class="grid" style="grid-template-columns:1fr;gap:.34em;margin-top:.4em;">
    <div class="card" style="padding:.42em .85em;"><b class="small">Channels</b> <span class="xsmall muted">Citizens · Contractors · Industries · Officers — Web · PWA · React Native mobile</span></div>
    <div class="card" style="padding:.42em .85em;"><b class="small">Edge / Security</b> <span class="xsmall muted">CDN · WAF · DDoS · TLS 1.3 · Nginx reverse proxy</span></div>
    <div class="card" style="padding:.42em .85em;"><b class="small">Presentation</b> <span class="xsmall muted">React 18 + TypeScript + Tailwind (shared design system) · WordPress (CMS)</span></div>
    <div class="card" style="padding:.42em .85em;"><b class="small">API Gateway</b> <span class="xsmall muted">OAuth2 / signed JWT · rate-limit · validation · CORS · audit</span></div>
    <div class="card" style="padding:.42em .85em;"><b class="small">Services</b> <span class="xsmall muted">PPMS · Contractor · Allocation · E-Tariff · CMS — Node 20 / Django 5 / Laravel 11</span></div>
    <div class="card" style="padding:.42em .85em;"><b class="small">Data</b> <span class="xsmall muted">PostgreSQL 16 (audit tables) · Redis 7 cache · OpenSearch / Meilisearch</span></div>
    <div class="card accent" style="padding:.42em .85em;"><b class="small">Infrastructure</b> <span class="xsmall" style="color:#d7fbff;">JAP-IT / NIC State Data Centre — HA · DR (RPO 4h / RTO 8h) · 90-day backups</span></div>
  </div>
  <p class="xsmall muted" style="margin-top:.45em;">No proprietary licensing cost · source code &amp; IPR fully handed to WRD — zero vendor lock-in.</p>
  <aside class="notes">Banks the 3 Architecture marks (2.1.2). The NIC/JAPIT slide. Name the exact mandated stack (§13). Hammer the anti-lock-in message — the RFP is obsessed with source-code/IPR ownership.</aside>
</section>

<!-- 6 SECURITY -->
<section class="lt">
  <div class="kicker">Security &amp; Compliance — non-negotiable</div>
  <h2>Security-by-design, audited before every Go-Live</h2>
  <div class="title-rule"></div>
  <div class="grid g2" style="margin-top:.5em;">
    <div class="card"><h3>Application &amp; data</h3><ul class="xsmall muted" style="margin-top:.2em;"><li>• OWASP Top 10 · static + dynamic analysis</li><li>• AES-256 at rest · TLS 1.3 in transit</li><li>• MFA (officers) · OTP (citizens) · RBAC least-privilege</li><li>• bcrypt / argon2id · tamper-evident audit logs</li></ul></div>
    <div class="card"><h3>Infra &amp; assurance</h3><ul class="xsmall muted" style="margin-top:.2em;"><li>• WAF · DDoS mitigation · 24×7 monitoring</li><li>• <b>CERT-In empanelled VAPT</b> pre-Go-Live &amp; annual</li><li>• Critical patches within 48 hrs</li><li>• CERT-In 6-hour incident reporting</li></ul></div>
  </div>
  <div style="margin-top:.6em;"><span class="chip">IT Act 2000</span><span class="chip">DPDP Act 2023</span><span class="chip">CERT-In</span><span class="chip">GIGW 3.0</span><span class="chip">NIST CSF</span><span class="chip">UIDAI / Aadhaar</span></div>
  <aside class="notes">The JAPIT comfort slide. Mention your CERT-In empanelled audit partner by name (if any). JAP-IT will not host an un-audited app — owning this de-risks you.</aside>
</section>

<!-- 7 INTEGRATION -->
<section class="lt">
  <div class="kicker">Integration Backbone</div>
  <h2>The hard plumbing, done right</h2>
  <div class="title-rule"></div>
  <div class="grid g4" style="margin-top:.5em;">
    <div class="card"><b class="small">JE-GRAS / Treasury</b><p class="xsmall muted">Division-wise revenue credit &amp; auto-reconciliation</p></div>
    <div class="card"><b class="small">DigiLocker</b><p class="xsmall muted">Certificate push &amp; QR verification</p></div>
    <div class="card"><b class="small">SWCS</b><p class="xsmall muted">Single-window inter-dept clearance</p></div>
    <div class="card"><b class="small">Jharkhand SSO</b><p class="xsmall muted">Unified identity across all 5 apps</p></div>
    <div class="card"><b class="small">SMS Gateway</b><p class="xsmall muted">JAPIT / CDAC · DLT-compliant alerts &amp; OTP</p></div>
    <div class="card"><b class="small">Email</b><p class="xsmall muted">DKIM / SPF / DMARC</p></div>
    <div class="card"><b class="small">GIS / Maps</b><p class="xsmall muted">Project &amp; dam geo-visualisation</p></div>
    <div class="card"><b class="small">DSC / e-Sign</b><p class="xsmall muted">CCA-approved · Aadhaar e-Sign</p></div>
  </div>
  <p class="small muted" style="margin-top:.6em;">Every integration is API-first — keeping WRD future-ready for mobile and new departments.</p>
  <aside class="notes">NIC/JAPIT confidence slide. If asked, walk the JE-GRAS division-wise payment as a numbered sequence: consumer → gateway → division-account resolver → treasury → reconciliation → receipt. Shown live two slides on.</aside>
</section>

<!-- 8 DEMO 1 PPMS -->
<section class="hero" data-background-gradient="linear-gradient(150deg,#04293f,#0a4763 58%,#0E7C86 135%)">
  <?= $WAVE ?>
  <div class="kicker">● Live Demonstration 1 · Component A</div>
  <h2>PPMS — Fund Requisition Lifecycle</h2>
  <div class="title-rule"></div>
  <p class="muted lead">Watch a fund demand travel WRD's real hierarchy — raise → review → finance → release — with auto requisition number, full audit trail, and a PDF Release Certificate.</p>
  <div class="flow" style="margin:.7em 0;">
    <span class="node">EE raises</span><span class="arrow">→</span><span class="node">SE recommends</span><span class="arrow">→</span><span class="node">Finance allocates</span><span class="arrow">→</span><span class="node fin">Released + Certificate</span>
  </div>
  <div style="margin-top:.5em;"><a class="demo-btn" href="<?= demo('app/ppms/requisitions.php') ?>" target="_blank">▶ Launch live PPMS</a></div>
  <p class="bi" style="margin-top:1em;">डब्ल्यूआरडी की दैनिक पीड़ा का समाधान — लाइव</p>
  <aside class="notes">SWITCH TO BROWSER. Open requisition #4 (Mandal Dam, "Pending Review"). Use the sidebar role-switcher: SE → Accept; FINANCE → allocate amount + fund code → Approve; ADMIN → Release. Open the PDF Fund Release Certificate (note the QR). WRD officials' core daily pain solved live — the knockout punch.</aside>
</section>

<!-- 9 DEMO 2 COMMAND CENTRE -->
<section class="hero" data-background-gradient="linear-gradient(150deg,#04293f,#0a4763 58%,#0E7C86 135%)">
  <?= $WAVE ?>
  <div class="kicker">● Live Demonstration 2 · Component A</div>
  <h2>Executive Command Centre</h2>
  <div class="title-rule"></div>
  <p class="muted lead">The Secretary's single screen — GIS project map of Jharkhand, KPI cards, division-wise revenue, fund-requisition status, drill-down State → Division → Project.</p>
  <div class="grid g4" style="margin:.7em 0;max-width:25em;">
    <div class="card dark"><div class="kpi"><?= $projects ?></div><div class="xsmall">Projects</div></div>
    <div class="card dark"><div class="kpi"><?= inr($sanctioned) ?></div><div class="xsmall">Outlay</div></div>
    <div class="card dark"><div class="kpi"><?= inr($revenue) ?></div><div class="xsmall">Revenue</div></div>
    <div class="card dark"><div class="kpi"><?= $divisions ?></div><div class="xsmall">Divisions</div></div>
  </div>
  <div><a class="demo-btn" href="<?= demo('index.php') ?>" target="_blank">▶ Launch Command Centre</a></div>
  <aside class="notes">SWITCH TO BROWSER as 'secretary'. Show the GIS map (colour-coded by status), click a pin. Show division-wise revenue bar + fund-requisition doughnut. "This is the screen the Secretary screen-shares to the Minister." Numbers here are pulled live from the same DB.</aside>
</section>

<!-- 10 DEMO 3 E-TARIFF -->
<section class="hero" data-background-gradient="linear-gradient(150deg,#04293f,#0a4763 58%,#0E7C86 135%)">
  <?= $WAVE ?>
  <div class="kicker">● Live Demonstration 3 · Component D · The Centrepiece</div>
  <h2>E-Tariff &amp; Division-wise Revenue</h2>
  <div class="title-rule"></div>
  <p class="muted lead">The single most distinctive RFP requirement (§10.7.2): every consumer payment is auto-routed to that consumer's <b>mapped division bank account</b>, with auto-reconciliation.</p>
  <div class="flow" style="margin:.8em 0;font-size:.66em;">
    <span class="node">🏭 Consumer</span><span class="arrow">──▶</span><span class="node">🏦 JE-GRAS</span><span class="arrow">──▶</span><span class="node fin">🏛 Correct Division A/C</span>
  </div>
  <div><a class="demo-btn" href="<?= demo('app/etariff/index.php') ?>" target="_blank">▶ Launch E-Tariff</a></div>
  <p class="bi" style="margin-top:1em;">प्रत्येक भुगतान सही प्रमंडल खाते में — पारदर्शी राजस्व</p>
  <aside class="notes">SWITCH TO BROWSER. Optionally first show JE→AE→EE flow (JE drawal entry with anomaly flag → AE verify → EE raise demand). Then as 'consumer' open a Demand-Raised bill → Pay Now → watch the animated routing Consumer→JE-GRAS→correct division account → receipt showing the exact account credited. THE centrepiece — slow down, let it land.</aside>
</section>

<!-- 11 DEMO 4 CONTRACTOR -->
<section class="hero" data-background-gradient="linear-gradient(150deg,#04293f,#0a4763 58%,#0E7C86 135%)">
  <?= $WAVE ?>
  <div class="kicker">● Live Demonstration 4 · Component B</div>
  <h2>Contractor Registration · QR · DigiLocker</h2>
  <div class="title-rule"></div>
  <p class="muted lead">Aadhaar-OTP guided wizard, JH-only GSTIN validation, E-GRAS payment, ASO→EIC processing, and a QR digital certificate pushed to DigiLocker — publicly verifiable.</p>
  <div style="margin:.7em 0;"><span class="chip">Aadhaar e-KYC</span><span class="chip">Multi-step wizard</span><span class="chip">QR certificate</span><span class="chip">DigiLocker push</span><span class="chip">Public verify</span><span class="chip">Risk scoring</span></div>
  <div><a class="demo-btn" href="<?= demo('app/contractor/index.php') ?>" target="_blank">▶ Launch Contractor Portal</a></div>
  <aside class="notes">SWITCH TO BROWSER. Run the registration wizard (Aadhaar OTP → details with JH GSTIN check → docs → E-GRAS). In the Processing Inbox forward an app ASO→…→EIC, Approve; open the certificate, the QR verify page, click "Push to DigiLocker". Point out the publicly-shown blacklisted contractor + smart risk scores.</aside>
</section>

<!-- 12 DEMO 5 CMS -->
<section class="hero" data-background-gradient="linear-gradient(150deg,#04293f,#0a4763 58%,#0E7C86 135%)">
  <?= $WAVE ?>
  <div class="kicker">● Live Demonstration 5 · Component E</div>
  <h2>CMS Website — Bilingual &amp; Accessible</h2>
  <div class="title-rule"></div>
  <p class="muted lead">One-click Hindi/English toggle, WCAG 2.1 AA accessibility toolbar, live PPMS project feed, and a non-technical publishing workflow.</p>
  <div style="margin:.7em 0;"><span class="chip">हिंदी / English</span><span class="chip">A- / A / A+</span><span class="chip">High contrast</span><span class="chip">Screen-reader</span><span class="chip">GIGW 3.0</span><span class="chip">Live PPMS feed</span></div>
  <div><a class="demo-btn" href="<?= demo('index.php') ?>" target="_blank">▶ Launch Public Website</a></div>
  <aside class="notes">SWITCH TO BROWSER. On the public site toggle हिंदी (Devanagari persists across pages), open the accessibility panel (A+/contrast). Show live stats from PPMS API. Then as 'admin' in the CMS publish a notice and show it appear instantly on the public Tenders page. NIC/GIGW marks slide — show a Lighthouse score if you can.</aside>
</section>

<!-- 13 DEMO 6 ALLOCATION -->
<section class="hero" data-background-gradient="linear-gradient(150deg,#04293f,#0a4763 58%,#0E7C86 135%)">
  <?= $WAVE ?>
  <div class="kicker">● Live Demonstration 6 · Component C</div>
  <h2>Industrial Water Allocation</h2>
  <div class="title-rule"></div>
  <p class="muted lead">Policy-driven allocation engine (source + seasonal validation), SWCS integration, and the full AE → EE → SE → CE → EIC → Secretary approval hierarchy ending in a generated licence.</p>
  <div class="flow" style="margin:.7em 0;font-size:.58em;">
    <span class="node">AE</span><span class="arrow">→</span><span class="node">EE</span><span class="arrow">→</span><span class="node">SE</span><span class="arrow">→</span><span class="node">CE</span><span class="arrow">→</span><span class="node">EIC</span><span class="arrow">→</span><span class="node fin">Secretary · Licence</span>
  </div>
  <div><a class="demo-btn" href="<?= demo('app/allocation/index.php') ?>" target="_blank">▶ Launch Allocation Portal</a></div>
  <aside class="notes">SWITCH TO BROWSER. Create an application (show source/season validation), forward AE→…→Secretary via role-switcher, approve to generate the licence. Completes the set across all five components.</aside>
</section>

<!-- 14 UX -->
<section class="lt">
  <div class="kicker">User Experience &amp; Accessibility</div>
  <h2>Government-grade, genuinely usable</h2>
  <div class="title-rule"></div>
  <div class="grid g2" style="margin-top:.5em;">
    <div class="card"><h3>Design system</h3><ul class="xsmall muted" style="margin-top:.2em;"><li>• "Institutional Water Authority" identity, State emblem &amp; branding</li><li>• Accessible colour palette (AA contrast), consistent components</li><li>• Mobile-first responsive · PWA · dark mode</li></ul></div>
    <div class="card"><h3>Accessibility (WCAG 2.1 AA)</h3><ul class="xsmall muted" style="margin-top:.2em;"><li>• Keyboard nav · ARIA landmarks · skip-to-content</li><li>• Font A-/A/A+ · high-contrast theme · screen-reader</li><li>• Page load &lt;3s on 4G · Lighthouse ≥85</li></ul></div>
  </div>
  <p class="small muted" style="margin-top:.6em;">The presentation criterion explicitly rewards a <b>user-friendly application</b> — and you've just seen it, live and bilingual.</p>
  <aside class="notes">Claims the "user-friendly application" half of the 20 presentation marks. Reference what they just watched — bilingual toggle, accessibility toolbar — so it's evidence, not a promise.</aside>
</section>

<!-- 15 MIGRATION -->
<section class="lt">
  <div class="kicker">Data Migration &amp; HOTO</div>
  <h2>Zero-data-loss is the acceptance criterion</h2>
  <div class="title-rule"></div>
  <div class="flow" style="margin:.7em 0;">
    <span class="node">Data audit (30d)</span><span class="arrow">→</span><span class="node">Field mapping sign-off</span><span class="arrow">→</span><span class="node">2× mock migrations</span><span class="arrow">→</span><span class="node">Reconciliation</span><span class="arrow">→</span><span class="node">30-day parallel run</span><span class="arrow">→</span><span class="node on">Cut-over + rollback</span>
  </div>
  <div class="grid g3" style="margin-top:.4em;">
    <div class="card"><h3>Checksum integrity</h3><p class="xsmall muted">Every document &amp; financial record verified by checksum and count reconciliation.</p></div>
    <div class="card"><h3>HOTO from incumbents</h3><p class="xsmall muted">Structured take-over of legacy code, data &amp; infra from existing vendors / NIC.</p></div>
    <div class="card"><h3>Signed reconciliation</h3><p class="xsmall muted">Cut-over only after WRD signs the Reconciliation Sign-off Certificate.</p></div>
  </div>
  <aside class="notes">Crushes the #1 Government fear. Hand the committee a one-page "Zero-Data-Loss Migration Playbook" here. Visibly supports the Project Plan / Data Governance marks (2.1.4) and builds trust more than any feature.</aside>
</section>

<!-- 16 DATA GOVERNANCE -->
<section class="lt">
  <div class="kicker">Data Governance</div>
  <h2>WRD owns everything; we protect it</h2>
  <div class="title-rule"></div>
  <div class="grid g2" style="margin-top:.5em;">
    <div class="card"><h3>Ownership &amp; classification</h3><ul class="xsmall muted" style="margin-top:.2em;"><li>• All data, source code, IPR remain WRD property</li><li>• Data classification &amp; retention (90-day backups)</li><li>• Forced password reset for migrated officer accounts</li></ul></div>
    <div class="card"><h3>Protection &amp; accountability</h3><ul class="xsmall muted" style="margin-top:.2em;"><li>• DPDP Act 2023 compliance for all personal data</li><li>• RBAC least-privilege + periodic access review</li><li>• Tamper-evident audit logs on every CRUD / approval</li></ul></div>
  </div>
  <aside class="notes">Banks the Data Governance portion of the 5 marks in 2.1.4. Reassure on DPDP 2023 and the "no commercial exploitation of WRD data" clause — read it back to them.</aside>
</section>

<!-- 17 TEAM -->
<section class="lt">
  <div class="kicker">Engagement Team &amp; PMU</div>
  <h2>A named team, on-site in Ranchi</h2>
  <div class="title-rule"></div>
  <div class="grid g3" style="margin-top:.5em;">
    <div class="card"><h3>Project Manager</h3><p class="xsmall muted">12+ yrs · single point of accountability, risk &amp; CR register</p></div>
    <div class="card"><h3>Sr. Software Developer</h3><p class="xsmall muted">8+/10+ yrs · tech lead, code reviews, architecture</p></div>
    <div class="card"><h3>UI/UX Designer</h3><p class="xsmall muted">4+ yrs · accessibility &amp; bilingual design</p></div>
    <div class="card"><h3>Mobile App Developer</h3><p class="xsmall muted">React Native — single codebase, Android &amp; iOS</p></div>
    <div class="card"><h3>DBA + QA Engineer</h3><p class="xsmall muted">PostgreSQL tuning, audit tables, test automation</p></div>
    <div class="card accent"><h3>On-site PMU</h3><p class="xsmall" style="color:#d7fbff;">Dedicated unit at WRD Ranchi for the full 3-yr AMC</p></div>
  </div>
  <p class="small muted" style="margin-top:.6em;">CVs attached &amp; signed. Local office operational from Day 1 — named people you can hold accountable.</p>
  <aside class="notes">Banks the 4 Engagement Team &amp; Work Plan marks (2.1.3). Show faces/names. "Named = real = low-risk." Stress on-site Ranchi PMU and the mandatory local office within 2 months of LOA.</aside>
</section>

<!-- 18 PLAN -->
<section class="lt">
  <div class="kicker">Project Plan &amp; WBS</div>
  <h2>12 months to integrated Go-Live</h2>
  <div class="title-rule"></div>
  <ul class="tl" style="margin-top:.5em;">
    <li><b>T0 + 1–8 wks</b> — Mobilisation · As-Is study · SRS (all 5 components)</li>
    <li><b>T0 + 16 wks</b> — SDD (HLD/LLD) + UI/UX clickable prototypes (Hindi &amp; English)</li>
    <li><b>T0 + 36–40 wks</b> — Phase-1 dev + UAT: PPMS, Allocation, Contractor</li>
    <li><b>T0 + 46–48 wks</b> — Phase-2 dev + UAT: E-Tariff &amp; CMS Website</li>
    <li><b>T0 + 52 wks</b> — Integrated Go-Live · VAPT clearance · Training · HOTO</li>
    <li><b>+ 3 years</b> — Comprehensive AMC (extendable up to 2 years)</li>
  </ul>
  <p class="xsmall muted" style="margin-top:.4em;">Payment milestones aligned to RFP §16: 30% (SRS/SDD) · 20% Phase-1 · 10% Phase-2 · 10% UAT/VAPT · 30% AMC.</p>
  <aside class="notes">Banks the WBS portion of 2.1.4. Mirrors the RFP's own macro schedule (§16). Payment milestones map 1:1 to their schedule — no commercial surprise.</aside>
</section>

<!-- 19 SLA -->
<section class="lt">
  <div class="kicker">SLA &amp; O&amp;M Commitment</div>
  <h2>We stay, and we're accountable</h2>
  <div class="title-rule"></div>
  <div class="grid g2" style="margin-top:.5em;align-items:start;">
    <div class="card"><table class="mini">
      <tr><td>Critical incident response</td><td>2 hrs (24×7)</td></tr>
      <tr><td>Critical resolution</td><td>4 hrs</td></tr>
      <tr><td>Major / Minor resolution</td><td>8 hrs / 24 hrs</td></tr>
      <tr><td>Manpower attendance</td><td>≥ 95% / month</td></tr>
      <tr><td>Security patch (critical)</td><td>within 48 hrs</td></tr>
    </table></div>
    <div class="card accent"><h3>Our commitment</h3><p class="xsmall" style="color:#eafdff;margin-top:.2em;">We <b>accept the RFP SLA matrix</b> and publish a <b>monthly SLA compliance dashboard</b> to WRD. Annual VAPT &amp; remediation across the full 3-year AMC.</p></div>
  </div>
  <aside class="notes">Accepting the SLA matrix signals confidence and accountability. Offer the monthly SLA dashboard — small commitment, reads as transparency.</aside>
</section>

<!-- 20 INNOVATION -->
<section class="lt">
  <div class="kicker">Innovation Showcase</div>
  <h2>Beyond compliance — at no extra cost</h2>
  <div class="title-rule"></div>
  <div class="grid g3" style="margin-top:.5em;">
    <div class="card"><h3>GIS monitoring</h3><p class="xsmall muted">Colour-coded project map, drill-down. <span class="chip">In scope</span></p></div>
    <div class="card"><h3>AI anomaly detection</h3><p class="xsmall muted">Flags abnormal water drawal. <span class="chip">Value-add</span></p></div>
    <div class="card"><h3>Predictive fund utilisation</h3><p class="xsmall muted">Burn-rate &amp; year-end forecast. <span class="chip">Value-add</span></p></div>
    <div class="card"><h3>Contractor risk scoring</h3><p class="xsmall muted">History + blacklist → risk band. <span class="chip">Value-add</span></p></div>
    <div class="card"><h3>Command centre</h3><p class="xsmall muted">Cross-component executive view. <span class="chip">In scope+</span></p></div>
    <div class="card"><h3>Mobile-first / PWA</h3><p class="xsmall muted">React Native app, offline reading. <span class="chip">In scope</span></p></div>
  </div>
  <p class="small muted" style="margin-top:.6em;">We clearly distinguish <b>RFP-scope</b> from <b>value-added</b> — precise reading <i>and</i> generosity.</p>
  <aside class="notes">Earns the "wow" memory. Explicitly label in-scope vs free value-adds — evaluators reward precise scope reading plus generosity. Don't over-promise AI you can't demo.</aside>
</section>

<!-- 21 WHY US -->
<section class="lt">
  <div class="kicker">Why Us</div>
  <h2>The safest, most execution-ready partner</h2>
  <div class="title-rule"></div>
  <div class="grid g2" style="margin-top:.5em;column-gap:2em;">
    <div class="checkrow"><span class="tick">✓</span><span><b>Local &amp; present</b> — Ranchi office, on-site PMU, prior Jharkhand-Govt delivery</span></div>
    <div class="checkrow"><span class="tick">✓</span><span><b>Certified</b> — ISO 9001 / 27001 / 20000-1, GST, Govt empanelment</span></div>
    <div class="checkrow"><span class="tick">✓</span><span><b>Secure</b> — CERT-In audit partner, DPDP &amp; OWASP by design</span></div>
    <div class="checkrow"><span class="tick">✓</span><span><b>Proven integrated build</b> — you just saw it running, end to end</span></div>
    <div class="checkrow"><span class="tick">✓</span><span><b>Zero lock-in</b> — full source code &amp; IPR to WRD</span></div>
    <div class="checkrow"><span class="tick">✓</span><span><b>Ready Week 1</b> — named team, clear plan, accepted SLAs</span></div>
  </div>
  <aside class="notes">Closing primacy. These six pillars are the line you want the committee to repeat in deliberation. Closing statement: "You're not choosing a feature list; you're choosing the partner least likely to fail your migration, your VAPT and your revenue reconciliation — and most likely to make WRD a model department."</aside>
</section>

<!-- 22 THANK YOU -->
<section class="hero cover" data-background-gradient="linear-gradient(150deg,#04293f,#0a4763 55%,#0E7C86 130%)">
  <?= $WAVE ?>
  <div class="cwrap">
    <div class="cleft">
      <div class="kicker">Thank You · धन्यवाद</div>
      <h1>Ready to begin<br>in Week 1.</h1>
      <div class="title-rule"></div>
      <p class="muted lead">One WRD. One secure, citizen-centric, revenue-smart digital backbone — built, demonstrated, and ready for Jharkhand.</p>
      <p class="bi" style="margin-top:1.1em;">[YOUR COMPANY] · RANCHI · contact@yourcompany.in</p>
    </div>
    <div class="cright">
      <div class="ring" style="width:360px;height:360px;"></div>
      <div class="ring" style="width:270px;height:270px;border-color:rgba(255,255,255,.22);"></div>
      <div class="dropbig"><span>✓</span></div>
    </div>
  </div>
  <aside class="notes">Confident close. Restate the single narrative — "we followed one rupee and one drop of water full circle through all five components." Invite questions. Q&amp;A owners: PM = commercial/timeline/SLA; Architect = security/integration/DR; Lead Dev = functional/workflow; UI/UX = accessibility/GIGW.</aside>
</section>

</div></div>

<svg class="wavefix" viewBox="0 0 1280 140" preserveAspectRatio="none" aria-hidden="true"><path d="M0 70 C 180 20 360 120 540 70 C 720 20 900 120 1080 65 C 1180 38 1240 60 1280 55 L1280 140 L0 140 Z" fill="rgba(255,255,255,.06)"/><path d="M0 92 C 200 52 380 132 560 90 C 760 44 940 132 1120 88 C 1200 66 1250 82 1280 78 L1280 140 L0 140 Z" fill="rgba(255,255,255,.05)"/></svg>
<div class="foot"><span class="fl"><span class="drop"><svg width="14" height="14" viewBox="0 0 24 24"><path d="M12 2.5C12 2.5 5 10 5 14.5a7 7 0 0 0 14 0C19 10 12 2.5 12 2.5Z" fill="#fff"/></svg></span>WRD Jharkhand · Integrated Digital Backbone</span><span>Technical Presentation</span></div>

<script src="https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/dist/reveal.js"></script>
<script src="https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/plugin/notes/notes.js"></script>
<script>
var _qp = new URLSearchParams(location.search);
Reveal.initialize({
  width:1280, height:720, margin:0.04, minScale:0.2, maxScale:2.0,
  center:true, hash:true, slideNumber:'c/t', transition:(_qp.get('t')||'slide'), controls:true, progress:true,
  plugins:[ RevealNotes ]
});
function paint(s){ document.body.classList.toggle('hero-active', s && s.classList.contains('hero')); }
Reveal.on('ready', e=>paint(e.currentSlide));
Reveal.on('slidechanged', e=>paint(e.currentSlide));
</script>
</body>
</html>
