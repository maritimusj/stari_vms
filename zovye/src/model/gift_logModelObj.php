<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\FlashEgg;
use zovye\User;
use function zovye\tb;
use zovye\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;

/**
 * @method setStatus(int $int)
 */
class gift_logModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('gift_log');
    }

	/** @var int */
	protected $id;

    /** @var int */
    protected $uniacid;

	/** @var int */
	protected $gift_id;

	/** @var int */
	protected $user_id;

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

    public function getGift(): ?giftModelObj
    {
        return FlashEgg::getGift($this->gift_id);
    }

    public function getUser(): ?userModelObj
    {
        return User::get($this->user_id);
    }

    public function profile(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone_number' => $this->phone_num,
            'location' => $this->location,
            'address' => $this->address,
            'status' => $this->status,
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
        $gift = $this->getGift();
        if ($gift) {
            $data['gift'] = $gift->profile($fullpath);
        }

        return $data;
    }
}