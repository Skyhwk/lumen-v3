#!/usr/bin/env python3
"""
AUDIT ONLY (dry-run) — tidak melakukan UPDATE/TRIM.

Scope disetujui:
  - parameter (nama_lab, nama_regulasi, nama_lhp)
  - master_harga_parameter (nama_parameter)
  - order_detail (parameter JSON: "id;nama")
  - colorimetri, titrimetri, gravimetri, subkontrak (parameter)
  - request_quotation + request_quotation_kontrak_H/D (data_pendukung_sampling JSON)
  - master_bakumutu (parameter)

Tabel sub_kontrak → subkontrak (nama di DB).
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
    ANALISA_JOIN_TARGETS,
    JSON_QUOTATION_SAMPLING,
    SCALAR_TARGETS,
)
from dataclasses import dataclass, field
from typing import Any, Iterable

try:
    import pymysql
except ImportError:
    print("pip install pymysql", file=sys.stderr)
    sys.exit(1)

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_ENV = ROOT / ".env"

def whitespace_edge_sql(col: str) -> str:
    """
    Deteksi whitespace di awal/akhir (spasi, tab, dll).
    Jangan pakai `col <> TRIM(col)` saja — di VARCHAR MySQL bisa dianggap sama
    walau masih ada spasi di storage (kollation/pad).
    """
    return (
        f"({col} IS NOT NULL AND {col} <> '' "
        f"AND ({col} REGEXP '^[[:space:]]' OR {col} REGEXP '[[:space:]]$'))"
    )


TRAILING_SQL = whitespace_edge_sql("{col}")
WHITESPACE_EDGE_SQL = TRAILING_SQL

MODE_BANNER = "=== AUDIT DRY-RUN (read-only, tanpa UPDATE) ==="

PARAMETER_TEXT_COLS = ("nama_lab", "nama_regulasi", "nama_lhp")


def load_env(path: Path) -> dict[str, str]:
    if not path.is_file():
        raise FileNotFoundError(f".env tidak ditemukan: {path}")
    env: dict[str, str] = {}
    for raw in path.read_text(encoding="utf-8", errors="replace").splitlines():
        line = raw.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, _, value = line.partition("=")
        key, value = key.strip(), value.strip()
        if (value.startswith('"') and value.endswith('"')) or (
            value.startswith("'") and value.endswith("'")
        ):
            value = value[1:-1]
        env[key] = value
    return env


def db_connect(env: dict[str, str]):
    return pymysql.connect(
        host=env.get("DB_HOST", "127.0.0.1"),
        port=int(env.get("DB_PORT", "3306")),
        user=env.get("DB_USERNAME", "root"),
        password=env.get("DB_PASSWORD", ""),
        database=env.get("DB_DATABASE", ""),
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
    )


def active_where(alias: str | None = None) -> str:
    t = alias or ""
    p = f"{t}." if t else ""
    return f"({p}is_active = 1 OR {p}is_active = TRUE)"


def normalize_ws(value: str) -> str:
    return value.strip(" \t\n\r\v\f")


def edge_whitespace_chars(value: str) -> str:
    if not value:
        return ""
    return value[: len(value) - len(normalize_ws(value))] + (
        value[len(value) - len(value.lstrip(" \t\n\r\v\f")) :]
        if len(value) != len(normalize_ws(value))
        else ""
    )


def needs_edge_trim(value: str) -> bool:
    return bool(value) and normalize_ws(value) != value


def trailing_chars(value: str) -> int:
    """Perkiraan jumlah karakter whitespace di tepi (awal+akhir)."""
    if not value:
        return 0
    return len(value) - len(normalize_ws(value))


def has_trailing(value: str) -> bool:
    return needs_edge_trim(value)


def parse_id_nama(token: str) -> tuple[str | None, str]:
    if ";" in token:
        left, right = token.split(";", 1)
        return left.strip() or None, right
    return None, token


@dataclass
class ScalarHit:
    table: str
    column: str
    id: Any
    nama: str
    extra: dict = field(default_factory=dict)


@dataclass
class JsonHit:
    table: str
    column: str
    row_id: Any
    token: str
    nama_part: str
    path: str


def audit_scalar_table(
    conn,
    table: str,
    column: str,
    *,
    id_col: str = "id",
    extra_cols: Iterable[str] = (),
    table_alias: str | None = None,
) -> tuple[int, list[ScalarHit]]:
    alias = table_alias or table
    trail = TRAILING_SQL.format(col=f"{alias}.{column}")
    where = f"WHERE {active_where(alias)} AND {trail}"
    extras = ", ".join(f"{alias}.{c} AS {c}" for c in extra_cols)
    extra_sql = f", {extras}" if extras else ""

    with conn.cursor() as cur:
        cur.execute(f"SELECT COUNT(*) AS cnt FROM `{table}` AS {alias} {where}")
        total = int(cur.fetchone()["cnt"])
        cur.execute(
            f"SELECT {alias}.{id_col} AS id, {alias}.{column} AS nama {extra_sql} "
            f"FROM `{table}` AS {alias} {where} ORDER BY {alias}.{id_col}"
        )
        rows = cur.fetchall()

    hits: list[ScalarHit] = []
    for row in rows:
        nama = row.get("nama") or ""
        extra = {k: row[k] for k in extra_cols if k in row}
        hits.append(ScalarHit(table, column, row["id"], nama, extra))
    return total, hits


def load_master_bad_parameters(conn) -> tuple[int, list[dict]]:
    trail = whitespace_edge_sql("nama_lab")
    sql = (
        f"SELECT id, id_kategori, nama_lab, nama_regulasi, nama_lhp FROM parameter "
        f"WHERE {active_where()} AND {trail} ORDER BY id"
    )
    with conn.cursor() as cur:
        cur.execute(sql)
        rows = cur.fetchall()
    return len(rows), rows


def count_parameter_trailing_per_column(conn) -> dict[str, int]:
    counts: dict[str, int] = {}
    with conn.cursor() as cur:
        for col in PARAMETER_TEXT_COLS:
            trail = whitespace_edge_sql(col)
            cur.execute(
                f"SELECT COUNT(*) AS cnt FROM parameter WHERE {active_where()} AND {trail}"
            )
            counts[col] = int(cur.fetchone()["cnt"])
    return counts


def summarize_lab_regulasi_lhp_alignment(master_rows: list[dict]) -> tuple[int, int, int, int]:
    """Di antara baris dengan nama_lab trailing: berapa regulasi/lhp juga trailing."""
    lab_trail = len(master_rows)
    reg_trail = sum(
        1 for r in master_rows if has_trailing((r.get("nama_regulasi") or ""))
    )
    lhp_trail = sum(
        1 for r in master_rows if has_trailing((r.get("nama_lhp") or ""))
    )
    all_three = sum(
        1
        for r in master_rows
        if has_trailing(r.get("nama_lab") or "")
        and has_trailing(r.get("nama_regulasi") or "")
        and has_trailing(r.get("nama_lhp") or "")
    )
    return lab_trail, reg_trail, lhp_trail, all_three


def iter_json_rows(
    conn, table: str, column: str, batch: int = 2000, *, filter_is_active: bool = True
):
    where = (
        f"{active_where()} AND `{column}` IS NOT NULL AND `{column}` <> ''"
        if filter_is_active
        else f"`{column}` IS NOT NULL AND `{column}` <> ''"
    )
    sql = f"SELECT id, `{column}` AS payload FROM `{table}` WHERE {where}"
    with conn.cursor() as cur:
        cur.execute(sql)
        while True:
            chunk = cur.fetchmany(batch)
            if not chunk:
                break
            for row in chunk:
                yield row["id"], row["payload"]


def scan_order_detail_parameter(conn) -> tuple[int, int, list[JsonHit]]:
    rows_hit = 0
    tokens = 0
    hits: list[JsonHit] = []
    for row_id, raw in iter_json_rows(conn, "order_detail", "parameter"):
        try:
            data = json.loads(raw)
        except json.JSONDecodeError:
            continue
        if not isinstance(data, list):
            continue
        row_bad = False
        for i, token in enumerate(data):
            if not isinstance(token, str):
                continue
            _, nama = parse_id_nama(token)
            if has_trailing(nama) or has_trailing(token):
                row_bad = True
                tokens += 1
                hits.append(
                    JsonHit(
                        "order_detail",
                        "parameter",
                        row_id,
                        token,
                        nama,
                        f"parameter[{i}]",
                    )
                )
        if row_bad:
            rows_hit += 1
    return rows_hit, tokens, hits


def scan_quotation_sampling_json(
    conn, table: str, column: str, *, filter_is_active: bool = True
) -> tuple[int, int, list[JsonHit]]:
    rows_hit = 0
    tokens = 0
    hits: list[JsonHit] = []
    for row_id, raw in iter_json_rows(
        conn, table, column, filter_is_active=filter_is_active
    ):
        try:
            data = json.loads(raw)
        except json.JSONDecodeError:
            continue
        if not isinstance(data, list):
            continue
        row_bad = False
        for si, sample in enumerate(data):
            if not isinstance(sample, dict):
                continue
            plist = sample.get("parameter")
            if not isinstance(plist, list):
                continue
            for pi, token in enumerate(plist):
                if not isinstance(token, str):
                    continue
                _, nama = parse_id_nama(token)
                if has_trailing(nama) or has_trailing(token):
                    row_bad = True
                    tokens += 1
                    hits.append(
                        JsonHit(
                            table,
                            column,
                            row_id,
                            token,
                            nama,
                            f"[{si}].parameter[{pi}]",
                        )
                    )
        if row_bad:
            rows_hit += 1
    return rows_hit, tokens, hits


def count_rows_linked_to_bad_master(
    conn, table: str, column: str, *, mismatch_only: bool = False
) -> int:
    """
    Baris yang mengacu parameter master bermasalah (nama_lab ada spasi belakang).
    Join pakai TRIM — relevan karena MySQL bisa match 'Bau' = 'Bau '.
    mismatch_only: hanya baris yang string tersimpan != nama_lab master (inkonsistensi).
    """
    trail_p = whitespace_edge_sql("p.nama_lab")
    eq = (
        " AND BINARY c.`{col}` <> BINARY p.nama_lab".format(col=column)
        if mismatch_only
        else ""
    )
    sql = f"""
        SELECT COUNT(*) AS cnt
        FROM `{table}` AS c
        INNER JOIN parameter AS p
          ON TRIM(c.`{column}`) = TRIM(p.nama_lab)
        WHERE {active_where('c')} AND {active_where('p')} AND {trail_p}{eq}
    """
    with conn.cursor() as cur:
        cur.execute(sql)
        return int(cur.fetchone()["cnt"])


def master_ids_with_trailing(master_rows: list[dict]) -> set[int]:
    return {int(r["id"]) for r in master_rows}


def print_master_section(
    master_count: int,
    master_rows: list[dict],
    col_counts: dict[str, int],
    alignment: tuple[int, int, int, int],
):
    lab_n, reg_n, lhp_n, all_three = alignment
    print("\n--- MASTER: tabel parameter (is_active, whitespace tepi: spasi/tab) ---")
    print("Jumlah per kolom:")
    for col in PARAMETER_TEXT_COLS:
        print(f"  {col:<16}: {col_counts[col]} baris")
    print(
        f"\nAcuan nama_lab trailing: {master_count} baris "
        f"(regulasi juga trailing: {reg_n}, lhp juga trailing: {lhp_n}, "
        f"ketiganya trailing: {all_three})"
    )
    if master_rows:
        print("\nDetail (acuan nama_lab bermasalah):")
        for r in master_rows:
            parts = []
            for col in PARAMETER_TEXT_COLS:
                val = r.get(col) or ""
                sp = trailing_chars(val)
                flag = "OK" if sp == 0 else f"spasi={sp}"
                parts.append(f"{col}={val!r} ({flag})")
            print(f"  id={r['id']} id_kategori={r['id_kategori']}")
            for p in parts:
                print(f"    {p}")


def print_scalar_hits(label: str, total: int, hits: list[ScalarHit], show_all: bool):
    print(f"\n--- {label} ---")
    print(f"Baris terdampak (trailing): {total}")
    if not show_all or not hits:
        return
    for h in hits:
        extra = " ".join(f"{k}={v}" for k, v in h.extra.items())
        sp = trailing_chars(h.nama)
        line = f"  id={h.id} spasi={sp} {h.nama!r}"
        if extra:
            line += f" {extra}"
        print(line)


def print_json_hits(
    label: str, rows_hit: int, tokens: int, hits: list[JsonHit], show_all: bool
):
    print(f"\n--- {label} ---")
    print(f"Baris dokumen/sampel terdampak: {rows_hit}")
    print(f"Entri parameter (token) terdampak: {tokens}")
    if not show_all or not hits:
        return
    for h in hits:
        print(
            f"  id={h.row_id} path={h.path} spasi_nama={trailing_chars(h.nama_part)} "
            f"token={h.token!r}"
        )


def main() -> int:
    if hasattr(sys.stdout, "reconfigure"):
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    if hasattr(sys.stderr, "reconfigure"):
        sys.stderr.reconfigure(encoding="utf-8", errors="replace")

    parser = argparse.ArgumentParser(
        description="Audit trailing space pada nama parameter (DRY-RUN only)."
    )
    parser.add_argument("--env", type=Path, default=DEFAULT_ENV)
    parser.add_argument(
        "--all",
        action="store_true",
        help="Tampilkan semua baris detail (bukan hanya ringkasan)",
    )
    args = parser.parse_args()

    print(MODE_BANNER)
    env = load_env(args.env)
    conn = db_connect(env)

    try:
        master_count, master_rows = load_master_bad_parameters(conn)
        col_counts = count_parameter_trailing_per_column(conn)
        alignment = summarize_lab_regulasi_lhp_alignment(master_rows)
        print_master_section(master_count, master_rows, col_counts, alignment)
        mhp_total, mhp_hits = audit_scalar_table(
            conn,
            "master_harga_parameter",
            "nama_parameter",
            extra_cols=("id_parameter",),
        )

        analisa_tables = list(ANALISA_JOIN_TARGETS)
        scalar_extra = [
            (t, c) for t, c in SCALAR_TARGETS if (t, c) not in analisa_tables
        ]
        analisa_results: dict[str, tuple[int, list[ScalarHit]]] = {}
        scalar_ex_results: dict[str, tuple[int, list[ScalarHit]]] = {}
        for tbl, col in analisa_tables:
            analisa_results[tbl] = audit_scalar_table(conn, tbl, col)
        for tbl, col in scalar_extra:
            scalar_ex_results[tbl] = audit_scalar_table(conn, tbl, col)

        od_rows, od_tokens, od_hits = scan_order_detail_parameter(conn)
        quotation_scans: dict[str, tuple[int, int, list[JsonHit]]] = {}
        for table, column, active in JSON_QUOTATION_SAMPLING:
            quotation_scans[table] = scan_quotation_sampling_json(
                conn, table, column, filter_is_active=active
            )
        rq_rows, rq_tokens, rq_hits = quotation_scans.get(
            "request_quotation", (0, 0, [])
        )

        print("\n=== RINGKASAN AUDIT (scope disetujui) ===")
        print(f"{'Tabel':<28} {'Baris':>8}  {'Token/alt':>10}  Catatan")
        print("-" * 70)
        print(
            f"{'parameter.nama_lab':<28} {col_counts['nama_lab']:>8}  {'-':>10}  master"
        )
        print(
            f"{'parameter.nama_regulasi':<28} {col_counts['nama_regulasi']:>8}  "
            f"{'-':>10}  master"
        )
        print(
            f"{'parameter.nama_lhp':<28} {col_counts['nama_lhp']:>8}  {'-':>10}  master"
        )
        print(
            f"{'master_harga_parameter':<28} {mhp_total:>8}  {'-':>10}  nama_parameter"
        )
        for tbl, col in analisa_tables:
            cnt, _ = analisa_results[tbl]
            print(f"{tbl:<28} {cnt:>8}  {'-':>10}  kolom {col}")
        for tbl, col in scalar_extra:
            cnt, _ = scalar_ex_results[tbl]
            print(f"{tbl:<28} {cnt:>8}  {'-':>10}  kolom {col}")
        print(
            f"{'order_detail':<28} {od_rows:>8}  {od_tokens:>10}  JSON parameter"
        )
        for table, column, _active in JSON_QUOTATION_SAMPLING:
            q_rows, q_tokens, _ = quotation_scans[table]
            print(
                f"{table:<28} {q_rows:>8}  {q_tokens:>10}  JSON {column}"
            )

        print("\n--- Terhubung master bermasalah (join TRIM nama_lab) ---")
        for tbl, col in analisa_tables:
            linked = count_rows_linked_to_bad_master(conn, tbl, col)
            mismatch = count_rows_linked_to_bad_master(
                conn, tbl, col, mismatch_only=True
            )
            print(
                f"  {tbl}.{col}: {linked} baris terhubung, "
                f"{mismatch} inkonsisten (kolom <> nama_lab master)"
            )

        bad_ids = master_ids_with_trailing(master_rows)

        def json_linked_to_master(hits: list[JsonHit]) -> int:
            n = 0
            for h in hits:
                pid, _ = parse_id_nama(h.token)
                if pid is not None and int(pid) in bad_ids:
                    n += 1
                    continue
                for m in master_rows:
                    if (m["nama_lab"] or "").rstrip(" \t") == (h.nama_part or "").rstrip(
                        " \t"
                    ):
                        n += 1
                        break
            return n

        print_scalar_hits(
            "master_harga_parameter.nama_parameter",
            mhp_total,
            mhp_hits,
            args.all,
        )
        for tbl, col in scalar_extra:
            cnt, hits = scalar_ex_results[tbl]
            print_scalar_hits(f"{tbl}.{col}", cnt, hits, args.all)
        for tbl, col in analisa_tables:
            cnt, hits = analisa_results[tbl]
            print_scalar_hits(f"{tbl}.{col}", cnt, hits, args.all)
        print_json_hits(
            "order_detail.parameter (JSON)",
            od_rows,
            od_tokens,
            od_hits,
            args.all,
        )
        od_linked = json_linked_to_master(od_hits) if od_hits else 0
        print(
            f"  Token JSON order_detail terhubung master (subset trailing scan): {od_linked}"
        )

        for table, column, _active in JSON_QUOTATION_SAMPLING:
            q_rows, q_tokens, q_hits = quotation_scans[table]
            print_json_hits(
                f"{table}.{column} (JSON)",
                q_rows,
                q_tokens,
                q_hits,
                args.all,
            )
            if table == "request_quotation" and q_hits:
                rq_linked = json_linked_to_master(q_hits)
                print(
                    f"  Token JSON {table} terhubung master "
                    f"(subset trailing scan): {rq_linked}"
                )

        print("\nLangkah berikutnya: script perbaikan terpisah dengan --dry-run default.")
    finally:
        conn.close()

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
