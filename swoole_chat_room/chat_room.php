<?php
# 定义 clientFds 数组 保存所有 websocket 连接
$clientFds = [];

# 创建 websocket 服务
$server = new swoole_websocket_server("0.0.0.0", 9501);
$server->set(array(
    'worker_num' =>4, //一般设置为服务器CPU数的1-4倍
    //'daemonize' =>1, //以守护进程执行
    'max_request'=>10000,
    'dispatch_mode'=>2,
    // 'task_worker_num'=>1, //task进程的数量
    // "task_ipc_mode"=>3, //使用消息队列通信，并设置为争抢模式
    "log_file"=>"log.log" ,//所有的输出都会写到日志中,
    //'task_enable_coroutine'    => true
));
# 握手成功 触发回调函数
$server->on('open', function (swoole_websocket_server $server, $request) use (&$clientFds) {

   # echo "server: handshake success with fd{$request->fd}\n";
   # 将所有客户端连接标识，握手成功后保存到数组中
   $clientFds[] = $request->fd;
});
# 收到消息 触发回调函数
$server->on('message', function (swoole_websocket_server $server, $frame) use (&$clientFds) {
    $receive_data = $frame->data;
    //var_dump($receive_data);
    $receive_data_arr = json_decode($receive_data,true);
    $user_sql = "select * from users where username='".$receive_data_arr['username']."';"; 
    echo $user_sql;
    $link = @mysqli_connect("127.0.0.1", "mtnusers", "usemtn", "swoole_chat_room");
    //var_dump($link);
    $link->query("set names 'utf8'");
    $results_sql_bro = $link->query($user_sql);
    //var_dump($results_sql_bro);
    if($results_sql_bro['num_rows']!=0){
        $data_bro = array();
        while ($fetchResult = mysqli_fetch_assoc($results_sql_bro) ){
            $data_bro['data'][] = $fetchResult;
        }
    }

    var_dump($data_bro['data'][0]);
   # echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
   # $server->push($frame->fd, "this is server");
   # 当有用户发送信息，发送广播通知所有用户
   foreach ($clientFds as $fd) {
      $server->push($fd, $frame->data);
   }
});
# 关闭连接 触发回调函数
$server->on('close', function ($ser, $fd) use (&$clientFds) {

   $res = array_search($fd, $clientFds, true);
   unset($clientFds[$res]);
});
# 启动 websocket 服务
$server->start();