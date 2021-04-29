<?php
/**
 * Author: Drunk
 * Date: 2019-1-21 21:40
 */

namespace dce\project\session;

class CookieCgi extends Cookie {
    /** @inheritDoc */
    public function get(string $key): mixed {
        return $_COOKIE[$key] ?? null;
    }

    /** @inheritDoc */
    public function set(string $key, string $value = "", int $expire = 0, string $path = "", string $domain = "", bool $secure = false, bool $httpOnly = false): void {
        setcookie($key, $value, $expire, $path, $domain, $secure, $httpOnly);
    }

    /** @inheritDoc */
    public function delete(string $key): void {
        unset($_COOKIE[$key]);
    }

    /** @inheritDoc */
    public function getAll(string $key): array {
        return $_COOKIE ?? [];
    }
}
