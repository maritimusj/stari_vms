<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wx;

use zovye\api\common;
use zovye\App;
use zovye\model\agentModelObj;
use zovye\Request;
use function zovye\err;
use function zovye\settings;

class goods
{
    public static function list(agentModelObj $agent): array
    {
        $params = [
            'page' => Request::int('page'),
            'pagesize' => Request::int('pagesize', DEFAULT_PAGE_SIZE),
            'keywords' => Request::trim('keywords', '', true),
            'default_goods' => true,
        ];

        if (Request::bool('all')) {
            $params['agent_id'] = "*{$agent->getId()}";
        } else {
            $params['agent_id'] = $agent->getId();
        }

        $result = \zovye\domain\Goods::getList($params);
        $result['goods_edit'] = boolval(settings('goods.agent.edit', 0));
        $result['lottery'] = [
            'enabled' => App::isLotteryGoodsSupported(),
        ];

        return $result;
    }

    public static function detail(agentModelObj $agent): array
    {
        $goods_id = Request::int('id');

        $goods = \zovye\domain\Goods::get($goods_id);
        if (empty($goods) || $goods->getAgentId() !== $agent->getId()) {
            return err('找不到这个商品！');
        }

        return \zovye\domain\Goods::data($goods_id, ['fullPath']);
    }

    public static function delete(agentModelObj $agent): array
    {
        common::checkPrivileges($agent, 'F_sp');

        $goods = \zovye\domain\Goods::get(Request::int('id'));
        if (empty($goods)) {
            return err('找不到指定的商品');
        }

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

    public static function create(agentModelObj $agent): array
    {
        common::checkPrivileges($agent, 'F_sp');

        $s1 = 0;
        if (Request::bool(\zovye\domain\Goods::AllowFree)) {
            $s1 = \zovye\domain\Goods::setFreeBitMask($s1);
        }
        if (Request::bool(\zovye\domain\Goods::AllowPay)) {
            $s1 = \zovye\domain\Goods::setPayBitMask($s1);
        }
        if (Request::bool(\zovye\domain\Goods::AllowBalance)) {
            $s1 = \zovye\domain\Goods::setBalanceBitMask($s1);
        }
        if (Request::bool(\zovye\domain\Goods::AllowDelivery)) {
            $s1 = \zovye\domain\Goods::setDeliveryBitMask($s1);
        }

        $goods_id = Request::int('goodsId');
        if ($goods_id > 0) {
            $goods = \zovye\domain\Goods::get($goods_id);
            if (empty($goods)) {
                return err('找不到这个商品！');
            }

            $goods->setS1($s1);

            if ($goods->getAgentId() !== $agent->getId()) {
                return err('没有权限管理这个商品');
            }

            //彩票商品商品指定货道
            if (Request::isset('goodsLaneID')) {
                $goods->setExtraData('lottery.size', Request::int('goodsLaneID'));
                $goods->setExtraData('lottery.index', Request::int('goodsMcbIndex'));
                $goods->setExtraData('lottery.unit', Request::int('goodsSizeUnit'));
            } else {
                $goods->setExtraData('lottery');
            }

            // 计时商品
            if (Request::isset('duration')) {
                $goods->setExtraData('ts.duration', Request::int('duration'));
            } else {
                $goods->setExtraData('ts');
            }

            if (Request::isset('costPrice')) {
                $goods->setExtraData('costPrice', Request::float('costPrice', 0, 2) * 100);
            }

            if (Request::isset('discountPrice')) {
                $goods->setExtraData('discountPrice',  Request::float('discountPrice', 0, 2) * 100);
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

            if (Request::isset('goodsCW')) {
                $goods->setExtraData('cw', Request::bool('goodsCW') ? 1 : 0);
            }

            if (App::isBalanceEnabled()) {
                $goods->setExtraData('balance', max(0, Request::int('balance')));
            }
        
        } else {
            $goods_data = [
                'name' => Request::trim('goodsName'),
                'img' => Request::trim('goodsImg'),
                's1' => $s1,
                'price' => Request::float('goodsPrice', 0, 2) * 100,
                'extra' => [
                    'unitTitle' => Request::trim('goodsUnitTitle'),
                    'costPrice' => Request::float('costPrice', 0, 2) * 100,
                    'discountPrice' =>  Request::float('discountPrice', 0, 2) * 100,
                    'cw' => Request::bool('goodsCW') ? 1 : 0, //成本是否参与分佣
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

            if (App::isBalanceEnabled()) {
                $goods_data['extra']['balance'] = max(0, Request::int('balance'));
            }
        
            //彩票商品商品指定货道
            if (Request::isset('goodsLaneID')) {
                $goods_data['extra']['lottery']['size'] = Request::int('goodsLaneID');
                $goods_data['extra']['lottery']['index'] = Request::int('goodsMcbIndex');
                $goods_data['extra']['lottery']['unit'] = Request::int('goodsSizeUnit');
                $goods_data['extra']['costPrice'] = Request::float('costPrice', 0, 2) * 100;
            }

            //计时商品
            if (Request::isset('duration')) {
                $goods_data['extra']['ts']['duration'] = Request::int('duration');
            }

            $goods_data['extra']['type'] = Request::str('type');

            $goods = \zovye\domain\Goods::create($goods_data);
        }
        
        if ($goods && $goods->save()) {
            return ['msg' => '商品保存成功！'];
        }

        return err('商品保存失败！');
    }
}