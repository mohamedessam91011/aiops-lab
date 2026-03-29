<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AiopsRespond extends Command
{
    // 1) Automation Engine Command Signature
    protected $signature = 'aiops:respond';
    protected $description = 'AIOps Automated Incident Response Engine';

    // 2) Response Policies
    private $policies = [
        'LATENCY_SPIKE' => 'restart_service',
        'ERROR_STORM'   => 'send_alert',
        'TRAFFIC_SURGE' => 'scale_service'
    ];

    public function handle()
    {
        $this->info(" Starting AIOps Automated Response Engine...");

        $aiopsDir = storage_path('aiops');
        $incidentsPath = $aiopsDir . '/incidents.json';
        $responsesPath = $aiopsDir . '/responses.json';

        // Ensure directory exists
        if (!File::exists($aiopsDir)) {
            File::makeDirectory($aiopsDir, 0755, true);
        }

        // Generate dummy incidents for demonstration if file doesn't exist
        //if (!File::exists($incidentsPath)) {
          //  $this->generateDummyIncidents($incidentsPath);
        //}

        $incidents = json_decode(File::get($incidentsPath), true);
        $responses = File::exists($responsesPath) ? json_decode(File::get($responsesPath), true) : [];

        $hasActiveIncidents = false;

        foreach ($incidents as $key => $incident) {
            if ($incident['status'] === 'RESOLVED') continue;
            
            $hasActiveIncidents = true;
            $incidentId = $incident['id'];
            $type = $incident['type'];
            $attempts = $incident['attempts'] ?? 0;

            $this->warn("  Processing Incident [{$incidentId}]: {$type}");

            // Match Policy
            $action = $this->policies[$type] ?? 'unknown_action';

            // 5) Escalation Logic
            $isEscalated = false;
            // Escalate if it failed multiple times OR if severity is CRITICAL
            if ($attempts >= 2 || $incident['severity'] === 'CRITICAL') {
                $action = 'CRITICAL_ALERT_ESCALATION';
                $isEscalated = true;
                $this->error("    ESCALATION TRIGGERED: Maximum attempts reached or Critical Severity.");
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

            // 4) Incident Response Logging
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
            $this->info(" No active incidents found. System is healthy.");
        } else {
            // Save updates to storage
            File::put($incidentsPath, json_encode($incidents, JSON_PRETTY_PRINT));
            File::put($responsesPath, json_encode($responses, JSON_PRETTY_PRINT));
            $this->info("\n Automated response cycle completed. Logs saved to storage/aiops/responses.json");
        }
    }

    private function simulateAction($action, $isEscalated)
    {
        if ($isEscalated) return 'ESCALATED';
        // Simulate 90% success rate for regular automated actions
        return (rand(1, 10) > 1) ? 'SUCCESS' : 'FAILED';
    }

    private function generateDummyIncidents($path)
    {
        $data = [
            ['id' => 'INC-001', 'type' => 'LATENCY_SPIKE', 'severity' => 'HIGH', 'status' => 'OPEN', 'attempts' => 0],
            ['id' => 'INC-002', 'type' => 'ERROR_STORM', 'severity' => 'MEDIUM', 'status' => 'OPEN', 'attempts' => 0],
            ['id' => 'INC-003', 'type' => 'TRAFFIC_SURGE', 'severity' => 'CRITICAL', 'status' => 'OPEN', 'attempts' => 0], // Triggers instant escalation
            ['id' => 'INC-004', 'type' => 'LATENCY_SPIKE', 'severity' => 'HIGH', 'status' => 'OPEN', 'attempts' => 2], // Triggers escalation due to attempts
        ];
        File::put($path, json_encode($data, JSON_PRETTY_PRINT));
    }
}