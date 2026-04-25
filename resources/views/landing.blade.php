<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>nuestore — Social Media Growth Otomatis</title>
<link rel="icon" href="/images/logo-png.png" type="image/png">
<link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,300;1,400&display=swap" rel="stylesheet">
<style>
:root {
  --bg: #000000;
  --s1: #0a0b10;
  --s2: #12141c;
  --ink: #ffffff;
  --ink2: #a0a5b5;
  --ink3: #555969;
  --primary: #0bd4fd;
  --primary2: #5ce4ff;
  --primary3: #b0f2ff;
  --secondary: #ff007f;
  --accent: #ff5eb0;
  --border: rgba(11,212,253,0.15);
  --border2: rgba(11,212,253,0.3);
}

*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}

body{
  font-family:'Poppins',sans-serif;
  background:var(--bg);
  color:var(--ink);
  overflow-x:hidden;
}

/* ── CURSOR ── */
.cur,.cur-t{position:fixed;pointer-events:none;z-index:9999;border-radius:50%}
.cur{width:8px;height:8px;background:var(--primary);mix-blend-mode:screen;transition:transform .15s}
.cur-t{width:32px;height:32px;border:1px solid rgba(0,195,255,.5);top:-16px;left:-16px;transition:all .18s cubic-bezier(.25,.46,.45,.94)}

/* ── STARFIELD ── */
.stars{position:fixed;inset:0;z-index:0;overflow:hidden;pointer-events:none}
.star{position:absolute;border-radius:50%;background:#fff;animation:twinkle var(--d,3s) ease-in-out infinite;opacity:0}
@keyframes twinkle{0%,100%{opacity:0;transform:scale(.5)}50%{opacity:var(--o,.6);transform:scale(1)}}

/* ── SCAN LINE ── */
body::after{
  content:'';
  position:fixed;inset:0;
  background:repeating-linear-gradient(0deg,transparent,transparent 2px,rgba(0,0,0,.03) 2px,rgba(0,0,0,.03) 4px);
  pointer-events:none;z-index:1;
}

/* ── AMBIENT ── */
.amb{position:fixed;border-radius:50%;filter:blur(120px);opacity:.07;pointer-events:none;z-index:0;animation:drift var(--t,12s) ease-in-out infinite alternate}
.amb-a{width:600px;height:600px;background:#c8a96e;top:-200px;right:-200px;--t:14s}
.amb-b{width:500px;height:500px;background:#22d3c8;bottom:-100px;left:-150px;--t:18s}
.amb-c{width:300px;height:300px;background:#ff6b9d;top:40%;left:40%;--t:10s}
@keyframes drift{from{transform:translate(0,0) scale(1)}to{transform:translate(40px,30px) scale(1.08)}}

/* ── NAV ── */
nav{
  position:fixed;top:0;left:0;right:0;z-index:100;
  padding:22px 64px;
  display:flex;align-items:center;justify-content:space-between;
  background:rgba(3,5,10,.75);
  backdrop-filter:blur(24px);
  border-bottom:1px solid var(--border);
}
.logo{font-weight:700;font-size:1.25rem;letter-spacing:.5px;color:var(--primary2)}
.logo em{font-style:normal;color:var(--ink);opacity:.5}
.nav-pill{
  display:flex;align-items:center;gap:10px;
  padding:10px 22px;
  border:1px solid var(--border2);border-radius:100px;
  font-size:.82rem;font-weight:500;color:var(--primary2);
  text-decoration:none;
  background:rgba(0,195,255,.04);
  transition:all .3s;cursor:none;
}
.nav-pill:hover{background:rgba(0,195,255,.1);border-color:var(--primary);color:var(--primary3)}

/* ── HERO ── */
.hero{
  position:relative;z-index:2;
  min-height:100vh;
  display:flex;align-items:center;justify-content:center;
  padding:140px 64px 80px;text-align:center;
}
.hero-in{max-width:820px}

.eyebrow{
  display:inline-flex;align-items:center;gap:10px;
  padding:7px 20px;
  border:1px solid var(--border2);border-radius:100px;
  font-size:.72rem;font-weight:500;letter-spacing:1.5px;text-transform:uppercase;
  color:var(--primary);margin-bottom:36px;
  animation:up .9s ease both;
}
.dot-live{
  width:6px;height:6px;background:var(--secondary);border-radius:50%;
  box-shadow:0 0 8px var(--secondary);
  animation:livepulse 1.8s ease infinite;
}
@keyframes livepulse{0%,100%{opacity:1;box-shadow:0 0 4px var(--secondary)}50%{opacity:.3;box-shadow:0 0 12px var(--secondary)}}

h1.headline{
  font-weight:800;
  font-size:clamp(2.6rem,6.5vw,5rem);
  line-height:1.06;letter-spacing:-1.5px;
  margin-bottom:24px;
  animation:up .9s ease .1s both;
}
.headline .shimmer{
  background:linear-gradient(105deg,var(--primary) 0%,var(--primary3) 35%,var(--secondary) 60%,var(--primary2) 100%);
  background-size:300% auto;
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;
  animation:shim 5s linear infinite,up .9s ease .1s both;
}
@keyframes shim{0%{background-position:0% center}100%{background-position:300% center}}

.hero p{
  font-size:1.05rem;font-weight:300;color:var(--ink2);
  line-height:1.8;max-width:520px;margin:0 auto 44px;
  animation:up .9s ease .2s both;
}

.hero-btns{display:flex;gap:14px;justify-content:center;flex-wrap:wrap;animation:up .9s ease .3s both}

.btn-gold{
  display:inline-flex;align-items:center;gap:10px;
  padding:15px 34px;
  background:linear-gradient(135deg,var(--primary),#007bb5);
  color:#0a0600;border-radius:8px;
  font-family:'Poppins',sans-serif;font-weight:600;font-size:.9rem;
  text-decoration:none;cursor:none;
  transition:transform .3s,box-shadow .3s;
  box-shadow:0 0 32px rgba(0,195,255,.2);
  position:relative;overflow:hidden;
}
.btn-gold::before{
  content:'';position:absolute;inset:0;
  background:linear-gradient(135deg,rgba(255,255,255,.2),transparent);
  opacity:0;transition:opacity .3s;
}
.btn-gold:hover{transform:translateY(-2px);box-shadow:0 0 48px rgba(0,195,255,.4)}
.btn-gold:hover::before{opacity:1}

.btn-ghost{
  display:inline-flex;align-items:center;gap:10px;
  padding:15px 34px;background:transparent;
  border:1px solid rgba(255,255,255,.1);border-radius:8px;
  color:var(--ink2);
  font-family:'Poppins',sans-serif;font-weight:500;font-size:.9rem;
  text-decoration:none;cursor:none;transition:all .3s;
}
.btn-ghost:hover{border-color:var(--primary);color:var(--primary);background:rgba(0,195,255,.04)}

/* ── TICKER ── */
.ticker-wrap{
  position:relative;z-index:2;overflow:hidden;
  padding:14px 0;
  border-top:1px solid var(--border);border-bottom:1px solid var(--border);
  background:var(--s1);
}
.ticker-track{display:flex;gap:64px;width:max-content;animation:ticker 20s linear infinite}
@keyframes ticker{from{transform:translateX(0)}to{transform:translateX(-50%)}}
.ticker-item{
  display:flex;align-items:center;gap:10px;white-space:nowrap;
  font-size:.75rem;font-weight:500;letter-spacing:1.5px;text-transform:uppercase;color:var(--ink3);
}
.ticker-item .t-accent{color:var(--primary);font-weight:600}

/* ── STATS ── */
.stats{position:relative;z-index:2;padding:80px 64px;display:flex;justify-content:center}
.stats-row{
  display:grid;grid-template-columns:repeat(3,1fr);
  max-width:860px;width:100%;
  border:1px solid var(--border);border-radius:20px;overflow:hidden;background:var(--s1);
}
.stat{
  padding:40px 32px;text-align:center;
  border-right:1px solid var(--border);
  transition:background .3s;position:relative;overflow:hidden;
}
.stat:last-child{border-right:none}
.stat::before{
  content:'';position:absolute;top:0;left:0;right:0;height:1px;
  background:linear-gradient(90deg,transparent,var(--primary),transparent);
  transform:scaleX(0);transition:transform .5s ease;
}
.stat:hover{background:var(--s2)}
.stat:hover::before{transform:scaleX(1)}
.stat-n{
  font-weight:700;font-size:2.2rem;letter-spacing:-1px;
  background:linear-gradient(135deg,var(--primary2),var(--secondary));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;
  display:block;margin-bottom:4px;
}
.stat-l{font-size:.78rem;font-weight:400;color:var(--ink2);letter-spacing:.5px}

/* ── SECTION BASE ── */
.sec{position:relative;z-index:2;padding:100px 64px}
.sec-max{max-width:1080px;margin:0 auto}
.tag{font-size:.68rem;font-weight:600;letter-spacing:3px;text-transform:uppercase;color:var(--primary);margin-bottom:14px}
.sec-h{font-weight:700;font-size:clamp(1.8rem,3.5vw,2.8rem);letter-spacing:-1px;line-height:1.1;margin-bottom:14px}
.sec-sub{font-size:.95rem;font-weight:300;color:var(--ink2);max-width:440px;line-height:1.8}

/* ── PLATFORM CARDS ── */
.plat-grid{
  display:grid;grid-template-columns:repeat(2,1fr);
  gap:2px;background:var(--border);
  border-radius:24px;overflow:hidden;margin-top:60px;
}
.plat-card{
  background:var(--s1);padding:56px 48px;
  position:relative;overflow:hidden;transition:background .3s;cursor:none;
}
.plat-card:hover{background:var(--s2)}
.plat-card .bg-num{
  position:absolute;bottom:-20px;right:-10px;
  font-weight:800;font-size:9rem;line-height:1;letter-spacing:-5px;
  opacity:.03;color:var(--primary);pointer-events:none;font-style:italic;
}
.plat-icon{
  width:64px;height:64px;border-radius:18px;
  display:flex;align-items:center;justify-content:center;font-size:0;
  margin-bottom:24px;
}
.plat-icon svg{width:32px;height:32px}
.plat-icon-ig{background:linear-gradient(135deg,#f09433 0%,#e6683c 25%,#dc2743 50%,#cc2366 75%,#bc1888 100%)}
.plat-icon-tt{background:#010101;border:1px solid rgba(255,255,255,.1)}
.plat-name{font-weight:700;font-size:1.4rem;margin-bottom:10px;letter-spacing:-.5px}
.plat-desc{font-size:.875rem;font-weight:300;color:var(--ink2);line-height:1.8;margin-bottom:28px}
.plat-pills{display:flex;flex-wrap:wrap;gap:8px}
.pill{
  font-size:.68rem;font-weight:500;letter-spacing:.5px;
  padding:5px 12px;border-radius:100px;
  background:rgba(0,195,255,.07);border:1px solid rgba(0,195,255,.18);color:var(--primary);
}

/* ── WHY US ── */
.why-sec{background:var(--s1)}
.why-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin-top:60px}
.why-card{
  padding:40px;border:1px solid var(--border);border-radius:20px;
  background:var(--bg);transition:border-color .3s,transform .3s;cursor:none;
  position:relative;overflow:hidden;
}
.why-card::after{
  content:'';position:absolute;bottom:0;left:0;right:0;height:1px;
  background:linear-gradient(90deg,transparent,var(--primary),transparent);
  opacity:0;transition:opacity .4s;
}
.why-card:hover{border-color:var(--border2);transform:translateY(-3px)}
.why-card:hover::after{opacity:1}
.why-ico{
  width:48px;height:48px;border-radius:12px;
  background:rgba(0,195,255,.06);border:1px solid rgba(0,195,255,.14);
  display:flex;align-items:center;justify-content:center;margin-bottom:20px;
}
.why-ico svg{width:22px;height:22px;stroke:var(--primary);fill:none;stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round}
.why-card h3{font-weight:600;font-size:1.05rem;margin-bottom:10px}
.why-card p{font-size:.855rem;font-weight:300;color:var(--ink2);line-height:1.75}

/* ── STEPS ── */
.steps-wrap{margin-top:64px;display:flex;flex-direction:column}
.step-row{display:grid;grid-template-columns:64px 1fr;gap:0 32px;padding-bottom:48px;position:relative}
.step-row:last-child{padding-bottom:0}
.step-row:last-child .step-line{display:none}
.step-left{display:flex;flex-direction:column;align-items:center}
.step-num{
  width:56px;height:56px;border-radius:50%;
  border:1px solid var(--primary);
  display:flex;align-items:center;justify-content:center;
  font-weight:700;font-size:1rem;color:var(--primary);
  background:var(--bg);flex-shrink:0;position:relative;z-index:1;
  box-shadow:0 0 24px rgba(0,195,255,.12);
}
.step-line{width:1px;flex:1;margin-top:8px;background:linear-gradient(180deg,var(--border2),transparent)}
.step-body{padding-top:14px}
.step-body h4{font-weight:600;font-size:1rem;margin-bottom:8px}
.step-body p{font-size:.86rem;font-weight:300;color:var(--ink2);line-height:1.75}

/* ── TESTIMONIALS ── */
.testi-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:60px}
.testi-card{
  background:var(--s1);border:1px solid var(--border);border-radius:20px;
  padding:32px;transition:border-color .3s,transform .3s;
}
.testi-card:hover{border-color:var(--border2);transform:translateY(-4px)}
.stars{color:var(--primary);font-size:.78rem;letter-spacing:2px;margin-bottom:16px}
.testi-q{font-size:.86rem;font-weight:300;color:var(--ink2);line-height:1.75;margin-bottom:20px;font-style:italic}
.testi-q::before{content:'\201C';font-size:1.4rem;line-height:0;vertical-align:-.3em;color:var(--primary);opacity:.5;margin-right:2px}
.testi-auth{display:flex;align-items:center;gap:12px}
.testi-av{
  width:36px;height:36px;border-radius:50%;
  background:linear-gradient(135deg,var(--primary),#7a5020);
  display:flex;align-items:center;justify-content:center;
  font-weight:600;font-size:.75rem;color:#0a0600;
}
.testi-name{font-weight:600;font-size:.85rem}
.testi-role{font-size:.73rem;color:var(--ink2);margin-top:1px}

/* ── FAQ ── */
.faq-list{margin-top:48px}
.faq-item{border-bottom:1px solid var(--border)}
.faq-q-row{
  display:flex;align-items:center;justify-content:space-between;
  padding:22px 0;cursor:none;
  font-weight:500;font-size:.95rem;transition:color .3s;
}
.faq-q-row:hover{color:var(--primary)}
.faq-toggle{
  width:28px;height:28px;flex-shrink:0;
  border:1px solid var(--border2);border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-size:.85rem;color:var(--ink2);
  transition:all .3s;margin-left:20px;
}
.faq-item.open .faq-toggle{border-color:var(--primary);color:var(--primary);transform:rotate(45deg)}
.faq-ans{
  font-size:.87rem;font-weight:300;color:var(--ink2);line-height:1.8;
  max-height:0;overflow:hidden;transition:max-height .4s ease,padding .3s;
}
.faq-item.open .faq-ans{max-height:160px;padding-bottom:20px}

/* ── CTA ── */
.cta-wrap{position:relative;z-index:2;padding:80px 64px 120px}
.cta-box{
  max-width:740px;margin:0 auto;
  background:var(--s1);border:1px solid var(--border);
  border-radius:28px;padding:80px 64px;text-align:center;
  position:relative;overflow:hidden;
}
.cta-box::before{
  content:'';position:absolute;top:-1px;left:25%;right:25%;height:1px;
  background:linear-gradient(90deg,transparent,var(--primary),transparent);
}
.cta-box::after{
  content:'';position:absolute;
  width:400px;height:400px;
  background:radial-gradient(circle,rgba(0,195,255,.06) 0%,transparent 70%);
  top:50%;left:50%;transform:translate(-50%,-50%);pointer-events:none;
}
.cta-box h2{font-weight:700;font-size:clamp(1.7rem,3.5vw,2.6rem);letter-spacing:-1px;margin-bottom:14px;position:relative;z-index:1}
.cta-box p{font-size:.95rem;font-weight:300;color:var(--ink2);margin-bottom:36px;position:relative;z-index:1}
.cta-btns{display:flex;gap:14px;justify-content:center;flex-wrap:wrap;position:relative;z-index:1}

.btn-tg{
  display:inline-flex;align-items:center;gap:10px;
  padding:15px 34px;
  background:linear-gradient(135deg,#1e88c7,#1566a0);
  color:#fff;border-radius:8px;
  font-family:'Poppins',sans-serif;font-weight:600;font-size:.9rem;
  text-decoration:none;cursor:none;
  transition:transform .3s,box-shadow .3s;
  box-shadow:0 0 32px rgba(30,136,199,.2);
}
.btn-tg:hover{transform:translateY(-2px);box-shadow:0 0 48px rgba(30,136,199,.4)}
.btn-tg svg{width:18px;height:18px;flex-shrink:0}

/* ── FOOTER ── */
footer{
  position:relative;z-index:2;padding:32px 64px;
  border-top:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;
}
.f-logo{font-weight:700;font-size:1rem;color:var(--primary)}
.f-copy{font-size:.75rem;color:var(--ink3)}
.f-links{display:flex;gap:24px}
.f-links a{font-size:.76rem;color:var(--ink3);text-decoration:none;transition:color .3s}
.f-links a:hover{color:var(--primary)}

/* ── REVEAL ── */
.rv{opacity:0;transform:translateY(28px);transition:all .75s cubic-bezier(.22,1,.36,1)}
.rv.in{opacity:1;transform:none}
.rv2{opacity:0;transform:translateY(28px);transition:all .75s cubic-bezier(.22,1,.36,1) .15s}
.rv2.in{opacity:1;transform:none}

@keyframes up{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:none}}

@media(max-width:768px){
  nav{padding:16px 24px}
  .hero,.sec,.cta-wrap,.stats{padding-left:24px;padding-right:24px}
  h1.headline{font-size:2.3rem}
  .plat-grid,.why-grid,.testi-grid{grid-template-columns:1fr}
  .stats-row{grid-template-columns:1fr}
  .stat{border-right:none;border-bottom:1px solid var(--border)}
  .stat:last-child{border-bottom:none}
  footer{padding:28px 24px;flex-direction:column;text-align:center}
  .cta-box{padding:48px 28px}
  .float-wrap {
    bottom: 12px;
    left: 12px;
    right: 12px;
    flex-direction: row;
    justify-content: space-between;
    transform: none;
    gap: 8px;
  }
  .float-btn {
    flex: 1;
    justify-content: center;
    padding: 10px 4px;
    gap: 4px;
    border-radius: 100px;
  }
  .float-icon {
    width: 22px;
    height: 22px;
    background: transparent;
  }
  .float-icon svg {
    width: 16px;
    height: 16px;
  }
  .float-text span {
    display: none;
  }
  .float-text strong {
    font-size: 0.8rem;
  }
}

/* ── FLOATING BUTTONS ── */
.float-wrap {
  position: fixed;
  bottom: 24px;
  right: 24px;
  display: flex;
  flex-direction: row;
  gap: 12px;
  z-index: 9999;
}
.float-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 16px 6px 6px;
  border-radius: 100px;
  text-decoration: none;
  font-family: 'Poppins', sans-serif;
  color: #fff !important;
  box-shadow: 0 4px 24px rgba(0,0,0,0.6);
  transition: transform 0.3s, box-shadow 0.3s, filter 0.3s;
  cursor: pointer;
}
.float-btn:hover {
  transform: translateY(-3px) scale(1.02);
  filter: brightness(1.1);
}
.float-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255,255,255,0.2);
}
.float-text {
  display: flex;
  flex-direction: column;
  text-align: left;
}
.float-text strong {
  font-size: 0.8rem;
  font-weight: 700;
  line-height: 1.1;
  color: #fff;
}
.float-text span {
  font-size: 0.55rem;
  font-weight: 600;
  letter-spacing: 1px;
  opacity: 0.9;
  color: rgba(255,255,255,0.8);
}
.float-wa {
  background: linear-gradient(135deg, #25D366, #128C7E);
  border: 1px solid rgba(255,255,255,0.1);
}
.float-tg {
  background: linear-gradient(135deg, #0088cc, #005f8f);
  border: 1px solid rgba(255,255,255,0.1);
}
.float-btn svg {
  width: 16px;
  height: 16px;
  fill: #fff;
}

</style>
</head>
<body>

<div class="cur" id="c"></div>
<div class="cur-t" id="ct"></div>
<div class="stars" id="stars"></div>
<div class="amb amb-a"></div>
<div class="amb amb-b"></div>
<div class="amb amb-c"></div>

<!-- NAV -->
<nav>
  <a href="/" class="logo" style="text-decoration: none;">
    <img src="/images/logo-png.png" alt="nuestore" style="height:36px; width:auto; object-fit:contain; display:block;">
  </a>
  <a href="https://t.me/Nuestore" target="_blank" class="nav-pill">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.248l-2.016 9.504c-.148.665-.54.828-1.092.514l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L7.46 14.725l-2.95-.924c-.64-.203-.654-.64.137-.948l11.521-4.443c.537-.194 1.006.131.394.838z"/></svg>
    Order via Bot
  </a>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-in">
    <div class="eyebrow"><div class="dot-live"></div>Proses Otomatis 24/7</div>
    <h1 class="headline">
      Bersama <span class="shimmer">nuestore</span>
    </h1>
    <p>Akun sosial media Anda akan selalu tumbuh konsisten dengan layanan yang dirancang khusus sesuai kebutuhan Anda. Raih lebih banyak pengikut, keterlibatan tinggi, dan hasil yang nyata.</p>
    <div class="hero-btns">
      <a href="https://t.me/Nuestore" class="btn-gold" target="_blank">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.248l-2.016 9.504c-.148.665-.54.828-1.092.514l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L7.46 14.725l-2.95-.924c-.64-.203-.654-.64.137-.948l11.521-4.443c.537-.194 1.006.131.394.838z"/></svg>
        Order via Bot Telegram
      </a>
      <a href="https://wa.me/62882007207715" class="btn-ghost" target="_blank">
        WhatsApp CS
      </a>
    </div>
  </div>
</section>

<!-- TICKER -->
<div class="ticker-wrap">
  <div class="ticker-track">
    <span class="ticker-item"><span class="t-accent">✦</span> Instagram Followers</span>
    <span class="ticker-item"><span class="t-accent">✦</span> TikTok Views</span>
    <span class="ticker-item"><span class="t-accent">✦</span> Instagram Likes</span>
    <span class="ticker-item"><span class="t-accent">✦</span> TikTok Followers</span>
    <span class="ticker-item"><span class="t-accent">✦</span> Instagram Reels Views</span>
    <span class="ticker-item"><span class="t-accent">✦</span> TikTok Likes</span>
    <span class="ticker-item"><span class="t-accent">✦</span> Instagram Comments</span>
    <span class="ticker-item"><span class="t-accent">✦</span> TikTok Shares</span>
    <span class="ticker-item"><span class="t-accent">✦</span> Instagram Followers</span>
    <span class="ticker-item"><span class="t-accent">✦</span> TikTok Views</span>
    <span class="ticker-item"><span class="t-accent">✦</span> Instagram Likes</span>
    <span class="ticker-item"><span class="t-accent">✦</span> TikTok Followers</span>
    <span class="ticker-item"><span class="t-accent">✦</span> Instagram Reels Views</span>
    <span class="ticker-item"><span class="t-accent">✦</span> TikTok Likes</span>
    <span class="ticker-item"><span class="t-accent">✦</span> Instagram Comments</span>
    <span class="ticker-item"><span class="t-accent">✦</span> TikTok Shares</span>
  </div>
</div>

<!-- STATS -->
<div class="stats">
  <div class="stats-row rv">
    <div class="stat"><span class="stat-n">10K+</span><span class="stat-l">Pesanan Selesai</span></div>
    <div class="stat"><span class="stat-n">99.8%</span><span class="stat-l">Tingkat Keberhasilan</span></div>
    <div class="stat"><span class="stat-n">&lt; 5 Mnt</span><span class="stat-l">Rata-rata Proses</span></div>
  </div>
</div>

<!-- PLATFORM -->
<section class="sec" id="platform">
  <div class="sec-max">
    <div class="rv">
      <div class="tag">Fitur</div>
      <h2 class="sec-h">Cara Termudah<br>Mencapai Target Digital Anda</h2>
      <p class="sec-sub">Ambil kendali penuh atas pertumbuhan akun Anda. Kami sediakan sistemnya, Anda tentukan hasilnya.</p>
    </div>
    <div class="testi-grid rv2">
      <div class="testi-card" style="text-align:center; padding:48px 32px">
        <h3 style="font-size:1.1rem; margin-bottom:12px; color:var(--primary2)">Insight Meningkat</h3>
        <p style="font-size:.86rem; font-weight:300; color:var(--ink2); line-height:1.75;">Tingkatkan performa akun dengan layanan yang benar-benar berdampak pada jangkauan dan engagement Anda.</p>
      </div>
      <div class="testi-card" style="text-align:center; padding:48px 32px">
        <h3 style="font-size:1.1rem; margin-bottom:12px; color:var(--primary2)">Garansi 100%</h3>
        <p style="font-size:.86rem; font-weight:300; color:var(--ink2); line-height:1.75;">Kami berani memberi jaminan karena yakin dengan kualitas layanan yang kami berikan.</p>
      </div>
      <div class="testi-card" style="text-align:center; padding:48px 32px">
        <h3 style="font-size:1.1rem; margin-bottom:12px; color:var(--primary2)">Respon Cepat</h3>
        <p style="font-size:.86rem; font-weight:300; color:var(--ink2); line-height:1.75;">Tidak perlu menunggu lama, tim nuestore siap menanggapi setiap pertanyaan dan kebutuhan Anda.</p>
      </div>
    </div>
  </div>
</section>

<!-- WHY US -->
<section class="sec why-sec">
  <div class="sec-max">
    <div class="rv">
      <div class="tag">Keunggulan</div>
      <h2 class="sec-h">Kenapa Harus<br>nuestore?</h2>
      <p class="sec-sub">Mengapa ribuan pengguna memilih nuestore sebagai solusi terbaik dan termudah untuk berkembang di sosial media.</p>
    </div>
    <div class="why-grid rv2">
      <div class="why-card">
        <div class="why-ico"><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></div>
        <h3>Kualitas Layanan</h3>
        <p>Kami menyediakan layanan Instagram dan TikTok terbaik dan berkualitas untuk menaikkan performa akun Anda secara konsisten.</p>
      </div>
      <div class="why-card">
        <div class="why-ico"><svg viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg></div>
        <h3>Harga Terjangkau</h3>
        <p>Kami menjamin harga terbaik di kelasnya, hasil maksimal dengan biaya yang efisien.</p>
      </div>
      <div class="why-card">
        <div class="why-ico"><svg viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
        <h3>Metode Pembayaran</h3>
        <p>Kami mendukung berbagai metode pembayaran populer di Indonesia, termasuk QRIS dan semua e-wallet utama.</p>
      </div>
      <div class="why-card">
        <div class="why-ico"><svg viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg></div>
        <h3>Proses Cepat</h3>
        <p>Semua pesanan diproses secara instan dan otomatis, tanpa antri, tanpa konfirmasi manual.</p>
      </div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="sec">
  <div class="sec-max" style="max-width:640px">
    <div class="rv">
      <div class="tag">Cara Kerja</div>
      <h2 class="sec-h">Cuma 4 Langkah!<br>Akun Auto Naik</h2>
      <p class="sec-sub">Siap populer? Ikuti panduan tiga menit ini dan lihat hasilnya hari ini.</p>
    </div>
    <div class="steps-wrap rv2">
      <div class="step-row">
        <div class="step-left"><div class="step-num">1</div><div class="step-line"></div></div>
        <div class="step-body">
          <h4>Buka Telegram @Nuestore</h4>
          <p>Ketuk /start dan bot akan menyapa Anda dengan menu layanan yang lengkap.</p>
        </div>
      </div>
      <div class="step-row">
        <div class="step-left"><div class="step-num">2</div><div class="step-line"></div></div>
        <div class="step-body">
          <h4>Pilih Platform & Layanan</h4>
          <p>Pilih platform (Instagram/TikTok), pilih kategori, dan berikan link target Anda.</p>
        </div>
      </div>
      <div class="step-row">
        <div class="step-left"><div class="step-num">3</div><div class="step-line"></div></div>
        <div class="step-body">
          <h4>Bayar via QRIS Otomatis</h4>
          <p>Bot akan mengirimkan tagihan QRIS unik. Bayar via e-wallet dan kirim bukti bayar langsung ke chat.</p>
        </div>
      </div>
      <div class="step-row">
        <div class="step-left"><div class="step-num">4</div></div>
        <div class="step-body">
          <h4>Pesanan Diproses</h4>
          <p>Sistem memproses pesanan secara instan setelah admin mengonfirmasi bukti bayar Anda.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- TESTIMONIALS -->
<section class="sec" style="background:var(--s1)">
  <div class="sec-max">
    <div class="rv">
      <div class="tag">Testimoni</div>
      <h2 class="sec-h">Kata Mereka<br>Tentang nuestore</h2>
      <p class="sec-sub">Lihat apa yang dikatakan pelanggan kami tentang pengalaman menggunakan layanan nuestore.</p>
    </div>
    <div class="testi-grid rv2">
      <div class="testi-card">
        <div class="stars">★★★★★</div>
        <p class="testi-q">Proses via WhatsApp cepet banget, admin-nya responsif dan ramah. Hasilnya juga nyata, puas banget langganan di sini.</p>
        <div class="testi-auth">
          <div class="testi-av">AR</div>
          <div><div class="testi-name">Aldi R.</div><div class="testi-role">Content Creator</div></div>
        </div>
      </div>
      <div class="testi-card">
        <div class="stars">★★★★★</div>
        <p class="testi-q">Suka banget karena bisa tanya-tanya dulu via chat sebelum order. Pembayaran QRIS-nya juga praktis, ga ribet.</p>
        <div class="testi-auth">
          <div class="testi-av">MS</div>
          <div><div class="testi-name">Mutiara S.</div><div class="testi-role">Online Seller</div></div>
        </div>
      </div>
      <div class="testi-card">
        <div class="stars">★★★★★</div>
        <p class="testi-q">Sudah coba beberapa tempat, nuestore yang paling trusted. Hasil permanen dan pengerjaannya profesional. Mantap!</p>
        <div class="testi-auth">
          <div class="testi-av">RB</div>
          <div><div class="testi-name">Roberto B.</div><div class="testi-role">Digital Marketer</div></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="sec">
  <div class="sec-max" style="max-width:700px">
    <div class="rv">
      <div class="tag">FAQ</div>
      <h2 class="sec-h">Pertanyaan Umum</h2>
      <p class="sec-sub">Punya pertanyaan? Kami siap membantu, hubungi kami kapan saja.</p>
    </div>
    <div class="faq-list rv2">
      <div class="faq-item">
        <div class="faq-q-row">Apakah Aman untuk Akun Saya? <span class="faq-toggle">+</span></div>
        <div class="faq-ans">Sangat aman. Kami hanya memerlukan username atau link profil Anda. nuestore tidak pernah dan tidak akan pernah meminta password akun sosial media Anda.</div>
      </div>
      <div class="faq-item">
        <div class="faq-q-row">Bagaimana Cara Menggunakan Layanan nuestore? <span class="faq-toggle">+</span></div>
        <div class="faq-ans">Hubungi admin kami di WhatsApp, pilih layanan yang Anda mau, masukkan link target, dan bayar via QRIS. Tim kami akan langsung memproses pesanan Anda.</div>
      </div>
      <div class="faq-item">
        <div class="faq-q-row">Bagaimana Jika Saya Mengalami Kendala? <span class="faq-toggle">+</span></div>
        <div class="faq-ans">Jika Anda memiliki kendala apapun, hubungi kami langsung via WhatsApp. Tim nuestore siap membantu Anda secepatnya.</div>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<div class="cta-wrap" id="order">
  <div class="cta-box rv">
    <h2>Pembayaran Paling Fleksibel</h2>
    <p>nuestore mendukung berbagai metode pembayaran, QRIS, GoPay, OVO, Dana, ShopeePay, dan seluruh mobile banking Indonesia.</p>
    <div class="cta-btns">
      <a href="https://t.me/Nuestore" class="btn-tg" target="_blank">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.248l-2.016 9.504c-.148.665-.54.828-1.092.514l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L7.46 14.725l-2.95-.924c-.64-.203-.654-.64.137-.948l11.521-4.443c.537-.194 1.006.131.394.838z"/></svg>
        Mulai Order di Telegram
      </a>
      <a href="https://wa.me/62882007207715" class="btn-ghost" target="_blank">
        WhatsApp Admin
      </a>
    </div>
  </div>
</div>

<!-- FOOTER -->
<footer>
  <a href="/" class="f-logo" style="text-decoration: none;">
    <img src="/images/logo-png.png" alt="nuestore" style="height:32px; width:auto; object-fit:contain; display:block;">
  </a>
  <div class="f-copy">© 2023 nuestore. All rights reserved.</div>
  <div class="f-links">
    <a href="#">Syarat & Ketentuan</a>
    <a href="#">Privasi</a>
    <a href="https://wa.me/62882007207715" target="_blank">Kontak</a>
  </div>
</footer>

<script>
(function(){
  const wrap=document.getElementById('stars');
  for(let i=0;i<120;i++){
    const s=document.createElement('div');s.className='star';
    const sz=Math.random()*2+.5;
    s.style.cssText=`width:${sz}px;height:${sz}px;left:${Math.random()*100}%;top:${Math.random()*100}%;--d:${Math.random()*4+2}s;--o:${Math.random()*.7+.2};animation-delay:${Math.random()*5}s`;
    wrap.appendChild(s);
  }
})();

const c=document.getElementById('c'),ct=document.getElementById('ct');
let mx=0,my=0,rx=0,ry=0;
document.addEventListener('mousemove',e=>{mx=e.clientX;my=e.clientY;c.style.left=mx-4+'px';c.style.top=my-4+'px'});
(function loop(){rx+=(mx-rx)*.1;ry+=(my-ry)*.1;ct.style.left=rx+'px';ct.style.top=ry+'px';requestAnimationFrame(loop)})();
document.querySelectorAll('a,button,.plat-card,.why-card,.faq-q-row,.testi-card').forEach(el=>{
  el.addEventListener('mouseenter',()=>{c.style.transform='scale(2.5)';ct.style.transform='scale(1.6)';ct.style.borderColor='var(--primary)'});
  el.addEventListener('mouseleave',()=>{c.style.transform='';ct.style.transform='';ct.style.borderColor='rgba(0,195,255,.5)'});
});

const obs=new IntersectionObserver(entries=>{entries.forEach(e=>{if(e.isIntersecting)e.target.classList.add('in')})},{threshold:.08});
document.querySelectorAll('.rv,.rv2').forEach(el=>obs.observe(el));

document.querySelectorAll('.faq-item').forEach(item=>{
  item.querySelector('.faq-q-row').addEventListener('click',()=>{
    const open=item.classList.contains('open');
    document.querySelectorAll('.faq-item').forEach(i=>i.classList.remove('open'));
    if(!open)item.classList.add('open');
  });
});

document.querySelectorAll('.btn-gold,.btn-tg,.btn-ghost').forEach(btn=>{
  btn.addEventListener('mousemove',e=>{
    const r=btn.getBoundingClientRect();
    const x=(e.clientX-r.left-r.width/2)*.18;
    const y=(e.clientY-r.top-r.height/2)*.18;
    btn.style.transform=`translate(${x}px,${y}px)`;
  });
  btn.addEventListener('mouseleave',()=>{btn.style.transform=''});
});
</script>

<!-- FLOATING CONTACTS -->
<div class="float-wrap">
  <a href="https://t.me/Nuestore" class="float-btn float-tg" target="_blank">
    <div class="float-icon">
      <svg viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.248l-2.016 9.504c-.148.665-.54.828-1.092.514l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L7.46 14.725l-2.95-.924c-.64-.203-.654-.64.137-.948l11.521-4.443c.537-.194 1.006.131.394.838z"/></svg>
    </div>
    <div class="float-text">
      <strong>Order via Bot</strong>
      <span>TELEGRAM</span>
    </div>
  </a>
  <a href="https://wa.me/62882007207715" class="float-btn float-wa" target="_blank">
    <div class="float-icon">
      <svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
    </div>
    <div class="float-text">
      <strong>Order / Tanya CS</strong>
      <span>WHATSAPP</span>
    </div>
  </a>
</div>
</body>
</html>

