<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (Job::refreshSettings()) {
    JSON::success('启动刷新任务成功！');
}

JSON::fail('启动刷新任务失败！');