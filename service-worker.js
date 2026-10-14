const CACHE='translate-v1.6.4';
const CORE=['assets/app.css','assets/app.js','assets/whisper-worker.js','assets/icon.svg','assets/icon-192.png','assets/icon-512.png','manifest.webmanifest','assets/flags/lv.svg','assets/flags/gb.svg','assets/flags/ru.svg','assets/flags/es.svg','assets/flags/fr.svg','assets/flags/de.svg','assets/flags/it.svg','assets/flags/pl.svg','assets/flags/ua.svg','assets/flags/lt.svg','assets/flags/ee.svg','assets/flags/dk.svg','assets/flags/se.svg','assets/flags/no.svg','assets/flags/fi.svg','assets/platforms/android.svg','assets/platforms/apple.svg','assets/platforms/apple-mobile.svg','assets/platforms/windows.svg','assets/platforms/linux.svg'];
self.addEventListener('install',e=>e.waitUntil(caches.open(CACHE).then(c=>c.addAll(CORE)).then(()=>self.skipWaiting())));
self.addEventListener('activate',e=>e.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim())));
self.addEventListener('fetch',e=>{
  if(e.request.method!=='GET')return;
  const u=new URL(e.request.url);
  // Authenticated HTML/API must stay network-owned. Do not wrap navigation in respondWith(fetch),
  // because a transient network failure otherwise becomes an uncaught service-worker error.
  if(e.request.mode==='navigate'||u.pathname.endsWith('/index.php')||u.pathname.endsWith('/api.php'))return;
  if(u.origin!==location.origin)return;
  e.respondWith(caches.match(e.request).then(cached=>{
    if(cached)return cached;
    return fetch(e.request).then(resp=>{
      if(resp&&resp.ok){const copy=resp.clone();caches.open(CACHE).then(c=>c.put(e.request,copy)).catch(()=>{});}
      return resp;
    }).catch(()=>new Response('',{status:503,statusText:'Offline'}));
  }));
});
