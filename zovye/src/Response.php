<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

/**
 * @method static devicePreparePage(array $tpl_data)
 * @method static devicePage(array $tpl_data)
 * @method static getPage(array $tpl_data)
 * @method static jumpPage(array $tpl_data)
 * @method static getBalanceBonusPage(array $array)
 * @method static scanPage(array $tpl_data)
 * @method static locationPage(array $tpl_data)
 * @method static douyinPage(array $array)
 * @method static moreAccountsPage(array $tpl_data)
 * @method static fillQuestionnairePage(array $array)
 * @method static smsPromoPage(array $array)
 * @method static keeperPage(array[] $array)
 * @method static giftLogsPage(array $array)
 * @method static luckyLogsPage(array $array)
 * @method static flashEggPage(array $array)
 * @method static giftRegistryPage(array $array)
 * @method static giftDetailPage(array $array)
 * @method static aliAuthPage(array $array)
 * @method static bonusPage(array $array)
 * @method static userPage(array $array)
 * @method static userBalanceLogPage(array $array)
 * @method static idCardPage(array $array)
 * @method static mallPage(array $array)
 * @method static mallOrderPage(array $array)
 * @method static mobilePage(array[] $array)
 * @method static orderPage(array $array)
 * @method static feedbackPage()
 * @method static payResultPage(array $array)
 * @method static followPage(array $array)
 * @method static giftGoodsListPage(array $array)
 * @method static taskPage(array $array)
 * @method static userInfoPage(array $array)
 * @method static cztvPage(array $array)
 */
class Response
{
    public static function showTemplate(string $filename, $tpl_data = [], $is_theme_file = false)
    {
        app()->showTemplate($is_theme_file ? Theme::file($filename) : $filename, $tpl_data);
    }

    public static function templateJSON(string $filename, string $title = '', $tpl_data = [])
    {
        $content = app()->fetchTemplate($filename, $tpl_data);
        JSON::success(['title' => $title, 'content' => $content]);
    }

    public static function __callStatic($name, $arguments)
    {
        $names = explode('_', toSnakeCase($name));
        $last = array_pop($names);
        if ($last == 'page') {
            $v = implode('_', $names);
            $file = ZOVYE_SRC.'pages'.DIRECTORY_SEPARATOR.$v.'.php';
            if (is_file($file)) {
                $GLOBALS['_tpl_var_'] = $arguments;
                require $file;
            }
        }
    }

    /**
     * 重定向客户端浏览器
     * @param string $url
     * @param array $params
     */
    public static function redirect(string $url, array $params = [], $response_code = 302)
    {
        if ($params) {
            $url = PlaceHolder::replace($url, $params);
        }

        header("Location:$url", true, $response_code);
    }

    /**
     * @param $msg
     * @param string $redirect
     * @param string $type
     * @return never-return
     */
    public static function message($msg, string $redirect = '', string $type = ''): void
    {
        We7::message($msg, $redirect ? We7::referer() : $redirect, $type);
    }

    /**
     * @param $msg
     * @param string $redirect
     * @param string $type
     * @return never-return
     */
    public static function toast($msg, string $redirect = '', string $type = ''): void
    {
        We7::itoast($msg, $redirect, $type);
    }

    public static function data($data, $web = false)
    {
        if (Request::is_ajax()) {
            JSON::result($data);
        } else {
            if ($web) {
                self::toast(
                    is_string($data) ? $data : ($data['message'] ?? '未知消息'),
                    '',
                    is_error($data) ? 'error' : 'success'
                );
            } else {
                if (is_string($data)) {
                    self::alert($data);
                }
                if (is_error($data)) {
                    self::alert($data['message'], 'error');
                }
                self::alert(is_string($data) ? $data : ($data['message'] ?? '未知消息'));
            }
        }
    }

    /**
     * 返回JSON响应.
     *
     * @param bool $status 结果
     * @param mixed $data 数据
     * @return never-return
     */
    public static function json(bool $status, $data = [])
    {
        header('Content-type: application/json; charset='._W('charset'));

        if (Request::has('callback')) {
            echo Request::str('callback').'('.json_encode(['status' => $status, 'data' => $data]).')';
        } else {
            echo json_encode(['status' => $status, 'data' => $data]);
        }

        exit();
    }

    /**
     * 手机端显示错误信息.
     *
     * @param string $msg
     * @param string $type
     * @param string $redirect
     * @return never-return
     */
    public static function alert(string $msg, string $type = 'success', string $redirect = '')
    {
        if (in_array($type, ['ok', 'success'])) {
            $icon = 'success';
            $btn = 'success';
        } else {
            $icon = 'error';
            $btn = 'danger';
        }

        if (_W('container') == 'wechat') {
            $jssdk = Util::jssdk();
            $js = <<<JS1
$jssdk
<script type="text/javascript">
    const url = "$redirect";
    wx.ready(function(){
        wx.hideAllNonBaseMenuItem();
    });
    function xclose(){
        if(url) {
            location.href = url;
        }else{
            wx && wx.closeWindow();
        }
    }
</script>
JS1;
        } elseif (Session::isAliAppContainer()) {
            $js = <<<JS2
<script src="https://gw.alipayobjects.com/as/g/h5-lib/alipayjsapi/3.1.1/alipayjsapi.inc.min.js"></script>
<script>
const url = "$redirect";
function xclose(){
    if(url) {
        location.href = url;
    }else{
        ap && ap.exitApp();
    }
}
</script>
JS2;
        } else {
            $js = <<<JS3
<script type="text/javascript">
    const url = "$redirect";
    function xclose(){
        if(url) {
            location.href = url;
        }
    }
</script>
JS3;
        }

        $css_url = _W('siteroot').'app/resource/css/common.min.css?v=20160906';
        $content = <<<HTML_CONTENT
<!DOCTYPE html>
<html lang="zh-hans">
	<head>
		<meta charset="UTF-8">
		<title>提示</title>
		<meta name="viewport" content="width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=1,user-scalable=no" />
    	<meta name="format-detection" content="telephone=no, address=no">
    	<meta name="apple-mobile-web-app-capable" content="yes" /> <!-- apple devices fullscreen -->
    	<meta name="apple-touch-fullscreen" content="yes"/>
    	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
    	<link href="$css_url" rel="stylesheet">
	</head>
    <body>
        <div class="mui-content">
		    <div class="mui-content-padded">
	        <div class="mui-message">
    			<div class="mui-message-icon">
    				<span class="mui-msg-$icon"></span>
    			</div>
    			<h4 class="title">$msg</h4>
    			<div class="mui-button-area">
    				<button type="button" class="mui-btn mui-btn-$btn mui-btn-block" onClick="xclose()">确定</button>
    			</div>
		    </div>
		</div>

    $js
    </div>
    </body>
</html>
HTML_CONTENT;

        exit($content);
    }

    public static function snapshotJs($params = []): string
    {
        $gif_url = MODULE_URL."static/img/here.gif";
        $html = <<<HTML
        <div style="position: fixed;width: 100vw;height:100vh;z-index: 1000;background: rgba(0,0,0,0.7);left: 0;top: 0;bottom:0">
        <div style="flex-direction: column;display: flex;align-items: center;justify-content: center;width: 100%;height: 100%;color: #fff;font-size: large;">
            <div style="width: 80%;text-align: center;padding: 20px 20px;background: rgba(0,0,0,.5);">
            需要用户授权才能使用该功能，请点击右下角 <b style="color:#fc6;">“使用完整服务”</b>！</span>
            </div>
            <img src="$gif_url" style="width:60px;bottom: 10px;right: 40px;position: absolute;">
        </div>
        </div>
HTML;

        if (empty($params['op'])) {
            $params['op'] = 'snapshot';
        }
        if (empty($params['entry'])) {
            $params['entry'] = 'entry';
        }

        $snapshot_url = Util::murl('util', $params);

        return <<<JSCODE
\r\n
        <script>
            zovye_fn.snapshot = function() {
                $.get("$snapshot_url").then(res => {
                    if (res.status && res.data && res.data.redirect) {
                        window.location.href = res.data.redirect;
                    }
                });
            }
            $(`$html`).appendTo('body').click(function(){
                zovye_fn.snapshot();
            });        
    </script>
JSCODE;
    }

}