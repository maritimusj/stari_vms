<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\account\CloudFIAccount;

defined('IN_IA') or exit('Access Denied');

Log::debug('cloudFI', [
    'raw' => Request::raw(),
]);

if (App::isCloudFIEnabled()) {
    CloudFIAccount::cb(Request::json());
}

exit(REQUEST_ID);