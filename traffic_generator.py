import requests
import time
import random
import json
from datetime import datetime, timedelta

BASE_URL = "http://127.0.0.1:8000/api"

def send_request(endpoint, method="GET", data=None):
    try:
        url = f"{BASE_URL}/{endpoint}"
        if method == "POST":
            requests.post(url, json=data, timeout=10)
        else:
            requests.get(url, timeout=10)
    except:
        pass

print("Starting Traffic Generator (Base Load)...")
for i in range(3000):
    r = random.random()
    if r < 0.70: send_request("normal")
    elif r < 0.85: send_request("slow")
    elif r < 0.90: send_request("slow?hard=1")
    elif r < 0.95: send_request("error")
    elif r < 0.98: send_request("db")
    else: send_request("validate", "POST", {"email": "test@me.com", "age": random.randint(10, 70)})
    
    if i % 100 == 0: print(f"Sent {i} requests...")

print("Starting Anomaly Window (Latency Spike)...")
start_anomaly = datetime.now().isoformat()
for _ in range(500):
    send_request("slow?hard=1")

end_anomaly = datetime.now().isoformat()

ground_truth = {
    "anomaly_start_iso": start_anomaly,
    "anomaly_end_iso": end_anomaly,
    "anomaly_type": "Latency Spike",
    "expected_behavior": "Increase in TIMEOUT_ERROR in logs and Grafana"
}

with open("ground_truth.json", "w") as f:
    json.dump(ground_truth, f, indent=4)

print("Done! ground_truth.json created.")