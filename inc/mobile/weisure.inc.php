<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

Log::debug('weisure', [
    'raw' => request::raw(),
    'userAction' => request::json('userAction'),
    'actionTime' => request::json('actionTime'),
    'outerUserId' => request::json('outerUserId'),
]);

if (App::isWeiSureEnabled()) {
    WeiSureAccount::cb(request::json());
} else {
    Log::debug('weisure', [
        'error' => '微保没有启用！',
    ]);
}

exit(WeiSureAccount::ResponseOk);