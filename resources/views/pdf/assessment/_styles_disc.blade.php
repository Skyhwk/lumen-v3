<style>
    @page {
        margin: 12mm 10mm 14mm 10mm;
    }

    body {
        font-family: "Times New Roman", Times, serif;
        font-size: 10pt;
        line-height: 1.45;
        color: #000;
    }

    .doc-title {
        font-size: 13pt;
        font-weight: bold;
        text-align: center;
        margin: 0 0 3px;
    }

    .doc-subtitle {
        font-size: 9pt;
        text-align: center;
        color: #444;
        margin: 0 0 10px;
    }

    .doc-divider {
        border: 0;
        border-top: 1.2px solid #000;
        margin: 0 0 12px;
    }

    .info-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 14px;
        font-size: 10pt;
    }

    .info-table td {
        padding: 3px 0;
        vertical-align: top;
    }

    .info-label {
        width: 110px;
        font-weight: bold;
        padding-right: 10px;
    }

    /* mPDF ignores borders on block elements nested in table cells,
       so every framed block below is built as a nested table. */
    .heading-table {
        width: 100%;
        border-collapse: collapse;
        margin: 0 0 8px;
    }

    .heading-table-spaced {
        margin-top: 16px;
    }

    .section-heading {
        border: 0.8px solid #000;
        background-color: #e6e6e6;
        padding: 4px 6px;
        font-size: 9.5pt;
        font-weight: bold;
        text-align: center;
        text-transform: uppercase;
    }

    .disc-layout {
        width: 100%;
        border-collapse: collapse;
    }

    .disc-col-left {
        width: 38%;
        vertical-align: top;
        padding-right: 7px;
    }

    .disc-col-right {
        width: 62%;
        vertical-align: top;
        padding-left: 7px;
    }

    /* ---------- Gambaran Karakter ---------- */

    .profile-card {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
        page-break-inside: avoid;
    }

    .profile-card td {
        border: 0.6px solid #8c8c8c;
    }

    .profile-label {
        padding: 4px 6px;
        font-size: 8pt;
        font-weight: bold;
        color: #333;
        vertical-align: middle;
    }

    .profile-tag {
        width: 40%;
        padding: 4px 4px;
        text-align: center;
        font-size: 7.5pt;
        background-color: #ededed;
        vertical-align: middle;
    }

    .profile-body {
        padding: 6px 8px 7px;
    }

    .profile-pattern {
        font-size: 8.5pt;
        font-weight: bold;
        text-transform: uppercase;
        margin: 0 0 4px;
    }

    .behaviour-table {
        width: 100%;
        border-collapse: collapse;
    }

    .behaviour-table td {
        border: 0;
        padding: 1.5px 0;
        font-size: 9.5pt;
        vertical-align: top;
    }

    .behaviour-bullet {
        width: 11px;
    }

    /* ---------- Deskripsi Kepribadian ---------- */

    .quote-card {
        width: 100%;
        border-collapse: collapse;
    }

    .quote-card td {
        border: 0.8px solid #8c8c8c;
        padding: 6px 10px 9px;
        text-align: justify;
    }

    .quote-mark {
        font-size: 18pt;
        font-weight: bold;
        line-height: 1;
        color: #8c8c8c;
        margin: 0;
    }

    .content-text {
        font-size: 9.5pt;
        text-align: justify;
        line-height: 1.6;
        margin: 0;
    }

    /* ---------- Job Match ---------- */

    .job-table {
        width: 100%;
        border-collapse: collapse;
    }

    .job-table td {
        border: 0.5px solid #9a9a9a;
        padding: 4px 8px;
        font-size: 9.5pt;
    }

    .job-num {
        width: 24px;
        text-align: center;
        font-weight: bold;
        background-color: #ededed;
    }

    .job-name {
        font-weight: bold;
    }

    .empty-note {
        font-size: 9.5pt;
        font-style: italic;
        color: #555;
        margin: 0;
    }

    /* ---------- Grafik Skor DISC ---------- */

    .text-center {
        text-align: center;
    }

    .chart-page-note {
        font-size: 8pt;
        color: #555;
        margin: 0 0 9px;
        text-align: left;
    }

    .decision-table {
        width: 100%;
        border-collapse: collapse;
        margin: 0 0 10px;
        page-break-inside: avoid;
    }

    .decision-table th {
        background-color: #ededed;
        border: 0.6px solid #8c8c8c;
        padding: 4px 6px;
        font-size: 8pt;
        text-align: left;
        font-weight: bold;
    }

    .decision-table td {
        border: 0.6px solid #8c8c8c;
        padding: 5px 6px;
        font-size: 8.5pt;
        vertical-align: top;
    }

    .decision-table tr.decision-primary td {
        background-color: #f2f2f2;
    }

    .decision-sub {
        font-size: 7.5pt;
        color: #555;
    }

    .decision-pattern {
        font-weight: bold;
        text-transform: uppercase;
        font-size: 8pt;
        vertical-align: middle;
    }

    .chart-block {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
        page-break-inside: avoid;
    }

    .chart-head {
        padding: 4px 8px;
        background-color: #ededed;
        border: 0.8px solid #8c8c8c;
    }

    .chart-block-primary .chart-head {
        background-color: #dcdcdc;
    }

    .chart-head-row td {
        border: 0;
        padding: 0;
        vertical-align: middle;
    }

    .chart-head-title {
        font-size: 8.5pt;
        font-weight: bold;
        text-align: left;
    }

    .chart-head-tag {
        width: 34%;
        font-size: 8pt;
        font-weight: bold;
        text-transform: uppercase;
        text-align: right;
    }

    .chart-primary-badge {
        font-size: 7pt;
        font-weight: bold;
        text-transform: uppercase;
        border: 0.5px solid #333;
        padding: 1px 5px;
        margin-left: 6px;
        background-color: #fff;
    }

    .chart-body {
        padding: 5px 8px 6px;
        vertical-align: top;
        border: 0.8px solid #8c8c8c;
        border-top: 0;
    }

    .hbar-table,
    .hbar-table td,
    .hbar-bar,
    .hbar-bar td,
    .hbar-ruler,
    .hbar-ruler td {
        border: 0;
        padding: 0;
    }

    .hbar-table {
        width: 100%;
        border-collapse: collapse;
    }

    .hbar-table > tr > td {
        padding: 3px 4px;
        vertical-align: middle;
    }

    .hbar-spacer {
        font-size: 1px;
        line-height: 1px;
    }

    .hbar-scale {
        padding: 0 4px 3px;
    }

    .hbar-ruler-left,
    .hbar-ruler-mid,
    .hbar-ruler-right {
        font-size: 7pt;
        color: #555;
        padding: 0 0 2px;
    }

    .hbar-ruler-left {
        width: 22%;
        text-align: left;
    }

    .hbar-ruler-mid {
        width: 56%;
        text-align: center;
        font-weight: bold;
        color: #333;
    }

    .hbar-ruler-right {
        width: 22%;
        text-align: right;
    }

    .hbar-label {
        width: 20%;
        font-size: 8.5pt;
        white-space: nowrap;
    }

    .hbar-track {
        width: 50%;
    }

    .hbar-pct {
        width: 10%;
        font-size: 10pt;
        font-weight: bold;
        text-align: right;
        white-space: nowrap;
    }

    .hbar-meta {
        width: 20%;
        font-size: 7.5pt;
        color: #444;
        white-space: nowrap;
        padding-left: 6px;
    }

    .hbar-strong {
        background-color: #f0f0f0;
    }

    .hbar-bar {
        width: 100%;
        border-collapse: collapse;
        border: 0.6px solid #b5b5b5;
    }

    .hbar-half {
        padding: 0;
        font-size: 1px;
        line-height: 1px;
    }

    .hbar-bar td.hbar-mid {
        border-right: 0.9px solid #111;
    }

    .disc-profile-wrap {
        width: 100%;
        margin: 4px 0 8px;
        text-align: center;
    }

    .disc-profile-wrap img {
        width: 100%;
    }

    .chart-legend {
        width: 100%;
        border-collapse: collapse;
        margin-top: 4px;
    }

    .chart-legend td {
        font-size: 7.5pt;
        padding: 3px 6px;
        border: 0.5px solid #c4c4c4;
        text-align: center;
    }
</style>
