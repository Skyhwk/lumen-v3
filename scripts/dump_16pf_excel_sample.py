# -*- coding: utf-8 -*-
import json
import os
import sys

import pandas as pd

sys.stdout.reconfigure(encoding='utf-8')

XLS = r'c:\Users\Programmer Now\Documents\Aplikasi Skoring 16PF.xls'
OUT = os.path.join(os.path.dirname(__file__), '..', 'storage', 'app', 'psychometric')

FACTORS = ['MD', 'A', 'B', 'C', 'E', 'F', 'G', 'H', 'I', 'L', 'M', 'N', 'O', 'Q1', 'Q2', 'Q3', 'Q4']

df = pd.read_excel(XLS, sheet_name='16pf', header=None)

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
            answers[str(no)] = s

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

os.makedirs(OUT, exist_ok=True)
with open(os.path.join(OUT, '16pf_excel_sample_answers.json'), 'w', encoding='utf-8') as f:
    json.dump(answers, f, ensure_ascii=False, indent=2)
with open(os.path.join(OUT, '16pf_excel_sample_expected.json'), 'w', encoding='utf-8') as f:
    json.dump(expected, f, ensure_ascii=False, indent=2)

print('Wrote sample answers', len(answers), 'and expected', len(expected))
