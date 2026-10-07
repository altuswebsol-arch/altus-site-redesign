
  // cinematic intro: logo -> background -> hero
  (function(){
    var intro = document.getElementById('intro');
    var seen = false;
    try { seen = !!sessionStorage.getItem('alx_intro_seen'); } catch(e) {}
    if (seen) { if(intro) intro.style.display = 'none'; document.body.classList.add('no-intro'); return; }
    try { sessionStorage.setItem('alx_intro_seen', '1'); } catch(e) {}
    var done = false;
    function finish(){
      if(done) return; done = true;
      intro.classList.add('bye');
      setTimeout(function(){ intro.style.display = 'none'; }, 1150);
    }
    setTimeout(finish, 1500);
    setTimeout(finish, 4500);
    if(intro) intro.addEventListener('click', finish);
  })();
  // marquee
  var words = ['SCHOOLS','HEALTHCARE','REAL ESTATE','E-COMMERCE','FINTECH','TRADES','HOSPITALITY','PROFESSIONAL SERVICES'];
  var track = document.getElementById('mtrack'), html = '';
  for(var r=0;r<2;r++){ words.forEach(function(w,i){ html += '<span>'+w+(i%3===0?' <b>\u25CF</b>':'')+'</span>'; }); }
  track.innerHTML = html + html;
  // header + panels + mobile + reveals + to-top
  var header = document.getElementById('dlHeader');
  var toTop = document.getElementById('toTop');
  function closePanels(){
    document.querySelectorAll('.mega').forEach(function(m){m.classList.remove('show')});
    document.querySelectorAll('.dropdown').forEach(function(d){d.classList.remove('show')});
  }
  window.addEventListener('scroll', function(){
    var y = window.scrollY || window.pageYOffset;
    header.classList.toggle('scrolled', y > 60);
    toTop.classList.toggle('show', y > 600);
    closePanels();
  }, {passive:true});
  var megaByKey = {megaAi:document.getElementById('megaAi'),megaWeb:document.getElementById('megaWeb')};
  var dropByKey = {dropInd:document.getElementById('dropInd'),dropRes:document.getElementById('dropRes')};
  document.querySelectorAll('.dl-nav > li').forEach(function(li){
    var mk = li.getAttribute('data-mega'), dp = li.getAttribute('data-drop');
    li.addEventListener('mouseenter', function(){
      closePanels();
      if(mk && megaByKey[mk]) megaByKey[mk].classList.add('show');
      if(dp && dropByKey[dp]){ var r = li.getBoundingClientRect(); dropByKey[dp].style.left = Math.max(24, r.left - 40) + 'px'; dropByKey[dp].classList.add('show'); }
    });
    li.addEventListener('mouseleave', closePanels);
  });
  document.querySelectorAll('.mega').forEach(function(m){
    m.addEventListener('mouseenter', function(){ m.classList.add('show'); });
    m.addEventListener('mouseleave', closePanels);
  });
  var burger = document.getElementById('burger'), mmenu = document.getElementById('mmenu');
  burger.addEventListener('click', function(){ mmenu.classList.add('open'); document.body.style.overflow='hidden'; });
  document.getElementById('mmClose').addEventListener('click', function(){ mmenu.classList.remove('open'); document.body.style.overflow=''; });
  document.querySelectorAll('.mm-acc').forEach(function(btn){
    btn.addEventListener('click', function(){
      var sub = btn.nextElementSibling, open = btn.classList.toggle('open');
      sub.style.maxHeight = open ? sub.scrollHeight + 'px' : '0px';
    });
  });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape'){ mmenu.classList.remove('open'); document.body.style.overflow=''; closePanels(); } });
  var io = new IntersectionObserver(function(es){
    es.forEach(function(e){ if(e.isIntersecting){ e.target.classList.add('in'); io.unobserve(e.target); } });
  }, {threshold:.12});
  document.querySelectorAll('.reveal').forEach(function(el){ io.observe(el); });
  toTop.addEventListener('click', function(){ window.scrollTo({top:0,behavior:'smooth'}); });

  // hero reveal via Web Animations API (robust, bypasses CSS animation issues)
  (function(){
    function reveal(){
      var fast = document.body.classList.contains('no-intro');
      var base = fast ? 250 : 1750;
      document.querySelectorAll('.hero .mask>span').forEach(function(el, i){
        try {
          el.animate([{transform:'translateY(112%)'},{transform:'translateY(0)'}],
            {duration:1050, delay: base + i*140, easing:'cubic-bezier(.22,1,.36,1)', fill:'backwards'});
        } catch(e){}
      });
      [['.hero-sub', fast?800:2400], ['.hero-ctas', fast?950:2550]].forEach(function(pair){
        var el = document.querySelector(pair[0]);
        if(!el) return;
        try {
          el.animate([{opacity:0, transform:'translateY(18px)'},{opacity:1, transform:'none'}],
            {duration:1000, delay: pair[1], easing:'ease', fill:'backwards'});
        } catch(e){}
      });
    }
    if(document.readyState === 'loading'){
      document.addEventListener('DOMContentLoaded', function(){ setTimeout(reveal, 50); });
    } else { setTimeout(reveal, 50); }
  })();


  // hero entrance: add loaded class (transition-based, robust)
  (function(){
    document.documentElement.classList.add('js');
    function load(){
      var hero = document.querySelector('.hero');
      if(!hero) return;
      var fast = document.body.classList.contains('no-intro');
      setTimeout(function(){ hero.classList.add('loaded'); }, fast ? 300 : 2100);
    }
    if(document.readyState === 'loading'){ document.addEventListener('DOMContentLoaded', load); }
    else { load(); }
  })();

