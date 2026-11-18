<?php
declare(strict_types=1);
require_once __DIR__.'/../Support/ChannelSecurity.php';

/** Normalize provider events without assigning a tenant from an untrusted URL. */
final class ChannelWebhookEvents
{
    /** Each batch has an asset id and independent events; history never triggers automation. */
    public static function meta(array $payload): array
    {
        $out=[];$object=(string)($payload['object']??'');
        foreach(($payload['entry']??[]) as $e){
            if(!is_array($e))continue;$account=(string)($e['id']??'');
            if(in_array($object,['page','instagram'],true)){
                $type=$object==='page'?'messenger':'instagram';$events=[];
                foreach(($e['messaging']??[]) as $m){if(!is_array($m))continue;
                    $msg=$m['message']??[];$echo=!empty($msg['is_echo']);$from=(string)($m[$echo?'recipient':'sender']['id']??'');
                    if(isset($msg['mid'])){$text=(string)($msg['text']??'');$media=[];foreach($msg['attachments']??[] as $a){$f=['provider_type'=>(string)($a['type']??'attachment'),'name'=>(string)($a['type']??'Adjunto')];$url=(string)($a['payload']['url']??'');if(ChannelSecurity::metaMediaUrl($url))$f['source_url']=$url;$media[]=$f;}if($text===''&&$media)$text='['.implode(', ',array_column($media,'name')).']';if($text!=='')$events[]=self::message($from,$text,(string)$msg['mid'],'',$echo,$media);}
                    foreach(($m['delivery']['mids']??[]) as $id)$events[]=['kind'=>'status','external'=>(string)$id,'status'=>'delivered','from'=>(string)($m['sender']['id']??'')];if(!empty($m['read']['mid']))$events[]=['kind'=>'status','external'=>(string)$m['read']['mid'],'status'=>'read','from'=>(string)($m['sender']['id']??'')];if(!empty($m['read']['watermark']))$events[]=['kind'=>'watermark','status'=>'read','from'=>(string)($m['sender']['id']??''),'watermark'=>(int)$m['read']['watermark']];
                }
                foreach($e['changes']??[] as $c){$v=$c['value']??[];if(in_array($c['field']??'',['comments','feed'],true)&&!empty($v['id'])){$from=(string)($v['from']['id']??$v['sender_id']??'');$text=(string)($v['message']??$v['text']??'');if($from!==''&&$text!=='')$events[]=['kind'=>'comment','from'=>$from,'text'=>$text,'external'=>(string)$v['id'],'name'=>(string)($v['from']['username']??$v['from']['name']??'')];}}
                $out[]=['type'=>$type,'asset'=>$account,'account'=>'','events'=>$events];continue;
            }
            if($object!=='whatsapp_business_account')continue;
            foreach($e['changes']??[] as $c){if(!is_array($c))continue;$v=$c['value']??[];$field=(string)($c['field']??'');$phone=(string)($v['metadata']['phone_number_id']??'');if($field==='message_template_status_update'){if($account!==''&&!empty($v['message_template_id']))$out[]=['type'=>'whatsapp_template','asset'=>'','account'=>$account,'events'=>[['kind'=>'template_status','external'=>(string)$v['message_template_id'],'name'=>(string)($v['message_template_name']??''),'language'=>(string)($v['message_template_language']??''),'status'=>(string)($v['event']??'')]]];continue;}if($phone==='')continue;$events=[];
                if($field==='messages'||$field==='smb_message_echoes'){
                    $echo=$field==='smb_message_echoes';$names=[];foreach($v['contacts']??[] as $p){$names[(string)($p['wa_id']??'')]=(string)($p['profile']['name']??'');}
                    foreach(($echo?($v['message_echoes']??$v['messages']??[]):($v['messages']??[])) as $m){if(!is_array($m))continue;$from=(string)($m[$echo?'to':'from']??'');$ev=self::whatsapp($m,$from,$names[$from]??'',$echo);if($ev)$events[]=$ev;}
                    if(!$echo)foreach($v['statuses']??[] as $st){$state=(string)($st['status']??'');if(in_array($state,['sent','delivered','read','failed'],true))$events[]=['kind'=>'status','external'=>(string)($st['id']??''),'status'=>$state,'from'=>(string)($st['recipient_id']??'')];}
                }elseif($field==='history'){
                    foreach($v['history']??[] as $h){foreach($h['threads']??[] as $thread){$contact=(string)($thread['id']??'');foreach($thread['messages']??[] as $m){$echo=isset($m['to'])&&!empty($m['to']);$ev=self::whatsapp($m,$contact,'',$echo);if($ev){$ev['historical']=true;$events[]=$ev;}}}}
                }elseif($field==='smb_app_state_sync'){
                    // Only explicitly shared contacts; unknown event shapes are deliberately ignored.
                    foreach($v['state_sync']??[] as $st){if(($st['type']??'')==='contact'&&($st['action']??'')!=='remove'){$p=$st['contact']??[];$id=(string)($p['phone_number']??$p['wa_id']??'');if(preg_match('/^\+?[0-9]{5,20}$/D',$id))$events[]=['kind'=>'contact','from'=>ltrim($id,'+'),'name'=>(string)($p['full_name']??$p['first_name']??'')];}}
                }
                $out[]=['type'=>'whatsapp','asset'=>$phone,'account'=>$account,'events'=>$events];
            }
        }
        return $out;
    }
    public static function whatsapp(array $m,string $from,string $name,bool $echo=false): ?array
    {
        $type=(string)($m['type']??'text');$text='';$media=[];
        if($type==='text')$text=(string)($m['text']['body']??'');
        elseif($type==='button')$text=(string)($m['button']['text']??$m['button']['payload']??'');
        elseif($type==='interactive'){$i=$m['interactive']??[];$text=(string)($i['button_reply']['title']??$i['list_reply']['title']??$i['button_reply']['id']??$i['list_reply']['id']??'[Respuesta interactiva]');}
        elseif(in_array($type,['image','audio','video','document','sticker'],true)){$p=$m[$type]??[];$text=(string)($p['caption']??'');$media[]=['provider_type'=>$type,'provider_id'=>(string)($p['id']??''),'name'=>(string)($p['filename']??$type),'mime'=>(string)($p['mime_type']??'')];if($text==='')$text='['.$type.']';}
        elseif($type==='location'){$l=$m['location']??[];$text='[Ubicación] '.($l['name']??'').' '.($l['latitude']??'').', '.($l['longitude']??'');}
        elseif($type==='contacts')$text='[Contacto compartido]';
        elseif($type==='reaction')$text='[Reacción] '.($m['reaction']['emoji']??'');
        elseif($type==='unsupported'||$type==='system'||$type==='unknown')return null;
        else $text='['.$type.']';
        if($from===''||trim($text)===''||empty($m['id']))return null;
        $event=self::message($from,$text,(string)$m['id'],$name,$echo,$media,(int)($m['timestamp']??0),$type);$event['reply_to']=(string)($m['context']['id']??'');return $event;
    }
    public static function message(string $from,string $text,string $external,string $name='',bool $echo=false,array $media=[],int $timestamp=0,string $type='text'): array
    {return ['kind'=>'message','from'=>$from,'text'=>$text,'external'=>$external,'name'=>$name,'echo'=>$echo,'media'=>$media,'timestamp'=>$timestamp,'message_type'=>$type];}
}
