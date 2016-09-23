<?php

require_once 'libs/phpqrcode/qrlib.php';
require_once 'libs/function.php';

class WebWeixin
{
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

    private $member_count;
    private $member_list;
    private $public_user_list;
    private $contact_list;
    private $group_list;


    public function __construct()
    {
        $this->cookie_jar = tempnam(sys_get_temp_dir(), 'wx_webapi');
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

        QRcode::png($url, 'saved/qrcode.png', 'L', 4, 2);

        exec('open saved/qrcode.png');

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
            'DeviceID' => 'e'.rand(100000000000000, 99999999999999)
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

        $this->SyncKey = $arr_data['SyncKey'];
        $this->User = $arr_data['User'];

        $rynckey_list = array();
        foreach ($this->SyncKey['List'] as $item) {
            $rynckey_list[] = $item['Key'].'_'.$item['Val'];
        }

        $this->rynckey = implode('|', $rynckey_list);

        return $arr_data['BaseResponse']['Ret'] == 0;
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
                'Content-Type: application/json',
            );

            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

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

        while (true) {
            _echo('正在获取 UUID ... ', $this->getUUID());
            _echo('正在获取二维码 ...', $this->genQRCodeImg());

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

        _echo('开启状态通知 ...', $this->webWxStatusNotify());

        _echo('获取联系人信息 ...', $this->webWxGetContact());

        _echo('获取联系人数量：'.$this->member_count);
    }
}

$weixin = new WebWeixin();
$weixin->run();