<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AiopsDetectCommand extends Command
{
    // Requirement: Daemon mode to continuously monitor
    protected $signature = 'aiops:detect {--daemon : Run continuously every 10 seconds}';
    protected $description = 'AIOps Detection Engine - Monitors ML predictions for anomalies';

    public function handle()
    {
        $this->info("🚀 Starting AIOps Detection Engine (Reading ML Outputs)...");

        do {
            $this->scanPredictions();

            if ($this->option('daemon')) {
                $this->info("⏳ Waiting 10 seconds for next scan...");
                sleep(10);
            }
        } while ($this->option('daemon'));
    }

    private function scanPredictions()
    {
        $this->line("\n🔍 --- Scanning ML Predictions ---");

        $csvPath = 'anomaly_predictions.csv'; 
        $fullPath = base_path($csvPath);

        if (!file_exists($fullPath)) {
            $this->warn("⚠️ Predictions file not found at: {$fullPath}. Waiting for Lab 3...");
            return;
        }

        $lines = file($fullPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (count($lines) < 2) return;

        // تنظيف الهيدر
        $headerLine = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', array_shift($lines));
        $header = array_map('trim', str_getcsv($headerLine));

        $hasAnomaly = false;
        $latestAnomalyData = null;
        $actualValue = null;

        $recentLines = array_slice($lines, -5);
        foreach ($recentLines as $line) {
            $data = array_map('trim', str_getcsv($line));
            if (count($header) !== count($data)) continue;
            
            $row = array_combine($header, $data);

            // دلوقتي إحنا عارفين اسم العمود بالظبط من الديباج!
            $anomalyValue = $row['is_anomaly'] ?? null;

            if ($anomalyValue !== null) {
                $actualValue = $anomalyValue;
                $valLower = strtolower($anomalyValue);
                
                // في الـ Isolation Forest، (-1) معناها Anomaly، أو كلمة True لو إنت مغيرها في البايثون
                if ($valLower === 'true' || $valLower === 'yes' || $anomalyValue === '-1') {
                    $hasAnomaly = true;
                    $latestAnomalyData = $row;
                    break;
                }
            }
        }

        if ($hasAnomaly) {
            // بما إن الـ CSV مفيهوش Latency، هنعتمد إن الـ ML لقط مشكلة ضغط ترافيك كسبب افتراضي
            $rootCause = 'TRAFFIC_SPIKE'; 
            $this->error("🚨 ML ANOMALY DETECTED! Root Cause: {$rootCause}");
            
            $this->logIncident($rootCause, 'CRITICAL', [
                'anomaly_score' => (float)($latestAnomalyData['anomaly_score'] ?? 0)
            ]);
        } else {
            $this->info("✅ Machine Learning confirms system is stable.");
            $this->line("ℹ️ [Debug] Checking column 'is_anomaly' | Last Value: " . ($actualValue ?? 'Null'));
        }
    }

    private function determineRootCause($data)
    {
        // تصنيف المشكلة بناءً على الداتا اللي جاية من الموديل
        $errorRate = (float) ($data['error_rate'] ?? 0);
        $latency = (float) ($data['latency_avg'] ?? 0);

        if ($errorRate > 0.1 && $latency > 2000) {
            return 'APPLICATION_BUG';
        } elseif ($latency > 2000) {
            return 'TRAFFIC_SPIKE';
        }
        
        return 'UNKNOWN_ANOMALY';
    }

    private function logIncident($rootCause, $severity, $signals)
    {
        $filePath = 'aiops/incidents.json';
        $incidents = [];

        if (Storage::exists($filePath)) {
            $incidents = json_decode(Storage::get($filePath), true) ?? [];
        }

        // عشان منسجلش نفس المشكلة مرتين في نفس الدقيقة
        $lastIncident = end($incidents);
        // تأمين قراءة الداتا القديمة (سواء كانت متسجلة باسم root_cause أو type)
        $lastCause = $lastIncident['root_cause'] ?? $lastIncident['type'] ?? null;
        $lastTimestamp = $lastIncident['timestamp'] ?? now()->toIso8601String();

        if ($lastIncident && $lastCause === $rootCause && (time() - strtotime($lastTimestamp)) < 60) {
            $this->line("ℹ️ Incident already logged recently. Skipping duplicate.");
            return;
        }

        $newIncident = [
            'incident_id' => 'INC-' . strtoupper(Str::random(6)),
            'timestamp'   => now()->toIso8601String(),
            'root_cause'  => $rootCause,
            'severity'    => $severity,
            'signals'     => $signals
        ];

        $incidents[] = $newIncident;

        Storage::put($filePath, json_encode($incidents, JSON_PRETTY_PRINT));
        $this->line("📝 Incident safely recorded for Lab 5 in storage/app/{$filePath}");
    }
}