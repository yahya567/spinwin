<?php

namespace TgWebValid\Make;

use Carbon\Carbon;
use TgWebValid\Entities\InitData\Chat;
use TgWebValid\Entities\InitData\Receiver;
use TgWebValid\Entities\InitData\User;

abstract class InitData extends Make
{
    /**
     * @param array<string, int|string|bool> $props
     */
    public function __construct(array $props = [])
    {
        foreach ($props as $prop => $value) {
            $value = match ($prop) {
                'user'      => new User($this->tryParseJSON($value)),
                'receiver'  => new Receiver($this->tryParseJSON($value)),
                'chat'      => new Chat($this->tryParseJSON($value)),
                'auth_date' => Carbon::createFromTimestamp((int) $value),
                default     => $value
            };
            $this->setProperty(camelize($prop), $value);
        }
    }
}
