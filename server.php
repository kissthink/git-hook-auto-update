<?php
function mylog($msg, $filename='hook_error'){
    error_log(date("Y-m-d H:i:s")."\t".$msg.PHP_EOL, 3, __DIR__.DIRECTORY_SEPARATOR.$filename.'.mylog');
}
define('PASSWORD', '');
$config = null;
$http = new swoole_http_server("0.0.0.0", 9501);
$http->set([
    'worker_num'=>4,
    'daemonize'=>1,
]);
$http->on('request', function ($request, $response) {

    $response->end('ok');
    if(false !== strpos($request->server['path_info'], 'reload')) { //重启server,(可重载配置)
        global $http;
        $http->reload();
        return;
    }
    if(empty($request->post['payload'])) {
        mylog('hook data empty');
        return;
    }
    $datas = json_decode($request->post['payload'], true);
    if(empty($datas['hook'])) {
        //错误的数据
        mylog('hook no json data');
        return;
    }
    $data = $datas['hook'];
    if(PASSWORD && $data['config']['secret'] != PASSWORD) {
        //密码不匹配
        mylog('password error');
        return ;
    }

    if(empty($data['push_data']['commits'])) {
        //没有提交者
        mylog('no commit');
        return ;
    }
    //项目名称
    $project = $data['push_data']['repository']['name'];
    //分支名称
    $ref = $data['push_data']['ref'];

    mylog("{$project}: {$ref} push");

    global $config;
    if(empty($config[$project][$ref])) {
        mylog("no {$project} {$ref}");
        return ;
    }

    $client = new \swoole_client(SWOOLE_SOCK_UDP);
    foreach($config[$project][$ref] as $item) {
        $client->connect($item['host'], $item['port']);
        $client->send(json_encode([$project, $ref, $item['path']]));
    }
});
$http->on('workerStart', function($serv, $workerId) {
    global $config;
    if(function_exists('opcache_reset')) {
        opcache_reset();
    }
    $config = include(__DIR__.DIRECTORY_SEPARATOR.'config.php');
});

$http->start();

