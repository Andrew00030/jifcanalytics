<?php
session_start();
require_once 'db.php';

// Verifica se o ID foi passado
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$atleta_id = (int)$_GET['id'];

// Processar cadastro de nova lesão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cadastrar_lesao') {
    $tipo = trim($_POST['tipo']);
    $descricao = trim($_POST['descricao']);
    $data_inicio = $_POST['data_inicio'];
    $previsao_retorno = !empty($_POST['previsao_retorno']) ? $_POST['previsao_retorno'] : null;
    $status = $_POST['status'];

    if (!empty($tipo) && !empty($data_inicio)) {
        $stmt = $pdo->prepare("INSERT INTO lesoes (atleta_id, tipo, descricao, data_inicio, previsao_retorno, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$atleta_id, $tipo, $descricao, $data_inicio, $previsao_retorno, $status]);
        header("Location: atleta.php?id=" . $atleta_id);
        exit;
    }
}

// Buscar dados do atleta
$stmt = $pdo->prepare("SELECT * FROM atletas WHERE id = ?");
$stmt->execute([$atleta_id]);
$atleta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$atleta) {
    header('Location: index.php');
    exit;
}

// Buscar estatísticas do atleta
$stmtStats = $pdo->prepare("
    SELECT 
        COALESCE(SUM(gols), 0) as total_gols,
        COALESCE(SUM(assistencias), 0) as total_assistencias,
        COALESCE(SUM(cartoes_amarelos), 0) as cartoes_amarelos,
        COALESCE(SUM(cartoes_vermelhos), 0) as cartoes_vermelhos,
        COUNT(DISTINCT partida_id) as partidas
    FROM estatisticas 
    WHERE atleta_id = ?
");
$stmtStats->execute([$atleta_id]);
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

// Buscar histórico de lesões
$stmtLesoes = $pdo->prepare("SELECT * FROM lesoes WHERE atleta_id = ? ORDER BY data_inicio DESC");
$stmtLesoes->execute([$atleta_id]);
$lesoes = $stmtLesoes->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($atleta['nome']) ?> - JIFC Analytics</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .btn-voltar {
            display: inline-block;
            color: #8cef22;
            text-decoration: none;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 24px;
            background: #181818;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            border: 1px solid #2a2a2a;
        }

        .profile-foto-wrap {
            position: relative;
            width: 110px;
            height: 110px;
        }

        .profile-foto {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #8cef22;
        }

        .profile-badge-num {
            position: absolute;
            bottom: 0;
            right: 0;
            background: #8cef22;
            color: #000;
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 14px;
        }

        .profile-info h1 {
            margin: 0 0 6px 0;
            font-size: 28px;
            color: #fff;
        }

        .profile-info p {
            margin: 0;
            color: #aaa;
            font-size: 16px;
        }

        .grid-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }

        .card-stat {
            background: #181818;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #2a2a2a;
            text-align: center;
        }

        .card-stat span {
            display: block;
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            margin-bottom: 8px;
            letter-spacing: 1px;
        }

        .card-stat strong {
            font-size: 28px;
            color: #fff;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .section-title {
            font-size: 20px;
            color: #fff;
            margin: 0;
        }

        .btn-add {
            background: #8cef22;
            color: #000;
            border: none;
            padding: 10px 18px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
        }

        .lesoes-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 32px;
        }

        .lesao-card {
            background: #181818;
            border: 1px solid #2a2a2a;
            padding: 16px 20px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .lesao-card .status {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-tratamento {
            background: rgba(255, 77, 77, 0.2);
            color: #ff4d4d;
            border: 1px solid #ff4d4d;
        }

        .status-recuperado {
            background: rgba(140, 239, 34, 0.2);
            color: #8cef22;
            border: 1px solid #8cef22;
        }

        /* Modal styling */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-body {
            background: #181818;
            border: 1px solid #2a2a2a;
            padding: 24px;
            border-radius: 12px;
            width: 100%;
            max-width: 480px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #aaa;
            font-size: 14px;
        }

        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 10px;
            background: #121212;
            border: 1px solid #333;
            color: #fff;
            border-radius: 6px;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="btn-voltar">&larr; Voltar ao Dashboard</a>

        <!-- Informações do Atleta -->
        <div class="profile-header">
            <div class="profile-foto-wrap">
                <img src="<?= htmlspecialchars($atleta['foto_url'] ?: 'img/default.jpg') ?>" class="profile-foto" alt="Foto">
                <span class="profile-badge-num"><?= htmlspecialchars($atleta['numero']) ?></span>
            </div>
            <div class="profile-info">
                <h1><?= htmlspecialchars($atleta['nome']) ?></h1>
                <p><strong>Posição:</strong> <?= htmlspecialchars($atleta['posicao']) ?></p>
                <?php if (!empty($atleta['data_nascimento'])): ?>
                    <p><small>Data de Nasc.: <?= date('d/m/Y', strtotime($atleta['data_nascimento'])) ?></small></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Estatísticas do Atleta -->
        <h2 class="section-title" style="margin-bottom: 16px;">Estatísticas Gerais</h2>
        <div class="grid-stats">
            <div class="card-stat">
                <span>Partidas</span>
                <strong><?= $stats['partidas'] ?></strong>
            </div>
            <div class="card-stat">
                <span>Gols</span>
                <strong><?= $stats['total_gols'] ?></strong>
            </div>
            <div class="card-stat">
                <span>Assistências</span>
                <strong><?= $stats['total_assistencias'] ?></strong>
            </div>
            <div class="card-stat">
                <span>Cartões Amarelos</span>
                <strong><?= $stats['cartoes_amarelos'] ?></strong>
            </div>
            <div class="card-stat">
                <span>Cartões Vermelhos</span>
                <strong><?= $stats['cartoes_vermelhos'] ?></strong>
            </div>
        </div>

        <!-- Histórico e Cadastro de Lesões -->
        <div class="section-header">
            <h2 class="section-title">Histórico de Lesões</h2>
            <button class="btn-add" onclick="openModal()">+ Registrar Lesão</button>
        </div>

        <div class="lesoes-list">
            <?php if (empty($lesoes)): ?>
                <div class="lesao-card">
                    <p style="color: #777; margin: 0;">Nenhuma lesão registrada para este atleta.</p>
                </div>
            <?php else: ?>
                <?php foreach ($lesoes as $lesao): ?>
                    <div class="lesao-card">
                        <div>
                            <strong style="color: #fff; font-size: 16px; display: block;">
                                <?= htmlspecialchars($lesao['tipo']) ?>
                            </strong>
                            <span style="color: #aaa; font-size: 13px;">
                                Início: <?= date('d/m/Y', strtotime($lesao['data_inicio'])) ?>
                                <?= $lesao['previsao_retorno'] ? ' | Previsão: ' . date('d/m/Y', strtotime($lesao['previsao_retorno'])) : '' ?>
                            </span>
                            <?php if ($lesao['descricao']): ?>
                                <p style="color: #888; font-size: 13px; margin: 6px 0 0 0;">
                                    <?= htmlspecialchars($lesao['descricao']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="status <?= $lesao['status'] === 'Em Tratamento' ? 'status-tratamento' : 'status-recuperado' ?>">
                                <?= htmlspecialchars($lesao['status']) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para Cadastrar Lesão -->
    <div class="modal-overlay" id="modalLesao">
        <div class="modal-body">
            <h2 style="color: #fff; margin-top: 0;">Cadastrar Lesão</h2>
            <form action="" method="POST">
                <input type="hidden" name="action" value="cadastrar_lesao">

                <div class="form-group">
                    <label for="tipo">Tipo / Local da Lesão *</label>
                    <input type="text" id="tipo" name="tipo" placeholder="Ex: Entorse no tornozelo" required>
                </div>

                <div class="form-group">
                    <label for="data_inicio">Data da Lesão *</label>
                    <input type="date" id="data_inicio" name="data_inicio" required>
                </div>

                <div class="form-group">
                    <label for="previsao_retorno">Previsão de Retorno</label>
                    <input type="date" id="previsao_retorno" name="previsao_retorno">
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="Em Tratamento">Em Tratamento</option>
                        <option value="Recuperado">Recuperado</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="descricao">Observações / Descrição</label>
                    <textarea id="descricao" name="descricao" rows="3" placeholder="Detalhes do departamento médico..."></textarea>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn-add" style="background: #333; color: #fff;" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn-add">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('modalLesao').style.display = 'flex';
        }
        function closeModal() {
            document.getElementById('modalLesao').style.display = 'none';
        }
    </script>
</body>
</html>