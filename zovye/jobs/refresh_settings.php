<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\refresh_settings;

use zovye\CtrlServ;
use zovye\Log;
use zovye\request;

$op = request::op('default');

if ($op == 'refresh_settings' && CtrlServ::checkJobSign($data)) {
    Log::debug('refresh_settings', ['start']);
}