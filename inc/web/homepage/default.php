<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

Response::showTemplate('web/home/default', [
    'commission_enabled' => App::isCommissionEnabled(),
    'url' => Util::url('homepage'),
]);
