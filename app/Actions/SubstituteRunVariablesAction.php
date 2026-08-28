<?php

declare(strict_types=1);

namespace App\Actions;

final class SubstituteRunVariablesAction
{
    /**
     * @param  array<string, mixed>  $variables
     */
    public function execute(string $value, array $variables): string
    {
        foreach ($variables as $name => $replacement) {
            if (! is_scalar($replacement) && $replacement !== null) {
                continue;
            }

            $value = str_replace('{{'.$name.'}}', (string) $replacement, $value);
        }

        return $value;
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $variables
     * @return array<string, string>
     */
    public function headers(array $headers, array $variables): array
    {
        $resolved = [];

        foreach ($headers as $name => $value) {
            $resolved[$name] = $this->execute($value, $variables);
        }

        return $resolved;
    }
}
