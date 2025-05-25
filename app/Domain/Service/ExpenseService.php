<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\Expense;
use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;

class ExpenseService
{
    private const DEFAULT_CATEGORIES = [
        'groceries', 'utilities',
        'transport', 'entertainment',
        'housing', 'health', 'other'
    ];

    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses,
    ) {}

    public function list(int $userId, int $year, int $month, int $pageNumber, int $pageSize): array
    {
        $criteria = [
            'user_id' => $userId,
            'year' => $year,
            'month' => $month,
        ];
        return $this->expenses->findBy($criteria, ($pageNumber - 1)* $pageSize, $pageSize);
    }

    public function countForUser(int $userId, int $year, int $month): int
    {
        return $this->expenses->countBy([
            'user_id' => $userId,
                'year' => $year,
                'month' => $month,
            ]);
    }

    public function getAvailableYears(int $userId):array
    {
        return $this->expenses->listExpenditureYears($userId);
    }

    public function findById(int $id): ?Expense
    {
        return $this->expenses->find($id);
    }

    public function create(
        int $userId,
        float $amount,
        string $description,
        DateTimeImmutable $date,
        string $category,
    ): void {
        $this->validateExpenseData($amount, $description, $date, $category);

        $expense = new Expense(null, $userId, $date, $category, (int)($amount * 100), $description);
        $this->expenses->save($expense);
    }

    public function update(
        Expense $expense,
        float $amount,
        string $description,
        DateTimeImmutable $date,
        string $category,
    ): void {
        $this->validateExpenseData($amount, $description, $date, $category);

        $expense->date = $date;
        $expense->category = $category;
        $expense->amountCents = (int)($amount * 100);
        $expense->description = $description;

        $this->expenses->save($expense);
    }
    public function delete(int $id): void
    {
        $this->expenses->delete($id);
    }

    private function validateExpenseData(
        float $amount,
        string $description,
        DateTimeImmutable $date,
        string $category
    ): void {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than 0');
        }

        if (empty($description)) {
            throw new InvalidArgumentException('Description cannot be empty');
        }

        if ($date > new DateTimeImmutable()) {
            throw new InvalidArgumentException('Date cannot be in the future');
        }

        if (empty($category)) {
            throw new InvalidArgumentException('Please select a valid category');
        }
    }
    public function importFromCsv(int $userId, UploadedFileInterface $csvFile): int
    {
         return 0;
    }

}
