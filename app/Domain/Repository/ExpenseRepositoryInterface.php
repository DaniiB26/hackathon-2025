<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Expense;
use App\Domain\Entity\User;

interface ExpenseRepositoryInterface
{

    public function save(Expense $expense): void;

    public function delete(int $id): void;

    public function find(int $id): ?Expense;

    public function findBy(array $criteria, int $from, int $limit): array;

    public function countBy(array $criteria): int;

    public function listExpenditureYears(User $user): array;

    public function sumAmountsByCategory(array $criteria): array;

    public function averageAmountsByCategory(array $criteria): array;

    public function sumAmounts(array $criteria): float;

    public function beginTransaction(): void;

    public function commit(): void;
    
    public function rollback(): void;
}
