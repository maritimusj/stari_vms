<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\modelObj;

use function zovye\tb;

/**
 * Class agent_msgModelObj
 * @package zovye
 * @method getAgentId()
 * @method setUpdatetime(int $time)
 * @method getMsgId()
 * @method getTitle()
 * @method getContent()
 * @method getCreatetime()
 * @method getUpdatetime()
 */
class agent_msgModelObj extends modelObj
{
    /** @var int */
    protected $id;

    protected $uniacid;

    protected $agent_id;

    protected $msg_id;

    protected $title;

    protected $content;

    protected $updatetime;

    protected $createtime;

    public static function getTableName($readOrWrite): string
    {
        return tb('agent_msg');
    }
}