import sys
filepath = sys.argv[1]
keep_lines = int(sys.argv[2])

with open(filepath, 'r') as f:
    lines = f.readlines()

with open(filepath, 'w') as f:
    f.writelines(lines[:keep_lines])

print(f"File truncated from {len(lines)} to {keep_lines} lines")
