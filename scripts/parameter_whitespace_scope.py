"""Daftar tabel/kolom dalam scope audit & fix whitespace nama parameter."""

from __future__ import annotations

# (table, column)
SCALAR_TARGETS = [
    ("master_harga_parameter", "nama_parameter"),
    ("master_bakumutu", "parameter"),
    ("colorimetri", "parameter"),
    ("titrimetri", "parameter"),
    ("gravimetri", "parameter"),
    ("subkontrak", "parameter"),
]

# Tabel analisa (string parameter) untuk join master — tanpa bakumutu/harga
ANALISA_JOIN_TARGETS = [
    ("colorimetri", "parameter"),
    ("titrimetri", "parameter"),
    ("gravimetri", "parameter"),
    ("subkontrak", "parameter"),
]

# (table, column, filter_is_active)
JSON_ORDER_DETAIL = ("order_detail", "parameter", True)

JSON_QUOTATION_SAMPLING = [
    ("request_quotation", "data_pendukung_sampling", True),
    ("request_quotation_kontrak_H", "data_pendukung_sampling", True),
    ("request_quotation_kontrak_D", "data_pendukung_sampling", False),
]

# Hanya perpanjangan scope (perizinan atasan)
EXTENSION_SCALAR = [
    ("master_bakumutu", "parameter"),
]

EXTENSION_JSON_QUOTATION = [
    ("request_quotation_kontrak_H", "data_pendukung_sampling", True),
    ("request_quotation_kontrak_D", "data_pendukung_sampling", False),
]
