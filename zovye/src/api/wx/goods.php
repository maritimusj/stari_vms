<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wx;

use zovye\App;
use zovye\request;
use zovye\State;
use function zovye\error;
use function zovye\settings;

class goods
{
    public static function list(): array
    {
        $user = common::getAgentOrPartner();

        common::checkCurrentUserPrivileges('F_sp');

        $params = [
            'page' => request::int('page'),
            'pagesize' => request::int('pagesize', DEFAULT_PAGE_SIZE),
            'keywords' => request::trim('keywords', '', true),
            'default_goods' => true,
        ];

        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();

        if (request::bool('all')) {
            $params['agent_id'] = "*{$agent->getId()}";
        } else {
            $params['agent_id'] = $agent->getId();
        }

        $result = \zovye\Goods::getList($params);
        $result['goods_edit'] = boolval(settings('goods.agent.edit', 0));
        $result['lottery'] = [
            'enabled' => App::isLotteryGoodsSupported(),
        ];

        return $result;
    }

    public static function detail(): array
    {
        $user = common::getAgentOrPartner();

        common::checkCurrentUserPrivileges('F_sp');

        $goods_id = request::int('id');

        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();
        $goods = \zovye\Goods::get($goods_id);
        if (empty($goods) || $goods->getAgentId() !== $agent->getId()) {
            return error(State::ERROR, '找不到这个商品！');
        }

        return \zovye\Goods::data($goods_id, ['fullPath']);
    }

    public static function delete(): array
    {
        $user = common::getAgentOrPartner();

        common::checkCurrentUserPrivileges('F_sp');

        $goods = \zovye\Goods::get(request::int('id'));
        if (empty($goods)) {
            return error(State::ERROR, '找不到指定的商品');
        }

        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();
        if ($goods->getAgentId() !== $agent->getId()) {
            return error(State::ERROR, '没有权限管理这个商品');
        }
        if ($goods->destroy()) {
            return ['msg' => '商品删除成功！'];
        }

        return error(State::ERROR, '商品删除失败！');
    }

    public static function create(): array
    {
        $user = common::getAgentOrPartner();

        common::checkCurrentUserPrivileges('F_sp');

        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();

        $s1 = 0;
        if (request::bool(\zovye\Goods::AllowFree)) {
            $s1 = \zovye\Goods::setFreeBitMask($s1);
        }
        if (request::bool(\zovye\Goods::AllowPay)) {
            $s1 = \zovye\Goods::setPayBitMask($s1);
        }
        if (request::bool(\zovye\Goods::AllowExchange)) {
            $s1 = \zovye\Goods::setExchangeBitMask($s1);
        }
        if (request::bool(\zovye\Goods::AllowDelivery)) {
            $s1 = \zovye\Goods::setDeliveryBitMask($s1);
        }

        $goods_id = request::int('goodsId');
        if ($goods_id > 0) {
            $goods = \zovye\Goods::get($goods_id);
            if (empty($goods)) {
                return error(State::ERROR, '找不到这个商品！');
            }

            $goods->setS1($s1);

            if ($goods->getAgentId() !== $agent->getId()) {
                return error(State::ERROR, '没有权限管理这个商品');
            }

            //固定货道商品商品指定货道
            if (request::isset('goodsLaneID')) {
                if (request::int('goodsLaneID') != $goods->getExtraData('lottery.size')) {
                    $goods->setExtraData('lottery.size', request::int('goodsLaneID'));
                }
            }

            if (request::isset('goodsMcbIndex')) {
                if (request::int('goodsMcbIndex') != $goods->getExtraData('lottery.index')) {
                    $goods->setExtraData('lottery.index', request::int('goodsMcbIndex'));
                }
            }

            if (request::isset('costPrice')) {
                $goods->setExtraData('costPrice', request::float('costPrice', 0, 2) * 100);
            }

            if (request::str('goodsName') != $goods->getName()) {
                $goods->setName(request::str('goodsName'));
            }

            if (request::str('goodsImg') != $goods->getImg()) {
                $goods->setImg(request::str('goodsImg'));
            }

            if (request::has('detailImg')) {
                $detailImg = request::trim('detailImg');
                if ($detailImg != $goods->getDetailImg()) {
                    $goods->setDetailImg($detailImg);
                    $goods->setGallery([$detailImg]);
                }
            } elseif (request::is_array('gallery')) {
                $gallery = request::array('gallery');
                if ($gallery) {
                    $goods->setDetailImg($gallery[0]);
                    $goods->setGallery($gallery);
                }
            }

            $price = request::float('goodsPrice', 0, 2) * 100;
            if ($price != $goods->getPrice()) {
                $goods->setPrice($price);
            }

            if (request::str('goodsUnitTitle') != $goods->getUnitTitle()) {
                $goods->setUnitTitle(request::str('goodsUnitTitle'));
            }
        } else {
            $goods_data = [
                'name' => request::trim('goodsName'),
                'img' => request::trim('goodsImg'),
                's1' => $s1,
                'price' => request::float('goodsPrice', 0, 2) * 100,
                'extra' => [
                    'unitTitle' => request::trim('goodsUnitTitle'),
                ],
            ];

            $goods_data['agent_id'] = $agent->getId();

            if (request::has('detailImg')) {
                $detailImg = request::trim('detailImg');
                $goods_data['extra']['detailImg'] = $detailImg;
                $goods_data['extra']['gallery'] = [$detailImg];
            }

            if (request::is_array('gallery')) {
                $gallery = request::array('gallery');
                if ($gallery) {
                    $goods_data['extra']['detailImg'] = $gallery[0];
                    $goods_data['extra']['gallery'] = $gallery;
                }
            }

            //固定货道商品商品指定货道
            if (request::is_string('goodsLaneID')) {
                $goods_data['extra']['lottery']['size'] = request::int('goodsLaneID');
            }
            if (request::has('goodsMcbIndex')) {
                $goods_data['extra']['lottery']['index'] = request::int('goodsMcbIndex');
            }
            if (request::isset('costPrice')) {
                $goods_data['extra']['costPrice'] = request::float('costPrice', 0, 2) * 100;
            }

            $goods_data['extra']['type'] = request::str('type');

            $goods = \zovye\Goods::create($goods_data);
        }
        
        if ($goods && $goods->save()) {
            return ['msg' => '商品保存成功！'];
        }

        return error(State::ERROR, '商品保存失败！');
    }
}