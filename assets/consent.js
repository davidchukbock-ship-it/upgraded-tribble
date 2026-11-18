(function(){
  'use strict';
  var d=document,me=d.currentScript,ID=me&&me.getAttribute('data-ga'),KEY='gcn_cc',box=d.getElementById('cc');
  if(!ID||!box){return;}
  function get(){try{return localStorage.getItem(KEY);}catch(e){return null;}}
  function set(v){try{localStorage.setItem(KEY,v);}catch(e){}}
  window.dataLayer=window.dataLayer||[];
  function gtag(){window.dataLayer.push(arguments);}
  var loaded=false;
  function load(){
    if(loaded){return;}loaded=true;
    gtag('consent','default',{analytics_storage:'granted',ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied'});
    gtag('js',new Date());gtag('config',ID,{anonymize_ip:true});
    var s=d.createElement('script');s.async=true;s.src='https://www.googletagmanager.com/gtag/js?id='+encodeURIComponent(ID);d.head.appendChild(s);
    d.addEventListener('gcn:lead',function(){gtag('event','generate_lead');});
    d.addEventListener('click',function(e){var a=e.target.closest&&e.target.closest('a');if(!a){return;}
      var h=a.getAttribute('href')||'';
      if(h.indexOf('t.me')>-1){gtag('event','telegram_click',{link_url:h});}
      else if(h.indexOf('#request')>-1||a.classList.contains('stick')){gtag('event','cta_click');}
    });
  }
  function apply(){var v=get();if(v==='granted'){box.hidden=true;load();}else if(v==='denied'){box.hidden=true;}else{box.hidden=false;}}
  box.addEventListener('click',function(e){var b=e.target.closest('[data-cc]');if(!b){return;}set(b.getAttribute('data-cc'));apply();});
  var o=d.getElementById('cc-open');if(o){o.addEventListener('click',function(){try{localStorage.removeItem(KEY);}catch(e){}box.hidden=false;});}
  apply();
})();
