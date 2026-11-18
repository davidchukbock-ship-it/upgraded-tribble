(function(){
  'use strict';
  var d=document;
  // photo slots: if the real photo is missing, show the quiet placeholder
  function empty(img){var f=img.closest('.photo');if(f){f.classList.add('is-empty');img.remove();}}
  d.addEventListener('error',function(e){if(e.target&&e.target.tagName==='IMG'){empty(e.target);}},true);
  d.querySelectorAll('.photo img').forEach(function(i){if(i.complete&&i.naturalWidth===0){empty(i);}});
  // mobile menu
  var b=d.querySelector('.menu'),n=d.getElementById('nav');
  if(b&&n){b.addEventListener('click',function(){var o=n.classList.toggle('open');b.setAttribute('aria-expanded',o?'true':'false');});}
  // request forms: send in the background, fall back to normal POST without JS
  var loaded=Date.now();
  d.querySelectorAll('form.req').forEach(function(f){
    var t=f.querySelector('[name=t]');if(t){t.value=String(loaded);}
    f.addEventListener('submit',function(e){
      if(!window.fetch||!window.FormData){return;}
      e.preventDefault();
      var s=f.querySelector('.status'),btn=f.querySelector('button[type=submit]');
      s.className='status';s.textContent=f.dataset.sending||'…';btn.disabled=true;
      fetch(f.action,{method:'POST',body:new FormData(f),headers:{'Accept':'application/json'}})
        .then(function(r){return r.json().catch(function(){return {ok:false};});})
        .then(function(j){
          if(j&&j.ok){d.dispatchEvent(new CustomEvent('gcn:lead'));s.className='status ok';s.textContent=f.dataset.ok;f.reset();if(t){t.value=String(Date.now());}}
          else{s.className='status err';s.textContent=(j&&j.error)||f.dataset.err;}
        })
        .catch(function(){s.className='status err';s.textContent=f.dataset.err;})
        .then(function(){btn.disabled=false;});
    });
  });
})();
