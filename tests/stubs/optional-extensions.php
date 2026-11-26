<?php

/*
 * The Festoon and Secret Santa classes the advent calendar reaches for, with
 * their signatures (checked against festoon 1.2 and secret-santa 1.0), for
 * static analysis where neither extension is installed. Never loaded when
 * the real classes exist.
 */

namespace Ernestdefoe\Festoon {
    if (! class_exists(Config::class, false)) {
        class Config
        {
            public function timezone(): string
            {
                return 'UTC';
            }
        }
    }
}

namespace ErnestDefoe\SecretSanta {
    use Flarum\Database\AbstractModel;

    if (! class_exists(Exchange::class, false)) {
        /**
         * @property int $id
         * @property string $title
         * @property string $status
         * @property \Carbon\Carbon $signup_ends_at
         */
        class Exchange extends AbstractModel
        {
            public const OPEN = 'open';
            public const DRAWN = 'drawn';

            public function signupsOpen(): bool
            {
                return $this->status === self::OPEN;
            }
        }
    }
}
