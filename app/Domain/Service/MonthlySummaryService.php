<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;

class MonthlySummaryService
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses,
    ) {}

    public function computeTotalExpenditure(User $user, int $year, int $month): float
    {
        return $this->expenses->sumAmounts([
            'user_id' => $user->id,
            'year' => $year,
            'month' => $month,
        ]);
    }

    public function computePerCategoryTotals(User $user, int $year, int $month): array
    {
        return $this->expenses->sumAmountsByCategory([
            'user_id' => $user->id,
            'year' => $year,
            'month' => $month,
        ]);
    }

    public function computePerCategoryAverages(User $user, int $year, int $month): array
    {
        return $this->expenses->averageAmountsByCategory([
            'user_id' => $user->id,
            'year' => $year,
            'month' => $month,
        ]);
    }
}
