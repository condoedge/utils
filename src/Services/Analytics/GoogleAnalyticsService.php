<?php

namespace Condoedge\Utils\Services\Analytics;

use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\OrderBy;
use Google\Analytics\Data\V1beta\OrderBy\MetricOrderBy;
use Google\Analytics\Data\V1beta\RunRealtimeReportRequest;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Illuminate\Support\Facades\Cache;
use Exception;

class GoogleAnalyticsService
{
    protected $client;
    protected $propertyId;

    public function __construct()
    {
        $credentialsPath = config('services.google_analytics.credentials_path');
        $this->propertyId = config('services.google_analytics.property_id');

        // Convert relative path to absolute path
        if (!str_starts_with($credentialsPath, '/') && !preg_match('/^[A-Z]:/i', $credentialsPath)) {
            $credentialsPath = base_path($credentialsPath);
        }

        if (!file_exists($credentialsPath)) {
            throw new Exception("Google Analytics credentials file not found at: {$credentialsPath}");
        }

        if (!$this->propertyId) {
            throw new Exception("Google Analytics Property ID not configured");
        }

        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $credentialsPath);

        $this->client = new BetaAnalyticsDataClient();
    }

    /**
     * Get realtime active users
     */
    public function getRealtimeUsers()
    {
        try {
            $request = new RunRealtimeReportRequest([
                'property' => 'properties/' . $this->propertyId,
                'metrics' => [
                    new Metric(['name' => 'activeUsers']),
                ],
            ]);

            $response = $this->client->runRealtimeReport($request);

            if ($response->getRows()->count() > 0) {
                return (int) $response->getRows()[0]->getMetricValues()[0]->getValue();
            }

            return 0;
        } catch (Exception $e) {
            \Log::error('Google Analytics Realtime Error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get analytics data for a specific date range
     */
    public function getAnalyticsData($startDate = '30daysAgo', $endDate = 'today')
    {
        try {
            \Log::info("getAnalyticsData called with dates: {$startDate} to {$endDate}");

            $request = new RunReportRequest([
                'property' => 'properties/' . $this->propertyId,
                'date_ranges' => [
                    new DateRange([
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ]),
                ],
                'metrics' => [
                    new Metric(['name' => 'activeUsers']),
                    new Metric(['name' => 'screenPageViews']),
                    new Metric(['name' => 'sessions']),
                    new Metric(['name' => 'bounceRate']),
                    new Metric(['name' => 'averageSessionDuration']),
                ],
            ]);

            $response = $this->client->runReport($request);

            $rowCount = $response->getRows()->count();
            \Log::info("API Response received. Row count: {$rowCount}");

            $data = [];
            if ($rowCount > 0) {
                $row = $response->getRows()[0];
                $metricValues = $row->getMetricValues();

                $data = [
                    'activeUsers' => (int) $metricValues[0]->getValue(),
                    'pageViews' => (int) $metricValues[1]->getValue(),
                    'sessions' => (int) $metricValues[2]->getValue(),
                    'bounceRate' => round((float) $metricValues[3]->getValue() * 100, 2),
                    'avgSessionDuration' => round((float) $metricValues[4]->getValue(), 2),
                ];

                \Log::info('Analytics data parsed successfully:', $data);
            } else {
                \Log::warning('No rows returned from Google Analytics API - returning default values');
                $data = [
                    'activeUsers' => 0,
                    'pageViews' => 0,
                    'sessions' => 0,
                    'bounceRate' => 0,
                    'avgSessionDuration' => 0,
                ];
            }

            return $data;
        } catch (Exception $e) {
            \Log::error('Google Analytics Data Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());

            return [
                'activeUsers' => 0,
                'pageViews' => 0,
                'sessions' => 0,
                'bounceRate' => 0,
                'avgSessionDuration' => 0,
            ];
        }
    }

    /**
     * Get top pages
     */
    public function getTopPages($limit = 10)
    {
        try {
            $request = new RunReportRequest([
                'property' => 'properties/' . $this->propertyId,
                'date_ranges' => [
                    new DateRange([
                        'start_date' => '30daysAgo',
                        'end_date' => 'today',
                    ]),
                ],
                'dimensions' => [
                    new Dimension(['name' => 'pagePath']),
                    new Dimension(['name' => 'pageTitle']),
                ],
                'metrics' => [
                    new Metric(['name' => 'screenPageViews']),
                ],
                'limit' => $limit,
            ]);

            // Add order by
            $orderBy = new \Google\Analytics\Data\V1beta\OrderBy();
            $metricOrderBy = new \Google\Analytics\Data\V1beta\OrderBy\MetricOrderBy();
            $metricOrderBy->setMetricName('screenPageViews');
            $orderBy->setMetric($metricOrderBy);
            $orderBy->setDesc(true);
            $request->setOrderBys([$orderBy]);

            $response = $this->client->runReport($request);

            $pages = [];
            foreach ($response->getRows() as $row) {
                $pages[] = [
                    'path' => $row->getDimensionValues()[0]->getValue(),
                    'title' => $row->getDimensionValues()[1]->getValue(),
                    'views' => (int) $row->getMetricValues()[0]->getValue(),
                ];
            }

            return $pages;
        } catch (Exception $e) {
            \Log::error('Google Analytics Top Pages Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get traffic sources
     */
    public function getTrafficSources()
    {
        try {
            $request = new RunReportRequest([
                'property' => 'properties/' . $this->propertyId,
                'date_ranges' => [
                    new DateRange([
                        'start_date' => '30daysAgo',
                        'end_date' => 'today',
                    ]),
                ],
                'dimensions' => [
                    new Dimension(['name' => 'sessionSource']),
                    new Dimension(['name' => 'sessionMedium']),
                ],
                'metrics' => [
                    new Metric(['name' => 'sessions']),
                ],
            ]);

            // Add order by
            $orderBy = new \Google\Analytics\Data\V1beta\OrderBy();
            $metricOrderBy = new \Google\Analytics\Data\V1beta\OrderBy\MetricOrderBy();
            $metricOrderBy->setMetricName('sessions');
            $orderBy->setMetric($metricOrderBy);
            $orderBy->setDesc(true);
            $request->setOrderBys([$orderBy]);

            $response = $this->client->runReport($request);

            $sources = [];
            foreach ($response->getRows() as $row) {
                $sources[] = [
                    'source' => $row->getDimensionValues()[0]->getValue(),
                    'medium' => $row->getDimensionValues()[1]->getValue(),
                    'sessions' => (int) $row->getMetricValues()[0]->getValue(),
                ];
            }

            return $sources;
        } catch (Exception $e) {
            \Log::error('Google Analytics Traffic Sources Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get device breakdown
     */
    public function getDeviceBreakdown()
    {
        try {
            $request = new RunReportRequest([
                'property' => 'properties/' . $this->propertyId,
                'date_ranges' => [
                    new DateRange([
                        'start_date' => '30daysAgo',
                        'end_date' => 'today',
                    ]),
                ],
                'dimensions' => [
                    new Dimension(['name' => 'deviceCategory']),
                ],
                'metrics' => [
                    new Metric(['name' => 'activeUsers']),
                ],
            ]);

            $response = $this->client->runReport($request);

            $devices = [];
            foreach ($response->getRows() as $row) {
                $devices[] = [
                    'device' => $row->getDimensionValues()[0]->getValue(),
                    'users' => (int) $row->getMetricValues()[0]->getValue(),
                ];
            }

            return $devices;
        } catch (Exception $e) {
            \Log::error('Google Analytics Device Breakdown Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get cached dashboard data (refresh every 5 minutes)
     */
    public function getDashboardData()
    {
        return Cache::remember('google_analytics_dashboard', 300, function () {
            return [
                'realtime' => $this->getRealtimeUsers(),
                'overview' => $this->getAnalyticsData('30daysAgo', 'today'),
                'today' => $this->getAnalyticsData('today', 'today'),
                'yesterday' => $this->getAnalyticsData('yesterday', 'yesterday'),
                'topPages' => $this->getTopPages(10),
                'trafficSources' => $this->getTrafficSources(),
                'devices' => $this->getDeviceBreakdown(),
            ];
        });
    }
}
