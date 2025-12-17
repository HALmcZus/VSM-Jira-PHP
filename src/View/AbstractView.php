<?php
namespace App\View;

abstract class AbstractView
{
    protected array $data = [];

    protected function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}
