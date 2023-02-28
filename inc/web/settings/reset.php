<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

Migrate::reset();
if (Migrate::detect()) {
    JSON::success(['redirect' => Util::url('migrate')]);
}

JSON::success('已重置！');