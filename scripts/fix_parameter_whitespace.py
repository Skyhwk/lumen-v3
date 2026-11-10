#!/usr/bin/env python3
"""
Perbaikan whitespace (spasi/tab/newline di awal atau akhir) pada nama parameter.

Default: --dry-run (hanya hitung & contoh).
Pakai --apply untuk menjalankan UPDATE (butuh konfirmasi eksplisit).

Scope: sama dengan audit yang disetujui.
"""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

_SCRIPTS_DIR = Path(__file__).resolve().parent
if str(_SCRIPTS_DIR) not in sys.path:
    sys.path.insert(0, str(_SCRIPTS_DIR))

from parameter_whitespace_scope import (
    EXTENSION_JSON_QUOTATION,
    EXTENSION_SCALAR,
    JSON_ORDER_DETAIL,
    JSON_QUOTATION_SAMPLING,
    SCALAR_TARGETS,
)

try:
    import pymysql
except ImportError:
    print("pip install pymysql", file=sys.stderr)
    sys.exit(1)

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_ENV = ROOT / ".env"

PARAMETER_COLS = ("nama_lab", "nama_regulasi", "nama_lhp")


def whitespace_edge_sql(col: str) -> str:
    return (
        f"({col} IS NOT NULL AND {col} <> '' "
        f"AND ({col} REGEXP '^[[:space:]]' OR {col} REGEXP '[[:space:]]$'))"
    )

# Token JSON "id;nama" — whitespace tepat sebelum tanda kutip penutup string JSON.
JSON_WHITESPACE_PREFILTER = (
    "`{col}` REGEXP '[[:space:]]\"' OR `{col}` REGEXP '\"[[:space:]]'"
)

def json_row_filter(column: str, *, filter_is_active: bool) -> str:
    base = f"`{column}` IS NOT NULL AND `{column}` <> ''"
    if filter_is_active:
        return f"{active_where()} AND {base}"
    return base


def load_env(path: Path) -> dict[str, str]:
    env: dict[str, str] = {}
    for raw in path.read_text(encoding="utf-8", errors="replace").splitlines():
        line = raw.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, _, value = line.partition("=")
        env[key.strip()] = value.strip().strip('"').strip("'")
    return env


def db_connect(env: dict[str, str], *, autocommit: bool = False):
    return pymysql.connect(
        host=env.get("DB_HOST", "127.0.0.1"),
        port=int(env.get("DB_PORT", "3306")),
        user=env.get("DB_USERNAME", "root"),
        password=env.get("DB_PASSWORD", ""),
        database=env.get("DB_DATABASE", ""),
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=autocommit,
    )


def active_where(alias: str = "") -> str:
    p = f"{alias}." if alias else ""
    return f"({p}is_active = 1 OR {p}is_active = TRUE)"


def count_needs_trim(conn, table: str, column: str, alias: str = "") -> int:
    col_ref = f"{alias}.{column}" if alias else f"`{column}`"
    edge = whitespace_edge_sql(col_ref)
    sql = (
        f"SELECT COUNT(*) AS cnt FROM `{table}`"
        + (f" AS {alias}" if alias else "")
        + f" WHERE {active_where(alias)} AND {edge}"
    )
    with conn.cursor() as cur:
        cur.execute(sql)
        return int(cur.fetchone()["cnt"])


def normalize_ws(value: str) -> str:
    return value.strip(" \t\n\r\v\f")


def fix_id_nama_token(token: str) -> str:
    if not isinstance(token, str):
        return token
    if ";" in token:
        left, right = token.split(";", 1)
        return f"{left.strip()};{normalize_ws(right)}"
    return normalize_ws(token)


def token_needs_fix(token: str) -> bool:
    if not isinstance(token, str):
        return False
    fixed = fix_id_nama_token(token)
    return fixed != token


def parameter_needs_trim_cond() -> str:
    return " OR ".join(whitespace_edge_sql(f"`{c}`") for c in PARAMETER_COLS)


def execute_retry(cur, sql, args=None, *, retries: int = 5):
    import time

    last: Exception | None = None
    for attempt in range(retries):
        try:
            if args is None:
                cur.execute(sql)
            else:
                cur.execute(sql, args)
            return
        except pymysql.err.OperationalError as exc:
            if exc.args[0] != 1205 or attempt == retries - 1:
                raise
            last = exc
            wait = 3 * (attempt + 1)
            print(
                f"     Lock timeout, tunggu {wait}s lalu retry ({attempt + 1}/{retries})...",
                flush=True,
            )
            time.sleep(wait)
    if last:
        raise last


def update_one_row_with_retry(conn, sql, args, *, row_id) -> str:
    """Return: 'ok' | 'skip' (lock)."""
    import time

    for attempt in range(3):
        try:
            conn.rollback()
            with conn.cursor() as cur:
                execute_retry(cur, sql, args, retries=1)
            conn.commit()
            return "ok"
        except pymysql.err.OperationalError as exc:
            conn.rollback()
            if exc.args[0] != 1205:
                raise
            wait = 2 * (attempt + 1)
            print(
                f"     id={row_id} terkunci, tunggu {wait}s ({attempt + 1}/3)...",
                flush=True,
            )
            time.sleep(wait)
    print(f"     id={row_id} dilewati (masih lock).", flush=True)
    return "skip"


def update_row_isolated(env: dict[str, str], sql: str, args, *, row_id) -> str:
    """Koneksi baru per UPDATE — hindari transaksi Python mengganggu lock."""
    import time

    for attempt in range(3):
        conn = db_connect(env, autocommit=True)
        try:
            with conn.cursor() as cur:
                cur.execute("SET SESSION innodb_lock_wait_timeout = 8")
                cur.execute(sql, args)
            return "ok"
        except pymysql.err.OperationalError as exc:
            if exc.args[0] != 1205:
                raise
            wait = 2 * (attempt + 1)
            print(
                f"     id={row_id} terkunci, tunggu {wait}s ({attempt + 1}/3)...",
                flush=True,
            )
            time.sleep(wait)
        finally:
            conn.close()
    print(f"     id={row_id} dilewati (masih lock).", flush=True)
    return "skip"


def parse_skip_ids(raw: str | None) -> set[int]:
    if not raw:
        return set()
    out: set[int] = set()
    for part in raw.split(","):
        part = part.strip()
        if part:
            out.add(int(part))
    return out


def update_parameter_table(
    conn,
    apply: bool,
    env: dict[str, str],
    skip_ids: set[int],
    only_ids: set[int] | None = None,
) -> dict[str, int]:
    cond = parameter_needs_trim_cond()
    if not apply:
        if only_ids:
            placeholders = ",".join(["%s"] * len(only_ids))
            with conn.cursor() as cur:
                cur.execute(
                    f"SELECT COUNT(*) AS cnt FROM `parameter` WHERE id IN ({placeholders}) "
                    f"AND {active_where()} AND ({cond})",
                    tuple(only_ids),
                )
                return {"parameter (baris)": int(cur.fetchone()["cnt"])}
        with conn.cursor() as cur:
            cur.execute(
                f"SELECT COUNT(*) AS cnt FROM `parameter` WHERE {active_where()} AND ({cond})"
            )
            return {"parameter (baris)": int(cur.fetchone()["cnt"])}

    if only_ids:
        ids = sorted(only_ids)
    else:
        with conn.cursor() as cur:
            cur.execute(
                f"SELECT id FROM `parameter` WHERE {active_where()} AND ({cond}) ORDER BY id"
            )
            ids = [int(r["id"]) for r in cur.fetchall()]

    if skip_ids:
        print(f"  skip id: {sorted(skip_ids)}", flush=True)
    ids = [i for i in ids if i not in skip_ids]
    print(
        f"  parameter: {len(ids)} id dalam antrean (koneksi baru per UPDATE)",
        flush=True,
    )
    affected = 0
    skipped = 0
    for pid in ids:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id, is_active, nama_lab, nama_regulasi, nama_lhp "
                "FROM `parameter` WHERE id = %s",
                (pid,),
            )
            row = cur.fetchone()
        if not row:
            print(f"  id={pid} tidak ditemukan.", flush=True)
            continue
        nl = (
            normalize_ws(row["nama_lab"]) if row.get("nama_lab") is not None else None
        )
        nr = (
            normalize_ws(row["nama_regulasi"])
            if row.get("nama_regulasi") is not None
            else None
        )
        nh = (
            normalize_ws(row["nama_lhp"]) if row.get("nama_lhp") is not None else None
        )
        if (nl, nr, nh) == (
            row.get("nama_lab"),
            row.get("nama_regulasi"),
            row.get("nama_lhp"),
        ):
            continue
        status = update_row_isolated(
            env,
            "UPDATE `parameter` SET nama_lab=%s, nama_regulasi=%s, nama_lhp=%s WHERE id=%s",
            (nl, nr, nh, pid),
            row_id=pid,
        )
        if status == "ok":
            affected += 1
        else:
            skipped += 1
    if skipped:
        print(f"  parameter: {skipped} id dilewati karena lock (jalankan ulang nanti).", flush=True)
    return {"parameter (baris)": affected}


def update_scalar_column(conn, table: str, column: str, apply: bool) -> int:
    edge = whitespace_edge_sql(f"`{column}`")
    if not apply:
        return count_needs_trim(conn, table, column)

    with conn.cursor() as cur:
        cur.execute(
            f"SELECT id FROM `{table}` WHERE {active_where()} AND {edge} ORDER BY id"
        )
        ids = [int(r["id"]) for r in cur.fetchall()]

    affected = 0
    skipped = 0
    for pid in ids:
        with conn.cursor() as cur:
            cur.execute(
                f"SELECT id, `{column}` AS v FROM `{table}` WHERE id = %s",
                (pid,),
            )
            row = cur.fetchone()
        if not row:
            continue
        v = row["v"]
        nv = normalize_ws(v) if v is not None else v
        if nv == v:
            continue
        status = update_one_row_with_retry(
            conn,
            f"UPDATE `{table}` SET `{column}` = %s WHERE id = %s",
            (nv, pid),
            row_id=pid,
        )
        if status == "ok":
            affected += 1
        else:
            skipped += 1
    if skipped:
        print(f"  {table}: {skipped} id dilewati karena lock.", flush=True)
    return affected


def fix_json_column(
    conn, table: str, column: str, apply: bool, *, scanner
) -> tuple[int, int]:
    """Returns (rows_changed, tokens_changed)."""
    rows_changed = 0
    tokens_changed = 0
    pending: list[tuple[Any, str]] = []

    for row_id, new_json in scanner(conn):
        pending.append((row_id, new_json))
        if apply and len(pending) >= 500:
            flush_json_updates(conn, table, column, pending)
            rows_changed += len(pending)
            pending.clear()

    if apply and pending:
        flush_json_updates(conn, table, column, pending)
        rows_changed += len(pending)
    elif not apply:
        rows_changed = len(pending)

    return rows_changed, tokens_changed


def flush_json_updates(conn, table: str, column: str, rows: list[tuple[Any, str]]):
    with conn.cursor() as cur:
        for row_id, payload in rows:
            cur.execute(
                f"UPDATE `{table}` SET `{column}` = %s WHERE id = %s",
                (payload, row_id),
            )


def json_prefilter_sql(column: str) -> str:
    return JSON_WHITESPACE_PREFILTER.format(col=column)


def scan_order_detail_fixes(conn):
    sql = (
        f"SELECT id, `parameter` AS payload FROM `order_detail` "
        f"WHERE {active_where()} AND `parameter` IS NOT NULL AND `parameter` <> ''"
    )
    with conn.cursor() as cur:
        cur.execute(sql)
        while True:
            chunk = cur.fetchmany(2000)
            if not chunk:
                break
            for row in chunk:
                raw = row["payload"]
                try:
                    data = json.loads(raw)
                except json.JSONDecodeError:
                    continue
                if not isinstance(data, list):
                    continue
                changed = False
                new_list = []
                for token in data:
                    if isinstance(token, str) and token_needs_fix(token):
                        changed = True
                        new_list.append(fix_id_nama_token(token))
                    else:
                        new_list.append(token)
                if changed:
                    yield row["id"], json.dumps(new_list, ensure_ascii=False)


def make_scan_quotation_sampling_fixes(
    table: str, column: str, filter_is_active: bool
):
    where = json_row_filter(column, filter_is_active=filter_is_active)

    def scanner(conn):
        sql = f"SELECT id, `{column}` AS payload FROM `{table}` WHERE {where}"
        with conn.cursor() as cur:
            cur.execute(sql)
            while True:
                chunk = cur.fetchmany(500)
                if not chunk:
                    break
                for row in chunk:
                    raw = row["payload"]
                    try:
                        data = json.loads(raw)
                    except json.JSONDecodeError:
                        continue
                    if not isinstance(data, list):
                        continue
                    changed = False
                    for sample in data:
                        if not isinstance(sample, dict):
                            continue
                        plist = sample.get("parameter")
                        if not isinstance(plist, list):
                            continue
                        for i, token in enumerate(plist):
                            if isinstance(token, str) and token_needs_fix(token):
                                changed = True
                                plist[i] = fix_id_nama_token(token)
                    if changed:
                        yield row["id"], json.dumps(data, ensure_ascii=False)

    scanner.table = table  # type: ignore[attr-defined]
    scanner.column = column  # type: ignore[attr-defined]
    return scanner


def quotation_json_scanners(
    targets: list[tuple[str, str, bool]],
) -> list:
    return [
        make_scan_quotation_sampling_fixes(t, c, a) for t, c, a in targets
    ]


def count_json_fix_rows(conn, scanner) -> int:
    return sum(1 for _ in scanner(conn))


def count_remaining_whitespace(
    conn, *, include_scalar: bool = True, include_json: bool = True
) -> dict[str, int]:
    """Post-check: baris yang masih perlu trim."""
    out: dict[str, int] = {}
    if include_scalar:
        for col in PARAMETER_COLS:
            out[f"parameter.{col}"] = count_needs_trim(conn, "parameter", col)
        for table, col in SCALAR_TARGETS:
            out[f"{table}.{col}"] = count_needs_trim(conn, table, col)
    if include_json:
        out["order_detail.parameter (JSON)"] = count_json_fix_rows(
            conn, scan_order_detail_fixes
        )
        for table, column, active in JSON_QUOTATION_SAMPLING:
            scanner = make_scan_quotation_sampling_fixes(table, column, active)
            out[f"{table}.{column} (JSON)"] = count_json_fix_rows(conn, scanner)
    return out


def main() -> int:
    if hasattr(sys.stdout, "reconfigure"):
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")

    parser = argparse.ArgumentParser()
    parser.add_argument("--env", type=Path, default=DEFAULT_ENV)
    parser.add_argument(
        "--apply",
        action="store_true",
        help="Jalankan UPDATE (tanpa flag ini = dry-run)",
    )
    parser.add_argument(
        "--yes",
        action="store_true",
        help="Lewati prompt konfirmasi saat --apply",
    )
    parser.add_argument(
        "--json-only",
        action="store_true",
        help="Hanya perbaiki JSON order_detail & request_quotation",
    )
    parser.add_argument(
        "--scalar-only",
        action="store_true",
        help="Hanya master/scalar: parameter, master_harga_parameter, "
        "colorimetri, titrimetri, gravimetri, subkontrak (tanpa JSON)",
    )
    parser.add_argument(
        "--parameter-only",
        action="store_true",
        help="Hanya tabel parameter (3 kolom nama); paling aman saat banyak lock",
    )
    parser.add_argument(
        "--extension-only",
        action="store_true",
        help="Hanya scope perpanjangan: master_bakumutu + quotation kontrak H/D",
    )
    parser.add_argument(
        "--show-locks",
        action="store_true",
        help="Tampilkan query aktif di MySQL lalu keluar (bantu cek siapa yang lock)",
    )
    parser.add_argument(
        "--skip-ids",
        type=str,
        default="",
        help="Lewati id parameter tertentu, contoh: 32,74",
    )
    parser.add_argument(
        "--id",
        type=int,
        metavar="ID",
        help="Hanya proses satu baris parameter by id (cocok untuk coba satu-satu)",
    )
    parser.add_argument(
        "--ids",
        type=str,
        default="",
        help="Hanya id tertentu (koma), contoh: 671,650,74",
    )
    args = parser.parse_args()
    skip_ids = parse_skip_ids(args.skip_ids or None)
    only_ids: set[int] | None = None
    if args.id is not None:
        only_ids = {args.id}
    elif args.ids.strip():
        only_ids = parse_skip_ids(args.ids)

    if args.json_only and args.scalar_only:
        parser.error("Pilih salah satu: --json-only atau --scalar-only, bukan keduanya.")
    if args.parameter_only and args.json_only:
        parser.error("--parameter-only tidak boleh digabung --json-only.")
    if args.extension_only and args.json_only:
        parser.error("--extension-only sudah cakup JSON kontrak; jangan gabung --json-only.")
    if args.extension_only and (args.scalar_only or args.parameter_only):
        parser.error("--extension-only tidak boleh digabung --scalar-only/--parameter-only.")

    mode = "APPLY" if args.apply else "DRY-RUN"
    if only_ids:
        scope = f"parameter id={sorted(only_ids)}"
    elif args.extension_only:
        scope = "extension-only"
    elif args.parameter_only:
        scope = "parameter-only"
    elif args.scalar_only:
        scope = "scalar-only"
    elif args.json_only:
        scope = "json-only"
    else:
        scope = "scalar+json"
    print(f"=== FIX WHITESPACE [{mode}] scope={scope} ===\n", flush=True)

    if args.apply and not args.yes:
        print("Perintah akan UPDATE database produksi dari .env.")
        print("Ulangi dengan: --apply --yes")
        return 2

    env = load_env(args.env)
    conn = db_connect(env, autocommit=False)
    summary: dict[str, int] = {}

    try:
        if args.show_locks:
            with conn.cursor() as cur:
                cur.execute("SHOW FULL PROCESSLIST")
                rows = cur.fetchall()
            print("=== Yang perlu kamu cek ===\n")
            print("1) Tutup DBeaver / stop worker PHP dulu.\n")
            print("2) Baris di bawah: kalau ada time besar + parameter = itu penghalang.\n")
            print("   Di DBeaver: KILL <Id>  (hati-hati, bukan production peak).\n")
            for r in rows:
                t = int(r.get("Time") or 0)
                st = (r.get("State") or "") or ""
                info = (r.get("Info") or "") or ""
                interesting = (
                    t >= 1
                    or "lock" in st.lower()
                    or "parameter" in info.lower()
                    or r.get("User") not in (env.get("DB_USERNAME"), "event_scheduler")
                )
                if not info and t == 0:
                    continue
                if interesting:
                    print(
                        f"  thread Id={r.get('Id')} user={r.get('User')} db={r.get('db')} "
                        f"time={t}s state={st[:50]!r}\n    {info[:300]}\n"
                    )
            return 0

        param_single = only_ids is not None
        run_master_parameter = not args.json_only and not args.extension_only
        scalar_tables = (
            EXTENSION_SCALAR
            if args.extension_only
            else ([] if args.parameter_only or param_single else SCALAR_TARGETS)
        )

        if not args.json_only:
            if args.apply:
                with conn.cursor() as cur:
                    cur.execute("SET SESSION innodb_lock_wait_timeout = 30")
                    cur.execute(
                        "SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED"
                    )
            if run_master_parameter:
                par = update_parameter_table(
                    conn, args.apply, env, skip_ids, only_ids=only_ids
                )
                summary.update(par)
                print(f"parameter: {par}", flush=True)

                if param_single:
                    if not args.apply:
                        print(
                            "\nDry-run. Jalankan dengan --apply --yes",
                            flush=True,
                        )
                    elif par.get("parameter (baris)", 0) == 0:
                        print(
                            "\nTidak ada perubahan (id tidak ada, tidak aktif, "
                            "atau sudah bersih).",
                            flush=True,
                        )

            for table, col in scalar_tables:
                print(f"  -> {table}.{col}...", flush=True)
                n = update_scalar_column(conn, table, col, args.apply)
                summary[f"{table}.{col}"] = n
                print(f"     {n} baris", flush=True)
            if args.apply and (run_master_parameter or scalar_tables):
                print("Scalar selesai (COMMIT per baris).", flush=True)

        def apply_json_scan(label: str, scanner):
            print(f"  -> {label}...", flush=True)
            count = 0
            with conn.cursor() as cur:
                for row_id, payload in scanner(conn):
                    count += 1
                    if args.apply:
                        cur.execute(
                            f"UPDATE `{scanner.table}` SET `{scanner.column}` = %s "
                            f"WHERE id = %s",
                            (payload, row_id),
                        )
                    if count % 25 == 0:
                        print(f"     ...{count} baris", flush=True)
            key = f"{scanner.table}.{scanner.column} (JSON rows)"
            summary[key] = count
            print(f"     {count} baris", flush=True)
            return count

        # Wrapper objects for table/column names
        scan_order_detail_fixes.table = "order_detail"  # type: ignore[attr-defined]
        scan_order_detail_fixes.column = "parameter"  # type: ignore[attr-defined]
        if args.extension_only:
            run_order_detail = False
            run_quotation_targets = EXTENSION_JSON_QUOTATION
        elif args.json_only:
            run_order_detail = True
            run_quotation_targets = JSON_QUOTATION_SAMPLING
        elif args.scalar_only or args.parameter_only or param_single:
            run_order_detail = False
            run_quotation_targets = []
        else:
            run_order_detail = True
            run_quotation_targets = JSON_QUOTATION_SAMPLING

        if run_order_detail:
            apply_json_scan("order_detail JSON", scan_order_detail_fixes)
        for scanner in quotation_json_scanners(run_quotation_targets):
            apply_json_scan(f"{scanner.table} JSON", scanner)

        if args.apply and (run_order_detail or run_quotation_targets):
            conn.commit()
            print("\nCOMMIT JSON berhasil.", flush=True)

        if not args.apply:
            conn.rollback()

        print(f"\n=== Ringkasan {'UPDATE' if args.apply else 'rencana UPDATE'} ===")
        for k, v in summary.items():
            print(f"  {k}: {v} baris")

        if args.apply:
            if args.extension_only:
                after = {}
                for table, col in EXTENSION_SCALAR:
                    after[f"{table}.{col}"] = count_needs_trim(conn, table, col)
                for table, column, active in EXTENSION_JSON_QUOTATION:
                    scanner = make_scan_quotation_sampling_fixes(
                        table, column, active
                    )
                    after[f"{table}.{column} (JSON)"] = count_json_fix_rows(
                        conn, scanner
                    )
            else:
                include_json = (
                    not args.scalar_only
                    and not args.parameter_only
                    and only_ids is None
                ) or args.json_only
                include_scalar = not args.json_only
                after = count_remaining_whitespace(
                    conn,
                    include_scalar=include_scalar,
                    include_json=include_json,
                )
            print("\nSesudah (sisa bermasalah, harus 0):")
            all_clear = True
            for k, v in after.items():
                flag = "OK" if v == 0 else "MASALAH"
                if v != 0:
                    all_clear = False
                print(f"  {k}: {v} [{flag}]")
            if all_clear:
                print("\nTidak ada sisa whitespace di tepi kolom (scope script).")
            else:
                print("\nMasih ada data bermasalah — perlu investigasi manual.")
        else:
            if args.scalar_only:
                print(
                    "\nJalankan: python fix_parameter_whitespace.py "
                    "--apply --yes --scalar-only"
                )
            elif args.json_only:
                print(
                    "\nJalankan: python fix_parameter_whitespace.py "
                    "--apply --yes --json-only"
                )
            else:
                print("\nJalankan: python fix_parameter_whitespace.py --apply --yes")

    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
