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
        if($expense->id === null)
        {
            $query = 'INSERT INTO expenses (user_id, date, category, amount_cents, description) 
                        VALUES (:user_id, :date, :category, :amount_cents, :description)';
            $parameters = [
                'user_id' => $expense->userId,
                'date' => $expense->date->format('Y-m-d'),
                'category' => $expense->category,
                'amount_cents' => $expense->amountCents,
                'description' => $expense->description,
            ];
        } else {
            $query = 'UPDATE expenses SET user_id = :user_id, 
                    date = :date, 
                    category = :category, 
                    amount_cents = :amount_cents,
                    description = :description 
                    WHERE id = :id AND user_id = :user_id';
            $parameters = [
                'id' => $expense->id,
                'user_id'=>$expense->userId,
                'date' => $expense->date->format('Y-m-d'),
                'category' => $expense->category,
                'amount_cents' => $expense->amountCents,
                'description' => $expense->description,
            ];
        }
        $statement = $this->pdo->prepare($query);
        $statement->execute($parameters);
        if($expense->id === null)
        {
            $expense->id = (int)$this->pdo->lastInsertId();
        }
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM expenses WHERE id=?');
        $statement->execute([$id]);
    }

    public function findBy(array $criteria, int $from, int $limit): array
    {
        $where = [];
        $parameters = [];

        foreach ($criteria as $key => $value) {
            if ($key === 'year') {
                $where[] = "date >= :year_start AND date < :year_end";
                $parameters['year_start'] = $value . '-01-01';
                $parameters['year_end'] = ($value + 1) . '-01-01';
            } elseif ($key === 'month') {
                $monthPadded = str_pad((string)$value, 2, '0', STR_PAD_LEFT);
                $where[] = "strftime('%m', date) = :month";
                $parameters['month'] = $monthPadded;
            } else {
                $where[] = "$key = :$key";
                $parameters[$key] = $value;
            }
        }

        $query = 'SELECT * FROM expenses';
        if (!empty($where)) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }
        $query .= ' ORDER BY date DESC LIMIT :limit OFFSET :offset';

        $parameters['limit'] = $limit;
        $parameters['offset'] = $from;

        $statement = $this->pdo->prepare($query);
        foreach ($parameters as $key => $value) {
            $statement->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->execute();

        $expenses = [];
        while ($data = $statement->fetch()) {
            $expenses[] = $this->createExpenseFromData($data);
        }
        return $expenses;
    }


    public function countBy(array $criteria): int
    {
        $where = [];
        $parameters = [];
        foreach ($criteria as $key => $value) {
            if($key === 'year'){
                $where[]= 'strftime("%Y", date) = :year';
                $parameters['year'] = $value;
            }elseif($key === 'month'){
                $where[]= 'strftime("%m", date) = :month';
                $parameters['month'] = str_pad((string)$value, 2, '0', STR_PAD_LEFT);
            }else {
                $where[]= "$key = :$key";
                $parameters[$key] = $value;
            }
        }
        $query = 'SELECT COUNT(*) FROM expenses';
        if(!empty($where)){
            $query .= ' WHERE '.implode(' AND ', $where);
        }
        $statement = $this->pdo->prepare($query);
        $statement->execute($parameters);

        return (int)$statement->fetchColumn();
    }

    public function listExpenditureYears(int $userId): array
    {
       $query = 'SELECT DISTINCT strftime("%Y", date) AS year 
                FROM expenses  
                WHERE user_id = :user_id 
                GROUP BY year 
                ORDER BY year DESC';
       $statement = $this->pdo->prepare($query);
        $statement->execute(['user_id'=>$userId]);
        $years = [];
        while ($data = $statement->fetch()) {
            $years[] = (int)$data['year'];
        }

        $currentYear = date('Y');
        if(!in_array($currentYear, $years)){
            $years[] = $currentYear;
            sort($years);
        }
        return $years;
    }

    public function sumAmountsByCategory(array $criteria): array
    {
        // TODO: Implement sumAmountsByCategory() method.
        return [];
    }

    public function averageAmountsByCategory(array $criteria): array
    {
        // TODO: Implement averageAmountsByCategory() method.
        return [];
    }

    public function sumAmounts(array $criteria): float
    {
        // TODO: Implement sumAmounts() method.
        return 0;
    }

    /**
     * @throws Exception
     */
    private function createExpenseFromData(array $data): Expense
    {
        return new Expense(
            (int)$data['id'],
            (int)$data['user_id'],
            new DateTimeImmutable($data['date']),
            $data['category'],
            (int)$data['amount_cents'],
            $data['description'],
        );
    }
}
