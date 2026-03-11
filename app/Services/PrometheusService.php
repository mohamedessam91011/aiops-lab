<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PrometheusService
{
    
    private $prometheusUrl = 'http://127.0.0.1:9090/api/v1/query';

    public function query($query)
    {
        try {
            
            $response = Http::timeout(2)->connectTimeout(2)->get($this->prometheusUrl, [
                'query' => $query
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (!empty($data['data']['result'])) {
                    $value = $data['data']['result'][0]['value'][1];
                    return is_numeric($value) ? (float) $value : 0.0;
                }
            }
            return 0.0;
        } catch (\Exception $e) {
            
            echo "\n[CONNECTION ERROR] Prometheus is not responding or taking too long.\n";
            Log::error("Prometheus Connection Error: " . $e->getMessage());
            return 0.0;
        }
    }

    public function getRequestRate()
    {
        return $this->query('sum(rate(http_requests_total[2m]))');
    }

    public function getErrorRate()
    {
        return $this->query('sum(rate(http_errors_total[2m]))');
    }

    public function getP95Latency()
    {
        return $this->query('histogram_quantile(0.95, sum(rate(http_request_duration_seconds_bucket[2m])) by (le))');
    }
}