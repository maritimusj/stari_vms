<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\account\MoscaleAccount;

defined('IN_IA') or exit('Access Denied');

$data = Request::raw();
Log::debug('moscale', $data);

MoscaleAccount::cb(json_decode($data, true));


