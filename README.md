# AIOps Lab: End-to-End API Monitoring, Detection & Automated Response

## Overview
This project demonstrates a complete AIOps pipeline for a Laravel API. It features custom telemetry logging, Prometheus metrics exposition, and Grafana dashboards for monitoring Request Rates, Error Breakdowns, and Latency Spikes.

## Architecture & Tools
- **Backend:** Laravel PHP (Generates structured JSON logs and exposes `/api/metrics`).
- **Monitoring Stack:** Prometheus & Grafana (Dockerized).
- **Traffic Generation & ML:** Python scripts to simulate load, inject controlled anomalies, and run ML-based anomaly detection.

## Setup & Execution
1. **Start the Infrastructure:**
   Run the following command in the project root to start Prometheus and Grafana containers:
   ```bash
   docker-compose up -d
   ```

2. **Start the Laravel API:**
   Ensure your `.env` is configured, then run:
   ```bash
   php artisan serve
   ```

3. **Generate Traffic & Trigger Anomaly:**
   Run the Python script to simulate a base load of 3000+ requests and automatically trigger a 2-minute latency spike:
   ```bash
   python traffic_generator.py
   ```

## Anomaly Detection Details
A controlled anomaly (`latency_spike`) is injected using the `/api/slow?hard=1` endpoint. This causes response times to exceed 3500ms, which is successfully captured in the generated `ground_truth.json` file and clearly visualized on the Grafana latency dashboard.

---

## Lab Work 2: AIOps Detection Engine
In this phase, we built a proactive AIOps Detection Engine within Laravel to monitor system telemetry, establish dynamic baselines, and correlate anomalies in real-time based on Prometheus metrics.

### Implemented Requirements
* **Detection Engine Command:** Developed a continuous daemon command (`php artisan aiops:detect --daemon`).
* **Prometheus Metrics Integration:** Dynamically query `http_requests_total`, `http_errors_total`, etc.
* **Baseline Modeling:** Computes normal behavior and persists securely in the `aiops_baselines` database table.
* **Multi-Signal Detection:** Correlation matrix mapping combined signal deviations to precise Root Causes.
* **Alerting System:** Real-time terminal warnings and structured JSON incident generation.

---

## Lab Work 3: ML Anomaly Detection
Implemented a Machine Learning pipeline to automatically detect system anomalies based on telemetry data.

### Implementation Details
* **Dataset Construction:** Engineered a structured dataset using 30-second time windows.
* **Model Training:** Utilized the Isolation Forest algorithm with dynamic anomaly thresholding.
* **Anomaly Prediction:** The model successfully generalized to the full dataset, assigning high anomaly scores to the exact latency spike window.
* **Deliverables:** `ml_aiops.py`, `aiops_dataset.csv`, `anomaly_predictions.csv`, `anomaly_visualization.png`.

---

## Lab Work 4: Automated Root Cause Analysis (RCA)
In this phase, we transitioned from anomaly detection to understanding why the anomaly occurred by developing an automated RCA engine that correlates system logs with ML signals.

### Key Deliverables:
1. **RCA Analyzer (`rca_analyzer.py`):** Scans Laravel logs, identifies the anomaly window, and isolates the faulty endpoint.
2. **Structured RCA Report (`rca_report.json`):** A structured output pinpointing the Root Cause Endpoint (`/api/slow`), the primary trigger signal (`latency`), and calculating a Confidence Score (95%).
3. **Visual Timeline (`incident_timeline.jpg`):** A graphical timeline illustrating the normal state, anomaly start, peak incident, and recovery phase.
4. **Engineering Report (`RCA report.pdf`):** A detailed 2-page analysis documenting the endpoint attribution and the distribution of `TIMEOUT_ERROR` categories during the incident.

---

## Lab 5: Automated Incident Response & Escalation
A fully functional Automation Engine developed in Laravel to seamlessly mitigate system anomalies detected in the previous labs.

### Implementation Details:
1. **Automation Engine Command (`aiops:respond`):** A custom Artisan command built to monitor active incidents and execute resolution workflows based on ML outputs.
2. **Response Policy Mapping:** Specific anomalies are mapped to automated actions:
   * `LATENCY_SPIKE` → Executed simulated `restart_service`.
   * `ERROR_STORM` → Executed simulated `send_alert`.
   * `TRAFFIC_SPIKE` → Executed simulated `rate_limit`.
3. **Response Logging:** Every evaluated incident produces a structured log entry containing `incident_id`, `action_taken`, `result`, and `notes`. These records are securely persisted in `storage/app/private/aiops/responses.json`.

### 🛡️ Response Strategy Note (Safety First)
> **Engineering Note:** The automated response system is rigorously designed around a "Safety First" operational principle. For incidents identified by the ML pipeline as having a **CRITICAL** severity, or if regular automated actions fail consecutively (attempts >= 2), the engine is programmed to bypass standard automated remediation. Instead, it triggers an immediate **CRITICAL_ALERT_ESCALATION** to human operators. This specific escalation logic prevents potentially risky automated actions during severe system degradation and ensures critical oversight.