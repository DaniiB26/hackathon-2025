<?php

declare(strict_types=1);

namespace App\Domain\Service;

use JsonException;
use RuntimeException;

class CategoryBudgetConfigService
{
    private array $budgets;

    public function __construct()
    {
        $json = $_ENV['CATEGORY_BUDGETS'] ?? '{}';

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Invalid CATEGORY_BUDGETS JSON in .env');
        }

        $this->budgets = is_array($decoded) ? $decoded : [];
    }

    public function getBudgets(): array
    {
        return $this->budgets;
    }
}
