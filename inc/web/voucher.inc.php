<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;
use Exception;
use zovye\model\goods_voucherModelObj;

$op = request::op('default');
$type = strval(request('type'));

$tpl_data = [
    'op' => $op,
    'type' => $type,
];

if ($op == 'default') {

    if (empty($type)) {
        $this->showTemplate("web/goods_voucher/default", $tpl_data);
    }

    $this->showTemplate("web/goods_voucher/logs", $tpl_data);

} elseif ($op == 'logs') {

    $params = [];

    $voucher_id = request::int('voucherId');
    if ($voucher_id > 0) {
        $params['voucherId'] = $voucher_id;
    }

    if (isset($type)) {
        $params['type'] = $type;
    }

    $params['page'] = max(1, request::int('page'));
    $params['pagesize'] = max(1, request::int('pagesize', DEFAULT_PAGESIZE));

    $res = GoodsVoucher::logList($params);
    if (is_error($res)) {
        JSON::fail($res);
    }

    $pager = We7::pagination($res['total'], $res['page'], $res['pagesize']);

    JSON::success([
        'type' => $type,
        'pager' => $pager,
        'logs' => $res['logs'],
    ]);

} elseif ($op == 'list') {

    $query = GoodsVoucher::query();

    $keywords = request::trim('keywords');
    if ($keywords) {
        $condition['goods_name LIKE'] = '%' . $keywords . '%';
    }

    $page = max(1, request::int('page'));
    $page_size = max(1, request::int('pagesize', DEFAULT_PAGESIZE));

    $total = $query->count();
    if (ceil($total / $page_size) < $page) {
        $page = 1;
    }

    $tpl_data['pager'] = We7::pagination($total, $page, $page_size);

    $tpl_data['agent_levels'] = settings('agent.levels');

    $vouchers = [];

    /** @var goods_voucherModelObj $entry */
    foreach ($query->page($page, $page_size)->orderBy('id DESC')->findAll() as $entry) {
        $vouchers[] = GoodsVoucher::format($entry);
    }

    $tpl_data['vouchers'] = $vouchers;

    JSON::success($tpl_data);

} elseif ($op == 'limitGoods') {

    $id = request::int('id');
    $voucher = GoodsVoucher::get($id);
    $limitGoodsIds = $voucher->getExtraData('limitGoods', []);

    $list = [];
    foreach ((array)$limitGoodsIds as $id) {
        $goods = Goods::get($id);
        if (isset($goods)) {
            $list[] = Goods::format($goods, false, true);
        }
    }

    JSON::success($list);

} elseif ($op == 'detail') {

    $id = request::int('id');
    $voucher = GoodsVoucher::get($id);
    if ($voucher) {
        $data = GoodsVoucher::format($voucher);
        $data['limitGoods'] = array_values((array)$voucher->getExtraData('limitGoods', []));
        if ($voucher->getBegin() > 0) {
            $data['begin'] = date('Y-m-d', $voucher->getBegin());
        }
        if ($voucher->getEnd() > 0) {
            $data['end'] = date('Y-m-d', $voucher->getEnd());
        }

        JSON::success($data);
    }

    JSON::fail('找不到指定的提货码！');

} elseif ($op == 'add' || $op == 'edit') {

    if ($op == 'edit') {
        $tpl_data['voucher_id'] = request::int('id');
    }

    $this->showTemplate("web/goods_voucher/edit", $tpl_data);

} elseif ($op == 'save') {

    $res = Util::transactionDo(function () {
        $goods_id = request::int('goodsId');
        $goods = Goods::get($goods_id);
        if (empty($goods)) {
            return error(State::ERROR, '要绑定的商品不存在！');
        }

        $ids = request::is_array('goods') ? request::array('goods') : [];
        $ids = array_map(function ($id) {
            return intval($id);
        }, $ids);

        $ids = array_filter(array_values($ids), function ($id) {
            return $id != -1;
        });


        $begin = 0;
        $end = 0;

        if (request('validate')) {
            try {
                $begin = (new DateTime(request('begin')))->getTimestamp();
            } catch (Exception $e) {
            }
            try {
                $end = (new DateTime(request('end')))->getTimestamp();
            } catch (Exception $e) {
            }
        }

        $total = request::int('total');
        $original_limit_goods_dds = [];

        if (request('id') > 0) {
            $id = request::int('id');
            $voucher = GoodsVoucher::get($id);
            if (empty($voucher)) {
                return error(State::ERROR, '找不到指定的提货码！');
            }

            $original_limit_goods_dds = (array)$voucher->getExtraData('limitGoods', []);

            $voucher->setGoodsId($goods_id);
            $voucher->setTotal($total);
            $voucher->setBegin($begin);
            $voucher->setEnd($end);
            $voucher->setExtraData('limitGoods', array_values($ids));

            if (!$voucher->save()) {
                return error(State::ERROR, '保存失败！');
            }
        } else {
            $voucher = GoodsVoucher::create(null, $goods, $total, $begin, $end, $ids);
            if (empty($voucher)) {
                return error(State::ERROR, '创建失败！');
            }
        }

        $voucher_id = intval($voucher->getId());

        //在赠送提货券的商品上做记录
        $v = array_diff($original_limit_goods_dds, $ids);
        foreach ($v as $id) {
            $goods = Goods::get($id);
            if ($goods) {
                $x = (array)$goods->getExtraData('vouchers', []);
                $x = array_filter($x, function ($id) use ($voucher_id) {
                    return $id != $voucher_id;
                });
                $goods->setExtraData('vouchers', $x);
                if (!$goods->save()) {
                    return error(State::ERROR, '保存数据失败！');
                }
            }
        }

        foreach ($ids as $id) {
            $goods = Goods::get($id);
            if ($goods) {
                $v = (array)$goods->getExtraData('vouchers', []);
                $v[] = $voucher_id;
                $goods->setExtraData('vouchers', array_unique($v));
                if (!$goods->save()) {
                    return error(State::ERROR, '保存数据失败！');
                }
            }
        }

        return true;
    });

    if (is_error($res)) {
        JSON::fail($res);
    }
    JSON::success('保存成功 ！');

} elseif ($op == 'enable') {

    $id = request::int('id');
    $voucher = GoodsVoucher::get($id);
    if ($voucher) {
        $enabled = $voucher->getEnable();
        $voucher->setEnable(!$enabled);
        $voucher->save();

        JSON::success(['msg' => '操作成功 ！', 'enabled' => $voucher->getEnable()]);
    }

    JSON::fail('操作失败！');

} elseif ($op == 'remove') {

    $id = request::int('id');
    $voucher = GoodsVoucher::get($id);
    if ($voucher) {
        $res = Util::transactionDo(function () use ($voucher) {
            $voucher->destroy();
            return true;
        });
        if (!is_error($res)) {
            JSON::success('操作成功 ！');
        }
    }

    JSON::success('操作失败 ！');

} elseif ($op == 'assign') {

    $id = request::int('id');
    $voucher = GoodsVoucher::get($id);
    if ($voucher) {
        $this->showTemplate('web/goods_voucher/assign', [
            'voucher' => GoodsVoucher::format($voucher, true),
            'multi_mode' => settings('advs.assign.multi') ? 'true' : '',
            'assign_data' => json_encode($voucher->getExtraData('assigned', [])),
            'agent_url' => $this->createWebUrl('agent'),
            'group_url' => $this->createWebUrl('device', array('op' => 'group')),
            'tag_url' => $this->createWebUrl('tags'),
            'device_url' => $this->createWebUrl('device'),
            'save_url' => $this->createWebUrl('voucher', array('op' => 'saveAssignData')),
            'back_url' => $this->createWebUrl('voucher'),
        ]);
    }

    Util::itoast('找不到指定的提货码！', Util::url('voucher'), 'error');

} elseif ($op == 'saveAssignData') {

    $id = request::int('id');
    $voucher = GoodsVoucher::get($id);
    if ($voucher) {
        $data = is_string(request('data')) ? json_decode(htmlspecialchars_decode(request('data')), true) : request('data');
        if ($voucher->setExtraData('assigned', $data) && $voucher->save()) {
            JSON::success('保存成功 ！');
        }
        JSON::success('保存失败 ！');
    }

    Util::itoast('找不到指定的提货码！', Util::url('voucher'), 'error');
}