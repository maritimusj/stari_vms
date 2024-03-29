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
use zovye\App;
use zovye\contract\IPay;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\Session;
use zovye\We7;
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

        try {
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

    public static function getPaymentCallbackUrl(IPay $pay): string
    {
        $notify_url = _W('siteroot');
        $path = 'addons/'.APP_NAME.'/';

        if (mb_strpos($notify_url, $path) === false) {
            $notify_url .= $path;
        }

        $config = $pay->getConfig();

        return $notify_url."payment/{$config['config_id']}.php";
    }

    /**
     * 保存证书到文件并返回路径
     */
    public static function writePEMToFile(array $pem, bool $force = false): array
    {
        if ($pem['cert'] && $pem['key']) {

            $dir = PEM_DIR.App::uid(8).DIRECTORY_SEPARATOR;

            We7::make_dirs($dir);

            $cert_filename = $dir.sha1($pem['cert']).'.pem';
            $key_filename = $dir.sha1($pem['key']).'.pem';

            if (!$force && file_exists($cert_filename) && file_exists($key_filename)) {
                return [$cert_filename, $key_filename];
            }

            if (
                file_put_contents($cert_filename, $pem['cert']) !== false &&
                file_put_contents($key_filename, $pem['key']) !== false
            ) {
                return [$cert_filename, $key_filename];
            }
        }

        return err('写入证书文件失败！');
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
        window.location.replace(api_url.replace("__msg__", encodeURIComponent(msg)));
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
            
            if (data && data.redirect) {
               window.location.replace(data.redirect);
               return resolve(null, '正在转跳...');
            }
            
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
            })
        })
    }
    zovye_fn.lane_wxpay = function(params, successFN, failFN) {
      return new Promise(function(resolve, reject) {
        const laneID = typeof params === 'object' && params.laneID !== undefined ? params.laneID : params;
        const total = typeof params === 'object' && params.total !== undefined ? params.total : 1;
          $.get("{$params['orderAPIURL']}", {op: "create", laneID, total}).then(function(res) {
              zovye_fn.pay(res).then(function(orderNO, msg) {
                  if (orderNO) {
                      if (typeof successFN !== 'function' || !successFN(orderNO)) {
                        zovye_fn.redirectToGetPayResultPage(orderNO, msg);
                      }
                      resolve(orderNO, msg);
                  }
              }).catch(function(msg) {
                  if (typeof failFN !== 'function' || !failFN(msg)) {
                    zovye_fn.redirectToPayFailedPage(msg);
                  }
                  reject(msg);
              })
          })
      })
    }
    zovye_fn.goods_wxpay = function(params, successFN, failFN) {
      return new Promise(function(resolve, reject) {
        const goodsID = typeof params === 'object' && params.goodsID !== undefined ? params.goodsID : params;
        const total = typeof params === 'object' && params.total !== undefined ? params.total : 1;
          $.get("{$params['orderAPIURL']}", {op: "create", goodsID, total}).then(function(res) {
              zovye_fn.pay(res).then(function(orderNO, msg) {
                  if (orderNO) {
                      if (typeof successFN !== 'function' || !successFN(orderNO)) {
                        zovye_fn.redirectToGetPayResultPage(orderNO, msg);
                      }
                      resolve(orderNO, msg);
                  }
              }).catch(function(msg) {
                  if (typeof failFN !== 'function' || !failFN(msg)) {
                    zovye_fn.redirectToPayFailedPage(msg);
                  }
                  reject(msg);
              })
          })
      })
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
              })
          })
      })
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
    })
    
    const zovye_fn = {};
    zovye_fn.redirectToGetPayResultPage = function(orderNO, msg) {
        let api_url = "{$params['payResultURL']}".replace("__orderNO__", orderNO);
        window.location.replace(api_url.replace("__msg__", encodeURIComponent(msg)));
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
            
            if (data && data.redirect) {
               window.location.replace(data.redirect);
               return resolve(null, '正在转跳...');
            }
            
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
            })
        })
    }
    zovye_fn.lane_wxpay = function(params, successFN, failFN) {
      return new Promise(function(resolve, reject) {
          const laneID = typeof params === 'object' && params.laneID !== undefined ? params.laneID : params;
          const total = typeof params === 'object' && params.total !== undefined ? params.total : 1;
          $.get("{$params['orderAPIURL']}", {op: "create", laneID, total}).then(function(res) {
              zovye_fn.pay(res).then(function(orderNO, msg) {
                  if (orderNO) {
                      if (typeof successFN !== 'function' || !successFN(orderNO)) {
                          zovye_fn.redirectToGetPayResultPage(orderNO, msg);
                      }
                      resolve(orderNO, msg);
                  }
              }).catch(function(msg) {
                  if (typeof failFN !== 'function' || !failFN(msg)) {
                      zovye_fn.redirectToPayFailedPage(msg);
                  }
                  reject(msg);
              })
          })   
      })
    }
    zovye_fn.goods_wxpay = function(params, successFN, failFN) {
      return new Promise(function(resolve, reject) {
          const goodsID = typeof params === 'object' && params.goodsID !== undefined ? params.goodsID : params;
          const total = typeof params === 'object' && params.total !== undefined ? params.total : 1;
          $.get("{$params['orderAPIURL']}", {op: "create", goodsID, total}).then(function(res) {
              zovye_fn.pay(res).then(function(orderNO, msg) {
                  if (orderNO) {
                      if (typeof successFN !== 'function' || !successFN(orderNO)) {
                          zovye_fn.redirectToGetPayResultPage(orderNO, msg);
                      }
                      resolve(orderNO, msg);
                  }
              }).catch(function(msg) {
                  if (typeof failFN !== 'function' || !failFN(msg)) {
                      zovye_fn.redirectToPayFailedPage(msg);
                  }
                  reject(msg);
              })
          })     
      })
    }
    
    zovye_fn.package_pay = function(packageID, successFN, failFN) {
      return new Promise(function(resolve, reject) {
          $.get("{$params['orderAPIURL']}", {op: "create", packageID}).then(function(res) {
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
              })
          })
      })
    }
</script>
JSCODE;
    }

    public static function getDummyPayJs(): string
    {
        $js_sdk = '';
        if (Session::isWxUser()) {
            $js_sdk = Util::jssdk();
        } elseif (Session::isAliUser()) {
            $js_sdk =  <<<JS
<script src="https://gw.alipayobjects.com/as/g/h5-lib/alipayjsapi/3.1.1/alipayjsapi.inc.min.js"></script>
JS;
        }

        $jquery_url = JS_JQUERY_URL;

        return <<<JSCODE
<script src="$jquery_url"></script>
{$js_sdk}
<script>
    const zovye_fn = {};
    zovye_fn.pay = function() {
        return new Promise(function(resolve, reject) {
            alert('暂时无法支付购买！');
            reject();
        })
    }
    zovye_fn.goods_wxpay = function(params, successFN, failFN) {
        if (typeof failFN === 'function') {
            failFN('暂时无法支付购买！');
            return;
        }
        
        return new Promise(function(resolve, reject) {
            alert('暂时无法支付购买！');
            reject();
        })
    }
    zovye_fn.package_pay = function(packageID, successFN, failFN) {
        if (typeof failFN === 'function') {
            failFN('暂时无法支付购买！');
            return;
        }
        return new Promise(function(resolve, reject) {
            alert('暂时无法支付购买！');
            reject();
        })
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

        return self::getDummyPayJs();
    }
}