<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\User;

class AlertGenerator
{
    public function __construct(
        private readonly MonthlySummaryService $summaryService,
        private readonly CategoryBudgetConfigService $budgetConfig,
    ) {}

    public function generate(User $user, int $year, int $month): array
    {
        $alerts = [];
        $budgets = $this->budgetConfig->getBudgets();

        $totals = $this->summaryService->computePerCategoryTotals($user, $year, $month);

        foreach ($totals as $category => $amountCents) {
            $amountEuros = $amountCents / 100;
            $budget = $budgets[$category] ?? null;

            if ($budget !== null && $amountEuros > $budget) {
                $diff = $amountEuros - $budget;
                $alerts[] = [
                    'category' => $category,
                    'exceeded' => number_format($diff, 2),
                ];
            }
        }

        return $alerts;
    }
}
