<?php

namespace Tests\ExampleClasses;

use RuntimeException;

/**
 * This is an example class to highlight how the Wrapped Facade works.
 */
class ApiRepository
{
    protected $data = [
        1 => 'Apple',
        2 => 'Banana',
        3 => 'Carrot',
        4 => 'Durifruit',
        5 => 'Eggplant',
    ];

    public function index(): array
    {
        return $this->data;
    }

    public function find(int $id): string
    {
        if (! array_key_exists($id, array_keys($this->data))) {
            throw new RuntimeException('Element by ID '.$id.' not found');
        }

        return $this->data[$id];
    }
}
