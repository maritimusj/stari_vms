<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\device_feedbackModelObj;

$id = Request::int('id');

/** @var device_feedbackModelObj $res */
$res = m('device_feedback')->findOne(['id' => $id]);
if ($res) {
    if ($res->getRemark() != '') {
        JSON::fail('已处理该反馈！');
    }
} else {
    JSON::fail('找不到该记录！');
}

$content = app()->fetchTemplate(
    'web/device/deal_fb',
    [
        'chartid' => Util::random(10),
        'id' => $res->getId(),
        'text' => $res->getText(),
    ]
);

JSON::success(['content' => $content]);