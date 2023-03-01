<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wx;

use zovye\App;
use zovye\Request;
use function zovye\err;
use function zovye\settings;

class goods
{
    public static function list(): array
    {
        $user = common::getAgentOrPartner();

        common::checkCurrentUserPrivileges('F_sp');

        $params = [
            'page' => Request::int('page'),
            'pagesize' => Request::int('pagesize', DEFAULT_PAGE_SIZE),
            'keywords' => Request::trim('keywords', '', true),
            'default_goods' => true,
        ];

        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();

        if (Request::bool('all')) {
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

        $goods_id = Request::int('id');

        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();
        $goods = \zovye\Goods::get($goods_id);
        if (empty($goods) || $goods->getAgentId() !== $agent->getId()) {
            return err('找不到这个商品！');
        }

        return \zovye\Goods::data($goods_id, ['fullPath']);
    }

    public static function delete(): array
    {
        $user = common::getAgentOrPartner();

        common::checkCurrentUserPrivileges('F_sp');

        $goods = \zovye\Goods::get(Request::int('id'));
        if (empty($goods)) {
            return err('找不到指定的商品');
        }

        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();
        if ($goods->getAgentId() !== $agent->getId()) {
            return err('没有权限管理这个商品');
        }

        if ($goods->isFlashEgg()) {
            return ['msg' => '闪蛋商品不能单独删除！'];
        }

        if ($goods->destroy()) {
            return ['msg' => '商品删除成功！'];
        }

        return err('商品删除失败！');
    }

    public static function create(): array
    {
        $user = common::getAgentOrPartner();

        common::checkCurrentUserPrivileges('F_sp');

        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();

        $s1 = 0;
        if (Request::bool(\zovye\Goods::AllowFree)) {
            $s1 = \zovye\Goods::setFreeBitMask($s1);
        }
        if (Request::bool(\zovye\Goods::AllowPay)) {
            $s1 = \zovye\Goods::setPayBitMask($s1);
        }
        if (Request::bool(\zovye\Goods::AllowBalance)) {
            $s1 = \zovye\Goods::setBalanceBitMask($s1);
        }
        if (Request::bool(\zovye\Goods::AllowDelivery)) {
            $s1 = \zovye\Goods::setDeliveryBitMask($s1);
        }

        $goods_id = Request::int('goodsId');
        if ($goods_id > 0) {
            $goods = \zovye\Goods::get($goods_id);
            if (empty($goods)) {
                return err('找不到这个商品！');
            }

            $goods->setS1($s1);

            if ($goods->getAgentId() !== $agent->getId()) {
                return err('没有权限管理这个商品');
            }

            //固定货道商品商品指定货道
            if (Request::isset('goodsLaneID')) {
                if (Request::int('goodsLaneID') != $goods->getExtraData('lottery.size')) {
                    $goods->setExtraData('lottery.size', Request::int('goodsLaneID'));
                }
            }

            if (Request::isset('goodsMcbIndex')) {
                if (Request::int('goodsMcbIndex') != $goods->getExtraData('lottery.index')) {
                    $goods->setExtraData('lottery.index', Request::int('goodsMcbIndex'));
                }
            }

            if (Request::isset('costPrice')) {
                $goods->setExtraData('costPrice', Request::float('costPrice', 0, 2) * 100);
            }

            if (Request::str('goodsName') != $goods->getName()) {
                $goods->setName(Request::str('goodsName'));
            }

            if (Request::str('goodsImg') != $goods->getImg()) {
                $goods->setImg(Request::str('goodsImg'));
            }

            if (Request::has('detailImg')) {
                $detailImg = Request::trim('detailImg');
                if ($detailImg != $goods->getDetailImg()) {
                    $goods->setDetailImg($detailImg);
                    $goods->setGallery([$detailImg]);
                }
            } elseif (Request::is_array('gallery')) {
                $gallery = Request::array('gallery');
                if ($gallery) {
                    $goods->setDetailImg($gallery[0]);
                    $goods->setGallery($gallery);
                }
            }

            $price = Request::float('goodsPrice', 0, 2) * 100;
            if ($price != $goods->getPrice()) {
                $goods->setPrice($price);
            }

            if (Request::str('goodsUnitTitle') != $goods->getUnitTitle()) {
                $goods->setUnitTitle(Request::str('goodsUnitTitle'));
            }
        } else {
            $goods_data = [
                'name' => Request::trim('goodsName'),
                'img' => Request::trim('goodsImg'),
                's1' => $s1,
                'price' => Request::float('goodsPrice', 0, 2) * 100,
                'extra' => [
                    'unitTitle' => Request::trim('goodsUnitTitle'),
                ],
            ];

            $goods_data['agent_id'] = $agent->getId();

            if (Request::has('detailImg')) {
                $detailImg = Request::trim('detailImg');
                $goods_data['extra']['detailImg'] = $detailImg;
                $goods_data['extra']['gallery'] = [$detailImg];
            }

            if (Request::is_array('gallery')) {
                $gallery = Request::array('gallery');
                if ($gallery) {
                    $goods_data['extra']['detailImg'] = $gallery[0];
                    $goods_data['extra']['gallery'] = $gallery;
                }
            }

            //固定货道商品商品指定货道
            if (Request::is_string('goodsLaneID')) {
                $goods_data['extra']['lottery']['size'] = Request::int('goodsLaneID');
            }
            if (Request::has('goodsMcbIndex')) {
                $goods_data['extra']['lottery']['index'] = Request::int('goodsMcbIndex');
            }
            if (Request::isset('costPrice')) {
                $goods_data['extra']['costPrice'] = Request::float('costPrice', 0, 2) * 100;
            }

            $goods_data['extra']['type'] = Request::str('type');

            $goods = \zovye\Goods::create($goods_data);
        }
        
        if ($goods && $goods->save()) {
            return ['msg' => '商品保存成功！'];
        }

        return err('商品保存失败！');
    }
}