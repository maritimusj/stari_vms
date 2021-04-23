<?php

/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use Exception;
use we7\template;
use WeModuleSite;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

class Site extends WeModuleSite
{
    public function __call($name, $args)
    {
        $isWeb = stripos($name, 'doWeb') === 0;
        $isMobile = stripos($name, 'doMobile') === 0;

        if ($isWeb || $isMobile) {
            $dir = IA_ROOT . '/addons/' . $this->modulename . '/inc/';
            $fun = '';

            if ($isWeb) {
                $dir .= 'web/';
                $fun = strtolower(substr($name, 5));
            }

            if ($isMobile) {
                $dir .= 'mobile/';
                $fun = strtolower(substr($name, 8));
            }

            $file = $dir . $fun . '.inc.php';
            if (file_exists($file)) {
                require $file;
                exit();
            }

            if ($isWeb) {
                app()->doWeb($fun);
            }

            if ($isMobile) {
                app()->doMobile($fun);
            }
        }
    }

    protected function template($filename)
    {
        global $_W;
        if (defined('IN_SYS')) {
            $source = ZOVYE_ROOT . "template/{$filename}.html";
            $compile = ZOVYE_ROOT . "data/tpl/{$filename}.tpl.php";
        } else {
            $source = ZOVYE_ROOT . "template/mobile/{$filename}.html";
            $compile = ZOVYE_ROOT . "data/tpl/mobile/{$filename}.tpl.php";
        }

        if (!is_file($source)) {
            exit("Error: template source '{$filename}' is not exist!");
        }
        $paths = pathinfo($compile);
        $compile = str_replace($paths['filename'], $_W['uniacid'] . '_' . $paths['filename'], $compile);
        if (DEVELOPMENT || !is_file($compile) || filemtime($source) > filemtime($compile)) {
            template::compile($source, $compile, true);
        }

        return $compile;
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

        include $this->template($name);

        return ob_get_clean();
    }

    /**
     * @param $filename
     * @param array $tpl_data
     */
    protected function showTemplate($filename, array $tpl_data = [])
    {
        $tpl_data['_GPC'] = $GLOBALS['_GPC'];
        $tpl_data['_W'] = $GLOBALS['_W'];

        extract($tpl_data);

        include $this->template($filename);
        exit();
    }

    /**
     * 用户扫描设备页面.
     *
     * @param array $params
     */
    public function scanPage($params = [])
    {
        //以下为页面数据
        $tpl = is_array($params) ? $params : [];

        $token = Util::random(16);
        $redirect_url = Util::murl('entry', ['from' => 'device', 'device' => $token]);
        $js_sdk = Util::fetchJSSDK();
        $jquery_url = JS_JQUERY_URL;
        $tpl['js']['code'] = <<<JSCODE
<script src="{$jquery_url}"></script>
{$js_sdk}
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
                            window.location.replace("{$redirect_url}".replace("{$token}", id));                            
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
            if(!target.hasClass("disable") && target.data("isMoved") == 0) target.trigger("tap");
        })
    })
</script>
JSCODE;
        $this->showTemplate(Theme::file('scan'), ['tpl' => $tpl]);
    }

    /**
     * 设备状态准备页面
     */
    public function devicePreparePage($params = [])
    {
        $tpl = is_array($params) ? $params : [];

        /** @var deviceModelObj $device */
        $device = $tpl['device']['_obj'];

        /** @var userModelObj $user */
        $user = $tpl['user']['_obj'];

        $device_url = empty($params['redirect']) ? Util::murl('entry', ['device' => $device->getImei()]) : strval($params['redirect']);
        $device_api_url = Util::murl('device', ['id' => $device->getId()]);
        $jquery_url = JS_JQUERY_URL;

        $js_sdk = Util::fetchJSSDK();

        $tpl['js']['code'] .= <<<JSCODE
        <script src="{$jquery_url}"></script>
        {$js_sdk}
        <script>
        wx.ready(function(){
            wx.hideAllNonBaseMenuItem();
        })
        if (typeof zovye_fn === 'undefined') {
            zovye_fn = {};
        }
        zovye_fn.getDetail = function (cb) {
            $.get("{$device_api_url}", {op: 'detail'}).then(function (res) {
                if (typeof cb === 'function') {
                    cb(res);
                }
            })
        }
        zovye_fn.redirect = function() {
            window.location.replace("{$device_url}");
        }
JSCODE;
        //检查用户在该设备上最近失败的免费订单
//         $minutes = settings('order.retry.last', 0);
//         if ($minutes > 0) {
//             $order = Order::findOne([
//                 'openid' => $user->getOpenid(),
//                 'device_id' => $device->getId(),
//                 'result_code <>' => 0,
//                 'price' => 0,
//                 'balance' => 0,
//                 'createtime >' => strtotime("-{$minutes} minute"),
//             ]);
//             if ($order) {
//                 $order_retry_url = Util::murl('order', ['op' => 'retry', 'device' => $device->getShadowId(), 'uid' => $order->getOrderNO()]);
//                 $tpl['js']['code'] .= <<<JSCODE
//     \r\nzovye_fn.retryOrder = function (cb) {
//         $.get("{$order_retry_url}").then(function (res) {
//             if (typeof cb === 'function') {
//                 cb(res);
//             }
//         })
//     }
// JSCODE;
//             }
//         }
        $tpl['js']['code'] .= "\r\n</script>";
        $this->showTemplate(Theme::file('prepare'), ['tpl' => $tpl]);
    }


    /**
     * 设备页面，通常展示了可用的关注二维码列表和支付信息.
     * @param array $params
     * @throws Exception
     */
    public function devicePage($params = [])
    {
        $tpl = is_array($params) ? $params : [];
        $tpl['slides'] = [];

        /** @var deviceModelObj $device */
        $device = $tpl['device']['_obj'];

        /** @var userModelObj $user */
        $user = $tpl['user']['_obj'];
        

        if (!App::isAliUser()) {
            $tpl['accounts'] = Account::getAvailableList($device, $user, [
                'exclude' => $params['exclude'],
            ]);
            foreach ($tpl['accounts'] as $index => $account) {
                if (!empty($account['redirect_url'])) {
                    //链接转跳前，先判断设备是否在线
                    if ($device->isMcbOnline()) {
                        Util::redirect($account['redirect_url']);
                        exit('正在转跳...');
                    }
                    unset($tpl['accounts'][$index]);
                }
            }
        } else {
            $tpl['accounts'] = [];
        }

        //如果设置必须关注公众号以后才能购买商品
        if (Helper::MustFollowAccount($device)) {
            if ($tpl['from'] != 'account') {
                if (empty($tpl['accounts'])) {
                    $account = Account::getNext($device, $user->settings('accounts.last.uid', ''));
                    if ($account) {
                        $uid = $account['uid'];

                        Account::updateAuthAccountQRCode($account, $user, $device);
                        if ($account) {
                            $tpl['accounts'][] = $account;
                        }

                        $user->updateSettings('accounts.last', [
                            'uid' => $uid,
                            'time' => time(),
                        ]);
                    }
                }
            } else {
                $tpl = array_merge($tpl, $device->getGoodsList($user));
            }
        } else {
            $tpl = array_merge($tpl, $device->getGoodsList($user));
        }

        //如果无法领取，则清除访问记录
        if (empty($tpl['accounts']) && empty($tpl['purchase'])) {
            $user->remove('last');
        }

        //如果只有一个货道，并且商品数量不足，则无法免费领取
        if ($device->getCargoLanesNum() == 1) {
            $goods = $device->getGoodsByLane(0);
            if (empty($goods) || $goods['num'] < 1) {
                $tpl['accounts'] = [];
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
        $tpl['js']['code'] = $pay_js;
        $tpl['js']['code'] .= <<<JSCODE
<script>
    if (typeof zovye_fn === 'undefined') {
        zovye_fn = {};
    }
    zovye_fn.closeWindow = function () {
        wx && wx.ready(function() {
            wx.closeWindow();
       })
    }
    zovye_fn.getAdvs = function(typeid, num, cb) {
        $.get("{$adv_api_url}", {typeid, num}).then(function(res){
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
        $.get("{$account_url}", {op:'play', uid, seconds, device:'{$device_imei}'}).then(function(res){
            if (res && res.status) {
                if (typeof cb === 'function') {
                    cb(res.data);
                } else {
                    console.log(res.data);
                }
            }
        })    
    }
    zovye_fn.redirectToOrder = function() {
        window.location.href= "{$order_jump_url}";
    }
    zovye_fn.redirectToFeedBack = function() {
        window.location.href= "{$feedback_url}&mobile={$mobile}&device_name={$device_name}&device_imei={$device_imei}";
    }
    zovye_fn.getDetail = function (cb) {
        $.get("{$device_api_url}", {op: 'detail'}).then(function (res) {
            if (typeof cb === 'function') {
                cb(res);
            }
        })
    }
JSCODE;
        if (!App::isAliUser() && App::isChannelPayEnabled()) {
            $pay_url = Util::murl('channel', ['device' => $device->getShadowId()]);
            $tpl['js']['code'] .= <<<JSCODE
\r\nzovye_fn.channelPay = function(goods, num, cb) {
    $.get("{$pay_url}", {goods, num}).then(function(res){
        if (typeof cb === 'function') {
            cb(res);
        }
    });
}
JSCODE;
        }
        //检查用户在该设备上最近失败的免费订单
        $minutes = settings('order.retry.last', 0);
        if ($minutes > 0) {
            $order = Order::findOne([
                'openid' => $user->getOpenid(),
                'device_id' => $device->getId(),
                'result_code <>' => 0,
                'price' => 0,
                'balance' => 0,
                'createtime >' => strtotime("-{$minutes} minute"),
            ]);
            if ($order) {
                $order_retry_url = Util::murl('order', ['op' => 'retry', 'device' => $device->getShadowId(), 'uid' => $order->getOrderNO()]);
                $tpl['js']['code'] .= <<<JSCODE
    \r\nzovye_fn.retryOrder = function (cb) {
        $.get("{$order_retry_url}").then(function (res) {
            if (typeof cb === 'function') {
                cb(res);
            }
        })
    }
JSCODE;
            }
        }

        $tpl['js']['code'] .= "\r\n</script>";
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
        $user = $tpl['user']['_obj'];

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
        $user_center_url = Util::murl('usercenter');

        $jquery_url = JS_JQUERY_URL;

        $tpl['timeout'] = App::deviceWaitTimeout();
        $tpl['js']['code'] = <<<JSCODE
<script src="{$jquery_url}"></script>
{$js_sdk}
<script>
    wx.ready(function(){
        wx.hideAllNonBaseMenuItem();
    })
    const zovye_fn = {};
    zovye_fn.usercenter = function() {
        window.location.replace("{$user_center_url}");
    }
    zovye_fn.getx = function(fn) {
        $.getJSON("{$get_x_url}").then(function(res){
            if (res && res.status && res.data.msg) {
                if (typeof fn === 'function') {
                    fn(res);
                }
            }
        })
    }
    zovye_fn.getGoods = function(id, fn) {
        $.getJSON("{$get_x_url}", {goodsid: id}).then(function(res){
            if (typeof fn === 'function') {
                fn(res);
            }
        })
    }
    zovye_fn.getGoodsList = function(fn) {
        $.getJSON("{$get_goods_list_url}").then(function(res){
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
     * 用户中心页面.
     *
     * @param array $params
     */
    public function userCenterPage($params = [])
    {
        $tpl = is_array($params) ? $params : [];

        $js_sdk = Util::fetchJSSDK();

        $get_prize_url = Util::murl('prize');
        $my_prizes_url = Util::murl('myprizes');
        $charge_url = Util::murl('charge');

        $jquery_url = JS_JQUERY_URL;

        $tpl['js']['code'] = <<<JSCODE
<script src="{$jquery_url}"></script>
{$js_sdk}
<script>
    wx.ready(function(){
        wx.hideAllNonBaseMenuItem();
    })
    const zovye_fn = {};
    zovye_fn.scan = function(){
        wx && wx.scanQRCode();
    }
    zovye_fn.getPrize = function(fn){
        $.getJSON("{$get_prize_url}").then(function(res){
            if(res && res.data){
                typeof fn == 'function' ? fn(res) : alert(res.data.msg);
            }
        })
    }
    zovye_fn.charge = function() {
        window.location.href = "{$charge_url}";
    }
    zovye_fn.myPrizes = function() {
        window.location.href = "{$my_prizes_url}";
    }
</script>
JSCODE;
        $this->showTemplate(Theme::file('usercenter'), ['tpl' => $tpl]);
    }

    /**
     * 我的奖品页面.
     *
     * @param array $params
     */
    public function myPrizesPage($params = [])
    {
        $tpl = is_array($params) ? $params : [];

        $js_sdk = Util::fetchJSSDK();

        $jquery_url = JS_JQUERY_URL;

        $tpl['js']['code'] = <<<JSCODE
<script src="{$jquery_url}"></script>
{$js_sdk}
<script>
    wx.ready(function(){
        wx.hideAllNonBaseMenuItem();
    })

    const zovye_fn = {};

</script>
JSCODE;

        $this->showTemplate(Theme::file('myprizes'), ['tpl' => $tpl]);
    }

    /**
     * 用户充值页面.
     *
     * @param array $params
     */
    public function chargePage($params = [])
    {
        $tpl = is_array($params) ? $params : [];

        $js_sdk = Util::fetchJSSDK();

        $get_order_url = Util::murl('order');
        $user_center_url = Util::murl('usercenter');

        $we7_util_url = JS_WE7UTIL_URL;
        $jquery_url = JS_JQUERY_URL;
        $mui_url = JS_MUI_URL;

        $tpl['js']['code'] = <<<JSCODE
<script src="{$we7_util_url}"></script>
<script src="{$jquery_url}"></script>
<script src="{$mui_url}"></script>
{$js_sdk}
<script>
    wx.ready(function(){
        wx.hideAllNonBaseMenuItem();
    })

    const zovye_fn = {};
    zovye_fn.charge = function(amount, coupon, success_cb, fail_cb) {
        $.getJSON("{$get_order_url}", {op: 'create', balance: amount, coupon: coupon}).then(function(res){
            if(res && res.status) {
                const data = res.data;
                if(data) {
    				util.pay({
    					orderFee: data.fee,
    					payMethod: "wechat",
    					orderTitle: data.title,
    					orderTid: data.orderNO,
    					module:  "{$tpl['module']}",
    					success: function(result) {
    					    if(typeof success_cb == 'function') {
    					        success_cb(result);
    					    }else{
        					    alert('支付成功！');
        						window.location.replace("{$user_center_url}");
    					    }

    					},
                        fail: function(result) {
                            $.get("{$get_order_url}", {op: 'cancel', tid: data.tid}, function(){
                            });
                            if(typeof fail_cb == 'function') {
                                fail_cb();
                            }else{
                                mui.toast('支付失败 : ' + (result.message || '未知'));
                            }
                        },
    					complete: function(result) {
    						//window.location.reload();
    					}
    				})
                }
            }
            if(res && res.data.msg) {
                if(typeof fail_cb == 'function') {
                    fail_cb();
                }else{
                    alert(res.data.msg);
                }
            }
        })
    }
</script>
JSCODE;
        $this->showTemplate(Theme::file('charge'), ['tpl' => $tpl]);
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
<script src="{$we7_util_url}"></script>
<script src="{$jquery_url}"></script>
{$js_sdk}
<script>
    wx.ready(function(){
        wx.hideAllNonBaseMenuItem();
    });

    const zovye_fn = {};
    zovye_fn.save = function(mobile, code, success, fail) {
        $.getJSON("{$mobile_url}", {op: 'save', mobile: mobile, code: code}, function(res){
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
        $.getJSON("{$mobile_url}", {op: 'check', code: code}, function(res){
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
<script src="{$we7_util_url}"></script>
<script src="{$jquery_url}"></script>
{$js_sdk}
<script>
    wx.ready(function(){
        wx.hideAllNonBaseMenuItem();
    })

    const zovye_fn = {};
    zovye_fn.save = function(mobile, success, fail) {
        $.getJSON("{$mobile_url}", {mobile: mobile}, function(res){
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
<script src="{$we7_util_url}"></script>
<script src="{$jquery_url}"></script>
<script src="https://mapapi.qq.com/web/mapComponents/geoLocation/v/geolocation.min.js"></script>
{$js_sdk}
<script>
    const zovye_fn = {
        cb: null,
        api_url: "{$api_url}",
        redirect_url: "{$tpl['redirect']}",
    }
    zovye_fn.check = function(cb) {
		const geolocation = new qq.maps.Geolocation("{$lbs_key}", "myapp");
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
			function error(res) {
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
    public function moreAccountsPage($params = [])
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
<script src="{$we7_util_url}"></script>
<script src="{$jquery_url}"></script>
{$js_sdk}
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
<script src="{$we7_util_url}"></script>
<script src="{$jquery_url}"></script>>
{$js_sdk}
<script>
    const zovye_fn = {
        api_url: "{$api_url}",
    }
    zovye_fn.verify = function(name, num) {
        $.getJSON(zovye_fn.api_url, {op: 'verify', name: name, num: num}).then(function(res){
            if (res.status) {
                alert(res.data.msg);
                window.location.replace("{$redirect_url}");
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
    appId: "{$app_id}",
    scopes: ['auth_user'],
  }, function(res){
        if (res['authCode']) {
            location.href = "{$cb_url}&auth_code="+res['authCode'];            
        }
    })
})
</script>
HTML;

        echo($html);
        exit();
    }
}
