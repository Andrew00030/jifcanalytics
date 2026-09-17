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

// Remover lesão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_lesao') {
    $lesao_id = (int)($_POST['lesao_id'] ?? 0);

    if ($lesao_id > 0) {
        $stmt = $pdo->prepare("DELETE FROM lesoes WHERE id = ? AND atleta_id = ?");
        $stmt->execute([$lesao_id, $atleta_id]);
    }

    header("Location: atleta.php?id=" . $atleta_id);
    exit;
}

// Atualizar foto do atleta no perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_photo') {
    $foto_path = '';
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $extensao = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($extensao, $extensoesPermitidas)) {
            $nomeArquivo = 'atleta_' . $atleta_id . '_' . time() . '.' . $extensao;
            $destino = __DIR__ . '/uploads/atletas/' . $nomeArquivo;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
                $foto_path = 'uploads/atletas/' . $nomeArquivo;
            }
        }
    }

    if (!empty($foto_path)) {
        $stmt = $pdo->prepare("UPDATE atletas SET foto_url = ? WHERE id = ?");
        $stmt->execute([$foto_path, $atleta_id]);
    }

    header("Location: atleta.php?id=" . $atleta_id);
    exit;
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
    FROM estatisticas_partidas 
    WHERE atleta_id = ?
");
$stmtStats->execute([$atleta_id]);
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

// Buscar histórico de partidas do atleta
$stmtPartidasAtleta = $pdo->prepare("
    SELECT p.id, p.adversario, p.data_partida, p.local, p.competicao, p.resultado,
            ep.gols, ep.assistencias, ep.cartoes_amarelos, ep.cartoes_vermelhos
    FROM estatisticas_partidas ep
    JOIN partidas p ON p.id = ep.partida_id
    WHERE ep.atleta_id = ?
    ORDER BY p.data_partida DESC, p.id DESC
");
$stmtPartidasAtleta->execute([$atleta_id]);
$historicoPartidas = $stmtPartidasAtleta->fetchAll(PDO::FETCH_ASSOC);

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
    <link rel="stylesheet" href="atleta.css">
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
                <button class="btn-add" onclick="openPhotoModal()">Editar Foto</button>
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

        <div class="section-header">
            <h2 class="section-title">Histórico de Partidas</h2>
        </div>
        <div class="lesoes-list">
            <?php if (empty($historicoPartidas)): ?>
                <div class="lesao-card">
                    <p style="color: #777; margin: 0;">Nenhuma partida registrada para este atleta.</p>
                </div>
            <?php else: ?>
                <?php foreach ($historicoPartidas as $partida): ?>
                    <div class="lesao-card">
                        <div>
                            <strong style="color: #fff; font-size: 16px; display: block;">
                                <?= htmlspecialchars($partida['adversario']) ?>
                            </strong>
                            <span style="color: #aaa; font-size: 13px;">
                                <?= date('d/m/Y', strtotime($partida['data_partida'])) ?>
                                <?= $partida['competicao'] ? ' | ' . htmlspecialchars($partida['competicao']) : '' ?>
                                <?= $partida['local'] ? ' | ' . htmlspecialchars($partida['local']) : '' ?>
                                <?= $partida['resultado'] ? ' | Resultado: ' . htmlspecialchars($partida['resultado']) : '' ?>
                            </span>
                            <p style="color: #cfcfcf; font-size: 13px; margin: 8px 0 0 0;">
                                Gols: <?= (int)$partida['gols'] ?> |
                                Assistências: <?= (int)$partida['assistencias'] ?> |
                                Cartões: <?= (int)$partida['cartoes_amarelos'] ?> amarelo / <?= (int)$partida['cartoes_vermelhos'] ?> vermelho
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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
                        <div class="lesao-actions">
                            <span class="status <?= $lesao['status'] === 'Em Tratamento' ? 'status-tratamento' : 'status-recuperado' ?>">
                                <?= htmlspecialchars($lesao['status']) ?>
                            </span>
                            <form method="POST" class="delete-lesao-form" onsubmit="return confirm('Deseja remover esta lesão?');">
                                <input type="hidden" name="action" value="delete_lesao">
                                <input type="hidden" name="lesao_id" value="<?= $lesao['id'] ?>">
                                <button type="submit" class="delete-btn small-delete">×</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para atualizar foto -->
    <div class="modal-overlay" id="modalFoto">
        <div class="modal-body">
            <h2 style="color: #fff; margin-top: 0;">Atualizar Foto</h2>
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_photo">
                <div class="form-group">
                    <label for="foto">Escolher imagem</label>
                    <input type="file" id="foto" name="foto" accept="image/*" required>
                    <div class="preview-box">
                        <img src="" id="previewFoto" alt="Preview da foto" class="preview-image">
                    </div>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn-add" style="background: #333; color: #fff;" onclick="closePhotoModal()">Cancelar</button>
                    <button type="submit" class="btn-add">Salvar Foto</button>
                </div>
            </form>
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
        function openPhotoModal() {
            document.getElementById('modalFoto').style.display = 'flex';
        }
        function closePhotoModal() {
            document.getElementById('modalFoto').style.display = 'none';
        }

        function setupPreview(inputId, previewId) {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);

            if (!input || !preview) {
                return;
            }

            input.addEventListener('change', function () {
                if (!this.files || !this.files[0]) {
                    preview.src = '';
                    preview.style.display = 'none';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(this.files[0]);
            });
        }

        setupPreview('fotoCadastro', 'previewCadastro');
        setupPreview('foto', 'previewFoto');
    </script>
</body>
</html>
