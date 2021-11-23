<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;
use we7\template;
use zovye\base\modelObj;
use zovye\model\accountModelObj;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\model\weapp_configModelObj;

class WeApp extends Settings
{
    private static $app_settings = null;
    private $logger;

    public function __construct()
    {
        parent::__construct('weapp', 'config', true);
    }

    public function createWebUrl($do, $params = []): string
    {
        return Util::url($do, $params, false);
    }

    public function createMobileUrl($do, $params = []): string
    {
        return Util::murl($do, $params);
    }

    /**
     * @param $filename
     * @param array $tpl_data
     */
    public function showTemplate($filename, array $tpl_data = [])
    {
        $tpl_data['_GPC'] = $GLOBALS['_GPC'];
        $tpl_data['_W'] = $GLOBALS['_W'];

        extract($tpl_data);

        include self::template($filename);
        exit();
    }

    public static function template($filename)
    {
        global $_W;
        if (defined('IN_SYS')) {
            $source = ZOVYE_ROOT . "template/$filename.html";
            $compile = ZOVYE_ROOT . "data/tpl/$filename.tpl.php";
        } else {
            $source = ZOVYE_ROOT . "template/mobile/$filename.html";
            $compile = ZOVYE_ROOT . "data/tpl/mobile/$filename.tpl.php";
        }

        if (!is_file($source)) {
            exit("Error: template source '$filename' is not exist!");
        }
        $paths = pathinfo($compile);
        $compile = str_replace($paths['filename'], $_W['uniacid'] . '_' . $paths['filename'], $compile);
        if (DEVELOPMENT || !is_file($compile) || filemtime($source) > filemtime($compile)) {
            template::compile($source, $compile, true);
        }

        return $compile;
    }


    public function forceUnlock(): bool
    {
        return We7::pdo_update(weapp_configModelObj::getTableName(modelObj::OP_WRITE),
            [
                OBJ_LOCKED_UID => UNLOCKED,
            ],
            [
                'name' => 'settings',
            ]
        );
    }

    public function lock(): ?RowLocker
    {
        //避免首次安装时，webapp_config没有任何数据时出错
        $this->updateSettings('app.locker', time());

        $global = m('weapp_config')->findOne(['name' => 'settings']);
        if ($global) {
            return Util::lockObject($global, [OBJ_LOCKED_UID => UNLOCKED], true);
        }

        return null;
    }

    public function isLocked(): bool
    {
        /** @var weapp_configModelObj $global */
        $global = m('weapp_config')->findOne(['name' => 'settings']);
        return $global && $global->getLockedUid() != UNLOCKED;
    }

    public function resetLock()
    {
        /** @var weapp_configModelObj $global */
        $global = m('weapp_config')->findOne(['name' => 'settings']);
        if ($global) {
            $global->setLockedUid(UNLOCKED);
            $global->save();
        }
    }

    public function isSite(): bool
    {
        return class_exists(__NAMESPACE__ . '\Site');
    }

    /**
     * @return $this
     * @throws Exception
     */
    public function run(): WeApp
    {
        class_alias(__NAMESPACE__ . '\Site', lcfirst(APP_NAME) . 'ModuleSite');
        return $this;
    }

    public function saveSettings($settings): bool
    {
        if ($this->set('settings', $settings)) {
            self::$app_settings = $settings;

            return true;
        }

        return false;
    }

    public function updateSettings($key, $val): bool
    {
        if (is_null(self::$app_settings)) {
            self::$app_settings = $this->get('settings', []);
        }

        setArray(self::$app_settings, $key, $val);

        return $this->set('settings', self::$app_settings);
    }

    public function settings($key = null, $default = null)
    {
        if (is_null(self::$app_settings)) {
            self::$app_settings = $this->get('settings', []);
            if (empty(self::$app_settings)) {
                $filename = ZOVYE_CORE_ROOT . 'include/settings_default.php';
                if (file_exists($filename)) {
                    self::$app_settings = include $filename;
                }
            }
        }

        return getArray(self::$app_settings, $key, $default);
    }


    public function log($level = null, $title = null, $data = null)
    {
        if (!isset($this->logger)) {
            $this->logger = new Log('app');
        }

        if ($this->logger && isset($level) && isset($title) && isset($data)) {
            $this->logger->log($level, $title, $data);
        }
    }

    /**
     * 加载并返回页面模板字符串.
     *
     * @param string $name 模板名称
     * @param array $tpl_data
     * @return string
     */
    public function fetchTemplate(string $name, array $tpl_data = []): string
    {
        $tpl_data['_GPC'] = $GLOBALS['_GPC'];
        $tpl_data['_W'] = $GLOBALS['_W'];

        extract($tpl_data);

        ob_start();

        include self::template($name);

        return ob_get_clean();
    }

    /**
     * 用户扫描设备页面.
     *
     * @param array $params
     */
    public function scanPage(array $params = [])
    {
        //以下为页面数据
        $tpl = is_array($params) ? $params : [];

        $token = Util::random(16);
        $redirect_url = Util::murl('entry', ['from' => 'device', 'device' => $token]);
        $js_sdk = Util::fetchJSSDK();
        $jquery_url = JS_JQUERY_URL;
        $tpl['js']['code'] = <<<JSCODE
<script src="$jquery_url"></script>
$js_sdk
<script>
    const zovye_fn = {};
    zovye_fn.scan = function(){
        wx.scanQRCode({
            needResult: 1,
            success: function(data) {
                if(data && data.resultStr) {
                    const url = data.resultStr;
                    let result = url.match(/id=(\w*)/);
                    if (!result) {
                        result =  url.match(/device=(\w*)/);
                    }
                    if(result) {
                        const id = result[1];
                        if(id) {
                            window.location.replace("$redirect_url".replace("$token", id));                            
                        }
                    }
                }
            }
        })
    }
    wx.ready(function(){
        wx.hideAllNonBaseMenuItem();
        zovye_fn.scan();
    })    
    $(function(){
       $(document).on("touchstart", function(e) {
            const target = $(e.target);
            if(!target.hasClass("disable")) target.data("isMoved", 0);
        })
        $(document).on("touchmove", function(e) {
            const target = $(e.target);
            if(!target.hasClass("disable")) target.data("isMoved", 1);
        })
        $(document).on("touchend", function(e) {
            const target = $(e.target);
            if(!target.hasClass("disable") && target.data("isMoved") === 0) target.trigger("tap");
        })
    })
</script>
JSCODE;
        $this->showTemplate(Theme::file('scan'), ['tpl' => $tpl]);
    }

    /**
     * 设备状态准备页面
     * @param array $params
     */
    public function devicePreparePage($params = [])
    {
        $tpl = is_array($params) ? $params : [];

        /** @var deviceModelObj $device */
        $device = $tpl['device']['_obj'];

        /** @var userModelObj $user */
        //$user = $tpl['user']['_obj'];

        $device_url = empty($params['redirect']) ? Util::murl('entry', ['device' => $device->getShadowId()]) : strval($params['redirect']);
        $device_api_url = Util::murl('device', ['id' => $device->getId()]);
        $jquery_url = JS_JQUERY_URL;

        $js_sdk = Util::fetchJSSDK();
        $tpl['max'] = is_numeric($params['max']) ? $params['max'] : 3;
        $tpl['text'] = empty($params['text']) ? '设备连接中' : $params['text'];
        $tpl['err_msg'] = empty($params['err_msg']) ? '设备不在线，请稍后再试！' : $params['err_msg'];

        $tpl['icon'] = [
            'loading' => empty($params['icon']['loading']) ? MODULE_URL . 'static/img/loading-puff.svg' : $params['icon']['loading'],
            'success' => empty($params['icon']['success']) ? MODULE_URL . 'static/img/smile.svg' : $params['icon']['success'],
            'error' => empty($params['icon']['error']) ? MODULE_URL . 'static/img/offline.svg' : $params['icon']['error'],
        ];

        $scene = empty($params['scene']) ? 'online' : $params['scene'];
        $tpl['js']['code'] .= <<<JSCODE
        <script src="$jquery_url"></script>
        $js_sdk
        <script>
        wx.ready(function(){
            wx.hideAllNonBaseMenuItem();
        })
        if (typeof zovye_fn === 'undefined') {
            zovye_fn = {};
        }
        zovye_fn.getDetail = function (cb) {
            $.get("$device_api_url", {op: 'detail'}).then(function (res) {
                if (typeof cb === 'function') {
                    cb(res);
                }
            })
        }
        zovye_fn.isReady = function (cb) {
            $.get("$device_api_url", {op: 'is_ready', scene: '$scene', serial: (new Date()).getTime()}).then(function (res) {
                if (typeof cb === 'function') {
                    cb(res);
                }
            })
        }
        zovye_fn.redirect = function() {
            window.location.replace("$device_url");
        }
JSCODE;
        $tpl['js']['code'] .= "\r\n</script>";
        $this->showTemplate(Theme::file('prepare'), ['tpl' => $tpl]);
    }

    /**
     * 设备页面，通常展示了可用的关注二维码列表和支付信息.
     * @param array $params
     */
    public function devicePage(array $params = [])
    {
        $tpl = is_array($params) ? $params : [];
        $tpl['slides'] = [];

        /** @var deviceModelObj $device */
        $device = $tpl['device']['_obj'];

        /** @var userModelObj $user */
        $user = $tpl['user']['_obj'];

        if (App::isAliUser()) {
            $tpl['accounts'] = [];
            //天猫拉新活动
            if (App::isCustomAliTicketEnabled()) {
                $result = AliTicket::fetchAsAccount($user, $device, true);
                if (!is_error($result)) {
                    $tpl['accounts'][] = $result;
                }
            }
        } else {
            $last_account = $user->getLastActiveData('account');
            if ($last_account) {
                $tpl['accounts'] = [$last_account];
                $user->setLastActiveData();
            } else {
                $tpl['accounts'] = Account::getAvailableList($device, $user, [
                    'exclude' => $params['exclude'],
                    //type 不包含 Account::WXAPP，兼容以前不支持该类型的皮肤，新皮肤使用js api接口获取
                    'type' => [
                        Account::NORMAL,
                        Account::VIDEO,
                        Account::AUTH,
                    ],
                    'include' => [Account::COMMISSION],
                ]);
            }
        }

        //如果设置必须关注公众号以后才能购买商品
        $goods_list_FN = false;
        if (Helper::MustFollowAccount($device)) {
            if ($tpl['from'] != 'account') {
                if (empty($tpl['accounts'])) {
                    $account = Account::getUserNext($device, $user);
                    if ($account) {
                        $tpl['accounts'][] = $account;
                    }
                }
            } else {
                $goods_list_FN = true;
                $tpl = array_merge($tpl, ['goods' => $device->getGoodsList($user, ['allowPay'])]);
            }
        } else {
            $goods_list_FN = true;
            $tpl = array_merge($tpl, ['goods' => $device->getGoodsList($user, ['allowPay'])]);
        }

        //如果无法领取，则清除访问记录
        if (empty($tpl['accounts']) && empty($tpl['goods'])) {
            $user->remove('last');
        }

        //如果没有货道，或只有一个货道，并且商品数量不足，或所有商品都没有允许免费领取，则无法免费领取
        $lanesNum = $device->getCargoLanesNum();
        if ($lanesNum == 1) {
            $goods = $device->getGoodsByLane(0);
            if (empty($goods) || $goods['num'] < 1) {
                $tpl['accounts'] = [];
            }
        } elseif ($lanesNum > 1) {
            $free_goods_list = $device->getGoodsList($user, ['allowFree']);
            if (empty($free_goods_list)) {
                $tpl['accounts'] = [];
            }
        } else {
            $tpl['accounts'] = [];
        }

        ComponentUser::removeAll(['user_id' => $user->getId()]);

        foreach ($tpl['accounts'] as $index => $account) {
            //检查直接转跳的吸粉广告或公众号
            if (!empty($account['redirect_url'])) {
                //链接转跳前，先判断设备是否在线
                if ($device->isMcbOnline()) {
                    Util::redirect($account['redirect_url']);
                    exit('正在转跳...');
                }
                unset($tpl['accounts'][$index]);
            }

            //检查需要关注出货的订阅号
            if (isset($account['service_type']) && $account['service_type'] != Account::SERVICE_ACCOUNT && empty($account['open_timing'])) {
                $footprint = User::makeUserFootprint($user);
                if (!ComponentUser::exists(['user_id' => $user->getId(), 'appid' => $account['appid']])) {
                    ComponentUser::create([
                        'appid' => $account['appid'],
                        'user_id' => $user->getId(),
                        'openid' => $footprint,
                        'extra' => [
                            'device' => $device->getShadowId(),
                            'time' => time(),
                        ]
                    ]);
                } else {
                    $obj = ComponentUser::findOne(['user_id' => $user->getId(), 'appid' => $account['appid']]);
                    if ($obj) {
                        $obj->setOpenid($footprint);
                        $obj->save();
                    }
                }
            }
        }

        //广告列表
        $tpl['slides'] = Advertising::getDeviceSliders($device);

        $device_api_url = Util::murl('device', ['id' => $device->getId()]);
        $adv_api_url = Util::murl('adv', ['deviceid' => $device->getImei()]);
        $order_jump_url = Util::murl('order', ['op' => 'jump']);
        $feedback_url = Util::murl('order', ['op' => 'feedback']);
        $account_url = Util::murl('account');

        $agent = $device->getAgent();
        $mobile = '';
        if ($agent) {
            $mobile = $agent->getMobile();
        }

        $device_name = $device->getName();
        $device_imei = $device->getImei();

        $pay_js = Pay::getPayJs($device, $user);
        if (is_error($pay_js)) {
            Util::resultAlert($pay_js['message'], 'error');
        }

        $requestID = REQUEST_ID;

        $tpl['js']['code'] = $pay_js;
        $tpl['js']['code'] .= <<<JSCODE
<script>
    const adv_api_url = "$adv_api_url";
    const account_api_url = "$account_url";
    const device_api_url = "$device_api_url";

    if (typeof zovye_fn === 'undefined') {
        zovye_fn = {};
    }
    zovye_fn.closeWindow = function () {
        wx && wx.ready(function() {
            wx.closeWindow();
       })
    }
    zovye_fn.getAdvs = function(typeid, num, cb) {
        const params = {num};
        if (typeof typeid == 'number') {
            params['typeid'] = typeid;
        } else {
            params['type'] = typeid;
        }
        $.get(adv_api_url, params).then(function(res){
            if (res && res.status) {
                if (typeof cb === 'function') {
                    cb(res.data);
                } else {
                    console.log(res.data);
                }
            }
        })           
    }
    zovye_fn.play = function(uid, seconds, cb) {
        $.get(account_api_url, {op:'play', uid, seconds, device:'$device_imei', serial: '$requestID'}).then(function(res){
            if (cb) cb(res);
        })    
    }
    zovye_fn.redirectToOrder = function() {
        window.location.href= "$order_jump_url";
    }
    zovye_fn.redirectToFeedBack = function() {
        window.location.href= "$feedback_url&mobile=$mobile&device_name=$device_name&device_imei=$device_imei";
    }
    zovye_fn.getDetail = function (cb) {
        $.get(device_api_url, {op: 'detail'}).then(function (res) {
            if (typeof cb === 'function') {
                cb(res);
            }
        })
    }
    zovye_fn.getAccounts = function(types, cb) {
        $.get(account_api_url, {op:'get_list', device:'$device_imei', types: types}).then(function(res){
            if (cb) cb(res);
        })
    }
    zovye_fn.redirectToAccountGetPage = function(uid) {
        $.get(account_api_url, {op:'get_url', uid, device:'$device_imei'}).then(function(res){
           if (res) {
               if (res.status && res.data.redirect) {
                   window.location.href = res.data.redirect;
               } else {
                   if (res.data && res.data.message) {
                       alert(res.data.message);
                   }
               }
           } else {
               alert('请求转跳网址失败！');
           }
        })
    }
JSCODE;
        if ($goods_list_FN) {
            $tpl['js']['code'] .= <<<JSCODE
\r\nzovye_fn.getGoodsList = function(cb) {
$.get("$device_api_url", {op: 'goods', type:'pay'}).then(function(res) {
        if (typeof cb === 'function') {
            cb(res);
        }
    });
}
zovye_fn.getBalanceGoodsList = function(cb) {
    $.get("$device_api_url", {op: 'goods', type:'balance'}).then(function(res) {
        if (typeof cb === 'function') {
            cb(res);
        }
    });
}
JSCODE;
        }
        if (!App::isAliUser() && App::isChannelPayEnabled()) {
            $pay_url = Util::murl('channel', ['device' => $device->getShadowId()]);
            $tpl['js']['code'] .= <<<JSCODE
\r\nzovye_fn.channelPay = function(goods, num, cb) {
    $.get("$pay_url", {goods, num}).then(function(res){
        if (typeof cb === 'function') {
            cb(res);
        }
    });
}
JSCODE;
        }
        if (App::isDonatePayEnabled()) {
            $donate_url = Util::murl('donate', ['device' => $device->getShadowId()]);
            $tpl['js']['code'] .= <<<JSCODE
\r\nzovye_fn.getDonationInfo = function(cb) {
    $.get("$donate_url").then(function(res) {
        if (typeof cb === 'function') {
            cb(res);
        }
    });
}
JSCODE;
        }

        if (empty($user->settings('fansData.sex'))) {
            $profile_url = Util::murl('util', ['op' => 'profile']);
            $tpl['js']['code'] .= <<<JSCODE
\r\nzovye_fn.saveUserProfile = function(data) {
    $.post("$profile_url", data);
}
JSCODE;
        }

        //检查用户在该设备上最近失败的免费订单
        $retry = settings('order.retry', []);
        if ($retry['last'] > 0) {
            $order = Order::query()->where([
                'openid' => $user->getOpenid(),
                'device_id' => $device->getId(),
                'result_code <>' => 0,
                'price' => 0,
                'createtime >' => strtotime("-{$retry['last']} minute"),
            ])->orderBy('id desc')->findOne();
            if ($order) {
                if (empty($retry['max']) || $order->getExtraData('retry.total', 0) < $retry['max']) {
                    $order_retry_url = Util::murl('order', ['op' => 'retry', 'device' => $device->getShadowId(), 'uid' => $order->getOrderNO()]);
                    $tpl['js']['code'] .= <<<JSCODE
\r\nzovye_fn.retryOrder = function (cb) {
    $.get("$order_retry_url").then(function (res) {
        if (typeof cb === 'function') {
            cb(res);
        }
    })
}
JSCODE;
                }
            }
        }

        if (App::isBalanceEnabled()) {
            $bonus_url = Util::murl('bonus');
            $user_data = [
                'status' => true,
                'data' => $user->profile(),
            ];
            $user_data['data']['balance'] = $user->getBalance()->total();
            $user_json_str = json_encode($user_data, JSON_HEX_TAG | JSON_HEX_QUOT);

            $tpl['js']['code'] .= <<<JSCODE
\r\nzovye_fn.redirectToBonusPage = function() {
    window.location.href = "$bonus_url";
}
zovye_fn.user = JSON.parse(`$user_json_str`);
zovye_fn.getUserInfo = function (cb) {
    if (typeof cb === 'function') {
        return cb(zovye_fn.user)
    }
    return new Promise((resolve, reject) => {
        resolve(zovye_fn.user);
    });
}
JSCODE;
        }

        $tpl['js']['code'] .= "\r\n</script>";

        if (App::isSQMPayEnabled()) {
            $js = htmlspecialchars_decode(SQM::getJs(), ENT_QUOTES);
            $tpl['js']['code'] .= "\r\n$js\r\n";
        }

        $this->showTemplate(Theme::file('device'), ['tpl' => $tpl]);
    }

    /**
     * 领取页面.
     *
     * @param array $params
     */
    public function getPage($params = [])
    {
        $tpl = is_array($params) ? $params : [];

        /** @var deviceModelObj $device */
        $device = $tpl['device']['_obj'];

        /** @var userModelObj $user */
        //$user = $tpl['user']['_obj'];

        if ($device) {
            //格式化广告
            $tpl['slides'] = [];
            $advs = $device->getAdvs(Advertising::GET_PAGE);
            if ($advs) {
                $url_params = [
                    'deviceid' => $tpl['device']['id'],
                    'accountid' => $tpl['account']['id'],
                ];
                foreach ($advs as $adv) {
                    if ($adv['extra']['images']) {
                        foreach ($adv['extra']['images'] as $image) {
                            if ($image) {
                                $url_params['advsid'] = $adv['id'];
                                $tpl['slides'][] = [
                                    'id' => intval($adv['id']),
                                    'name' => strval($adv['name']),
                                    'image' => strval(Util::toMedia($image)),
                                    'url' => Util::murl('advsStats', $url_params),
                                ];
                            }
                        }
                    }
                }
            }
        }

        $js_sdk = Util::fetchJSSDK();

        $get_x_url = Util::murl('getx', ['ticket' => $params['user']['ticket']]);
        $get_goods_list_url = Util::murl('goodslist', ['free' => true, 'ticket' => $params['user']['ticket']]);

        $jquery_url = JS_JQUERY_URL;

        $tpl['timeout'] = App::deviceWaitTimeout();
        $tpl['js']['code'] = <<<JSCODE
<script src="$jquery_url"></script>
$js_sdk
<script>
    wx.ready(function(){
        wx.hideAllNonBaseMenuItem();
    })
    const zovye_fn = {};
    zovye_fn.getx = function(fn) {
        $.getJSON("$get_x_url").then(function(res){
            if (res && res.status && res.data.msg) {
                if (typeof fn === 'function') {
                    fn(res);
                }
            }
        })
    }
    zovye_fn.getGoods = function(id, fn) {
        $.getJSON("$get_x_url", {goodsid: id}).then(function(res){
            if (typeof fn === 'function') {
                fn(res);
            }
        })
    }
    zovye_fn.getGoodsList = function(fn) {
        $.getJSON("$get_goods_list_url").then(function(res){
            if (typeof fn === 'function') {
                fn(res);
            }
        })
    }
</script>
JSCODE;
        $this->showTemplate(Theme::file('get'), ['tpl' => $tpl]);
    }


    /**
     * 代理商登记手机页面.
     *
     * @param array $params
     */
    public function mobilePage($params = [])
    {
        $tpl = is_array($params) ? $params : [];

        $js_sdk = Util::fetchJSSDK();

        $mobile_url = Util::murl('mobile');

        $we7_util_url = JS_WE7UTIL_URL;
        $jquery_url = JS_JQUERY_URL;

        $tpl['js']['code'] = <<<JSCODE
<script src="$we7_util_url"></script>
<script src="$jquery_url"></script>
$js_sdk
<script>
    wx.ready(function(){
        wx.hideAllNonBaseMenuItem();
    });

    const zovye_fn = {};
    zovye_fn.save = function(mobile, code, success, fail) {
        $.getJSON("$mobile_url", {op: 'save', mobile: mobile, code: code}, function(res){
            if (res) {
                if (res.status) {
                    if (typeof success === 'function') {
                        success(res.data);
                    } else {
                        if (res.data.msg) {
                            alert(res.data.msg);
                        }
                    }
                } else {
                    if (typeof fail === 'function') {
                        fail(res.data);
                    } else {
                        if (res.data.msg) {
                            alert(res.data.msg);
                        }
                    }
                }
            }
        })
    }
    
    zovye_fn.checkReferral = function(code, success, fail) {
        $.getJSON("$mobile_url", {op: 'check', code: code}, function(res){
            if (res) {
                if (res.status) {
                    if (typeof success === 'function') {
                        success(res.data);
                    } else {
                        if (res.data.msg) {
                            alert(res.data.msg);
                        }
                    }
                } else {
                    if (typeof fail === 'function') {
                        fail(res.data);
                    } else {
                        if (res.data.msg) {
                            alert(res.data.msg);
                        }
                    }
                }
            }
        })
    }

    zovye_fn.close = function() {
        wx && wx.closeWindow();
    }
</script>
JSCODE;
        $this->showTemplate(Theme::file('mobile'), ['tpl' => $tpl]);
    }

    public function keeperPage($params = [])
    {
        $tpl = is_array($params) ? $params : [];

        $js_sdk = Util::fetchJSSDK();

        $mobile_url = Util::murl('keeper', ['op' => 'save']);
        $we7_util_url = JS_WE7UTIL_URL;
        $jquery_url = JS_JQUERY_URL;

        $tpl['js']['code'] = <<<JSCODE
<script src="$we7_util_url"></script>
<script src="$jquery_url"></script>
$js_sdk
<script>
    wx.ready(function(){
        wx.hideAllNonBaseMenuItem();
    })

    const zovye_fn = {};
    zovye_fn.save = function(mobile, success, fail) {
        $.getJSON("$mobile_url", {mobile: mobile}, function(res){
            if (res) {
                if (res.status) {
                    if (typeof success == 'function') {
                        success(res.data);
                    } else {
                        if (res.data.msg) {
                            alert(res.data.msg);
                        }
                    }
                } else {
                    if (typeof fail == 'function') {
                        fail(res.data);
                    } else {
                        if (res.data.msg) {
                            alert(res.data.msg);
                        }
                    }
                }
            }
        })
    }

    zovye_fn.close = function() {
        wx && wx.closeWindow();
    }
</script>
JSCODE;
        $this->showTemplate(Theme::file('keeper'), ['tpl' => $tpl]);
    }

    /**
     * 获取用户定位页面.
     *
     * @param array $params
     */
    public function locationPage($params = [])
    {
        $tpl = is_array($params) ? $params : [];

        $js_sdk = Util::fetchJSSDK();

        $api_url = Util::murl('util', ['op' => 'location', 'id' => $tpl['device']['shadowId']]);

        $we7_util_url = JS_WE7UTIL_URL;
        $jquery_url = JS_JQUERY_URL;
        $lbs_key = settings('user.location.appkey', DEFAULT_LBS_KEY);

        $tpl['js']['code'] = <<<JSCODE
<script src="$we7_util_url"></script>
<script src="$jquery_url"></script>
<script src="https://mapapi.qq.com/web/mapComponents/geoLocation/v/geolocation.min.js"></script>
$js_sdk
<script>
    const zovye_fn = {
        cb: null,
        api_url: "$api_url",
        redirect_url: "{$tpl['redirect']}",
    }
    zovye_fn.check = function(cb) {
		const geolocation = new qq.maps.Geolocation("$lbs_key", "myapp");
		const options = {
			timeout: 8000,
		}
		geolocation.getLocation(
			function success(res) {
                $.getJSON(zovye_fn.api_url, {lng: res.lng, lat: res.lat}).then(function(res){
                    if (res.status) {
                        window.location.replace(zovye_fn.redirect_url);
                    }else{
                        if (typeof cb === 'function') {
                            cb(res.data && res.data.msg || '失败！');
                        }
                    }
                })
			},
			function error() {
				alert("定位失败，请检查是否开启定位功能！")
			}, options);
    }

    zovye_fn.close = function() {
        wx && wx.closeWindow();
    }

    zovye_fn.ready = function(fn) {
        zovye_fn.cb = fn;
    }

    wx.ready(function(){
        wx.hideAllNonBaseMenuItem();
        if (typeof zovye_fn.cb === 'function') {
            zovye_fn.cb();
        }
    })
</script>
JSCODE;

        $this->showTemplate(Theme::file('location'), ['tpl' => $tpl]);
    }

    /**
     * 更多关注页面.
     *
     * @param array $params
     */
    public function moreAccountsPage(array $params = [])
    {
        $tpl = is_array($params) ? $params : [];

        if ($tpl['device']['id']) {
            $device = Device::get($tpl['device']['id']);
            if ($device) {
                $tpl['slides'] = [];
                $advs = $device->getAdvs(Advertising::WELCOME_PAGE);
                foreach ($advs as $adv) {
                    if ($adv['extra']['images']) {
                        foreach ($adv['extra']['images'] as $image) {
                            if ($image) {
                                $tpl['slides'][] = [
                                    'id' => intval($adv['id']),
                                    'name' => strval($adv['name']),
                                    'image' => strval(Util::toMedia($image)),
                                    'link' => strval($adv['extra']['link']),
                                ];
                            }
                        }
                    }
                }
            }
        }

        $js_sdk = Util::fetchJSSDK();

        $api_url = Util::murl('util', ['op' => 'accounts', 'id' => $tpl['device']['shadowId']]);

        $we7_util_url = JS_WE7UTIL_URL;
        $jquery_url = JS_JQUERY_URL;

        $tpl['js']['code'] = <<<JSCODE
<script src="$we7_util_url"></script>
<script src="$jquery_url"></script>
$js_sdk
<script>
</script>
JSCODE;
        $this->showTemplate(Theme::file('accounts'), ['tpl' => $tpl, 'url' => $api_url]);
    }

    public function idCardPage($params = [])
    {
        $tpl = is_array($params) ? $params : [];

        $js_sdk = Util::fetchJSSDK();

        $api_url = Util::murl('idcard', ['tid' => $tpl['tid']]);
        $redirect_url = Util::murl('payresult', ['deviceid' => $tpl['deviceid'], 'orderid' => $tpl['tid']]);

        $we7_util_url = JS_WE7UTIL_URL;
        $jquery_url = JS_JQUERY_URL;

        $tpl['js']['code'] = <<<JSCODE
<script src="$we7_util_url"></script>
<script src="$jquery_url"></script>>
$js_sdk
<script>
    const zovye_fn = {
        api_url: "$api_url",
    }
    zovye_fn.verify = function(name, num) {
        $.getJSON(zovye_fn.api_url, {op: 'verify', name: name, num: num}).then(function(res){
            if (res.status) {
                alert(res.data.msg);
                window.location.replace("$redirect_url");
            } else {
                alert(res.data.msg)
            }
            
            if (res.data.code === 201) {
                zovye_fn.close();
            }
        })
    }
    zovye_fn.refund = function() {
        $.getJSON(zovye_fn.api_url, {op: 'refund'}).then(function(res){
            if (res.data && res.data.msg) {
                alert(res.data.msg);
            }
                       
            zovye_fn.close();
        })
    }    
    zovye_fn.close = function() {
        wx && wx.closeWindow();
    }
    wx.ready(function(){
        wx.hideAllNonBaseMenuItem();
    })
</script>
JSCODE;

        $this->showTemplate(Theme::file('idcard'), ['tpl' => $tpl]);
    }

    public function aliAuthPage($cb_url)
    {
        $app_id = settings('ali.appid');
        if (empty($app_id)) {
            Util::resultAlert('暂时不支持支付宝！', 'error');
        }

        $html = <<<HTML
<script src="https://gw.alipayobjects.com/as/g/h5-lib/alipayjsapi/3.1.1/alipayjsapi.min.js"></script>

<script>
function ready(callback) {
  if (window.AlipayJSBridge) {
    callback && callback();
  } else {
    document.addEventListener('AlipayJSBridgeReady', callback, false);
  }
}
ready(function(){
    ap.getAuthCode({
    appId: "$app_id",
    scopes: ['auth_user'],
  }, function(res){
        if (res['authCode']) {
            location.href = "$cb_url&auth_code="+res['authCode'];            
        }
    })
})
</script>
HTML;

        echo($html);
        exit();
    }

    public function douyinPage(deviceModelObj $device, userModelObj $user)
    {
        $api_url = Util::murl('douyin');
        $jquery_url = JS_JQUERY_URL;

        $tpl_data = Util::getTplData([$device, $user]);

        $tpl_data['js']['code'] = <<<JSCODE
<script src="$jquery_url"></script>
<script>
    const zovye_fn = {
        api_url: "$api_url",
    }
    zovye_fn.getAccounts = function() {
        return $.getJSON(zovye_fn.api_url, {op: 'account', 'device': '{$device->getShadowId()}', 'user': '{$user->getOpenid()}'});
    }
    zovye_fn.redirect = function(uid) {
        return $.getJSON(zovye_fn.api_url, {op: 'detail', 'uid': uid, 'device': '{$device->getShadowId()}', 'user': '{$user->getOpenid()}'});
    }
</script>
JSCODE;
        $this->showTemplate(Theme::file('douyin'), ['tpl' => $tpl_data]);
    }

    public function getBalanceBonusPage(userModelObj $user, accountModelObj $account)
    {
        $tpl_data = Util::getTplData([$user, $account]);

        $api_url = Util::murl('account');
        $jquery_url = JS_JQUERY_URL;

        $user_data = [
            'status' => true,
            'data' => $user->profile(),
        ];
        $user_data['data']['balance'] = $user->getBalance()->total();
        $user_json_str = json_encode($user_data, JSON_HEX_TAG | JSON_HEX_QUOT);

        $account_data = [
            'status' => true,
            'data' => $account->profile(),
        ];

        $account_data['data']['bonus'] = $account->getBalancePrice();
        $account_json_str = json_encode($account_data, JSON_HEX_TAG | JSON_HEX_QUOT);

        $js_sdk = Util::fetchJSSDK();

        $tpl_data['js']['code'] = <<<JSCODE
<script src="$jquery_url"></script>
$js_sdk
<script>
    wx.ready(function(){
        wx.hideAllNonBaseMenuItem();
    });

    const zovye_fn = {
        api_url: "$api_url",
        user: JSON.parse(`$user_json_str`),
        account: JSON.parse(`$account_json_str`),
    }
    zovye_fn.getAccountInfo = function (cb) {
        if (typeof cb === 'function') {
            return cb(zovye_fn.account)
        }
        return new Promise((resolve, reject) => {
            resolve(zovye_fn.account);
        });
    }
    zovye_fn.getUserInfo = function (cb) {
        if (typeof cb === 'function') {
            return cb(zovye_fn.user)
        }
        return new Promise((resolve, reject) => {
            resolve(zovye_fn.user);
        });
    }
JSCODE;

        $result = Util::checkBalanceAvailable($user, $account);
        if (is_error($result)) {
            $tpl_data['js']['code'] .= <<<JSCODE
        \r\nzovye_fn.isOk = function(cb) {
            const res = {
                status: false,
                data: {
                    msg: `{$result['message']}`,
                }
            }
            if (typeof cb === 'function') {
                return cb(res)
            }
            return new Promise((resolve, reject) => {
                resolve(res);
            });
        }
JSCODE;
        } else {
            $tpl_data['js']['code'] .= <<<JSCODE
        \r\nzovye_fn.isOk = function(cb) {
            const res = {
                status: true,
                data: {
                }
            }
            if (typeof cb === 'function') {
                return cb(res)
            }
            return new Promise((resolve, reject) => {
                resolve(res);
            });
        };
        zovye_fn.getBonus = function() {
            return $.getJSON(zovye_fn.api_url, {op: 'get_bonus', 'account': '{$account->getUid()}'});
        };
JSCODE;
        }
        $tpl_data['js']['code'] .= <<<JSCODE
\r\n</script>
JSCODE;

        $this->showTemplate(Theme::file('balance'), ['tpl' => $tpl_data]);
    }

    public function bonusPage(userModelObj $user)
    {
        $tpl_data = Util::getTplData([$user]);

        $user_data = [
            'status' => true,
            'data' => $user->profile(),
        ];
        $user_data['data']['balance'] = $user->getBalance()->total();
        $user_json_str = json_encode($user_data, JSON_HEX_TAG | JSON_HEX_QUOT);

        $api_url = Util::murl('bonus');
        $account_url = Util::murl('account');
        $adv_api_url = Util::murl('adv');
        $jquery_url = JS_JQUERY_URL;

        $js_sdk = Util::fetchJSSDK();

        $tpl_data['js']['code'] = <<<JSCODE
<script src="$jquery_url"></script>
$js_sdk
<script>
    wx.ready(function(){
        wx.hideAllNonBaseMenuItem();
    });

    const zovye_fn = {
        api_url: "$api_url",
        user: JSON.parse(`$user_json_str`),
    }
    zovye_fn.getUserInfo = function (cb) {
        if (typeof cb === 'function') {
            return cb(zovye_fn.user)
        }
        return new Promise((resolve, reject) => {
            resolve(zovye_fn.user);
        });
    }
    zovye_fn.getAdvs = function(typeid, num, cb) {
        const params = {num};
        if (typeof typeid == 'number') {
            params['typeid'] = typeid;
        } else {
            params['type'] = typeid;
        }
        $.get("$adv_api_url", params).then(function(res){
            if (res && res.status) {
                if (typeof cb === 'function') {
                    cb(res.data);
                } else {
                    console.log(res.data);
                }
            }
        })           
    }
    zovye_fn.getAccounts = function(type, max) {
        return $.getJSON(zovye_fn.api_url, {op: 'account', type, max});
    }
    zovye_fn.play = function(uid, seconds, cb) {
        $.get("$account_url", {op: 'play', uid, seconds}).then(function(res){
            if (cb) cb(res);
        })
    }
JSCODE;
    
    if (!$user->isSigned()) {
        $tpl_data['js']['code'] .= <<<JSCODE
    \r\nzovye_fn.signIn = function() {
        return $.getJSON(zovye_fn.api_url, {op: 'signIn'});
    }    
JSCODE;
    }

$tpl_data['js']['code'] .= <<<JSCODE
\r\n</script>
JSCODE;
        $this->showTemplate(Theme::file('bonus'), ['tpl' => $tpl_data]);
    }

}

