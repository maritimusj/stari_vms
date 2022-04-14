<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

YiDaoAccount::cb([
    'key' => request::str('key'),
    'develop_appid' => request::str('develop_appid'),
    'auth_open_id' => request::str('auth_open_id'),
    'nickname' => request::str('nickname'),
    'appid' => request::str('appid'),
    'appname' => request::str('appname'),
    'openid' => request::str('openid'),
    'state' => request::str('state'),
]);

echo REQUEST_ID;