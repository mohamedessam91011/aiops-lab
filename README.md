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

---

##  Lab Work 2: AIOps Detection Engine

In this phase, we built a proactive AIOps Detection Engine within Laravel to monitor system telemetry, establish dynamic baselines, and correlate anomalies in real-time based on Prometheus metrics.

###  Implemented Requirements (100% Compliance)

* **1. Detection Engine Command:** Developed a continuous daemon command (`php artisan aiops:detect --daemon`) with configurable flags (`--sensitivity`, `--alert-threshold`, `--dry-run`, `--baseline-window`) scanning every 10 seconds.
* **2. Prometheus Metrics Integration:** Integrated with the Prometheus HTTP API to dynamically query `http_requests_total`, `http_errors_total`, and `http_request_duration_seconds_bucket`.
* **3. Baseline Modeling:** Engineered a baseline modeling system that computes normal behavior during a predefined window. Data is persisted securely in a relational database (`aiops_baselines` table) capturing `window_start` and `window_end`.
* **4 & 5. Multi-Signal Detection & Event Correlation:** Implemented a correlation matrix that maps combined signal deviations (Traffic, Latency, Errors) to precise Root Causes (e.g., `RESOURCE_EXHAUSTION`, `APPLICATION_BUG`, `DATABASE_FAILURE`, `SERVICE_DEGRADATION`).
* **6. Structured Incident Generation:** Configured a logging mechanism that generates detailed JSON incident reports mapping Incident IDs to timestamps, root causes, and signal snapshots (`storage/logs/incidents.json`).
* **7. Alerting System:** Built a real-time alerting system outputting structured terminal warnings (Timestamp, Severity, Incident ID, Root Cause Summary) while simultaneously triggering a Laravel Event (`IncidentCreated`) for background processing.

### Evidence
Screenshots validating the baseline database persistence, proactive alerting, and incident generation are available in the `lab2-screenshots/` directory.