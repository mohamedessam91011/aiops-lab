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

---

##  Lab Work 3: ML Anomaly Detection

In this final phase, we implemented a Machine Learning pipeline to automatically detect system anomalies based on telemetry data from Lab 1.

###  Implementation Details

* **1. Dataset Construction:** Extracted raw logs and engineered a structured dataset using 30-second time windows. Addressed missing timestamps via synthetic sequencing to maintain temporal order.
* **2. Feature Engineering:** Computed 7 key operational features per window: `avg_latency`, `max_latency`, `request_rate`, `error_rate`, `latency_std`, `errors_per_window`, and `endpoint_frequency`.
* **3. Model Training:** Utilized the **Isolation Forest** algorithm with dynamic anomaly thresholding (`contamination='auto'`). Adhering to strict constraints, the model was trained exclusively on the first 5 minutes of the timeline (the confirmed normal behavior baseline).
* **4. Anomaly Prediction:** The model successfully generalized to the full dataset, accurately assigning high anomaly scores to the exact window where the latency spike (reaching ~7000ms) was artificially injected.
* **5. Visualization:** Generated comparative timelines highlighting detected anomalies against average latency and error rates (`anomaly_visualization.png`).

###  Deliverables
- `ml_aiops.py`: Full ML pipeline script.
- `aiops_dataset.csv`: Engineered feature dataset.
- `anomaly_predictions.csv`: Model predictions and scores.
- `anomaly_visualization.png`: Visual proof of accurate detection.
- `Lab3_ML_Report.pdf`: Engineering report detailing feature selection and model performance.

## Lab 5: Automated Incident Response

In this final phase, a fully functional Automation Engine was developed in Laravel to automatically mitigate system anomalies detected in previous labs.

### Implementation Details:
1. **Automation Engine Command (`aiops:respond`):** A custom Artisan command was built to monitor active incidents and execute resolution workflows based on real data exported from the ML pipeline.
2. **Response Policy Logic:** Mapped specific anomalies to automated actions:
   * `LATENCY_SPIKE` → Executed simulated `restart_service` action.
   * `ERROR_STORM` → Executed simulated `send_alert` action.
   * `TRAFFIC_SURGE` → Executed simulated `scale_service` action.
3. **Escalation Handling:** Implemented strict safety guardrails. If an incident has a `CRITICAL` severity, or if automated actions fail consecutively (attempts >= 2), the system aborts automation and triggers a `CRITICAL_ALERT_ESCALATION` to human operators.
4. **Response Logging:** Every evaluated incident produces a structured log entry containing `incident_id`, `action_taken`, `timestamp`, `result`, and `notes`. These records are successfully persisted in `storage/aiops/responses.json`.