<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

/** @var string $cb_url */
$cb_url = Util::getTemplateVar('cb_url');

$app_id = settings('ali.appid');
if (empty($app_id)) {
    Response::alert('暂时不支持支付宝！', 'error');
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