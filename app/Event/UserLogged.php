<?php
declare(strict_types=1);

namespace App\Event;


/**
 * Class UserLogged
 * @package App\Event
 */
class UserLogged
{
    public $user;

    /**
     * UserLogged constructor.
     * @param $user
     */
    public function __construct ($user)
    {
        $this->user = $user;
    }
}
