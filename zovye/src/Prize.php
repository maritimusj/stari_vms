<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\Contract\IPrize;
use zovye\model\prizelistModelObj;
use zovye\model\userModelObj;

class Prize
{

    /**
     * 随机抽取奖品给指定用户
     * @param userModelObj $user
     * @return mixed
     */
    public static function give(userModelObj $user)
    {
        static $prizes = null;
        static $max = 0;

        if (empty($prizes)) {

            $query = m('prizelist')->query();
            $query->where(We7::uniacid(['enabled' => 1]));
            $query->where('(max_count=0 OR total < max_count)');

            $now = time();
            $query->where("(begin_time=0 OR begin_time<={$now}) AND (end_time=0 OR end_time>{$now})");

            $max = max(100, intval($query->get('sum(percent)')));

            $query->orderBy('id desc');
            $prizes = $query->findAll();
        }

        if ($prizes && $max > 0) {

            $list = Prize::all();
            $hv = rand(1, $max);

            /** @var prizelistModelObj $prize */
            foreach ($prizes as $prize) {
                if ($hv <= $prize->getPercent()) {
                    $obj = $list[$prize->getName()];
                    if ($obj) {
                        $params = unserialize($prize->getExtra());
                        if ($params) {
                            $p = $obj->give($user, $params);
                            if ($p) {
                                $org_total = intval($prize->getTotal());
                                $prize->setTotal($org_total + 1);

                                return is_error($p) ? $p : $p[0];
                            }
                        } else {
                            return null;
                        }
                    }
                }

                $hv -= $prize->getPercent();
            }
        }

        return null;
    }

    /**
     * 获取可用奖品类型列表
     * @return array
     */
    public static function all(): array
    {
        static $entries = [];
        if (empty($entries)) {
            foreach (glob(ZOVYE_CORE_ROOT . 'src/prize/*.php') as $filename) {
                $name = basename($filename, '.php');
                $classname = __NAMESPACE__ . '\\prize\\' . ucfirst($name);
                if (class_exists($classname)) {
                    $obj = new $classname();
                    if ($obj instanceof IPrize) {
                        $entries[lcfirst($name)] = $obj;
                    }
                }
            }
        }

        return $entries;
    }
}
