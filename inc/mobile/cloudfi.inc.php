<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\account\CloudFIAccount;

Log::debug('cloudFI', [
    'raw' => Request::raw(),
]);

if (App::isCloudFIEnabled()) {
    CloudFIAccount::cb(Request::json());
}

exit(REQUEST_ID);