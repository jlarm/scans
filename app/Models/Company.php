<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\CompanyObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy(CompanyObserver::class)]
final class Company extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
        ];
    }
}
