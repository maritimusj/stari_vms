<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\base\ModelObj;
use zovye\business\FlashEgg;
use zovye\domain\User;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\tb;

/**
 * @method setStatus(int $int)
 * @method getStatus()
 */
class gift_logModelObj extends ModelObj
{
    public static function getTableName($read_or_write): string
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
        
        $gift = $this->getGift();
        if ($gift) {
            $data['gift'] = $gift->profile($fullpath);
        } else {
            $data['gift'] = [
                'name' => '<活动已删除>',
            ];
        }

        return $data;
    }
}