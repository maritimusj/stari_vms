<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\base\modelObjFinder;
use zovye\model\goodsModelObj;

class Goods
{
    const AllowPay = 'allowPay';
    const AllowFree = 'allowFree';
    const AllowExchange = 'allowExchange';
    const AllowDelivery = 'allowDelivery';

    const ALLOW_PAY = 0b01;
    const ALLOW_FREE = 0b10;
    const ALLOW_EXCHANGE = 0b100;
    const ALLOW_DELIVERY = 0b1000;

    public static function setPayBitMask($s1, $allow = true): int
    {
        return $allow ? $s1 | self::ALLOW_PAY : $s1 ^ self::ALLOW_PAY;
    }

    public static function isAllowPay($s1): bool
    {
        return $s1 & self::ALLOW_PAY;
    }

    public static function setFreeBitMask($s1, $allow = true): int
    {
        return $allow ? $s1 | self::ALLOW_FREE : $s1 ^ self::ALLOW_FREE;
    }

    public static function isAllowFree($s1): bool
    {
        return $s1 & self::ALLOW_FREE;
    }

    public static function setExchangeBitMask($s1, $allow = true): int
    {
        return $allow ? $s1 | self::ALLOW_EXCHANGE : $s1 ^ self::ALLOW_EXCHANGE;
    }

    public static function isAllowExchange($s1): bool
    {
        return $s1 & self::ALLOW_EXCHANGE;
    }

    public static function setDeliveryBitMask($s1, $allow = true): int
    {
        return $allow ? $s1 | self::ALLOW_DELIVERY : $s1 ^ self::ALLOW_DELIVERY;
    }

    public static function isAllowDelivery($s1): bool
    {
        return $s1 & self::ALLOW_DELIVERY;
    }

    /**
     * @param array $data
     * @return goodsModelObj|null
     */
    public static function create(array $data = []): ?goodsModelObj
    {
        if (empty($data['uniacid'])) {
            $data['uniacid'] = We7::uniacid();
        }

        /** @var goodsModelObj $classname */
        $classname = m('goods')->objClassname();
        $data['extra'] = $classname::serializeExtra($data['extra']);

        return m('goods')->create($data);
    }

    /**
     * @param $id
     * @param array $params
     * @return array
     */
    public static function data($id, array $params = []): array
    {
        $goods = self::get($id);
        if ($goods) {
            $detail = in_array('detail', $params) || $params['detail'];
            $use_image_proxy = in_array('useImageProxy', $params) || $params['useImageProxy'];
            $fullPath = in_array('fullPath', $params) || $params['fullPath'];

            return self::format($goods, $detail, $use_image_proxy, $fullPath);
        }

        return [];
    }

    /**
     * @param mixed $id
     * @param bool $deleted
     * @return goodsModelObj|null
     */
    public static function get($id, bool $deleted = false): ?goodsModelObj
    {
        /** @var goodsModelObj[] $cache */
        static $cache = [];

        $id = intval($id);
        if ($id) {
            if ($cache[$id]) {
                return $cache[$id];
            }
            $goods = $deleted ? m('goods')->where(We7::uniacid([]))->findOne(['id' => $id]) : self::query()->findOne(
                ['id' => $id]
            );
            if ($goods) {
                $cache[$goods->getId()] = $goods;

                return $goods;
            }
        }

        return null;
    }

    /**
     * @param goodsModelObj $entry
     * @param bool $detail
     * @param bool $use_image_proxy
     * @param bool $full_path
     * @return array
     */
    public static function format(
        goodsModelObj $entry,
        bool $detail = false,
        bool $use_image_proxy = false,
        bool $full_path = true
    ): array {
        $imageUrlFN = function ($url) use ($use_image_proxy, $full_path) {
            if ($full_path) {
                $url = Util::toMedia($url, $use_image_proxy);
            }

            return $url;
        };
        $data = [
            'id' => $entry->getId(),
            'name' => strval($entry->getName()),
            'img' => $imageUrlFN($entry->getImg()),
            'sync' => boolval($entry->getSync()),
            'price' => intval($entry->getPrice()),
            'price_formatted' => '???'.number_format($entry->getPrice() / 100, 2).'???',
            'unit_title' => $entry->getUnitTitle(),
            'createtime_formatted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
            'cw' => $entry->getExtraData('cw', 0), //????????????????????????
            Goods::AllowFree => $entry->allowFree(),
            Goods::AllowPay => $entry->allowPay(),
            Goods::AllowExchange => $entry->allowExchange(),
            Goods::AllowDelivery => $entry->allowDelivery(),
        ];

        if ($entry->isDeleted()) {
            $data['deleted'] = true;
        }

        $lottery = $entry->getExtraData('lottery', []);
        if (!empty($lottery)) {
            $data['lottery'] = $lottery;
        }

        $cost_price = $entry->getCostPrice();

        if (!empty($cost_price)) {
            $data['costPrice'] = $cost_price;
            $data['costPrice_formatted'] = '???'.number_format($cost_price / 100, 2).'???';
        }

        if (App::isBalanceEnabled()) {
            $data['balance'] = $entry->getBalance();
        }

        $discountPrice = $entry->getExtraData('discountPrice', 0);
        if (!empty($discountPrice)) {
            $data['discountPrice'] = $discountPrice;
            $data['discountPrice_formatted'] = '???'.number_format($discountPrice / 100, 2).'???';
        }

        $detailImg = $entry->getDetailImg();
        if ($detailImg) {
            $data['detailImg'] = $imageUrlFN($entry->getDetailImg());
        }

        $gallery = $entry->getGallery();
        if ($detailImg && (empty($gallery) || $gallery[0] != $detailImg)) {
            $gallery[] = $detailImg;
        }

        if ($gallery) {
            foreach ($gallery as $url) {
                $data['gallery'][] = $imageUrlFN($url);
            }
        }

        if ($detail) {
            if ($entry->getAgentId()) {
                $agent = Agent::get($entry->getAgentId());
                if ($agent) {
                    $data['agent'] = $agent->profile();
                }
            }
        }

        return $data;
    }

    /**
     * @param array $params
     * @param callable|null $formatter
     * @return array
     */
    public static function getList(array $params = [], callable $formatter = null): array
    {
        $page = max(1, intval($params['page']));
        $page_size = empty($params['pagesize']) ? DEFAULT_PAGE_SIZE : intval($params['pagesize']);

        $query = Goods::query();

        if (isset($params['agent_id'])) {
            if (We7::starts_with('*', $params['agent_id'])) {
                $agent_id = ltrim($params['agent_id'], '*');

                $agent = Agent::get($agent_id);
                if (empty($agent)) {
                    return error(State::ERROR, '???????????????????????????');
                }

                $query->where("agent_id=0 OR agent_id={$agent->getId()}");

            } elseif ($params['agent_id'] > 0) {
                $agent = Agent::get($params['agent_id']);
                if (empty($agent)) {
                    return error(State::ERROR, '???????????????????????????');
                }
                $query->where(['agent_id' => $agent->getId()]);
            } else {
                $query->where(['agent_id' => 0]);
            }
        }

        if (isset($params['price'])) {
            $query->where(['price' => $params['price'] * 100]);
        }

        $keywords = $params['keywords'];
        if ($keywords) {
            $query->where(['name LIKE' => "%$keywords%"]);
        }

        $s1 = 0;
        if ((!empty($params[Goods::AllowPay]) || in_array(Goods::AllowPay, $params, true))) {
            $s1 = Goods::setPayBitMask($s1);
        }
        if ((!empty($params[Goods::AllowFree]) || in_array(Goods::AllowFree, $params, true))) {
            $s1 = Goods::setFreeBitMask($s1);
        }
        if ((!empty($params[Goods::AllowExchange]) || in_array(Goods::AllowExchange, $params, true))) {
            $s1 = Goods::setExchangeBitMask($s1);
        }
        if ((!empty($params[Goods::AllowDelivery]) || in_array(Goods::AllowDelivery, $params, true))) {
            $s1 = Goods::setDeliveryBitMask($s1);
        }

        if ($s1) {
            if ($params['matchAny'] || in_array('matchAny', $params, true)) {
                $query->where("s1&$s1>1");
            } else {
                $query->where("s1&$s1=$s1");
            }
        }

        $total = $query->count();
        $total_page = ceil($total / $page_size);

        $goods_list = [];

        $query->page($page, $page_size);
        $query->orderBy('id DESC');

        $formatter = $formatter ?? function ($goods) {
                return self::format($goods, true, true);
            };
        /** @var goodsModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $goods_list[] = $formatter($entry);
        }

        return [
            'total' => $total,
            'totalpage' => $total_page,
            'page' => $page,
            'pagesize' => $page_size,
            'list' => $goods_list,
        ];
    }

    public static function CopyToAgent($agent_id, goodsModelObj $entry): bool
    {
        $goods = Goods::create(
            [
                'agent_id' => $agent_id,
                'name' => $entry->getName(),
                'img' => $entry->getImg(),
                'price' => $entry->getPrice(),
                'extra' => $entry->getExtraData(),
                'createtime' => $entry->getCreatetime(),
            ]
        );

        if (!empty($goods)) {
            $goods->updateSettings('extra.clone.original', $entry->getId());

            return true;
        }

        return false;
    }

    /**
     * @param array $condition
     * @return modelObjFinder
     */
    public static function query(array $condition = []): modelObjFinder
    {
        return m('goods')->where(We7::uniacid(['deleted' => 0]))->where($condition);
    }

    public static function findOne($cond): ?goodsModelObj
    {
        return self::query($cond)->findOne();
    }

}
