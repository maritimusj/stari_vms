<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use DateTime;
use Exception;
use zovye\model\userModelObj;

$query = User::query();

$user_types = Request::array('types');
if ($user_types) {
    $query->where(['app' => $user_types]);
}

$start = Request::str('start');
if ($start) {
    try {
        $s_date = new DateTime($start);
        $query->where(['createtime >=' => $s_date->getTimestamp()]);
    } catch (Exception $e) {
    }
}

$end = Request::str('end');
if ($end) {
    try {
        $e_date = new DateTime($end);
        $query->where(['createtime <' => $e_date->getTimestamp()]);
    } catch (Exception $e) {
    }
}

$step = Request::str('step');
if (empty($step)) {
    $total = $query->count();
    $content = app()->fetchTemplate('web/common/export', [
        'api_url' => Util::url('user', [ 'op' => 'user_export_do', 'types' => $user_types, 'start' => $start, 'end' => $end]),
        'total' => $total,
        'serial' => (new DateTime())->format('YmdHis'),
    ]);

    JSON::success([
        'title' => "导出用户信息",
        'content' => $content,
    ]);
}

$serial = Request::str('serial');
if (empty($serial)) {
    JSON::fail("缺少serial");
}

$filename = "$serial.csv";
$dirname = "export/user/";
$full_filename = Util::getAttachmentFileName($dirname, $filename);

if ($step == 'load') {
    $last_id = Request::int('last');
    $query =  $query->where(['id >' => $last_id])->limit(10)->orderBy('id asc');

    $result = [];
    $n = 0;
    $app_titles = [
        0 => '公众号',
        1 => '小程序',
        2 => '支付宝',
        3 => '抖音',
        10 => '第三方API',
        15 => '第三方公众号',
    ];
    $getAppTitle = function($i) use($app_titles) {
        return $app_titles[$i] ?? '';
    };
    $getTitle = function($i) {
        switch($i) {
            case 1: return '男';
            case 2: return '女';
            default: return '未知';
        }
    };
    /** @var userModelObj $user */
    foreach ($query->findAll() as $user) {
        $last_id = $user->getId();
        $data = [
            'id' => $user->getId(),
            'app' => $getAppTitle($user->getApp()),
            'openid' => $user->getOpenid(),
            'nickname' => $user->getNickname(),
            'sex' => $getTitle($user->settings('fansData.sex')),
            'mobile' => $user->getMobile(),
            'from' => '',
            'created_at' => date('Y-m-d H:i:s', $user->getCreatetime()),
        ];
        //用户来源信息
        $from_data = $user->get('fromData', []);
        if ($from_data) {
            if (!empty($from_data['device'])) {
                $data['from'] = "{$from_data['device']['name']}";
            } elseif (!empty($from_data['account'])) {
                $data['from'] = "{$from_data['account']['name']}，{$from_data['account']['title']}";
            }
        }
        $result[] = $data;
        $n++;
    }

    Util::exportExcelFile($full_filename, [
        '#',
        '归属',
        'openid',
        '昵称',
        '性别',
        '手机号码',
        '来源',
        '创建时间',
    ], $result);

    JSON::success([
        'num' => 10,
        'success' => $n,
        'last' => $last_id,
    ]);

} elseif ($step == 'download') {
    JSON::success([
        'url' => Util::toMedia("$dirname$filename"),
    ]);
}