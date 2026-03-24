import pandas as pd
import numpy as np
import matplotlib.pyplot as plt
import seaborn as sns
from sklearn.ensemble import IsolationForest
from sklearn.preprocessing import StandardScaler
import warnings
warnings.filterwarnings('ignore')

print("Starting AIOps ML Anomaly Detection...")

# 1) Load Data
print("[1/6] Loading logs.json...")
try:
    df_raw = pd.read_json('logs.json')
except ValueError:
    df_raw = pd.read_json('logs.json', lines=True)

# Column mapping
col_mapping = {'latency_ms': 'latency', 'route_name': 'endpoint', 'status_code': 'status'}
df_raw.rename(columns=col_mapping, inplace=True)

# Handle missing timestamp
if 'timestamp' not in df_raw.columns:
    print("  Generating synthetic timeline...")
    freq_ms = max(1, int((600 * 1000) / len(df_raw)))
    df_raw['timestamp'] = pd.date_range(start='2026-03-12 20:00:00', 
                                        periods=len(df_raw), 
                                        freq=f'{freq_ms}ms')

df_raw['timestamp'] = pd.to_datetime(df_raw['timestamp'])

# Error categorization
def get_error_category(status):
    if status >= 500: return 'Server_Error'
    elif status >= 400: return 'Client_Error'
    return 'None'

df_raw['error_category'] = df_raw['status'].apply(get_error_category)
df_raw['is_error'] = df_raw['status'] >= 400

# 2) Feature Engineering
print("[2/6] Feature Engineering (30s windows)...")
df_raw.set_index('timestamp', inplace=True)

features = df_raw.resample('30s').agg(
    avg_latency=('latency', 'mean'),
    max_latency=('latency', 'max'),
    latency_std=('latency', 'std'),
    request_rate=('endpoint', 'count'),
    errors_per_window=('is_error', 'sum'),
    endpoint_frequency=('endpoint', 'nunique')
)

features['error_rate'] = features['errors_per_window'] / features['request_rate']
features.fillna(0, inplace=True)
features.to_csv('aiops_dataset.csv')
print(f" Dataset: {len(features)} windows × 75 req/window = {len(features)*75} observations")

# 3) Define Normal Period Explicitly
print("[3/6] Defining normal behavior period...")
normal_end_time = features.index[0] + pd.Timedelta(minutes=5)
df_normal = features[features.index < normal_end_time]
print(f" Training on normal period: {len(df_normal)} windows")

# 4) Train Model
print("[4/6] Training Isolation Forest...")
ml_features = ['avg_latency', 'max_latency', 'request_rate', 'error_rate', 
               'latency_std', 'errors_per_window', 'endpoint_frequency']

X_train = df_normal[ml_features]

# Calculate contamination based on expected anomaly windows
total_windows = len(features)
expected_anomaly_windows = 3  # Based on Lab 1
contamination = expected_anomaly_windows / total_windows

model = IsolationForest(contamination='auto', random_state=42)
model.fit(X_train)

# 5) Predict
print("[5/6] Predicting anomalies...")
X_all = features[ml_features]
predictions = model.predict(X_all)
features['is_anomaly'] = predictions == -1
features['anomaly_score'] = model.decision_function(X_all) * -1

# Save predictions
features[['anomaly_score', 'is_anomaly']].to_csv('anomaly_predictions.csv')
print("  Saved anomaly_predictions.csv")

# 6) Visualize
print("[6/6] Generating visualizations...")
sns.set_theme(style="darkgrid")
fig, (ax1, ax2, ax3) = plt.subplots(3, 1, figsize=(15, 12), sharex=True)

anomalies = features[features['is_anomaly']]

# Latency plot
ax1.plot(features.index, features['avg_latency'], color='blue', linewidth=1, label='Avg Latency')
ax1.scatter(anomalies.index, anomalies['avg_latency'], color='red', s=50, label='Anomaly')
ax1.set_ylabel('Latency (ms)')
ax1.legend()
ax1.set_title('AIOps: Latency Timeline with ML Anomalies')

# Error rate plot
ax2.plot(features.index, features['error_rate'], color='orange', linewidth=1, label='Error Rate')
ax2.scatter(anomalies.index, anomalies['error_rate'], color='red', s=50)
ax2.set_ylabel('Error Rate')
ax2.legend()
ax2.set_title('Error Rate Timeline')

# Anomaly score plot
ax3.plot(features.index, features['anomaly_score'], color='green', linewidth=1, label='Anomaly Score')
ax3.axhline(y=0, color='gray', linestyle='--', alpha=0.5)
ax3.fill_between(features.index, 0, features['anomaly_score'], 
                  where=(features['anomaly_score'] > 0), color='red', alpha=0.3)
ax3.set_ylabel('Anomaly Score')
ax3.set_xlabel('Timestamp')
ax3.legend()
ax3.set_title('Anomaly Score Timeline')

plt.tight_layout()
plt.savefig('anomaly_visualization.png', dpi=300)
print("  Saved anomaly_visualization.png")

# 7) Report
print("\n" + "="*50)
print("ANOMALY DETECTION REPORT")
print("="*50)
print(f"Total windows: {len(features)}")
print(f"Anomalies detected: {features['is_anomaly'].sum()}")
print(f"Anomaly ratio: {features['is_anomaly'].mean():.2%}")

# Check if Lab 1 anomaly detected
lab1_anomaly = pd.Timestamp('2026-03-12 20:05:30')
if lab1_anomaly in anomalies.index:
    print(f" Successfully detected Lab 1 anomaly at {lab1_anomaly}")
else:
    print(f"  Lab 1 anomaly at {lab1_anomaly} not detected")

print("\n Lab 3 Complete!")