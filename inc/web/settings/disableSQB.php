<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (updateSettings('pay.SQB', [])) {
    JSON::success('成功！');
}

JSON::fail('失败！');