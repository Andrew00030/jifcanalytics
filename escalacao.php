<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM atletas WHERE usuario_id = ? ORDER BY nome ASC");
$stmt->execute([$_SESSION['user_id']]);
$atletas = $stmt->fetchAll();

$selectedAtletaId = isset($_GET['atleta_id']) ? (int)$_GET['atleta_id'] : 0;
$selectedAtleta = null;
foreach ($atletas as $atleta) {
    if ((int)$atleta['id'] === $selectedAtletaId) {
        $selectedAtleta = $atleta;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_match') {
    $adversario = trim($_POST['adversario'] ?? '');
    $data_partida = trim($_POST['data_partida'] ?? '');
    $local = trim($_POST['local'] ?? '');
    $competicao = trim($_POST['competicao'] ?? 'JIFC');
    $resultado = trim($_POST['resultado'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');

    if (!empty($adversario) && !empty($data_partida)) {
        $stmt = $pdo->prepare("INSERT INTO partidas (usuario_id, adversario, data_partida, local, competicao, resultado, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $adversario,
            $data_partida,
            $local,
            $competicao,
            $resultado,
            $observacoes
        ]);

        $partida_id = $pdo->lastInsertId();
        $atleta_id = isset($_POST['selected_atleta_id']) ? (int)$_POST['selected_atleta_id'] : 0;

        if ($atleta_id > 0) {
            $minutos = (int)($_POST['minutos_' . $atleta_id] ?? 0);
            $gols = (int)($_POST['gols_' . $atleta_id] ?? 0);
            $assistencias = (int)($_POST['assistencias_' . $atleta_id] ?? 0);
            $cartoes_amarelos = (int)($_POST['cartoes_amarelos_' . $atleta_id] ?? 0);
            $cartoes_vermelhos = (int)($_POST['cartoes_vermelhos_' . $atleta_id] ?? 0);
            $avaliacao = isset($_POST['avaliacao_' . $atleta_id]) ? (float)$_POST['avaliacao_' . $atleta_id] : 8;
            $observacoes_atleta = trim($_POST['observacoes_atleta_' . $atleta_id] ?? '');

            $stmtEst = $pdo->prepare("INSERT INTO estatisticas_partidas (partida_id, atleta_id, minutos_jogados, gols, assistencias, cartoes_amarelos, cartoes_vermelhos, avaliacao, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtEst->execute([
                $partida_id,
                $atleta_id,
                $minutos,
                $gols,
                $assistencias,
                $cartoes_amarelos,
                $cartoes_vermelhos,
                $avaliacao,
                $observacoes_atleta
            ]);
        }
    }

    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escalação - JIFC Analytics</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="escalacao-page">
    <div class="escalacao-app">
        <header class="escalacao-topbar">
            <div class="escalacao-brand">
                <span class="escalacao-brand-logo">
                    <span class="escalacao-brand-logo-text">JIFC</span>
                </span>
                <span class="escalacao-brand-name">MEU TIME</span>
            </div>

            <h1 class="escalacao-title">ESCALAÇÃO</h1>

            <div class="escalacao-scheme">
                <span class="escalacao-scheme-label">ESQUEMA TÁTICO</span>
                <span class="escalacao-scheme-value">3-1</span>
            </div>
        </header>

        <main class="escalacao-content">
            <aside class="escalacao-sidebar">
                <div class="escalacao-sidebar-title">ELENCO</div>

                <section class="escalacao-list">
                    <div class="escalacao-list-label">TITULARES</div>

                    <?php foreach ($atletas as $atleta): ?>
                        <a class="escalacao-player-row <?= $selectedAtleta && $selectedAtleta['id'] == $atleta['id'] ? 'active' : '' ?>" href="escalacao.php?atleta_id=<?= (int)$atleta['id'] ?>">
                            <span class="escalacao-player-number"><?= htmlspecialchars($atleta['numero']) ?></span>
                            <span class="escalacao-player-name"><?= htmlspecialchars($atleta['nome']) ?></span>
                            <span class="escalacao-player-role"><?= htmlspecialchars($atleta['posicao']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </section>

                <section class="escalacao-list">
                    <div class="escalacao-list-label">SUPLENTES</div>
                    <div class="escalacao-player-row suppl">
                        <span class="escalacao-player-number">11</span>
                        <span class="escalacao-player-name">José</span>
                        <span class="escalacao-player-role">SUPLENTE</span>
                    </div>
                </section>
            </aside>

            <section class="escalacao-board">
                <section class="escalacao-board-inner">
                    <div class="escalacao-court">
                        <div class="quadra-lines">
                            <span class="line-horizontal"></span>
                            <span class="line-vertical"></span>
                            <span class="center-circle"></span>
                            <span class="area-left"></span>
                            <span class="area-right"></span>
                        </div>
                    </div>
                </section>

                <section class="escalacao-match-panel">
                    <div class="escalacao-match-head">
                        <span class="escalacao-match-title">CADASTRAR PARTIDA</span>
                    </div>

                    <form method="POST" class="escalacao-form">
                        <input type="hidden" name="action" value="add_match">
                        <input type="hidden" name="selected_atleta_id" value="<?= (int)($selectedAtleta['id'] ?? 0) ?>">

                        <div class="escalacao-form-grid">
                            <div class="escalacao-form-field">
                                <label for="adversario">ADVERSÁRIO</label>
                                <input type="text" id="adversario" name="adversario" class="form-control" required>
                            </div>

                            <div class="escalacao-form-field">
                                <label for="data_partida">DATA</label>
                                <input type="date" id="data_partida" name="data_partida" class="form-control" required>
                            </div>
                        </div>

                        <div class="escalacao-form-grid">
                            <div class="escalacao-form-field">
                                <label for="competicao">COMPETIÇÃO</label>
                                <input type="text" id="competicao" name="competicao" class="form-control" value="JIFC">
                            </div>

                            <div class="escalacao-form-field">
                                <label for="local">LOCAL</label>
                                <input type="text" id="local" name="local" class="form-control" value="Ginásio">
                            </div>
                        </div>

                        <div class="escalacao-form-grid">
                            <div class="escalacao-form-field">
                                <label for="resultado">RESULTADO</label>
                                <input type="text" id="resultado" name="resultado" class="form-control" placeholder="Vitória / Derrota / Empate">
                            </div>

                            <div class="escalacao-form-field">
                                <label for="observacoes">OBSERVAÇÕES</label>
                                <textarea id="observacoes" name="observacoes" class="form-control" rows="2"></textarea>
                            </div>
                        </div>

                        <?php if ($selectedAtleta): ?>
                            <section class="escalacao-player-stats">
                                <div class="escalacao-stats-title">ESTATÍSTICAS DO JOGADOR</div>
                                <div class="selected-player-card">
                                    <div class="selected-player-head">
                                        <span class="selected-player-number"><?= htmlspecialchars($selectedAtleta['numero']) ?></span>
                                        <span class="selected-player-name"><?= htmlspecialchars($selectedAtleta['nome']) ?></span>
                                        <span class="selected-player-role"><?= htmlspecialchars($selectedAtleta['posicao']) ?></span>
                                    </div>

                                    <div class="escalacao-player-stat-grid">
                                        <div class="escalacao-player-stat-field">
                                            <label for="minutos_<?= (int)$selectedAtleta['id'] ?>">Minutos</label>
                                            <input type="number" id="minutos_<?= (int)$selectedAtleta['id'] ?>" name="minutos_<?= (int)$selectedAtleta['id'] ?>" value="0" min="0" class="form-control small-input">
                                        </div>

                                        <div class="escalacao-player-stat-field">
                                            <label for="gols_<?= (int)$selectedAtleta['id'] ?>">Gols</label>
                                            <input type="number" id="gols_<?= (int)$selectedAtleta['id'] ?>" name="gols_<?= (int)$selectedAtleta['id'] ?>" value="0" min="0" class="form-control small-input">
                                        </div>

                                        <div class="escalacao-player-stat-field">
                                            <label for="assistencias_<?= (int)$selectedAtleta['id'] ?>">Assistências</label>
                                            <input type="number" id="assistencias_<?= (int)$selectedAtleta['id'] ?>" name="assistencias_<?= (int)$selectedAtleta['id'] ?>" value="0" min="0" class="form-control small-input">
                                        </div>

                                        <div class="escalacao-player-stat-field">
                                            <label for="cartoes_amarelos_<?= (int)$selectedAtleta['id'] ?>">Cartões amarelos</label>
                                            <input type="number" id="cartoes_amarelos_<?= (int)$selectedAtleta['id'] ?>" name="cartoes_amarelos_<?= (int)$selectedAtleta['id'] ?>" value="0" min="0" class="form-control small-input">
                                        </div>

                                        <div class="escalacao-player-stat-field">
                                            <label for="cartoes_vermelhos_<?= (int)$selectedAtleta['id'] ?>">Cartões vermelhos</label>
                                            <input type="number" id="cartoes_vermelhos_<?= (int)$selectedAtleta['id'] ?>" name="cartoes_vermelhos_<?= (int)$selectedAtleta['id'] ?>" value="0" min="0" class="form-control small-input">
                                        </div>

                                        <div class="escalacao-player-stat-field">
                                            <label for="avaliacao_<?= (int)$selectedAtleta['id'] ?>">Avaliação</label>
                                            <input type="number" id="avaliacao_<?= (int)$selectedAtleta['id'] ?>" name="avaliacao_<?= (int)$selectedAtleta['id'] ?>" value="8" min="0" max="10" step="0.1" class="form-control small-input">
                                        </div>

                                        <div class="escalacao-player-stat-field full-field">
                                            <label for="observacoes_atleta_<?= (int)$selectedAtleta['id'] ?>">Observações</label>
                                            <textarea id="observacoes_atleta_<?= (int)$selectedAtleta['id'] ?>" name="observacoes_atleta_<?= (int)$selectedAtleta['id'] ?>" class="form-control" rows="3"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        <?php else: ?>
                            <div class="escalacao-empty-stats">
                                <span>Selecione um jogador</span>
                            </div>
                        <?php endif; ?>

                        <div class="escalacao-actions">
                            <a href="index.php" class="btn-secondary">Voltar</a>
                            <button type="submit" class="btn-primary btn-match-save">SALVAR PARTIDA</button>
                        </div>
                    </form>
                </section>
            </section>
        </main>
    </div>
</body>
</html>
