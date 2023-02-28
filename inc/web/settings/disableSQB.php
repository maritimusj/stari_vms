<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

if (updateSettings('pay.SQB', [])) {
    JSON::success('成功！');
}

JSON::fail('失败！');