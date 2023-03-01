<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\account\YiDaoAccount;

YiDaoAccount::cb([
    'key' => Request::str('key'),
    'develop_appid' => Request::str('develop_appid'),
    'auth_open_id' => Request::str('auth_open_id'),
    'nickname' => Request::str('nickname'),
    'appid' => Request::str('appid'),
    'appname' => Request::str('appname'),
    'openid' => Request::str('openid'),
    'state' => Request::str('state'),
]);

echo REQUEST_ID;