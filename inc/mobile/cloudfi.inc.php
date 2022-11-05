<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

Log::debug('cloudFI', [
    'raw' => request::raw(),
]);

if (App::isCloudFIEnabled()) {
    CloudFIAccount::cb(request::json());
}

exit(REQUEST_ID);