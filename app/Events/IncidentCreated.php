<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncidentCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $incident_id;
    public $severity_level;
    public $affected_metrics;
    public $message;
    public function __construct($incident_id, $severity_level, $affected_metrics, $message = '')
    {
        $this->incident_id = $incident_id;
        $this->severity_level = $severity_level;
        $this->affected_metrics = $affected_metrics;
        $this->message = $message;
    }
}