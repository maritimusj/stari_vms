<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

if (Job::refreshSettings()) {
    JSON::success('启动刷新任务成功！');
}

JSON::success('启动刷新任务失败！');