<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use DateTime;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');

if ($op == 'default') {
    $tpl_data = [
        'status_arr' => [
            Delivery::UNPAID => Delivery::formatStatus(Delivery::UNPAID),
            Delivery::PAYED => Delivery::formatStatus(Delivery::PAYED),
            Delivery::REFUND => Delivery::formatStatus(Delivery::REFUND),
            Delivery::SHIPPING => Delivery::formatStatus(Delivery::SHIPPING),
            Delivery::CONFIRMED => Delivery::formatStatus(Delivery::CONFIRMED),
            Delivery::RETURNING => Delivery::formatStatus(Delivery::RETURNING),
            Delivery::RETURNED => Delivery::formatStatus(Delivery::RETURNED),
            Delivery::FINISHED => Delivery::formatStatus(Delivery::FINISHED),
        ],
    ];

    $query = Delivery::query();

    if (request::has('keyword')) {
        $keyword = request::trim('keyword');
        $query->whereOr([
            'order_no LIKE' => "%$keyword%",
            'name LIKE' => "%$keyword%",
            'phone_num LIKE' => "%$keyword%",
            'address LIKE' => "%$keyword%",
        ]);
        $tpl_data['s_keyword'] = $keyword;

    }

    if (request::isset('status')) {
        $status = request::int('status');
        if ($status >= 0) {
            $query->where(['status' => $status]);
        }     
        $tpl_data['s_status'] = $status;   
    }

    if (request::has('user_id')) {
        $user_id = request::int('user_id');
        $user = User::get($user_id);
        if (empty($user)) {
            Util::resultAlert('找不到这个用户！', 'error');
        }

        $query->where(['user_id' => $user->getId()]);

        $tpl_data['user_res'] = $user->profile(false);
        $tpl_data['s_user_id'] = $user_id;
    }

    $limit = request::array('datelimit');
    if ($limit['start']) {
        $start = DateTime::createFromFormat('Y-m-d H:i:s', $limit['start'] . ' 00:00:00');
        if ($start) {
            $tpl_data['s_start_date'] = $start->format('Y-m-d');
            $query->where(['createtime >=' => $start->getTimestamp()]);
        }
    }

    if ($limit['end']) {
        $end = DateTime::createFromFormat('Y-m-d H:i:s', $limit['end'] . ' 00:00:00');
        if ($end) {
            $tpl_data['s_end_date'] = $end->format('Y-m-d');
            $end->modify('next day');
            $query->where(['createtime <' => $end->getTimestamp()]);
        }
    }

    $total = $query->count();

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

    $query->page($page, $page_size);
    $query->orderBy('id DESC');

    $orders = [];
    /** @var deliveryModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $data = [
            'id' => $entry->getId(),
            'order_no' => $entry->getOrderNo(),
            'num' => $entry->getNum(),
            'recipient' => [
                'name' => $entry->getName(),
                'phoneNum' => $entry->getPhoneNum(),
                'address' => $entry->getAddress(),
            ],
            'goods' => $entry->getRawGoodsData(),
            'status' => $entry->getStatus(),
            'status_formatted' => $entry->getFormattedStatus(),
            'createtime_formatted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
        ];

        $user = $entry->getUser();
        if ($user) {
            $data['user'] = $user->profile(false);
        }

        $orders[] = $data;
    }

    $pager = We7::pagination($total, $page, $page_size);
    if (stripos($pager, '&filter=1') === false) {
        $filter = [
            'user_id' => $user_id,
            'status' => $status,
            'keyword' => $keyword,
            'datelimit[start]' => isset($start) ? $start->format('Y-m-d') : '',
            'datelimit[end]' => isset($end) ? $end->format('Y-m-d') : '',
            'filter' => 1,
        ];

        foreach ($filter as $index => $entry) {
            if (empty($entry)) {
                unset($filter[$index]);
            }
        }
        $params_str = http_build_query($filter);
        $pager = preg_replace('#href="(.*?)"#', 'href="${1}&' . $params_str . '"', $pager);
    }


    $tpl_data['backer'] = $keyword || $limit['start'] || $limit['end'] || $tpl_data['s_user_id'] || isset($tpl_data['s_status']);
    $tpl_data['pager'] = $pager;
    $tpl_data['orders'] = $orders;

    app()->showTemplate('web/mall/default', $tpl_data);

} elseif ($op == 'setStatus') {

    $id = request::int('id');

    $delivery = Delivery::get($id);
    if (!$delivery) {
        JSON::fail('找不到这个商品订单！');
    }

    $status = request::int('status');
    $delivery->setStatus($status);
    if ($delivery->save()) {
        JSON::success([
            'msg' => Delivery::formatStatus($status),
            'status' => $status,
        ]);
    }
    JSON::fail('操作失败！');
}