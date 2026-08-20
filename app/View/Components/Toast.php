<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Enums\ToastType;
use Illuminate\Contracts\View\View;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\Component;

final class Toast extends Component
{
    public function render(): View
    {
        return view('components.toast', [
            'messages' => $this->messages(),
            'durationMs' => $this->durationMs(),
            'typeClasses' => ToastType::containerClasses(),
            'closeLabel' => 'Закрити',
            'regionLabel' => 'Сповіщення',
        ]);
    }

    /**
     * @return list<array{type: string, message: string}>
     */
    private function messages(): array
    {
        return [
            ...$this->sessionFlashes(),
            ...$this->validationErrors(),
        ];
    }

    /**
     * @return list<array{type: string, message: string}>
     */
    private function sessionFlashes(): array
    {
        $items = [];

        foreach (ToastType::sessionMap() as $key => $type) {
            $message = session($key);

            if (! is_string($message) || $message === '') {
                continue;
            }

            $items[] = $this->item($type, $message);
        }

        return $items;
    }

    /**
     * @return list<array{type: string, message: string}>
     */
    private function validationErrors(): array
    {
        $errors = session('errors');

        if (! $errors instanceof ViewErrorBag || $errors->isEmpty()) {
            return [];
        }

        $items = [];

        foreach ($errors->all() as $message) {
            if (! is_string($message) || $message === '') {
                continue;
            }

            $items[] = $this->item(ToastType::Error, $message);
        }

        return $items;
    }

    /**
     * @return array{type: string, message: string}
     */
    private function item(ToastType $type, string $message): array
    {
        return [
            'type' => $type->value,
            'message' => $message,
        ];
    }

    private function durationMs(): int
    {
        return max(1, (int) config('ui.toast_duration_ms', 5000));
    }
}
