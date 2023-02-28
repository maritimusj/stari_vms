<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

//处理反馈
use zovye\model\device_feedbackModelObj;

$id = request::int('id');
$remark = request::trim('remark');
if (empty($remark)) {
    JSON::fail('请输入处理内容！');
}

/** @var device_feedbackModelObj $res */
$res = m('device_feedback')->findOne(['id' => $id]);

if ($res) {
    $res->setRemark($remark);
    $res->save();

    JSON::success(['id' => $id, 'remark' => $remark]);
} else {
    JSON::fail('error');
}