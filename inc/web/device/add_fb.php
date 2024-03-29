<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\DeviceFeedback;
use zovye\model\device_feedbackModelObj;
use zovye\util\Util;

$id = Request::int('id');

/** @var device_feedbackModelObj $res */
$res = DeviceFeedback::get($id);
if ($res) {
    if (!empty($res->getRemark())) {
        JSON::fail('已处理该反馈！');
    }
} else {
    JSON::fail('找不到该记录！');
}

Response::templateJSON(
    'web/device/deal_fb',
    '',
    [
        'chartId' => Util::random(10),
        'id' => $res->getId(),
        'text' => $res->getText(),
    ]
);