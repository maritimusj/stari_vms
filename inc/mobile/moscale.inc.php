<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\account\MoscaleAccount;

$data = Request::raw();
Log::debug('moscale', $data);

MoscaleAccount::cb(json_decode($data, true));


