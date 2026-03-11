<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PrometheusService;
use App\Models\AiopsBaseline; 
use App\Events\IncidentCreated; // 🔥 أضفنا استدعاء الـ Event هنا
use Illuminate\Support\Str;

class AiopsDetectCommand extends Command
{
    // Requirement 1: تعريف الأمر والـ Flags المطلوبة
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
            // Requirement 2: سحب البيانات من PrometheusService
            $currentTraffic = (float) $prometheus->getRequestRate();
            $currentErrors = (float) $prometheus->getErrorRate();
            $currentLatency = (float) $prometheus->getP95Latency();

            if ($currentTraffic == 0 && $currentLatency == 0) {
                $this->line("System Idle - Waiting for data...");
                return;
            }

            // Requirement 3: سحب الـ Baseline من قاعدة البيانات
            $baselineTraffic = $this->getOrCreateBaseline('traffic', $currentTraffic);
            $baselineLatency = 5.0; // قيمة ثابتة لتجنب الـ False Positives في البيئة المحلية

            $sensitivity = (float) $this->option('sensitivity');
            $alertThreshold = (float) $this->option('alert-threshold');

            // Requirement 4: كشف الانحرافات (Anomaly Detection)
            $isTrafficSpike = $currentTraffic > ($baselineTraffic * $sensitivity) && $currentTraffic > 5;
            $isLatencySpike = $currentLatency > 7.0; 
            $isErrorSpike = $currentErrors > $alertThreshold;

            $rootCause = null;
            $severity = 'INFO';

            // Requirement 5: تحديد السبب الجذري (Event Correlation)
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

                // 🔥 Requirement 7: Trigger Laravel Event
                event(new IncidentCreated($incidentId, $severity, $signals, "Anomaly: $rootCause"));

                // Requirement 7: Alerting in Console
                $this->error("[" . now()->format('H:i:s') . "] [ALERT] [$severity] Anomaly Detected: $rootCause (ID: $incidentId)");

                // Requirement 6: Structured Incident Generation (JSON)
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

    // Requirement 3: Baseline Modeling (Database Storage)
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

    // Requirement 6: Structured Incident Logging
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