<?php
namespace Websocket;

if (!defined('WEBSOCKET_RCVBUF')) define('WEBSOCKET_RCVBUF', 1024 * 64); // 最大接受缓冲区
if (!defined('WEBSOCKET_SNDBUF')) define('WEBSOCKET_SNDBUF', 1024 * 64); // 最大发送缓冲区
if (!defined('WEBSOCKET_ONLINE')) define('WEBSOCKET_ONLINE', 2048);

class Websocket {
    const WEBSOCKET_KEY = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
    const VERSION_OLD = 1;
    const VERSION_NEW = 2;

    private $config = [
        'host' => '127.0.0.1',
        'port' => '6901',
        'path' => '/',
        'domain' => '',
    ];

    protected $socket = [];
    protected $cycle = [];
    protected $accept = [];
    protected $salt = '';
    protected $type = [];
    protected $bind = [];
    protected $time = [];

    function __construct($config = null)
    {
        if ($config !== null) {
            foreach ($config as $k => $v) {
                if (isset($this->congfig[$k])) {
                    $this->config[$k] = $v;
                }
            }
        }
        $this->run();
    }

    /**
     * [运行Socket进程]
     * @return bool
     */
    private function run()
    {
        //首先创建socket套接字
        if (!$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) { //ip4
            return false;
        } else {
            echo "Create Socket Socket Id:{$this->socket} Successfully" . PHP_EOL;
        }

        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, true); //允许使用本地地址
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVBUF, WEBSOCKET_RCVBUF ); //接收缓冲区 最大字节
        socket_set_option($this->socket, SOL_SOCKET, SO_SNDBUF, WEBSOCKET_SNDBUF);

        if (!socket_bind($this->socket, $this->config['host'], $this->config['port'])) {
            return false;
        } else {
            echo "Bind Socket Successfully." . PHP_EOL;
        }

        if (!socket_listen($this->socket, WEBSOCKET_RCVBUF)) return false;

        while (true) {
            $this->cycle = $this->accept;
            $this->cycle[] = $this->socket;

            socket_select($this->cycle, $write, $except, null);
            foreach ($this->cycle as $v) {

                if ($this->socket === $v) {
                    if (!$accept = socket_accept($v)) {
                        continue;
                    }
                    $this->addAccept($accept);
                    continue;
                }

                if (($index = $this->searchAccept($accept)) === false) {
                    continue; //搜索用户失败则跳过
                }

                if (!socket_recv($v, $data, WEBSOCKET_RCVBUF, 0) || !$data) {
                    continue; //没有数据则跳过
                }

                $type = $this->type[$index]; //获取用户状态
                if ($type === false) { //没有接受到header则进行接受
                    $type = $this->getHeader($data, $v);
                    if ($type === false) {
                        $this->close($v);
                        continue;
                    }
                    $this->type[$index] = $type;

                    continue;
                }

                if (!$data = $this->getRcvData($data, $v)) {
                    $this->close($v);
                    continue;
                }
                var_dump($data);
            }
        }
    }

    /**
     * [添加首次连接的用户]
     * @param $accept
     */
    protected function addAccept($accept)
    {
        $this->accept[] = $accept;
        var_dump($this->accept);
        $index = array_keys($this->accept);
        $index = end($index);
        $this->type[$index] = false;
        $this->bind[$index] = [];
        $this->time[$index] = time();
    }

    protected function getRcvData($data, $accept)
    {
        if (!$data || !$accept) {
            return false;
        }

        $index = $this->searchAccept($accept);
        $type = $this->type[$index];

        /*if (!isset($this->class[$type])) {
            return false;
        }*/

        return $this->decode($data);
    }

    function decode($data)
    {
        if (strlen($data) < 6) {
            return [];
        }

        $result = [];
        $back = $data;
        while ($back) {
            $type = bindec(substr(sprintf('%08b', ord($back[0])) , 4, 4));
            $encrypt = (bool)substr(sprintf('%08b', ord($back[1])), 0, 1);
            $payload = ord($back[1]) & 127;
            $datalen = strlen($back);
            if($payload == 126) {
                if ($datalen <= 8) {
                    break;
                }
                $len = substr($back, 2, 2);
                $len = unpack('n*', $len);
                $len = end($len);

                if ($datalen < 8 + $len) {
                    break;
                }
                $mask = substr($back, 4, 4);
                $data = substr($back, 8, $len);
                $back = substr($back, 8 + $len);
            } else if ($payload == 127) {
                if ($datalen <= 14) {
                    break;
                }
                $len = substr($back, 2, 8);
                $len = unpack('N*', $len);
                $len = end($len);
                if ($datalen < 14 + $len) {
                    break;
                }
                $mask = substr($back, 10, 4);
                $data = substr($back, 14, $len);
                $back = substr($back, 14 + $len);
            } else {
                $len = $payload;
                if ($datalen < 6 + $len) {
                    break;
                }
                $mask = substr($back, 2, 4);
                $data = substr($back, 6, $len);
                $back = substr($back, 6 + $len);
            }

            if ($type != 1) {
                continue;
            }
            $str = '';
            if ($encrypt) {
                $len = strlen($data);
                for ($i = 0; $i < $len; $i++) {
                    $str .= $data[$i] ^ $mask[$i % 4];
                }
            } else {
                $str = $data;
            }
            $result[] = $str;
        }
        return $result;
    }


    function encode($data)
    {
        $data = is_array($data) || is_object($data) ? json_encode($data) : (string)$data;
        $len = strlen($data);
        $head[0] = 129;
        if ($len <= 125) {
            $head[1] = $len;
        } elseif ($len <= 65535) {
            $split = str_split(sprintf('%016b', $len ), 8);
            $head[1] = 126;
            $head[2] = bindec($split[0]);
            $head[3] = bindec($split[1]);
        } else {
            $split = str_split(sprintf('%064b', $len), 8);
            $head[1] = 127;
            for ($i = 0; $i < 8; $i++) {
                $head[$i+2] = bindec($split[$i]);
            }
            if ($head[2] > 127) {
                return false;
            }
        }
        foreach ($head as $k => $v) {
            $head[$k] = chr($v);
        }

        return implode('', $head) . $data;
    }

    /**
     * [搜索用户]
     * @param $accept
     * @return bool|mixed
     */
    protected function searchAccept($accept)
    {
        $search = array_search($accept, $this->accept, true);
        if ($search === null) {
            return false;
        }
        return $search;
    }

    /**
     * [获取头部信息,并进行握手]
     * @param $data
     * @param $accept
     * @return bool|int
     */
    protected function getHeader($data, $accept)
    {
        $header = $this->parseHeader($data, true);
        $msg = '';

        // 最多 4096 信息
        if (strlen($data) >= 4096) {
            return false;
        }

        // 系统本身的 api 调用
        if (!empty($header['api'])) {
            // key = 验证的 time = 验证的
            $arr = explode('|' , trim($header['api']), 2);
            if (count($arr) != 2) {
                return false;
            }

            list($time, $key) = $arr;
            if ($time > time() || $time < (time() - 10)) {
                return false;
            }

            if (empty($this->key) || strlen($key) != 64) {
                return false;
            }

            if ((md5($this->key . $time) . md5($time . $this->key)) !== $key) {
                return false;
            }
            $msg .= '200';
            if (!socket_write($accept, $msg, strlen($msg))) {
                return false;
            }
            return false;
        }

        // flash 验证信息
        if (trim(implode('', $header)) == '<policy-file-request/>') {
            $msg .= '<?xml version="1.0"?>';
            $msg .= '<cross-domain-policy>';
            $msg .= '<allow-access-from domain="'. ( $this->domain ? '*.' . $this->domain : '*' ) .'" to-ports="*"/>';
            $msg .= '</cross-domain-policy>';
            $msg .= "\0";
            socket_write($accept, $msg, strlen($msg));
            return false;
        }

        // 超过最大在线
        if (WEBSOCKET_ONLINE <= count($this->accept)) {
            return false;
        }

        // 来路
        $origin = empty($header['origin']) ? empty($header['websocket-origin']) ? '' : $header['websocket-origin'] : $header['origin'];
        $parse = parse_url($origin);
        $scheme = empty($parse['scheme']) || $parse['scheme'] != 'https' ? '' : 's';
        $origin = $origin && !empty($parse['host']) ? 'http' . $scheme . '://' . $parse['host'] : '';

        // 无效来路
        if ($this->config['domain'] && !empty( $parse['host'] ) && !preg_match('/(^|\.)' . preg_quote($this->config['domain'], '/') . '$/i', $parse['host'])) {
            return false;
        }

        //  10+ 版本的 ---握手
        if (!empty($header['sec-websocket-key'])) {
            $type = self::VERSION_NEW;
            $a = base64_encode(sha1(trim($header['sec-websocket-key']) . self::WEBSOCKET_KEY, true));

            $msg .= "HTTP/1.1 101 Switching Protocols\r\n";
            $msg .= "Upgrade: websocket\r\n";
            $msg .= "Connection: Upgrade\r\n";
            if ($origin) {
                $msg .= "Sec-WebSocket-Origin: {$origin}\r\n";
            }
            $msg .= "Sec-WebSocket-Accept: $a\r\n";
            $msg .= "\r\n";

            if (!socket_write($accept, $msg, strlen($msg))) {
                return false;
            }

            return self::VERSION_NEW;
        }

        // 10- 版本的
        if (!empty($header['sec-websocket-key1']) && !empty($header['sec-websocket-key2']) && !empty($header['header'])) {

            $key1 = $header['sec-websocket-key1'];
            $key2 = $header['sec-websocket-key2'];
            $key3 = $header['header'];
            if (!preg_match_all('/([\d]+)/', $key1, $key1_num) || !preg_match_all('/([\d]+)/', $key2, $key2_num)) {
                return false;
            }
            $key1_num = implode($key1_num[0]);
            $key2_num = implode($key2_num[0]);

            if (!preg_match_all('/([ ]+)/', $key1, $key1_spc) || !preg_match_all('/([ ]+)/', $key2, $key2_spc)) {
                return false;
            }

            $key1_spc = strlen(implode($key1_spc[0]));
            $key2_spc = strlen(implode($key2_spc[0]));

            $key1_sec = pack("N", $key1_num / $key1_spc);
            $key2_sec = pack("N", $key2_num / $key2_spc);

            $msg .= "HTTP/1.1 101 Web Socket Protocol Handshake\r\n";
            $msg .= "Upgrade: WebSocket\r\n";
            $msg .= "Connection: Upgrade\r\n";
            if ($origin) {
                $msg .= "Sec-WebSocket-Origin: {$origin}\r\n";
            }
            $msg .= "Sec-WebSocket-Location: ws{$scheme}://{$this->host}:{$this->port}{$this->path}\r\n";
            $msg .= "\r\n";
            $msg .= md5($key1_sec . $key2_sec . $key3, true);
            if (!socket_write($accept, $msg, strlen($msg))) {
                return false;
            }
            return self::VERSION_OLD;
        }

        return false;
    }

    public function parseHeader($header = '', $strtolower = false)
    {
        echo "\n\r\n\r";
        if ($header === '') {
            return [];
        }

        $header = str_replace("\r\n", "\n", $header);
        $header = explode("\n\n", $header, 2);
        $info   = explode("\n", $header[0]);

        $result  = [];
        foreach ($info as $v) {
            if ($v) {
                $v = explode(':', $v);
                if (isset($v[1])) {
                    if ($strtolower) {
                        $v[0] = strtolower($v[0]);
                    }
                    if (substr($v['1'], 0, 1) == '') {
                        $v[1] = substr($v[1], 1);
                    }
                    $result[trim($v[0])] = $v[1];
                } else if (empty($result['status']) && preg_match('/^(HTTP|GET|POST)/', $v[0])) {
                    $result['status'] = $v[0];
                } else {
                    $result[] = $v[0];
                }
            }
        }
        if (!empty($header[1])) {
            $result['header'] = $header[1];
        }
        return $result;
    }

    function close($accept)
    {
        if (($index = $this->searchAccept($accept)) === false) {
            return false;
        }
        socket_close($accept);
        $bind = [];
        if (isset($this->accept[$index])) {
            unset($this->accept[$index]);
        }

        if (isset($this->type[$index])) {
            unset($this->type[$index]);
        }

        if (isset($this->bind[$index])) {
            $bind = $this->bind[$index];
            unset($this->bind[$index]);
        }

        if (isset($this->cycle[$index])) {
            unset($this->cycle[$index]);
        }

        if (isset($this->time[$index])) {
            unset($this->time[$index]);
        }

        empty($this->function['close']) || call_user_func_array($this->function['close'], array($bind, $this));
        return true;
    }
}
//Class Websocket End!!

function add_socket_call($accept, $index, $class)
{

    // 自动关闭 90 秒没有动作的
    $class->time[$index] = time();
    $class->bind[$index]['ip'] = $class->ip($accept);

    // 关闭过久没响应的
    if (rand(0, 1000)) {
        return false;
    }
    foreach ($class->accept as $k => $v) {
        if ($class->type[$k] != WEBSOCKET_TYPE_API) {
            if (empty( $class->time[$k]) || (time() - $class->time[$k]) > 100) {
                $class->close($v);
            }
        }
    }
}

function get_socket_call($data, $accept, $index, $class)
{
    // 超过 1024 字节就结束
    if (strlen($data) > 1024) {
        return false;
    }

    $data = string_turn_array($data);

    // time 包
    if (!empty($data['time'])) {
        $time = time();
        $class->time[$index] = $time;
        return $class->send(array('time' => $time), $accept);
    }

    // 添加名称
    if (!empty($data['name'])) {
        $name = htmlspecialchars((string)$data['name'], ENT_QUOTES);
        $admin = explode(',', $name , 2);

        // 管理员的
        if (!empty($admin[1]) && $admin[1] === (string)ADMIN_PASS) {
            $name = '<strong class="admin_name">管理员:'. $admin[0] .'</strong>';
            $class->bind[$index]['admin'] = true;
        }

        // 你已经有名称了
        if (!empty($class->bind[$index]['name'])) {
            return  $class->send(array('msg' => '<div class="msg error">你已经有名称了</div>'), $accept);
        }

        // 名称以存在
        foreach ($class->bind as $k => $v) {
            if (!empty($v['name']) && $v['name'] == $name) {
                return  $class->send( array( 'msg' => '<div class="msg error">名称已存在</div>' ), $accept );
            }
        }

        ws_send_all(array('list' => array( array( $name, true ))), $class);
        ws_send_all(array('msg' => '<div class="msg login"><strong class="name">'. $name .'</strong>登录聊天室</div>' ), $class );

        $class->bind[$index]['name'] = $name;
        $list = array();
        foreach($class->bind as $v) {
            if (!empty( $v['name'])) {
                $list[] = array($v['name'], true);
            }
        }
        $class->send(array('list' => $list), $accept);
        return $class->send(array('name' => true, 'msg' => '<div class="msg yes">你已经成功登录上聊天室</div>'), $accept);
    }

    // 聊天
    if (!empty( $data['chat'])) {
        $name = empty( $class->bind[$index]['name']) ? '' : $class->bind[$index]['name'];
        $admin = !empty( $class->bind[$index]['admin']);
        $chat = $admin ? (string) $data['chat'] : nl2br(htmlspecialchars((string) $data['chat'], ENT_QUOTES ));

        if ($admin && $chat == 'die') {
            die;
        }

        if (!$name) {
            return $class->send(array('msg' => '<div class="msg error">你还没有输入你的名称</div>'), $accept);
        }

        return ws_send_all(array('chat' => '<div class="chat'. ($admin ? 'admin_chat' : '') .'"><div class="name">'. $name .'</div><p>'. $chat .'</p></div>' ), $class);
    }
}

function close_socket_call($bind, $class)
{
    if (empty($bind['name'])) {
        return false;
    }
    ws_send_all(array('list' => array(array($bind['name'], false))), $class);
    ws_send_all(array('msg' => '<div class="msg logout"><strong class="name">'. $bind['name'] .'</strong>离开聊天室</div>' ), $class);
}

function ws_send_all($data, $class)
{
    foreach ($class->bind as $k => $v) {
        if (empty( $v['name']) || $class->type[$k] == WEBSOCKET_TYPE_API) {
            continue;
        }
        $class->send($data, $class->accept[$k]);
    }
}
