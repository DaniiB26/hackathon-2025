<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Expense;
use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use DateTimeImmutable;
use Exception;
use PDO;

class PdoExpenseRepository implements ExpenseRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * @throws Exception
     */
    public function find(int $id): ?Expense
    {
        $query = 'SELECT * FROM expenses WHERE id = :id';
        $statement = $this->pdo->prepare($query);
        $statement->execute(['id' => $id]);
        $data = $statement->fetch();
        if (false === $data) {
            return null;
        }

        return $this->createExpenseFromData($data);
    }

    public function save(Expense $expense): void
    {
        if ($expense->id === null) {
            // INSERT new
            $statement = $this->pdo->prepare(
                'INSERT INTO expenses (user_id, date, category, amount_cents, description)
             VALUES (:user_id, :date, :category, :amount_cents, :description)'
            );

            $statement->execute([
                'user_id' => $expense->userId,
                'date' => $expense->date->format('Y-m-d H:i:s'),
                'category' => $expense->category,
                'amount_cents' => $expense->amountCents,
                'description' => $expense->description,
            ]);
        } else {
            // UPDATE for an existing expense
            $statement = $this->pdo->prepare(
                'UPDATE expenses
             SET date = :date,
                 category = :category,
                 amount_cents = :amount_cents,
                 description = :description
             WHERE id = :id AND user_id = :user_id'
            );

            $statement->execute([
                'date' => $expense->date->format('Y-m-d H:i:s'),
                'category' => $expense->category,
                'amount_cents' => $expense->amountCents,
                'description' => $expense->description,
                'id' => $expense->id,
                'user_id' => $expense->userId,
            ]);
        }
    }


    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM expenses WHERE id=?');
        $statement->execute([$id]);
    }

    public function findBy(array $criteria, int $from, int $limit): array
    {
        $sql = 'SELECT * FROM expenses';
        $params = [];
        $conditions = [];

        foreach ($criteria as $key => $value) {
            if ($key === 'year') {
                $conditions[] = 'strftime("%Y", date) = :year';
                $params['year'] = (string)$value;
            } elseif ($key === 'month') {
                $conditions[] = 'strftime("%m", date) = :month';
                $params['month'] = str_pad((string)$value, 2, '0', STR_PAD_LEFT);
            } else {
                $conditions[] = "$key = :$key";
                $params[$key] = $value;
            }
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY date DESC LIMIT :limit OFFSET :offset';
        $params['limit'] = $limit;
        $params['offset'] = $from;

        $statement = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $statement->bindValue(":$key", $value, $type);
        }

        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $expenses = [];
        foreach ($rows as $row) {
            $expenses[] = $this->createExpenseFromData($row);
        }

        return $expenses;
    }


    public function countBy(array $criteria): int
    {
        $query = 'SELECT COUNT(*) FROM expenses WHERE user_id = :user_id';
        $params = ['user_id' => $criteria['user_id']];

        if (isset($criteria['year']) && isset($criteria['month'])) {
            $query .= ' AND strftime("%Y", date) = :year AND strftime("%m", date) = :month';
            $params['year'] = (string)$criteria['year'];
            $params['month'] = str_pad((string)$criteria['month'], 2, '0', STR_PAD_LEFT);
        }

        $statement = $this->pdo->prepare($query);
        $statement->execute($params);
        return (int)$statement->fetchColumn();
    }

    public function listExpenditureYears(User $user): array
    {
        $query = 'SELECT DISTINCT strftime("%Y", date) as year FROM expenses WHERE user_id = :user_id ORDER BY year DESC';

        $statement = $this->pdo->prepare($query);
        $statement->execute(['user_id' => $user->id]);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $years = [];
        foreach ($rows as $row) {
            $years[] = (int)$row['year'];
        }

        return $years;
    }

    public function sumAmountsByCategory(array $criteria): array
    {
        $sql = 'SELECT category, SUM(amount_cents) AS total FROM expenses';
        $params = [];
        $conditions = [];

        foreach ($criteria as $key => $value) {
            if ($key === 'year') {
                $conditions[] = 'strftime("%Y", date) = :year';
                $params['year'] = (string)$value;
            } elseif ($key === 'month') {
                $conditions[] = 'strftime("%m", date) = :month';
                $params['month'] = str_pad((string)$value, 2, '0', STR_PAD_LEFT);
            } else {
                $conditions[] = "$key = :$key";
                $params[$key] = $value;
            }
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' GROUP BY category';

        $statement = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $statement->bindValue(":$key", $value, $type);
        }

        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $result[$row['category']] = (int)$row['total'];
        }

        return $result;
    }


    public function averageAmountsByCategory(array $criteria): array
    {
        $sql = 'SELECT category, AVG(amount_cents) AS average FROM expenses';
        $params = [];
        $conditions = [];

        foreach ($criteria as $key => $value) {
            if ($key === 'year') {
                $conditions[] = 'strftime("%Y", date) = :year';
                $params['year'] = (string)$value;
            } elseif ($key === 'month') {
                $conditions[] = 'strftime("%m", date) = :month';
                $params['month'] = str_pad((string)$value, 2, '0', STR_PAD_LEFT);
            } else {
                $conditions[] = "$key = :$key";
                $params[$key] = $value;
            }
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' GROUP BY category';

        $statement = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $statement->bindValue(":$key", $value, $type);
        }

        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $result[$row['category']] = (float)$row['average'];
        }

        return $result;
    }


    public function sumAmounts(array $criteria): float
    {
        $sql = 'SELECT SUM(amount_cents) AS total FROM expenses';
        $params = [];
        $conditions = [];

        foreach ($criteria as $key => $value) {
            if ($key === 'year') {
                $conditions[] = 'strftime("%Y", date) = :year';
                $params['year'] = (string)$value;
            } elseif ($key === 'month') {
                $conditions[] = 'strftime("%m", date) = :month';
                $params['month'] = str_pad((string)$value, 2, '0', STR_PAD_LEFT);
            } else {
                $conditions[] = "$key = :$key";
                $params[$key] = $value;
            }
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $statement = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $statement->bindValue(":$key", $value, $type);
        }

        $statement->execute();
        $result = $statement->fetch(\PDO::FETCH_ASSOC);

        return isset($result['total']) ? (float)$result['total'] : 0;
    }


    /**
     * @throws Exception
     */
    private function createExpenseFromData(mixed $data): Expense
    {
        return new Expense(
            $data['id'],
            $data['user_id'],
            new DateTimeImmutable($data['date']),
            $data['category'],
            $data['amount_cents'],
            $data['description'],
        );
    }

    public function exists(int $userId, \DateTimeImmutable $date, string $description, int $amountCents, string $category): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM expenses WHERE user_id = :user_id AND date = :date AND description = :description AND amount_cents = :amount_cents AND category = :category'
        );

        $statement->execute([
            'user_id'       => $userId,
            'date'          => $date->format('Y-m-d H:i:s'),
            'description'   => $description,
            'amount_cents'  => $amountCents,
            'category'      => $category,
        ]);

        return (int)$statement->fetchColumn() > 0;
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        $this->pdo->rollBack();
    }
}
