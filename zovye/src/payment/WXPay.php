<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\payment;

use wx\pay;
use zovye\contract\IPay;
use zovye\Log;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\util\Util;
use zovye\We7;
use function zovye\_W;
use function zovye\is_error;

class WXPay implements IPay
{
    private $config = [];

    public function getName(): string
    {
        return empty($this->config['name']) ? \zovye\Pay::WX : $this->config['name'];
    }

    public function setConfig(array $config = [])
    {
        $this->config = $config;
    }

    private function getWx(): pay
    {
        return new pay([
            'appid' => $this->config['appid'],
            'mch_id' => $this->config['mch_id'],
            'key' => $this->config['key'],
            'pem' => $this->config['pem'],
        ]);
    }

    protected function getNotifyUrl(): string
    {
        $notify_url = _W('siteroot');
        $path = 'addons/'.APP_NAME.'/';

        if (mb_strpos($notify_url, $path) === false) {
            $notify_url .= $path;
        }

        $notify_url .= 'payment/wx.php';

        return $notify_url;
    }

    public function createQrcodePay(
        string $code,
        string $device_uid,
        string $order_no,
        int $price,
        string $body = ''
    ) {
        $wx = $this->getWx();

        $params = [
            'device_info' => $device_uid,
            'out_trade_no' => $order_no,
            'auth_code' => $code,
            'body' => $body,
            'total_fee' => $price,
        ];

        $res = $wx->buildQrcodePay($params);

        Log::debug('qr_pay', [
            'params' => $params,
            'res' => $res,
        ]);

        return $res;
    }

    public function createXAppPay(
        string $user_uid,
        string $device_uid,
        string $order_no,
        int $price,
        string $body = ''
    ): array {
        //小程序支付，使用小程序appid
        $this->config['appid'] = $this->config['wxappid'];

        return $this->createJsPay($user_uid, $device_uid, $order_no, $price, $body);
    }

    /**
     * 创建一个支付订单，并返回支付数据给前端js
     * @param string $user_uid
     * @param string $device_uid
     * @param string $order_no
     * @param int $price
     * @param string $body
     * @return mixed
     */
    public function createJsPay(
        string $user_uid,
        string $device_uid,
        string $order_no,
        int $price,
        string $body = ''
    ): array {
        $wx = $this->getWx();

        $params = [
            'device_info' => $device_uid,
            'out_trade_no' => $order_no,
            'trade_type' => 'JSAPI',
            'openid' => $user_uid,
            'body' => $body,
            'total_fee' => $price,
            'notify_url' => $this->getNotifyUrl(),
        ];

        $res = $wx->buildUnifiedOrder($params);

        Log::debug('js_pay', [
            'params' => $params,
            'res' => $res,
        ]);

        if (is_error($res)) {
            return $res;
        }

        $data = [
            'appId' => $res['appid'],
            'timeStamp' => time().'',
            'nonceStr' => $res['nonce_str'],
            'package' => 'prepay_id='.$res['prepay_id'],
            'signType' => 'MD5',
        ];

        $data['paySign'] = $wx->buildSign($data);

        return $data;
    }

    /**
     * 获取页面支付时需要调用的js代码
     * @param deviceModelObj $device
     * @param userModelObj $user
     * @return string
     */
    public function getPayJs(deviceModelObj $device, userModelObj $user): string
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

    public function query(string $order_no)
    {
        $wx = $this->getWx();

        $res = $wx->queryOrder($order_no);
        if (is_error($res)) {
            return $res;
        }

        return [
            'result' => 'success',
            'type' => $this->getName(),
            'merchant_no' => $this->config['mch_id'],
            'orderNO' => $res['out_trade_no'],
            'transaction_id' => $res['transaction_id'],
            'total' => $res['total_fee'],
            'paytime' => $res['time_end'],
            'openid' => $res['openid'],
            'deviceUID' => $res['device_info'],
        ];
    }

    public function close(string $order_no)
    {
        $wx = $this->getWx();

        return $wx->close($order_no);
    }

    public function refund(string $order_no, int $total, bool $is_transaction_id = false)
    {
        $wx = $this->getWx();

        $res = $this->query($order_no);
        if (is_error($res)) {
            return $res;
        }

        return $wx->refund($order_no, intval($res['total']), $total, $is_transaction_id);
    }

    public function decodeData(string $input): array
    {
        $data = We7::xml2array($input);

        return [
            'type' => 'wx',
            'deviceUID' => $data['device_info'],
            'orderNO' => $data['out_trade_no'],
            'total' => $data['total_fee'],
            'transaction_id' => $data['transaction_id'],
            'raw' => $data,
        ];
    }

    /**
     * 检验支付回调数据
     * @param array $data
     * @return bool
     */
    public function checkResult(array $data = []): bool
    {
        if ($data['return_code'] != 'SUCCESS' || $data['result_code'] != 'SUCCESS') {
            return false;
        }

        $wx = $this->getWx();

        return $wx->buildSign($data) === $data['sign'];
    }

    public function getResponse(bool $ok = true): string
    {
        $result = array(
            'return_code' => $ok ? 'SUCCESS' : 'FAIL',
            'return_msg' => $ok ? 'OK' : '',
        );

        return We7::array2xml($result);
    }
}
