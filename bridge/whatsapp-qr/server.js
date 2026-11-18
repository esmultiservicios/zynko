import express from 'express';
import QRCode from 'qrcode';
import pino from 'pino';
import { makeWASocket, useMultiFileAuthState, DisconnectReason, downloadMediaMessage } from '@whiskeysockets/baileys';
import fs from 'fs/promises';
import path from 'path';
import { createHash, timingSafeEqual, randomUUID } from 'crypto';

const app=express();
const port=Number(process.env.PORT||8091);
const token=process.env.ZYNKO_QR_BRIDGE_TOKEN||'';
const webhook=process.env.ZYNKO_QR_WEBHOOK||'';
const sessions=new Map();
const starting=new Map();
const root=path.resolve(process.env.ZYNKO_QR_SESSION_DIR||'./sessions');
const logger=pino({level:'silent'});
if(!token)throw new Error('ZYNKO_QR_BRIDGE_TOKEN is required');
if(!webhook||!/^https?:\/\//i.test(webhook))throw new Error('ZYNKO_QR_WEBHOOK is required');
await fs.mkdir(root,{recursive:true,mode:0o700});
const spool=path.join(root,'.events');await fs.mkdir(spool,{recursive:true,mode:0o700});
const validId=id=>typeof id==='string'&&/^[A-Za-z0-9_-]{1,120}$/.test(id);
const sessionDir=id=>{if(!validId(id))throw new Error('Invalid session');return path.join(root,id);};
const validMedia=id=>/^[a-f0-9]{64}$/.test(id);
const equal=(a,b)=>{const x=Buffer.from(a),y=Buffer.from(b);return x.length===y.length&&timingSafeEqual(x,y);};
app.disable('x-powered-by');
app.use('/api',(req,res,next)=>{if(!equal(String(req.headers.authorization||''),`Bearer ${token}`))return res.status(401).json({ok:false,message:'No autorizado'});res.set('Cache-Control','no-store');next();});
app.use(express.json({limit:'16mb'}));
app.param('id',(req,res,next,id)=>validId(id)?next():res.status(400).json({ok:false,message:'Sesión inválida'}));
let flushing=false;
async function flushEvents(){
 if(flushing)return;flushing=true;
 try{
  const names=(await fs.readdir(spool)).filter(x=>/^[0-9-]+[a-f0-9-]+\.json$/.test(x)).sort().slice(0,40);
  for(const name of names){
   const file=path.join(spool,name);let payload;try{payload=JSON.parse(await fs.readFile(file,'utf8'));}catch{continue;}
   if(payload.next_at>Date.now())continue;
   try{
    const r=await fetch(webhook,{method:'POST',redirect:'error',signal:AbortSignal.timeout(25000),headers:{'content-type':'application/json','x-zynko-qr-token':token},body:JSON.stringify(payload.event)});
    if(r.ok){await fs.unlink(file);continue;}
   }catch{}
   payload.attempts=(payload.attempts||0)+1;payload.next_at=Date.now()+Math.min(900000,1000*2**Math.min(payload.attempts,10));
   const tmp=file+'.tmp';await fs.writeFile(tmp,JSON.stringify(payload),{mode:0o600});await fs.rename(tmp,file);
  }
 }finally{flushing=false;}
}
async function postWebhook(event){const file=path.join(spool,`${Date.now()}-${randomUUID()}.json`);await fs.writeFile(file+'.tmp',JSON.stringify({event,attempts:0,next_at:0}),{mode:0o600});await fs.rename(file+'.tmp',file);void flushEvents().catch(()=>{});}
setInterval(()=>void flushEvents().catch(()=>{}),5000).unref();
function senderId(jid){return typeof jid==='string'&&/^[0-9]+(?::[0-9]+)?@s\.whatsapp\.net$/.test(jid)?jid:'';}
function phoneOf(s){return String(s?.sock?.user?.id||'').split(/[:@]/)[0];}
function unwrap(m){let v=m;for(let i=0;i<4;i++){const inner=v?.ephemeralMessage?.message||v?.viewOnceMessage?.message||v?.viewOnceMessageV2?.message||v?.documentWithCaptionMessage?.message;if(!inner)break;v=inner;}return v||{};}
async function storeAttachment(entry,m,v){
 const match=[['image','imageMessage'],['video','videoMessage'],['audio','audioMessage'],['document','documentMessage'],['image','stickerMessage']].find(([,k])=>v[k]);if(!match)return null;
 const [type,key]=match;const data=v[key];const length=Number(data.fileLength||0);if(length>15*1024*1024)return null;
 try{const bytes=await downloadMediaMessage(m,'buffer',{}, {logger,reuploadRequest:entry.sock.updateMediaMessage});if(!Buffer.isBuffer(bytes)||bytes.length>15*1024*1024)return null;
  const id=createHash('sha256').update(String(m.key.id)+'|'+String(m.key.remoteJid)).digest('hex');const dir=path.join(sessionDir(entry.id),'media');await fs.mkdir(dir,{recursive:true,mode:0o700});await fs.writeFile(path.join(dir,id),bytes,{mode:0o600});
  return {id,type,name:path.basename(String(data.fileName||`${type}-${id.slice(0,12)}`)),mime:String(data.mimetype||'application/octet-stream')};
 }catch{return null;}
}
async function connect(entry){
 if(entry.deleted)return entry;const dir=sessionDir(entry.id);await fs.mkdir(dir,{recursive:true,mode:0o700});
 const {state,saveCreds}=await useMultiFileAuthState(dir);if(entry.deleted)return entry;
 const sock=makeWASocket({auth:state,printQRInTerminal:false,logger,syncFullHistory:false,markOnlineOnConnect:false});entry.sock=sock;entry.qr=null;
 sock.ev.on('creds.update',()=>{if(!entry.deleted&&entry.sock===sock)void saveCreds().catch(()=>{});});
 sock.ev.on('connection.update',async u=>{if(entry.deleted||entry.sock!==sock)return;try{
  if(u.qr){entry.qr=await QRCode.toDataURL(u.qr);entry.connected=false;entry.loggedOut=false;}
  if(u.connection==='open'){entry.connected=true;entry.qr=null;entry.loggedOut=false;entry.attempts=0;await postWebhook({event:'status',session_id:entry.id,connected:true,phone:phoneOf(entry)});}
  if(u.connection==='close'){entry.connected=false;entry.qr=null;const code=u.lastDisconnect?.error?.output?.statusCode;entry.loggedOut=code===DisconnectReason.loggedOut;await postWebhook({event:'status',session_id:entry.id,connected:false});if(!entry.loggedOut&&!entry.deleted){entry.attempts++;clearTimeout(entry.timer);entry.timer=setTimeout(()=>{if(!entry.deleted&&entry.sock===sock)void connect(entry).catch(()=>{sessions.delete(entry.id);});},Math.min(60000,2500*2**Math.min(5,entry.attempts-1)));}}
 }catch{}});
 sock.ev.on('messages.upsert',async ev=>{if(entry.deleted||entry.sock!==sock||ev.type!=='notify')return;for(const m of ev.messages||[]){try{
  const from=senderId(m.key?.remoteJid);if(!from||!m.key?.id||!m.message)continue;
  const v=unwrap(m.message);const attachment=await storeAttachment(entry,m,v);const text=String(v.conversation||v.extendedTextMessage?.text||v.imageMessage?.caption||v.videoMessage?.caption||v.documentMessage?.caption||v.buttonsResponseMessage?.selectedDisplayText||v.listResponseMessage?.title||'');
  if(!text&&!attachment)continue;await postWebhook({event:'message',session_id:entry.id,from,text,attachment,message_id:m.key.id,push_name:m.pushName||'',from_me:!!m.key.fromMe,timestamp:Number(m.messageTimestamp||0)});
 }catch{}}});
 return entry;
}
async function start(id){sessionDir(id);if(starting.has(id))return starting.get(id);if(sessions.has(id))return sessions.get(id);
 const entry={id,connected:false,qr:null,sock:null,attempts:0,loggedOut:false,deleted:false,timer:null};sessions.set(id,entry);const promise=connect(entry).catch(e=>{sessions.delete(id);throw e;}).finally(()=>starting.delete(id));starting.set(id,promise);return promise;
}
function status(s){return {ok:true,connected:s.connected,qr:s.qr,phone:phoneOf(s),message:s.connected?'Conectado':s.loggedOut?'Sesión cerrada; desvincula para generar un nuevo QR.':'Esperando vinculación'};}
app.post('/api/sessions/:id/start',async(req,res)=>res.json(status(await start(req.params.id))));
app.post('/api/sessions/:id/pair',async(req,res)=>{
 const phone=String(req.body.phone||'');if(!/^[1-9][0-9]{6,14}$/.test(phone))return res.status(422).json({ok:false,message:'Número internacional inválido'});
 const session=await start(req.params.id);if(session.connected||session.sock?.authState?.creds?.registered)return res.status(409).json({ok:false,message:'El dispositivo ya está vinculado'});
 if(typeof session.sock?.requestPairingCode!=='function')return res.status(409).json({ok:false,message:'El bridge no soporta vinculación mediante código'});
 if(session.pairingAt&&Date.now()-session.pairingAt<60000)return res.status(429).json({ok:false,message:'Espere antes de solicitar otro código'});
 session.pairingAt=Date.now();try{const code=await session.sock.requestPairingCode(phone);if(typeof code!=='string'||!code)return res.status(502).json({ok:false,message:'Código no confirmado'});res.json({ok:true,code});}catch{return res.status(409).json({ok:false,message:'WhatsApp no pudo generar el código. Vuelva a intentar o utilice QR.'});}
});
app.get('/api/sessions/:id/status',async(req,res)=>res.json(status(await start(req.params.id))));
app.get('/api/sessions/:id/media/:asset',async(req,res)=>{if(!validMedia(req.params.asset))return res.status(404).end();const file=path.join(sessionDir(req.params.id),'media',req.params.asset);try{const st=await fs.stat(file);if(!st.isFile()||st.size>15*1024*1024)return res.status(404).end();res.type('application/octet-stream').sendFile(file);}catch{return res.status(404).end();}});
app.post('/api/sessions/:id/send',async(req,res)=>{
 const s=await start(req.params.id);if(!s.connected)return res.status(409).json({ok:false,message:'Sesión no conectada'});const to=String(req.body.to||'').replace(/\D/g,'');if(!/^[0-9]{5,20}$/.test(to))return res.status(422).json({ok:false,message:'Destino inválido'});
 let msg={text:String(req.body.text||'')};const a=req.body.attachment;if(a){if(!['image','video','audio','document'].includes(a.type)||typeof a.data!=='string'||!/^[A-Za-z0-9+/]*={0,2}$/.test(a.data))return res.status(422).json({ok:false,message:'Adjunto inválido'});const bytes=Buffer.from(a.data,'base64');if(!bytes.length||bytes.length>10*1024*1024)return res.status(422).json({ok:false,message:'Adjunto inválido'});msg={[a.type]:bytes,mimetype:String(a.mime||'application/octet-stream')};if(a.type==='document')msg.fileName=path.basename(String(a.name||'archivo'));if(['image','video','document'].includes(a.type)&&req.body.text)msg.caption=String(req.body.text);}else if(!msg.text.trim())return res.status(422).json({ok:false,message:'Mensaje vacío'});
 try{const out=await s.sock.sendMessage(`${to}@s.whatsapp.net`,msg);if(!out?.key?.id)return res.status(502).json({ok:false,message:'Envío no confirmado'});res.json({ok:true,message_id:out.key.id});}catch{return res.status(502).json({ok:false,message:'Resultado de envío no confirmado'});}
});
app.delete('/api/sessions/:id',async(req,res)=>{const id=req.params.id;await starting.get(id)?.catch(()=>{});const s=sessions.get(id);if(s){s.deleted=true;clearTimeout(s.timer);if(s.sock)await s.sock.logout().catch(()=>{});s.sock?.end?.(undefined);sessions.delete(id);}await fs.rm(sessionDir(id),{recursive:true,force:true});res.json({ok:true});});
app.use((err,req,res,next)=>{if(res.headersSent)return next(err);res.status(500).json({ok:false,message:'No se pudo procesar la solicitud del bridge'});});
app.listen(port,'127.0.0.1',()=>console.log(`ZYNKO WhatsApp QR bridge on 127.0.0.1:${port}`));
// Restore only existing linked sessions, not arbitrary directories or deleted sessions.
for(const e of await fs.readdir(root,{withFileTypes:true})){if(e.isDirectory()&&validId(e.name)){try{await fs.access(path.join(sessionDir(e.name),'creds.json'));void start(e.name).catch(()=>{});}catch{}}}
