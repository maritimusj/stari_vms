<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

//处理反馈
use zovye\domain\DeviceFeedback;
use zovye\model\device_feedbackModelObj;

$id = Request::int('id');

$remark = Request::trim('remark');
if (empty($remark)) {
    JSON::fail('请输入处理内容！');
}

/** @var device_feedbackModelObj $res */
$res = DeviceFeedback::findOne(['id' => $id]);

if ($res) {
    $res->setRemark($remark);
    $res->save();

    JSON::success(['id' => $id, 'remark' => $remark]);
}

JSON::fail('error');