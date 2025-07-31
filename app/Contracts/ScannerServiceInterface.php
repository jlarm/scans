<?php

declare(strict_types=1);

namespace App\Contracts;

interface ScannerServiceInterface
{
    public function scan(string $target, string $type): array;
}