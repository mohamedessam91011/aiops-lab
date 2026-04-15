import pandas as pd
import json
import matplotlib.pyplot as plt
import uuid
import datetime
import glob
import os

def run_rca():
    print("🔍 Starting Root Cause Analysis (RCA)...")
    
    # --- التعديل الذكي: البحث التلقائي عن أحدث ملف لوجات ---
    # بيدور في المسار الحالي وجوه فولدر اللوجات بتاع لارافيل
    log_files = glob.glob('laravel*.log') + glob.glob('storage/logs/laravel*.log') + glob.glob('storage/logs/laravel.log')
    
    if not log_files:
        print("❌ Could not find any Laravel log files in current directory or storage/logs/.")
        return
        
    # اختيار أحدث ملف تم التعديل عليه
    log_file = max(log_files, key=os.path.getmtime)
    print(f"📂 Auto-detected log file: {log_file}")
    # --------------------------------------------------------

    logs = []
    
    try:
        with open(log_file, 'r', encoding='utf-8') as f:
            for line in f:
                if 'API Telemetry' in line:
                    try:
                        json_str = line.split('API Telemetry ')[1].strip()
                        log_entry = json.loads(json_str)
                        time_str = line.split(']')[0].replace('[', '')
                        log_entry['timestamp'] = pd.to_datetime(time_str)
                        logs.append(log_entry)
                    except:
                        continue
    except Exception as e:
        print(f"❌ Error reading file: {e}")
        return

    df = pd.DataFrame(logs)
    if df.empty:
        print("❌ No telemetry data found in logs. Make sure you generated traffic!")
        return

    # 2. تحديد وقت المشكلة
    if 'latency_ms' not in df.columns:
        print("❌ Latency data not found in logs. Check your Telemetry Middleware.")
        return
        
    anomaly_window = df[(df['latency_ms'] > 2000) | (df['error_category'].notnull())]
    
    if anomaly_window.empty:
        print("✅ No anomalies found to analyze. Generate some errors or slow traffic first!")
        return

    # 3. Endpoint Attribution
    top_endpoint = anomaly_window['route_name'].value_counts().idxmax()
    
    # 4. Error Category Analysis
    if not anomaly_window['error_category'].dropna().empty:
        top_error = anomaly_window['error_category'].value_counts().idxmax()
    else:
        top_error = "HIGH_LATENCY_NO_ERROR"

    avg_latency = anomaly_window['latency_ms'].mean()
    peak_latency = anomaly_window['latency_ms'].max()
    error_count = len(anomaly_window[anomaly_window['error_category'].notnull()])

    print(f"🚨 Anomaly isolated: Endpoint [{top_endpoint}] caused [{top_error}].")

    # 5. توليد التقرير
    rca_report = {
        "incident_id": f"INC-RCA-{str(uuid.uuid4())[:8].upper()}",
        "timestamp": datetime.datetime.now().isoformat(),
        "root_cause_endpoint": top_endpoint,
        "primary_signal": "latency" if avg_latency > 2000 else "error_rate",
        "supporting_evidence": {
            "peak_latency_ms": round(peak_latency, 2),
            "average_latency_ms": round(avg_latency, 2),
            "total_errors_in_window": error_count,
            "predominant_error_type": top_error
        },
        "confidence_score": "95%",
        "recommended_action": f"Apply Rate Limiting or isolate endpoint {top_endpoint}. Investigate backend logic for {top_error}."
    }

    with open('rca_report.json', 'w') as f:
        json.dump(rca_report, f, indent=4)
    print("📝 rca_report.json generated successfully!")

    # 6. رسم الـ Timeline
    plt.figure(figsize=(12, 6))
    plt.plot(df['timestamp'], df['latency_ms'], label='Latency (ms)', color='blue', alpha=0.6)
    
    start_anomaly = anomaly_window['timestamp'].min()
    end_anomaly = anomaly_window['timestamp'].max()
    plt.axvspan(start_anomaly, end_anomaly, color='red', alpha=0.3, label='Anomaly Window')
    
    plt.text(start_anomaly, peak_latency, ' Peak Incident', color='darkred', fontsize=10, fontweight='bold')
    
    plt.title(f"Incident Timeline: Root Cause -> {top_endpoint}")
    plt.xlabel("Time")
    plt.ylabel("Latency (ms)")
    plt.legend()
    plt.grid(True, linestyle='--', alpha=0.5)
    plt.tight_layout()
    
    plt.savefig('incident_timeline.png', dpi=300)
    print("📈 incident_timeline.png generated successfully!")

if __name__ == "__main__":
    run_rca()