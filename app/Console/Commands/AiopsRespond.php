<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class AiopsRespond extends Command
{
    // 1) Automation Engine Command Signature
    protected $signature = 'aiops:respond';
    protected $description = 'AIOps Automated Incident Response Engine';

    // 2) Response Policies (تم إضافة TRAFFIC_SPIKE اللي بتطلع من لاب 4)
    private $policies = [
        'LATENCY_SPIKE' => 'restart_service',
        'ERROR_STORM'   => 'send_alert',
        'TRAFFIC_SURGE' => 'scale_service',
        'TRAFFIC_SPIKE' => 'rate_limit',       // الأكشن المناسب لمشكلة الترافيك
        'APPLICATION_BUG' => 'rollback_deploy' // الأكشن المناسب لمشكلة الأكواد
    ];

    public function handle()
    {
        $this->info("⚙️ Starting AIOps Automated Response Engine...");

        // استخدام Storage للوصول لنفس مسار لاب 4 بالظبط (بيقرأ من storage/app/aiops)
        $incidentsPath = 'aiops/incidents.json';
        $responsesPath = 'aiops/responses.json';

        if (!Storage::exists($incidentsPath)) {
            $this->info("✅ No active incidents found. System is healthy.");
            return;
        }

        $incidents = json_decode(Storage::get($incidentsPath), true) ?? [];
        $responses = Storage::exists($responsesPath) ? json_decode(Storage::get($responsesPath), true) : [];

        $hasActiveIncidents = false;

        foreach ($incidents as $key => $incident) {
            // التوافق مع مفاتيح Lab 4 الجديدة
            $status = $incident['status'] ?? 'OPEN';
            if ($status === 'RESOLVED') continue;
            
            $hasActiveIncidents = true;
            $incidentId = $incident['incident_id'] ?? $incident['id'] ?? 'UNKNOWN';
            $type = $incident['root_cause'] ?? $incident['type'] ?? 'UNKNOWN';
            $attempts = $incident['attempts'] ?? 0;
            $severity = $incident['severity'] ?? 'HIGH';

            $this->warn("🚨 Processing Incident [{$incidentId}]: {$type}");

            // Match Policy
            $action = $this->policies[$type] ?? 'unknown_action';

            // 5) Escalation Logic
            $isEscalated = false;
            // يتم التصعيد لو المحاولات زادت عن 2 أو لو المشكلة CRITICAL
            if ($attempts >= 2 || $severity === 'CRITICAL') {
                $action = 'CRITICAL_ALERT_ESCALATION';
                $isEscalated = true;
                $this->error("🔥 ESCALATION TRIGGERED: Maximum attempts reached or Critical Severity.");
            }

            // 3) Action Execution (Simulated)
            $result = $this->simulateAction($action, $isEscalated);

            // Update incident state
            $incidents[$key]['attempts'] = $attempts + 1;
            if ($result === 'SUCCESS' && !$isEscalated) {
                $incidents[$key]['status'] = 'RESOLVED';
            } elseif ($isEscalated) {
                $incidents[$key]['status'] = 'ESCALATED';
            }

            // 4) Incident Response Logging (زي ما الـ Rubric طالب بالظبط)
            $logEntry = [
                'incident_id'  => $incidentId,
                'action_taken' => $action,
                'timestamp'    => now()->toIso8601String(),
                'result'       => $result,
                'notes'        => $isEscalated ? "Escalated to human operators. Automated action bypassed." : "Automated policy executed successfully."
            ];

            $responses[] = $logEntry;
            $this->line("   ↳ Action: {$action} | Result: {$result}");
        }

        if (!$hasActiveIncidents) {
            $this->info("✅ No active incidents found. System is healthy.");
        } else {
            // حفظ التحديثات في نفس المسار
            Storage::put($incidentsPath, json_encode($incidents, JSON_PRETTY_PRINT));
            Storage::put($responsesPath, json_encode($responses, JSON_PRETTY_PRINT));
            $this->info("\n💾 Automated response cycle completed. Logs saved to storage/app/{$responsesPath}");
        }
    }

    private function simulateAction($action, $isEscalated)
    {
        if ($isEscalated) return 'ESCALATED';
        return (rand(1, 10) > 1) ? 'SUCCESS' : 'FAILED';
    }
}