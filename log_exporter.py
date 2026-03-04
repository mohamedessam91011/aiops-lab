import json
import os
import glob

log_dir = os.path.join('storage', 'logs')
list_of_files = glob.glob(os.path.join(log_dir, '*.log'))

output_file = 'logs.json'
logs_list = []

if not list_of_files:
    print("No log files found in storage/logs/")
else:
    latest_log = max(list_of_files, key=os.path.getctime)
    print(f"Reading from: {latest_log}")
    
    with open(latest_log, 'r', encoding='utf-8') as f:
        for line in f:
            if 'API Telemetry' in line:
                try:
                    
                    json_part = line.split('API Telemetry ')[1]
                    logs_list.append(json.loads(json_part))
                except:
                    continue

    final_logs = logs_list[-1500:]

    with open(output_file, 'w') as f:
        json.dump(final_logs, f, indent=4)

    print(f"Done! Created {output_file} with {len(final_logs)} entries.")