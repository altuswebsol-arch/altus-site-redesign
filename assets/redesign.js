/* Altus redesign interactions */
(function(){
  // cinematic intro: plays once per visit when the site is opened, fast entrance after
  var intro = document.getElementById('intro');
  if (intro) {
    var seen = false;
    try { seen = !!sessionStorage.getItem('alx_intro_seen'); } catch(e) {}
    if (seen) {
      if (intro) intro.style.display = 'none';
      document.body.classList.add('no-intro');
    } else {
      var src = document.querySelector('#dlHeader .dl-logo img');
      if (src) {
        var c = document.createElement('img');
        c.src = src.currentSrc || src.src;
        c.alt = 'Altus Web Solutions';
        var il = document.getElementById('introLogo'); if (il) il.appendChild(c);
      }
      var done = false;
      function finish(){
        if (done) return; done = true;
        intro.classList.add('bye');
        setTimeout(function(){ intro.style.display = 'none'; }, 1150);
      }
      setTimeout(finish, 1500);
      setTimeout(finish, 4500);
      intro.addEventListener('click', finish);
      try { sessionStorage.setItem('alx_intro_seen', '1'); } catch(e) {}
    }
  }

  var header = document.getElementById('dlHeader');
  var toTop = document.createElement('button');
  toTop.className = 'to-top';
  toTop.id = 'toTop';
  toTop.setAttribute('aria-label', 'Back to top');
  toTop.textContent = '↑';
  document.body.appendChild(toTop);

  function closePanels(){
    document.querySelectorAll('.mega').forEach(function(m){ m.classList.remove('show'); });
    document.querySelectorAll('.dropdown').forEach(function(d){ d.classList.remove('show'); });
  }
  function onScroll(){
    var y = window.scrollY || window.pageYOffset;
    if (header) header.classList.toggle('scrolled', y > 60);
    toTop.classList.toggle('show', y > 600);
    closePanels();
  }
  window.addEventListener('scroll', onScroll, {passive:true});
  onScroll();

  var megaByKey = {megaAi:document.getElementById('megaAi'), megaWeb:document.getElementById('megaWeb')};
  var dropByKey = {dropInd:document.getElementById('dropInd'), dropRes:document.getElementById('dropRes')};
  document.querySelectorAll('.dl-nav > li').forEach(function(li){
    var mk = li.getAttribute('data-mega'), dp = li.getAttribute('data-drop');
    li.addEventListener('mouseenter', function(){
      closePanels();
      if (mk && megaByKey[mk]) megaByKey[mk].classList.add('show');
      if (dp && dropByKey[dp]) {
        var r = li.getBoundingClientRect();
        dropByKey[dp].style.left = Math.max(24, r.left - 40) + 'px';
        dropByKey[dp].classList.add('show');
      }
    });
    li.addEventListener('mouseleave', closePanels);
  });
  document.querySelectorAll('.mega').forEach(function(m){
    m.addEventListener('mouseenter', function(){ m.classList.add('show'); });
    m.addEventListener('mouseleave', closePanels);
  });

  var burger = document.getElementById('burger'), mmenu = document.getElementById('mmenu');
  if (burger && mmenu) {
    burger.addEventListener('click', function(){ mmenu.classList.add('open'); document.body.style.overflow='hidden'; });
    var mmc = document.getElementById('mmClose');
    if (mmc) mmc.addEventListener('click', function(){ mmenu.classList.remove('open'); document.body.style.overflow=''; });
  }
  document.querySelectorAll('.mm-acc').forEach(function(btn){
    btn.addEventListener('click', function(){
      var sub = btn.nextElementSibling, open = btn.classList.toggle('open');
      if (sub) sub.style.maxHeight = open ? sub.scrollHeight + 'px' : '0px';
    });
  });
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') { if (mmenu) mmenu.classList.remove('open'); document.body.style.overflow=''; closePanels(); }
  });

  var io = new IntersectionObserver(function(es){
    es.forEach(function(e){ if (e.isIntersecting){ e.target.classList.add('in'); io.unobserve(e.target); } });
  }, {threshold:.12});
  document.querySelectorAll('.reveal').forEach(function(el){ io.observe(el); });

  toTop.addEventListener('click', function(){ window.scrollTo({top:0, behavior:'smooth'}); });

  // FAQ accordions in transformed content
  document.querySelectorAll('.fq-q').forEach(function(btn){
    btn.addEventListener('click', function(){
      var a = btn.nextElementSibling, open = btn.classList.toggle('open');
      if (a) a.style.maxHeight = open ? a.scrollHeight + 'px' : '0px';
    });
  });
})();

/* hero entrance: transition-based (robust) */
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
