<?php
/** CLI integration tests; database mutations are rolled back. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
define('IN_KAMI',true);
define('ROOT_PATH',dirname(__DIR__,3).'/');
$_SERVER['HTTP_HOST']=$argv[1] ?? 'ai-dev01.kamicore.org';
$_SERVER['REQUEST_URI']='/uk/blog';
$_SERVER['REQUEST_METHOD']='GET';
$_SERVER['HTTPS']='on';
require ROOT_PATH.'core/init.php';
define('LANG',in_array('uk',DOMAIN_CONFIG['languages'],true) ? 'uk' : DOMAIN_CONFIG['default_language']);
define('USERGROUP_ID',(int)GLOBAL_SETTINGS['usergroup_root']);
define('PAGE_SLUG','admin-seo');
$page=DB::getRow('SELECT * FROM pages WHERE domain_id=$1 ORDER BY page_id LIMIT 1',[DOMAIN_ID]);
define('PAGE_ID',(int)$page['page_id']);
use Plugins\SimpleSEO\Schema;
use Core\Request;

$checks=0;
function check(bool $value,string $message): void {
    global $checks;
    if (!$value) throw new RuntimeException($message);
    $checks++;
}
function input(array $data,string $method='POST'): void {
    (new ReflectionProperty(Request::class,'data'))->setValue(null,['merged'=>$data,'cookie'=>[]]);
    (new ReflectionProperty(Request::class,'method'))->setValue(null,$method);
    (new ReflectionProperty(Request::class,'pathParams'))->setValue(null,array_intersect_key($data,array_flip(['seo-section','seo-id','seo-copy'])));
    (new ReflectionProperty(Request::class,'consumedPathParams'))->setValue(null,[]);
}
function invoke(object $object,string $method,mixed ...$args): mixed {
    return (new ReflectionMethod($object,$method))->invoke($object,...$args);
}
set_error_handler(static function(int $severity,string $message,string $file,int $line): never {
    throw new ErrorException($message,0,$severity,$file,$line);
});
$sessionId=null;
DB::beginTransaction();
try {
    Request::init();
    Core\Session::init();
    $sessionId=Core\Session::id();
    $registry=new Core\PluginRegistry();
    $seo=$registry->get('SimpleSEO');
    check($seo instanceof Plugins\SimpleSEO\SimpleSEO,'Plugin unavailable');
    $text="Quotes \" and slash \\ and newline\n </script> {{other}}";
    $schema=Schema::render('{"@type":"Article","headline":"{{text}}","image":"{{images}}","count":"{{zero}}","active":"{{false}}","optional":"{{missing}}","author":{"@type":"Person","name":"{{missing}}"},"description":"Prefix {{text}}"}',
        ['text'=>$text,'images'=>['a','b'],'zero'=>0,'false'=>false,'other'=>'INJECTED']);
    check($schema['headline']===$text,'Exact scalar placeholder changed');
    check($schema['description']==='Prefix '.$text,'Embedded scalar placeholder changed');
    check($schema['image']===['a','b'] && $schema['count']===0 && $schema['active']===false,'Native JSON types lost');
    check(!isset($schema['optional'],$schema['author']),'Empty optional properties retained');
    $quoted = Schema::render('{"@type":"Article","headline":"Say \\"{{text}}\\""}',['text'=>'A "quote"']);
    check($quoted['headline']==='Say "A "quote""','Quoted embedded placeholder failed');
    $json=Schema::scriptJson($schema);
    check(!str_contains($json,'</script>') && !str_contains($json,'{{other}}'),'Unsafe script or second-pass placeholder');
    check(json_decode($json,true,512,JSON_THROW_ON_ERROR)===$schema,'Safe JSON encoding changed data');
    $prettyJson=Schema::scriptJson($schema,true);
    check(str_contains($prettyJson,PHP_EOL . '    "@type"'),'Pretty JSON-LD formatting missing');
    check(json_decode($prettyJson,true,512,JSON_THROW_ON_ERROR)===$schema,'Pretty JSON-LD changed data');
    check(Schema::text('{{title}} / {{other}}',['title'=>'{{other}}','other'=>'ok'])==='{{other}} / ok','Text was recursively substituted');
    foreach (['{"@type":', '{"@graph":"invalid"}', '{"@graph":[42]}', '{"@type":42}'] as $invalidTemplate) {
        $rejected = false;
        try { Schema::validate($invalidTemplate); } catch(\Throwable) { $rejected = true; }
        check($rejected,'Invalid schema accepted');
    }
    $layout=['_seo_preview'=>true,'faqItems'=>[['question'=>'Question?','answer'=>'<p>Answer "yes".</p>']],
        'listItems'=>[['name'=>'One','url'=>'/blog'],['name'=>'Unsafe','url'=>'javascript:alert(1)']]];
    $override=['metadata'=>[LANG=>['title'=>'Test "title" {{site_name}}','description'=>'Description','image'=>'/media/test.png']],
        'options'=>['schemas'=>['webpage'],'robots'=>'noindex, follow']];
    $result=invoke($seo,'build',$page,[],LANG,$layout,[['title'=>'Home','link'=>'/'],['title'=>'Here']],$override);
    check(count($result['graph'])===5,'Expected website, page, breadcrumbs, FAQ and list');
    check($result['graph'][0]['@type']==='WebSite','Automatic WebSite missing');
    check($result['graph'][1]['isPartOf']['@id']===$result['graph'][0]['@id'],'WebPage is not linked to WebSite');
    $legacy=invoke($seo,'build',$page,[],LANG,[],[],[
        'metadata'=>[LANG=>['title'=>'Legacy home']],
        'options'=>['schemas'=>['home']]
    ]);
    check(count(array_filter($legacy['graph'], static fn(array $node): bool => ($node['@type'] ?? '') === 'WebSite'))===1,
        'Legacy Home assignment duplicated WebSite');
    check(($legacy['graph'][1]['@type'] ?? '')==='WebPage','Legacy Home assignment did not map to WebPage');
    check(str_contains($result['html'],'noindex, follow'),'Robots missing');
    check(str_contains($result['html'],'https://'.DOMAIN_CONFIG['name_orig'].'/media/test.png'),'Asset URL includes language prefix');
    check(!str_contains($result['html'],'javascript:'),'Unsafe URL emitted');
    check(count($result['graph'][4]['itemListElement'])===1,'Invalid list URL retained');
    check($result['graph'][2]['itemListElement'][1]['position']===2,'Breadcrumb positions incorrect');
    $token=invoke($seo,'csrf');
    check(invoke($seo,'csrf')===$token,'CSRF token changes between requests');

    $base=['seo-section'=>'pages','seo-id'=>(string)$page['page_id'],'seo_csrf'=>$token,
        'metadata'=>[LANG=>['title'=>'Saved "title"','description'=>'Saved description']],
        'schemas'=>['webpage'],'robots'=>'noindex, follow','og_type'=>'website'];
    input($base);
    $seo->save();
    $stored=json_decode(DB::getOne('SELECT metadata FROM seo_pages WHERE page_id=$1',[$page['page_id']]),true);
    check($stored[LANG]['title']==='Saved "title"','Metadata save failed');
    input(array_replace($base,['seo_csrf'=>'wrong']));
    try { $seo->save(); check(false,'Invalid CSRF accepted'); } catch(RuntimeException $e) { check(str_contains($e->getMessage(),'token'),'Unexpected CSRF error'); }
    input($base,'GET');
    $rejected = false;
    try { $seo->save(); } catch(RuntimeException) { $rejected = true; }
    check($rejected,'GET mutation accepted');
    input(array_replace($base,['preview_language'=>LANG]));
    $preview=$seo->preview();
    check(str_contains($preview,'seo-preview') && str_contains($preview,'Saved &quot;title&quot;'),'Page preview failed');

    $key='seo_test_'.bin2hex(random_bytes(4));
    $schemaInput=['seo-section'=>'schemas','seo-id'=>'new','seo_csrf'=>$token,'schema_key'=>$key,
        'schema_title'=>'Test schema','template'=>'{"@type":"WebPage","name":"{{title}}"}','enabled'=>'1'];
    input($schemaInput);
    $registry=new Core\PluginRegistry();$seo=$registry->get('SimpleSEO');$seo->save();
    check((int)DB::getOne('SELECT count(*) FROM seo_schemas WHERE domain_id=$1 AND schema_key=$2',[DOMAIN_ID,$key])===1,'Schema creation failed');
    input(array_replace($schemaInput,['template'=>'{"bad":']));
    $registry=new Core\PluginRegistry();$seo=$registry->get('SimpleSEO');
    $invalid=$seo->save();
    check(str_contains($invalid,'&#123;&quot;bad&quot;:'),'Invalid schema draft was lost');
    $registry=new Core\PluginRegistry();$seo=$registry->get('SimpleSEO');
    input(['seo-section'=>'schemas','seo-id'=>$key,'seo_csrf'=>$token]);
    $seo->deleteSchema();
    check(!DB::getOne('SELECT 1 FROM seo_schemas WHERE domain_id=$1 AND schema_key=$2',[DOMAIN_ID,$key]),'Schema delete failed');
    $registry=new Core\PluginRegistry();$seo=$registry->get('SimpleSEO');
    input(['seo-section'=>'types'],'GET');
    check(str_contains($seo->manage(),'seo-manager'),'Type manager failed to render');
    $itemRow = DB::getRow("SELECT item_id,ct_id FROM content_items WHERE item_slug IS NOT NULL AND item_slug<>'' ORDER BY item_id LIMIT 1");
    if ($itemRow) {
        $typeInput = ['seo-section'=>'types','seo-id'=>(string)$itemRow['ct_id'],'seo_csrf'=>$token,
            'metadata'=>[LANG=>['title'=>'Item: {{title}}','description'=>'{{summary}}']],
            'schemas'=>['article'],'robots'=>'index, follow','og_type'=>'article',
            'preview_language'=>LANG,'preview_page'=>$page['page_id'],'preview_item'=>$itemRow['item_id']];
        input($typeInput);
        $seo=(new Core\PluginRegistry())->get('SimpleSEO');
        $preview=$seo->preview();
        check(str_contains($preview,'seo-preview') && str_contains($preview,'Item: '),'Content type preview failed');
        $seo->save();
        $stored=json_decode(DB::getOne('SELECT metadata FROM seo_types WHERE domain_id=$1 AND ct_id=$2',[DOMAIN_ID,$itemRow['ct_id']]),true);
        check($stored[LANG]['title']==='Item: {{title}}','Content type save failed');
        $seo=(new Core\PluginRegistry())->get('SimpleSEO');
        $item=Core\Content::getItem((int)$itemRow['item_id'],LANG);
        $result=invoke($seo,'build',$page,$item,LANG,['_seo_preview'=>true]);
        check(str_starts_with($result['title'],'Item: '),'Routed item used page metadata');
        check($result['graph'][0]['@type']==='WebSite','Routed item is missing automatic WebSite');
        check($result['graph'][1]['@type']==='Article','Routed item used page schema');
        input(['seo-section'=>'schemas','seo-id'=>'article','seo_csrf'=>$token,'schema_key'=>'article',
            'schema_title'=>'Article','template'=>'{"@type":"Article","headline":"{{title}}"}','enabled'=>'1',
            'preview_language'=>LANG,'preview_page'=>$page['page_id'],'preview_item'=>$itemRow['item_id']]);
        $seo=(new Core\PluginRegistry())->get('SimpleSEO');
        check(str_contains($seo->preview(),'Item: '),'Schema preview ignored type metadata');
    }
    $seo=(new Core\PluginRegistry())->get('SimpleSEO');
    input(['seo-section'=>'general'],'GET');
    check(str_contains($seo->manage(),'title_format'),'Domain settings failed to render');
    $_SERVER['REQUEST_URI']='/uk/blog/va-page/2';
    $url=invoke($seo,'canonical',$page,[],LANG,[]);
    check(str_ends_with($url,'/blog/va-page/2'),'Pagination URL was collapsed');
    Core\User::$user=['usergroup_id'=>(int)GLOBAL_SETTINGS['usergroup_guest']];
    try { $seo->manage(); check(false,'Guest manager access accepted'); } catch(RuntimeException $e) { check(str_contains($e->getMessage(),'denied'),'Unexpected ACL error'); }
    echo "PASS: {$checks} integration checks; all database changes rolled back.\n";
} finally {
    DB::rollBack();
    if($sessionId) Cache::del(invoke(new Core\Session(),'cacheKey',DOMAIN_ID,$sessionId));
    restore_error_handler();
}
