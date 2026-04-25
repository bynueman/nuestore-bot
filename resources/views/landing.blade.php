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

[data-theme="light"] {
  --bg: #f8f9ff;
  --s1: #ffffff;
  --s2: #f0f2ff;
  --ink: #0a0b10;
  --ink2: #4a5568;
  --ink3: #718096;
  --primary: #0088cc;
  --primary2: #0099dd;
  --primary3: #00aaee;
  --secondary: #9333ea;
  --accent: #a855f7;
  --border: rgba(0,136,204,0.2);
  --border2: rgba(0,136,204,0.35);
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
.starfield{position:fixed;inset:0;z-index:0;overflow:hidden;pointer-events:none}
.star{position:absolute;border-radius:50%;background:#fff;animation:twinkle var(--d,3s) ease-in-out infinite;opacity:0}
[data-theme="light"] .star{background:#0088cc}
@keyframes twinkle{0%,100%{opacity:0;transform:scale(.5)}50%{opacity:var(--o,.3);transform:scale(1)}}

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
  position:fixed;top:20px;left:20px;right:20px;z-index:100;
  padding:18px 48px;
  display:flex;align-items:center;justify-content:space-between;
  background:rgba(3,5,10,.85);
  backdrop-filter:blur(24px);
  border:1px solid var(--border);
  border-radius:24px;
  box-shadow:0 8px 32px rgba(0,0,0,.3);
}
[data-theme="light"] nav{
  background:rgba(255,255,255,.9);
  box-shadow:0 8px 32px rgba(0,0,0,.08);
}
.logo{font-weight:700;font-size:1.25rem;letter-spacing:.5px;color:var(--primary2)}
.logo em{font-style:normal;color:var(--ink);opacity:.5}

.nav-center{
  display:flex;align-items:center;gap:32px;
}
.nav-link{
  font-size:.88rem;font-weight:500;color:var(--ink2);
  text-decoration:none;transition:color .3s;
  position:relative;
}
.nav-link:hover{color:var(--primary2)}
.nav-link::after{
  content:'';position:absolute;bottom:-4px;left:0;right:0;
  height:2px;background:var(--primary);
  transform:scaleX(0);transition:transform .3s;
}
.nav-link:hover::after{transform:scaleX(1)}

.nav-right{display:flex;align-items:center;gap:16px}
.theme-toggle{
  width:40px;height:40px;border-radius:50%;
  border:1px solid var(--border2);
  background:rgba(0,195,255,.04);
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;transition:all .3s;
}
[data-theme="light"] .theme-toggle{background:rgba(0,136,204,.06)}
.theme-toggle:hover{background:rgba(0,195,255,.1);border-color:var(--primary)}
.theme-toggle svg{width:18px;height:18px;stroke:var(--primary2);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}

.nav-pill{
  display:flex;align-items:center;gap:10px;
  padding:10px 22px;
  border:1px solid var(--border2);border-radius:100px;
  font-size:.82rem;font-weight:500;color:var(--primary2);
  text-decoration:none;
  background:rgba(0,195,255,.04);
  transition:all .3s;cursor:none;
}
[data-theme="light"] .nav-pill{
  background:rgba(0,136,204,.06);
  color:var(--primary);
}
.nav-pill:hover{background:rgba(0,195,255,.1);border-color:var(--primary);color:var(--primary3)}

.hamburger{
  display:none;width:40px;height:40px;
  border:1px solid var(--border2);border-radius:12px;
  background:rgba(0,195,255,.04);
  flex-direction:column;align-items:center;justify-content:center;
  gap:5px;cursor:pointer;transition:all .3s;
  position:relative;z-index:101;
}
[data-theme="light"] .hamburger{background:rgba(0,136,204,.06)}
.hamburger span{
  width:20px;height:2px;background:var(--primary2);
  transition:all .3s;border-radius:2px;
  display:block;
}
.hamburger.active span:nth-child(1){transform:rotate(45deg) translate(6px,6px)}
.hamburger.active span:nth-child(2){opacity:0}
.hamburger.active span:nth-child(3){transform:rotate(-45deg) translate(6px,-6px)}

.mobile-menu{
  position:fixed;top:100px;left:20px;right:20px;
  background:rgba(3,5,10,.95);
  backdrop-filter:blur(24px);
  border:1px solid var(--border);border-radius:20px;
  padding:24px;display:none;flex-direction:column;gap:16px;
  box-shadow:0 8px 32px rgba(0,0,0,.3);
  z-index:99;
}
[data-theme="light"] .mobile-menu{
  background:rgba(255,255,255,.95);
  box-shadow:0 8px 32px rgba(0,0,0,.08);
}
.mobile-menu.active{display:flex}
.mobile-menu a{
  font-size:.95rem;font-weight:500;color:var(--ink2);
  text-decoration:none;padding:12px 16px;
  border-radius:12px;transition:all .3s;
}
.mobile-menu a:hover{background:rgba(0,195,255,.08);color:var(--primary2)}

/* ── HERO ── */
.hero{
  position:relative;z-index:2;
  min-height:100vh;
  display:flex;align-items:center;justify-content:center;
  padding:140px 64px 80px;text-align:center;
  overflow:hidden;
}
.hero-in{max-width:820px;position:relative;z-index:3}

/* ── FLOATING SOCIAL ICONS ── */
.social-float{
  position:absolute;
  width:80px;height:80px;
  border-radius:20px;
  display:flex;align-items:center;justify-content:center;
  opacity:.08;
  animation:float var(--dur,8s) ease-in-out infinite;
  pointer-events:none;
  backdrop-filter:blur(2px);
}
[data-theme="light"] .social-float{opacity:.12}
.social-float svg{width:50%;height:50%}
.social-ig{
  background:linear-gradient(135deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888);
  top:20%;left:8%;--dur:7s;
}
.social-tt{
  background:#000;border:2px solid rgba(255,255,255,.2);
  top:60%;right:10%;--dur:9s;animation-delay:-2s;
}
.social-ig-2{
  background:linear-gradient(135deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888);
  bottom:15%;right:15%;--dur:8s;animation-delay:-4s;
}
.social-tt-2{
  background:#000;border:2px solid rgba(255,255,255,.2);
  top:35%;left:12%;--dur:10s;animation-delay:-6s;
}
@keyframes float{
  0%,100%{transform:translate(0,0) rotate(0deg) scale(1)}
  25%{transform:translate(20px,-20px) rotate(5deg) scale(1.05)}
  50%{transform:translate(-15px,15px) rotate(-5deg) scale(.95)}
  75%{transform:translate(15px,10px) rotate(3deg) scale(1.02)}
}

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
[data-theme="light"] .btn-gold{
  background:linear-gradient(135deg,#0088cc,#0066aa);
  color:#ffffff;
  box-shadow:0 4px 24px rgba(0,136,204,.25);
}
.btn-gold::before{
  content:'';position:absolute;inset:0;
  background:linear-gradient(135deg,rgba(255,255,255,.2),transparent);
  opacity:0;transition:opacity .3s;
}
.btn-gold:hover{transform:translateY(-2px);box-shadow:0 0 48px rgba(0,195,255,.4)}
[data-theme="light"] .btn-gold:hover{box-shadow:0 8px 32px rgba(0,136,204,.35)}
.btn-gold:hover::before{opacity:1}

.btn-ghost{
  display:inline-flex;align-items:center;gap:10px;
  padding:15px 34px;background:transparent;
  border:1px solid rgba(255,255,255,.1);border-radius:8px;
  color:var(--ink2);
  font-family:'Poppins',sans-serif;font-weight:500;font-size:.9rem;
  text-decoration:none;cursor:none;transition:all .3s;
}
[data-theme="light"] .btn-ghost{
  border-color:rgba(0,136,204,.25);
  color:var(--primary);
}
.btn-ghost:hover{border-color:var(--primary);color:var(--primary);background:rgba(0,195,255,.04)}
[data-theme="light"] .btn-ghost:hover{background:rgba(0,136,204,.08)}

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
.testi-stars{color:var(--primary);font-size:.78rem;letter-spacing:2px;margin-bottom:16px}
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
[data-theme="light"] .btn-tg{
  background:linear-gradient(135deg,#0088cc,#0066aa);
  box-shadow:0 4px 24px rgba(0,136,204,.25);
}
.btn-tg:hover{transform:translateY(-2px);box-shadow:0 0 48px rgba(30,136,199,.4)}
[data-theme="light"] .btn-tg:hover{box-shadow:0 8px 32px rgba(0,136,204,.35)}
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
  nav{padding:16px 20px;top:12px;left:12px;right:12px;border-radius:20px}
  .nav-center,.nav-pill{display:none}
  .hamburger{display:flex}
  .hero,.sec,.cta-wrap,.stats{padding-left:24px;padding-right:24px}
  .hero{padding-top:120px}
  h1.headline{font-size:2.3rem}
  .plat-grid,.why-grid,.testi-grid{grid-template-columns:1fr}
  .stats-row{grid-template-columns:1fr}
  .stat{border-right:none;border-bottom:1px solid var(--border)}
  .stat:last-child{border-bottom:none}
  footer{padding:28px 24px;flex-direction:column;text-align:center}
  .cta-box{padding:48px 28px}
  .social-float{width:60px;height:60px}
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
<div class="starfield" id="starfield"></div>
<div class="amb amb-a"></div>
<div class="amb amb-b"></div>
<div class="amb amb-c"></div>

<!-- NAV -->
<nav>
  <a href="/" class="logo" style="text-decoration: none;">
    <img src="/images/logo-png.png" alt="nuestore" style="height:36px; width:auto; object-fit:contain; display:block;">
  </a>
  
  <div class="nav-center">
    <a href="#platform" class="nav-link">Fitur</a>
    <a href="#keunggulan" class="nav-link">Keunggulan</a>
    <a href="#cara-kerja" class="nav-link">Cara Kerja</a>
    <a href="#testimoni" class="nav-link">Testimoni</a>
    <a href="#faq" class="nav-link">FAQ</a>
  </div>
  
  <div class="nav-right">
    <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
      <svg class="sun-icon" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="5"></circle>
        <line x1="12" y1="1" x2="12" y2="3"></line>
        <line x1="12" y1="21" x2="12" y2="23"></line>
        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
        <line x1="1" y1="12" x2="3" y2="12"></line>
        <line x1="21" y1="12" x2="23" y2="12"></line>
        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
      </svg>
      <svg class="moon-icon" viewBox="0 0 24 24" style="display:none">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
      </svg>
    </button>
    <a href="https://t.me/nuestorebot" target="_blank" class="nav-pill">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.248l-2.016 9.504c-.148.665-.54.828-1.092.514l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L7.46 14.725l-2.95-.924c-.64-.203-.654-.64.137-.948l11.521-4.443c.537-.194 1.006.131.394.838z"/></svg>
      Order via Bot
    </a>
    <div class="hamburger" id="hamburger">
      <span></span>
      <span></span>
      <span></span>
    </div>
  </div>
</nav>

<!-- MOBILE MENU -->
<div class="mobile-menu" id="mobileMenu">
  <a href="#platform">Fitur</a>
  <a href="#keunggulan">Keunggulan</a>
  <a href="#cara-kerja">Cara Kerja</a>
  <a href="#testimoni">Testimoni</a>
  <a href="#faq">FAQ</a>
  <a href="https://t.me/nuestorebot" target="_blank" style="background:rgba(0,195,255,.1);color:var(--primary2);text-align:center">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="display:inline-block;vertical-align:middle;margin-right:8px"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.248l-2.016 9.504c-.148.665-.54.828-1.092.514l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L7.46 14.725l-2.95-.924c-.64-.203-.654-.64.137-.948l11.521-4.443c.537-.194 1.006.131.394.838z"/></svg>
    Order via Bot
  </a>
</div>

<!-- HERO -->
<section class="hero">
  <!-- Floating Social Media Icons -->
  <div class="social-float social-ig">
    <svg viewBox="0 0 24 24" fill="white">
      <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
    </svg>
  </div>
  <div class="social-float social-tt">
    <svg viewBox="0 0 24 24" fill="white">
      <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/>
    </svg>
  </div>
  <div class="social-float social-ig-2">
    <svg viewBox="0 0 24 24" fill="white">
      <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
    </svg>
  </div>
  <div class="social-float social-tt-2">
    <svg viewBox="0 0 24 24" fill="white">
      <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/>
    </svg>
  </div>
  
  <div class="hero-in">
    <div class="eyebrow"><div class="dot-live"></div>Online 24/7 Nonstop</div>
    <h1 class="headline">
      Partner terbaik untuk growth sosial media kamu — <span class="shimmer">nuestore</span>
    </h1>
    <p>Mau akun sosmed kamu makin hits? Followers naik, engagement meledak, dan konten kamu dilirik banyak orang. Semua bisa terwujud tanpa ribet, tinggal klik aja!</p>
    <div class="hero-btns">
      <a href="https://t.me/nuestorebot" class="btn-gold" target="_blank">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.248l-2.016 9.504c-.148.665-.54.828-1.092.514l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L7.46 14.725l-2.95-.924c-.64-.203-.654-.64.137-.948l11.521-4.443c.537-.194 1.006.131.394.838z"/></svg>
        Order via Bot Telegram
      </a>
      <a href="https://wa.me/62882007207715" class="btn-ghost" target="_blank">
        Chat Admin Dulu
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
    <div class="stat"><span class="stat-n">10K+</span><span class="stat-l">Order Sukses</span></div>
    <div class="stat"><span class="stat-n">99.8%</span><span class="stat-l">Rate Berhasil</span></div>
    <div class="stat"><span class="stat-n">&lt; 5 Mnt</span><span class="stat-l">Proses Cepet</span></div>
  </div>
</div>

<!-- PLATFORM -->
<section class="sec" id="platform">
  <div class="sec-max">
    <div class="rv">
      <div class="tag">Yang Kamu Dapet</div>
      <h2 class="sec-h">Cara Paling Gampang<br>Buat Viral di Sosmed</h2>
      <p class="sec-sub">Kontrol penuh ada di tangan kamu. Tinggal pilih mau apa, sisanya biar kami yang urus!</p>
    </div>
    <div class="testi-grid rv2">
      <div class="testi-card" style="text-align:center; padding:48px 32px">
        <h3 style="font-size:1.1rem; margin-bottom:12px; color:var(--primary2)">Insight Naik Drastis</h3>
        <p style="font-size:.86rem; font-weight:300; color:var(--ink2); line-height:1.75;">Bukan cuma angka doang, tapi engagement beneran yang bikin konten kamu makin dilirik orang!</p>
      </div>
      <div class="testi-card" style="text-align:center; padding:48px 32px">
        <h3 style="font-size:1.1rem; margin-bottom:12px; color:var(--primary2)">Garansi 100%</h3>
        <p style="font-size:.86rem; font-weight:300; color:var(--ink2); line-height:1.75;">Kami jamin uang kamu gak bakal sia-sia. Kalau ada masalah, langsung kami tangani sampai tuntas!</p>
      </div>
      <div class="testi-card" style="text-align:center; padding:48px 32px">
        <h3 style="font-size:1.1rem; margin-bottom:12px; color:var(--primary2)">Fast Response</h3>
        <p style="font-size:.86rem; font-weight:300; color:var(--ink2); line-height:1.75;">Ada pertanyaan? Langsung aja chat! Tim kami standby buat bantu kamu kapan aja.</p>
      </div>
    </div>
  </div>
</section>

<!-- WHY US -->
<section class="sec why-sec" id="keunggulan">
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
        <h3>Proses Kilat</h3>
        <p>Dengan sistem Bot Telegram 24/7, pesanan Anda masuk ke sistem kami secara instan tepat setelah pembayaran dikonfirmasi.</p>
      </div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="sec" id="cara-kerja">
  <div class="sec-max" style="max-width:640px">
    <div class="rv">
      <div class="tag">Cara Pakenya</div>
      <h2 class="sec-h">Cuma 4 Langkah!<br>Akun Langsung Naik</h2>
      <p class="sec-sub">Gampang banget kok! Ikutin aja step by step ini, dijamin langsung paham.</p>
    </div>
    <div class="steps-wrap rv2">
      <div class="step-row">
        <div class="step-left"><div class="step-num">1</div><div class="step-line"></div></div>
        <div class="step-body">
          <h4>Buka Bot Telegram Kita</h4>
          <p>Cari <strong>@nuestorebot</strong> di Telegram, terus ketuk <strong>/start</strong>. Langsung deh muncul menu lengkapnya!</p>
        </div>
      </div>
      <div class="step-row">
        <div class="step-left"><div class="step-num">2</div><div class="step-line"></div></div>
        <div class="step-body">
          <h4>Pilih Layanan & Kirim Link</h4>
          <p>Mau boost Instagram atau TikTok? Pilih aja yang kamu mau, terus paste link profil atau postingan kamu.</p>
        </div>
      </div>
      <div class="step-row">
        <div class="step-left"><div class="step-num">3</div><div class="step-line"></div></div>
        <div class="step-body">
          <h4>Scan QRIS & Upload Bukti</h4>
          <p>Bot bakal kirim QRIS dengan nominal unik. Tinggal bayar pake e-wallet favorit kamu, screenshot, upload. Done!</p>
        </div>
      </div>
      <div class="step-row">
        <div class="step-left"><div class="step-num">4</div></div>
        <div class="step-body">
          <h4>Pesanan Langsung Jalan</h4>
          <p>Setelah bukti kamu upload, tim kita langsung verifikasi dalam hitungan menit. Pesanan auto jalan deh!</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- TESTIMONIALS -->
<section class="sec" style="background:var(--s1)" id="testimoni">
  <div class="sec-max">
    <div class="rv">
      <div class="tag">Kata Mereka</div>
      <h2 class="sec-h">Real Review<br>dari Pengguna nuestore</h2>
      <p class="sec-sub">Jangan cuma percaya kata kita aja. Ini kata mereka yang udah ngerasain sendiri!</p>
    </div>
    <div class="testi-grid rv2">
      <div class="testi-card">
        <div class="testi-stars">★★★★★</div>
        <p class="testi-q">Gila sih ini bot cepet banget! Baru pertama kali nyoba langsung ketagihan. Followers masuk dalam hitungan menit, bayar pake QRIS juga gampang banget!</p>
        <div class="testi-auth">
          <div class="testi-av">AR</div>
          <div><div class="testi-name">Aldi R.</div><div class="testi-role">Content Creator</div></div>
        </div>
      </div>
      <div class="testi-card">
        <div class="testi-stars">★★★★★</div>
        <p class="testi-q">Jualan online jadi makin laris sejak pake nuestore. Insight IG naik drastis, engagement juga meningkat. Bot-nya user friendly banget, gak ribet!</p>
        <div class="testi-auth">
          <div class="testi-av">MS</div>
          <div><div class="testi-name">Mutiara S.</div><div class="testi-role">Online Seller</div></div>
        </div>
      </div>
      <div class="testi-card">
        <div class="testi-stars">★★★★★</div>
        <p class="testi-q">Trusted banget pokoknya! Tinggal klik-klik di Bot langsung jalan. Adminnya juga fast response kalau ada yang mau ditanyain. Recommended!</p>
        <div class="testi-auth">
          <div class="testi-av">RB</div>
          <div><div class="testi-name">Roberto B.</div><div class="testi-role">Digital Marketer</div></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="sec" id="faq">
  <div class="sec-max" style="max-width:700px">
    <div class="rv">
      <div class="tag">Ada Pertanyaan?</div>
      <h2 class="sec-h">Yang Sering Ditanyain</h2>
      <p class="sec-sub">Masih bingung? Cek dulu FAQ-nya, siapa tau jawabannya udah ada di sini!</p>
    </div>
    <div class="faq-list rv2">
      <div class="faq-item">
        <div class="faq-q-row">Aman gak sih buat akun gue? <span class="faq-toggle">+</span></div>
        <div class="faq-ans">100% aman! Kita cuma butuh username atau link profil kamu aja. Password? Big NO! Kita gak bakal pernah minta password akun sosmed kamu.</div>
      </div>
      <div class="faq-item">
        <div class="faq-q-row">Gimana cara pake layanan nuestore? <span class="faq-toggle">+</span></div>
        <div class="faq-ans">Gampang banget! Buka Telegram, cari <strong>@nuestorebot</strong>, ketuk <strong>/start</strong>, pilih layanan yang kamu mau. Abis transfer, kirim bukti bayar ke bot. Selesai deh!</div>
      </div>
      <div class="faq-item">
        <div class="faq-q-row">Kalau ada masalah gimana? <span class="faq-toggle">+</span></div>
        <div class="faq-ans">Tenang aja! Klik tombol <strong>Bantuan</strong> di Bot Telegram atau langsung chat admin via WhatsApp. Kita siap bantu kamu kapan aja!</div>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<div class="cta-wrap" id="order">
  <div class="cta-box rv">
    <h2>Bayar Pake Apa Aja Bisa!</h2>
    <p>QRIS, GoPay, OVO, Dana, ShopeePay, sampai mobile banking semua support. Pilih yang paling gampang buat kamu!</p>
    <div class="cta-btns">
      <a href="https://t.me/nuestorebot" class="btn-tg" target="_blank">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.248l-2.016 9.504c-.148.665-.54.828-1.092.514l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L7.46 14.725l-2.95-.924c-.64-.203-.654-.64.137-.948l11.521-4.443c.537-.194 1.006.131.394.838z"/></svg>
        Yuk Order Sekarang!
      </a>
      <a href="https://wa.me/62882007207715" class="btn-ghost" target="_blank">
        Tanya-tanya Dulu
      </a>
    </div>
  </div>
</div>

<!-- FOOTER -->
<footer>
  <a href="/" class="f-logo" style="text-decoration: none;">
    <img src="/images/logo-png.png" alt="nuestore" style="height:32px; width:auto; object-fit:contain; display:block;">
  </a>
  <div class="f-copy">© 2023 nuestore. Semua hak dilindungi.</div>
  <div class="f-links">
    <a href="#">Syarat & Ketentuan</a>
    <a href="#">Privasi</a>
    <a href="https://wa.me/62882007207715" target="_blank">Kontak</a>
  </div>
</footer>

<script>
// Theme Toggle
const themeToggle=document.getElementById('themeToggle');
const sunIcon=themeToggle.querySelector('.sun-icon');
const moonIcon=themeToggle.querySelector('.moon-icon');
const html=document.documentElement;

const savedTheme=localStorage.getItem('theme')||'dark';
html.setAttribute('data-theme',savedTheme);
if(savedTheme==='light'){
  sunIcon.style.display='none';
  moonIcon.style.display='block';
}

themeToggle.addEventListener('click',()=>{
  const current=html.getAttribute('data-theme');
  const newTheme=current==='dark'?'light':'dark';
  html.setAttribute('data-theme',newTheme);
  localStorage.setItem('theme',newTheme);
  
  if(newTheme==='light'){
    sunIcon.style.display='none';
    moonIcon.style.display='block';
  }else{
    sunIcon.style.display='block';
    moonIcon.style.display='none';
  }
});

// Hamburger Menu
const hamburger=document.getElementById('hamburger');
const mobileMenu=document.getElementById('mobileMenu');

hamburger.addEventListener('click',()=>{
  hamburger.classList.toggle('active');
  mobileMenu.classList.toggle('active');
});

// Close mobile menu when clicking a link
document.querySelectorAll('.mobile-menu a').forEach(link=>{
  link.addEventListener('click',()=>{
    hamburger.classList.remove('active');
    mobileMenu.classList.remove('active');
  });
});

// Stars
(function(){
  const wrap=document.getElementById('starfield');
  for(let i=0;i<120;i++){
    const s=document.createElement('div');s.className='star';
    const sz=Math.random()*2+.5;
    const left=Math.random()*100;
    const top=Math.random()*100;
    // Skip stars in top-left corner (nav area)
    if(left<15&&top<15)continue;
    s.style.cssText=`width:${sz}px;height:${sz}px;left:${left}%;top:${top}%;--d:${Math.random()*4+2}s;--o:${Math.random()*.5+.15};animation-delay:${Math.random()*5}s`;
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
  <a href="https://t.me/nuestorebot" class="float-btn float-tg" target="_blank">
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
      <strong>Chat Langsung</strong>
      <span>WHATSAPP</span>
    </div>
  </a>
</div>
</body>
</html>

