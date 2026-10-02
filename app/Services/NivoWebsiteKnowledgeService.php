<?php
declare(strict_types=1);

final class NivoWebsiteKnowledgeService
{
    public function __construct(private PDO $pdo, private string $root) { $this->ensureSchema(); }

    public function ensureSchema(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS nivo_knowledge_websites (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(180) NOT NULL,
            base_url VARCHAR(500) NOT NULL,
            crawl_scope ENUM('page','domain') NOT NULL DEFAULT 'domain',
            exclude_paths TEXT NULL,
            max_pages SMALLINT UNSIGNED NOT NULL DEFAULT 10,
            refresh_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24,
            auto_sync TINYINT(1) NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sync_status ENUM('never','syncing','ready','error') NOT NULL DEFAULT 'never',
            pages_count INT UNSIGNED NOT NULL DEFAULT 0,
            last_error VARCHAR(1000) NULL,
            last_synced_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_nivo_web_source(tenant_id,base_url),
            INDEX idx_nivo_web_due(tenant_id,active,auto_sync,last_synced_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function list(int $tenantId): array
    {
        $q=$this->pdo->prepare('SELECT * FROM nivo_knowledge_websites WHERE tenant_id=? ORDER BY active DESC,updated_at DESC,id DESC');
        $q->execute([$tenantId]); return $q->fetchAll();
    }

    public function create(int $tenantId,array $data): int
    {
        $name=trim((string)($data['name']??''));$url=$this->normalizeUrl((string)($data['base_url']??''));
        if($name==='')throw new RuntimeException('Escribe un nombre para la fuente web.');
        $this->assertSafeUrl($url);
        $scope=in_array(($data['crawl_scope']??'domain'),['page','domain'],true)?$data['crawl_scope']:'domain';
        $max=max(1,min(30,(int)($data['max_pages']??10)));$hours=max(1,min(720,(int)($data['refresh_hours']??24)));
        $exclude=trim((string)($data['exclude_paths']??'/admin,/login,/checkout,/cart,/account'));
        $q=$this->pdo->prepare("INSERT INTO nivo_knowledge_websites(tenant_id,name,base_url,crawl_scope,exclude_paths,max_pages,refresh_hours,auto_sync,active) VALUES(?,?,?,?,?,?,?,?,1)");
        $q->execute([$tenantId,mb_substr($name,0,180),$url,$scope,$exclude,$max,$hours,!empty($data['auto_sync'])?1:0]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $tenantId,int $id,array $data): void
    {
        $name=trim((string)($data['name']??''));$url=$this->normalizeUrl((string)($data['base_url']??''));
        if($name==='')throw new RuntimeException('Escribe un nombre para la fuente web.');$this->assertSafeUrl($url);
        $scope=in_array(($data['crawl_scope']??'domain'),['page','domain'],true)?$data['crawl_scope']:'domain';
        $max=max(1,min(30,(int)($data['max_pages']??10)));$hours=max(1,min(720,(int)($data['refresh_hours']??24)));
        $exclude=trim((string)($data['exclude_paths']??''));
        $q=$this->pdo->prepare('UPDATE nivo_knowledge_websites SET name=?,base_url=?,crawl_scope=?,exclude_paths=?,max_pages=?,refresh_hours=?,auto_sync=?,active=? WHERE id=? AND tenant_id=?');
        $q->execute([mb_substr($name,0,180),$url,$scope,$exclude,$max,$hours,!empty($data['auto_sync'])?1:0,!empty($data['active'])?1:0,$id,$tenantId]);
        if(!$q->rowCount()){$v=$this->pdo->prepare('SELECT id FROM nivo_knowledge_websites WHERE id=? AND tenant_id=?');$v->execute([$id,$tenantId]);if(!$v->fetchColumn())throw new RuntimeException('La fuente web no existe.');}
    }

    public function delete(int $tenantId,int $id): void
    {
        $this->pdo->beginTransaction();
        try{
            $pattern='website:'.$id.'|%';
            $this->pdo->prepare("DELETE FROM knowledge_sources WHERE tenant_id=? AND source_type='url' AND source_ref LIKE ?")->execute([$tenantId,$pattern]);
            $this->pdo->prepare('DELETE FROM nivo_knowledge_websites WHERE id=? AND tenant_id=?')->execute([$id,$tenantId]);
            $this->pdo->commit();
        }catch(Throwable $e){$this->pdo->rollBack();throw $e;}
    }

    public function sync(int $tenantId,int $id): array
    {
        $q=$this->pdo->prepare('SELECT * FROM nivo_knowledge_websites WHERE id=? AND tenant_id=? LIMIT 1');$q->execute([$id,$tenantId]);$site=$q->fetch();
        if(!$site)throw new RuntimeException('La fuente web no existe.');if(!(int)$site['active'])throw new RuntimeException('Activa la fuente antes de sincronizarla.');
        $this->assertSafeUrl((string)$site['base_url']);
        $this->pdo->prepare("UPDATE nivo_knowledge_websites SET sync_status='syncing',last_error=NULL WHERE id=? AND tenant_id=?")->execute([$id,$tenantId]);
        try{
            $pages=$this->crawl($site);
            if(!$pages)throw new RuntimeException('No se encontró contenido público legible en esa URL.');
            $this->pdo->beginTransaction();
            $pattern='website:'.$id.'|%';
            $this->pdo->prepare("DELETE FROM knowledge_sources WHERE tenant_id=? AND source_type='url' AND source_ref LIKE ?")->execute([$tenantId,$pattern]);
            $ins=$this->pdo->prepare("INSERT INTO knowledge_sources(tenant_id,solution_id,module_id,name,source_type,source_ref,content,status,approval_status) VALUES(?,NULL,NULL,?,'url',?,?,'ready','approved')");
            foreach($pages as $p){$ref='website:'.$id.'|'.$p['url'];$ins->execute([$tenantId,mb_substr($p['title'],0,180),mb_substr($ref,0,500),$p['content']]);}
            $this->pdo->prepare("UPDATE nivo_knowledge_websites SET sync_status='ready',pages_count=?,last_error=NULL,last_synced_at=NOW() WHERE id=? AND tenant_id=?")->execute([count($pages),$id,$tenantId]);
            $this->pdo->commit();
            return ['pages'=>count($pages),'site'=>$site['name']];
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();$this->pdo->prepare("UPDATE nivo_knowledge_websites SET sync_status='error',last_error=? WHERE id=? AND tenant_id=?")->execute([mb_substr($e->getMessage(),0,1000),$id,$tenantId]);throw $e;}
    }

    public function syncDue(?int $tenantId=null,int $limit=5): array
    {
        $sql="SELECT id,tenant_id FROM nivo_knowledge_websites WHERE active=1 AND auto_sync=1 AND (last_synced_at IS NULL OR DATE_ADD(last_synced_at,INTERVAL refresh_hours HOUR)<=NOW())";
        $params=[];if($tenantId!==null){$sql.=' AND tenant_id=?';$params[]=$tenantId;}$sql.=' ORDER BY COALESCE(last_synced_at,\'1970-01-01\') ASC LIMIT '.max(1,min(20,$limit));
        $q=$this->pdo->prepare($sql);$q->execute($params);$done=[];
        foreach($q->fetchAll() as $r){try{$done[]=['id'=>(int)$r['id'],'ok'=>true,'result'=>$this->sync((int)$r['tenant_id'],(int)$r['id'])];}catch(Throwable $e){$done[]=['id'=>(int)$r['id'],'ok'=>false,'error'=>$e->getMessage()];}}
        return $done;
    }

    private function crawl(array $site): array
    {
        $queue=[(string)$site['base_url']];$seen=[];$pages=[];$max=max(1,min(30,(int)$site['max_pages']));$baseHost=$this->hostKey((string)parse_url((string)$site['base_url'],PHP_URL_HOST));$scope=(string)$site['crawl_scope'];$excludes=$this->excludeList((string)($site['exclude_paths']??''));
        while($queue&&count($pages)<$max){$url=array_shift($queue);$key=$this->canonical($url);if(isset($seen[$key]))continue;$seen[$key]=true;if($this->isExcluded($url,$excludes))continue;
            $this->assertSafeUrl($url);$html=$this->fetch($url);if($html==='')continue;$parsed=$this->extract($html,$url);if($parsed['content']!=='')$pages[]=['url'=>$url,'title'=>$parsed['title'],'content'=>$parsed['content']];
            if($scope==='domain')foreach($parsed['links'] as $link){if(count($seen)+count($queue)>=$max*4)break;$host=$this->hostKey((string)parse_url($link,PHP_URL_HOST));if($host===$baseHost&&!isset($seen[$this->canonical($link)])&&!$this->isExcluded($link,$excludes))$queue[]=$link;}
            if($scope==='page')break;
        }
        return $pages;
    }

    private function fetch(string $url): string
    {
        if(!function_exists('curl_init'))throw new RuntimeException('El servidor necesita la extensión cURL para sincronizar sitios web.');
        $current=$url;
        for($hop=0;$hop<4;$hop++){
            $this->assertSafeUrl($current);$headers=[];$ch=curl_init($current);
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>6,CURLOPT_TIMEOUT=>12,CURLOPT_USERAGENT=>'ZYNKO-NIVO-Knowledge/1.0',CURLOPT_HTTPHEADER=>['Accept: text/html,application/xhtml+xml'],CURLOPT_HEADERFUNCTION=>function($ch,$line)use(&$headers){$len=strlen($line);$p=strpos($line,':');if($p!==false)$headers[strtolower(trim(substr($line,0,$p)))]=trim(substr($line,$p+1));return $len;}]);
            $body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$type=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);curl_close($ch);
            if($code>=300&&$code<400&&!empty($headers['location'])){$next=$this->absoluteUrl($current,$headers['location']);if($next===null)return '';$current=$next;continue;}
            if($body===false||$code<200||$code>=400)return '';if($type!==''&&!str_contains(strtolower($type),'text/html'))return '';return (string)$body;
        }
        return '';
    }

    private function extract(string $html,string $url): array
    {
        if(!class_exists('DOMDocument'))return ['title'=>$url,'content'=>trim(strip_tags($html)),'links'=>[]];
        $dom=new DOMDocument('1.0','UTF-8');$prev=libxml_use_internal_errors(true);@$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html,LIBXML_NOERROR|LIBXML_NOWARNING);libxml_clear_errors();libxml_use_internal_errors($prev);$xp=new DOMXPath($dom);
        foreach(['//script','//style','//noscript','//svg','//canvas','//nav','//footer','//aside','//form'] as $expr)foreach(iterator_to_array($xp->query($expr)?:[]) as $n)$n->parentNode?->removeChild($n);
        $title=trim((string)($xp->query('//title')->item(0)?->textContent??''));if($title==='')$title=trim((string)($xp->query('//h1')->item(0)?->textContent??''));if($title==='')$title=(string)parse_url($url,PHP_URL_HOST);
        $chunks=[];foreach(['//meta[@name="description"]/@content','//h1','//h2','//h3','//p','//li'] as $expr){foreach(iterator_to_array($xp->query($expr)?:[]) as $n){$t=preg_replace('/\s+/u',' ',trim((string)$n->textContent));if($t!==''&&mb_strlen($t)>=20)$chunks[]=$t;}}
        $chunks=array_values(array_unique($chunks));$content=trim(implode("\n\n",$chunks));if(mb_strlen($content)>50000)$content=mb_substr($content,0,50000);
        $links=[];foreach(iterator_to_array($xp->query('//a[@href]')?:[]) as $a){$href=trim((string)$a->getAttribute('href'));$abs=$this->absoluteUrl($url,$href);if($abs!==null)$links[]=$abs;}$links=array_values(array_unique($links));
        return ['title'=>$title,'content'=>$content,'links'=>$links];
    }

    private function absoluteUrl(string $base,string $href): ?string
    {
        if($href===''||str_starts_with($href,'#')||preg_match('#^(mailto:|tel:|javascript:)#i',$href))return null;
        if(preg_match('#^https?://#i',$href))return $this->canonical($href);
        $p=parse_url($base);if(!$p||empty($p['scheme'])||empty($p['host']))return null;$origin=$p['scheme'].'://'.$p['host'].(isset($p['port'])?':'.$p['port']:'');
        if(str_starts_with($href,'//'))return $this->canonical($p['scheme'].':'.$href);if(str_starts_with($href,'/'))return $this->canonical($origin.$href);
        $dir=preg_replace('#/[^/]*$#','/',(string)($p['path']??'/'));return $this->canonical($origin.$dir.$href);
    }

    private function canonical(string $url): string
    {
        $p=parse_url($url);if(!$p||empty($p['scheme'])||empty($p['host']))return $url;$path=$p['path']??'/';$segments=[];foreach(explode('/',$path) as $seg){if($seg===''||$seg==='.')continue;if($seg==='..'){array_pop($segments);continue;}$segments[]=$seg;}$path='/'.implode('/',$segments);if(str_ends_with($path,'/')&&$path!=='/')$path=rtrim($path,'/');return strtolower($p['scheme']).'://'.strtolower($p['host']).(isset($p['port'])?':'.$p['port']:'').$path.(!empty($p['query'])?'?'.$p['query']:'');
    }

    private function hostKey(string $host): string { return preg_replace('/^www\./','',strtolower(trim($host))) ?: strtolower(trim($host)); }

    private function normalizeUrl(string $url): string
    {
        $url=trim($url);if($url!==''&&!preg_match('#^https?://#i',$url))$url='https://'.$url;$url=$this->canonical($url);return rtrim($url,'/');
    }

    private function excludeList(string $raw): array { return array_values(array_filter(array_map(fn($x)=>trim($x),preg_split('/[,\r\n]+/',$raw)?:[]))); }
    private function isExcluded(string $url,array $excludes): bool { $path=(string)(parse_url($url,PHP_URL_PATH)??'/');foreach($excludes as $x)if($x!==''&&str_starts_with($path,$x))return true;return false; }

    private function assertSafeUrl(string $url): void
    {
        $p=parse_url($url);if(!$p||!in_array(strtolower((string)($p['scheme']??'')),['http','https'],true)||empty($p['host']))throw new RuntimeException('Usa una URL pública válida con http:// o https://.');
        $host=strtolower((string)$p['host']);if($this->localAllowed($host))return;if($host==='localhost'||str_ends_with($host,'.local')||str_ends_with($host,'.internal'))throw new RuntimeException('Por seguridad, esa dirección local no puede usarse como fuente en este ambiente.');
        $ips=@gethostbynamel($host)?:[];if(!$ips)throw new RuntimeException('No fue posible resolver el dominio de la fuente.');foreach($ips as $ip){if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))throw new RuntimeException('La fuente resuelve a una red privada o reservada y fue bloqueada por seguridad.');}
    }

    private function localAllowed(string $host): bool
    {
        $env=@parse_ini_file($this->root.'/.env',false,INI_SCANNER_RAW)?:[];$mode=strtolower((string)($env['APP_ENV']??''));$current=strtolower(preg_replace('/:\d+$/','',(string)($_SERVER['HTTP_HOST']??'')));
        return in_array($mode,['local','development','dev'],true)||(str_ends_with($host,'.test')&&str_ends_with($current,'.test'));
    }
}
