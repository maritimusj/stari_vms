<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\payment;

use lcsw\pay;
use zovye\App;
use zovye\Contract\IPay;
use zovye\Log;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\Session;
use zovye\Util;
use function zovye\_W;
use function zovye\err;
use function zovye\is_error;

class LCSWPay implements IPay
{
    private $config = [];

    const RESPONSE_OK = '{"return_code":"01","return_msg":"SUCCESS"}';

    public function getName(): string
    {
        return \zovye\Pay::LCSW;
    }

    public function setConfig(array $config = [])
    {
        $this->config = $config;
    }

    private function getLCSW(): pay
    {
        return new pay([
            'merchant_no' => $this->config['merchant_no'],
            'terminal_id' => $this->config['terminal_id'],
            'access_token' => $this->config['access_token'],
        ]);
    }

    protected function getNotifyUrl(): string
    {
        $url = _W('siteroot');
        $path = 'addons/'.APP_NAME.'/';

        if (mb_strpos($url, $path) === false) {
            $url .= $path;
        }

        $url .= 'payment/lcsw.php';

        return $url;
    }

    protected function createPay(
        callable $fn,
        string $user_uid,
        string $device_uid,
        string $order_no,
        int $price,
        string $body = ''
    ): array {
        $lcsw = $this->getLCSW();

        $params = [
            'userUID' => $user_uid,
            'deviceUID' => $device_uid,
            'orderNO' => $order_no,
            'price' => $price,
            'body' => $body,
            'notify_url' => $this->getNotifyUrl(),
        ];

        $res = $fn($lcsw, $params);

        Log::debug('lcsw_xapppay', [
            'params' => $params,
            'res' => $res,
        ]);

        if (is_error($res)) {
            return $res;
        }

        if (App::isAliUser()) {
            return [
                'orderNO' => $order_no,
                'tradeNO' => $res['ali_trade_no'],
                'tradeNo' => $res['ali_trade_no'],
            ];
        }

        return [
            'appId' => $res['appId'],
            'timeStamp' => $res['timeStamp'],
            'nonceStr' => $res['nonceStr'],
            'package' => $res['package_str'],
            'signType' => $res['signType'],
            'paySign' => $res['paySign'],
        ];
    }

    public function createQrcodePay(string $code,
        string $device_uid,
        string $order_no,
        int $price,
        string $body = '')
    {
        $lcsw = $this->getLCSW();

        $params = [
            'code' => $code,
            'deviceUID' => $device_uid,
            'orderNO' => $order_no,
            'price' => $price,
            'body' => $body,
            'notify_url' => $this->getNotifyUrl(),
        ];

        return $lcsw->qrpay($params);
    }

    /**
     * @param string $user_uid
     * @param string $device_uid
     * @param string $order_no
     * @param int $price
     * @param string $body
     * @return mixed
     */
    public function createXAppPay(
        string $user_uid,
        string $device_uid,
        string $order_no,
        int $price,
        string $body = ''
    ): array {
        return $this->createPay(function ($lcsw, $params) {
            return $lcsw->xAppPay($params);
        }, $user_uid, $device_uid, $order_no, $price, $body);
    }

    /**
     * @param string $user_uid
     * @param string $device_uid
     * @param string $order_no
     * @param int $price
     * @param string $body
     * @param array $goodsDetail
     * @return mixed
     */
    public function createJsPay(
        string $user_uid,
        string $device_uid,
        string $order_no,
        int $price,
        string $body = '',
        array $goodsDetail = []
    ): array {
        return $this->createPay(function ($lcsw, $params) use ($goodsDetail) {
            if ($goodsDetail) {
                $params['goods_detail'] = json_encode($goodsDetail);
            }

            return $lcsw->Jspay($params);
        }, $user_uid, $device_uid, $order_no, $price, $body);
    }

    public function getPayJs(deviceModelObj $device, userModelObj $user): string
    {
        $params = [
            'JQueryURL' => JS_JQUERY_URL,
            'orderAPIURL' => Util::murl('order', ['deviceUID' => $device->getImei()]),
            'payResultURL' => Util::murl('payresult', ['orderNO' => '__orderNO__', 'deviceid' => $device->getId()]),
            'payFailed' => Util::murl('payfailed', ['msg' => '__msg__']),
        ];

        if (App::isAliUser()) {
            return $this->getAliPayJs($params);
        } else {
            return $this->getWxPayJs($params);
        }
    }

    protected function getAliPayJs(array $params = []): string
    {
        return <<<ALI_JSCODE
<script src="{$params['JQueryURL']}"></script>
<script src="https://gw.alipayobjects.com/as/g/h5-lib/alipayjsapi/3.1.1/alipayjsapi.inc.min.js"></script>
<script>
    const zovye_fn = {};
    zovye_fn.redirectToGetPayResultPage = function(orderNO, msg) {
        let api_url = "{$params['payResultURL']}".replace("__orderNO__", orderNO);
        api_url = api_url.replace("__msg__", encodeURIComponent(msg));
        window.location.replace(api_url);
    }
    
    zovye_fn.redirectToPayFailedPage = function(msg) {
        //window.location.replace("{$params['payFailed']}".replace('__msg__', encodeURIComponent(msg)));
        alert(msg);
    }

    zovye_fn.pay = function (res) {
        return new Promise(function(resolve, reject) {
           if (!res) {
                return reject("请求失败！");
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
            ap.tradePay({tradeNO: data.tradeNo}, function (res) {
                if (parseInt(res.resultCode) === 9000) {
                    $.get("{$params['orderAPIURL']}", { op: "finished", orderNO: data.orderNO });
                    resolve(data.orderNO, data.msg || "");
                } else if (parseInt(res.resultCode) === 6001) {
                    $.get("{$params['orderAPIURL']}", { op: "cancel", orderNO: data.orderNO });
                    reject("支付取消!");
                } else {
                    $.get("{$params['orderAPIURL']}", { op: "cancel", orderNO: data.orderNO });
                    reject("支付失败!");
                }
            });
        });
    }
    zovye_fn.goods_wxpay = function(params, successFN, failFN) {
      return new Promise(function(resolve, reject) {
        const goodsID = typeof params === 'object' && params.goodsID !== undefined ? params.goodsID : params;
        const total = typeof params === 'object' && params.total !== undefined ? params.total : 1;
          $.get("{$params['orderAPIURL']}", {op: "create", goodsID: goodsID, total: total}).then(function(res) {
              zovye_fn.pay(res).then(function(orderNO, msg) {
                  if (typeof successFN !== 'function' || !successFN(orderNO)) {
                    zovye_fn.redirectToGetPayResultPage(orderNO, msg);
                  }
                  resolve(orderNO, msg);
              }).catch(function(msg) {
                  if (typeof failFN !== 'function' || !failFN(msg)) {
                    zovye_fn.redirectToPayFailedPage(msg);
                  }
                  reject(msg);
              });
          });  
      });
    }
    zovye_fn.package_pay = function(packageID, successFN, failFN) {
      return new Promise(function(resolve, reject) {
          $.get("{$params['orderAPIURL']}", {op: "create", packageID: packageID}).then(function(res) {
              zovye_fn.pay(res).then(function(orderNO, msg) {
                  if (typeof successFN !== 'function' || !successFN(orderNO)) {
                    zovye_fn.redirectToGetPayResultPage(orderNO, msg);
                  }
                  resolve(orderNO, msg);
              }).catch(function(msg) {
                  if (typeof failFN !== 'function' || !failFN(msg)) {
                    zovye_fn.redirectToPayFailedPage(msg);
                  }
                  reject(msg);
              });
          });  
      });
    }
</script>
ALI_JSCODE;
    }

    protected function getWxPayJs(array $params = []): string
    {
        $js_sdk = Session::fetchJSSDK();

        return <<<JSCODE
<script src="{$params['JQueryURL']}"></script>
{$js_sdk}
<script>
    wx.ready(function() {
        wx.hideAllNonBaseMenuItem();
    });
    
    const zovye_fn = {};
    zovye_fn.redirectToGetPayResultPage = function(orderNO, msg) {
        let api_url = "{$params['payResultURL']}".replace("__orderNO__", orderNO);
        api_url = api_url.replace("__msg__", encodeURIComponent(msg))
        window.location.replace(api_url);
    }
    
    zovye_fn.redirectToPayFailedPage = function(msg) {
        //window.location.replace("{$params['payFailed']}".replace('__msg__', encodeURIComponent(msg)));
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
                    $.get("{$params['orderAPIURL']}", { op: "finished", orderNO: data.orderNO });
                    resolve(data.orderNO, data.msg || "");
                } else if (res.err_msg === 'get_brand_wcpay_request:cancel') {
                    $.get("{$params['orderAPIURL']}", { op: "cancel", orderNO: data.orderNO });
                    reject("支付取消!");
                } else {
                    $.get("{$params['orderAPIURL']}", { op: "cancel", orderNO: data.orderNO });
                    reject("支付失败!");
                }
            });
        });
    }
    
    zovye_fn.goods_wxpay = function(params, successFN, failFN) {
      return new Promise(function(resolve, reject) {
          const goodsID = typeof params === 'object' && params.goodsID !== undefined ? params.goodsID : params;
          const total = typeof params === 'object' && params.total !== undefined ? params.total : 1;
          $.get("{$params['orderAPIURL']}", {op: "create", goodsID: goodsID, total: total}).then(function(res) {
              zovye_fn.pay(res).then(function(orderNO, msg) {
                  if (typeof successFN !== 'function' || !successFN(orderNO)) {
                      zovye_fn.redirectToGetPayResultPage(orderNO, msg);
                  }
                  resolve(orderNO, msg);
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
JSCODE;
    }

    public function refund(string $order_no, int $total, bool $is_transaction_id = false)
    {
        $res = $this->query($order_no);
        if (is_error($res)) {
            return $res;
        }

        if ($total < 1 || $total > $res['total']) {
            return err('退款金额不正确！');
        }

        $lcsw = $this->getLCSW();
        $res = $lcsw->doRefund($res['transaction_id'], $total, $res['pay_type']);

        if (is_error($res)) {
            return $res;
        }

        if ($res['result_code'] !== '01') {
            return err($res['return_msg']);
        }

        return $res;
    }

    public function query(string $order_no)
    {
        $lcsw = $this->getLCSW();
        $res = $lcsw->queryOrder($order_no);
        if (is_error($res)) {
            return $res;
        }

        return [
            'result' => 'success',
            'type' => $this->getName(),
            'pay_type' => $res['pay_type'],
            'merchant_no' => $res['merchant_no'],
            'orderNO' => $res['pay_trace'],
            'transaction_id' => $res['out_trade_no'],
            'total' => $res['total_fee'],
            'paytime' => $res['end_time'],
            'openid' => $res['user_id'],
            'deviceUID' => $res['attach'],
        ];
    }

    public function decodeData(string $input): array
    {
        $data = json_decode($input, true);
        if (empty($data)) {
            return err('数据为空！');
        }

        if ($data['result_code'] !== '01') {
            return err($data['return_msg']);
        }

        return [
            'type' => 'lcsw',
            'deviceUID' => $data['attach'],
            'orderNO' => $data['terminal_trace'],
            'total' => intval($data['total_fee']),
            'transaction_id' => $data['out_trade_no'],
            'raw' => $data,
        ];
    }

    public function checkResult(array $data = []): bool
    {
        $lcsw = $this->getLCSW();

        $sign = $lcsw->sign([
            'return_code' => $data['return_code'],
            'return_msg' => $data['return_msg'],
            'result_code' => $data['result_code'],
            'pay_type' => $data['pay_type'],
            'user_id' => $data['user_id'],
            'merchant_name' => $data['merchant_name'],
            'merchant_no' => $data['merchant_no'],
            'terminal_id' => $data['terminal_id'],
            'terminal_trace' => $data['terminal_trace'],
            'terminal_time' => $data['terminal_time'],
            'total_fee' => $data['total_fee'],
            'end_time' => $data['end_time'],
            'out_trade_no' => $data['out_trade_no'],
            'channel_trade_no' => $data['channel_trade_no'],
            'attach' => $data['attach'],
        ]);

        return $sign === $data['key_sign'];
    }

    public function getResponse(bool $ok = true): string
    {
        return self::RESPONSE_OK;
    }
}
