<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

if (Migrate::detect()) {
    JSON::success(['redirect' => Util::url('migrate')]);
}