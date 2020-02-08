<?php

namespace DarkGhostHunter\Laraguard\Eloquent;

use DateTime;
use Illuminate\Support\Carbon;

trait HandlesCodes
{
    /**
     * Validates a given code, optionally for a given timestamp and future window.
     *
     * @param  string  $code
     * @param  int|string|\Illuminate\Support\Carbon|\Datetime  $at
     * @param  int  $window
     * @return bool
     */
    public function validateCode(string $code, $at = 'now', int $window = null) : bool
    {
        $window = $window ?? $this->window;

        for ($i = 0; $i <= $window; ++$i) {
            if (hash_equals($this->makeCode($at, -$i), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Creates a Code for a given timestamp, optionally by a given period offset.
     *
     * @param  int|string|\Illuminate\Support\Carbon|\Datetime  $at
     * @param  int  $offset
     * @return string
     */
    public function makeCode($at = 'now', int $offset = 0) : string
    {
        return $this->generateCode(
            $this->getTimestampFromPeriod($at, $offset)
        );
    }

    /**
     * Generates a valid Code for a given timestamp.
     *
     * @param  int  $timestamp
     * @return string
     */
    protected function generateCode(int $timestamp)
    {
        $hmac = hash_hmac(
            $this->algorithm,
            $this->timestampToBinary($timestamp),
            $this->attributes['shared_secret'],
            true
        );

        $offset = ord($hmac[strlen($hmac) - 1]) & 0xF;

        $number = (
                ((ord($hmac[$offset + 0]) & 0x7F) << 24) |
                ((ord($hmac[$offset + 1]) & 0xFF) << 16) |
                ((ord($hmac[$offset + 2]) & 0xFF) << 8) |
                (ord($hmac[$offset + 3]) & 0xFF)
            ) % (10 ** $this->digits);

        return str_pad((string)$number, $this->digits, '0', STR_PAD_LEFT);
    }

    /**
     * Creates a 64-bit raw binary string from a timestamp.
     *
     * @param  int  $timestamp
     * @return string
     */
    protected function timestampToBinary(int $timestamp)
    {
        return pack('N*', 0) . pack('N*', $timestamp);
    }

    /**
     * Get the timestamp from a given elapsed "periods" of seconds.
     *
     * @param  int|string|\Datetime|\Illuminate\Support\Carbon  $at
     * @param  int  $period
     * @return int
     */
    protected function getTimestampFromPeriod($at, int $period = 0)
    {
        $periods = ($this->parseTimestamp($at) / $this->seconds) + $period;

        return (int)$periods * $this->seconds;
    }

    /**
     * Normalizes the Timestamp from a string, integer or object.
     *
     * @param  int|string|\Datetime|\Illuminate\Support\Carbon  $at
     * @return int
     */
    protected function parseTimestamp($at) : int
    {
        if ($at instanceof DateTime) {
            return $at->getTimestamp();
        }

        if (is_string($at)) {
            return Carbon::parse($at)->getTimestamp();
        }

        return $at;
    }
}
