# AIOps Lab: API Monitoring & Anomaly Detection

## Overview
This project demonstrates a complete AIOps pipeline for a Laravel API. It features custom telemetry logging, Prometheus metrics exposition, and Grafana dashboards for monitoring Request Rates, Error Breakdowns, and Latency Spikes.

## Architecture & Tools
- **Backend:** Laravel PHP (Generates structured JSON logs and exposes `/api/metrics`).
- **Monitoring Stack:** Prometheus & Grafana (Dockerized).
- **Traffic Generation & Evaluation:** Python scripts to simulate normal load, inject controlled anomalies, and extract logs.

## Setup & Execution
1. **Start the Infrastructure:**
   Run the following command in the project root to start Prometheus and Grafana containers:
   ```bash
   docker-compose up -d

2. **Start the Laravel API:**
Ensure your .env is configured, then run:
    php artisan serve

3. **Generate Traffic & Trigger Anomaly:**
Run the Python script to simulate a base load of 3000+ requests and automatically trigger a 2-minute latency spike:
    python traffic_generator.py

**Anomaly Detection Details**
A controlled anomaly (latency_spike) is injected using the /api/slow?hard=1 endpoint. This causes response times to exceed 3500ms, which is successfully captured in the generated ground_truth.json file and clearly visualized on the Grafana latency dashboard.

**Deliverables Included**
. logs.json: Extracted structured logs (>22,500 entries).

. ground_truth.json: Exact timestamps and details of the injected anomaly window.

. dashboard.json: The Grafana JSON model containing all 3 required panels.

. docker-compose.yml & prometheus.yml: Configuration files.

. traffic_generator.py & log_exporter.py: Python automation scripts.

. aiops.log: The raw application log file.