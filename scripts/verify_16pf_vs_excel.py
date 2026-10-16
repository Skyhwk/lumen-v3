# -*- coding: utf-8 -*-
"""Compare 16PF raw + STEN from app bundle/keys vs Aplikasi Skoring 16PF.xls (Mrs. X sample)."""
import json
import os
import sys

import pandas as pd

sys.stdout.reconfigure(encoding='utf-8')

XLS = r'c:\Users\Programmer Now\Documents\Aplikasi Skoring 16PF.xls'
BUNDLE = os.path.join(os.path.dirname(__file__), '..', 'storage', 'app', 'psychometric', '16pf_bundle.json')
NORM_GROUP = 'smp_sma_l'  # Excel: Jenis L, pendidikan SMP-SMA → kode 1

FACTORS = ['MD', 'A', 'B', 'C', 'E', 'F', 'G', 'H', 'I', 'L', 'M', 'N', 'O', 'Q1', 'Q2', 'Q3', 'Q4']
PRIMARY = [f for f in FACTORS if f != 'MD']


def extract_answers(df):
    answers = {}
    for r in range(df.shape[0]):
        for c in range(min(45, df.shape[1] - 1)):
            v = df.iloc[r, c]
            try:
                no = int(float(v))
            except (TypeError, ValueError):
                continue
            if not (1 <= no <= 105):
                continue
            nxt = df.iloc[r, c + 1]
            if pd.isna(nxt):
                continue
            s = str(nxt).strip().lower()
            if s in ('a', 'b', 'c'):
                answers[no] = s
    return answers


def extract_excel_expected(df):
    expected = {}
    for r in range(12, 35):
        fac = df.iloc[r, 21]
        if pd.isna(fac):
            continue
        fac = str(fac).strip()
        if fac not in FACTORS:
            continue
        raw = df.iloc[r, 30]
        sten = df.iloc[r, 32]
        expected[fac] = {
            'raw': int(float(raw)) if pd.notna(raw) else None,
            'sten': int(float(sten)) if pd.notna(sten) else None,
        }
    return expected


def lookup_sten(norm_tables, factor, raw):
    table = norm_tables.get(factor) or {}
    if not table:
        return 5
    items = sorted((int(k), int(v)) for k, v in table.items())
    sten = 5
    for raw_key, value in items:
        if raw >= raw_key:
            sten = value
    return max(1, min(10, sten))


def score_bundle(questions, answers, norm_group):
    raw = {f: 0 for f in FACTORS}
    answered = 0
    q_by_no = {q['no']: q for q in questions}

    for no, choice in sorted(answers.items()):
        q = q_by_no.get(no)
        if not q:
            continue
        factor = q.get('factor', 'A')
        points = q.get('points') or {}
        if choice not in points:
            continue
        raw[factor] = raw.get(factor, 0) + int(points[choice])
        answered += 1

    norms = bundle['norms'].get(norm_group) or {}
    stens = {fac: lookup_sten(norms, fac, raw[fac]) for fac in FACTORS}
    return raw, stens, answered


with open(BUNDLE, encoding='utf-8') as f:
    bundle = json.load(f)

df = pd.read_excel(XLS, sheet_name='16pf', header=None)
answers = extract_answers(df)
expected = extract_excel_expected(df)

computed_raw, computed_sten, answered = score_bundle(bundle['questions'], answers, NORM_GROUP)

print('Norm group:', NORM_GROUP)
print('Jawaban terbaca dari Excel:', answered, '/ 105')
print()
print(f"{'Faktor':<6} {'Raw app':>8} {'Raw xls':>8} {'OK?':>5} {'STEN app':>9} {'STEN xls':>9} {'OK?':>5}")
print('-' * 60)

raw_mismatch = []
sten_mismatch = []
for fac in FACTORS:
    er = expected.get(fac, {}).get('raw')
    es = expected.get(fac, {}).get('sten')
    cr = computed_raw.get(fac)
    cs = computed_sten.get(fac)
    rok = '✓' if er == cr else '✗'
    sok = '✓' if es == cs else '✗'
    if er != cr:
        raw_mismatch.append(fac)
    if es != cs:
        sten_mismatch.append(fac)
    print(f"{fac:<6} {cr:>8} {str(er):>8} {rok:>5} {cs:>9} {str(es):>9} {sok:>5}")

print()
if not raw_mismatch and not sten_mismatch:
    print('HASIL: SEMUA raw dan STEN cocok dengan Excel.')
    sys.exit(0)

if raw_mismatch:
    print('Raw beda:', ', '.join(raw_mismatch))
if sten_mismatch:
    print('STEN beda:', ', '.join(sten_mismatch))
    for fac in sten_mismatch:
        cr = computed_raw[fac]
        print(f'  {fac}: app raw={cr}→STEN {computed_sten[fac]} | excel raw={expected[fac]["raw"]}→STEN {expected[fac]["sten"]}')
sys.exit(1)
