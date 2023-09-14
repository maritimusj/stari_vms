<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\domain;

use zovye\base\ModelFactory;
use function zovye\m;

class AgentApplication extends Base
{
    const WAIT = 0;
    const CHECKED = 1;

    public static function model(): ModelFactory
    {
        return m('agent_app');
    }
}