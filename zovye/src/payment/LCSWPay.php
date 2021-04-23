<?php

namespace zovye\payment;

use lcsw\pay;
use zovye\App;
use zovye\Contract\IPay;
use zovye\model\deviceModelObj;
use zovye\State;
use zovye\model\userModelObj;
use zovye\Util;
use function zovye\_W;
use function zovye\error;
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

    /**
     * @param string $user_uid
     * @param string $device_uid
     * @param string $order_no
     * @param int $price
     * @param string $body
     * @return mixed
     */
    public function createXAppPay(string $user_uid, string $device_uid, string $order_no, int $price, string $body = ''): array
    {
        $lcsw = $this->getLCSW();

        $notify_url = _W('siteroot');
        $path = 'addons/' . APP_NAME . '/';

        if (mb_strpos($notify_url, $path) === false) {
            $notify_url .= $path;
        }

        if (App::isAliUser()) {
            $notify_url .= 'payment/alixapp.php';
        } else {
            $notify_url .= 'payment/lcsw.php';
        }

        $params = [
            'userUID' => $user_uid,
            'deviceUID' => $device_uid,
            'orderNO' => $order_no,
            'price' => $price,
            'body' => $body,
            'notify_url' => $notify_url,
        ];

        $res = $lcsw->xAppPay($params);

        Util::logToFile('xapppay', [
            'params' => $params,
            'res' => $res,
        ]);

        if (is_error($res)) {
            return $res;
        }

        if (App::isAliUser()) {
            return [
                'orderNO' => $order_no,
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

    /**
     * @param string $user_uid
     * @param string $device_uid
     * @param string $order_no
     * @param int $price
     * @param string $body
     * @param array $goodsDetail
     * @return mixed
     */
    public function createJsPay(string $user_uid, string $device_uid, string $order_no, int $price, string $body = '', array $goodsDetail = []): array
    {
        $lcsw = $this->getLCSW();

        $notify_url = _W('siteroot');
        $path = 'addons/' . APP_NAME . '/';

        if (mb_strpos($notify_url, $path) === false) {
            $notify_url .= $path;
        }

        $notify_url .= 'payment/lcsw.php';

        $params = [
            'userUID' => $user_uid,
            'deviceUID' => $device_uid,
            'orderNO' => $order_no,
            'price' => $price,
            'body' => $body,
            'notify_url' => $notify_url,
        ];

        if ($goodsDetail) {
            $params['goods_detail'] = json_encode($goodsDetail);
        }

        $res = $lcsw->Jspay($params);

        Util::logToFile('js_pay', [
            'params' => $params,
            'res' => $res,
        ]);

        if (is_error($res)) {
            return $res;
        }

        if (App::isAliUser()) {
            return [
                'orderNO' => $order_no,
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
</script>
ALI_JSCODE;
    }

    protected function getWxPayJs(array $params = []): string
    {
        $js_sdk = Util::fetchJSSDK(false);

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
            return error(State::ERROR, '退款金额不正确！');
        }

        $lcsw = $this->getLCSW();
        $res = $lcsw->doRefund($res['transaction_id'], $total, $res['pay_type']);

        if (is_error($res)) {
            return $res;
        }

        if ($res['result_code'] !== '01') {
            return error(State::FAIL, $res['return_msg']);
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
            'merchant_no' => $this->config['mch_id'],
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
        if (empty($data) || $data['result_code'] !== '01') {
            return error(State::FAIL, $data['return_msg']);
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

    public function checkResult(array $data = [])
    {
        if (empty($data) || $data['result_code'] !== '01') {
            return error(State::FAIL, $data['return_msg']);
        }

        //检查签名
        return true;
    }

    public function getResponse(bool $ok = true)
    {
        if ($ok) {
            return self::RESPONSE_OK;
        }

        return false;
    }
}
