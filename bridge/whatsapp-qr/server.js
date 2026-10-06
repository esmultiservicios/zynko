import express from 'express';
import QRCode from 'qrcode';
import pino from 'pino';
import { makeWASocket, useMultiFileAuthState, DisconnectReason } from '@whiskeysockets/baileys';
import fs from 'fs/promises';
import path from 'path';

const app=express();app.use(express.json({limit:'8mb'}));
const port=Number(process.env.PORT||8091);const token=process.env.ZYNKO_QR_BRIDGE_TOKEN||'';const webhook=process.env.ZYNKO_QR_WEBHOOK||'';const sessions=new Map();const root=path.resolve(process.env.ZYNKO_QR_SESSION_DIR||'./sessions');await fs.mkdir(root,{recursive:true});
const auth=(req,res,next)=>{if(token&&req.headers.authorization!==`Bearer ${token}`)return res.status(401).json({ok:false,message:'No autorizado'});next();};app.use('/api',auth);
async function postWebhook(payload){if(!webhook)return;try{await fetch(webhook,{method:'POST',headers:{'content-type':'application/json','x-zynko-qr-token':token},body:JSON.stringify(payload)});}catch{}}
async function start(id){if(sessions.has(id))return sessions.get(id);const dir=path.join(root,id);const {state,saveCreds}=await useMultiFileAuthState(dir);const entry={id,connected:false,qr:null,sock:null};sessions.set(id,entry);const sock=makeWASocket({auth:state,printQRInTerminal:false,logger:pino({level:'silent'}),syncFullHistory:false});entry.sock=sock;sock.ev.on('creds.update',saveCreds);sock.ev.on('connection.update',async u=>{if(u.qr)entry.qr=await QRCode.toDataURL(u.qr);if(u.connection==='open'){entry.connected=true;entry.qr=null;await postWebhook({event:'status',session_id:id,connected:true});}if(u.connection==='close'){entry.connected=false;const code=u.lastDisconnect?.error?.output?.statusCode;if(code!==DisconnectReason.loggedOut)setTimeout(()=>{sessions.delete(id);start(id)},2500);}});sock.ev.on('messages.upsert',async ev=>{for(const m of ev.messages||[]){if(m.key.fromMe||!m.message)continue;const text=m.message.conversation||m.message.extendedTextMessage?.text||'';await postWebhook({event:'message',session_id:id,from:m.key.remoteJid,text,message_id:m.key.id,push_name:m.pushName||''});}});return entry;}
app.post('/api/sessions/:id/start',async(req,res)=>{const s=await start(req.params.id);res.json({ok:true,connected:s.connected,qr:s.qr});});
app.get('/api/sessions/:id/status',async(req,res)=>{const s=await start(req.params.id);res.json({ok:true,connected:s.connected,qr:s.qr,message:s.connected?'Conectado':'Esperando vinculación'});});
app.post('/api/sessions/:id/send',async(req,res)=>{const s=await start(req.params.id);if(!s.connected)return res.status(409).json({ok:false,message:'Sesión no conectada'});let to=String(req.body.to||'').replace(/\D/g,'');if(!to)return res.status(422).json({ok:false,message:'Destino inválido'});const out=await s.sock.sendMessage(`${to}@s.whatsapp.net`,{text:String(req.body.text||'')});res.json({ok:true,message_id:out.key.id});});
app.delete('/api/sessions/:id',async(req,res)=>{const s=sessions.get(req.params.id);if(s?.sock)await s.sock.logout().catch(()=>{});sessions.delete(req.params.id);await fs.rm(path.join(root,req.params.id),{recursive:true,force:true});res.json({ok:true});});
app.listen(port,'127.0.0.1',()=>console.log(`ZYNKO WhatsApp QR bridge on 127.0.0.1:${port}`));
