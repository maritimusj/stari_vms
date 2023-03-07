<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\base\modelObj;
use zovye\FlashEgg;
use zovye\traits\ExtraDataGettersAndSetters;
use zovye\User;
use function zovye\tb;

/**
 * @method setStatus(int $int)
 * @method getStatus()
 */
class lucky_logModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('lucky_log');
    }
    
	/** @var int */
	protected $id;

	/** @var int */
	protected $uniacid;

	/** @var int */
	protected $lucky_id;

	/** @var int */
	protected $user_id;

	/** @var string */
	protected $serial;

	/** @var string */
	protected $name;

	/** @var string */
	protected $phone_num;

	/** @var string */
	protected $location;

	/** @var string */
	protected $address;

	/** @var int */
	protected $status;

	protected $extra;

	/** @var int */
	protected $createtime;

	use ExtraDataGettersAndSetters;

    public function getLucky(): ?luckyModelObj
    {
        return FlashEgg::getLucky($this->lucky_id);
    }

    public function getUser(): ?userModelObj
    {
        return User::get($this->user_id);
    }

    public function profile(): array
    {
        return [
            'id' => $this->id,
			'serial' => $this->serial,
            'name' => $this->name,
            'phone_number' => $this->phone_num,
            'location' => $this->location,
            'address' => $this->address,
            'status' => $this->status,
            'delivery' => $this->getExtraData('delivery', []),
            'createtime_formatted' => date('Y-m-d H:i:s', $this->createtime),
        ];
    }

    public function format($fullpath = false): array
    {
        $data = $this->profile();

        $user = $this->getUser();
        if ($user) {
            $data['user'] = $user->profile(false);
        }
        $lucky = $this->getLucky();
        if ($lucky) {
            $data['lucky'] = $lucky->profile($fullpath);
        }

        return $data;
    }
}