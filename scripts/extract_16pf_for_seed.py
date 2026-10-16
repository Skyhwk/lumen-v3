# -*- coding: utf-8 -*-
"""Extract 16PF questions, item keys, and norm tables from user Excel files."""
import json
import os
import re
import sys

import pandas as pd

sys.stdout.reconfigure(encoding='utf-8')

XLSX = r'c:\Users\Programmer Now\Documents\Tes 16PF.xlsx'
XLS = r'c:\Users\Programmer Now\Documents\Aplikasi Skoring 16PF.xls'
OUT_DIR = os.path.join(os.path.dirname(__file__), '..', 'storage', 'app', 'psychometric')

FACTORS = ['MD', 'A', 'B', 'C', 'E', 'F', 'G', 'H', 'I', 'L', 'M', 'N', 'O', 'Q1', 'Q2', 'Q3', 'Q4']

def clean_option_text(val, leg):
    val = str(val).strip() if val is not None else ''
    val = re.sub(r'^\[([a-c])\]\s*', '', val, flags=re.I)
    val = re.sub(r'^[a-cA-C][\.\)]\s*', '', val)
    val = val.replace('[', '').replace(']', '').strip()
    return val or {'a': 'Ya', 'b': 'Di antaranya', 'c': 'Tidak'}.get(leg, leg)


NORM_GROUPS = [
    ('smp_sma_l', 46, 47),
    ('smp_sma_p', 63, 64),
    ('smp_sma_lp', 80, 81),
    ('s1_l', 97, 98),
    ('s1_p', 114, 115),
    ('s1_lp', 131, 132),
    ('umum_l', 148, 149),
    ('umum_p', 165, 166),
    ('umum_lp', 182, 183),
]


def infer_points(chosen, pt):
    chosen = (chosen or 'b').lower()
    pt = int(pt)
    if pt == 2:
        if chosen == 'a':
            return {'a': 2, 'b': 1, 'c': 0}
        if chosen == 'c':
            return {'a': 0, 'b': 1, 'c': 2}
        return {'a': 0, 'b': 0, 'c': 2}
    if pt == 0:
        if chosen == 'a':
            return {'a': 0, 'b': 1, 'c': 2}
        if chosen == 'c':
            return {'a': 2, 'b': 1, 'c': 0}
        return {'a': 2, 'b': 0, 'c': 0}
    if pt == 1:
        if chosen == 'b':
            return {'a': 2, 'b': 1, 'c': 0}
        if chosen == 'a':
            return {'a': 1, 'b': 0, 'c': 0}
        return {'a': 0, 'b': 0, 'c': 1}
    return {'a': 0, 'b': 0, 'c': 0}


def extract_questions():
    df = pd.read_excel(XLSX, sheet_name='Kuesioner 16PF', header=None)
    questions = []
    for _, row in df.iterrows():
        try:
            no = int(row[0])
        except (TypeError, ValueError):
            continue
        if no < 1 or no > 105:
            continue
        opt_a = clean_option_text(row[2] if pd.notna(row[2]) else None, 'a')
        opt_b = clean_option_text(row[3] if pd.notna(row[3]) else None, 'b')
        opt_c = clean_option_text(row[4] if pd.notna(row[4]) else None, 'c')
        questions.append({
            'no': no,
            'text': str(row[1]).strip(),
            'options': {'a': opt_a, 'b': opt_b, 'c': opt_c},
        })
    questions.sort(key=lambda x: x['no'])
    return questions


def extract_item_keys(df):
    keys = {}
    for r in range(12, 32):
        fac = df.iloc[r, 21]
        if pd.isna(fac) or str(fac).strip() not in FACTORS:
            continue
        fac = str(fac).strip()
        slots = []
        for c in [1, 3, 5, 7, 9, 11, 13]:
            no = df.iloc[r, c]
            if pd.isna(no):
                continue
            no = int(float(no))
            ans = df.iloc[r, c + 1]
            ans = str(ans).strip().lower() if pd.notna(ans) else 'b'
            slots.append((no, ans))
        pts = []
        for c in range(22, 29):
            v = df.iloc[r, c]
            if pd.notna(v):
                pts.append(int(float(v)))
            elif len(slots) > len(pts):
                pts.append(0)
        for i, (no, ans) in enumerate(slots):
            pt = pts[i] if i < len(pts) else 0
            keys[no] = {
                'factor': fac,
                'points': infer_points(ans, pt),
            }
    return keys


def extract_norms(df):
    """Norm tables: one block per education/gender group; MD uses shared cols 40–42."""
    md_raw_col = 40
    md_sten_p_col = 41
    md_sten_l_col = 42
    factor_header_row = 13
    data_row_start = 14
    data_row_end = 35

    norms = {}
    for group_key, rw_col, _sw_col in NORM_GROUPS:
        table = {f: {} for f in FACTORS}

        factor_cols = []
        for offset in range(16):
            fc = rw_col + 1 + offset
            if fc >= df.shape[1]:
                break
            fac = df.iloc[factor_header_row, fc]
            if pd.isna(fac):
                break
            fac = str(fac).strip()
            if fac in FACTORS and fac != 'MD':
                factor_cols.append((fc, fac))

        for r in range(data_row_start, data_row_end):
            rv = df.iloc[r, rw_col]
            if pd.isna(rv):
                continue
            try:
                raw = int(float(rv))
            except (TypeError, ValueError):
                continue

            for fc, fac in factor_cols:
                sten = df.iloc[r, fc]
                if pd.isna(sten):
                    continue
                try:
                    table[fac][raw] = int(float(sten))
                except (TypeError, ValueError):
                    pass

        md_sten_col = md_sten_p_col if group_key.endswith('_p') else md_sten_l_col
        for r in range(data_row_start, data_row_end + 5):
            rv = df.iloc[r, md_raw_col]
            if pd.isna(rv):
                continue
            try:
                raw = int(float(rv))
            except (TypeError, ValueError):
                continue
            sten = df.iloc[r, md_sten_col]
            if pd.isna(sten):
                continue
            try:
                table['MD'][raw] = int(float(sten))
            except (TypeError, ValueError):
                pass

        norms[group_key] = table
    return norms


def extract_psychogram_profile():
    """Long pole descriptions from sheet Grafik Psikogram (matches printed Excel profile)."""
    df = pd.read_excel(XLS, sheet_name='Grafik Psikogram', header=None)
    profile = {}
    for r in range(5, df.shape[0]):
        fac = df.iloc[r, 1]
        if pd.isna(fac):
            continue
        fac = str(fac).strip()
        if fac not in ['A', 'B', 'C', 'E', 'F', 'G', 'H', 'I', 'L', 'M', 'N', 'O', 'Q1', 'Q2', 'Q3', 'Q4']:
            continue
        low = df.iloc[r, 2]
        high = df.iloc[r, 13]
        profile[fac] = {
            'low': str(low).strip() if pd.notna(low) else '',
            'high': str(high).strip() if pd.notna(high) else '',
        }
    return profile


def psychogram_labels():
    return {
        'A': {'low': 'Berhati-hati, pendiam, kritis', 'high': 'Ramah, lembut, berpartisipasi'},
        'B': {'low': 'Kapasitas mental rendah', 'high': 'Kapasitas mental tinggi'},
        'C': {'low': 'Emosi kurang mantap', 'high': 'Emosi mantap, tenang'},
        'E': {'low': 'Patuh, rendah hati', 'high': 'Agresif, dominan, teguh'},
        'F': {'low': 'Serius, tenang', 'high': 'Antusias, suka bersenang-senang'},
        'G': {'low': 'Superego lemah', 'high': 'Teliti, bermoral, superego kuat'},
        'H': {'low': 'Pemalu, peka ancaman', 'high': 'Berani, tegas sosial'},
        'I': {'low': 'Keras, realistik', 'high': 'Lembut, peka, dependen'},
        'L': {'low': 'Menaruh kepercayaan', 'high': 'Curiga, sukar bertindak bodoh'},
        'M': {'low': 'Praktis, sederhana', 'high': 'Imajinatif, melamun'},
        'N': {'low': 'Jujur, blak-blakan', 'high': 'Lihai, halus budi bahasa'},
        'O': {'low': 'Tenang, yakin diri', 'high': 'Kuatir, gelisah, cemas'},
        'Q1': {'low': 'Konservatif, tradisional', 'high': 'Liberal, radikal'},
        'Q2': {'low': 'Tergantung kelompok', 'high': 'Mandiri, kecukupan diri'},
        'Q3': {'low': 'Lalai, lemah integrasi', 'high': 'Disiplin diri, kompulsif'},
        'Q4': {'low': 'Santai, ketegangan rendah', 'high': 'Tegang, mudah frustrasi'},
    }


def main():
    os.makedirs(OUT_DIR, exist_ok=True)
    questions = extract_questions()
    df = pd.read_excel(XLS, sheet_name='16pf', header=None)
    keys = extract_item_keys(df)
    norms = extract_norms(df)

    for q in questions:
        k = keys.get(q['no'], {'factor': 'A', 'points': {'a': 0, 'b': 1, 'c': 0}})
        q['factor'] = k['factor']
        q['points'] = k['points']

    missing = [q['no'] for q in questions if q['no'] not in keys]
    payload = {
        'questions': questions,
        'norms': norms,
        'psychogram_labels': psychogram_labels(),
        'psychogram_profile': extract_psychogram_profile(),
        'meta': {
            'question_count': len(questions),
            'keys_mapped': len(keys),
            'missing_key_items': missing,
        },
    }

    path = os.path.join(OUT_DIR, '16pf_bundle.json')
    with open(path, 'w', encoding='utf-8') as f:
        json.dump(payload, f, ensure_ascii=False, indent=2)

    print('Wrote', path)
    print('Questions:', len(questions), 'Keys:', len(keys), 'Missing:', missing)


if __name__ == '__main__':
    main()
