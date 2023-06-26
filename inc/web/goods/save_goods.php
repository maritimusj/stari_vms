<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$params = [];
parse_str(Request::raw(), $params);

if (empty($params['goodsName'])) {
    Util::itoast('商品名称不能为空！', '', 'error');
}

if ($params['agentId']) {
    $agent = Agent::get($params['agentId']);
    if (empty($agent)) {
        Util::itoast('找不到这个代理商！', '', 'error');
    }
}

if ($params['costPrice'] < 0 || $params['goodsPrice'] < 0 || $params['costPrice'] > $params['goodsPrice']) {
    Util::itoast('成本价不能高于单价！', '', 'error');
}

if ($params['discountPrice'] < 0 || $params['goodsPrice'] < 0 ||
    ($params['discountPrice'] > 0 && $params['discountPrice'] >= $params['goodsPrice'])) {
    Util::itoast('优惠价不能高于或者等于单价！', '', 'error');
}

$s1 = 0;
if ($params[Goods::AllowFree]) {
    $s1 = Goods::setFreeBitMask($s1);
}
if ($params[Goods::AllowPay]) {
    $s1 = Goods::setPayBitMask($s1);
}
if ($params[Goods::AllowBalance]) {
    $s1 = Goods::setBalanceBitMask($s1);
}
if ($params[Goods::AllowDelivery]) {
    $s1 = Goods::setDeliveryBitMask($s1);
}

if (isset($params['goodsId'])) {
    $goods = Goods::get($params['goodsId']);
    if (empty($goods)) {
        Util::itoast('找不到这个商品！', '', 'error');
    }
    if ($goods->getType() == Goods::FlashEgg) {
        $price = round(floatval($params['goodsPrice']) * 100);
        if ($price != $goods->getPrice()) {
            $goods->setPrice($price);
        }

        $img = trim($params['goodsImg']);
        if ($img != $goods->getImg()) {
            $goods->setImg($img);
        }

        $images = $params['gallery'];
        if ($images) {
            $goods->setDetailImg($images[0]);
            $goods->setGallery($images);
        } else {
            $goods->setDetailImg('');
            $goods->setGallery();
        }
        if ($params['goodsUnitTitle'] != $goods->getUnitTitle()) {
            $goods->setUnitTitle($params['goodsUnitTitle']);
        }

        if (isset($params['costPrice'])) {
            $goods->setExtraData('costPrice', floatval($params['costPrice'] * 100));
        }

        if (isset($params['discountPrice'])) {
            $goods->setExtraData('discountPrice', round(floatval($params['discountPrice']) * 100));
        }
    } else {
        $goods->setS1($s1);

        if (isset($params['goodsSize'])) {
            if ($params['goodsSize'] != $goods->getExtraData('lottery.size')) {
                $goods->setExtraData('lottery.size', intval($params['goodsSize']));
            }
        }

        if ($params['goodsMcbIndex'] != '') {
            if ($params['goodsMcbIndex'] != $goods->getExtraData('lottery.index')) {
                $goods->setExtraData('lottery.index', intval($params['goodsMcbIndex']));
            }
        } else {
            $goods->setExtraData('lottery.index');
        }

        if (isset($params['goodsSizeUnit'])) {
            if ($params['goodsSizeUnit'] != $goods->getExtraData('lottery.unit')) {
                $goods->setExtraData('lottery.unit', intval($params['goodsSizeUnit']));
            }
        }

        if (App::isBalanceEnabled()) {
            if (isset($params['balance'])) {
                $goods->setExtraData('balance', max(0, intval($params['balance'])));
            }
        }

        $price = round(floatval($params['goodsPrice']) * 100);
        if ($price != $goods->getPrice()) {
            $goods->setPrice($price);
        }

        if (isset($params['costPrice'])) {
            $goods->setExtraData('costPrice', floatval($params['costPrice'] * 100));
        }

        if (App::isMultiGoodsItemEnabled() && is_numeric($params['itemNum'])) {
            $goods->setExtraData('itemNum', intval($params['itemNum']));
        } else {
            $goods->setExtraData('itemNum', '');
        }

        $goods->setExtraData('cw', empty($params['goodsCW']) ? 0 : 1);

        if (isset($params['discountPrice'])) {
            $goods->setExtraData('discountPrice', round(floatval($params['discountPrice']) * 100));
        }

        if ($params['agentId'] != $goods->getAgentId()) {
            $goods->setAgentId($params['agentId']);
        }

        if ($params['goodsName'] != $goods->getName()) {
            $goods->setName($params['goodsName']);
        }

        $img = trim($params['goodsImg']);
        if ($img != $goods->getImg()) {
            $goods->setImg($img);
        }

        $images = $params['gallery'];
        if ($images) {
            $goods->setDetailImg($images[0]);
            $goods->setGallery($images);
        } else {
            $goods->setDetailImg('');
            $goods->setGallery();
        }

        if ($params['syncAll'] != $goods->getSync()) {
            $goods->setSync($params['syncAll']);
        }

        if ($params['goodsUnitTitle'] != $goods->getUnitTitle()) {
            $goods->setUnitTitle($params['goodsUnitTitle']);
        }
    }
} else {
    $data = [
        'agent_id' => !empty($agent) ? $agent->getId() : 0,
        'name' => trim($params['goodsName']),
        'img' => trim($params['goodsImg']),
        'sync' => $params['syncAll'] ? 1 : 0,
        'price' => round(floatval($params['goodsPrice']) * 100),
        's1' => $s1,
        'extra' => [
            'unitTitle' => trim($params['goodsUnitTitle']),
            'balance' => intval($params['goodsBalance']),
        ],
    ];

    $images = $params['gallery'];
    if ($images) {
        $data['extra']['detailImg'] = $images[0];
        $data['extra']['gallery'] = $images;
    }

    if (isset($params['goodsSize'])) {
        $data['extra']['lottery'] = [
            'size' => intval($params['goodsSize']),
            'index' => $params['goodsMcbIndex'] ? intval($params['goodsMcbIndex']) : '',
            'unit' => intval($params['goodsSizeUnit']),
        ];
    }

    if (isset($params['costPrice'])) {
        $data['extra']['costPrice'] = round(floatval($params['costPrice']) * 100);
    }

    if (App::isMultiGoodsItemEnabled() && is_numeric($params['itemNum'])) {
        $data['extra']['itemNum'] = intval($params['itemNum']);
    }

    //成本是否参与分佣
    $data['extra']['cw'] = empty($params['goodsCW']) ? 0 : 1;

    if (App::isBalanceEnabled()) {
        $data['extra']['balance'] = max(0, intval($params['balance']));
    }

    if (isset($params['discountPrice'])) {
        $data['extra']['discountPrice'] = round(floatval($params['discountPrice']) * 100);
    }

    $data['extra']['type'] = strval($params['type']);

    $goods = Goods::create($data);
}

if (!empty($goods)) {
    if ($goods->isFlashEgg()) {
        $goods->setExtraData('redirect_url', request::trim('redirectUrl'));
    }
    if (App::isGDCVMachineEnabled()) {
        $goods->setCVMachineItemCode(Request::trim('GDCVMachineItemCode'));
    }
    if ($goods->save()) {
        if ($params['syncAll']) {
            Job::goodsClone($goods->getId());
        }
        Util::itoast('商品保存成功！', '', 'success');
    }
}

Util::itoast('商品保存失败！', '', 'error');