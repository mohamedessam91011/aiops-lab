<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PrometheusService;
use App\Models\AiopsBaseline; 
use App\Events\IncidentCreated; 
use Illuminate\Support\Str;

class AiopsDetectCommand extends Command
{
    
    protected $signature = 'aiops:detect 
                            {--daemon} 
                            {--baseline-window=5} 
                            {--sensitivity=2.0} 
                            {--alert-threshold=0.05} 
                            {--dry-run}'; 

    protected $description = 'Requirement 1: AIOps Detection Engine';

    public function handle(PrometheusService $prometheus)
    {
        $this->info("Starting AIOps Detection Engine...");
        $isDaemon = $this->option('daemon');
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) $this->warn("!!! RUNNING IN DRY-RUN MODE (No Incidents will be saved) !!!");

        do {
            $this->runDetectionCycle($prometheus);
            if ($isDaemon) {
                // Requirement 1: Scan every 10 seconds
                sleep(10); 
            }
        } while ($isDaemon);
    }

    private function runDetectionCycle(PrometheusService $prometheus)
    {
        try {
            
            $currentTraffic = (float) $prometheus->getRequestRate();
            $currentErrors = (float) $prometheus->getErrorRate();
            $currentLatency = (float) $prometheus->getP95Latency();

            if ($currentTraffic == 0 && $currentLatency == 0) {
                $this->line("System Idle - Waiting for data...");
                return;
            }

            
            $baselineTraffic = $this->getOrCreateBaseline('traffic', $currentTraffic);
            $baselineLatency = 5.0; 

            $sensitivity = (float) $this->option('sensitivity');
            $alertThreshold = (float) $this->option('alert-threshold');

           
            $isTrafficSpike = $currentTraffic > ($baselineTraffic * $sensitivity) && $currentTraffic > 5;
            $isLatencySpike = $currentLatency > 7.0; 
            $isErrorSpike = $currentErrors > $alertThreshold;

            $rootCause = null;
            $severity = 'INFO';

           
            if ($isErrorSpike && $isLatencySpike) {
                $rootCause = 'DATABASE_FAILURE';
                $severity = 'CRITICAL';
            } elseif ($isTrafficSpike && $isLatencySpike) {
                $rootCause = 'RESOURCE_EXHAUSTION';
                $severity = 'CRITICAL';
            } elseif ($isErrorSpike) {
                $rootCause = 'APPLICATION_BUG';
                $severity = 'WARNING';
            } elseif ($isLatencySpike) {
                $rootCause = 'SERVICE_DEGRADATION';
                $severity = 'WARNING';
            }

            if ($rootCause) {
                $incidentId = 'INC-' . strtoupper(Str::random(6));
                $signals = [
                    'traffic' => round($currentTraffic, 2), 
                    'latency' => round($currentLatency, 2), 
                    'errors' => round($currentErrors, 3)
                ];

                
                event(new IncidentCreated($incidentId, $severity, $signals, "Anomaly: $rootCause"));

                
                $this->error("[" . now()->format('H:i:s') . "] [ALERT] [$severity] Anomaly Detected: $rootCause (ID: $incidentId)");

                
                if (!$this->option('dry-run')) {
                    $this->generateIncidentFile($incidentId, $rootCause, $severity, $signals);
                }
            } else {
                $this->info("System Normal - T: " . round($currentTraffic, 2) . ", L: " . round($currentLatency, 2) . ", E: " . round($currentErrors, 3));
            }
        } catch (\Exception $e) {
            $this->error("Cycle Error: " . $e->getMessage());
        }
    }

    
    private function getOrCreateBaseline($metricName, $currentValue)
    {
        $windowMinutes = (int) $this->option('baseline-window');

        $baseline = AiopsBaseline::firstOrCreate(
            ['metric_name' => $metricName],
            [
                'value' => $currentValue > 0 ? $currentValue : 1.0,
                'window_start' => now(),
                'window_end' => now()->addMinutes($windowMinutes)
            ]
        );

        return (float) $baseline->value;
    }

    
    private function generateIncidentFile($incidentId, $rootCause, $severity, $signals)
    {
        $file = storage_path('logs/incidents.json');
        $currentData = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
        
        $currentData[] = [
            'incident_id' => $incidentId,
            'timestamp' => now()->toIso8601String(),
            'root_cause' => $rootCause,
            'severity' => $severity,
            'signals' => $signals,
            'status' => 'OPEN'
        ];

        file_put_contents($file, json_encode($currentData, JSON_PRETTY_PRINT));
    }
}