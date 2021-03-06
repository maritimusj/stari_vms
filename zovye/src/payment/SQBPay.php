<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\payment;

use SQB\pay;
use zovye\App;
use zovye\Contract\IPay;
use zovye\Log;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\request;
use zovye\Util;
use function zovye\_W;
use function zovye\err;
use function zovye\is_error;

class SQBPay implements IPay
{
    private $config = [];

    public function getName(): string
    {
        return \zovye\Pay::SQB;
    }

    public function setConfig(array $config = [])
    {
        $this->config = $config;
    }

    private function getSQB(): pay
    {
        return new pay($this->config);
    }

    public function createXAppPay(
        string $user_uid,
        string $device_uid,
        string $order_no,
        int $price,
        string $body = ''
    ): array {
        $SQB = $this->getSQB();
        $notify_url = _W('siteroot');
        $path = 'addons/'.APP_NAME.'/';

        if (mb_strpos($notify_url, $path) === false) {
            $notify_url .= $path;
        }

        if (App::isAliUser()) {
            $notify_url .= 'payment/SQBAlipay.php';
        } else {
            $notify_url .= 'payment/SQB.php';
        }

        $res = $SQB->xAppPay($user_uid, $order_no, $price, $device_uid, $body, $notify_url);

        Log::debug('sqb_xapppay', [
            'params' => [
                'user_uid' => $user_uid,
                'order_no' => $order_no,
                'price' => $price,
                'device_uid' => $device_uid,
                'body' => $body,
                'notify_url' => $notify_url,
            ],
            'res' => $res,
        ]);

        if (is_error($res)) {
            return $res;
        }

        if ($res['result_code'] !== 'PRECREATE_SUCCESS') {
            return err($res['error_message']);
        }

        return is_array($res['data']['wap_pay_request']) ? $res['data']['wap_pay_request'] : [];
    }

    public function createJsPay(
        string $user_uid,
        string $device_uid,
        string $order_no,
        int $price,
        string $body = ''
    ): array {
        $SQB = $this->getSQB();
        $notify_url = _W('siteroot');
        $path = 'addons/'.APP_NAME.'/';

        if (mb_strpos($notify_url, $path) === false) {
            $notify_url .= $path;
        }

        $notify_url .= 'payment/SQB.php';
        $pay_result_url = Util::murl('payresult', ['op' => 'SQB']);

        return [
            'redirect' => $SQB->wapApiPro($order_no, $price, $device_uid, $body, $notify_url, $pay_result_url),
        ];
    }

    public function getPayJs(deviceModelObj $device, userModelObj $user): string
    {
        $device_uid = $device->getImei();

        $js_sdk = Util::fetchJSSDK(false);
        $jquery_url = JS_JQUERY_URL;
        $order_api_url = Util::murl('order', ['deviceUID' => $device_uid]);

        return <<<JS_CODE
<script src="$jquery_url"></script>
$js_sdk
<script>
    wx.ready(function(){
        wx.hideAllNonBaseMenuItem();
    });
    const zovye_fn = {};
    zovye_fn.pay = function(res) {
        return new Promise(function(resolve, reject) {
           if (!res) {
                return reject("???????????????");
            }       
           if (!res.status) {
                if (res.message) {
                    return reject(res.message);
                }
                if(res.data && res.data.msg) {                   
                    return reject(res.data.msg);
                }        
                return reject("???????????????");
           } 
           const data = res.data;
           if (data && data.redirect) {
               window.location.replace(data.redirect);
               return resolve('????????????...');
           }
           return reject('???????????????????????????');
        })
    }
    zovye_fn.goods_wxpay = function(params) {
        return new Promise(function(resolve, reject) {
            const goodsID = typeof params === 'object' && params.goodsID !== undefined ? params.goodsID : params;
            const total = typeof params === 'object' && params.total !== undefined ? params.total : 1;
            $.get("$order_api_url", {op: "create", goodsID: goodsID, total: total}).then(function(res) {
              zovye_fn.pay(res).catch(function(msg) {
                  reject(msg);
              });
          });
        }).catch((e)=>{
            console.log(e);
        });
    }
    zovye_fn.package_pay = function(packageID) {
        return new Promise(function(resolve, reject) {
            $.get("$order_api_url", {op: "create", packageID: packageID}).then(function(res) {
              zovye_fn.pay(res).catch(function(msg) {
                  reject(msg);
              });
          });
        }).catch((e)=>{
            console.log(e);
        });
    }
    </script>
JS_CODE;
    }

    public function refund(string $order_no, int $total, bool $is_transaction_id = false)
    {
        $SQB = $this->getSQB();
        $res = $SQB->refund($order_no, $total, $is_transaction_id);
        if (is_error($res)) {
            return $res;
        }

        if ($res['result_code'] === 'REFUND_SUCCESS') {
            return $res;
        }

        if ($res['result_code'] == 'REFUND_IN_PROGRESS') {
            return err('???????????????');
        }

        if ($res['result_code'] == 'REFUND_ERROR' || $res['result_code'] == 'REFUND_FAIL') {
            return err('????????????');
        }

        return err('???????????????????????????');
    }

    public function query(string $order_no)
    {
        $SQB = $this->getSQB();
        $res = $SQB->query($order_no);
        if (is_error($res)) {
            return $res;
        }

        if ($res['result_code'] === 'SUCCESS') {
            $data = $res['data'];
            if ($data['order_status'] == 'PAID') {
                return [
                    'result' => 'success',
                    'type' => $this->getName(),
                    'pay_way' => $data['payway_name'],
                    'merchant_no' => $this->config['mch_id'],
                    'orderNO' => $data['client_sn'],
                    'transaction_id' => $data['sn'],
                    'total' => $data['total_amount'],
                    'paytime' => $data['finish_time'],
                    'openid' => $data['payer_uid'],
                    'deviceUID' => $data['operator'],
                ];
            }
            if ($data['order_status'] == 'CREATED') {
                return err('?????????');
            }

            return err('??????????????????');
        }

        return err($res['error_message']);
    }

    public function decodeData(string $input): array
    {
        $data = json_decode($input, true);
        if ($data['status'] === 'SUCCESS' && $data['order_status'] === 'PAID') {
            return [
                'type' => 'SQB',
                'deviceUID' => $data['operator'],
                'orderNO' => $data['client_sn'],
                'total' => intval($data['total_amount']),
                'transaction_id' => $data['sn'],
                'raw' => $data,
            ];
        }

        return err('????????????????????????????????????????????????');
    }

    public function checkResult(array $data = []): bool
    {
        $SQB = $this->getSQB();

        return $SQB->checkSign(request::raw(), request::header('HTTP_AUTHORIZATION'));
    }

    public function getResponse(bool $ok = true): string
    {
        return 'success';
    }
}