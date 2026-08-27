[![License](https://img.shields.io/github/license/vielhuber/chessmaster)](https://github.com/vielhuber/chessmaster/blob/main/LICENSE.md)
[![Last Commit](https://img.shields.io/github/last-commit/vielhuber/chessmaster)](https://github.com/vielhuber/chessmaster/commits)

# ♞ chessmaster ♞

chessmaster is a small php application that imports your games from [chess.com](https://www.chess.com) and displays their history, statistics, openings and blunders.

## installation

```sh
git clone git@github.com:vielhuber/chessmaster.git .
npm install
npm run prod
cp .env.example .env
php -S localhost:8080 -t _public
```

## configuration

```dotenv
USERNAME=vielhuber
DATABASE=_data/chessmaster.sqlite
```
