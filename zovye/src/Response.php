<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

class Response
{
    /**
     * 重定向客户端浏览器
     * @param string $url
     * @param array $params
     */
    public static function redirect(string $url, array $params = [])
    {
        if ($params) {
            $url = PlaceHolder::replace($url, $params);
        }

        header("Location:$url", true, 302);
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
    public static function itoast($msg, string $redirect = '', string $type = ''): void
    {
        We7::itoast($msg, $redirect, $type);
    }

    public static function data($data, $web = false)
    {
        if (Request::is_ajax()) {
            JSON::result($data);
        } else {
            if ($web) {
                self::itoast(
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
            $jssdk = Session::fetchJSSDK();
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
}