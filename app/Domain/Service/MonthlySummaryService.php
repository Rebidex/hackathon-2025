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

    public function computeTotalExpenditure(int $userId, int $year, int $month): float
    {
        return $this->expenses->sumAmounts([
            'user_id' => $userId,
            'year' => $year,
            'month' => $month,
        ]);
    }

    public function computePerCategoryTotals(int $userId, int $year, int $month): array
    {
        $totals = $this->expenses->sumAmountsByCategory([
            'user_id' => $userId,
            'year' => $year,
            'month' => $month,
        ]);
        $totalExpentidure = $this->computeTotalExpenditure($userId, $year, $month);
        foreach ($totals as &$category) {
            $category['percentage'] = $totalExpentidure > 0 ? ($category['value'] / $totalExpentidure) * 100 : 0;
        }
        return $totals;
    }

    public function computePerCategoryAverages(int $userId, int $year, int $month): array
    {
        $averages = $this->expenses->averageAmountsByCategory([
            'user_id' => $userId,
            'year' => $year,
            'month' => $month,
        ]);
        $totalExpentidure = $this->computeTotalExpenditure($userId, $year, $month);
        foreach ($averages as &$category) {
            $category['percentage'] = $totalExpentidure > 0 ? ($category['value'] / $totalExpentidure) * 100 : 0;;
        }
        return $averages;
    }

    public function getAvailableYears(int $userId):array
    {
        return $this->expenses->getAvailableYears($userId);
    }
}
