<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT
        a.id,
        a.nome,
        a.numero,
        a.posicao,
        COUNT(DISTINCT ep.partida_id) AS total_jogos,
        COALESCE(SUM(ep.gols), 0) AS total_gols,
        COALESCE(SUM(ep.assistencias), 0) AS total_assistencias
    FROM atletas a
    LEFT JOIN estatisticas_partidas ep ON ep.atleta_id = a.id
    WHERE a.usuario_id = ?
    GROUP BY a.id, a.nome, a.numero, a.posicao
    ORDER BY total_gols DESC, total_assistencias DESC, a.nome ASC");
$stmt->execute([$userId]);
$atletasComEstatisticas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estatísticas do Elenco - JIFC Analytics</title>
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>
<body>
    <header class="header">
        <div>
            <h2>JIFC ANALYTICS</h2>
            <small style="color: #888;">Estatísticas do Elenco</small>
        </div>
        <a href="index.php?logout=1" style="color: #ef4444; text-decoration: none;">Sair</a>
    </header>

    <main class="content-section statistics-page">
        <div class="section-toolbar">
            <div>
                <h1 class="section-title-dashboard">ESTATÍSTICAS DO ELENCO</h1>
                <p class="statistics-intro">Ranking de desempenho dos atletas nas partidas cadastradas.</p>
            </div>
            <a href="index.php" class="btn-secondary">&larr; VOLTAR AO DASHBOARD</a>
        </div>

        <div class="statistics-table-card">
            <?php if (empty($atletasComEstatisticas)): ?>
                <p class="ranking-empty">Nenhum atleta cadastrado.</p>
            <?php else: ?>
                <div class="statistics-table-wrap">
                    <table class="statistics-table">
                        <colgroup>
                            <col class="statistics-col-athlete">
                            <col class="statistics-col-position">
                            <col class="statistics-col-games">
                            <col class="statistics-col-goals">
                            <col class="statistics-col-assists">
                            <col class="statistics-col-ga">
                        </colgroup>
                        <thead>
                            <tr>
                                <th><button type="button" class="statistics-sort" data-sort="text">NOME E NÚMERO <span aria-hidden="true">↕</span></button></th>
                                <th><button type="button" class="statistics-sort" data-sort="text">POSIÇÃO <span aria-hidden="true">↕</span></button></th>
                                <th><button type="button" class="statistics-sort" data-sort="number">JOGOS <span aria-hidden="true">↕</span></button></th>
                                <th><button type="button" class="statistics-sort" data-sort="number">GOLS <span aria-hidden="true">↕</span></button></th>
                                <th><button type="button" class="statistics-sort" data-sort="number">ASSISTÊNCIAS <span aria-hidden="true">↕</span></button></th>
                                <th><button type="button" class="statistics-sort" data-sort="number">G/A <span aria-hidden="true">↕</span></button></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($atletasComEstatisticas as $atleta): ?>
                                <?php $gols = (int)$atleta['total_gols']; ?>
                                <?php $assistencias = (int)$atleta['total_assistencias']; ?>
                                <tr>
                                    <td class="statistics-athlete">
                                        <strong><?= htmlspecialchars($atleta['nome']) ?></strong>
                                        <span>#<?= (int)$atleta['numero'] ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($atleta['posicao']) ?></td>
                                    <td><?= (int)$atleta['total_jogos'] ?></td>
                                    <td class="statistics-highlight"><?= $gols ?></td>
                                    <td class="statistics-highlight"><?= $assistencias ?></td>
                                    <td class="statistics-highlight"><?= $gols + $assistencias ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script>
        document.querySelectorAll('.statistics-sort').forEach((button) => {
            button.addEventListener('click', () => {
                const table = button.closest('table');
                const column = button.closest('th').cellIndex;
                const isNumber = button.dataset.sort === 'number';
                const direction = button.dataset.direction === 'desc' ? 'asc' : 'desc';
                const rows = Array.from(table.tBodies[0].rows);

                rows.sort((first, second) => {
                    const firstValue = first.cells[column].innerText.trim();
                    const secondValue = second.cells[column].innerText.trim();
                    const comparison = isNumber
                        ? Number(firstValue) - Number(secondValue)
                        : firstValue.localeCompare(secondValue, 'pt-BR');
                    return direction === 'asc' ? comparison : -comparison;
                });

                rows.forEach((row) => table.tBodies[0].appendChild(row));
                table.querySelectorAll('.statistics-sort').forEach((item) => {
                    item.dataset.direction = '';
                    item.querySelector('span').textContent = '↕';
                });
                button.dataset.direction = direction;
                button.querySelector('span').textContent = direction === 'desc' ? '↓' : '↑';
            });
        });
    </script>
</body>
</html>
