<?php
$host = 'localhost';
$db   = 'jifc_analytics';
$user = 'root'; // Ajuste conforme seu ambiente
$pass = '';     // Ajuste conforme seu ambiente


try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

/**
 * Adds goalkeeper fields to databases created before those fields existed.
 */
function garantirCamposEstatisticasGoleiro(PDO $pdo): void {
    $colunas = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estatisticas_partidas'")
        ->fetchAll(PDO::FETCH_COLUMN);
    $colunasExistentes = array_flip($colunas);

    $migracoes = [
        'defesas' => 'ALTER TABLE estatisticas_partidas ADD COLUMN defesas INT NOT NULL DEFAULT 0 AFTER assistencias',
        'gols_sofridos' => 'ALTER TABLE estatisticas_partidas ADD COLUMN gols_sofridos INT NOT NULL DEFAULT 0 AFTER defesas',
        'goleiro_titular' => 'ALTER TABLE estatisticas_partidas ADD COLUMN goleiro_titular TINYINT(1) NOT NULL DEFAULT 0 AFTER gols_sofridos',
    ];

    foreach ($migracoes as $coluna => $sql) {
        if (!isset($colunasExistentes[$coluna])) {
            $pdo->exec($sql);
        }
    }
}

garantirCamposEstatisticasGoleiro($pdo);

/**
 * Regra disciplinar: vermelho, dois amarelos na mesma partida ou três
 * amarelos acumulados suspendem o atleta para a partida seguinte.
 */
function obterSuspensoesAtivas(PDO $pdo, int $usuarioId): array {
    $stmtAtletas = $pdo->prepare("SELECT id, nome FROM atletas WHERE usuario_id = ?");
    $stmtAtletas->execute([$usuarioId]);
    $status = [];
    foreach ($stmtAtletas->fetchAll() as $atleta) {
        $status[(int)$atleta['id']] = ['nome' => $atleta['nome'], 'amarelos' => 0, 'suspenso' => false, 'motivo' => ''];
    }

    $stmtPartidas = $pdo->prepare("SELECT id FROM partidas WHERE usuario_id = ? ORDER BY data_partida ASC, id ASC");
    $stmtPartidas->execute([$usuarioId]);
    $stmtCartoes = $pdo->prepare("SELECT ep.atleta_id, ep.cartoes_amarelos, ep.cartoes_vermelhos FROM estatisticas_partidas ep JOIN atletas a ON a.id = ep.atleta_id WHERE ep.partida_id = ? AND a.usuario_id = ?");

    foreach ($stmtPartidas->fetchAll() as $partida) {
        // A primeira partida posterior à punição cumpre a suspensão.
        foreach ($status as &$atletaStatus) {
            if ($atletaStatus['suspenso']) {
                $atletaStatus['suspenso'] = false;
                $atletaStatus['motivo'] = '';
            }
        }
        unset($atletaStatus);

        $stmtCartoes->execute([(int)$partida['id'], $usuarioId]);
        foreach ($stmtCartoes->fetchAll() as $cartoes) {
            $atletaId = (int)$cartoes['atleta_id'];
            if (!isset($status[$atletaId])) continue;
            $amarelos = max(0, (int)$cartoes['cartoes_amarelos']);
            $vermelhos = max(0, (int)$cartoes['cartoes_vermelhos']);

            if ($vermelhos >= 1 || $amarelos >= 2) {
                $status[$atletaId]['suspenso'] = true;
                $status[$atletaId]['motivo'] = $vermelhos >= 1 ? 'cartão vermelho' : 'dois cartões amarelos';
                $status[$atletaId]['amarelos'] = 0;
            } else {
                $status[$atletaId]['amarelos'] += $amarelos;
                if ($status[$atletaId]['amarelos'] >= 3) {
                    $status[$atletaId]['suspenso'] = true;
                    $status[$atletaId]['motivo'] = 'três cartões amarelos acumulados';
                    $status[$atletaId]['amarelos'] = 0;
                }
            }
        }
    }
    return $status;
}
?>
