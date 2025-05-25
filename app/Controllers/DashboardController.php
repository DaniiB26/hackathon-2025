<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Entity\User;
use App\Domain\Service\AlertGenerator;
use App\Domain\Service\ExpenseService;
use App\Domain\Service\MonthlySummaryService;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class DashboardController extends BaseController
{
    public function __construct(
        Twig $view,
        private readonly MonthlySummaryService $summaryService,
        private readonly AlertGenerator $alertGenerator,
        private readonly ExpenseService $expenseService
    ) {
        parent::__construct($view);
    }

    public function index(Request $request, Response $response): Response
    {
        // TODO: parse the request parameters
        // TODO: load the currently logged-in user
        // TODO: get the list of available years for the year-month selector
        // TODO: call service to generate the overspending alerts for current month
        // TODO: call service to compute total expenditure per selected year/month
        // TODO: call service to compute category totals per selected year/month
        // TODO: call service to compute category averages per selected year/month

        if (!isset($_SESSION['user_id'])) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $userId = (int)$_SESSION['user_id'];
        $user = new User($userId, '', '', new DateTimeImmutable());

        $query = $request->getQueryParams();
        $year = (int)($query['year'] ?? date('Y'));
        $month = (int)($query['month'] ?? date('m'));

        $years = $this->expenseService->getAvailableYearsForUser($userId);

        if (!in_array($year, $years, true)) {
            $year = (int)date('Y');
        }
        if ($month < 1 || $month > 12) {
            $month = (int)date('m');
        }

        $totalForMonth = $this->summaryService->computeTotalExpenditure($user, $year, $month);
        $totalForMonth = number_format($totalForMonth / 100, 2);
        
        $totalsRaw = $this->summaryService->computePerCategoryTotals($user, $year, $month);
        $averagesRaw = $this->summaryService->computePerCategoryAverages($user, $year, $month);

        $maxTotal = max(array_values($totalsRaw)) ?: 1;
        $maxAvg = max(array_values($averagesRaw)) ?: 1;

        $totalsForCategories = [];
        foreach ($totalsRaw as $category => $totalCents) {
            $totalsForCategories[$category] = [
                'value' => number_format($totalCents / 100, 2),
                'percentage' => round($totalCents / $maxTotal * 100),
            ];
        }

        $averagesForCategories = [];
        foreach ($averagesRaw as $category => $avgCents) {
            $averagesForCategories[$category] = [
                'value' => number_format($avgCents / 100, 2),
                'percentage' => round($avgCents / $maxAvg * 100),
            ];
        }


        $alerts = [];
        if ($year === (int)date('Y') && $month === (int)date('m')) {
            $alerts = $this->alertGenerator->generate($user, $year, $month);
        }

        return $this->render($response, 'dashboard.twig', [
            'year' => $year,
            'month' => $month,
            'years' => $years,
            'alerts' => $alerts,
            'totalForMonth' => $totalForMonth,
            'totalsForCategories' => $totalsForCategories,
            'averagesForCategories' => $averagesForCategories,
        ]);
    }
}
