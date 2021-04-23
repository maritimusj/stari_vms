<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$data = request::raw();
Util::logToFile('moscale', $data);

MoscaleAccount::cb(json_decode($data, true));


