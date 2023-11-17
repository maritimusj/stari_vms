<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\util;

use WeChatPay\Builder;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Util\PemUtil;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\WxPayV3Client;

require_once MODULE_ROOT.'vendor/autoload.php';

class WxPayUtil
{
    public static function getV3Client(array $config): WxPayV3Client
    {
        // 从「微信支付平台证书」中获取「证书序列号」
        $serial = PemUtil::parseCertificateSerialNo($config['pem']['cert']);

        // 构造一个 APIv3 客户端实例
        $instance = Builder::factory([
            'mchid' => $config['mch_id'],         // 商户号
            'serial' => $config['serial'],        // 「商户API证书」的「证书序列号」
            'privateKey' => Rsa::from($config['pem']['key']),
            'certs' => [
                $serial => Rsa::from($config['pem']['cert'], Rsa::KEY_TYPE_PUBLIC),
            ],
        ]);

        return new WxPayV3Client($instance);
    }

    /**
     * 获取页面支付时需要调用的js代码
     */
    public static function getPayJs(deviceModelObj $device, userModelObj $user): string
    {
        $device_uid = $device->getImei();

        $js_sdk = Util::jssdk();
        $jquery_url = JS_JQUERY_URL;
        $order_api_url = Util::murl('order', ['deviceUID' => $device_uid]);
        $pay_result_url = Util::murl('payresult', ['orderNO' => '__orderNO__', 'deviceid' => $device->getId()]);
        $pay_fail_url = Util::murl('payfailed', ['msg' => '__msg__']);

        return <<<JS_CODE
<script src="$jquery_url"></script>
$js_sdk
<script>
    wx.ready(function(){
        wx.hideAllNonBaseMenuItem();
    });
    
    const zovye_fn = {};
    zovye_fn.redirectToGetPayResultPage = function(orderNO, msg) {
        let api_url = "$pay_result_url".replace("__orderNO__", orderNO);
        api_url = api_url.replace("__msg__", encodeURIComponent(msg));
        window.location.replace(api_url);
    }
    
    zovye_fn.redirectToPayFailedPage = function(msg) {
        //window.location.replace("$pay_fail_url".replace('__msg__', encodeURIComponent(msg)));
        alert(msg);
    }
    
    zovye_fn.pay = function(res) {
        return new Promise(function(resolve, reject) {
            if (!res) {
                return reject('请求失败！');
            }
            if (!res.status) {
                if (res.message) {
                    return reject(res.message);
                }
                if(res.data && res.data.msg) {                   
                    return reject(res.data.msg);
                }        
                return reject("请求失败！");
            } 
            
            const data = res.data;
            WeixinJSBridge.invoke('getBrandWCPayRequest', {
                "appId": data.appId,
                "timeStamp": data.timeStamp,
                "nonceStr": data.nonceStr,
                "package": data.package,
                "signType": data.signType,
                "paySign": data.paySign,
            }, function (res) {
                if (res.err_msg === "get_brand_wcpay_request:ok") {
                    $.get("$order_api_url", { op: "finished", orderNO: data.orderNO });
                    resolve(data.orderNO, data.msg || "");
                } else if (res.err_msg === 'get_brand_wcpay_request:cancel') {
                    $.get("$order_api_url", { op: "cancel", orderNO: data.orderNO });
                    reject("支付取消!");
                } else {
                    $.get("$order_api_url", { op: "cancel", orderNO: data.orderNO });
                    reject("支付失败!");
                }
            })
        });
    }
    
    zovye_fn.goods_wxpay = function(params, successFN, failFN) {
        return new Promise(function(resolve, reject) {
            const goodsID = typeof params === 'object' && params.goodsID !== undefined ? params.goodsID : params;
            const total = typeof params === 'object' && params.total !== undefined ? params.total : 1;
            $.get("$order_api_url", {op: "create", goodsID: goodsID, total: total}).then(function(res) {
              zovye_fn.pay(res).then(function(orderNO, msg) {
                  if (typeof successFN !== 'function' || !successFN(orderNO)) {
                    zovye_fn.redirectToGetPayResultPage(orderNO, msg);
                  }
                  resolve(orderNO, msg)
              }).catch(function(msg) {
                  if (typeof failFN !== 'function' || !failFN(msg)) {                  
                    zovye_fn.redirectToPayFailedPage(msg);
                  }
                  reject(msg);
              });
          });
        }).catch((e)=>{
            alert(e);
        });
    }
    zovye_fn.package_pay = function(packageID, successFN, failFN) {
        return new Promise(function(resolve, reject) {
            $.get("$order_api_url", {op: "create", packageID: packageID}).then(function(res) {
              zovye_fn.pay(res).then(function(orderNO, msg) {
                  if (typeof successFN !== 'function' || !successFN(orderNO)) {
                    zovye_fn.redirectToGetPayResultPage(orderNO, msg);
                  }
                  resolve(orderNO, msg)
              }).catch(function(msg) {
                  if (typeof failFN !== 'function' || !failFN(msg)) {                  
                    zovye_fn.redirectToPayFailedPage(msg);
                  }
                  reject(msg);
              });
          });
        }).catch((e)=>{
            alert(e);
        });
    }
</script>
JS_CODE;
    }
}