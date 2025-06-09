# PHP + SQLite FTS Filter Backend (NTT East Style)

This repository contains a backend prototype for a multi-category filter and full-text search engine, built using PHP, SQLite (FTS5), and MeCab.  
It replicates the core logic of a real-world enterprise project developed for NTT East Japan.

---

## Technologies Used

- PHP (vanilla)
- SQLite (with FTS5 full-text search)
- MeCab (Japanese tokenizer)
- Custom GET-based filtering logic
- Simple MVC-like file structure

---

## Screenshots
![NTT Screenshot 1](./images/ntt1.png)
![NTT Screenshot 2](./images/ntt2.png)
![NTT Screenshot 3](./images/ntt3.png)

| Filter UI (Production) | Localhost Implementation |



These screenshots show the actual category filter logic and how keyword-based search is combined with multiple checkbox filters.  
This project focuses on backend logic, not UI styling.

---

## Features

- Multi-category filtering using dynamic GET parameters
- URL structure: `?sv=2|3&issues=1|4` (OR within / AND between)
- Full-text search in Japanese (via MeCab + SQLite FTS5)
- Page-type scalability: Column / Case / Video / Document
- Logic separated into common handler (`common.php`)

---

## File Overview

| File             | Purpose |
|------------------|---------|
| `index.php`      | Form and results handler |
| `common.php`     | Core logic: parse filters, build SQL |
| `db_connect.php` | Connects to SQLite DB |
| `data_loader.php`| Seeds example data |
| `ntt_east.db`    | FTS5-indexed SQLite DB |
| `examples.json`  | Dummy data source |

---

## Structure Overview

```txt
GET: ?k=keyword&sv=2|3&issues=1|4
↓
common.php parses parameters
↓
FTS MATCH + OR/AND logic assembled
↓
SQL executed on FTS5-indexed SQLite DB
↓
Filtered results returned to index.php
