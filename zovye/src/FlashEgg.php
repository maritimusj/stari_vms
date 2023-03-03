<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\base\modelFactory;
use zovye\model\deviceModelObj;
use zovye\model\gift_logModelObj;
use zovye\model\giftModelObj;
use zovye\base\modelObjFinder;
use zovye\model\accountModelObj;
use zovye\model\userModelObj;

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
        Request::setData($params);

        $type = Request::str('mediaType', 'video');

        //适配小程序端上传图片
        $stripUrl = function ($url) {
            $result = explode('@', $url, 2);
            if (empty($result)) {
                return '';
            }

            return count($result) == 2 ? $result[1] : $result[0];
        };

        $goodsImg = $stripUrl(Request::str('goodsImage'));
        $gallery = [];
        foreach (Request::array('gallery') as $url) {
            $gallery[] = $stripUrl($url);
        }

        $goods = $account->getGoods();
        if (empty($goods)) {
            $s1 = Goods::setFreeBitMask(0);
            $s1 = Goods::setPayBitMask($s1);
            $goods_data = [
                'agent_id' => $account->getAgentId(),
                'name' => $account->getTitle(),
                'img' => $goodsImg,
                'sync' => 0,
                'price' => intval(round(Request::float('goodsPrice', 0, 2) * 100)),
                's1' => $s1,
                'extra' => [
                    'unitTitle' => Request::trim('goodsUnitTitle', '个'),
                    'type' => Goods::FlashEgg,
                    'accountId' => $account->getId(),
                ],
            ];

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
            $goods->setImg($goodsImg);
            $goods->setPrice(intval(round(Request::float('goodsPrice', 0, 2) * 100)));
            $goods->setUnitTitle(Request::trim('goodsUnitTitle', '个'));

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
                'duration' => Request::int('duration'),
                'area' => Request::trim('area'),
            ],
            'goods' => [
                'id' => $goods->getId(),
            ],
        ];

        if ($type == 'video') {
            $config['ad']['video'] = [
                'url' => $stripUrl(Request::trim('video')),
            ];
        } else {
            $images = [];
            foreach (Request::array('images') as $url) {
                $images[] = $stripUrl($url);
            }
            $config['ad']['images'] = $images;
        }

        return $account->set('config', $config);
    }

    public static function gift(): modelFactory
    {
        return m('gift');
    }

    public static function getGift(int $id): ?giftModelObj
    {
        return self::gift()->findOne(['id' => $id]);
    }

    public static function createGift(array $data): ?giftModelObj
    {
        if ($data['extra']) {
            $data['extra'] = giftModelObj::serializeExtra($data['extra']);
        }

        return self::gift()->create(We7::uniacid($data));
    }

    public static function giftQuery($cond = []): modelObjFinder
    {
        return self::gift()->query($cond);
    }

    public static function giftLog(): modelFactory
    {
        return m('gift_log');
    }

    public static function getGiftLog(int $id): ?gift_logModelObj
    {
        return self::giftLog()->findOne(['id' => $id]);
    }

    public static function createGiftLog(array $data): ?gift_logModelObj
    {
        if ($data['extra']) {
            $data['extra'] = gift_logModelObj::serializeExtra($data['extra']);
        }

        return self::giftLog()->create($data);
    }

    public static function giftLogQuery($cond = []): modelObjFinder
    {
        return self::giftLog()->query($cond);
    }

    public static function isUserGiftLogExists(userModelObj $user, giftModelObj $gift): bool
    {
        return self::giftLogQuery([
            'gift_id' => $gift->getId(),
            'user_id' => $user->getId(),
        ])->exists();
    }

    public static function getUserGiftDetail(userModelObj $user, giftModelObj $gift): array
    {
        $goods_list = $gift->getGoodsList(true);
        foreach ($goods_list as &$goods) {
            if ($goods['num'] > 0) {
                $goods['acquired'] = Order::query([
                    'openid' => $user->getOpenid(),
                    'goods_id' => $goods['id'],
                ])->limit($goods['num'])->count();
            }
        }

        $data = $gift->profile(true);
        $data['list'] = $goods_list;

        return $data;
    }

    public static function selectGiftForUser(userModelObj $user, deviceModelObj $device): ?giftModelObj
    {
        $agent = $device->getAgent();
        if ($agent) {
            $key = sha1("{$device->getId()}:agent:{$agent->getId()}");
        } else {
            $key = sha1("{$device->getId()}:agent:0");
        }

        $id = $user->settings("flash_gift.$key", 0);

        if ($id > 0) {
            $gift = self::getGift($id);
            if ($gift && $gift->isEnabled()) {
                return $gift;
            }
        }

        $list = [];
        if ($agent) {
            $query = self::giftQuery([
                'agent_id' => $agent->getId(),
                'enabled' => 1,
            ])->orderBy('id desc');

            /** @var giftModelObj $item */
            foreach ($query->findAll() as $item) {
                if (self::isUserGiftLogExists($user, $item)) {
                    continue;
                }
                $list[] = $item;
            }
        }

        if (empty($list)) {
            $query = self::giftQuery([
                'agent_id' => 0,
                'enabled' => 1,
            ])->orderBy('id desc');

            foreach ($query->findAll() as $item) {
                if (self::isUserGiftLogExists($user, $item)) {
                    continue;
                }
                $list[] = $item;
            }
        }

        if (empty($list)) {
            $gift = null;
        } elseif (count($list) == 1) {
            $gift = $list[0];
        } else {
            $gift = $list[array_rand($list)];
        }

        if ($gift) {
            $user->updateSettings("flash_gift.$key", $gift->getId());
        } else {
            $user->removeSettings('flash_gift', $key);
        }
        
        return $gift;
    }
}