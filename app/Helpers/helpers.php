<?php

if (! function_exists('money')) {
    function money(
        float|int|string|null $amount,
        string $symbol = '$',
        int $decimals = 2
    ): string {
        if ($amount === null) {
            return $symbol . '0.00';
        }

        return $symbol . number_format(
            (float) $amount,
            $decimals,
            '.',
            ','
        );
    }
}
