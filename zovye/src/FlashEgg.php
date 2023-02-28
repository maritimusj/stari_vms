<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\accountModelObj;

class FlashEgg
{
    const DEBUG_API_URL = 'https://test-sdi-api.newposm.com/device/api/v1/triggerAdPlay';
    const PRO_API_URL = 'https://sdi-api.newposm.com/device/api/v1/triggerAdPlay';

    protected $app_key = '08171593-81be-f859-047d-97923081ca0d';

    protected $debug = false;

    /**
     * @param string $app_key
     * @param bool $debug
     */
    public function __construct(string $app_key = '', bool $debug = false)
    {
        if ($app_key) {
            $this->app_key = $app_key;
        }

        $this->debug = $debug;
    }

    protected function sign($params = []): string
    {
        return md5(implode($params).$this->app_key);
    }

    public function debug(): FlashEgg
    {
        $this->debug = true;
        return $this;
    }

    /**
     * 请求触发闪蛋广告设备播放指定位置广告
     * @param $uid string 闪蛋广告设备uid
     * @param $no string 闪蛋广告点位值
     * @return array|true
     */
    public function triggerAdPlay(string $uid, string $no)
    {
        $url = $this->debug ? self::DEBUG_API_URL : self::PRO_API_URL;
        
        $data = [
            'devMac' => $uid,
            'triggerNo' => $no,
            'sign' => $this->sign([$uid, $no]),
        ];

        $res = Util::post($url, $data);

        Log::debug('flash_egg', [
            'url' => $url,
            'data' => $data,
            'result' => $res,
        ]);

        if (empty($res)) {
            return err('请求API接口失败！');
        }

        if (is_error($res)) {
            return $res;
        }

        if ($res['code'] != 200) {
            return err($res['msg'] ?? '发生未知错误！');
        }

        return true;
    }

    public static function createOrUpdate(accountModelObj $account, $params)
    {
        request::setData($params);

        $type = request::str('mediaType', 'video');

        $goods = $account->getGoods();
        if (empty($goods)) {
            $s1 = Goods::setFreeBitMask(0);
            $s1 = Goods::setPayBitMask($s1);

            $goods_data = [
                'agent_id' => $account->getAgentId(),
                'name' => $account->getTitle(),
                'img' => request::trim('goodsImage'),
                'sync' => 0,
                'price' => intval(round(request::float('goodsPrice', 0, 2) * 100)),
                's1' => $s1,
                'extra' => [
                    'unitTitle' => request::trim('goodsUnitTitle', '个'),
                    'type' => Goods::FlashEgg,
                    'accountId' => $account->getId(),
                ],
            ];

            $gallery = request::array('gallery');
            if ($gallery) {
                $goods_data['extra']['detailImg'] = $gallery[0];
                $goods_data['extra']['gallery'] = $gallery;
            }

            $goods = Goods::create($goods_data);
            if (empty($goods)) {
                return err('创建商品失败！');
            }
        } else {
            $goods->setAgentId($account->getAgentId());
            $goods->setImg(request::trim('goodsImage'));
            $goods->setPrice(intval(round(request::float('goodsPrice', 0, 2) * 100)));
            $goods->setUnitTitle(request::trim('goodsUnitTitle', '个'));

            $gallery = request::array('gallery');
            $goods->setGallery($gallery);
            if ($gallery) {
                $goods->setDetailImg($gallery[0]);
            } else {
                $goods->setDetailImg('');
            }
            $goods->save();
        }

        $config = [
            'type' => Account::FlashEgg,
            'ad' => [
                'type' => $type,
                'duration' => request::int('duration'),
                'area' => request::trim('area'),
            ],
            'goods' => [
                'id' => $goods->getId(),
            ],
        ];

        if ($type == 'video') {
            $config['ad']['video'] = [
                'url' => request::trim('video'),
            ];
        } else {
            $config['ad']['images'] = request::array('images');
        }

        $account->set('config', $config);

        return true;
    }
}