<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

Response::showTemplate('misc/data', [
    'api_url' => Util::murl('app', ['op' => 'data_vw']),
]);