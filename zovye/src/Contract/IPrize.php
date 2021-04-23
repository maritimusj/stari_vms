<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\Contract;

use zovye\model\userModelObj;

interface IPrize
{
    public function isValid(array $params);

    public function desc();

    public function give(userModelObj $user, array $params = []);
}
