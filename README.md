[![License](https://img.shields.io/github/license/vielhuber/chessmaster)](https://github.com/vielhuber/chessmaster/blob/main/LICENSE.md)
[![Last Commit](https://img.shields.io/github/last-commit/vielhuber/chessmaster)](https://github.com/vielhuber/chessmaster/commits)

# ♞ chessmaster ♞

chessmaster downloads a complete Chess.com game history into SQLite and turns it into a private dashboard with statistics, opening results and interactive Stockfish blunder training.

the application is not published as a Composer or npm package. it requires PHP 8.5 with cURL, JSON, PDO and SQLite. Chart.js, chess.js and the Stockfish 18 lite WebAssembly engine are installed as private build dependencies and served locally from `_public/assets`.

## setup

```sh
cp .env.example .env
```

configure the Chess.com username and SQLite path:

```dotenv
USERNAME=vielhuber
DATABASE=_data/chessmaster.sqlite
```

point the virtual host document root to `_public/` and open the site. use the `Spiele aktualisieren` button to import monthly archives. completed historical months are skipped, the latest month is checked conditionally and games are inserted by their unique Chess.com URL. regular page views only read from SQLite.

SQLite and `.env` stay outside the public document root and are excluded from git.

the pages are available at:

- `/`: complete game history
- `/statistics`: Chart.js statistics
- `/openings`: opening results
- `/blunders`: interactive Stockfish blunder training (displayed as `Patzer`)

## data

the public Chess.com API does not require credentials. chessmaster stores the full PGN and original JSON response for every game. the interface contains:

- `Statistiken`: results, ratings, time controls and the rolling 24-month score rendered with Chart.js
- `Eröffnungen`: coarsely grouped opening families, the best and worst result among families with at least 50 games and interactive example lines for the three strongest families
- `Patzer`: a random game analyzed locally with Stockfish, followed by an interactive find-the-better-move exercise
- `Historie`: every imported game with result, opponent, rating, opening and a link back to Chess.com

## local development

```sh
npm install
npm update
npm run prod
php -S localhost:8080 -t _public
```

then open `http://localhost:8080`.

## third-party software

- [Chart.js](https://github.com/chartjs/Chart.js), MIT
- [chess.js](https://github.com/jhlywa/chess.js), BSD-2-Clause
- [Stockfish.js](https://github.com/nmrugg/stockfish.js), GPL-3.0
- [Wikimedia/Cburnett chess pieces](https://github.com/shaack/cm-chessboard/blob/master/assets/pieces/standard.svg), CC BY-SA 3.0

`npm run prod` copies the corresponding license texts and a generated source manifest to `_public/assets/licenses/`. The exact Stockfish source archive is published as `_public/assets/sources/stockfish.js-source.tar.gz`. Generated third-party files are excluded from git.
