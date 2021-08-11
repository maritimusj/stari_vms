<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

Util::logToFile('snto', [
    'raw' => request::raw(),
]);