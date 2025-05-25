<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\Expense;
use App\Domain\Repository\ExpenseRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
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
    public function importFromCsv(int $userId, UploadedFileInterface $csvFile): array
    {
        $stream = $csvFile->getStream();
        $stream->rewind();
        $content = $stream->getContents();

        $lines = explode("\n", $content);
        $imported = 0;
        $skipped = 0;
        $existingExpenses = [];
        $logger = new Logger('import');
        $logger->pushHandler(new StreamHandler(__DIR__.'/../../var/import.log'));

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $parts = str_getcsv($line);
            if (count($parts) !== 4) {
                $logger->info('Skipped row - invalid column count', ['row' => $line]);
                $skipped++;
                continue;
            }

            [$dateStr, $amountStr, $description, $category] = $parts;
            $description = trim($description);
            $category = trim($category);

            // Skipping the empty descriptions
            if (empty($description)) {
                $logger->info('Skipped row - empty description', ['row' => $line]);
                $skipped++;
                continue;
            }

            $categoryLower = strtolower($category);
            $validCategories =  array_map('strtolower', self::DEFAULT_CATEGORIES);
            if (!in_array($categoryLower, $validCategories)) {
                $logger->info('Skipped row - invalid category', ['row' => $line, 'category' => $category]);
                $skipped++;
                continue;
            }

            if (!is_numeric($amountStr)) {
                $logger->info('Skipped row - invalid amount', ['row' => $line, 'amount' => $amountStr]);
                $skipped++;
                continue;
            }
            $amount = (float)$amountStr;

            if ($amount <= 0) {
                $logger->info('Skipped row - amount <= 0', ['row' => $line, 'amount' => $amount]);
                $skipped++;
                continue;
            }


            // Checking for duplicates
            $key = md5($dateStr . $description . $amountStr . $category);
            if (isset($existingExpenses[$key])) {
                $logger->info('Skipped row - duplicate', ['row' => $line]);
                $skipped++;
                continue;
            }
            $existingExpenses[$key] = true;

            try {
                $date = new \DateTimeImmutable($dateStr);
                $categoryKey = array_search($categoryLower, $validCategories);
                $properCategory = self::DEFAULT_CATEGORIES[$categoryKey];

                $this->create(
                    $userId,
                    $amount,
                    $description,
                    $date,
                    $properCategory
                );

                $imported++;
            } catch (\Exception $e) {
                $logger->error('Failed to import row', [
                    'row' => $line,
                    'error' => $e->getMessage()]);
            }
            $skipped++;
        }

        $logger->info('Import completed', [
            'imported' => $imported,
            'skipped' => $skipped,
            'userId' => $userId
        ]);

        return [
            'imported' => $imported,
            'skipped' => $skipped
        ];
    }

}
