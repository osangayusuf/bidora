<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DailyReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(private readonly DailyReportService $reports) {}

    public function index(Request $request): Response
    {
        [$period, $date] = $this->resolveFilters($request);

        return Inertia::render('Admin/Reports/Index', [
            'summary' => $this->reports->summary($period, $date),
            'activeUsers' => $this->reports->activeUsers(
                (int) $request->input('active_page', 1),
            ),
            'nonActiveUsers' => $this->reports->nonActiveUsers(
                (int) $request->input('inactive_page', 1),
            ),
            'filters' => [
                'period' => $period,
                'date' => $date,
            ],
        ]);
    }

    public function downloadPdf(Request $request): HttpResponse
    {
        [$period, $date] = $this->resolveFilters($request);

        $data = $this->reports->forExport($period, $date);

        $pdf = Pdf::loadView('reports.daily', $data);

        $filename = sprintf(
            'bidora-%s-report-%s-to-%s.pdf',
            $period,
            $data['range']['start'],
            $data['range']['end'],
        );

        return $pdf->download($filename);
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function resolveFilters(Request $request): array
    {
        $period = in_array($request->input('period'), ['daily', 'weekly', 'monthly'], true)
            ? $request->input('period')
            : 'daily';

        $date = $request->filled('date') ? $request->string('date')->toString() : null;

        return [$period, $date];
    }
}
