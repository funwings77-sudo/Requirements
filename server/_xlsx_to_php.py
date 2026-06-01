# -*- coding: utf-8 -*-
"""xlsx의 진척관리대장을 schedule.php가 include할 PHP 배열 파일로 변환."""
import zipfile, re
import xml.etree.ElementTree as ET
from datetime import datetime, timedelta

PATH = r"C:\Users\user\Downloads\이뻐 Project 2차 구축 진척관리대장.xlsx"
OUT  = r"C:\Users\user\Desktop\Requirements Definition\project_manager\schedule_data.php"
NS = {'s':'http://schemas.openxmlformats.org/spreadsheetml/2006/main'}

def excel_serial_to_date(n):
    """Excel 1900 시리얼 -> YYYY-MM-DD (1900 leap bug 보정 위해 base = 1899-12-30)."""
    try:
        return (datetime(1899,12,30) + timedelta(days=int(float(n)))).strftime('%Y-%m-%d')
    except: return ''

with zipfile.ZipFile(PATH) as z:
    sst = []
    if 'xl/sharedStrings.xml' in z.namelist():
        sst_xml = ET.fromstring(z.read('xl/sharedStrings.xml'))
        for si in sst_xml.findall('s:si', NS):
            sst.append(''.join(t.text or '' for t in si.iter('{http://schemas.openxmlformats.org/spreadsheetml/2006/main}t')))
    sh = ET.fromstring(z.read('xl/worksheets/sheet1.xml'))

def col_to_num(col_str):
    n = 0
    for ch in col_str: n = n*26 + (ord(ch)-64)
    return n

def parse_cell_ref(ref):
    m = re.match(r'([A-Z]+)(\d+)', ref)
    return (int(m.group(2)), col_to_num(m.group(1)))

# rows: dict[int rownum] -> dict[int colnum] -> str value
rows = {}
sd = sh.find('s:sheetData', NS)
for row in sd.findall('s:c', NS): pass  # not used; iterate via row elements
for r in sd.findall('s:row', NS):
    rn = int(r.get('r'))
    rd = {}
    for c in r.findall('s:c', NS):
        rr, cc = parse_cell_ref(c.get('r'))
        t = c.get('t')
        v = c.find('s:v', NS)
        val = ''
        if t == 's' and v is not None:
            try: val = sst[int(v.text)]
            except: val = v.text or ''
        elif t == 'inlineStr':
            isn = c.find('s:is', NS)
            if isn is not None:
                val = ''.join((tn.text or '') for tn in isn.iter('{http://schemas.openxmlformats.org/spreadsheetml/2006/main}t'))
        elif v is not None:
            val = v.text or ''
        rd[cc] = val
    rows[rn] = rd

def cell(rn, cn):
    return rows.get(rn,{}).get(cn,'').strip()

def php_str(s):
    s = (s or '').replace("\\", "\\\\").replace("'", "\\'")
    return f"'{s}'"

# === 추출 ===
meta = {
    'title': cell(2,1),                                # 이뻐 Project 2차 구축 진척관리대장
    'version_line': cell(3,1),                         # Version: 1.1 Last Updated: ...
    'total': cell(3,11),                               # 총 개체수
    'sheets_url': cell(12,1),                          # 운영개발 SHEETS : https://...
}

staff = [
    ('프로젝트관리(PM)', cell(5,4)),
    ('기획(PL)',         cell(6,4)),
    ('디자인(DE)',       cell(7,4)),
    ('퍼블리싱(PU)',     cell(8,4)),
    ('프론트엔드(APP)',  cell(9,4)),
    ('백엔드(BE)',       cell(10,4)),
]

# 통계: 구분 헤더(r5: 기획/디자인/퍼블리싱/프로그램 = K..N col11~14)
stat_rows = [
    ('진행예정',       cell(6,11),  cell(6,12),  cell(6,13),  cell(6,14)),
    ('진행중',         cell(7,11),  cell(7,12),  cell(7,13),  cell(7,14)),
    ('완료',           cell(8,11),  cell(8,12),  cell(8,13),  cell(8,14)),
    ('검수완료(최종)', cell(9,11),  cell(9,12),  cell(9,13),  cell(9,14)),
    ('남은 개체수',    cell(10,11), cell(10,12), cell(10,13), cell(10,14)),
    ('작업대상아님',   cell(11,11), cell(11,12), cell(11,13), cell(11,14)),
    ('보류',           cell(12,11), cell(12,12), cell(12,13), cell(12,14)),
    ('완료율',         cell(13,11), cell(13,12), cell(13,13), cell(13,14)),
]

# 최종 완료일 (r15: J,L,N = col10,12,14 ; 머지로 J15:K15 ...)
final_dates = {
    '전체':        excel_serial_to_date(cell(15,10)),
    '디자인':      excel_serial_to_date(cell(15,12)),
    '퍼블리싱':    excel_serial_to_date(cell(15,14)),
    '개발':        excel_serial_to_date(cell(15,16)),
}

# === 메인 테이블 ===
# header r16, r17 — 일부 컬럼은 두 줄 merged
# 컬럼 정의(번호->key/label/type)
COLS = [
    (1,  'no',           '번호',          'int'),
    (2,  'phase',        '차수',          'str'),
    (3,  'priority',     '우선순위',      'str'),
    (4,  'platform',     'APP/BO',        'str'),
    (5,  'item',         '항목',          'str'),
    (6,  'requirement',  '주요 요건정의', 'str'),
    (7,  'history',      '이슈/History', 'str'),
    (8,  'progress_prev','진행률(지난주)','pct'),
    (9,  'progress_curr','진행률(이번주)','pct'),
    (10, 'start_date',   '최초 시작일',   'date'),
    (11, 'end_date',     '최종 종료일',   'date'),
    (12, 'final_status', '최종 상태',     'str'),
    (13, 'workdays',     '작업일수',      'str'),
    (14, 'attach_plan',  '기획서첨부',    'str'),
    (15, 'attach_des',   '디자인첨부',    'str'),
    (16, 'attach_pub',   '퍼블리싱첨부',  'str'),
    (17, 'plan_status',  '기획상태',      'str'),
    (18, 'plan_owner',   '기획담당',      'str'),
    (19, 'plan_end',     '기획종료일',    'date'),
    (20, 'des_status',   '디자인상태',    'str'),
    (21, 'des_owner',    '디자인담당',    'str'),
    (22, 'des_end',      '디자인종료일',  'date'),
    (23, 'pub_status',   '퍼블리싱상태',  'str'),
    (24, 'pub_owner',    '퍼블리싱담당',  'str'),
    (25, 'pub_end',      '퍼블리싱종료일','date'),
    (26, 'dev_status',   '개발상태',      'str'),
    (27, 'dev_owner',    '개발담당',      'str'),
    (28, 'dev_end',      '개발종료일',    'date'),
    (29, 'dev_deploy',   '개발서버반영일','date'),
    (30, 'qa',           '검수여부',      'str'),
    (31, 'prod_deploy',  '운영서버반영일','date'),
]

items = []
for rn in range(18, 60):
    no_cell = cell(rn,1)
    if not no_cell: break
    rec = {}
    for cn, key, label, ctype in COLS:
        v = cell(rn, cn)
        if ctype == 'date':
            v = excel_serial_to_date(v) if v else ''
        elif ctype == 'pct':
            try:
                fv = float(v) if v else None
                v = ('%g'%(fv*100))+'%' if fv is not None else ''
            except: pass
        rec[key] = v
    items.append(rec)

# === PHP 출력 ===
def php_arr(d):
    parts = []
    for k,v in d.items():
        parts.append(f"  {php_str(k)} => {php_str(v)},")
    return '[\n' + '\n'.join(parts) + '\n]'

with open(OUT, 'w', encoding='utf-8') as f:
    f.write("<?php\n")
    f.write("/* schedule_data.php — 이뻐 Project 2차 구축 진척관리대장 (xlsx에서 정적 변환)\n")
    f.write("   자동 생성됨 — 수정 필요 시 server/_xlsx_to_php.py 재실행 또는 직접 수정. */\n\n")
    f.write("return [\n")
    # meta
    f.write("  'meta' => " + php_arr(meta) + ",\n")
    # staff
    f.write("  'staff' => [\n")
    for role, name in staff:
        f.write(f"    [{php_str(role)}, {php_str(name)}],\n")
    f.write("  ],\n")
    # stats
    f.write("  'stat_cols' => ['기획','디자인','퍼블리싱','프로그램'],\n")
    f.write("  'stats' => [\n")
    for row in stat_rows:
        cells_php = ', '.join(php_str(x) for x in row)
        f.write(f"    [{cells_php}],\n")
    f.write("  ],\n")
    f.write("  'final_dates' => " + php_arr(final_dates) + ",\n")
    # cols
    f.write("  'cols' => [\n")
    for cn, key, label, ctype in COLS:
        f.write(f"    ['key'=>{php_str(key)}, 'label'=>{php_str(label)}, 'type'=>{php_str(ctype)}],\n")
    f.write("  ],\n")
    # items
    f.write("  'items' => [\n")
    for r in items:
        f.write("    " + php_arr(r) + ",\n")
    f.write("  ],\n")
    f.write("];\n")

print(f"WROTE: {OUT}")
print(f"items: {len(items)}")
