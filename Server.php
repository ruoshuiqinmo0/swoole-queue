<?php
require_once './RedisPool.php';
class Server
{
    public $serv;
    public function __construct()
    {

        $this->serv = new Swoole\Http\Server('0.0.0.0', 9574);   // 允许所有IP访问
        $this->serv->set([
            'worker_num' => 4,       // 一般设置为服务器CPU数的1-4倍
            'daemonize' => true,      // 以守护进程执行
            'log_file' => '/data/swoole_log/queue.log' ,    // swoole日志

            // 数据包分发策略（dispatch_mode=1/3时，底层会屏蔽onConnect/onClose事件，
            // 原因是这2种模式下无法保证onConnect/onClose/onReceive的顺序，非请求响应式的服务器程序，请不要使用模式1或3）
            //  'dispatch_mode' => 2,        // 固定模式，根据连接的文件描述符分配worker。这样可以保证同一个连接发来的数据只会被同一个worker处理
        ]);
        $this->serv->on('Request', [$this, 'onRequest']);
        //$this->serv->on('Task', [$this, 'onTask']);
       // $this->serv->on('Finish', [$this, 'onFinish']);
        $this->serv->start();
    }

    public function onRequest($request, $response)
    {
        $get = $request->get;
        $return = null;
        if(empty($get['r'])){
            $return= ['code'=>404,'msg'=>'无传送地址'];
        }
        switch ($get['r']){
            case 'producer':
                $return = $this->producer($get['url']);
                break;
            case 'consumption':
                $key = $request->get['key'];
                $return = $this->consumption($key);
            default:
                $return = ['code'=>403,'msg'=>'not supprt the function'];
                break;
        }
        $response->end(json_encode($return));
    }

    /**
     * 生产者
     * $key 队列任务的key
     * $params   string|array  多个任务为数组 ,单个任务为字符串
     * $params = [
     *    '/index.php?id=1',
     *    '/index.php?id=2',
     *    '/index.php?id=3',
     *    '/index.php?id=4',
     * ]
     * 或者
     * $params = '/index.php?id=1',
     */
    protected function producer($url)
    {
        go(function() use($url){
            $url = urldecode($url);
            $resquestBody = $this->httpRequest($url);
            $resquestArr = json_decode($resquestBody,true);
            $number =0;

            if(empty($resquestArr)){
                $this->error= '请传队列的值';
                return false;
            }else if(is_array($resquestArr['AttrList'])){
                $redis = RedisPool::getIntance()->pop();
                foreach($resquestArr['AttrList'] as $k=>$v){
                    echo $url = $redis->lpush($resquestArr['key'],$resquestArr['FirstUrl'].$v);
                    $number++;
                }
                RedisPool::getIntance()->push($redis);
            }
            return $number;
        });
    }

    /**
     * 消费者
     * redis list key='getOrder:1' getOrder 队列任务名称  1表示1秒消费一个任务,  0.5 表示0.5秒消费一个任务
     * redis list value=
     */
    protected function consumption($key)
    {
        list($taskName,$intavl) = explode('.', $key);
        $redis = RedisPool::getIntance()->pop();
        \Swoole\Timer::tick($intavl * 1000, function () use ($redis,$key) {
            go(function () use ($redis, $key) {
                $taskTimes = $redis->llen($key);
                if ($taskTimes < 1) {
                    co::sleep(10);
                }else{
                    $paramas = $redis->rpop($key);
                    $res = $this->httpRequest($paramas);
                    echo  $res;
                }
            });
        });
        RedisPool::getIntance()->push($redis);
    }

    /**
     * request 请求
     * @author: jiang
     * @param $url
     * @return mixed
     */
    protected function httpRequest($url)
    {
        $url = parse_url($url);
        var_dump($url);
        echo "\r\n";
        $cli = new Swoole\Coroutine\Http\Client($url['host'], $url['port']);
        $cli->set(['timeout' => 0,'keep_alive' => false]);
        $cli->setHeaders([
            'Host' => $url['host'],
            "User-Agent" => 'Chrome/49.0.2587.3',
        ]);


        $cli->get($url['path'].'?'.$url['query']);
        $body = $cli->body;
        $cli->statusCode;
        $cli->close();
        return $body;
    }
}
new Server();