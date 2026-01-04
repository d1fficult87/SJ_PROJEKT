# ELT proces datasetu Opta Data: Football – SAMPLE (EPL)

Tento repozitár predstavuje implementáciu **ELT procesu v Snowflake** pre dataset zo **Snowflake Marketplace** a vytvorenie dátového skladu so schémou **Star Schema**. Projekt sa zameriava na analýzu futbalových zápasov a výkonnosti hráčov/tímov (góly, strely, prihrávky a ich presnosť). Výsledný dátový model umožňuje multidimenzionálnu analýzu a tvorbu vizualizácií.

* * *

## 1. Úvod a popis zdrojových dát

V tomto projekte analyzujeme dáta z futbalu (EPL) s cieľom porozumieť:
- výkonnosti hráčov v zápasoch (strely, góly, prihrávky),
- tímovej produktivite (strely vs góly, presné prihrávky),
- trendom naprieč matchday (kolami),
- porovnaniu hráčov a tímov v kľúčových metrikách.

### Zdrojové dáta (Snowflake Marketplace)
Dataset: **Opta Data: Football – SAMPLE**

- **Shared Database:** `OPTA_DATA_FOOTBALL__SAMPLE`
- **Schema:** `EPL`

Dataset obsahuje (príklad):
- Core objekty: `GAME`, `EVENT`, `PLAYER`, `TEAM`, `VENUE`, `PLAYER_GAME_STATISTIC`, `TEAM_GAME_STATISTIC`
- Referenčné tabuľky: `EVENT_TYPE`, `PLAYER_POSITION` (a ďalšie)

> Poznámka: Keďže ide o **shared database**, nie je možné v nej vytvárať vlastné tabuľky/objekty. Preto si vytvárame vlastnú DB a robíme staging kopie (CTAS).

* * *

### 1.1 Dátová architektúra

### ERD diagram
Surové dáta sú usporiadané v relačnom modeli. ERD pôvodnej dátovej štruktúry:

![ERD Schema](img/erd_source.png)

Obrázok 1 Entitno-relačná schéma (source / marketplace)

Krátky popis vybraných tabuliek/views:
- **GAME** – informácie o zápase (dátum, tímy, skóre, matchday, venue, attendance…)
- **EVENT** – detailné udalosti počas zápasu (čas, typ udalosti, pozícia, hráč, tím…)
- **PLAYER** – informácie o hráčoch
- **TEAM** – informácie o tímoch
- **VENUE** – štadióny
- **PLAYER_GAME_STATISTIC** – agregované štatistiky hráča v zápase (strely, góly, prihrávky…)
- **TEAM_GAME_STATISTIC** – agregované štatistiky tímu v zápase
- **EVENT_TYPE**, **PLAYER_POSITION** – referenčné/číselníkové tabuľky

* * *

## 2 Dimenzionálny model

Navrhnutá je schéma hviezdy (Star Schema) podľa Kimballovej metodológie.
Faktová tabuľka `FACT_PLAYER_MATCH` (grain: **hráč × zápas**) je prepojená s dimenziami:

- `DIM_PLAYER` – informácie o hráčovi (SCD Type 1)
- `DIM_TEAM` – informácie o tíme (SCD Type 1)
- `DIM_GAME` – informácie o zápase (SCD Type 0/1)
- `DIM_VENUE` – informácie o štadióne (SCD Type 1)
- `DIM_DATE` – kalendár (SCD Type 0)

### Star Schema diagram
![Star Schema](img/star_schema.png)

Obrázok 2 Schéma hviezdy (DWH)

### Faktová tabuľka: FACT_PLAYER_MATCH
- **PK (logicky):** (`PLAYER_ID`, `GAME_ID`)
- **FK:** `DATE_KEY`, `GAME_ID`, `PLAYER_ID`, `TEAM_ID`, `VENUE_ID`
- **Metriky (príklad):**
  - `GOALS`
  - `TOTAL_SCORING_ATT`, `ONTARGET_SCORING_ATT`, `BLOCKED_SCORING_ATT`, `POST_SCORING_ATT`
  - `TOTAL_PASS`, `ACCURATE_PASS`
  - `TOTAL_CROSS`, `ACCURATE_CROSS`
  - (voliteľne) odvodené metriky ako presnosť prihrávok

✅ Povinná požiadavka (window functions vo faktovej tabuľke) – príklad:
- `RANK() OVER (PARTITION BY GAME_ID ORDER BY GOALS DESC, TOTAL_SCORING_ATT DESC) AS RANK_IN_GAME`
- `SUM(GOALS) OVER (PARTITION BY PLAYER_ID ORDER BY GAME_DATE ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS CUM_GOALS`

* * *

## 3. ELT proces v Snowflake

ELT proces pozostáva z troch hlavných fáz: `extrahovanie` (Extract), `načítanie` (Load) a `transformácia` (Transform). Implementácia je v Snowflake s cieľom pripraviť dáta zo staging vrstvy do viacdimenzionálneho modelu vhodného na analýzu a vizualizáciu.

* * *

### 3.1 Extract (Extrahovanie dát)

Keďže zdroj je **Marketplace (shared database)**, najskôr si vytvoríme vlastnú databázu a schémy:

#### Príklad kódu:
```sql
CREATE OR REPLACE DATABASE FOOTBALL_DWH;
CREATE OR REPLACE SCHEMA FOOTBALL_DWH.STAGING;
CREATE OR REPLACE SCHEMA FOOTBALL_DWH.DWH;
