<?php

require_once 'libs/phpqrcode/qrlib.php';
require_once 'libs/function.php';

class WebWeixin
{
    private $id;
    private $uuid;
    private $appid = 'wx782c26e4c19acffb';
    private $redirect_uri;
    private $base_uri;
    private $skey;
    private $sid;
    private $uin;
    private $pass_ticket;
    private $BaseRequest;
    private $cookie_jar;
    private $SyncKey;
    private $User;
    private $device_id;
    private $synckey;

    private $syncCheck_num = 0;

    private $member_count;
    private $member_list;
    private $public_user_list;
    private $contact_list;
    private $group_list;

    private $bot_member_list = array();


    public function __construct()
    {
        $this->cookie_jar = tempnam(sys_get_temp_dir(), 'wx_webapi');
        $this->device_id = 'e'.rand(100000000000000, 999999999999999);
    }

    /**
     * 设置ID
     * @param $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * 获取 UUID
     * @return bool
     */
    public function getUUID()
    {
        $url = 'https://login.weixin.qq.com/jslogin';

        $params = array(
            'appid' => $this->appid,
            'fun' => 'new',
            'lang' => 'zh_CN',
            '_' => time()
        );

        $data = $this->_post($url, $params, false);

        $regx = '#window.QRLogin.code = (\d+); window.QRLogin.uuid = "(\S+?)"#';

        preg_match($regx, $data, $res);

        if ($res) {
            $code = $res[1];
            $this->uuid = $res[2];

            return $code == '200';
        }

        return false;
    }


    /**
     * 生成登录二维码
     */
    public function genQRCodeImg()
    {
        $url = 'https://login.weixin.qq.com/l/'.$this->uuid;

        QRcode::png($url, 'saved/'.$this->uuid.'.png', 'L', 4, 2);

        return true;
    }


    /**
     * 检测是否扫描二维码登录
     * @param int $tip
     * @return bool
     */
    public function waitForLogin($tip=1)
    {
        $url = sprintf('https://login.weixin.qq.com/cgi-bin/mmwebwx-bin/login?tip=%s&uuid=%s&_=%s', $tip, $this->uuid, time());


        $data = $this->_get($url);

        if ($data == false) {
            return false;
        }

        $regx = '#window.code=(\d+);#';

        preg_match($regx, $data, $res);

        $code = $res[1];

        if ($code == '201') {
            return true;
        } elseif ($code == '200') {
            $regx = '#window.redirect_uri="(\S+?)";#';

            preg_match($regx, $data, $res);

            $r_uri = $res[1].'&fun=new';

            $this->redirect_uri = $r_uri;
            $this->base_uri = substr($r_uri, 0, strrpos($r_uri, '/'));
            return true;
        } elseif ($code == '408') {
            _echo('登录超时');
        } else {
            _echo('登录异常');
        }

        return false;
    }


    /**
     * 执行登录
     */
    public function login()
    {
        $data = $this->_get($this->redirect_uri);

        $xml = simplexml_load_string($data);

        $arr_xml = json_decode(json_encode($xml), true);

        $this->skey = $arr_xml['skey'];
        $this->sid = $arr_xml['wxsid'];
        $this->uin = $arr_xml['wxuin'];
        $this->pass_ticket = $arr_xml['pass_ticket'];

        if (in_array('', array($this->skey, $this->sid, $this->uin, $this->pass_ticket))) {
            return false;
        }

        $this->BaseRequest = array(
            'Uin' => intval($this->uin),
            'Sid' => $this->sid,
            'Skey' => $this->skey,
            'DeviceID' => $this->device_id
        );

        return true;
    }


    /**
     * 微信初始化
     */
    public function webWxInit()
    {
        $url = sprintf($this->base_uri . '/webwxinit?pass_ticket=%s&skey=%s&r=%s', $this->pass_ticket, $this->skey, time());

        $params = json_encode(array('BaseRequest'=>$this->BaseRequest));

        $data = $this->_post($url, $params);

        $arr_data = json_decode($data, true);

        if ($arr_data['BaseResponse']['Ret'] != 0) {
            return false;
        }

        $this->SyncKey = $arr_data['SyncKey'];
        $this->User = $arr_data['User'];

        $synckey_list = array();
        foreach ($this->SyncKey['List'] as $item) {
            $synckey_list[] = $item['Key'].'_'.$item['Val'];
        }

        $this->synckey = implode('|', $synckey_list);

        return true;
    }


    /**
     * 消息通知
     * @return bool
     */
    public function webWxStatusNotify()
    {
        $url = sprintf($this->base_uri.'/webwxstatusnotify?lang=zh_CN&pass_ticket=%s', $this->pass_ticket);

        $params = array(
            'BaseRequest' => $this->BaseRequest,
            'Code' => 3,
            'FromUserName' => $this->User['UserName'],
            'ToUserName' => $this->User['UserName'],
            'ClientMsgId' => time()
        );

        $data = $this->_post($url, json_encode($params));

        $arr_data = json_decode($data, true);

        return $arr_data['BaseResponse']['Ret'] == 0;
    }


    /**
     * 获取联系人列表
     * @return bool
     */
    public function webWxGetContact()
    {
        $url = sprintf($this->base_uri.'/webwxgetcontact?pass_ticket=%s&skey=%s&r=%s', $this->pass_ticket, $this->skey, time());

        $data = $this->_post($url , array());

        $arr_data = json_decode($data, true);

        file_put_contents('/tmp/data.json', $data);

        $this->member_count = $arr_data['MemberCount'];
        $this->member_list = $arr_data['MemberList'];

        return true;
    }


    /**
     * 同步刷新
     * @return bool
     */
    public function syncCheck()
    {

        $params = array(
            'r' => time(),
            'sid' => $this->sid,
            'uin' => $this->uin,
            'skey' => $this->skey,
            'devicedid' => $this->device_id,
            'synckey' => $this->synckey,
            '_' => time()
        );

        $url = $this->base_uri.'/synccheck?'.http_build_query($params);

        $data = $this->_get($url);

        $regx = '#window.synccheck={retcode:"(\d+)",selector:"(\d+)"}#';

        preg_match($regx, $data, $res);

        $retcode = $res[1];
        $selector = $res[2];

        switch ($retcode) {
            case 0:
                _echo('同步数据轮次: '.++$this->syncCheck_num);
                break;
            default:
                _echo('同步数据失败 或 登出微信');
                exit();
        }

        _echo('retcode: '.$retcode.', selector: '.$selector);

        return array('retcode'=>$retcode, 'selector'=>$selector);
    }


    /**
     * 获取消息
     * @return mixed
     */
    public function webWxSync()
    {

        $url = sprintf($this->base_uri.'/webwxsync?sid=%s&skey=%s&pass_ticket=%s', $this->sid, $this->skey, $this->pass_ticket);

        $params = array(
            'BaseRequest' => $this->BaseRequest,
            'SyncKey' => $this->SyncKey,
            'rr' => ~time()
        );

        $data = $this->_post($url, json_encode($params));;

        $arr_data = json_decode($data, true);

        if ($arr_data['BaseResponse']['Ret'] == '0') {
            $this->SyncKey = $arr_data['SyncKey'];

            $synckey_list = array();
            foreach ($this->SyncKey['List'] as $item) {
                $synckey_list[] = $item['Key'].'_'.$item['Val'];
            }

            $this->synckey = implode('|', $synckey_list);
        }

        return $arr_data;
    }


    /**
     * 监听消息
     */
    public function listenMsgMode()
    {
        _echo('进入消息监听模式 ... 成功');

        while (true) {

            $sync_time = time();

            $sync_check = $this->syncCheck();

            if ($sync_check['retcode'] == 0) {

                switch ($sync_check['selector']) {
                    case 0:
                        break;
                    case 2:
                        $res = $this->webWxSync();
                        $this->handleMsg($res);
                        break;
                    case 7:
                        break;

                    default:
                        _echo('意外退出 ...');
                        exit();
                }
            }

            $sleep = (time()-$sync_time) > 3 ? 3 : 1;

            sleep($sleep);
        }
    }

    /**
     * @param $id
     */
    public function getUserRemarkName($id)
    {

        $name = '陌生人';

        if (substr($id, 0, 2) == '@@') {
            $name = '未知群';
        }

        if ($id == $this->User['UserName']) {
            return $this->User['NickName'];
        }

        if (substr($id, 0, 2) == '@@') {
            $name = '未知群';
        }
    }


    public function handleMsg($res)
    {
        foreach ($res['AddMsgList'] as $msg) {

            $msg_type = $msg['MsgType'];
            $from_username = $msg['FromUserName'];
            $msgid = $msg['MsgId'];
            $content = $msg['Content'];

            if ($msg_type == 1) {

                // 控制退出
                if ($from_username == $this->User['UserName'] && $content == '退出托管') {
                    $this->_webWxSendmsg('退出托管成功', $this->User['UserName']);
                    exit();
                }

                if ($content == '开启') {
                    $this->bot_member_list[$from_username] = 1;
                    $this->_webWxSendmsg('已开始机器人回复模式', $from_username);
                    return ;
                }

                if ($content == '关闭') {
                    unset($this->bot_member_list[$from_username]);
                    $this->_webWxSendmsg('已关闭机器人回复模式', $from_username);
                    return ;
                }

                $this->_showMsg($msg);

                if (in_array($from_username, array_keys($this->bot_member_list))) {

                    $answer = $this->_tuling_bot($content, $from_username);

                    $this->_webWxSendmsg($answer, $from_username);
                }

            }
        }
    }


    /**
     * 发送文本消息
     * @param $content
     * @param $user
     * @return bool
     */
    private function _webWxSendmsg($content, $user)
    {
        $url = sprintf($this->base_uri.'/webwxsendmsg?pass_ticket=%s', $this->pass_ticket);
        $clientMsgId = time()*1000 . rand(1000, 9999);

        $params = array(
            'BaseRequest' => $this->BaseRequest,
            'Msg' => array(
                'Type' => 1,
                'Content' => $content,
                'FromUserName' => $this->User['UserName'],
                'ToUserName' => $user,
                'LocalID' => $clientMsgId,
                'ClientMsgId' => $clientMsgId
            )
        );

        $data = $this->_post($url, json_encode($params, JSON_UNESCAPED_UNICODE));

        $arr_data = json_decode($data, true);

        return $arr_data['BaseResponse']['Ret'] == 0;
    }


    /**
     * 图灵机器人
     * @param $query
     * @param $userid
     * @return mixed
     */
    private function _tuling_bot($query, $userid)
    {
        $url = 'http://www.tuling123.com/openapi/api';

        $params = array(
            'key' => 'a0dc5c2edd76999392a9bf45533ab758',
            'info' => $query,
            'userid' => $userid
        );

        $data = $this->_post($url, json_encode($params));

        $arr_data = json_decode($data, true);

        return $arr_data['text'];
    }


    /**
     * 一个AI机器人
     * @param $query
     * @param $userid
     * @return mixed
     */
    private function _yigeai_bot($query, $userid)
    {
        $data = array(
            'token' => '20F21FED84B1BC7F88C798C90FBAEBBB',
            'query' => $query,
            'session_id' => md5($userid)
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://www.yige.ai/v1/query');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);

        $arr_res = json_decode($response, true);

        return $arr_res['answer'];
    }


    private function _showMsg($message)
    {
        $from_username = $message['FromUserName'];
        $to_username = $message['ToUserName'];

        $search = array('&lt;', '&gt;');
        $replace = array('<', '>');

        $content = str_replace($search, $replace, $message['Content']);
        $message_id = $message['MsgId'];

        if (substr($message['FromUserName'], 0, 2) == '@@') {
            list($from_username, $content) = explode(':<br/>', $content);
        }

        _echo('');
        _echo('MsgId: '. $message_id);
        _echo('From: '.$from_username);
        _echo('TO: '.$to_username);
        _echo('消息内容: '.$content);
        _echo('');
    }


    /**
     * POST请求
     * @param $url
     * @param $params
     * @return bool|mixed
     */
    private function _post($url, $params, $jsonfmt=true)
    {
        $ch = curl_init();

        if ($jsonfmt) {
            $header = array(
                'Content-Type: application/json; charset=UTF-8',
            );

            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.1.4322; .NET CLR 2.0.50727)");
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_jar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_jar);

        $data = curl_exec($ch);

        if (curl_errno($ch)) {
            //print "Error: " . curl_error($ch);
            return false;
        } else {
            return $data;
        }
    }


    /**
     * GET请求
     * @param $url
     * @return bool|mixed
     */
    private function _get($url)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_REFERER, 'https://wx.qq.com/');
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.1.4322; .NET CLR 2.0.50727)");
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_jar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_jar);

        $data = curl_exec($ch);

        if (curl_errno($ch)) {
            _echo(' Error: ' . curl_error($ch));
            return false;
        } else {
            return $data;
        }
    }


    /**
     * 运行
     */
    public function run()
    {
        _echo('微信网页版 ... 启动');

        $login_num = 0;
        while (true) {
            _echo('正在获取 UUID ... ', $this->getUUID());
            _echo('正在获取二维码 ...', $this->genQRCodeImg());

            // 设置用户与二维码对应关系
            $id_info = array('status'=>3, 'uuid'=>$this->uuid);
            set_cache($this->id, $id_info);

            $login_num++;

            if ($login_num == 3) {
                exit();
            }

            _echo('请使用微信扫描二维码 ...');

            if (!$this->waitForLogin()) {
                continue;
            }

            _echo('请在手机上点击确认登录 ...');

            if (!$this->waitForLogin(0)) {
                continue;
            }

            break;
        }

        _echo('正在登录 ...', $this->login());

        _echo('微信初始化 ...', $this->webWxInit());

        $id_info = array('status'=>4);
        set_cache($this->id, $id_info);

        _echo('开启状态通知 ...', $this->webWxStatusNotify());

        _echo('获取联系人信息 ...', $this->webWxGetContact());

        _echo('获取联系人数量：'.$this->member_count);

        $this->_webWxSendmsg('微信托管成功', $this->User['UserName']);

        $this->listenMsgMode();
    }
}

$id = $argv[1];

register_shutdown_function('shutdown', $id);


$weixin = new WebWeixin();
$weixin->setId($id);
$weixin->run();
