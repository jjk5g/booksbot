/* CostaCams lightweight analytics 2026-11; no external JS dependencies. */
(function(){
"use strict";
if(window.self!==window.top)return; // Never count embedded maps twice.
if(window.__ccAnalyticsLoaded)return;window.__ccAnalyticsLoaded=true;
var key="ccAnonymousVisitorV1",id="";
try{id=localStorage.getItem(key)||"";if(!/^[a-z0-9-]{16,80}$/i.test(id)){id=(crypto.randomUUID?crypto.randomUUID():Array.from(crypto.getRandomValues(new Uint8Array(16)),n=>n.toString(16).padStart(2,"0")).join(""));localStorage.setItem(key,id);}}catch(e){id="anon-"+Math.random().toString(36).slice(2)+Math.random().toString(36).slice(2);}
var path=location.pathname.toLowerCase(),params=new URLSearchParams(location.search);
var page=path==="/"?"home":path==="/map"||path==="/map-clean.php"?"map":path==="/surf-match.php"?"surf-match":path==="/event.php"?"event:"+(params.get("slug")||"unknown").replace(/[^a-z0-9-]/gi,"").slice(0,90):(/^\/[a-z0-9-]+\/?$/.test(path)?"beach:"+path.replace(/\//g,""):"other");
function post(kind,name){
 try{
  var b=new URLSearchParams({kind:kind,key:name,visitor:id});
  if(navigator.sendBeacon){navigator.sendBeacon("/analytics-collect.php",new Blob([b.toString()],{type:"application/x-www-form-urlencoded"}));}
  else fetch("/analytics-collect.php",{method:"POST",body:b,credentials:"same-origin",keepalive:true}).catch(function(){});
 }catch(e){}
}
var fired=new Set();
function track(eventName,detail){
 var label=(eventName+"_"+detail).replace(/[^a-zA-Z0-9_-]/g,"_").slice(0,110);
 if(fired.has(label))return;
 fired.add(label);
 if(typeof window.gtag==="function"){try{window.gtag("event",eventName,{event_category:"CostaCams",content_type:detail||"interaction"});}catch(e){}}
 post("action",label);
}
function onClick(ev){
 var el=ev.target&&ev.target.closest?ev.target.closest("a,button"):null;if(!el)return;
 var href=el.tagName==="A"?(el.getAttribute("href")||""):"";
 var text=(el.textContent||"").trim().toLowerCase().slice(0,100);
 var idAttr=(el.id||"").toLowerCase();
 if(/\/surf-match\.php/.test(href)){track("surf_match_open","link");return;}
 if(/\/event\.php\?slug=/.test(href)){track("event_article_open","calendar");return;}
 if(href==="/map"){track("map_open","link");return;}
 if(el.matches("a.card[href^='/']")&&!href.includes("event.php")){track("beach_camera_open","listing");return;}
 if(page!=="surf-match")return;
 if(/sample/i.test(text)||/sample/.test(idAttr)){track("surf_sample_request","click");return;}
 if(/subscribe|save profile/.test(text)||/subscribe/.test(idAttr)){track("surf_subscribe_request","click");return;}
 if(/profile link|edit link/.test(text)||/manage/.test(idAttr)){track("surf_profile_link_request","click");return;}
}
document.addEventListener("click",onClick,{passive:true});
document.addEventListener("change",function(ev){
 if(page!=="surf-match")return;
 var el=ev.target;if(!el||!el.id)return;
 if(el.id==="ccv13DriveRange"||el.id==="ccv13DriveMode")track("surf_drive_limit_change","control");
},{passive:true});
function record(){post("page",page);}
if(document.readyState==="complete")setTimeout(record,60);
else window.addEventListener("load",function(){setTimeout(record,60);},{once:true});
})();