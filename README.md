# 메디힘 요구사항 수집 — MySQL DB + 백엔드 + 웹

엑셀 `메디힘 요구사항 수집.xlsx` 를 **MySQL 데이터베이스 + PHP 웹앱(요구사항 목록·대시보드, `?api=` JSON) + 선택적 Node REST API**로 구성한 프로젝트입니다.

```
Requirements Definition/
├─ requirements/
│  ├─ requirements.php     # 요구사항 목록 (PHP 페이지 + ?api= JSON API)
│  └─ dashboard.php        # 요구사항 대시보드
├─ lib.php                 # 공유 백엔드 ($DB + ?api= JSON API). 각 페이지가 require __DIR__.'/../lib.php'
├─ app.js / style.css      # 공유 프런트엔드 / 스타일
├─ php.ini                 # PHP 빌트인 서버 설정 (short_open_tag=Off · extension=pdo_mysql) — 필수
├─ service_todolist/       # 서비스팀 TO-DO 독립 페이지 (자체 백엔드 내장)
├─ member/                 # 회원 관리 독립 페이지 (자체 백엔드 내장)
├─ db/
│  ├─ schema.sql           # DB·테이블·뷰 생성 (정규화: 코드 테이블 + FK)
│  ├─ seed.sql             # 코드값 + 기존 30건 데이터
│  ├─ todo.sql             # 서비스팀 TO-DO 테이블/뷰/시드 (독립 페이지용)
│  └─ member.sql           # 회원 테이블 + 시드 (gd.xlsx 기반 15명)
└─ server/
   ├─ server.js            # (선택) Express REST API /api/* — JSON 전용, PHP 화면은 미제공
   ├─ db.js                # MySQL 커넥션 풀
   ├─ package.json
   └─ .env.example         # 접속정보 템플릿
```

웹 화면(`requirements/requirements.php` / `requirements/dashboard.php`)은 **두 가지 모드**로 동작합니다.
- 🟢 **DB 연결 모드** — PHP 빌트인 서버로 제공 시 MySQL과 실시간 연동 (CRUD 영구 저장).
- 💾 **로컬 모드** — 서버에 연결되지 않으면 `app.js`가 자동으로 브라우저 localStorage 폴백으로 동작.

화면 우상단 배지로 현재 모드를 표시합니다.

---

## 1. 데이터베이스 만들기

MySQL 8.0+ 설치 후 (한글 안전을 위해 `utf8mb4` 사용):

```bash
mysql -u root -p < db/schema.sql
mysql -u root -p < db/seed.sql
mysql -u root -p < db/todo.sql   # 서비스팀 TO-DO 독립 페이지를 쓸 경우
mysql -u root -p < db/member.sql # 회원 관리 페이지를 쓸 경우
```

메인 앱(`requirements/requirements.php` 목록 / `requirements/dashboard.php` 대시보드)의 LNB 메뉴는 **요구사항 정의** 그룹(요구사항 목록 / 요구사항 대시보드)으로 구성됩니다.
서비스팀 TO-DO는 별도 독립 페이지 `service_todolist/service_todolist.php` 에서 관리합니다 (같은 MySQL `todo` 테이블 사용).
회원 관리는 독립 페이지 `member/member.php` 에서 제공합니다 (MySQL `member` 테이블, `db/member.sql` 로 적재). 모든 페이지 LNB의 **회원** 그룹으로 이동.

확인:
```sql
USE medihim;
SELECT COUNT(*) FROM requirement;          -- 30
SELECT * FROM v_requirement LIMIT 3;        -- 한글 라벨로 평면 조회
SELECT * FROM v_dashboard_summary;          -- 요약 집계
```

### 스키마 개요 (정규화)
- 코드 테이블: `cat_category`(구분) · `cat_part`(파트) · `cat_priority`(우선순위) ·
  `cat_level`(상/중/하) · `cat_version`(목표버전) · `cat_status`(상태) · `cat_rank`(순번)
- 메인 테이블 `requirement` — 코드값은 `*_id` 외래키(FK)로 참조, 미선택 항목은 `NULL`
- 뷰 `v_requirement` — FK를 한글 라벨로 JOIN한 평면 뷰(엑셀 시트와 동일 형태)
- 대시보드 뷰 — `v_dashboard_summary` / `_category` / `_priority` / `_status` / `_version`

---

## 2. 웹앱 실행 (PHP 빌트인 서버 — 메인)

프로젝트 루트에서:

```bash
php -c php.ini -S localhost:8000
```

브라우저에서 접속:
- 요구사항 목록     → **http://localhost:8000/requirements/requirements.php**
- 요구사항 대시보드 → **http://localhost:8000/requirements/dashboard.php**
- 서비스팀 TO-DO    → **http://localhost:8000/service_todolist/service_todolist.php**
- 회원 관리         → **http://localhost:8000/member/member.php**

> `-c php.ini` 는 **필수**입니다 — `short_open_tag=Off` 가 아니면 xlsx 생성 JS의 `<?xml … ?>` 를 PHP 태그로 오인해 파싱 에러가 나고, `extension=pdo_mysql` 도 필요합니다.

각 PHP 페이지는 **같은 파일에서 `?api=...` 로 JSON API도 제공**합니다(`lib.php`가 처리). 예:
`requirements/requirements.php?api=requirements` · `?api=dashboard` · `?api=options` · `?api=health` (GET/POST/PUT/DELETE).

## 3. (선택) Node REST API

별도 REST API가 필요할 때만 Node 서버를 띄웁니다. **이 서버는 JSON API(`/api/*`) 전용이며 PHP 화면은 제공하지 않습니다**(PHP는 Node에서 실행되지 않음 — 화면은 위 PHP 서버로 띄우세요).

```bash
cd server
copy .env.example .env       # (Windows) 또는  cp .env.example .env
#  → .env 의 DB_USER / DB_PASSWORD 등을 본인 환경에 맞게 수정
npm install
npm start
```

`http://localhost:3000/api/health` 등으로 호출합니다.

### REST API (Node, `/api/*`)
| 메서드 | 경로 | 설명 |
|--------|------|------|
| GET    | `/api/health` | 헬스체크 |
| GET    | `/api/options` | 드롭다운 코드값 전체 |
| GET    | `/api/requirements` | 목록 (쿼리: `q`, `category`, `priority`, `status`) |
| GET    | `/api/requirements/:id` | 단건 |
| POST   | `/api/requirements` | 생성 |
| PUT    | `/api/requirements/:id` | 수정 |
| DELETE | `/api/requirements/:id` | 삭제 |
| GET    | `/api/dashboard` | 요약 + 차원별 집계 |

API는 프런트엔드의 한글 값(예: `category:"기능"`)을 받아 자동으로 FK id로 변환해 저장하고,
조회 시 다시 한글 라벨로 돌려줍니다.

---

## 참고
- 엑셀/CSV 내보내기, 검색·필터, 대시보드는 두 모드 모두 동작합니다.
- JSON 복원(가져오기)은 로컬 모드 전용입니다. DB에는 `db/seed.sql` 로 적재하세요.
