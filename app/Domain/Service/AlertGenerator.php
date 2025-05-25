<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\User;

class AlertGenerator
{
    public function __construct(private readonly MonthlySummaryService $summaryService)
    {
        $budgetJson = $_ENV['CATEGORY_BUDGETS'] ?? '{"groceries": 300.00, "utilities": 200.00, "transport": 500.00, "entertainment": 200.00, "housing": 600.00, "health": 75.00, "other": 100.00}';
        $this->categoryBudgets = json_decode($budgetJson, true) ?? [
            'groceries' => 300.00,
            'utilities' => 200.00,
            'transport' => 500.00,
            'entertainment' => 200.00,
            'housing' => 600.00,
            'health' => 75.00,
            'other' => 100.00,
        ];
    }

    private array $categoryBudgets;

    public function generate(int $userId, int $year, int $month): array
    {
        $categoryTotals = $this->summaryService->computePerCategoryTotals($userId, $year, $month);
        $alerts = [];

        $overBudgetCount = 0;

        foreach ($this->categoryBudgets as $category => $budget) {
            $overAmount = 0;

            foreach ($categoryTotals as $categoryData) {
                $spent = 0;
                if ($categoryData['category'] === $category) {
                    $spent = $categoryData['value'];
                    break;
                }
            }

            if ($spent > $budget) {
                $overBudgetCount++;
                $overAmount = $spent - $budget;
                $alerts[] = [
                    'type' => 'danger',
                    'message' => sprintf('You spent %.2f on %s, which is more than your budget of %.2f', $overAmount, $category, $budget),
                ];
            }
        }
            if ($overBudgetCount === 0) {
                $alerts[] = [
                    'type' => 'success',
                    'message' => sprintf('You spent %.2f on %s, which is less than your budget of %.2f', $overAmount, $category, $budget),
                ];
            }
            return $alerts;
        }
    }
