import pymysql
from pathlib import Path

def load_env(path):
    env = {}
    for raw in path.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, _, v = line.partition("=")
        env[k.strip()] = v.strip().strip('"').strip("'")
    return env

env = load_env(Path(r"D:\lumen-v3\.env"))
conn = pymysql.connect(
    host=env["DB_HOST"],
    user=env["DB_USERNAME"],
    password=env["DB_PASSWORD"],
    database=env["DB_DATABASE"],
    charset="utf8mb4",
    cursorclass=pymysql.cursors.DictCursor,
)
cur = conn.cursor()
cur.execute(
    """
    SELECT trx_id, trx_state, trx_started, trx_mysql_thread_id,
           trx_query, trx_rows_locked, trx_rows_modified
    FROM information_schema.innodb_trx
    """
)
for r in cur.fetchall():
    print("TRX:", r)
    tid = r["trx_mysql_thread_id"]
    try:
        cur.execute(f"KILL {tid}")
        print(f"Killed zombie thread {tid}")
    except Exception as e:
        print(f"Failed to kill {tid}: {e}")
conn.close()
