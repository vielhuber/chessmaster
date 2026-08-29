import { Chess } from './chess.js';
import LossReasonClassifier from './loss-reason-classifier.js';

class Charts {
    static DATA_SELECTOR = '#dashboard-data';

    load() {
        let $data = document.querySelector(Charts.DATA_SELECTOR);
        if (!$data || typeof window.Chart === 'undefined') {
            return;
        }

        let data = JSON.parse($data.textContent);
        window.Chart.defaults.color = '#9ca9a1';
        window.Chart.defaults.borderColor = 'rgba(235, 244, 238, 0.1)';
        window.Chart.defaults.font.family = 'Inter, ui-sans-serif, system-ui, sans-serif';

        new window.Chart(document.querySelector('#result-chart'), {
            type: 'doughnut',
            data: {
                labels: data.results.labels,
                datasets: [
                    {
                        data: data.results.values,
                        backgroundColor: ['#8acb6b', '#f0a35b', '#d96b61'],
                        borderWidth: 0,
                        hoverOffset: 5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } } }
            }
        });

        new window.Chart(document.querySelector('#time-class-chart'), {
            type: 'bar',
            data: {
                labels: data.timeClasses.labels,
                datasets: [
                    { label: 'Siege', data: data.timeClasses.wins, backgroundColor: '#8acb6b', borderRadius: 4 },
                    { label: 'Remis', data: data.timeClasses.draws, backgroundColor: '#f0a35b', borderRadius: 4 },
                    { label: 'Niederlagen', data: data.timeClasses.losses, backgroundColor: '#d96b61', borderRadius: 4 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } },
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 18 } } }
            }
        });

        new window.Chart(document.querySelector('#month-chart'), {
            type: 'line',
            data: {
                labels: data.months.labels,
                datasets: [
                    {
                        label: 'Score in %',
                        data: data.months.scores,
                        borderColor: '#b6ef80',
                        backgroundColor: 'rgba(182, 239, 128, 0.08)',
                        pointBackgroundColor: '#b6ef80',
                        pointRadius: 3,
                        tension: 0.3,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { min: 0, suggestedMax: 100, grace: '5%', ticks: { callback: value => `${value} %` } }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            afterLabel: context => `${data.months.games[context.dataIndex]} Partien`
                        }
                    }
                }
            }
        });
    }
}

class ChessboardView {
    static PIECES = {
        K: 'wk',
        Q: 'wq',
        R: 'wr',
        B: 'wb',
        N: 'wn',
        P: 'wp',
        k: 'bk',
        q: 'bq',
        r: 'br',
        b: 'bb',
        n: 'bn',
        p: 'bp'
    };

    constructor($board) {
        this.$board = $board;
    }

    render(options) {
        let board = {};
        let rows = options.fen.split(' ')[0].split('/');
        rows.forEach((row, rowIndex) => {
            let fileIndex = 0;
            Array.from(row).forEach(character => {
                if (/\d/.test(character)) {
                    fileIndex += Number(character);
                    return;
                }
                board[`${String.fromCharCode(97 + fileIndex)}${8 - rowIndex}`] = character;
                fileIndex += 1;
            });
        });

        let ranks = options.orientation === 'white' ? [8, 7, 6, 5, 4, 3, 2, 1] : [1, 2, 3, 4, 5, 6, 7, 8];
        let files =
            options.orientation === 'white'
                ? ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h']
                : ['h', 'g', 'f', 'e', 'd', 'c', 'b', 'a'];
        let selectedSquare = options.selectedSquare ?? null;
        let legalMoves = options.legalMoves ?? [];
        let onSquareClick = options.onSquareClick ?? null;
        let $fragment = document.createDocumentFragment();

        ranks.forEach(rank => {
            files.forEach(file => {
                let square = `${file}${rank}`;
                let $square = document.createElement(onSquareClick === null ? 'div' : 'button');
                if ($square instanceof HTMLButtonElement) {
                    $square.type = 'button';
                }
                $square.className = `square ${(file.charCodeAt(0) + rank) % 2 === 0 ? 'dark' : 'light'}`;
                $square.dataset.square = square;
                let piece = ChessboardView.PIECES[board[square]];
                if (piece) {
                    let $piece = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                    let $pieceReference = document.createElementNS('http://www.w3.org/2000/svg', 'use');
                    $piece.classList.add('piece');
                    $piece.setAttribute('viewBox', '0 0 40 40');
                    $piece.setAttribute('aria-hidden', 'true');
                    $pieceReference.setAttribute('href', `/assets/chess-pieces.svg#${piece}`);
                    $piece.append($pieceReference);
                    $square.append($piece);
                }
                $square.setAttribute('aria-label', square);
                if (square === selectedSquare) {
                    $square.classList.add('selected');
                }
                if (legalMoves.some(move => move.to === square)) {
                    $square.classList.add('legal');
                }
                if (onSquareClick !== null) {
                    $square.addEventListener('click', () => onSquareClick(square));
                }
                $fragment.append($square);
            });
        });

        if (options.arrow) {
            let fromFileIndex = files.indexOf(options.arrow.from[0]);
            let fromRankIndex = ranks.indexOf(Number(options.arrow.from[1]));
            let toFileIndex = files.indexOf(options.arrow.to[0]);
            let toRankIndex = ranks.indexOf(Number(options.arrow.to[1]));
            let fromX = fromFileIndex + 0.5;
            let fromY = fromRankIndex + 0.5;
            let toX = toFileIndex + 0.5;
            let toY = toRankIndex + 0.5;
            let deltaX = toX - fromX;
            let deltaY = toY - fromY;
            let distance = Math.hypot(deltaX, deltaY);
            let endPadding = 0.16;
            let $arrow = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            let $definitions = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
            let $marker = document.createElementNS('http://www.w3.org/2000/svg', 'marker');
            let $arrowHead = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            let $line = document.createElementNS('http://www.w3.org/2000/svg', 'line');

            $arrow.classList.add('blunder-arrow');
            $arrow.setAttribute('viewBox', '0 0 8 8');
            $arrow.setAttribute('aria-hidden', 'true');
            let arrowHeadId = options.arrow.correct ? 'correct-arrow-head' : 'blunder-arrow-head';
            $marker.setAttribute('id', arrowHeadId);
            $marker.setAttribute('viewBox', '0 0 1 1');
            $marker.setAttribute('markerWidth', '0.32');
            $marker.setAttribute('markerHeight', '0.32');
            $marker.setAttribute('refX', '0.82');
            $marker.setAttribute('refY', '0.5');
            $marker.setAttribute('orient', 'auto-start-reverse');
            $marker.setAttribute('markerUnits', 'userSpaceOnUse');
            $arrowHead.setAttribute('d', 'M 0 0 L 1 0.5 L 0 1 z');
            $line.setAttribute('x1', String(fromX));
            $line.setAttribute('y1', String(fromY));
            $line.setAttribute('x2', String(toX - (deltaX / distance) * endPadding));
            $line.setAttribute('y2', String(toY - (deltaY / distance) * endPadding));
            $line.setAttribute('marker-end', `url(#${arrowHeadId})`);
            $arrow.classList.toggle('correct', options.arrow.correct === true);
            $marker.append($arrowHead);
            $definitions.append($marker);
            $arrow.append($definitions, $line);
            $fragment.append($arrow);
        }

        this.$board.replaceChildren($fragment);
    }
}

class OpeningExamplePlayer {
    static MAXIMUM_PLIES = 8;

    constructor($element, data) {
        this.$board = $element.querySelector('[data-opening-board]');
        this.$moves = $element.querySelector('[data-opening-moves]');
        this.$status = $element.querySelector('[data-opening-status]');
        this.$previous = $element.querySelector('[data-opening-previous]');
        this.$play = $element.querySelector('[data-opening-play]');
        this.$next = $element.querySelector('[data-opening-next]');
        this.data = data;
        this.moves = [];
        this.currentPly = 0;
        this.playback = null;
    }

    load() {
        let game = new Chess();
        game.loadPgn(this.data.pgn);
        this.moves = game.history({ verbose: true }).slice(0, OpeningExamplePlayer.MAXIMUM_PLIES);
        this.orientation = this.data.playerColor;
        this.boardView = new ChessboardView(this.$board);
        this.$moveButtons = this.moves.map((move, index) => {
            let $button = document.createElement('button');
            let moveNumber = Math.floor(index / 2) + 1;
            $button.type = 'button';
            $button.textContent = move.color === 'w' ? `${moveNumber}. ${move.san}` : `${moveNumber}… ${move.san}`;
            $button.addEventListener('click', () => {
                this.stopPlayback();
                this.showPosition(index + 1);
            });
            this.$moves.append($button);
            return $button;
        });
        this.$previous.addEventListener('click', () => {
            this.stopPlayback();
            this.showPosition(this.currentPly - 1);
        });
        this.$next.addEventListener('click', () => {
            this.stopPlayback();
            this.showPosition(this.currentPly + 1);
        });
        this.$play.addEventListener('click', () => this.togglePlayback());
        this.showPosition(0);
    }

    showPosition(ply) {
        this.currentPly = Math.min(this.moves.length, Math.max(0, ply));
        let position = new Chess();
        this.moves.slice(0, this.currentPly).forEach(move => position.move(move.san));
        this.boardView.render({ fen: position.fen(), orientation: this.orientation });
        this.$moveButtons.forEach(($button, index) =>
            $button.classList.toggle('active', index === this.currentPly - 1)
        );
        this.$previous.disabled = this.currentPly === 0;
        this.$next.disabled = this.currentPly === this.moves.length;

        if (this.currentPly === 0) {
            this.$status.textContent = 'Ausgangsstellung';
            return;
        }

        let currentMove = this.$moveButtons[this.currentPly - 1].textContent;
        if (this.currentPly === this.moves.length) {
            this.$status.textContent = `Beispielstellung erreicht · ${currentMove}`;
            return;
        }
        this.$status.textContent = `Zug ${this.currentPly} von ${this.moves.length} · ${currentMove}`;
    }

    togglePlayback() {
        if (this.playback !== null) {
            this.stopPlayback();
            return;
        }
        if (this.currentPly === this.moves.length) {
            this.showPosition(0);
        }

        this.$play.textContent = 'Pause';
        this.playback = window.setInterval(() => {
            if (this.currentPly === this.moves.length) {
                this.stopPlayback();
                return;
            }
            this.showPosition(this.currentPly + 1);
            if (this.currentPly === this.moves.length) {
                this.stopPlayback();
            }
        }, 800);
    }

    stopPlayback() {
        if (this.playback !== null) {
            window.clearInterval(this.playback);
        }
        this.playback = null;
        this.$play.textContent = 'Abspielen';
    }
}

class OpeningExamples {
    load() {
        let $data = document.querySelector('#opening-examples-data');
        if (!$data) {
            return;
        }

        let examples = JSON.parse($data.textContent);
        document.querySelectorAll('[data-opening-example]').forEach(($element, index) => {
            if (!examples[index]) {
                return;
            }
            new OpeningExamplePlayer($element, examples[index]).load();
        });
    }
}

class StockfishEvaluator {
    static DEPTH = 10;

    constructor() {
        this.worker = null;
        this.workerReady = null;
        this.analysis = null;
        this.queue = Promise.resolve();
    }

    evaluate(fen) {
        let evaluation = this.queue.then(async () => {
            await this.prepareWorker();

            return new Promise(resolve => {
                this.analysis = { resolve, score: 0, bestMove: null };
                this.worker.postMessage(`position fen ${fen}`);
                this.worker.postMessage(`go depth ${StockfishEvaluator.DEPTH}`);
            });
        });
        this.queue = evaluation.catch(() => undefined);

        return evaluation;
    }

    async prepareWorker() {
        if (this.workerReady !== null) {
            return this.workerReady;
        }

        this.workerReady = new Promise((resolve, reject) => {
            this.worker = new Worker('/assets/stockfish-18-lite-single.js');
            let timeout = window.setTimeout(() => reject(new Error('Stockfish konnte nicht gestartet werden.')), 20000);
            this.worker.onerror = () => reject(new Error('Stockfish konnte nicht geladen werden.'));
            this.worker.onmessage = event => {
                let lines = String(event.data).split('\n');
                lines.forEach(line => {
                    if (line === 'uciok') {
                        this.worker.postMessage('isready');
                    }
                    if (line === 'readyok') {
                        window.clearTimeout(timeout);
                        resolve();
                    }
                    this.consumeAnalysisLine(line);
                });
            };
            this.worker.postMessage('uci');
        });

        return this.workerReady;
    }

    consumeAnalysisLine(line) {
        if (this.analysis === null) {
            return;
        }

        let centipawns = line.match(/\bscore cp (-?\d+)/);
        let mate = line.match(/\bscore mate (-?\d+)/);
        let variation = line.match(/\bpv ([a-h][1-8][a-h][1-8][qrbn]?)/);
        if (centipawns) {
            this.analysis.score = Number(centipawns[1]);
        }
        if (mate) {
            this.analysis.score = Number(mate[1]) > 0 ? 100000 : -100000;
        }
        if (variation) {
            this.analysis.bestMove = variation[1];
        }
        if (!line.startsWith('bestmove')) {
            return;
        }

        let bestMove = line.match(/^bestmove\s+(\S+)/);
        let result = {
            score: this.analysis.score,
            bestMove: this.analysis.bestMove || bestMove?.[1] || null
        };
        let resolve = this.analysis.resolve;
        this.analysis = null;
        resolve(result);
    }
}

class LossReasonAnalyzer {
    static ENDPOINT = '/api/loss-analysis';

    constructor(stockfish) {
        this.stockfish = stockfish;
        this.$status = document.querySelector('#loss-analysis-status');
    }

    async load() {
        if (document.querySelector('#chessboard')) {
            return;
        }

        let analyzedGames = 0;

        try {
            while (true) {
                let response = await fetch(LossReasonAnalyzer.ENDPOINT, { headers: { Accept: 'application/json' } });
                let data = await response.json();
                if (!response.ok) {
                    throw new Error(data.error || 'Eine Niederlage konnte nicht geladen werden.');
                }

                let game = data.game;
                if (game === null) {
                    break;
                }

                this.showStatus(`Analysiere Niederlage ${analyzedGames + 1} …`);
                let reason = await this.analyzeGame(game);
                let saveResponse = await fetch(LossReasonAnalyzer.ENDPOINT, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ url: game.url, reason })
                });
                if (!saveResponse.ok) {
                    let saveData = await saveResponse.json();
                    throw new Error(saveData.error || 'Der Niederlagengrund konnte nicht gespeichert werden.');
                }
                analyzedGames += 1;
            }

            if (analyzedGames > 0) {
                window.location.reload();
            }
        } catch {
            this.showStatus('Stockfish-Analyse pausiert');
        }
    }

    async analyzeGame(game) {
        let chess = new Chess();
        try {
            chess.loadPgn(game.pgn);
        } catch {
            return 'unknown';
        }

        let color = game.playerColor === 'white' ? 'w' : 'b';
        let moves = chess.history({ verbose: true }).filter(move => move.color === color);
        if (moves.length === 0) {
            return 'unknown';
        }

        for (let move of moves) {
            let before = await this.stockfish.evaluate(move.before);
            let after = await this.stockfish.evaluate(move.after);
            if (LossReasonClassifier.isBlunder(before.score, after.score)) {
                return 'blunder';
            }
        }

        return 'outplayed';
    }

    showStatus(message) {
        if (!this.$status) {
            return;
        }

        this.$status.textContent = message;
        this.$status.hidden = false;
    }
}

class BlunderTrainer {
    static MINIMUM_BLUNDER_LOSS = 100;

    constructor(stockfish) {
        this.$board = document.querySelector('#chessboard');
        this.$placeholder = document.querySelector('#board-placeholder');
        this.$placeholderText = this.$placeholder?.querySelector('p');
        this.$button = document.querySelector('#new-blunder');
        this.$title = document.querySelector('#trainer-title');
        this.$description = document.querySelector('#trainer-description');
        this.$feedback = document.querySelector('#trainer-feedback');
        this.$source = document.querySelector('#trainer-source');
        this.$progress = document.querySelector('#analysis-progress');
        this.$progressBar = document.querySelector('#analysis-progress-bar');
        this.$evaluationBar = document.querySelector('#evaluation-bar');
        this.$evaluationWhite = document.querySelector('#evaluation-white');
        this.$evaluationScore = document.querySelector('#evaluation-score');
        this.$navigation = document.querySelector('#board-navigation');
        this.$previous = document.querySelector('#board-previous');
        this.$next = document.querySelector('#board-next');
        this.$boardPosition = document.querySelector('#board-position');
        this.boardView = this.$board ? new ChessboardView(this.$board) : null;
        this.stockfish = stockfish;
        this.position = null;
        this.orientation = 'white';
        this.selectedSquare = null;
        this.legalMoves = [];
        this.answering = false;
        this.reviewMoves = [];
        this.trainingPly = 0;
        this.currentPly = 0;
        this.variantPlayed = false;
        this.variantMoveSan = null;
        this.correctMoveArrow = null;
    }

    load() {
        if (!this.$button) {
            return;
        }
        this.$button.addEventListener('click', () => this.findBlunder());
        this.$previous.addEventListener('click', () =>
            this.showReviewPosition(this.variantPlayed ? this.currentPly : this.currentPly - 1)
        );
        this.$next.addEventListener('click', () => this.showReviewPosition(this.currentPly + 1));
        this.findBlunder();
    }

    async findBlunder() {
        this.$button.disabled = true;
        this.$button.textContent = 'Stockfish analysiert …';
        this.$placeholder.hidden = false;
        this.$placeholderText.textContent = 'Stockfish analysiert deine Partien …';
        this.$progress.hidden = false;
        this.$feedback.textContent = '';
        this.$source.hidden = true;
        this.$evaluationBar.hidden = true;
        this.$navigation.hidden = true;
        this.$title.textContent = 'Partie wird untersucht';
        this.$description.textContent = 'Stockfish bewertet deine Züge direkt im Browser.';

        try {
            let strongestCandidate = null;
            for (let attempt = 0; attempt < 3; attempt += 1) {
                let game = await this.loadRandomGame();
                let candidate = await this.analyzeGame(game);
                if (candidate === null) {
                    continue;
                }

                if (this.isClearerCandidate(candidate, strongestCandidate)) {
                    strongestCandidate = candidate;
                }
                if (candidate.obvious) {
                    break;
                }
            }

            if (strongestCandidate === null) {
                throw new Error('In den gewählten Partien wurde keine trainierbare Stellung gefunden.');
            }
            this.showCandidate(strongestCandidate);
        } catch (error) {
            this.$title.textContent = 'Analyse nicht möglich';
            this.$description.textContent = error instanceof Error ? error.message : 'Unbekannter Fehler.';
            this.$placeholderText.textContent = 'Keine Stellung gefunden.';
            this.$progress.hidden = true;
        } finally {
            this.$button.disabled = false;
            this.$button.textContent = 'Anderen Patzer suchen';
        }
    }

    async loadRandomGame() {
        let response = await fetch('/api/random', { headers: { Accept: 'application/json' } });
        let data = await response.json();
        if (!response.ok) {
            throw new Error(data.error || 'Die Trainingspartie konnte nicht geladen werden.');
        }
        return data;
    }

    async analyzeGame(game) {
        let chess = new Chess();
        chess.loadPgn(game.pgn);
        let color = game.playerColor === 'white' ? 'w' : 'b';
        let moves = chess.history({ verbose: true }).filter(move => move.color === color);
        if (moves.length === 0) {
            throw new Error('Diese Partie enthält keine auswertbaren Züge.');
        }

        let strongestCandidate = null;
        for (let index = 0; index < moves.length; index += 1) {
            this.$description.textContent = `Analysiere Zug ${index + 1} von ${moves.length} gegen ${game.opponent} …`;
            this.$progressBar.style.width = `${((index + 1) / moves.length) * 100}%`;
            let before = await this.stockfish.evaluate(moves[index].before);
            let after = await this.stockfish.evaluate(moves[index].after);
            let loss = Math.max(0, before.score + after.score);
            if (loss < BlunderTrainer.MINIMUM_BLUNDER_LOSS) {
                continue;
            }

            let bestMoveSan = this.moveSan(moves[index].before, before.bestMove);
            let tacticalBestMove = /[x+#]/.test(bestMoveSan);
            let directPunishment = after.bestMove?.slice(2, 4) === moves[index].to;
            let playableBefore = before.score >= -150;
            let obvious = playableBefore && (tacticalBestMove || directPunishment);
            let clarity = (tacticalBestMove ? 4 : 0) + (directPunishment ? 3 : 0) + (playableBefore ? 2 : 0);
            let candidate = {
                game,
                move: moves[index],
                before: moves[index].before,
                bestMove: before.bestMove,
                bestScore: before.score,
                loss,
                obvious,
                clarity
            };
            if (this.isClearerCandidate(candidate, strongestCandidate)) {
                strongestCandidate = candidate;
            }
        }

        return strongestCandidate;
    }

    isClearerCandidate(candidate, currentCandidate) {
        if (currentCandidate === null) {
            return true;
        }
        if (candidate.obvious !== currentCandidate.obvious) {
            return candidate.obvious;
        }
        if (candidate.clarity !== currentCandidate.clarity) {
            return candidate.clarity > currentCandidate.clarity;
        }
        return candidate.loss > currentCandidate.loss;
    }

    showCandidate(candidate) {
        let game = new Chess();
        game.loadPgn(candidate.game.pgn);
        this.reviewMoves = game.history({ verbose: true });
        this.trainingPly = this.reviewMoves.findIndex(
            move =>
                move.before === candidate.before && move.from === candidate.move.from && move.to === candidate.move.to
        );
        if (this.trainingPly < 0) {
            throw new Error('Die Trainingsstellung konnte in der Partie nicht gefunden werden.');
        }
        this.currentPly = this.trainingPly;
        this.variantPlayed = false;
        this.variantMoveSan = null;
        this.correctMoveArrow = null;
        this.position = new Chess(candidate.before);
        this.orientation = candidate.game.playerColor;
        this.selectedSquare = null;
        this.legalMoves = [];
        this.answering = true;
        this.candidate = candidate;
        this.$placeholder.hidden = true;
        this.$progress.hidden = true;
        this.$title.textContent = `Patzer gegen ${candidate.game.opponent}`;
        this.$description.textContent = `Du spieltest ${candidate.move.san} und verlorst dabei ungefähr ${Math.round(candidate.loss / 100)} Bauerneinheiten. Finde einen besseren Zug.`;
        this.$source.href = candidate.game.url;
        this.$source.hidden = false;
        this.$feedback.textContent = 'Du bist am Zug.';
        this.$evaluationBar.classList.toggle('flipped', this.orientation === 'black');
        this.$evaluationBar.hidden = false;
        this.$navigation.hidden = false;
        this.updateEvaluation(candidate.game.playerColor === 'white' ? candidate.bestScore : -candidate.bestScore);
        this.updateReviewControls();
        this.renderBoard();
    }

    async showReviewPosition(ply) {
        if (this.stockfish.analysis !== null) {
            return;
        }

        this.currentPly = Math.min(this.reviewMoves.length, Math.max(0, ply));
        this.variantPlayed = false;
        this.variantMoveSan = null;
        this.correctMoveArrow = null;
        this.position = new Chess();
        this.reviewMoves.slice(0, this.currentPly).forEach(move => this.position.move(move.san));
        this.selectedSquare = null;
        this.legalMoves = [];
        this.answering = false;
        this.$feedback.textContent = 'Bewerte Stellung …';
        this.updateReviewControls();
        this.$previous.disabled = true;
        this.$next.disabled = true;
        this.renderBoard();

        try {
            let turn = this.position.turn();
            let evaluation = await this.stockfish.evaluate(this.position.fen());
            this.updateEvaluation(turn === 'w' ? evaluation.score : -evaluation.score);
            this.answering = this.currentPly === this.trainingPly;
            this.$feedback.textContent = this.answering
                ? 'Du bist am Zug.'
                : 'Mit den Pfeilen kannst du die Partie Zug für Zug durchgehen.';
            this.renderBoard();
        } catch (error) {
            this.$feedback.textContent =
                error instanceof Error ? error.message : 'Die Stellung konnte nicht bewertet werden.';
        } finally {
            this.updateReviewControls();
        }
    }

    updateReviewControls() {
        this.$previous.disabled = this.currentPly === 0 && !this.variantPlayed;
        this.$next.disabled = this.currentPly === this.reviewMoves.length;

        if (this.variantPlayed) {
            this.$boardPosition.textContent = `Deine Variante · ${this.variantMoveSan}`;
            return;
        }
        if (this.currentPly === this.trainingPly) {
            this.$boardPosition.textContent = 'Trainingsstellung';
            return;
        }
        if (this.currentPly === 0) {
            this.$boardPosition.textContent = 'Ausgangsstellung';
            return;
        }
        if (this.currentPly === this.reviewMoves.length) {
            this.$boardPosition.textContent = 'Partieende';
            return;
        }

        this.$boardPosition.textContent = `Zug ${this.currentPly} von ${this.reviewMoves.length} · ${this.reviewMoves[this.currentPly - 1].san}`;
    }

    renderBoard() {
        let arrow = null;
        if (this.variantPlayed || this.currentPly === this.trainingPly) {
            arrow = this.correctMoveArrow ?? (this.answering ? this.candidate?.move : null);
        }
        this.boardView.render({
            fen: this.position.fen(),
            orientation: this.orientation,
            selectedSquare: this.selectedSquare,
            legalMoves: this.legalMoves,
            onSquareClick: square => this.selectSquare(square),
            arrow
        });
    }

    selectSquare(square) {
        if (!this.answering) {
            return;
        }

        let selectedMove = this.legalMoves.find(move => move.to === square);
        if (selectedMove) {
            this.answering = false;
            this.position.move({
                from: selectedMove.from,
                to: selectedMove.to,
                promotion: selectedMove.promotion || 'q'
            });
            this.selectedSquare = null;
            this.legalMoves = [];
            this.variantPlayed = true;
            this.variantMoveSan = selectedMove.san;
            this.updateReviewControls();
            this.renderBoard();
            this.evaluateAnswer(selectedMove);
            return;
        }

        let moves = this.position.moves({ square, verbose: true });
        this.selectedSquare = moves.length > 0 ? square : null;
        this.legalMoves = moves;
        this.renderBoard();
    }

    async evaluateAnswer(move) {
        this.$feedback.textContent = 'Bewerte deinen Zug …';
        let after = await this.stockfish.evaluate(this.position.fen());
        this.updateEvaluation(this.position.turn() === 'w' ? after.score : -after.score);
        let playerScore = -after.score;
        let loss = Math.max(0, this.candidate.bestScore - playerScore);
        let playedMove = `${move.from}${move.to}${move.promotion || ''}`;
        let bestSan = this.moveSan(this.candidate.before, this.candidate.bestMove);

        if (playedMove === this.candidate.bestMove || loss <= 30) {
            this.$feedback.textContent = `Sehr stark. ${move.san} ist die beste Wahl.`;
            return;
        }
        if (loss <= 80) {
            this.$feedback.textContent = `Gute Alternative. Stockfish bevorzugt ${bestSan}.`;
            return;
        }
        if (this.candidate.bestMove) {
            this.correctMoveArrow = {
                from: this.candidate.bestMove.slice(0, 2),
                to: this.candidate.bestMove.slice(2, 4),
                correct: true
            };
            this.renderBoard();
        }
        this.$feedback.textContent = `Noch nicht ganz. Der beste Zug ist ${bestSan}; gespielt wurde damals ${this.candidate.move.san}.`;
    }

    updateEvaluation(whiteScore) {
        let whitePercentage = 100 / (1 + Math.exp(-whiteScore / 400));
        let boundedPercentage = Math.min(97, Math.max(3, whitePercentage));
        let scoreLabel = '0,0';
        if (Math.abs(whiteScore) >= 100000) {
            scoreLabel = whiteScore > 0 ? '+M' : '−M';
        }
        if (Math.abs(whiteScore) < 100000 && whiteScore !== 0) {
            let sign = whiteScore > 0 ? '+' : '−';
            scoreLabel =
                sign +
                Math.abs(whiteScore / 100)
                    .toFixed(1)
                    .replace('.', ',');
        }

        this.$evaluationWhite.style.height = `${boundedPercentage}%`;
        this.$evaluationScore.textContent = scoreLabel;
        this.$evaluationBar.setAttribute('aria-label', `Stockfish-Bewertung ${scoreLabel} aus Sicht von Weiß`);
    }

    moveSan(fen, uciMove) {
        if (!uciMove) {
            return 'einen anderen Zug';
        }
        let chess = new Chess(fen);
        let move = chess.move({
            from: uciMove.slice(0, 2),
            to: uciMove.slice(2, 4),
            promotion: uciMove.slice(4, 5) || 'q'
        });
        return move?.san || uciMove;
    }
}

let stockfish = new StockfishEvaluator();
new Charts().load();
new OpeningExamples().load();
new LossReasonAnalyzer(stockfish).load();
new BlunderTrainer(stockfish).load();
