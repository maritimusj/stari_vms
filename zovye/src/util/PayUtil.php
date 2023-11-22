<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\util;

use Exception;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use WeChatPay\Builder;
use WeChatPay\BuilderChainable;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Util\PemUtil;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use function zovye\_W;
use function zovye\err;
use function zovye\is_error;

class PayUtil
{
    public static function getWxPayV3Builder(array $config): BuilderChainable
    {
        $params = [
            'mchid' => $config['mch_id'],         // 商户号
            'serial' => $config['serial'],        // 「商户API证书」的「证书序列号」
            'privateKey' => Rsa::from($config['pem']['key']),
            'certs' => [
                'any' => null,
            ],
        ];

        if ($config['pem']['cert']['data']) {
            $cert_data = $config['pem']['cert']['data'];
              // 从「微信支付平台证书」中获取「证书序列号」
              $serial = PemUtil::parseCertificateSerialNo($cert_data);
              $params['certs'] = [
                  $serial => Rsa::from($cert_data, Rsa::KEY_TYPE_PUBLIC),
              ];
        }

        // 构造一个 APIv3 客户端实例
        return Builder::factory($params);
    }

    public static function parseWxPayV3Response(ResponseInterface $response)
    {
        $contents = $response->getBody()->getContents();

        if ($contents) {
            return json_decode($contents, true);
        }

        return err('请求失败！');
    }

    public static function getWxPlatformCertificate($config)
    {
        try {
            // 发送请求
            try {
                $response = (self::getWxPayV3Builder($config))->chain('v3/certificates')->get();
            } catch (RequestException $e) {
                if ($e->hasResponse()) {
                    $response = $e->getResponse();
                } else {
                    throw new $e;
                }
            }

            $result = self::parseWxPayV3Response($response);

            if (is_error($result)) {
                return $result;
            }

            [
                'data' => [
                    0 => [
                        'encrypt_certificate' => [
                            'associated_data' => $associated_data,
                            'ciphertext' => $ciphertext,
                            'nonce' => $nonce,
                        ],
                        'expire_time' => $expire_time,
                        'serial_no' => $serial_no,
                    ],
                ],
            ] = $result;

            // 加密文本消息解密
            $data = AesGcm::decrypt($ciphertext, $config['key'], $nonce, $associated_data);
  
            // 保存
            return [
                'serial_no' => $serial_no,
                'data' => $data,
                'expire_time' => $expire_time,
            ];

        } catch (Exception $e) {
            return err($e->getMessage());
        }
    }

    public static function getPaymentCallbackUrl($config_id): string
    {
        $notify_url = _W('siteroot');
        $path = 'addons/'.APP_NAME.'/';

        if (mb_strpos($notify_url, $path) === false) {
            $notify_url .= $path;
        }

        return $notify_url."payment/$config_id.php";
    }

    public static function getAliPayJs(array $params = []): string
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

    public static function getWxPayJs(array $params = []): string
    {
        $js_sdk = Util::jssdk();

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

    /**
     * 获取页面支付时需要调用的js代码
     */
    public static function getPayJs(deviceModelObj $device, userModelObj $user): string
    {
        $params = [
            'JQueryURL' => JS_JQUERY_URL,
            'orderAPIURL' => Util::murl('order', ['deviceUID' => $device->getImei()]),
            'payResultURL' => Util::murl('payresult', ['orderNO' => '__orderNO__', 'deviceid' => $device->getId()]),
            'payFailed' => Util::murl('payfailed', ['msg' => '__msg__']),
        ];

        if ($user->isWxUser()) {
            return self::getWxPayJs($params);
        }

        if ($user->isAliUser()) {
            return self::getAliPayJs($params);
        }

        return <<<JS
<script src="{$params['JQueryURL']}"></script>
<script>
$(function() {
    alert("当前环境不支持！");
})
</script>
JS;
    }
}