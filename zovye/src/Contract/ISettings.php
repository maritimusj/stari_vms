<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\Contract;

interface ISettings
{
    public function set($key, $val);

    public function get($key, $default = null);

    public function has($key);

    public function remove($key);

    public function pop($key, $default = null);
}
