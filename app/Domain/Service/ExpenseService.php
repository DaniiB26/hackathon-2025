<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\Expense;
use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use DateTimeImmutable;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Exception;
use League\Csv\Reader;

class ExpenseService
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses,
    ) {}

    public function list(int $userId, int $year, int $month, int $pageNumber, int $pageSize): array
    {
        $offset = ($pageNumber - 1) * $pageSize;

        $criteria = [
            'user_id' => $userId,
            'year' => $year,
            'month' => $month,
        ];

        return $this->expenses->findBy($criteria, $offset, $pageSize);
    }

    public function create(
        int $userId,
        float $amount,
        string $description,
        DateTimeImmutable $date,
        string $category,
    ): void {

        if ($amount < 0) {
            throw new RuntimeException('Amount must be greater than 0');
        }

        if (trim($description) === '') {
            throw new RuntimeException('Description cannot be empty');
        }

        $today = new DateTimeImmutable('today');
        if ($date > $today) {
            throw new RuntimeException('Date cannot be in the future');
        }

        $validCategories = ['groceries', 'utilities', 'entertainment', 'transport', 'housing', 'healthcare', 'other'];
        if (!in_array($category, $validCategories, true)) {
            throw new RuntimeException('Invalid category.');
        }

        $amountCents = (int)round($amount * 100);

        $expense = new Expense(
            id: null,
            userId: $userId,
            date: $date,
            category: $category,
            amountCents: $amountCents,
            description: $description
        );

        $this->expenses->save($expense);
    }

    public function update(
        Expense $expense,
        float $amount,
        string $description,
        DateTimeImmutable $date,
        string $category,
    ): void {
        if ($amount < 0) {
            throw new RuntimeException('Amount must be greater than 0');
        }

        if (trim($description) === '') {
            throw new RuntimeException('Description cannot be empty');
        }

        if ($date > new DateTimeImmutable('tomorrow')) {
            throw new RuntimeException('Date cannot be in the future');
        }

        $validCategories = ['groceries', 'utilities', 'entertainment', 'transport', 'housing', 'healthcare', 'other'];
        if (!in_array($category, $validCategories, true)) {
            throw new RuntimeException('Invalid category.');
        }

        $expense->date = $date;
        $expense->category = $category;
        $expense->amountCents = (int)round($amount * 100);
        $expense->description = $description;

        $this->expenses->save($expense);
    }

    public function importFromCsv(User $user, UploadedFileInterface $csvFile): int
    {
        $importedCount = 0;
        $skippedRows = [];
        $validCategories = ['groceries', 'utilities', 'entertainment', 'transport', 'housing', 'healthcare', 'other'];

        $stream = $csvFile->getStream()->getContents();
        $csv = Reader::createFromString($stream);
        $csv->setDelimiter(',');
        $csv->setHeaderOffset(null);

        $records = $csv->getRecords();

        $this->expenses->beginTransaction();

        try {
            foreach ($records as $row) {
                if (count($row) !== 4) {
                    $skippedRows[] = ['reason' => 'Invalid column count', 'data' => $row];
                    continue;
                }

                [$dateString, $amount, $description, $category] = $row;

                if (!in_array($category, $validCategories, true)) {
                    $skippedRows[] = ['reason' => 'Unknown category', 'data' => $row];
                    continue;
                }

                if (trim($description) === '') {
                    $skippedRows[] = ['reason' => 'Empty description', 'data' => $row];
                    continue;
                }

                try {
                    $date = new \DateTimeImmutable($dateString);
                } catch (Exception) {
                    $skippedRows[] = ['reason' => 'Invalid date', 'data' => $row];
                    continue;
                }

                $amountFloat = (float)$amount;
                if ($amountFloat <= 0) {
                    $skippedRows[] = ['reason' => 'Invalid amount', 'data' => $row];
                    continue;
                }
                $expense = new Expense(
                    id: null,
                    userId: $user->id,
                    date: $date,
                    category: $category,
                    amountCents: (int)round($amountFloat * 100),
                    description: $description
                );

                $this->expenses->save($expense);
                $importedCount++;
            }
            $this->expenses->commit();
        } catch (\Throwable $th) {
            $this->expenses->rollBack();
            throw new RuntimeException('CSV import failed: ' . $th->getMessage());
        }

        return $importedCount;
    }

    public function getExpenseById(int $id): ?Expense
    {
        return $this->expenses->find($id);
    }

    public function deleteExpenseById(int $id): void
    {
        $this->expenses->delete($id);
    }

    public function getAvailableYearsForUser(int $userId): array
    {
        $user = new User($userId, '', '', new DateTimeImmutable()); //dummy user for the id
        return $this->expenses->listExpenditureYears($user);
    }

    public function countBy(int $userId, int $year, int $month): int
    {
        return $this->expenses->countBy([
            'user_id' => $userId,
            'year' => $year,
            'month' => $month,
        ]);
    }
}
