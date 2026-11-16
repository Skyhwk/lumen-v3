#!/usr/bin/env python3
"""
Cek baris aktif di tabel `parameter` (kolom nama_lab) dan `master_harga_parameter`
(kolom nama_parameter) yang masih punya spasi di akhir nama.

Membaca koneksi DB dari file .env Laravel (variabel DB_*).
"""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

try:
    import pymysql
except ImportError:
    print(
        "Paket pymysql belum terpasang. Jalankan: pip install pymysql",
        file=sys.stderr,
    )
    sys.exit(1)

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_ENV = ROOT / ".env"

TRAILING_SPACE_SQL = (
    "({col} IS NOT NULL AND {col} <> '' "
    "AND CHAR_LENGTH({col}) > CHAR_LENGTH(TRIM(TRAILING FROM {col})))"
)


def load_env(path: Path) -> dict[str, str]:
    if not path.is_file():
        raise FileNotFoundError(f"File .env tidak ditemukan: {path}")

    env: dict[str, str] = {}
    for raw in path.read_text(encoding="utf-8", errors="replace").splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        if "=" not in line:
            continue
        key, _, value = line.partition("=")
        key = key.strip()
        value = value.strip()
        if (value.startswith('"') and value.endswith('"')) or (
            value.startswith("'") and value.endswith("'")
        ):
            value = value[1:-1]
        env[key] = value
    return env


def db_config(env: dict[str, str]) -> dict:
    return {
        "host": env.get("DB_HOST", "127.0.0.1"),
        "port": int(env.get("DB_PORT", "3306")),
        "user": env.get("DB_USERNAME", "root"),
        "password": env.get("DB_PASSWORD", ""),
        "database": env.get("DB_DATABASE", ""),
        "charset": "utf8mb4",
        "cursorclass": pymysql.cursors.DictCursor,
    }


def active_clause(table: str) -> str:
    """Filter is_active: boolean/tinyint umum di Laravel."""
    return f"({table}.is_active = 1 OR {table}.is_active = TRUE)"


def count_and_sample(
    conn,
    table: str,
    name_col: str,
    *,
    id_col: str = "id",
    extra_select: str | None = None,
    limit: int | None = 20,
) -> tuple[int, list[dict]]:
    trail = TRAILING_SPACE_SQL.format(col=name_col)
    where = f"WHERE {active_clause(table)} AND {trail}"

    with conn.cursor() as cur:
        cur.execute(f"SELECT COUNT(*) AS cnt FROM {table} {where}")
        total = int(cur.fetchone()["cnt"])

        cols = f"{table}.{id_col} AS id, {table}.{name_col} AS nama"
        if extra_select:
            cols += f", {extra_select}"
        sql = (
            f"SELECT {cols} FROM {table} {where} ORDER BY {table}.{id_col}"
        )
        if limit == 0:
            rows = []
        elif limit is not None:
            sql += " LIMIT %s"
            cur.execute(sql, (limit,))
            rows = cur.fetchall()
        else:
            cur.execute(sql)
            rows = cur.fetchall()

    for row in rows:
        nama = row.get("nama") or ""
        row["trailing_spaces"] = len(nama) - len(nama.rstrip(" \t"))
        row["nama_repr"] = repr(nama)

    return total, rows


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Hitung parameter aktif dengan spasi di belakang nama."
    )
    parser.add_argument(
        "--env",
        type=Path,
        default=DEFAULT_ENV,
        help=f"Path ke .env (default: {DEFAULT_ENV})",
    )
    parser.add_argument(
        "--sample",
        type=int,
        default=15,
        help="Jumlah baris ditampilkan per tabel (0 = tidak tampilkan)",
    )
    parser.add_argument(
        "--all",
        action="store_true",
        help="Tampilkan semua baris terdampak (tanpa LIMIT)",
    )
    args = parser.parse_args()

    row_limit: int | None
    if args.all:
        row_limit = None
    elif args.sample == 0:
        row_limit = 0
    else:
        row_limit = args.sample

    env = load_env(args.env)
    cfg = db_config(env)
    if not cfg["database"]:
        print("DB_DATABASE kosong di .env", file=sys.stderr)
        return 1

    conn = pymysql.connect(**cfg)
    try:
        param_total, param_rows = count_and_sample(
            conn,
            "parameter",
            "nama_lab",
            extra_select="parameter.id_kategori",
            limit=row_limit,
        )
        mhp_total, mhp_rows = count_and_sample(
            conn,
            "master_harga_parameter",
            "nama_parameter",
            extra_select="master_harga_parameter.id_parameter",
            limit=row_limit,
        )

        with conn.cursor() as cur:
            trail_p = TRAILING_SPACE_SQL.format(col="p.nama_lab")
            trail_m = TRAILING_SPACE_SQL.format(col="m.nama_parameter")
            cur.execute(
                f"""
                SELECT COUNT(DISTINCT m.id_parameter) AS cnt
                FROM master_harga_parameter m
                INNER JOIN parameter p ON p.id = m.id_parameter
                WHERE {active_clause('m')} AND {active_clause('p')}
                  AND ({trail_p} OR {trail_m})
                """
            )
            distinct_params = int(cur.fetchone()["cnt"])

        print("=== Spasi di belakang nama (is_active = true/1) ===\n")
        print(f"Tabel parameter (nama_lab):")
        print(f"  Jumlah baris terdampak : {param_total}")
        print(f"Tabel master_harga_parameter (nama_parameter):")
        print(f"  Jumlah baris terdampak : {mhp_total}")
        print(f"Gabungan (id_parameter unik, salah satu/both bermasalah):")
        print(f"  Jumlah parameter       : {distinct_params}")

        section_title = "Semua" if args.all else "Daftar"

        if param_rows:
            print(f"\n--- {section_title}: parameter ({len(param_rows)} baris) ---")
            for r in param_rows:
                print(
                    f"  id={r['id']} id_kategori={r.get('id_kategori')} "
                    f"spasi_belakang={r['trailing_spaces']} {r['nama_repr']}"
                )
        if mhp_rows:
            print(
                f"\n--- {section_title}: master_harga_parameter "
                f"({len(mhp_rows)} baris) ---"
            )
            for r in mhp_rows:
                print(
                    f"  id={r['id']} id_parameter={r.get('id_parameter')} "
                    f"spasi_belakang={r['trailing_spaces']} {r['nama_repr']}"
                )

    finally:
        conn.close()

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
