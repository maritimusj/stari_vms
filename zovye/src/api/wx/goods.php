<?php


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
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_sp');

        $params = [
            'page' => request::int('page'),
            'pagesize' => request::int('pagesize', DEFAULT_PAGESIZE),
            'keywords' => trim(urldecode(request::trim('keywords'))),
            'default_goods' => true,
        ];

        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();
        $params['agent_id'] = $agent->getId();

        $result = \zovye\Goods::getList($params);
        $result['goods_edit'] = boolval(settings('goods.agent.edit', 0));
        $result['lottery'] = [
            'enabled' => App::isLotteryGoodsSupported(),
        ];

        return $result;
    }

    public static function detail(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_sp');

        $goods_id = request::int('id');

        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();
        $goods = \zovye\Goods::get($goods_id);
        if (empty($goods) || $goods->getAgentId() !== $agent->getId()) {
            return error(State::ERROR, '找不到这个商品！');
        }

        return \zovye\Goods::data($goods_id);
    }

    public static function delete(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_sp');

        $goods = \zovye\Goods::get(request::int('id'));
        if (empty($goods)) {
            return error(State::ERROR, '找不到指定的商品');
        }

        if ($goods) {
            $agent = $user->isAgent() ? $user : $user->getPartnerAgent();
            if ($goods->getAgentId() !== $agent->getId()) {
                return error(State::ERROR, '没有权限管理这个商品');
            }
            if ($goods->destroy()) {
                return ['msg' => '商品删除成功！'];
            }
        }

        return error(State::ERROR, '商品删除失败！');
    }

    public static function create(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_sp');

        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();

        $goods_id = request::int('goodsId');
        if ($goods_id > 0) {
            $goods = \zovye\Goods::get($goods_id);
            if (empty($goods)) {
                return error(State::ERROR, '找不到这个商品！');
            }

            if ($goods->getAgentId() !== $agent->getId()) {
                return error(State::ERROR, '没有权限管理这个商品');
            }

            //固定货道商品商品指定货道
            if (request::isset('goodsLaneID')) {
                if (request::int('goodsLaneID') != $goods->getExtraData('lottery.size')) {
                    $goods->setExtraData('lottery.size', request::int('goodsLaneID'));
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

            if (request::str('detailImg') != $goods->getDetailImg()) {
                $goods->setDetailImg(request::str('detailImg'));
            }

            if (request::str('detailImg') != $goods->getDetailImg()) {
                $goods->setDetailImg(request::str('detailImg'));
            }

            if (request::str('detailImg') != $goods->getDetailImg()) {
                $goods->setDetailImg(request::str('detailImg'));
            }

            $price = request::float('goodsPrice', 0, 2) * 100;
            if ($price != $goods->getPrice()) {
                $goods->setPrice($price);
            }

            if (request::str('goodsBalance') != $goods->getBalance()) {
                $goods->setBalance(request::str('goodsBalance'));
            }

            if (request::bool('allowFree') != $goods->allowFree()) {
                $goods->setAllowFree(request::bool('allowFree'));
            }

            if (request::bool('allowPay') != $goods->allowPay()) {
                $goods->setAllowPay(request::bool('allowPay'));
            }

            if (request::str('goodsUnitTitle') != $goods->getUnitTitle()) {
                $goods->setUnitTitle(request::str('goodsUnitTitle'));
            }
        } else {
            $goods_data = [
                'agent_id' => 0,
                'name' => request::trim('goodsName'),
                'img' => request::trim('goodsImg'),

                'price' => request::bool('allowPay') ? request::float('goodsPrice', 0, 2) * 100 : 0,
                'extra' => [
                    'detailImg' => request::trim('detailImg'),
                    'unitTitle' => request::trim('goodsUnitTitle'),
                    'allowFree' => request::bool('allowFree') ? 1 : 0,
                    'allowPay' => request::bool('allowPay') ? 1 : 0,
                    'balance' => request::bool('allowFree') ? request::int('goodsBalance') : 0,
                ],
            ];

            $goods_data['agent_id'] = $agent->getId();

            //固定货道商品商品指定货道
            if (request::is_string('goodsLaneID')) {
                $goods_data['extra']['lottery'] = [
                    'size' => request::int('goodsLaneID'),
                ];
            }

            if (request::isset('costPrice')) {
                $goods_data['extra']['costPrice'] = request::float('costPrice', 0, 2) * 100;
            }

            $goods = \zovye\Goods::create($goods_data);
        }

        if ($goods && $goods->save()) {
            return ['msg' => '商品保存成功！'];
        }

        return error(State::ERROR, '商品保存失败！');
    }
}