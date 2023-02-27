<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

app()->showTemplate('web/home/default', [
    'commission_enabled' => App::isCommissionEnabled(),
    'url' => Util::url('homepage'),
]);
